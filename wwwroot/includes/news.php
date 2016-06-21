<?php

include_once('includes/db.php');
include_once('includes/bbcode.php');
include_once('includes/cache.php');


function makelink($link, $str)
{
	return sprintf('<a href="%s">%s</a>', $link, $str);
}

function link_replace( $str ) {
	if ( !isset($_SERVER['HTTPS'] ) ) {
		return $str;
	}
	$str = str_replace("&#58;", ":", $str); //FIXME, bbcode parser error
	$str = str_replace("&#46;", ".", $str);
	$str = str_replace("http://springrts.com/", "/", $str);
	return str_replace("https://springrts.com/", "/", $str);
}

function get_news($db) {
	$sql = "SELECT t.topic_id, topic_poster, p.post_text, u.username, u.user_email, t.topic_time, t.topic_posts_approved, t.topic_title, t.forum_id\n"
	     . "FROM phpbb3_topics AS t, phpbb3_users AS u, phpbb3_posts AS p\n"
	     . "WHERE(t.forum_id = 2 or t.forum_id = 38) AND t.topic_poster = u.user_id AND t.topic_id = p.topic_id AND t.topic_time = p.post_time\n"
	     . "ORDER BY t.topic_time DESC\n"
	     . "LIMIT 10";

	$res = mysqli_query($db, $sql);
	$newstemplate = file_get_contents('templates/newsitem.html');
	$news = "";
	$newskeys = array('#HEADLINE#', '#BODY#', '#POSTER#', '#POSTDATE#', '#COMMENTS#');

	$i = 0;
	while ( $row = mysqli_fetch_array( $res ) ) {
		if ( $row['forum_id'] == 38 ) {
			if (preg_match('/^\[(game|map|engine|website|misc)\] (.*)$/', $row['topic_title'], $arr) === FALSE) {
				continue;
			}
			if ( sizeof($arr) == 0) {
				continue;
			}
			$title = $arr[1] . ": ".htmlspecialchars_decode($arr[2]);
		} else {
			$title = $row['topic_title'];
		}
		$posterlink = '/phpbb/memberlist.php?mode=viewprofile&amp;u=' . $row['topic_poster'];
		$postlink = '/phpbb/viewtopic.php?t=' . $row['topic_id'] ;
		$title = makelink($postlink, $title);
		$newstext = link_replace(parse_bbcode($row['post_text']));
		$poster = makelink($posterlink, $row['username']);
		$postdate = date("Y-m-d H:i", $row['topic_time']);
		$comments = makelink($postlink, ($row['topic_posts_approved'] - 1)  . ' comments');
		$newsdata = array( $title, $newstext, $poster, $postdate, $comments );
		$news .= str_replace( $newskeys, $newsdata, $newstemplate );
		$i++;
		if ( $i >= 5 ) {
			break;
		}
	}

	return $news;
}
