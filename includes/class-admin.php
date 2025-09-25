<?php
if (!defined('ABSPATH')) exit;

class Ask_Adam_Lite_Admin {
    const PRO_URL = 'https://www.askadamit.com'; // <-- change if needed

    public function __construct() {
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_init', [$this, 'maybe_save']);
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
            add_settings_error('aalite', 'saved', __('Settings saved.', 'ask-adam-lite'), 'updated');
        }

        // Save Widget
        if (isset($_POST['save_widget'])) {
            $w = get_option('aalite_widget_settings', []);
            $w['enabled']        = (int) ($_POST['enabled'] ?? 0);
            $w['position']       = in_array(($_POST['position'] ?? ''), ['bottom-right','bottom-left'], true) ? $_POST['position'] : 'bottom-right';
            $w['assistant_name'] = sanitize_text_field($_POST['assistant_name'] ?? 'Adam');
            $w['avatar_url']     = esc_url_raw($_POST['avatar_url'] ?? '');
            update_option('aalite_widget_settings', $w);
            add_settings_error('aalite', 'widget_saved', __('Widget saved.', 'ask-adam-lite'), 'updated');
        }

        // Save KB + actions
        if (isset($_POST['save_kb']) || isset($_POST['kb_repair']) || isset($_POST['kb_purge']) || isset($_POST['kb_crawl']) || isset($_POST['kb_embed'])) {
            $kb = get_option('aalite_kb_settings', []);
            if (isset($_POST['save_kb'])) {
                $kb['sitemap_url']  = esc_url_raw($_POST['sitemap_url'] ?? '');
                $kb['priority_url'] = esc_url_raw($_POST['priority_url'] ?? '');
                update_option('aalite_kb_settings', $kb);
                add_settings_error('aalite', 'kb_saved', __('KB settings saved.', 'ask-adam-lite'), 'updated');
            }
            if (isset($_POST['kb_repair'])) { Ask_Adam_Lite_KB::maybe_install_db(); add_settings_error('aalite','kb_repair',__('KB tables checked.','ask-adam-lite'), 'updated'); }
            if (isset($_POST['kb_purge']))  { Ask_Adam_Lite_KB::purge_index();       add_settings_error('aalite','kb_purge', __('KB purged.','ask-adam-lite'), 'updated'); }
            if (isset($_POST['kb_crawl']))  { Ask_Adam_Lite_KB::crawl_from_settings(); add_settings_error('aalite','kb_crawl', __('Crawl finished.','ask-adam-lite'), 'updated'); }
            if (isset($_POST['kb_embed']))  { Ask_Adam_Lite_KB::embed_pending();     add_settings_error('aalite','kb_embed', __('Embedding finished.','ask-adam-lite'), 'updated'); }
        }
    }

    public function render() {
        if (!current_user_can('manage_options')) wp_die('Nope');

        $api = get_option('aalite_api_settings', []);
        $w   = get_option('aalite_widget_settings', ['enabled'=>1,'position'=>'bottom-right','assistant_name'=>'Adam','avatar_url'=>'']);
        $kb  = get_option('aalite_kb_settings', ['sitemap_url'=>'', 'priority_url'=>'']);

        settings_errors('aalite'); ?>
        <div class="wrap adam-admin is-light">
          <!-- Tabs nav -->
          <nav class="adam-tabs">
            <ul class="adam-tablist">
              <li><button class="adam-tab is-active" data-tab="assistant">Assistant</button></li>
              <li><button class="adam-tab" data-tab="widget">Widget</button></li>
              <li><button class="adam-tab" data-tab="kb">Knowledge Base</button></li>
              <li><button class="adam-tab pro" data-tab="providers">Providers (Pro)</button></li>
              <li><button class="adam-tab pro" data-tab="web">Web Search (Pro)</button></li>
              <li><button class="adam-tab pro" data-tab="profiles">Profiles (Pro)</button></li>
              <li><button class="adam-tab pro" data-tab="theme">Theme (Pro)</button></li>
            </ul>
          </nav>

          <!-- Assistant -->
          <section class="adam-tabpanel" data-panel="assistant">
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
              <h2>Widget</h2>
              <div class="anna-grid">
                <div>
                  <label class="anna-label">Enable</label>
                  <select class="anna-select" name="enabled">
                    <option value="1" <?php selected((int)$w['enabled'],1); ?>>On</option>
                    <option value="0" <?php selected((int)$w['enabled'],0); ?>>Off</option>
                  </select>
                </div>
                <div>
                  <label class="anna-label">Position</label>
                  <select class="anna-select" name="position">
                    <option value="bottom-right" <?php selected($w['position'],'bottom-right'); ?>>Bottom Right</option>
                    <option value="bottom-left"  <?php selected($w['position'],'bottom-left');  ?>>Bottom Left</option>
                  </select>
                </div>
                <div>
                  <label class="anna-label">Assistant Name</label>
                  <input class="anna-input" name="assistant_name" value="<?php echo esc_attr($w['assistant_name']); ?>">
                </div>
                <div>
                  <label class="anna-label">Avatar URL</label>
                  <input class="anna-input" type="url" name="avatar_url" value="<?php echo esc_url($w['avatar_url']); ?>">
                </div>
              </div>
              <div class="anna-actions">
                <button class="anna-btn" name="save_widget" value="1">Save Widget</button>
              </div>
            </form>
          </section>

          <!-- KB -->
          <section class="adam-tabpanel" data-panel="kb" hidden>
            <form method="post" class="anna-card">
              <?php wp_nonce_field('aalite_save'); ?>
              <input type="hidden" name="_aalite_flag" value="1">
              <h2>Knowledge Base (Lite)</h2>
              <p class="anna-hint">Lite indexes the first sitemap URL and the first priority URL. Caps: 50 pages, 300 chunks.</p>

              <label class="anna-label">Sitemap URL</label>
              <input class="anna-input" type="url" name="sitemap_url" value="<?php echo esc_url($kb['sitemap_url']); ?>" placeholder="https://example.com/sitemap.xml">

              <label class="anna-label">Priority URL</label>
              <input class="anna-input" type="url" name="priority_url" value="<?php echo esc_url($kb['priority_url']); ?>" placeholder="https://example.com/important-page/">

              <div class="anna-actions">
                <button class="anna-btn" name="save_kb" value="1">Save KB</button>
                <button class="anna-btn secondary" name="kb_repair" value="1" type="submit">Repair Tables</button>
                <button class="anna-btn secondary" name="kb_purge" value="1" type="submit">Purge</button>
                <button class="anna-btn accent"   name="kb_crawl" value="1" type="submit">Crawl</button>
                <button class="anna-btn accent"   name="kb_embed" value="1" type="submit">Embed</button>
              </div>
            </form>
          </section>

          <!-- Providers (Pro) -->
          <section class="adam-tabpanel" data-panel="providers" hidden>
            <?php
              echo $this->pro_card(
                'Connect to more AI providers and choose the best model for each task.',
                [
                  'Anthropic (Claude) support and routing',
                  'xAI and custom OpenAI-compatible endpoints',
                  'Per-provider fallbacks and timeouts',
                  'Usage dashboards and per-site limits',
                ]
              );
            ?>
          </section>

          <!-- Web Search  -->
          <section class="adam-tabpanel" data-panel="web" hidden>
            <?php
              echo $this->pro_card(
                'Real-time web search for fresher answers with citations.',
                [
                  'Brave Search integration (configurable)',
                  'Blended results with your Knowledge Base',
                  'Citations with titles and favicons',
                  'Query budgets and safe-mode controls',
                ]
              );
            ?>
          </section>

          <!-- Profiles (Pro) -->
          <section class="adam-tabpanel" data-panel="profiles" hidden>
            <?php
              echo $this->pro_card(
                'Reusable profiles for tone, limits, and specialty prompts.',
                [
                  'Temperature, max tokens, and Top-K controls',
                  'System prompts per profile',
                  'Per-page or shortcode profile switcher',
                  'Multi-site presets you can export/import',
                ]
              );
            ?>
          </section>

          <!-- Theme (Pro) -->
          <section class="adam-tabpanel" data-panel="theme" hidden>
            <?php
              echo $this->pro_card(
                'Fully customize the chat widget style to match your brand.',
                [
                  'Theme presets and color pickers',
                  'Upgraded UI/UX',
                  'Compact vs. spacious layout',
                  'Custom CSS variables per site',
                  'Accessibility contrast checker',
                ]
              );
            ?>
          </section>
        </div>
        <?php
    }

    /** Renders a marketing card for Pro tabs (no settings, just synopsis + CTA) */
    private function pro_card($lead, array $bullets = []) {
        ob_start(); ?>
        <div class="anna-card pro-card">
          <h2>Ask Adam Pro</h2>
          <p class="anna-muted"><?php echo esc_html($lead); ?></p>
          <?php if ($bullets): ?>
            <ul class="anna-list">
              <?php foreach ($bullets as $b): ?>
                <li>✔ <?php echo esc_html($b); ?></li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
          <div class="anna-actions">
            <a class="anna-btn accent" href="<?php echo esc_url(self::PRO_URL); ?>" target="_blank" rel="noopener">Get Pro</a>
          </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
new Ask_Adam_Lite_Admin();
