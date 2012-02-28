<?php

include_once('includes/db.php');
include_once('includes/bbcode.php');
include_once('includes/cache.php');

function get_news()
{
	$sql = "";
	$sql .= "SELECT t.topic_id, topic_poster, p.post_text, u.username, u.user_email, t.topic_time, t.topic_replies, t.topic_title ";
	$sql .= "FROM phpbb3_topics AS t, phpbb3_users AS u, phpbb3_posts AS p ";
	$sql .= "WHERE t.forum_id = 2 AND t.topic_poster = u.user_id AND t.topic_id = p.topic_id AND t.topic_time = p.post_time ";
	$sql .= "ORDER  BY t.topic_time DESC ";
	$sql .= "LIMIT 3";

	$res = mysql_query($sql);
	$newstemplate = file_get_contents('templates/newsitem.html');
	$news = "";
	$newskeys = array('#HEADLINE#', '#BODY#', '#POSTER#', '#POSTDATE#', '#COMMENTS#');

	while ($row = mysql_fetch_array($res))
	{
		$newstext = parse_bbcode($row['post_text']);
		$poster = '<a href="/phpbb/memberlist.php?mode=viewprofile&amp;u=' . $row['topic_poster'] . '">' . $row['username'] . '</a>';
		$postdate = date("Y-m-d H:i", $row['topic_time']);
		$comments = '<a href="/phpbb/viewtopic.php?t=' . $row['topic_id'] . '">' . $row['topic_replies'] . ' comments</a>.';
		$newsdata = array($row['topic_title'], $newstext, $poster, $postdate, $comments);
		$news .= str_replace($newskeys, $newsdata, $newstemplate);
	}

	return $news;
}

function get_news_from_feed($feedurl)
{
	//downloads + parses a rss 2.0 feed
	$xml = cached_file_get_contents($feedurl);
	try {
		libxml_use_internal_errors(true);
		$xml = new SimpleXMLElement($xml);
	}
	catch (Exception $ex) {
		$xml = false;
	}
	$cnewstemplate = file_get_contents('templates/cnewsitem.html');
	$cnews = "";
	$cnewskeys = array('#HEADLINE#', '#LINK#');

	if ($xml)
	{
		$count = 1;
		foreach ($xml->xpath('/rss/channel/item') as $item)
		{
			$newsdata = array((string) $item->title, (string) $item->link);
			$cnews .= str_replace($cnewskeys, $newsdata, $cnewstemplate);
			if($count>=20) break; //limit to 20 news items
			$count++;
		}
	}
	if ($cnews == '')
	{
		// hack to hide community news altogether if an error occurs
		$cnews = "<style>.cnews{display:none}</style>";
	}

	return $cnews;
}

function get_community_news()
{
	return get_news_from_feed('http://feeds.feedburner.com/SpringCommunityHeadlines');
}

function get_forum_posts()
{
	return get_news_from_feed('http://' . $_SERVER['SERVER_NAME'] . '/phpbb/newposts.php');
}

?>

