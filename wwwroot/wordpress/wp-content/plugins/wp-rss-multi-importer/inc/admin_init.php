<?php







add_action( 'admin_head', 'rssmi_custom_post_type_icon' );

function rssmi_custom_post_type_icon() {
    ?>
    <style>
        /* Post Screen - 32px */
        .icon32-posts-rssmi_feed {
            background: transparent url( <?php echo WP_RSS_MULTI_IMAGES.'RSSadmin32.png'; ?> ) no-repeat left top !important;
        } 
        /* Post Screen - 32px */
        .icon32-posts-rssmi_feed_item {
            background: transparent url( <?php echo WP_RSS_MULTI_IMAGES.'RSSadmin32.png'; ?> ) no-repeat left top !important;
        }   
    </style>
<?php }

//ON INIT

add_action('admin_init','wp_rss_multi_importer_start');









function wp_rss_multi_importer_start () {
	
register_setting('wp_rss_multi_importer_options', 'rss_import_items');
register_setting('wp_rss_multi_importer_categories', 'rss_import_categories');	
register_setting('wp_rss_multi_importer_item_options', 'rss_import_options');	 
register_setting('wp_rss_multi_importer_template_item', 'rss_template_item');	 
register_setting('wp_rss_multi_importer_feed_options', 'rss_feed_options');	 
register_setting('wp_rss_multi_importer_post_options', 'rss_post_options');	 
register_setting('wp_rss_multi_importer_admin_options', 'rss_admin_options');
register_setting('wp_rss_multi_importer_categories_images', 'rss_import_categories_images');	 
add_settings_section( 'wp_rss_multi_importer_main', '', 'wp_section_text', 'wprssimport' );  

}

add_action('admin_init', 'ilc_farbtastic_script');

function ilc_farbtastic_script() {
  wp_enqueue_style( 'farbtastic' );
  wp_enqueue_script( 'farbtastic' );
}



add_action('init', 'wp_rss_multi_importer_post_to_feed');

function wp_rss_multi_importer_post_to_feed(){
  $post_options = get_option('rss_post_options'); 
	if (!empty($post_options)) {
		if ($post_options['targetWindow']==0 && (isset($post_options['active']) && $post_options['active']==1)){
			add_action('wp_footer','colorbox_scripts');
		}
		if ($post_options['noindex']==1){
			add_action('wp_head', 'rssmi_noindex_function');
		}
	}
}



function rssmi_isMobile() {
    return preg_match("/(android|avantgo|blackberry|bolt|boost|cricket|docomo|fone|hiptop|mini|mobi|palm|phone|pie|tablet|up\.browser|up\.link|webos|wos)/i", $_SERVER["HTTP_USER_AGENT"]);
}

function isMobileForWordPress() {
	global $isMobileDevice;
    if(rssmi_isMobile()){
       $isMobileDevice=1;
		}else{
 			$isMobileDevice=0;
		}
		return $isMobileDevice;
}

add_action('init', 'isMobileForWordPress', 1);


function startSimplePie(){
	if(! class_exists('SimplePie')){
	     		require_once(ABSPATH . WPINC . '/class-simplepie.php');
	}

	class SimplePie_RSSMI extends SimplePie {}	

	
}
add_action('init', 'startSimplePie');




//  The main admin menu system

add_action('admin_menu','wp_rss_multi_importer_menu');

function wp_rss_multi_importer_menu () {
	
	add_menu_page(__('Overview'), __('Multi Importer'), 'manage_options', 'wprssmi', 'wp_rss_multi_importer_intro_page', WP_RSS_MULTI_IMAGES."RSSadmin16.png", '150'); 

	add_submenu_page('wprssmi', 'Instructions', 'Instructions','manage_options', 'wprssmi', 'wp_rss_multi_importer_intro_page', '', '');

	add_submenu_page( 'wprssmi', 'Feed List', 'Feed List', 'manage_options', 'edit.php?post_type=rssmi_feed', '' );

	add_submenu_page( 'wprssmi', 'Add a Feed', 'Add a Feed', 'edit_posts', 'post-new.php?post_type=rssmi_feed', '' );
	
	add_submenu_page( 'wprssmi', 'Upload RSS Feed', 'Upload RSS Feeds', 'manage_options', 'wprssmi_options8', 'wp_rss_multi_importer_upload_page' );

	add_submenu_page( 'wprssmi', 'Categories', 'Categories', 'manage_options', 'wprssmi_options', 'rssmi_category_pages','' );  
	
	add_submenu_page( 'wprssmi', 'Shortcode Settings', 'Shortcode Settings', 'manage_options', 'wprssmi_options2', 'wp_rss_multi_importer_options_page' );   
	
	add_submenu_page( 'wprssmi', 'Shortcode Items', 'Shortcode Items', 'edit_posts', 'edit.php?post_type=rssmi_feed_item', '' );

	add_submenu_page( 'wprssmi', 'AutoPost Settings', 'AutoPost Settings', 'manage_options', 'wprssmi_options3', 'wp_rss_multi_importer_post_page' );  

	add_submenu_page( 'wprssmi', 'Manage AutoPosts', 'Manage AutoPosts', 'manage_options', 'wprssmi_options4', 'rssmi_posts_list' );  

	add_submenu_page( 'wprssmi', 'Template Options', 'Template Options', 'manage_options', 'wprssmi_options5', 'wp_rss_multi_importer_template_page' ); 

	add_submenu_page( 'wprssmi', 'Shortcode Parameters', 'Shortcode Parameters', 'manage_options', 'wprssmi_options6', 'wp_rss_multi_importer_style_tags' );  
	
	add_submenu_page( 'wprssmi', 'Export RSS Feed', 'Export RSS Feed', 'manage_options', 'wprssmi_options7', 'wp_rss_multi_importer_feed_page' );
	
	
	
		
	
}


function rssmi_category_pages(){
	
		wp_rss_multi_importer_category_page();
	wp_rss_multi_importer_category_images_page();
}

function rssmi_posts_list(){
	global $myListTable;
	my_add_menu_items();
	add_options();
	my_render_list_page();
	$myListTable->admin_header();
}


add_action( 'widgets_init', 'src_load_widgets');  //load widget

function src_load_widgets() {
register_widget('WP_Multi_Importer_Widget');
}










?>