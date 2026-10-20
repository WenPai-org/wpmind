const { test, expect } = require('@playwright/test');
const { MODULE_IDS, resetToFreshInstall, getOptions, setOptions, wpEvalJson } = require('./utils/wp-cli');
const { openModulesTab, card } = require('./utils/admin');

test.describe.configure({ mode: 'serial' });

test.beforeEach(() => {
	resetToFreshInstall();
});

test.afterAll(() => {
	resetToFreshInstall();
});

const moduleOption = (id) => `wpmind_module_${id}_enabled`;

test.describe('旧站升级迁移（maybe_upgrade_module_defaults）', () => {
	test('没有 wpmind_installed_version 时：没设过的模块变 1，已设的保留，核心模块强开，写入版本号', async ({ page }) => {
		const version = wpEvalJson('return WPMIND_VERSION;');

		// 模拟 0.12 之前的旧站：没有版本号；geo 和 auto-meta 用户之前关过；cost-control 被写成 0。
		const preset = {};
		for (const id of MODULE_IDS) preset[moduleOption(id)] = null;
		preset.wpmind_installed_version = null;
		preset[moduleOption('geo')] = '0';
		preset[moduleOption('auto-meta')] = '0';
		preset[moduleOption('cost-control')] = '0';
		setOptions(preset);

		// 注意：任何一次 wp-cli 调用也会触发 plugins_loaded，所以中间不再用 wp-cli 读库，
		// 直接用一次后台页面请求触发迁移。
		// 一次后台请求触发 plugins_loaded → 迁移。
		await openModulesTab(page);

		const after = getOptions([
			'wpmind_installed_version',
			...MODULE_IDS.map(moduleOption),
		]);
		expect(after.wpmind_installed_version).toBe(version);
		for (const id of ['analytics', 'api-gateway', 'exact-cache', 'media-intelligence']) {
			expect(after[moduleOption(id)], id).toBe('1');
		}
		expect(after[moduleOption('geo')]).toBe('0');
		expect(after[moduleOption('auto-meta')]).toBe('0');
		expect(after[moduleOption('cost-control')]).toBe('1');

		// 迁移写库后页面状态（同一请求里 ModuleLoader 先于或后于迁移读取，刷新一次再看 UI）。
		await openModulesTab(page);
		for (const id of ['analytics', 'api-gateway', 'exact-cache', 'media-intelligence', 'cost-control']) {
			await expect(card(page, id), id).toHaveClass(/\bis-enabled\b/);
		}
		for (const id of ['geo', 'auto-meta']) {
			await expect(card(page, id), id).toHaveClass(/\bis-disabled\b/);
		}
	});

	test('已有 wpmind_installed_version（新装/已迁移）时不再改模块选项', async ({ page }) => {
		setOptions({ [moduleOption('geo')]: null, [moduleOption('analytics')]: null });
		await openModulesTab(page);
		const after = getOptions([moduleOption('geo'), moduleOption('analytics')]);
		expect(after[moduleOption('geo')]).toBe(null);
		expect(after[moduleOption('analytics')]).toBe(null);
		await expect(card(page, 'geo')).toHaveClass(/\bis-disabled\b/);
		await expect(card(page, 'analytics')).toHaveClass(/\bis-disabled\b/);
	});
});

test.describe('更新通知（WPMind_Updater，伪造 updates.wenpai.net）', () => {
	const info = () =>
		wpEvalJson(`
			return [
				'basename' => WPMIND_PLUGIN_BASENAME,
				'slug'     => dirname( WPMIND_PLUGIN_BASENAME ),
				'version'  => WPMIND_VERSION,
				'override' => apply_filters( 'wenpai_updater_override', false, dirname( WPMIND_PLUGIN_BASENAME ) ),
				'mu'       => function_exists( 'wpmind_e2e_json' ),
			];
		`);

	const forceCheck = () =>
		wpEvalJson(`
			delete_site_transient( 'update_plugins' );
			wp_update_plugins();
			$t = get_site_transient( 'update_plugins' );
			$r = [];
			foreach ( (array) ( $t->response ?? [] ) as $file => $u ) { $r[ $file ] = $u->new_version ?? ''; }
			return [ 'response' => $r, 'hits' => get_option( 'wpmind_e2e_fake_update_hits', [] ) ];
		`);

	test('插件列表出现 99.0.0 更新行，详情弹窗显示伪造的插件信息', async ({ page }) => {
		const p = info();
		expect(p.mu, 'tests/E2E/mu-plugins 没挂进 wp-env').toBe(true);
		expect(p.override, '测试环境里不应有别的插件接管 wenpai_updater_override').toBe(false);

		setOptions({ wpmind_e2e_fake_update: '1' });
		const check = forceCheck();
		expect(check.hits).toContain('/api/v1/update-check');
		expect(check.response[p.basename]).toBe('99.0.0');

		await page.goto('/wp-admin/plugins.php');
		const row = page.locator(`tr.plugin-update-tr[data-plugin="${p.basename}"]`);
		await expect(row).toBeVisible();
		await expect(row).toContainText('99.0.0');
		await expect(page.locator(`tr[data-plugin="${p.basename}"]`).first()).toHaveClass(/\bupdate\b/);

		const details = row.locator('a.open-plugin-details-modal');
		await expect(details).toHaveCount(1);
		await details.click();
		const frame = page.frameLocator('#TB_iframeContent');
		await expect(frame.locator('#plugin-information-title')).toContainText(/WPMind E2E Fake/);
		await expect(frame.locator('body')).toContainText('WPMIND-E2E-FAKE-DESCRIPTION');

		const hits = getOptions(['wpmind_e2e_fake_update_hits']).wpmind_e2e_fake_update_hits;
		expect(hits).toContain(`/api/v1/plugins/${p.slug}/info`);
	});

	test('wenpai_updater_override 返回 true 时，更新器不注册，不请求更新源，也没有更新行', async ({ page }) => {
		const p = info();
		setOptions({ wpmind_e2e_fake_update: '1', wpmind_e2e_updater_override: '1' });
		const check = forceCheck();
		expect(check.hits).toEqual([]);
		expect(check.response[p.basename]).toBeUndefined();

		await page.goto('/wp-admin/plugins.php');
		await expect(page.locator(`tr.plugin-update-tr[data-plugin="${p.basename}"]`)).toHaveCount(0);
		expect(getOptions(['wpmind_e2e_fake_update_hits']).wpmind_e2e_fake_update_hits).toBe(null);
	});
});
