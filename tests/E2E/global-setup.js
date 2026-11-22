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
	page.setDefaultNavigationTimeout(60_000);
	await page.goto('/wp-login.php', { waitUntil: 'domcontentloaded' });

	// playground runtime auto-logs visitors in as admin and redirects away from
	// the login form; in that case the session is already usable.
	if ( /\/wp-admin\//.test( page.url() ) ) {
		await page.context().storageState({ path: storageState } );
		await browser.close();
		return;
	}

	await page.fill('#user_login', user);
	await page.fill('#user_pass', pass);
	await Promise.all([
		page.waitForURL(/\/wp-admin\//, { timeout: 60_000 }),
		page.click('#wp-submit'),
	]);
	await page.context().storageState({ path: storageState });
	await browser.close();
};
