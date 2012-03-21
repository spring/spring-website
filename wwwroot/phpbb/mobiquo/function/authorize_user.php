<?php
/**
*
* @copyright (c) 2009 Quoord Systems Limited
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

defined('IN_MOBIQUO') or exit;

function authorize_user_func($xmlrpc_params)
{
    global $auth;
    
    $params = php_xmlrpc_decode($xmlrpc_params);
    
    $username = $params[0];
    $password = $params[1];
    set_var($username, $username, 'string', true);
    set_var($password, $password, 'string', true);
    header('Set-Cookie: mobiquo_a=0');
    header('Set-Cookie: mobiquo_b=0');
    header('Set-Cookie: mobiquo_c=0');
    $login_result = $auth->login($username, $password);
    
    $login_status = false;
    if ($login_result['status'] == LOGIN_SUCCESS) $login_status = true;
    
    $response = new xmlrpcval(array('authorize_result' => new xmlrpcval($login_status, 'boolean')), 'struct');
    
    return new xmlrpcresp($response);
}
