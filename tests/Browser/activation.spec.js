import { test, expect } from '@playwright/test';
test('web activation and deactivation complete without PHP diagnostics', async ({ page }) => {
  // Authenticate through WordPress; its delayed login autofocus can interrupt
  // synthetic typing. The browser context shares this request's real cookies.
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
  expect(login.url()).toMatch(/\/wp-admin\//);
  await page.goto('/wp-admin/plugins.php');
  const plugin = page.locator('tr[data-slug="uop-core"]');
  await plugin.getByRole('link', { name: 'Activate UOP Core', exact: true }).click();
  await expect(plugin.getByRole('link', { name: 'Deactivate UOP Core', exact: true })).toBeVisible();
  await expect(page.locator('body')).not.toContainText(/Fatal error:|Warning:|Notice:|Failed checks:/);
  await plugin.getByRole('link', { name: 'Deactivate UOP Core', exact: true }).click();
  await expect(plugin.getByRole('link', { name: 'Activate UOP Core', exact: true })).toBeVisible();
});
