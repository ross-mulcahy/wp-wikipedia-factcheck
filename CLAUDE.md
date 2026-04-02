# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

WordPress plugin that adds a Wikipedia research sidebar to the Gutenberg block editor. Writers can search Wikipedia articles, see credibility signals (revert risk scoring), get AI-powered topic suggestions, generate research briefings, and insert Fact Box / Tooltip blocks. Powered by the Wikimedia Enterprise API and WordPress AI Client.

## Build & Dev Commands

```bash
npm start        # Dev build with file watching
npm run build    # Production build
npm run lint:js  # ESLint on src/
```

All commands use `@wordpress/scripts` (wp-scripts). No custom webpack config. The build output goes to `build/`.

There are no tests configured (no jest, phpunit, or test scripts).

## Architecture

Three layers:

**Backend PHP** (`wp-wikipedia-factcheck.php` + `includes/`)
- Main plugin file registers 7 REST endpoints under `/wp-wikipedia-factcheck/v1/` (lookup, test-connection, suggest-topics, briefing, analyze, interesting-facts, ai-health), manages settings, and renders dynamic blocks.
- `Wikimedia_API` class (`includes/class-wikimedia-api.php`): JWT auth with 23-hour TTL, article lookup with 1-hour transient caching.
- `Wikimedia_Factcheck_AI` class (`includes/class-wikimedia-factcheck-ai.php`): Wraps WordPress AI Client with provider fallback (OpenAI → Google → Anthropic). Builds prompts for topic suggestions, briefings, selection analysis, and fact extraction. Supports both WP 7.0+ core AI Client and WP 6.9.x AI Experiments plugin.

**REST API** (communication layer)
- All endpoints require `edit_posts` capability.
- Wikimedia credentials stored server-side, never exposed to the browser.

**Frontend React** (`src/`)
- Entry point: `src/index.js` registers the `wp-wikipedia-factcheck` plugin with `FactCheckSidebar` as the render component.
- Sidebar components in `src/sidebar/`: `FactCheckSidebar` (state management), `SearchPanel` (search + AI suggestions), `ResultPanel` (article display), `CredibilityBadge` (3-tier revert risk badge: green ≤0.15, amber ≤0.40, red >0.40).
- Two custom blocks in `src/blocks/`: `fact-box` and `tooltip`, each with `block.json` metadata and corresponding Edit components.
- REST calls go through `src/api.js` helper.

## Key Patterns

- **Credibility scoring** uses Wikimedia's "revert risk" metric with 3-tier color badge.
- **AI responses** use JSON schema for structured output; the AI class has `forceJsonFromText()` fallback for providers that don't support native JSON mode.
- **Caching**: Wikipedia lookups cached 1 hour via WordPress transients, keyed by search term + language hash.
- **i18n**: Text domain is `wp-wikipedia-factcheck`. Supports EN, DE, FR, ES, IT, PT, JA, ZH.
- **WordPress Playground**: `blueprint.json` in repo root configures a demo environment (WP 6.7, PHP 8.2).

## Requirements

- WordPress 6.4+, PHP 8.1+
- Wikimedia Enterprise API credentials (configured in Settings → Wikipedia Fact-Check)
- WordPress AI Client (core or plugin) for AI features
