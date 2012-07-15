<?php
/**
*
* @copyright (c) 2009, 2010, 2011 Quoord Systems Limited
* @license http://opensource.org/licenses/gpl-2.0.php GNU Public License (GPLv2)
*
*/

defined('IN_MOBIQUO') or exit;

function get_message_func($xmlrpc_params)
{
    global $db, $auth, $user, $config, $template, $phpbb_root_path, $phpEx;
    
    $user->setup('ucp');
    
    $params = php_xmlrpc_decode($xmlrpc_params);
    
    if (!$user->data['is_registered']) trigger_error('LOGIN_EXPLAIN_UCP');
    if (!$config['allow_privmsg']) trigger_error('Module not accessible');

    // get msg id from parameters
    $msg_id = intval($params[0]);
    if (!$msg_id) trigger_error('NO_MESSAGE');
    $GLOBALS['return_html'] = isset($params[2]) ? $params[2] : false;
    
    $message_row = array();

    // Get Message user want to see
    $sql = 'SELECT t.*, p.*, u.*
            FROM ' . PRIVMSGS_TO_TABLE . ' t, ' . PRIVMSGS_TABLE . ' p, ' . USERS_TABLE . ' u
            WHERE t.user_id = ' . $user->data['user_id'] . "
            AND p.author_id = u.user_id
            AND t.msg_id = p.msg_id
            AND p.msg_id = $msg_id";
    $result = $db->sql_query($sql);
    $message_row = $db->sql_fetchrow($result);
    $db->sql_freeresult($result);

    if (!$message_row) trigger_error('NO_MESSAGE');
    
    $message_row['message_text'] = preg_replace('/\[b:'.$message_row['bbcode_uid'].'\](.*?)\[\/b:'.$message_row['bbcode_uid'].'\]/si', '[b]$1[/b]', $message_row['message_text']);
    $message_row['message_text'] = preg_replace('/\[i:'.$message_row['bbcode_uid'].'\](.*?)\[\/i:'.$message_row['bbcode_uid'].'\]/si', '[i]$1[/i]', $message_row['message_text']);
    $message_row['message_text'] = preg_replace('/\[u:'.$message_row['bbcode_uid'].'\](.*?)\[\/u:'.$message_row['bbcode_uid'].'\]/si', '[u]$1[/u]', $message_row['message_text']);
    $message_row['message_text'] = preg_replace('/\[color=#(\w{6}):'.$message_row['bbcode_uid'].'\](.*?)\[\/color:'.$message_row['bbcode_uid'].'\]/si', '[color=#$1]$2[/color]', $message_row['message_text']);
    
    // Update unread status
    $user->add_lang('posting');
    include_once($phpbb_root_path . 'includes/functions_privmsgs.' . $phpEx);
    update_unread_status($message_row['pm_unread'], $message_row['msg_id'], $user->data['user_id'], $message_row['folder_id']);
    
    include($phpbb_root_path . 'includes/ucp/ucp_pm_viewmessage.' . $phpEx);
    view_message('', '', $message_row['folder_id'], $msg_id, '', $message_row);
    
    $msg_to = array();
    foreach ($template->_tpldata['to_recipient'] as $address_row)
    {
        $msg_to[] = new xmlrpcval(array(
            'user_id'  => new xmlrpcval($address_row['UG_ID'], 'string'),
            'username' => new xmlrpcval($address_row['NAME'], 'base64'),
        ), 'struct');
    }

    $sent_date  = mobiquo_iso8601_encode($message_row['message_time']);
    $icon_url   = ($user->optionget('viewavatars')) ? get_user_avatar_url($message_row['user_avatar'], $message_row['user_avatar_type']) : '';
    $msg_subject = html_entity_decode(strip_tags(censor_text($message_row['message_subject'])));
    $msg_body = post_html_clean(parse_quote($template->_rootref['MESSAGE']));
    
    if ($config['load_onlinetrack']) {
        $sql = 'SELECT session_user_id, MAX(session_time) as online_time, MIN(session_viewonline) AS viewonline
                FROM ' . SESSIONS_TABLE . '
                WHERE session_user_id=' . $message_row['user_id'] . '
                GROUP BY session_user_id';
        $result = $db->sql_query($sql);
        $online_info = $db->sql_fetchrow($result);
        
        $update_time = $config['load_online_time'] * 60;
        $is_online = (time() - $update_time < $online_info['online_time'] && (($online_info['viewonline']) || $auth->acl_get('u_viewonline'))) ? true : false;
    } else {
        $is_online = false;
    }
    
    $result = new xmlrpcval(array(
        'msg_from'      => new xmlrpcval($message_row['username'], 'base64'),
        'msg_from_id'   => new xmlrpcval($message_row['user_id'], 'string'),
        'msg_to'        => new xmlrpcval($msg_to, 'array'),
        'icon_url'      => new xmlrpcval($icon_url),
        'sent_date'     => new xmlrpcval($sent_date, 'dateTime.iso8601'),
        'timestamp'     => new xmlrpcval($message_row['message_time'], 'string'),
        'msg_subject'   => new xmlrpcval($msg_subject, 'base64'),
        'text_body'     => new xmlrpcval($msg_body, 'base64'),
        'is_online'     => new xmlrpcval($is_online, 'boolean'),
        'allow_smilies' => new xmlrpcval($message_row['enable_smilies'] ? true : false, 'boolean'),
    ), 'struct');
    
    return new xmlrpcresp($result);
}
