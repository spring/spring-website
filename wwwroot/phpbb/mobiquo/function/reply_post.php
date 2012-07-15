<?php
/**
*
* @copyright (c) 2009, 2010, 2011 Quoord Systems Limited
* @license http://opensource.org/licenses/gpl-2.0.php GNU Public License (GPLv2)
*
*/

defined('IN_MOBIQUO') or exit;

function reply_post_func($xmlrpc_params)
{
    global $db, $auth, $user, $config, $phpbb_root_path, $phpEx, $mobiquo_config, $phpbb_home;
    
    $user->setup('posting');
    if (!$user->data['is_registered']) trigger_error('LOGIN_EXPLAIN_POST');
    
    $params = php_xmlrpc_decode($xmlrpc_params);
    
    // get parameters
    $forum_id   = isset($params[0]) ? intval($params[0]) : '';
    $topic_id   = isset($params[1]) ? intval($params[1]) : '';
    $subject    = isset($params[2]) ? $params[2] : '';
    $text_body  = isset($params[3]) ? $params[3] : '';
    
    $attach_list = isset($params[4]) ? $params[4] : array();
    $_POST['attachment_data'] = (isset($params[5]) && $params[5]) ? unserialize(base64_decode($params[5])) : array();
    $GLOBALS['return_html'] = isset($params[6]) ? $params[6] : false;
    
    if (!$topic_id) trigger_error('NO_TOPIC');
    if (utf8_clean_string($text_body) === '') trigger_error('TOO_FEW_CHARS');

    $post_data = array();
    $current_time = time();
    
    // get topic data
    $sql = 'SELECT *
            FROM ' . TOPICS_TABLE . '
            WHERE topic_id = ' . $topic_id;
    $result = $db->sql_query($sql);
    $post_data = $db->sql_fetchrow($result);
    $db->sql_freeresult($result);
    
    // get forum data
    $sql = 'SELECT *
            FROM ' . FORUMS_TABLE . "
            WHERE forum_type = " . FORUM_POST . ($post_data['forum_id'] ? "
            AND forum_id = '$post_data[forum_id]' " : '');
    $result = $db->sql_query_limit($sql, 1);
    $forum_data = $db->sql_fetchrow($result);
    $db->sql_freeresult($result);
    
    $post_data = array_merge($post_data, $forum_data);
    
    if (!$post_data) trigger_error('NO_TOPIC');
    
    // Use post_row values in favor of submitted ones...
    $forum_id    = (!empty($post_data['forum_id'])) ? (int) $post_data['forum_id'] : (int) $forum_id;
    $topic_id    = (!empty($post_data['topic_id'])) ? (int) $post_data['topic_id'] : (int) $topic_id;

    // Need to login to passworded forum first?
    if ($post_data['forum_password'] && !check_forum_password($forum_id))
        trigger_error('LOGIN_FORUM');

    // Check permissions
    if ($user->data['is_bot']) trigger_error('NOT_AUTHORISED');
    
    // Is the user able to read within this forum?
    if (!$auth->acl_get('f_read', $forum_id))
    {
        if ($user->data['user_id'] != ANONYMOUS)
        {
            trigger_error('USER_CANNOT_READ');
        }
        
        trigger_error('LOGIN_EXPLAIN_POST');
    }

    // Permission to do the reply
    if (!$auth->acl_get('f_reply', $forum_id))
    {    
        if ($user->data['user_id'] != ANONYMOUS)
        {
            trigger_error('USER_CANNOT_REPLY');
        }
        
        trigger_error('LOGIN_EXPLAIN_POST');
    }

    // Is the user able to post within this forum?
    if ($post_data['forum_type'] != FORUM_POST)
    {
        trigger_error('USER_CANNOT_FORUM_POST');
    }

    // Forum/Topic locked?
    if (($post_data['forum_status'] == ITEM_LOCKED || (isset($post_data['topic_status']) && $post_data['topic_status'] == ITEM_LOCKED)) && !$auth->acl_get('m_edit', $forum_id))
    {
        trigger_error(($post_data['forum_status'] == ITEM_LOCKED) ? 'FORUM_LOCKED' : 'TOPIC_LOCKED');
    }
    
    $subject = ((strpos($subject, 'Re: ') !== 0) ? 'Re: ' : '') . ($subject ? $subject : censor_text($post_data['topic_title']));
    
    $post_data['post_edit_locked']  = (isset($post_data['post_edit_locked'])) ? (int) $post_data['post_edit_locked'] : 0;
    $post_data['post_subject']      = (isset($post_data['topic_title'])) ? $post_data['topic_title'] : '';
    $post_data['topic_time_limit']  = (isset($post_data['topic_time_limit'])) ? (($post_data['topic_time_limit']) ? (int) $post_data['topic_time_limit'] / 86400 : (int) $post_data['topic_time_limit']) : 0;
    $post_data['poll_length']       = (!empty($post_data['poll_length'])) ? (int) $post_data['poll_length'] / 86400 : 0;
    $post_data['poll_start']        = (!empty($post_data['poll_start'])) ? (int) $post_data['poll_start'] : 0;
    $post_data['icon_id']           = 0;
    $post_data['poll_options']      = array();
    
    // Get Poll Data
    if ($post_data['poll_start'])
    {
        $sql = 'SELECT poll_option_text
            FROM ' . POLL_OPTIONS_TABLE . "
            WHERE topic_id = $topic_id
            ORDER BY poll_option_id";
        $result = $db->sql_query($sql);
    
        while ($row = $db->sql_fetchrow($result))
        {
            $post_data['poll_options'][] = trim($row['poll_option_text']);
        }
        $db->sql_freeresult($result);
    }
    
    $orig_poll_options_size = sizeof($post_data['poll_options']);
    
    include($phpbb_root_path . 'includes/message_parser.' . $phpEx);
    $message_parser = new parse_message();

    // Set some default variables
    $uninit = array('post_attachment' => 0, 'poster_id' => $user->data['user_id'], 'enable_magic_url' => 0, 'topic_status' => 0, 'topic_type' => POST_NORMAL, 'post_subject' => '', 'topic_title' => '', 'post_time' => 0, 'post_edit_reason' => '', 'notify_set' => 0);
    
    foreach ($uninit as $var_name => $default_value)
    {
        if (!isset($post_data[$var_name]))
        {
            $post_data[$var_name] = $default_value;
        }
    }
    unset($uninit);
    
    // Always check if the submitted attachment data is valid and belongs to the user.
    // Further down (especially in submit_post()) we do not check this again.
    $message_parser->get_submitted_attachment_data($post_data['poster_id']);
    
    $post_data['username']       = '';
    $post_data['enable_urls']    = $post_data['enable_magic_url'];
    $post_data['enable_sig']     = ($config['allow_sig'] && $user->optionget('attachsig')) ? true: false;
    $post_data['enable_smilies'] = ($config['allow_smilies'] && $user->optionget('smilies')) ? true : false;
    $post_data['enable_bbcode']  = ($config['allow_bbcode'] && $user->optionget('bbcode')) ? true : false;
    $post_data['enable_urls']    = true;
    
    $post_data['enable_magic_url'] = $post_data['drafts'] = false;
    
    $check_value = (($post_data['enable_bbcode']+1) << 8) + (($post_data['enable_smilies']+1) << 4) + (($post_data['enable_urls']+1) << 2) + (($post_data['enable_sig']+1) << 1);
    
    // Check if user is watching this topic
    if ($config['allow_topic_notify'] && $user->data['is_registered'])
    {
        $sql = 'SELECT topic_id
                FROM ' . TOPICS_WATCH_TABLE . '
                WHERE topic_id = ' . $topic_id . '
                AND user_id = ' . $user->data['user_id'];
        $result = $db->sql_query($sql);
        $post_data['notify_set'] = (int) $db->sql_fetchfield('topic_id');
        $db->sql_freeresult($result);
    }
    
    // HTML, BBCode, Smilies, Images and Flash status
    $bbcode_status  = ($config['allow_bbcode'] && $auth->acl_get('f_bbcode', $forum_id)) ? true : false;
    $smilies_status = ($bbcode_status && $config['allow_smilies'] && $auth->acl_get('f_smilies', $forum_id)) ? true : false;
    $img_status     = ($bbcode_status && $auth->acl_get('f_img', $forum_id)) ? true : false;
    $url_status     = ($config['allow_post_links']) ? true : false;
    $flash_status   = ($bbcode_status && $auth->acl_get('f_flash', $forum_id) && $config['allow_post_flash']) ? true : false;
    $quote_status   = ($auth->acl_get('f_reply', $forum_id)) ? true : false;
    
    $post_data['topic_cur_post_id'] = request_var('topic_cur_post_id', 0);
    $post_data['post_subject']      = utf8_normalize_nfc($subject);
    $message_parser->message        = utf8_normalize_nfc(htmlspecialchars($text_body));
    $post_data['username']          = utf8_normalize_nfc(request_var('username', $post_data['username'], true));
    $post_data['post_edit_reason']  = '';

    $post_data['orig_topic_type']   = $post_data['topic_type'];
    $post_data['topic_type']        = request_var('topic_type', (int) $post_data['topic_type']);
    $post_data['topic_time_limit']  = request_var('topic_time_limit', (int) $post_data['topic_time_limit']);
    $post_data['icon_id']           = request_var('icon', 0);

    $post_data['enable_bbcode']     = (!$bbcode_status || isset($_POST['disable_bbcode'])) ? false : true;
    $post_data['enable_smilies']    = (!$smilies_status || isset($_POST['disable_smilies'])) ? false : true;
    $post_data['enable_urls']       = (isset($_POST['disable_magic_url'])) ? 0 : 1;
    $post_data['enable_sig']        = (!$config['allow_sig'] || !$auth->acl_get('f_sigs', $forum_id) || !$auth->acl_get('u_sig')) ? false : ($user->data['is_registered'] ? true : false);

    if ($config['allow_topic_notify'] && $user->data['is_registered'])
    {
        $notify = (!$post_data['notify_set'] ? $user->data['user_notify'] : $post_data['notify_set']) ? true : false;
    }
    else
    {
        $notify = false;
    }

    $post_data['poll_title']        = utf8_normalize_nfc(request_var('poll_title', '', true));
    $post_data['poll_length']       = request_var('poll_length', 0);
    $post_data['poll_option_text']  = utf8_normalize_nfc(request_var('poll_option_text', '', true));
    $post_data['poll_max_options']  = request_var('poll_max_options', 1);
    $post_data['poll_vote_change']  = ($auth->acl_get('f_votechg', $forum_id) && isset($_POST['poll_vote_change'])) ? 1 : 0;

    // Parse Attachments - before checksum is calculated
    $message_parser->parse_attachments('fileupload', 'reply', $forum_id, true, false, false);
    
    // Grab md5 'checksum' of new message
    $message_md5 = md5($message_parser->message);

    // Check checksum ... don't re-parse message if the same
    if (sizeof($message_parser->warn_msg))
    {
        trigger_error(join("\n", $message_parser->warn_msg));
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
            trigger_error('FLOOD_ERROR');
        }
    }

    // Validate username
    if (($post_data['username'] && !$user->data['is_registered']))
    {
        include($phpbb_root_path . 'includes/functions_user.' . $phpEx);

        if (($result = validate_username($post_data['username'], (!empty($post_data['post_username'])) ? $post_data['post_username'] : '')) !== false)
        {
            $user->add_lang('ucp');
            trigger_error($result . '_USERNAME');
        }
    }

    $post_data['poll_last_vote'] = (isset($post_data['poll_last_vote'])) ? $post_data['poll_last_vote'] : 0;

    $poll = array();

//    if (sizeof($message_parser->warn_msg))
//    {
//        return get_error();
//    }

    // DNSBL check
    if ($config['check_dnsbl'] && $mobiquo_config['check_dnsbl'])
    {
        if (($dnsbl = $user->check_dnsbl('post')) !== false)
        {
            trigger_error(sprintf($user->lang['IP_BLACKLISTED'], $user->ip, $dnsbl[1]));
        }
    }

    // Store message, sync counters
    $data = array(
        'topic_title'         => (empty($post_data['topic_title'])) ? $post_data['post_subject'] : $post_data['topic_title'],
        'topic_first_post_id' => (isset($post_data['topic_first_post_id'])) ? (int) $post_data['topic_first_post_id'] : 0,
        'topic_last_post_id'  => (isset($post_data['topic_last_post_id'])) ? (int) $post_data['topic_last_post_id'] : 0,
        'topic_time_limit'    => (int) $post_data['topic_time_limit'],
        'topic_attachment'    => (isset($post_data['topic_attachment'])) ? (int) $post_data['topic_attachment'] : 0,
        'post_id'             => 0,
        'topic_id'            => (int) $topic_id,
        'forum_id'            => (int) $forum_id,
        'icon_id'             => (int) $post_data['icon_id'],
        'poster_id'           => (int) $post_data['poster_id'],
        'enable_sig'          => (bool) $post_data['enable_sig'],
        'enable_bbcode'       => (bool) $post_data['enable_bbcode'],
        'enable_smilies'      => (bool) $post_data['enable_smilies'],
        'enable_urls'         => (bool) $post_data['enable_urls'],
        'enable_indexing'     => (bool) $post_data['enable_indexing'],
        'message_md5'         => (string) $message_md5,
        'post_time'           => (isset($post_data['post_time'])) ? (int) $post_data['post_time'] : $current_time,
        'post_checksum'       => (isset($post_data['post_checksum'])) ? (string) $post_data['post_checksum'] : '',
        'post_edit_reason'    => $post_data['post_edit_reason'],
        'post_edit_user'      => (isset($post_data['post_edit_user']) ? (int) $post_data['post_edit_user'] : 0),
        'forum_parents'       => $post_data['forum_parents'],
        'forum_name'          => $post_data['forum_name'],
        'notify'              => $notify,
        'notify_set'          => $post_data['notify_set'],
        'poster_ip'           => (isset($post_data['poster_ip'])) ? $post_data['poster_ip'] : $user->ip,
        'post_edit_locked'    => (int) $post_data['post_edit_locked'],
        'bbcode_bitfield'     => $message_parser->bbcode_bitfield,
        'bbcode_uid'          => $message_parser->bbcode_uid,
        'message'             => $message_parser->message,
        'attachment_data'     => $message_parser->attachment_data,
        'filename_data'       => $message_parser->filename_data,

        'topic_approved'      => (isset($post_data['topic_approved'])) ? $post_data['topic_approved'] : false,
        'post_approved'       => (isset($post_data['post_approved'])) ? $post_data['post_approved'] : false,
        
        // for mod post expire compatibility
        'post_expire_time'      => -1,
    );
    
    include($phpbb_root_path . 'includes/functions_posting.' . $phpEx);

    $update_message = true;
    
    $cwd = getcwd();
    chdir('../');
    $phpbb_root_path_tmp = $phpbb_root_path;
    $phpbb_root_path = './';
    $redirect_url = submit_post('reply', $post_data['post_subject'], $post_data['username'], $post_data['topic_type'], $poll, $data, $update_message);
    chdir($cwd);
    $phpbb_root_path = $phpbb_root_path_tmp;
    
    // Check the permissions for post approval, as well as the queue trigger where users are put on approval with a post count lower than specified. Moderators are not affected.
    $approved = true;
    if ((($config['enable_queue_trigger'] && $user->data['user_posts'] < $config['queue_trigger_posts']) || !$auth->acl_get('f_noapprove', $data['forum_id'])) && !$auth->acl_get('m_approve', $data['forum_id']))
    {
        $approved = false;
    }

    $reply_success = false;
    $post_id = '';
    if ($redirect_url)
    {
        preg_match('/&amp;p=(\d+)/', $redirect_url, $matches);
        $post_id = $matches[1];
        $reply_success = true;
        
        // get new post_content
        $message = censor_text($data['message']);
        $quote_wrote_string = $user->lang['WROTE'];
        $message = str_replace('[/quote:'.$data['bbcode_uid'].']', '[/quote]', $message);
        $message = preg_replace('/\[quote(?:=&quot;(.*?)&quot;)?:'.$data['bbcode_uid'].'\]/ise', "'[quote]' . ('$1' ? '$1' . ' $quote_wrote_string:\n' : '\n')", $message);
        $blocks = preg_split('/(\[\/?quote\])/i', $message, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
        $quote_level = 0;
        $message = '';
            
        foreach($blocks as $block)
        {
            if ($block == '[quote]') {
                if ($quote_level == 0) $message .= $block;
                $quote_level++;
            } else if ($block == '[/quote]') {
                if ($quote_level <= 1) $message .= $block;
                if ($quote_level >= 1) $quote_level--;            
            } else {
                if ($quote_level <= 1) $message .= $block;
            }
        }
        
        $message = preg_replace('/\[(youtube|video|googlevideo|gvideo):'.$data['bbcode_uid'].'\](.*?)\[\/\1:'.$data['bbcode_uid'].'\]/sie', "video_bbcode_format('$1', '$2')", $message);
        $message = preg_replace('/\[(BBvideo)[\d, ]+:'.$row['bbcode_uid'].'\](.*?)\[\/\1:'.$row['bbcode_uid'].'\]/si', "[url=$2]YouTube Video[/url]", $message);
        $message = preg_replace('/\[(spoil|spoiler):'.$row['bbcode_uid'].'\](.*?)\[\/\1:'.$row['bbcode_uid'].'\]/si', "[spoiler]$2[/spoiler]", $message);
        $message = preg_replace('/\[b:'.$data['bbcode_uid'].'\](.*?)\[\/b:'.$data['bbcode_uid'].'\]/si', '[b]$1[/b]', $message);
        $message = preg_replace('/\[i:'.$data['bbcode_uid'].'\](.*?)\[\/i:'.$data['bbcode_uid'].'\]/si', '[i]$1[/i]', $message);
        $message = preg_replace('/\[u:'.$data['bbcode_uid'].'\](.*?)\[\/u:'.$data['bbcode_uid'].'\]/si', '[u]$1[/u]', $message);
        $message = preg_replace('/\[color=#(\w{6}):'.$data['bbcode_uid'].'\](.*?)\[\/color:'.$data['bbcode_uid'].'\]/si', '[color=#$1]$2[/color]', $message);
        
        // Second parse bbcode here
        if ($data['bbcode_bitfield'])
        {
            $bbcode = new bbcode(base64_encode($data['bbcode_bitfield']));
            $bbcode->bbcode_second_pass($message, $data['bbcode_uid'], $data['bbcode_bitfield']);
        }
        
        $message = bbcode_nl2br($message);
        $message = smiley_text($message);
        
        if (!empty($data['attachment_data']))
        {
            parse_attachments($forum_id, $message, $data['attachment_data'], $update_count);
        }
        
        $updated_post_title = html_entity_decode(strip_tags(censor_text($data['topic_title'])), ENT_QUOTES, 'UTF-8');
        
        $edit_allowed = ($auth->acl_get('m_edit', $forum_id) || (
            $auth->acl_get('f_edit', $forum_id) &&
            !$data['post_edit_locked'] &&
            ($data['post_time'] > time() - ($config['edit_time'] * 60) || !$config['edit_time'])
        ));
        
        $delete_allowed = ($auth->acl_get('m_delete', $forum_id) || (
            $auth->acl_get('f_delete', $forum_id) &&
            ($data['post_time'] > time() - ($config['delete_time'] * 60) || !$config['delete_time']) &&
            // we do not want to allow removal of the last post if a moderator locked it!
            !$data['post_edit_locked']
        ));
        
        $attachments = array();  
        if (sizeof($attach_list) && sizeof($data['attachment_data']))
        {
            $sql = 'SELECT *
                FROM ' . ATTACHMENTS_TABLE . '
                WHERE ' . $db->sql_in_set('attach_id', $attach_list) . '
                    AND in_message = 0
                ORDER BY filetime DESC';
            $result = $db->sql_query($sql);
            
            while ($row = $db->sql_fetchrow($result))
            {
                $attachment_by_id[$row['attach_id']] = $row;
            }
            $db->sql_freeresult($result);
            
            foreach($data['attachment_data'] as $attachment)
            {               
                if(preg_match('/<img src=\".*?(\/download\/file.php\?id=(\d+).*?)\"/is', $attachment, $matches))
                {
                    $file_url = html_entity_decode($phpbb_home.$matches[1]);
                    $attach_id = $matches[2];
                    unset($matches);
                
                    $xmlrpc_attachment = new xmlrpcval(array(
                        'filename'      => new xmlrpcval($attachment_by_id[$attach_id]['real_filename'], 'base64'),
                        'filesize'      => new xmlrpcval($attachment_by_id[$attach_id]['filesize'], 'int'),
                        'content_type'  => new xmlrpcval('image'),
                        'thumbnail_url' => new xmlrpcval(''),
                        'url'           => new xmlrpcval($file_url)
                    ), 'struct');
                    $attachments[] = $xmlrpc_attachment;
                }
            }
        }
    }
    
    $xmlrpc_reply_topic = new xmlrpcval(array(
        'result'    => new xmlrpcval($reply_success, 'boolean'),
        'post_id'   => new xmlrpcval($post_id, 'string'),
        'state'     => new xmlrpcval($approved ? 0 : 1, 'int'),
        'post_title'        => new xmlrpcval($updated_post_title, 'base64'),
        'post_content'      => new xmlrpcval(post_html_clean($message), 'base64'),
        'post_author_name'  => new xmlrpcval(html_entity_decode($user->data['username']), 'base64'),
        'is_online'         => new xmlrpcval(true, 'boolean'),
        'can_edit'          => new xmlrpcval($edit_allowed, 'boolean'),
        'icon_url'          => new xmlrpcval(($user->optionget('viewavatars')) ? get_user_avatar_url($user->data['user_avatar'], $user->data['user_avatar_type']) : ''),
        'post_time'         => new xmlrpcval(mobiquo_iso8601_encode($current_time), 'dateTime.iso8601'),
        'can_delete'        => new xmlrpcval($delete_allowed, 'boolean'),
        'allow_smilies'     => new xmlrpcval($data['enable_smilies'] ? true : false, 'boolean'),
        'attachments'       => new xmlrpcval($attachments, 'array'),
    ), 'struct');

    return new xmlrpcresp($xmlrpc_reply_topic);
}
