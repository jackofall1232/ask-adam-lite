<?php
// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Remove Lite options (keep content untouched).
$opts = [
    'aalite_widget_settings',
    'aalite_api_settings',
    'aalite_kb_settings',
    'aalite_reasoning_model',
    'aalite_vision_model',
    'aalite_intent_model',
    'aalite_embedding_model',
    'aalite_kb_indexed_embedding_model',
];

// Delete options for single-site.
foreach ( $opts as $k ) {
    delete_option( $k );
}

// Also clean up for multisite.
if ( is_multisite() ) {
    foreach ( $opts as $k ) {
        delete_site_option( $k );
    }
}
