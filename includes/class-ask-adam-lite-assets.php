<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! class_exists( 'Ask_Adam_Lite_Assets' ) ) {

class Ask_Adam_Lite_Assets {
    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action( 'wp_enqueue_scripts', [ $this, 'register_frontend' ], 5 );
        add_action( 'admin_enqueue_scripts', [ $this, 'register_admin' ], 5 );
    }

    /**
     * Register (but do not enqueue) public assets.
     * Shortcode will call enqueue_frontend() when it renders.
     */
    public function register_frontend() {
        // CSS
        wp_register_style(
            'ask-adam-lite-frontend',
            ADAML_PLUGIN_URL . 'assets/css/frontend.css',
            [],
            ADAML_PLUGIN_VERSION
        );

        // JS
        wp_register_script(
            'ask-adam-lite-frontend',
            ADAML_PLUGIN_URL . 'assets/js/frontend.js',
            [],
            ADAML_PLUGIN_VERSION,
            true
        );
    }

    /**
     * Register (but do not enqueue) admin assets.
     * Admin class should call enqueue_admin() on its screens.
     */
    public function register_admin() {
        wp_register_style(
            'ask-adam-lite-admin',
            ADAML_PLUGIN_URL . 'assets/css/admin.css',
            [],
            ADAML_PLUGIN_VERSION
        );
        wp_register_script(
            'ask-adam-lite-admin',
            ADAML_PLUGIN_URL . 'assets/js/admin.js',
            [ 'jquery' ],
            ADAML_PLUGIN_VERSION,
            true
        );
    }

    /** Enqueue frontend assets. Safe to call multiple times. */
    public static function enqueue_frontend() {
        // Ensure registered
        if ( ! wp_style_is( 'ask-adam-lite-frontend', 'registered' ) ) {
            ( new self() )->register_frontend();
        }
        wp_enqueue_style( 'ask-adam-lite-frontend' );
        wp_enqueue_script( 'ask-adam-lite-frontend' );
    }

    /** Enqueue admin assets on plugin screens (call from Admin class with $hook check). */
    public static function enqueue_admin() {
        if ( ! wp_style_is( 'ask-adam-lite-admin', 'registered' ) ) {
            ( new self() )->register_admin();
        }
        wp_enqueue_style( 'ask-adam-lite-admin' );
        wp_enqueue_script( 'ask-adam-lite-admin' );
    }
}

}
