<?php
/**
*
* @copyright (c) 2009, 2010, 2011 Quoord Systems Limited
* @license http://opensource.org/licenses/gpl-2.0.php GNU Public License (GPLv2)
*
*/
defined('IN_MOBIQUO') or exit;

include_once $phpbb_root_path . 'includes/functions_convert.' . $phpEx;

function get_config_func()
{   
    global $mobiquo_config, $config, $auth, $user, $phpbb_extension_manager;
    $config_list = array(
        'sys_version'=> (string)$config['version'],
    	  'is_open'    => (boolean)($mobiquo_config['is_open'] && $phpbb_extension_manager->is_enabled('tapatalk/tapatalk') ? true : false),
        'guest_okay' => (boolean)$mobiquo_config['guest_okay'] ? true : false,
    );
    
    if($config['require_activation'] == USER_ACTIVATION_DISABLE)
    {
    	$mobiquo_config['sign_in'] = 0;
	    $mobiquo_config['inappreg'] = 0;
	    
	    $mobiquo_config['sso_signin'] = 0;
	    $mobiquo_config['sso_register'] = 0;
	    $mobiquo_config['native_register'] = 0;
    }
	if (!function_exists('curl_init') && !@ini_get('allow_url_fopen'))
	{
	    $mobiquo_config['sign_in'] = 0;
	    $mobiquo_config['inappreg'] = 0;
	    
	    $mobiquo_config['sso_login'] = 0;
	    $mobiquo_config['sso_signin'] = 0;
	    $mobiquo_config['sso_register'] = 0;
	}
	if(isset($config['tapatalk_register_status']))
	{
		if($config['tapatalk_register_status'] == 0)
		{
			$mobiquo_config['sign_in'] = 0;
		    $mobiquo_config['inappreg'] = 0;

		    $mobiquo_config['sso_signin'] = 0;
		    $mobiquo_config['sso_register'] = 0;
		    $mobiquo_config['native_register'] = 0;
		}
		elseif($config['tapatalk_register_status'] == 1)
		{
			$mobiquo_config['inappreg'] = 0;
			$mobiquo_config['sign_in'] = 0;

		    $mobiquo_config['sso_signin'] = 0;
		    $mobiquo_config['sso_register'] = 0;
		}
	}
	foreach($mobiquo_config as $key => $value)
    {
        if (!in_array($key, array('is_open', 'guest_okay', 'php_extension', 'shorten_quote', 'hide_forum_id', 'check_dnsbl')))
        {
            $config_list[$key] = (string)$value;
        }
    }
    if(!$mobiquo_config['is_open'])
    {
    	$config_list['is_open'] = (string)'0';
    	$config_list['result_text'] =  (string)'Tapatalk pulgin is disabled';
    }
    if($config['board_disable'])
    {
    	$config_list['is_open'] = (string)'0';
    	$config_list['result_text'] = (string)$config['board_disable_msg'];
    }
    
 	//undo
    //if(push_table_exists())
    //{
    //	$config_list['alert'] = (string)'1';
    //}
    if ($auth->acl_get('u_search') && $auth->acl_getf_global('f_search') && $config['load_search'])
    {
        $config_list['guest_search'] = (string)'1';
    }
    
    if ($auth->acl_gets('u_viewprofile', 'a_user', 'a_useradd', 'a_userdel'))
    {
        $config_list['guest_whosonline'] = (string)'1';
    }
	if($config['require_activation'] == USER_ACTIVATION_DISABLE)
    {
    	$config_list['inappreg'] = (string)'0';
    	$config_list['inappsignin'] = (string)'0';
    }
    if ($config['search_type'] == 'fulltext_native')
    {
        $config_list['min_search_length'] = (int)$config['fulltext_native_min_chars'];
    }
    else if ($config['search_type'] == 'fulltext_mysql')
    {
        $config_list['min_search_length'] = (int)$config['fulltext_mysql_min_word_len'];
    }
    
    if(isset($config['tapatalk_push_key']))
    {
    	$config_list['automod'] = (string)'1';
    }
    else 
    {
    	$config_list['automod'] = (string)'0';
    }
    
    if(!empty($config['tapatalk_push_key']))
    {
    	$config_list['api_key'] = (string)(md5($config['tapatalk_push_key']));
    }
    if(isset($config['tapatalk_ad_filter']))
    {
    	$config_list['ads_disabled_group'] = (string)$config['tapatalk_ad_filter'];
    }
    $config_list['guest_group_id'] = (string)get_group_id('GUESTS');
    $config_list['stats'] = array(
        'topic'    => (int)$config['num_topics'],
        'user'     => (int)$config['num_users'],
    	'post'     => (int)$config['num_posts'],
    	'active'   => (int)$config['record_online_users'], 
    );
    $response = $config_list;
    
    return $response;
}
