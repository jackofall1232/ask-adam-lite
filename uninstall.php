<?php
// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) { exit; }

// Remove Lite options (keep content alone).
$opts = [
  'adam_lite_widget_settings',
  'adam_lite_api_settings',
  'adam_lite_theme_colors',
  'adam_lite_kb_settings',
  'adam_lite_api_keys',
];

foreach ( $opts as $k ) {
    delete_option( $k );
}

// If you ever store sitewide (multisite), also delete_site_option( $k ).
