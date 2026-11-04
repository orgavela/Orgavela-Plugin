import { test, expect } from '@playwright/test';
import { execFileSync } from 'node:child_process';

// Twenty real HTTPS requests from one authenticated WordPress session.
// Four PHP-CLI HTTP server workers dispatch separate REST transactions.
test.skip(process.env.UOP_HTTPS_E2E !== '1', 'Only disposable isolated HTTPS CI');
test.use({ baseURL: 'https://127.0.0.1:8443', ignoreHTTPSErrors: true });

function fixture(command) {
  if (process.env.UOP_M610_CI !== '1' || !process.env.GITHUB_ACTIONS ||
      !process.env.WP_ROOT || !process.env.WP_ADMIN_PASSWORD ||
      !process.env.UOP_M610_PORTAL_PAGE) {
    throw new Error('Guardian REST stress requires disposable GitHub WordPress.');
  }
  return execFileSync('wp', [
    'eval-file', 'tests/Fixtures/m610-guardian-quota-stress.php',
    '--path=' + process.env.WP_ROOT,
  ], {
    env: { ...process.env, UOP_GUARDIAN_QUOTA_ACTION: command },
    timeout: 30000, encoding: 'utf8', stdio: 'pipe',
  });
}

test('16 parallel authenticated HTTP guardian claims never bypass three-attempt quota', async ({ page }) => {
  const targets = JSON.parse(fixture('subjects'));
  expect(targets).toHaveLength(16);
  expect(new Set(targets).size).toBe(16);

  // Authentication and nonce are created by real WordPress, not mock headers.
  await page.goto('/wp-login.php');
  await page.locator('#user_login').fill('uop_ci_quota_parent');
  await page.locator('#user_pass').fill(process.env.WP_ADMIN_PASSWORD);
  await page.locator('#wp-submit').click();
  await page.waitForURL(url => url.pathname.startsWith('/wp-admin/'), {
    waitUntil: 'commit', timeout: 20000,
  });
  await expect(page.locator('#wpadminbar')).toBeVisible();
  await page.goto('/?page_id=' + process.env.UOP_M610_PORTAL_PAGE);
  await expect(page.locator('[data-uop-portal-root="portal"]')).toBeVisible();

  const nonce = await page.evaluate(() => globalThis.uopM6Portal?.nonce || '');
  expect(nonce.length).toBeGreaterThan(4);

  const results = await page.evaluate(async ({ targets, nonce }) => {
    const endpoint = '/?rest_route=%2Fuop%2Fv1%2Fme%2Fguardian-claims';
    // All requests are started before awaiting any one completion.
    return Promise.all(targets.map(async subject_id => {
      const response = await fetch(endpoint, {
        method: 'POST', credentials: 'same-origin', cache: 'no-store',
        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
        body: JSON.stringify({ subject_id }),
      });
      return { status: response.status, data: await response.json() };
    }));
  }, { targets, nonce });

  // A valid authenticated request returns an opaque receipt even when blocked.
  expect(results).toHaveLength(16);
  for (const result of results) {
    expect(result.status).toBe(202);
    expect(result.data).toEqual({ status: 'received' });
  }

  // More attempts cannot be admitted after the first committed three.
  const further = await page.evaluate(async ({ nonce, subject_id }) => {
    const response = await fetch('/?rest_route=%2Fuop%2Fv1%2Fme%2Fguardian-claims', {
      method: 'POST', credentials: 'same-origin', cache: 'no-store',
      headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
      body: JSON.stringify({ subject_id }),
    });
    return { status: response.status, data: await response.json() };
  }, { nonce, subject_id: targets[15] });
  expect(further).toEqual({ status: 202, data: { status: 'received' } });

  // Independent WP-CLI / MySQL session confirms persistent side effects.
  // Passing HTTP status alone cannot prove that the quota was enforced.
  expect(fixture('verify')).toContain('exactly 3 persisted requests, 0 grants');
});
