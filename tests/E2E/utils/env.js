const os = require('os');
const path = require('path');

// playwright.config.js 和 utils 共用的默认值。
const baseURL = process.env.WP_BASE_URL || 'http://localhost:8941';
const storageState =
	process.env.WPMIND_E2E_STORAGE_STATE || path.join(os.tmpdir(), 'wpmind-e2e-admin-state.json');

module.exports = { baseURL, storageState };
