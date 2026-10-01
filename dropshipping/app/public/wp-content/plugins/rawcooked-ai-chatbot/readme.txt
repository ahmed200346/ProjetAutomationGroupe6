=== RawCooked AI Chatbot ===
Contributors: rawcooked
Tags: chatbot, ai, woocommerce, assistant, rag
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.5.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI chatbot with RAG on your content and WooCommerce products. Bring your own key: Gemini (free tier), Claude, OpenAI, or local Ollama.

== Description ==

RawCooked is an AI assistant for your WordPress site. It reads your pages, posts and WooCommerce products, then answers your visitors' questions and guides them to the right page or product — in their own language.

**Bring your own AI provider:**

* **Google Gemini** — has a free tier, perfect to start at zero cost
* **Anthropic Claude**
* **OpenAI**
* **Ollama** — run Llama 3 and other open models locally, no API key at all

**Retrieval-Augmented Generation (RAG):**

* Indexes products (name, price, stock, description), pages and posts
* Semantic search with embeddings stored in your own database — no external vector service
* Falls back to keyword search when no embedding provider is configured
* Auto re-index on save + nightly refresh

**WooCommerce tools:**

* Live product search (real-time price and stock)
* Order status — strictly limited to the logged-in customer's own orders

**Three ways to display it:**

* Floating bubble in the corner (round, soft or square style, your color)
* Side panel sliding from the screen edge
* Dedicated page via the `[rafiq_chatbot]` shortcode

**Privacy by design:**

* API calls are made server-side; your API key never reaches the browser
* The model can only see data of the visitor it is talking to
* No internal IDs, emails or other customers' data in answers
* Per-IP rate limiting protects your API bill

== Installation ==

1. Upload the plugin and activate it.
2. Open **RawCooked AI** in the admin menu.
3. Pick a provider and paste your API key (Gemini has a free tier at aistudio.google.com), or point it to your Ollama server.
4. Open the **Knowledge** tab and click **Index now**.
5. Done — the widget appears on your site.

== External Services ==

This plugin connects to the AI provider **you choose and configure** in the settings. No data is sent anywhere until you enter an API key (or an Ollama URL) and enable the bot.

Depending on your choice, the plugin sends visitor chat messages, recent conversation context, and excerpts of your own site content (for indexing and retrieval) to:

* **Google Gemini API** (generativelanguage.googleapis.com) — [Terms](https://ai.google.dev/gemini-api/terms), [Privacy](https://policies.google.com/privacy)
* **Anthropic API** (api.anthropic.com) — [Terms](https://www.anthropic.com/legal/consumer-terms), [Privacy](https://www.anthropic.com/legal/privacy)
* **OpenAI API** (api.openai.com) — [Terms](https://openai.com/policies/terms-of-use), [Privacy](https://openai.com/policies/privacy-policy)
* **Ollama** — a server you host yourself (local or private); no data leaves your infrastructure.

API keys are stored in your WordPress database and used only server-side; they are never exposed to visitors' browsers. No analytics or telemetry is sent to the plugin author.

== Frequently Asked Questions ==

= Do I need to pay for an AI service? =

No. Google Gemini offers a free tier, and Ollama lets you run open models on your own machine for free.

= Where is my data stored? =

Everything (content chunks and embeddings) is stored in your own WordPress database. Conversations are kept in the visitor's browser session only.

= Does it need WooCommerce? =

No. Without WooCommerce it answers from your pages and posts. With WooCommerce it also searches products and order status.

== Screenshots ==

1. The floating chat bubble answering a product question with live links.
2. Five themes: Classic, Midnight, Glass, Gradient and Minimal.
3. The Conversations tab: 7-day stats, transcripts and lead export.
4. Provider settings: bring your own key — Gemini, Claude, OpenAI or local Ollama.
5. Mobile bottom-sheet layout: the site stays visible while chatting.

== Changelog ==

= 1.5.0 =
* Renamed to RawCooked AI Chatbot (new text domain rawcooked-ai-chatbot) to avoid confusion with an unrelated product. No functional changes.

= 1.4.1 =
* Fix: Google retired the text-embedding-004 model — the default and previously saved settings migrate automatically to gemini-embedding-001 (768 dimensions). Use "Rebuild from scratch" once after updating to re-embed your content.

= 1.4.0 =
* New: teaser bubble — a small dismissible speech bubble next to the launcher, less intrusive than auto-open, with its own delay setting and a JS API for add-ons.

= 1.3.1 =
* New: public widget JS API (window.RafiqWidget.open) and local assistant messages, used by add-ons for proactive engagement.

= 1.3.0 =
* New: extension hooks (tools, system prompt, chat result, settings, admin tabs/panels) so add-ons can extend the bot without modifying it.
* New: optional Freemius integration for add-on licensing (loads only when the SDK is bundled).

= 1.2.1 =
* Plugin Check compliance pass: fully prepared SQL in the keyword-search fallback, prefixed globals in uninstall, block/shortcode rendering during indexing instead of the_content filter, removed the deprecated load_plugin_textdomain call.

= 1.2.0 =
* Fix: hardened widget CSS against aggressive theme styles (broken send icon, oversized input, dark-on-dark text).
* New: 4 open/close animations (pop, slide, fade, bounce) and adjustable Glass blur intensity.
* New: mobile layout choice — full screen or bottom-sheet (site stays visible).
* New: unread badge on the launcher when a reply arrives while the chat is closed.
* New: custom avatar image (hops when a reply lands) and per-page widget exclusion.

= 1.1.0 =
* New: 5 widget themes — Classic, Midnight (dark), Glass, Gradient, Minimal — with theme picker cards in the admin.
* New: Conversations tab — 7-day stats, transcripts, "% unanswered by site content" insight, GDPR retention with nightly auto-purge.
* New: lead capture (optional or required email before chatting) with CSV export.
* New: suggested questions chips, proactive auto-open, personality tone (friendly / professional / playful).

= 1.0.1 =
* Fix: "auto" embeddings no longer assume a local Ollama server exists; Ollama is only auto-selected when it is the chat provider.
* Fix: when embeddings fail, content is still indexed for keyword search instead of being skipped, with one aggregated warning.

= 1.0.0 =
* Initial release: multi-provider chat, RAG with local vector store, WooCommerce tools, bubble/sidebar/page display modes.
