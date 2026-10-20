const os               = require( 'os' );
const path             = require( 'path' );
const { defineConfig } = require( '@playwright/test' );

// 本地 QA 环境用 wp-env 端口 8941（见 QA 交接的端口登记），其他环境用 WP_BASE_URL 覆盖.
const baseURL        = process.env.WP_BASE_URL || 'http://localhost:8941';
const storageState   = process.env.WPMIND_E2E_STORAGE_STATE || path.join( os.tmpdir(), 'wpmind-e2e-admin-state.json' );
const executablePath = process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH || undefined;

module.exports = defineConfig( {
	testDir: 'tests/E2E',
	timeout: 120 * 1000,
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
		launchOptions: executablePath ? { executablePath } : {},
		trace: 'retain-on-failure',
	},
} );
