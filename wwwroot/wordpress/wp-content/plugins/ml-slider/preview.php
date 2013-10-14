<?php

define('WP_USE_THEMES', false);

require('../../../wp-blog-header.php');

if (isset($_GET['slider_id']) && (int)$_GET['slider_id'] > 0) {
	add_filter( 'show_admin_bar', '__return_false' );
	remove_action('init', 'wp_admin_bar_init');

    $id = intval($_GET['slider_id']);
    echo "<html style='margin-top: 0 !important'><head>";
    echo "</head><body style='overflow: hidden; margin: 0; padding: 0;'>";
    echo do_shortcode("[metaslider id={$id}]");
    wp_footer();
    echo "</body></html>";
}

die();