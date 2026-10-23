const { defineConfig } = require( '@playwright/test' );

// 本地 QA 环境用 wp-env 端口 8941（见 QA 交接的端口登记），其他环境用 WP_BASE_URL 覆盖；默认值在 tests/E2E/utils/env.js.
const { baseURL, storageState } = require( './tests/E2E/utils/env' );
const executablePath            = process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH || undefined;

module.exports = defineConfig( {
	testDir: 'tests/E2E',
	// playground runtime（PHP wasm）在负载高的机器上一个页面要 10–20 秒，模块开关用例要好几次整页刷新.
	timeout: 300 * 1000,
	// 用例共享同一个 WordPress 站点和选项，必须串行.
	workers: 1,
	fullyParallel: false,
	retries: 0,
	reporter: [ [ 'list' ] ],
	globalSetup: require.resolve( './tests/E2E/global-setup.js' ),
	use: {
		baseURL,
		headless: true,
		storageState,
		// System/snap Chromium needs --no-sandbox on this host runner; bundling
		// Playwright's own browser is skipped (PLAYWRIGHT_SKIP_BROWSER_DOWNLOAD).
		launchOptions: executablePath
			? {
				executablePath,
				args: [ '--no-sandbox', '--disable-dev-shm-usage', '--disable-gpu' ],
			}
			: {},
		trace: 'retain-on-failure',
	},
} );
