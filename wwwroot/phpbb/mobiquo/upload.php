<?php
/**
*
* @copyright (c) 2009, 2010, 2011 Quoord Systems Limited
* @license http://opensource.org/licenses/gpl-2.0.php GNU Public License (GPLv2)
*
*/

define('IN_PHPBB', true);
define('IN_MOBIQUO', true);
define('MOBIQUO_DEBUG', 0);

include('./config/config.php');
include($phpbb_root_path . 'common.' . $phpEx);
require('./mobiquo_common.php');

include('./include/xmlrpc.inc');
include('./include/xmlrpcs.inc');

error_reporting(MOBIQUO_DEBUG);
set_error_handler('xmlrpc_error_handler');

if ($_POST['method_name'] == 'upload_attach')
{
    include($phpbb_root_path . 'includes/functions_posting.' . $phpEx);
    include($phpbb_root_path . 'includes/message_parser.' . $phpEx);
    
    // Start session management
    $user->session_begin();
    $auth->acl($user->data);
    $user->setup('posting');
    header('Mobiquo_is_login:'.($user->data['is_registered'] ? 'true' : 'false'));
    
    if (!$user->data['is_registered']) trigger_error('LOGIN_EXPLAIN_POST');
    
    $forum_id = request_var('forum_id', 0);
    $_POST['attachment_data'] = $_POST['group_id'] ? unserialize(base64_decode($_POST['group_id'])) : array();
    $new_attach_position = count($_POST['attachment_data']);
    
    // Forum does not exist
    if (!$forum_id) trigger_error('NO_FORUM');
    
    $sql = "SELECT f.* FROM " . FORUMS_TABLE . " f WHERE f.forum_id = $forum_id";
    $result = $db->sql_query($sql);
    $forum_data = $db->sql_fetchrow($result);
    $db->sql_freeresult($result);
    
    if (!$forum_data) trigger_error('NO_FORUM');
    if ($forum_data['forum_type'] != FORUM_POST) trigger_error('USER_CANNOT_FORUM_POST');
    if (!$auth->acl_gets('f_read', $forum_id)) trigger_error('USER_CANNOT_READ');
    if ($forum_data['forum_password'] && !check_forum_password($forum_id)) trigger_error('LOGIN_FORUM');
    if (($user->data['is_bot'] || !$auth->acl_get('f_attach', $forum_id) || !$auth->acl_get('u_attach') || !$config['allow_attachments'] || @ini_get('file_uploads') == '0' || strtolower(@ini_get('file_uploads')) == 'off'))
        trigger_error('NOT_AUTHORISED');
    
    if ((!$auth->acl_get('f_post', $forum_id) && !$auth->acl_gets('f_edit', 'm_edit', $forum_id) && !$auth->acl_get('f_reply', $forum_id)))
        trigger_error('USER_CANNOT_POST');
    
    $_POST['add_file'] = 'Add the file';
    
    $message_parser = new parse_message();
    $message_parser->get_submitted_attachment_data();
    $message_parser->parse_attachments('fileupload', 'post', $forum_id, false, false, true);
    $attachment_id = isset($message_parser->attachment_data[$new_attach_position]) ? $message_parser->attachment_data[0]['attach_id'] : '';
    $group_id = base64_encode(serialize($message_parser->attachment_data));
    $warn_msg = join("\n", $message_parser->warn_msg);
} elseif ($_POST['method_name'] == 'upload_avatar')
{
    require($phpbb_root_path . 'includes/functions_user.' . $phpEx);
    
    $user->session_begin();
    $auth->acl($user->data);
    $user->setup('ucp');
    $user->add_lang('posting');
    header('Mobiquo_is_login:'.($user->data['is_registered'] ? 'true' : 'false'));
    
    $status = true;
    $error = array();
    if (!$user->data['is_registered']) {
        trigger_error('LOGIN_EXPLAIN_POST');
    } else {
        include($phpbb_root_path . 'includes/functions_display.' . $phpEx);
        
        if (!avatar_process_user($error))
        {
            $status = false;
            // Replace "error" strings with their real, localised form
            $error = preg_replace('#^([A-Z_]+)$#e', "(!empty(\$user->lang['\\1'])) ? \$user->lang['\\1'] : '\\1'", $error);
    
            if (!$config['allow_avatar'] && $user->data['user_avatar_type'])
            {
                $error[] = $user->lang['AVATAR_NOT_ALLOWED'];
            }
            else if ((($user->data['user_avatar_type'] == AVATAR_UPLOAD) && !$config['allow_avatar_upload']) ||
             (($user->data['user_avatar_type'] == AVATAR_REMOTE) && !$config['allow_avatar_remote']) ||
             (($user->data['user_avatar_type'] == AVATAR_GALLERY) && !$config['allow_avatar_local']))
            {
                $error[] = $user->lang['AVATAR_TYPE_NOT_ALLOWED'];
            }
        }
    }
    $warn_msg = strip_tags(join("\n", $error));
}

require('./server_define.php');

$rpcServer = new xmlrpc_server($server_param, false);
$rpcServer->setDebug(1);
$rpcServer->compress_response = 'true';
$rpcServer->response_charset_encoding = 'UTF-8';
$raw_data = '<?xml version="1.0"?><methodCall><methodName>' . $_POST['method_name'] . '</methodName><params></params></methodCall>';
$response = $rpcServer->service($raw_data);



function upload_attach_func() {
    global $attachment_id, $group_id, $warn_msg;
    
    $xmlrpc_result = new xmlrpcval(array(
        'attachment_id' => new xmlrpcval($attachment_id),
        'group_id'      => new xmlrpcval($group_id),
        'result'        => new xmlrpcval($attachment_id ? true : false, 'boolean'),
        'result_text'   => new xmlrpcval(strip_tags($warn_msg), 'base64'),
    ), 'struct');
    
    return new xmlrpcresp($xmlrpc_result);
}

function upload_avatar_func() {
    global $status, $warn_msg;
    
    $xmlrpc_result = new xmlrpcval(array(
        'result'        => new xmlrpcval($status, 'boolean'),
        'result_text'   => new xmlrpcval($warn_msg, 'base64'),
    ), 'struct');
    
    return new xmlrpcresp($xmlrpc_result);
}

exit;