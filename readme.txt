=== Scanvexa SEO — AI Website Analyzer ===
Contributors: deep7197
Tags: seo, ai seo, schema, sitemap, meta tags
Requires at least: 7.0
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.0.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI-powered SEO agent for WordPress. Connect your own AI provider, scan your site, and optimize your SEO effortlessly.

== Description ==

**Scanvexa SEO — AI Website Analyzer** is a modern, intelligent WordPress SEO plugin designed to audit, optimize, and streamline your search engine performance using artificial intelligence.

Bring Your Own Key (BYOK) to connect directly with leading AI providers—including **OpenAI (ChatGPT)**, **Anthropic (Claude)**, and **Google Gemini**—to generate high-converting meta titles, meta descriptions, image ALT text, and structured data schemas with no middleman markup or vendor lock-in.

### Key Features

* **Bring Your Own Key (BYOK)**: Connect directly to OpenAI, Anthropic Claude, or Google Gemini. Your API keys are stored in your own WordPress database and are never sent anywhere except the AI provider you configure.
* **Full-Site SEO Audits**: Automatically detect technical SEO problems, missing meta tags, broken structures, and content opportunities across your entire site.
* **AI Meta Title & Description Generator**: Generate catchy, keyword-rich meta tags tailored to your content's topic and tone.
* **Automated Image ALT Text**: Analyze images and generate descriptive, accessibility-compliant, and SEO-friendly ALT attributes.
* **Rich Schema & Structured Data**: Built-in JSON-LD schema support for Articles, Organizations, WebSite, Breadcrumbs, FAQs, and more.
* **XML Sitemaps**: Dynamic, fast, and search engine-compliant sitemaps for posts, pages, custom post types, and taxonomies.
* **Social Previews & Open Graph**: Full support for Open Graph and Twitter Card tags to ensure stunning previews across social networks.
* **Real-time Cost & Token Tracker**: Keep complete visibility and control over your AI usage with request logs and estimated spend tracking.
* **Privacy & Control First**: Your content is only sent to the AI provider you configure when you request generation. No unexpected background data leaks.

== Installation ==

1. Upload the `scanvexa-seo` folder to your `/wp-content/plugins/` directory, or install it directly via the WordPress Plugins screen.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Navigate to **Scanvexa SEO — AI Website Analyzer > Settings > AI Provider** in your WordPress admin menu.
4. Enter your API key for OpenAI, Anthropic, or Google Gemini and save your settings.
5. Run your first site scan from the **Scanvexa SEO — AI Website Analyzer > Dashboard**!

== Frequently Asked Questions ==

= Do I need an AI API key to use this plugin? =
Basic SEO features like XML Sitemaps, Schema generation, and standard metadata work out of the box. To utilize the AI generation features (meta titles, descriptions, ALT text), you will need an API key from OpenAI, Anthropic, or Google Gemini.

= Are my API keys secure? =
Your API keys are stored in your own WordPress database (the same options used by WordPress core's AI Client), never in plaintext in the page source, and are only ever transmitted to the official AI provider endpoint you configure — never to us or any other third party.

= Which AI models are supported? =
Scanvexa SEO — AI Website Analyzer supports models from OpenAI (such as GPT-4o and GPT-4o-mini), Anthropic (Claude 3.5 Sonnet and Haiku), and Google Gemini (Gemini 1.5 Pro and Flash).

= Does this plugin work with page builders? =
Yes! Scanvexa SEO — AI Website Analyzer is compatible with the WordPress Block Editor (Gutenberg), Classic Editor, Elementor, and major page builders.

== External services ==

This plugin is Bring-Your-Own-Key (BYOK): it does not call any AI service on its own, and never in the background. A request is sent only when you explicitly click an AI generation action (title, meta description, ALT text, keyword suggestions, or schema markup) in the editor or dashboard, for the one piece of content you're acting on, to whichever provider you have connected and selected as active. No request is ever made without that click.

What is sent: the relevant content for the feature you triggered (e.g. the post title, excerpt/content, and permalink for a meta description request, or the image for ALT text) and the plugin's instructions to the AI model. No content is sent for posts you are not actively generating for.

This plugin supports three providers; only the one(s) you add a key for are ever contacted:

* **OpenAI** — used to generate titles, meta descriptions, ALT text, keyword suggestions, and schema markup when OpenAI is your selected provider.
Terms of Use: https://openai.com/policies/row-terms-of-use/ — Privacy Policy: https://openai.com/policies/privacy-policy/
* **Anthropic (Claude)** — same features, when Anthropic is your selected provider.
Terms of Service: https://www.anthropic.com/legal/consumer-terms — Privacy Policy: https://www.anthropic.com/legal/privacy
* **Google Gemini** — same features, when Google is your selected provider.
Terms of Service: https://ai.google.dev/gemini-api/terms — Privacy Policy: https://policies.google.com/privacy

Your API key for each provider is stored in your own WordPress database and is sent only to that provider's own API endpoint, never to us.

== Screenshots ==

1. Main Dashboard showing SEO Health Score, category breakdown, and quick actions.
2. Site Audit & Issue Tracker categorizing high, medium, and low priority fixes.
3. AI Provider Settings with secure Bring-Your-Own-Key configuration.
4. Content editor SEO & Schema meta box with AI generation tools.

== Changelog ==
= 1.0.2 =
* Renamed plugin.

= 1.0.1 =
* Code Improvements.

= 1.0.0 =
* Initial release of Scanvexa SEO — AI Website Analyzer.
* Multi-provider AI support (OpenAI, Anthropic Claude, Google Gemini).
* Complete site audit and issue diagnosis engine.
* Automated AI meta tag and ALT text generators.
* JSON-LD Schema integration and XML Sitemap generator.
* Real-time token usage and cost tracking dashboard.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
