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

        add_action('wp_enqueue_scripts', [$this, 'enqueue_front']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin']);
        add_action('widgets_init', function(){ register_widget('Ask_Adam_Lite_Widget'); });
    }

    public static function activate() {
        // Ensure KB class is available during activation
        require_once AALITE_DIR.'includes/class-knowledge-base.php';
        Ask_Adam_Lite_KB::maybe_install_db();
    }

    public function enqueue_front() {
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
