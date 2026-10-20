# WPMind E2E（Playwright + wp-env）

只在测试环境用。`tests/` 不进发布包：release.yml、deploy.sh、scripts/build-candidate.py 都排除了 `tests`。

## 本地跑：docker runtime

```bash
npm ci
export WP_ENV_PORT=8941 WP_ENV_TESTS_PORT=8942
npx wp-env start
npx playwright test
```

## 本地或 CI 跑：playground runtime（不需要 docker）

```bash
npm ci
git clone --depth=1 --branch 7.1.3 https://github.com/WordPress/WordPress.git /tmp/wp-core
jq --arg core /tmp/wp-core --arg p "$PWD" \
  '.core=$core | .plugins=[$p] | .mappings["wp-content/mu-plugins"]=($p+"/tests/E2E/mu-plugins")' \
  .wp-env.json > /tmp/wpmind-wp-env.json
WP_ENV_PORT=8941 npx wp-env start --runtime=playground --config /tmp/wpmind-wp-env.json
npx playwright test
npx wp-env stop --runtime=playground --config /tmp/wpmind-wp-env.json
```

- core 用本地目录，是因为 wp-env 用 node 下载 zip 时不走 `HTTPS_PROXY`。
- `package.json` 的 `overrides` 把 `@wp-playground/cli` 固定在 3.1.54。原因：
  - 3.1.12 会在页面刷新、请求中途断开时崩掉整个服务（`Controller is already closed`，3.1.15 修复）；
  - 3.1.56 起要求 node >= 24。

## 环境变量

- `WP_BASE_URL`：站点地址，默认 `http://localhost:8941`。
- `WP_USERNAME` / `WP_PASSWORD`：默认是 wp-env 的 `admin` / `password`。
- `WPMIND_E2E_STORAGE_STATE`：管理员登录态文件，默认放在系统临时目录。
- `PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH`：用系统 chromium 时设置，CI 里是 `/usr/sbin/chromium-browser`。

## 复位端点（替代 `wp-env run cli`）

wp-env 的 playground runtime 不支持 `run`。用例通过 `mu-plugins/wpmind-e2e-reset.php` 提供的 `wpmind-e2e/v1` REST 端点读写站点状态，docker 和 playground 两种 runtime 用的是同一套端点：

| 端点 | 用途 |
|---|---|
| `POST /reset` | 复位成新装站点 |
| `POST /options {get, set}` | 读写 option，值为 null 表示删除 |
| `GET /info` | 插件信息 |
| `POST /update-check` | 强制做一次插件更新检查 |

注册门槛，下面三条都满足才注册：

1. `wp_get_environment_type() === 'local'`，并且定义了 `WP_TESTS_DOMAIN`（两种 runtime 都会自动设置）；
2. 定义了常量 `WPMIND_E2E_RESET === true`（由 `.wp-env.json` 的 `config` 写入）；
3. 每个路由的 `permission_callback` 都要求 `manage_options`。

另外，option 只允许 `wpmind_` 前缀。`e2e-reset-gate.spec.js` 验证：未登录、管理员 cookie 不带 nonce、subscriber 都会被拒，白名单外的 option 返回 400。

注意：playground runtime 用 `--login` 启动，没有 `playground_auto_login_already_happened` cookie 的访客会被自动登录成 admin。所以这个 runtime 只能在本机或 CI 内部使用，不要把端口暴露出去。

## 文件

- `global-setup.js`：登录 wp-admin，保存登录态。
- `utils/wp-cli.js`：状态读写，走上面的 REST 端点。文件名沿用旧名。
- `utils/env.js`：baseURL 和 storageState 的默认值，`playwright.config.js` 和 utils 共用。
- `modules-toggle.spec.js`：模块开关、核心模块、模块依赖、API Gateway 的 REST 注册。
- `upgrade.spec.js`：旧站迁移（`maybe_upgrade_module_defaults`）和更新通知（`WPMind_Updater`）。
- `e2e-reset-gate.spec.js`：复位端点的权限门槛。
- `mu-plugins/wpmind-e2e-fake-updates.php`：只在 option `wpmind_e2e_fake_update=1` 时用 `pre_http_request` 伪造 `updates.wenpai.net`，并挡掉 api.wordpress.org。不连真实更新源，也不用任何真实密钥。
- `mu-plugins/wpmind-e2e-reset.php`：上面的复位端点。
