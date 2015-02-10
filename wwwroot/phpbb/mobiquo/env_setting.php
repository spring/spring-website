<?php

defined('IN_MOBIQUO') or exit;
if(isset($_POST['api_key']) && isset($_POST['start_uid']) && isset($_POST['limit']))
{
	include './function/get_contact.php';
	sync_user_func();
	exit;
}//undo
if (isset($_SERVER['HTTP_APP_VAR'] ) && $_SERVER['HTTP_APP_VAR'])
    @header('App-Var: '.$_SERVER['HTTP_APP_VAR']);
$mobiquo_config['reg_url'] = !empty($config['mobiquo_reg_url']) ? $config['mobiquo_reg_url'] : $mobiquo_config['reg_url'];
$mobiquo_config['hide_forum_id'] = !empty($config['mobiquo_hide_forum_id']) ? explode(',', $config['mobiquo_hide_forum_id']) : $mobiquo_config['hide_forum_id'];

if(empty($config['tapatalkdir'])) $config['tapatalkdir'] = TAPATALK_DIR;

mobi_parse_request();


if (in_array($request_method, array('logout_user', 'get_config')))
{
    define('IN_CHECK_BAN', 1);
}

require_once 'include/emoji.php';

$_SERVER['QUERY_STRING'] = 'method='.$request_method.'&amp;params='.intval($request_params[0]);
$request_file = $request_method;

switch ($request_method)
{
    case 'login':
        $tapatalk_handle = 'system';
        $request_file = PHPBB_ROOT_PATH."ucp.$phpEx";
        foreach($_COOKIE as $key => $value)
        {
            if (strstr($key,'phpbb3_'))
                unset($_COOKIE[$key]);
        }
        $_GET['mode'] = 'login';
        $_POST['username'] = $request_params[0];
        $_POST['password'] = $request_params[1];
        if (isset($request_params[2])&&(intval($request_params[2])!==0))
            $_POST['viewonline']= 'on';
        $_POST['redirect'] = 'index.php';
        $_POST['login']    = 'Login';
        break;
    case 'get_topic':
        $tapatalk_handle = 'system';
        $request_file = PHPBB_ROOT_PATH."viewforum.$phpEx";
        $_GET['st'] = 0;
        $_GET['sk'] = 't';
        $_GET['sd'] = 'd';
        $_GET['f'] = $request_params[0];
        list($_GET['start'], $perpage) = process_page($request_params[1], $request_params[2]);
        break;
    case 'login_forum':
        $tapatalk_handle = 'system';
        $request_file = PHPBB_ROOT_PATH."viewforum.$phpEx";
        $_GET['f'] = $request_params[0];
        $_POST['login'] = 'Login';
        $_POST['password'] = $request_params[1];
        break;
    case 'get_thread':
        $tapatalk_handle = 'system';
        $request_file = PHPBB_ROOT_PATH."viewtopic.$phpEx";
        $_GET['st'] = 0;
        $_GET['sk'] = 't';
        $_GET['sd'] = 'a';
        $_GET['t'] = $request_params[0];
        list($_GET['start'], $perpage) = process_page($request_params[1], $request_params[2]);
        $return_html = isset($request_params[3]) ? $request_params[3] : false;
        break;
    case 'new_topic':
        $tapatalk_handle = 'system';
        $request_file = PHPBB_ROOT_PATH."posting.$phpEx";
        $_GET['mode'] = 'post';
        $_GET['f'] = $request_params[0];
        $_POST['subject'] = $request_params[1];
        $_POST['message'] = emoji_unified_to_names($request_params[2]);
        //$_POST['attachment_data'] = (isset($params[5]) && $params[5]) ? unserialize(base64_decode($params[5])) : array();
        $_POST['addbbcode20'] = 100;
        $_POST['post'] = 'submit';
        $_POST['creation_time'] = time()-1;
        $_POST['form_token'] = sha1($_POST['creation_time'] . 'TapatalkApp' . 'posting' . '');
        break;
        
    case 'reply_post':
        $tapatalk_handle = 'system';
        $request_file = PHPBB_ROOT_PATH."posting.$phpEx";
        $_GET['mode'] = 'reply';
        $_GET['f'] = $request_params[0];
        $_GET['t'] = $request_params[1];
        $_POST['subject'] = $request_params[2];
        $_POST['message'] = emoji_unified_to_names($request_params[3]);
        //$_POST['attachment_data'] = (isset($params[5]) && $params[5]) ? unserialize(base64_decode($params[5])) : array();
        $return_html = isset($request_params[6]) ? $request_params[6] : false;
        $_POST['addbbcode20'] = 100;
        $_POST['post'] = 'submit';
        $_POST['creation_time'] = time()-1;
        $_POST['form_token'] = sha1($_POST['creation_time'] . 'TapatalkApp' . 'posting' . '');
        break;
    
    case 'save_raw_post':
        $tapatalk_handle = 'system';
        $request_file = PHPBB_ROOT_PATH."posting.$phpEx";
        $_GET['mode'] = 'edit';
        $_GET['f'] = 1;
        $_GET['p'] = $request_params[0];
        $_POST['subject'] = $request_params[1];
        $_POST['message'] = emoji_unified_to_names($request_params[2]);
        $return_html = isset($request_params[3]) ? $request_params[3] : false;
        //$_POST['attachment_data'] = (isset($params[4]) && $params[4]) ? unserialize(base64_decode($params[5])) : array();
        $_POST['edit_reason'] = $request_params[6];
        $_POST['addbbcode20'] = 100;
        $_POST['post'] = 'submit';
        $_POST['creation_time'] = time()-1;
        $_POST['form_token'] = sha1($_POST['creation_time'] . 'TapatalkApp' . 'posting' . '');
        break;
    
    case 'get_raw_post':
        $tapatalk_handle = 'system';
        $request_file = PHPBB_ROOT_PATH."posting.$phpEx";
        $_GET['mode'] = 'edit';
        $_GET['f'] = 1;
        $_GET['p'] = $request_params[0];
        break;
        
    case 'get_quote_post':
        $tapatalk_handle = 'system';
        $request_file = PHPBB_ROOT_PATH."posting.$phpEx";
        $_GET['mode'] = 'quote';
        $_GET['f'] = 1;
        $_GET['p'] = $request_params[0];
        break;
        
    case 'get_user_info':
        $tapatalk_handle = 'system';
        $request_file = PHPBB_ROOT_PATH."memberlist.$phpEx";
        $_GET['mode'] = 'viewprofile';
        if(!empty($request_params[1]))
        {
            $_GET['u'] = $request_params[1];
        }
        else
        {
            $_GET['un']= $request_params[0];
        }
        break;
    case 'get_online_users':
        $tapatalk_handle = 'system';
        $request_file = PHPBB_ROOT_PATH."viewonline.$phpEx";
        break;
     
    case 'get_thread_by_unread':
        $tapatalk_handle = 'system';
        $request_file = PHPBB_ROOT_PATH."viewtopic.$phpEx";
        $_GET['st'] = 0;
        $_GET['sk'] = 't';
        $_GET['sd'] = 'a';
        $_GET['t'] = $request_params[0];
        $_GET['view'] = 'unread';
        $perpage = $request_params[1];
        $return_html = isset($request_params[2]) ? $request_params[2] : false;
        $request_method = 'get_thread';
        break;
        
    case 'get_thread_by_post':
        $tapatalk_handle = 'system';
        $request_file = PHPBB_ROOT_PATH."viewtopic.$phpEx";
        $_GET['st'] = 0;
        $_GET['sk'] = 't';
        $_GET['sd'] = 'a';
        $_GET['p'] = $request_params[0];
        $_GET['view'] = 'unread';
        $perpage = $request_params[1];
        $return_html = isset($request_params[2]) ? $request_params[2] : false;
        $request_method = 'get_thread';
        break;    
    
    case 'search':
        $request_file = 'search';
        $search_filter = $request_params[0];
        $page    = isset($search_filter['page']) ? $search_filter['page'] : 1;
        $perpage = isset($search_filter['perpage']) ? $search_filter['perpage'] : 20;
        $_GET['start']      = max(($page - 1) * $perpage, 0);
        $_GET['submit']     = 'Search';
        $_GET['sr']         = isset($search_filter['showposts']) && $search_filter['showposts'] ? 'posts' : 'topics';
        $_GET['sf']         = isset($search_filter['titleonly']) && $search_filter['titleonly'] ? 'titleonly' : 'all';
        $_GET['keywords']   = isset($search_filter['keywords'])   ? $search_filter['keywords']  : '';
        $_GET['searchid']   = isset($search_filter['searchid'])   ? $search_filter['searchid']  : '';
        $_GET['author_id']  = isset($search_filter['userid'])     ? $search_filter['userid']    : '';
        $_GET['author']     = isset($search_filter['searchuser']) ? $search_filter['searchuser']: '';
        $_GET['fid']        = isset($search_filter['forumid'])    ? array($search_filter['forumid'])   : '';
        if (!empty($search_filter['threadid']))
        {
            $_GET['t'] = $search_filter['threadid'];
            $_GET['sf'] = 'msgonly';
            $_GET['showresults'] = 'posts';
        }
        
        if (!empty($search_filter['searchtime']) && is_numeric($search_filter['searchtime']))
        {
            $_GET['st'] = $search_filter['searchtime']/86400;
        }
        
        if (isset($search_filter['only_in']) && is_array($search_filter['only_in']))
        {
            $_GET['fid'] = $search_filter['only_in'];
        }
        if (isset($search_filter['not_in']) && is_array($search_filter['not_in']))
        {
        	//add for tapatalk
			foreach ($search_filter['not_in'] as $key => $value)
			{
				if($value != 0)
				{
					$ex_fid_ary[]=$search_filter['not_in'][$key];
				}
			}
            $_GET['exclude'] = array_map('intval', $ex_fid_ary);
        }
        break;
        
    case 'search_topic':
        $request_file = 'search';
        process_page($request_params[1], $request_params[2]);
        $perpage = $limit;
        $_GET['start'] = max(($page - 1) * $perpage, 0);
        
        $_GET['submit'] = 'Search';
        $_GET['sr'] = 'topics';
        $_GET['sf'] = 'all';
        $_GET['keywords'] = $request_params[0];
        $_GET['searchid'] = isset($request_params[2]) ? $request_params[2] : '';
        $request_method = 'search';
        break;
        
    case 'search_post':
        $request_file = 'search';
        process_page($request_params[1], $request_params[2]);
        $perpage = $limit;
        $_GET['start'] = max(($page - 1) * $perpage, 0);
        
        $_GET['submit'] = 'Search';
        $_GET['sr'] = 'posts';
        $_GET['sf'] = 'all';
        $_GET['keywords'] = $request_params[0];
        $_GET['searchid'] = isset($request_params[2]) ? $request_params[2] : '';
        $request_method = 'search';
        break;
        
    case 'get_unread_topic':
        $request_file = 'search';
        process_page($request_params[0], $request_params[1]);
        $perpage = $limit;
        $_GET['start'] = max(($page - 1) * $perpage, 0);
        
        $_GET['sr'] = 'topics';
        $_GET['submit'] = 'Search';
        $_GET['search_id'] = 'unreadposts';
        
        if (isset($request_params[3]['only_in']) && is_array($request_params[3]['only_in']))
        {
            $_GET['fid'] = array_map('intval', $request_params[3]['only_in']);
        }
        
        if (isset($request_params[3]['not_in']) && is_array($request_params[3]['not_in']))
        {
            $_GET['exclude'] = array_map('intval', $request_params[3]['not_in']);
        }
        $request_method = 'search';
        break;
        
    case 'get_participated_topic':
        $request_file = 'search';
        process_page($request_params[1], $request_params[2]);
        $perpage = $limit;
        $_GET['start'] = max(($page - 1) * $perpage, 0);
        
        
        $_GET['sr'] = 'topics';
        $_GET['submit'] = 'Search';
        
        if (isset($request_params[4]) && $request_params[4]) {
            $_GET['author_id'] = intval($request_params[4]);
        } else if (isset($request_params[0]) && $request_params[0]) {
            $_GET['author'] = $request_params[0];
        } else {
            $_GET['search_id'] = 'egosearch';
        }
        $request_method = 'search';
        break;

    case 'get_user_topic':
        $request_file = 'search';
        $_GET['start'] = 0;
        $perpage = 50;
        
        $_GET['sr'] = 'topics';
        $_GET['submit'] = 'Search';
        $_GET['sf'] = 'firstpost';
        
        if (isset($request_params[1]) && $request_params[1]) {
            $_GET['author_id'] = intval($request_params[1]);
        } else if (isset($request_params[0]) && $request_params[0]) {
            $_GET['author'] = $request_params[0];
        } else {
            $_GET['search_id'] = 'egosearch';
        }
        $request_method = 'search';
        $search_method = 'get_user_topic';
        break;

    case 'get_user_reply_post':
        $request_file = 'search';
        $_GET['start'] = 0;
        $perpage = 50;
        
        $_GET['sr'] = 'posts';
        $_GET['submit'] = 'Search';
        
        if (isset($request_params[1]) && $request_params[1]) {
            $_GET['author_id'] = intval($request_params[1]);
        } else if (isset($request_params[0]) && $request_params[0]) {
            $_GET['author'] = $request_params[0];
        } else {
            $_GET['search_id'] = 'egosearch';
        }
        $request_method = 'search';
        $search_method = 'get_user_reply_post';
        break;
    
    case 'get_subscribed_topic':
        $request_file = 'search';
        process_page($request_params[0], $request_params[1]);
        $perpage = $limit;
        $_GET['start'] = max(($page - 1) * $perpage, 0);
        
        $_GET['sr'] = 'topics';
        $_GET['submit'] = 'Search';
        $_GET['search_id'] = 'subscribedtopics';
        $request_method = 'search';
        break;
    
    case 'get_latest_topic':
        $request_file = 'search';
        process_page($request_params[0], $request_params[1]);
        $perpage = $limit;
        $_GET['start'] = max(($page - 1) * $perpage, 0);
        
        
        $_GET['sr'] = 'topics';
        $_GET['st'] = 0;
        $_GET['sk'] = 't';
        $_GET['sd'] = 'd';
        $_GET['search_id'] = 'newposts';
        
        if (isset($request_params[3]['only_in']) && is_array($request_params[3]['only_in']))
        {
            $_GET['fid'] = array_map('intval', $request_params[3]['only_in']);
        }
        
        if (isset($request_params[3]['not_in']) && is_array($request_params[3]['not_in']))
        {
            $_GET['exclude'] = array_map('intval', $request_params[3]['not_in']);
        }
        $request_method = 'search';
        break;
        

    //undo
    case 'get_subscribed_topic':
        $request_file = 'search';
        process_page($request_params[0], $request_params[1]);
        $_GET['page'] = $page;
        $_GET['perpage'] = $limit;
        $_GET['search_id'] = 'subscribedtopics';
        break;
    
    case 'thank_post':
        $_GET['thanks'] = $request_params[0];
        $_GET['p'] = $request_params[0];
        $request_file = 'viewtopic';
        break;
        
    case 'm_stick_topic':
        $_GET['t'] = $request_params[0];
        $_GET['quickmod'] = 1;
        $_GET['confirm_key'] = $user->data['user_last_confirm_key'] = $user->session_id;
        $_POST['topic_id_list'] = array($request_params[0]);
        $_POST['action'] = $request_params[1] == 1 ? 'make_sticky' : 'make_normal';
        $_POST['user_id'] = $user->data['user_id'];
        $_POST['confirm_uid'] = $user->data['user_id'];
        $_POST['sess'] = $user->session_id;
        $_POST['confirm'] = $user->lang['YES'];
        break;
    case 'm_close_topic':
        $_GET['t'] = $request_params[0];
        $_GET['quickmod'] = 1;
        $_GET['confirm_key'] = $user->data['user_last_confirm_key'] = $user->session_id;
        $_POST['topic_id_list'] = array($request_params[0]);
        $_POST['action'] = $request_params[1] == 2 ? 'lock' : 'unlock';
        $_POST['user_id'] = $user->data['user_id'];
        $_POST['confirm_uid'] = $user->data['user_id'];
        $_POST['sess'] = $user->session_id;
        $_POST['confirm'] = $user->lang['YES'];
        break;
    case 'm_delete_topic':
        $_GET['t'] = $request_params[0];
        $_GET['quickmod'] = 1;
        $_GET['confirm_key'] = $user->data['user_last_confirm_key'] = $user->session_id;
        $_POST['topic_id_list'] = array($request_params[0]);
        $_POST['action'] = 'delete_topic';
        $_POST['user_id'] = $user->data['user_id'];
        $_POST['confirm_uid'] = $user->data['user_id'];
        $_POST['sess'] = $user->session_id;
        $_POST['confirm'] = $user->lang['YES'];
        break;
    case 'm_delete_post':
        $request_file = 'delete_post';
        $_GET['p'] = $request_params[0];
        break;
    case 'm_move_topic':
    	$_GET['quickmod'] = 1;
    	$topic_ids = explode(',', $request_params[0]);
    	if(count($topic_ids) > 1)
    	{
    		$_POST['topic_id_list'] = $topic_ids;
    		$_GET['t'] = $topic_ids[0];
    		$_GET['quickmod'] = 0;
    	}
    	else 
    	{
    		$_GET['t'] = $request_params[0];
    		$_POST['topic_id_list'] = array($request_params[0]);
    	}              
        $_GET['confirm_key'] = $user->data['user_last_confirm_key'] = $user->session_id;
        $_POST['to_forum_id'] = $request_params[1];
        if(isset($request_params[2]))
        {
        	$_POST['move_leave_shadow'] = $request_params[2];
        }
        //$_POST['topic_id_list'] = array($request_params[0]);
        $_POST['action'] = 'move';
        $_POST['user_id'] = $user->data['user_id'];
        $_POST['confirm_uid'] = $user->data['user_id'];
        $_POST['sess'] = $user->session_id;
        $_POST['confirm'] = $user->lang['YES'];
        break;
    case 'm_move_post':
        if (empty($request_params[1])) {
            $_POST['action'] = 'split_all';
            $_POST['to_forum_id'] = $request_params[3];
            $_POST['subject'] = $request_params[2];
        } else {
            $_POST['action'] = 'merge_posts';
            $_POST['to_topic_id'] = $request_params[1];
        }
        $post_ids = explode(',', $request_params[0]);
    	if(count($post_ids) > 1)
    	{
    		$_POST['post_id_list'] = $post_ids;
    		$_GET['p'] = $post_ids[0];
    	}
    	else 
    	{
    		$_GET['p'] = $request_params[0];
    		$_POST['post_id_list'] = array($request_params[0]);
    	}  
        //$_GET['p'] = $request_params[0];
        $_GET['confirm_key'] = $user->data['user_last_confirm_key'] = $user->session_id;
        $_POST['i'] = 'main';
        $_POST['mode'] = 'topic_view';
        //$_POST['post_id_list'] = array($request_params[0]);
        $_POST['user_id'] = $user->data['user_id'];
        $_POST['confirm_uid'] = $user->data['user_id'];
        $_POST['sess'] = $user->session_id;
        $_POST['confirm'] = $user->lang['YES'];
        break;
    case 'm_merge_topic':
    	$topic_ids = explode(',', $request_params[0]);
    	if(count($topic_ids) > 1)
    	{
    		$_POST['topic_id_list'] = $topic_ids;
    		$_GET['t'] = $topic_ids[0];
    	}
    	else 
    	{
    		$_GET['t'] = $request_params[0];
    		$_POST['topic_id_list'] = array($request_params[0]);
    	}  
        //$_GET['t'] = $request_params[0];
        $_GET['confirm_key'] = $user->data['user_last_confirm_key'] = $user->session_id;
        $_POST['i'] = 'main';
        $_POST['mode'] = 'forum_view';
        //$_POST['topic_id_list'] = array($request_params[0]);
        $_POST['action'] = 'merge_topics';
        $_POST['to_topic_id'] = $request_params[1];
        $_POST['user_id'] = $user->data['user_id'];
        $_POST['confirm_uid'] = $user->data['user_id'];
        $_POST['sess'] = $user->session_id;
        $_POST['confirm'] = $user->lang['YES'];
        break;
    case 'm_approve_topic':
        $_GET['t'] = $request_params[0];
        $_GET['confirm_key'] = $user->data['user_last_confirm_key'] = $user->session_id;
        $_POST['notify_poster'] = 'on';
        $_POST['i'] = 'queue';
        $_POST['mode'] = 'unapproved_topics';
        $_POST['action'] = 'approve';
        $_POST['user_id'] = $user->data['user_id'];
        $_POST['confirm_uid'] = $user->data['user_id'];
        $_POST['sess'] = $user->session_id;
        $_POST['confirm'] = $user->lang['YES'];
        break;
    case 'm_approve_post':
        $_GET['p'] = $request_params[0];
        $_GET['confirm_key'] = $user->data['user_last_confirm_key'] = $user->session_id;
        $_POST['notify_poster'] = 'on';
        $_POST['i'] = 'queue';
        $_POST['post_id_list'] = array($request_params[0]);
        $_POST['mode'] = 'unapproved_topics';
        $_POST['action'] = 'approve';
        $_POST['user_id'] = $user->data['user_id'];
        $_POST['confirm_uid'] = $user->data['user_id'];
        $_POST['sess'] = $user->session_id;
        $_POST['confirm'] = $user->lang['YES'];
        break;
    case 'm_ban_user':
    	$_POST['ban'] = $request_params[0];
        $_POST['bansubmit'] = 1;
        $_POST['banreason'] = $request_params[2]; 
        if(isset($request_params[3]) && intval($request_params[3]) > time())
        {
        	$_POST['banlength'] = intval(($request_params[3] - time())/60);
        }
       
        $_GET['confirm_key'] = $user->data['user_last_confirm_key'] = $user->session_id;
        $_POST['i'] = 'ban';
        $_POST['user_id'] = $user->data['user_id'];
        $_POST['confirm_uid'] = $user->data['user_id'];
        $_POST['sess'] = $user->session_id;
        $_POST['confirm'] = $user->lang['YES'];
        break;
    case 'm_get_moderate_topic':
        if ($params_num == 2) {
            process_page($request_params[0], $request_params[1]);
        } elseif ($params_num == 3) {
            process_page($request_params[1], $request_params[2]);
        } else {
            $start = 0;
            $limit = 50;
        }
        $config['topics_per_page'] = $limit;
        $_GET['start'] = $start;
        $_GET['i'] = 'queue';
        $_GET['mode'] = 'unapproved_topics';
        break;
    case 'm_get_moderate_post':
        if ($params_num == 2) {
            process_page($request_params[0], $request_params[1]);
        } elseif ($params_num == 3) {
            process_page($request_params[1], $request_params[2]);
        } else {
            $start = 0;
            $limit = 50;
        }
        $config['topics_per_page'] = $limit;
        $_GET['start'] = $start;
        $_GET['i'] = 'queue';
        $_GET['mode'] = 'unapproved_posts';
        break;
    case 'm_get_report_post':
        process_page($request_params[0], $request_params[1]);
        $config['topics_per_page'] = $limit;
        $_GET['start'] = $start;
        $_GET['i'] = 'reports';
        $_GET['mode'] = 'reports';
        break;
    case 'm_close_report':
    	$_POST['action'] = 'close';
    	$_POST['i'] = 'reports';
    	$_POST['mode'] = 'reports';
    	$_POST['post_id_list'] = explode(',', $request_params[0]);
    	$_POST['sess'] = $user->session_id;
        $_POST['confirm'] = $user->lang['YES'];
    	$_POST['confirm_uid'] = $user->data['user_id'];
    	$_GET['confirm_key'] = $user->data['user_last_confirm_key'] = $user->session_id;
    	break;
    case 'register':
    	$_POST['creation_time'] = time();
    	$_POST['username'] = $request_params[0];
    	$_POST['new_password'] = $request_params[1];
    	$_POST['password_confirm'] = $request_params[1];
    	$_POST['email'] = $request_params[2];
    	if(count($request_params) == 5)
    	{
    		$_POST['tt_token'] = $request_params[3];
    		$_POST['tt_code'] = $request_params[4];
    	}
    	$_POST['submit'] = 'Submit';
    	break;
    case 'update_password':
    	$_POST['creation_time'] = time();
		$_POST['cur_password'] = !isset($request_params[2]) ? $request_params[0] : 'true';
    	$_POST['new_password'] = !isset($request_params[2]) ? $request_params[1] : $request_params[0] ;
    	$_POST['password_confirm'] = !isset($request_params[2]) ? $request_params[1] : $request_params[0] ;
    	if(isset($request_params[2]))
    	{
    		$_POST['tt_token'] = $request_params[1];
    		$_POST['tt_code'] = $request_params[2]; 
    	}
    	break;
    case 'update_email':
    	$request_file = "update_password";
    	$_POST['creation_time'] = time();
		$_POST['cur_password'] = !isset($request_params[2]) ? $request_params[0] : 'true';
    	$_POST['email'] = !isset($request_params[2]) ? $request_params[1] : $request_params[0] ;
    	$_POST['email_confirm'] = !isset($request_params[2]) ? $request_params[1] : $request_params[0] ;
    	if(isset($request_params[2]))
    	{
    		$_POST['tt_token'] = $request_params[1];
    		$_POST['tt_code'] = $request_params[2]; 
    	}
    	break;
    case 'forget_password':
    	$_POST['username'] = $request_params[0];
    	$_POST['tt_token'] = $request_params[1];
    	$_POST['tt_code'] = $request_params[2];
    	break;
    case 'sign_in':
    	$_POST['token'] = $request_params[0];
    	$_POST['code'] = $request_params[1];
    	$_POST['email'] = $request_params[2];
    	$_POST['username'] = $request_params[3];
    	$_POST['password'] = $request_params[4];
    	break;
    case 'prefetch_account':
    	$_POST['email'] = $request_params[0];
    	break;
    case 'search_user':
    	$_POST['username'] = $request_params[0];
    	$_POST['email'] = $request_params[0];
    	$_POST['page'] = $request_params[1];
    	$_POST['perpage'] = $request_params[2];
    	break;
    case 'ignore_user':
    	if(!isset($request_params[1]) || $request_params[1] == 1)
    	{
    		$_POST['add'] = $request_params[0];
    		$_POST['mode'] = 'foes';
    		$_POST['submit'] = 1;
    	}
    	else 
    	{
    		$_POST['usernames'] = explode(',', $request_params[0]);
    		$_POST['submit'] = 'Submit';
    	}
    	break;
    case 'get_recommended_user':
    	$_POST['page'] = !empty($request_params[0]) ? $request_params [0] : '1';
    	$_POST['perpage'] = isset($request_params[1]) ? $request_params[1] : '20';
    	$_POST['mode'] = isset($request_params[2]) ? $request_params[2] : 1;
    	break;
}

foreach($_GET  as $key => $value) $_REQUEST[$key] = $value;
foreach($_POST as $key => $value) $_REQUEST[$key] = $value;

error_reporting(MOBIQUO_DEBUG);
