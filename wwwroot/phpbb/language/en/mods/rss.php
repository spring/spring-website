<?php
/**
* @package: phpBB3 :: RSS feed 2.0 -> language -> en -> mods 
* @version: $Id: rss.php, v 1.0.9 2009/02/20 09:02:20 leviatan21 Exp $
* @copyright: leviatan21 < info@mssti.com > (Gabriel) http://www.mssti.com/phpbb3/
* @license: http://opensource.org/licenses/gpl-license.php GNU Public License
* @author: leviatan21 - http://www.phpbb.com/community/memberlist.php?mode=viewprofile&u=345763
*
**/

/**
* DO NOT CHANGE
*/
if (!defined('IN_PHPBB'))
{
	exit;
}

if (empty($lang) || !is_array($lang))
{
	$lang = array();
}

// DEVELOPERS PLEASE NOTE
//
// All language files should use UTF-8 as their encoding and the files must not contain a BOM.
//
// Placeholders can now contain order information, e.g. instead of
// 'Page %s of %s' you can (and should) write 'Page %1$s of %2$s', this allows
// translators to re-order the output of data while ensuring it remains correct
//
// You do not need this where single placeholders are used, e.g. 'Message %d' is fine
// equally where a string contains only two placeholders which are used to wrap text
// in a url you again do not need to specify an order e.g., 'Click %sHERE%s' is fine

$lang = array_merge($lang, array(
	'MSSTI_LINK'						=> 'RSS Feeds by <a href="http://www.mssti.com/phpbb3/" onclick="window.open(this.href);return false;" >.:: MSSTI ::.</a>',
	'ACP_RSS'							=> 'RSS management',
	'ACP_RSS_FEEDS'						=> 'RSS',
	'ACP_RSS_MANAGEMENT'				=> 'General RSS Feeds settings',
	'ACP_RSS_MANAGEMENT_EXPLAIN'		=> 'This Module makes available various RSS Feeds, parsing any BBCode in posts to make them readable in external feeds.',

// ACP Feeds to Serve
	'ACP_RSS_LEGEND1'					=> 'Feeds to Serve',

	'ACP_RSS_ENABLE'					=> 'Enable Feeds',
	'ACP_RSS_ENABLE_EXPLAIN'			=> 'Turns on or off RSS for the entire board.<br />Disabling this switches off all Feeds, no matter how the options below are set.',
	'ACP_RSS_FORUM'						=> 'Enable Per-Forum Feeds',
	'ACP_RSS_FORUM_EXPLAIN'				=> 'Single Forum new posts.',
	'ACP_RSS_OVERALL_FORUMS'			=> 'Enable overall forums feed',
	'ACP_RSS_OVERALL_FORUMS_EXPLAIN'	=> 'Enables the "All Forums" feed.',
	'ACP_RSS_OVERALL_FORUMS_LIMIT'		=> 'Number of items per page to display in the site-wide feed',
	'ACP_RSS_OVERALL_THREAD'			=> 'Enable overall threads feed',
	'ACP_RSS_OVERALL_THREAD_EXPLAIN'	=> 'Enables the "All Threads" feed',
	'ACP_RSS_OVERALL_THREAD_LIMIT'		=> 'Number of items per page to display in the All Threads feed',
	'ACP_RSS_OVERALL_POSTS'				=> 'Enable overall posts feed',
	'ACP_RSS_OVERALL_POSTS_EXPLAIN'		=> 'Enables the "All Posts" feed',
	'ACP_RSS_OVERALL_POSTS_LIMIT'		=> 'Number of items per page to display in the All Posts feed',
	'ACP_RSS_EGOSEARCH'					=> 'Enable the Ego Search Feed',
	'ACP_RSS_EGOSEARCH_EXPLAIN'			=> 'Feed similar to "View Your Posts"; only works if you remain logged in after leaving the forum.',
	'ACP_RSS_EGOSEARCH_LIMIT'			=> 'Number of items per page to display in the "you posted it" feed',
	'ACP_RSS_THREAD'					=> 'Enable Per-Thread Feeds',
	'ACP_RSS_THREAD_EXPLAIN'			=> 'Single Thread new posts.',
	'ACP_RSS_NEWS'						=> 'News Feeds',
	'ACP_RSS_NEWS_EXPLAIN'				=> 'Pull the first post from this forums ID\'s. Separate ID\'s by comma for multi-forums, eg. 1,2,5 <br />Leave this blank for disable News Feeds.',

// ACP General RSS Settings
	'ACP_RSS_LEGEND2'					=> 'General RSS Settings',

	'ACP_RSS_CHARACTERS'				=> 'Max length of post text to display',
	'ACP_RSS_CHARACTERS_EXPLAIN'		=> 'Max number of characters shown for each feed item, recommended setting is 1000.<br /> 0 means infinite, 1 means no text.',
	'ACP_RSS_ATTACHMENTS'				=> 'Attachments',
	'ACP_RSS_ATTACHMENTS_EXPLAIN'		=> 'Display attachments on feeds',
	'ACP_RSS_CHARS'						=> 'characters',
	'ACP_RSS_IMAGE_SIZE'				=> 'Max image width in pixels',
	'ACP_RSS_IMAGE_SIZE_EXPLAIN'		=> 'An image will be resized (as displayed in feeds) if it exceeds the specified width.<br /> 0 disables resizing.<br />PHP function getimagesize() <strong>Required</strong>',
	'ACP_RSS_AUTH'						=> 'Skip permissions',
	'ACP_RSS_AUTH_EXPLAIN'				=> 'If enabled, posts will be included in feeds without regard to restrictions you otherwise set on who may view them.',
	'ACP_RSS_BOARD_STATISTICS'			=> 'Board statistics',
	'ACP_RSS_BOARD_STATISTICS_EXPLAIN'	=> 'Display The Board statistics in the first page of the overall board feed.',
	'ACP_RSS_ITEMS_STATISTICS'			=> 'Items statistics',
	'ACP_RSS_ITEMS_STATISTICS_EXPLAIN'	=> 'Display individual items statistics in the board statistics<br />( Posted by + date and time + Replies + Views )',
	'ACP_RSS_PAGINATION'				=> 'Feed pagination',
	'ACP_RSS_PAGINATION_EXPLAIN'		=> 'Limits the number of items shown if there are more than the per page number of items.',
	'ACP_RSS_LIMIT'						=> 'Number of items per page',
	'ACP_RSS_LIMIT_EXPLAIN'				=> 'The maximum number of feed items to display per page, when pagination is enabled.',
	'ACP_RSS_EXCLUDE_ID'				=> 'Exclude this Forums',
	'ACP_RSS_EXCLUDE_ID_EXPLAIN'		=> 'The RSS will <strong>not pull</strong> data from this forums ID\'s and its childs. Separate ID\'s by comma for multi-forums, eg. 1,2,5 <br />Leave blank to pull from all forums.',

// FEED text
	'BOARD_DAYS'				=> 'Days since started',
	'COPYRIGHT'					=> 'Copyright',
	'NO_RSS_ITEMS'				=> 'No Items Available',
	'NO_RSS_ITEMS_EXPLAIN'		=> 'Unfortunately there appears to be no news items on the page you have requested logged here',
	'NO_RSS_ITEMS_LOGGED_IN'	=> 'You must be logged in to use %1$s RSS Feed',

));

?>