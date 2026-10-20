const { expect } = require('@playwright/test');

/** 打开 WPMind 设置页并切到「模块管理」页签。 */
async function openModulesTab(page) {
	await page.goto('/wp-admin/admin.php?page=wpmind#modules');
	const tab = page.locator('a.wpmind-tab[data-tab="modules"]');
	await expect(tab).toBeVisible();
	await tab.click();
	await expect(page.locator('.wpmind-modules-grid')).toBeVisible();
}

function card(page, id) {
	return page.locator(`.wpmind-module-card[data-module-id="${id}"]`);
}

function navTab(page, tabId) {
	return page.locator(`nav.wpmind-tab-list a.wpmind-tab[data-tab="${tabId}"]`);
}

/**
 * 点模块开关（真实的 checkbox 被样式藏起来了，点它外面的 label）。
 * 成功后前端会 location.reload()，刷新会让浏览器丢掉 AJAX 响应体，
 * 所以用 route 先把响应取到手再原样交给页面。
 */
async function toggleModule(page, id) {
	let captured = null;
	const handler = async (route) => {
		const req = route.request();
		if (req.method() !== 'POST' || !String(req.postData() || '').includes('action=wpmind_toggle_module')) {
			return route.continue();
		}
		const res = await route.fetch();
		captured = await res.json();
		return route.fulfill({ response: res });
	};
	await page.route('**/wp-admin/admin-ajax.php', handler);
	try {
		await Promise.all([
			page.waitForEvent('load'),
			card(page, id).locator('label.wpmind-switch').click(),
		]);
	} finally {
		await page.unroute('**/wp-admin/admin-ajax.php', handler);
	}
	expect(captured, 'wpmind_toggle_module 没有发出').not.toBeNull();
	expect(captured.success, JSON.stringify(captured)).toBe(true);
	expect(captured.data.reload).toBe(true);
	await page.waitForLoadState('domcontentloaded');
	return captured;
}

module.exports = { openModulesTab, card, navTab, toggleModule };
