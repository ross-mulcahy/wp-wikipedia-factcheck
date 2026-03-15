# WP Wikipedia Fact-Check

A WordPress plugin that adds a Wikipedia-powered fact-check sidebar to the Gutenberg block editor. Look up Wikipedia articles, view summaries, credibility scores, and metadata — without leaving the editor.

Built on the [Wikimedia Enterprise API](https://enterprise.wikimedia.com/).

## Features

- **Editor sidebar panel** — search Wikipedia directly from the block editor
- **Auto-detect selected text** — highlight text in the editor and search it with one click
- **Article summaries** — view titles, abstracts, images, categories, and last-modified dates
- **Credibility badge** — color-coded indicator based on Wikimedia's revert risk score (high credibility / moderate / flagged)
- **Wikidata links** — jump to the Wikidata entry for any article
- **Multi-language support** — search across 8 Wikipedia languages (English, German, French, Spanish, Italian, Portuguese, Japanese, Chinese)
- **Response caching** — lookups are cached for 1 hour to reduce API calls
- **Connection test** — verify your API credentials from the settings page

## Requirements

- WordPress 6.4+
- PHP 8.1+
- [Wikimedia Enterprise](https://enterprise.wikimedia.com/) API credentials

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
5. Review the article summary, credibility badge, categories, and links.

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

The plugin registers a Gutenberg sidebar panel that communicates with two REST API endpoints:

| Endpoint | Method | Permission | Description |
|---|---|---|---|
| `/wp-wikipedia-factcheck/v1/lookup` | POST | `edit_posts` | Search for a Wikipedia article by term |
| `/wp-wikipedia-factcheck/v1/test-connection` | POST | `manage_options` | Verify API credentials |

All API calls are made server-side via the `Wikimedia_API` class, which handles JWT authentication with automatic token refresh (23-hour TTL). Credentials are never exposed to the browser.

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
│   └── class-wikimedia-api.php  # Wikimedia Enterprise API client
├── src/
│   ├── index.js                 # Plugin entry point
│   ├── style.scss               # Sidebar styles
│   └── sidebar/
│       ├── FactCheckSidebar.js  # Main sidebar component
│       ├── SearchPanel.js       # Search input with text auto-detection
│       ├── ResultPanel.js       # Article result display
│       └── CredibilityBadge.js  # Revert risk badge
├── build/                       # Compiled assets (generated)
└── package.json
```

## License

GPL-2.0-or-later
