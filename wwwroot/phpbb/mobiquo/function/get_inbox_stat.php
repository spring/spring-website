<?php
/**
*
* @copyright (c) 2009 Quoord Systems Limited
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

defined('IN_MOBIQUO') or exit;

function get_inbox_stat_func()
{
    global $db, $user, $config;
    
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
    
    // get unread count in inbox only
    $sql = 'SELECT COUNT(msg_id) as num_messages
            FROM ' . PRIVMSGS_TO_TABLE . '
            WHERE pm_unread = 1
            AND folder_id = ' . PRIVMSGS_INBOX . '
            AND user_id = ' . $user->data['user_id'];
    $result = $db->sql_query($sql);
    $unread_num = (int) $db->sql_fetchfield('num_messages');
    $db->sql_freeresult($result);
    
    $result = new xmlrpcval(array(
        'inbox_unread_count' => new xmlrpcval($unread_num, 'int')
    ), 'struct');

    return new xmlrpcresp($result);
}
