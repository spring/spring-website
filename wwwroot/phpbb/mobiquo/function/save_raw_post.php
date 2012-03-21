<?php
/**
*
* @copyright (c) 2009 Quoord Systems Limited
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

defined('IN_MOBIQUO') or exit;

function save_raw_post_func($xmlrpc_params)
{
    global $db, $auth, $user, $config, $template, $cache, $phpEx, $phpbb_root_path, $phpbb_home;

    include($phpbb_root_path . 'includes/message_parser.' . $phpEx);

    $params = php_xmlrpc_decode($xmlrpc_params);

    $submit     = true;
    $preview    = false;
    $refresh    = false;
    $mode       = 'edit';

    // get post information from parameters
    $post_id        = $params[0];
    $post_title     = $params[1];
    $post_content   = $params[2];

    $post_data = array();

    $sql = 'SELECT f.*, t.*, p.*, u.username, u.username_clean, u.user_sig, u.user_sig_bbcode_uid, u.user_sig_bbcode_bitfield
            FROM ' . POSTS_TABLE . ' p, ' . TOPICS_TABLE . ' t, ' . FORUMS_TABLE . ' f, ' . USERS_TABLE . " u
            WHERE p.post_id = $post_id
            AND t.topic_id = p.topic_id
            AND u.user_id = p.poster_id
            AND f.forum_id = t.forum_id";

    $result = $db->sql_query($sql);
    $post_data = $db->sql_fetchrow($result);
    $db->sql_freeresult($result);

    if (!$post_data)
    {
        return get_error(26);
    }

    // Use post_row values in favor of submitted ones...
    $forum_id = (int) $post_data['forum_id'];
    $topic_id = (int) $post_data['topic_id'];
    $post_id  = (int) $post_id;

    // Need to login to passworded forum first?
    if ($post_data['forum_password'] && !check_forum_password($forum_id))
    {
        return get_error(6);
    }

    // Is the user able to read within this forum?
    if (!$auth->acl_get('f_read', $forum_id))
    {
        return get_error(17);
    }

    // Permission to do the action asked?
    if (!($user->data['is_registered'] && $auth->acl_gets('f_edit', 'm_edit', $forum_id)))
    {
        return get_error(2);
    }

    // Forum/Topic locked?
    if (($post_data['forum_status'] == ITEM_LOCKED || (isset($post_data['topic_status']) && $post_data['topic_status'] == ITEM_LOCKED)) && !$auth->acl_get('m_edit', $forum_id))
    {
        return get_error(27);
    }

    // Can we edit this post ... if we're a moderator with rights then always yes
    // else it depends on editing times, lock status and if we're the correct user
    if (!$auth->acl_get('m_edit', $forum_id))
    {
        if ($user->data['user_id'] != $post_data['poster_id'])
        {
            return get_error(28);
        }

        if (!($post_data['post_time'] > time() - ($config['edit_time'] * 60) || !$config['edit_time']))
        {
            return get_error(29);
        }

        if ($post_data['post_edit_locked'])
        {
            return get_error(30);
        }
    }

    // Determine some vars
    if (isset($post_data['poster_id']) && $post_data['poster_id'] == ANONYMOUS)
    {
        $post_data['quote_username'] = (!empty($post_data['post_username'])) ? $post_data['post_username'] : $user->lang['GUEST'];
    }
    else
    {
        $post_data['quote_username'] = isset($post_data['username']) ? $post_data['username'] : '';
    }

    $post_data['post_edit_locked']    = (isset($post_data['post_edit_locked'])) ? (int) $post_data['post_edit_locked'] : 0;
    $post_data['post_subject']        = (in_array($mode, array('quote', 'edit'))) ? $post_data['post_subject'] : ((isset($post_data['topic_title'])) ? $post_data['topic_title'] : '');
    $post_data['topic_time_limit']    = (isset($post_data['topic_time_limit'])) ? (($post_data['topic_time_limit']) ? (int) $post_data['topic_time_limit'] / 86400 : (int) $post_data['topic_time_limit']) : 0;
    $post_data['poll_length']        = (!empty($post_data['poll_length'])) ? (int) $post_data['poll_length'] / 86400 : 0;
    $post_data['poll_start']        = (!empty($post_data['poll_start'])) ? (int) $post_data['poll_start'] : 0;
    $post_data['icon_id']            = (!isset($post_data['icon_id']) || in_array($mode, array('quote', 'reply'))) ? 0 : (int) $post_data['icon_id'];
    $post_data['poll_options']        = array();

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

    $message_parser = new parse_message();

    if (isset($post_data['post_text']))
    {
        $message_parser->message = &$post_data['post_text'];
        unset($post_data['post_text']);
    }

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

    if ($post_data['post_attachment'] && !$refresh && !$preview && $mode == 'edit')
    {
        // Do not change to SELECT *
        $sql = 'SELECT attach_id, is_orphan, attach_comment, real_filename
            FROM ' . ATTACHMENTS_TABLE . "
            WHERE post_msg_id = $post_id
                AND in_message = 0
                AND is_orphan = 0
            ORDER BY filetime DESC";
        $result = $db->sql_query($sql);
        $message_parser->attachment_data = array_merge($message_parser->attachment_data, $db->sql_fetchrowset($result));
        $db->sql_freeresult($result);
    }

    if ($post_data['poster_id'] == ANONYMOUS)
    {
        $post_data['username'] = ($mode == 'quote' || $mode == 'edit') ? trim($post_data['post_username']) : '';
    }
    else
    {
        $post_data['username'] = ($mode == 'quote' || $mode == 'edit') ? trim($post_data['username']) : '';
    }

    $post_data['enable_urls'] = $post_data['enable_magic_url'];

    $post_data['enable_magic_url'] = $post_data['drafts'] = false;

    $check_value = (($post_data['enable_bbcode']+1) << 8) + (($post_data['enable_smilies']+1) << 4) + (($post_data['enable_urls']+1) << 2) + (($post_data['enable_sig']+1) << 1);

    // Check if user is watching this topic
    if ($mode != 'post' && $config['allow_topic_notify'] && $user->data['is_registered'])
    {
        $sql = 'SELECT topic_id
            FROM ' . TOPICS_WATCH_TABLE . '
            WHERE topic_id = ' . $topic_id . '
                AND user_id = ' . $user->data['user_id'];
        $result = $db->sql_query($sql);
        $post_data['notify_set'] = (int) $db->sql_fetchfield('topic_id');
        $db->sql_freeresult($result);
    }

    // Do we want to edit our post ?
    if ($post_data['bbcode_uid'])
    {
        $message_parser->bbcode_uid = $post_data['bbcode_uid'];
    }

    // HTML, BBCode, Smilies, Images and Flash status
    $bbcode_status    = ($config['allow_bbcode'] && $auth->acl_get('f_bbcode', $forum_id)) ? true : false;
    $smilies_status    = ($bbcode_status && $config['allow_smilies'] && $auth->acl_get('f_smilies', $forum_id)) ? true : false;
    $img_status        = ($bbcode_status && $auth->acl_get('f_img', $forum_id)) ? true : false;
    $url_status        = ($config['allow_post_links']) ? true : false;
    $flash_status    = ($bbcode_status && $auth->acl_get('f_flash', $forum_id) && $config['allow_post_flash']) ? true : false;
    $quote_status    = ($auth->acl_get('f_reply', $forum_id)) ? true : false;

    $solved_captcha = false;

    $post_data['topic_cur_post_id']    = request_var('topic_cur_post_id', 0);
    $post_data['post_subject']        = utf8_normalize_nfc($post_title);
    $message_parser->message        = utf8_normalize_nfc(htmlspecialchars($post_content));

    $post_data['username']            = utf8_normalize_nfc(request_var('username', $post_data['username'], true));
    $post_data['post_edit_reason']    = (!empty($_POST['edit_reason']) && $mode == 'edit' && $auth->acl_get('m_edit', $forum_id)) ? utf8_normalize_nfc(request_var('edit_reason', '', true)) : '';

    $post_data['orig_topic_type']    = $post_data['topic_type'];
    $post_data['topic_type']        = request_var('topic_type', (($mode != 'post') ? (int) $post_data['topic_type'] : POST_NORMAL));
    $post_data['topic_time_limit']    = request_var('topic_time_limit', (($mode != 'post') ? (int) $post_data['topic_time_limit'] : 0));
    $post_data['icon_id']            = request_var('icon', 0);

    $post_data['enable_bbcode']        = (!$bbcode_status || isset($_POST['disable_bbcode'])) ? false : true;
    $post_data['enable_smilies']    = (!$smilies_status || isset($_POST['disable_smilies'])) ? false : true;
    $post_data['enable_urls']        = (isset($_POST['disable_magic_url'])) ? 0 : 1;
    $post_data['enable_sig']        = (!$config['allow_sig'] || !$auth->acl_get('f_sigs', $forum_id) || !$auth->acl_get('u_sig')) ? false : ($user->data['is_registered'] ? true : false);

    if ($config['allow_topic_notify'] && $user->data['is_registered'])
    {
        $notify = (isset($_POST['notify'])) ? true : false;
    }
    else
    {
        $notify = false;
    }

    $topic_lock     = (isset($_POST['lock_topic'])) ? true : false;
    $post_lock      = (isset($_POST['lock_post'])) ? true : false;
    $poll_delete    = (isset($_POST['poll_delete'])) ? true : false;

    $status_switch = (($post_data['enable_bbcode']+1) << 8) + (($post_data['enable_smilies']+1) << 4) + (($post_data['enable_urls']+1) << 2) + (($post_data['enable_sig']+1) << 1);
    $status_switch = ($status_switch != $check_value);

    $post_data['poll_title']        = utf8_normalize_nfc(request_var('poll_title', '', true));
    $post_data['poll_length']        = request_var('poll_length', 0);
    $post_data['poll_option_text']    = utf8_normalize_nfc(request_var('poll_option_text', '', true));
    $post_data['poll_max_options']    = request_var('poll_max_options', 1);
    $post_data['poll_vote_change']    = ($auth->acl_get('f_votechg', $forum_id) && isset($_POST['poll_vote_change'])) ? 1 : 0;

    // Parse Attachments - before checksum is calculated
    $message_parser->parse_attachments('fileupload', $mode, $forum_id, $submit, $preview, $refresh);

    // Grab md5 'checksum' of new message
    $message_md5 = md5($message_parser->message);

    // Check checksum ... don't re-parse message if the same
    $update_message = ($mode != 'edit' || $message_md5 != $post_data['post_checksum'] || $status_switch || strlen($post_data['bbcode_uid']) < BBCODE_UID_LEN) ? true : false;

    // Parse message
    if ($update_message)
    {
        if (sizeof($message_parser->warn_msg))
        {
            return get_error();
        }

        $message_parser->parse($post_data['enable_bbcode'], ($config['allow_post_links']) ? $post_data['enable_urls'] : false, $post_data['enable_smilies'], $img_status, $flash_status, $quote_status, $config['allow_post_links']);
    }
    else
    {
        $message_parser->bbcode_bitfield = $post_data['bbcode_bitfield'];
    }

    // Validate username
    if (($post_data['username'] && !$user->data['is_registered']) || ($mode == 'edit' && $post_data['poster_id'] == ANONYMOUS && $post_data['username'] && $post_data['post_username'] && $post_data['post_username'] != $post_data['username']))
    {
        include($phpbb_root_path . 'includes/functions_user.' . $phpEx);

        if (($result = validate_username($post_data['username'], (!empty($post_data['post_username'])) ? $post_data['post_username'] : '')) !== false)
        {
            $user->add_lang('ucp');
            return get_error();;
        }
    }

    // Parse subject
    if (utf8_clean_string($post_data['post_subject']) === '' && $post_data['topic_first_post_id'] == $post_id)
    {
        return get_error(15);
    }

    $post_data['poll_last_vote'] = (isset($post_data['poll_last_vote'])) ? $post_data['poll_last_vote'] : 0;

    if ($post_data['poll_option_text'] && $post_id == $post_data['topic_first_post_id']
        && $auth->acl_get('f_poll', $forum_id))
    {
        $poll = array(
            'poll_title'        => $post_data['poll_title'],
            'poll_length'        => $post_data['poll_length'],
            'poll_max_options'    => $post_data['poll_max_options'],
            'poll_option_text'    => $post_data['poll_option_text'],
            'poll_start'        => $post_data['poll_start'],
            'poll_last_vote'    => $post_data['poll_last_vote'],
            'poll_vote_change'    => $post_data['poll_vote_change'],
            'enable_bbcode'        => $post_data['enable_bbcode'],
            'enable_urls'        => $post_data['enable_urls'],
            'enable_smilies'    => $post_data['enable_smilies'],
            'img_status'        => $img_status
        );

        $message_parser->parse_poll($poll);

        $post_data['poll_options'] = (isset($poll['poll_options'])) ? $poll['poll_options'] : '';
        $post_data['poll_title'] = (isset($poll['poll_title'])) ? $poll['poll_title'] : '';
    }
    else
    {
        $poll = array();
    }

    // Check topic type
    if ($post_data['topic_type'] != POST_NORMAL && $post_data['topic_first_post_id'] == $post_id)
    {
        switch ($post_data['topic_type'])
        {
            case POST_GLOBAL:
            case POST_ANNOUNCE:
                $auth_option = 'f_announce';
            break;

            case POST_STICKY:
                $auth_option = 'f_sticky';
            break;

            default:
                $auth_option = '';
            break;
        }

        if (!$auth->acl_get($auth_option, $forum_id))
        {
            // There is a special case where a user edits his post whereby the topic type got changed by an admin/mod.
            // Another case would be a mod not having sticky permissions for example but edit permissions.
            // To prevent non-authed users messing around with the topic type we reset it to the original one.
            $post_data['topic_type'] = $post_data['orig_topic_type'];
        }
    }

    // DNSBL check
    if ($config['check_dnsbl'])
    {
        if (($dnsbl = $user->check_dnsbl('post')) !== false)
        {
            return get_error('DNSBL check failed');
        }
    }

    // Check if we want to de-globalize the topic... and ask for new forum
    if ($post_data['topic_type'] != POST_GLOBAL)
    {
        $sql = 'SELECT topic_type, forum_id
            FROM ' . TOPICS_TABLE . "
            WHERE topic_id = $topic_id";
        $result = $db->sql_query($sql);
        $row = $db->sql_fetchrow($result);
        $db->sql_freeresult($result);

        if ($row && !$row['forum_id'] && $row['topic_type'] == POST_GLOBAL)
        {
            $to_forum_id = request_var('to_forum_id', 0);

            if ($to_forum_id)
            {
                $sql = 'SELECT forum_type
                    FROM ' . FORUMS_TABLE . '
                    WHERE forum_id = ' . $to_forum_id;
                $result = $db->sql_query($sql);
                $forum_type = (int) $db->sql_fetchfield('forum_type');
                $db->sql_freeresult($result);

                if ($forum_type != FORUM_POST || !$auth->acl_get('f_post', $to_forum_id))
                {
                    $to_forum_id = 0;
                }
            }

            if (!$to_forum_id)
            {
                include_once($phpbb_root_path . 'includes/functions_admin.' . $phpEx);

                $template->assign_vars(array(
                    'S_FORUM_SELECT'    => make_forum_select(false, false, false, true, true, true),
                    'S_UNGLOBALISE'        => true)
                );

                $submit = false;
                $refresh = true;
            }
            else
            {
                if (!$auth->acl_get('f_post', $to_forum_id))
                {
                    // This will only be triggered if the user tried to trick the forum.
                    return get_error(2);
                }

                $forum_id = $to_forum_id;
            }
        }
    }

    // Lock/Unlock Topic
    $change_topic_status = $post_data['topic_status'];
    $perm_lock_unlock = ($auth->acl_get('m_lock', $forum_id) || ($auth->acl_get('f_user_lock', $forum_id) && $user->data['is_registered'] && !empty($post_data['topic_poster']) && $user->data['user_id'] == $post_data['topic_poster'] && $post_data['topic_status'] == ITEM_UNLOCKED)) ? true : false;

    if ($post_data['topic_status'] == ITEM_LOCKED && !$topic_lock && $perm_lock_unlock)
    {
        $change_topic_status = ITEM_UNLOCKED;
    }
    else if ($post_data['topic_status'] == ITEM_UNLOCKED && $topic_lock && $perm_lock_unlock)
    {
        $change_topic_status = ITEM_LOCKED;
    }

    if ($change_topic_status != $post_data['topic_status'])
    {
        $sql = 'UPDATE ' . TOPICS_TABLE . "
            SET topic_status = $change_topic_status
            WHERE topic_id = $topic_id
                AND topic_moved_id = 0";
        $db->sql_query($sql);

        $user_lock = ($auth->acl_get('f_user_lock', $forum_id) && $user->data['is_registered'] && $user->data['user_id'] == $post_data['topic_poster']) ? 'USER_' : '';

        add_log('mod', $forum_id, $topic_id, 'LOG_' . $user_lock . (($change_topic_status == ITEM_LOCKED) ? 'LOCK' : 'UNLOCK'), $post_data['topic_title']);
    }

    // Lock/Unlock Post Edit
    if ($mode == 'edit' && $post_data['post_edit_locked'] == ITEM_LOCKED && !$post_lock && $auth->acl_get('m_edit', $forum_id))
    {
        $post_data['post_edit_locked'] = ITEM_UNLOCKED;
    }
    else if ($mode == 'edit' && $post_data['post_edit_locked'] == ITEM_UNLOCKED && $post_lock && $auth->acl_get('m_edit', $forum_id))
    {
        $post_data['post_edit_locked'] = ITEM_LOCKED;
    }

    $data = array(
        'topic_title'            => (empty($post_data['topic_title'])) ? $post_data['post_subject'] : $post_data['topic_title'],
        'topic_first_post_id'    => (isset($post_data['topic_first_post_id'])) ? (int) $post_data['topic_first_post_id'] : 0,
        'topic_last_post_id'    => (isset($post_data['topic_last_post_id'])) ? (int) $post_data['topic_last_post_id'] : 0,
        'topic_time_limit'        => (int) $post_data['topic_time_limit'],
        'topic_attachment'        => (isset($post_data['topic_attachment'])) ? (int) $post_data['topic_attachment'] : 0,
        'post_id'                => (int) $post_id,
        'topic_id'                => (int) $topic_id,
        'forum_id'                => (int) $forum_id,
        'icon_id'                => (int) $post_data['icon_id'],
        'poster_id'                => (int) $post_data['poster_id'],
        'enable_sig'            => (bool) $post_data['enable_sig'],
        'enable_bbcode'            => (bool) $post_data['enable_bbcode'],
        'enable_smilies'        => (bool) $post_data['enable_smilies'],
        'enable_urls'            => (bool) $post_data['enable_urls'],
        'enable_indexing'        => (bool) $post_data['enable_indexing'],
        'message_md5'            => (string) $message_md5,
        'post_time'                => (isset($post_data['post_time'])) ? (int) $post_data['post_time'] : time(),
        'post_checksum'            => (isset($post_data['post_checksum'])) ? (string) $post_data['post_checksum'] : '',
        'post_edit_reason'        => $post_data['post_edit_reason'],
        'post_edit_user'        => ($mode == 'edit') ? $user->data['user_id'] : ((isset($post_data['post_edit_user'])) ? (int) $post_data['post_edit_user'] : 0),
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

    $data['topic_replies_real'] = $post_data['topic_replies_real'];
    $data['topic_replies'] = $post_data['topic_replies'];

    include($phpbb_root_path . 'includes/functions_posting.' . $phpEx);
    $redirect_url = submit_post($mode, $post_data['post_subject'], $post_data['username'], $post_data['topic_type'], $poll, $data, $update_message);

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
    }

    $xmlrpc_reply_topic = new xmlrpcval(array(
        'result'    => new xmlrpcval($reply_success, 'boolean'),
        'approved'  => new xmlrpcval($approved ? 0 : 1, 'int'),
    ), 'struct');

    return new xmlrpcresp($xmlrpc_reply_topic);
}
