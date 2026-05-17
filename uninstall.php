<?php
/**
 * Uninstall routine for Ask Adam Lite.
 *
 * Runs when the plugin is deleted via the WordPress admin.
 * Removes all plugin options and custom database tables.
 *
 * @package Ask_Adam_Lite
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// All options registered by this plugin.
$aalite_opts = [
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
foreach ( $aalite_opts as $aalite_opt_key ) {
    delete_option( $aalite_opt_key );
}

// Also clean up for multisite.
if ( is_multisite() ) {
    foreach ( $aalite_opts as $aalite_opt_key ) {
        delete_site_option( $aalite_opt_key );
    }
}

// Drop custom tables using esc_sql on the prefix to satisfy Plugin Check.
global $wpdb;
$aalite_chunks_table = esc_sql( $wpdb->prefix . 'aalite_kb_chunks' );
$aalite_docs_table   = esc_sql( $wpdb->prefix . 'aalite_kb_docs' );

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Dropping custom tables during uninstall.
$wpdb->query( "DROP TABLE IF EXISTS `{$aalite_chunks_table}`" );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Dropping custom tables during uninstall.
$wpdb->query( "DROP TABLE IF EXISTS `{$aalite_docs_table}`" );
