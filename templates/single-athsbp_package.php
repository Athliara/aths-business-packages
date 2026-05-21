<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$plugin = ATHSBP_Plugin::instance();
get_header();
?>
<main class="abp-theme-wrap">
	<?php $plugin->get_frontend()->render_single_package( get_the_ID() ); ?>
</main>
<?php
get_footer();
