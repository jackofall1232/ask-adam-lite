<?php
if (!defined('ABSPATH')) exit;

class Ask_Adam_Lite_Plugin {
    private static $instance;

    public static function get_instance() {
        return self::$instance ??= new self();
    }

    private function __construct() {
        // Includes (runtime)
        require_once AALITE_DIR.'includes/class-admin.php';
        require_once AALITE_DIR.'includes/class-widget.php';
        require_once AALITE_DIR.'includes/class-shortcode.php';
        require_once AALITE_DIR.'includes/class-api-router.php';
        require_once AALITE_DIR.'includes/class-logic-handler.php';
        require_once AALITE_DIR.'includes/class-knowledge-base.php';

        // i18n
        add_action('plugins_loaded', [$this, 'load_textdomain']);

        add_action('wp_enqueue_scripts',   [$this, 'enqueue_front']);
        add_action('admin_enqueue_scripts',[$this, 'enqueue_admin']);

        add_action('widgets_init', function(){ register_widget('Ask_Adam_Lite_Widget'); });
    }

    public static function activate() {
        // Ensure KB class is available during activation
        require_once AALITE_DIR.'includes/class-knowledge-base.php';
        Ask_Adam_Lite_KB::maybe_install_db();
    }

    /**
     * Load translations from /languages
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            AALITE_TD,
            false,
            dirname(plugin_basename(AALITE_FILE)) . '/languages'
        );
    }

    /**
     * Front-end assets: only load when needed (widget enabled OR shortcode used)
     */
    public function enqueue_front() {
        $should_enqueue = false;

        // 1) If the floating widget is enabled in settings
        $w = get_option('aalite_widget_settings', []);
        if (!empty($w) && !empty($w['enabled']) && (int)$w['enabled'] === 1) {
            $should_enqueue = true;
        }

        // 2) Or if the current singular content has the shortcode
        if (!$should_enqueue && !is_admin() && is_singular()) {
            $post = get_post();
            if ($post && function_exists('has_shortcode') && has_shortcode($post->post_content, 'ask_adam_lite')) {
                $should_enqueue = true;
            }
        }

        // (Optional) Allow themes/child plugins to force/disable enqueuing
        $should_enqueue = apply_filters('aalite_should_enqueue_front', $should_enqueue);

        if (!$should_enqueue) {
            return;
        }

        wp_enqueue_style('aalite-widget', AALITE_URL.'assets/css/widget.css', [], AALITE_VER);
        wp_enqueue_script('aalite-widget', AALITE_URL.'assets/js/widget.js', ['jquery'], AALITE_VER, true);

        wp_localize_script('aalite-widget', 'AskAdamLite', [
            'restUrl' => esc_url_raw(rest_url('adam-lite/v1/chat')),
            'nonce'   => wp_create_nonce('wp_rest'),
        ]);
    }

    public function enqueue_admin($hook) {
        if ($hook !== 'toplevel_page_ask-adam-lite') return;
        wp_enqueue_style('aalite-admin', AALITE_URL.'assets/css/admin.css', [], AALITE_VER);
        wp_enqueue_script('aalite-admin', AALITE_URL.'assets/js/admin.js', [], AALITE_VER, true);
    }
}
