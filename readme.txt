=== Ask Adam Lite ===
Contributors: jackofall1232
Tags: ai, chatbot, assistant, openai
Requires at least: 5.8
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Ask Adam Lite is a secure, minimal AI chat widget and lightweight knowledge base for WordPress.

== Description ==

Ask Adam Lite makes it easy to add an AI-powered assistant to your WordPress site.  
It works out of the box with your own OpenAI API key and provides a simple, standalone solution.  
You can display it as a floating widget or drop it into any page/post with the shortcode.

**Features in Lite:**
- Floating AI chat widget or `[ask_adam_lite]` shortcode
- Uses OpenAI (GPT-4o mini) with your own API key
- Basic knowledge base: index up to 1 sitemap and 1 priority URL
- Simple settings for position, name, and avatar
- No external accounts or subscriptions beyond your OpenAI key

**Need more?**  
Ask Adam Lite is fully functional as-is.  
If you want to expand with multiple AI providers, real-time web search, advanced profiles, or theme customization, those features are available in the Pro version at [askadamit.com](https://www.askadamit.com).

== Installation ==

1. Upload the ZIP via **Plugins → Add New → Upload Plugin**.
2. Activate the plugin.
3. Go to **Ask Adam Lite → Settings** and enter your OpenAI API key.
4. Use the `[ask_adam_lite]` shortcode or enable the floating widget.

== Frequently Asked Questions ==

= Do I need an OpenAI account? =
Yes. You'll need your own OpenAI API key (no other account required).

= Does Ask Adam Lite work without Pro? =
Yes. Lite is a standalone product. It gives you a working chat widget and a small knowledge base with no external services beyond your OpenAI key.

= How do I upgrade to Pro? =
Pro adds advanced features like multiple providers, real-time search, profiles, and theme controls.  
You can learn more at [askadamit.com](https://www.askadamit.com).

== Screenshots ==

1. Floating chat widget on a WordPress site  
2. Shortcode embed example  
3. Lite admin settings panel

== External services ==

This plugin connects to the OpenAI API to provide AI-generated chat responses and to create semantic embeddings for knowledge-base search.

**Data sent:**
- Text prompts entered by site visitors or admins.
- Site content chosen by the administrator for indexing (titles, excerpts, or page text).

**Destination:**
- https://api.openai.com

**Purpose:**
- Generate chat responses and semantic vectors for search.

**User data handling:**
- No personally identifying information is sent unless a user explicitly types it in.
- Site owners control when and what content is indexed.

**Policies:**
- [OpenAI Terms of Use](https://openai.com/policies/terms-of-use)
- [OpenAI Privacy Policy](https://openai.com/policies/privacy-policy)

== Changelog ==

= 1.0.2 =
* **Security/Compliance:** Sanitized all `$_POST` and `$_SERVER` inputs with proper `wp_unslash()` and validation.
* **Database:** Added proper `phpcs:ignore` documentation for custom table queries with clear explanations.
* **Widget:** Fixed heredoc syntax issues - now using output buffering for inline JavaScript.
* **Enqueue:** Added version parameter (`AALITE_VER`) to all script registrations.
* **Code Quality:** Resolved all WordPress Coding Standards warnings flagged by Plugin Check.
* **Uninstall:** Properly documented schema changes during plugin cleanup.
* Tested with WP_DEBUG enabled on clean WordPress installation.

= 1.0.1 =
* Added contributor **jackofall1232** to readme.
* Improved shortcode rendering and output escaping.
* Updated widget JavaScript handling to use `wp_enqueue_script` and `wp_add_inline_script`.
* Admin assets now properly enqueued via `admin_enqueue_scripts`.
* General code cleanup for WordPress.org compliance.

= 1.0.0 =
* Initial release.
