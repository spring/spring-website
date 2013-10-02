<?php
 add_action( 'init', 'create_rssmi_feed' );

function create_rssmi_feed() 
{

      
       $feed_args = array(
           	'public'        => true,
           	'query_var'     => 'rssmifeed',
           	'menu_position' => 100,
        	'show_in_menu'  => false,
           	'supports'      => array( 'title' ),
           	'rewrite'       => array(
                               'slug'       => 'rssmifeeds',
                               'with_front' => false
                               ),            
           	'labels'        => array(
                               'name'                  => __( 'Feed List' ),
                               'singular_name'         => __( 'Feed' ),
                               'add_new'               => __( 'Add New Feed Source' ),
                               'all_items'             => __( 'View Feed Sources' ),
                               'add_new_item'          => __( 'Add New Feed Source' ),
                               'edit_item'             => __( 'Edit Feed Source' ),
                               'new_item'              => __( 'New Feed Source' ),
                               'view_item'             => __( 'View Feed Source' ),
                               'search_items'          => __( 'Search Feeds' ),
                               'not_found'             => __( 'No Feed Sources Found' ),
                               'not_found_in_trash'    => __( 'No Feed Sources Found In Trash' ),
                               'menu_name'             => __( 'RSS Multi Importer' )
                               ),
       );
       
 
       register_post_type( 'rssmi_feed', $feed_args );

   
       $feed_item_args = array(
           'public'         => true,
           'query_var'      => 'feed_item',
           'show_in_menu'   => false,
           'rewrite'        => array(
                                'slug'       => 'rssmifeeds/items',
                                'with_front' => false,
                               ),       
           'labels'         => array(
                                'name'                  => __( 'Shortcode Items' ),
                                'singular_name'         => __( 'Shortcode Items' ),
                                'all_items'             => __( 'Shortcode Items' ),
                                'view_item'             => __( 'View Shortcode Items' ),                            
                                'search_items'          => __( 'Search Shortcode Items' ),
                                'not_found'             => __( 'No Imported Feeds Found' ),
                                'not_found_in_trash'    => __( 'No Imported Feeds Found In Trash')
                               ),
       );
       
       // Register the 'feed_item' post type
       register_post_type( 'rssmi_feed_item', $feed_item_args );        
}

add_action( 'admin_init', 'my_admin' );

function remove_publish_box()
{
	remove_meta_box( 'submitdiv', 'rssmi_feed', 'side' );


}

add_action( 'admin_menu', 'remove_publish_box' );



add_filter( 'manage_edit-rssmi_feed_columns', 'rssmi_set_custom_columns'); 
   /*     
    Set up the custom columns for the wprss_feed list

    */      
   function rssmi_set_custom_columns( $columns ) {

       $columns = array (
           'cb'          => '<input type="checkbox" />',
           'title'       => __( 'Name', 'rssmi' ),
           'url'         => __( 'URL', 'rssmi' ),
           'category' => __( 'Category', 'rssmi' ),
 			'bloguser' => __( 'Blog User', 'rssmi' ),
			'feedstatus' => __( 'Feed Status', 'rssmi' ),
			'ID' => __( 'ID', 'rssmi' ),
       );
       return $columns;
   }





	add_action( "manage_rssmi_feed_posts_custom_column", "rssmi_show_custom_columns", 10, 2 );
   /*
    Show up the custom columns for the wprss_feed list

    */  
   function rssmi_show_custom_columns( $column, $post_id ) {
    
     switch ( $column ) {    
       case 'url':
         	$url = get_post_meta( $post_id, 'rssmi_url', true);
         	echo '<a href="' . esc_url($url) . '">' . esc_url($url) . '</a>';
         	break;
       case 'category':
         	$category = get_post_meta( $post_id, 'rssmi_cat', true);
         	echo esc_html( wp_getCategoryName($category) );
         	break;  
		case 'bloguser':
	    	$bloguser = get_post_meta( $post_id, 'rssmi_user', true);
			$bloguser=(int) $bloguser;
			echo esc_html(get_userdata($bloguser)->display_name );
	    	break;
		case 'feedstatus':
			echo rssmi_check_url_status($post_id);
			break; 
		case 'ID':
			echo $post_id;
			break;   
     }
   }


   /*
    * Make the custom columns sortable
    */  
   function rssmi_sortable_columns() {
       return array(
           // meta column id => sortby value used in query
           'title' => 'title',             
       );
   }


   add_filter( 'manage_edit-rssmi_feed_item_columns', 'rssmi_set_feed_item_custom_columns'); 
   /*
    Set up the custom columns for the wprss_feed source list

    */      
   function rssmi_set_feed_item_custom_columns( $columns ) {
 //rssmi_fetch_all_feed_items();
       $columns = array (
           'cb'          => '<input type="checkbox" />',
           'title'        => __( 'Name', 'rssmi' ),
           'permalink'   => __( 'Permalink', 'rssmi' ),
           'publishdate' => __( 'Date published', 'rssmi' ),
           'source'      => __( 'Source', 'rssmi' )
       );
       return $columns;
   }


/*
    Suppress action items on shortcode item list page
    */
function rssmi_remove_row_actions( $actions )
{

	global $pagenow;

    if( get_post_type() == 'rssmi_feed_item' && $pagenow !== 'edit.php'){
        unset( $actions['edit'] );
        unset( $actions['view'] );
        unset( $actions['inline hide-if-no-js'] );
    return $actions;
}
}
//add_filter( 'post_row_actions', 'rssmi_remove_row_actions', 10, 1 );


function rssmi_delete_bulk_actions($actions){
	if( get_post_type() === 'rssmi_feed_item' ){
       // unset( $actions['edit'] );
       // return $actions;
	 $actions = array(
	    'delete'    => 'Do Not Delete'
	  );
	  return $actions;
}
    }
add_filter('bulk_actions-{$this->screen->id}','rssmi_delete_bulk_actions');  //NOT WORKING








	add_action( "manage_rssmi_feed_item_posts_custom_column", "rssmi_show_feed_item_custom_columns", 10, 2 );
   /*
    Show up the custom columns for the wprss_feed list
    */  
   function rssmi_show_feed_item_custom_columns( $column, $post_id ) {

       switch ( $column ) {             
           case "permalink":
               $url = get_post_meta( $post_id, 'rssmi_item_permalink', true);
               echo '<a href="' . $url . '">' . $url. '</a>';
               break;         
           
           case "publishdate":

              $publishdate = date( 'Y-m-d H:i:s', intval(get_post_meta( get_the_ID(), 'rssmi_item_date', true ) )) ;   
		//	$publishdate = date( 'Y/m/d', intval(get_post_meta( get_the_ID(), 'rssmi_item_date', true ) )) ;          
               echo $publishdate;
               break;   
           
           case "source":        
               //$query = new WP_Query();                 
               $source = '<a href="' . get_edit_post_link( get_post_meta( $post_id, 'rssmi_feed_id', true ) ) . '">' . get_the_title( get_post_meta( $post_id, 'rssmi_feed_id', true ) ) . '</a>';                
               echo $source;
               break;   
       }
   }






	add_action( 'add_meta_boxes', 'rssmi_add_meta_boxes');
   /*
    Set up the input boxes for the rssmi_feed post type
    */   
   function rssmi_add_meta_boxes() {
       global $rssmi_meta_fields;

       // Remove the default WordPress Publish box, because we will be using custom ones
    remove_meta_box( 'submitdiv', 'rssmi_feed', 'side' );
    add_meta_box(
           'rssmi-save-link-side-meta',
           'Save Feed Source',
           'rssmi_save_feed_source_meta_box',
           'rssmi_feed',
           'side',
           'high'
       );

       
       add_meta_box(
           'custom_meta_box', // $id
           __( 'Feed Source Details', 'rssmi' ), // $title 
           'display_rssmi_feed_meta_box', // $callback
           'rssmi_feed', // $page
           'normal', // $context
           'high'); // $priority


       add_meta_box(
           'preview_meta_box', // $id
           __( 'Feed Preview', 'rssmi' ), // $title 
           'rssmi_preview_meta_box', // $callback
           'rssmi_feed', // $page
           'normal', // $context
           'low'); // $priority

		//  This adds the box with a direct link to the Add Multiple Feeds option
		/*	add_meta_box(
		           'rssmi-add-multiple',
		           'Add Multiple Feeds',
		           'rssmi_add_multiple_feeds_meta_box',
		           'rssmi_feed',
		           'normal',
		           'low'
		       );
		*/
   }    






function rssmi_add_multiple_feeds_meta_box(){
	
	echo "<a href=\"admin.php?page=wprssmi_options8\">Click here to add a bunch of feeds.</a>";
}








// Hide "add new" button on edit page
function hd_add_buttons() {
  global $pagenow;

  if(is_admin()){
	if($pagenow == 'edit.php' && $_GET['post_type'] == 'rssmi_feed_item'){
	echo '<style>.add-new-h2{display: none;}</style>';
	}
  }
}
add_action('admin_head','hd_add_buttons');


// Highlight plugin admin menu when on edit screen
function highlight_rssmi_menu(){
	$screen = get_current_screen();
	global $pagenow;
	global $parent_file;
	if($pagenow == 'post.php' && 'rssmi_feed' == $screen->post_type ){
		global $parent_file;
		$parent_file = 'wprssmi';
		echo '<style>#edit-slug-box{display: none;}</style>';
		
	}
}
add_action('admin_head','highlight_rssmi_menu');




//  give message that feed has been saved (instead of default post message)
add_filter('post_updated_messages', 'rssmi_updated_messages');
function rssmi_updated_messages( $messages ) {
	global $pagenow;
	$screen = get_current_screen();
		if($pagenow == 'post.php' && 'rssmi_feed' == $screen->post_type ){
			$messages["post"][6] = 'The feed has been successfully saved.';
		}
return $messages;
}


function change_default_title( $title ){
     $screen = get_current_screen();
 
     if  ( 'rssmi_feed' == $screen->post_type ) {
          $title = 'Give your feed a name - e.g., the site name for the source of the RSS feed';
     }
     return $title;
}
 
add_filter( 'enter_title_here', 'change_default_title' );





 function rssmi_save_feed_source_meta_box() {
        global $post;

        // insert nonce??

        echo '<input type="submit" name="publish" id="publish" class="button-primary" value="Save This Feed" tabindex="5" accesskey="s">';

        /**
         * Check if user has disabled trash, in that case he can only delete feed sources permanently,
         * else he can deactivate them. By default, if not modified in wp_config.php, EMPTY_TRASH_DAYS is set to 30.
         */
        if ( current_user_can( "delete_post", $post->ID ) ) {
            if ( ! EMPTY_TRASH_DAYS )
                $delete_text = __('Delete Permanently');
            else
                $delete_text = __('Move Feed to Trash');

        echo '&nbsp;&nbsp;<a class="submitdelete deletion" href="' . get_delete_post_link( $post->ID ) . '">' . $delete_text . '</a>';
        }
    }


	/**     
    * Set up the meta box for the wprss_feed post type
    * 
    * @since 2.0
    */ 
   function rssmi_show_meta_box() {
       global $post;
 
       // Use nonce for verification
       echo '<input type="hidden" name="rssmi_meta_box_nonce" value="' . wp_create_nonce( basename( __FILE__ ) ) . '" />';
           
           // Begin the field table and loop
           echo '<table class="form-table">';
           foreach ( $meta_fields as $field ) {
               // get value of this field if it exists for this post
               $meta = get_post_meta( $post->ID, $field['id'], true );
               // begin a table row with
               echo '<tr>
                       <th><label for="' . $field['id'] . '">' . $field['label'] . '</label></th>
                       <td>';
                       
                       switch( $field['type'] ) {
                       
                           // text
                           case 'text':
                               echo '<input type="text" name="'.$field['id'].'" id="'.$field['id'].'" value="'.$meta.'" size="55" />
                                   <br /><span class="description">'.$field['desc'].'</span>';
                           break;
                       
                           // textarea
                           case 'textarea':
                               echo '<textarea name="'.$field['id'].'" id="'.$field['id'].'" cols="60" rows="4">'.$meta.'</textarea>
                                   <br /><span class="description">'.$field['desc'].'</span>';
                           break;
                       
                           // checkbox
                           case 'checkbox':
                               echo '<input type="checkbox" name="'.$field['id'].'" id="'.$field['id'].'" ',$meta ? ' checked="checked"' : '','/>
                                   <label for="'.$field['id'].'">'.$field['desc'].'</label>';
                           break;    
                       
                           // select
                           case 'select':
                               echo '<select name="'.$field['id'].'" id="'.$field['id'].'">';
                               foreach ($field['options'] as $option) {
                                   echo '<option', $meta == $option['value'] ? ' selected="selected"' : '', ' value="'.$option['value'].'">'.$option['label'].'</option>';
                               }
                               echo '</select><br /><span class="description">'.$field['desc'].'</span>';
                           break;                                            
                       
                       } //end switch
               echo '</td></tr>';
           } // end foreach
           echo '</table>'; // end table
   }



	function rssmi_preview_meta_box() {

       global $post;
       $feed_url = get_post_meta( $post->ID, 'rssmi_url', true );

 
       if( ! empty( $feed_url ) ) {         
           $feed = wp_rss_fetchFeed( $feed_url ); 
           if ( !$feed->error()) {
               $items = $feed->get_items();    
			$feedCount=count($items);

               echo '<h4>Latest 5 feed items from ' . get_the_title() . '</h4>'; 
               $count = 0;
               $feedlimit = 5;
               foreach ( $items as $item ) { 
                   echo '<ul>';
                   echo '<li>' . $item->get_title() . '</li>';
                   echo '</ul>';
                   if( ++$count == $feedlimit ) break; //break if count is met
               } 
echo "THIS FEED HAS A TOTAL OF ".$feedCount." ITEMS.";
           }
           else echo "<strong>Invalid feed URL</strong> - Validate the feed source URL by <a href=\"http://feedvalidator.org/check?url=".$feed_url."\" target=\"_blank\">clicking here</a> and if the feed is valid then  <a href=\"http://www.allenweiss.com/faqs/im-told-the-feed-isnt-valid-or-working/\" target=\"_blank\">go here to learn more about what might be wrong</a>.";
       }

       else echo 'No feed URL defined yet';
   }



	function rssmi_check_url_status() {
     global $post;
     $feed_url = get_post_meta( $post->ID, 'rssmi_url', true );
	 $checkmark=WP_RSS_MULTI_IMAGES."check_mark.png";
	 $urlerror=WP_RSS_MULTI_IMAGES."error.png";
     if( ! empty( $feed_url ) ) {   
//	echo $feed_url;    
        $feed = wp_rss_fetchFeed( $feed_url ); 
       //  if ( ! is_wp_error( $feed ) ) {
	if (!$feed->error()){
				//	return "<span style=\"color:green\">OK</span>";
				return "<img src=$checkmark>";
			}else{
				return "<img src=$urlerror>";
				//	return "<span style=\"color:red\">NOT AVAILABLE</span>";
			}
    	}         
 	}





	add_action( 'add_meta_boxes', 'rssmi_remove_meta_boxes', 100 );
   /*
    Remove unneeded meta boxes from add feed source scre
    */       
   function rssmi_remove_meta_boxes() {
      // if ( 'rssmi_feed' !== get_current_screen()->id ) return;     
       remove_meta_box( 'sharing_meta', 'rssmi_feed' ,'advanced' );
       remove_meta_box( 'content-permissions-meta-box', 'rssmi_feed' ,'advanced' );
       remove_meta_box( 'wpseo_meta', 'rssmi_feed' ,'normal' );
       remove_meta_box( 'theme-layouts-post-meta-box', 'rssmi_feed' ,'side' );
       remove_meta_box( 'post-stylesheets', 'rssmi_feed' ,'side' );
       remove_meta_box( 'hybrid-core-post-template', 'rssmi_feed' ,'side' );
       remove_meta_box( 'trackbacksdiv22', 'rssmi_feed' ,'advanced' ); 
       remove_action( 'post_submitbox_start', 'fpp_post_submitbox_start_action' );
   
   }


   add_filter( 'gettext', 'rssmi_change_publish_button', 10, 2 );
   /**
    * Change 'Publish' button text
    */     
   function rssmi_change_publish_button( $translation, $text ) {
   if ( 'rssmi_feed' == get_post_type())
   if ( $text == 'Publish' )
       return 'Add Feed';

   return $translation;
   }



function my_admin() {
add_meta_box( 'rssmi_feed_meta_box',
'Feed Source Details',
'display_rssmi_feed_meta_box',
'rssmi_feeds', 'normal', 'high' );
}

function display_rssmi_feed_meta_box( $rssmi_feed ) {
 echo '<input type="hidden" name="rssmi_meta_box_nonce" value="' . wp_create_nonce( basename( __FILE__ ) ) . '" />';
$rssmi_url =
esc_html( get_post_meta( $rssmi_feed->ID,
'rssmi_url', true ) );
$rssmi_cat =
intval( get_post_meta( $rssmi_feed->ID,
'rssmi_cat', true ) );
$rssmi_user =
intval( get_post_meta( $rssmi_feed->ID,
'rssmi_user', true ) );
?>
<table>
<tr>
<td style="width: 20%">Feed URL</td>
<td><input type="text" size="120"
name="rssmi_url"
value="<?php echo $rssmi_url; ?>" /></td>
</tr>
<tr>
<td style="width: 20%">Feed Category</td>
<td>
<select style="width: 200px"
name="rssmi_cat">
	<OPTION selected VALUE=''>None</OPTION>
<?php
// Generate all items of drop-down list
$catOptions= get_option( 'rss_import_categories' ); 
if (!empty($catOptions)){
$catsize = count($catOptions);
for ( $k=1; $k<=$catsize; $k++) {  
	if( $k % 2== 0 ) continue;
	$catkey = key( $catOptions );
 	$nameValue=$catOptions[$catkey];
	next( $catOptions );
 	$catkey = key( $catOptions );
	$IDValue=$catOptions[$catkey]; 
?>
<option value="<?php echo $IDValue; ?>"
<?php echo selected( $IDValue,
$rssmi_cat ); ?>>
<?php echo $nameValue; ?> 
<?php
next( $catOptions );
}}?>
</select> <?php if (empty($catOptions)){echo 'If you added categories, you can assign this feed to a category. <a href="admin.php?page=wprssmi_options">Go here to set up categories.</a>';}?>
</td>
</tr>

<tr>
<td style="width: 20%">Blog User</td>
<td>
<select style="width: 200px"
name="rssmi_user">
<?php
$blogusers = get_users();
foreach ($blogusers as $user){
	?>
	<option value="<?php echo $user->ID; ?>"
	<?php echo selected( $user->ID,
	$rssmi_user ); ?>>
	<?php echo $user->display_name; ?> 
<?php	} ?>
	</select> (for use on AutoPost)
</table>
<?php }





function rssmi_custom_fields() {
    $prefix = 'rssmi_';
    
    $rssmi_meta_fields['url'] = array(
        'label' => __( 'URL', 'rssmi' ),
        'id'    => $prefix.'url'
    );
    $rssmi_meta_fields['cat'] = array(
        'label' => __( 'Category', 'rssmi' ),
        'id'    => $prefix.'cat'
    );  
	$rssmi_meta_fields['user'] = array(
        'label' => __( 'User', 'rssmi' ),
        'id'    => $prefix.'user'
    );  
	return $rssmi_meta_fields;
}




//add_action('delete_post', 'rssmi_trash_function');  //  Needs work

function rssmi_trash_function($post_id){
	wp_delete_post($post_id, true);
}

function rssmi_delete_prior_posts($post_id){
	 global $wpdb;
		$query = "SELECT post_id FROM $wpdb->postmeta WHERE meta_value = $post_id";
		$prior_posts =$wpdb->get_results($query);
		foreach ($prior_posts as $prior_post){
			wp_delete_post($prior_post->post_id, true);	
		}
}


add_action( 'save_post', 'rssmi_save_custom_fields' ); 
  
    function rssmi_save_custom_fields( $post_id ) {

	    $meta_fields = rssmi_custom_fields();
	
		$rssmi_nonce_var=(isset($_POST[ 'rssmi_meta_box_nonce' ]) ? $_POST[ 'rssmi_meta_box_nonce' ] :NULL);
        // verify nonce
      if ( ! wp_verify_nonce( $rssmi_nonce_var, basename( __FILE__ ) ) ) 
//if ( ! wp_verify_nonce ($_POST[ 'rssmi_meta_box_nonce' ], basename( __FILE__ ) ) ) 
            return $post_id;
        
        // check autosave
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE)
            return $post_id;
        
        // check permissions
        if ( 'page' == $_POST[ 'post_type' ] ) {
            if ( ! current_user_can( 'edit_page', $post_id ) )
                return $post_id;
            } elseif ( ! current_user_can( 'edit_post', $post_id ) ) {
                return $post_id;
        }

		// delete prior posts if this post_id url changes
		$oldURL = get_post_meta( $post_id, 'rssmi_url', true );
		$newURL=$_POST['rssmi_url' ];
		if($newURL && $newURL != $oldURL){
			rssmi_delete_prior_posts($post_id);	
		}

        // loop through fields and save the data
        foreach ( $meta_fields as $field ) {
            $old = get_post_meta( $post_id, $field[ 'id' ], true );
            $new = $_POST[ $field[ 'id' ] ];
			if ( $new && $new != $old ) {
               	update_post_meta( $post_id, $field[ 'id' ], $new );
            } elseif ( '' == $new && $old ) {
              	delete_post_meta( $post_id, $field[ 'id' ], $old );
            }
        } // end foreach
	
    }


add_action( 'before_delete_post', 'rssmi_delete_custom_fields');

	function rssmi_delete_custom_fields($postid){
		global $wpdb;
		$delete_array = $wpdb->get_results("SELECT post_id FROM $wpdb->postmeta WHERE  meta_value =$postid");
		foreach($delete_array as $delete_item){
			wp_delete_post($delete_item->post_id, true);
		}
	}


	add_filter("manage_edit-rssmi_feed_item_sortable_columns", 'rssmi_shortcode_sortable_columns');

	     
	       function rssmi_shortcode_sortable_columns($columns)
	  	{
	  	  $custom = array(
	            // meta column id => sortby value used in query
	  	    'publishdate'    => 'publishdate',
	  	  );

	  	  return wp_parse_args($custom, $columns);
	  	}	

		add_action( 'pre_get_posts', 'rssmi_feed_source_order' );
	   
	    function rssmi_feed_source_order( $query ) {
	        if( ! is_admin() )
	            return;
	        $post_type = $query->get('post_type');
	        if ( $post_type == 'rssmi_feed_item') { 
	            $query->set('orderby','publishdate');
	            $orderby = $query->get( 'orderby');
	            if( 'publishdate' == $orderby ) {
	                $query->set( 'meta_key', 'rssmi_item_date' );
	                $query->set( 'orderby', 'meta_value_num' );
	            }
	        }
	    }
		
	//  ADD THIS ONLY IF YOU DO NOT WANT SHORTCODE POSTS TO BE SEARCHABLE	
	
	//	add_filter('pre_get_posts', 'rssmi_search_filter');
		
		function rssmi_search_filter($query){
		if ( is_search() && !is_admin()){
			if ( $query->is_search ){
				$post_types=get_post_types();
				unset($post_types['rssmi_feed_item']);
				$query->set('post_type',$post_types); 
				return $query;
			}
		}	
		}
	
	//  This function makes searchable shortcode posts click directly to the feed source
		
		add_filter( 'the_title', 'rssmi_search_modified_post_title');  
		
		function rssmi_search_modified_post_title ($title) {
		  if (in_the_loop() && is_search() && !is_admin()) {	
			$post_options = get_option('rss_import_items'); 
			$targetWindow=$options['targetWindow'];  // 0=LB, 1=same, 2=new
				if($targetWindow==0){
					$openWindow='class="colorbox"';
				}elseif ($targetWindow==1){
					$openWindow='target=_self';		
				}else{
					$openWindow='target=_blank ';	
				}
			global $wp_query;
			$postID=$wp_query->post->ID;
			$myLink = get_post_meta( $postID, 'rssmi_item_permalink', true);
				if (!empty($myLink)){
					$myTitle=$wp_query->post->post_title;
					$myLinkTitle='<a href='.$myLink.' '.$openWindow.'>'.$myTitle.'</a>';  // change how the link opens here
				return $myLinkTitle;					
					}
		  }
		  return $title;
		}
	
?>