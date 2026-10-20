const { test, expect, request } = require("@playwright/test");
const { resetToFreshInstall, setOptions, getOptions } = require("./utils/wp-cli");
const { openModulesTab, toggleModule } = require("./utils/admin");
const { baseURL } = require("./utils/env");

/*
 * B22（PR #24）：开关 GEO / llms.txt / AI sitemap 后，/llms.txt 和 /ai-sitemap.xml
 * 下一次请求就正确（延迟 flush），不再 404 或被 redirect_canonical 301 成 /llms.txt/。
 * 假设站点用 wp-env 默认的漂亮固定链接；朴素固定链接只覆盖查询串形式。
 */

test.describe.configure({ mode: "serial" });

let anon;
test.beforeAll(async () => {
	anon = await request.newContext({ baseURL, storageState: { cookies: [], origins: [] } });
});
test.beforeEach(async () => {
	await resetToFreshInstall();
	await setOptions({ wpmind_llms_txt_enabled: null, wpmind_ai_sitemap_enabled: null, wpmind_geo_enabled: null });
});
test.afterAll(async () => {
	await resetToFreshInstall();
	await setOptions({ wpmind_ai_sitemap_enabled: null, wpmind_flush_rewrite_rules: null });
	await anon.dispose();
});

/** 第一个请求消费延迟 flush 标志，之后的请求才看到新规则。 */
async function warm() {
	await anon.get("/");
}
async function status(path) {
	const r = await anon.get(path, { maxRedirects: 0 });
	return r.status();
}

test("GEO 关闭时 /llms.txt 404；用 UI 开启后 200、text/plain、不 301；再关闭回到 404", async ({ page }) => {
	await warm();
	expect(await status("/llms.txt")).toBe(404);

	await openModulesTab(page);
	expect((await toggleModule(page, "geo")).data.enabled).toBe(true);
	await warm();
	const r = await anon.get("/llms.txt", { maxRedirects: 0 });
	expect(r.status()).toBe(200);
	expect(r.headers()["content-type"]).toContain("text/plain");
	expect(r.headers()["x-wpmind-llms-txt"]).toBe("1");
	expect(await r.text()).toMatch(/^# /);
	expect(await status("/llms.txt/")).toBe(200);
	expect(await status("/?wpmind_llms_txt=1")).toBe(200);

	await openModulesTab(page);
	expect((await toggleModule(page, "geo")).data.enabled).toBe(false);
	await warm();
	expect(await status("/llms.txt")).toBe(404);
	expect((await getOptions(["wpmind_flush_rewrite_rules"])).wpmind_flush_rewrite_rules).toBeFalsy();
});

test("llms.txt 子开关：关掉后 404，再开 200（GEO 保持开启）", async () => {
	await setOptions({ wpmind_module_geo_enabled: "1" });
	await warm();
	expect(await status("/llms.txt")).toBe(200);
	await setOptions({ wpmind_llms_txt_enabled: "0" });
	await warm();
	expect(await status("/llms.txt")).toBe(404);
	await setOptions({ wpmind_llms_txt_enabled: "1" });
	await warm();
	expect(await status("/llms.txt")).toBe(200);
});

test("AI sitemap：默认关 404，开启后 /ai-sitemap.xml 200 且是 XML，关闭后 404", async () => {
	await setOptions({ wpmind_module_geo_enabled: "1" });
	await warm();
	expect(await status("/ai-sitemap.xml")).toBe(404);
	await setOptions({ wpmind_ai_sitemap_enabled: "1" });
	await warm();
	const r = await anon.get("/ai-sitemap.xml", { maxRedirects: 0 });
	expect(r.status()).toBe(200);
	expect(await r.text()).toContain("<urlset");
	expect(await status("/?wpmind_ai_sitemap=1")).toBe(200);
	await setOptions({ wpmind_ai_sitemap_enabled: "0" });
	await warm();
	expect(await status("/ai-sitemap.xml")).toBe(404);
});
