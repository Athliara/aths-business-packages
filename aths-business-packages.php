<?php
/**
 * Plugin Name: Aths Business Packages
 * Description: Build filterable business package listings with package cards, galleries, tables, PDFs, and shortcode output.
 * Version: 0.2.17
 * Requires at least: 6.9
 * Requires PHP: 8.2
 * Author: Athlios
 * Author URI: https://a-wd.eu/
 * License: GPL-3.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: aths-business-packages
 * Domain Path: /languages
 */
/**
 * Aths Business Packages is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Aths Business Packages is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Aths Business Packages. If not, see https://www.gnu.org/licenses/gpl-3.0.html.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ATHSBP_VERSION', '0.2.17' );
define( 'ATHSBP_PLUGIN_FILE', __FILE__ );
define( 'ATHSBP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ATHSBP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once ATHSBP_PLUGIN_DIR . 'includes/class-athsbp-plugin.php';

ATHSBP_Plugin::instance();
