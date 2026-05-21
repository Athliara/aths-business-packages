# Aths Business Packages

Custom WordPress plugin built for package-based businesses, starting with travel agencies.

Current version: `0.2.13`

## What this version includes

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
- Stable tag and main plugin version are aligned at `0.2.13`.
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
- Currency output uses symbols such as `â‚¬`, `$`, and `Â£` where available.
- Package rich-text lists keep proper bullets/numbers even when the active theme removes list styling globally.
- The featured image is available as the first gallery thumbnail so visitors can return to the original main image.
- The internal Package Types taxonomy is hidden from package editing so only active filters remain visible.
- License: `GNU General Public License v3 or later`

## 0.2.13 Package Update Performance

- Avoided unnecessary custom filter option writes during admin requests to keep package updates lighter on content-heavy sites.
- Marked the plugin as tested up to WordPress 7.0.

## 0.2.12 Plugin Check Cleanup

- Normalized plugin file line endings and removed BOM risk across text files.
- Documented one-time legacy migration database operations with precise PHPCS ignores while preserving the migration safeguards that keep existing live package data intact.
