<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! class_exists( 'Ask_Adam_Lite_Plugin' ) ) {
class Ask_Adam_Lite_Plugin {
    private static $instance = null;
    public static function get_instance() { return self::$instance ?? ( self::$instance = new self() ); }
    private function __construct() {
        // Place for future cron/health checks, etc.
    }
}}
