# 设计说明：网络启用前已存在的子站按全新站处理（topic wpmind-network-activate-fresh-sites，I2）

时间：2026-10-21 01:55（UTC+8） 基线 main 94d1f03

## 根因
#27 只给「网络启用期间新建」的子站写 `wpmind_installed_version`。网络启用之前就存在的子站从没跑过 activate()，第一次加载时没有版本号，`SiteDefaults::maybe_upgrade_module_defaults()` 把它当成 0.12 之前的老站，7 个 legacy 模块（含 GEO）全部打开。

## 方案（主 bot 定）
不加网络级标记。判断全放在 `maybe_upgrade_module_defaults()`（plugins_loaded:1）：
1. 有 `wpmind_installed_version` → 直接返回（和现在一样，不查 options 表）。
2. 没有版本号 → 查一次 `SELECT option_name FROM {options} WHERE option_name LIKE 'wpmind\_%' LIMIT 1`（`$wpdb->esc_like('wpmind_') . '%'`）。
   - 有任何一行 → 老站：照旧补写 7 个 legacy 模块开关，写版本号。
   - 一行都没有 → 全新站：不写模块开关（走 manifest 默认值），只写版本号。
3. `_transient_wpmind_*`、`_transient_timeout_wpmind_*`、`_site_transient_*` 不以 `wpmind_` 开头，不会被当成「有设置」。
4. 拿不到 `$wpdb`（理论上不会）时按老站处理，宁可多开模块也不让老站丢行为。
5. 版本号写入后普通请求只做一次 `get_option('wpmind_installed_version')`（autoload=no 的 option 会走一次查询/缓存，和现在相同），不再跑 LIKE 查询。

单站同一套逻辑：0.11.5/0.12 单站只要有任何 wpmind 设置（模块开关、端点、请求超时等）就照旧升级；全新单站本来由 activate() 写版本号，不受影响。插件只在单个子站启用时，新建子站不写 option，保持 #27 的行为。

## 要改的文件
- `includes/Core/SiteDefaults.php`：新增 `has_wpmind_settings()`，在 `maybe_upgrade_module_defaults()` 里使用。
- `tests/Unit/SiteDefaultsTest.php`：老站用例补一个 wpmind 设置；测试内模拟 `$wpdb`（`esc_like`、`prepare`、`get_var` 对 `$GLOBALS['wpmind_test_options']` 做 LIKE 匹配，并计数查询次数）。
- 新增 `tests/Unit/SiteDefaultsSettingsProbeTest.php`：空子站、只残留 transient、有设置走升级、已有版本号不查表。
- QA 的 `tests/Unit/SiteDefaultsFreshDetectTest.php`（af45867）由 QA 自己加到 PR，我不碰。

## 兼容和升级
- 老站（有任何 `wpmind_` option）：行为不变。
- 网络启用前就存在、但从没保存过任何 WPMind 设置的子站：以前全开 7 个模块，现在按全新站（manifest 默认）。这些子站以前从没真正用过 WPMind，这是本次要修的行为。
- 已有版本号的站：不变。

## 风险
- 某个 0.11.5 老站如果只剩 transient、没有任何 wpmind_ option（理论上只有手工清过库才会这样），会被当成全新站，模块按默认值；可接受，写进 CHANGELOG 升级说明。
- LIKE 查询只在没有版本号时跑一次，写完版本号后不再出现；大网络下每个子站首次加载各一次。

## 验收
1. 单测：空子站 → 全新（无模块开关、写版本号）；只有 `_transient_wpmind_x` / `_transient_timeout_wpmind_x` → 全新；有 `wpmind_request_timeout` → 7 个模块补写；已有版本号 → 0 次查询；LIKE 参数是 `wpmind\_%`。
2. wp-env 多站点（8953/8954）：网络停用状态下建空子站 → 网络启用 → 访问该子站：只有版本号，模块全为默认（GEO 关）；有 wpmind 设置的老子站 → 7 个模块补写；主站不变。
3. composer check、gitleaks、Playwright 全绿；CI 6/6。
