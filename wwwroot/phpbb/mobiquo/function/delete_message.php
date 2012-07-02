<?php
/**
*
* @copyright (c) 2009, 2010, 2011 Quoord Systems Limited
* @license http://opensource.org/licenses/gpl-2.0.php GNU Public License (GPLv2)
*
*/

defined('IN_MOBIQUO') or exit;

function delete_message_func($xmlrpc_params)
{
    global $db, $user, $config, $phpbb_root_path, $phpEx;
    
    $user->setup('ucp');
    
    $params = php_xmlrpc_decode($xmlrpc_params);
    
    // get folder id from parameters
    $msg_id = intval($params[0]);
    $user_id = $user->data['user_id'];
    
    if (!$msg_id) trigger_error('NO_MESSAGE');
    if (!$user->data['is_registered']) trigger_error('LOGIN_EXPLAIN_UCP');
    
    // Is PM disabled?
    if (!$config['allow_privmsg']) trigger_error('Module not accessible');
    
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
        'result_text' => new xmlrpcval($result ? '' : 'Delete message failed', 'base64'),
    ), 'struct');
    
    return new xmlrpcresp($response);
}
