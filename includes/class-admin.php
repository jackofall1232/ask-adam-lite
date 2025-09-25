<?php
if (!defined('ABSPATH')) exit;

class Ask_Adam_Lite_Admin {
    const PRO_URL   = 'https://www.askadamit.com';      // Pro landing
    const ANNA_URL  = 'https://askadamit.com/anna/';    // Ask Anna landing

    /** Local (in-page) notices buffer */
    private $local_notices = [];

    public function __construct() {
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_init', [$this, 'maybe_save']);
        add_action('admin_enqueue_scripts', [$this, 'admin_styles']);
    }

    public function menu() {
        add_menu_page(
            __('Ask Adam Lite', 'ask-adam-lite'),
            __('Ask Adam Lite', 'ask-adam-lite'),
            'manage_options',
            'ask-adam-lite',
            [$this, 'render'],
            'dashicons-format-chat',
            58
        );
    }

    /**
     * Enqueue admin styles only on our admin page
     */
    public function admin_styles($hook) {
        if ($hook !== 'toplevel_page_ask-adam-lite') {
            return;
        }

        // Enqueue your admin CSS file
        $css_path = plugin_dir_path(dirname(__FILE__)) . 'assets/css/adam-admin.css';
        $css_url  = plugin_dir_url(dirname(__FILE__)) . 'assets/css/adam-admin.css';
        $css_ver  = file_exists($css_path) ? filemtime($css_path) : '1.0.0';

        wp_enqueue_style('ask-adam-lite-admin', $css_url, [], $css_ver);
    }

    private function get_api_key() {
        if (defined('AALITE_OPENAI_API_KEY') && trim((string)constant('AALITE_OPENAI_API_KEY')) !== '') {
            return trim((string)constant('AALITE_OPENAI_API_KEY'));
        }
        $opt = get_option('aalite_api_settings', []);
        return (string)($opt['openai'] ?? '');
    }

    /** Handle form posts for Lite settings only */
    public function maybe_save() {
        if (!is_admin() || !isset($_POST['_aalite_flag'])) return;
        if (!current_user_can('manage_options')) return;
        if (!check_admin_referer('aalite_save')) return;

        // Save Assistant
        if (isset($_POST['save_assistant'])) {
            $api = get_option('aalite_api_settings', []);
            $api['openai'] = sanitize_text_field(wp_unslash($_POST['openai'] ?? ''));
            update_option('aalite_api_settings', $api);
            $this->add_admin_notice(__('Settings saved.', 'ask-adam-lite'), 'updated');
        }

        // Save Widget
        if (isset($_POST['save_widget'])) {
            $w = get_option('aalite_widget_settings', []);
            $w['enabled']        = (int) ($_POST['enabled'] ?? 0);
            $w['position']       = in_array(($_POST['position'] ?? ''), ['bottom-right','bottom-left'], true) ? $_POST['position'] : 'bottom-right';
            $w['assistant_name'] = sanitize_text_field($_POST['assistant_name'] ?? 'Adam');
            $w['avatar_url']     = esc_url_raw($_POST['avatar_url'] ?? '');
            update_option('aalite_widget_settings', $w);
            $this->add_admin_notice(__('Widget saved.', 'ask-adam-lite'), 'updated');
        }

        // Save KB + actions
        if (isset($_POST['save_kb']) || isset($_POST['kb_repair']) || isset($_POST['kb_purge']) || isset($_POST['kb_crawl']) || isset($_POST['kb_embed'])) {
            $kb = get_option('aalite_kb_settings', []);
            if (isset($_POST['save_kb'])) {
                $kb['sitemap_url']  = esc_url_raw($_POST['sitemap_url'] ?? '');
                $kb['priority_url'] = esc_url_raw($_POST['priority_url'] ?? '');
                update_option('aalite_kb_settings', $kb);
                $this->add_admin_notice(__('KB settings saved.', 'ask-adam-lite'), 'updated');
            }
            if (isset($_POST['kb_repair'])) {
                Ask_Adam_Lite_KB::maybe_install_db();
                $this->add_admin_notice(__('KB tables checked.', 'ask-adam-lite'), 'updated');
            }
            if (isset($_POST['kb_purge'])) {
                Ask_Adam_Lite_KB::purge_index();
                $this->add_admin_notice(__('KB purged.', 'ask-adam-lite'), 'updated');
            }
            if (isset($_POST['kb_crawl'])) {
                Ask_Adam_Lite_KB::crawl_from_settings();
                $this->add_admin_notice(__('Crawl finished.', 'ask-adam-lite'), 'updated');
            }
            if (isset($_POST['kb_embed'])) {
                Ask_Adam_Lite_KB::embed_pending();
                $this->add_admin_notice(__('Embedding finished.', 'ask-adam-lite'), 'updated');
            }
        }
    }

    /**
     * Add our own admin notice to local buffer (no WP global notices)
     */
    private function add_admin_notice($message, $type = 'updated') {
        $t = ($type === 'error') ? 'error' : 'updated';
        $this->local_notices[] = [
            'type'    => $t,
            'message' => (string) $message,
        ];
    }

    /**
     * Display local notices
     */
    private function show_admin_notices() {
        if (empty($this->local_notices)) return;

        foreach ($this->local_notices as $n) {
            printf(
                '<div class="%s" style="margin-top:12px;"><p>%s</p></div>',
                esc_attr($n['type']),
                esc_html($n['message'])
            );
        }
    }

    public function render() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'ask-adam-lite'));
        }

        $api = get_option('aalite_api_settings', []);
        $w   = get_option('aalite_widget_settings', [
            'enabled' => 1,
            'position' => 'bottom-right',
            'assistant_name' => 'Adam',
            'avatar_url' => ''
        ]);
        $kb  = get_option('aalite_kb_settings', [
            'sitemap_url' => '',
            'priority_url' => ''
        ]);

        ?>
        <div class="wrap adam-admin is-light">
          <!-- WordPress/global notices from core/other plugins will appear above this .wrap automatically -->

          <!-- Hero section using your CSS structure -->
          <section class="adam-hero" aria-label="Ask Adam Lite">
            <div class="adam-hero__inner">
              <div class="adam-hero__brand">
                <div>
                  <div class="adam-hero__title"><?php esc_html_e('Ask Adam Lite — Free Version', 'ask-adam-lite'); ?></div>
                  <div class="adam-hero__subtitle"><?php esc_html_e('You\'re using the free edition. Upgrade to remove the "Lite-Free" watermark and unlock multi-provider models, web search, themes, and longer memory.', 'ask-adam-lite'); ?></div>
                </div>
              </div>
              <div class="anna-actions">
                <a class="anna-btn" href="<?php echo esc_url(self::PRO_URL); ?>" target="_blank" rel="noopener">
                  <?php esc_html_e('Get Pro', 'ask-adam-lite'); ?>
                </a>
              </div>
            </div>
          </section>

          <!-- Local (plugin-only) notices: render inside our page, after hero -->
          <?php $this->show_admin_notices(); ?>

          <!-- Tabs nav -->
          <nav class="adam-tabs">
            <ul class="adam-tablist">
              <li><button class="adam-tab is-active" data-tab="overview"><?php esc_html_e('Overview', 'ask-adam-lite'); ?></button></li>
              <li><button class="adam-tab" data-tab="assistant"><?php esc_html_e('Assistant', 'ask-adam-lite'); ?></button></li>
              <li><button class="adam-tab" data-tab="widget"><?php esc_html_e('Widget', 'ask-adam-lite'); ?></button></li>
              <li><button class="adam-tab" data-tab="kb"><?php esc_html_e('Knowledge Base', 'ask-adam-lite'); ?></button></li>
              <li><button class="adam-tab pro" data-tab="providers"><?php esc_html_e('Providers (Pro)', 'ask-adam-lite'); ?></button></li>
              <li><button class="adam-tab pro" data-tab="web"><?php esc_html_e('Web Search (Pro)', 'ask-adam-lite'); ?></button></li>
              <li><button class="adam-tab pro" data-tab="profiles"><?php esc_html_e('Profiles (Pro)', 'ask-adam-lite'); ?></button></li>
              <li><button class="adam-tab pro" data-tab="theme"><?php esc_html_e('Theme (Pro)', 'ask-adam-lite'); ?></button></li>
            </ul>
          </nav>

          <!-- Overview (default visible) -->
          <section class="adam-tabpanel" data-panel="overview">
            <div class="anna-card">
              <h2><?php esc_html_e('Overview', 'ask-adam-lite'); ?></h2>
              <p class="anna-muted"><?php esc_html_e('Ask Adam Lite adds a lightweight AI assistant to your site. It supports a floating chat widget and an optional knowledge base index to answer questions from your own content.', 'ask-adam-lite'); ?></p>

              <h3><?php esc_html_e('Quick Start', 'ask-adam-lite'); ?></h3>
              <ol class="anna-list">
                <li><?php esc_html_e('Open the Assistant tab and add your OpenAI API key (GPT-4o mini is used in Lite).', 'ask-adam-lite'); ?></li>
                <li><?php esc_html_e('Open the Widget tab to turn the widget On, choose position, and set a friendly assistant name.', 'ask-adam-lite'); ?></li>
                <li><?php esc_html_e('(Optional) Open the Knowledge Base tab to enter your sitemap URL and a priority URL, then Crawl and Embed.', 'ask-adam-lite'); ?></li>
                <li><?php esc_html_e('(Optional) Add the shortcode [ask_adam_lite] to any page or post to embed the assistant inline.', 'ask-adam-lite'); ?></li>
              </ol>

              <h3><?php esc_html_e('What the Plugin Does', 'ask-adam-lite'); ?></h3>
              <ul class="anna-list">
                <li><?php esc_html_e('Adds a privacy-friendly, first-party AI chat widget.', 'ask-adam-lite'); ?></li>
                <li><?php esc_html_e('Lets you index parts of your site (Knowledge Base) for grounded answers.', 'ask-adam-lite'); ?></li>
                <li><?php esc_html_e('Keeps settings minimal and performance-focused.', 'ask-adam-lite'); ?></li>
              </ul>

              <h3><?php esc_html_e('Lite vs Pro (at a glance)', 'ask-adam-lite'); ?></h3>
              <ul class="anna-list">
                <li><strong><?php esc_html_e('Lite:', 'ask-adam-lite'); ?></strong>
                  <?php esc_html_e('OpenAI (GPT-4o mini), basic widget controls, single sitemap + priority URL, KB caps (≈50 pages / 300 chunks).', 'ask-adam-lite'); ?>
                </li>
                <li><strong><?php esc_html_e('Pro:', 'ask-adam-lite'); ?></strong>
                  <?php esc_html_e('Multiple providers, profiles, theme controls, optional web search, larger KB limits, Image analysis in the shortcode,  and no Lite watermark.', 'ask-adam-lite'); ?>
                </li>
              </ul>

              <p class="anna-muted" style="margin-top:.5rem;">
                <a href="<?php echo esc_url(self::PRO_URL); ?>" target="_blank" rel="noopener"><?php esc_html_e('Learn more about Ask Adam Pro', 'ask-adam-lite'); ?></a>
              </p>
            </div>
          </section>

          <!-- Assistant (hidden by default now that Overview is first) -->
          <section class="adam-tabpanel" data-panel="assistant" hidden>
            <form method="post" class="anna-card">
              <?php wp_nonce_field('aalite_save'); ?>
              <input type="hidden" name="_aalite_flag" value="1">
              <h2><?php esc_html_e('Assistant (OpenAI only)', 'ask-adam-lite'); ?></h2>
              <p class="anna-hint"><?php esc_html_e('Lite uses GPT-4o mini only.', 'ask-adam-lite'); ?></p>

              <label class="anna-label"><?php esc_html_e('OpenAI API Key', 'ask-adam-lite'); ?></label>
              <input class="anna-input" type="password" name="openai" value="<?php echo esc_attr($this->get_api_key()); ?>" placeholder="sk-...">

              <div class="anna-actions">
                <button class="anna-btn" name="save_assistant" value="1"><?php esc_html_e('Save Settings', 'ask-adam-lite'); ?></button>
              </div>
            </form>
          </section>

          <!-- Widget -->
          <section class="adam-tabpanel" data-panel="widget" hidden>
            <form method="post" class="anna-card">
              <?php wp_nonce_field('aalite_save'); ?>
              <input type="hidden" name="_aalite_flag" value="1">
              <h2><?php esc_html_e('Widget', 'ask-adam-lite'); ?></h2>
              <div class="anna-grid">
                <div>
                  <label class="anna-label"><?php esc_html_e('Enable', 'ask-adam-lite'); ?></label>
                  <select class="anna-select" name="enabled">
                    <option value="1" <?php selected((int)$w['enabled'], 1); ?>><?php esc_html_e('On', 'ask-adam-lite'); ?></option>
                    <option value="0" <?php selected((int)$w['enabled'], 0); ?>><?php esc_html_e('Off', 'ask-adam-lite'); ?></option>
                  </select>
                </div>
                <div>
                  <label class="anna-label"><?php esc_html_e('Position', 'ask-adam-lite'); ?></label>
                  <select class="anna-select" name="position">
                    <option value="bottom-right" <?php selected($w['position'], 'bottom-right'); ?>><?php esc_html_e('Bottom Right', 'ask-adam-lite'); ?></option>
                    <option value="bottom-left"  <?php selected($w['position'], 'bottom-left'); ?>><?php esc_html_e('Bottom Left', 'ask-adam-lite'); ?></option>
                  </select>
                </div>
                <div>
                  <label class="anna-label"><?php esc_html_e('Assistant Name', 'ask-adam-lite'); ?></label>
                  <input class="anna-input" name="assistant_name" value="<?php echo esc_attr($w['assistant_name']); ?>">
                </div>
                <div>
                  <label class="anna-label"><?php esc_html_e('Avatar URL', 'ask-adam-lite'); ?></label>
                  <input class="anna-input" type="url" name="avatar_url" value="<?php echo esc_url($w['avatar_url']); ?>">
                </div>
              </div>
              <div class="anna-actions">
                <button class="anna-btn" name="save_widget" value="1"><?php esc_html_e('Save Widget', 'ask-adam-lite'); ?></button>
              </div>
            </form>
          </section>

          <!-- KB -->
          <section class="adam-tabpanel" data-panel="kb" hidden>
            <div class="anna-card">
              <h2 style="margin:0 0 8px;"><?php esc_html_e('Knowledge Base — Quick Guide', 'ask-adam-lite'); ?></h2>
              <ol class="anna-list">
                <li><strong><?php esc_html_e('Enter URLs:', 'ask-adam-lite'); ?></strong> <?php esc_html_e('Add your Sitemap URL and (optionally) one Priority URL.', 'ask-adam-lite'); ?></li>
                <li><strong><?php esc_html_e('Save:', 'ask-adam-lite'); ?></strong> <?php esc_html_e('Click “Save KB” to store your settings.', 'ask-adam-lite'); ?></li>
                <li><strong><?php esc_html_e('Crawl:', 'ask-adam-lite'); ?></strong> <?php esc_html_e('Click “Crawl” to fetch and index pages from your sitemap (Lite caps apply).', 'ask-adam-lite'); ?></li>
                <li><strong><?php esc_html_e('Embed:', 'ask-adam-lite'); ?></strong> <?php esc_html_e('Click “Embed” to generate vector embeddings so the assistant can use your content.', 'ask-adam-lite'); ?></li>
                <li><strong><?php esc_html_e('Maintenance:', 'ask-adam-lite'); ?></strong> <?php esc_html_e('Use “Repair Tables” if needed, or “Purge” to clear all indexed data.', 'ask-adam-lite'); ?></li>
              </ol>
              <p class="anna-hint" style="margin-top:.25rem;"><?php esc_html_e('Tip: Re-run Crawl and Embed after major site changes.', 'ask-adam-lite'); ?></p>
            </div>

            <form method="post" class="anna-card">
              <?php wp_nonce_field('aalite_save'); ?>
              <input type="hidden" name="_aalite_flag" value="1">
              <h2><?php esc_html_e('Knowledge Base (Lite)', 'ask-adam-lite'); ?></h2>
              <p class="anna-hint"><?php esc_html_e('Lite indexes the first sitemap URL and the first priority URL. Caps: 50 pages, 300 chunks.', 'ask-adam-lite'); ?></p>

              <label class="anna-label"><?php esc_html_e('Sitemap URL', 'ask-adam-lite'); ?></label>
              <input class="anna-input" type="url" name="sitemap_url" value="<?php echo esc_url($kb['sitemap_url']); ?>" placeholder="https://example.com/sitemap.xml">

              <label class="anna-label"><?php esc_html_e('Priority URL', 'ask-adam-lite'); ?></label>
              <input class="anna-input" type="url" name="priority_url" value="<?php echo esc_url($kb['priority_url']); ?>" placeholder="https://example.com/important-page/">

              <div class="anna-actions">
                <button class="anna-btn" name="save_kb" value="1"><?php esc_html_e('Save KB', 'ask-adam-lite'); ?></button>
                <button class="anna-btn secondary" name="kb_repair" value="1" type="submit"><?php esc_html_e('Repair Tables', 'ask-adam-lite'); ?></button>
                <button class="anna-btn secondary" name="kb_purge" value="1" type="submit"><?php esc_html_e('Purge', 'ask-adam-lite'); ?></button>
                <button class="anna-btn accent" name="kb_crawl" value="1" type="submit"><?php esc_html_e('Crawl', 'ask-adam-lite'); ?></button>
                <button class="anna-btn accent" name="kb_embed" value="1" type="submit"><?php esc_html_e('Embed', 'ask-adam-lite'); ?></button>
              </div>
            </form>
          </section>

          <!-- Providers (Pro) -->
          <section class="adam-tabpanel" data-panel="providers" hidden>
            <?php
              echo wp_kses_post(
                  $this->pro_card(
                      esc_html__('Choose the right model for the job and keep costs predictable.', 'ask-adam-lite'),
                      [
                          esc_html__('Multiple providers: OpenAI & Anthropic (Claude), plus custom OpenAI-compatible endpoints', 'ask-adam-lite'),
                          esc_html__('Flagship & mini models to balance comprehensive vs cost-efficient answers', 'ask-adam-lite'),
                          esc_html__('API keys prioritized from wp-config.php (or encrypted DB)', 'ask-adam-lite'),
                          esc_html__('Automatic retries and fallback resilience', 'ask-adam-lite'),
                      ],
                      '<p class="anna-muted" style="margin-top:.75rem;">' .
                      esc_html__('Just need a simple widget without provider controls?', 'ask-adam-lite') . ' ' .
                      '<a href="' . esc_url(self::ANNA_URL) . '" target="_blank" rel="noopener">' . esc_html__('See Ask Anna', 'ask-adam-lite') . '</a>.</p>'
                  )
              );
            ?>
          </section>

          <!-- Web Search (Pro) -->
          <section class="adam-tabpanel" data-panel="web" hidden>
            <?php
              echo wp_kses_post(
                  $this->pro_card(
                      esc_html__('Blend your Knowledge Base with fresh results and real citations.', 'ask-adam-lite'),
                      [
                          esc_html__('Real-time Brave Search integration', 'ask-adam-lite'),
                          esc_html__('Blended answers: your KB + the live web', 'ask-adam-lite'),
                          esc_html__('Inline citations with titles, favicons, and links', 'ask-adam-lite'),
                          esc_html__('Per-query budgets and safe-mode filters', 'ask-adam-lite'),
                      ]
                  )
              );
            ?>
          </section>

          <!-- Profiles (Pro) -->
          <section class="adam-tabpanel" data-panel="profiles" hidden>
            <?php
              echo wp_kses_post(
                  $this->pro_card(
                      esc_html__('Set the voice and guardrails of your assistant—once, and reuse anywhere.', 'ask-adam-lite'),
                      [
                          esc_html__('Custom system prompts to control tone and rules', 'ask-adam-lite'),
                          esc_html__('Generation controls (temperature, max tokens, etc.)', 'ask-adam-lite'),
                          esc_html__('Apply profiles globally or via shortcode', 'ask-adam-lite'),
                      ]
                  )
              );
            ?>
          </section>

          <!-- Theme (Pro) -->
          <section class="adam-tabpanel" data-panel="theme" hidden>
            <?php
              echo wp_kses_post(
                  $this->pro_card(
                      esc_html__('Make the widget match your brand—and remove the Lite watermark.', 'ask-adam-lite'),
                      [
                          esc_html__('Exact HEX color pickers for brand-perfect colors', 'ask-adam-lite'),
                          esc_html__('Larger avatar / brand logo area in the header', 'ask-adam-lite'),
                          esc_html__('Premium polish with smooth SVG accents and scrolling', 'ask-adam-lite'),
                          esc_html__('Brand-safe layout: core shapes and spacing kept consistent', 'ask-adam-lite'),
                      ],
                      '<p class="anna-muted" style="margin-top:.75rem;">' .
                      esc_html__('Prefer a lightweight widget?', 'ask-adam-lite') . ' ' .
                      '<a href="' . esc_url(self::ANNA_URL) . '" target="_blank" rel="noopener">' . esc_html__('Learn about Ask Anna', 'ask-adam-lite') . '</a>.</p>'
                  )
              );
            ?>
          </section>

        </div>
        <?php
    }

    /**
     * Renders a marketing card for Pro tabs (no settings, just synopsis + CTA)
     * @param string $lead
     * @param array  $bullets
     * @param string $footnote_html Optional small HTML (muted), e.g., Ask Anna mention
     * @return string
     */
    private function pro_card($lead, array $bullets = [], $footnote_html = '') {
        ob_start(); ?>
        <div class="anna-card pro-card">
          <h2><?php esc_html_e('Ask Adam Pro', 'ask-adam-lite'); ?></h2>
          <p class="anna-muted"><?php echo esc_html($lead); ?></p>
          <?php if ($bullets): ?>
            <ul class="anna-list">
              <?php foreach ($bullets as $b): ?>
                <li>✔ <?php echo esc_html($b); ?></li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>

          <?php if (!empty($footnote_html)) : ?>
            <div class="anna-footnote"><?php echo wp_kses_post($footnote_html); ?></div>
          <?php endif; ?>

          <div class="anna-actions" style="margin-top:1rem;">
            <a class="anna-btn accent" href="<?php echo esc_url(self::PRO_URL); ?>" target="_blank" rel="noopener">
              <?php esc_html_e('Get Pro', 'ask-adam-lite'); ?>
            </a>
          </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
new Ask_Adam_Lite_Admin();
