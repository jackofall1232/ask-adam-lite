<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! class_exists( 'Ask_Adam_Lite_Admin' ) ) {

class Ask_Adam_Lite_Admin {
    private static $instance = null;
    private $page_hook = '';

    public static function get_instance() {
        if ( null === self::$instance ) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_menu', [ $this, 'add_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_filter( 'plugin_action_links_' . ADAML_PLUGIN_BASENAME, [ $this, 'add_action_links' ] );
    }

    /** Top-level menu + page */
    public function add_menu() {
        $this->page_hook = add_menu_page(
            __( 'Ask Adam Lite', ADAML_TEXT_DOMAIN ),
            __( 'Ask Adam Lite', ADAML_TEXT_DOMAIN ),
            'manage_options',
            'ask-adam-lite',
            [ $this, 'render_page' ],
            'dashicons-format-chat',
            62
        );

        // Enqueue admin assets only on this screen
        add_action( 'load-' . $this->page_hook, function () {
            if ( class_exists( 'Ask_Adam_Lite_Assets' ) && method_exists( 'Ask_Adam_Lite_Assets', 'enqueue_admin' ) ) {
                Ask_Adam_Lite_Assets::enqueue_admin();
            }
        } );
    }

    /** “Settings” link in Plugins list */
    public function add_action_links( $links ) {
        $url = admin_url( 'admin.php?page=ask-adam-lite' );
        array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', ADAML_TEXT_DOMAIN ) . '</a>' );
        return $links;
    }

    /** Register and sanitize options */
    public function register_settings() {

        // --- API Keys (OpenAI only in Lite) ---
        register_setting(
            'adam_lite_group_api',
            'adam_lite_api_keys',
            [ 'type' => 'array', 'sanitize_callback' => [ $this, 'sanitize_api_keys' ] ]
        );

        add_settings_section(
            'adam_lite_section_api',
            __( 'API', ADAML_TEXT_DOMAIN ),
            function () {
                echo '<p>' . esc_html__( 'Enter your provider credentials. Lite supports OpenAI out of the box.', ADAML_TEXT_DOMAIN ) . '</p>';
            },
            'ask-adam-lite'
        );

        add_settings_field(
            'adam_lite_api_keys_openai',
            __( 'OpenAI API Key', ADAML_TEXT_DOMAIN ),
            [ $this, 'field_openai_key' ],
            'ask-adam-lite',
            'adam_lite_section_api'
        );

        // --- Basic Chat Settings ---
        register_setting(
            'adam_lite_group_settings',
            'adam_lite_api_settings',
            [ 'type' => 'array', 'sanitize_callback' => [ $this, 'sanitize_api_settings' ] ]
        );

        add_settings_section(
            'adam_lite_section_chat',
            __( 'Chat Settings', ADAML_TEXT_DOMAIN ),
            function () { echo '<p>' . esc_html__( 'Basics for the Lite chat experience.', ADAML_TEXT_DOMAIN ) . '</p>'; },
            'ask-adam-lite'
        );

        add_settings_field(
            'adam_lite_assistant_name',
            __( 'Assistant Name', ADAML_TEXT_DOMAIN ),
            [ $this, 'field_assistant_name' ],
            'ask-adam-lite',
            'adam_lite_section_chat'
        );

        add_settings_field(
            'adam_lite_welcome',
            __( 'Welcome Message', ADAML_TEXT_DOMAIN ),
            [ $this, 'field_welcome' ],
            'ask-adam-lite',
            'adam_lite_section_chat'
        );

        add_settings_field(
            'adam_lite_model',
            __( 'Model (optional override)', ADAML_TEXT_DOMAIN ),
            [ $this, 'field_model' ],
            'ask-adam-lite',
            'adam_lite_section_chat'
        );

        add_settings_field(
            'adam_lite_limits',
            __( 'Limits', ADAML_TEXT_DOMAIN ),
            [ $this, 'field_limits' ],
            'ask-adam-lite',
            'adam_lite_section_chat'
        );

        // --- Theme Colors (optional) ---
        register_setting(
            'adam_lite_group_theme',
            'adam_lite_theme_colors',
            [ 'type' => 'array', 'sanitize_callback' => [ $this, 'sanitize_theme_colors' ] ]
        );

        add_settings_section(
            'adam_lite_section_theme',
            __( 'Theme', ADAML_TEXT_DOMAIN ),
            function () { echo '<p>' . esc_html__( 'Customize the widget’s basic colors.', ADAML_TEXT_DOMAIN ) . '</p>'; },
            'ask-adam-lite'
        );

        add_settings_field(
            'adam_lite_theme_colors',
            __( 'Colors', ADAML_TEXT_DOMAIN ),
            [ $this, 'field_theme_colors' ],
            'ask-adam-lite',
            'adam_lite_section_theme'
        );

        // --- Knowledge Base (lite-cap) ---
        register_setting(
            'adam_lite_group_kb',
            'adam_lite_kb_settings',
            [ 'type' => 'array', 'sanitize_callback' => [ $this, 'sanitize_kb_settings' ] ]
        );

        add_settings_section(
            'adam_lite_section_kb',
            __( 'Knowledge Base (Lite)', ADAML_TEXT_DOMAIN ),
            function () {
                echo '<p>' . esc_html__( 'Optional sitemap-based indexing with conservative limits in Lite.', ADAML_TEXT_DOMAIN ) . '</p>';
            },
            'ask-adam-lite'
        );

        add_settings_field(
            'adam_lite_kb_fields',
            __( 'Sitemap & Limits', ADAML_TEXT_DOMAIN ),
            [ $this, 'field_kb' ],
            'ask-adam-lite',
            'adam_lite_section_kb'
        );
    }

    // ---------- Sanitize callbacks ----------

    public function sanitize_api_keys( $val ) {
        $out = [
            'openai' => isset( $val['openai'] ) ? sanitize_text_field( $val['openai'] ) : '',
        ];
        return $out;
    }

    public function sanitize_api_settings( $val ) {
        $out = [
            'endpoint'      => 'https://api.openai.com/v1/chat/completions',
            'model'         => isset( $val['model'] ) ? sanitize_text_field( $val['model'] ) : '',
            'max_tokens'    => isset( $val['max_tokens'] ) && $val['max_tokens'] !== '' ? max( 1, intval( $val['max_tokens'] ) ) : 600,
            'temperature'   => isset( $val['temperature'] ) && $val['temperature'] !== '' ? min( 2.0, max( 0.0, floatval( $val['temperature'] ) ) ) : 0.7,
            'timeout'       => 20,
            'system_prompt' => isset( $val['system_prompt'] ) ? sanitize_text_field( $val['system_prompt'] ) : 'You are Adam, a concise and helpful assistant.',
        ];
        return $out;
    }

    public function sanitize_theme_colors( $val ) {
        $out = [
            'primary'    => isset( $val['primary'] )    ? sanitize_hex_color( $val['primary'] )    : '#667eea',
            'secondary'  => isset( $val['secondary'] )  ? sanitize_hex_color( $val['secondary'] )  : '#764ba2',
            'background' => isset( $val['background'] ) ? sanitize_hex_color( $val['background'] ) : '#ffffff',
            'text'       => isset( $val['text'] )       ? sanitize_hex_color( $val['text'] )       : '#1f2937',
        ];
        return $out;
    }

    public function sanitize_kb_settings( $val ) {
        $out = [
            'enabled'       => ! empty( $val['enabled'] ) ? (bool) $val['enabled'] : false,
            'sitemap_url'   => isset( $val['sitemap_url'] ) ? esc_url_raw( $val['sitemap_url'] ) : '',
            'max_pages'     => isset( $val['max_pages'] ) ? max( 1, intval( $val['max_pages'] ) ) : 150,
            'max_depth'     => isset( $val['max_depth'] ) ? max( 0, intval( $val['max_depth'] ) ) : 2,
            'chunk_size'    => isset( $val['chunk_size'] ) ? max( 100, intval( $val['chunk_size'] ) ) : 800,
            'chunk_overlap' => isset( $val['chunk_overlap'] ) ? max( 0, intval( $val['chunk_overlap'] ) ) : 120,
        ];
        return $out;
    }

    // ---------- Fields ----------

    public function field_openai_key() {
        $keys   = get_option( 'adam_lite_api_keys', [ 'openai' => '' ] );
        $openai = $keys['openai'] ?? '';
        ?>
        <input type="password" class="regular-text" name="adam_lite_api_keys[openai]" value="<?php echo esc_attr( $openai ); ?>" placeholder="sk-..." />
        <p class="description"><?php esc_html_e( 'Your OpenAI API key (kept in wp_options).', ADAML_TEXT_DOMAIN ); ?></p>
        <?php
    }

    public function field_assistant_name() {
        $w = get_option( 'adam_lite_widget_settings', [] );
        $name = $w['assistant_name'] ?? 'Adam';
        ?>
        <input type="text" class="regular-text" name="adam_lite_widget_settings[assistant_name]" value="<?php echo esc_attr( $name ); ?>" />
        <p class="description"><?php esc_html_e( 'Shown in headers and messages (can be overridden per shortcode/widget).', ADAML_TEXT_DOMAIN ); ?></p>
        <?php
        // also ensure this option exists
        if ( false === get_option( 'adam_lite_widget_settings', false ) ) {
            add_option( 'adam_lite_widget_settings', [ 'assistant_name' => $name, 'position' => 'bottom-right', 'enabled' => true ] );
        }
    }

    public function field_welcome() {
        $s = get_option( 'adam_lite_api_settings', [] );
        $prompt = $s['system_prompt'] ?? 'You are Adam, a concise and helpful assistant.';
        ?>
        <input type="text" class="regular-text" name="adam_lite_api_settings[system_prompt]" value="<?php echo esc_attr( $prompt ); ?>" />
        <p class="description"><?php esc_html_e( 'Initial system tone / behavior for the assistant.', ADAML_TEXT_DOMAIN ); ?></p>
        <?php
    }

    public function field_model() {
        $s = get_option( 'adam_lite_api_settings', [] );
        $model = $s['model'] ?? '';
        ?>
        <input type="text" class="regular-text" name="adam_lite_api_settings[model]" value="<?php echo esc_attr( $model ); ?>" placeholder="e.g., gpt-4o-mini" />
        <p class="description"><?php esc_html_e( 'Optional: set a default model. Users can still override in the shortcode/widget.', ADAML_TEXT_DOMAIN ); ?></p>
        <?php
    }

    public function field_limits() {
        $s = get_option( 'adam_lite_api_settings', [] );
        $max  = isset( $s['max_tokens'] ) ? intval( $s['max_tokens'] ) : 600;
        $temp = isset( $s['temperature'] ) ? floatval( $s['temperature'] ) : 0.7;
        ?>
        <label>
            <?php esc_html_e( 'Max tokens:', ADAML_TEXT_DOMAIN ); ?>
            <input type="number" name="adam_lite_api_settings[max_tokens]" value="<?php echo esc_attr( $max ); ?>" min="1" step="1" />
        </label>
        &nbsp;&nbsp;
        <label>
            <?php esc_html_e( 'Temperature (0–2):', ADAML_TEXT_DOMAIN ); ?>
            <input type="number" name="adam_lite_api_settings[temperature]" value="<?php echo esc_attr( $temp ); ?>" min="0" max="2" step="0.1" />
        </label>
        <?php
    }

    public function field_theme_colors() {
        $c = get_option( 'adam_lite_theme_colors', [] );
        $primary   = $c['primary']    ?? '#667eea';
        $secondary = $c['secondary']  ?? '#764ba2';
        $bg        = $c['background'] ?? '#ffffff';
        $text      = $c['text']       ?? '#1f2937';
        ?>
        <label><?php esc_html_e( 'Primary', ADAML_TEXT_DOMAIN ); ?>:
            <input type="text" class="regular-text" name="adam_lite_theme_colors[primary]" value="<?php echo esc_attr( $primary ); ?>" placeholder="#667eea" />
        </label><br/>
        <label><?php esc_html_e( 'Secondary', ADAML_TEXT_DOMAIN ); ?>:
            <input type="text" class="regular-text" name="adam_lite_theme_colors[secondary]" value="<?php echo esc_attr( $secondary ); ?>" placeholder="#764ba2" />
        </label><br/>
        <label><?php esc_html_e( 'Background', ADAML_TEXT_DOMAIN ); ?>:
            <input type="text" class="regular-text" name="adam_lite_theme_colors[background]" value="<?php echo esc_attr( $bg ); ?>" placeholder="#ffffff" />
        </label><br/>
        <label><?php esc_html_e( 'Text', ADAML_TEXT_DOMAIN ); ?>:
            <input type="text" class="regular-text" name="adam_lite_theme_colors[text]" value="<?php echo esc_attr( $text ); ?>" placeholder="#1f2937" />
        </label>
        <p class="description"><?php esc_html_e( 'Use hex colors (e.g., #112233).', ADAML_TEXT_DOMAIN ); ?></p>
        <?php
    }

    public function field_kb() {
        $k = get_option( 'adam_lite_kb_settings', [] );
        $enabled = ! empty( $k['enabled'] );
        $sitemap = $k['sitemap_url'] ?? '';
        $max_pages = isset( $k['max_pages'] ) ? intval( $k['max_pages'] ) : 150;
        $max_depth = isset( $k['max_depth'] ) ? intval( $k['max_depth'] ) : 2;
        $chunk     = isset( $k['chunk_size'] ) ? intval( $k['chunk_size'] ) : 800;
        $overlap   = isset( $k['chunk_overlap'] ) ? intval( $k['chunk_overlap'] ) : 120;
        ?>
        <p>
            <label>
                <input type="checkbox" name="adam_lite_kb_settings[enabled]" value="1" <?php checked( $enabled, true ); ?> />
                <?php esc_html_e( 'Enable Knowledge Base features in Lite (limited).', ADAML_TEXT_DOMAIN ); ?>
            </label>
        </p>
        <p>
            <label><?php esc_html_e( 'Sitemap URL', ADAML_TEXT_DOMAIN ); ?>:
                <input type="url" class="regular-text" name="adam_lite_kb_settings[sitemap_url]" value="<?php echo esc_attr( $sitemap ); ?>" placeholder="https://example.com/sitemap.xml" />
            </label>
        </p>
        <p>
            <label><?php esc_html_e( 'Max Pages', ADAML_TEXT_DOMAIN ); ?>:
                <input type="number" name="adam_lite_kb_settings[max_pages]" value="<?php echo esc_attr( $max_pages ); ?>" min="1" step="1" />
            </label>
            &nbsp;&nbsp;
            <label><?php esc_html_e( 'Max Depth', ADAML_TEXT_DOMAIN ); ?>:
                <input type="number" name="adam_lite_kb_settings[max_depth]" value="<?php echo esc_attr( $max_depth ); ?>" min="0" step="1" />
            </label>
        </p>
        <p>
            <label><?php esc_html_e( 'Chunk Size', ADAML_TEXT_DOMAIN ); ?>:
                <input type="number" name="adam_lite_kb_settings[chunk_size]" value="<?php echo esc_attr( $chunk ); ?>" min="100" step="10" />
            </label>
            &nbsp;&nbsp;
            <label><?php esc_html_e( 'Chunk Overlap', ADAML_TEXT_DOMAIN ); ?>:
                <input type="number" name="adam_lite_kb_settings[chunk_overlap]" value="<?php echo esc_attr( $overlap ); ?>" min="0" step="10" />
            </label>
        </p>
        <?php
    }

    /** Page renderer */
    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        ?>
        <div class="wrap ask-adam-lite-admin">
            <h1><?php esc_html_e( 'Ask Adam Lite — Settings', ADAML_TEXT_DOMAIN ); ?></h1>

            <div class="notice-info" style="padding:12px 16px;border-left:4px solid #2271b1;margin:16px 0;background:#f6f7f7;">
                <p style="margin:0;">
                    <strong><?php esc_html_e( 'Upgrade to Ask Adam Pro', ADAML_TEXT_DOMAIN ); ?></strong> —
                    <?php esc_html_e( 'Unlock advanced models, larger limits, fine-tuning, and deeper Knowledge Base features.', ADAML_TEXT_DOMAIN ); ?>
                    <a href="https://www.askadamit.com" class="button button-primary" target="_blank" rel="noopener">
                        <?php esc_html_e( 'Learn more', ADAML_TEXT_DOMAIN ); ?>
                    </a>
                </p>
            </div>

            <form action="options.php" method="post">
                <?php
                settings_fields( 'adam_lite_group_api' );
                settings_fields( 'adam_lite_group_settings' );
                settings_fields( 'adam_lite_group_theme' );
                settings_fields( 'adam_lite_group_kb' );

                do_settings_sections( 'ask-adam-lite' );
                submit_button( __( 'Save Settings', ADAML_TEXT_DOMAIN ) );
                ?>
            </form>
        </div>
        <?php
    }
}

}
