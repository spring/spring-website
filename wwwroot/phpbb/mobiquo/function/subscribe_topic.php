<?php
/**
*
* @copyright (c) 2009, 2010, 2011 Quoord Systems Limited
* @license http://opensource.org/licenses/gpl-2.0.php GNU Public License (GPLv2)
*
*/

defined('IN_MOBIQUO') or exit;

function subscribe_topic_func($xmlrpc_params)
{
    global $db, $user;
    
    $user->setup('viewtopic');
    
    $params = php_xmlrpc_decode($xmlrpc_params);
    
    // get topic id from parameters
    $topic_id = intval($params[0]);
    if (!$topic_id) trigger_error('NO_TOPIC');
    $user_id = $user->data['user_id'];
    $s_result = false;

    // Is user login?
    if ($user_id != ANONYMOUS)
    {
        $sql = 'SELECT notify_status
                FROM ' . TOPICS_WATCH_TABLE . "
                WHERE topic_id = $topic_id
                AND user_id = $user_id";
        $result = $db->sql_query($sql);

        $notify_status = ($row = $db->sql_fetchrow($result)) ? $row['notify_status'] : NULL;
        $db->sql_freeresult($result);

        if (!is_null($notify_status) && $notify_status !== '')
        {
            if ($notify_status)
            {
                $sql = 'UPDATE ' . TOPICS_WATCH_TABLE . "
                        SET notify_status = 0
                        WHERE topic_id = $topic_id
                        AND user_id = $user_id";
                $db->sql_query($sql);
            }
        }
        else
        {
            $sql = 'INSERT INTO ' . TOPICS_WATCH_TABLE . " (user_id, topic_id, notify_status)
                    VALUES ($user_id, $topic_id, 0)";
            $db->sql_query($sql);
            $s_result = true;
        }
    }
    
    $response = new xmlrpcval(
        array(
            'result'        => new xmlrpcval($s_result, 'boolean'),
            'result_text'   => new xmlrpcval($s_result ? '' : 'Subscribe failed', 'base64'),
        ),
        'struct'
    );
    
    return new xmlrpcresp($response);
}
