<?php
/**
*
* @copyright (c) 2009, 2010, 2011 Quoord Systems Limited
* @license http://opensource.org/licenses/gpl-2.0.php GNU Public License (GPLv2)
*
*/

defined('IN_MOBIQUO') or exit;

function report_post_func($xmlrpc_params)
{
    global $db, $auth, $user, $config;
    
    $user->setup('mcp');
    
    $params = php_xmlrpc_decode($xmlrpc_params);
    
    $pm_id = 0;
    $post_id = intval($params[0]);
    $report_text = utf8_normalize_nfc($params[1]);
    $reason_id   = intval($params[2]) ? intval($params[2]) : 2;
    $forum_id    = intval($params[3]) ? intval($params[3]) : '';
    $user_notify =  true;
    
    if (!$post_id)
    {
        trigger_error('NO_POST_SELECTED');
    }
    
    // Grab all relevant data
    $sql = 'SELECT t.*, p.*
        FROM ' . POSTS_TABLE . ' p, ' . TOPICS_TABLE . " t
        WHERE p.post_id = $post_id
            AND p.topic_id = t.topic_id";
    $result = $db->sql_query($sql);
    $report_data = $db->sql_fetchrow($result);
    $db->sql_freeresult($result);

    if (!$report_data)
    {
        trigger_error('POST_NOT_EXIST');
    }

    $forum_id = (int) ($report_data['forum_id']) ? $report_data['forum_id'] : $forum_id;
    $topic_id = (int) $report_data['topic_id'];

    $sql = 'SELECT *
        FROM ' . FORUMS_TABLE . '
        WHERE forum_id = ' . $forum_id;
    $result = $db->sql_query($sql);
    $forum_data = $db->sql_fetchrow($result);
    $db->sql_freeresult($result);

    if (!$forum_data)
    {
        trigger_error('FORUM_NOT_EXIST');
    }

    // Check required permissions
    $acl_check_ary = array('f_list' => 'POST_NOT_EXIST', 'f_read' => 'USER_CANNOT_READ', 'f_report' => 'USER_CANNOT_REPORT');

    foreach ($acl_check_ary as $acl => $error)
    {
        if (!$auth->acl_get($acl, $forum_id))
        {
            trigger_error($error);
        }
    }
    unset($acl_check_ary);

    if ($report_data['post_reported'])
    {
        trigger_error('ALREADY_REPORTED');
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
        'reason_id'     => (int) $reason_id,
        'post_id'       => $post_id,
        'user_id'       => (int) $user->data['user_id'],
        'user_notify'   => (int) $user_notify,
        'report_closed' => 0,
        'report_time'   => (int) time(),
        'report_text'   => (string) $report_text
    );
    
    if (version_compare($config['version'], '3.0.6', '>='))
        $sql_ary['pm_id'] = $pm_id;

    $sql = 'INSERT INTO ' . REPORTS_TABLE . ' ' . $db->sql_build_array('INSERT', $sql_ary);
    $db->sql_query($sql);
    $report_id = $db->sql_nextid();

    $sql = 'UPDATE ' . POSTS_TABLE . '
        SET post_reported = 1
        WHERE post_id = ' . $post_id;
    $db->sql_query($sql);

    if (!$report_data['topic_reported'])
    {
        $sql = 'UPDATE ' . TOPICS_TABLE . '
            SET topic_reported = 1
            WHERE topic_id = ' . $report_data['topic_id'] . '
                OR topic_moved_id = ' . $report_data['topic_id'];
        $db->sql_query($sql);
    }

    $result = new xmlrpcval(array('result' => new xmlrpcval(true, 'boolean')), 'struct');
    return new xmlrpcresp($result);
}
