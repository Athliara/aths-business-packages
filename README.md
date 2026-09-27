# Aths Business Packages

Custom WordPress plugin built for package-based businesses, starting with travel agencies.

Current version: `0.3.5`

## What this version includes

- Strictly Latin slugs rule: all package URL slugs (permalinks) are guaranteed to be in lowercase Latin letters (`a-z0-9-`) with zero percent-encoded non-ASCII characters
- Greek-to-Latin transliteration engine: robust `latinize_slug()` converts Greek titles into clean, SEO-optimized Latin slugs (Greeklish) handling diphthongs, accented vowels, and final sigmas
- Core post hook enforcement: `wp_insert_post_data` automatically enforces Latin slugs for any package post created or updated via importer, REST API, or WP admin
- AI Package Importer & Instructions: added `slug` support to AI instructions, template, and importer for custom Latin slugs while automatically generating them if omitted
- Importer database insertion hardening: resolved `db_insert_error` caused by database column overflows on long percent-encoded non-ASCII/Greek slugs and unicode punctuation
- Slug length bounding: automatically limits generated package slugs to 120 characters via `_truncate_post_slug()` to safely avoid MySQL `varchar(200)` limits when appending uniqueness suffixes
- Unicode dash normalization: automatically sanitizes unicode dashes (en-dash `–`, em-dash `—`, minus) into standard hyphens in titles and slugs
- Complete core post defaults: explicitly sets `post_name`, `post_author`, `post_content`, `comment_status`, and `ping_status` with automatic fallback retry as `draft`
- Detailed database error reporting: captures and surfaces underlying MySQL error strings in importer admin notices
- Importer title length enforcement: strictly capped package titles to 60 characters for WordPress publishing and SEO Meta Title limits
- SEO Meta Description support across the importer, package editor, and REST/save hooks with language-aware fallbacks from package subtitles or itineraries
- SEO plugin synchronization: auto-syncs descriptions and titles with `post_excerpt`, Rank Math (`rank_math_description`, `rank_math_title`), Yoast SEO (`_yoast_wpseo_metadesc`, `_yoast_wpseo_title`), All in One SEO, and SEOPress
- Package editor SEO Meta Description fields in both Greek and English Translations tabs with one-click auto-translation support
- Updated AI Agent Import instructions and reference JSON template mandating max 60-character titles and SEO meta descriptions

- Native multilingual translation support with seamless language switching and graceful fallback for monolingual setups
- Dedicated `Translations (EN)` tab in the package editor for titles, subtitles, itineraries, inclusions, exclusions, notes, and tables
- One-click auto-translation in the package editor with real-time UI population
- Extensible `athsbp_current_language` filter hook with auto-detection for Polylang and WPML
- Custom post type for packages
- Predefined travel filter groups plus unlimited extra custom filter groups
- Drag-and-drop filter display ordering, including predefined and extra filters
- Auto-applying archive filters without requiring an Apply Filters button
- Searchable multi-select dropdown behavior for select-style filters such as Countries
- Business type selector with `Travel Agency` and `Insurance Broker`
- Display language selector with `Greek` and `English`
- Frontend package archive with left sidebar filters and right-side card grid
- Pagination support
- Shortcode support with `[athsbp_packages]`
- Dedicated single package layout with title, subtitle, main image, gallery thumbnails, info bar, rich sections, manual HTML table content, multiple builder tables, PDF display, and similar package suggestions
- Similar package suggestions rank relevant taxonomy matches first and then fall back to recent alternatives
- Styling tab controls for frontend titles, subtitles, labels, tags, card image labels, range sliders, and pagination
- Dynamic two-tag package cards using Important Holidays and Travel Categories by default, with manual per-package overrides
- AI Package Import tool to import travel packages from JSON files or raw JSON text, with bundled AI agent instructions and downloadable markdown guide

## Installation

1. Copy the `aths-business-packages` folder into `wp-content/plugins/`.
2. Activate **Aths Business Packages** from WordPress admin.
3. Confirm your environment meets the plugin requirements:
   - WordPress `6.9` or later
   - PHP `8.2` or later
4. WordPress reads those requirements from the plugin header during installation and activation checks.
5. Go to `Business Packages -> Settings`.
6. Select the default business type, display language, and currency.
7. Review the predefined filters and add any extra custom ones you need.
8. Add package terms for the editable extra taxonomies if needed.
9. Create package entries from `Business Packages`.
10. Use `[athsbp_packages]` on any page, BeTheme page builder block, or Elementor shortcode widget.

## Shortcode

```text
[athsbp_packages per_page="9" show_filters="yes" show_pagination="yes"]
```

## WordPress.org Publishing Notes

- Main plugin header includes plugin name, description, version, WordPress requirement, PHP requirement, author, license, text domain, and domain path.
- `readme.txt` follows the WordPress.org readme structure and includes requirements, stable tag, license, installation, FAQ, and changelog sections.
- Stable tag and main plugin version are aligned at `0.2.18`.
- Author/developer is `Athlios`.
- Contributor username is listed as `athlios`.
- License is `GPL-3.0-or-later` with the GNU GPL v3 license URI.
- The plugin does not use external services.

## Notes

- The plugin is theme-friendly and does not require Elementor or BeTheme to function.
- Elementor compatibility is handled through the shortcode widget.
- The plugin has been checked with PHP `8.5.4` and declares a minimum supported PHP version of `8.2`.
- Travel-agency mode seeds predefined filters and multilingual country / holiday terms automatically.
- Card image labels have their own frontend text and background color settings.
- Filter order can be changed from `Business Packages -> Settings` using the drag handles in the settings screen.
- Text-only prices are treated as zero for price-range filtering but remain unchanged in package display.
- Currency output uses symbols such as `€`, `$`, and `£` where available.
- Package rich-text lists keep proper bullets/numbers even when the active theme removes list styling globally.
- The featured image is available as the first gallery thumbnail so visitors can return to the original main image.
- The internal Package Types taxonomy is hidden from package editing so only active filters remain visible.
- License: `GNU General Public License v3 or later`



## 0.2.18 AI Package Import

- Added AI Package Import tool in plugin settings to quickly import travel packages from JSON files or raw JSON text.
- Added ready-to-use instructions for AI agents (ChatGPT, Claude, Gemini, etc.) to convert travel package brochures (PDF, Word, flyers) into structured package JSON.
- Added a one-click "Copy Instructions" button and a direct "Download Instructions (.md)" button in plugin settings.
- Added automatic term creation and assignment for package taxonomies (Destinations, Countries, Important Holidays, Travel Categories, and custom taxonomies) during import.
- Added automatic stripping of markdown code fences (```json ... ```) when pasting AI conversation responses.
- Added option to import packages as immediately published or saved as draft.

## 0.2.17 Tabbed Editor & General Information

- Refactored package editor to use tabbed panels for a cleaner editing interface.
- Added a new "General Information" (Γενικές Πληροφορίες) rich-text editor section.

## 0.2.16 Package Expiration

- Added an optional expiration date field to each package.
- Expired packages stay available in WordPress admin but are hidden from public archives, direct package pages, related suggestions, and range filter bounds.

## 0.2.15 WordPress.org Package Cleanup

- Removed hidden release dotfiles from the WordPress.org package.
- Rebuilt the release package for clean WordPress.org update delivery.
## 0.2.14 Visual Refinements

- Tightened package card title, badge, type chip, and subtitle spacing.
- Added responsive single package title sizing for 1080p and 2K screens.
- Limited related package suggestions to three cards.
## 0.2.13 Package Update Performance

- Avoided unnecessary custom filter option writes during admin requests to keep package updates lighter on content-heavy sites.
- Marked the plugin as tested up to WordPress 7.0.

## 0.2.12 Plugin Check Cleanup

- Normalized plugin file line endings and removed BOM risk across text files.
- Documented one-time legacy migration database operations with precise PHPCS ignores while preserving the migration safeguards that keep existing live package data intact.
