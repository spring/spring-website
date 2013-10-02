<?php
/*
The code on this page was adapted and expanded from the WP RSS Aggregator by jeangalea
*/

function rssmi_fetch_all_feed_items( ) {            
       
	   $directFetch=0; 
        // Get all feed sources
        $feed_sources = new WP_Query( array(
            'post_type'      => 'rssmi_feed',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
        ) );

        if( $feed_sources->have_posts() ) {
            /* Start by getting one feed source, we will cycle through them one by one, 
               fetching feed items and adding them to the database in each pass */
            while ( $feed_sources->have_posts() ) {                
                $feed_sources->the_post();
             
                $feed_ID = get_the_ID();
                $feed_url = get_post_meta( get_the_ID(), 'rssmi_url', true );
				$feed_cat = get_post_meta( get_the_ID(), 'rssmi_cat', true );
                    
                // Use the URL custom field to fetch the feed items for this source
                if( !empty( $feed_url ) ) {    
					
							$url = esc_url_raw(strip_tags($feed_url));
	
							if ($directFetch==1){
								$feed = wp_rss_fetchFeed($url,$timeout,$forceFeed);
							}else{
								$feed = fetch_feed($url);
							}
					
					         
                    if ( !is_wp_error( $feed ) ) {
                        // Figure out how many total items there are, but limit it to 10. 
                        $maxitems = $feed->get_item_quantity(10); 

                        // Build an array of all the items, starting with element 0 (first element).
                        $items = $feed->get_items( 0, $maxitems );   
                    }
                }

                if ( ! empty( $items ) ) {
                    // Gather the permalinks of existing feed item's related to this feed source
                    global $wpdb;
                    $existing_permalinks = $wpdb->get_col(
                        "SELECT meta_value
                        FROM $wpdb->postmeta
                        WHERE meta_key = 'rssmi_item_permalink'
                        AND post_id IN ( SELECT post_id FROM $wpdb->postmeta WHERE meta_value = $feed_ID)
                        ");

                    foreach ( $items as $item ) {

                        // Check if newly fetched item already present in existing feed item item, 
                        // if not insert it into wp_posts and insert post meta.
                        if (  ! ( in_array( $item->get_permalink(), $existing_permalinks ) )  ) { 
                            // Create post object
                            $feed_item = array(
                                'post_title' => $item->get_title(),
                                'post_content' => '',
                                'post_status' => 'publish',
                                'post_type' => 'rssmi_feed_item'
                            );                
                            $inserted_ID = wp_insert_post( $feed_item, $wp_error );
							
							if ($feedAuthor = $item->get_author())
							{
								$feedAuthor=$item->get_author()->get_name();
							}
							
					
									if ($enclosure = $item->get_enclosure()){
										if(!IS_NULL($item->get_enclosure()->get_thumbnail())){			
											$mediaImage=$item->get_enclosure()->get_thumbnail();
										}else if (!IS_NULL($item->get_enclosure()->get_link())){
											$mediaImage=$item->get_enclosure()->get_link();	
										}
									}
						
						
									if ($itemAuthor = $item->get_author())
									{
										$itemAuthor=$item->get_author()->get_name();
									}else if (!IS_NULL($feedAuthor)){
										$itemAuthor=$feedAuthor;
							
									}
						
						$myarray[]=array(
							"mystrdate"=>strtotime($item->get_date()),
							"mytitle"=>$item->get_title(),
							"mylink"=>$item->get_link(),
							"mydesc"=>$item->get_content(),
							"myimage"=>$mediaImage,
							"myAuthor"=>$itemAuthor
							);
				
									unset($mediaImage);
									unset($itemAuthor);
							
                           update_post_meta( $inserted_ID, 'rssmi_item_permalink', $item->get_permalink() );
                            update_post_meta( $inserted_ID, 'rssmi_item_description', $myarray );                        
                            update_post_meta( $inserted_ID, 'rssmi_item_date', $item->get_date( 'U' ) ); // Save as Unix timestamp format
                            update_post_meta( $inserted_ID, 'rssmi_feed_id', $feed_ID);
							unset($myarray);
                       } //end if
                    } //end foreach
                } // end if
            } // end $feed_sources while loop
            wp_reset_postdata(); // Restore the $post global to the current post in the main query        
       // } // end if
    } // end if

} 


add_action('wp_insert_post', 'rssmi_fetch_feed_items'); 
/**
 * Fetches feed items from post 
 */
function rssmi_fetch_feed_items( $post_id ) {        
	  
    $directFetch=0; 
 	global $wpdb;
   
    $post = get_post( $post_id );
     
    if( ( $post->post_type == 'rssmi_feed' ) && ( $post->post_status == 'publish' ) ) { 
        // Get the feed source

		$query = "SELECT ID, post_date, post_title, guid FROM $wpdb->posts WHERE post_status = 'publish' AND post_type = 'rssmi_feed' AND ID = $post_id";
		$feed_source =$wpdb->get_results($query);
           
        if( !empty($feed_source)) {
                 
                $feed_ID = $post_id;
                $feed_url = get_post_meta( $post_id, 'rssmi_url', true );
 
                if( !empty( $feed_url ) ) {             
                    
                    $feed = fetch_feed( $feed_url );
                    
                    if ( !is_wp_error( $feed ) ) {
                        // Figure out how many total items there are, but limit it to 10. 
                        $maxitems = $feed->get_item_quantity(10); 

                        // Build an array of all the items, starting with element 0 (first element).
                        $items = $feed->get_items( 0, $maxitems );   
                    }
                    else { return; }
                }       

                if ( ! empty( $items ) ) {
                    // Gather the permalinks of existing feed item's related to this feed source
                    $existing_permalinks = $wpdb->get_col(
                        "SELECT meta_value
                        FROM $wpdb->postmeta
                        WHERE meta_key = 'rssmi_item_permalink'
                        AND post_id IN ( SELECT post_id FROM $wpdb->postmeta WHERE meta_value = $feed_ID)
                        ");

                    foreach ( $items as $item ) {

                        // Check if newly fetched item already present in existing feed item item, 
                        // if not insert it into wp_posts and insert post meta.
                        if (  ! ( in_array( $item->get_permalink(), $existing_permalinks ) )  ) { 
                            // Create post object
                            $feed_item = array(
                                'post_title' => $item->get_title(),
                                'post_content' => '',
                                'post_status' => 'publish',
                                'post_type' => 'rssmi_feed_item'
                            ); 
               				remove_action('save_post', 'rssmi_save_custom_fields');
                            $inserted_ID = wp_insert_post( $feed_item );
							add_action( 'save_post', 'rssmi_save_custom_fields' ); 
  							if ($feedAuthor = $item->get_author())
								{
									$feedAuthor=$item->get_author()->get_name();
								}
										if ($enclosure = $item->get_enclosure()){
											if(!IS_NULL($item->get_enclosure()->get_thumbnail())){			
												$mediaImage=$item->get_enclosure()->get_thumbnail();
											}else if (!IS_NULL($item->get_enclosure()->get_link())){
												$mediaImage=$item->get_enclosure()->get_link();	
											}
										}

										if ($itemAuthor = $item->get_author())
										{
											$itemAuthor=$item->get_author()->get_name();
										}else if (!IS_NULL($feedAuthor)){
											$itemAuthor=$feedAuthor;
										}

							$myarray[]=array(
								"mystrdate"=>strtotime($item->get_date()),
								"mytitle"=>$item->get_title(),
								"mylink"=>$item->get_link(),
								"mydesc"=>$item->get_content(),
								"myimage"=>$mediaImage,
								"myAuthor"=>$itemAuthor
								);
										unset($mediaImage);
										unset($itemAuthor);

	                            update_post_meta( $inserted_ID, 'rssmi_item_permalink', $item->get_permalink() );
	                            update_post_meta( $inserted_ID, 'rssmi_item_description', $myarray );                        
	                            update_post_meta( $inserted_ID, 'rssmi_item_date', $item->get_date( 'U' ) ); // Save as Unix timestamp format
	                            update_post_meta( $inserted_ID, 'rssmi_feed_id', $feed_ID);
								unset($myarray);

           
                       } //end if
                    } //end foreach
                } // end if
            } // end if not empty
    } // end if
} // end 





?>