<?php
/**
 * Plugin Name:       Ask Adam Lite
 * Plugin URI:        https://www.askadamit.com
 * Description:       Lite version of Ask Adam — secure, minimal AI chat widget + knowledge base for WordPress.
 * Version:           1.0.0
 * Update URI:        https://api.freemius.com
 * Author:            Ask Adam
 * Author URI:        https://www.askadamit.com
 * Text Domain:       ask-adam-lite
 * Domain Path:       /languages
 * Requires at least: 5.8
 * Tested up to:      6.7
 * Requires PHP:      7.4
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 *
 * © Ask Adam 2025 — https://www.askadamit.com
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** ================================
 *  REQUIRED CONSTANTS
 *  ================================ */
if ( ! defined( 'ADAML_PLUGIN_DIR' ) ) {
    define( 'ADAML_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'ADAML_PLUGIN_URL' ) ) {
    define( 'ADAML_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}
if ( ! defined( 'ADAML_PLUGIN_VERSION' ) ) {
    define( 'ADAML_PLUGIN_VERSION', '1.0.0' );
}
if ( ! defined( 'ADAML_PLUGIN_FILE' ) ) {
    define( 'ADAML_PLUGIN_FILE', __FILE__ );
}
if ( ! defined( 'ADAML_PLUGIN_BASENAME' ) ) {
    define( 'ADAML_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
}
if ( ! defined( 'ADAML_TEXT_DOMAIN' ) ) {
    define( 'ADAML_TEXT_DOMAIN', 'ask-adam-lite' );
}

/** ================================
 *  FREEMIUS SDK (RECOMMENDED FOR DISTRO)
 *  ================================ */
/**
 * NOTE: Replace YOUR_FS_PRODUCT_ID and YOUR_FS_PUBLIC_KEY with your Lite product values.
 * If you’re not ready to bundle the SDK yet, the plugin will show an admin notice
 * but remain loadable for local dev.
 */
if ( ! function_exists( 'adam_lite_fs' ) ) {
    function adam_lite_fs() {
        static $loaded = null;
        if ( $loaded !== null ) {
            return $loaded;
        }

        // Resolve SDK path (support vendor/ or freemius/ folders)
        $sdk_path = null;
        if ( file_exists( ADAML_PLUGIN_DIR . 'vendor/freemius/start.php' ) ) {
            $sdk_path = ADAML_PLUGIN_DIR . 'vendor/freemius/start.php';
        } elseif ( file_exists( ADAML_PLUGIN_DIR . 'freemius/start.php' ) ) {
            $sdk_path = ADAML_PLUGIN_DIR . 'freemius/start.php';
        }

        if ( $sdk_path ) {
            require_once $sdk_path;

            $loaded = fs_dynamic_init( array(
                // --- REQUIRED: use your Lite app details ---
                'id'               => 'YOUR_FS_PRODUCT_ID', // e.g., '20999'
                'slug'             => 'ask-adam-lite',
                'type'             => 'plugin',
                'public_key'       => 'YOUR_FS_PUBLIC_KEY',

                // Lite is free; Pro is a separate plugin
                'is_premium'       => false,
                'is_premium_only'  => false,
                'has_paid_plans'   => false,
                'has_addons'       => false,
                'is_org_compliant' => false,

                // FS admin menu (Account/Support)
                'menu' => array(
                    'slug'    => 'ask-adam-lite',
                    'support' => false,
                ),

                // Multisite hints (safe defaults)
                'multisite' => array(
                    'is_network_active_only' => false,
                    'is_premium'             => false,
                ),

                'is_live' => true,
            ) );

            return $loaded;
        }

        // SDK missing: warn in admin, keep plugin loading for development.
        add_action( 'admin_notices', function () {
            if ( current_user_can( 'activate_plugins' ) ) {
                echo '<div class="notice notice-warning"><p>' .
                    esc_html__( 'Ask Adam Lite: Freemius SDK not found. Place it under vendor/freemius/ or freemius/ for update delivery and telemetry.', ADAML_TEXT_DOMAIN ) .
                '</p></div>';
            }
        } );

        return $loaded = false;
    }

    // Initialize Freemius early (optional). Unlike Pro, Lite won’t bail if FS missing.
    adam_lite_fs();
    do_action( 'adam_lite_fs_loaded' );
}

/** ================================
 *  LOAD DEPENDENCIES (WITH GUARDS)
 *  ================================ */
/**
 * File map uses WP-style names; classes are namespaced via prefixes.
 * These files should exist (empty skeletons are fine to start):
 *
 * includes/
 *   class-ask-adam-lite-plugin.php      -> class Ask_Adam_Lite_Plugin
 *   class-ask-adam-lite-admin.php       -> class Ask_Adam_Lite_Admin
 *   class-ask-adam-lite-assets.php      -> class Ask_Adam_Lite_Assets
 *   class-ask-adam-lite-rest.php        -> class Ask_Adam_Lite_REST
 *   class-ask-adam-lite-kb.php          -> class Ask_Adam_Lite_KB
 *   class-ask-adam-lite-widget.php      -> class Ask_Adam_Lite_Widget
 *   class-ask-adam-lite-shortcode.php   -> class Ask_Adam_Lite_Shortcode
 *
 * (No heavy logic-handler or custom DB installer in Lite.)
 */
$adaml_required_files = array(
    'includes/class-ask-adam-lite-plugin.php'    => 'Ask_Adam_Lite_Plugin',
    'includes/class-ask-adam-lite-admin.php'     => 'Ask_Adam_Lite_Admin',
    'includes/class-ask-adam-lite-assets.php'    => 'Ask_Adam_Lite_Assets',
    'includes/class-ask-adam-lite-rest.php'      => 'Ask_Adam_Lite_REST',
    'includes/class-ask-adam-lite-kb.php'        => 'Ask_Adam_Lite_KB',
    'includes/class-ask-adam-lite-widget.php'    => 'Ask_Adam_Lite_Widget',
    'includes/class-ask-adam-lite-shortcode.php' => 'Ask_Adam_Lite_Shortcode',
);

$adaml_missing = array();
foreach ( $adaml_required_files as $rel => $class ) {
    $path = ADAML_PLUGIN_DIR . $rel;
    if ( file_exists( $path ) ) {
        require_once $path;
    } else {
        $adaml_missing[] = $rel;
    }
}
if ( ! empty( $adaml_missing ) ) {
    add_action( 'admin_notices', function () use ( $adaml_missing ) {
        if ( current_user_can( 'activate_plugins' ) ) {
            echo '<div class="notice notice-error"><p>';
            echo esc_html__( 'Ask Adam Lite: Missing files: ', ADAML_TEXT_DOMAIN );
            echo esc_html( implode( ', ', $adaml_missing ) );
            echo '</p></div>';
        }
    } );
    // Stop bootstrap if anything critical is missing
    return;
}

/** ================================
 *  ACTIVATION / DEACTIVATION HOOKS
 *  ================================ */
register_activation_hook( __FILE__, function () {
    // Widget defaults (Lite-sane)
    if ( false === get_option( 'adam_lite_widget_settings', false ) ) {
        add_option( 'adam_lite_widget_settings', array(
            'enabled'        => true,
            'assistant_name' => 'Adam',
            'position'       => 'bottom-right',
        ) );
    }

    // API defaults (Lite: single provider)
    if ( false === get_option( 'adam_lite_api_settings', false ) ) {
        add_option( 'adam_lite_api_settings', array(
            'endpoint'      => 'https://api.openai.com/v1/chat/completions',
            'model'         => 'gpt-4o-mini',
            'max_tokens'    => 600,
            'temperature'   => 0.7,
            'timeout'       => 20,
            'system_prompt' => 'You are Adam, a concise and helpful assistant.',
        ) );
    }

    // Knowledge base (Lite: conservative defaults; can be off-by-empty)
    if ( false === get_option( 'adam_lite_kb_settings', false ) ) {
        add_option( 'adam_lite_kb_settings', array(
            'sitemap_url'   => '',
            'max_pages'     => 150,
            'max_depth'     => 2,
            'chunk_size'    => 800,
            'chunk_overlap' => 120,
            'enabled'       => false,
        ) );
    }

    // API keys (stored by admin UI)
    if ( false === get_option( 'adam_lite_api_keys', false ) ) {
        add_option( 'adam_lite_api_keys', array(
            'openai' => '',
        ) );
    }

    // No DB installers in Lite by default.
} );

register_deactivation_hook( __FILE__, function () {
    // Keep settings/data on deactivation (no destructive action).
} );

/** ================================
 *  INIT
 *  ================================ */
add_action( 'plugins_loaded', function () {

    // i18n
    load_plugin_textdomain( ADAML_TEXT_DOMAIN, false, dirname( ADAML_PLUGIN_BASENAME ) . '/languages' );

    // Verify required classes exist
    $required_classes = array(
        'Ask_Adam_Lite_Plugin',
        'Ask_Adam_Lite_Admin',
        'Ask_Adam_Lite_Assets',
        'Ask_Adam_Lite_REST',
        'Ask_Adam_Lite_KB',
        'Ask_Adam_Lite_Widget',
        'Ask_Adam_Lite_Shortcode',
    );
    foreach ( $required_classes as $cls ) {
        if ( ! class_exists( $cls ) ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( "Ask Adam Lite: Missing class {$cls}" );
            }
            return;
        }
    }

    // Register core services
    // Assets (register handles; enqueue is conditional inside the class)
    if ( method_exists( 'Ask_Adam_Lite_Assets', 'get_instance' ) ) {
        Ask_Adam_Lite_Assets::get_instance();
    } else {
        new Ask_Adam_Lite_Assets();
    }

    // REST (namespace should be ask-adam-lite/v1 inside the class)
    if ( method_exists( 'Ask_Adam_Lite_REST', 'get_instance' ) ) {
        Ask_Adam_Lite_REST::get_instance();
    } else {
        new Ask_Adam_Lite_REST();
    }

    if ( is_admin() ) {
        // Admin screens
        if ( method_exists( 'Ask_Adam_Lite_Admin', 'get_instance' ) ) {
            Ask_Adam_Lite_Admin::get_instance();
        } else {
            new Ask_Adam_Lite_Admin();
        }

        // Register shortcode in admin as well (avoid unknown shortcode notices)
        if ( method_exists( 'Ask_Adam_Lite_Shortcode', 'get_instance' ) ) {
            Ask_Adam_Lite_Shortcode::get_instance();
        } else {
            new Ask_Adam_Lite_Shortcode();
        }

    } else {
        // Frontend surfaces
        if ( method_exists( 'Ask_Adam_Lite_Widget', 'get_instance' ) ) {
            Ask_Adam_Lite_Widget::get_instance();
        } else {
            new Ask_Adam_Lite_Widget();
        }

        // Embedded chat via [ask_adam_lite]
        if ( method_exists( 'Ask_Adam_Lite_Shortcode', 'get_instance' ) ) {
            Ask_Adam_Lite_Shortcode::get_instance();
        } else {
            new Ask_Adam_Lite_Shortcode();
        }
    }
}, 9 );

/** ================================
 *  HOUSEKEEPING / SUPPORT HELPERS
 *  ================================ */

/**
 * Optional: quick helper to detect if Lite’s shortcode appears in content.
 * You may use this in your Assets class to decide when to enqueue frontend JS/CSS.
 *
 * @param string $content
 * @return bool
 */
function adam_lite_content_has_shortcode( $content ) {
    if ( empty( $content ) ) {
        return false;
    }
    // Keep in sync with the actual tag registered in Ask_Adam_Lite_Shortcode
    $tag = 'ask_adam_lite';
    return ( has_shortcode( $content, $tag ) );
}
