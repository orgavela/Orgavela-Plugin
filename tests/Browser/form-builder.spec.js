import { test, expect } from '@playwright/test';

test('form builder supports accessible keyboard reorder, saving and immutable publish', async ({ page }) => {
  test.setTimeout(120000);
  const loginPage = await page.request.get('/wp-login.php');
  const login = await page.request.post('/wp-login.php', {
    form: {
      log: process.env.WP_ADMIN_USER || 'uop_test_admin',
      pwd: process.env.WP_ADMIN_PASSWORD,
      testcookie: '1',
      redirect_to: new URL('/wp-admin/', loginPage.url()).href,
    },
  });
  expect(login.ok()).toBeTruthy();
  await page.goto('/wp-admin/plugins.php');
  const plugin = page.locator('tr[data-slug="uop-core"]');
  const activate = plugin.getByRole('link', { name: 'Activate UOP Core', exact: true });
  if (await activate.isVisible()) await activate.click();
  await page.goto('/wp-admin/admin.php?page=uop-forms');
  await expect(page.getByRole('heading', { name: 'UOP Form Builder' })).toBeVisible();
  const keyInput = page.getByLabel('Form key');
  // On slower CI WordPress may paint the admin shell before wp-element mounts.
  // Reload once if the JS editor did not initialize; persistent mount failures still fail.
  try {
    await expect(keyInput).toBeVisible({ timeout: 8000 });
  } catch {
    await page.reload({ waitUntil: 'domcontentloaded' });
    await expect(keyInput).toBeVisible({ timeout: 10000 });
  }
  const unique = 'builder_' + Date.now();
  await keyInput.fill(unique);
  await page.getByLabel('Title').fill('Keyboard Form');
  await page.getByRole('button', { name: 'Create draft' }).click();
  await expect(page.getByRole('heading', { name: 'Keyboard Form' })).toBeVisible({ timeout: 30000 });
  await page.getByRole('button', { name: 'Add field' }).click();
  await expect(page.locator('.uop-m3__form-fields li')).toHaveCount(2);
  await expect(page.getByRole('button', { name: 'Remove: Name', exact: true })).toBeVisible();
  await expect(page.getByRole('button', { name: 'Remove: New field', exact: true })).toBeVisible();
  const upButton = page.getByRole('button', { name: 'Move field up: New field' });
  await page.getByRole('button', { name: 'Add field' }).click();
  await page.getByLabel('Label', { exact: true }).last().fill('Third field');
  await expect(page.locator('.uop-m3__form-fields li')).toHaveCount(3);
  // Reach the reorder button by Tab from the preceding field's last control.
  const previousControls = page.locator('.uop-m3__form-fields li').first().locator('input, select, textarea, button').filter({ visible: true });
  await previousControls.last().focus();
  await page.keyboard.press('Tab');
  await expect(upButton).toBeFocused();
  await page.keyboard.press('Enter');
  await expect(page.locator('.uop-m3__form-fields li').first()).toContainText('New field');
  const position = page.getByRole('combobox', { name: 'Move field to position: New field' });
  await expect(position).toBeFocused(); // up is now disabled: focus stays in moved field
  await position.selectOption('2');
  await expect(position).toBeFocused();
  await expect(page.locator('.uop-m3__form-fields li').last()).toContainText('New field');
  const moveUp = page.getByRole('button', { name: 'Move field up: New field' });
  await position.press('Shift+Tab');
  // Move via keyboard from the up button, then down to the disabled boundary.
  await moveUp.focus();
  await moveUp.press('Enter');
  await expect(moveUp).toBeFocused();
  await page.keyboard.press('Tab');
  await page.keyboard.press('Enter');
  await expect(position).toBeFocused();
  await expect(page.locator('.uop-m3__form-fields li').last()).toContainText('New field');
  await page.getByRole('button', { name: 'Save draft' }).click();
  await expect(page.getByRole('status')).toContainText('Draft saved', { timeout: 30000 });

  // Simulate another editor saving the revision while this browser is open:
  // the stale local draft must show a conflict instead of overwriting it.
  const formUuid = await page.getByLabel('Existing forms').inputValue();
  await page.evaluate(async uuid => {
    const fresh = await globalThis.wp.apiFetch({ path: '/uop/v1/forms/' + uuid });
    await globalThis.wp.apiFetch({
      path: '/uop/v1/forms/' + uuid + '/draft',
      method: 'PUT',
      data: { revision: fresh.revision, draft: { ...fresh.draft, fields: fresh.draft.fields.map((field, index) => index === 0 ? { ...field, label: 'Editor A label' } : field) } },
    });
  }, formUuid);
  await page.getByLabel('Label', { exact: true }).first().fill('Stale browser label');
  await page.getByRole('button', { name: 'Save draft' }).click();
  await expect(page.getByRole('alert')).toContainText('Draft changed', { timeout: 30000 });

  await expect(page.getByLabel('Label', { exact: true }).first()).toHaveValue('Stale browser label');
  await expect(page.getByRole('button', { name: 'Save draft' })).toBeDisabled();
  // A failed reload must leave B's input intact and allow retry.
  const formRequest = url => url.pathname.endsWith('/uop/v1/forms/' + formUuid) || url.searchParams.get('rest_route') === '/uop/v1/forms/' + formUuid;
  await page.route(formRequest, route => route.fulfill({
    status: 403, contentType: 'application/json', body: JSON.stringify({ code: 'rest_forbidden', message: 'Access denied', data: { status: 403 } }),
  }));
  await page.getByRole('button', { name: 'Load current version for comparison' }).click();
  await expect(page.getByRole('alert')).toContainText('Access denied');
  await expect(page.getByLabel('Label', { exact: true }).first()).toHaveValue('Stale browser label');
  await page.unroute(formRequest);
  await page.getByRole('button', { name: 'Load current version for comparison' }).click();
  await expect(page.getByLabel('Current server draft')).toHaveValue(/Editor A label/);
  await expect(page.getByLabel('Label', { exact: true }).first()).toHaveValue('Stale browser label');
  const serverLabel = () => page.evaluate(async uuid => {
    const data = await globalThis.wp.apiFetch({ path: '/uop/v1/forms/' + uuid });
    return data.draft.fields[0].label;
  }, formUuid);
  expect(await serverLabel()).toBe('Editor A label');
  await page.getByRole('button', { name: 'Keep local draft for review' }).click();
  expect(await serverLabel()).toBe('Editor A label');
  await page.getByRole('button', { name: 'Save draft' }).click();
  await expect(page.getByRole('status')).toContainText('Draft saved', { timeout: 30000 });
  expect(await serverLabel()).toBe('Stale browser label');
  await page.getByRole('button', { name: 'Publish immutable version' }).click();
  await expect(page.getByRole('status')).toContainText('Immutable version published', { timeout: 30000 });
  await page.setViewportSize({ width: 320, height: 720 });
  const panel = page.locator('.uop-m3__panel');
  await expect(panel).toBeVisible();
  const overflow = await panel.evaluate(element => element.scrollWidth - element.clientWidth);
  expect(overflow).toBeLessThanOrEqual(1);
  await page.goto('/wp-admin/plugins.php');
  await plugin.getByRole('link', { name: 'Deactivate UOP Core', exact: true }).click();
});
