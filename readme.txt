=== Nomad Horizons Safari AI Chatbot ===
Contributors: nomadhorizonsafrica
Tags: chatbot, ai, safari, leads, openai, anthropic, ollama
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI-powered safari assistant chatbot with OpenAI/Claude/Ollama support, Safari.com knowledge base, and booking lead capture with affiliate tracking.

== Description ==

Adds a floating AI chat widget to your safari affiliate website. Visitors can ask about destinations, experiences and trip planning; the assistant answers using your chosen LLM provider, grounded in content crawled from Safari.com. Booking intent triggers an in-chat enquiry form that emails you, stores the lead, and hands the visitor an affiliate-tracked booking link.

= Features =

* Three LLM providers: OpenAI, Anthropic Claude, Ollama (local)
* Knowledge base crawler with scheduled recrawls and optional live web search
* Reuses the Safari.com API token from the Safari Finder plugin
* Booking lead capture, status workflow, CSV export, monthly summary email
* Encrypted API key storage, nonces, rate limiting, IP blocklist, reCAPTCHA
* Shortcodes, sidebar widget, REST API, and developer hooks
* Accessible, mobile-friendly widget with quick replies and typing indicator

== Installation ==

1. Upload the plugin to `/wp-content/plugins/` and activate it.
2. Configure under Settings → Safari AI Chatbot.
3. Add an LLM API key and (optionally) crawl URLs, then test the connection.

== Frequently Asked Questions ==

= Are API keys safe? =
Keys are encrypted at rest and only used server-side; they are never sent to the browser.

= Does deleting the plugin remove my leads? =
Only if you enable "Remove data on uninstall" under Security & Rate Limiting. Otherwise all data is preserved.

== Changelog ==

= 1.0.3 =
* Fix: "Cookie check failed" error on settings save by using referer-independent nonce verification. Improves compatibility with servers/CDNs that strip HTTP_REFERER header.

= 1.0.2 =
* Feature: Auto-link destinations (Kenya, Tanzania, Serengeti, etc.) in chatbot responses to Safari.com with affiliate tracking.
* Feature: Display affiliate-tracked booking link prominently in booking confirmation.
* Fix: Change affiliate URL parameter from ?aff=ID to ?a=ID to match Safari.com format.

= 1.0.1 =
* Fix: fatal error when the Safari Finder plugin exposes a non-static get_token() method. Token lookup now uses reflection and is fully exception-guarded.

= 1.0.0 =
* Initial release.
