<?php
if (!defined('ABSPATH')) exit;

class Ask_Adam_Lite_Shortcode {
    public function __construct() { add_shortcode('ask_adam_lite', [$this, 'render']); }

    public function render($atts = []) {
        $w = get_option('aalite_widget_settings', [
            'assistant_name' => 'Adam',
            'avatar_url'     => ''
        ]);
        $name         = esc_html($w['assistant_name']);
        $avatar       = esc_url($w['avatar_url']);
        $display_name = $name . ' • Ask Adam Lite-Free Version'; // <<< added line

        // Unique ID helps if you later want to add a close in embeds too
        $id = 'aalite-embed-'.wp_generate_uuid4();

        ob_start(); ?>
        <div id="<?php echo esc_attr($id); ?>" class="aalite-embed" data-name="<?php echo $name; ?>" data-avatar="<?php echo $avatar; ?>">
          <div class="aalite-panel">
            <div class="aalite-head">
              <?php if ($avatar): ?>
                <img src="<?php echo $avatar; ?>" alt="<?php echo $name; ?>" class="aalite-avatar"/>
              <?php else: ?>
                <div class="aalite-avatar aa-fallback">A</div>
              <?php endif; ?>
              <strong><?php echo esc_html($display_name); ?></strong>
            </div>

            <div class="aalite-body"></div>

            <form class="aalite-form" method="dialog" onsubmit="return false">
              <textarea required placeholder="Ask a question…"></textarea>
              <button type="submit">Send</button>
            </form>
          </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
new Ask_Adam_Lite_Shortcode();
