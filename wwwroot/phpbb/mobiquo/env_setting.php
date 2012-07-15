<?php

defined('IN_MOBIQUO') or exit;

mobi_parse_requrest();

if (in_array($request_method, array('login', 'logout_user', 'get_config')))
{
    define('IN_CHECK_BAN', 1);
}

include('./include/user.class.php');
$user = new tapa_user;
$user->session_begin();
$auth->acl($user->data);
$user->setup();
$phpbb_home = generate_board_url().'/';
$can_subscribe = ($config['email_enable'] || $config['jab_enable']) && $config['allow_topic_notify'] && $user->data['is_registered'];

header('Mobiquo_is_login:'.($user->data['is_registered'] ? 'true' : 'false'));

if ($user->data['user_new_privmsg'])
{
    include_once($phpbb_root_path . 'includes/functions_privmsgs.' . $phpEx);
    place_pm_into_folder($global_privmsgs_rules);
}

$request_file = $request_method;

switch ($request_method)
{
    case 'get_thread':
        $request_file = 'viewtopic';
        $_GET['st'] = 0;
        $_GET['sk'] = 't';
        $_GET['sd'] = 'a';
        $_GET['t'] = $request_params[0];
        list($_GET['start'], $_GET['limit']) = process_page($request_params[1], $request_params[2]);
        $return_html = isset($request_params[3]) ? $request_params[3] : false;
        break;
    case 'get_thread_by_unread':
        $request_file = 'viewtopic';
        $_GET['st'] = 0;
        $_GET['sk'] = 't';
        $_GET['sd'] = 'a';
        $_GET['t'] = $request_params[0];
        $_GET['limit'] = $request_params[1];
        $_GET['view'] = 'unread';
        $return_html = isset($request_params[2]) ? $request_params[2] : false;
        break;
    case 'get_thread_by_post':
        $request_file = 'viewtopic';
        $_GET['st'] = 0;
        $_GET['sk'] = 't';
        $_GET['sd'] = 'a';
        $_GET['p'] = $request_params[0];
        $_GET['limit'] = $request_params[1];
        $return_html = isset($request_params[2]) ? $request_params[2] : false;
        break;
    
    
    case 'search':
        $include_topic_num = true;
        $search_filter = $request_params[0];
        $_GET['page'] = isset($search_filter['page']) ? $search_filter['page'] : 1;
        $_GET['perpage'] = isset($search_filter['perpage']) ? $search_filter['perpage'] : 20;
        $_GET['submit'] = 'Search';
        $_GET['sr'] = isset($search_filter['showposts']) && $search_filter['showposts'] ? 'posts' : 'topics';
        $_GET['sf'] = isset($search_filter['titleonly']) && $search_filter['titleonly'] ? 'titleonly' : 'all';
        isset($search_filter['searchid']) && $_GET['searchid'] = $search_filter['searchid'];
        isset($search_filter['keywords']) && $_GET['keywords'] = $search_filter['keywords'];
        isset($search_filter['searchuser']) && $_GET['author'] = $search_filter['searchuser'];
        isset($search_filter['userid']) && $_GET['author_id'] = $search_filter['userid'];
        isset($search_filter['forumid']) && $_GET['fid'] = array($search_filter['forumid']);
        
        if (isset($search_filter['threadid']))
        {
            $_GET['t'] = $search_filter['threadid'];
            $_GET['sf'] = 'msgonly';
            $_GET['showresults'] = 'posts';
        }
        
        if (isset($search_filter['searchtime']) && is_numeric($search_filter['searchtime']))
        {
            $_GET['st'] = $search_filter['searchtime']/86400;
        }
        
        if (isset($search_filter['only_in']) && is_array($search_filter['only_in']))
        {
            $_GET['fid'] = array_map('intval', $search_filter['only_in']);
        }
        
        if (isset($search_filter['not_in']) && is_array($search_filter['not_in']))
        {
            $_GET['exclude'] = array_map('intval', $search_filter['not_in']);
        }
        break;
    case 'search_topic':
        $include_topic_num = true;
        $request_file = 'search';
        process_page($request_params[1], $request_params[2]);
        $_GET['page'] = $page;
        $_GET['perpage'] = $limit;
        $_GET['submit'] = 'Search';
        $_GET['sr'] = 'topics';
        $_GET['sf'] = 'all';
        $_GET['keywords'] = $request_params[0];
        break;
    case 'search_post':
        $include_topic_num = true;
        $request_file = 'search';
        process_page($request_params[1], $request_params[2]);
        $_GET['page'] = $page;
        $_GET['perpage'] = $limit;
        $_GET['submit'] = 'Search';
        $_GET['sr'] = 'posts';
        $_GET['sf'] = 'all';
        $_GET['keywords'] = $request_params[0];
        break;
    case 'get_latest_topic':
        $include_topic_num = true;
        $request_file = 'search';
        process_page($request_params[0], $request_params[1]);
        $_GET['page'] = $page;
        $_GET['perpage'] = $limit;
        $_GET['search_id'] = 'latesttopics';
        
        if (isset($request_params[3]['only_in']) && is_array($request_params[3]['only_in']))
        {
            $_GET['fid'] = array_map('intval', $request_params[3]['only_in']);
        }
        
        if (isset($request_params[3]['not_in']) && is_array($request_params[3]['not_in']))
        {
            $_GET['exclude'] = array_map('intval', $request_params[3]['not_in']);
        }
        break;
    case 'get_unread_topic':
        $include_topic_num = true;
        $request_file = 'search';
        process_page($request_params[0], $request_params[1]);
        $_GET['page'] = $page;
        $_GET['perpage'] = $limit;
        $_GET['search_id'] = 'unreadposts';
        
        if (isset($request_params[3]['only_in']) && is_array($request_params[3]['only_in']))
        {
            $_GET['fid'] = array_map('intval', $request_params[3]['only_in']);
        }
        
        if (isset($request_params[3]['not_in']) && is_array($request_params[3]['not_in']))
        {
            $_GET['exclude'] = array_map('intval', $request_params[3]['not_in']);
        }
        break;
    case 'get_participated_topic':
        $include_topic_num = true;
        $request_file = 'search';
        process_page($request_params[1], $request_params[2]);
        $_GET['page'] = $page;
        $_GET['perpage'] = $limit;
        $_GET['sr'] = 'topics';
        $_GET['submit'] = 'Search';
        $_GET['search_id'] = 'tt_user_search';
        
        if (isset($request_params[4]) && $request_params[4]) {
            $_GET['author_id'] = intval($request_params[4]);
        } else if (isset($request_params[0]) && $request_params[0]) {
            $_GET['author'] = $request_params[0];
        } else {
            $_GET['search_id'] = 'egosearch';
        }
        break;
    case 'get_user_topic':
        $request_file = 'search';
        $_GET['page'] = 1;
        $_GET['perpage'] = 50;
        $_GET['sr'] = 'topics';
        $_GET['submit'] = 'Search';
        $_GET['sf'] = 'firstpost';
        $_GET['search_id'] = 'tt_user_search';
        
        if (isset($request_params[1]) && $request_params[1]) {
            $_GET['author_id'] = intval($request_params[1]);
        } else if (isset($request_params[0]) && $request_params[0]) {
            $_GET['author'] = $request_params[0];
        } else {
            $_GET['search_id'] = 'egosearch';
        }
        break;
    case 'get_user_reply_post':
        $request_file = 'search';
        $_GET['page'] = 1;
        $_GET['perpage'] = 50;
        $_GET['sr'] = 'posts';
        $_GET['submit'] = 'Search';
        $_GET['search_id'] = 'tt_user_search';
        
        if (isset($request_params[1]) && $request_params[1]) {
            $_GET['author_id'] = intval($request_params[1]);
        } else if (isset($request_params[0]) && $request_params[0]) {
            $_GET['author'] = $request_params[0];
        } else {
            $_GET['search_id'] = 'egosearch';
        }
        break;
    case 'get_subscribed_topic':
        $include_topic_num = true;
        $request_file = 'search';
        process_page($request_params[0], $request_params[1]);
        $_GET['page'] = $page;
        $_GET['perpage'] = $limit;
        $_GET['search_id'] = 'subscribedtopics';
    
    
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
        $_GET['t'] = $request_params[0];
        $_GET['quickmod'] = 1;
        $_GET['confirm_key'] = $user->data['user_last_confirm_key'] = $user->session_id;
        $_POST['to_forum_id'] = $request_params[1];
        $_POST['topic_id_list'] = array($request_params[0]);
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
        $_GET['p'] = $request_params[0];
        $_GET['confirm_key'] = $user->data['user_last_confirm_key'] = $user->session_id;
        $_POST['i'] = 'main';
        $_POST['mode'] = 'topic_view';
        $_POST['post_id_list'] = array($request_params[0]);
        $_POST['user_id'] = $user->data['user_id'];
        $_POST['confirm_uid'] = $user->data['user_id'];
        $_POST['sess'] = $user->session_id;
        $_POST['confirm'] = $user->lang['YES'];
        break;
    case 'm_merge_topic':
        $_GET['t'] = $request_params[0];
        $_GET['confirm_key'] = $user->data['user_last_confirm_key'] = $user->session_id;
        $_POST['i'] = 'main';
        $_POST['mode'] = 'forum_view';
        $_POST['topic_id_list'] = array($request_params[0]);
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
        if ($request_params[1] == 2) {
            $_POST['unbansubmit'] = 1;
        } else {
            $_POST['ban'] = $request_params[0];
            $_POST['bansubmit'] = 1;
            $_POST['banreason'] = $request_params[2];
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
}

foreach($_GET  as $key => $value) $_REQUEST[$key] = $value;
foreach($_POST as $key => $value) $_REQUEST[$key] = $value;

error_reporting(MOBIQUO_DEBUG);
