<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once ATHSBP_PLUGIN_DIR . 'includes/class-athsbp-admin.php';
require_once ATHSBP_PLUGIN_DIR . 'includes/class-athsbp-frontend.php';

class ATHSBP_Plugin {
	const CPT = 'athsbp_package';
	const TYPE_TAX = 'athsbp_package_type';
	const SETTINGS_KEY = 'athsbp_settings';
	const FILTER_GROUPS_KEY = 'athsbp_filter_groups';
	const PREDEFINED_VISIBILITY_KEY = 'athsbp_predefined_filter_visibility';
	const FILTER_ORDER_KEY = 'athsbp_filter_order';
	const META_KEY = '_athsbp_package_meta';
	const PRICE_NUMERIC_META_KEY = '_athsbp_price_numeric';
	const DURATION_NUMERIC_META_KEY = '_athsbp_duration_numeric';
	const EXPIRATION_DATE_META_KEY = '_athsbp_expiration_date';
	const PREDEFINED_SYNC_KEY = '_athsbp_predefined_sync_state';
	const LEGACY_MIGRATION_KEY = 'athsbp_legacy_prefix_migration_020';
	const LEGACY_REWRITE_FLUSH_KEY = 'athsbp_legacy_rewrite_flush_020';
	const REWRITE_VERSION_KEY = 'athsbp_rewrite_rules_version';

	/**
	 * @var ATHSBP_Plugin|null
	 */
	private static $instance = null;

	/**
	 * @var ATHSBP_Admin
	 */
	private $admin;

	/**
	 * @var ATHSBP_Frontend
	 */
	private $frontend;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		$this->admin    = new ATHSBP_Admin( $this );
		$this->frontend = new ATHSBP_Frontend( $this );

		register_activation_hook( ATHSBP_PLUGIN_FILE, array( $this, 'activate' ) );

		add_action( 'init', array( $this, 'maybe_migrate_legacy_data' ), 5 );
		add_action( 'init', array( $this, 'register_content_types' ) );
		add_action( 'init', array( $this, 'register_dynamic_taxonomies' ), 11 );
		add_action( 'init', array( $this, 'maybe_sync_predefined_data' ), 20 );
		add_action( 'init', array( $this, 'maybe_flush_rewrites_after_legacy_migration' ), 30 );
		add_action( 'admin_init', array( $this, 'redirect_legacy_admin_post_type' ) );
		add_action( 'pre_get_posts', array( $this, 'exclude_expired_packages_from_main_queries' ) );
		add_filter( 'request', array( $this, 'map_package_pretty_request' ) );
		add_action( 'template_redirect', array( $this, 'render_package_pretty_url_directly' ), 0 );
		add_filter( 'template_include', array( $this, 'load_templates' ) );
		add_filter( 'enter_title_here', array( $this, 'filter_title_placeholder' ), 10, 2 );
		add_filter( 'plugin_action_links_' . plugin_basename( ATHSBP_PLUGIN_FILE ), array( $this, 'add_plugin_action_links' ) );
	}

	public function activate() {
		$this->maybe_migrate_legacy_data();

		$settings = wp_parse_args(
			get_option( self::SETTINGS_KEY, array() ),
			$this->get_default_settings()
		);

		update_option( self::SETTINGS_KEY, $settings );

		$this->register_content_types();
		$this->register_dynamic_taxonomies();
		$this->migrate_custom_filter_groups();
		$this->sync_predefined_terms();
		flush_rewrite_rules();
	}

	public function maybe_flush_rewrites_after_legacy_migration() {
		if ( ! get_option( self::LEGACY_REWRITE_FLUSH_KEY ) ) {
			return;
		}

		flush_rewrite_rules( false );
		delete_option( self::LEGACY_REWRITE_FLUSH_KEY );
	}

	public function redirect_legacy_admin_post_type() {
		if ( ! is_admin() ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin compatibility redirect.
		$post_type = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : '';
		if ( 'ab' . 'p_package' !== $post_type ) {
			return;
		}

		$url = add_query_arg(
			array( 'post_type' => self::CPT ),
			admin_url( 'edit.php' )
		);

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Page slug is preserved only for navigation compatibility.
		if ( ! empty( $_GET['page'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin compatibility redirect.
			$page = sanitize_key( wp_unslash( $_GET['page'] ) );
			if ( 'ab' . 'p-settings' === $page ) {
				$page = 'athsbp-settings';
			}
			$url = add_query_arg( 'page', $page, $url );
		}

		wp_safe_redirect( $url );
		exit;
	}

	public function exclude_expired_packages_from_main_queries( $query ) {
		if ( is_admin() || ! $query instanceof WP_Query || ! $query->is_main_query() ) {
			return;
		}

		$post_type = $query->get( 'post_type' );
		$is_package_query = self::CPT === $post_type || ( is_array( $post_type ) && in_array( self::CPT, $post_type, true ) );

		if ( ! $is_package_query && ! $query->is_post_type_archive( self::CPT ) && ! $query->is_singular( self::CPT ) ) {
			return;
		}

		$query->set(
			'meta_query',
			$this->merge_meta_queries(
				$query->get( 'meta_query' ),
				$this->get_active_package_meta_query_args()
			)
		);
	}

	public function get_today_date() {
		return current_time( 'Y-m-d' );
	}

	public function normalize_date_value( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}

		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
			return $value;
		}

		return '';
	}

	public function is_package_expired( $post_id ) {
		$expiration_date = get_post_meta( $post_id, self::EXPIRATION_DATE_META_KEY, true );

		if ( '' === $expiration_date ) {
			$meta = $this->get_package_meta( $post_id );
			$expiration_date = isset( $meta['expiration_date'] ) ? $meta['expiration_date'] : '';
		}

		$expiration_date = $this->normalize_date_value( $expiration_date );

		return '' !== $expiration_date && $expiration_date < $this->get_today_date();
	}

	public function get_active_package_meta_query_args() {
		return array(
			'relation' => 'OR',
			array(
				'key'     => self::EXPIRATION_DATE_META_KEY,
				'compare' => 'NOT EXISTS',
			),
			array(
				'key'     => self::EXPIRATION_DATE_META_KEY,
				'value'   => '',
				'compare' => '=',
			),
			array(
				'key'     => self::EXPIRATION_DATE_META_KEY,
				'value'   => $this->get_today_date(),
				'type'    => 'DATE',
				'compare' => '>=',
			),
		);
	}

	public function merge_meta_queries( $first_query, $second_query ) {
		$first_query  = is_array( $first_query ) ? $first_query : array();
		$second_query = is_array( $second_query ) ? $second_query : array();

		if ( empty( $first_query ) ) {
			return $second_query;
		}

		if ( empty( $second_query ) ) {
			return $first_query;
		}

		return array(
			'relation' => 'AND',
			$first_query,
			$second_query,
		);
	}

	public function clear_numeric_filter_bounds_cache() {
		$today = $this->get_today_date();
		$keys  = array(
			'numeric_bounds_' . md5( self::PRICE_NUMERIC_META_KEY . '|' . $today ),
			'numeric_bounds_' . md5( self::DURATION_NUMERIC_META_KEY . '|' . $today ),
		);

		foreach ( $keys as $key ) {
			wp_cache_delete( $key, 'aths_business_packages' );
		}
	}

	public function get_default_settings() {
		return array(
			'business_name'       => "Aths Business Packages",
			'business_subtitle'   => 'Packages for businesses like Travel agencies, Insurance Brokers, and more.',
			'default_vertical'    => 'travel-agency',
			'language'            => 'el',
			'currency'            => 'EUR',
			'archive_title'       => 'Travel Packages',
			'archive_intro'       => 'Discover curated travel packages with flexible filters, polished cards, and theme-friendly layouts.',
			'related_title'       => 'Similar Packages',
			'description_heading' => 'Description',
			'includes_heading'    => 'Package Includes',
			'style_label_text_color'       => '#ffffff',
			'style_label_background_color' => '#183b69',
			'style_tag_text_color'         => '#ffffff',
			'style_tag_background_color'   => '#2ea7e0',
			'style_card_badge_text_color'       => '#ffffff',
			'style_card_badge_background_color' => '#2ea7e0',
			'style_title_color'            => '#183b69',
			'style_subtitle_color'         => '#35537c',
			'style_slider_active_color'    => '#f26923',
			'style_slider_track_color'     => '#d6dfeb',
			'style_slider_thumb_color'     => '#f26923',
			'style_pagination_text_color'              => '#183b69',
			'style_pagination_background_color'        => '#ffffff',
			'style_pagination_border_color'            => '#dbe4ef',
			'style_pagination_active_text_color'       => '#ffffff',
			'style_pagination_active_background_color' => '#183b69',
		);
	}

	public function register_content_types() {
		$labels_map = $this->get_ui_labels();
		$labels = array(
			'name'               => $labels_map['packages_plural'],
			'singular_name'      => $labels_map['package_singular'],
			'add_new'            => $labels_map['add_package'],
			'add_new_item'       => $labels_map['add_new_package'],
			'edit_item'          => $labels_map['edit_package'],
			'new_item'           => $labels_map['new_package'],
			'view_item'          => $labels_map['view_package'],
			'search_items'       => $labels_map['search_packages'],
			'not_found'          => $labels_map['no_packages_found'],
			'not_found_in_trash' => $labels_map['no_packages_found_trash'],
			'all_items'          => $labels_map['all_packages'],
			'menu_name'          => $labels_map['business_packages_menu'],
		);

		register_post_type(
			self::CPT,
			array(
				'labels'            => $labels,
				'public'            => true,
				'show_in_rest'      => true,
				'menu_icon'         => 'dashicons-palmtree',
				'supports'          => array( 'title', 'thumbnail' ),
				'has_archive'       => true,
				'rewrite'           => array( 'slug' => 'packages' ),
				'show_in_nav_menus' => true,
			)
		);

		register_taxonomy(
			self::TYPE_TAX,
			self::CPT,
			array(
				'labels'            => array(
					'name'          => $labels_map['package_types'],
					'singular_name' => $labels_map['package_type'],
				),
				'public'            => true,
				'show_ui'           => false,
				'show_in_menu'      => false,
				'show_in_rest'      => true,
				'hierarchical'      => true,
				'show_admin_column' => false,
				'rewrite'           => array( 'slug' => 'package-type' ),
			)
		);
	}

	public function register_dynamic_taxonomies() {
		$groups = $this->get_filter_groups();

		foreach ( $groups as $group ) {
			if ( $this->is_range_filter_type( $group['input_type'] ) ) {
				continue;
			}

			$slug = $this->taxonomy_name_from_slug( $group['slug'] );

			register_taxonomy(
				$slug,
				self::CPT,
				array(
					'labels'            => array(
						'name'          => $group['label'],
						'singular_name' => $group['singular'],
					),
					'public'            => true,
					'hierarchical'      => true,
					'show_in_rest'      => true,
					'show_admin_column' => true,
					'rewrite'           => array( 'slug' => sanitize_title( $group['slug'] ) ),
				)
			);
		}
	}



	public function render_package_pretty_url_directly() {
		if ( is_admin() || wp_doing_ajax() ) {
			return;
		}

		$path = $this->get_current_frontend_path();
		if ( '' === $path ) {
			return;
		}

		if ( 'packages' === $path ) {
			$this->prepare_direct_package_query_context( 'archive' );
			$this->render_direct_template( 'archive' );
		}

		if ( 0 !== strpos( $path, 'packages/' ) ) {
			return;
		}

		$parts = array_values( array_filter( explode( '/', $path ) ) );
		if ( 2 !== count( $parts ) ) {
			return;
		}

		$package = get_page_by_path( sanitize_title( $parts[1] ), OBJECT, self::CPT );
		if ( ! $package || 'publish' !== get_post_status( $package ) ) {
			return;
		}

		if ( $this->is_package_expired( $package->ID ) ) {
			$this->prepare_direct_404_context();
			return;
		}

		global $post, $wp_query;
		$post = $package;
		setup_postdata( $post );

		$this->prepare_direct_package_query_context( 'single', $package );
		$this->render_direct_template( 'single' );
	}

	private function get_current_frontend_path() {
		$uri_path = isset( $_SERVER['REQUEST_URI'] ) ? wp_parse_url( esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH ) : '';
		if ( ! is_string( $uri_path ) || '' === $uri_path ) {
			return '';
		}

		$home_path = wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		if ( is_string( $home_path ) && '/' !== $home_path && 0 === strpos( $uri_path, $home_path ) ) {
			$uri_path = substr( $uri_path, strlen( $home_path ) );
		}

		return trim( $uri_path, '/' );
	}

	private function prepare_direct_package_query_context( $type, $package = null ) {
		global $post, $wp_query;

		if ( ! ( $wp_query instanceof WP_Query ) ) {
			return;
		}

		$wp_query->is_home              = false;
		$wp_query->is_page              = false;
		$wp_query->is_404               = false;
		$wp_query->is_archive           = false;
		$wp_query->is_post_type_archive = false;
		$wp_query->is_singular          = false;
		$wp_query->is_single            = false;

		if ( 'archive' === $type ) {
			$post                              = null;
			$wp_query->query_vars['post_type'] = self::CPT;
			$wp_query->queried_object          = get_post_type_object( self::CPT );
			$wp_query->queried_object_id       = 0;
			$wp_query->post                    = null;
			$wp_query->posts                   = array();
			$wp_query->post_count              = 0;
			$wp_query->found_posts             = 0;
			$wp_query->is_archive              = true;
			$wp_query->is_post_type_archive    = true;
			set_query_var( 'post_type', self::CPT );
			return;
		}

		if ( $package instanceof WP_Post ) {
			$wp_query->queried_object    = $package;
			$wp_query->queried_object_id = (int) $package->ID;
			$wp_query->post              = $package;
			$wp_query->posts             = array( $package );
			$wp_query->post_count        = 1;
			$wp_query->found_posts       = 1;
			$wp_query->is_singular       = true;
			$wp_query->is_single         = true;
		}
	}

	private function prepare_direct_404_context() {
		global $wp_query;

		if ( $wp_query instanceof WP_Query ) {
			$wp_query->set_404();
		}

		status_header( 404 );
		nocache_headers();
	}

	private function render_direct_template( $type ) {
		$template = 'single' === $type
			? ATHSBP_PLUGIN_DIR . 'templates/single-athsbp_package.php'
			: ATHSBP_PLUGIN_DIR . 'templates/archive-athsbp_package.php';

		if ( ! file_exists( $template ) ) {
			return;
		}

		status_header( 200 );
		nocache_headers();
		add_filter(
			'body_class',
			static function ( $classes ) use ( $type ) {
				$classes[] = 'single' === $type ? 'single-athsbp_package' : 'post-type-archive-athsbp_package';
				$classes[] = 'athsbp-direct-template';

				return array_unique( $classes );
			}
		);
		include $template;
		exit;
	}

	public function map_package_pretty_request( $query_vars ) {
		$path = '';

		if ( isset( $query_vars['pagename'] ) ) {
			$path = trim( (string) $query_vars['pagename'], '/' );
		} elseif ( isset( $query_vars['name'] ) && 'packages' === $query_vars['name'] ) {
			$path = 'packages';
		}

		if ( '' === $path ) {
			return $query_vars;
		}

		if ( 'packages' === $path ) {
			unset( $query_vars['pagename'], $query_vars['page'], $query_vars['name'] );
			$query_vars['post_type'] = self::CPT;
			return $query_vars;
		}

		if ( 0 !== strpos( $path, 'packages/' ) ) {
			return $query_vars;
		}

		$parts = array_values( array_filter( explode( '/', $path ) ) );
		if ( 2 !== count( $parts ) ) {
			return $query_vars;
		}

		$package_slug = sanitize_title( $parts[1] );
		if ( '' === $package_slug ) {
			return $query_vars;
		}

		unset( $query_vars['pagename'], $query_vars['page'] );
		$query_vars['post_type'] = self::CPT;
		$query_vars['name']      = $package_slug;

		return $query_vars;
	}

	public function load_templates( $template ) {
		if ( is_post_type_archive( self::CPT ) ) {
			$archive = ATHSBP_PLUGIN_DIR . 'templates/archive-athsbp_package.php';
			if ( file_exists( $archive ) ) {
				return $archive;
			}
		}

		if ( is_singular( self::CPT ) ) {
			$post_id = get_queried_object_id();
			if ( $post_id && $this->is_package_expired( $post_id ) ) {
				global $wp_query;

				if ( $wp_query instanceof WP_Query ) {
					$wp_query->set_404();
				}

				status_header( 404 );
				$not_found = get_404_template();

				return $not_found ? $not_found : $template;
			}

			$single = ATHSBP_PLUGIN_DIR . 'templates/single-athsbp_package.php';
			if ( file_exists( $single ) ) {
				return $single;
			}
		}

		return $template;
	}

	public function filter_title_placeholder( $title, $post ) {
		if ( isset( $post->post_type ) && self::CPT === $post->post_type ) {
			return $this->get_ui_labels()['enter_package_title'];
		}

		return $title;
	}

	public function add_plugin_action_links( $links ) {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'edit.php?post_type=' . self::CPT . '&page=athsbp-settings' ) ),
			esc_html__( 'Settings', 'aths-business-packages' )
		);

		array_unshift( $links, $settings_link );

		return $links;
	}

	public function get_settings() {
		return wp_parse_args(
			get_option( self::SETTINGS_KEY, array() ),
			$this->get_default_settings()
		);
	}

	public function get_business_type_options() {
		return array(
			'travel-agency'    => __( 'Travel Agency', 'aths-business-packages' ),
			'insurance-broker' => __( 'Insurance Broker', 'aths-business-packages' ),
		);
	}

	public function get_language_options() {
		return array(
			'el' => __( 'Greek', 'aths-business-packages' ),
			'en' => __( 'English', 'aths-business-packages' ),
		);
	}

	public function get_current_language() {
		$settings = $this->get_settings();
		$language = isset( $settings['language'] ) ? $settings['language'] : 'el';

		return array_key_exists( $language, $this->get_language_options() ) ? $language : 'el';
	}

	public function get_frontend() {
		return $this->frontend;
	}

	public function get_custom_filter_groups() {
		$groups = get_option( self::FILTER_GROUPS_KEY, array() );

		if ( ! is_array( $groups ) ) {
			return array();
		}

		$prepared = array();

		foreach ( $groups as $group ) {
			if ( empty( $group['label'] ) || empty( $group['slug'] ) ) {
				continue;
			}

			$prepared[] = array(
				'label'       => sanitize_text_field( $group['label'] ),
				'slug'        => sanitize_title( $group['slug'] ),
				'singular'    => ! empty( $group['singular'] ) ? sanitize_text_field( $group['singular'] ) : sanitize_text_field( $group['label'] ),
				'input_type'  => ! empty( $group['input_type'] ) && in_array( $group['input_type'], array( 'checkbox', 'select', 'range_price', 'range_duration' ), true ) ? $group['input_type'] : 'checkbox',
				'show_counts' => ! empty( $group['show_counts'] ) ? 1 : 0,
			);
		}

		return $prepared;
	}

	public function get_filter_groups() {
		$groups = array();

		foreach ( array_merge( $this->get_predefined_filter_groups(), $this->get_custom_filter_groups() ) as $group ) {
			$groups[ $group['slug'] ] = $group;
		}

		return $this->order_filter_groups( array_values( $groups ) );
	}

	public function get_filter_order() {
		$order = get_option( self::FILTER_ORDER_KEY, array() );

		if ( ! is_array( $order ) ) {
			return array();
		}

		return array_values(
			array_unique(
				array_filter(
					array_map( 'sanitize_title', $order )
				)
			)
		);
	}

	public function order_filter_groups( $groups ) {
		if ( empty( $groups ) ) {
			return array();
		}

		$ordered = array();
		$by_slug = array();

		foreach ( $groups as $group ) {
			if ( empty( $group['slug'] ) ) {
				continue;
			}

			$by_slug[ $group['slug'] ] = $group;
		}

		foreach ( $this->get_filter_order() as $slug ) {
			if ( ! isset( $by_slug[ $slug ] ) ) {
				continue;
			}

			$ordered[] = $by_slug[ $slug ];
			unset( $by_slug[ $slug ] );
		}

		return array_merge( $ordered, array_values( $by_slug ) );
	}

	public function is_range_filter_type( $input_type ) {
		return in_array( $input_type, array( 'range_price', 'range_duration' ), true );
	}

	public function taxonomy_name_from_slug( $slug ) {
		return 'athsbp_' . str_replace( '-', '_', sanitize_title( $slug ) );
	}

	public function get_package_meta( $post_id ) {
		$labels = $this->get_ui_labels();
		$defaults = array(
			'subtitle'            => '',
			'card_subtitle'       => '',
			'badge_text'          => '',
			'card_primary_tag'    => '',
			'card_secondary_tag'  => '',
			'price'               => '',
			'price_note'          => '',
			'price_label'         => $labels['price_label'],
			'duration'            => '',
			'duration_label'      => $labels['duration_label'],
			'nights'              => '',
			'nights_label'        => $labels['nights_label'],
			'expiration_date'     => '',
			'description_title'   => $labels['description'],
			'description_content' => '',
			'includes_title'      => $labels['whats_included'],
			'includes_content'    => '',
			'includes_table_html' => '',
			'excludes_title'      => $labels['whats_not_included'],
			'excludes_content'    => '',
			'includes_table'      => '',
			'includes_tables'     => array(),
			'includes_pdf_id'     => 0,
			'gallery_ids'         => array(),
		);

		$meta = get_post_meta( $post_id, self::META_KEY, true );
		if ( ! is_array( $meta ) ) {
			$meta = array();
		}

		$meta = wp_parse_args( $meta, $defaults );

		$translatable_meta_defaults = array(
			'price_label'       => array( 'Price', 'Τιμή' ),
			'duration_label'    => array( 'Duration', 'Διάρκεια' ),
			'nights_label'      => array( 'Nights', 'Διανυκτερεύσεις' ),
			'description_title' => array( 'Description', 'Περιγραφή' ),
			'includes_title'    => array( 'What\'s Included', 'Τι Περιλαμβάνεται', 'Τι περιλαμβάνεται' ),
			'excludes_title'    => array( 'What\'s Not Included', 'Τι δεν περιλαμβάνεται' ),
		);

		foreach ( $translatable_meta_defaults as $meta_key => $legacy_values ) {
			if ( '' === trim( (string) $meta[ $meta_key ] ) || in_array( $meta[ $meta_key ], $legacy_values, true ) ) {
				$meta[ $meta_key ] = $defaults[ $meta_key ];
			}
		}

		$meta['expiration_date'] = $this->normalize_date_value( $meta['expiration_date'] );

		if ( ! is_array( $meta['gallery_ids'] ) ) {
			$meta['gallery_ids'] = array_filter( array_map( 'absint', explode( ',', (string) $meta['gallery_ids'] ) ) );
		}

		if ( ! is_array( $meta['includes_tables'] ) ) {
			$meta['includes_tables'] = array();
		}

		$meta['includes_tables'] = array_values(
			array_filter(
				array_map(
					function ( $table ) {
						return is_string( $table ) ? trim( $table ) : '';
					},
					$meta['includes_tables']
				)
			)
		);

		if ( empty( $meta['includes_tables'] ) && ! empty( $meta['includes_table'] ) ) {
			$meta['includes_tables'] = array( $meta['includes_table'] );
		}

		$meta['includes_pdf_id'] = absint( $meta['includes_pdf_id'] );

		return $meta;
	}

	public function get_localized_setting( $key ) {
		$settings = $this->get_settings();
		$labels   = $this->get_ui_labels();
		$value    = isset( $settings[ $key ] ) ? trim( (string) $settings[ $key ] ) : '';

		$defaults = array(
			'archive_title' => array( 'Travel Packages', 'Ταξιδιωτικά Πακέτα' ),
			'archive_intro' => array(
				'Discover curated travel packages with flexible filters, polished cards, and theme-friendly layouts.',
				'Ανακαλύψτε επιλεγμένα ταξιδιωτικά πακέτα με ευέλικτα φίλτρα, προσεγμένες κάρτες και διάταξη φιλική προς το θέμα σας.',
			),
			'related_title' => array( 'Similar Packages', 'Παρόμοια Πακέτα' ),
		);

		if ( isset( $defaults[ $key ] ) ) {
			if ( '' === $value || in_array( $value, $defaults[ $key ], true ) ) {
				return isset( $labels[ $key ] ) ? $labels[ $key ] : $value;
			}
		}

		return $value;
	}

	public function get_filter_request_values( $slug ) {
		$key = 'athsbp_' . sanitize_title( $slug );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public archive filters are read-only.
		if ( ! isset( $_GET[ $key ] ) ) {
			return array();
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Values are sanitized by scalar/array shape immediately below.
		$value = wp_unslash( $_GET[ $key ] );

		if ( is_array( $value ) ) {
			return array_filter( array_map( 'sanitize_title', $value ) );
		}

		return array_filter( array_map( 'sanitize_title', explode( ',', sanitize_text_field( $value ) ) ) );
	}

	public function get_currency_options() {
		return array(
			'EUR' => array(
				'label'    => 'Euro (€)',
				'symbol'   => '€',
				'position' => 'after',
			),
			'USD' => array(
				'label'    => 'US Dollar ($)',
				'symbol'   => '$',
				'position' => 'before',
			),
			'GBP' => array(
				'label'    => 'British Pound (£)',
				'symbol'   => '£',
				'position' => 'before',
			),
			'CHF' => array(
				'label'    => 'Swiss Franc (CHF)',
				'symbol'   => 'CHF',
				'position' => 'after',
			),
		);
	}

	public function get_currency_config() {
		$settings = $this->get_settings();
		$options  = $this->get_currency_options();
		$code     = isset( $settings['currency'] ) ? $settings['currency'] : 'EUR';

		return isset( $options[ $code ] ) ? $options[ $code ] : $options['EUR'];
	}

	public function format_price_display( $raw_price ) {
		$raw_price = trim( (string) $raw_price );
		if ( '' === $raw_price ) {
			return '';
		}

		$currency = $this->get_currency_config();
		$numeric  = $this->extract_numeric_value( $raw_price );

		if ( null !== $numeric && preg_match( '/^\s*\d+(?:[.,]\d+)?\s*$/', $raw_price ) ) {
			$formatted_number = number_format_i18n( $numeric, floor( $numeric ) !== $numeric ? 2 : 0 );

			return 'before' === $currency['position']
				? $currency['symbol'] . ' ' . $formatted_number
				: $formatted_number . ' ' . $currency['symbol'];
		}

		return $raw_price;
	}

	public function get_filter_query_args() {
		$tax_query = array();

		$type_values = $this->get_filter_request_values( 'package-type' );
		if ( ! empty( $type_values ) ) {
			$tax_query[] = array(
				'taxonomy' => self::TYPE_TAX,
				'field'    => 'slug',
				'terms'    => $type_values,
			);
		}

		foreach ( $this->get_filter_groups() as $group ) {
			$values = $this->get_filter_request_values( $group['slug'] );
			if ( empty( $values ) ) {
				continue;
			}

			$tax_query[] = array(
				'taxonomy' => $this->taxonomy_name_from_slug( $group['slug'] ),
				'field'    => 'slug',
				'terms'    => $values,
			);
		}

		if ( count( $tax_query ) > 1 ) {
			$tax_query['relation'] = 'AND';
		}

		return $tax_query;
	}

	public function get_meta_filter_query_args() {
		$meta_query = array();

		foreach ( $this->get_filter_groups() as $group ) {
			if ( 'range_price' === $group['input_type'] ) {
				$range = $this->get_range_request_values( 'price' );
				$key   = self::PRICE_NUMERIC_META_KEY;
				$clauses = array();

				if ( null !== $range['min'] ) {
					$clauses[] = array(
						'key'     => $key,
						'value'   => $range['min'],
						'type'    => 'NUMERIC',
						'compare' => '>=',
					);
				}

				if ( null !== $range['max'] ) {
					$clauses[] = array(
						'key'     => $key,
						'value'   => $range['max'],
						'type'    => 'NUMERIC',
						'compare' => '<=',
					);
				}

				if ( empty( $clauses ) ) {
					continue;
				}

				if ( ( null === $range['min'] || $range['min'] <= 0 ) && ( null === $range['max'] || $range['max'] >= 0 ) ) {
					$clauses = array(
						'relation' => 'OR',
						array_merge(
							array( 'relation' => 'AND' ),
							$clauses
						),
						array(
							'key'     => $key,
							'compare' => 'NOT EXISTS',
						),
					);
				} elseif ( count( $clauses ) > 1 ) {
					$clauses['relation'] = 'AND';
				}

				$meta_query[] = $clauses;
				continue;
			} elseif ( 'range_duration' === $group['input_type'] ) {
				$range = $this->get_range_request_values( 'duration' );
				$key   = self::DURATION_NUMERIC_META_KEY;
			} else {
				continue;
			}

			if ( null !== $range['min'] ) {
				$meta_query[] = array(
					'key'     => $key,
					'value'   => $range['min'],
					'type'    => 'NUMERIC',
					'compare' => '>=',
				);
			}

			if ( null !== $range['max'] ) {
				$meta_query[] = array(
					'key'     => $key,
					'value'   => $range['max'],
					'type'    => 'NUMERIC',
					'compare' => '<=',
				);
			}
		}

		if ( count( $meta_query ) > 1 ) {
			$meta_query['relation'] = 'AND';
		}

		return $meta_query;
	}

	public function get_range_request_values( $slug ) {
		$min_key = 'athsbp_' . sanitize_title( $slug ) . '_min';
		$max_key = 'athsbp_' . sanitize_title( $slug ) . '_max';

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public range filters are read-only.
		$raw_min = isset( $_GET[ $min_key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $min_key ] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public range filters are read-only.
		$raw_max = isset( $_GET[ $max_key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $max_key ] ) ) : '';

		$min = '' !== $raw_min ? floatval( $raw_min ) : null;
		$max = '' !== $raw_max ? floatval( $raw_max ) : null;

		return array(
			'min' => $min,
			'max' => $max,
		);
	}

	public function get_numeric_filter_bounds( $meta_key ) {
		global $wpdb;

		$cache_key = 'numeric_bounds_' . md5( (string) $meta_key . '|' . $this->get_today_date() );
		$cached    = wp_cache_get( $cache_key, 'aths_business_packages' );
		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		if ( self::PRICE_NUMERIC_META_KEY === $meta_key ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Aggregate bounds are cached and cannot be retrieved efficiently with WP_Query.
			$result = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT MIN(COALESCE(CAST(pm.meta_value AS DECIMAL(10,2)), 0)) AS min_value, MAX(COALESCE(CAST(pm.meta_value AS DECIMAL(10,2)), 0)) AS max_value
					FROM {$wpdb->posts} p
					LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = %s
					LEFT JOIN {$wpdb->postmeta} expm ON expm.post_id = p.ID AND expm.meta_key = %s
					WHERE p.post_type = %s
					AND p.post_status = 'publish'
					AND (expm.meta_value IS NULL OR expm.meta_value = '' OR expm.meta_value >= %s)",
					$meta_key,
					self::EXPIRATION_DATE_META_KEY,
					self::CPT,
					$this->get_today_date()
				),
				ARRAY_A
			);

			$min = isset( $result['min_value'] ) && null !== $result['min_value'] ? (float) $result['min_value'] : 0;
			$max = isset( $result['max_value'] ) && null !== $result['max_value'] ? (float) $result['max_value'] : 0;
			$bounds = array(
				'min' => $min,
				'max' => $max,
			);
			wp_cache_set( $cache_key, $bounds, 'aths_business_packages', HOUR_IN_SECONDS );

			return $bounds;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Aggregate bounds are cached and cannot be retrieved efficiently with WP_Query.
		$result = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT MIN(CAST(pm.meta_value AS DECIMAL(10,2))) AS min_value, MAX(CAST(pm.meta_value AS DECIMAL(10,2))) AS max_value
				FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				LEFT JOIN {$wpdb->postmeta} expm ON expm.post_id = p.ID AND expm.meta_key = %s
				WHERE pm.meta_key = %s
				AND p.post_type = %s
				AND p.post_status = 'publish'
				AND (expm.meta_value IS NULL OR expm.meta_value = '' OR expm.meta_value >= %s)",
				self::EXPIRATION_DATE_META_KEY,
				$meta_key,
				self::CPT,
				$this->get_today_date()
			),
			ARRAY_A
		);

		$min = isset( $result['min_value'] ) && null !== $result['min_value'] ? (float) $result['min_value'] : 0;
		$max = isset( $result['max_value'] ) && null !== $result['max_value'] ? (float) $result['max_value'] : 0;
		$bounds = array(
			'min' => $min,
			'max' => $max,
		);
		wp_cache_set( $cache_key, $bounds, 'aths_business_packages', HOUR_IN_SECONDS );

		return $bounds;
	}

	public function extract_numeric_value( $value ) {
		if ( preg_match( '/(\d+(?:[.,]\d+)?)/', (string) $value, $matches ) ) {
			return (float) str_replace( ',', '.', $matches[1] );
		}

		return null;
	}

	public function get_ui_labels() {
		$language = $this->get_current_language();

		$labels = array(
			'en' => array(
				'packages_plural'         => 'Packages',
				'package_singular'        => 'Package',
				'package_types'           => 'Package Types',
				'package_type'            => 'Package Type',
				'package_fallback'        => 'Package',
				'travel_package'          => 'Travel Package',
				'archive_kicker'          => 'Travel Packages',
				'apply_filters'           => 'Apply Filters',
				'reset'                   => 'Reset',
				'any'                     => 'Any',
				'previous'                => 'Previous',
				'next'                    => 'Next',
				'days'                    => 'days',
				'results_count'           => '%1$d - %2$d of %3$d packages',
				'no_packages_match'       => 'No packages matched the current filters.',
				'add_package'             => 'Add Package',
				'add_new_package'         => 'Add New Package',
				'edit_package'            => 'Edit Package',
				'new_package'             => 'New Package',
				'view_package'            => 'View Package',
				'search_packages'         => 'Search Packages',
				'no_packages_found'       => 'No packages found.',
				'no_packages_found_trash' => 'No packages found in trash.',
				'all_packages'            => 'All Packages',
				'business_packages_menu'  => 'Business Packages',
				'enter_package_title'     => 'Enter the package title visitors will see',
				'duration_label'          => 'Duration',
				'nights_label'            => 'Nights',
				'price_label'             => 'Price',
				'description'             => 'Description',
				'whats_included'          => 'What\'s Included',
				'whats_not_included'      => 'What\'s Not Included',
				'package_tables'          => 'Package Tables',
				'similar_packages'        => 'Similar Packages',
				'archive_title'           => 'Travel Packages',
				'archive_intro'           => 'Discover curated travel packages with flexible filters, polished cards, and theme-friendly layouts.',
			),
			'el' => array(
				'packages_plural'         => 'Πακέτα',
				'package_singular'        => 'Πακέτο',
				'package_types'           => 'Τύποι Πακέτων',
				'package_type'            => 'Τύπος Πακέτου',
				'package_fallback'        => 'Πακέτο',
				'travel_package'          => 'Ταξιδιωτικό Πακέτο',
				'archive_kicker'          => 'Ταξιδιωτικά Πακέτα',
				'apply_filters'           => 'Εφαρμογή Φίλτρων',
				'reset'                   => 'Επαναφορά',
				'any'                     => 'Οποιαδήποτε',
				'previous'                => 'Προηγούμενη',
				'next'                    => 'Επόμενη',
				'days'                    => 'ημ.',
				'results_count'           => '%1$d - %2$d από %3$d πακέτα',
				'no_packages_match'       => 'Δεν βρέθηκαν πακέτα με τα τρέχοντα φίλτρα.',
				'add_package'             => 'Προσθήκη Πακέτου',
				'add_new_package'         => 'Προσθήκη Νέου Πακέτου',
				'edit_package'            => 'Επεξεργασία Πακέτου',
				'new_package'             => 'Νέο Πακέτο',
				'view_package'            => 'Προβολή Πακέτου',
				'search_packages'         => 'Αναζήτηση Πακέτων',
				'no_packages_found'       => 'Δεν βρέθηκαν πακέτα.',
				'no_packages_found_trash' => 'Δεν βρέθηκαν πακέτα στον κάδο.',
				'all_packages'            => 'Όλα τα Πακέτα',
				'business_packages_menu'  => 'Επαγγελματικά Πακέτα',
				'enter_package_title'     => 'Συμπληρώστε τον τίτλο πακέτου που θα βλέπουν οι επισκέπτες',
				'duration_label'          => 'Διάρκεια',
				'nights_label'            => 'Διανυκτερεύσεις',
				'price_label'             => 'Τιμή',
				'description'             => 'Περιγραφή',
				'whats_included'          => 'Τι περιλαμβάνεται',
				'whats_not_included'      => 'Τι δεν περιλαμβάνεται',
				'package_tables'          => 'Πίνακες πακέτου',
				'similar_packages'        => 'Παρόμοια Πακέτα',
				'archive_title'           => 'Ταξιδιωτικά Πακέτα',
				'archive_intro'           => 'Ανακαλύψτε επιλεγμένα ταξιδιωτικά πακέτα με ευέλικτα φίλτρα, προσεγμένες κάρτες και διάταξη φιλική προς το θέμα σας.',
			),
		);

		return $labels[ $language ];
	}

	public function get_predefined_filter_groups() {
		$groups      = $this->get_all_predefined_filter_groups();
		$visibility  = $this->get_predefined_filter_visibility();
		$visible_set = array();

		foreach ( $groups as $group ) {
			$slug = $group['slug'];
			if ( ! isset( $visibility[ $slug ] ) || ! $visibility[ $slug ] ) {
				continue;
			}

			$visible_set[] = $group;
		}

		return $visible_set;
	}

	public function get_all_predefined_filter_groups() {
		$language = $this->get_current_language();
		$vertical = $this->get_settings()['default_vertical'];

		$labels = array(
			'en' => array(
				'price'       => 'Price Range',
				'destination' => 'Destinations',
				'country'     => 'Countries',
				'holidays'    => 'Important Holidays',
				'style'       => 'Travel Categories',
				'duration'    => 'Duration',
			),
			'el' => array(
				'price'       => 'Εύρος Τιμών',
				'destination' => 'Προορισμοί',
				'country'     => 'Χώρες',
				'holidays'    => 'Εορτές και Αργίες',
				'style'       => 'Κατηγορίες Ταξιδίου',
				'duration'    => 'Διάρκεια Ταξιδίου',
			),
		);

		$current = $labels[ $language ];
		$groups  = array(
			array(
				'label'       => $current['price'],
				'slug'        => 'price',
				'singular'    => $current['price'],
				'input_type'  => 'range_price',
				'show_counts' => 0,
			),
		);

		if ( 'travel-agency' === $vertical ) {
			$groups[] = array(
				'label'       => $current['destination'],
				'slug'        => 'destination',
				'singular'    => $current['destination'],
				'input_type'  => 'checkbox',
				'show_counts' => 1,
			);
			$groups[] = array(
				'label'       => $current['country'],
				'slug'        => 'country',
				'singular'    => $current['country'],
				'input_type'  => 'select',
				'show_counts' => 1,
			);
			$groups[] = array(
				'label'       => $current['holidays'],
				'slug'        => 'important-holidays',
				'singular'    => $current['holidays'],
				'input_type'  => 'checkbox',
				'show_counts' => 1,
			);
			$groups[] = array(
				'label'       => $current['style'],
				'slug'        => 'travel-style',
				'singular'    => $current['style'],
				'input_type'  => 'checkbox',
				'show_counts' => 1,
			);
			$groups[] = array(
				'label'       => $current['duration'],
				'slug'        => 'duration',
				'singular'    => $current['duration'],
				'input_type'  => 'range_duration',
				'show_counts' => 0,
			);
		}

		return $groups;
	}

	public function get_predefined_filter_visibility() {
		$stored   = get_option( self::PREDEFINED_VISIBILITY_KEY, array() );
		$defaults = array();

		foreach ( $this->get_all_predefined_filter_groups() as $group ) {
			$defaults[ $group['slug'] ] = 1;
		}

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		foreach ( $defaults as $slug => $default_value ) {
			$stored[ $slug ] = isset( $stored[ $slug ] ) ? (int) ! empty( $stored[ $slug ] ) : $default_value;
		}

		return $stored;
	}

	public function maybe_sync_predefined_data() {
		$this->migrate_custom_filter_groups();

		$state = wp_json_encode(
			array(
				'language' => $this->get_current_language(),
				'vertical' => $this->get_settings()['default_vertical'],
				'visible'  => $this->get_predefined_filter_visibility(),
				'terms'    => 'travel-categories-descriptions-1',
			)
		);

		if ( get_option( self::PREDEFINED_SYNC_KEY ) !== $state ) {
			$this->sync_predefined_terms();
			update_option( self::PREDEFINED_SYNC_KEY, $state );
		}
	}

	public function maybe_migrate_legacy_data() {
		if ( get_option( self::LEGACY_MIGRATION_KEY ) ) {
			return;
		}

		global $wpdb;

		$legacy_prefix = 'ab' . 'p';
		$legacy_cpt    = $legacy_prefix . '_package';
		$legacy_type   = $legacy_prefix . '_package_type';

		$this->migrate_legacy_options( $legacy_prefix );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time legacy post type migration; no WordPress API exists for bulk post type renames.
		$wpdb->update(
			$wpdb->posts,
			array( 'post_type' => self::CPT ),
			array( 'post_type' => $legacy_cpt ),
			array( '%s' ),
			array( '%s' )
		);

		$this->migrate_legacy_post_meta_key( '_' . $legacy_prefix . '_package_meta', self::META_KEY );
		$this->migrate_legacy_post_meta_key( '_' . $legacy_prefix . '_price_numeric', self::PRICE_NUMERIC_META_KEY );
		$this->migrate_legacy_post_meta_key( '_' . $legacy_prefix . '_duration_numeric', self::DURATION_NUMERIC_META_KEY );

		$this->migrate_legacy_taxonomy_name( $legacy_type, self::TYPE_TAX );

		foreach ( $this->get_filter_groups() as $group ) {
			if ( $this->is_range_filter_type( $group['input_type'] ) ) {
				continue;
			}

			$slug = str_replace( '-', '_', sanitize_title( $group['slug'] ) );
			$this->migrate_legacy_taxonomy_name( $legacy_prefix . '_' . $slug, $this->taxonomy_name_from_slug( $group['slug'] ) );
		}

		update_option( self::LEGACY_MIGRATION_KEY, gmdate( 'c' ), false );
		update_option( self::LEGACY_REWRITE_FLUSH_KEY, 1, false );
	}

	private function migrate_legacy_options( $legacy_prefix ) {
		$option_map = array(
			$legacy_prefix . '_settings'                     => self::SETTINGS_KEY,
			$legacy_prefix . '_filter_groups'                => self::FILTER_GROUPS_KEY,
			$legacy_prefix . '_predefined_filter_visibility' => self::PREDEFINED_VISIBILITY_KEY,
			$legacy_prefix . '_filter_order'                 => self::FILTER_ORDER_KEY,
		);

		foreach ( $option_map as $legacy_key => $new_key ) {
			$legacy_value = get_option( $legacy_key, null );
			if ( null === $legacy_value ) {
				continue;
			}

			$new_value = get_option( $new_key, null );
			$should_copy = null === $new_value;
			if ( ! $should_copy && is_array( $legacy_value ) && ! empty( $legacy_value ) && is_array( $new_value ) && empty( $new_value ) ) {
				$should_copy = true;
			}

			if ( $should_copy ) {
				update_option( $new_key, $legacy_value, false );
			}
		}
	}

	private function migrate_legacy_post_meta_key( $legacy_key, $new_key ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time legacy meta key migration; direct prepared update avoids loading every package meta row.
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->postmeta} SET meta_key = %s WHERE meta_key = %s",
				$new_key,
				$legacy_key
			)
		);
	}

	private function migrate_legacy_taxonomy_name( $legacy_taxonomy, $new_taxonomy ) {
		global $wpdb;

		if ( $legacy_taxonomy === $new_taxonomy ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time legacy taxonomy migration needs term_taxonomy IDs to preserve relationships.
		$legacy_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT tt.term_taxonomy_id, tt.term_id, t.slug
				FROM {$wpdb->term_taxonomy} tt
				INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
				WHERE tt.taxonomy = %s",
				$legacy_taxonomy
			)
		);

		foreach ( $legacy_rows as $legacy_row ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time legacy taxonomy merge checks whether the seeded destination term already exists.
			$existing = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT tt.term_taxonomy_id, tt.term_id
					FROM {$wpdb->term_taxonomy} tt
					INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
					WHERE tt.taxonomy = %s AND t.slug = %s
					LIMIT 1",
					$new_taxonomy,
					$legacy_row->slug
				)
			);

			if ( $existing && (int) $existing->term_taxonomy_id !== (int) $legacy_row->term_taxonomy_id ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time legacy taxonomy merge moves relationships to the existing destination term.
				$wpdb->update(
					$wpdb->term_relationships,
					array( 'term_taxonomy_id' => (int) $existing->term_taxonomy_id ),
					array( 'term_taxonomy_id' => (int) $legacy_row->term_taxonomy_id ),
					array( '%d' ),
					array( '%d' )
				);

				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time cleanup removes the duplicate legacy taxonomy row after relationships are moved.
				$wpdb->delete(
					$wpdb->term_taxonomy,
					array( 'term_taxonomy_id' => (int) $legacy_row->term_taxonomy_id ),
					array( '%d' )
				);

				$this->delete_orphaned_legacy_term( (int) $legacy_row->term_id );
				$this->refresh_term_taxonomy_count( (int) $existing->term_taxonomy_id );
				continue;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time legacy taxonomy rename; WordPress has no API for renaming taxonomy identifiers in place.
			$wpdb->update(
				$wpdb->term_taxonomy,
				array( 'taxonomy' => $new_taxonomy ),
				array( 'term_taxonomy_id' => (int) $legacy_row->term_taxonomy_id ),
				array( '%s' ),
				array( '%d' )
			);

			$this->refresh_term_taxonomy_count( (int) $legacy_row->term_taxonomy_id );
		}
	}

	private function delete_orphaned_legacy_term( $term_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time legacy cleanup checks whether a migrated term is still used by another taxonomy.
		$remaining = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->term_taxonomy} WHERE term_id = %d",
				$term_id
			)
		);

		if ( 0 !== $remaining ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time legacy cleanup removes orphaned term metadata.
		$wpdb->delete( $wpdb->termmeta, array( 'term_id' => $term_id ), array( '%d' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time legacy cleanup removes the orphaned term row.
		$wpdb->delete( $wpdb->terms, array( 'term_id' => $term_id ), array( '%d' ) );
	}

	private function refresh_term_taxonomy_count( $term_taxonomy_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time legacy taxonomy migration recalculates relationship counts for migrated terms.
		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->term_relationships} WHERE term_taxonomy_id = %d",
				$term_taxonomy_id
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time legacy taxonomy migration stores the recalculated relationship count.
		$wpdb->update(
			$wpdb->term_taxonomy,
			array( 'count' => $count ),
			array( 'term_taxonomy_id' => $term_taxonomy_id ),
			array( '%d' ),
			array( '%d' )
		);
	}

	private function migrate_custom_filter_groups() {
		$groups = get_option( self::FILTER_GROUPS_KEY, array() );
		if ( ! is_array( $groups ) ) {
			$groups = array();
		}

		$predefined_slugs = array_map(
			static function ( $group ) {
				return $group['slug'];
			},
			$this->get_all_predefined_filter_groups()
		);

		$legacy_slugs = array( 'destination', 'country', 'price', 'duration', 'important-holidays', 'travel-style' );
		$blocked      = array_unique( array_merge( $predefined_slugs, $legacy_slugs ) );
		$filtered     = array();

		foreach ( $groups as $group ) {
			$slug = ! empty( $group['slug'] ) ? sanitize_title( $group['slug'] ) : '';
			if ( '' === $slug || in_array( $slug, $blocked, true ) ) {
				continue;
			}

			$filtered[] = $group;
		}

		if ( $filtered !== $groups ) {
			update_option( self::FILTER_GROUPS_KEY, $filtered );
		}
	}

	private function sync_predefined_terms() {
		$term_sets = $this->get_predefined_term_sets();

		foreach ( $this->get_all_predefined_filter_groups() as $group ) {
			if ( $this->is_range_filter_type( $group['input_type'] ) ) {
				continue;
			}

			if ( empty( $term_sets[ $group['slug'] ] ) ) {
				continue;
			}

			$taxonomy = $this->taxonomy_name_from_slug( $group['slug'] );

			foreach ( $term_sets[ $group['slug'] ] as $term ) {
				$existing = get_term_by( 'slug', $term['slug'], $taxonomy );

				if ( $existing && ! is_wp_error( $existing ) ) {
					wp_update_term(
						$existing->term_id,
						$taxonomy,
						array(
							'name'        => $term['name'],
							'slug'        => $term['slug'],
							'description' => isset( $term['description'] ) ? $term['description'] : '',
						)
					);
				} else {
					wp_insert_term(
						$term['name'],
						$taxonomy,
						array(
							'slug'        => $term['slug'],
							'description' => isset( $term['description'] ) ? $term['description'] : '',
						)
					);
				}
			}
		}
	}

	private function get_predefined_term_sets() {
		$language = $this->get_current_language();
		$terms    = array(
			'destination' => array(
				array( 'slug' => 'far-east', 'en' => 'Far East', 'el' => 'Άπω Ανατολή' ),
				array( 'slug' => 'australia-oceania-pacific', 'en' => 'Australia - Oceania - Pacific', 'el' => 'Αυστραλία - Ωκεανία - Ειρηνικός' ),
				array( 'slug' => 'africa-indian-ocean', 'en' => 'Africa - Indian Ocean', 'el' => 'Αφρική - Ινδικός Ωκεανός' ),
				array( 'slug' => 'north-central-america-caribbean', 'en' => 'North - Central America & Caribbean', 'el' => 'Βόρεια - Κεντρική Αμερική & Καραϊβική' ),
				array( 'slug' => 'greece', 'en' => 'Greece', 'el' => 'Ελλάδα' ),
				array( 'slug' => 'europe', 'en' => 'Europe', 'el' => 'Ευρώπη' ),
				array( 'slug' => 'indian-peninsula', 'en' => 'Indian Peninsula', 'el' => 'Ινδική Χερσόνησος' ),
				array( 'slug' => 'middle-east', 'en' => 'Middle East', 'el' => 'Μέση Ανατολή' ),
				array( 'slug' => 'south-america', 'en' => 'South America', 'el' => 'Νότια Αμερική' ),
				array( 'slug' => 'southeast-asia', 'en' => 'Southeast Asia', 'el' => 'Νοτιοανατολική Ασία' ),
			),
			'important-holidays' => array(
				array( 'slug' => 'summer', 'en' => 'Summer', 'el' => 'Καλοκαίρι' ),
				array( 'slug' => 'christmas', 'en' => 'Christmas', 'el' => 'Χριστούγεννα' ),
				array( 'slug' => 'easter', 'en' => 'Easter', 'el' => 'Πάσχα' ),
				array( 'slug' => 'carnival-clean-monday', 'en' => 'Carnival / Clean Monday', 'el' => 'Απόκριες / Καθαρά Δευτέρα' ),
				array( 'slug' => 'may-day', 'en' => 'May Day', 'el' => 'Πρωτομαγιά' ),
				array( 'slug' => 'holy-spirit', 'en' => 'Holy Spirit', 'el' => 'Αγίου Πνεύματος' ),
				array( 'slug' => 'assumption-day', 'en' => 'Assumption Day', 'el' => 'Δεκαπενταύγουστος' ),
				array( 'slug' => 'october-28', 'en' => 'October 28', 'el' => '28η Οκτωβρίου' ),
				array( 'slug' => 'new-year-winter-getaways', 'en' => 'New Year / Winter Getaways', 'el' => 'Πρωτοχρονιά / Χειμερινές Αποδράσεις' ),
				array( 'slug' => 'epiphany', 'en' => 'Epiphany', 'el' => 'Θεοφάνεια' ),
			),
			'travel-style' => array(
				array( 'slug' => 'individual-packages', 'en' => 'Individual Packages', 'el' => 'Ατομικά Πακέτα' ),
				array( 'slug' => 'group-packages', 'en' => 'Group Packages', 'el' => 'Ομαδικά Πακέτα' ),
				array( 'slug' => 'cruises', 'en' => 'Cruises', 'el' => 'Κρουαζιέρες' ),
			),
			'country' => array(
				array( 'slug' => 'greece', 'en' => 'Greece', 'el' => 'Ελλάδα' ),
				array( 'slug' => 'italy', 'en' => 'Italy', 'el' => 'Ιταλία' ),
				array( 'slug' => 'france', 'en' => 'France', 'el' => 'Γαλλία' ),
				array( 'slug' => 'spain', 'en' => 'Spain', 'el' => 'Ισπανία' ),
				array( 'slug' => 'portugal', 'en' => 'Portugal', 'el' => 'Πορτογαλία' ),
				array( 'slug' => 'cyprus', 'en' => 'Cyprus', 'el' => 'Κύπρος' ),
				array( 'slug' => 'malta', 'en' => 'Malta', 'el' => 'Μάλτα' ),
				array( 'slug' => 'united-kingdom', 'en' => 'United Kingdom', 'el' => 'Ηνωμένο Βασίλειο' ),
				array( 'slug' => 'ireland', 'en' => 'Ireland', 'el' => 'Ιρλανδία' ),
				array( 'slug' => 'iceland', 'en' => 'Iceland', 'el' => 'Ισλανδία' ),
				array( 'slug' => 'norway', 'en' => 'Norway', 'el' => 'Νορβηγία' ),
				array( 'slug' => 'sweden', 'en' => 'Sweden', 'el' => 'Σουηδία' ),
				array( 'slug' => 'finland', 'en' => 'Finland', 'el' => 'Φινλανδία' ),
				array( 'slug' => 'denmark', 'en' => 'Denmark', 'el' => 'Δανία' ),
				array( 'slug' => 'netherlands', 'en' => 'Netherlands', 'el' => 'Ολλανδία' ),
				array( 'slug' => 'belgium', 'en' => 'Belgium', 'el' => 'Βέλγιο' ),
				array( 'slug' => 'germany', 'en' => 'Germany', 'el' => 'Γερμανία' ),
				array( 'slug' => 'austria', 'en' => 'Austria', 'el' => 'Αυστρία' ),
				array( 'slug' => 'switzerland', 'en' => 'Switzerland', 'el' => 'Ελβετία' ),
				array( 'slug' => 'czech-republic', 'en' => 'Czech Republic', 'el' => 'Τσεχία' ),
				array( 'slug' => 'hungary', 'en' => 'Hungary', 'el' => 'Ουγγαρία' ),
				array( 'slug' => 'poland', 'en' => 'Poland', 'el' => 'Πολωνία' ),
				array( 'slug' => 'croatia', 'en' => 'Croatia', 'el' => 'Κροατία' ),
				array( 'slug' => 'slovenia', 'en' => 'Slovenia', 'el' => 'Σλοβενία' ),
				array( 'slug' => 'montenegro', 'en' => 'Montenegro', 'el' => 'Μαυροβούνιο' ),
				array( 'slug' => 'albania', 'en' => 'Albania', 'el' => 'Αλβανία' ),
				array( 'slug' => 'serbia', 'en' => 'Serbia', 'el' => 'Σερβία' ),
				array( 'slug' => 'romania', 'en' => 'Romania', 'el' => 'Ρουμανία' ),
				array( 'slug' => 'bulgaria', 'en' => 'Bulgaria', 'el' => 'Βουλγαρία' ),
				array( 'slug' => 'turkey', 'en' => 'Turkey', 'el' => 'Τουρκία' ),
				array( 'slug' => 'egypt', 'en' => 'Egypt', 'el' => 'Αίγυπτος' ),
				array( 'slug' => 'morocco', 'en' => 'Morocco', 'el' => 'Μαρόκο' ),
				array( 'slug' => 'tunisia', 'en' => 'Tunisia', 'el' => 'Τυνησία' ),
				array( 'slug' => 'south-africa', 'en' => 'South Africa', 'el' => 'Νότια Αφρική' ),
				array( 'slug' => 'kenya', 'en' => 'Kenya', 'el' => 'Κένυα' ),
				array( 'slug' => 'tanzania', 'en' => 'Tanzania', 'el' => 'Τανζανία' ),
				array( 'slug' => 'seychelles', 'en' => 'Seychelles', 'el' => 'Σεϋχέλλες' ),
				array( 'slug' => 'mauritius', 'en' => 'Mauritius', 'el' => 'Μαυρίκιος' ),
				array( 'slug' => 'madagascar', 'en' => 'Madagascar', 'el' => 'Μαδαγασκάρη' ),
				array( 'slug' => 'united-arab-emirates', 'en' => 'United Arab Emirates', 'el' => 'Ηνωμένα Αραβικά Εμιράτα' ),
				array( 'slug' => 'jordan', 'en' => 'Jordan', 'el' => 'Ιορδανία' ),
				array( 'slug' => 'israel', 'en' => 'Israel', 'el' => 'Ισραήλ' ),
				array( 'slug' => 'saudi-arabia', 'en' => 'Saudi Arabia', 'el' => 'Σαουδική Αραβία' ),
				array( 'slug' => 'oman', 'en' => 'Oman', 'el' => 'Ομάν' ),
				array( 'slug' => 'qatar', 'en' => 'Qatar', 'el' => 'Κατάρ' ),
				array( 'slug' => 'india', 'en' => 'India', 'el' => 'Ινδία' ),
				array( 'slug' => 'nepal', 'en' => 'Nepal', 'el' => 'Νεπάλ' ),
				array( 'slug' => 'sri-lanka', 'en' => 'Sri Lanka', 'el' => 'Σρι Λάνκα' ),
				array( 'slug' => 'maldives', 'en' => 'Maldives', 'el' => 'Μαλδίβες' ),
				array( 'slug' => 'thailand', 'en' => 'Thailand', 'el' => 'Ταϊλάνδη' ),
				array( 'slug' => 'vietnam', 'en' => 'Vietnam', 'el' => 'Βιετνάμ' ),
				array( 'slug' => 'cambodia', 'en' => 'Cambodia', 'el' => 'Καμπότζη' ),
				array( 'slug' => 'laos', 'en' => 'Laos', 'el' => 'Λάος' ),
				array( 'slug' => 'malaysia', 'en' => 'Malaysia', 'el' => 'Μαλαισία' ),
				array( 'slug' => 'singapore', 'en' => 'Singapore', 'el' => 'Σιγκαπούρη' ),
				array( 'slug' => 'indonesia', 'en' => 'Indonesia', 'el' => 'Ινδονησία' ),
				array( 'slug' => 'philippines', 'en' => 'Philippines', 'el' => 'Φιλιππίνες' ),
				array( 'slug' => 'japan', 'en' => 'Japan', 'el' => 'Ιαπωνία' ),
				array( 'slug' => 'china', 'en' => 'China', 'el' => 'Κίνα' ),
				array( 'slug' => 'south-korea', 'en' => 'South Korea', 'el' => 'Νότια Κορέα' ),
				array( 'slug' => 'taiwan', 'en' => 'Taiwan', 'el' => 'Ταϊβάν' ),
				array( 'slug' => 'hong-kong', 'en' => 'Hong Kong', 'el' => 'Χονγκ Κονγκ' ),
				array( 'slug' => 'australia', 'en' => 'Australia', 'el' => 'Αυστραλία' ),
				array( 'slug' => 'new-zealand', 'en' => 'New Zealand', 'el' => 'Νέα Ζηλανδία' ),
				array( 'slug' => 'fiji', 'en' => 'Fiji', 'el' => 'Φίτζι' ),
				array( 'slug' => 'united-states', 'en' => 'United States', 'el' => 'Ηνωμένες Πολιτείες' ),
				array( 'slug' => 'canada', 'en' => 'Canada', 'el' => 'Καναδάς' ),
				array( 'slug' => 'mexico', 'en' => 'Mexico', 'el' => 'Μεξικό' ),
				array( 'slug' => 'cuba', 'en' => 'Cuba', 'el' => 'Κούβα' ),
				array( 'slug' => 'dominican-republic', 'en' => 'Dominican Republic', 'el' => 'Δομινικανή Δημοκρατία' ),
				array( 'slug' => 'jamaica', 'en' => 'Jamaica', 'el' => 'Τζαμάικα' ),
				array( 'slug' => 'bahamas', 'en' => 'Bahamas', 'el' => 'Μπαχάμες' ),
				array( 'slug' => 'costa-rica', 'en' => 'Costa Rica', 'el' => 'Κόστα Ρίκα' ),
				array( 'slug' => 'panama', 'en' => 'Panama', 'el' => 'Παναμάς' ),
				array( 'slug' => 'brazil', 'en' => 'Brazil', 'el' => 'Βραζιλία' ),
				array( 'slug' => 'argentina', 'en' => 'Argentina', 'el' => 'Αργεντινή' ),
				array( 'slug' => 'peru', 'en' => 'Peru', 'el' => 'Περού' ),
				array( 'slug' => 'chile', 'en' => 'Chile', 'el' => 'Χιλή' ),
				array( 'slug' => 'colombia', 'en' => 'Colombia', 'el' => 'Κολομβία' ),
			),
		);

		$prepared = array();
		foreach ( $terms as $group_slug => $group_terms ) {
			$prepared[ $group_slug ] = array_map(
				function ( $term ) use ( $language, $group_slug ) {
					$name = $term[ $language ];

					return array(
						'slug'        => $term['slug'],
						'name'        => $name,
						'description' => $this->get_seed_term_description( $group_slug, $name, $language ),
					);
				},
				$group_terms
			);
		}

		return $prepared;
	}

	private function get_seed_term_description( $group_slug, $name, $language ) {
		$descriptions = array(
			'destination' => array(
				'en' => 'Discover curated travel packages for %s, with hand-picked routes, trusted accommodation options, flexible durations, and memorable experiences designed for easy holiday planning.',
				'el' => 'Ανακαλύψτε επιλεγμένα ταξιδιωτικά πακέτα για %s, με προσεγμένες διαδρομές, αξιόπιστες επιλογές διαμονής, ευέλικτη διάρκεια και εμπειρίες που κάνουν τον προγραμματισμό του ταξιδιού πιο εύκολο.',
			),
			'country' => array(
				'en' => 'Explore travel packages to %s with carefully selected hotels, seasonal offers, organized services, and destination ideas for city breaks, holidays, tours, and relaxing escapes.',
				'el' => 'Εξερευνήστε ταξιδιωτικά πακέτα για %s με επιλεγμένα ξενοδοχεία, εποχικές προσφορές, οργανωμένες υπηρεσίες και ιδέες για αποδράσεις, διακοπές, εκδρομές και ξεκούραστα ταξίδια.',
			),
			'important-holidays' => array(
				'en' => 'Find travel packages for %s, ideal for holiday getaways, long weekends, family trips, romantic escapes, and seasonal journeys planned around the most popular travel dates.',
				'el' => 'Βρείτε ταξιδιωτικά πακέτα για %s, ιδανικά για εορταστικές αποδράσεις, τριήμερα, οικογενειακά ταξίδια, ρομαντικές εξορμήσεις και εποχικά προγράμματα στις πιο δημοφιλείς ημερομηνίες.',
			),
			'travel-style' => array(
				'en' => 'Browse %s designed for different travel preferences, from flexible private arrangements to organized experiences, curated routes, and package options that make choosing easier.',
				'el' => 'Δείτε %s σχεδιασμένα για διαφορετικές ταξιδιωτικές ανάγκες, από ευέλικτες ατομικές επιλογές μέχρι οργανωμένες εμπειρίες, προσεγμένες διαδρομές και πακέτα που κάνουν την επιλογή πιο απλή.',
			),
		);

		if ( empty( $descriptions[ $group_slug ][ $language ] ) ) {
			return '';
		}

		return sprintf( $descriptions[ $group_slug ][ $language ], $name );
	}
}
