<?php
/**
*
* @copyright (c) 2009, 2010, 2011 Quoord Systems Limited
* @license http://opensource.org/licenses/gpl-2.0.php GNU Public License (GPLv2)
*
*/

defined('IN_MOBIQUO') or exit;

function report_pm_func($xmlrpc_params)
{
    global $db, $user, $config;
    
    $user->setup('mcp');
    
    $params = php_xmlrpc_decode($xmlrpc_params);
    
    $post_id = 0;
    $pm_id = intval($params[0]);
    $report_text = utf8_normalize_nfc($params[1]);
    $reason_id   = intval($params[2]) ? intval($params[2]) : 2;
    $forum_id    = intval($params[3]) ? intval($params[3]) : '';
    $user_notify =  true;
    
    if (!$pm_id || !$config['allow_pm_report'])
    {
        trigger_error('NO_POST_SELECTED');
    }
    
    // Grab all relevant data
    $sql = 'SELECT p.*, pt.*
        FROM ' . PRIVMSGS_TABLE . ' p, ' . PRIVMSGS_TO_TABLE . " pt
        WHERE p.msg_id = $pm_id
            AND p.msg_id = pt.msg_id
            AND (p.author_id = " . $user->data['user_id'] . " OR pt.user_id = " . $user->data['user_id'] . ")";
    $result = $db->sql_query($sql);
    $report_data = $db->sql_fetchrow($result);
    $db->sql_freeresult($result);

    if (!$report_data)
    {
        $user->add_lang('ucp');
        trigger_error('NO_MESSAGE');
    }

    if ($report_data['message_reported'])
    {
        trigger_error('ALREADY_REPORTED_PM');
    }
    
    $sql = 'SELECT *
        FROM ' . REPORTS_REASONS_TABLE . "
        WHERE reason_id = $reason_id";
    $result = $db->sql_query($sql);
    $row = $db->sql_fetchrow($result);
    $db->sql_freeresult($result);

    if (!$row || (!$report_text && strtolower($row['reason_title']) == 'other'))
    {
        trigger_error('EMPTY_REPORT');
    }

    $sql_ary = array(
        'reason_id' => (int) $reason_id,
        'post_id'   => $post_id,
        'pm_id'     => $pm_id,
        'user_id'   => (int) $user->data['user_id'],
        'user_notify'   => (int) $user_notify,
        'report_closed' => 0,
        'report_time'   => (int) time(),
        'report_text'   => (string) $report_text
    );

    $sql = 'INSERT INTO ' . REPORTS_TABLE . ' ' . $db->sql_build_array('INSERT', $sql_ary);
    $db->sql_query($sql);
    $report_id = $db->sql_nextid();

    $sql = 'UPDATE ' . PRIVMSGS_TABLE . '
        SET message_reported = 1
        WHERE msg_id = ' . $pm_id;
    $db->sql_query($sql);

    $sql_ary = array(
        'msg_id'        => $pm_id,
        'user_id'        => ANONYMOUS,
        'author_id'        => (int) $report_data['author_id'],
        'pm_deleted'    => 0,
        'pm_new'        => 0,
        'pm_unread'        => 0,
        'pm_replied'    => 0,
        'pm_marked'        => 0,
        'pm_forwarded'    => 0,
        'folder_id'        => PRIVMSGS_INBOX,
    );

    $sql = 'INSERT INTO ' . PRIVMSGS_TO_TABLE . ' ' . $db->sql_build_array('INSERT', $sql_ary);
    $db->sql_query($sql);

    $result = new xmlrpcval(array('result' => new xmlrpcval(true, 'boolean')), 'struct');
    return new xmlrpcresp($result);
}
