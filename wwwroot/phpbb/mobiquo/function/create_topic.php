<?php
/**
*
* @copyright (c) 2009 Quoord Systems Limited
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

defined('IN_MOBIQUO') or exit;

function create_topic_func($xmlrpc_params)
{
    global $db, $auth, $user, $config, $phpbb_root_path, $phpEx, $mobiquo_config;

    $params = php_xmlrpc_decode($xmlrpc_params);
    
    // get parameters
    $forum_id   = isset($params[0]) ? $params[0] : '';
    $subject    = isset($params[1]) ? $params[1] : '';
    $image      = isset($params[2]) ? $params[2] : '';
    $text_body  = isset($params[3]) ? $params[3] : '';
    $attach_id  = isset($params[4]) ? $params[4] : '';
    
    if (!$forum_id || !$subject || !$text_body)
    {
        return get_error(1);
    }

    $post_data = array();
    $current_time = time();
    
    $sql = 'SELECT * FROM ' . FORUMS_TABLE . " WHERE forum_id = $forum_id";
    $result = $db->sql_query($sql);
    $post_data = $db->sql_fetchrow($result);
    $db->sql_freeresult($result);
    
    if (!$post_data)
    {
        return get_error(3);
    }
    
    // Need to login to passworded forum first?
    if ($post_data['forum_password'] && !check_forum_password($forum_id))
    {
        return get_error(6);
    }
    
    // Check permissions
    if ($user->data['is_bot'])
    {
        return get_error(2);
    }
    
    // Is the user able to read and post within this forum?
    if (!$auth->acl_get('f_read', $forum_id) || !$auth->acl_get('f_post', $forum_id))
    {
        return get_error(10);
    }
    
    // Is the user able to post within this forum?
    if ($post_data['forum_type'] != FORUM_POST)
    {
        return get_error(11);
    }
    
    // Forum/Topic locked?
    if ($post_data['forum_status'] == ITEM_LOCKED && !$auth->acl_get('m_edit', $forum_id))
    {
        return get_error(12);
    }
    
    if($attach_id)
    {
        // Get the attachment data, based on the attach id...
        $sql = 'SELECT attach_id, is_orphan, real_filename, attach_comment
                FROM ' . ATTACHMENTS_TABLE . "
                WHERE attach_id = $attach_id
                AND is_orphan = 1
                AND poster_id = " . $user->data['user_id'];
        $result = $db->sql_query($sql);
        
        $attach_data = array();
        while ($row = $db->sql_fetchrow($result))
        {
            $attach_data['attach_id'] = $row['attach_id'];
            $attach_data['is_orphan'] = $row['is_orphan'];
            $attach_data['real_filename'] = $row['real_filename'];
            $attach_data['attach_comment'] = $row['attach_comment'];
            
            $_POST['attachment_data'][] = $attach_data;
        }
        $db->sql_freeresult($result);
    }
    
    $post_data['quote_username'] = '';
    $post_data['post_edit_locked']    = 0;
    $post_data['post_subject']        = '';
    $post_data['topic_time_limit']    = 0;
    $post_data['poll_length']        = 0;
    $post_data['poll_start']        = 0;
    $post_data['icon_id']            = 0;
    $post_data['poll_options']        = array();
    
    include($phpbb_root_path . 'includes/message_parser.' . $phpEx);
    $message_parser = new parse_message();
    
    // Set some default variables
    $uninit = array('post_attachment' => 0, 
                    'poster_id' => $user->data['user_id'], 
                    'enable_magic_url' => 0, 
                    'topic_status' => 0, 
                    'topic_type' => POST_NORMAL, 
                    'post_subject' => '', 
                    'topic_title' => '', 
                    'post_time' => 0, 
                    'post_edit_reason' => '', 
                    'notify_set' => 0);
    
    foreach ($uninit as $var_name => $default_value)
    {
        if (!isset($post_data[$var_name]))
        {
            $post_data[$var_name] = $default_value;
        }
    }
    unset($uninit);
    
    if ($config['allow_topic_notify'] && $user->data['is_registered'])
    {
        $notify = $user->data['user_notify'] ? true : false;
    }
    else
    {
        $notify = false;
    }
    
    // Always check if the submitted attachment data is valid and belongs to the user.
    // Further down (especially in submit_post()) we do not check this again.
    $message_parser->get_submitted_attachment_data($post_data['poster_id']);
    
    $post_data['username']          = '';
    $post_data['enable_urls']       = $post_data['enable_magic_url'];
    $post_data['enable_sig']        = ($config['allow_sig'] && $user->optionget('attachsig')) ? true: false;
    $post_data['enable_smilies']    = ($config['allow_smilies'] && $user->optionget('smilies')) ? true : false;
    $post_data['enable_bbcode']        = ($config['allow_bbcode'] && $user->optionget('bbcode')) ? true : false;
    $post_data['enable_urls']        = true;
    $post_data['enable_magic_url']  = $post_data['drafts'] = false;
    
    $check_value = (($post_data['enable_bbcode']+1) << 8) + (($post_data['enable_smilies']+1) << 4) + (($post_data['enable_urls']+1) << 2) + (($post_data['enable_sig']+1) << 1);
    
    // HTML, BBCode, Smilies, Images and Flash status
    $bbcode_status    = ($config['allow_bbcode'] && $auth->acl_get('f_bbcode', $forum_id)) ? true : false;
    $smilies_status    = ($bbcode_status && $config['allow_smilies'] && $auth->acl_get('f_smilies', $forum_id)) ? true : false;
    $img_status        = ($bbcode_status && $auth->acl_get('f_img', $forum_id)) ? true : false;
    $url_status        = ($config['allow_post_links']) ? true : false;
    $flash_status    = ($bbcode_status && $auth->acl_get('f_flash', $forum_id) && $config['allow_post_flash']) ? true : false;
    $quote_status    = ($auth->acl_get('f_reply', $forum_id)) ? true : false;

    $post_data['topic_cur_post_id']    = request_var('topic_cur_post_id', 0);
    $post_data['post_subject']        = utf8_normalize_nfc($subject);
    $message_parser->message        = utf8_normalize_nfc(htmlspecialchars($text_body));

    $post_data['username']            = utf8_normalize_nfc(request_var('username', $post_data['username'], true));
    $post_data['post_edit_reason']    = '';

    $post_data['orig_topic_type']    = $post_data['topic_type'];
    $post_data['topic_type']        = request_var('topic_type', POST_NORMAL);
    $post_data['topic_time_limit']    = request_var('topic_time_limit', 0);
    $post_data['icon_id']            = request_var('icon', 0);

    $post_data['enable_bbcode']        = (!$bbcode_status || isset($_POST['disable_bbcode'])) ? false : true;
    $post_data['enable_smilies']    = (!$smilies_status || isset($_POST['disable_smilies'])) ? false : true;
    $post_data['enable_urls']        = (isset($_POST['disable_magic_url'])) ? 0 : 1;
    $post_data['enable_sig']        = (!$config['allow_sig'] || !$auth->acl_get('f_sigs', $forum_id) || !$auth->acl_get('u_sig')) ? false : ($user->data['is_registered'] ? true : false);

    $topic_lock            = (isset($_POST['lock_topic'])) ? true : false;
    $post_lock            = (isset($_POST['lock_post'])) ? true : false;
    $poll_delete        = (isset($_POST['poll_delete'])) ? true : false;

    $status_switch = (($post_data['enable_bbcode']+1) << 8) + (($post_data['enable_smilies']+1) << 4) + (($post_data['enable_urls']+1) << 2) + (($post_data['enable_sig']+1) << 1);
    $status_switch = ($status_switch != $check_value);

    $post_data['poll_title']        = utf8_normalize_nfc(request_var('poll_title', '', true));
    $post_data['poll_length']        = request_var('poll_length', 0);
    $post_data['poll_option_text']    = utf8_normalize_nfc(request_var('poll_option_text', '', true));
    $post_data['poll_max_options']    = request_var('poll_max_options', 1);
    $post_data['poll_vote_change']    = ($auth->acl_get('f_votechg', $forum_id) && isset($_POST['poll_vote_change'])) ? 1 : 0;

    // Parse Attachments - before checksum is calculated
    $message_parser->parse_attachments('fileupload', 'post', $forum_id, true, false, false);

    // Grab md5 'checksum' of new message
    $message_md5 = md5($message_parser->message);

    if (sizeof($message_parser->warn_msg))
    {
        return get_error();
    }

    $message_parser->parse($post_data['enable_bbcode'], ($config['allow_post_links']) ? $post_data['enable_urls'] : false, $post_data['enable_smilies'], $img_status, $flash_status, $quote_status, $config['allow_post_links']);

    if ($config['flood_interval'] && !$auth->acl_get('f_ignoreflood', $forum_id))
    {
        // Flood check
        $last_post_time = 0;

        if ($user->data['is_registered'])
        {
            $last_post_time = $user->data['user_lastpost_time'];
        }
        else
        {
            $sql = 'SELECT post_time AS last_post_time
                FROM ' . POSTS_TABLE . "
                WHERE poster_ip = '" . $user->ip . "'
                    AND post_time > " . ($current_time - $config['flood_interval']);
            $result = $db->sql_query_limit($sql, 1);
            if ($row = $db->sql_fetchrow($result))
            {
                $last_post_time = $row['last_post_time'];
            }
            $db->sql_freeresult($result);
        }

        if ($last_post_time && ($current_time - $last_post_time) < intval($config['flood_interval']))
        {
            return get_error(13);
        }
    }

    // Validate username
    if (($post_data['username'] && !$user->data['is_registered']))
    {
        include($phpbb_root_path . 'includes/functions_user.' . $phpEx);

        if (($result = validate_username($post_data['username'], (!empty($post_data['post_username'])) ? $post_data['post_username'] : '')) !== false)
        {
            return get_error(14);
        }
    }

    // Parse subject
    if (utf8_clean_string($post_data['post_subject']) === '')
    {
        return get_error(15);
    }

    if (sizeof($message_parser->warn_msg))
    {
        return get_error();
    }

    // DNSBL check
    if ($config['check_dnsbl'] && $mobiquo_config['check_dnsbl'])
    {
        if (($dnsbl = $user->check_dnsbl('post')) !== false)
        {
            return get_error(16);
        }
    }

    // Store message, sync counters
    $data = array(
        'topic_title'            => (empty($post_data['topic_title'])) ? $post_data['post_subject'] : $post_data['topic_title'],
        'topic_first_post_id'    => (isset($post_data['topic_first_post_id'])) ? (int) $post_data['topic_first_post_id'] : 0,
        'topic_last_post_id'    => (isset($post_data['topic_last_post_id'])) ? (int) $post_data['topic_last_post_id'] : 0,
        'topic_time_limit'        => (int) $post_data['topic_time_limit'],
        'topic_attachment'        => (isset($post_data['topic_attachment'])) ? (int) $post_data['topic_attachment'] : 0,
        'post_id'                => 0,
        'topic_id'                => 0,
        'forum_id'                => (int) $forum_id,
        'icon_id'                => (int) $post_data['icon_id'],
        'poster_id'                => (int) $post_data['poster_id'],
        'enable_sig'            => (bool) $post_data['enable_sig'],
        'enable_bbcode'            => (bool) $post_data['enable_bbcode'],
        'enable_smilies'        => (bool) $post_data['enable_smilies'],
        'enable_urls'            => (bool) $post_data['enable_urls'],
        'enable_indexing'        => (bool) $post_data['enable_indexing'],
        'message_md5'            => (string) $message_md5,
        'post_time'                => (isset($post_data['post_time'])) ? (int) $post_data['post_time'] : $current_time,
        'post_checksum'            => (isset($post_data['post_checksum'])) ? (string) $post_data['post_checksum'] : '',
        'post_edit_reason'        => $post_data['post_edit_reason'],
        'post_edit_user'        => (isset($post_data['post_edit_user'])) ? (int) $post_data['post_edit_user'] : 0,
        'forum_parents'            => $post_data['forum_parents'],
        'forum_name'            => $post_data['forum_name'],
        'notify'                => $notify,
        'notify_set'            => $post_data['notify_set'],
        'poster_ip'                => (isset($post_data['poster_ip'])) ? $post_data['poster_ip'] : $user->ip,
        'post_edit_locked'        => (int) $post_data['post_edit_locked'],
        'bbcode_bitfield'        => $message_parser->bbcode_bitfield,
        'bbcode_uid'            => $message_parser->bbcode_uid,
        'message'                => $message_parser->message,
        'attachment_data'        => $message_parser->attachment_data,
        'filename_data'            => $message_parser->filename_data,
        'topic_approved'        => (isset($post_data['topic_approved'])) ? $post_data['topic_approved'] : false,
        'post_approved'            => (isset($post_data['post_approved'])) ? $post_data['post_approved'] : false,
    );

    $poll = array();
    //include($phpbb_root_path . 'includes/functions_posting.' . $phpEx);
    include('include/functions_posting.' . $phpEx);

    $update_message = true;
    $redirect_url = submit_post('post', $post_data['post_subject'], $post_data['username'], $post_data['topic_type'], $poll, $data, $update_message);

    // Check the permissions for post approval, as well as the queue trigger where users are put on approval with a post count lower than specified. Moderators are not affected.
    $approved = true;
    if ((($config['enable_queue_trigger'] && $user->data['user_posts'] < $config['queue_trigger_posts']) || !$auth->acl_get('f_noapprove', $data['forum_id'])) && !$auth->acl_get('m_approve', $data['forum_id']))
    {
        $approved = false;
    }
    
    $posted_success = false;
    $topic_id = '';
    if ($redirect_url)
    {
        preg_match('/&amp;t=(\d+)/', $redirect_url, $matches);
        $topic_id = $matches[1];
        $posted_success = true;
    }
    
    $xmlrpc_create_topic = new xmlrpcval(array(
        'result'    => new xmlrpcval($posted_success, 'boolean'),
        'topic_id'  => new xmlrpcval($topic_id),
//      'approved'  => new xmlrpcval($approved, 'boolean'),
    ), 'struct');

    return new xmlrpcresp($xmlrpc_create_topic);
} // End of create_topic_func

?>