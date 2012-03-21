<?php
/**
*
* @copyright (c) 2009 Quoord Systems Limited
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

defined('IN_MOBIQUO') or exit;

function login_func($xmlrpc_params)
{
    global $auth, $user, $config;
    
    $params = php_xmlrpc_decode($xmlrpc_params);
    
    $user->setup('ucp');
    
    $username = $params[0];
    $password = $params[1];
    set_var($username, $username, 'string', true);
    set_var($password, $password, 'string', true);
    header('Set-Cookie: mobiquo_a=0');
    header('Set-Cookie: mobiquo_b=0');
    header('Set-Cookie: mobiquo_c=0');
    $login_result = $auth->login($username, $password, true);
    
    if ($login_result['status'] == LOGIN_SUCCESS) {
        $login_status = true;
        $error_msg = '';
        $user_id = $login_result['user_row']['user_id'];
    } else {
        $login_status = false;
        $error_msg = str_replace('%s', '', strip_tags($user->lang[$login_result['error_msg']]));
        $user_id = '';
    }
    
    $response = new xmlrpcval(array(
        'result'        => new xmlrpcval($login_status, 'boolean'),
        'result_text'   => new xmlrpcval($error_msg, 'base64'),
        'user_id'       => new xmlrpcval($user_id, 'string'),
        'can_pm'        => new xmlrpcval($config['allow_privmsg'] ? true : false, 'boolean'),
        'can_send_pm'   => new xmlrpcval($config['allow_privmsg'] ? true : false, 'boolean'),
    ), 'struct');
    
    return new xmlrpcresp($response);
}
