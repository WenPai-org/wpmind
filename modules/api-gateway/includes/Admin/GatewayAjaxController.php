<?php
/**
 * Gateway AJAX Controller
 *
 * Handles admin AJAX requests for the API Gateway module.
 *
 * @package WPMind\Modules\ApiGateway\Admin
 * @since 1.0.0
 */

declare(strict_types=1);

namespace WPMind\Modules\ApiGateway\Admin;

use WPMind\Modules\ApiGateway\Auth\ApiKeyManager;
use WPMind\Modules\ApiGateway\Auth\ApiKeyRepository;

/**
 * Class GatewayAjaxController
 *
 * Registers and handles all AJAX actions for the API Gateway settings page.
 */
class GatewayAjaxController {

	/**
	 * Register AJAX hooks.
	 */
	public function register_hooks(): void {
		add_action( 'wp_ajax_wpmind_save_gateway_settings', [ $this, 'ajax_save_gateway_settings' ] );
		add_action( 'wp_ajax_wpmind_create_api_key', [ $this, 'ajax_create_api_key' ] );
		add_action( 'wp_ajax_wpmind_list_api_keys', [ $this, 'ajax_list_api_keys' ] );
		add_action( 'wp_ajax_wpmind_revoke_api_key', [ $this, 'ajax_revoke_api_key' ] );
	}

	/**
	 * Save gateway settings.
	 */
	public function ajax_save_gateway_settings(): void {
		check_ajax_referer( 'wpmind_ajax', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( '权限不足', 'wpmind' ) ] );
		}

		$enabled           = ! empty( $_POST['gateway_enabled'] );
		$sse_global_limit  = absint( wp_unslash( $_POST['sse_global_limit'] ?? 20 ) );
		$default_rpm       = absint( wp_unslash( $_POST['default_rpm'] ?? 60 ) );
		$default_tpm       = absint( wp_unslash( $_POST['default_tpm'] ?? 100000 ) );
		$max_body_bytes    = absint( wp_unslash( $_POST['max_body_bytes'] ?? 0 ) );
		$max_tokens_cap    = absint( wp_unslash( $_POST['max_tokens_cap'] ?? 0 ) );
		$log_prompts       = ! empty( $_POST['log_prompts'] );

		// Clamp values to reasonable ranges.
		$sse_global_limit = max( 1, min( 200, $sse_global_limit ) );
		$default_rpm      = max( 1, min( 10000, $default_rpm ) );
		$default_tpm      = max( 1000, min( 10000000, $default_tpm ) );
		$max_body_bytes   = min( 104857600, $max_body_bytes ); // 100 MB max.
		$max_tokens_cap   = min( 1000000, $max_tokens_cap );

		update_option( 'wpmind_gateway_enabled', $enabled ? '1' : '0' );
		update_option( 'wpmind_gateway_sse_global_limit', $sse_global_limit );
		update_option( 'wpmind_gateway_default_rpm', $default_rpm );
		update_option( 'wpmind_gateway_default_tpm', $default_tpm );
		update_option( 'wpmind_gateway_max_body_bytes', $max_body_bytes );
		update_option( 'wpmind_gateway_max_tokens_cap', $max_tokens_cap );
		update_option( 'wpmind_gateway_log_prompts', $log_prompts ? '1' : '0' );

		wp_send_json_success( [ 'message' => __( '网关设置已保存', 'wpmind' ) ] );
	}

	/**
	 * Create a new API key.
	 */
	public function ajax_create_api_key(): void {
		check_ajax_referer( 'wpmind_ajax', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( '权限不足', 'wpmind' ) ] );
		}

		$name              = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
		$rpm_limit         = absint( wp_unslash( $_POST['rpm_limit'] ?? 60 ) );
		$tpm_limit         = absint( wp_unslash( $_POST['tpm_limit'] ?? 100000 ) );
		$concurrency_limit = absint( wp_unslash( $_POST['concurrency_limit'] ?? 2 ) );
		$monthly_budget    = (float) ( $_POST['monthly_budget_usd'] ?? 0 );
		$ip_whitelist_raw  = sanitize_text_field( wp_unslash( $_POST['ip_whitelist'] ?? '' ) );
		$expires_at        = sanitize_text_field( wp_unslash( $_POST['expires_at'] ?? '' ) );

		if ( empty( $name ) ) {
			wp_send_json_error( [ 'message' => __( '请输入 Key 名称', 'wpmind' ) ] );
		}

		// Parse IP whitelist.
		$ip_whitelist = [];
		if ( ! empty( $ip_whitelist_raw ) ) {
			$ips = array_map( 'trim', explode( ',', $ip_whitelist_raw ) );
			foreach ( $ips as $ip ) {
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					$ip_whitelist[] = $ip;
				}
			}
		}

		// Clamp values.
		$rpm_limit         = max( 1, min( 10000, $rpm_limit ) );
		$tpm_limit         = max( 1000, min( 10000000, $tpm_limit ) );
		$concurrency_limit = max( 1, min( 100, $concurrency_limit ) );
		$monthly_budget    = max( 0.0, $monthly_budget );

		$attrs = [
			'name'              => $name,
			'owner_user_id'     => get_current_user_id(),
			'rpm_limit'         => $rpm_limit,
			'tpm_limit'         => $tpm_limit,
			'concurrency_limit' => $concurrency_limit,
			'monthly_budget_usd' => $monthly_budget,
		];

		if ( ! empty( $ip_whitelist ) ) {
			$attrs['ip_whitelist'] = $ip_whitelist;
		}

		if ( ! empty( $expires_at ) ) {
			$attrs['expires_at'] = gmdate( 'Y-m-d H:i:s', strtotime( $expires_at ) );
		}

		$result = ApiKeyManager::create_api_key( $attrs );

		wp_send_json_success( [
			'message'    => __( 'API Key 创建成功', 'wpmind' ),
			'raw_key'    => $result['raw_key'],
			'key_id'     => $result['key_id'],
			'key_prefix' => $result['key_prefix'],
		] );
	}

	/**
	 * List all API keys with usage data.
	 */
	public function ajax_list_api_keys(): void {
		check_ajax_referer( 'wpmind_ajax', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( '权限不足', 'wpmind' ) ] );
		}

		$keys = ApiKeyRepository::list_all_with_usage();

		wp_send_json_success( [ 'keys' => $keys ] );
	}

	/**
	 * Revoke an API key.
	 */
	public function ajax_revoke_api_key(): void {
		check_ajax_referer( 'wpmind_ajax', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( '权限不足', 'wpmind' ) ] );
		}

		$key_id = sanitize_text_field( wp_unslash( $_POST['key_id'] ?? '' ) );

		if ( empty( $key_id ) ) {
			wp_send_json_error( [ 'message' => __( '缺少 Key ID', 'wpmind' ) ] );
		}

		// Verify key exists.
		$row = ApiKeyRepository::find_by_key_id( $key_id );
		if ( $row === null ) {
			wp_send_json_error( [ 'message' => __( 'API Key 不存在', 'wpmind' ) ] );
		}

		if ( $row['status'] === 'revoked' ) {
			wp_send_json_error( [ 'message' => __( '该 Key 已被吊销', 'wpmind' ) ] );
		}

		ApiKeyRepository::revoke_key( $key_id, get_current_user_id(), 'admin_revoke' );

		wp_send_json_success( [ 'message' => __( 'API Key 已吊销', 'wpmind' ) ] );
	}
}
