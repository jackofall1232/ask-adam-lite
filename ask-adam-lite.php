<?php
/**
 * Plugin Name:       Ask Adam Lite
 * Description:       Free AI chat widget + mini knowledge base (1 sitemap + 1 priority URL) using OpenAI.
 * Version:           1.0.0
 * Author:            Ask Adam
 * Text Domain:       ask-adam-lite
 * Requires at least: 5.8
 * Tested up to:      6.7
 * Requires PHP:      7.4
 * License:           GPLv2 or later
 */
if (!defined('ABSPATH')) exit;

define('AALITE_VER',  '1.0.0');
define('AALITE_FILE', __FILE__);
define('AALITE_DIR',  plugin_dir_path(__FILE__));
define('AALITE_URL',  plugin_dir_url(__FILE__));
define('AALITE_TD',   'ask-adam-lite');

// Load the plugin class
require_once AALITE_DIR . 'includes/class-plugin.php';

register_activation_hook(__FILE__, ['Ask_Adam_Lite_Plugin', 'activate']);
register_uninstall_hook(__FILE__, 'ask_adam_lite_uninstall');

function ask_adam_lite_uninstall() {
    delete_option('aalite_api_settings');
    delete_option('aalite_widget_settings');
    delete_option('aalite_kb_settings');
    global $wpdb;
    $wpdb->query('DROP TABLE IF EXISTS '.$wpdb->prefix.'aalite_kb_chunks');
    $wpdb->query('DROP TABLE IF EXISTS '.$wpdb->prefix.'aalite_kb_docs');
}

Ask_Adam_Lite_Plugin::get_instance();
