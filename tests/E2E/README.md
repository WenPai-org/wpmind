# WPMind E2E（Playwright + wp-env）

只在测试环境用，`tests/` 不进发布包（release.yml、deploy.sh、scripts/build-candidate.py 都排除了 `tests`）。

## 本地跑

```bash
npm ci
export WP_ENV_HOME=$HOME/.wp-env-wpmind-qa WP_ENV_PORT=8941 WP_ENV_TESTS_PORT=8942
npx wp-env start
npx playwright test
```

- `WP_BASE_URL`：站点地址，默认 `http://localhost:8941`。
- `WP_USERNAME` / `WP_PASSWORD`：默认 wp-env 的 `admin` / `password`。
- `WPMIND_E2E_WP_CLI`：WP-CLI 前缀，默认 `npx wp-env run cli wp`。
- `PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH`：用系统 chromium 时设置。

## 文件

- `global-setup.js`：登录 wp-admin，存登录态。
- `modules-toggle.spec.js`：模块管理页的开关、核心模块、依赖、API Gateway 的 REST 注册。
- `upgrade.spec.js`：旧站迁移（`maybe_upgrade_module_defaults`）和更新通知（`WPMind_Updater`）。
- `mu-plugins/wpmind-e2e-fake-updates.php`：由 `.wp-env.json` 的 `mappings` 挂到 `wp-content/mu-plugins`，
  只在 option `wpmind_e2e_fake_update=1` 时用 `pre_http_request` 伪造 `updates.wenpai.net`，不连真实更新源，不用任何真实密钥。

每个用例开始前都会用 WP-CLI 把模块选项复位成「新装站点」，可以重复跑。
