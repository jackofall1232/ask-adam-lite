=== Ask Adam Lite ===
Contributors: jackofall1232
Tags: ai, chatbot, assistant, openai
Requires at least: 5.8
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Ask Adam Lite is a secure, minimal AI chat widget and lightweight knowledge base for WordPress.

== Description ==

Ask Adam Lite makes it easy to add an AI-powered assistant to your WordPress site.
It works out of the box with your own OpenAI API key and provides a simple, standalone solution.
You can display it as a floating widget or drop it into any page or post using a shortcode.

Features in Lite:
- Floating AI chat widget or [ask_adam_lite] shortcode
- Works with your own OpenAI API key (no extra accounts required)
- Basic knowledge base: index up to one sitemap and one priority URL
- Simple admin settings for position, assistant name, and avatar
- Privacy-friendly — no data collection or tracking of your visitors
- Lightweight, fast, and secure — follows WordPress Coding Standards

Need more features?
Ask Adam Lite is fully functional on its own.
If you’d like advanced options such as multiple AI providers, real-time web search, custom chat themes, or multi-profile assistants, those are available in the Pro version at https://www.askadamit.com

== Installation ==

1. Upload the plugin ZIP file via Plugins → Add New → Upload Plugin.
2. Activate the plugin through the Plugins menu in WordPress.
3. Go to Ask Adam Lite → Settings and enter your OpenAI API key.
4. Use the [ask_adam_lite] shortcode on any page, or enable the floating widget.

== Frequently Asked Questions ==

= Do I need an OpenAI account? =
Yes. You’ll need your own OpenAI API key. No additional subscription is required.

= Does Ask Adam Lite work without Pro? =
Yes. Lite is a complete standalone version that provides a working AI chat widget and small knowledge base.

= Is any data sent to your servers? =
No. All requests go directly from your WordPress site to OpenAI’s API. No data is stored or transmitted anywhere else.

= How do I upgrade to Pro? =
Pro adds support for multiple AI providers, web search, custom themes, and multi-profile assistants.
Learn more at https://www.askadamit.com

== Screenshots ==

1. Floating chat widget on a WordPress site
2. Shortcode embed example
3. Lite settings panel in WordPress admin

== External Services ==

This plugin connects directly to the OpenAI API to generate chat responses and create semantic embeddings for knowledge-base search.

Data sent:
- Text prompts entered by site visitors or admins.
- Optional content indexed by the site owner (titles, excerpts, or text).
- Images uploaded by site visitors (base64-encoded), when vision is used.
  Images are sent directly to OpenAI and are never stored on your server
  or in the WordPress database.

Destination:
https://api.openai.com

Purpose:
To generate AI responses and semantic vectors for local search.

User data handling:
- No personally identifying information is sent unless a user explicitly types it.
- Site owners control what content is indexed.
- The plugin does not store chat logs or personal data.

== Privacy ==

Ask Adam Lite does not track, log, or store user data.
All AI interactions are handled directly through your OpenAI account.
The plugin is compatible with WordPress privacy guidelines and GDPR when used responsibly.

== Changelog ==

= 2.0.0 =
* Architecture: Introduced centralized Ask_Adam_Lite_Model_Config class
  for all model and endpoint management. No more hardcoded model strings
  in business logic.
* GPT-5 Support: Full Responses API support for GPT-5 and later models.
  Endpoint routing is automatic based on model prefix.
* Vision: Image upload support added to both the floating widget and the
  [ask_adam_lite] shortcode. Accepts JPEG, PNG, GIF, and WebP up to 5MB.
  Images are sent directly to OpenAI — never stored on disk or in the database.
* Reasoning Models: o1 and o3 models now correctly use max_completion_tokens
  and omit unsupported parameters (temperature, max_tokens).
* Knowledge Base: Embedding model is now configurable via WordPress options.
  Mismatch detection warns admins when the active model differs from the
  indexed model. Upgraded sites are automatically backfilled on activation.
* Security: Removed jQuery dependency from front-end script. Inline widget
  styles moved to enqueued stylesheet. Inline onclick handler removed.
* Bug Fix: Admin CSS filename mismatch corrected — admin styles now load
  reliably on all configurations.
* Bug Fix: Shortcode embed now correctly injects REST URL and nonce even
  when the floating widget is disabled.
* Bug Fix: Uninstall routine consolidated into uninstall.php — all plugin
  options including new model options are now fully cleaned up on removal.
* Compliance: All changes tested against WordPress Coding Standards and
  Plugin Check. PHP 7.4+ compatible throughout.

= 1.0.5 =
* Compatibility: Fully tested with WordPress 6.9.
* Maintenance: General cleanup and version bump for WP.org.

= 1.0.4 =
* Assets: Added WordPress.org banner and icon.
* Deployment: Added GitHub Actions workflow for automatic WP.org deployment.
* Polish: Minor UI cleanup and improved file organization.

= 1.0.3 =
* UI Improvements: Refined floating chat widget for better alignment.
* Accessibility: Added Escape key to close the widget and improved ARIA states.
* Stability: Simplified inline JavaScript for reliable open/close behavior.
* CSS Cleanup: Reduced redundant selectors and fixed FAB centering.
* Compliance: Removed powered-by and credit links from all front-end displays.
* Encoding Fix: Normalized placeholder text and labels to plain UTF-8.
* Code Quality: Removed unused markup and improved escaping.

= 1.0.2 =
* Sanitized input handling and improved database cleanup.
* Fixed inline script handling and added proper versioning to enqueue calls.
* Resolved WordPress Coding Standards issues and improved uninstall routine.

= 1.0.1 =
* Improved shortcode rendering and widget script loading.
* General cleanup for WordPress.org compliance.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 2.0.0 =
Major update. Adds GPT-5 and vision support, fixes admin CSS loading,
shortcode REST injection, and uninstall cleanup. Re-run Crawl and Embed
in the Knowledge Base tab after upgrading if you use the KB feature.

= 1.0.5 =
Tested for compatibility with WordPress 6.9. Recommended update for all users.
