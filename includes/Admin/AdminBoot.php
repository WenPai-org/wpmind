<?php
/**
 * Admin bootstrapping.
 *
 * @package WPMind\Admin
 * @since 3.3.0
 */

declare(strict_types=1);

namespace WPMind\Admin;

/**
 * Class AdminBoot
 */
final class AdminBoot {

    /**
     * Singleton instance.
     *
     * @var AdminBoot|null
     */
    private static ?AdminBoot $instance = null;

    /**
     * Whether hooks are registered.
     *
     * @var bool
     */
    private bool $initialized = false;

    /**
     * Get singleton instance.
     *
     * @return AdminBoot
     */
    public static function instance(): AdminBoot {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {}

    /**
     * Register admin hooks.
     */
    public function init(): void {
        if ( $this->initialized ) {
            return;
        }

        $assets = AdminAssets::instance();
        $page = AdminPage::instance();
        $ajax = AjaxController::instance();

        add_action( 'admin_menu', [ $page, 'add_admin_menu' ] );
        add_action( 'admin_init', [ $page, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts', [ $assets, 'enqueue_admin_assets' ] );

        $ajax->register_hooks();

        add_filter(
            'plugin_action_links_' . plugin_basename( WPMIND_PLUGIN_FILE ),
            [ $page, 'plugin_action_links' ]
        );
        add_filter( 'plugin_row_meta', [ $page, 'plugin_row_meta' ], 10, 2 );

        $this->initialized = true;
    }
}
