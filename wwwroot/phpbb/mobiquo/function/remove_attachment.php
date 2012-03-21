<?php
/**
*
* @copyright (c) 2009, 2010, 2011 Quoord Systems Limited
* @license http://opensource.org/licenses/gpl-2.0.php GNU Public License (GPLv2)
*
*/

defined('IN_MOBIQUO') or exit;

function remove_attachment_func($xmlrpc_params)
{
    global $db, $auth, $user, $config, $phpbb_root_path, $phpEx;
    
    $user->setup('posting');
    if (!$user->data['is_registered']) trigger_error('LOGIN_EXPLAIN_POST');
    
    $params = php_xmlrpc_decode($xmlrpc_params);
    
    include($phpbb_root_path . 'includes/functions_posting.' . $phpEx);
    include($phpbb_root_path . 'includes/message_parser.' . $phpEx);
    
    // get parameters
    $attachment_id  = isset($params[0]) ? intval($params[0]) : trigger_error('Attachment not exists');
    $forum_id       = isset($params[1]) ? intval($params[1]) : trigger_error('NO_FORUM');
    $group_id       = isset($params[2]) ? $params[2] : '';
    $post_id        = isset($params[3]) ? intval($params[3]) : '';
    $_POST['attachment_data'] = $group_id ? unserialize(base64_decode($group_id)) : array();
    
    // Forum does not exist
    if (!$forum_id) trigger_error('NO_FORUM');
    
    $sql = "SELECT f.* FROM " . FORUMS_TABLE . " f WHERE f.forum_id = $forum_id";
    $result = $db->sql_query($sql);
    $forum_data = $db->sql_fetchrow($result);
    $db->sql_freeresult($result);   
    
    if (!$forum_data) trigger_error('NO_FORUM');
    if ($forum_data['forum_password'] && !check_forum_password($forum_id))
        trigger_error('LOGIN_FORUM');
    
    if (!$auth->acl_gets('f_read', $forum_id))
    {
        if ($user->data['user_id'] != ANONYMOUS)
        {
            trigger_error('USER_CANNOT_READ');
        }
        
        trigger_error('LOGIN_EXPLAIN_POST');
    }
    
    // Is the user able to post within this forum?
    if ($forum_data['forum_type'] != FORUM_POST)
    {
        trigger_error('USER_CANNOT_FORUM_POST');
    }
    
    // Check permissions
    if (($user->data['is_bot'] || !$auth->acl_get('f_attach', $forum_id) || !$auth->acl_get('u_attach') || !$config['allow_attachments'] || @ini_get('file_uploads') == '0' || strtolower(@ini_get('file_uploads')) == 'off'))
        trigger_error('NOT_AUTHORISED');
    
    if ((!$auth->acl_get('f_post', $forum_id) && !$auth->acl_gets('f_edit', 'm_edit', $forum_id) && !$auth->acl_get('f_reply', $forum_id)))
        trigger_error('USER_CANNOT_POST');
    
    global $warn_msg;

    $position = '';
    foreach($_POST['attachment_data'] as $pos => $data) {
        if ($data['attach_id'] == $attachment_id) {
            $position = $pos;
            break;
        }
    }
    if ($position === '') {
        $warn_msg = 'Attachment not exists';
    } else {
        $_POST['delete_file'][$position] = 'Delete file';
        $_REQUEST['delete_file'][$position] = 'Delete file';
        
        $message_parser = new parse_message();
        $message_parser->get_submitted_attachment_data();
        $message_parser->parse_attachments('fileupload', 'post', $forum_id, false, false, true);
        $group_id = base64_encode(serialize($message_parser->attachment_data));
        $warn_msg = join("\n", $message_parser->warn_msg);
    }
    
    $xmlrpc_result = new xmlrpcval(array(
        'result'        => new xmlrpcval($warn_msg ? false : true, 'boolean'),
        'result_text'   => new xmlrpcval(strip_tags($warn_msg), 'base64'),
        'group_id'      => new xmlrpcval($group_id),
    ), 'struct');
    
    return new xmlrpcresp($xmlrpc_result);
}
