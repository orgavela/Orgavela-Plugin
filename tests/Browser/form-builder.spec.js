import { test, expect } from '@playwright/test';

test('form builder supports accessible keyboard reorder, saving and immutable publish', async ({ page }) => {
  test.setTimeout(90000);
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
  const upButton = page.getByRole('button', { name: 'Move field up: New field' });
  await upButton.focus();
  await expect(upButton).toBeFocused();
  await page.keyboard.press('Enter');
  await expect(page.locator('.uop-m3__form-fields li').first()).toContainText('New field');
  await page.getByRole('button', { name: 'Save draft' }).click();
  await expect(page.getByRole('status')).toContainText('Draft saved', { timeout: 30000 });
  await page.getByRole('button', { name: 'Publish immutable version' }).click();
  await expect(page.getByRole('status')).toContainText('Immutable version published', { timeout: 30000 });
  await page.goto('/wp-admin/plugins.php');
  await plugin.getByRole('link', { name: 'Deactivate UOP Core', exact: true }).click();
});
