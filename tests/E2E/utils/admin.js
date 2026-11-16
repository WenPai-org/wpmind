const { expect } = require('@playwright/test');

/** 打开 WPMind 后台「模块」视图（4.0 壳：?page=wpmind&tab=modules）。 */
async function openModulesTab(page) {
	await page.goto('/wp-admin/admin.php?page=wpmind&tab=modules');
	await expect(page.locator('.wenpai-app .wpmind-module-list')).toBeVisible();
}

function card(page, id) {
	return page.locator(`.wpmind-module-card:has(input.wpmind-module-switch[data-module-id="${id}"])`);
}

/**
 * 模块卡片里的「设置」入口：只在模块启用且有 settings_tab 时渲染，
 * href 指向 ?page=wpmind&tab=module&module=<id>（templates/admin/views/modules.php）。
 */
function moduleLink(page, id) {
	return card(page, id).locator(`a[href*="module=${id}"]`);
}

/**
 * 点模块开关（kit .wenpai-switch：input 透明覆盖在 track 上，直接点 input）。
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
			card(page, id).locator(`input.wpmind-module-switch[data-module-id="${id}"]`).click(),
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

module.exports = { openModulesTab, card, moduleLink, toggleModule };
