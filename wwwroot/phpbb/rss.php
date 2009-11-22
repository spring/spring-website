<?php
/**
* @package: phpBB3 :: RSS feed 2.0
* @version: $Id: rss.php, v 1.0.9 2009/02/20 09:02:20 leviatan21 Exp $
* @copyright: leviatan21 < info@mssti.com > (Gabriel) http://www.mssti.com/phpbb3/
* @license: http://opensource.org/licenses/gpl-license.php GNU Public License
* @author: leviatan21 - http://www.phpbb.com/community/memberlist.php?mode=viewprofile&u=345763
*
**/

/**
* @ignore
* http://www.uatsap.com/rss/manual/6
* http://blogs.law.harvard.edu/tech/rss
**/

define('IN_PHPBB', true);
define('RSS_DEBUG_MODE', false);
define('RSS_DEBUG_SQL', false);

$phpbb_root_path = (defined('PHPBB_ROOT_PATH')) ? PHPBB_ROOT_PATH : './';
$phpEx = substr(strrchr(__FILE__, '.'), 1);
include($phpbb_root_path . 'common.' . $phpEx);

// Start session
$user->session_begin();

/** FIX user - Start **/
/** 
$user_id	= request_var('uid', 0);
if ( $user_id != 0 )
{
	$user->session_create($user_id);
}
**/
/** FIX user - END **/

$auth->acl($user->data);
$user->setup();
$user->add_lang( array('common', 'acp/common', 'mods/rss') );

// Initial var setup
$starttime = explode(' ', microtime());
$starttime = $starttime[1] + $starttime[0];

$board_url	= generate_board_url();

$rss_f_id	= request_var('f', 0);
$rss_t_id	= request_var('t', 0);
$rss_mode	= request_var('mode', '');
$feed_mode	= empty($rss_mode) ? '' : $rss_mode ;

// Flood limits
$text_limit = $config['rss_characters'];
$show_text	= ( $text_limit == 1 ) ? false : true;

// Pagination
$start		= max(request_var('start', 0), 0);
$u_rss		= 'rss.' . $phpEx . ( empty($rss_mode) ? '' : '?mode='.$rss_mode ) . ( empty($rss_f_id) ? '' : '?f='.$rss_f_id ) . ( empty($rss_t_id) ? '' : ( empty($rss_f_id) ? '?t='.$rss_t_id : '&amp;t='.$rss_t_id ) );
$per_page	= $config['rss_limit'];
$total_count= 0;

// Query default
$sql		= '';
$sql_array = array(
	'SELECT'	=> '',
	'FROM'		=> array(),
	'LEFT_JOIN'	=> array(),
	'WHERE'		=> '',
);

// The SQL query selects the latest topics of all forum
switch ($rss_mode)
{
	case 'forums':
		// Check if this option is enabled
		if ( $config['rss_overall_forums'] )
		{
			// This option is forced here, only for a specific user request
			$config['rss_forums_topics'] = true;

			$row_title		= 'forum_name';
			$row_creator	= 'forum_last_poster_id';
			$row_username	= 'forum_last_poster_name';
			$row_text		= 'forum_desc';
			$row_bit		= 'forum_desc_bitfield';
			$row_uid		= 'forum_desc_uid';
			$row_date		= 'forum_last_post_time';

			$sql_array['SELECT'] = 'f.forum_id, f.forum_password, f.forum_topics, f.forum_posts, f.forum_name, f.forum_last_poster_id, f.forum_last_poster_name, f.forum_desc, f.forum_desc_bitfield, f.forum_desc_uid, f.forum_last_post_time, f.parent_id, f.left_id, f.right_id ';
			$sql_array['FROM'][FORUMS_TABLE] = 'f';
			$sql_array['WHERE'] = 'f.forum_type = ' . FORUM_POST . ' AND f.forum_last_post_id > 0 ';
			$sql_array['ORDER_BY'] = 'f.left_id';
		}
		break;

	case 'topics':
		// Check if this option is enabled
		if ( $config['rss_overall_threads'] )
		{
			$per_page		= $config['rss_overall_threads_limit'];

			$row_title		= 'topic_title';
			$row_title2		= 'forum_name';
			$row_creator	= 'topic_poster';
			$row_text		= 'post_text';
			$row_bit		= 'bbcode_bitfield';
			$row_uid		= 'bbcode_uid';
			$row_date		= 'topic_time';

			$sql_array['SELECT'] = 'f.forum_id, f.forum_password, f.forum_name, f.forum_topics, f.forum_posts, f.parent_id, f.left_id, f.right_id';
			$sql_array['SELECT'].= ', t.topic_id, t.topic_title, t.topic_poster, t.topic_first_poster_name, t.topic_replies, t.topic_views, t.topic_time';
			$sql_array['SELECT'].= ', p.post_id, p.post_text, p.bbcode_bitfield, p.bbcode_uid, p.post_attachment';
			$sql_array['FROM'][FORUMS_TABLE] = 'f';
			$sql_array['FROM'][TOPICS_TABLE] = 't';
			$sql_array['FROM'][POSTS_TABLE]  = 'p';
			$sql_array['WHERE'] = 't.topic_approved = 1 ';
			$sql_array['WHERE'].= ' AND ( f.forum_id = t.forum_id ';
			$sql_array['WHERE'].= ' AND p.post_id = t.topic_first_post_id ) ';
			$sql_array['ORDER_BY'] = 't.topic_last_post_time DESC';
		}
		break;

	// Force the feeds to read specified forums ?
	case 'news':
		// Check if this option is enabled
		if ( $config['rss_news_id'] !== '' )
		{
			$per_page		= $config['rss_overall_forums_limit'];

			$include_forums = explode(",", $config['rss_news_id'] );

			$row_title		= 'topic_title';
			$row_title2		= 'forum_name';
			$row_creator	= 'topic_poster';
			$row_text		= 'post_text';
			$row_bit		= 'bbcode_bitfield';
			$row_uid		= 'bbcode_uid';
			$row_date		= 'topic_time';

			$sql_array['SELECT'] = 'f.forum_id, f.forum_password, f.forum_name, f.forum_topics, f.forum_posts, f.parent_id, f.left_id, f.right_id';
			$sql_array['SELECT'].= ', t.topic_id, t.topic_title, t.topic_poster, t.topic_first_poster_name, t.topic_replies, t.topic_views, t.topic_time';
			$sql_array['SELECT'].= ', p.post_id, p.post_text, p.bbcode_bitfield, p.bbcode_uid, p.post_attachment';
			$sql_array['FROM'][FORUMS_TABLE] = 'f';
			$sql_array['FROM'][TOPICS_TABLE] = 't';
			$sql_array['FROM'][POSTS_TABLE]  = 'p';
			$sql_array['WHERE'] = $db->sql_in_set('t.forum_id', $include_forums);
			$sql_array['WHERE'].= ' AND ( f.forum_id = t.forum_id ';
			$sql_array['WHERE'].= ' AND p.post_id = t.topic_first_post_id ) ';
			$sql_array['ORDER_BY'] = 't.topic_last_post_time DESC';
		}
		break;

	case 'posts':
		// Check if this option is enabled
		if ( $config['rss_overall_posts'] )
		{
			$per_page		= $config['rss_overall_posts_limit'];

			$row_title		= 'post_subject';
			$row_title2		= 'forum_name';
			$row_creator	= 'poster_id';
			$row_text		= 'post_text';
			$row_bit		= 'bbcode_bitfield';
			$row_uid		= 'bbcode_uid';
			$row_date		= 'post_time';

			$sql_array['SELECT'] = 'f.forum_id, f.forum_password, f.forum_name, f.parent_id, f.left_id, f.right_id';
			$sql_array['SELECT'].= ', p.post_id, p.poster_id, p.post_time, p.post_subject, p.post_text, p.bbcode_bitfield, p.bbcode_uid, p.post_attachment';
			$sql_array['FROM'][FORUMS_TABLE] = 'f';
			$sql_array['FROM'][POSTS_TABLE]  = 'p';
			$sql_array['WHERE'] = 'p.post_approved = 1 ';
			$sql_array['WHERE'].= ' AND f.forum_id = p.forum_id ';
			$sql_array['ORDER_BY'] = 'p.post_time DESC';
		}
		break;

	case 'egosearch':
		if ( $config['rss_egosearch'] )
		{
			//check logged on
			if ( $user->data['user_id'] == ANONYMOUS )
			{
				trigger_error($user->lang['NO_RSS_ITEMS'] . '<p>' . sprintf($user->lang['NO_RSS_ITEMS_LOGGED_IN'], $config['sitename'] ) . '</p>');
			}
			$per_page		= $config['rss_egosearch_limit'];

			$row_title		= 'post_subject';
			$row_title2		= 'forum_name';
			$row_creator	= 'poster_id';
			$row_text		= 'post_text';
			$row_bit		= 'bbcode_bitfield';
			$row_uid		= 'bbcode_uid';
			$row_date		= 'post_time';

			$sql_array['SELECT'] = 'f.forum_id, f.forum_password, f.forum_name, f.left_id, f.right_id, f.left_id, f.right_id';
			$sql_array['SELECT'].= ', p.post_id, p.poster_id, p.post_time, p.post_subject, p.post_text, p.bbcode_bitfield, p.bbcode_uid, p.post_attachment';
			$sql_array['FROM'][FORUMS_TABLE] = 'f';
			$sql_array['FROM'][POSTS_TABLE]  = 'p';
			$sql_array['WHERE'] = 'p.poster_id =' . $user->data['user_id'] . ' AND p.post_approved = 1 ';
			$sql_array['WHERE'].= ' AND f.forum_id = p.forum_id ';
			$sql_array['ORDER_BY'] = 'p.post_time DESC';
		}
		break;

	default:
		// Check if this option is enabled
		if ( $config['rss_enable'] || $config['rss_forum'] || $config['rss_thread'] )
		{
			$last_post_time_sql = '';
			$forum_sql	= '';
			$topic_sql	= '';
			$order_sql	= '';
			$group_by	= 'p.post_id';

			$row_title		= 'post_subject';
			$row_title2		= 'topic_title';
			$row_creator	= 'poster_id';
			$row_text		= 'post_text';
			$row_bit		= 'bbcode_bitfield';
			$row_uid		= 'bbcode_uid';
			$row_date		= 'post_time';

			// Check if this option is enabled
			if ( !$config['rss_forum'] && $rss_f_id != 0 && $rss_t_id == 0 )
			{
				break;
			}

//			$forum_sql = ($rss_f_id == 0) ? '' : " AND ( f.forum_id = $rss_f_id OR sf.forum_id = $rss_f_id )";
			if ( $rss_f_id != 0 )
			{
				$forum_sql = " AND ( f.forum_id = $rss_f_id OR sf.forum_id = $rss_f_id )";
				$group_by .= ', f.forum_id';
			}

			// Check if this option is enabled
			if ( !$config['rss_thread'] && $rss_t_id != 0 )
			{
				break;
			}

//			$topic_sql = ($rss_t_id == 0) ? ' AND p.post_id = t.topic_last_post_id ' : " AND p.topic_id = t.topic_id AND t.topic_id = $rss_t_id";
			if ( $rss_t_id == 0 )
			{
				$topic_sql = ' AND p.post_id = t.topic_last_post_id ';
			}
			else
			{
				$topic_sql = " AND p.topic_id = t.topic_id AND t.topic_id = $rss_t_id";
				$group_by .= ', p.topic_id';
			}

			$order_sql = (empty($topic_sql) ? 't.topic_last_post_time DESC' : 'p.post_time DESC');

			if ( !$forum_sql && !$topic_sql )
			{
				// Search for active topics in last 7 days
				$sort_days = request_var('st', 7);
	
				$last_post_time_sql	= ($sort_days) ? " AND t.topic_last_post_time > " . (time() - ($sort_days * 24 * 3600)) : '';
			}

			$sql_array['SELECT'] = 'DISTINCT f.forum_id, f.forum_password, f.forum_name, f.parent_id, f.left_id, f.right_id' ;
			$sql_array['SELECT'].= ', t.topic_id, t.topic_last_post_time, t.topic_title, t.topic_time, t.topic_replies, t.topic_views';
			$sql_array['SELECT'].= ', p.post_id, p.topic_id, p.poster_id, p.post_time, p.post_subject, p.post_text, p.bbcode_bitfield, p.bbcode_uid, p.post_attachment';
			$sql_array['FROM'][FORUMS_TABLE] = 'f';
			$sql_array['FROM'][TOPICS_TABLE] = 't';
			$sql_array['FROM'][POSTS_TABLE]  = 'p';
			$sql_array['LEFT_JOIN'][] = array('FROM' => array(FORUMS_TABLE =>'sf'), 'ON' => 'f.left_id BETWEEN sf.left_id AND sf.right_id');
			$sql_array['WHERE'] = 't.topic_moved_id = 0 ';
			$sql_array['WHERE'].= ' AND ( f.forum_id = p.forum_id ';
			$sql_array['WHERE'].= ' AND p.topic_id = t.topic_id ) ';
			$sql_array['WHERE'].=  $forum_sql . $topic_sql . $last_post_time_sql;
			$sql_array['GROUP_BY'] = $group_by;
			$sql_array['ORDER_BY'] = $order_sql;
		}
		break;
}

// is there any query to run? may be the feed is disabled ;)
if ( $sql_array['SELECT'] != '' )
{

	// Apply filters
	$f_exclude_ary = rss_filters();
	$sql_array['WHERE'] .= (sizeof($f_exclude_ary)) ? " AND " . $db->sql_in_set('f.forum_id', $f_exclude_ary, true) : "";

	$sql = $db->sql_build_query('SELECT', $sql_array);
}

// only return up to 100 ids (you can change it to the value that best suits your needs)
if ( $result = $db->sql_query_limit($sql, 100) )
{
	$forum_data = array();
	while ($row = $db->sql_fetchrow($result))
	{
		$forum_data[] = $row;
	}
	$db->sql_freeresult($result);
	$total_count = sizeof($forum_data);
	unset($forum_data);

	$result = $db->sql_query_limit($sql, $per_page, $start);
}

if(!$result || !$total_count )
{
	$template->assign_block_vars('items', array(
		'DESCRIPTION' => $user->lang['NO_RSS_ITEMS_EXPLAIN'],
	));
}

// If we are here means that all is fine,
// So we will need some user data 
$user_data = array();
$sql_user = 'SELECT user_id, username, user_email, user_allow_viewemail 
			FROM ' . USERS_TABLE;
$result_user = $db->sql_query($sql_user);
while ($row = $db->sql_fetchrow($result_user))
{
	$user_data[$row['user_id']] = $row;
}
$db->sql_freeresult($result_user);


// Okay, lets dump out the page ...
while ($row = $db->sql_fetchrow($result))
{
	// Reset some data
	$feed_mode = $item_link = $user_link = $stats = '';

	switch ($rss_mode)
	{
		case 'forums':
			$feed_mode	= $user->lang['FORUMS'];

			$item_link	= rss_append_sid("$board_url/viewforum.$phpEx", "f={$row['forum_id']}");
			$stats		= sprintf($user->lang['TOTAL_TOPICS_OTHER'], $row['forum_topics']) . ' &bull; ' . sprintf($user->lang['TOTAL_POSTS_OTHER'], $row['forum_posts']);

			$row['post_id'] = 0;
			// Get and display all topics in this forum ?
			if ( $config['rss_forums_topics'] )
			{
				$topic_sql = "SELECT t.topic_id, t.topic_title 
					FROM " . TOPICS_TABLE . " t 
					WHERE t.forum_id = {$row['forum_id']} 
					GROUP BY t.topic_id, t.topic_title";
				// Only return up to 20 topics, more will be dangerous ;)
				$topic_result = $db->sql_query_limit($topic_sql, 20);

				$topic_titles = '';
				while ( $topic_row = $db->sql_fetchrow($topic_result) )
				{
					$topic_titles .= $topic_row['topic_title'] . "\r";
				}
				$db->sql_freeresult($topic_result);

				$row[$row_text] .= "<p><strong>{$user->lang['SUBFORUMS']} : </strong>" . "<div style='margin-left: 30px;'>$topic_titles</div></p>" . ( $row['forum_topics'] > 20 ? '...' : '' );
			}
		break;

		case 'topics':
		case 'news':
			$feed_mode	= ( $rss_mode == 'news' ) ? $user->lang['RSS_NEWS'] : $user->lang['TOPICS'];
			$item_link	= rss_append_sid("$board_url/viewtopic.$phpEx", "p={$row['post_id']}#p{$row['post_id']}");

			$user_link	= '<a href="' . rss_append_sid("$board_url/memberlist.$phpEx", "mode=viewprofile&amp;u=" . $user_data[$row[$row_creator]]['user_id']) . '">' . $user_data[$row[$row_creator]]['username'] . '</a>';
			$stats		= $user->lang['POSTED'] . ' ' . $user->lang['POST_BY_AUTHOR'] . ' ' . $user_link . ' &bull; ' . $user->lang['POSTED_ON_DATE'] . ' ' . $user->format_date($row['topic_time']). ' &bull; ' . $user->lang['REPLIES'] . ' ' . $row['topic_replies'] . ' &bull; ' . $user->lang['VIEWS'] . ' ' . $row['topic_views'];
		break;

		case 'posts':
			$feed_mode	= $user->lang['POSTS'];
			$item_link	= rss_append_sid("$board_url/viewtopic.$phpEx", "p={$row['post_id']}#p{$row['post_id']}");

			$user_link	= '<a href="' . rss_append_sid("$board_url/memberlist.$phpEx", "mode=viewprofile&amp;u=" . $user_data[$row[$row_creator]]['user_id']) . '">' . $user_data[$row[$row_creator]]['username'] . '</a>';
			$stats		= $user->lang['POSTED'] . ' ' . $user->lang['POST_BY_AUTHOR'] . ' ' . $user_link . ' &bull; ' . $user->lang['POSTED_ON_DATE'] . ' ' . $user->format_date($row['post_time']);
		break;

		case 'egosearch':
			$feed_mode	= $user->lang['SEARCH_SELF'];
			$item_link	= rss_append_sid("$board_url/viewtopic.$phpEx", "p={$row['post_id']}#p{$row['post_id']}");
		break;

		default:
			$row[$row_title] = $row['forum_name'] . " | " . (( $row[$row_title] ) ? $row[$row_title] : $row[$row_title2]);
			$feed_mode	= ( ($rss_f_id) ? $user->lang['FORUMS'] .' > ' . $row['forum_name'] . ( ($rss_t_id) ? ' : ' . $user->lang['TOPICS'] . ' : ' . $row['topic_title'] : '' ) : '' );
			$item_link	= rss_append_sid("$board_url/viewtopic.$phpEx", "p={$row['post_id']}#p{$row['post_id']}");

			$user_link	= '<a href="' . rss_append_sid("$board_url/memberlist.$phpEx", "mode=viewprofile&amp;u=" . $user_data[$row[$row_creator]]['user_id']) . '">' . $user_data[$row[$row_creator]]['username'] . '</a>';
			$stats		= $user->lang['POSTED'] . ' ' . $user->lang['POST_BY_AUTHOR'] . ' ' . $user_link . ' &bull; ' . $user->lang['POSTED_ON_DATE'] . ' ' . $user->format_date($row['topic_time']). ' &bull; ' . $user->lang['REPLIES'] . ' ' . $row['topic_replies'] . ' &bull; ' . $user->lang['VIEWS'] . ' ' . $row['topic_views'];
			break;
	}

	// Does post have an attachment? If so, add it to the list
	$attach_list = array();
	if (isset($row['post_attachment']) && $row['post_attachment'] && $config['rss_allow_attachments'])
	{
		$attach_list[] = $row['post_id'];
	}

	$template->assign_block_vars('items', array(
		'TITLE'			=> ( $row[$row_title] ) ? $row[$row_title] : $row[$row_title2],
		'LINK'			=> htmlspecialchars($item_link),
		'DESCRIPTION'	=> ( $row_text != '' && $show_text) ? generate_content($row[$row_text], $row[$row_uid], $row[$row_bit], $attach_list, $row['post_id'], $row['forum_id']) : '',
		'STATISTICS'	=> ( !$config['rss_items_statistics'] ) ? '' : $user->lang['STATISTICS'] . ' : ' . $stats,
		'PUBDATE'		=> ( !$config['rss_items_statistics'] ) ? '' : date2822(false, $row[$row_date]),
		'CATEGORY'		=> ( !$config['rss_items_statistics'] ) ? '' : "$board_url/viewforum.$phpEx?f={$row['forum_id']}",
		'CATEGORY_NAME'	=> ( !$config['rss_items_statistics'] ) ? '' : utf8_htmlspecialchars($row['forum_name']),
		'AUTHOR'		=> ( !$config['rss_items_statistics'] ) ? '' : ( ($user_data[$row[$row_creator]]['user_allow_viewemail']) ? $user_data[$row[$row_creator]]['user_email'] : $config['board_email'] ) . ' (' . $user_data[$row[$row_creator]]['username'] . ')',
		'GUID'			=> htmlspecialchars($item_link),
	));

	unset($attach_list);
}

// Set custom template for styles area
$template->set_custom_template($phpbb_root_path . 'styles', 'rss');

// the rss template is never stored in the database
$user->theme['template_storedb'] = false;

$template->assign_vars(array(
	'FEED_ENCODING'			=> '<?xml version="1.0" encoding="UTF-8"?>',
	'FEED_MODE'				=> ($rss_mode == 'egosearch') ? $user->lang['USERNAME'] .' : '. $user->data['username'] : $feed_mode,
	'FEED_TITLE'			=> $config['sitename'],
	'FEED_DESCRIPTION'		=> $config['site_desc'],
	'FEED_LINK'				=> "$board_url/index.$phpEx",
	'FEED_LANG'				=> $user->lang['USER_LANG'],
	'FEED_COPYRIGHT'		=> date('Y', $config['board_startdate']) . ' ' . $config['sitename'],
	'FEED_INDEX'			=> "$board_url/rss.$phpEx",
	'FEED_DATE'				=> date2822(true),
	'FEED_TIME'				=> date2822(),
	'FEED_MANAGING'			=> $config['board_email'] . " (" . $config['sitename'] . ")",
	'FEED_IMAGE'			=> $board_url . '/' . substr($user->img('site_logo', '', false, '', $type = 'src'),2),
	'FEED_TEXT'				=> $show_text,
));

// Is pagination enabled ?
if ( $config['rss_pagination'] )
{
	$template->assign_vars(array(
		'PAGINATION'		=> generate_pagination("$board_url/$u_rss", $total_count, $per_page, $start),
		'PAGE_NUMBER'		=> on_page($total_count, $per_page, $start),
	));	
}

// Is Board statistics enabled and runing the main the main board feed ?
if ( $config['rss_board_statistics'] && ( !$rss_mode && !$rss_f_id && !$rss_t_id ) )
{
	// Which timezone?
	$tz = ($user->data['user_id'] != ANONYMOUS) ? strval(doubleval($user->data['user_timezone'])) : strval(doubleval($config['board_timezone']));

	// Days since board start
	$boarddays = (time() - $config['board_startdate']) / 86400;

	$template->assign_vars(array(
		'S_STATISTICS'			=> true,
		'STAT_TITLE'			=> $config['sitename'] . ' ' . $user->lang['STATISTICS'] . ' : ',
		'STAT_BOARD_STARTED'	=> $user->format_date($config['board_startdate']),
		'STAT_BOARD_DAYS'		=> floor(abs( (time() - $config['board_startdate']) / (60 * 60 * 24) )),
		'STAT_BOARD_VERSION'	=> $config['version'],
		'L_STAT_TIMEZONE'		=> sprintf($user->lang['ALL_TIMES'], '', ''),
		'STAT_TIMEZONE'			=> ($user->data['user_dst'] || ($user->data['user_id'] != ANONYMOUS && $config['board_dst'])) ? $user->lang['tz'][$tz] . ' - ' . $user->lang['tz']['dst'] : $user->lang['tz'][$tz], '',
		'STAT_TOTAL_POSTS'		=> $config['num_posts'],
		'STAT_POSTS_PER_DAY'	=> sprintf('%.2f', $config['num_posts'] / $boarddays),
		'STAT_TOTAL_TOPICS'		=> $config['num_topics'],
		'STAT_TOPICS_PER_DAY'	=> sprintf('%.2f', $config['num_topics'] / $boarddays),
		'STAT_TOTAL_USERS'		=> $config['num_users'],
		'STAT_USERS_PER_DAY'	=> sprintf('%.2f', $config['num_users'] / $boarddays),
		'L_NEWEST_USER'			=> sprintf($user->lang['NEWEST_USER'], ''),
		'STAT_ONLINE_USERS'		=> sprintf($user->lang['RECORD_ONLINE_USERS'], $config['record_online_users'], $user->format_date($config['record_online_date'])),
		'STAT_NEWEST_USER'		=> $config['newest_username'],
	));	
}
// Output page

/**
// Check whether the session is still valid if we have one
if ( basename(trim($config['auth_method'])) == 'apache')
{
	$php_auth_user	= $_SERVER['PHP_AUTH_USER'];
	$php_auth_pw	= $_SERVER['PHP_AUTH_PW'];

	if ( !rss_checkLogin($php_auth_user, $php_auth_pw) )
	{
		rss_askAuth();
		exit;
	}
}
**/

// application/xhtml+xml not used because of IE	//header("Content-Type: application/xhtml+xml; charset=UTF-8"); 
header('Content-type: application/rss+xml; charset=UTF-8');

// Do you need/want it ?, else comment it
header("Last-Modified: " . date2822() );

$template->set_filenames(array(
	'body'	=> 'rss_template.xml',
));

// Output page creation time
if ( DEBUG && RSS_DEBUG_MODE )
{
	$mtime = explode(' ', microtime());
	$totaltime = $mtime[0] + $mtime[1] - $starttime;

	$debug_output = sprintf('Time : %.3fs | ' . $db->sql_num_queries(false) . ' Queries | GZIP : ' . (($config['gzip_compress']) ? 'On' : 'Off') . (($user->load) ? ' | Load : ' . $user->load : ''), $totaltime);

	if ($auth->acl_get('a_') && defined('DEBUG_EXTRA'))
	{
		if (function_exists('memory_get_usage'))
		{
			if ($memory_usage = memory_get_usage())
			{
				global $base_memory_usage;
				$memory_usage -= $base_memory_usage;
				$memory_usage = get_formatted_filesize($memory_usage);
				$debug_output .= ' | Memory Usage: ' . $memory_usage;
			}
		}
	}

	if ( RSS_DEBUG_SQL )
	{
		$debug_output .= "<br /><strong>SQL : </strong>$sql";
	}

	$template->assign_vars(array(
		'DEBUG_OUTPUT'		=> $debug_output,
	));
}

//page_footer();
$template->display('body');

garbage_collection();
exit_handler();

/******************************************************************************************************************************************/
/* Common functions                                                                                                                       */
/******************************************************************************************************************************************/

/**
* find out in which forums the user is not allowed to view
* @return array with forum id
**/
function rss_filters()
{
	global $auth, $db, $config;

	// Which forums should not be searched ?
	$exclude_forums	= explode(",", $config['rss_exclude_id'] );
	$f_id_ary 		= array();

	// Get some extra data for the excluded forums - Start
	$sql = 'SELECT forum_id, left_id, right_id 
			FROM ' . FORUMS_TABLE . ' 
			WHERE ' . $db->sql_in_set('forum_id', $exclude_forums) . ' 
			ORDER BY forum_id';
	$result = $db->sql_query($sql);

	while ($row = $db->sql_fetchrow($result))
	{
		$f_id_ary[] = array('forum_id' => $row['forum_id'], 'left_id' => $row['left_id'], 'right_id' => $row['right_id']);
	}
	$db->sql_freeresult($result);
	// Get some extra data for the excluded forums - End

	// Get all forums
	$sql = 'SELECT forum_id, forum_name, parent_id, left_id, right_id, forum_parents, forum_password, forum_type 
			FROM ' . FORUMS_TABLE . " 
			ORDER BY forum_id";
	$result = $db->sql_query($sql);

	$f_exclude_ary = array();
	while ($row = $db->sql_fetchrow($result))
	{
		$skip_id	= false;

		// Getting authentication
		if ( !$config['rss_permissions'] && !$auth->acl_get('f_read', $row['forum_id']) )
		{
			$f_exclude_ary[] = (int) $row['forum_id'];
			$skip_id = true;
			continue;
		}

		// If there is a exlude ID, 
		foreach ( $f_id_ary as $exclude_id => $exclude_data )
		{
			// First exclude for forums ID
			if ( $row['forum_id'] == $exclude_data['forum_id'] )
			{
				$f_exclude_ary[] = (int) $row['forum_id'];
				$skip_id = true;
				continue;
			}

			// Second exclude direct child of current branch
			if ( $row['parent_id'] == $exclude_data['forum_id'] )
			{
				$f_exclude_ary[] = (int) $row['forum_id'];
				$skip_id = true;
				continue;
			}

			// Then exclude child 
			if ( $row['forum_type'] == FORUM_POST && ($row['right_id'] < $exclude_data['right_id']) && ($row['left_id'] > $exclude_data['left_id']) )
			{
				$f_exclude_ary[] = (int) $row['forum_id'];
				$skip_id = true;
				continue;
			}

			// Getting authentication
			if ( $config['rss_permissions'] && !$auth->acl_get('f_read', $row['forum_id']) )
			{
				$f_exclude_ary[] = (int) $row['forum_id'];
				$skip_id = true;
				continue;
			}

			// Last skip paswored forums
			if ( $row['forum_password'] )
			{
				$f_exclude_ary[] = (int) $row['forum_id'];
				$skip_id = true;
				continue;
			}
		}

		if ( $skip_id )
		{
			continue;
		}
	}
	$db->sql_freeresult($result);

	return $f_exclude_ary;
}

/**
* Property build links 
*
* @param string $url The url the session id needs to be appended to (can have params)
* @param mixed $params String or array of additional url parameters
* @param bool $is_amp Is url using &amp; (true) or & (false)
* @param string $session_id Possibility to use a custom session id instead of the global one
*
* Examples:
* <code>
* append_sid("{$phpbb_root_path}viewtopic.$phpEx?t=1&amp;f=2");
* append_sid("{$phpbb_root_path}viewtopic.$phpEx", 't=1&amp;f=2');
* append_sid("{$phpbb_root_path}viewtopic.$phpEx", 't=1&f=2', false);
* append_sid("{$phpbb_root_path}viewtopic.$phpEx", array('t' => 1, 'f' => 2));
* </code>
*
* Code based off root/includes/function.php -> reapply_sid()
**/
function rss_append_sid($url, $params)
{
	$rss_link = append_sid($url, $params, false);

	// Remove added sid
	if ( strpos($rss_link, 'sid=') !== false )
	{
		$rss_link = preg_replace('/(&amp;|&|\?)sid=[a-z0-9]+(&amp;|&)?/', '\1', $rss_link);
	}
	return $rss_link;
}

/**
* Enter description here...
*
* @param string		$content
* @param int		$uid
* @param int		$bitfield
* @return string	
**/
function generate_content( $content, $uid, $bitfield, $attach_list, $post_id, $forum_id )
{
	global $text_limit, $show_text, $board_url;
	global $user, $config, $phpbb_root_path, $phpEx;

	if ( $show_text && !empty($content) )
	{
		// Remove Comments from smiles
		$content	= smiley_text($content);

		// Truncates post text ?
		$content	= generate_truncate_content($content, $text_limit, $uid, false );

		// Prepare some bbcodes for better parsing
		$content	= preg_replace("#\[quote(=&quot;.*?&quot;)?:$uid\]\s*(.*?)\s*\[/quote:$uid\]#si", "[quote$1:$uid]<br />$2<br />[/quote:$uid]", $content);

		/* Just remember : Never use it !
		* Commented out so I do not make the same error twice.
		$content	= html_entity_decode($content);
		*/

		// Parse it!
		$content	= generate_text_for_display($content, $uid, $bitfield, 7);

		// Fix smilies
		$content	= str_replace('{SMILIES_PATH}/', "{$phpbb_root_path}{$config['smilies_path']}/", $content);

		// Relative Path to Absolute path, Windows style
		$content	= str_replace('./', "$board_url/", $content);

		// Fix some spaces
		$content	= bbcode_nl2br($content);

		// Remove "Select all" link and mouse events
		$content	= str_replace('<a href="#" onclick="selectCode(this); return false;">' .$user->lang['SELECT_ALL_CODE'] . '</a>', '', $content);
		$content	= preg_replace('#(onkeypress|onclick)="(.*?)"#si', '', $content);

		// Remove Comments from post content
		$content	= preg_replace('#<!-- ([lmwe]) -->(.*?)<!-- ([lmwe]) -->#si', '$2', $content);

		// Remove embed Windows Media Streams
		$content	= preg_replace( '#<\!--\[if \!IE\]>-->([^[]+)<\!--<!\[endif\]-->#si', '', $content);

		// Remove embed and objects
		// Use (<|&lt;) and (>|&gt;) because can be contained into [code][/code]
		$content	= preg_replace( '#(<|&lt;)(object|embed)(.*?) (value|src)=(.*?) ([^[]+)(object|embed)(>|&gt;)#si',' <a href=$5 target="_blank"><strong>$2</strong></a> ',$content);

		// Remove some specials html tag, because somewhere there are a mod to allow html tags ;)
		// Use (<|&lt;) and (>|&gt;) because can be contained into [code][/code]
		$content	= preg_replace( '#(<|&lt;)script([^[]+)script(>|&gt;)#si', ' <strong>Script</strong> ', $content);
		$content	= preg_replace( '#(<|&lt;)iframe([^[]+)iframe(>|&gt;)#si', ' <strong>Iframe</strong> ', $content);

		// Resize images ?
		if ( $config['rss_image_size'] )
		{
			$content	= preg_replace('#<img.*?src=\"(.*?)\" alt=(.*?)/>#ise', "check_rss_imagesize( '$1' )", $content);
		}

		/* Convert special HTML entities back to characters
		* Some languages will need it
		* Commented out so I do not loose the code.
		$content = htmlspecialchars_decode($content);
		*/

		// Other control characters
		$content = preg_replace('#(?:[\x00-\x1F\x7F]+|(?:\xC2[\x80-\x9F])+)#', '', $content);

		// Pull attachment data
		if (sizeof($attach_list))
		{
			global $auth, $db;

			$attachments = $update_count = array();
			$attachments['post_id'][] = $post_id;

			// Pull attachment data
			if ($auth->acl_get('u_download') && $auth->acl_get('f_download', $forum_id))
			{
				$attachments = array();
				$sql = 'SELECT *
					FROM ' . ATTACHMENTS_TABLE . '
					WHERE ' . $db->sql_in_set('post_msg_id', $attach_list) . '
						AND in_message = 0
					ORDER BY filetime DESC, post_msg_id ASC';
				$result = $db->sql_query($sql);

				while ($row = $db->sql_fetchrow($result))
				{
					$attachments[$row['post_msg_id']][] = $row;
				}
				$db->sql_freeresult($result);
			}
			// Attach in-line
			parse_attachments($forum_id, $content, $attachments[$post_id], $update_count);

		   // Display not already displayed Attachments for this post, we already parsed them. ;)
			if ( !empty($attachments[$post_id]) )
			{
				$attachment_data = '';
				foreach ($attachments[$post_id] as $attachment)
				{
					$attachment_data .= $attachment;
				}
				$content .= '<br /><br />' . $user->lang['ATTACHMENTS'] . $attachment_data;
			}
		}
		else
		{
			// Remove attachments [ia]
			$content = preg_replace('#<div class="(inline-attachment|attachtitle)">(.*?)<!-- ia(.*?) -->(.*?)<!-- ia(.*?) -->(.*?)</div>#si','',$content);
		}

	}

	/* Just remember : Never use it !
	* Commented out so I do not make the same error twice.
	$content = htmlspecialchars($content);
	*/
	return $content;
}

/**
* Truncates post text while retaining completes bbcodes tag, triying to not cut in between 
*
* @param string		$content		post text
* @param int		$text_limit		number of characters to get
* @param string		$uid			bbcode uid
* @param bolean		$recursive		call this function from inside this?
* @return string	$content
**/
function generate_truncate_content($content, $text_limit, $uid, $recursive = true )
{
	global $phpbb_root_path, $config;

	// Check lenght
	$text_limit = ( $text_limit == 0 ) ? strlen($content)+1 : $text_limit;

	if ( strlen($content) < $text_limit )
	{
		return $content;
	}
	else
	{
		$end_content = ( !$recursive ? '<br />...' : '' );

		$content = " " . $content;
		// Change " with '
		$str				= " " . str_replace("&quot;", "'", $content);
		$curr_pos			= 0;
		$curr_length		= 0;
		$curr_text			= '';
		$next_text			= '';

		// Start at the 1st char of the string, looking for opening tags. Cutting the text in each space...
		while( $curr_length < $text_limit )
		{
			$_word = split(' ', $str);
			$skip_lenght = false;

			// pad it with a space so we can distinguish between FALSE and matching the 1st char (index 0).
			$curr_word = (( $_word[0] != ' ') ? ' ' : '' ) . $_word[0];
			
			// current word/part carry a posible bbcode tag ?
			$the_first_open_bbcode = strpos($curr_word, "[");

			// fix for smiles, make sure always are completes
			if ( strpos($curr_word, "<img") )
			{
				$smile_open		= strpos( $str, '<img src="' . $phpbb_root_path . $config['smilies_path'] . '/');
				$smile_close	= strpos( $str, " />", $smile_open );
				$curr_word	= substr( $str, 0, $smile_close+3 );
				$the_first_open_bbcode = false;
			}

			// if yes looks for the end of this bbcode tag
			if ( $the_first_open_bbcode !== false )
			{
				$the_first_open_bbcode = strpos($str, "[");
				$the_first_close_bbode = strpos($str, "]");

				if ( $the_first_open_bbcode > $the_first_close_bbode )
				{
					$the_first_open_bbcode = -1;
				}

				// Get the current bbcode, all between [??:??]
				$the_curr_bbcode_tag = substr($str, ($the_first_open_bbcode+1), (($the_first_close_bbode)-($the_first_open_bbcode+1)));

				// Now search for the end of the current bbcode tag, all between [/??:??]
				if ( (strpos($the_curr_bbcode_tag, " ") || strpos($the_curr_bbcode_tag, "=") || strpos($the_curr_bbcode_tag, ":")) )
				{
					list( $bbcode_tag, $garbage ) = split( '[ =:]', $the_curr_bbcode_tag );

					$bbcode_tag = str_replace('/','', $bbcode_tag);

					if ( $bbcode_tag == 'list' )
					{
						if ( strpos($the_curr_bbcode_tag, "=") )
						{
							$bbcode_tag = $bbcode_tag . ":o";
						}
						else
						{
							$bbcode_tag = $bbcode_tag . ":u";
						}
					}

					if ( $bbcode_tag == '*')
					{
							$bbcode_tag = $bbcode_tag . ":m";
					}

					// little fix for a particular bbode :)
					if ( $bbcode_tag != 'tr' && $bbcode_tag != 'td')
					{
						$bbcode_tag .= ":" . $uid ;
					}
				}
				else
				{
					$bbcode_tag = $the_curr_bbcode_tag;
				}

				$the_curr_bbcode_tag_close = "[/" . $bbcode_tag . "]";

				// Is this a simple bbcode tag without a close bbcode [??:??] // like [tab=xx]
				// Or may be the user use the "[" and/or "]" for another propose...
				if ( strpos($str, $the_curr_bbcode_tag_close) === false )
				{
					$the_first_close_bbode = $the_first_close_bbode+1;
					$the_second_close_bbcode = $the_first_close_bbode;
					$skip_lenght = true;
				}
				else
				{
					$the_second_close_bbcode = strpos($str, $the_curr_bbcode_tag_close)+strlen($the_curr_bbcode_tag_close);
				}

				// Until here all works like expected, 
				// But sometimes the length is much longer as expected, because a bbcode can contain a lot of text, so try to do some magic :)
				$curr_length_until = strlen( $curr_text ) + strlen( substr($str, 0, $the_second_close_bbcode) );

				// Test if the future lenght is longer that the $text_limit 
				if ( ( $curr_length_until > $text_limit ) && !$recursive && !$skip_lenght)
				{
					// Run me again but this time only with the current bbcode content, Can we do that ? :) Yes !
					$the_second_open_bbcode = strpos($str, "[");

					if ( $the_second_open_bbcode )
					{
						$curr_text .= " " . substr($str, 0, $the_second_open_bbcode);
						$str = substr($str, $the_second_open_bbcode);
					}

					$current_bbcode_content = substr( $str, strlen("[$the_curr_bbcode_tag]") );
					$current_bbcode_content = substr( $current_bbcode_content, 0, strpos($current_bbcode_content, $the_curr_bbcode_tag_close) );

					$next_text = "[" . $the_curr_bbcode_tag . "]" . generate_truncate_content($current_bbcode_content, ($text_limit-$curr_length), $uid, true) . $the_curr_bbcode_tag_close;
				}
				else
				{
					$next_text = substr($str, 0, $the_second_close_bbcode);
				}

				$curr_text .= $next_text;
				$curr_pos = strlen($curr_text);
			}
			else
			// current word is not a bbcode tag
			{
				$curr_text .= $curr_word;
				$curr_pos += strlen($curr_word);
			}

			$str = substr( $content, $curr_pos );

			// Count for words, without bbcodes, so get the real post length :)
			$curr_length = strlen( preg_replace( "#\[(.*?)\](.*?)\[(.*?)\]#is", '$2', $curr_text ) );
		}
		return $curr_text . $end_content;
	}
}

/**
 *Get date in RFC2822 format
*
* @param $forced	bool 	force time to 0 
* @param $timestamp	integer	the time
* @param $timezone	integer	the time zone
* @return string	string	date in RFC2822 format
* Code based off : http://cyber.law.harvard.edu/rss/rss.html#requiredChannelElements
**/
function date2822( $forced = false, $timestamp = 0, $timezone = 0 )
{
	global $config;

	// Local differential hours+min. (HHMM) ( ("+" / "-") 4DIGIT ); 
	$timezone  = ( $timezone ) ? $timezone   : $config['board_timezone'];
	$timezone  = ( $timezone > 0 ) ? '+' . $timezone : $timezone;
	$tz = $tzhour = $tzminutes = '';

	$matches = array();
	if ( preg_match('/^([\-+])?([0-9]+)?(\.)?([0-9]+)?$/', $timezone, $matches) )
	{
		$tz			= isset($matches[1] ) ? $matches[1] : $tz;
		$tzhour		= isset($matches[2] ) ? str_pad($matches[2], 2, "0", STR_PAD_LEFT) : $tzhour;
		$tzminutes	= isset($matches[4] ) ? ( ( $matches[4] == '75' ) ? '45' : '30' ) : '00';
		$timezone	= $tz . $tzhour . $tzminutes;
	}
	$timezone  = ( (int) $timezone == 0 ) ? 'GMT' : $timezone;

	$date_time = ( $timestamp ) ? $timestamp : time();
	$date_time = ( $forced ) ? date('D, d M Y 00:00:00', $date_time) : date('D, d M Y H:i:s', $date_time);

	return $date_time . ' ' . $timezone;
}

/**
* Try to resize a big image
*
* @param string 	$image_src		the image url
* @param int		$rss_imagesize	the max-width 
* @return html
**/
function check_rss_imagesize( $image_src, $image_size = 0 )
{
	global $user, $config;

	$rss_imagesize	= ( $image_size ) ? $image_size : $config['img_link_width'];
	$rss_imagesize	= ( $image_size ) ? $image_size : $config['img_max_width'];
	$rss_imagesize	= ( $image_size ) ? $image_size : $config['img_max_thumb_width'];
	$rss_imagesize	= ( $image_size ) ? $image_size : 200;
	$width			= '';

	// check image with timeout to ensure we don’t wait quite long
	$timeout = 5;
	$old = ini_set('default_socket_timeout', $timeout);

	if( $dimension = @getimagesize($image_src) )
	{
		if ( $dimension !== false || !empty($dimension[0]) )
		{
			if ( $dimension[0] > $rss_imagesize )
			{
				$width = 'width="' . $rss_imagesize . '" ';
			}
		}
	}

	ini_set('default_socket_timeout', $old);
	return '<img src="' . $image_src . '" alt="' . $user->lang['IMAGE'] . '" ' . $width . ' border="0" />';
}

/**
* Check if the user is valid
* Code based on root/includes/auth/auth_apache -> login_apache()
**/
function rss_checkLogin($php_auth_user, $php_auth_pw)
{
	global $db;

	if (!empty($php_auth_user) && !empty($php_auth_pw))
	{
		$sql = 'SELECT user_id, username, user_password, user_passchg, user_email, user_type
				FROM ' . USERS_TABLE . "
				WHERE username = '" . $db->sql_escape($php_auth_user) . "'";
		$result = $db->sql_query($sql);
		$row = $db->sql_fetchrow($result);
		$db->sql_freeresult($result);

		if ($row)
		{
			// User inactive...
			if ($row['user_type'] == USER_INACTIVE || $row['user_type'] == USER_IGNORE)
			{
				return false;
			}
			// Successful login...
			return true;
		}
		return false;
	}
	return false;
}

function rss_askAuth()
{
	global $config, $user;

	// The name of the area the box asks for access to
	$title = $config['sitename'];

	// I'm not sure about these ones exept they make sure the box comes and gives an message if the login fails
	// There should probably be some control of the tries, like a maximum of 3 tries
	header("WWW-Authenticate: Basic realm='$title'");
	header("HTTP/1.0 401 Unauthorized");
	echo $user->lang['LOGIN_ERROR_EXTERNAL_AUTH_APACHE']; // echo "Sorry, but you are not allowed to see this.";
	exit;
}

?>