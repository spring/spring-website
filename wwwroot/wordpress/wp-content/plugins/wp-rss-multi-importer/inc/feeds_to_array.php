<?php
function get_my_array($myfeeds,$sortDir, $maxposts, $dumpthis)
{

	$directFetch=1;

	foreach($myfeeds as $feeditem)
	{
		
		$url=(string)($feeditem["FeedURL"]);
		while ( stristr($url, 'http') != $url )
		{	$url = substr($url, 1);	}
		
		if (empty($url)) {continue;}
		$url = esc_url_raw(strip_tags($url));

		if ($directFetch==1)
		{
			$feed = wp_rss_fetchFeed($url,$timeout,$forceFeed);
		}else{
			$feed = fetch_feed($url);
		}
 
		if (is_wp_error( $feed ) ) 
		{
			if (isset($dumpthis) && $dumpthis==1)
			{
				echo $feed->get_error_message();
			}	
				//echo $feed->get_error_message();	
				continue;
		
		}
		$maxfeed= $feed->get_item_quantity(0);  
		if ($feedAuthor = $feed->get_author())
		{
			$feedAuthor=$feed->get_author()->get_name();
		}
		
		//SORT DEPENDING ON SETTINGS

		if($sortDir==1)
		{
			for ($i=$maxfeed-1;$i>=$maxfeed-$maxposts;$i--)
			{
				$item = $feed->get_item($i);
				if (empty($item))	{	continue;	}
				
				if(include_post($feeditem["FeedCatID"],$item->get_content(),$item->get_title())==0) 
				{	
					continue;   // FILTER 	
				}
				
				if ($enclosure = $item->get_enclosure())
				{
					if(!IS_NULL($item->get_enclosure()->get_thumbnail()))
					{
						$mediaImage=$item->get_enclosure()->get_thumbnail();
					}
					else if (!IS_NULL($item->get_enclosure()->get_link()))
					{
						$mediaImage=$item->get_enclosure()->get_link();	
					}
				}
						
						
				if ($itemAuthor = $item->get_author())
				{
					$itemAuthor=$item->get_author()->get_name();
				}
				else if (!IS_NULL($feedAuthor))
				{
					$itemAuthor=$feedAuthor;
				}
				
				$myarray[] = array(
									"mystrdate"=>strtotime($item->get_date()),
									"mytitle"=>$item->get_title(),
									"mylink"=>$item->get_link(),
									"myGroup"=>$feeditem["FeedName"],
									"mydesc"=>$item->get_content(),
									"myimage"=>$mediaImage,
									"mycatid"=>$feeditem["FeedCatID"],
									"myAuthor"=>$itemAuthor,
									"bloguserid"=>$feeditem["FeedUser"]
								);
				unset($mediaImage);
				unset($itemAuthor);
			}
		}
		else
		{	
				
			for ($i=0;$i<=$maxposts-1;$i++)
			{
				$mediaImage='';
				$item = $feed->get_item($i);
				if (empty($item))	{	continue;		}
				if(include_post($feeditem["FeedCatID"],$item->get_content(),$item->get_title())==0)
				{	
					continue;   // FILTER 
				}
				
				if ($enclosure = $item->get_enclosure())
				{
					if(!IS_NULL($item->get_enclosure()->get_thumbnail()))
					{
						$mediaImage=$item->get_enclosure()->get_thumbnail();
					}
					else if (!IS_NULL($item->get_enclosure()->get_link()))
					{
						$mediaImage=$item->get_enclosure()->get_link();	
					}	
				}
				
				if ($itemAuthor = $item->get_author())
				{
					$itemAuthor=$item->get_author()->get_name();
				}
				else if (!IS_NULL($feedAuthor))
				{
					$itemAuthor=$feedAuthor;	
				}
				
				$myarray[] = array(
									"mystrdate"=>strtotime($item->get_date()),
									"mytitle"=>$item->get_title(),
									"mylink"=>$item->get_link(),
									"myGroup"=>$feeditem["FeedName"],
									"mydesc"=>$item->get_content(),
									"myimage"=>$mediaImage,
									"mycatid"=>$feeditem["FeedCatID"],
									"myAuthor"=>$itemAuthor,
									"bloguserid"=>(isset($feeditem["FeedUser"]) ? $feeditem["FeedUser"] : null)
									);
		
				unset($mediaImage);
				unset($itemAuthor);
			}	
		}
	}

	return $myarray;
}
?>