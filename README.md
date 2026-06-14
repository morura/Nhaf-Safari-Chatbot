# Nomad Horizons Safari AI Chatbot

AI-powered chat assistant for African safari affiliate websites. Answers visitor questions using OpenAI, Anthropic Claude, or a local Ollama model, grounded in content crawled from Safari.com, and converts conversations into booking leads with affiliate tracking.

- **Version:** 1.0.2
- **Requires:** WordPress 5.8+, PHP 7.4+ with `curl`, `json`, `openssl`
- **License:** GPL-2.0-or-later

---

## Installation

1. Upload the `nhaf-safari-chatbot` folder to `/wp-content/plugins/`, or install the zip via **Plugins → Add New → Upload Plugin**.
2. Activate **Nomad Horizons Safari AI Chatbot**. Activation creates three database tables, seeds default options, and verifies the required PHP extensions.
3. Go to **Settings → Safari AI Chatbot** to configure.

## Configuration guide

### General
Enable/disable the widget, choose its corner and pixel offsets, set the welcome message, theme colors (primary / secondary / text), business name, contact email, and post types where the widget should be hidden.

### LLM Configuration
Pick the provider and enter its credentials:

| Provider | Required fields |
| --- | --- |
| OpenAI | API key, model (gpt-3.5-turbo, gpt-4, …), temperature, max tokens |
| Anthropic Claude | API key, model (Haiku / Sonnet), temperature |
| Ollama (local) | Endpoint URL (e.g. `http://localhost:11434`), model name |

API keys are stored **encrypted** (AES-256-CBC, per-site salt) and are never exposed to the browser; all LLM calls happen server-side. Use **Send test message** after saving to verify connectivity. The system prompt is editable and is sent with every request.

### Knowledge Base
Add Safari.com URLs (one per line) to crawl and index. Choose a daily/weekly/monthly recrawl schedule, run a crawl on demand, and review or clear indexed entries. Optionally enable real-time web search (SerpAPI or Google Custom Search, scoped to safari.com) to enrich answers.

### Safari.com API
Reuse the API token from the companion **Nomad Horizons Africa Safari Finder** plugin (detected automatically via its `get_token()` method or stored options), or enter a token directly. Select which endpoints (destinations, experiences, safaris) feed the chatbot context, and test the connection.

### Lead Management
Set the notification email, auto-responder text, affiliate base URL, and affiliate ID. The leads table shows every enquiry with a status dropdown (new → contacted → converted → closed) and a **CSV export** button. A monthly lead summary email is sent automatically.

### Security & Rate Limiting
Per-minute and per-day message limits per IP, max message length, IP blocklist, optional Google reCAPTCHA (v2/v3), and the **Remove data on uninstall** opt-in.

## Usage

The floating widget appears automatically on the front end when enabled. You can also use:

### Shortcodes

```text
[nhaf_chatbot_button text="Chat with our Safari AI"]   → inline button that opens the chat
[nhaf_chatbot_embed]                                   → embeds the chat window in page content
```

### Sidebar widget
Add **Safari AI Chat** under Appearance → Widgets.

### REST API

All routes live under `/wp-json/nhaf-chatbot/v1/`:

| Route | Method | Access | Purpose |
| --- | --- | --- | --- |
| `/chat` | POST | Public (nonce) | Send a message, get the AI reply |
| `/lead` | POST | Public (nonce) | Submit a booking enquiry |
| `/leads` | GET | Admin | List captured leads |
| `/crawl` | POST | Admin | Trigger a knowledge-base crawl |
| `/health` | GET | Public | Status check |

## Developer hooks

### Filters

| Filter | Purpose |
| --- | --- |
| `nhaf_chatbot_allowed_html` | HTML tags allowed in bot replies |
| `nhaf_chatbot_system_prompt` | Modify the system prompt |
| `nhaf_chatbot_llm_request_args` | Adjust outgoing LLM HTTP args |
| `nhaf_chatbot_before_response` | Filter the reply before it is returned |
| `nhaf_chatbot_knowledge_sources` | Add/remove context sources |
| `nhaf_chatbot_rate_limit` | Change rate-limit thresholds |
| `nhaf_chatbot_booking_detection_prompt` | Tune booking-intent keywords |
| `nhaf_chatbot_quick_replies` | Customize the quick-reply buttons |
| `nhaf_chatbot_should_display` | Control where the widget renders |

### Actions

| Action | Fires |
| --- | --- |
| `nhaf_chatbot_before_chat_render` | Before the widget container is printed |
| `nhaf_chatbot_after_lead_submit` | After a lead is stored (receives lead data) |
| `nhaf_chatbot_on_crawl_complete` | After a crawl finishes (receives summary) |
| `nhaf_chatbot_on_llm_error` | When an LLM request fails (receives `WP_Error`, provider) |

Example — forward new leads to Slack:

```php
add_action( 'nhaf_chatbot_after_lead_submit', function ( $lead ) {
	wp_remote_post( 'https://hooks.slack.com/services/XXX/YYY/ZZZ', array(
		'headers' => array( 'Content-Type' => 'application/json' ),
		'body'    => wp_json_encode( array(
			'text' => sprintf( 'New safari lead: %s <%s> — %s', $lead['name'], $lead['email'], $lead['reference'] ),
		) ),
	) );
} );
```

## Testing locally

- **Ollama:** install Ollama, run `ollama run llama2`, set provider to *Ollama* with endpoint `http://localhost:11434` — no API key needed.
- **Mock server:** point `nhaf_chatbot_llm_request_args` at a local mock, or use the fixture responses in `tests/fixtures/` with the PHPUnit scaffold in `tests/`.
- Run `php -l` across files or `composer test` if you wire up the included `tests/bootstrap.php` to the WP test suite.

## Troubleshooting

| Symptom | Check |
| --- | --- |
| "I'm still learning about safaris…" replies | No LLM provider is configured — add an API key under LLM Configuration |
| No reply / friendly error message | Provider outage or invalid key; the admin is emailed (max once/hour). Enable `WP_DEBUG` for logs |
| Widget not visible | Chatbot enabled? Current post type excluded? Theme prints `wp_footer()`? |
| Crawl indexes nothing | URLs reachable from the server? Some hosts block outbound requests |
| 429 errors in console | Rate limit reached — raise limits under Security & Rate Limiting |
| reCAPTCHA failures | Verify site/secret keys match the chosen version (v2 vs v3) |

Error details are written to `wp-content/debug.log` when `WP_DEBUG` and `WP_DEBUG_LOG` are enabled.

## Uninstalling

Deactivation preserves all data and only clears scheduled tasks. Deleting the plugin removes tables, options, and transients **only if** "Remove data on uninstall" was enabled under Security & Rate Limiting.
