<?php
if (!defined('ABSPATH')) exit;

class Ask_Adam_Lite_Widget extends WP_Widget {
    public function __construct() {
        parent::__construct('ask_adam_lite_widget', 'Ask Adam Lite');
        add_action('wp_footer', [$this, 'render_floating']); // sidebar compat; also renders floating
    }

    public function render_floating() { self::render_floating_static(); }

    public static function render_floating_static() {
        // Defaults + merge to avoid missing keys disabling the widget
        $defaults = [
            'enabled'        => 1,
            'position'       => 'bottom-right',
            'assistant_name' => 'Adam',
            'avatar_url'     => ''
        ];
        $w = wp_parse_args(get_option('aalite_widget_settings', []), $defaults);
        if (!(int)$w['enabled']) return;

        $pos_class = in_array($w['position'], ['bottom-right','bottom-left'], true)
            ? 'aalite-pos-'.$w['position'] : 'aalite-pos-bottom-right';

        $name   = esc_html($w['assistant_name']);
        $avatar = esc_url($w['avatar_url']);
        $display_name = $name . ' • Free Version'; // <<< only change

        // Enhanced toggle helper with better error handling
        static $printed_toggle = false;
        if (!$printed_toggle) {
            $printed_toggle = true; ?>
            <script>
            (function(){
              window.AALiteToggle = function(uuid, open){
                var el = document.querySelector('[data-aalite-id="'+ uuid +'"]'); if(!el) return;
                var panel = el.querySelector('.aalite-panel'); if(!panel) return;
                var fab = el.querySelector('.aalite-btn');
                
                if(open){
                  panel.removeAttribute('hidden');
                  if(fab) fab.setAttribute('aria-expanded', 'true');
                  var ta = panel.querySelector('textarea'); 
                  if(ta) {
                    setTimeout(function() {
                      try{ ta.focus(); }catch(e){}
                    }, 50);
                  }
                } else {
                  panel.setAttribute('hidden','hidden');
                  if(fab) {
                    fab.setAttribute('aria-expanded', 'false');
                    fab.focus();
                  }
                }
              };
              
              // ESC key support
              document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                  var openPanels = document.querySelectorAll('.aalite-panel:not([hidden])');
                  openPanels.forEach(function(panel) {
                    var widget = panel.closest('[data-aalite-id]');
                    if (widget) {
                      var uuid = widget.getAttribute('data-aalite-id');
                      AALiteToggle(uuid, false);
                    }
                  });
                }
              });
            })();
            </script>
        <?php }

        // Unique token for this instance (keeps CSS/JS id hooks intact)
        $uuid = wp_generate_uuid4();
        ?>
        <div id="aalite-widget"
             class="<?php echo esc_attr($pos_class); ?> anna-root"
             data-aalite-id="<?php echo esc_attr($uuid); ?>">
          <!-- Floating Action Button (FAB) -->
          <button class="aalite-btn anna-fab" type="button" 
                  aria-label="Open <?php echo esc_attr($name); ?> chat"
                  aria-expanded="false"
                  onclick="AALiteToggle('<?php echo esc_js($uuid); ?>', true)"></button>

          <!-- Pro-style Panel (scoped classes + Lite hooks) -->
          <div class="aalite-panel anna-panel" hidden>
            <!-- Header -->
            <div class="aalite-head anna-head">
              <div class="anna-identity">
                <?php if ($avatar): ?>
                  <img src="<?php echo $avatar; ?>" alt="<?php echo esc_attr($name); ?>" 
                       class="aalite-avatar anna-avatar" loading="lazy"/>
                <?php else: ?>
                  <div class="aalite-avatar aa-fallback anna-avatar">
                    <?php echo esc_html(strtoupper(substr($name, 0, 1))); ?>
                  </div>
                <?php endif; ?>
                <div class="anna-titles">
                  <strong class="anna-title"><?php echo esc_html($display_name); ?></strong>
                  <span class="anna-subtitle">Ask Adam Lite</span>
                </div>
              </div>

              <!-- Close -->
              <button class="aalite-close anna-close" type="button" 
                      aria-label="Close chat"
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
                          placeholder="Ask a question…"
                          maxlength="2000"
                          aria-label="Your message"></textarea>
                <button class="anna-send" type="submit" aria-label="Send">
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
                <span class="anna-muted">Powered by GPT-4o mini • <em>Ask Adam Lite-Free</em></span>
              </div>
            </form>

            <noscript>
              <div class="aa-msg err anna-noscript">
                <strong>JavaScript Required:</strong> Ask Adam Lite requires JavaScript to function.
              </div>
            </noscript>
          </div>
        </div>
        <?php
    }
}
