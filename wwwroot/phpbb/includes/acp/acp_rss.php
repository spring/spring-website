<?php
/**
* @package: phpBB3 :: RSS feed 2.0 -> includes -> acp
* @version: $Id: acp_rss.php, v 1.0.9 2009/02/20 09:02:20 leviatan21 Exp $
* @copyright: leviatan21 < info@mssti.com > (Gabriel) http://www.mssti.com/phpbb3/
* @license: http://opensource.org/licenses/gpl-license.php GNU Public License
* @author: leviatan21 - http://www.phpbb.com/community/memberlist.php?mode=viewprofile&u=345763
*
**/

/**
* @ignore
*/
if (!defined('IN_PHPBB'))
{
	exit;
}

/**
* @package acp
*/
class acp_rss
{
	var $u_action;
	var $new_config = array();

	function main($id, $mode)
	{
		global $config, $user, $cache, $template;

		$user->add_lang('mods/rss');

		$action	= request_var('action', '');
		$submit = (isset($_POST['submit'])) ? true : false;

		$form_key = 'acp_rss_feeds';
		add_form_key($form_key);

		// Set some default values so the user havn't to run any install ;)
		$config['rss_enable']				= ( isset($config['rss_enable'])				) ? $config['rss_enable']				: true;
		$config['rss_overall_forums']		= ( isset($config['rss_overall_forums'])		) ? $config['rss_overall_forums']		: true;
		$config['rss_overall_forums_limit']	= ( isset($config['rss_overall_forums_limit'])	) ? $config['rss_overall_forums_limit']	: 40;
		$config['rss_overall_threads']		= ( isset($config['rss_overall_threads'])		) ? $config['rss_overall_threads']		: true;
		$config['rss_overall_threads_limit']= ( isset($config['rss_overall_threads_limit'])	) ? $config['rss_overall_threads_limit']: 30;
		$config['rss_overall_posts']		= ( isset($config['rss_overall_posts'])			) ? $config['rss_overall_posts']		: true;
		$config['rss_overall_posts_limit']	= ( isset($config['rss_overall_posts_limit'])	) ? $config['rss_overall_posts_limit']	: 20;
		$config['rss_egosearch']			= ( isset($config['rss_egosearch'])				) ? $config['rss_egosearch']			: true;
		$config['rss_egosearch_limit']		= ( isset($config['rss_egosearch_limit'])		) ? $config['rss_egosearch_limit']		: 10;
		$config['rss_forum']				= ( isset($config['rss_forum'])					) ? $config['rss_forum']				: true;
		$config['rss_thread']				= ( isset($config['rss_thread'])				) ? $config['rss_thread']				: true;
		$config['rss_news_id']				= ( isset($config['rss_news_id'])				) ? $config['rss_news_id']				: '';

		$config['rss_characters']			= ( isset($config['rss_characters'])			) ? $config['rss_characters']			: 500;
		$config['rss_allow_attachments']	= ( isset($config['rss_allow_attachments'])		) ? $config['rss_allow_attachments']	: true;
		$config['rss_image_size']			= ( isset($config['rss_image_size'])			) ? $config['rss_image_size']			: 200;
		$config['rss_limit']				= ( isset($config['rss_limit'])					) ? $config['rss_limit']				: 50;
		$config['rss_board_statistics']		= ( isset($config['rss_board_statistics'])		) ? $config['rss_board_statistics']		: true;
		$config['rss_items_statistics']		= ( isset($config['rss_items_statistics'])		) ? $config['rss_items_statistics']		: true;
		$config['rss_pagination']			= ( isset($config['rss_pagination'])			) ? $config['rss_pagination']			: true;
		$config['rss_permissions']			= ( isset($config['rss_permissions'])			) ? $config['rss_permissions']			: false;
		$config['rss_exclude_id']			= ( isset($config['rss_exclude_id'])			) ? $config['rss_exclude_id']			: '';

		$display_vars = array(
			'title'	=> 'ACP_RSS_MANAGEMENT',
#			'lang'	=> 'mods/rss',
			'vars'	=> array(
				'legend1'					=> 'ACP_RSS_LEGEND1',
				'rss_enable'				=> array('lang' => 'ACP_RSS_ENABLE',				'validate' => 'bool',	'type' => 'radio:enabled_disabled',	'explain' => true ),
				'rss_overall_forums'		=> array('lang'	=> 'ACP_RSS_OVERALL_FORUMS',		'validate' => 'bool',	'type' => 'radio:enabled_disabled',	'explain' => true ),
				'rss_overall_forums_limit'	=> array('lang' => 'ACP_RSS_OVERALL_FORUMS_LIMIT',	'validate' => 'int:5',	'type' => 'text:3:4',				'explain' => false),
				'rss_overall_threads'		=> array('lang' => 'ACP_RSS_OVERALL_THREAD',		'validate' => 'bool',	'type' => 'radio:enabled_disabled',	'explain' => true ),
				'rss_overall_threads_limit'	=> array('lang' => 'ACP_RSS_OVERALL_THREAD_LIMIT',	'validate' => 'int:5',	'type' => 'text:3:4',				'explain' => false),
				'rss_overall_posts'			=> array('lang' => 'ACP_RSS_OVERALL_POSTS',			'validate' => 'bool',	'type' => 'radio:enabled_disabled',	'explain' => true ),
				'rss_overall_posts_limit'	=> array('lang' => 'ACP_RSS_OVERALL_POSTS_LIMIT',	'validate' => 'int:5',	'type' => 'text:3:4',				'explain' => false),
				'rss_egosearch'				=> array('lang' => 'ACP_RSS_EGOSEARCH',				'validate' => 'bool',	'type' => 'radio:enabled_disabled',	'explain' => true ),
				'rss_egosearch_limit'		=> array('lang' => 'ACP_RSS_EGOSEARCH_LIMIT',		'validate' => 'int:5',	'type' => 'text:3:4',				'explain' => false),
				'rss_forum'					=> array('lang' => 'ACP_RSS_FORUM',					'validate' => 'bool',	'type' => 'radio:enabled_disabled',	'explain' => true ),
				'rss_thread'				=> array('lang' => 'ACP_RSS_THREAD',				'validate' => 'bool',	'type' => 'radio:enabled_disabled',	'explain' => true ),
				'rss_news_id'				=> array('lang' => 'ACP_RSS_NEWS',					'validate' => 'string',	'type' => 'text:0:255',				'explain' => true ),

				'legend2'					=> 'ACP_RSS_LEGEND2',
				'rss_characters'			=> array('lang' => 'ACP_RSS_CHARACTERS',			'validate' => 'int:0',	'type' => 'text:3:4',				'explain' => true, 'append' => ' ' . $user->lang['ACP_RSS_CHARS']),
				'rss_allow_attachments'		=> array('lang' => 'ACP_RSS_ATTACHMENTS',			'validate' => 'bool',	'type' => 'radio:enabled_disabled',	'explain' => true ),
				'rss_image_size'			=> array('lang' => 'ACP_RSS_IMAGE_SIZE',			'validate' => 'int:0',	'type' => 'text:3:4',				'explain' => true, 'append' => ' px'),
				'rss_permissions'			=> array('lang' => 'ACP_RSS_AUTH',					'validate' => 'bool',	'type' => 'radio:enabled_disabled',	'explain' => true ),
				'rss_board_statistics'		=> array('lang' => 'ACP_RSS_BOARD_STATISTICS',		'validate' => 'bool',	'type' => 'radio:enabled_disabled',	'explain' => true ),
				'rss_items_statistics'		=> array('lang' => 'ACP_RSS_ITEMS_STATISTICS',		'validate' => 'bool',	'type' => 'radio:enabled_disabled',	'explain' => true ),
				'rss_pagination'			=> array('lang' => 'ACP_RSS_PAGINATION',			'validate' => 'bool',	'type' => 'radio:enabled_disabled',	'explain' => true ),
				'rss_limit'					=> array('lang' => 'ACP_RSS_LIMIT',					'validate' => 'int:5',	'type' => 'text:3:4',				'explain' => true ),
				'rss_exclude_id'			=> array('lang' => 'ACP_RSS_EXCLUDE_ID',			'validate' => 'string',	'type' => 'text:0:255',				'explain' => true ),

				'legend6'					=> 'CONFIRM',
			)
		);

		$this->new_config = $config;
		$cfg_array = (isset($_REQUEST['config'])) ? utf8_normalize_nfc(request_var('config', array('' => ''), true)) : $this->new_config;
		$error = array();

		// We validate the complete config if whished
		validate_config_vars($display_vars['vars'], $cfg_array, $error);

		if ($submit && !check_form_key($form_key))
		{
			$error[] = $user->lang['FORM_INVALID'];
		}

		// Do not write values if there is an error
		if (sizeof($error))
		{
			$submit = false;
		}

		// Grab global variables, re-cache if necessary
		if ($submit)
		{
			$config = $cache->obtain_config();
		}

		// We go through the display_vars to make sure no one is trying to set variables he/she is not allowed to...
		foreach ($display_vars['vars'] as $config_name => $null)
		{
			if (!isset($cfg_array[$config_name]) || strpos($config_name, 'legend') !== false)
			{
				continue;
			}

			$this->new_config[$config_name] = $config_value = $cfg_array[$config_name];

			if ($submit)
			{
				set_config($config_name, $config_value);
			}
		}

		if ($submit)
		{
			add_log('admin', 'LOG_CONFIG_' . strtoupper($mode));

			trigger_error($user->lang['CONFIG_UPDATED'] . adm_back_link($this->u_action));
		}

		$this->tpl_name = 'acp_board';
		$this->page_title = $display_vars['title'];

		$template->assign_vars(array(
			'L_TITLE'			=> '</h1><span style="float: right;">' . $user->lang['MSSTI_LINK'] . '</span><h1>' . $user->lang[$display_vars['title']],
			'L_TITLE_EXPLAIN'	=> $user->lang[$display_vars['title'] . '_EXPLAIN'] ,

			'S_ERROR'			=> (sizeof($error)) ? true : false,
			'ERROR_MSG'			=> implode('<br />', $error),

			'U_ACTION'			=> $this->u_action)
		);

		// Output relevant page
		foreach ($display_vars['vars'] as $config_key => $vars)
		{
			if (!is_array($vars) && strpos($config_key, 'legend') === false)
			{
				continue;
			}

			if (strpos($config_key, 'legend') !== false)
			{
				$template->assign_block_vars('options', array(
					'S_LEGEND'		=> true,
					'LEGEND'		=> (isset($user->lang[$vars])) ? $user->lang[$vars] : $vars)
				);

				continue;
			}

			$type = explode(':', $vars['type']);

			$l_explain = '';
			if ($vars['explain'] && isset($vars['lang_explain']))
			{
				$l_explain = (isset($user->lang[$vars['lang_explain']])) ? $user->lang[$vars['lang_explain']] : $vars['lang_explain'];
			}
			else if ($vars['explain'])
			{
				$l_explain = (isset($user->lang[$vars['lang'] . '_EXPLAIN'])) ? $user->lang[$vars['lang'] . '_EXPLAIN'] : '';
			}

			$template->assign_block_vars('options', array(
				'KEY'			=> $config_key,
				'TITLE'			=> (isset($user->lang[$vars['lang']])) ? $user->lang[$vars['lang']] : $vars['lang'],
				'S_EXPLAIN'		=> $vars['explain'],
				'TITLE_EXPLAIN'	=> $l_explain,
				'CONTENT'		=> build_cfg_template($type, $config_key, $this->new_config, $config_key, $vars),
				)
			);
		
			unset($display_vars['vars'][$config_key]);
		}
	}
}

?>