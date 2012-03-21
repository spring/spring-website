<?php
/**
*
* @copyright (c) 2009, 2010, 2011 Quoord Systems Limited
* @license http://opensource.org/licenses/gpl-2.0.php GNU Public License (GPLv2)
*
*/

defined('IN_MOBIQUO') or exit;

function login_func($xmlrpc_params)
{
    global $auth, $user, $config, $db, $phpbb_root_path;
    
    $params = php_xmlrpc_decode($xmlrpc_params);
    
    $user->setup('ucp');
    
    $username = $params[0];
    $password = $params[1];
    $viewonline = isset($params[2]) ? !$params[2] : 1;
    set_var($username, $username, 'string', true);
    set_var($password, $password, 'string', true);
    header('Set-Cookie: mobiquo_a=0');
    header('Set-Cookie: mobiquo_b=0');
    header('Set-Cookie: mobiquo_c=0');
    $login_result = $auth->login($username, $password, true, $viewonline);
    
    $usergroup_id = array();
    if ($login_result['status'] == LOGIN_SUCCESS) {
        $login_status = true;
        $error_msg = '';
        $auth->acl($user->data);
    } else {
        $login_status = false;
        $error_msg = str_replace('%s', '', strip_tags($user->lang[$login_result['error_msg']]));
    }
    
    if ($config['max_attachments'] == 0) $config['max_attachments'] = 100;
    
    $usergroup_id[] = new xmlrpcval($user->data['group_id']);
    $can_readpm = $config['allow_privmsg'] && $auth->acl_get('u_readpm') && ($user->data['user_allow_pm'] || $auth->acl_gets('a_', 'm_') || $auth->acl_getf_global('m_'));
    $can_sendpm = $config['allow_privmsg'] && $auth->acl_get('u_sendpm') && ($user->data['user_allow_pm'] || $auth->acl_gets('a_', 'm_') || $auth->acl_getf_global('m_'));
    $can_upload = ($config['allow_avatar_upload'] && file_exists($phpbb_root_path . $config['avatar_path']) && (function_exists('phpbb_is_writable') ? phpbb_is_writable($phpbb_root_path . $config['avatar_path']) : 1) && $auth->acl_get('u_chgavatar') && (@ini_get('file_uploads') || strtolower(@ini_get('file_uploads')) == 'on')) ? true : false;
    $max_attachment = $login_status ? $config['max_attachments'] : 0;
    $max_filesize = ($config['max_filesize'] === '0' || $config['max_filesize'] > 10485760) ? 10485760 : $config['max_filesize'];
    
    $response = new xmlrpcval(array(
        'result'        => new xmlrpcval($login_status, 'boolean'),
        'result_text'   => new xmlrpcval($error_msg, 'base64'),
        'user_id'       => new xmlrpcval($user->data['user_id'], 'string'),
        'can_pm'        => new xmlrpcval($can_readpm, 'boolean'),
        'can_send_pm'   => new xmlrpcval($can_sendpm, 'boolean'),
        'can_moderate'  => new xmlrpcval($auth->acl_get('m_') || $auth->acl_getf_global('m_'), 'boolean'),
        'usergroup_id'  => new xmlrpcval($usergroup_id, 'array'),
        'max_attachment'=> new xmlrpcval($max_attachment, 'int'),
        'max_png_size'  => new xmlrpcval($max_filesize, 'int'),
        'max_jpg_size'  => new xmlrpcval($max_filesize, 'int'),
        'can_upload_avatar' => new xmlrpcval($can_upload, 'boolean'),
    ), 'struct');
    
    return new xmlrpcresp($response);
}
