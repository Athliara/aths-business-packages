=== Aths Business Packages ===
Contributors: athlios
Tags: travel, packages, shortcode, filters, business listings
Requires at least: 6.9
Tested up to: 7.0
Requires PHP: 8.2
Stable tag: 0.2.13
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
* Site currency selection with symbol-based display.
* Shortcode-based archive output for theme builders, Elementor, BeTheme, and standard pages.
* Custom single-package presentation with gallery, info bar, description sections, manual HTML table content, multiple builder tables, optional PDF display, and similar package suggestions.
* Separate rich-text sections for what is included and what is not included.
* Styling controls for labels, tags, titles, subtitles, card image badges, and range sliders.
* Dynamic two-tag package cards using Important Holidays and Travel Categories, with per-package manual tag overrides.

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

= What versions are required? =

The plugin requires WordPress 6.9 or later and PHP 8.2 or later. It has also been checked with PHP 8.5.

= Does the plugin require Elementor or BeTheme? =

No. The plugin works without a page builder. Elementor, BeTheme, and other theme builders can display the archive through the `[athsbp_packages]` shortcode.

= Does the plugin connect to external services? =

No. The plugin does not send package data to external services. Uploaded images and PDFs use the standard WordPress media library.

= What license is used? =

This plugin is distributed under the GNU General Public License v3 or later.

== Changelog ==


= 0.2.13 =

* Avoided unnecessary custom filter option writes during admin requests to improve package update performance.
* Marked the plugin as tested up to WordPress 7.0.

= 0.2.12 =

* Normalized plugin file line endings for Plugin Check.
* Documented one-time legacy migration database operations with precise PHPCS ignores while preserving existing package migration safeguards.
