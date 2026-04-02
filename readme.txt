=== WP Wikipedia Fact-Check ===
Contributors: rossmulcahy
Tags: wikipedia, fact-check, gutenberg, research, editor
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 1.0.16
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Wikipedia-powered fact-check panel for the Gutenberg block editor using the Wikimedia Enterprise API.

== Description ==

WP Wikipedia Fact-Check adds a research sidebar to the WordPress block editor. Look up Wikipedia articles, review credibility cues, and generate AI-powered research briefings without leaving the editor.

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

**Requirements**

* [Wikimedia Enterprise](https://enterprise.wikimedia.com/) API credentials.
* For AI features: WordPress 7.0+ with the WordPress AI Client, or WordPress 6.9.x with the [AI Experiments plugin](https://wordpress.org/plugins/ai/) and at least one configured provider.

== Installation ==

1. Upload the `wp-wikipedia-factcheck` folder to `wp-content/plugins/`.
2. Activate the plugin through the **Plugins** menu.
3. Go to **Settings > Wikipedia Fact-Check** and enter your Wikimedia Enterprise username and password.
4. Click **Test Connection** to verify your credentials.
5. Open any post in the block editor and look for the **Wikipedia Fact-Check** sidebar panel.

== Frequently Asked Questions ==

= Where do I get Wikimedia Enterprise credentials? =

Sign up at [enterprise.wikimedia.com](https://enterprise.wikimedia.com/). The On-demand API provides authenticated access to Wikipedia article data.

= Do I need the AI features? =

No. The core Wikipedia lookup, credibility badge, and article summary features work without AI. The AI-powered topic suggestions and research briefings require the WordPress AI Client or AI Experiments plugin with at least one configured provider.

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
