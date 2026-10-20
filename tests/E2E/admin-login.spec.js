const { test, expect } = require('@playwright/test');

// 这个用例看的是未登录的登录页，不带 globalSetup 存下的登录态。
test.use({ storageState: { cookies: [], origins: [] } });

test('wp-admin login page loads', async ({ page }) => {
  await page.goto('/wp-login.php');
  await expect(page).toHaveTitle(/Log In|WordPress|登录/i);
  await expect(page.locator('#loginform')).toBeVisible();
});
