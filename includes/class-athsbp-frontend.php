<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ATHSBP_Frontend {
	/**
	 * @var ATHSBP_Plugin
	 */
	private $plugin;

	public function __construct( ATHSBP_Plugin $plugin ) {
		$this->plugin = $plugin;

		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
		add_shortcode( 'athsbp_packages', array( $this, 'render_packages_shortcode' ) );
	}

	public function register_assets() {
		wp_register_style( 'athsbp-frontend', ATHSBP_PLUGIN_URL . 'assets/css/frontend.css', array(), ATHSBP_VERSION );
		wp_add_inline_style( 'athsbp-frontend', $this->get_custom_style_css() );
		wp_register_script( 'athsbp-frontend', ATHSBP_PLUGIN_URL . 'assets/js/frontend.js', array(), ATHSBP_VERSION, true );
	}

	public function enqueue_assets() {
		wp_enqueue_style( 'athsbp-frontend' );
		wp_add_inline_style( 'athsbp-frontend', $this->get_custom_style_css() );
		wp_enqueue_script( 'athsbp-frontend' );
	}

	private function get_custom_style_css() {
		$settings = $this->plugin->get_settings();
		$keys = array(
			'style_label_text_color'                   => '--abp-label-text-color',
			'style_label_background_color'             => '--abp-label-background-color',
			'style_tag_text_color'                     => '--abp-tag-text-color',
			'style_tag_background_color'               => '--abp-tag-background-color',
			'style_tag_border_color'                   => '--abp-tag-border-color',
			'style_card_badge_text_color'              => '--abp-card-badge-text-color',
			'style_card_badge_background_color'        => '--abp-card-badge-background-color',
			'style_single_kicker_text_color'           => '--abp-single-kicker-text-color',
			'style_single_kicker_background_color'     => '--abp-single-kicker-background-color',
			'style_title_color'                        => '--abp-title-color',
			'style_subtitle_color'                     => '--abp-subtitle-color',
			'style_slider_active_color'                => '--abp-slider-active-color',
			'style_slider_track_color'                 => '--abp-slider-track-color',
			'style_slider_thumb_color'                 => '--abp-slider-thumb-color',
			'style_pagination_text_color'              => '--abp-pagination-text-color',
			'style_pagination_background_color'        => '--abp-pagination-background-color',
			'style_pagination_border_color'            => '--abp-pagination-border-color',
			'style_pagination_active_text_color'       => '--abp-pagination-active-text-color',
			'style_pagination_active_background_color' => '--abp-pagination-active-background-color',
		);

		$variables = array();
		foreach ( $keys as $setting_key => $css_variable ) {
			$value = isset( $settings[ $setting_key ] ) ? sanitize_hex_color( $settings[ $setting_key ] ) : '';
			if ( $value ) {
				$variables[] = $css_variable . ': ' . $value;
			}
		}

		$dimension_keys = array(
			'style_single_title_font_size'    => '--abp-single-title-font-size',
			'style_single_subtitle_font_size' => '--abp-single-subtitle-font-size',
			'style_single_header_padding'     => '--abp-single-header-padding',
			'style_section_title_font_size'   => '--abp-section-title-font-size',
			'style_section_body_font_size'    => '--abp-section-body-font-size',
		);

		foreach ( $dimension_keys as $dim_key => $css_var ) {
			if ( ! empty( $settings[ $dim_key ] ) ) {
				$clean = trim( preg_replace( '/[^0-9a-zA-Z\s.,%()\-]/', '', $settings[ $dim_key ] ) );
				if ( is_numeric( $clean ) ) {
					$clean .= 'px';
				}
				if ( '' !== $clean ) {
					$variables[] = $css_var . ': ' . $clean;
				}
			}
		}

		if ( empty( $variables ) ) {
			return '';
		}

		return ':root, .abp-theme-wrap, .abp-archive-shell, .abp-single {' . implode( ';', $variables ) . ';}';
	}

	public function render_packages_shortcode( $atts = array() ) {
		$this->enqueue_assets();

		$atts = shortcode_atts(
			array(
				'per_page'        => 9,
				'show_filters'    => 'yes',
				'show_pagination' => 'yes',
			),
			$atts,
			'athsbp_packages'
		);

		$paged = max( 1, absint( get_query_var( 'paged' ) ? get_query_var( 'paged' ) : get_query_var( 'page' ) ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public pagination is read-only.
		if ( isset( $_GET['athsbp_page'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public pagination is read-only.
			$paged = max( 1, absint( sanitize_text_field( wp_unslash( $_GET['athsbp_page'] ) ) ) );
		}

		$args = array(
			'post_type'      => ATHSBP_Plugin::CPT,
			'post_status'    => 'publish',
			'paged'          => $paged,
			'posts_per_page' => absint( $atts['per_page'] ),
		);

		$tax_query = $this->plugin->get_filter_query_args();
		if ( ! empty( $tax_query ) ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Taxonomy filters are the core package filtering feature.
			$args['tax_query'] = $tax_query;
		}

		$meta_query = $this->plugin->merge_meta_queries(
			$this->plugin->get_meta_filter_query_args(),
			$this->plugin->get_active_package_meta_query_args()
		);
		if ( ! empty( $meta_query ) ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Numeric range and active package filters require package meta filtering.
			$args['meta_query'] = $meta_query;
		}

		$query    = new WP_Query( $args );
		$settings = $this->plugin->get_settings();
		$labels   = $this->plugin->get_ui_labels();

		ob_start();
		?>
		<div class="abp-archive-shell">
			<?php $custom_css = $this->get_custom_style_css(); if ( ! empty( $custom_css ) ) : ?>
				<style><?php echo $custom_css; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></style>
			<?php endif; ?>
			<div class="abp-archive-header">
				<span class="abp-archive-kicker"><?php echo esc_html( $labels['archive_kicker'] ); ?></span>
				<h1><?php echo esc_html( $this->plugin->get_localized_setting( 'archive_title' ) ); ?></h1>
				<p><?php echo esc_html( $this->plugin->get_localized_setting( 'archive_intro' ) ); ?></p>
			</div>

			<div class="abp-grid-layout">
				<?php if ( 'yes' === $atts['show_filters'] ) : ?>
					<aside class="abp-filters">
						<form method="get" class="abp-filters-form">
							<?php foreach ( $this->plugin->get_filter_groups() as $group ) : ?>
								<?php $this->render_filter_block( $this->plugin->taxonomy_name_from_slug( $group['slug'] ), $group['label'], $group['input_type'], $group['show_counts'], $group['slug'] ); ?>
							<?php endforeach; ?>
							<div class="abp-filter-actions">
								<noscript><button type="submit" class="abp-button"><?php echo esc_html( $labels['apply_filters'] ); ?></button></noscript>
								<a class="abp-button abp-button-light" href="<?php echo esc_url( get_permalink() ); ?>"><?php echo esc_html( $labels['reset'] ); ?></a>
							</div>
						</form>
					</aside>
				<?php endif; ?>

				<div class="abp-results">
					<div class="abp-results-meta">
						<span>
							<?php
							$first_result = (int) $query->found_posts ? ( ( $paged - 1 ) * absint( $atts['per_page'] ) ) + 1 : 0;
							$last_result  = min( (int) $query->found_posts, $paged * absint( $atts['per_page'] ) );
							$total_count  = (int) $query->found_posts;

							printf(
								/* translators: 1: first item, 2: last item, 3: total items */
								esc_html( $labels['results_count'] ),
								esc_html( (string) $first_result ),
								esc_html( (string) $last_result ),
								esc_html( (string) $total_count )
							);
							?>
						</span>
					</div>

					<div class="abp-card-grid">
						<?php if ( $query->have_posts() ) : ?>
							<?php while ( $query->have_posts() ) : ?>
								<?php $query->the_post(); ?>
								<?php $this->render_package_card( get_the_ID() ); ?>
							<?php endwhile; ?>
						<?php else : ?>
							<div class="abp-empty-state"><?php echo esc_html( $labels['no_packages_match'] ); ?></div>
						<?php endif; ?>
					</div>

					<?php if ( 'yes' === $atts['show_pagination'] && $query->max_num_pages > 1 ) : ?>
						<div class="abp-pagination">
							<?php
							echo wp_kses_post(
								paginate_links(
									array(
										'base'      => esc_url_raw( add_query_arg( 'athsbp_page', '%#%' ) ),
										'format'    => '',
										'current'   => $paged,
										'total'     => $query->max_num_pages,
										'prev_text' => $labels['previous'],
										'next_text' => $labels['next'],
									)
								)
							);
							?>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
		wp_reset_postdata();

		return ob_get_clean();
	}

	private function render_filter_block( $taxonomy, $label, $input_type, $show_counts, $request_slug ) {
		if ( 'range_price' === $input_type ) {
			$this->render_range_filter_block( $label, 'price', ATHSBP_Plugin::PRICE_NUMERIC_META_KEY, $this->plugin->get_currency_config()['symbol'] );
			return;
		}

		if ( 'range_duration' === $input_type ) {
			$this->render_range_filter_block( $label, 'duration', ATHSBP_Plugin::DURATION_NUMERIC_META_KEY, $this->plugin->get_ui_labels()['days'] );
			return;
		}

		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => true,
			)
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return;
		}

		$selected = $this->plugin->get_filter_request_values( $request_slug );
		$field    = 'athsbp_' . sanitize_title( $request_slug );
		?>
		<div class="abp-filter-card">
			<h3><?php echo esc_html( $label ); ?></h3>
			<?php if ( 'select' === $input_type ) : ?>
				<div class="abp-multiselect" data-abp-multiselect>
					<button type="button" class="abp-multiselect-toggle" aria-expanded="false">
						<span data-abp-multiselect-label><?php echo esc_html( $this->get_multiselect_label( $terms, $selected ) ); ?></span>
						<span class="abp-multiselect-arrow" aria-hidden="true"></span>
					</button>
					<div class="abp-multiselect-panel">
						<input type="search" class="abp-multiselect-search" placeholder="<?php esc_attr_e( 'Search', 'aths-business-packages' ); ?>">
						<div class="abp-checkbox-list abp-multiselect-options">
							<?php foreach ( $terms as $term ) : ?>
								<label data-abp-option-label="<?php echo esc_attr( strtolower( $term->name ) ); ?>">
									<input type="checkbox" name="<?php echo esc_attr( $field ); ?>[]" value="<?php echo esc_attr( $term->slug ); ?>" data-label="<?php echo esc_attr( $term->name ); ?>" <?php checked( in_array( $term->slug, $selected, true ) ); ?>>
									<span><?php echo esc_html( $this->get_filter_term_label( $term, $show_counts ) ); ?></span>
								</label>
							<?php endforeach; ?>
						</div>
					</div>
				</div>
			<?php else : ?>
				<div class="abp-checkbox-list">
					<?php foreach ( $terms as $term ) : ?>
						<label>
							<input type="checkbox" name="<?php echo esc_attr( $field ); ?>[]" value="<?php echo esc_attr( $term->slug ); ?>" <?php checked( in_array( $term->slug, $selected, true ) ); ?>>
							<span>
								<?php echo esc_html( $this->get_filter_term_label( $term, $show_counts ) ); ?>
							</span>
						</label>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	private function get_multiselect_label( $terms, $selected ) {
		if ( empty( $selected ) ) {
			return $this->plugin->get_ui_labels()['any'];
		}

		$names = array();
		foreach ( $terms as $term ) {
			if ( in_array( $term->slug, $selected, true ) ) {
				$names[] = $term->name;
			}
		}

		if ( empty( $names ) ) {
			return $this->plugin->get_ui_labels()['any'];
		}

		return implode( ', ', array_slice( $names, 0, 2 ) ) . ( count( $names ) > 2 ? ' +' . ( count( $names ) - 2 ) : '' );
	}

	private function get_filter_term_label( $term, $show_counts ) {
		if ( $show_counts ) {
			return sprintf( '%s (%d)', $term->name, (int) $term->count );
		}

		return $term->name;
	}

	private function render_range_filter_block( $label, $slug, $meta_key, $unit ) {
		$bounds   = $this->plugin->get_numeric_filter_bounds( $meta_key );
		$current  = $this->plugin->get_range_request_values( $slug );
		$min      = (int) floor( $bounds['min'] );
		$max      = (int) ceil( $bounds['max'] );
		$selected_min = null !== $current['min'] ? (float) $current['min'] : $min;
		$selected_max = null !== $current['max'] ? (float) $current['max'] : $max;

		if ( $max <= $min ) {
			return;
		}
		?>
		<div class="abp-filter-card">
			<h3><?php echo esc_html( $label ); ?></h3>
			<div class="abp-range-filter" data-unit="<?php echo esc_attr( $unit ); ?>">
				<div class="abp-range-sliders">
					<input type="range" class="abp-range-input abp-range-input-min" min="<?php echo esc_attr( $min ); ?>" max="<?php echo esc_attr( $max ); ?>" value="<?php echo esc_attr( $selected_min ); ?>">
					<input type="range" class="abp-range-input abp-range-input-max" min="<?php echo esc_attr( $min ); ?>" max="<?php echo esc_attr( $max ); ?>" value="<?php echo esc_attr( $selected_max ); ?>">
				</div>
				<div class="abp-range-scale">
					<span class="abp-range-min-label"><?php echo esc_html( $selected_min . ' ' . $unit ); ?></span>
					<span class="abp-range-max-label"><?php echo esc_html( $selected_max . ' ' . $unit ); ?></span>
				</div>
				<input type="hidden" name="<?php echo esc_attr( 'athsbp_' . $slug . '_min' ); ?>" class="abp-range-storage-min" value="<?php echo esc_attr( $selected_min ); ?>">
				<input type="hidden" name="<?php echo esc_attr( 'athsbp_' . $slug . '_max' ); ?>" class="abp-range-storage-max" value="<?php echo esc_attr( $selected_max ); ?>">
			</div>
		</div>
		<?php
	}

	public function render_package_card( $post_id ) {
		$meta        = $this->plugin->get_package_meta( $post_id );
		$price_text  = $this->plugin->format_price_display( $meta['price'] );
		$card_tags   = $this->get_card_tags( $post_id, $meta );
		$badge_label = $meta['badge_text'];

		if ( empty( $badge_label ) ) {
			$country_terms = get_the_terms( $post_id, $this->plugin->taxonomy_name_from_slug( 'country' ) );
			if ( ! empty( $country_terms ) && ! is_wp_error( $country_terms ) ) {
				$badge_label = $country_terms[0]->name;
			}
		}

		if ( empty( $badge_label ) ) {
			foreach ( $this->plugin->get_filter_groups() as $group ) {
				if ( $this->plugin->is_range_filter_type( $group['input_type'] ) ) {
					continue;
				}

				if ( 'country' === $group['slug'] ) {
					continue;
				}

				$terms = get_the_terms( $post_id, $this->plugin->taxonomy_name_from_slug( $group['slug'] ) );
				if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
					$badge_label = $terms[0]->name;
					break;
				}
			}
		}
		?>
		<article class="abp-card">
			<a class="abp-card-media" href="<?php echo esc_url( get_permalink( $post_id ) ); ?>">
				<?php if ( has_post_thumbnail( $post_id ) ) : ?>
					<?php echo get_the_post_thumbnail( $post_id, 'large' ); ?>
				<?php else : ?>
					<div class="abp-card-placeholder"></div>
				<?php endif; ?>
				<?php if ( $badge_label ) : ?>
					<span class="abp-card-badge"><?php echo esc_html( $badge_label ); ?></span>
				<?php endif; ?>
			</a>
			<div class="abp-card-body">
				<?php if ( ! empty( $card_tags ) ) : ?>
					<div class="abp-card-tags">
						<?php foreach ( $card_tags as $tag ) : ?>
							<span class="abp-card-type"><?php echo esc_html( $tag ); ?></span>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
				<h3><a href="<?php echo esc_url( get_permalink( $post_id ) ); ?>"><?php echo esc_html( get_the_title( $post_id ) ); ?></a></h3>
				<div class="abp-card-price-row">
					<div>
						<strong><?php echo esc_html( $price_text ); ?></strong>
						<?php if ( $meta['price_note'] ) : ?>
							<span><?php echo esc_html( $meta['price_note'] ); ?></span>
						<?php endif; ?>
					</div>
					<?php if ( $meta['duration'] ) : ?>
						<div class="abp-card-duration"><?php echo esc_html( $meta['duration'] ); ?></div>
					<?php endif; ?>
				</div>
				<?php if ( $meta['card_subtitle'] ) : ?>
					<p class="abp-card-subtitle"><?php echo esc_html( $meta['card_subtitle'] ); ?></p>
				<?php endif; ?>
			</div>
		</article>
		<?php
	}

	private function get_card_tags( $post_id, $meta ) {
		$tags = array();

		$primary = trim( (string) $meta['card_primary_tag'] );
		if ( '' === $primary ) {
			$primary = $this->get_first_term_card_label( $post_id, 'important-holidays' );
		}

		$secondary = trim( (string) $meta['card_secondary_tag'] );
		if ( '' === $secondary ) {
			$secondary = $this->get_first_term_card_label( $post_id, 'travel-style' );
		}

		foreach ( array( $primary, $secondary ) as $tag ) {
			$tag = trim( (string) $tag );
			if ( '' !== $tag ) {
				$tags[] = $tag;
			}
		}

		return array_slice( $tags, 0, 2 );
	}

	private function get_first_term_card_label( $post_id, $group_slug ) {
		$taxonomy = $this->plugin->taxonomy_name_from_slug( $group_slug );
		$terms    = get_the_terms( $post_id, $taxonomy );

		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return '';
		}

		$term = reset( $terms );
		return $this->format_card_term_label( $group_slug, $term );
	}

	private function format_card_term_label( $group_slug, $term ) {
		$language = $this->plugin->get_current_language();
		$slug     = isset( $term->slug ) ? $term->slug : '';
		$name     = isset( $term->name ) ? $term->name : '';

		$mapped = array(
			'important-holidays' => array(
				'en' => array(
					'summer'                   => 'Summer Package',
					'christmas'                => 'Christmas Package',
					'easter'                   => 'Easter Package',
					'carnival-clean-monday'    => 'Carnival Package',
					'may-day'                  => 'May Day Package',
					'holy-spirit'              => 'Holy Spirit Package',
					'assumption-day'           => 'August Package',
					'october-28'               => 'October 28 Package',
					'new-year-winter-getaways' => 'New Year Package',
					'epiphany'                 => 'Epiphany Package',
				),
				'el' => array(
					'summer'                   => 'Καλοκαιρινό Πακέτο',
					'christmas'                => 'Χριστουγεννιάτικο Πακέτο',
					'easter'                   => 'Πασχαλινό Πακέτο',
					'carnival-clean-monday'    => 'Αποκριάτικο Πακέτο',
					'may-day'                  => 'Πακέτο Πρωτομαγιάς',
					'holy-spirit'              => 'Πακέτο Αγίου Πνεύματος',
					'assumption-day'           => 'Πακέτο Δεκαπενταύγουστου',
					'october-28'               => 'Πακέτο 28ης Οκτωβρίου',
					'new-year-winter-getaways' => 'Πρωτοχρονιάτικο Πακέτο',
					'epiphany'                 => 'Πακέτο Θεοφανείων',
				),
			),
			'travel-style' => array(
				'en' => array(
					'individual-packages' => 'Individual Package',
					'group-packages'      => 'Group Package',
					'cruises'             => 'Cruise',
				),
				'el' => array(
					'individual-packages' => 'Ατομικό Πακέτο',
					'group-packages'      => 'Ομαδικό Πακέτο',
					'cruises'             => 'Κρουαζιέρα',
				),
			),
		);

		if ( isset( $mapped[ $group_slug ][ $language ][ $slug ] ) ) {
			return $mapped[ $group_slug ][ $language ][ $slug ];
		}

		if ( 'important-holidays' === $group_slug ) {
			return 'el' === $language ? $this->format_greek_holiday_card_label( $name ) : sprintf( '%s Package', $name );
		}

		return $name;
	}

	private function format_greek_holiday_card_label( $name ) {
		$seasonal_parts = array(
			'Άνοιξη'    => 'Ανοιξιάτικο',
			'Ανοιξη'    => 'Ανοιξιάτικο',
			'Καλοκαίρι' => 'Καλοκαιρινό',
			'Φθινόπωρο' => 'Φθινοπωρινό',
			'Χειμώνας'   => 'Χειμερινό',
			'Χειμωνας'   => 'Χειμερινό',
		);

		$label = (string) $name;
		foreach ( $seasonal_parts as $search => $replace ) {
			$label = str_replace( $search, $replace, $label );
		}

		if ( $label !== (string) $name ) {
			return sprintf( '%s Πακέτο', $label );
		}

		return sprintf( '%s Πακέτο', $name );
	}

	public function render_single_package( $post_id ) {
		$this->enqueue_assets();

		$meta          = $this->plugin->get_package_meta( $post_id );
		$price_text    = $this->plugin->format_price_display( $meta['price'] );
		$gallery_ids   = $meta['gallery_ids'];
		$related_title = $this->plugin->get_localized_setting( 'related_title' );
		$labels        = $this->plugin->get_ui_labels();
		$settings      = $this->plugin->get_settings();
		$show_kicker   = ! isset( $settings['single_show_kicker'] ) || 'no' !== $settings['single_show_kicker'];
		$gallery_theme = isset( $settings['gallery_theme'] ) && 'theme_2' === $settings['gallery_theme'] ? 'theme_2' : 'theme_1';
		$has_includes      = '' !== trim( wp_strip_all_tags( $meta['includes_content'] ) );
		$has_excludes      = '' !== trim( wp_strip_all_tags( $meta['excludes_content'] ) );
		$has_general_info  = '' !== trim( wp_strip_all_tags( $meta['general_info_content'] ) );
		$has_manual_table  = '' !== trim( wp_strip_all_tags( $meta['includes_table_html'] ) );
		$has_tables        = ! empty( $meta['includes_tables'] );
		$has_pdf       = ! empty( $meta['includes_pdf_id'] );
		?>
		<div class="abp-single">
			<?php $custom_css = $this->get_custom_style_css(); if ( ! empty( $custom_css ) ) : ?>
				<style><?php echo $custom_css; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></style>
			<?php endif; ?>
			<header class="abp-single-header">
				<?php if ( $show_kicker ) : ?>
					<span class="abp-single-kicker"><?php echo esc_html( $labels['travel_package'] ); ?></span>
				<?php endif; ?>
				<h1><?php echo esc_html( get_the_title( $post_id ) ); ?></h1>
				<?php if ( $meta['subtitle'] ) : ?>
					<p class="abp-single-subtitle"><?php echo esc_html( $meta['subtitle'] ); ?></p>
				<?php endif; ?>
			</header>

			<div class="abp-single-gallery-shell">
				<div class="abp-single-gallery abp-gallery-<?php echo esc_attr( $gallery_theme ); ?><?php echo 'theme_2' === $gallery_theme ? ' abp-gallery-theme-2 abp-gallery-theme_2' : ''; ?>">
					<div class="abp-single-main-image">
						<?php if ( has_post_thumbnail( $post_id ) ) : ?>
							<?php echo get_the_post_thumbnail( $post_id, 'full', array( 'class' => 'abp-active-image', 'data-abp-main-image' => '1' ) ); ?>
						<?php endif; ?>
					</div>
					<?php if ( ! empty( $gallery_ids ) ) : ?>
						<div class="abp-single-thumbs" data-abp-gallery>
							<?php if ( has_post_thumbnail( $post_id ) ) : ?>
								<?php $featured_id = get_post_thumbnail_id( $post_id ); ?>
								<?php $featured_full = wp_get_attachment_image_url( $featured_id, 'full' ); ?>
								<button type="button" class="abp-thumb is-active" data-image="<?php echo esc_url( $featured_full ); ?>">
									<?php echo get_the_post_thumbnail( $post_id, 'medium' ); ?>
								</button>
							<?php endif; ?>
							<?php foreach ( $gallery_ids as $image_id ) : ?>
								<?php $full = wp_get_attachment_image_url( $image_id, 'full' ); ?>
								<?php $thumb = wp_get_attachment_image_url( $image_id, 'medium' ); ?>
								<button type="button" class="abp-thumb" data-image="<?php echo esc_url( $full ); ?>" data-thumb="<?php echo esc_url( $thumb ); ?>">
									<?php echo wp_get_attachment_image( $image_id, 'medium' ); ?>
								</button>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>

				<div class="abp-info-tiles">
					<?php
					$duration_nights_text = $this->get_duration_and_nights_display( $meta );
					if ( '' !== $duration_nights_text ) :
					?>
						<div class="abp-info-tile abp-info-duration">
							<span class="abp-info-tile-icon abp-info-tile-icon-duration" aria-hidden="true"><?php echo wp_kses( $this->get_info_icon_svg( 'duration' ), $this->get_svg_allowed_html() ); ?></span>
							<div class="abp-info-tile-content">
								<span class="abp-info-tile-label"><?php echo esc_html( $labels['duration_label'] ); ?></span>
								<strong class="abp-info-tile-value"><?php echo esc_html( $duration_nights_text ); ?></strong>
							</div>
						</div>
					<?php endif; ?>
					<?php if ( $meta['price'] ) : ?>
						<div class="abp-info-tile abp-info-price">
							<span class="abp-info-tile-icon abp-info-tile-icon-price" aria-hidden="true"><?php echo wp_kses( $this->get_info_icon_svg( 'price' ), $this->get_svg_allowed_html() ); ?></span>
							<div class="abp-info-tile-content">
								<span class="abp-info-tile-label"><?php echo esc_html( ! empty( $meta['price_label'] ) ? $meta['price_label'] : $labels['price_label'] ); ?></span>
								<strong class="abp-info-tile-value"><?php echo esc_html( $price_text ); ?></strong>
							</div>
						</div>
					<?php endif; ?>
					<?php
					$destination_text = $this->get_package_destination_display( $post_id, $meta );
					if ( '' !== $destination_text ) :
					?>
						<div class="abp-info-tile abp-info-destination">
							<span class="abp-info-tile-icon abp-info-tile-icon-destination" aria-hidden="true"><?php echo wp_kses( $this->get_info_icon_svg( 'destination' ), $this->get_svg_allowed_html() ); ?></span>
							<div class="abp-info-tile-content">
								<span class="abp-info-tile-label"><?php echo esc_html( $labels['destination_label'] ); ?></span>
								<strong class="abp-info-tile-value"><?php echo esc_html( $destination_text ); ?></strong>
							</div>
						</div>
					<?php endif; ?>
				</div>
			</div>

			<section class="abp-content-section">
				<h2><?php echo esc_html( $meta['description_title'] ); ?></h2>
				<div class="abp-richtext"><?php echo wp_kses_post( wpautop( $meta['description_content'] ) ); ?></div>
			</section>

			<?php if ( $has_includes ) : ?>
				<section class="abp-content-section abp-includes-section">
					<h2><?php echo esc_html( $meta['includes_title'] ); ?></h2>
					<div class="abp-richtext"><?php echo wp_kses_post( wpautop( $meta['includes_content'] ) ); ?></div>
				</section>
			<?php endif; ?>

			<?php if ( $has_excludes ) : ?>
				<section class="abp-content-section abp-excludes-section">
					<h2><?php echo esc_html( $meta['excludes_title'] ); ?></h2>
					<div class="abp-richtext"><?php echo wp_kses_post( wpautop( $meta['excludes_content'] ) ); ?></div>
				</section>
			<?php endif; ?>

			<?php if ( $has_general_info ) : ?>
				<section class="abp-content-section abp-general-info-section">
					<h2><?php echo esc_html( $meta['general_info_title'] ); ?></h2>
					<div class="abp-richtext"><?php echo wp_kses_post( wpautop( $meta['general_info_content'] ) ); ?></div>
				</section>
			<?php endif; ?>

			<?php if ( $has_manual_table || $has_tables || $has_pdf ) : ?>
				<section class="abp-content-section abp-tables-section">
					<?php if ( $has_manual_table || $has_tables ) : ?>
						<h2><?php echo esc_html( $labels['package_tables'] ); ?></h2>
					<?php endif; ?>
					<?php if ( $has_manual_table ) : ?>
						<div class="abp-richtext abp-manual-table-content"><?php echo wp_kses_post( do_shortcode( $meta['includes_table_html'] ) ); ?></div>
					<?php endif; ?>
					<?php if ( $has_tables ) : ?>
						<?php foreach ( $meta['includes_tables'] as $table_text ) : ?>
							<?php $this->render_table_from_text( $table_text ); ?>
						<?php endforeach; ?>
					<?php endif; ?>
					<?php $this->render_pdf_attachment( $meta['includes_pdf_id'] ); ?>
				</section>
			<?php endif; ?>

			<?php $this->render_related_packages( $post_id, $related_title ); ?>
		</div>
		<?php
	}

	private function get_svg_allowed_html() {
		return array(
			'svg'  => array(
				'viewBox'     => true,
				'fill'        => true,
				'stroke'      => true,
				'xmlns'       => true,
				'width'       => true,
				'height'      => true,
				'class'       => true,
				'aria-hidden' => true,
				'focusable'   => true,
			),
			'path' => array(
				'd'               => true,
				'fill'            => true,
				'stroke'          => true,
				'stroke-width'    => true,
				'stroke-linecap'  => true,
				'stroke-linejoin' => true,
				'opacity'         => true,
			),
			'circle' => array(
				'cx'              => true,
				'cy'              => true,
				'r'               => true,
				'fill'            => true,
				'stroke'          => true,
				'stroke-width'    => true,
				'opacity'         => true,
			),
			'line' => array(
				'x1'              => true,
				'x2'              => true,
				'y1'              => true,
				'y2'              => true,
				'stroke'          => true,
				'stroke-width'    => true,
				'stroke-linecap'  => true,
			),
			'rect' => array(
				'x'               => true,
				'y'               => true,
				'width'           => true,
				'height'          => true,
				'rx'              => true,
				'ry'              => true,
				'fill'            => true,
				'stroke'          => true,
				'stroke-width'    => true,
			),
			'ellipse' => array(
				'cx'           => true,
				'cy'           => true,
				'rx'           => true,
				'ry'           => true,
				'fill'         => true,
				'stroke'       => true,
				'stroke-width' => true,
				'opacity'      => true,
				'transform'    => true,
			),
		);
	}

	private function get_info_icon_svg( $type ) {
		if ( 'duration' === $type ) {
			return '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6 3h12M6 21h12" stroke="#2a78bf" stroke-width="2" stroke-linecap="round"/><path d="M7 3v3.5a5.5 5.5 0 0 0 2.2 4.4L12 13l2.8-2.1A5.5 5.5 0 0 0 17 6.5V3" fill="#edf6ff" stroke="#2a78bf" stroke-width="1.8" stroke-linejoin="round"/><path d="M7 21v-3.5a5.5 5.5 0 0 1 2.2-4.4L12 11l2.8 2.1A5.5 5.5 0 0 1 17 17.5V21" fill="#edf6ff" stroke="#2a78bf" stroke-width="1.8" stroke-linejoin="round"/><ellipse cx="12" cy="18" rx="2.5" ry="1.2" fill="#f26923"/><circle cx="12" cy="14" r="0.9" fill="#f26923"/></svg>';
		}

		if ( 'nights' === $type ) {
			return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M2 19h20M2 17V5a1 1 0 0 1 1-1h1a1 1 0 0 1 1 1v7h14V9a1 1 0 0 1 1-1h1a1 1 0 0 1 1 1v8" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M5 14h14a3 3 0 0 1 3 3v2H2v-2a3 3 0 0 1 3-3z" stroke-width="1.8" stroke-linejoin="round"/><circle cx="7" cy="8.5" r="1.5" fill="currentColor"/></svg>';
		}

		if ( 'destination' === $type ) {
			return '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><ellipse cx="12" cy="19.5" rx="8" ry="3.2" fill="#e1f0ff" stroke="#1e88e5" stroke-width="1.8"/><circle cx="12" cy="19.5" r="1.6" fill="#1e88e5"/><path d="M12 2C8.68 2 6 4.68 6 8c0 4.5 6 10.5 6 10.5S18 12.5 18 8c0-3.32-2.68-6-6-6z" fill="#e53935" stroke="#b71c1c" stroke-width="1.4"/><circle cx="12" cy="8" r="2.4" fill="#ffffff"/></svg>';
		}

		return '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M9 3.5h6l1.2 3.2c-.8.5-2 .8-4.2.8s-3.4-.3-4.2-.8L9 3.5z" fill="#f59e0b" stroke="#d97706" stroke-width="1.6" stroke-linejoin="round"/><path d="M12 7.5c-4.8 0-8 3.2-8 7.5 0 4.2 3.6 5.5 8 5.5s8-1.3 8-5.5c0-4.3-3.2-7.5-8-7.5z" fill="#fffbeb" stroke="#d97706" stroke-width="1.8" stroke-linejoin="round"/><path d="M12 10.5v5.5M13.8 12c0-.8-.7-1.3-1.8-1.3s-1.8.5-1.8 1.3.8 1.2 1.8 1.4c1 .2 1.8.6 1.8 1.4s-.7 1.4-1.8 1.4-1.8-.6-1.8-1.4" stroke="#d97706" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>';
	}

	private function get_duration_and_nights_display( $meta ) {
		$lang     = $this->plugin->get_current_language();
		$duration = 'en' === $lang && ! empty( $meta['duration_en'] ) ? trim( (string) $meta['duration_en'] ) : ( isset( $meta['duration'] ) ? trim( (string) $meta['duration'] ) : '' );
		$nights   = 'en' === $lang && ! empty( $meta['nights_en'] ) ? trim( (string) $meta['nights_en'] ) : ( isset( $meta['nights'] ) ? trim( (string) $meta['nights'] ) : '' );

		$duration_num = 0;
		if ( preg_match( '/\d+/', $duration, $m ) ) {
			$duration_num = (int) $m[0];
		}

		$nights_num = 0;
		if ( preg_match( '/\d+/', $nights, $m ) ) {
			$nights_num = (int) $m[0];
		}

		if ( $nights_num > 0 && $duration_num <= 0 ) {
			$duration_num = $nights_num + 1;
		} elseif ( $duration_num > 1 && $nights_num <= 0 ) {
			$nights_num = $duration_num - 1;
		}

		if ( $duration_num > 0 && $nights_num > 0 ) {
			if ( 'en' === $lang ) {
				$d_txt = 1 === $duration_num ? '1 Day' : $duration_num . ' Days';
				$n_txt = 1 === $nights_num ? '1 Night' : $nights_num . ' Nights';
			} else {
				$d_txt = 1 === $duration_num ? '1 Ημέρα' : $duration_num . ' Ημέρες';
				$n_txt = 1 === $nights_num ? '1 Διανυκτέρευση' : $nights_num . ' Διανυκτερεύσεις';
			}
			return $d_txt . ' / ' . $n_txt;
		}

		if ( '' !== $duration && '' !== $nights ) {
			return $duration . ' / ' . $nights;
		}

		if ( '' !== $duration ) {
			return $duration;
		}

		if ( '' !== $nights ) {
			return $nights;
		}

		return '';
	}

	private function get_package_destination_display( $post_id, $meta ) {
		$lang = $this->plugin->get_current_language();
		$dest = '';

		// 1. Direct meta field
		if ( 'en' === $lang && ! empty( $meta['destination_en'] ) ) {
			$dest = trim( (string) $meta['destination_en'] );
		}
		if ( '' === $dest && ! empty( $meta['destination'] ) ) {
			$dest = trim( (string) $meta['destination'] );
		}

		// 2. Taxonomy athsbp_destination
		if ( '' === $dest ) {
			$terms = get_the_terms( $post_id, $this->plugin->taxonomy_name_from_slug( 'destination' ) );
			if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
				$term = reset( $terms );
				$dest = $term->name;
			}
		}

		// 3. Taxonomy athsbp_country
		if ( '' === $dest ) {
			$terms = get_the_terms( $post_id, $this->plugin->taxonomy_name_from_slug( 'country' ) );
			if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
				$term = reset( $terms );
				$dest = $term->name;
			}
		}

		// 4. Fallback to badge_text
		if ( '' === $dest ) {
			if ( 'en' === $lang && ! empty( $meta['badge_text_en'] ) ) {
				$dest = trim( (string) $meta['badge_text_en'] );
			}
			if ( '' === $dest && ! empty( $meta['badge_text'] ) ) {
				$dest = trim( (string) $meta['badge_text'] );
			}
		}

		return $dest;
	}

	private function render_table_from_text( $table_text ) {
		$lines = array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', (string) $table_text ) ) );
		if ( empty( $lines ) ) {
			return;
		}
		?>
		<div class="abp-table-wrap">
			<table class="abp-custom-table">
				<tbody>
					<?php foreach ( $lines as $index => $line ) : ?>
						<?php $cells = array_map( 'trim', explode( '|', $line ) ); ?>
						<tr>
							<?php foreach ( $cells as $cell_index => $cell ) : ?>
								<?php if ( 0 === $index && 0 === $cell_index ) : ?>
									<th class="abp-corner-heading"><?php echo esc_html( $cell ); ?></th>
								<?php elseif ( 0 === $index ) : ?>
									<th><?php echo esc_html( $cell ); ?></th>
								<?php elseif ( 0 === $cell_index ) : ?>
									<th scope="row" class="abp-row-heading"><?php echo esc_html( $cell ); ?></th>
								<?php else : ?>
									<td><?php echo esc_html( $cell ); ?></td>
								<?php endif; ?>
							<?php endforeach; ?>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	private function render_pdf_attachment( $attachment_id ) {
		$attachment_id = absint( $attachment_id );
		if ( ! $attachment_id ) {
			return;
		}

		$url = wp_get_attachment_url( $attachment_id );
		if ( ! $url ) {
			return;
		}

		$title = get_the_title( $attachment_id );
		if ( ! $title ) {
			$title = basename( $url );
		}
		?>
		<div class="abp-pdf-embed">
			<iframe src="<?php echo esc_url( $url ); ?>" title="<?php echo esc_attr( $title ); ?>"></iframe>
			<a class="abp-pdf-link" href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $title ); ?></a>
		</div>
		<?php
	}

	private function render_related_packages( $post_id, $heading ) {
		$taxonomies = array( ATHSBP_Plugin::TYPE_TAX );
		$groups     = array_merge(
			$this->plugin->get_all_predefined_filter_groups(),
			$this->plugin->get_custom_filter_groups()
		);

		foreach ( $groups as $group ) {
			if ( $this->plugin->is_range_filter_type( $group['input_type'] ) ) {
				continue;
			}

			$taxonomies[] = $this->plugin->taxonomy_name_from_slug( $group['slug'] );
		}

		$taxonomies       = array_values( array_unique( $taxonomies ) );
		$terms            = wp_get_post_terms( $post_id, $taxonomies );
		$tax_query        = array();
		$current_term_map = array();
		$related_ids      = array();

		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				if ( ! isset( $current_term_map[ $term->taxonomy ] ) ) {
					$current_term_map[ $term->taxonomy ] = array();
				}
				$current_term_map[ $term->taxonomy ][] = (int) $term->term_id;
			}

			foreach ( $current_term_map as $taxonomy => $term_ids ) {
				$tax_query[] = array(
					'taxonomy' => $taxonomy,
					'field'    => 'term_id',
					'terms'    => $term_ids,
					'operator' => 'IN',
				);
			}
		}

		if ( ! empty( $tax_query ) ) {
			$tax_query['relation'] = 'OR';

			$query = new WP_Query(
				array(
					'post_type'      => ATHSBP_Plugin::CPT,
					'post_status'    => 'publish',
					'posts_per_page' => 24,
					// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Similar package relevance is based on shared package taxonomies.
					'tax_query'      => $tax_query,
					// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Similar package output excludes expired packages.
					'meta_query'     => $this->plugin->get_active_package_meta_query_args(),
					'orderby'        => 'date',
					'order'          => 'DESC',
					'no_found_rows'  => true,
				)
			);

			if ( $query->have_posts() ) {
				$candidate_ids = array_diff( wp_list_pluck( $query->posts, 'ID' ), array( $post_id ) );
				$related_ids   = $this->rank_related_packages( $candidate_ids, $current_term_map, $taxonomies );
			}
		}

		if ( count( $related_ids ) < 3 ) {
			$related_ids = array_merge(
				$related_ids,
				$this->get_fallback_related_package_ids( $post_id, $related_ids, 3 - count( $related_ids ) )
			);
		}

		$related_ids = array_slice( array_values( array_unique( array_map( 'intval', $related_ids ) ) ), 0, 3 );

		if ( empty( $related_ids ) ) {
			return;
		}
		?>
		<section class="abp-content-section">
			<h2><?php echo esc_html( $heading ); ?></h2>
			<div class="abp-card-grid">
				<?php foreach ( $related_ids as $related_id ) : ?>
					<?php $this->render_package_card( $related_id ); ?>
				<?php endforeach; ?>
			</div>
		</section>
		<?php
		wp_reset_postdata();
	}

	private function get_fallback_related_package_ids( $post_id, $exclude_ids, $limit ) {
		if ( $limit < 1 ) {
			return array();
		}

		$exclude_ids = array_unique(
			array_merge(
				array( (int) $post_id ),
				array_map( 'intval', $exclude_ids )
			)
		);

		$query = new WP_Query(
			array(
				'post_type'      => ATHSBP_Plugin::CPT,
				'post_status'    => 'publish',
				'posts_per_page' => $limit + count( $exclude_ids ),
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Fallback related output excludes expired packages.
				'meta_query'     => $this->plugin->get_active_package_meta_query_args(),
				'orderby'        => 'date',
				'order'          => 'DESC',
				'no_found_rows'  => true,
			)
		);

		if ( ! $query->have_posts() ) {
			return array();
		}

		$ids = array_diff( wp_list_pluck( $query->posts, 'ID' ), $exclude_ids );

		return array_slice( array_values( array_map( 'intval', $ids ) ), 0, $limit );
	}

	private function rank_related_packages( $candidate_ids, $current_term_map, $taxonomies ) {
		$weights = array(
			ATHSBP_Plugin::TYPE_TAX => 5,
			$this->plugin->taxonomy_name_from_slug( 'destination' ) => 4,
			$this->plugin->taxonomy_name_from_slug( 'country' ) => 4,
			$this->plugin->taxonomy_name_from_slug( 'important-holidays' ) => 3,
			$this->plugin->taxonomy_name_from_slug( 'travel-style' ) => 2,
		);

		$scored = array();

		foreach ( $candidate_ids as $candidate_id ) {
			$candidate_terms = wp_get_post_terms( $candidate_id, $taxonomies );
			if ( is_wp_error( $candidate_terms ) || empty( $candidate_terms ) ) {
				continue;
			}

			$candidate_map = array();
			foreach ( $candidate_terms as $term ) {
				if ( ! isset( $candidate_map[ $term->taxonomy ] ) ) {
					$candidate_map[ $term->taxonomy ] = array();
				}
				$candidate_map[ $term->taxonomy ][] = (int) $term->term_id;
			}

			$score = 0;
			foreach ( $current_term_map as $taxonomy => $term_ids ) {
				if ( empty( $candidate_map[ $taxonomy ] ) ) {
					continue;
				}

				$shared = array_intersect( $term_ids, $candidate_map[ $taxonomy ] );
				if ( empty( $shared ) ) {
					continue;
				}

				$weight = isset( $weights[ $taxonomy ] ) ? $weights[ $taxonomy ] : 1;
				$score += count( $shared ) * $weight;
			}

			if ( $score > 0 ) {
				$scored[] = array(
					'id'    => (int) $candidate_id,
					'score' => $score,
					'date'  => get_post_time( 'U', true, $candidate_id ),
				);
			}
		}

		if ( empty( $scored ) ) {
			return array();
		}

		usort(
			$scored,
			static function ( $left, $right ) {
				if ( $left['score'] === $right['score'] ) {
					return $right['date'] <=> $left['date'];
				}

				return $right['score'] <=> $left['score'];
			}
		);

		return array_slice(
			array_map(
				static function ( $item ) {
					return $item['id'];
				},
				$scored
			),
			0,
			4
		);
	}
}
