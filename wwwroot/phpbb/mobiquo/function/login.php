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
    global $auth, $user, $config, $db, $phpbb_root_path, $phpEx;
    
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
    if ($login_result['status'] == LOGIN_SUCCESS)
    {
        $auth->acl($user->data);
        
        // Compatibility with mod NV who was here
        if (file_exists($phpbb_root_path . 'includes/mods/who_was_here.' . $phpEx))
        {
            include_once($phpbb_root_path . 'includes/mods/who_was_here.' . $phpEx);
            if (class_exists('phpbb_mods_who_was_here') && method_exists('phpbb_mods_who_was_here', 'update_session'))
            {
                @phpbb_mods_who_was_here::update_session();
            }
        }
        
    } else {
        $error_msg = str_replace('%s', '', strip_tags($user->lang[$login_result['error_msg']]));
        
        return new xmlrpcresp(new xmlrpcval(array(
            'result'        => new xmlrpcval(false, 'boolean'),
            'result_text'   => new xmlrpcval($error_msg, 'base64'),
        ), 'struct'));
    }
    
    if ($config['max_attachments'] == 0) $config['max_attachments'] = 100;
    
    $usergroup_id[] = new xmlrpcval($user->data['group_id']);
    $can_readpm = $config['allow_privmsg'] && $auth->acl_get('u_readpm') && ($user->data['user_allow_pm'] || $auth->acl_gets('a_', 'm_') || $auth->acl_getf_global('m_'));
    $can_sendpm = $config['allow_privmsg'] && $auth->acl_get('u_sendpm') && ($user->data['user_allow_pm'] || $auth->acl_gets('a_', 'm_') || $auth->acl_getf_global('m_'));
    $can_upload = ($config['allow_avatar_upload'] && file_exists($phpbb_root_path . $config['avatar_path']) && (function_exists('phpbb_is_writable') ? phpbb_is_writable($phpbb_root_path . $config['avatar_path']) : 1) && $auth->acl_get('u_chgavatar') && (@ini_get('file_uploads') || strtolower(@ini_get('file_uploads')) == 'on')) ? true : false;
    $can_search = $auth->acl_get('u_search') && $auth->acl_getf_global('f_search') && $config['load_search'];
    $can_whosonline = $auth->acl_gets('u_viewprofile', 'a_user', 'a_useradd', 'a_userdel');
    $max_filesize   = ($config['max_filesize'] === '0' || $config['max_filesize'] > 10485760) ? 10485760 : $config['max_filesize'];
    
    $response = new xmlrpcval(array(
        'result'        => new xmlrpcval(true, 'boolean'),
        'user_id'       => new xmlrpcval($user->data['user_id'], 'string'),
        'username'      => new xmlrpcval($user->data['username'], 'base64'),
        'usergroup_id'  => new xmlrpcval($usergroup_id, 'array'),
        'icon_url'      => new xmlrpcval(get_user_avatar_url($user->data['user_avatar'], $user->data['user_avatar_type']), 'string'),
        'post_count'    => new xmlrpcval($user->data['user_posts'], 'int'),
        'can_pm'        => new xmlrpcval($can_readpm, 'boolean'),
        'can_send_pm'   => new xmlrpcval($can_sendpm, 'boolean'),
        'can_moderate'  => new xmlrpcval($auth->acl_get('m_') || $auth->acl_getf_global('m_'), 'boolean'),
        'max_attachment'=> new xmlrpcval($config['max_attachments'], 'int'),
        'max_png_size'  => new xmlrpcval($max_filesize, 'int'),
        'max_jpg_size'  => new xmlrpcval($max_filesize, 'int'),
        'can_search'    => new xmlrpcval($can_search, 'boolean'),
        'can_whosonline'    => new xmlrpcval($can_whosonline, 'boolean'),
        'can_upload_avatar' => new xmlrpcval($can_upload, 'boolean'),
    ), 'struct');
    
    return new xmlrpcresp($response);
}
