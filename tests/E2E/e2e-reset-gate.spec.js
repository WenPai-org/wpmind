const { test, expect, request: pwRequest } = require('@playwright/test');
const { baseURL } = require('./utils/env');
const { e2eRest, getOptions } = require('./utils/wp-cli');

/*
 * 复位端点（tests/E2E/mu-plugins/wpmind-e2e-reset.php）的门槛：
 * 非管理员（未登录、管理员 cookie 不带 nonce、subscriber）一律 4xx；白名单外的 option 被拒。
 * 「未定义 WPMIND_E2E_RESET 时路由不存在」需要换一份不带该常量的 wp-env 配置，不能在同一站点里切换，
 * 手工验证记录见 QA 报告 e2e-ci-2026-10-19.md。
 */

test.describe.configure({ mode: 'serial' });

const ROUTE = '/?rest_route=/wpmind-e2e/v1/options';
// wp-env 的 playground runtime 用 `--login` 启动：没有这个 cookie 的访客会被自动登录成 admin（302）。
// 带上它才是真正的未登录访客；docker runtime 不认这个 cookie，没有影响。
const ANON_STATE = {
	cookies: [
		{
			name: 'playground_auto_login_already_happened',
			value: '1',
			domain: new URL(baseURL).hostname,
			path: '/',
			expires: -1,
			httpOnly: false,
			secure: false,
			sameSite: 'Lax',
		},
	],
	origins: [],
};

const SUB = { username: 'wpmind_e2e_subscriber', email: 'wpmind-e2e-sub@example.org' };

async function deleteSubscriber() {
	const users = await e2eCore('GET', `/wp/v2/users&search=${SUB.username}&context=edit`);
	for (const u of users) {
		if (u.username === SUB.username) {
			await e2eCore('DELETE', `/wp/v2/users/${u.id}&force=true&reassign=1`);
		}
	}
}

// 用管理员登录态调 Core REST（建/删 subscriber）。
let adminCtx;
async function e2eCore(method, route, data) {
	if (!adminCtx) {
		const ctx = await pwRequest.newContext({ baseURL, storageState: require('./utils/env').storageState });
		const nonce = (await (await ctx.get('/wp-admin/admin-ajax.php?action=rest-nonce')).text()).trim();
		adminCtx = { ctx, nonce };
	}
	const r = await adminCtx.ctx.fetch(`/?rest_route=${route}`, { method, headers: { 'X-WP-Nonce': adminCtx.nonce }, data });
	expect(r.ok(), `${method} ${route} → ${r.status()} ${await r.text()}`).toBe(true);
	return r.json();
}

test.afterAll(async () => {
	await deleteSubscriber();
});

test('管理员带 nonce 可以用；白名单外的 option 被拒（400），值不变', async () => {
	await expect(getOptions(['wpmind_e2e_probe'])).resolves.toEqual({ wpmind_e2e_probe: null });

	const blogname = (await e2eCore('GET', '/wp/v2/settings')).title;
	await expect(e2eRest('POST', '/options', { set: { blogname: 'hacked' } })).rejects.toThrow(/HTTP 400/);
	await expect(e2eRest('POST', '/options', { get: ['siteurl'] })).rejects.toThrow(/HTTP 400/);
	expect((await e2eCore('GET', '/wp/v2/settings')).title).toBe(blogname);
});

test('未登录、管理员 cookie 不带 nonce：401，选项不被写入', async () => {
	const anon = await pwRequest.newContext({ baseURL, storageState: ANON_STATE });
	const r1 = await anon.post(ROUTE, { data: { set: { wpmind_e2e_probe: 'x' } }, maxRedirects: 0 });
	expect(r1.status()).toBe(401);
	await anon.dispose();

	// 带管理员 cookie 但没有 X-WP-Nonce：WP 的 cookie 认证会当作未登录。
	const noNonce = await pwRequest.newContext({ baseURL, storageState: require('./utils/env').storageState });
	const r2 = await noNonce.post(ROUTE, { data: { set: { wpmind_e2e_probe: 'x' } }, maxRedirects: 0 });
	expect(r2.status()).toBe(401);
	await noNonce.dispose();

	expect((await getOptions(['wpmind_e2e_probe'])).wpmind_e2e_probe).toBe(null);
});

test('subscriber 登录并带自己的 nonce：403，选项不被写入', async ({ browser }) => {
	await deleteSubscriber();
	const password = `Sub-${Date.now()}-e2e!Pw`;
	await e2eCore('POST', '/wp/v2/users', { ...SUB, password, roles: ['subscriber'] });

	const ctx = await browser.newContext({ baseURL, storageState: ANON_STATE });
	const page = await ctx.newPage();
	await page.goto('/wp-login.php');
	await page.fill('#user_login', SUB.username);
	await page.fill('#user_pass', password);
	await Promise.all([page.waitForURL(/\/wp-admin\//), page.click('#wp-submit')]);

	const nonce = (await (await ctx.request.get('/wp-admin/admin-ajax.php?action=rest-nonce')).text()).trim();
	expect(nonce).toMatch(/^[a-f0-9]{10}$/);
	const r = await ctx.request.post(ROUTE, {
		headers: { 'X-WP-Nonce': nonce },
		data: { set: { wpmind_e2e_probe: 'x' } },
	});
	expect(r.status()).toBe(403);
	expect((await r.json()).code).toBe('rest_forbidden');
	await ctx.close();

	expect((await getOptions(['wpmind_e2e_probe'])).wpmind_e2e_probe).toBe(null);
});
