<?php
if (!defined('ABSPATH')) exit;

class Ask_Adam_Lite_Widget extends WP_Widget {

    public function __construct() {
        parent::__construct('ask_adam_lite_widget', 'Ask Adam Lite');
        add_action('wp_footer', [$this, 'render_floating']);
    }

    public function render_floating() { self::render_floating_static(); }

    protected static function ensure_toggle_inline_once() {
        static $added = false;
        if ($added) return;

        if (!wp_script_is('aalite-widget', 'registered')) {
            $ver = defined('AALITE_VER') ? AALITE_VER : '1.0.0';
            wp_register_script('aalite-widget', '', [], $ver, true);
        }
        if (!wp_script_is('aalite-widget', 'enqueued')) {
            wp_enqueue_script('aalite-widget');
        }

        $js = '(function(){
            window.AALiteToggle=function(id,open){
                const el=document.querySelector("[data-aalite-id=\'"+id+"\']");
                if(!el)return;
                const panel=el.querySelector(".aalite-panel");
                const fab=el.querySelector(".aalite-btn");
                if(!panel||!fab)return;

                const isOpen = panel.classList.contains("visible");
                if(open===undefined){open=!isOpen;}

                if(open&&!isOpen){
                    panel.classList.add("visible");
                    fab.classList.add("is-open");
                    fab.setAttribute("aria-expanded","true");
                } else if(!open&&isOpen){
                    panel.classList.remove("visible");
                    fab.classList.remove("is-open");
                    fab.setAttribute("aria-expanded","false");
                }
            };
            document.addEventListener("click",function(e){
                const btn=e.target.closest(".aalite-btn");
                if(!btn)return;
                const wrap=btn.closest("[data-aalite-id]");
                if(!wrap)return;
                e.preventDefault();
                window.AALiteToggle(wrap.getAttribute("data-aalite-id"));
            });
            document.addEventListener("keydown",function(e){
                if(e.key==="Escape"){
                    document.querySelectorAll(".aalite-panel.visible").forEach(function(p){
                        const w=p.closest("[data-aalite-id]");
                        if(w)window.AALiteToggle(w.getAttribute("data-aalite-id"),false);
                    });
                }
            });
        })();';
        wp_add_inline_script('aalite-widget', $js, 'before');
        $added = true;
    }

    public static function render_floating_static() {
        $defaults = [
            'enabled'        => 1,
            'position'       => 'bottom-right',
            'assistant_name' => 'Adam',
            'avatar_url'     => ''
        ];
        $w = wp_parse_args(get_option('aalite_widget_settings', []), $defaults);
        if (!(int)$w['enabled']) return;

        $pos_class = in_array($w['position'], ['bottom-right','bottom-left'], true)
            ? 'aalite-pos-' . $w['position']
            : 'aalite-pos-bottom-right';

        $assistant_name = (string)$w['assistant_name'];
        $avatar_url     = (string)$w['avatar_url'];
        $display_name   = $assistant_name . ' • ASK ADAM LITE';
        $initial        = strtoupper(function_exists('mb_substr') ? mb_substr($assistant_name ?: 'A', 0, 1) : substr($assistant_name ?: 'A', 0, 1));

        // Inline FAB positioning
        $side_is_left = ($w['position'] === 'bottom-left');
        $fab_style = 'position:fixed;bottom:20px;'
                   . ($side_is_left ? 'left:20px;right:auto;' : 'right:20px;left:auto;')
                   . 'z-index:1000001;';

        self::ensure_toggle_inline_once();
        wp_enqueue_script('aalite-widget');
        $uuid = wp_generate_uuid4();
        ?>
        <div id="aalite-widget"
             class="<?php echo esc_attr($pos_class); ?> anna-root aa-pro-ui"
             data-aalite-id="<?php echo esc_attr($uuid); ?>">

          <!-- Chat Panel -->
          <div class="aalite-panel anna-panel glassy">
            <div class="aalite-head anna-head sleek">
              <div class="anna-identity">
                <?php if ($avatar_url): ?>
                  <img src="<?php echo esc_url($avatar_url); ?>" alt="<?php echo esc_attr($assistant_name); ?>" class="aalite-avatar anna-avatar pro-shadow"/>
                <?php else: ?>
                  <div class="aalite-avatar aa-fallback anna-avatar pro-shadow"><?php echo esc_html($initial); ?></div>
                <?php endif; ?>
                <div class="anna-titles">
                  <strong class="anna-title"><?php echo esc_html($display_name); ?></strong>
                  <span class="anna-subtitle">Your AI Assistant</span>
                </div>
              </div>
            </div>

            <div class="aalite-body anna-body aa-scrollbar">
              <div class="aa-placeholder">
                Hi, I'm <?php echo esc_html($assistant_name); ?>! How can I help you today?
              </div>
            </div>

            <form class="aalite-form anna-form pro-input" onsubmit="return false">
              <div class="aalite-image-preview" hidden>
                <img class="aalite-image-preview-img" src="" alt="">
                <button type="button" class="aalite-image-remove" aria-label="<?php echo esc_attr__('Remove image', 'ask-adam-lite'); ?>">&times;</button>
              </div>
              <div class="anna-input-wrap">
                <button type="button" class="aalite-attach" aria-label="<?php echo esc_attr__('Attach image', 'ask-adam-lite'); ?>">
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/>
                  </svg>
                </button>
                <input type="file" class="aalite-file" accept="image/jpeg,image/png,image/gif,image/webp" hidden>
                <textarea class="anna-textarea" placeholder="<?php echo esc_attr__('Type your message...', 'ask-adam-lite'); ?>" maxlength="2000"></textarea>
                <button class="anna-send glowing" type="submit" aria-label="<?php echo esc_attr__('Send message', 'ask-adam-lite'); ?>">
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                       width="20" height="20" stroke="currentColor" fill="none"
                       stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M22 2 11 13M22 2 15 22 11 13 2 9z" />
                  </svg>
                </button>
              </div>
            </form>
          </div>

          <!-- Floating Button -->
          <?php
          // translators: %s: Assistant name, e.g. "Adam".
          $fab_aria_label = esc_attr( sprintf( __( 'Toggle %s chat', 'ask-adam-lite' ), $assistant_name ) );
          ?>
          <button class="aalite-btn anna-fab pro-gradient"
                  type="button"
                  style="<?php echo esc_attr($fab_style); ?>"
                  aria-label="<?php echo $fab_aria_label; ?>"
                  aria-expanded="false">
              <span class="fab-glow"></span>
              <svg class="fab-icon fab-icon-plus" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" stroke="currentColor" fill="none" stroke-width="2.5" stroke-linecap="round">
                  <circle cx="12" cy="12" r="10"/>
                  <path d="M8 12h8M12 8v8"/>
              </svg>
              <svg class="fab-icon fab-icon-close" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" stroke="currentColor" fill="none" stroke-width="2.5" stroke-linecap="round">
                  <path d="M18 6L6 18M6 6l12 12"/>
              </svg>
          </button>
        </div>
        <?php
    }
}
