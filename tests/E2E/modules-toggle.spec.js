const { test, expect } = require('@playwright/test');
const { DEFAULT_ENABLED, MODULE_IDS, resetToFreshInstall, getOptions, setOptions } = require('./utils/wp-cli');
const { openModulesTab, card, navTab, toggleModule } = require('./utils/admin');

// 模块 id → 主导航里的页签 data-tab（templates/settings-page.php:800–835）。
const NAV_TAB = {
	analytics: 'analytics',
	'cost-control': 'budget',
	geo: 'geo',
	'api-gateway': 'api-gateway',
	'exact-cache': 'exact-cache',
	'media-intelligence': 'media-intelligence',
	'auto-meta': 'auto-meta',
};

test.describe.configure({ mode: 'serial' });

test.beforeEach(() => {
	resetToFreshInstall();
});

test.afterAll(() => {
	resetToFreshInstall();
});

test('新装站点：7 个模块卡片都渲染，默认状态与 manifest 一致', async ({ page }) => {
	await openModulesTab(page);
	await expect(page.locator('.wpmind-module-card')).toHaveCount(MODULE_IDS.length);

	for (const id of MODULE_IDS) {
		const c = card(page, id);
		await expect(c, id).toHaveCount(1);
		await expect(c, id).toHaveClass(DEFAULT_ENABLED[id] ? /\bis-enabled\b/ : /\bis-disabled\b/);
		if (id !== 'cost-control') {
			const sw = c.locator('input.wpmind-module-switch');
			if (DEFAULT_ENABLED[id]) {
				await expect(sw, id).toBeChecked();
			} else {
				await expect(sw, id).not.toBeChecked();
			}
		}
		// 导航页签跟随启用状态出现或隐藏。
		await expect(navTab(page, NAV_TAB[id]), id).toHaveCount(DEFAULT_ENABLED[id] ? 1 : 0);
	}
});

test('cost-control 是核心模块：有「核心」徽章和锁，没有开关', async ({ page }) => {
	await openModulesTab(page);
	const c = card(page, 'cost-control');
	await expect(c.locator('.wpmind-module-badge-core')).toBeVisible();
	await expect(c.locator('.wpmind-toggle-locked')).toBeVisible();
	await expect(c.locator('input.wpmind-module-switch')).toHaveCount(0);
	await expect(c.locator('.status-core')).toBeVisible();
});

test('开启 GEO：刷新后卡片启用、导航出现 GEO 页签、选项为 1；再关闭后页签消失、选项为 0', async ({ page }) => {
	await openModulesTab(page);
	await expect(navTab(page, 'geo')).toHaveCount(0);

	const on = await toggleModule(page, 'geo');
	expect(on.data.enabled).toBe(true);
	await openModulesTab(page);
	await expect(card(page, 'geo')).toHaveClass(/\bis-enabled\b/);
	await expect(card(page, 'geo').locator('input.wpmind-module-switch')).toBeChecked();
	await expect(card(page, 'geo').locator('.wpmind-module-settings-link')).toHaveCount(1);
	await expect(navTab(page, 'geo')).toHaveCount(1);
	expect(getOptions(['wpmind_module_geo_enabled']).wpmind_module_geo_enabled).toBe('1');

	const off = await toggleModule(page, 'geo');
	expect(off.data.enabled).toBe(false);
	await openModulesTab(page);
	await expect(card(page, 'geo')).toHaveClass(/\bis-disabled\b/);
	await expect(navTab(page, 'geo')).toHaveCount(0);
	expect(getOptions(['wpmind_module_geo_enabled']).wpmind_module_geo_enabled).toBe('0');
});

test('直接 POST 禁用 cost-control 被拒，选项没有被写成 0', async ({ page }) => {
	await openModulesTab(page);
	const res = await page.evaluate(async () => {
		const body = new URLSearchParams({
			action: 'wpmind_toggle_module',
			nonce: window.wpmindData.nonce,
			module_id: 'cost-control',
			enable: '0',
		});
		const r = await fetch(window.wpmindData.ajaxurl, { method: 'POST', body, credentials: 'same-origin' });
		return r.json();
	});
	expect(res.success).toBe(false);
	expect(res.data.message).toBe('此模块不能被禁用');
	// 新装站点 cost-control 靠 manifest 默认值启用，选项可能还没写过（null）；关键是没被写成 '0'。
	expect([null, '1']).toContain(getOptions(['wpmind_module_cost-control_enabled'])['wpmind_module_cost-control_enabled']);

	await openModulesTab(page);
	await expect(card(page, 'cost-control')).toHaveClass(/\bis-enabled\b/);
});

test('不带有效 nonce 的开关请求被拒，选项不变', async ({ page }) => {
	await openModulesTab(page);
	const status = await page.evaluate(async () => {
		const body = new URLSearchParams({
			action: 'wpmind_toggle_module',
			nonce: 'invalid',
			module_id: 'geo',
			enable: '1',
		});
		const r = await fetch(window.wpmindData.ajaxurl, { method: 'POST', body, credentials: 'same-origin' });
		return r.status;
	});
	expect(status).toBe(403);
	expect(getOptions(['wpmind_module_geo_enabled']).wpmind_module_geo_enabled).toBe(null);
});

test('analytics 依赖 cost-control：cost-control 被改成 0 也会被强制拉回 1，analytics 照常启用并出页签', async ({ page }) => {
	// 模拟库里被写成 0 的旧数据。
	setOptions({ 'wpmind_module_cost-control_enabled': '0' });

	await openModulesTab(page);
	// 核心模块强制启用（ModuleLoader::discover_modules）。
	await expect(card(page, 'cost-control')).toHaveClass(/\bis-enabled\b/);
	expect(getOptions(['wpmind_module_cost-control_enabled'])['wpmind_module_cost-control_enabled']).toBe('1');

	await toggleModule(page, 'analytics');
	await openModulesTab(page);
	await expect(card(page, 'analytics')).toHaveClass(/\bis-enabled\b/);
	await expect(navTab(page, 'analytics')).toHaveCount(1);
	await expect(navTab(page, 'budget')).toHaveCount(1);
	expect(getOptions(['wpmind_module_analytics_enabled']).wpmind_module_analytics_enabled).toBe('1');
});

test('API Gateway：模块关闭或网关开关关闭时不注册 mind/v1 REST 路由，两者都开才注册', async ({ request }) => {
	const routes = async () => {
		const r = await request.get('/?rest_route=/');
		expect(r.ok()).toBe(true);
		const idx = await r.json();
		return {
			ns: idx.namespaces || [],
			mind: Object.keys(idx.routes || {}).filter((k) => k.startsWith('/mind/v1')),
		};
	};

	// 默认：模块关。
	let s = await routes();
	expect(s.ns).not.toContain('mind/v1');
	expect(s.mind).toEqual([]);

	// 模块开，但网关总开关关（wpmind_gateway_enabled 默认 '0'）。
	setOptions({ 'wpmind_module_api-gateway_enabled': '1', wpmind_gateway_enabled: '0' });
	s = await routes();
	expect(s.ns).not.toContain('mind/v1');
	expect(s.mind).toEqual([]);

	// 正向对照：两者都开才有路由，证明上面的断言不是因为查错了前缀。
	setOptions({ wpmind_gateway_enabled: '1' });
	s = await routes();
	expect(s.ns).toContain('mind/v1');
	expect(s.mind.length).toBeGreaterThan(0);

	// 再关掉模块：路由消失。
	setOptions({ 'wpmind_module_api-gateway_enabled': '0' });
	s = await routes();
	expect(s.ns).not.toContain('mind/v1');
	expect(s.mind).toEqual([]);
});
