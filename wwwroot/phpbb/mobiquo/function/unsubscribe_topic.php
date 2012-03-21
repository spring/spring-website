<?php
/**
*
* @copyright (c) 2009 Quoord Systems Limited
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

defined('IN_MOBIQUO') or exit;

function unsubscribe_topic_func($xmlrpc_params)
{
    global $db, $user;
    
    $params = php_xmlrpc_decode($xmlrpc_params);
    
    if (!isset($params[0]))     // topic id undefine
    {
        return get_error(1);
    }
    else if ($params[0] === 0)  // topic id equal 0
    {
        return get_error(7);
    }

    // get topic id from parameters
    $topic_id = $params[0];
    $user_id = $user->data['user_id'];
    $uns_result = false;

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
            $sql = 'DELETE FROM ' . TOPICS_WATCH_TABLE . "
                WHERE topic_id = $topic_id
                    AND user_id = $user_id";
            $db->sql_query($sql);
            $uns_result = true;
        }
    }
    
    $response = new xmlrpcval(
        array(
            'result'        => new xmlrpcval($uns_result, 'boolean'),
            'result_text'   => new xmlrpcval($uns_result ? '' : 'Unsubscribe failed', 'base64'),
        ),
        'struct'
    );
    
    return new xmlrpcresp($response);
}
