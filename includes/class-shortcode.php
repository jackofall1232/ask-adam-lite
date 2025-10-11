<?php
if (!defined('ABSPATH')) exit;

class Ask_Adam_Lite_Shortcode {
    public function __construct() {
        add_shortcode('ask_adam_lite', [$this, 'render']);
    }

    public function render($atts = []) {
        $w = get_option('aalite_widget_settings', [
            'assistant_name' => 'Adam',
            'avatar_url'     => ''
        ]);

        // Sanitize early
        $name_raw = isset($w['assistant_name']) ? (string) $w['assistant_name'] : 'Adam';
        $name     = sanitize_text_field($name_raw);
        $avatar   = isset($w['avatar_url']) ? esc_url_raw((string) $w['avatar_url']) : '';

        $display_name = $name . ' • Ask Adam Lite-Free Version';
        $initial      = strtoupper(function_exists('mb_substr')
            ? mb_substr($name, 0, 1)
            : substr($name, 0, 1)
        );

        // Unique ID helps isolate multiple embeds on the same page
        $id = 'aalite-embed-' . wp_generate_uuid4();

        // Make sure front-end assets are loaded
        if (!wp_script_is('aalite-widget', 'enqueued')) {
            wp_enqueue_style('aalite-widget', AALITE_URL . 'assets/css/widget.css', [], AALITE_VER);
            wp_enqueue_script('aalite-widget', AALITE_URL . 'assets/js/widget.js', ['jquery'], AALITE_VER, true);
        }

        ob_start(); ?>
        <div id="<?php echo esc_attr($id); ?>"
             class="aalite-embed anna-root"
             data-name="<?php echo esc_attr($name); ?>"
             data-avatar="<?php echo esc_url($avatar); ?>">
          <div class="aalite-panel anna-panel">
            <div class="aalite-head anna-head">
              <?php if (!empty($avatar)) : ?>
                <img src="<?php echo esc_url($avatar); ?>"
                     alt="<?php echo esc_attr($name); ?>"
                     class="aalite-avatar anna-avatar"/>
              <?php else : ?>
                <div class="aalite-avatar aa-fallback anna-avatar">
                  <?php echo esc_html($initial); ?>
                </div>
              <?php endif; ?>
              <strong class="anna-title"><?php echo esc_html($display_name); ?></strong>
            </div>

            <div class="aalite-body anna-body" role="log" aria-live="polite"></div>

            <form class="aalite-form anna-form" method="dialog" onsubmit="return false">
              <textarea required
                        placeholder="<?php echo esc_attr__('Ask a question…', 'ask-adam-lite'); ?>"
                        aria-label="<?php echo esc_attr__('Your message', 'ask-adam-lite'); ?>"></textarea>
              <button type="submit" class="anna-send">
                <?php esc_html_e('Send', 'ask-adam-lite'); ?>
              </button>
            </form>

            <noscript>
              <div class="aa-msg err anna-noscript">
                <strong><?php echo esc_html__('JavaScript Required:', 'ask-adam-lite'); ?></strong>
                <?php echo esc_html__('Ask Adam Lite requires JavaScript to function.', 'ask-adam-lite'); ?>
              </div>
            </noscript>
          </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
new Ask_Adam_Lite_Shortcode();
