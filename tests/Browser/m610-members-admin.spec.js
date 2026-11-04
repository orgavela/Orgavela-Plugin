import { test, expect } from '@playwright/test';
import { execFileSync } from 'node:child_process';

test.skip(process.env.UOP_HTTPS_E2E !== '1', 'Isolated disposable HTTPS WordPress only');
test.use({ baseURL: 'https://127.0.0.1:8443', ignoreHTTPSErrors: true });
test.describe.configure({ mode: 'serial' });

function verifyIsolated() {
  if (process.env.UOP_M610_CI !== '1' || !process.env.WP_ROOT ||
      !process.env.WP_ADMIN_PASSWORD || !process.env.UOP_M610_MEMBER_PAGE ||
      !process.env.UOP_M610_PORTAL_PAGE) {
    throw new Error('Refusing member browser test without complete disposable CI configuration.');
  }
}
function action(command) {
  verifyIsolated();
  execFileSync('wp', [
    'eval-file', 'tests/Fixtures/m610-members-helper.php',
    '--path=' + process.env.WP_ROOT,
  ], {
    env: { ...process.env, UOP_M610_MEMBER_ACTION: command },
    timeout: 30000, stdio: 'pipe', encoding: 'utf8',
  });
}
async function login(page, username) {
  verifyIsolated();
  // Retry only a still-present WordPress login form after a transport stall.
  // Credential errors and unexpected redirects always fail the security test.
  for (let attempt = 0; attempt < 2; attempt++) {
    await page.goto('/wp-login.php', { waitUntil: 'domcontentloaded', timeout: 30000 });
    await page.locator('#user_login').fill(username);
    await page.locator('#user_pass').fill(process.env.WP_ADMIN_PASSWORD);
    try {
      await page.locator('#wp-submit').click();
      await page.waitForURL(url => url.pathname.startsWith('/wp-admin/'), {
        waitUntil: 'domcontentloaded', timeout: 35000,
      });
      // Both a true authenticated redirect and the live admin toolbar
      // are required; the retry cannot turn a denied login green.
      await expect(page.locator('#wpadminbar')).toBeVisible({ timeout: 15000 });
      await page.waitForLoadState('load', { timeout: 20000 });
      return;
    } catch (error) {
      const rejection = await page.locator('#login_error').textContent().catch(() => '');
      if (rejection) {
        throw new Error('WordPress rejected disposable test login for ' + username + ': ' + rejection.trim());
      }
      const stillOnLogin = new URL(page.url()).pathname === '/wp-login.php' &&
        await page.locator('#loginform').isVisible().catch(() => false);
      if (!stillOnLogin || attempt === 1) {
        throw new Error('Disposable login did not complete for ' + username +
          '; URL=' + page.url() + '; attempt=' + (attempt + 1) + '; cause=' + String(error));
      }
    }
  }
}
async function memberForm(page) {
  await page.goto('/?page_id=' + process.env.UOP_M610_MEMBER_PAGE);
  const form = page.locator('form[data-uop-registration-form][data-uop-guest="0"]');
  await expect(form).toBeVisible();
  return form;
}
async function submit(form, subjectName, email, personName) {
  const selection = form.locator('[data-uop-subject]');
  await expect(selection).toBeEnabled();
  await selection.selectOption({ label: subjectName });
  await expect(form.locator('[data-uop-decision-capacity]')).toContainText(
    subjectName === 'Caregiver Minor' ? 'acting for another person' : 'registering yourself'
  );
  await form.locator('[data-uop-field="name"]').fill(personName);
  await form.locator('[data-uop-field="contact"]').fill(email);
  await form.getByRole('button', { name: 'Submit registration' }).click();
  await expect(form.locator('[data-uop-result]')).toContainText('Your registration was received', { timeout: 15000 });
}
async function portal(page) {
  await page.goto('/?page_id=' + process.env.UOP_M610_PORTAL_PAGE);
  const root = page.locator('[data-uop-portal-root="portal"]');
  await expect(root.locator('select.uop-portal__select')).toBeVisible();
  return root;
}

test('adult: real WordPress login, own Gutenberg submission, private portal; manager access is denied', async ({ page }) => {
  verifyIsolated();
  await login(page, 'uop_ci_adult');
  const form = await memberForm(page);
  const choices = form.locator('[data-uop-subject] option');
  await expect(choices).toHaveCount(2); // Placeholder plus this linked self, no strangers or dependents
  await expect(form.locator('[data-uop-subject]')).not.toContainText('Caregiver Minor');
  await submit(form, 'Adult Member', 'adult-ci@example.invalid', 'CI adult browser');
  const root = await portal(page);
  await expect(root.locator('select.uop-portal__select option')).toHaveCount(1);
  await expect(root.locator('.uop-portal__entry')).toContainText('submitted');
  const adminDenied = await page.goto('/wp-admin/admin.php?page=uop-registrations');
  expect(adminDenied.status()).toBeGreaterThanOrEqual(400);
  await expect(page.getByRole('heading', { name: 'Registrations', exact: true })).toHaveCount(0);
});

test('caregiver: no implicit access, explicit delegated minor submission, immediate revocation and stale REST denial', async ({ page }) => {
  verifyIsolated();
  await login(page, 'uop_ci_parent');
  let form = await memberForm(page);
  await expect(form.locator('[data-uop-subject] option')).toHaveCount(2);
  await expect(form.locator('[data-uop-subject]')).not.toContainText('Caregiver Minor');
  await expect(form.locator('[data-uop-subject]')).not.toContainText('Unrelated Member');
  // No browser shortcut that grants access: only trusted server-side delegation.
  action('grant-child');
  form = await memberForm(page);
  const selection = form.locator('[data-uop-subject]');
  await expect(selection).toContainText('Caregiver Minor');
  await expect(selection).not.toContainText('Unrelated Member');
  await submit(form, 'Caregiver Minor', 'parent-ci@example.invalid', 'CI dependent minor');
  const childUuid = await selection.inputValue();
  const root = await portal(page);
  const selector = root.locator('select.uop-portal__select');
  await selector.selectOption({ label: 'Caregiver Minor' });
  await expect(root.locator('.uop-portal__entry')).toContainText('submitted');
  await expect(root.getByRole('button', { name: /Cancel registration/ })).toBeVisible();

  // Relationship evidence is independent of the technical grant. Losing the
  // former must immediately conceal the dependent and deny stale REST access.
  action('revoke-relationship');
  await root.getByRole('button', { name: 'Refresh access' }).click();
  await expect(selector).not.toContainText('Caregiver Minor');
  const relationshipDenied = await page.evaluate(async id => {
    const nonce = globalThis.uopM6Portal?.nonce || '';
    const response = await fetch('/?rest_route=/uop/v1/me/portal/registrations&person_id=' + encodeURIComponent(id), {
      credentials: 'same-origin',
      headers: { 'X-WP-Nonce': nonce, Accept: 'application/json' },
      cache: 'no-store',
    });
    return response.status;
  }, childUuid);
  expect(relationshipDenied).toBe(404);

  // Re-enabling the verified relationship restores only the still-active,
  // explicit delegation; it never creates a new grant automatically.
  action('reactivate-relationship');
  await root.getByRole('button', { name: 'Refresh access' }).click();
  await expect(selector).toContainText('Caregiver Minor');
  await selector.selectOption({ label: 'Caregiver Minor' });
  await expect(root.locator('.uop-portal__entry')).toContainText('submitted');

  // Remove authorization while the caregiver still has an open browser session.
  action('revoke-child');
  await root.getByRole('button', { name: 'Refresh access' }).click();
  await expect(selector).not.toContainText('Caregiver Minor');
  await expect(selector).toContainText('Caregiver Member');
  await expect(root.locator('.uop-portal__entry')).toHaveCount(0);
  // A stale client cannot read the minor's registrations after live revocation.
  const status = await page.evaluate(async id => {
    const nonce = globalThis.uopM6Portal?.nonce || '';
    const result = await fetch('/?rest_route=/uop/v1/me/portal/registrations&person_id=' + encodeURIComponent(id), {
      credentials: 'same-origin',
      headers: { 'X-WP-Nonce': nonce, Accept: 'application/json' },
    });
    return result.status;
  }, childUuid);
  expect(status).toBe(404);
});

test('administrator: real control center, registration review, capacity acceptance, rejection and audit', async ({ page }) => {
  verifyIsolated();
  await login(page, 'uop_test_admin');
  await page.goto('/wp-admin/admin.php?page=uop-overview');
  await expect(page.getByRole('heading', { name: 'UOP Control Center' })).toBeVisible();
  await expect(page.locator('.uop-m6-console__nav a[href*="uop-registrations"]')).toBeVisible();

  await page.goto('/wp-admin/admin.php?page=uop-registrations');
  const list = page.getByRole('region', { name: 'Record list' });
  const detail = page.getByRole('region', { name: 'Record details' });
  await expect(list.getByRole('button', { name: /Adult Member/ })).toBeVisible();
  await list.getByRole('button', { name: /Adult Member/ }).click();
  await expect(detail.getByRole('button', { name: 'Mark for review' })).toBeVisible();
  page.on('dialog', dialog => dialog.accept());
  await detail.getByRole('button', { name: 'Mark for review' }).click();
  await expect(detail.locator('.uop-m6-status')).toHaveText('review', { timeout: 15000 });
  await expect(detail.getByRole('button', { name: 'Decide allocation' })).toBeVisible();
  await detail.getByRole('button', { name: 'Decide allocation' }).click();
  await expect(detail.locator('.uop-m6-status')).toHaveText('accepted', { timeout: 15000 });

  // WordPress's admin navigation may remain hovered after the previous
  // click and open a submenu over the next record. Dismiss that transient
  // platform menu by moving the pointer into the content region. We still
  // exercise the REAL pointer click and never use force:true.
  const currentViewport = page.viewportSize();
  await page.mouse.move((currentViewport?.width || 1280) - 24, 110);
  const childRecord = list.getByRole('button', { name: /Caregiver Minor/ });
  await childRecord.click();
  await expect(childRecord).toHaveAttribute('aria-pressed', 'true');
  await expect(detail.getByRole('button', { name: 'Reject', exact: true })).toBeVisible();
  await detail.getByRole('button', { name: 'Reject', exact: true }).click();
  await expect(detail.locator('.uop-m6-status')).toHaveText('rejected', { timeout: 15000 });

  await page.goto('/wp-admin/admin.php?page=uop-audit');
  await expect(page.getByRole('heading', { name: 'UOP Audit' })).toBeVisible();
  await expect(page.locator('.uop-m6-console__table')).toBeVisible();
  action('verify-states');
});

test('dependent minor: separate own WordPress login sees own rejected registration after caregiver access was revoked', async ({ page }) => {
  verifyIsolated();
  await login(page, 'uop_ci_minor');
  const root = await portal(page);
  const people = root.locator('select.uop-portal__select');
  await expect(people.locator('option')).toHaveCount(1);
  await expect(people).toContainText('Caregiver Minor');
  await expect(people).not.toContainText('Caregiver Member');
  await expect(root.locator('.uop-portal__entry')).toContainText('rejected');
  await expect(root.getByRole('button', { name: /Cancel registration/ })).toHaveCount(0);
  const denied = await page.goto('/wp-admin/admin.php?page=uop-registrations');
  expect(denied.status()).toBeGreaterThanOrEqual(400);
  await expect(page.getByRole('heading', { name: 'Registrations', exact: true })).toHaveCount(0);
});

test('expired WordPress session cannot read participant data or submit an authenticated registration', async ({ page, request }) => {
  verifyIsolated();
  await login(page, 'uop_ci_adult');
  const root = await portal(page);
  await expect(root.locator('select.uop-portal__select')).toContainText('Adult Member');
  const oldNonce = await page.evaluate(() => globalThis.uopM6Portal?.nonce || '');
  expect(oldNonce).not.toBe('');
  // Simulate session loss while the already-rendered portal remains open.
  // Deliberately clear only browser authentication cookies in disposable CI.
  await page.context().clearCookies();
  const staleRead = await page.evaluate(async nonce => {
    const response = await fetch('/?rest_route=%2Fuop%2Fv1%2Fme%2Fportal', {
      credentials: 'same-origin',
      headers: { 'X-WP-Nonce': nonce },
      cache: 'no-store',
    });
    return { status: response.status, body: await response.text() };
  }, oldNonce);
  expect([401, 403]).toContain(staleRead.status);
  expect(staleRead.body).not.toContain('Adult Member');
  expect(staleRead.body).not.toContain('adult-ci@example.invalid');

  // An unauthenticated command must not be accepted as a guest submission.
  const write = await request.post('/?rest_route=%2Fuop%2Fv1%2Fregistrations', {
    data: {
      person_id: '00000000-0000-4000-8000-000000000001',
      event_id: '00000000-0000-4000-8000-000000000002',
      command_id: '00000000-0000-4000-8000-000000000003',
      fields: { name: 'Attempt', contact: 'unauthorized@example.invalid' },
    },
    failOnStatusCode: false,
    headers: { 'Content-Type': 'application/json' },
  });
  expect(write.status()).toBe(401);
  const denied = await write.json();
  expect(denied.code).toBe('uop_unauthenticated');
  await page.goto('/?page_id=' + process.env.UOP_M610_PORTAL_PAGE);
  await expect(page.getByText('Sign in to access your participant portal.')).toBeVisible();
  await expect(page.locator('[data-uop-portal-root="portal"]')).toHaveCount(0);
});
