<?php
/**
 * Suite-wide integration hooks for WenPai ecosystem (WPCY display, service status, quota items).
 *
 * @package WPMind\Admin
 * @since   0.14.0
 */

declare(strict_types=1);

namespace WPMind\Admin;

use WPMind\Failover\FailoverManager;
use WPMind\Modules\CostControl\BudgetManager;
use WPMind\Modules\CostControl\UsageTracker;

defined( 'ABSPATH' ) || exit;

/**
 * Integrates WPMind with WenPai Leaf (WPCY) white-label display, service status, and quota summary.
 */
final class SuiteIntegration {

	/**
	 * Whether filters have been registered.
	 *
	 * @var bool
	 */
	private static bool $initialized = false;

	/**
	 * Register suite filters idempotently.
	 */
	public static function init(): void {
		if ( self::$initialized || ! function_exists( 'add_filter' ) ) {
			return;
		}

		add_filter( 'wpcy_service_status', [ __CLASS__, 'filter_service_status' ], 10, 2 );
		add_filter( 'wpcy_quota_items', [ __CLASS__, 'filter_quota_items' ], 10, 2 );

		self::$initialized = true;
	}

	/**
	 * Reset initialization state for unit tests.
	 *
	 * @internal
	 */
	public static function reset_for_tests(): void {
		self::$initialized = false;
	}

	/**
	 * Resolve display brand and external links through `wenpai_plugin_display`.
	 *
	 * White-label mode activates when `whitelabel` is truthy, `brand` is overridden,
	 * or `WPCY_WHITELABEL` is truthy: clears `github_url` and hides default WenPai links
	 * unless explicitly replaced with custom URLs.
	 *
	 * @return array{
	 *     brand: string,
	 *     plugin_name: string,
	 *     menu_label: string,
	 *     help_url: string,
	 *     feedback_url: string,
	 *     docs_url: string,
	 *     github_url: string,
	 *     whitelabel: bool
	 * }
	 */
	public static function display(): array {
		$defaults = [
			'brand'        => __( '文派心思', 'wpmind' ),
			'plugin_name'  => 'WPMind',
			'menu_label'   => __( '心思', 'wpmind' ),
			'help_url'     => 'https://wpcy.com/mind',
			'feedback_url' => 'https://wpcy.com/support',
			'docs_url'     => 'https://wpcy.com/document',
			'github_url'   => 'https://github.com/WenPai-org/wpmind',
			'whitelabel'   => false,
		];

		$filtered = function_exists( 'apply_filters' )
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Shared WenPai suite hook.
			? apply_filters( 'wenpai_plugin_display', $defaults, 'wpmind' )
			: $defaults;

		if ( ! is_array( $filtered ) ) {
			$filtered = $defaults;
		}

		$merged = array_merge( $defaults, $filtered );

		$brand       = is_string( $merged['brand'] ) && '' !== trim( $merged['brand'] )
			? trim( $merged['brand'] )
			: $defaults['brand'];
		$plugin_name = is_string( $merged['plugin_name'] ) && '' !== trim( $merged['plugin_name'] )
			? trim( $merged['plugin_name'] )
			: $defaults['plugin_name'];
		$menu_label  = is_string( $merged['menu_label'] ) && '' !== trim( $merged['menu_label'] )
			? trim( $merged['menu_label'] )
			: $defaults['menu_label'];

		$constant_wl = defined( 'WPCY_WHITELABEL' ) && (bool) constant( 'WPCY_WHITELABEL' );
		$brand_diff  = $brand !== $defaults['brand'];
		$whitelabel  = ! empty( $merged['whitelabel'] ) || $brand_diff || $constant_wl;

		$help_url     = is_string( $merged['help_url'] ) ? trim( $merged['help_url'] ) : '';
		$feedback_url = is_string( $merged['feedback_url'] ) ? trim( $merged['feedback_url'] ) : '';
		$docs_url     = is_string( $merged['docs_url'] ) ? trim( $merged['docs_url'] ) : '';
		$github_url   = is_string( $merged['github_url'] ) ? trim( $merged['github_url'] ) : '';

		if ( $whitelabel ) {
			$github_url = '';
			if ( $help_url === $defaults['help_url'] ) {
				$help_url = '';
			}
			if ( $feedback_url === $defaults['feedback_url'] ) {
				$feedback_url = '';
			}
			if ( $docs_url === $defaults['docs_url'] ) {
				$docs_url = '';
			}
		}

		return [
			'brand'        => $brand,
			'plugin_name'  => $plugin_name,
			'menu_label'   => $menu_label,
			'help_url'     => $help_url,
			'feedback_url' => $feedback_url,
			'docs_url'     => $docs_url,
			'github_url'   => $github_url,
			'whitelabel'   => $whitelabel,
		];
	}

	/**
	 * Filter `wpcy_service_status`: populate `$status['ai']`.
	 *
	 * @param mixed $status   Existing status map.
	 * @param mixed $settings Optional WPCY settings snapshot.
	 * @return array<string, array<string, string>>
	 */
	public static function filter_service_status( $status, $settings = [] ): array {
		unset( $settings );
		if ( ! is_array( $status ) ) {
			$status = [];
		}

		$cloud         = self::cloud_status();
		$cloud_enabled = ! empty( $cloud['enabled'] );
		$cloud_ready   = $cloud_enabled && ! empty( $cloud['bound'] );

		$providers = [];
		if ( class_exists( FailoverManager::class ) ) {
			$providers = FailoverManager::instance()->get_status_summary();
		}

		$total   = count( $providers );
		$healthy = 0;
		$testing = 0;
		$down    = 0;

		foreach ( $providers as $provider ) {
			$state = (string) ( $provider['state'] ?? 'unknown' );
			if ( 'healthy' === $state || 'closed' === $state ) {
				++$healthy;
			} elseif ( 'half_open' === $state ) {
				++$testing;
			} else {
				++$down;
			}
		}

		$cloud_url    = AdminPage::url( [ 'tab' => 'cloud' ] );
		$services_url = AdminPage::url( [ 'tab' => 'services' ] );
		$overview_url = AdminPage::url( [ 'tab' => 'overview' ] );

		if ( 0 === $total && ! $cloud_enabled ) {
			$entry = [
				'summary'      => __( '尚未配置 AI 服务商或官方算力', 'wpmind' ),
				'pill'         => __( '待配置', 'wpmind' ),
				'state'        => 'warn',
				'action_label' => __( '配置服务', 'wpmind' ),
				'action_url'   => $cloud_url,
			];
		} elseif ( $down > 0 && 0 === $healthy && ! $cloud_ready ) {
			$entry = [
				'summary'      => sprintf(
					/* translators: %d: circuit-open provider count */
					__( '%d 个 AI 服务熔断中', 'wpmind' ),
					$down
				),
				'pill'         => __( '异常', 'wpmind' ),
				'state'        => 'bad',
				'action_label' => __( '查看状态', 'wpmind' ),
				'action_url'   => $services_url,
			];
		} elseif ( $down > 0 || $testing > 0 ) {
			$entry = [
				'summary'      => sprintf(
					/* translators: 1: healthy count, 2: total count, 3: degraded count */
					__( '%1$d/%2$d 服务在线 · %3$d 项异常', 'wpmind' ),
					$healthy,
					$total,
					$down + $testing
				),
				'pill'         => __( '降级', 'wpmind' ),
				'state'        => 'warn',
				'action_label' => __( '查看服务', 'wpmind' ),
				'action_url'   => $services_url,
			];
		} elseif ( $cloud_enabled && ! $cloud_ready && 0 === $healthy ) {
			$entry = [
				'summary'      => __( '官方算力已开启，待绑定站点通行证', 'wpmind' ),
				'pill'         => __( '待绑定', 'wpmind' ),
				'state'        => 'warn',
				'action_label' => __( '去绑定', 'wpmind' ),
				'action_url'   => $cloud_url,
			];
		} else {
			$online  = $healthy + ( $cloud_ready ? 1 : 0 );
			$summary = $cloud_ready && isset( $cloud['credits_balance'] )
				? sprintf(
					/* translators: 1: online service count, 2: remaining credits, 3: unit label */
					__( '%1$d 项服务在线 · 剩余 %2$s %3$s', 'wpmind' ),
					max( 1, $online ),
					self::format_int( (int) $cloud['credits_balance'] ),
					(string) ( $cloud['unit_label'] ?? __( '心思点数', 'wpmind' ) )
				)
				: sprintf(
					/* translators: %d: online service count */
					__( '%d 项 AI 服务运行正常', 'wpmind' ),
					max( 1, $online )
				);

			$entry = [
				'summary'      => $summary,
				'pill'         => __( '运行中', 'wpmind' ),
				'state'        => 'ok',
				'action_label' => __( '进入控制台', 'wpmind' ),
				'action_url'   => $overview_url,
			];
		}

		$status['ai'] = $entry;
		return $status;
	}

	/**
	 * Filter `wpcy_quota_items`: provide real WPMind AI quota entry.
	 *
	 * @param mixed $items   Existing quota items.
	 * @param mixed $context Optional caller context.
	 * @return array<int, array<string, mixed>>
	 */
	public static function filter_quota_items( $items, $context = [] ): array {
		unset( $context );
		if ( ! is_array( $items ) ) {
			$items = [];
		}

		$entry = self::build_quota_item();

		$replaced = false;
		foreach ( $items as $index => $existing ) {
			if ( ! is_array( $existing ) ) {
				continue;
			}
			$existing_id   = isset( $existing['id'] ) ? (string) $existing['id'] : '';
			$existing_name = isset( $existing['name'] ) ? (string) $existing['name'] : '';
			if ( 'wpmind' === $existing_id || $existing_name === $entry['name'] ) {
				$items[ $index ] = $entry;
				$replaced        = true;
				break;
			}
		}

		if ( ! $replaced ) {
			$items[] = $entry;
		}

		return array_values( $items );
	}

	/**
	 * Build the primary WPMind quota item for WPCY and WPMind overview.
	 *
	 * @return array<string, mixed>
	 */
	public static function build_quota_item(): array {
		$name          = __( '心思 AI 接口服务', 'wpmind' );
		$services_url  = AdminPage::url( [ 'tab' => 'cloud' ] );
		$analytics_url = AdminPage::url( [ 'tab' => 'analytics' ] );

		$cloud = self::cloud_status();
		if ( ! empty( $cloud['enabled'] ) ) {
			$unit    = (string) ( $cloud['unit_label'] ?? __( '心思点数', 'wpmind' ) );
			$balance = isset( $cloud['credits_balance'] )
				? (int) $cloud['credits_balance']
				: null;
			$used    = (int) ( $cloud['credits_used_month'] ?? 0 );
			$limit   = isset( $cloud['credits_limit_month'] )
				? (int) $cloud['credits_limit_month']
				: null;

			if ( null !== $limit && $limit > 0 ) {
				$pct   = min( 100, max( 0, (int) round( $used / $limit * 100 ) ) );
				$rem   = null !== $balance ? $balance : max( 0, $limit - $used );
				$usage = sprintf(
					/* translators: 1: remaining credits, 2: unit label */
					__( '剩余 %1$s %2$s', 'wpmind' ),
					self::format_int( $rem ),
					$unit
				);
				$note = sprintf(
					/* translators: 1: used credits, 2: monthly limit, 3: unit label */
					__( '本月已用 %1$s / %2$s %3$s', 'wpmind' ),
					self::format_int( $used ),
					self::format_int( $limit ),
					$unit
				);
				return [
					'id'           => 'wpmind',
					'name'         => $name,
					'usage'        => $usage,
					'percent'      => $pct,
					'note'         => $note,
					'action_label' => __( '管理算力', 'wpmind' ),
					'action_url'   => $services_url,
				];
			}

			if ( null !== $balance ) {
				return [
					'id'           => 'wpmind',
					'name'         => $name,
					'usage'        => sprintf(
						/* translators: 1: remaining credits, 2: unit label */
						__( '剩余 %1$s %2$s', 'wpmind' ),
						self::format_int( $balance ),
						$unit
					),
					'percent'      => null,
					'note'         => sprintf(
						/* translators: 1: used credits, 2: unit label */
						__( '官方算力已启用 · 本月已用 %1$s %2$s', 'wpmind' ),
						self::format_int( $used ),
						$unit
					),
					'action_label' => __( '管理算力', 'wpmind' ),
					'action_url'   => $services_url,
				];
			}

			return [
				'id'           => 'wpmind',
				'name'         => $name,
				'usage'        => __( '官方算力已开启', 'wpmind' ),
				'percent'      => null,
				'note'         => ! empty( $cloud['bound'] )
					? __( '首次请求后自动同步心思点数余额', 'wpmind' )
					: __( '绑定站点通行证后即可使用官方算力', 'wpmind' ),
				'action_label' => __( '管理算力', 'wpmind' ),
				'action_url'   => $services_url,
			];
		}

		$month = class_exists( UsageTracker::class ) ? UsageTracker::get_month_stats() : [];

		if ( class_exists( BudgetManager::class ) ) {
			$budget_settings = BudgetManager::instance()->get_settings();
			$global          = is_array( $budget_settings['global'] ?? null ) ? $budget_settings['global'] : [];
			$limit_usd       = (float) ( $global['monthly_limit_usd'] ?? 0 );
			$limit_cny       = (float) ( $global['monthly_limit_cny'] ?? 0 );

			if ( $limit_usd > 0 ) {
				$used_usd = (float) ( $month['cost_usd'] ?? 0 );
				$pct      = min( 100, max( 0, (int) round( $used_usd / $limit_usd * 100 ) ) );
				return [
					'id'           => 'wpmind',
					'name'         => $name,
					'usage'        => sprintf( '$%s / $%s', number_format( $used_usd, 2 ), rtrim( rtrim( number_format( $limit_usd, 2 ), '0' ), '.' ) ),
					'percent'      => $pct,
					'note'         => __( '本月自有密钥费用占比', 'wpmind' ),
					'action_label' => __( '预算管理', 'wpmind' ),
					'action_url'   => $analytics_url,
				];
			}

			if ( $limit_cny > 0 ) {
				$used_cny = (float) ( $month['cost_cny'] ?? 0 );
				$pct      = min( 100, max( 0, (int) round( $used_cny / $limit_cny * 100 ) ) );
				return [
					'id'           => 'wpmind',
					'name'         => $name,
					'usage'        => sprintf( '¥%s / ¥%s', number_format( $used_cny, 2 ), rtrim( rtrim( number_format( $limit_cny, 2 ), '0' ), '.' ) ),
					'percent'      => $pct,
					'note'         => __( '本月自有密钥费用占比', 'wpmind' ),
					'action_label' => __( '预算管理', 'wpmind' ),
					'action_url'   => $analytics_url,
				];
			}
		}

		$requests = (int) ( $month['requests'] ?? 0 );
		return [
			'id'           => 'wpmind',
			'name'         => $name,
			'usage'        => sprintf(
				/* translators: %s: formatted monthly request count */
				__( '本月 %s 次调用', 'wpmind' ),
				self::format_int( $requests )
			),
			'percent'      => null,
			'note'         => __( '使用自有密钥 · 未设月度预算上限', 'wpmind' ),
			'action_label' => __( '查看分析', 'wpmind' ),
			'action_url'   => $analytics_url,
		];
	}

	/**
	 * Safely read WenpaiCloud status with class/method guards.
	 *
	 * @return array<string, mixed>
	 */
	public static function cloud_status(): array {
		$defaults = [
			'enabled'             => get_option( 'wpmind_cloud_enabled', '0' ) === '1',
			'mode'                => (string) get_option( 'wpmind_cloud_mode', 'primary' ),
			'bound'               => false,
			'binding_source'      => 'none',
			'managed_by_suite'    => false,
			'site_uuid'           => '',
			'token_fingerprint'   => '',
			'credits_balance'     => null,
			'credits_used_month'  => 0,
			'credits_limit_month' => null,
			'starter_credits'     => 500,
			'unit_label'          => __( '心思点数', 'wpmind' ),
			'base_url'            => 'https://mind-api.wenpai.net/v1',
			'bind_url'            => 'https://wenpai.net/connect/mind',
			'topup_url'           => 'https://wenpai.net/mind-credits',
		];

		$cloud_class = '\\WPMind\\Provider\\WenpaiCloud';
		// @phpstan-ignore function.alreadyNarrowedType
		if ( class_exists( $cloud_class ) && method_exists( $cloud_class, 'get_status' ) ) {
			$status = $cloud_class::get_status();
			// @phpstan-ignore function.alreadyNarrowedType
			if ( is_array( $status ) ) {
				return array_merge( $defaults, $status );
			}
		}

		return $defaults;
	}

	/**
	 * Check whether a provider is locked by site configuration (with class/method guard).
	 *
	 * @param string $provider_id Provider identifier.
	 * @return bool
	 */
	public static function is_provider_locked( string $provider_id ): bool {
		$cloud_class = '\\WPMind\\Provider\\WenpaiCloud';
		// @phpstan-ignore function.alreadyNarrowedType
		if ( class_exists( $cloud_class ) && method_exists( $cloud_class, 'is_provider_locked' ) ) {
			return (bool) $cloud_class::is_provider_locked( $provider_id );
		}
		return false;
	}

	/**
	 * Detect installed & active WenPai ecosystem plugins that have enabled WPMind integration.
	 *
	 * Ecosystem plugins may also self-register or enrich entries via the
	 * `wpmind_ecosystem_integrations` filter. Only entries with `active => true` are returned.
	 *
	 * @return array<int, array{
	 *     slug: string,
	 *     name: string,
	 *     desc: string,
	 *     feature: string,
	 *     icon: string,
	 *     version: string,
	 *     settings_url: string,
	 *     requests: int,
	 *     tokens: int,
	 *     active: bool
	 * }>
	 */
	public static function ecosystem_integrations(): array {
		$by_caller = [];
		if ( class_exists( UsageTracker::class ) ) {
			$ctx_stats = UsageTracker::get_context_stats();
			foreach ( $ctx_stats as $ctx_key => $row ) {
				$caller = (string) ( $row['caller_plugin'] ?? '' );
				if ( '' === $caller ) {
					$caller = (string) $ctx_key;
				}
				if ( '' === $caller ) {
					continue;
				}
				if ( ! isset( $by_caller[ $caller ] ) ) {
					$by_caller[ $caller ] = [
						'requests' => 0,
						'tokens'   => 0,
					];
				}
				$by_caller[ $caller ]['requests'] += (int) ( $row['requests'] ?? 0 );
				$by_caller[ $caller ]['tokens']   += (int) ( $row['input_tokens'] ?? 0 ) + (int) ( $row['output_tokens'] ?? 0 );
			}
		}

		$menu_url = static function ( string $slug ): string {
			if ( class_exists( 'Wenpai_Admin_Menu', false ) ) {
				return (string) \Wenpai_Admin_Menu::url( $slug );
			}
			return '';
		};

		$items = [];

		// 1. 文派别名（WPSlug）.
		if ( defined( 'WPSLUG_VERSION' ) || class_exists( 'WPSlug_Settings', false ) ) {
			$opts      = get_option( 'wpslug_options', [] );
			$opts      = is_array( $opts ) ? $opts : [];
			$mode      = (string) ( $opts['conversion_mode'] ?? 'pinyin' );
			$service   = (string) ( $opts['translation_service'] ?? 'none' );
			$pt_modes  = is_array( $opts['post_type_modes'] ?? null ) ? $opts['post_type_modes'] : [];
			$features  = [];
			$ai_modes  = [ 'semantic_pinyin', 'seo_slug', 'ai' ];
			$has_pt_ai = false;
			foreach ( $pt_modes as $pt_mode ) {
				if ( in_array( (string) $pt_mode, $ai_modes, true ) ) {
					$has_pt_ai = true;
					break;
				}
			}
			if ( in_array( $mode, $ai_modes, true ) || $has_pt_ai ) {
				$features[] = __( '语义拼音', 'wpmind' );
			}
			if ( 'wpmind' === $service ) {
				$features[] = __( 'AI 翻译', 'wpmind' );
			}
			$slug_stats = $by_caller['wpslug'] ?? [
				'requests' => 0,
				'tokens'   => 0,
			];
			if ( ! empty( $features ) || $slug_stats['requests'] > 0 ) {
				$items['wpslug'] = [
					'slug'         => 'wpslug',
					'name'         => __( '文派别名（WPSlug）', 'wpmind' ),
					'desc'         => __( '文章、分类与媒体别名转换已接入文派心思，复用站点路由与预算。', 'wpmind' ),
					'feature'      => ! empty( $features ) ? implode( ' / ', $features ) : __( 'AI 别名转换', 'wpmind' ),
					'icon'         => 'globe',
					'version'      => defined( 'WPSLUG_VERSION' ) ? (string) constant( 'WPSLUG_VERSION' ) : '',
					'settings_url' => $menu_url( 'wpslug' ),
					'requests'     => (int) $slug_stats['requests'],
					'tokens'       => (int) $slug_stats['tokens'],
					'active'       => true,
				];
			}
		}

		// 2. 笔笙 HTML（Bisheng HTML）.
		if ( defined( 'BISHENG_HTML_VERSION' ) || class_exists( 'BishengHtml_Settings', false ) ) {
			$bh_cb    = [ 'BishengHtml_AI', 'wpmind_available' ];
			$bh_ready = is_callable( $bh_cb )
				? (bool) call_user_func( $bh_cb )
				: ( function_exists( 'wpmind_is_available' ) && wpmind_is_available() );
			$bh_req   = (int) ( $by_caller['bisheng']['requests'] ?? 0 ) + (int) ( $by_caller['bisheng-html']['requests'] ?? 0 );
			$bh_tok   = (int) ( $by_caller['bisheng']['tokens'] ?? 0 ) + (int) ( $by_caller['bisheng-html']['tokens'] ?? 0 );
			if ( $bh_ready || $bh_req > 0 ) {
				$items['bisheng-html'] = [
					'slug'         => 'bisheng-html',
					'name'         => __( '笔笙 HTML（Bisheng）', 'wpmind' ),
					'desc'         => __( '页面与代码生成已连接文派心思公共接口。', 'wpmind' ),
					'feature'      => __( 'AI 页面生成', 'wpmind' ),
					'icon'         => 'sparkle',
					'version'      => defined( 'BISHENG_HTML_VERSION' ) ? (string) constant( 'BISHENG_HTML_VERSION' ) : '',
					'settings_url' => $menu_url( 'bisheng-html' ),
					'requests'     => $bh_req,
					'tokens'       => $bh_tok,
					'active'       => true,
				];
			}
		}

		// 3. 文派文档（WPDocs）.
		if ( defined( 'WPDOCS_VERSION' ) || class_exists( '\\WPDocs\\Plugin', false ) ) {
			$docs_wired = ( function_exists( 'has_filter' ) && ( false !== has_filter( 'wpmind_llms_txt_sections' ) || false !== has_filter( 'wpmind_raw_markdown_for_post' ) ) );
			$docs_stats = $by_caller['wpdocs'] ?? [
				'requests' => 0,
				'tokens'   => 0,
			];
			if ( $docs_wired || $docs_stats['requests'] > 0 ) {
				$items['wpdocs'] = [
					'slug'         => 'wpdocs',
					'name'         => __( '文派文档（WPDocs）', 'wpmind' ),
					'desc'         => __( '文档库已联动文派心思 GEO（llms.txt / Markdown Feed）与 AI 能力。', 'wpmind' ),
					'feature'      => __( 'GEO / 文档 AI', 'wpmind' ),
					'icon'         => 'doc',
					'version'      => defined( 'WPDOCS_VERSION' ) ? (string) constant( 'WPDOCS_VERSION' ) : '',
					'settings_url' => $menu_url( 'wpdocs' ),
					'requests'     => (int) $docs_stats['requests'],
					'tokens'       => (int) $docs_stats['tokens'],
					'active'       => true,
				];
			}
		}

		// 4. 文派智储（WPSSS）.
		if ( defined( 'WPSSS_VERSION' ) ) {
			$sss_wired = function_exists( 'has_filter' ) && false !== has_filter( 'wpsss_ci_audit_text' );
			$sss_stats = $by_caller['wpsss'] ?? [
				'requests' => 0,
				'tokens'   => 0,
			];
			if ( $sss_wired || $sss_stats['requests'] > 0 ) {
				$items['wpsss'] = [
					'slug'         => 'wpsss',
					'name'         => __( '文派智储（WPSSS）', 'wpmind' ),
					'desc'         => __( '云端媒体与内容安全审核已联动文派心思。', 'wpmind' ),
					'feature'      => __( '内容审核 / 媒体 AI', 'wpmind' ),
					'icon'         => 'cloud',
					'version'      => (string) constant( 'WPSSS_VERSION' ),
					'settings_url' => $menu_url( 'wpsss' ),
					'requests'     => (int) $sss_stats['requests'],
					'tokens'       => (int) $sss_stats['tokens'],
					'active'       => true,
				];
			}
		}

		// 5. 文派合规（WPICP）.
		if ( defined( 'WPICP_VERSION' ) ) {
			$icp_opts  = get_option( 'wpicp_settings', [] );
			$icp_opts  = is_array( $icp_opts ) ? $icp_opts : [];
			$icp_ai    = ! empty( $icp_opts['ai_review_enabled'] ) || 'wpmind' === (string) ( $icp_opts['ai_engine'] ?? '' );
			$icp_stats = $by_caller['wpicp'] ?? [
				'requests' => 0,
				'tokens'   => 0,
			];
			if ( $icp_ai || $icp_stats['requests'] > 0 ) {
				$items['wpicp'] = [
					'slug'         => 'wpicp',
					'name'         => __( '文派合规（WPICP）', 'wpmind' ),
					'desc'         => __( '广告法极限词与合规复核已启用文派心思语义判定。', 'wpmind' ),
					'feature'      => __( '合规语义复核', 'wpmind' ),
					'icon'         => 'lock',
					'version'      => (string) constant( 'WPICP_VERSION' ),
					'settings_url' => $menu_url( 'wpicp' ),
					'requests'     => (int) $icp_stats['requests'],
					'tokens'       => (int) $icp_stats['tokens'],
					'active'       => true,
				];
			}
		}

		// 6. 文派评论（WPComments）.
		if ( defined( 'WPCOMMENTS_VERSION' ) ) {
			$cmt_stats = $by_caller['wpcomments'] ?? [
				'requests' => 0,
				'tokens'   => 0,
			];
			if ( $cmt_stats['requests'] > 0 ) {
				$items['wpcomments'] = [
					'slug'         => 'wpcomments',
					'name'         => __( '文派评论（WPComments）', 'wpmind' ),
					'desc'         => __( '评论辅助回复与内容过滤已接入文派心思。', 'wpmind' ),
					'feature'      => __( '评论 AI 辅助', 'wpmind' ),
					'icon'         => 'feedback',
					'version'      => (string) constant( 'WPCOMMENTS_VERSION' ),
					'settings_url' => $menu_url( 'wpcomments' ),
					'requests'     => (int) $cmt_stats['requests'],
					'tokens'       => (int) $cmt_stats['tokens'],
					'active'       => true,
				];
			}
		}

		if ( function_exists( 'apply_filters' ) ) {
			$filtered = apply_filters( 'wpmind_ecosystem_integrations', array_values( $items ), $by_caller );
			if ( is_array( $filtered ) ) {
				$items = $filtered;
			}
		}

		$normalized = [];
		foreach ( $items as $entry ) {
			if ( ! is_array( $entry ) || empty( $entry['active'] ) || empty( $entry['name'] ) ) {
				continue;
			}
			$slug         = sanitize_key( (string) ( $entry['slug'] ?? '' ) );
			$caller_stat  = '' !== $slug && isset( $by_caller[ $slug ] ) ? $by_caller[ $slug ] : [
				'requests' => 0,
				'tokens'   => 0,
			];
			$normalized[] = [
				'slug'         => $slug,
				'name'         => (string) $entry['name'],
				'desc'         => (string) ( $entry['desc'] ?? '' ),
				'feature'      => (string) ( $entry['feature'] ?? '' ),
				'icon'         => (string) ( $entry['icon'] ?? 'package' ),
				'version'      => (string) ( $entry['version'] ?? '' ),
				'settings_url' => (string) ( $entry['settings_url'] ?? '' ),
				'requests'     => isset( $entry['requests'] ) ? (int) $entry['requests'] : (int) $caller_stat['requests'],
				'tokens'       => isset( $entry['tokens'] ) ? (int) $entry['tokens'] : (int) $caller_stat['tokens'],
				'active'       => true,
			];
		}

		return $normalized;
	}

	/**
	 * Format an integer safely with or without WordPress i18n helper.
	 *
	 * @param int $number Integer to format.
	 * @return string
	 */
	private static function format_int( int $number ): string {
		if ( function_exists( 'number_format_i18n' ) ) {
			return number_format_i18n( $number );
		}
		return number_format( $number );
	}
}
