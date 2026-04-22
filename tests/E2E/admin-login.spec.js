const { test, expect } = require('@playwright/test');

test('wp-admin login page loads', async ({ page }) => {
  await page.goto('/wp-login.php');
  await expect(page).toHaveTitle(/Log In|WordPress/i);
  await expect(page.locator('#loginform')).toBeVisible();
});
