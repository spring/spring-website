<?php
/**
*
* @copyright (c) 2009 Quoord Systems Limited
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

defined('IN_MOBIQUO') or exit;

function attach_image_func($xmlrpc_params)
{
    global $db, $auth, $user, $config, $phpbb_root_path, $phpEx;

    $params = php_xmlrpc_decode($xmlrpc_params);

    include($phpbb_root_path . 'includes/message_parser.' . $phpEx);
    include('include/functions_posting.' . $phpEx);

    // get parameters
    $image     = isset($params[0]) ? $params[0] : '';
    $name      = isset($params[1]) ? $params[1] : '';
    $type      = isset($params[2]) ? ($params[2] == 'JPG' ? 'image/jpeg' : 'image/png') : 'image/jpeg';
    $forum_id  = isset($params[3]) ? $params[3] : '';
    
    //------- Grab appropriate forum data --------        
    $sql = "SELECT f.* FROM " . FORUMS_TABLE . " f WHERE f.forum_id = $forum_id";
    $result = $db->sql_query($sql);
    $forum_data = $db->sql_fetchrow($result);
    $db->sql_freeresult($result);    
    
    // Forum does not exist
    if (!$forum_data)
    {
        return get_error(3);
    }
    
    // Can not upload attachment to link forum
    if ($forum_data['forum_type'] != FORUM_POST)
    {
        return get_error(3);
    }
    
    // Is the user able to read within this forum?
    if (!$auth->acl_gets('f_read', $forum_id))
    {
        return get_error(17);
    }
    
    // Need to login to passworded forum first?
    if ($post_data['forum_password'] && !check_forum_password($forum_id))
    {
        return get_error(6);
    }
    
    // Check permissions
    if ($user->data['is_bot'] || !$auth->acl_gets('f_attach', $forum_id) || !$auth->acl_gets('u_attach') || !$config['allow_attachments'] || @ini_get('file_uploads') == '0' || strtolower(@ini_get('file_uploads')) == 'off')
    {
        return get_error(2);
    }
    
    if (!$user->data['is_registered'] 
        || (!$auth->acl_get('f_post', $forum_id) && !$auth->acl_gets('f_edit', 'm_edit', $forum_id) && !$auth->acl_get('f_reply', $forum_id)))
    {
        return get_error(2);
    }

//    $tmp_name = tempnam(sys_get_temp_dir(), 'php');
//    $fp = fopen($tmp_name, 'w');
//    fwrite($fp, $image);
//    fclose($fp);
    
    $fp = tmpfile();
    fwrite($fp, $image);
    $file_info = stream_get_meta_data($fp);
    $tmp_name = $file_info['uri'];
    $filesize = @filesize($tmp_name);
    
    if($filesize == 0){
        if(file_exists($tmp_name)){
            @unlink($tmp_name);
        }
        $tmp_name = tempnam(getcwd().'/tmp', '');
        $tmp_file = fopen ($tmp_name, "w");
        $tmp_size =  fwrite($tmp_file, $image);
        fclose($tmp_file); 
        $filesize =  filesize($tmp_name);
    }

    $_FILES[fileupload] = array(
        'name' => $name,
        'type' => $type,
        'tmp_name' => $tmp_name,
        'error' => 0,
        'size' => $filesize ? $filesize : strlen($image)
    );
    
    $_POST['add_file'] = true;
    
    $message_parser = new parse_message();
    
//    // Always check if the submitted attachment data is valid and belongs to the user.
//    // Further down (especially in submit_post()) we do not check this again.
//    $message_parser->get_submitted_attachment_data($post_data['poster_id']);

    // Parse Attachments - before checksum is calculated
    //$message_parser->parse_attachments('fileupload', $mode, $forum_id, $submit, $preview, $refresh);
    $message_parser->parse_attachments('fileupload', $mode, $forum_id, false, false, true);
    
    fclose($fp);

    $attachment_id = $message_parser->attachment_data['0']['attach_id'];
    
    if(file_exists($tmp_name)){
        @unlink($tmp_name);
    }
    
    if ($attachment_id) 
    {
        $xmlrpc_result = new xmlrpcval(array('attachment_id'  => new xmlrpcval($attachment_id)), 'struct');
        return new xmlrpcresp($xmlrpc_result);
    } 
    else 
    {
        return get_error();
    }
}
