<?php
/**
*
* @copyright (c) 2009, 2010, 2011 Quoord Systems Limited
* @license http://opensource.org/licenses/gpl-2.0.php GNU Public License (GPLv2)
*
*/

defined('IN_MOBIQUO') or exit;
include($phpbb_root_path . 'includes/functions_posting.' . $phpEx);

$post_id = request_var('p', 0);

if (!$post_id)
{
    $user->setup('posting');
    trigger_error('NO_POST');
}

// Force forum id
$sql = 'SELECT forum_id
    FROM ' . POSTS_TABLE . '
    WHERE post_id = ' . $post_id;
$result = $db->sql_query($sql);
$forum_id = (int) $db->sql_fetchfield('forum_id');
$db->sql_freeresult($result);

if (!$forum_id) trigger_error('NO_FORUM');

$sql = 'SELECT f.*, t.*, p.*, u.username, u.username_clean, u.user_sig, u.user_sig_bbcode_uid, u.user_sig_bbcode_bitfield
    FROM ' . POSTS_TABLE . ' p, ' . TOPICS_TABLE . ' t, ' . FORUMS_TABLE . ' f, ' . USERS_TABLE . " u
    WHERE p.post_id = $post_id
        AND t.topic_id = p.topic_id
        AND u.user_id = p.poster_id
        AND (f.forum_id = t.forum_id
            OR f.forum_id = $forum_id)" .
        (($auth->acl_get('m_approve', $forum_id)) ? '' : 'AND p.post_approved = 1');

$result = $db->sql_query($sql);
$post_data = $db->sql_fetchrow($result);
$db->sql_freeresult($result);

if (!$post_data)
{
    $user->setup('posting');
    trigger_error('NO_POST');
}

$user->setup(array('posting', 'mcp', 'viewtopic'), $post_data['forum_style']);

// Use post_row values in favor of submitted ones...
$forum_id   = (!empty($post_data['forum_id'])) ? (int) $post_data['forum_id'] : (int) $forum_id;
$topic_id   = (!empty($post_data['topic_id'])) ? (int) $post_data['topic_id'] : (int) $topic_id;
$post_id    = (!empty($post_data['post_id'])) ? (int) $post_data['post_id'] : (int) $post_id;

if ($post_data['forum_password'] && !check_forum_password($forum_id))
{
    trigger_error('LOGIN_FORUM');
}

// Check permissions
if ($user->data['is_bot'] || $user->data['user_id'] == ANONYMOUS)
{
    trigger_error('USER_CANNOT_DELETE');
}

// Is the user able to read within this forum?
if (!$auth->acl_get('f_read', $forum_id))
{
    trigger_error('USER_CANNOT_READ');
}

if (!$user->data['is_registered'] || !$auth->acl_gets('f_delete', 'm_delete', $forum_id))
{
    trigger_error('USER_CANNOT_DELETE');
}

// Forum/Topic locked?
if (($post_data['forum_status'] == ITEM_LOCKED || (isset($post_data['topic_status']) && $post_data['topic_status'] == ITEM_LOCKED)) && !$auth->acl_get('m_edit', $forum_id))
{
    trigger_error(($post_data['forum_status'] == ITEM_LOCKED) ? 'FORUM_LOCKED' : 'TOPIC_LOCKED');
}

// If moderator removing post or user itself removing post, present a confirmation screen
if ($auth->acl_get('m_delete', $forum_id) || ($post_data['poster_id'] == $user->data['user_id'] && $user->data['is_registered'] && $auth->acl_get('f_delete', $forum_id) && $post_id == $post_data['topic_last_post_id'] && !$post_data['post_edit_locked'] && ($post_data['post_time'] > time() - ($config['delete_time'] * 60) || !$config['delete_time'])))
{
    $data = array(
        'topic_first_post_id'   => $post_data['topic_first_post_id'],
        'topic_last_post_id'    => $post_data['topic_last_post_id'],
        'topic_replies_real'    => $post_data['topic_replies_real'],
        'topic_approved'        => $post_data['topic_approved'],
        'topic_type'            => $post_data['topic_type'],
        'post_approved'         => $post_data['post_approved'],
        'post_reported'         => $post_data['post_reported'],
        'post_time'             => $post_data['post_time'],
        'poster_id'             => $post_data['poster_id'],
        'post_postcount'        => $post_data['post_postcount']
    );

    $next_post_id = delete_post($forum_id, $topic_id, $post_id, $data);
    
    // delete thanks on this post
    if (file_exists($phpbb_root_path . 'includes/functions_thanks.' . $phpEx))
    {
        if (!function_exists('delete_post_thanks'))
        {
            include($phpbb_root_path . 'includes/functions_thanks.' . $phpEx);
        }
    
        if (function_exists('delete_post_thanks'))
        {
            delete_post_thanks($post_id);
        }
    }
    
    $post_username = ($post_data['poster_id'] == ANONYMOUS && !empty($post_data['post_username'])) ? $post_data['post_username'] : $post_data['username'];

    if ($next_post_id === false)
    {
        add_log('mod', $forum_id, $topic_id, 'LOG_DELETE_TOPIC', $post_data['topic_title'], $post_username);
    }
    else
    {
        add_log('mod', $forum_id, $topic_id, 'LOG_DELETE_POST', $post_data['post_subject'], $post_username);
    }
}
else
{
    // If we are here the user is not able to delete - present the correct error message
    if ($post_data['poster_id'] != $user->data['user_id'] && $auth->acl_get('f_delete', $forum_id))
    {
        trigger_error('DELETE_OWN_POSTS');
    }
    
    if ($post_data['poster_id'] == $user->data['user_id'] && $auth->acl_get('f_delete', $forum_id) && $post_id != $post_data['topic_last_post_id'])
    {
        trigger_error('CANNOT_DELETE_REPLIED');
    }
    
    trigger_error('USER_CANNOT_DELETE');
}