<?php
/**
*
* @copyright (c) 2009 Quoord Systems Limited
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

defined('IN_MOBIQUO') or exit;

function get_raw_post_func($xmlrpc_params)
{
    global $db, $auth, $user, $config, $template, $cache, $phpEx, $phpbb_root_path, $phpbb_home;
    
    include($phpbb_root_path . 'includes/message_parser.' . $phpEx);

    $params = php_xmlrpc_decode($xmlrpc_params);
    
    // get post id from parameters
    $post_id = $params[0];

    $post_data = array();
    $sql = 'SELECT f.*, t.*, p.*
            FROM ' . POSTS_TABLE . ' p, ' . TOPICS_TABLE . ' t, ' . FORUMS_TABLE . " f
            WHERE p.post_id = $post_id
            AND t.topic_id = p.topic_id
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

    $message_parser = new parse_message();
    
    if (isset($post_data['post_text']))
    {
        $message_parser->message = &$post_data['post_text'];
        unset($post_data['post_text']);
    }

    // Do we want to edit our post ?
    if ($post_data['bbcode_uid'])
    {
        $message_parser->bbcode_uid = $post_data['bbcode_uid'];
    }
    
    // Decode text for message display
    $message_parser->decode_message($post_data['bbcode_uid']);
    $post_data['post_text'] = $message_parser->message;

    return new xmlrpcresp(
        new xmlrpcval(array(
                'post_id'       => new xmlrpcval($post_id),
                'post_title'    => new xmlrpcval(html_entity_decode(strip_tags($post_data['post_subject'])), 'base64'),
                'post_content'  => new xmlrpcval(html_entity_decode($post_data['post_text']), 'base64'),
            ),
            'struct'
        )
    );
}
