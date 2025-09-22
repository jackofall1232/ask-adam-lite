<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! class_exists( 'Ask_Adam_Lite_Shortcode' ) ) {

class Ask_Adam_Lite_Shortcode {
    private static $instance = null;

    private function __construct() {
        add_action( 'init', [ $this, 'register_shortcode' ], 10 );
    }

    public static function get_instance() {
        if ( null === self::$instance ) self::$instance = new self();
        return self::$instance;
    }

    private function __clone() {}
    public function __wakeup() { throw new \Exception( 'Cannot unserialize singleton' ); }

    public function register_shortcode() {
        // Use a Lite-specific tag to avoid conflicts with Pro
        add_shortcode( 'ask_adam_lite', [ $this, 'render_shortcode' ] );
    }

    /**
     * Renders the container only. All JS/CSS and behavior are handled
     * by the enqueued frontend assets. We push a per-instance config
     * into a global queue that frontend.js consumes.
     */
    public function render_shortcode( $atts = [], $content = null, $tag = '' ) {
        if ( is_admin() ) { return ''; }

        $defaults = [
            'title'       => 'Adam',
            'welcome'     => "Hi! I'm Adam — ask me anything.",
            'width'       => '100%',
            'height'      => '600',
            'placeholder' => 'Ask me anything...',
            'kb'          => 'true',
            'model'       => '',
            'max_tokens'  => '',
            'temperature' => '',
        ];
        $a = shortcode_atts( $defaults, $atts, $tag );

        $title       = sanitize_text_field( $a['title'] );
        $welcome     = sanitize_text_field( $a['welcome'] );
        $width_css   = ( $a['width'] === '' ? '100%' : sanitize_text_field( $a['width'] ) );
        $height_px   = max( 1, intval( $a['height'] ) );
        $placeholder = sanitize_text_field( $a['placeholder'] );
        $kb          = strtolower( sanitize_text_field( $a['kb'] ) ) === 'true';
        $model       = sanitize_text_field( $a['model'] );
        $max_tokens  = ( $a['max_tokens'] === '' ? '' : intval( $a['max_tokens'] ) );
        $temperature = ( $a['temperature'] === '' ? '' : floatval( $a['temperature'] ) );

        // Pull Lite options (colors, avatar, name override)
        $colors     = get_option( 'adam_lite_theme_colors', [] );
        $c_primary  = isset( $colors['primary'] )    ? sanitize_hex_color( $colors['primary'] )    : '#667eea';
        $c_secondary= isset( $colors['secondary'] )  ? sanitize_hex_color( $colors['secondary'] )  : '#764ba2';
        $c_bg       = isset( $colors['background'] ) ? sanitize_hex_color( $colors['background'] ) : '#ffffff';
        $c_text     = isset( $colors['text'] )       ? sanitize_hex_color( $colors['text'] )       : '#1f2937';

        $w          = get_option( 'adam_lite_widget_settings', [] );
        $avatar_url = isset( $w['avatar_url'] ) ? esc_url( $w['avatar_url'] ) : '';
        if ( ! empty( $w['assistant_name'] ) ) {
            $title = sanitize_text_field( $w['assistant_name'] ); // optional override
        }

        // Unique root ID per instance
        $uid     = substr( md5( serialize( [ $title, $welcome, $width_css, $height_px, wp_rand() ] ) ), 0, 8 );
        $root_id = 'adam-lite-sc-' . $uid;

        // REST + Nonce for Lite
        $rest_url = esc_url_raw( rest_url( 'ask-adam-lite/v1/chat' ) );
        $nonce    = wp_create_nonce( 'wp_rest' );

        // Ensure frontend assets are registered/enqueued only when shortcode is present
        if ( class_exists( 'Ask_Adam_Lite_Assets' ) && method_exists( 'Ask_Adam_Lite_Assets', 'enqueue_frontend' ) ) {
            Ask_Adam_Lite_Assets::enqueue_frontend();
        } else {
            // Fallback if assets class isn’t loaded yet; keep handles consistent
            wp_register_style( 'ask-adam-lite-frontend', ADAML_PLUGIN_URL . 'assets/css/frontend.css', [], ADAML_PLUGIN_VERSION );
            wp_register_script( 'ask-adam-lite-frontend', ADAML_PLUGIN_URL . 'assets/js/frontend.js', [], ADAML_PLUGIN_VERSION, true );
            wp_enqueue_style( 'ask-adam-lite-frontend' );
            wp_enqueue_script( 'ask-adam-lite-frontend' );
        }

        /**
         * Push this instance config into a global queue (`window.ADAM_LITE_Q`)
         * that frontend.js will consume and mount.
         * This keeps inline JS tiny and cache-friendly.
         */
        $instance_cfg = [
            'rootId'      => $root_id,
            'restUrl'     => $rest_url,
            'restNonce'   => $nonce,
            'title'       => $title,
            'welcome'     => $welcome,
            'placeholder' => $placeholder,
            'kb'          => (bool) $kb,
            'model'       => $model,
            'maxTokens'   => ( $max_tokens === '' ? null : (int) $max_tokens ),
            'temperature' => ( $temperature === '' ? null : (float) $temperature ),
            'avatarUrl'   => $avatar_url,
            'colors'      => [
                'primary'    => $c_primary,
                'secondary'  => $c_secondary,
                'background' => $c_bg,
                'text'       => $c_text,
            ],
            'width'       => $width_css,
            'height'      => $height_px,
        ];

        // 1) Seed the queue if needed, 2) push this instance
        $seed = 'window.ADAM_LITE_Q = window.ADAM_LITE_Q || [];';
        wp_add_inline_script( 'ask-adam-lite-frontend', $seed, 'before' );
        wp_add_inline_script( 'ask-adam-lite-frontend', 'window.ADAM_LITE_Q.push(' . wp_json_encode( $instance_cfg ) . ');', 'after' );

        // Output only the mount container (no heavy inline CSS/JS)
        ob_start(); ?>
        <div id="<?php echo esc_attr( $root_id ); ?>"
             class="adam-lite-sc-root"
             data-adam-lite-shortcode="1"
             style="width:<?php echo esc_attr( $width_css ); ?>;max-width:100%;position:relative;min-height:<?php echo esc_attr( $height_px ); ?>px;">
            <!-- Ask Adam Lite will mount here via assets/js/frontend.js -->
        </div>
        <?php
        return ob_get_clean();
    }
}

}
