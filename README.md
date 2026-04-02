# WP Wikipedia Fact-Check

A WordPress plugin that adds a Wikipedia-powered research sidebar to the Gutenberg block editor. Look up Wikipedia articles, review credibility cues, and generate draft-aware AI briefings without leaving the editor.

Built on the [Wikimedia Enterprise API](https://enterprise.wikimedia.com/).

## Features

- **Editor sidebar panel** - search Wikipedia directly from the block editor
- **Selected-text assist** - highlighted text in the editor can auto-populate the search field
- **Article summaries** - view titles, abstracts, images, freshness cues, and topic tags
- **Credibility badge** - color-coded indicator based on Wikimedia's revert risk score
- **AI topic suggestions** - scan the current draft and suggest Wikipedia topics worth checking
- **AI research brief** - generate a compact briefing with key facts, angles, and cautions for a matched article
- **Wikidata links** - jump to the Wikidata entry for any article
- **Multi-language support** — search across 8 Wikipedia languages (English, German, French, Spanish, Italian, Portuguese, Japanese, Chinese)
- **Response caching** - lookups and AI results are cached to reduce repeat requests
- **Connection test** - verify your API credentials from the settings page

## Requirements

- WordPress 6.4+
- PHP 8.1+
- [Wikimedia Enterprise](https://enterprise.wikimedia.com/) API credentials
- For AI features: either WordPress 7.0+ with the WordPress AI Client, or WordPress 6.9.x with the [AI Experiments plugin](https://wordpress.org/plugins/ai/) and at least one configured provider

## Installation

1. Download or clone this repository into your `wp-content/plugins/` directory:
   ```bash
   cd wp-content/plugins/
   git clone <repo-url> wp-wikipedia-factcheck
   ```

2. Install dependencies and build:
   ```bash
   cd wp-wikipedia-factcheck
   npm install
   npm run build
   ```

3. Activate the plugin in **Plugins → Installed Plugins**.

4. Go to **Settings → Wikipedia Fact-Check** and enter your Wikimedia Enterprise username and password.

5. Click **Test Connection** to verify your credentials.

## Usage

1. Open any post or page in the block editor.
2. Click the **book icon** in the editor toolbar or find **Wikipedia Fact-Check** in the sidebar panel list.
3. Type a search term, or select text in the editor and it will auto-populate.
4. Click **Search** to look up the term on Wikipedia.
5. Review the article summary, credibility badge, topic tags, and source links.
6. Click **Suggest from draft** to let AI propose Wikipedia topics based on the current article content.
7. Click one of the suggested topics to open the Wikipedia match and generate an **AI Research Brief**.

## Development

Start the dev build with file watching:

```bash
npm start
```

Production build:

```bash
npm run build
```

Lint JavaScript:

```bash
npm run lint:js
```

## How it works

The plugin registers a Gutenberg sidebar panel that communicates with several REST API endpoints:

| Endpoint | Method | Permission | Description |
|---|---|---|---|
| `/wp-wikipedia-factcheck/v1/lookup` | POST | `edit_posts` | Search for a Wikipedia article by term |
| `/wp-wikipedia-factcheck/v1/test-connection` | POST | `manage_options` | Verify API credentials |
| `/wp-wikipedia-factcheck/v1/suggest-topics` | POST | `edit_posts` | Use AI to suggest Wikipedia search terms from the current draft |
| `/wp-wikipedia-factcheck/v1/briefing` | POST | `edit_posts` | Generate an AI research briefing for a Wikipedia match |
| `/wp-wikipedia-factcheck/v1/analyze` | POST | `edit_posts` | Compare selected draft text against a Wikipedia summary |
| `/wp-wikipedia-factcheck/v1/interesting-facts` | POST | `edit_posts` | Generate fact candidates from a Wikipedia article |
| `/wp-wikipedia-factcheck/v1/ai-health` | POST | `manage_options` | Return AI client diagnostics for debugging |

Wikipedia API calls are made server-side via the `Wikimedia_API` class, which handles JWT authentication with automatic token refresh (23-hour TTL). Credentials are never exposed to the browser.

AI features are handled by `Wikimedia_Factcheck_AI`, which supports:
- WordPress 7.0+ core AI Client
- WordPress 6.9.x with the AI Experiments plugin

When multiple providers are configured, the plugin currently prefers OpenAI first, then Google, then Anthropic.

### Credibility scoring

The credibility badge uses Wikimedia's **revert risk** score — the probability that the current article revision will be reverted (indicating potential vandalism or low-quality edits):

| Score | Badge | Color |
|---|---|---|
| 0 – 0.15 | High credibility | Green |
| 0.15 – 0.40 | Moderate | Amber |
| 0.40+ | Flagged | Red |
| No data | No score | Grey |

## Project structure

```
wp-wikipedia-factcheck/
├── wp-wikipedia-factcheck.php   # Main plugin file, hooks, REST routes, settings
├── includes/
│   ├── class-wikimedia-api.php  # Wikimedia Enterprise API client
│   └── class-wikimedia-factcheck-ai.php # AI helpers for suggestions and briefings
├── src/
│   ├── index.js                 # Plugin entry point
│   ├── style.scss               # Sidebar styles
│   └── sidebar/
│       ├── FactCheckSidebar.js  # Main sidebar component
│       ├── SearchPanel.js       # Search input and draft-driven suggestions
│       ├── ResultPanel.js       # Article result display
│       └── CredibilityBadge.js  # Revert risk badge
├── build/                       # Compiled assets (generated)
└── package.json
```

## License

GPL-2.0-or-later
