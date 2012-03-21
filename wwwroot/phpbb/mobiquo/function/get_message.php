<?php
/**
*
* @copyright (c) 2009 Quoord Systems Limited
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

defined('IN_MOBIQUO') or exit;

function get_message_func($xmlrpc_params)
{
    global $db, $user, $config, $template, $phpbb_root_path, $phpEx;
    
    $user->setup('ucp');
    
    $params = php_xmlrpc_decode($xmlrpc_params);
    
    if (!isset($params[0]))     // message id undefine
    {
        return get_error(1);
    }

    // get folder id from parameters
    $msg_id = $params[0];
    
    // Only registered users can go beyond this point
    if (!$user->data['is_registered'])
    {
        return get_error(9);
    }
    
    // Is PM disabled?
    if (!$config['allow_privmsg'])
    {
        return get_error(21);
    }
    
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

    if (!$message_row)
    {
        return get_error(20);
    }

    // Update unread status
    $user->add_lang('posting');
    include_once($phpbb_root_path . 'includes/functions_privmsgs.' . $phpEx);
    update_unread_status($message_row['pm_unread'], $message_row['msg_id'], $user->data['user_id'], $message_row['folder_id']);
    
    include($phpbb_root_path . 'includes/ucp/ucp_pm_viewmessage.' . $phpEx);
    view_message('', '', $message_row['folder_id'], $msg_id, '', $message_row);
    
    $msg_to = array();
    foreach ($template->_tpldata['to_recipient'] as $address_row)
    {
        $msg_to[] = new xmlrpcval(array('username' => new xmlrpcval($address_row['NAME'], 'base64')), 'struct');
    }

    $sent_date  = mobiquo_iso8601_encode($message_row['message_time']);
    $icon_url   = ($user->optionget('viewavatars')) ? get_user_avatar_url($message_row['user_avatar'], $message_row['user_avatar_type']) : '';
    $msg_subject = html_entity_decode(strip_tags(censor_text($message_row['message_subject'])));
    $msg_body = post_html_clean(parse_quote($template->_rootref['MESSAGE']));

    $result = new xmlrpcval(array(
        'msg_from'      => new xmlrpcval($message_row['username'], 'base64'),
        'msg_to'        => new xmlrpcval($msg_to, 'array'),
        'icon_url'      => new xmlrpcval($icon_url),
        'sent_date'     => new xmlrpcval($sent_date,'dateTime.iso8601'),
        'msg_subject'   => new xmlrpcval($msg_subject, 'base64'),
        'text_body'     => new xmlrpcval($msg_body, 'base64')
    ), 'struct');
    
    return new xmlrpcresp($result);
}
