const { request } = require('@playwright/test');
const { baseURL, storageState } = require('./env');

/*
 * 测试站点的状态读写。
 *
 * 以前用 `wp-env run cli wp eval`，但 wp-env 的 playground runtime 不支持 `run`，
 * 现在改走 tests/E2E/mu-plugins/wpmind-e2e-reset.php 提供的 wpmind-e2e/v1 REST 端点，
 * docker 和 playground 两种 runtime 都能用。用 globalSetup 存下的管理员登录态 + REST nonce 认证。
 */

const NS = '/?rest_route=/wpmind-e2e/v1';

let ctxPromise = null;

async function adminContext() {
	if (!ctxPromise) {
		ctxPromise = (async () => {
			const ctx = await request.newContext({ baseURL, storageState });
			const r = await ctx.get('/wp-admin/admin-ajax.php?action=rest-nonce');
			const nonce = (await r.text()).trim();
			if (!r.ok() || !/^[a-f0-9]{10}$/.test(nonce)) {
				throw new Error(`取不到 REST nonce（登录态失效？）：HTTP ${r.status()} ${nonce.slice(0, 200)}`);
			}
			return { ctx, nonce };
		})();
	}
	return ctxPromise;
}

/** 以管理员身份调用 wpmind-e2e/v1 端点，返回 JSON；非 2xx 直接抛错。 */
async function e2eRest(method, route, data) {
	const { ctx, nonce } = await adminContext();
	const r = await ctx.fetch(`${NS}${route}`, {
		method,
		headers: { 'X-WP-Nonce': nonce },
		data,
	});
	const body = await r.text();
	if (!r.ok()) {
		throw new Error(`${method} wpmind-e2e/v1${route} → HTTP ${r.status()}：${body.slice(0, 500)}`);
	}
	return JSON.parse(body);
}

const MODULE_IDS = [
	'analytics',
	'api-gateway',
	'auto-meta',
	'cost-control',
	'exact-cache',
	'geo',
	'media-intelligence',
];

// manifest 默认值（modules/*/module.json 的 default_enabled）。
const DEFAULT_ENABLED = {
	analytics: false,
	'api-gateway': false,
	'auto-meta': false,
	'cost-control': true,
	'exact-cache': true,
	geo: false,
	'media-intelligence': false,
};

/** 复位成「新装站点」：删掉所有模块开关选项、网关开关和 E2E 开关，写入当前版本号。 */
async function resetToFreshInstall() {
	return e2eRest('POST', '/reset');
}

async function getOptions(names) {
	return (await e2eRest('POST', '/options', { get: names })).options;
}

/** 写 option；值为 null 表示删除。 */
async function setOptions(map) {
	await e2eRest('POST', '/options', { set: map });
	return true;
}

/** 插件版本、basename、slug、wenpai_updater_override 结果、fake-updates mu-plugin 是否挂上。 */
async function pluginInfo() {
	return e2eRest('GET', '/info');
}

/** 删掉 update_plugins 缓存后跑一次 wp_update_plugins()。 */
async function forceUpdateCheck() {
	return e2eRest('POST', '/update-check');
}

module.exports = {
	e2eRest,
	MODULE_IDS,
	DEFAULT_ENABLED,
	resetToFreshInstall,
	getOptions,
	setOptions,
	pluginInfo,
	forceUpdateCheck,
};
