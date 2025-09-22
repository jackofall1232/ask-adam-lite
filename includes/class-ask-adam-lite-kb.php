<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! class_exists( 'Ask_Adam_Lite_KB' ) ) {
class Ask_Adam_Lite_KB {
    private static $instance = null;
    public static function get_instance() { return self::$instance ?? ( self::$instance = new self() ); }
    private function __construct() {
        // Lite: optional/no-op. Keep methods ready for Pro-like calls if needed.
    }
}}
