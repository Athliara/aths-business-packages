<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ATHSBP_Admin {
	/**
	 * @var ATHSBP_Plugin
	 */
	private $plugin;

	public function __construct( ATHSBP_Plugin $plugin ) {
		$this->plugin = $plugin;

		add_action( 'admin_menu', array( $this, 'register_admin_pages' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'add_meta_boxes', array( $this, 'register_meta_boxes' ) );
		add_action( 'save_post_' . ATHSBP_Plugin::CPT, array( $this, 'save_package_meta' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function register_admin_pages() {
		add_submenu_page(
			'edit.php?post_type=' . ATHSBP_Plugin::CPT,
			__( 'Package Builder Settings', 'aths-business-packages' ),
			__( 'Settings', 'aths-business-packages' ),
			'manage_options',
			'athsbp-settings',
			array( $this, 'render_settings_page' )
		);
	}

	public function register_settings() {
		register_setting(
			'athsbp_settings_group',
			ATHSBP_Plugin::SETTINGS_KEY,
			array( $this, 'sanitize_settings' )
		);

		register_setting(
			'athsbp_settings_group',
			ATHSBP_Plugin::FILTER_GROUPS_KEY,
			array( $this, 'sanitize_filter_groups' )
		);

		register_setting(
			'athsbp_settings_group',
			ATHSBP_Plugin::PREDEFINED_VISIBILITY_KEY,
			array( $this, 'sanitize_predefined_visibility' )
		);

		register_setting(
			'athsbp_settings_group',
			ATHSBP_Plugin::FILTER_ORDER_KEY,
			array( $this, 'sanitize_filter_order' )
		);
	}

	public function sanitize_settings( $settings ) {
		$defaults = $this->plugin->get_default_settings();
		$current  = wp_parse_args( get_option( ATHSBP_Plugin::SETTINGS_KEY, array() ), $defaults );
		$settings = is_array( $settings ) ? $settings : array();

		return array(
			'business_name'       => $defaults['business_name'],
			'business_subtitle'   => $defaults['business_subtitle'],
			'default_vertical'    => ! empty( $settings['default_vertical'] ) && array_key_exists( $settings['default_vertical'], $this->plugin->get_business_type_options() ) ? sanitize_title( $settings['default_vertical'] ) : $current['default_vertical'],
			'language'            => ! empty( $settings['language'] ) && array_key_exists( $settings['language'], $this->plugin->get_language_options() ) ? sanitize_text_field( $settings['language'] ) : $current['language'],
			'currency'            => ! empty( $settings['currency'] ) && array_key_exists( $settings['currency'], $this->plugin->get_currency_options() ) ? sanitize_text_field( $settings['currency'] ) : $current['currency'],
			'archive_title'       => isset( $settings['archive_title'] ) && '' !== $settings['archive_title'] ? sanitize_text_field( $settings['archive_title'] ) : $current['archive_title'],
			'archive_intro'       => isset( $settings['archive_intro'] ) && '' !== $settings['archive_intro'] ? sanitize_textarea_field( $settings['archive_intro'] ) : $current['archive_intro'],
			'related_title'       => isset( $settings['related_title'] ) && '' !== $settings['related_title'] ? sanitize_text_field( $settings['related_title'] ) : $current['related_title'],
			'description_heading' => ! empty( $settings['description_heading'] ) ? sanitize_text_field( $settings['description_heading'] ) : $current['description_heading'],
			'includes_heading'    => ! empty( $settings['includes_heading'] ) ? sanitize_text_field( $settings['includes_heading'] ) : $current['includes_heading'],
			'style_label_text_color'       => $this->sanitize_hex_setting( $settings, $current, 'style_label_text_color' ),
			'style_label_background_color' => $this->sanitize_hex_setting( $settings, $current, 'style_label_background_color' ),
			'style_tag_text_color'         => $this->sanitize_hex_setting( $settings, $current, 'style_tag_text_color' ),
			'style_tag_background_color'   => $this->sanitize_hex_setting( $settings, $current, 'style_tag_background_color' ),
			'style_card_badge_text_color'       => $this->sanitize_hex_setting( $settings, $current, 'style_card_badge_text_color' ),
			'style_card_badge_background_color' => $this->sanitize_hex_setting( $settings, $current, 'style_card_badge_background_color' ),
			'style_title_color'            => $this->sanitize_hex_setting( $settings, $current, 'style_title_color' ),
			'style_subtitle_color'         => $this->sanitize_hex_setting( $settings, $current, 'style_subtitle_color' ),
			'style_slider_active_color'    => $this->sanitize_hex_setting( $settings, $current, 'style_slider_active_color' ),
			'style_slider_track_color'     => $this->sanitize_hex_setting( $settings, $current, 'style_slider_track_color' ),
			'style_slider_thumb_color'     => $this->sanitize_hex_setting( $settings, $current, 'style_slider_thumb_color' ),
			'style_pagination_text_color'              => $this->sanitize_hex_setting( $settings, $current, 'style_pagination_text_color' ),
			'style_pagination_background_color'        => $this->sanitize_hex_setting( $settings, $current, 'style_pagination_background_color' ),
			'style_pagination_border_color'            => $this->sanitize_hex_setting( $settings, $current, 'style_pagination_border_color' ),
			'style_pagination_active_text_color'       => $this->sanitize_hex_setting( $settings, $current, 'style_pagination_active_text_color' ),
			'style_pagination_active_background_color' => $this->sanitize_hex_setting( $settings, $current, 'style_pagination_active_background_color' ),
		);
	}

	private function sanitize_hex_setting( $settings, $current, $key ) {
		if ( ! isset( $settings[ $key ] ) ) {
			return $current[ $key ];
		}

		$value = sanitize_hex_color( $settings[ $key ] );

		return $value ? $value : $current[ $key ];
	}

	public function sanitize_filter_groups( $groups ) {
		if ( ! is_array( $groups ) ) {
			return array();
		}

		$clean = array();
		$blocked_slugs = array_map(
			static function ( $group ) {
				return $group['slug'];
			},
			$this->plugin->get_all_predefined_filter_groups()
		);

		foreach ( $groups as $group ) {
			$label = ! empty( $group['label'] ) ? sanitize_text_field( $group['label'] ) : '';
			$slug  = ! empty( $group['slug'] ) ? sanitize_title( $group['slug'] ) : sanitize_title( $label );

			if ( empty( $label ) || empty( $slug ) || in_array( $slug, $blocked_slugs, true ) ) {
				continue;
			}

			$clean[] = array(
				'label'       => $label,
				'slug'        => $slug,
				'singular'    => ! empty( $group['singular'] ) ? sanitize_text_field( $group['singular'] ) : $label,
				'input_type'  => ! empty( $group['input_type'] ) && in_array( $group['input_type'], array( 'checkbox', 'select', 'range_price', 'range_duration' ), true ) ? $group['input_type'] : 'checkbox',
				'show_counts' => ! empty( $group['show_counts'] ) ? 1 : 0,
			);
		}

		return $clean;
	}

	public function sanitize_predefined_visibility( $visibility ) {
		$clean = array();

		if ( ! is_array( $visibility ) ) {
			$visibility = array();
		}

		foreach ( $this->plugin->get_all_predefined_filter_groups() as $group ) {
			$slug          = $group['slug'];
			$clean[ $slug ] = ! empty( $visibility[ $slug ] ) ? 1 : 0;
		}

		return $clean;
	}

	public function sanitize_filter_order( $order ) {
		if ( ! is_array( $order ) ) {
			return array();
		}

		$allowed = array();
		foreach ( array_merge( $this->plugin->get_all_predefined_filter_groups(), $this->plugin->get_custom_filter_groups() ) as $group ) {
			$allowed[] = $group['slug'];
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Settings API verifies the options form nonce; each group field is sanitized below.
		$posted_groups = isset( $_POST[ ATHSBP_Plugin::FILTER_GROUPS_KEY ] ) ? wp_unslash( $_POST[ ATHSBP_Plugin::FILTER_GROUPS_KEY ] ) : array();
		if ( is_array( $posted_groups ) ) {
			foreach ( $posted_groups as $group ) {
				if ( ! is_array( $group ) ) {
					continue;
				}

				$label = ! empty( $group['label'] ) ? sanitize_text_field( $group['label'] ) : '';
				$slug  = ! empty( $group['slug'] ) ? sanitize_title( $group['slug'] ) : sanitize_title( $label );
				if ( '' !== $slug ) {
					$allowed[] = $slug;
				}
			}
		}

		$allowed = array_unique( $allowed );
		$clean = array();
		foreach ( $order as $slug ) {
			$slug = sanitize_title( $slug );
			if ( '' === $slug || ! in_array( $slug, $allowed, true ) || in_array( $slug, $clean, true ) ) {
				continue;
			}

			$clean[] = $slug;
		}

		return $clean;
	}

	public function register_meta_boxes() {
		$editor_labels = $this->get_editor_labels();

		add_meta_box(
			'athsbp-package-details',
			$editor_labels['package_details'],
			array( $this, 'render_package_meta_box' ),
			ATHSBP_Plugin::CPT,
			'normal',
			'high'
		);
	}

	public function enqueue_assets( $hook ) {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		$allowed_ids = array( ATHSBP_Plugin::CPT, ATHSBP_Plugin::CPT . '_page_athsbp-settings' );
		if ( ! in_array( $screen->id, $allowed_ids, true ) ) {
			return;
		}

		$admin_css_path = ATHSBP_PLUGIN_DIR . 'assets/css/admin.css';
		$admin_js_path  = ATHSBP_PLUGIN_DIR . 'assets/js/admin.js';
		$admin_css_ver  = file_exists( $admin_css_path ) ? (string) filemtime( $admin_css_path ) : ATHSBP_VERSION;
		$admin_js_ver   = file_exists( $admin_js_path ) ? (string) filemtime( $admin_js_path ) : ATHSBP_VERSION;

		wp_enqueue_media();
		wp_enqueue_style( 'athsbp-admin', ATHSBP_PLUGIN_URL . 'assets/css/admin.css', array(), $admin_css_ver );
		wp_enqueue_script( 'athsbp-admin', ATHSBP_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery', 'wp-util' ), $admin_js_ver, true );
		$editor_labels = $this->get_editor_labels();
		wp_localize_script(
			'athsbp-admin',
			'athsbpAdmin',
			array(
				'galleryTitle'    => $editor_labels['choose_gallery_images'],
				'galleryButton'   => $editor_labels['use_images'],
				'pdfTitle'       => $editor_labels['choose_pdf'],
				'pdfButton'      => $editor_labels['use_pdf'],
				'cellPlaceholder' => $editor_labels['cell_content'],
				'columnTitlePlaceholder' => $editor_labels['column_title'],
				'rowTitlePlaceholder'    => $editor_labels['row_title'],
			)
		);
	}

	public function render_settings_page() {
		$settings              = $this->plugin->get_settings();
		$custom_filter_groups  = $this->plugin->get_custom_filter_groups();
		$predefined_filters    = $this->plugin->get_all_predefined_filter_groups();
		$predefined_visibility = $this->plugin->get_predefined_filter_visibility();
		$ordered_filters       = $this->plugin->order_filter_groups( array_merge( $predefined_filters, $custom_filter_groups ) );
		$predefined_slugs      = wp_list_pluck( $predefined_filters, 'slug' );
		$custom_filter_indexes = array();
		$business_type_options = $this->plugin->get_business_type_options();
		$current_business_type = isset( $business_type_options[ $settings['default_vertical'] ] ) ? $business_type_options[ $settings['default_vertical'] ] : $business_type_options['travel-agency'];
		$current_setup_text    = 'insurance-broker' === $settings['default_vertical']
			? __( 'The plugin is currently configured for insurance brokers. It starts with shared package tools and the price-range preset, while extra filters can be added for broker-specific needs.', 'aths-business-packages' )
			: __( 'The plugin is currently configured for travel agencies, with travel-focused filters, country and holiday presets, and room for extra custom filters.', 'aths-business-packages' );

		foreach ( $custom_filter_groups as $index => $group ) {
			$custom_filter_indexes[ $group['slug'] ] = $index;
		}
		?>
		<div class="wrap abp-settings athsbp-settings">
			<div class="abp-settings-hero athsbp-settings-hero">
				<div class="abp-settings-hero-copy athsbp-settings-hero-copy">
					<span class="abp-settings-kicker athsbp-settings-kicker"><?php esc_html_e( 'Travel Package Builder', 'aths-business-packages' ); ?></span>
					<h1><?php esc_html_e( 'Ath\'s Business Packages', 'aths-business-packages' ); ?></h1>
					<p><?php esc_html_e( 'A more polished package-management experience for travel agencies today, with room to adapt the wording for other business types later.', 'aths-business-packages' ); ?></p>
					<div class="abp-hero-pills">
						<span><?php esc_html_e( 'Structured editor', 'aths-business-packages' ); ?></span>
						<span><?php esc_html_e( 'Theme-friendly output', 'aths-business-packages' ); ?></span>
						<span><?php esc_html_e( 'Flexible filters', 'aths-business-packages' ); ?></span>
					</div>
				</div>
				<div class="abp-settings-hero-panel athsbp-settings-hero-panel">
					<strong><?php esc_html_e( 'Current setup', 'aths-business-packages' ); ?></strong>
					<span><?php echo esc_html( $current_business_type ); ?></span>
					<p><?php echo esc_html( $current_setup_text ); ?></p>
				</div>
			</div>

			<nav class="abp-settings-tabs athsbp-settings-tabs" aria-label="<?php esc_attr_e( 'Settings sections', 'aths-business-packages' ); ?>">
				<button type="button" class="abp-settings-tab athsbp-settings-tab is-active" data-athsbp-settings-tab="general"><?php esc_html_e( 'General Branding', 'aths-business-packages' ); ?></button>
				<button type="button" class="abp-settings-tab athsbp-settings-tab" data-athsbp-settings-tab="styling"><?php esc_html_e( 'Styling', 'aths-business-packages' ); ?></button>
			</nav>

			<form method="post" action="options.php">
				<?php settings_fields( 'athsbp_settings_group' ); ?>

				<div class="abp-settings-panel athsbp-settings-panel is-active" data-athsbp-settings-panel="general">
				<div class="abp-settings-card athsbp-settings-card">
					<div class="abp-section-heading">
						<div>
							<h2><?php esc_html_e( 'General Branding', 'aths-business-packages' ); ?></h2>
							<p><?php esc_html_e( 'These values shape the plugin wording and the package archive presentation.', 'aths-business-packages' ); ?></p>
						</div>
					</div>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="abp-default-vertical"><?php esc_html_e( 'Default Business Type', 'aths-business-packages' ); ?></label></th>
							<td>
								<select id="abp-default-vertical" name="<?php echo esc_attr( ATHSBP_Plugin::SETTINGS_KEY ); ?>[default_vertical]">
									<?php foreach ( $this->plugin->get_business_type_options() as $vertical_slug => $vertical_label ) : ?>
										<option value="<?php echo esc_attr( $vertical_slug ); ?>" <?php selected( $settings['default_vertical'], $vertical_slug ); ?>><?php echo esc_html( $vertical_label ); ?></option>
									<?php endforeach; ?>
								</select>
								<p class="description"><?php esc_html_e( 'This controls which predefined filters and seeded terms the plugin provides by default.', 'aths-business-packages' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="abp-language"><?php esc_html_e( 'Display Language', 'aths-business-packages' ); ?></label></th>
							<td>
								<select id="abp-language" name="<?php echo esc_attr( ATHSBP_Plugin::SETTINGS_KEY ); ?>[language]">
									<?php foreach ( $this->plugin->get_language_options() as $language_code => $language_label ) : ?>
										<option value="<?php echo esc_attr( $language_code ); ?>" <?php selected( $settings['language'], $language_code ); ?>><?php echo esc_html( $language_label ); ?></option>
									<?php endforeach; ?>
								</select>
								<p class="description"><?php esc_html_e( 'Predefined filter labels, seeded country/holiday terms, and frontend package UI text will follow this language.', 'aths-business-packages' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="abp-currency"><?php esc_html_e( 'Currency', 'aths-business-packages' ); ?></label></th>
							<td>
								<select id="abp-currency" name="<?php echo esc_attr( ATHSBP_Plugin::SETTINGS_KEY ); ?>[currency]">
									<?php foreach ( $this->plugin->get_currency_options() as $currency_code => $currency ) : ?>
										<option value="<?php echo esc_attr( $currency_code ); ?>" <?php selected( $settings['currency'], $currency_code ); ?>><?php echo esc_html( $currency['label'] ); ?></option>
									<?php endforeach; ?>
								</select>
								<p class="description"><?php esc_html_e( 'Used for price display and price range filters across package pages.', 'aths-business-packages' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="abp-archive-title"><?php esc_html_e( 'Archive Heading', 'aths-business-packages' ); ?></label></th>
							<td><input id="abp-archive-title" name="<?php echo esc_attr( ATHSBP_Plugin::SETTINGS_KEY ); ?>[archive_title]" type="text" class="regular-text" value="<?php echo esc_attr( $this->plugin->get_localized_setting( 'archive_title' ) ); ?>"></td>
						</tr>
						<tr>
							<th scope="row"><label for="abp-archive-intro"><?php esc_html_e( 'Archive Intro', 'aths-business-packages' ); ?></label></th>
							<td><textarea id="abp-archive-intro" name="<?php echo esc_attr( ATHSBP_Plugin::SETTINGS_KEY ); ?>[archive_intro]" rows="4" class="large-text"><?php echo esc_textarea( $this->plugin->get_localized_setting( 'archive_intro' ) ); ?></textarea></td>
						</tr>
						<tr>
							<th scope="row"><label for="abp-related-title"><?php esc_html_e( 'Similar Packages Heading', 'aths-business-packages' ); ?></label></th>
							<td><input id="abp-related-title" name="<?php echo esc_attr( ATHSBP_Plugin::SETTINGS_KEY ); ?>[related_title]" type="text" class="regular-text" value="<?php echo esc_attr( $this->plugin->get_localized_setting( 'related_title' ) ); ?>"></td>
						</tr>
					</table>
				</div>

				<div class="abp-settings-card athsbp-settings-card">
					<div class="abp-section-heading">
						<div>
							<h2><?php esc_html_e( 'Filter Groups', 'aths-business-packages' ); ?></h2>
							<p><?php esc_html_e( 'Drag the existing rows by the handle to control the frontend filter order. Built-in rows stay read-only, while extra rows remain editable.', 'aths-business-packages' ); ?></p>
						</div>
					</div>
					<div class="abp-filter-groups" data-next-index="<?php echo esc_attr( count( $custom_filter_groups ) ); ?>">
						<div class="abp-filter-group-head">
							<span></span>
							<span><?php esc_html_e( 'Label', 'aths-business-packages' ); ?></span>
							<span><?php esc_html_e( 'Slug', 'aths-business-packages' ); ?></span>
							<span><?php esc_html_e( 'Singular', 'aths-business-packages' ); ?></span>
							<span><?php esc_html_e( 'Input Type', 'aths-business-packages' ); ?></span>
							<span><?php esc_html_e( 'Show / Counts', 'aths-business-packages' ); ?></span>
							<span></span>
						</div>

						<div class="abp-filter-group-list abp-sortable-filter-list">
							<?php foreach ( $ordered_filters as $group ) : ?>
								<?php if ( in_array( $group['slug'], $predefined_slugs, true ) ) : ?>
									<div class="abp-filter-group-row abp-sortable-filter-row" data-filter-slug="<?php echo esc_attr( $group['slug'] ); ?>">
										<?php $this->render_drag_handle( $group['slug'] ); ?>
										<span class="abp-readonly-value"><?php echo esc_html( $group['label'] ); ?></span>
										<span class="abp-readonly-value"><?php echo esc_html( $group['slug'] ); ?></span>
										<span class="abp-readonly-value"><?php echo esc_html( $group['singular'] ); ?></span>
										<span class="abp-readonly-value"><?php echo esc_html( $this->get_filter_input_type_label( $group['input_type'] ) ); ?></span>
										<label class="abp-inline-checkbox">
											<input type="checkbox" name="<?php echo esc_attr( ATHSBP_Plugin::PREDEFINED_VISIBILITY_KEY ); ?>[<?php echo esc_attr( $group['slug'] ); ?>]" value="1" <?php checked( ! empty( $predefined_visibility[ $group['slug'] ] ) ); ?>>
											<span><?php esc_html_e( 'Show', 'aths-business-packages' ); ?></span>
										</label>
										<span class="abp-readonly-badge"><?php esc_html_e( 'Built-in', 'aths-business-packages' ); ?></span>
									</div>
								<?php else : ?>
									<?php $this->render_filter_group_row( isset( $custom_filter_indexes[ $group['slug'] ] ) ? $custom_filter_indexes[ $group['slug'] ] : count( $custom_filter_indexes ), $group ); ?>
								<?php endif; ?>
							<?php endforeach; ?>
						</div>

						<button type="button" class="button button-secondary abp-add-filter-group"><?php esc_html_e( 'Add Filter Group', 'aths-business-packages' ); ?></button>
					</div>
				</div>
				</div>

				<div class="abp-settings-panel athsbp-settings-panel" data-athsbp-settings-panel="styling">
				<div class="abp-settings-card athsbp-settings-card">
					<div class="abp-section-heading">
						<div>
							<h2><?php esc_html_e( 'Styling', 'aths-business-packages' ); ?></h2>
							<p><?php esc_html_e( 'Set hex colors for package labels, tags, titles, and subtitles on the frontend.', 'aths-business-packages' ); ?></p>
						</div>
					</div>
					<table class="form-table abp-style-table" role="presentation">
						<?php $this->render_style_color_row( 'style_label_text_color', __( 'Label Text Color', 'aths-business-packages' ), __( 'Used for small labels such as archive/single package pills and info labels.', 'aths-business-packages' ), $settings ); ?>
						<?php $this->render_style_color_row( 'style_label_background_color', __( 'Label Background Color', 'aths-business-packages' ), __( 'Used behind small label pills.', 'aths-business-packages' ), $settings ); ?>
						<?php $this->render_style_color_row( 'style_tag_text_color', __( 'Tag Text Color', 'aths-business-packages' ), __( 'Used for package image badges and package type tags.', 'aths-business-packages' ), $settings ); ?>
						<?php $this->render_style_color_row( 'style_tag_background_color', __( 'Tag Background Color', 'aths-business-packages' ), __( 'Used behind package image badges and package type tags.', 'aths-business-packages' ), $settings ); ?>
						<?php $this->render_style_color_row( 'style_card_badge_text_color', __( 'Card Image Label Text Color', 'aths-business-packages' ), __( 'Used only for the label shown over package card images.', 'aths-business-packages' ), $settings ); ?>
						<?php $this->render_style_color_row( 'style_card_badge_background_color', __( 'Card Image Label Background Color', 'aths-business-packages' ), __( 'Used only behind the label shown over package card images.', 'aths-business-packages' ), $settings ); ?>
						<?php $this->render_style_color_row( 'style_title_color', __( 'Title Color', 'aths-business-packages' ), __( 'Used for archive titles, single package titles, card titles, filter headings, and section headings.', 'aths-business-packages' ), $settings ); ?>
						<?php $this->render_style_color_row( 'style_subtitle_color', __( 'Subtitle Color', 'aths-business-packages' ), __( 'Used for archive intro text, package subtitles, card subtitles, and descriptive text accents.', 'aths-business-packages' ), $settings ); ?>
						<?php $this->render_style_color_row( 'style_slider_active_color', __( 'Slider Active Color', 'aths-business-packages' ), __( 'Used for the selected range segment on price and duration sliders.', 'aths-business-packages' ), $settings ); ?>
						<?php $this->render_style_color_row( 'style_slider_track_color', __( 'Slider Track Color', 'aths-business-packages' ), __( 'Used for the inactive range track on price and duration sliders.', 'aths-business-packages' ), $settings ); ?>
						<?php $this->render_style_color_row( 'style_slider_thumb_color', __( 'Slider Button Color', 'aths-business-packages' ), __( 'Used for the draggable slider handles.', 'aths-business-packages' ), $settings ); ?>
						<?php $this->render_style_color_row( 'style_pagination_text_color', __( 'Pagination Text Color', 'aths-business-packages' ), __( 'Used for normal pagination numbers and next/previous links.', 'aths-business-packages' ), $settings ); ?>
						<?php $this->render_style_color_row( 'style_pagination_background_color', __( 'Pagination Background Color', 'aths-business-packages' ), __( 'Used behind normal pagination links.', 'aths-business-packages' ), $settings ); ?>
						<?php $this->render_style_color_row( 'style_pagination_border_color', __( 'Pagination Border Color', 'aths-business-packages' ), __( 'Used for pagination link borders.', 'aths-business-packages' ), $settings ); ?>
						<?php $this->render_style_color_row( 'style_pagination_active_text_color', __( 'Active Pagination Text Color', 'aths-business-packages' ), __( 'Used for the currently selected pagination number.', 'aths-business-packages' ), $settings ); ?>
						<?php $this->render_style_color_row( 'style_pagination_active_background_color', __( 'Active Pagination Background Color', 'aths-business-packages' ), __( 'Used behind the currently selected pagination number.', 'aths-business-packages' ), $settings ); ?>
					</table>
				</div>
				</div>

				<?php submit_button( __( 'Save Settings', 'aths-business-packages' ) ); ?>
			</form>

			<script type="text/html" id="tmpl-abp-filter-group-row">
				<?php
				$this->render_filter_group_row(
					'{{data.index}}',
					array(
						'label'       => '',
						'slug'        => '',
						'singular'    => '',
						'input_type'  => 'checkbox',
						'show_counts' => 0,
					)
				);
				?>
			</script>
		</div>
		<?php
	}

	private function render_style_color_row( $key, $label, $description, $settings ) {
		$value = isset( $settings[ $key ] ) ? $settings[ $key ] : '';
		?>
		<tr>
			<th scope="row"><label for="abp-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td>
				<div class="abp-color-field">
					<span class="abp-color-swatch" style="background-color: <?php echo esc_attr( $value ); ?>"></span>
					<input id="abp-<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( ATHSBP_Plugin::SETTINGS_KEY ); ?>[<?php echo esc_attr( $key ); ?>]" type="text" class="regular-text" value="<?php echo esc_attr( $value ); ?>" placeholder="#183b69" pattern="^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$">
				</div>
				<p class="description"><?php echo esc_html( $description ); ?></p>
			</td>
		</tr>
		<?php
	}

	private function render_drag_handle( $slug ) {
		?>
		<button type="button" class="abp-drag-handle" aria-label="<?php esc_attr_e( 'Drag to reorder', 'aths-business-packages' ); ?>">
			<span></span>
			<span></span>
			<span></span>
		</button>
		<input type="hidden" class="abp-filter-order-input" name="<?php echo esc_attr( ATHSBP_Plugin::FILTER_ORDER_KEY ); ?>[]" value="<?php echo esc_attr( $slug ); ?>">
		<?php
	}

	private function render_filter_group_row( $index, $group ) {
		?>
		<div class="abp-filter-group-row abp-sortable-filter-row" data-filter-slug="<?php echo esc_attr( $group['slug'] ); ?>">
			<?php $this->render_drag_handle( $group['slug'] ); ?>
			<input type="text" class="abp-filter-label" name="<?php echo esc_attr( ATHSBP_Plugin::FILTER_GROUPS_KEY ); ?>[<?php echo esc_attr( $index ); ?>][label]" value="<?php echo esc_attr( $group['label'] ); ?>" placeholder="<?php esc_attr_e( 'Destination', 'aths-business-packages' ); ?>">
			<input type="text" class="abp-filter-slug" name="<?php echo esc_attr( ATHSBP_Plugin::FILTER_GROUPS_KEY ); ?>[<?php echo esc_attr( $index ); ?>][slug]" value="<?php echo esc_attr( $group['slug'] ); ?>" placeholder="<?php esc_attr_e( 'destination', 'aths-business-packages' ); ?>">
			<input type="text" name="<?php echo esc_attr( ATHSBP_Plugin::FILTER_GROUPS_KEY ); ?>[<?php echo esc_attr( $index ); ?>][singular]" value="<?php echo esc_attr( $group['singular'] ); ?>" placeholder="<?php esc_attr_e( 'Destination', 'aths-business-packages' ); ?>">
			<select name="<?php echo esc_attr( ATHSBP_Plugin::FILTER_GROUPS_KEY ); ?>[<?php echo esc_attr( $index ); ?>][input_type]">
				<option value="checkbox" <?php selected( $group['input_type'], 'checkbox' ); ?>><?php esc_html_e( 'Checkboxes', 'aths-business-packages' ); ?></option>
				<option value="select" <?php selected( $group['input_type'], 'select' ); ?>><?php esc_html_e( 'Dropdown', 'aths-business-packages' ); ?></option>
				<option value="range_price" <?php selected( $group['input_type'], 'range_price' ); ?>><?php esc_html_e( 'Price Range Slider', 'aths-business-packages' ); ?></option>
				<option value="range_duration" <?php selected( $group['input_type'], 'range_duration' ); ?>><?php esc_html_e( 'Duration Range Slider', 'aths-business-packages' ); ?></option>
			</select>
			<label class="abp-inline-checkbox">
				<input type="checkbox" name="<?php echo esc_attr( ATHSBP_Plugin::FILTER_GROUPS_KEY ); ?>[<?php echo esc_attr( $index ); ?>][show_counts]" value="1" <?php checked( ! empty( $group['show_counts'] ) ); ?>>
				<span><?php esc_html_e( 'Show', 'aths-business-packages' ); ?></span>
			</label>
			<button type="button" class="button-link-delete abp-remove-filter-group"><?php esc_html_e( 'Remove', 'aths-business-packages' ); ?></button>
		</div>
		<?php
	}

	private function get_filter_input_type_label( $input_type ) {
		$labels = array(
			'checkbox'       => __( 'Checkboxes', 'aths-business-packages' ),
			'select'         => __( 'Dropdown', 'aths-business-packages' ),
			'range_price'    => __( 'Price Range Slider', 'aths-business-packages' ),
			'range_duration' => __( 'Duration Range Slider', 'aths-business-packages' ),
		);

		return isset( $labels[ $input_type ] ) ? $labels[ $input_type ] : $input_type;
	}

	public function render_package_meta_box( $post ) {
		$meta        = $this->plugin->get_package_meta( $post->ID );
		$labels      = $this->get_editor_labels();
		wp_nonce_field( 'athsbp_save_package_meta', 'athsbp_package_meta_nonce' );
		?>
		<div class="abp-editor-intro">
			<div>
				<h3><?php echo esc_html( $labels['package_editor'] ); ?></h3>
				<p><?php echo esc_html( $labels['package_editor_intro'] ); ?></p>
			</div>
			<div class="abp-editor-tip">
				<strong><?php echo esc_html( $labels['recommended_flow'] ); ?></strong>
				<span><?php echo esc_html( $labels['recommended_flow_steps'] ); ?></span>
			</div>
		</div>

		<div class="abp-meta-section">
			<div class="abp-meta-section-header">
				<h4><?php echo esc_html( $labels['core_package_info'] ); ?></h4>
				<p><?php echo esc_html( $labels['core_package_info_desc'] ); ?></p>
			</div>
			<div class="abp-meta-grid">
				<div class="abp-field">
					<label for="abp-subtitle"><?php echo esc_html( $labels['subtitle'] ); ?></label>
					<input id="abp-subtitle" type="text" name="athsbp_meta[subtitle]" value="<?php echo esc_attr( $meta['subtitle'] ); ?>" class="widefat">
					<p class="description"><?php echo esc_html( $labels['subtitle_desc'] ); ?></p>
				</div>
				<div class="abp-field">
					<label for="abp-card-subtitle"><?php echo esc_html( $labels['card_subtitle'] ); ?></label>
					<input id="abp-card-subtitle" type="text" name="athsbp_meta[card_subtitle]" value="<?php echo esc_attr( $meta['card_subtitle'] ); ?>" class="widefat">
					<p class="description"><?php echo esc_html( $labels['card_subtitle_desc'] ); ?></p>
				</div>
				<div class="abp-field">
					<label for="abp-badge-text"><?php echo esc_html( $labels['image_tag_badge'] ); ?></label>
					<input id="abp-badge-text" type="text" name="athsbp_meta[badge_text]" value="<?php echo esc_attr( $meta['badge_text'] ); ?>" class="widefat">
					<p class="description"><?php echo esc_html( $labels['image_tag_badge_desc'] ); ?></p>
				</div>
				<div class="abp-field">
					<label for="abp-card-primary-tag"><?php echo esc_html( $labels['card_primary_tag'] ); ?></label>
					<input id="abp-card-primary-tag" type="text" name="athsbp_meta[card_primary_tag]" value="<?php echo esc_attr( $meta['card_primary_tag'] ); ?>" class="widefat">
					<p class="description"><?php echo esc_html( $labels['card_primary_tag_desc'] ); ?></p>
				</div>
				<div class="abp-field">
					<label for="abp-card-secondary-tag"><?php echo esc_html( $labels['card_secondary_tag'] ); ?></label>
					<input id="abp-card-secondary-tag" type="text" name="athsbp_meta[card_secondary_tag]" value="<?php echo esc_attr( $meta['card_secondary_tag'] ); ?>" class="widefat">
					<p class="description"><?php echo esc_html( $labels['card_secondary_tag_desc'] ); ?></p>
				</div>
				<div class="abp-field">
					<label for="abp-price"><?php echo esc_html( $labels['price_or_price_text'] ); ?></label>
					<input id="abp-price" type="text" name="athsbp_meta[price]" value="<?php echo esc_attr( $meta['price'] ); ?>" class="widefat">
					<p class="description"><?php echo esc_html( $labels['price_or_price_text_desc'] ); ?></p>
				</div>
				<div class="abp-field">
					<label for="abp-price-note"><?php echo esc_html( $labels['price_note'] ); ?></label>
					<input id="abp-price-note" type="text" name="athsbp_meta[price_note]" value="<?php echo esc_attr( $meta['price_note'] ); ?>" class="widefat" placeholder="<?php echo esc_attr( $labels['price_note_placeholder'] ); ?>">
				</div>
				<div class="abp-field">
					<label for="abp-price-label"><?php echo esc_html( $labels['price_label'] ); ?></label>
					<input id="abp-price-label" type="text" name="athsbp_meta[price_label]" value="<?php echo esc_attr( $meta['price_label'] ); ?>" class="widefat">
				</div>
				<div class="abp-field">
					<label for="abp-duration"><?php echo esc_html( $labels['duration_value'] ); ?></label>
					<input id="abp-duration" type="text" name="athsbp_meta[duration]" value="<?php echo esc_attr( $meta['duration'] ); ?>" class="widefat" placeholder="<?php echo esc_attr( $labels['duration_placeholder'] ); ?>">
				</div>
				<div class="abp-field">
					<label for="abp-duration-label"><?php echo esc_html( $labels['duration_label'] ); ?></label>
					<input id="abp-duration-label" type="text" name="athsbp_meta[duration_label]" value="<?php echo esc_attr( $meta['duration_label'] ); ?>" class="widefat">
				</div>
				<div class="abp-field">
					<label for="abp-nights"><?php echo esc_html( $labels['nights_value'] ); ?></label>
					<input id="abp-nights" type="text" name="athsbp_meta[nights]" value="<?php echo esc_attr( $meta['nights'] ); ?>" class="widefat" placeholder="<?php echo esc_attr( $labels['nights_placeholder'] ); ?>">
				</div>
				<div class="abp-field">
					<label for="abp-nights-label"><?php echo esc_html( $labels['nights_label'] ); ?></label>
					<input id="abp-nights-label" type="text" name="athsbp_meta[nights_label]" value="<?php echo esc_attr( $meta['nights_label'] ); ?>" class="widefat">
				</div>
			</div>
		</div>

		<div class="abp-meta-section">
			<div class="abp-meta-section-header">
				<h4><?php echo esc_html( $labels['gallery'] ); ?></h4>
				<p><?php echo esc_html( $labels['gallery_desc'] ); ?></p>
			</div>
			<div class="abp-field">
				<label><?php echo esc_html( $labels['gallery_images'] ); ?></label>
				<input type="hidden" class="abp-gallery-input" name="athsbp_meta[gallery_ids]" value="<?php echo esc_attr( implode( ',', array_map( 'absint', $meta['gallery_ids'] ) ) ); ?>">
				<div class="abp-gallery-preview">
					<?php foreach ( $meta['gallery_ids'] as $image_id ) : ?>
						<?php echo wp_get_attachment_image( $image_id, 'thumbnail' ); ?>
					<?php endforeach; ?>
				</div>
				<p class="abp-button-row">
					<button type="button" class="button abp-select-gallery"><?php echo esc_html( $labels['choose_gallery_images'] ); ?></button>
					<button type="button" class="button-link-delete abp-clear-gallery"><?php echo esc_html( $labels['clear_gallery'] ); ?></button>
				</p>
			</div>
		</div>

		<div class="abp-meta-section">
			<div class="abp-meta-section-header">
				<h4><?php echo esc_html( $labels['description_section'] ); ?></h4>
				<p><?php echo esc_html( $labels['description_section_desc'] ); ?></p>
			</div>
			<div class="abp-field">
				<label for="abp-description-title"><?php echo esc_html( $labels['description_section_title'] ); ?></label>
				<input id="abp-description-title" type="text" name="athsbp_meta[description_title]" value="<?php echo esc_attr( $meta['description_title'] ); ?>" class="widefat">
			</div>
			<div class="abp-field">
				<label for="abp-description-content"><?php echo esc_html( $labels['description_content'] ); ?></label>
				<?php
				wp_editor(
					$meta['description_content'],
					'athsbp_description_content',
					array(
						'textarea_name' => 'athsbp_meta[description_content]',
						'textarea_rows' => 6,
					)
				);
				?>
			</div>
		</div>

		<div class="abp-meta-section">
			<div class="abp-meta-section-header">
				<h4><?php echo esc_html( $labels['includes_section'] ); ?></h4>
				<p><?php echo esc_html( $labels['includes_section_desc'] ); ?></p>
			</div>
			<div class="abp-field">
				<label for="abp-includes-title"><?php echo esc_html( $labels['includes_section_title'] ); ?></label>
				<input id="abp-includes-title" type="text" name="athsbp_meta[includes_title]" value="<?php echo esc_attr( $meta['includes_title'] ); ?>" class="widefat">
			</div>
			<div class="abp-field">
				<label for="abp-includes-content"><?php echo esc_html( $labels['includes_content'] ); ?></label>
				<?php
				wp_editor(
					$meta['includes_content'],
					'athsbp_includes_content',
					array(
						'textarea_name' => 'athsbp_meta[includes_content]',
						'textarea_rows' => 5,
					)
				);
				?>
			</div>
			<div class="abp-field">
				<label for="abp-excludes-title"><?php echo esc_html( $labels['excludes_section_title'] ); ?></label>
				<input id="abp-excludes-title" type="text" name="athsbp_meta[excludes_title]" value="<?php echo esc_attr( $meta['excludes_title'] ); ?>" class="widefat">
			</div>
			<div class="abp-field">
				<label for="abp-excludes-content"><?php echo esc_html( $labels['excludes_content'] ); ?></label>
				<?php
				wp_editor(
					$meta['excludes_content'],
					'athsbp_excludes_content',
					array(
						'textarea_name' => 'athsbp_meta[excludes_content]',
						'textarea_rows' => 5,
					)
				);
				?>
			</div>
			<div class="abp-field">
				<label for="abp-includes-table-html"><?php echo esc_html( $labels['manual_table_editor'] ); ?></label>
				<p class="description"><?php echo esc_html( $labels['manual_table_editor_desc'] ); ?></p>
				<?php
				wp_editor(
					$meta['includes_table_html'],
					'athsbp_includes_table_html',
					array(
						'textarea_name' => 'athsbp_meta[includes_table_html]',
						'textarea_rows' => 6,
					)
				);
				?>
			</div>
			<div class="abp-field">
				<div class="abp-table-builders" data-next-index="<?php echo esc_attr( max( 1, count( $meta['includes_tables'] ) ) ); ?>">
					<?php foreach ( ! empty( $meta['includes_tables'] ) ? $meta['includes_tables'] : array( '' ) as $table_index => $table_text ) : ?>
						<?php $this->render_table_builder( $table_index, $table_text, $labels ); ?>
					<?php endforeach; ?>
				</div>
				<p class="abp-button-row">
					<button type="button" class="button button-secondary abp-add-table"><?php echo esc_html( $labels['add_another_table'] ); ?></button>
				</p>
			</div>
			<div class="abp-field abp-pdf-field">
				<label><?php echo esc_html( $labels['includes_pdf'] ); ?></label>
				<input type="hidden" class="abp-pdf-input" name="athsbp_meta[includes_pdf_id]" value="<?php echo esc_attr( absint( $meta['includes_pdf_id'] ) ); ?>">
				<div class="abp-pdf-preview">
					<?php if ( $meta['includes_pdf_id'] ) : ?>
						<?php $pdf_url = wp_get_attachment_url( $meta['includes_pdf_id'] ); ?>
						<?php if ( $pdf_url ) : ?>
							<a href="<?php echo esc_url( $pdf_url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( get_the_title( $meta['includes_pdf_id'] ) ); ?></a>
						<?php endif; ?>
					<?php endif; ?>
				</div>
				<p class="description"><?php echo esc_html( $labels['includes_pdf_desc'] ); ?></p>
				<p class="abp-button-row">
					<button type="button" class="button button-secondary abp-select-pdf"><?php echo esc_html( $labels['choose_pdf'] ); ?></button>
					<button type="button" class="button-link-delete abp-clear-pdf"><?php echo esc_html( $labels['clear_pdf'] ); ?></button>
				</p>
			</div>
		</div>
		<?php
	}

	private function render_table_builder( $index, $table_text, $labels ) {
		$table_shape = $this->get_table_shape( $table_text );
		?>
		<div class="abp-table-builder" data-table-index="<?php echo esc_attr( $index ); ?>">
			<div class="abp-table-builder-top">
				<div>
					<label><?php echo esc_html( $labels['custom_table_builder'] ); ?></label>
					<p class="description"><?php echo esc_html( $labels['custom_table_builder_desc'] ); ?></p>
				</div>
				<div class="abp-table-size-controls">
					<div>
						<span><?php echo esc_html( $labels['data_rows'] ); ?></span>
						<input type="number" min="0" max="20" value="<?php echo esc_attr( $table_shape['rows'] ); ?>" class="small-text abp-table-rows">
					</div>
					<div>
						<span><?php echo esc_html( $labels['data_columns'] ); ?></span>
						<input type="number" min="0" max="8" value="<?php echo esc_attr( $table_shape['cols'] ); ?>" class="small-text abp-table-cols">
					</div>
					<button type="button" class="button button-secondary abp-build-table"><?php echo esc_html( $labels['build_table'] ); ?></button>
					<button type="button" class="button-link-delete abp-remove-table"><?php echo esc_html( $labels['remove_table'] ); ?></button>
				</div>
			</div>
			<div class="abp-table-grid"></div>
			<textarea name="athsbp_meta[includes_tables][]" rows="4" class="widefat abp-table-storage"><?php echo esc_textarea( $table_text ); ?></textarea>
		</div>
		<?php
	}

	private function get_table_shape( $table_text ) {
		$lines = array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', (string) $table_text ) ) );
		$rows  = count( $lines );
		$cols  = 0;

		foreach ( $lines as $line ) {
			$cols = max( $cols, count( explode( '|', $line ) ) );
		}

		return array(
			'rows' => $rows ? max( 0, $rows - 1 ) : 0,
			'cols' => $cols ? max( 0, $cols - 1 ) : 0,
		);
	}

	private function sanitize_table_list( $tables ) {
		if ( ! is_array( $tables ) ) {
			return array();
		}

		$clean = array();

		foreach ( $tables as $table ) {
			$table = sanitize_textarea_field( $table );
			if ( '' === trim( $table ) ) {
				continue;
			}

			$clean[] = $table;
		}

		return $clean;
	}

	public function save_package_meta( $post_id ) {
		if ( empty( $_POST['athsbp_package_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['athsbp_package_meta_nonce'] ) ), 'athsbp_save_package_meta' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Package meta is unslashed here and each field is sanitized according to its expected shape below.
		$raw_meta = isset( $_POST['athsbp_meta'] ) ? wp_unslash( $_POST['athsbp_meta'] ) : array();
		if ( ! is_array( $raw_meta ) ) {
			$raw_meta = array();
		}

		$includes_tables = isset( $raw_meta['includes_tables'] ) ? $this->sanitize_table_list( $raw_meta['includes_tables'] ) : array();
		if ( empty( $includes_tables ) && ! empty( $raw_meta['includes_table'] ) ) {
			$includes_tables = $this->sanitize_table_list( array( $raw_meta['includes_table'] ) );
		}

		$meta = array(
			'subtitle'            => isset( $raw_meta['subtitle'] ) ? sanitize_text_field( $raw_meta['subtitle'] ) : '',
			'card_subtitle'       => isset( $raw_meta['card_subtitle'] ) ? sanitize_text_field( $raw_meta['card_subtitle'] ) : '',
			'badge_text'          => isset( $raw_meta['badge_text'] ) ? sanitize_text_field( $raw_meta['badge_text'] ) : '',
			'card_primary_tag'    => isset( $raw_meta['card_primary_tag'] ) ? sanitize_text_field( $raw_meta['card_primary_tag'] ) : '',
			'card_secondary_tag'  => isset( $raw_meta['card_secondary_tag'] ) ? sanitize_text_field( $raw_meta['card_secondary_tag'] ) : '',
			'price'               => isset( $raw_meta['price'] ) ? sanitize_text_field( $raw_meta['price'] ) : '',
			'price_note'          => isset( $raw_meta['price_note'] ) ? sanitize_text_field( $raw_meta['price_note'] ) : '',
			'price_label'         => isset( $raw_meta['price_label'] ) ? sanitize_text_field( $raw_meta['price_label'] ) : '',
			'duration'            => isset( $raw_meta['duration'] ) ? sanitize_text_field( $raw_meta['duration'] ) : '',
			'duration_label'      => isset( $raw_meta['duration_label'] ) ? sanitize_text_field( $raw_meta['duration_label'] ) : '',
			'nights'              => isset( $raw_meta['nights'] ) ? sanitize_text_field( $raw_meta['nights'] ) : '',
			'nights_label'        => isset( $raw_meta['nights_label'] ) ? sanitize_text_field( $raw_meta['nights_label'] ) : '',
			'description_title'   => isset( $raw_meta['description_title'] ) ? sanitize_text_field( $raw_meta['description_title'] ) : '',
			'description_content' => isset( $raw_meta['description_content'] ) ? wp_kses_post( $raw_meta['description_content'] ) : '',
			'includes_title'      => isset( $raw_meta['includes_title'] ) ? sanitize_text_field( $raw_meta['includes_title'] ) : '',
			'includes_content'    => isset( $raw_meta['includes_content'] ) ? wp_kses_post( $raw_meta['includes_content'] ) : '',
			'includes_table_html' => isset( $raw_meta['includes_table_html'] ) ? wp_kses_post( $raw_meta['includes_table_html'] ) : '',
			'excludes_title'      => isset( $raw_meta['excludes_title'] ) ? sanitize_text_field( $raw_meta['excludes_title'] ) : '',
			'excludes_content'    => isset( $raw_meta['excludes_content'] ) ? wp_kses_post( $raw_meta['excludes_content'] ) : '',
			'includes_table'      => ! empty( $includes_tables ) ? $includes_tables[0] : '',
			'includes_tables'     => $includes_tables,
			'includes_pdf_id'     => isset( $raw_meta['includes_pdf_id'] ) ? absint( $raw_meta['includes_pdf_id'] ) : 0,
			'gallery_ids'         => isset( $raw_meta['gallery_ids'] ) ? array_filter( array_map( 'absint', explode( ',', (string) $raw_meta['gallery_ids'] ) ) ) : array(),
		);

		update_post_meta( $post_id, ATHSBP_Plugin::META_KEY, $meta );

		$price_numeric = $this->plugin->extract_numeric_value( $meta['price'] );
		$duration_numeric = $this->plugin->extract_numeric_value( $meta['duration'] );

		if ( null !== $price_numeric ) {
			update_post_meta( $post_id, ATHSBP_Plugin::PRICE_NUMERIC_META_KEY, $price_numeric );
		} elseif ( '' !== trim( (string) $meta['price'] ) ) {
			update_post_meta( $post_id, ATHSBP_Plugin::PRICE_NUMERIC_META_KEY, 0 );
		} else {
			delete_post_meta( $post_id, ATHSBP_Plugin::PRICE_NUMERIC_META_KEY );
		}

		if ( null !== $duration_numeric ) {
			update_post_meta( $post_id, ATHSBP_Plugin::DURATION_NUMERIC_META_KEY, $duration_numeric );
		} else {
			delete_post_meta( $post_id, ATHSBP_Plugin::DURATION_NUMERIC_META_KEY );
		}
	}

	private function get_editor_labels() {
		if ( 'el' === $this->plugin->get_current_language() ) {
			return array(
				'package_details'            => 'Στοιχεία Πακέτου',
				'package_editor'             => 'Επεξεργαστής Πακέτου',
				'package_editor_intro'       => 'Ο τίτλος στην κορυφή είναι το πραγματικό όνομα του πακέτου. Χρησιμοποιήστε τα παρακάτω πεδία για να διαμορφώσετε ακριβώς την κάρτα και τη σελίδα πακέτου που θα βλέπουν οι επισκέπτες.',
				'recommended_flow'          => 'Προτεινόμενη ροή',
				'recommended_flow_steps'    => 'Τίτλος -> Υπότιτλος -> Στοιχεία τιμής -> Gallery -> Περιγραφή -> Περιλαμβάνονται -> Πίνακας',
				'core_package_info'         => 'Βασικά Στοιχεία Πακέτου',
				'core_package_info_desc'    => 'Αυτές οι τιμές ελέγχουν την κάρτα αρχείου και τη γραμμή σύνοψης στη σελίδα του πακέτου.',
				'subtitle'                  => 'Υπότιτλος',
				'subtitle_desc'             => 'Εμφανίζεται ακριβώς κάτω από τον τίτλο του πακέτου στη μονή σελίδα.',
				'card_subtitle'             => 'Υπότιτλος Κάρτας',
				'card_subtitle_desc'        => 'Μια πιο σύντομη σύνοψη που χρησιμοποιείται στις κάρτες πακέτων.',
				'image_tag_badge'           => 'Ετικέτα / Badge Εικόνας',
				'image_tag_badge_desc'      => 'Αφήστε το κενό για να χρησιμοποιηθεί αυτόματα η Χώρα. Συμπληρώστε το μόνο αν θέλετε να αντικαταστήσετε το προεπιλεγμένο badge.',
				'card_primary_tag'          => '1η ετικέτα κάρτας',
				'card_primary_tag_desc'     => 'Προαιρετική αντικατάσταση. Αν μείνει κενό, χρησιμοποιείται αυτόματα η Εορτή / Αργία, π.χ. Καλοκαιρινό Πακέτο.',
				'card_secondary_tag'        => '2η ετικέτα κάρτας',
				'card_secondary_tag_desc'   => 'Προαιρετική αντικατάσταση. Αν μείνει κενό, χρησιμοποιείται αυτόματα η Κατηγορία Ταξιδίου, π.χ. Ομαδικό Πακέτο ή Κρουαζιέρα.',
				'price_or_price_text'       => 'Τιμή ή Κείμενο Τιμής',
				'price_or_price_text_desc'  => 'Χρησιμοποιήστε και απλό κείμενο αν η τιμολόγηση αλλάζει συχνά.',
				'price_note'                => 'Σημείωση Τιμής',
				'price_note_placeholder'    => 'Περιλαμβάνονται φόροι / Από ανά διανυκτέρευση / Καλέστε για τιμή',
				'price_label'               => 'Ετικέτα Τιμής',
				'duration_value'            => 'Τιμή Διάρκειας',
				'duration_placeholder'      => '3 ημέρες',
				'duration_label'            => 'Ετικέτα Διάρκειας',
				'nights_value'              => 'Τιμή Διανυκτερεύσεων',
				'nights_placeholder'        => '2 νύχτες',
				'nights_label'              => 'Ετικέτα Διανυκτερεύσεων',
				'gallery'                   => 'Συλλογή Εικόνων',
				'gallery_desc'              => 'Χρησιμοποιήστε την επιλεγμένη εικόνα ως κύρια εικόνα του πακέτου και προσθέστε εδώ τις υπόλοιπες εικόνες της gallery.',
				'gallery_images'            => 'Εικόνες Gallery',
				'choose_gallery_images'     => 'Επιλογή Εικόνων Gallery',
				'clear_gallery'             => 'Καθαρισμός Gallery',
				'description_section'       => 'Ενότητα Περιγραφής',
				'description_section_desc'  => 'Αυτό είναι το βασικό περιγραφικό περιεχόμενο της σελίδας πακέτου.',
				'description_section_title' => 'Τίτλος Ενότητας Περιγραφής',
				'description_content'       => 'Περιεχόμενο Περιγραφής',
				'includes_section'          => 'Ενότητα Περιλαμβάνονται',
				'includes_section_desc'     => 'Χρησιμοποιήστε τον editor για σημειώσεις και λίστες και στη συνέχεια δημιουργήστε έναν αντίστοιχο πίνακα αν χρειάζεται.',
				'includes_section_title'    => 'Τίτλος Ενότητας Περιλαμβάνονται',
				'includes_content'          => 'Περιεχόμενο Περιλαμβάνονται',
				'excludes_section_title'    => 'Τίτλος Ενότητας Δεν Περιλαμβάνονται',
				'excludes_content'          => 'Περιεχόμενο Δεν Περιλαμβάνονται',
				'manual_table_editor'      => 'Πίνακες με κείμενο / HTML editor',
				'manual_table_editor_desc' => 'Προαιρετικό πεδίο για έτοιμο HTML πίνακα ή περιεχόμενο πίνακα από visual/code editor, αν δεν θέλετε να χρησιμοποιήσετε τον custom table builder.',
				'custom_table_builder'      => 'Δημιουργός Προσαρμοσμένου Πίνακα',
				'custom_table_builder_desc' => 'Ορίστε το μέγεθος της εσωτερικής περιοχής δεδομένων. Ο builder προσθέτει αυτόματα την πάνω γραμμή τίτλων και την αριστερή στήλη τίτλων γραμμών.',
				'data_rows'                 => 'Γραμμές Δεδομένων',
				'data_columns'              => 'Στήλες Δεδομένων',
				'build_table'               => 'Δημιουργία Πίνακα',
				'add_another_table'         => 'Προσθήκη άλλου πίνακα',
				'remove_table'              => 'Αφαίρεση πίνακα',
				'includes_pdf'              => 'PDF κάτω από τους πίνακες',
				'includes_pdf_desc'         => 'Προαιρετικό αρχείο PDF που θα εμφανίζεται κάτω από τους πίνακες στη σελίδα του πακέτου.',
				'choose_pdf'                => 'Επιλογή PDF',
				'use_pdf'                   => 'Χρήση PDF',
				'clear_pdf'                 => 'Καθαρισμός PDF',
				'choose_gallery_images'     => 'Επιλογή Εικόνων Gallery',
				'use_images'                => 'Χρήση Εικόνων',
				'cell_content'              => 'Περιεχόμενο κελιού',
				'column_title'              => 'Τίτλος στήλης',
				'row_title'                 => 'Τίτλος γραμμής',
			);
		}

		return array(
			'package_details'            => 'Package Details',
			'package_editor'             => 'Package Editor',
			'package_editor_intro'       => 'The title at the top is the real package name. Use the fields below to build the exact card and single-package layout your visitors will see.',
			'recommended_flow'          => 'Recommended flow',
			'recommended_flow_steps'    => 'Title -> Subtitle -> Price info -> Gallery -> Description -> Includes -> Table',
			'core_package_info'         => 'Core Package Info',
			'core_package_info_desc'    => 'These values control the archive card and the single-page summary bar.',
			'subtitle'                  => 'Subtitle',
			'subtitle_desc'             => 'Shown directly below the package title on the single page.',
			'card_subtitle'             => 'Card Subtitle',
			'card_subtitle_desc'        => 'A shorter summary used on package cards.',
			'image_tag_badge'           => 'Image Tag / Badge',
			'image_tag_badge_desc'      => 'Leave empty to use the Country term automatically. Fill this in only if you want to override the default badge.',
			'card_primary_tag'          => 'Card Tag 1',
			'card_primary_tag_desc'     => 'Optional override. Leave empty to use the Important Holiday term automatically, for example Summer Package.',
			'card_secondary_tag'        => 'Card Tag 2',
			'card_secondary_tag_desc'   => 'Optional override. Leave empty to use the Travel Category term automatically, for example Group Package or Cruise.',
			'price_or_price_text'       => 'Price or Price Text',
			'price_or_price_text_desc'  => 'Use plain text too if pricing changes often.',
			'price_note'                => 'Price Note',
			'price_note_placeholder'    => 'Taxes included / From per night / Call for pricing',
			'price_label'               => 'Price Label',
			'duration_value'            => 'Duration Value',
			'duration_placeholder'      => '3 days',
			'duration_label'            => 'Duration Label',
			'nights_value'              => 'Nights Value',
			'nights_placeholder'        => '2 nights',
			'nights_label'              => 'Nights Label',
			'gallery'                   => 'Gallery',
			'gallery_desc'              => 'Use the featured image as the main package image and add the extra gallery images here.',
			'gallery_images'            => 'Gallery Images',
			'choose_gallery_images'     => 'Choose Gallery Images',
			'clear_gallery'             => 'Clear Gallery',
			'description_section'       => 'Description Section',
			'description_section_desc'  => 'This is the main descriptive content of the package page.',
			'description_section_title' => 'Description Section Title',
			'description_content'       => 'Description Content',
			'includes_section'          => 'Includes Section',
			'includes_section_desc'     => 'Use the text editor for notes and bullet lists, then build a matching table if needed.',
			'includes_section_title'    => 'Includes Section Title',
			'includes_content'          => 'Includes Content',
			'excludes_section_title'    => 'Not Included Section Title',
			'excludes_content'          => 'Not Included Content',
			'manual_table_editor'      => 'Text / HTML Table Editor',
			'manual_table_editor_desc' => 'Optional field for ready-made HTML tables or table content from the visual/code editor if you do not want to use the custom table builder.',
			'custom_table_builder'      => 'Custom Table Builder',
			'custom_table_builder_desc' => 'Enter the size of the inner data area. The builder automatically adds the top title row and the left row-title column.',
			'data_rows'                 => 'Data Rows',
			'data_columns'              => 'Data Columns',
			'build_table'               => 'Build Table',
			'add_another_table'         => 'Add Another Table',
			'remove_table'              => 'Remove Table',
			'includes_pdf'              => 'PDF Below Tables',
			'includes_pdf_desc'         => 'Optional PDF file displayed below the tables on the single package page.',
			'choose_pdf'                => 'Choose PDF',
			'use_pdf'                   => 'Use PDF',
			'clear_pdf'                 => 'Clear PDF',
			'use_images'                => 'Use images',
			'cell_content'              => 'Cell content',
			'column_title'              => 'Column title',
			'row_title'                 => 'Row title',
		);
	}
}
