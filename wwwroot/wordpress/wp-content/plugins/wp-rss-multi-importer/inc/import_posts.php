<?php

$feedID = (isset( $_GET[ 'rssmi_feedID' ] ) ? $_GET['rssmi_feedID'] : NULL);
$catID = (isset( $_GET[ 'rssmi_catID' ] ) ? $_GET['rssmi_catID'] :  NULL);
if (!IS_NULL($feedID) || !IS_NULL($catID) ){
	if(! class_exists('SimplePie')){
	     		require_once(ABSPATH . WPINC . '/class-simplepie.php');
	}
	class SimplePie_RSSMI extends SimplePie {}	
	$post_options = get_option('rss_post_options');
	if($post_options['active']==1){
		if (!IS_NULL($feedID)){
			$result=wp_rss_multi_importer_post($feedID);  /// Used for external cron jobs	
	}else{
			$result=wp_rss_multi_importer_post($catID);	
	}
	
		if ($result==True){
			echo "success";
		}
			die();
	}
}




function deleteArticles(){

	
	global $wpdb;

  $mypostids = $wpdb->get_results("select * from $wpdb->postmeta where meta_key LIKE '%rssmi_source_link%");


    foreach( $mypostids as $mypost ) {
	
	//	delete_post_meta($mypost->ID, 'rssmi_source_link');
 

    }
}


function setFeaturedImage($post_id,$url,$featuredImageTitle){  
	
    // Download file to temp location and setup a fake $_FILE handler
    // with a new name based on the post_id
    $tmp_name = download_url( $url );
//								echo $tmp_name;
    $file_array['name'] = $post_id. '-thumb.jpg';  // new filename based on slug
    $file_array['tmp_name'] = $tmp_name;



    // If error storing temporarily, unlink
    if ( is_wp_error( $tmp_name ) ) {
        @unlink($file_array['tmp_name']);
        $file_array['tmp_name'] = '';
    }

    // do validation and storage .  Make a description based on the Post_ID
    $attachment_id = media_handle_sideload( $file_array, $post_id, 'Thumbnail for ' .$post_id);



    // If error storing permanently, unlink
    if ( is_wp_error($attachment_id) ) {
	$error_string = $attachment_id->get_error_message();
        @unlink($file_array['tmp_name']);
        return;
    }


    // Set as the post attachment
   $post_result= add_post_meta( $post_id, '_thumbnail_id', $attachment_id, true );

//					echo $post_result);
		
}






function rssmi_import_feed_post() {
	
	$post_options = get_option('rss_post_options');
	
	if($post_options['active']==1){
		wp_rss_multi_importer_post();
	}
}




add_action('wp_ajax_fetch_now', 'fetch_rss_callback');

function fetch_rss_callback() {


	$post_options = get_option('rss_post_options');

		if($post_options['active']==1){

		$result=wp_rss_multi_importer_post();
		
			if ($result===3){
					echo '<h3>There was a problem with fetching feeds.  This is likely due to a settings problem or invalid feeds.</h3>';
			
					
			}elseif ($result===4){
				echo '<h3>There were no new feed items to add.</h3>';
			
			}else{
	        	echo '<h3>The most recent valid feeds have been put into posts.</h3>';
				echo '<p>To check if any feeds are currently not working, click on the Feed List tab</p>';
			
	
			}
			
			
		}else{
			
	 		echo '<h3>Nothing was done because you have not activated this service.</h3>';
}

	die(); 
}



function rssmi_delete_feed_post_admin() {
rssmi_delete_posts_admin();
}



add_action('wp_ajax_fetch_delete', 'fetch_rss_callback_delete');

function fetch_rss_callback_delete() {

			rssmi_delete_feed_post_admin();
			echo '<h3>All posts have been deleted.</h3>';
			die();
}






function filter_id_callback2($val) {
    if ($val != null && $val !=99999){
	return true;
}
}

function filter_id_callback($val) {
	foreach($val as $thisval){
    if ($thisval != null){
	return true;
	}
}
}




function get_values_for_id_keys($mapping, $keys) {
    foreach($keys as $key) {
        $output_arr[] = $mapping[$key];
    }
    return $output_arr;
}


function strip_qs_var($sourcestr,$url,$key){
	if (strpos($url,$sourcestr)>0){
		return preg_replace( '/('.$key.'=.*?)&/', '', $url );
	}else{
		return $url;
	}		
}

$post_filter_options = get_option('rss_post_options');   // make title of post on listing page clickable
if(isset($post_filter_options['titleFilter']) && $post_filter_options['titleFilter']==1){
	add_filter( 'the_title', 'ta_modified_post_title');  
}else{
	remove_filter( 'the_title', 'ta_modified_post_title' );  
}



function ta_modified_post_title ($title) {
	$post_options = get_option('rss_post_options'); 
	$targetWindow=$post_options['targetWindow']; 
	if($targetWindow==0){
		$openWindow='class="colorbox"';
	}elseif ($targetWindow==1){
		$openWindow='target=_self';		
	}else{
		$openWindow='target=_blank ';	
	}
	
  if ( in_the_loop() && !is_page() ) {
	global $wp_query;
	$postID=$wp_query->post->ID;
	$myLink = get_post_meta($postID, 'rssmi_source_link' , true);
		if (!empty($myLink)){
			$myTitle=$wp_query->post->post_title;
			$myLinkTitle='<a href='.$myLink.' '.$openWindow.'>'.$myTitle.'</a>';  // change how the link opens here
		return $myLinkTitle;					
			}
  }
  return $title;
}



function isAllCat(){
$post_options = get_option('rss_post_options'); 
$catSize=count($post_options['categoryid']);

	for ( $l=1; $l<=$catSize; $l++ ){

		if($post_options['categoryid']['plugcatid'][$l]==0){
			
			$allCats[]= $post_options['categoryid']['wpcatid'][$l];
		}
}
return $allCats;
}




function getAllWPCats(){
	$category_ids = get_all_category_ids();
	foreach($category_ids as $cat_id) {
		if ($cat_id==1) continue;
 		$getAllWPCats[]=$cat_id;
	}
	return $getAllWPCats;
}






function wp_rss_multi_importer_post($feedID=NULL,$catID=NULL){
	
 	$postMsg = FALSE; 

	
require_once(ABSPATH . "wp-admin" . '/includes/media.php');
require_once(ABSPATH . "wp-admin" . '/includes/file.php');
require_once(ABSPATH . "wp-admin" . '/includes/image.php');

if(!function_exists("wprssmi_hourly_feed")) {
function wprssmi_hourly_feed() { return 0; }  // no caching of RSS feed
}
add_filter( 'wp_feed_cache_transient_lifetime', 'wprssmi_hourly_feed' );


  
   	$options = get_option('rss_import_options','option not found');
	$option_items = get_option('rss_import_items','option not found');
	$post_options = get_option('rss_post_options', 'option not found');
	$category_tags=get_option('rss_import_categories_images', 'option not found');
	
	global $fopenIsSet;
	$fopenIsSet = ini_get('allow_url_fopen');






if(!IS_NULL($feedID)){
	$feedIDArray=explode(",",$feedID);
}
 
   if(!empty($post_options)){

	
//GET PARAMETERS  
$size = count($option_items);
$sortDir=0;  // 1 is ascending
$maxperPage=$options['maxperPage'];
global $setFeaturedImage;
$setFeaturedImage=$post_options['setFeaturedImage'];
$addSource=(isset($post_options['addSource']) ? $post_options['addSource'] : null);	
$sourceAnchorText=$post_options['sourceAnchorText'];
$maxposts=$post_options['maxfeed'];
$post_status=$post_options['post_status'];
$addAuthor=(isset($post_options['addAuthor']) ? $post_options['addAuthor'] : null);	
$post_format=$post_options['post_format'];
$postTags=(isset($post_options['postTags']) ? $post_options['postTags'] : null);
global $RSSdefaultImage;
$RSSdefaultImage=$post_options['RSSdefaultImage'];   // 0- process normally, 1=use default for category, 2=replace when no image available
$serverTimezone=$post_options['timezone'];
$autoDelete=$post_options['autoDelete'];
$sourceWords=$post_options['sourceWords'];
$readMore=$post_options['readmore'];
$showVideo=$post_options['showVideo'];
$custom_type_name=$post_options['custom_type_name'];
$includeExcerpt=(isset($post_options['includeExcerpt']) ? $post_options['includeExcerpt'] : null);
global $morestyle;
$morestyle=' ...read more';
$sourceWords_Label=$post_options['sourceWords_Label'];

if (!is_null($readMore) && strlen($readMore)>0) {$morestyle=$readMore;} 

switch ($sourceWords) {
    case 1:
        $sourceLable='Source';
        break;
    case 2:
        $sourceLable='Via';
        break;
    case 3:
        $sourceLable='Read more here';
        break;
	case 4:
	    $sourceLable='From';
	    break;
	case 5:
		$sourceLable=$sourceWords_Label;
		break;
    default:
       	$sourceLable='Source';
}

if (isset($serverTimezone) && $serverTimezone!=''){  //set time zone
	date_default_timezone_set($serverTimezone);
	$rightNow=date("Y-m-d H:i:s", time());
}else{
	$rightNow=date("Y-m-d H:i:s", time());
}





if ($post_options['categoryid']['wpcatid'][1]!==NULL){
$wpcatids=array_filter($post_options['categoryid']['wpcatid'],'filter_id_callback'); //array of post blog categories that have been entered
}




if (!empty($wpcatids)){
	$catArray = get_values_for_id_keys($post_options['categoryid']['plugcatid'], array_keys($wpcatids));  //array of plugin categories that have an association with post blog categories
	$catArray=array_diff($catArray, array(''));
	
	


}else{
	$catArray=array(0);
	
}






if(!IS_NULL($catID)){
		$catArray=array($catID);  //  change to category ID if using external CRON
}



$targetWindow=$post_options['targetWindow'];  // 0=LB, 1=same, 2=new

if(empty($options['sourcename'])){
	$attribution='';
}else{
	$attribution=$options['sourcename'].': ';
}
global $ftp;
$ftp=1;  //identify pass to excerpt_functions comes from feed to post


global $maximgwidth;
$maximgwidth=$post_options['maximgwidth'];;
$descNum=$post_options['descnum'];
$stripAll=$post_options['stripAll'];

$stripSome=(isset($post_options['stripSome']) ? $post_options['stripSome'] : null);
$maxperfetch=$post_options['maxperfetch'];
$showsocial=(isset($post_options['showsocial']) ? $post_options['showsocial'] : null);
$overridedate=$post_options['overridedate'];
$commentStatus=(isset($post_options['commentstatus']) ? $post_options['commentstatus'] : null);
$noFollow=(isset($post_options['noFollow']) ? $post_options['noFollow'] : 0 );


if ($commentStatus=='1'){
	$comment_status='closed';
}else{
	$comment_status='open';	
}


$adjustImageSize=1;

$floatType=1;

if ($floatType=='1'){
	$float="left";
}else{
	$float="none";	
}



global $wpdb;
$myarray = array();
$feed_array=$wpdb->get_results("SELECT ID FROM $wpdb->posts WHERE  post_type ='rssmi_feed' AND post_status='publish'");



 //  ***  GET THE FEED URLS  ***
foreach ($feed_array as $feed){ 

	$feedlimit=0;
	$rssmi_cat= get_post_meta($feed->ID, 'rssmi_cat', true );
	$rssmi_url= get_post_meta($feed->ID, 'rssmi_url', true );
	$rssmi_user= get_post_meta($feed->ID, 'rssmi_user', true );
	$rssmi_title= get_the_title( $feed->ID); 
	$rssmi_feedID=$feed->ID;
	
//  *** MAKE SURE THEY ARE IN THE DESIRED CATEGORIES ***

if (((!in_array(0, $catArray ) && in_array(intval($rssmi_cat), $catArray ))) || in_array(0, $catArray ) || !EMPTY($feedIDArray)) {  


if (!EMPTY($feedIDArray)){	//only pick up specific feed arrary if indicated in querystring
	if (!in_array($rssmi_feedID, $feedIDArray )) {
		continue;
	}
}

	$myfeeds[] = array("FeedName"=>$rssmi_title,"FeedURL"=>$rssmi_url,"FeedCatID"=>$rssmi_cat, "FeedUser"=>$rssmi_user); 


}
   
}


	$dumpthis = (isset($dumpthis) ? $dumpthis : null);

//$myarray=get_my_array($myfeeds,$sortDir,$maxposts, $dumpthis,$source);

//  *** GET THE THE FEED ITEMS FOR EACH OF THE FEED URLS ***

$myarray=get_my_array($myfeeds,$sortDir,$maxposts, $dumpthis);  

if (is_integer($myarray)) return $myarray;  // RETURNS ERROR CODE IF PRESENT



//  CHECK $myarray BEFORE DOING ANYTHING ELSE //

if (isset($dumpthis) && $dumpthis==1){
	var_dump($myarray);
}
if (!isset($myarray) || empty($myarray)){
	return 3;
	exit;
}

//$myarrary sorted by mystrdate

foreach ($myarray as $key => $row) {
    $dates[$key]  = $row["mystrdate"]; 
}



//SORT, DEPENDING ON SETTINGS

if($sortDir==1){
	array_multisort($dates, SORT_ASC, $myarray);
}else{
	array_multisort($dates, SORT_DESC, $myarray);		
}



if($targetWindow==0){
	$openWindow='class="colorbox"';
}elseif ($targetWindow==1){
	$openWindow='target=_self';		
}else{
	$openWindow='target=_blank ';	
}

	$total=0;
	$added=0;


global $wpdb; // get all links that have been previously processed

$wpdb->show_errors = true;



foreach($myarray as $items) {
	
	$total = $total +1;
	if ($total>$maxperfetch) break;
	$thisLink=trim($items["mylink"]);
//	echo $thisLink.'<br>';
	
	
	// VIDEO CHECK
	if ($targetWindow==0){
		$getVideoArray=rssmi_video($items["mylink"]);
		$openWindow=$getVideoArray[1];
		$items["mylink"]=$getVideoArray[0];
		$vt=$getVideoArray[2];
	}
	

	
	
	$thisLink = strip_qs_var('bing.com',$thisLink,'tid');  // clean time based links from Bing
	
	$thisLink=mysql_real_escape_string($thisLink);
	
	

			$wpdb->flush();
			$mypostids = $wpdb->get_results("select post_id from $wpdb->postmeta where meta_key = 'rssmi_source_link' and meta_value like '%".$thisLink."%'");
		
			$myposttitle=$wpdb->get_results("select post_title from $wpdb->posts where post_type!='rssmi_feed_item' and post_title like '%".mysql_real_escape_string(trim($items["mytitle"]))."%'");
			
		if ((empty( $mypostids ) && $mypostids !== false) && empty($myposttitle)){ 
		
			
			$added=$added=1;
			$thisContent='';
  			$post = array();  

  			$post['post_status'] = $post_status;



	if ($overridedate==1){
		$post['post_date'] = $rightNow;  	
	}else{
  		$post['post_date'] = date('Y-m-d H:i:s',$items['mystrdate']);
	}




	$post['post_title'] = trim($items["mytitle"]);



	$authorPrep="By ";

		if(!empty($items["myAuthor"]) && $addAuthor==1){
		 	$thisContent .=  '<span style="font-style:italic; font-size:16px;">'.$authorPrep.' <a '.$openWindow.' href='.$items["mylink"].' '.($noFollow==1 ? 'rel=nofollow':'').'">'.$items["myAuthor"].'</a></span>  ';  
			}

	
	$thisExcerpt = showexcerpt($items["mydesc"],$descNum,$openWindow,$stripAll,$items["mylink"],$adjustImageSize,$float,$noFollow,$items["myimage"],$items["mycatid"],$stripSome);
	
	
	if ((strpos($items["mylink"],'www.youtube.com')>0 || strpos($items["mylink"],'player.vimeo')>0 ) && $showVideo==1){
		
		if ($vt=='yt'){
			$thisExcerpt = rssmi_yt_video_content($items["mydesc"])."<br>";
		}else if ($vt=='vm'){
			$thisExcerpt = rssmi_vimeo_video_content($items["mydesc"])."<br>";
		}

	//	$thisContent.="\r\n".$orig_video_link."\r\n";

		$thisExcerpt .= '<iframe title=".$items["mytitle"]." width="420" height="315" src="'.$items["mylink"].'" frameborder="0" allowfullscreen allowTransparency="true"></iframe>';
	}
	

$thisContent .= $thisExcerpt;




	if ($addSource==1){
		
		
		switch ($sourceAnchorText) {
		    case 1:
		        $anchorText=$items["myGroup"];
		        break;
		    case 2:
		        $anchorText=$items["mytitle"];
		        break;
		    case 3:
		        $anchorText=$items["mylink"];
		        break;
		    default:
		        $anchorText=$items["myGroup"];
		}	
		
	$thisContent .= ' <p>'.$sourceLable.': <a href='.$items["mylink"].'  '.$openWindow.'  title="'.$items["mytitle"].'" '.($noFollow==1 ? 'rel=nofollow':'').'>'.$anchorText.'</a></p>';
	}


	
	if ($showsocial==1){
	$thisContent .= '<span style="margin-left:10px;"><a href="http://www.facebook.com/sharer/sharer.php?u='.$items["mylink"].'"><img src="'.WP_RSS_MULTI_IMAGES.'facebook.png"/></a>&nbsp;&nbsp;<a href="http://twitter.com/intent/tweet?text='.rawurlencode($items["mytitle"]).'%20'.$items["mylink"].'"><img src="'.WP_RSS_MULTI_IMAGES.'twitter.png"/></a>&nbsp;&nbsp;<a href="http://plus.google.com/share?url='.rawurlencode($items["mylink"]).'"><img src="'.WP_RSS_MULTI_IMAGES.'gplus.png"/></a></span>';
	}
	
  	$post['post_content'] = $thisContent;


	if ($includeExcerpt==1){
		$post['post_excerpt'] = $thisExcerpt;
	}

	$mycatid=$items["mycatid"];
	
	
	$blogcatid=array();
	
	if (!empty($post_options['categoryid']['plugcatid'])){
		$catkey=array_search($mycatid, $post_options['categoryid']['plugcatid']);
		
		$blogcatid = (isset($post_options['categoryid']['wpcatid'][$catkey]) ? $post_options['categoryid']['wpcatid'][$catkey] : 0);
		//$blogcatid=$post_options['categoryid']['wpcatid'][$catkey];
	}else{
		$blogcatid=0;
	}
	
	
	if ($post_options['categoryid']['plugcatid'][1]=='0'){   //this gets all the wp categories indicated when All is chosen in the first position
		$allblogcatid=$post_options['categoryid']['wpcatid'][1];
			if (is_array($blogcatid)){
				$blogcatid=array_merge ($blogcatid,$allblogcatid);
				$blogcatid = array_unique($blogcatid);
			}else{
				$blogcatid=$allblogcatid;
			}
	}



	$post['post_category'] =$blogcatid;
	
	
	if(is_null($items["bloguserid"])){
		if (is_null($bloguserid) || empty($bloguserid)){$bloguserid=1;}  //check that userid isn't empty else give it admin status
	}else{
		$bloguserid=$items["bloguserid"];
	}
	
	$post['post_author'] =$bloguserid;
	

	
	$post['comment_status'] = $comment_status;
	
	if (!empty($category_tags[$mycatid]['tags'])) {	
		$postTags=$category_tags[$mycatid]['tags'];
	}else{
		$postTags='';
	}

	if($postTags!=''){
		$post['tags_input'] =$postTags;
	}
	
	if($custom_type_name!=''){
		$post['post_type'] =$custom_type_name;
	}

 	$post_id = wp_insert_post($post);
	set_post_format( $post_id , $post_format);
	
	if(add_post_meta($post_id, 'rssmi_source_link', $thisLink)!=false){
	
	

	if ($setFeaturedImage==1 || $setFeaturedImage==2){
		global $featuredImage;
			if (isset($featuredImage)){
				$featuredImageTitle=trim($items["mytitle"]);	
				setFeaturedImage($post_id,$featuredImage,$featuredImageTitle);
				unset($featuredImage);
				}
			}
			
	}else{
		
	wp_delete_post($post_id, true);
	unset($post);
	continue;	
		
		
	}

	unset($post);

}


}
if ($added==0) return 4;
	$postMsg = TRUE; 
}
if ($autoDelete==1){
	rssmi_delete_posts();
}

return $postMsg;

  }

?>