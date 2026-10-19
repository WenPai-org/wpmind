const { execFileSync } = require('child_process');
const path = require('path');

const ROOT = path.resolve(__dirname, '..', '..', '..');
const MARK = '__WPMIND_E2E__';

/**
 * 在 wp-env 的 cli 容器里跑 WP-CLI。
 * 可用 WPMIND_E2E_WP_CLI 覆盖整条前缀（空格分隔），例如 "wp --path=/var/www/html"。
 */
function wp(args) {
	const prefix = process.env.WPMIND_E2E_WP_CLI
		? process.env.WPMIND_E2E_WP_CLI.split(' ')
		: ['npx', 'wp-env', 'run', 'cli', 'wp'];
	const [cmd, ...rest] = prefix;
	return execFileSync(cmd, [...rest, ...args], {
		cwd: ROOT,
		encoding: 'utf8',
		stdio: ['ignore', 'pipe', 'pipe'],
		env: process.env,
	});
}

/**
 * 跑一段 PHP，返回 PHP 用 echo 输出的 JSON（用标记包住，避开 wp-env 的提示行）。
 */
function wpEvalJson(php) {
	const code = `$__r = (function () { ${php} })(); echo "${MARK}" . wp_json_encode( $__r ) . "${MARK}";`;
	const out = wp(['eval', code]);
	const m = out.match(new RegExp(`${MARK}([\\s\\S]*?)${MARK}`));
	if (!m) {
		throw new Error(`wp eval 没有返回标记输出：\n${out}`);
	}
	return JSON.parse(m[1]);
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

/**
 * 复位成「新装站点」：删掉所有模块开关选项、网关开关和 E2E 开关，写入当前版本号。
 */
function resetToFreshInstall() {
	const ids = JSON.stringify(MODULE_IDS);
	return wpEvalJson(`
		foreach ( json_decode( '${ids}', true ) as $id ) { delete_option( "wpmind_module_{$id}_enabled" ); }
		delete_option( 'wpmind_gateway_enabled' );
		delete_option( 'wpmind_e2e_fake_update' );
		delete_option( 'wpmind_e2e_updater_override' );
		delete_option( 'wpmind_e2e_fake_update_hits' );
		delete_site_transient( 'update_plugins' );
		update_option( 'wpmind_installed_version', WPMIND_VERSION, false );
		return true;
	`);
}

function getOptions(names) {
	const list = JSON.stringify(names);
	return wpEvalJson(`
		$o = [];
		foreach ( json_decode( '${list}', true ) as $n ) { $o[ $n ] = get_option( $n, null ); }
		return $o;
	`);
}

function setOptions(map) {
	const json = JSON.stringify(map).replace(/'/g, "\\'");
	return wpEvalJson(`
		foreach ( json_decode( '${json}', true ) as $n => $v ) {
			if ( null === $v ) { delete_option( $n ); } else { update_option( $n, $v, false ); }
		}
		return true;
	`);
}

module.exports = { wp, wpEvalJson, MODULE_IDS, DEFAULT_ENABLED, resetToFreshInstall, getOptions, setOptions };
