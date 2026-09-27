=== Aths Business Packages ===
Contributors: athlios
Tags: travel, packages, shortcode, filters, business listings
Requires at least: 6.9
Tested up to: 7.1
Requires PHP: 8.2
Stable tag: 0.3.5
License: GPL-3.0-or-later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Build filterable package listings with custom cards, galleries, tables, PDFs, and shortcode output.

== Description ==

Aths Business Packages is a WordPress plugin for package-based businesses, starting with travel agencies.

It provides a structured package editor, theme-friendly frontend layouts, multilingual travel presets, and flexible filtering for package archives.

Main features:

* Custom post type for packages.
* Predefined and extra configurable filter groups.
* Drag-and-drop filter display ordering.
* Auto-applying archive filters with searchable multi-select country filtering.
* Price and duration range filters.
* Business-type presets for travel agencies and insurance brokers.
* Greek and English display-language options for package UI text and seeded travel terms.
* Native multilingual package translation support with graceful fallback for monolingual sites.
* One-click AI / automated package translation directly in the package editor.
* Extensible language synchronization filter (`athsbp_current_language`) compatible with any theme, Polylang, and WPML.
* Site currency selection with symbol-based display.
* Shortcode-based archive output for theme builders, Elementor, BeTheme, and standard pages.
* Custom single-package presentation with gallery themes, standalone stat tiles, description sections, manual HTML table content, multiple builder tables, optional PDF display, and similar package suggestions.
* Separate rich-text sections for what is included and what is not included.
* Styling controls for labels, pill badges, tags, titles, subtitles, card image badges, single header padding, typography sizes, and range sliders.
* Dynamic two-tag package cards using Important Holidays and Travel Categories, with per-package manual tag overrides.
* AI Package Import tool to import travel packages from JSON files or raw JSON text, with direct admin submenu access, bundled AI agent instructions, and downloadable markdown guide.

This plugin is intended for WordPress 6.9 or later and requires PHP 8.2 or later. The codebase has also been checked with modern PHP versions, including PHP 8.5.

== Installation ==

1. Upload the `aths-business-packages` folder to `/wp-content/plugins/`.
2. Activate the plugin through the `Plugins` screen in WordPress.
3. Confirm the installation environment meets the plugin requirements:
   * WordPress 6.9 or later
   * PHP 8.2 or later
4. Open `Business Packages -> Settings`.
5. Select the preferred business type, display language, and site currency.
6. Review the predefined filters and add any extra custom filters you need.
7. Add package entries and assign their package data.
8. Use the `[athsbp_packages]` shortcode on any page where you want the archive view to appear.

== Frequently Asked Questions ==

The plugin requires WordPress 6.9 or later and PHP 8.2 or later. It has also been checked with PHP 8.5.

= Does the plugin require Elementor or BeTheme? =

No. The plugin works without a page builder. Elementor, BeTheme, and other theme builders can display the archive through the `[athsbp_packages]` shortcode.

= Does the plugin connect to external services? =

No. The plugin does not send package data to external services. Uploaded images and PDFs use the standard WordPress media library.

= What license is used? =

This plugin is distributed under the GNU General Public License v3 or later.

== Changelog ==

= 0.3.5 =

* Strictly Latin Slugs (Greeklish Transliteration): Enforced a strict rule that package URL slugs (permalinks) must ALWAYS be in lowercase Latin letters (`a-z0-9-`).
* Automatic Greek Transliteration: Added `latinize_slug()` with a comprehensive Greek-to-Latin mapping (diphthongs, uppercase/lowercase accents, and final sigma), converting Greek titles into clean, readable, SEO-optimized Latin slugs (e.g. "Ηράκλειο - Κωνσταντινούπολη" -> "irakleio-konstantinoupoli").
* Core Hook Latin Slug Enforcement: Hooked `wp_insert_post_data` for packages, ensuring any package post created or updated (via importer, REST API, or WP admin) always maintains clean Latin slugs with zero percent-encoded octets.
* AI Package Importer & Instructions: Added `slug` field support to AI package instructions, template, and importer, allowing explicit custom Latin slugs while automatically generating them if omitted.

= 0.3.4 =

* Database Insertion Hardening & Bug Fix: Fixed package importer `db_insert_error` ("Could not insert post into the database") caused by database column overflow on percent-encoded non-ASCII/Greek slugs and unicode punctuation.
* Slug Length Bounding: Automatically bounds generated package post slugs to max 120 characters via `_truncate_post_slug()`, preventing MySQL `varchar(200)` overflows when appending uniqueness suffixes (`-2`, `-3`).
* Unicode Dash Normalization: Automatically converts unicode dashes (en-dash, em-dash, horizontal bar, minus) in titles and slugs into standard hyphens.
* Robust Post Insertion Defaults: Explicitly populates `post_name`, `post_author`, `post_content`, `comment_status`, and `ping_status` during import, with automatic fallback retry as `draft` if publishing hooks or status restrictions fail.
* Enhanced Error Diagnostics: Captures and displays underlying MySQL database errors in admin import notices when post creation fails.

= 0.3.3 =

* Importer Bug Fix & Title Length Limit: Enforced strict 60-character limit on package titles in AI import instructions and package importer, preventing publishing errors and truncated SEO titles in WordPress.
* SEO Meta Description Support: Added `meta_description` (and multilingual `meta_description_en`) support across the importer, package editor, and REST/save actions. Automatically derives fallback search engine snippets from package subtitles or itineraries when omitted.
* SEO Plugin & Theme Synchronization: Automatically synchronizes meta descriptions and titles to `post_excerpt`, Rank Math (`rank_math_description`, `rank_math_title`), Yoast SEO (`_yoast_wpseo_metadesc`, `_yoast_wpseo_title`), All in One SEO, and SEOPress, with frontend `<meta name="description">` fallback tag for single package pages.
* Package Editor Enhancements: Added dedicated SEO Meta Description inputs in both primary and English Translations tabs, with one-click auto-translation integration.
* Updated AI Package Instructions: Updated bundled prompt and downloadable guide to mandate max 60-character titles and SEO meta descriptions matching the package language.

= 0.3.2 =

* Locked Destination filter taxonomy (`athsbp_destination` / `Προορισμοί`): Disabled adding, editing, or deleting destination terms in WordPress admin and hooked `pre_insert_term` to block unauthorized term insertion outside internal predefined term syncing.
* Hardened Package Importer for destinations: Enforced strict resolution of imported destination inputs against the 10 predefined destination options (Greek/English names, slugs, and unicode dash variations: en-dash, em-dash, and standard hyphens).
* Implemented Country-to-Destination fallback matching: When destination inputs or package country terms match predefined countries (e.g., Morocco -> Africa - Indian Ocean, Italy -> Europe, Peru -> South America), the importer automatically maps and assigns the package to the correct regional destination.
* Restricted Importer term creation: Importer is explicitly denied from calling `wp_insert_term` for destination terms, completely preventing the creation of rogue or unrecognized destination categories.

= 0.3.1 =

* Added direct "AI Import" navigation link under Settings in the WordPress admin menu for quick access to the AI package import interface.
* Synchronized admin submenu highlighting so navigating to the AI Import screen keeps the AI Import menu item active.
* Updated AI Package Import instructions (`ai-package-import-instructions.md`) and reference template (`ai-package-template.json`):
  * Mandated that AI agents (ChatGPT, Claude, Gemini, etc.) always output and return a downloadable `.json` file rather than raw chat text or code blocks.
  * Enforced strict matching against established WordPress filter taxonomies (destinations, countries, holidays, categories), strictly prohibiting arbitrary or non-existing filter terms.
* Enhanced package term assignment in `assign_terms_to_package()` with case-insensitive fallback matching by slug and name before creating new terms in WordPress.
* Fixed WordPress Plugin Check (PCP) nonce verification warning on admin settings page navigation by sanitizing page query parameter with standard PHPCS annotation.
* Improved release ZIP packaging with explicit directory entries and strict Unix forward slashes (`/`).

= 0.3.0 =

* Rethemed package stats into 3 standalone modern tiles with custom vector SVG line-art icons:
  * Tile 1 (Duration & Nights - Left): Combines days and nights into a single consolidated stat (e.g., "13 Ημέρες / 12 Διανυκτερεύσεις" / "13 Days / 12 Nights") with custom blue hourglass SVG icon.
  * Tile 2 (Destination - Middle): Displays package destination/country (e.g., "Περού", "Μαρόκο") with custom red location pin on blue oval base SVG icon.
  * Tile 3 (Price - Right): Price display with custom gold/amber money pouch SVG icon.
* Security and sanitization hardening: Ensured strict nonce annotations and input sanitization (esc_url_raw, wp_unslash, sanitize_key) across language detection helpers passing WordPress Plugin Check (PCP).
* Integrated Duration & Nights calculation: Nights is now the primary field; duration in days is automatically calculated (nights + 1 = days) live in the admin editor, on package save, and during AI package JSON import.
* Added Gallery Theme selection in plugin settings: Theme 1 (default stacked gallery) and Theme 2 (side-by-side layout with 16:9 main image viewport on the left and 4 thumbnail choices on the right perfectly matching total height).
* Clarified Country Pill Badge styling controls: Explicitly marked ".abp-card-badge" with sample values (e.g., "Περού", "Κένυα") in plugin styling settings for easy customization of background and text colors.
* Seamless theme translator compatibility: Expanded language detection to support Polylang, WPML, TranslatePress, determine_locale() / get_locale() fallbacks, URL path prefixes (/en/), and query parameters (?lang=en).
* Package editor enhancements: Added dedicated Destination field with English translation and one-click auto-translation support.
* Updated AI Package Importer: Added direct "AI Import" submenu navigation under Settings, updated AI instructions to mandate downloadable .json file output and strict matching to existing filter taxonomies without creating random terms, and added case-insensitive slug/name matching fallbacks.
* Styling and responsive enhancements: Added single package kicker pill badge show/hide toggle, typography font size controls for titles/subtitles/headings/body, padding controls, and responsive tile wrapping.

= 0.2.19 =

* Added native multilingual support: dedicated Translations (EN) tab in the package editor for titles, subtitles, itineraries, inclusions, exclusions, notes, and tables.
* Added one-click auto-translation button in the package editor to populate English fields on demand with live progress feedback.
* Added `athsbp_current_language` filter hook and automatic detection for Polylang and WPML.
* Added dynamic frontend translation resolution: cards, titles, and single package views automatically display translated fields when in English mode while gracefully falling back to primary content if untranslated.
* Added `the_title` filter integration for package posts in English mode.
* Full backward compatibility: zero breaking changes and zero overhead for monolingual setups or third-party themes.

= 0.2.18 =

* Added AI Package Import tool in plugin settings to quickly import travel packages from JSON files or raw JSON text.
* Added ready-to-use instructions for AI agents (ChatGPT, Claude, Gemini, etc.) to convert travel package brochures (PDF, Word, flyers) into structured package JSON.
* Added a one-click "Copy Instructions" button and a direct "Download Instructions (.md)" button in plugin settings.
* Added automatic term creation and assignment for package taxonomies (Destinations, Countries, Important Holidays, Travel Categories, and custom taxonomies) during import.
* Added automatic stripping of markdown code fences (```json ... ```) when pasting AI conversation responses.
* Added option to import packages as immediately published or saved as draft.

= 0.2.17 =

* Refactored package editor to use tabbed panels for a cleaner editing interface.
* Added a new "General Information" (Γενικές Πληροφορίες) rich-text editor section.

= 0.2.16 =

* Added an optional expiration date field for each package.
* Expired packages are hidden from public package lists, related suggestions, range filter bounds, and direct package pages while staying editable in WordPress admin.

= 0.2.15 =

* Removed hidden release dotfiles from the WordPress.org package.
* Rebuilt the release package for WordPress.org update delivery.

= 0.2.14 =

* Refined package card typography, badges, type chips, and subtitles for a tighter visual layout.
* Adjusted single package title sizing for 1080p and 2K screens.
* Limited related package suggestions to three items.

= 0.2.13 =

* Avoided unnecessary custom filter option writes during admin requests to improve package update performance.
* Marked the plugin as tested up to WordPress 7.0.

= 0.2.12 =

* Normalized plugin file line endings for Plugin Check.
* Documented one-time legacy migration database operations with precise PHPCS ignores while preserving existing package migration safeguards.
