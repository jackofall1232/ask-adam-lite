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

        // Sanitize values early
        $name_raw = isset($w['assistant_name']) ? (string) $w['assistant_name'] : 'Adam';
        $name     = sanitize_text_field($name_raw);
        $avatar   = isset($w['avatar_url']) ? (string) $w['avatar_url'] : '';

        $display_name = $name . ' • Ask Adam Lite-Free Version';

        // Unique ID helps if you later want to add a close in embeds too
        $id = 'aalite-embed-' . wp_generate_uuid4();

        ob_start(); ?>
        <div id="<?php echo esc_attr($id); ?>"
             class="aalite-embed"
             data-name="<?php echo esc_attr($name); ?>"
             data-avatar="<?php echo esc_url($avatar); ?>">
          <div class="aalite-panel">
            <div class="aalite-head">
              <?php if (!empty($avatar)) : ?>
                <img src="<?php echo esc_url($avatar); ?>"
                     alt="<?php echo esc_attr($name); ?>"
                     class="aalite-avatar"/>
              <?php else : ?>
                <div class="aalite-avatar aa-fallback">
                  <?php echo esc_html( strtoupper( mb_substr($name, 0, 1) ) ); ?>
                </div>
              <?php endif; ?>
              <strong><?php echo esc_html($display_name); ?></strong>
            </div>

            <div class="aalite-body"></div>

            <form class="aalite-form" method="dialog" onsubmit="return false">
              <textarea required
                        placeholder="<?php echo esc_attr__('Ask a question…', 'ask-adam-lite'); ?>"></textarea>
              <button type="submit"><?php esc_html_e('Send', 'ask-adam-lite'); ?></button>
            </form>
          </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
new Ask_Adam_Lite_Shortcode();
