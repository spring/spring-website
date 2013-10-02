<?php



function rssmi_restore_all_delete() {
rssmi_restore_all();
}



add_action('wp_ajax_restore_all', 'restore_all_callback_delete');

function restore_all_callback_delete() {

			rssmi_restore_all_delete();
			echo '<h3>Everything has been deleted.</h3>';
			die();
}














function delete_db_transients() {

    global $wpdb;

  
    $expired = $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '_transient_wprssmi_%';" );

    foreach( $expired as $transient ) {

        $key = str_replace('_transient_', '', $transient);
        delete_transient($key);

    }
}


function rssmi_list_the_plugins() {
    $plugins = get_option ( 'active_plugins', array () );
    foreach ( $plugins as $plugin ) {
        echo "<li>$plugin</li>";
    }
}





function rssmi_list_options(){
	
	 $options = get_option( 'rss_import_options' );
	
	 foreach ( $options as $option ) {
	        echo "<li>$option</li>";
	    }
	
}

function rssmi_change_post_status($post_id,$status){
    $current_post = get_post( $post_id, 'ARRAY_A' );
    $current_post['post_status'] = $status;
    wp_update_post($current_post);
}



function rssmi_delete_attachment($id_ID){
	$args = array( 'post_type' => 'attachment', 'numberposts' => -1, 'post_status' =>'any', 'post_parent' => $id_ID ); 
	$attachments = get_posts($args);			
	if ($attachments) {
		foreach ( $attachments as $attachment ) {
			 wp_delete_attachment($attachment->ID,true);
		}
	}
}


function rssmi_delete_posts(){
	
	global $wpdb;
	$post_options_delete = get_option('rss_post_options');
	$expiration=$post_options_delete['expiration'];
	$oldPostStatus=$post_options_delete['oldPostStatus'];
	$serverTimezone=$post_options_delete['timezone'];
	
	if (isset($serverTimezone) && $serverTimezone!=''){
		date_default_timezone_set($serverTimezone);
	}
	

	$query = "SELECT ID FROM $wpdb->posts WHERE post_status = 'publish' AND post_type = 'post' AND DATEDIFF(NOW(), `post_date`) > ".$expiration;
	$ids = $wpdb->get_results($query);
	
	foreach ($ids as $id){
		$mypostids = $wpdb->get_results("SELECT * FROM $wpdb->postmeta WHERE meta_key = 'rssmi_item_permalink' AND post_id = ".$id->ID);
		if(get_post_meta($id->ID, 'rssmi_source_protect', true)==1) continue;
		if (!empty($mypostids)){
			
			if($oldPostStatus==0){
				rssmi_delete_attachment($id->ID);
				wp_delete_post($id->ID, true);
			}elseif ($oldPostStatus==1){
				wp_delete_post($id->ID, false);
			}elseif($oldPostStatus==2){
				rssmi_change_post_status($id->ID,'pending');
			}
		
		}

	}
	
}



function rssmi_delete_custom_posts(){  // TIMED DELETE ITEMS USED FOR SHORTCODE
	
	global $wpdb;
	$custom_post_options_delete = get_option('rss_import_options');
	$post_options = get_option('rss_post_options');
	$expiration=$custom_post_options_delete['expiration'];
	$serverTimezone=$post_options['timezone'];
	$expiration=$expiration;
	
	if (isset($serverTimezone) && $serverTimezone!=''){
		date_default_timezone_set($serverTimezone);
	}
	if (isset($expiration) && $expiration!=0){
		
	
	$query = "SELECT ID FROM $wpdb->posts WHERE post_status = 'publish' AND post_type = 'rssmi_feed_item' AND DATEDIFF(NOW(), `post_date`) > ".$expiration;
	

	
	
	$ids = $wpdb->get_results($query);
		if (!empty($ids)){
			foreach ($ids as $id){
					wp_delete_post($id->ID, true);
				}

			}
	}
}








function rssmi_delete_posts_admin(){  //  USE FOR QUICK DELETE OF BLOG POSTS
	
	global $wpdb;
	$expiration=-1;
	$query = "SELECT ID FROM $wpdb->posts WHERE post_status = 'publish' AND post_type = 'post' AND DATEDIFF(NOW(), `post_date`) > ".$expiration;
	$ids = $wpdb->get_results($query);
	
	foreach ($ids as $id){
		$mypostids = $wpdb->get_results("SELECT * FROM $wpdb->postmeta WHERE meta_key = 'rssmi_source_link' AND post_id = ".$id->ID);
		if (!empty($mypostids)){
			rssmi_delete_attachment($id->ID);
				wp_delete_post($id->ID, true);
		}
	}
	
}


function rssmi_restore_all(){  //  DELETES EVERYTHING CAUSED BY THIS PLUGIN IN THE POST AND POST META TABLES
	global $wpdb;	
	rssmi_delete_posts_admin();

	$query = "SELECT ID FROM $wpdb->posts WHERE post_status = 'publish' AND post_type = 'rssmi_feed_item' OR post_type = 'rssmi_feed'";

	$ids = $wpdb->get_results($query);

	if (!empty($ids)){
		foreach ($ids as $id){
			wp_delete_post($id->ID, true);
			}

		}
	
}



?>