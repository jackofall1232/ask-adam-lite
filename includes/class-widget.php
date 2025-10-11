<?php
if (!defined('ABSPATH')) exit;

class Ask_Adam_Lite_Widget extends WP_Widget {

    const HANDLE = 'ask-adam-lite-widget'; // script handle for enqueues

    public function __construct() {
        parent::__construct('ask_adam_lite_widget', 'Ask Adam Lite');

        // Register an empty script handle we can attach inline JS to.
        add_action('wp_enqueue_scripts', [$this, 'register_assets']);

        // Render the floating widget at the footer (sidebar-compat remains)
        add_action('wp_footer', [$this, 'render_floating']);
    }

    /**
     * Register a bare script handle so we can attach inline JS via wp_add_inline_script
     * without printing <script> blocks directly in markup.
     */
    public function register_assets() {
        // No external JS file required here—this creates a proper handle.
        // If you later add a real file, do:
        // wp_register_script(self::HANDLE, plugins_url('../assets/js/aalite-widget.js', __FILE__), [], '1.0.0', true);
        wp_register_script(self::HANDLE, '', [], null, true);
    }

    public function render_floating() { self::render_floating_static(); }

    /**
     * Ensure our toggle helper is printed only once, and via enqueue API.
     */
    protected static function ensure_toggle_inline_once() {
        static $added = false;
        if ($added) return;

        $js = <<<JS
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
      panel.setAttribute('hidden', 'hidden');
      if(fab){
        fab.setAttribute('aria-expanded', 'false');
        try{ fab.focus(); }catch(e){}
      }
    }
  };

  // ESC key support to close any open panel
  document.addEventListener('keydown', function(e){
    if (e.key === 'Escape') {
      var openPanels = document.querySelectorAll('.aalite-panel:not([hidden])');
      openPanels.forEach(function(panel){
        var widget = panel.closest('[data-aalite-id]');
        if (widget) {
          var uuid = widget.getAttribute('data-aalite-id');
          if (uuid) { AALiteToggle(uuid, false); }
        }
      });
    }
  });
})();
JS;

        // Attach BEFORE the (empty) handle to ensure global is defined early.
        wp_add_inline_script(self::HANDLE, $js, 'before');
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

        // Initial (fallback avatar)
        $initial = '';
        if ($assistant_name !== '') {
            $initial = strtoupper((function_exists('mb_substr') ? mb_substr($assistant_name, 0, 1) : substr($assistant_name, 0, 1)));
        }

        // Enqueue our script handle and inject the toggle helper once.
        wp_enqueue_script(self::HANDLE);
        // Because this is a static method, call the helper via the class name:
        if (method_exists(__CLASS__, 'ensure_toggle_inline_once')) {
            // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
            call_user_func([__CLASS__, 'ensure_toggle_inline_once']);
        }

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

          <!-- Pro-style Panel (scoped classes + Lite hooks) -->
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

            <!-- Conversation body (Lite hook retained) -->
            <div class="aalite-body anna-body"
                 role="log"
                 aria-live="polite"></div>

            <!-- Composer (Lite hook retained) -->
            <form class="aalite-form anna-form" method="dialog" onsubmit="return false">
              <div class="anna-input-wrap">
                <textarea class="anna-textarea" required
                          placeholder="<?php echo esc_attr__('Ask a question…', 'ask-adam-lite'); ?>"
                          maxlength="2000"
                          aria-label="<?php echo esc_attr__('Your message', 'ask-adam-lite'); ?>"></textarea>
                <button class="anna-send" type="submit" aria-label="<?php echo esc_attr__('Send', 'ask-adam-lite'); ?>">
                  <!-- Inline paper plane icon (generic, accessible, no external deps) -->
                  <svg
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 24 24"
                    width="20" height="20"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    aria-hidden="true" focusable="false">
                    <path d="M22 2 11 13" />
                    <path d="M22 2 15 22 11 13 2 9 22 2z" />
                  </svg>
                </button>
              </div>
              <div class="anna-footnote">
                <span class="anna-muted"><?php echo esc_html__('Powered by GPT-4o mini • ', 'ask-adam-lite'); ?><em><?php echo esc_html__('Ask Adam Lite-Free', 'ask-adam-lite'); ?></em></span>
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
