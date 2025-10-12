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

                const isCurrentlyOpen = panel.classList.contains("visible");
                if(open === undefined) { open = !isCurrentlyOpen; }

                if(open && !isCurrentlyOpen){
                    panel.classList.add("visible");
                    fab.setAttribute("aria-expanded","true");
                    fab.classList.add("is-open");
                } else if(!open && isCurrentlyOpen){
                    panel.classList.remove("visible");
                    fab.setAttribute("aria-expanded","false");
                    fab.classList.remove("is-open");
                }
            };
            document.addEventListener("keydown",e=>{
                if(e.key==="Escape"){
                    document.querySelectorAll(".aalite-panel.visible").forEach(p=>{
                        const w=p.closest("[data-aalite-id]");
                        if(w){AALiteToggle(w.getAttribute("data-aalite-id"),false);}
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

        // Compute FAB inline style to force correct side
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
                👋 Hi, I'm <?php echo esc_html($assistant_name); ?>! How can I help you today?
              </div>
            </div>

            <form class="aalite-form anna-form pro-input" onsubmit="return false">
              <div class="anna-input-wrap">
                <textarea class="anna-textarea" placeholder="<?php echo esc_attr__('Type your message…', 'ask-adam-lite'); ?>" maxlength="2000"></textarea>
                <button class="anna-send glowing" type="submit" aria-label="Send message">
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                       width="20" height="20" stroke="currentColor" fill="none"
                       stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M22 2 11 13M22 2 15 22 11 13 2 9z" />
                  </svg>
                </button>
              </div>
              <div class="anna-footnote subtle">
                <small>Powered by GPT-4o mini • <a href="https://askadamit.com" target="_blank" rel="noopener">Upgrade to Pro</a></small>
              </div>
            </form>
          </div>

          <!-- Floating Button (toggle open/close) -->
          <button class="aalite-btn anna-fab pro-gradient"
                  type="button"
                  style="<?php echo esc_attr($fab_style); ?>"
                  aria-label="<?php echo esc_attr(sprintf('Toggle %s chat', $assistant_name)); ?>"
                  aria-expanded="false"
                  onclick="AALiteToggle('<?php echo esc_js($uuid); ?>')">
              <span class="fab-glow"></span>
              <!-- Plus icon when closed -->
              <svg class="fab-icon fab-icon-plus" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" stroke="currentColor" fill="none" stroke-width="2.5" stroke-linecap="round">
                  <circle cx="12" cy="12" r="10"/>
                  <path d="M8 12h8M12 8v8"/>
              </svg>
              <!-- X icon when open -->
              <svg class="fab-icon fab-icon-close" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" stroke="currentColor" fill="none" stroke-width="2.5" stroke-linecap="round">
                  <path d="M18 6L6 18M6 6l12 12"/>
              </svg>
          </button>
        </div>
        <style>
        /* Anchor the container; panel gets side margin so it doesn't sit under FAB */
        #aalite-widget.aalite-pos-bottom-right { position: fixed; right: 20px; bottom: 20px; }
        #aalite-widget.aalite-pos-bottom-left  { position: fixed; left: 20px;  bottom: 20px; }

        #aalite-widget.aalite-pos-bottom-right .aalite-panel { margin-right: 76px; }
        #aalite-widget.aalite-pos-bottom-left  .aalite-panel { margin-left:  76px; }

        /* Panel visibility */
        .aa-pro-ui .aalite-panel {
            opacity: 0;
            transform: translateY(20px) scale(0.95);
            pointer-events: none;
            transition: opacity .3s ease, transform .3s cubic-bezier(.34,1.56,.64,1);
        }
        .aa-pro-ui .aalite-panel.visible {
            opacity: 1;
            transform: translateY(0) scale(1);
            pointer-events: all;
        }

        .aa-pro-ui .glassy {
            background: rgba(25,25,35,.95);
            backdrop-filter: blur(16px) saturate(180%);
            border-radius: 20px;
            border: 1px solid rgba(255,255,255,.1);
            box-shadow: 0 8px 40px rgba(0,0,0,.5);
            overflow: hidden;
        }

        /* FAB visuals (positioning is inline via PHP) */
        .aa-pro-ui .aalite-btn {
            width:64px; height:64px; border-radius:50%;
            display:flex; align-items:center; justify-content:center;
            background: linear-gradient(135deg,#00f5a0,#00d9ff);
            color:#fff; border:none; cursor:pointer;
            box-shadow:0 4px 20px rgba(0,255,200,.4);
            transition: transform .3s cubic-bezier(.34,1.56,.64,1), box-shadow .3s, background .3s;
            overflow:hidden;
        }
        .aa-pro-ui .aalite-btn:hover { transform:scale(1.08) rotate(5deg); box-shadow:0 6px 30px rgba(0,255,200,.6); }
        .aa-pro-ui .aalite-btn:active { transform: scale(1.02); }

        .aa-pro-ui .fab-icon { position:absolute; transition: opacity .3s, transform .3s; }
        .aa-pro-ui .fab-icon-plus  { opacity:1; transform: rotate(0deg)   scale(1); }
        .aa-pro-ui .fab-icon-close { opacity:0; transform: rotate(90deg)  scale(.8); }
        .aa-pro-ui .aalite-btn.is-open .fab-icon-plus  { opacity:0; transform: rotate(-90deg) scale(.8); }
        .aa-pro-ui .aalite-btn.is-open .fab-icon-close { opacity:1; transform: rotate(0deg)   scale(1); }

        .aa-pro-ui .aalite-btn.is-open {
            background: linear-gradient(135deg,#ff6b6b,#ff8e53);
            box-shadow:0 6px 30px rgba(255,107,107,.5);
        }

        .aa-pro-ui .pro-shadow { box-shadow:0 0 15px rgba(0,255,255,.35); border-radius:50%; }
        .aa-pro-ui .aa-placeholder { text-align:center; padding:2rem 1.5rem; color:#d0d0d0; font-style:italic; font-size: 15px; }
        .aa-pro-ui .anna-textarea {
            background:#0d1117; color:#fff; border:1px solid #2d2f35; border-radius:14px; padding:12px 16px;
            resize:none; font-size:14px; line-height:1.5; min-height:44px; transition: border-color .2s, box-shadow .2s;
        }
        .aa-pro-ui .anna-textarea:focus { outline:none; border-color:#00d9ff; box-shadow:0 0 0 3px rgba(0,217,255,.15); }
        .aa-pro-ui .glowing {
            background:linear-gradient(90deg,#00d9ff,#00f5a0); border:none; border-radius:12px; color:#fff;
            padding:12px; cursor:pointer; transition: opacity .2s, transform .2s;
        }
        .aa-pro-ui .glowing:hover { opacity:.9; transform: scale(1.05); }
        .aa-pro-ui .glowing:active { transform: scale(0.98); }
        .aa-pro-ui .anna-footnote.subtle { text-align:center; margin-top:10px; color:#999; font-size:12px; }
        .aa-pro-ui .anna-footnote.subtle a { color:#00d9ff; text-decoration:none; transition: color .2s; }
        .aa-pro-ui .anna-footnote.subtle a:hover { color:#00f5a0; text-decoration: underline; }

        .aa-pro-ui .sleek {
            padding: 18px 20px;
            background: linear-gradient(135deg, rgba(0,245,160,.15), rgba(0,217,255,.15));
            border-bottom: 1px solid rgba(255,255,255,.08);
        }
        .aa-pro-ui .anna-title { font-size: 16px; font-weight: 600; color: #fff; letter-spacing: 0.3px; }
        .aa-pro-ui .anna-subtitle { font-size: 13px; color: rgba(255,255,255,.65); }

        .aa-pro-ui .anna-input-wrap { display:flex; gap:10px; align-items:flex-end; }
        </style>
        <?php
    }
}
