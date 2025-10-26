=== Ask Adam Lite ===
Contributors: jackofall1232
Tags: ai, chatbot, assistant, openai
Requires at least: 5.8
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Ask Adam Lite is a secure, minimal AI chat widget and lightweight knowledge base for WordPress.

== Description ==

Ask Adam Lite makes it easy to add an AI-powered assistant to your WordPress site.  
It works out of the box with your own OpenAI API key and provides a simple, standalone solution.  
You can display it as a floating widget or drop it into any page or post using a shortcode.

**Features in Lite:**
- Floating AI chat widget or `[ask_adam_lite]` shortcode
- Works with your own OpenAI API key (no extra accounts)
- Basic knowledge base: index up to one sitemap and one priority URL
- Simple admin settings for position, assistant name, and avatar
- Privacy-friendly — no data collection or tracking of your visitors
- Lightweight, fast, and secure — built to WordPress Coding Standards

**Need more features?**  
Ask Adam Lite is fully functional on its own.  
If you’d like advanced options such as multiple AI providers, real-time web search, custom chat themes, or multi-profile assistants, those are available in the Pro version at [askadamit.com](https://www.askadamit.com).

== Installation ==

1. Upload the plugin ZIP file via **Plugins → Add New → Upload Plugin**.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Go to **Ask Adam Lite → Settings** and enter your OpenAI API key.
4. Use the `[ask_adam_lite]` shortcode on any page, or enable the floating widget.

== Frequently Asked Questions ==

= Do I need an OpenAI account? =
Yes. You’ll need your own OpenAI API key.  
No other external accounts or subscriptions are required.

= Does Ask Adam Lite work without Pro? =
Yes. Lite is a complete standalone version that provides a working AI chat widget and small knowledge base without any additional services.

= Is any data sent to your servers? =
No. All requests go directly from your WordPress site to OpenAI’s API.  
No data is stored or transmitted anywhere else.

= How do I upgrade to Pro? =
Pro adds advanced features like multiple AI providers, real-time search, customizable themes, and user profiles.  
You can learn more at [askadamit.com](https://www.askadamit.com).

== Screenshots ==

1. Floating chat widget on a WordPress site  
2. Shortcode embed example  
3. Lite settings panel in WordPress admin

== External Services ==

This plugin connects directly to the OpenAI API to generate chat responses and create semantic embeddings for knowledge-base search.

**Data sent:**
- Text prompts entered by site visitors or admins.
- Optional content indexed by the site owner for knowledge-base use (titles, excerpts, or text).

**Destination:**
- https://api.openai.com

**Purpose:**
- To generate AI responses and semantic vectors for local search.

**User data handling:**
- No personally identifying information is sent unless a user explicitly types it in.
- Site owners have full control over what content is indexed.
- The plugin stores no chat history or personal data.

**Policies:**
- [OpenAI Terms of Use](https://openai.com/policies/terms-of-use)  
- [OpenAI Privacy Policy](https://openai.com/policies/privacy-policy)

== Privacy ==

Ask Adam Lite does not track, log, or store user data.  
All AI interactions are handled directly through your OpenAI account.  
The plugin is fully compliant with WordPress privacy guidelines and the GDPR when used responsibly.

== Changelog ==

= 1.0.3 =
* **UI Improvements:** Refined floating chat widget for better alignment on left and right positions.
* **Accessibility:** Added keyboard Escape handler and improved ARIA attributes for toggle states.
* **Stability:** Simplified inline JavaScript for reliable open/close behavior.
* **CSS Cleanup:** Reduced redundant selectors, improved transitions, and fixed FAB centering.
* **Compliance:** Removed powered-by and credit links from all front-end displays per WordPress.org rules.
* **Encoding Fix:** Normalized placeholder text and labels to plain UTF-8 (no emoji or special symbols).
* **Code Quality:** Removed unused `.anna-footnote` markup and improved escaping for dynamic content.

= 1.0.2 =
* **Security/Compliance:** Sanitized all `$_POST` and `$_SERVER` inputs with proper `wp_unslash()` and validation.
* **Database:** Added `phpcs:ignore` documentation for custom table queries with explanations.
* **Widget:** Fixed heredoc syntax issues and improved inline JavaScript output buffering.
* **Enqueue:** Added version parameter (`AALITE_VER`) to all script registrations.
* **Code Quality:** Resolved all WordPress Coding Standards warnings flagged by Plugin Check.
* **Uninstall:** Properly documented schema cleanup.
* Tested with `WP_DEBUG` enabled on a clean installation.

= 1.0.1 =
* Added contributor **jackofall1232** to readme.
* Improved shortcode rendering and escaping.
* Updated widget JavaScript handling with `wp_enqueue_script` and `wp_add_inline_script`.
* Admin assets now properly enqueued via `admin_enqueue_scripts`.
* General code cleanup for WordPress.org compliance.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.3 =
This update improves layout alignment, accessibility, and WordPress.org compliance.  
All credit links have been removed, and the widget is now fully polished for public release.
