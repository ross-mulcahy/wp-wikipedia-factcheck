=== WP Wikipedia Fact-Check ===
Contributors: rossmulcahy
Tags: wikipedia, fact-check, gutenberg, research, editor
Requires at least: 6.9
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 1.0.16
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Wikipedia-powered fact-check panel for the Gutenberg block editor using the Wikimedia Enterprise API.

== Description ==

WP Wikipedia Fact-Check adds a research sidebar to the WordPress block editor. Look up Wikipedia articles, review credibility cues, and generate AI-powered research briefings without leaving the editor.

Built on the [Wikimedia Enterprise API](https://enterprise.wikimedia.com/).

**Features**

* **Editor sidebar panel** -- search Wikipedia directly from the block editor.
* **Selected-text assist** -- highlighted text auto-populates the search field.
* **Article summaries** -- view titles, abstracts, images, freshness cues, and topic tags.
* **Credibility badge** -- color-coded indicator based on Wikimedia's revert risk score.
* **AI topic suggestions** -- scan the current draft and suggest Wikipedia topics worth checking.
* **AI research brief** -- generate a compact briefing with key facts, angles, and cautions.
* **Wikidata links** -- jump to the Wikidata entry for any article.
* **Multi-language support** -- search across 8 Wikipedia languages (English, German, French, Spanish, Italian, Portuguese, Japanese, Chinese).
* **Response caching** -- lookups and AI results are cached to reduce repeat requests.
* **Fact Box block** -- insert a compact Wikipedia fact box into posts.
* **Tooltip block** -- add inline Wikipedia-sourced tooltips to highlighted phrases.
* **Connection test** -- verify your API credentials from the settings page.

**Requirements**

* [Wikimedia Enterprise](https://enterprise.wikimedia.com/) API credentials.
* For AI features on WordPress 6.9.x: the [AI](https://wordpress.org/plugins/ai/) plugin with at least one configured provider (WordPress 7.0+ includes the AI Client in core).

**Usage**

1. Open any post or page in the block editor.
2. Click the book icon in the editor toolbar or find Wikipedia Fact-Check in the sidebar panel list.
3. Type a search term, or select text in the editor and it will auto-populate.
4. Click Search to look up the term on Wikipedia.
5. Review the article summary, credibility badge, topic tags, and source links.
6. Click Suggest from draft to let AI propose Wikipedia topics based on the current article content.
7. Click one of the suggested topics to open the Wikipedia match and generate an AI Research Brief.

**How it works**

The plugin registers a Gutenberg sidebar panel that communicates with several REST API endpoints:

* `/wp-wikipedia-factcheck/v1/lookup` (POST, `edit_posts`) -- Search for a Wikipedia article by term.
* `/wp-wikipedia-factcheck/v1/test-connection` (POST, `manage_options`) -- Verify API credentials.
* `/wp-wikipedia-factcheck/v1/suggest-topics` (POST, `edit_posts`) -- Use AI to suggest Wikipedia search terms from the current draft.
* `/wp-wikipedia-factcheck/v1/briefing` (POST, `edit_posts`) -- Generate an AI research briefing for a Wikipedia match.
* `/wp-wikipedia-factcheck/v1/analyze` (POST, `edit_posts`) -- Compare selected draft text against a Wikipedia summary.
* `/wp-wikipedia-factcheck/v1/interesting-facts` (POST, `edit_posts`) -- Generate fact candidates from a Wikipedia article.
* `/wp-wikipedia-factcheck/v1/ai-health` (POST, `manage_options`) -- Return AI client diagnostics for debugging.

Wikipedia API calls are made server-side. Credentials are never exposed to the browser.

When multiple AI providers are configured, the plugin prefers OpenAI first, then Google, then Anthropic.

**Credibility scoring**

The credibility badge uses Wikimedia's revert risk score -- the probability that the current article revision will be reverted:

* 0 -- 0.15: High credibility (green)
* 0.15 -- 0.40: Moderate (amber)
* 0.40+: Flagged (red)
* No data: No score (grey)

== Installation ==

1. Upload the `wp-wikipedia-factcheck` folder to `wp-content/plugins/`.
2. Activate the plugin through the **Plugins** menu.
3. Go to **Settings > Wikipedia Fact-Check** and enter your Wikimedia Enterprise username and password.
4. Click **Test Connection** to verify your credentials.
5. Open any post in the block editor and look for the **Wikipedia Fact-Check** sidebar panel.

== Frequently Asked Questions ==

= Where do I get Wikimedia Enterprise credentials? =

Sign up at [enterprise.wikimedia.com](https://enterprise.wikimedia.com/). The On-demand API provides authenticated access to Wikipedia article data.

= Do I need the AI plugin? =

On WordPress 7.0+, the AI Client is built into core -- no extra plugin needed. On WordPress 6.9.x, install the [AI](https://wordpress.org/plugins/ai/) plugin and configure at least one provider. The core Wikipedia lookup, credibility badge, and article summaries work without any AI provider.

= Which AI providers are supported? =

When multiple providers are configured, the plugin prefers OpenAI, then Google, then Anthropic. Any single provider is sufficient.

= What does the credibility badge show? =

The badge uses Wikimedia's revert risk score -- the probability that the current article revision will be reverted. Scores below 0.15 show green (high credibility), 0.15-0.40 show amber (moderate), and above 0.40 show red (flagged).

== Screenshots ==

1. The Wikipedia Fact-Check sidebar in the block editor.
2. AI-suggested topics from the current draft.
3. A research briefing with key facts, angles, and cautions.
4. The settings page with connection test.

== Changelog ==

= 1.0.16 =
* Improved sidebar hero alignment.
* Simplified sidebar results UI.
* Fixed AI schema response formats.
* Added AI draft topic suggestions.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.16 =
UI improvements and AI feature fixes.
