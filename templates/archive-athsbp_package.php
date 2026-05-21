<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$plugin = ATHSBP_Plugin::instance();
get_header();
?>
<main class="abp-theme-wrap">
	<?php echo do_shortcode( '[athsbp_packages]' ); ?>
</main>
<?php
get_footer();

