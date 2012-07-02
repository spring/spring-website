<?php

defined('IN_MOBIQUO') or exit;

include('./include/user.class.php');
$user = new tapa_user;
$user->session_begin();
$auth->acl($user->data);
$user->setup();
$phpbb_home = generate_board_url().'/';

header('Mobiquo_is_login:'.($user->data['is_registered'] ? 'true' : 'false'));

if ($user->data['user_new_privmsg'])
{
    include_once($phpbb_root_path . 'includes/functions_privmsgs.' . $phpEx);
    place_pm_into_folder($global_privmsgs_rules);
}

mobi_parse_requrest();

switch ($request_method) {
    case 'thank_post':
        $_GET['thanks'] = $request_params[0];
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
        $_GET['p'] = $request_params[0];
        $_GET['quickmod'] = 1;
        $_GET['confirm_key'] = $user->data['user_last_confirm_key'] = $user->session_id;
        $_POST['post_id_list'] = array($request_params[0]);
        $_POST['action'] = 'delete_post';
        $_POST['user_id'] = $user->data['user_id'];
        $_POST['confirm_uid'] = $user->data['user_id'];
        $_POST['sess'] = $user->session_id;
        $_POST['confirm'] = $user->lang['YES'];
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
