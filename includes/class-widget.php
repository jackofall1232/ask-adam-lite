<?php
if (!defined('ABSPATH')) exit;

class Ask_Adam_Lite_Widget extends WP_Widget {

    public function __construct() {
        parent::__construct('ask_adam_lite_widget', 'Ask Adam Lite');
        // Sidebar compatibility; render floating widget in the footer.
        add_action('wp_footer', [$this, 'render_floating']);
    }

    public function render_floating() { self::render_floating_static(); }

    /**
     * Inject a tiny toggle helper once, using the existing 'aalite-widget' handle.
     * If the handle isn't registered (edge case), register an empty one so we can attach inline JS.
     */
    protected static function ensure_toggle_inline_once() {
        static $added = false;
        if ($added) return;

        // Ensure the handle exists so wp_add_inline_script works.
        if ( ! wp_script_is('aalite-widget', 'registered') ) {
            // Provide a version to satisfy WPCS (avoid browser cache issues).
            $ver = defined('AALITE_VER') ? AALITE_VER : '1.0.0';
            wp_register_script('aalite-widget', '', [], $ver, true);
        }
        // Make sure it will print in the footer.
        if ( ! wp_script_is('aalite-widget', 'enqueued') ) {
            wp_enqueue_script('aalite-widget');
        }

        // Build inline JS without heredoc/nowdoc.
        ob_start();
        ?>
(function(){
  window.AALiteToggle = function(uuid, open){
    var el = document.querySelector('[data-aalite-id="'+ uuid +'"]'); if(!el) return;
    var panel = el.querySelector('.aalite-panel'); if(!panel) return;
    var fab = el.querySelector('.aalite-btn');

    if(open){
      panel.removeAttribute('hidden');
      if(fab) fab.setAttribute('aria-expanded', 'true');
      var ta = panel.querySelector('textarea');
      if(ta){
        setTimeout(function(){ try{ ta.focus(); }catch(e){} }, 50);
      }
    } else {
      panel.setAttribute('hidden','hidden');
      if(fab){
        fab.setAttribute('aria-expanded', 'false');
        try{ fab.focus(); }catch(e){}
      }
    }
  };

  // ESC closes any open panel
  document.addEventListener('keydown', function(e){
    if (e.key === 'Escape') {
      document.querySelectorAll('.aalite-panel:not([hidden])').forEach(function(panel){
        var widget = panel.closest('[data-aalite-id]');
        if (widget) {
          var uuid = widget.getAttribute('data-aalite-id');
          if (uuid) { AALiteToggle(uuid, false); }
        }
      });
    }
  });
})();
        <?php
        $js = (string) ob_get_clean();

        // Attach BEFORE main widget file so global is available early.
        wp_add_inline_script('aalite-widget', $js, 'before');
        $added = true;
    }

    public static function render_floating_static() {
        // Defaults + merge to avoid missing keys disabling the widget
        $defaults = [
            'enabled'        => 1,
            'position'       => 'bottom-right',
            'assistant_name' => 'Adam',
            'avatar_url'     => ''
        ];
        $w = wp_parse_args(get_option('aalite_widget_settings', []), $defaults);
        if (!(int) $w['enabled']) return;

        $pos_class = in_array($w['position'], ['bottom-right','bottom-left'], true)
            ? 'aalite-pos-' . $w['position']
            : 'aalite-pos-bottom-right';

        // Keep raw, escape on output
        $assistant_name = (string) $w['assistant_name'];
        $avatar_url     = (string) $w['avatar_url'];
        $display_name   = $assistant_name . ' • Free Version';

        // Fallback avatar initial
        $initial = '';
        if ($assistant_name !== '') {
            $initial = strtoupper((function_exists('mb_substr') ? mb_substr($assistant_name, 0, 1) : substr($assistant_name, 0, 1)));
        }

        // Ensure our inline helper is present and the main handle is enqueued
        self::ensure_toggle_inline_once();
        wp_enqueue_script('aalite-widget'); // idempotent; safe if already enqueued

        // Unique token for this instance (keeps CSS/JS id hooks intact)
        $uuid = wp_generate_uuid4();
        ?>
        <div id="aalite-widget"
             class="<?php echo esc_attr($pos_class); ?> anna-root"
             data-aalite-id="<?php echo esc_attr($uuid); ?>">
          <!-- Floating Action Button (FAB) -->
          <button class="aalite-btn anna-fab" type="button"
                  aria-label="<?php echo esc_attr(sprintf('Open %s chat', $assistant_name)); ?>"
                  aria-expanded="false"
                  onclick="AALiteToggle('<?php echo esc_js($uuid); ?>', true)"></button>

          <!-- Panel -->
          <div class="aalite-panel anna-panel" hidden>
            <!-- Header -->
            <div class="aalite-head anna-head">
              <div class="anna-identity">
                <?php if (!empty($avatar_url)): ?>
                  <img src="<?php echo esc_url($avatar_url); ?>" alt="<?php echo esc_attr($assistant_name); ?>"
                       class="aalite-avatar anna-avatar" loading="lazy"/>
                <?php else: ?>
                  <div class="aalite-avatar aa-fallback anna-avatar">
                    <?php echo esc_html($initial); ?>
                  </div>
                <?php endif; ?>
                <div class="anna-titles">
                  <strong class="anna-title"><?php echo esc_html($display_name); ?></strong>
                  <span class="anna-subtitle">Ask Adam Lite</span>
                </div>
              </div>

              <!-- Close -->
              <button class="aalite-close anna-close" type="button"
                      aria-label="<?php echo esc_attr__('Close chat', 'ask-adam-lite'); ?>"
                      onclick="AALiteToggle('<?php echo esc_js($uuid); ?>', false)">×</button>
            </div>

            <!-- Conversation body -->
            <div class="aalite-body anna-body" role="log" aria-live="polite"></div>

            <!-- Composer -->
            <form class="aalite-form anna-form" method="dialog" onsubmit="return false">
              <div class="anna-input-wrap">
                <textarea class="anna-textarea" required
                          placeholder="<?php echo esc_attr__('Ask a question…', 'ask-adam-lite'); ?>"
                          maxlength="2000"
                          aria-label="<?php echo esc_attr__('Your message', 'ask-adam-lite'); ?>"></textarea>
                <button class="anna-send" type="submit" aria-label="<?php echo esc_attr__('Send', 'ask-adam-lite'); ?>">
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                       width="20" height="20" fill="none" stroke="currentColor"
                       stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                       aria-hidden="true" focusable="false">
                    <path d="M22 2 11 13" />
                    <path d="M22 2 15 22 11 13 2 9 22 2z" />
                  </svg>
                </button>
              </div>
              <div class="anna-footnote">
                <span class="anna-muted">
                  <?php echo esc_html__('Powered by GPT-4o mini • ', 'ask-adam-lite'); ?>
                  <em><?php echo esc_html__('Ask Adam Lite-Free', 'ask-adam-lite'); ?></em>
                </span>
              </div>
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
    }
}
