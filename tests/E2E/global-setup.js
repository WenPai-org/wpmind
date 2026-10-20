const { chromium } = require('@playwright/test');

/**
 * 登录 wp-admin（wp-env 默认 admin/password），把登录态存成 storageState。
 */
module.exports = async (config) => {
	const { baseURL, storageState, launchOptions } = config.projects[0].use;
	const user = process.env.WP_USERNAME || 'admin';
	const pass = process.env.WP_PASSWORD || 'password';

	const browser = await chromium.launch(launchOptions || {});
	const page = await browser.newPage({ baseURL });
	await page.goto('/wp-login.php');
	await page.fill('#user_login', user);
	await page.fill('#user_pass', pass);
	await Promise.all([
		page.waitForURL(/\/wp-admin\//),
		page.click('#wp-submit'),
	]);
	await page.context().storageState({ path: storageState });
	await browser.close();
};
