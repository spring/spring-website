<?php
/**
*
* @copyright (c) 2009 Quoord Systems Limited
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

defined('IN_MOBIQUO') or exit;

function delete_message_func($xmlrpc_params)
{
    global $db, $user, $config, $phpbb_root_path, $phpEx;
    
    $params = php_xmlrpc_decode($xmlrpc_params);
    
    if (!isset($params[0]))     // message id undefine
    {
        return get_error(1);
    }

    // get folder id from parameters
    $msg_id = $params[0];
    $user_id = $user->data['user_id'];
    
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
    
    $sql = 'SELECT folder_id
            FROM ' . PRIVMSGS_TO_TABLE . "
            WHERE user_id = $user_id
            AND msg_id = $msg_id";
    $result = $db->sql_query_limit($sql, 1);
    $folder_id = (int) $db->sql_fetchfield('folder_id');
    $db->sql_freeresult($result);
    
    include_once($phpbb_root_path . 'includes/functions_privmsgs.' . $phpEx);
    $result = delete_pm($user_id, $msg_id, $folder_id);
    $response = new xmlrpcval(array(
        'result'      => new xmlrpcval($result, 'boolean'),
        'result_text' => new xmlrpcval('Delete message failed', 'base64'),
    ), 'struct');
    
    return new xmlrpcresp($response);
}
