<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! class_exists( 'Ask_Adam_Lite_Widget' ) ) {

class Ask_Adam_Lite_Widget extends WP_Widget {

    public static function get_instance() {
        static $booted = false;
        if ( ! $booted ) {
            add_action( 'widgets_init', function () {
                register_widget( 'Ask_Adam_Lite_Widget' );
            } );
            $booted = true;
        }
        return $booted;
    }

    public function __construct() {
        parent::__construct(
            'ask_adam_lite_widget',
            __( 'Ask Adam Lite (Chat Widget)', ADAML_TEXT_DOMAIN ),
            array(
                'classname'   => 'ask_adam_lite_widget',
                'description' => __( 'Lite chat widget powered by Ask Adam Lite.', ADAML_TEXT_DOMAIN ),
                'customize_selective_refresh' => true,
            )
        );
    }

    /* ---------------------------
     *  FRONTEND RENDER
     * --------------------------- */
    public function widget( $args, $instance ) {
        if ( is_admin() ) return;

        // Theme wrappers (before_widget/title/after_title/after_widget)
        echo $args['before_widget'] ?? '';

        $defaults = array(
            'title'       => 'Adam',
            'welcome'     => "Hi! I'm Adam — ask me anything.",
            'width'       => '100%',
            'height'      => '420',
            'placeholder' => 'Ask me anything...',
            'kb'          => 'true',
            'model'       => '',
            'max_tokens'  => '',
            'temperature' => '',
            'show_widget_title' => 'false',
        );
        $vals = wp_parse_args( (array) $instance, $defaults );

        $title       = sanitize_text_field( $vals['title'] );
        $welcome     = sanitize_text_field( $vals['welcome'] );
        $width_css   = ( $vals['width'] === '' ? '100%' : sanitize_text_field( $vals['width'] ) );
        $height_px   = max( 1, intval( $vals['height'] ) );
        $placeholder = sanitize_text_field( $vals['placeholder'] );
        $kb          = ( strtolower( (string) $vals['kb'] ) === 'true' );
        $model       = sanitize_text_field( $vals['model'] );
        $max_tokens  = ( $vals['max_tokens'] === '' ? '' : intval( $vals['max_tokens'] ) );
        $temperature = ( $vals['temperature'] === '' ? '' : floatval( $vals['temperature'] ) );
        $show_widget_title = ( strtolower( (string) $vals['show_widget_title'] ) === 'true' );

        // Pull Lite options (colors, avatar, name override)
        $colors     = get_option( 'adam_lite_theme_colors', array() );
        $c_primary  = isset( $colors['primary'] )    ? sanitize_hex_color( $colors['primary'] )    : '#667eea';
        $c_secondary= isset( $colors['secondary'] )  ? sanitize_hex_color( $colors['secondary'] )  : '#764ba2';
        $c_bg       = isset( $colors['background'] ) ? sanitize_hex_color( $colors['background'] ) : '#ffffff';
        $c_text     = isset( $colors['text'] )       ? sanitize_hex_color( $colors['text'] )       : '#1f2937';

        $w          = get_option( 'adam_lite_widget_settings', array() );
        $avatar_url = isset( $w['avatar_url'] ) ? esc_url( $w['avatar_url'] ) : '';
        if ( ! empty( $w['assistant_name'] ) ) {
            $title = sanitize_text_field( $w['assistant_name'] ); // optional override
        }

        // Unique ID per instance render
        $uid     = substr( md5( serialize( array( $title, $welcome, $width_css, $height_px, wp_rand() ) ) ), 0, 8 );
        $root_id = 'adam-lite-w-' . $uid;

        // REST + Nonce
        $rest_url = esc_url_raw( rest_url( 'ask-adam-lite/v1/chat' ) );
        $nonce    = wp_create_nonce( 'wp_rest' );

        // Ensure frontend assets are present
        if ( class_exists( 'Ask_Adam_Lite_Assets' ) && method_exists( 'Ask_Adam_Lite_Assets', 'enqueue_frontend' ) ) {
            Ask_Adam_Lite_Assets::enqueue_frontend();
        } else {
            wp_register_style( 'ask-adam-lite-frontend', ADAML_PLUGIN_URL . 'assets/css/frontend.css', array(), ADAML_PLUGIN_VERSION );
            wp_register_script( 'ask-adam-lite-frontend', ADAML_PLUGIN_URL . 'assets/js/frontend.js', array(), ADAML_PLUGIN_VERSION, true );
            wp_enqueue_style( 'ask-adam-lite-frontend' );
            wp_enqueue_script( 'ask-adam-lite-frontend' );
        }

        // Push instance config for frontend.js
        $instance_cfg = array(
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
            'colors'      => array(
                'primary'    => $c_primary,
                'secondary'  => $c_secondary,
                'background' => $c_bg,
                'text'       => $c_text,
            ),
            'width'       => $width_css,
            'height'      => $height_px,
        );

        $seed = 'window.ADAM_LITE_Q = window.ADAM_LITE_Q || [];';
        wp_add_inline_script( 'ask-adam-lite-frontend', $seed, 'before' );
        wp_add_inline_script( 'ask-adam-lite-frontend', 'window.ADAM_LITE_Q.push(' . wp_json_encode( $instance_cfg ) . ');', 'after' );

        // Optional visible widget title (theme's before/after_title wrappers)
        if ( $show_widget_title && ! empty( $instance['title'] ) ) {
            echo $args['before_title'] ?? '';
            echo esc_html( $instance['title'] );
            echo $args['after_title'] ?? '';
        }

        // Output mount container
        ?>
        <div id="<?php echo esc_attr( $root_id ); ?>"
             class="adam-lite-widget-root"
             data-adam-lite-widget="1"
             style="width:<?php echo esc_attr( $width_css ); ?>;max-width:100%;position:relative;min-height:<?php echo esc_attr( $height_px ); ?>px;">
            <!-- Ask Adam Lite will mount here via assets/js/frontend.js -->
        </div>
        <?php

        echo $args['after_widget'] ?? '';
    }

    /* ---------------------------
     *  BACKEND FORM
     * --------------------------- */
    public function form( $instance ) {
        $defaults = array(
            'title'       => 'Adam',
            'welcome'     => "Hi! I'm Adam — ask me anything.",
            'width'       => '100%',
            'height'      => '420',
            'placeholder' => 'Ask me anything...',
            'kb'          => 'true',
            'model'       => '',
            'max_tokens'  => '',
            'temperature' => '',
            'show_widget_title' => 'false',
        );
        $vals = wp_parse_args( (array) $instance, $defaults );

        // Helpers
        $f = function( $key ) { return $this->get_field_id( $key ); };
        $n = function( $key ) { return $this->get_field_name( $key ); };

        ?>
        <p>
            <label for="<?php echo esc_attr( $f('title') ); ?>"><?php esc_html_e( 'Widget Title (optional):', ADAML_TEXT_DOMAIN ); ?></label>
            <input class="widefat" id="<?php echo esc_attr( $f('title') ); ?>" name="<?php echo esc_attr( $n('title') ); ?>" type="text" value="<?php echo esc_attr( $vals['title'] ); ?>">
        </p>
        <p>
            <input type="checkbox" id="<?php echo esc_attr( $f('show_widget_title') ); ?>" name="<?php echo esc_attr( $n('show_widget_title') ); ?>" value="true" <?php checked( strtolower( (string) $vals['show_widget_title'] ), 'true' ); ?> />
            <label for="<?php echo esc_attr( $f('show_widget_title') ); ?>"><?php esc_html_e( 'Show widget title above chat', ADAML_TEXT_DOMAIN ); ?></label>
        </p>

        <p>
            <label for="<?php echo esc_attr( $f('welcome') ); ?>"><?php esc_html_e( 'Welcome message:', ADAML_TEXT_DOMAIN ); ?></label>
            <input class="widefat" id="<?php echo esc_attr( $f('welcome') ); ?>" name="<?php echo esc_attr( $n('welcome') ); ?>" type="text" value="<?php echo esc_attr( $vals['welcome'] ); ?>">
        </p>

        <p>
            <label for="<?php echo esc_attr( $f('placeholder') ); ?>"><?php esc_html_e( 'Input placeholder:', ADAML_TEXT_DOMAIN ); ?></label>
            <input class="widefat" id="<?php echo esc_attr( $f('placeholder') ); ?>" name="<?php echo esc_attr( $n('placeholder') ); ?>" type="text" value="<?php echo esc_attr( $vals['placeholder'] ); ?>">
        </p>

        <p>
            <label for="<?php echo esc_attr( $f('width') ); ?>"><?php esc_html_e( 'Width (CSS value):', ADAML_TEXT_DOMAIN ); ?></label>
            <input class="widefat" id="<?php echo esc_attr( $f('width') ); ?>" name="<?php echo esc_attr( $n('width') ); ?>" type="text" value="<?php echo esc_attr( $vals['width'] ); ?>" placeholder="e.g., 100%, 600px">
        </p>

        <p>
            <label for="<?php echo esc_attr( $f('height') ); ?>"><?php esc_html_e( 'Min height (px):', ADAML_TEXT_DOMAIN ); ?></label>
            <input class="widefat" id="<?php echo esc_attr( $f('height') ); ?>" name="<?php echo esc_attr( $n('height') ); ?>" type="number" step="1" min="120" value="<?php echo esc_attr( $vals['height'] ); ?>">
        </p>

        <p>
            <input type="checkbox" id="<?php echo esc_attr( $f('kb') ); ?>" name="<?php echo esc_attr( $n('kb') ); ?>" value="true" <?php checked( strtolower( (string) $vals['kb'] ), 'true' ); ?> />
            <label for="<?php echo esc_attr( $f('kb') ); ?>"><?php esc_html_e( 'Enable Knowledge Base (if configured)', ADAML_TEXT_DOMAIN ); ?></label>
        </p>

        <p>
            <label for="<?php echo esc_attr( $f('model') ); ?>"><?php esc_html_e( 'Model override (optional):', ADAML_TEXT_DOMAIN ); ?></label>
            <input class="widefat" id="<?php echo esc_attr( $f('model') ); ?>" name="<?php echo esc_attr( $n('model') ); ?>" type="text" value="<?php echo esc_attr( $vals['model'] ); ?>" placeholder="e.g., gpt-4o-mini">
        </p>

        <p>
            <label for="<?php echo esc_attr( $f('max_tokens') ); ?>"><?php esc_html_e( 'Max tokens (optional):', ADAML_TEXT_DOMAIN ); ?></label>
            <input class="widefat" id="<?php echo esc_attr( $f('max_tokens') ); ?>" name="<?php echo esc_attr( $n('max_tokens') ); ?>" type="number" step="1" min="1" value="<?php echo esc_attr( $vals['max_tokens'] ); ?>">
        </p>

        <p>
            <label for="<?php echo esc_attr( $f('temperature') ); ?>"><?php esc_html_e( 'Temperature (0–2, optional):', ADAML_TEXT_DOMAIN ); ?></label>
            <input class="widefat" id="<?php echo esc_attr( $f('temperature') ); ?>" name="<?php echo esc_attr( $n('temperature') ); ?>" type="number" step="0.1" min="0" max="2" value="<?php echo esc_attr( $vals['temperature'] ); ?>">
        </p>
        <?php
    }

    /* ---------------------------
     *  SAVE
     * --------------------------- */
    public function update( $new_instance, $old_instance ) {
        $out = array();

        $out['title']       = sanitize_text_field( $new_instance['title'] ?? '' );
        $out['show_widget_title'] = ( ! empty( $new_instance['show_widget_title'] ) && strtolower( (string) $new_instance['show_widget_title'] ) === 'true' ) ? 'true' : 'false';
        $out['welcome']     = sanitize_text_field( $new_instance['welcome'] ?? '' );
        $out['placeholder'] = sanitize_text_field( $new_instance['placeholder'] ?? '' );
        $out['width']       = sanitize_text_field( $new_instance['width'] ?? '100%' );

        $h = isset( $new_instance['height'] ) ? intval( $new_instance['height'] ) : 420;
        $out['height']      = max( 120, $h );

        $out['kb']          = ( ! empty( $new_instance['kb'] ) && strtolower( (string) $new_instance['kb'] ) === 'true' ) ? 'true' : 'false';
        $out['model']       = sanitize_text_field( $new_instance['model'] ?? '' );

        $mt = isset( $new_instance['max_tokens'] ) && $new_instance['max_tokens'] !== '' ? intval( $new_instance['max_tokens'] ) : '';
        $out['max_tokens']  = ( $mt !== '' && $mt > 0 ) ? $mt : '';

        $temp = isset( $new_instance['temperature'] ) && $new_instance['temperature'] !== '' ? floatval( $new_instance['temperature'] ) : '';
        if ( $temp !== '' ) {
            $temp = max( 0.0, min( 2.0, $temp ) );
        }
        $out['temperature'] = ( $temp === '' ) ? '' : $temp;

        return $out;
    }
}

}
