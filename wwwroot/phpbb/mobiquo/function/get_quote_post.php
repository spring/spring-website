<?php
/**
*
* @copyright (c) 2009 Quoord Systems Limited
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

defined('IN_MOBIQUO') or exit;

function get_quote_post_func($xmlrpc_params)
{
    global $db, $auth, $user, $phpEx, $phpbb_root_path;
    
    include($phpbb_root_path . 'includes/message_parser.' . $phpEx);

    $params = php_xmlrpc_decode($xmlrpc_params);
    
    // get post id from parameters
    $post_id = $params[0];
    
    $post_data = array();

    // We need to know some basic information in all cases before we do anything.
    if (!$post_id)
    {
        get_error(26);
    }

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
        get_error(26);
    }

    // Use post_row values in favor of submitted ones...
    $forum_id = (int) $post_data['forum_id'];
    $topic_id = (int) $post_data['topic_id'];
    $post_id  = (int) $post_data['post_id'];

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
    if (!$auth->acl_get('f_reply', $forum_id))
    {
        return get_error(19);
    }

    // Is the user able to post within this forum?
    if ($post_data['forum_type'] != FORUM_POST)
    {
        return get_error(19);
    }

    // Forum/Topic locked?
    if (($post_data['forum_status'] == ITEM_LOCKED || (isset($post_data['topic_status']) && $post_data['topic_status'] == ITEM_LOCKED)) && !$auth->acl_get('m_edit', $forum_id))
    {
        return get_error(27);
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

    $message_parser = new parse_message();
    
    if (isset($post_data['post_text']))
    {
        $message_parser->message = &$post_data['post_text'];
        unset($post_data['post_text']);
    }

    if ($post_data['poster_id'] == ANONYMOUS)
    {
        $post_data['username'] = ($mode == 'quote' || $mode == 'edit') ? trim($post_data['post_username']) : '';
    }
    else
    {
        $post_data['username'] = ($mode == 'quote' || $mode == 'edit') ? trim($post_data['username']) : '';
    }

    // Decode text for message display
    $message_parser->decode_message($post_data['bbcode_uid']);
    $message_parser->message = '[quote=&quot;' . $post_data['quote_username'] . '&quot;]' . censor_text(trim($message_parser->message)) . "[/quote]\n";
    $post_data['post_subject'] = ((strpos($post_data['post_subject'], 'Re: ') !== 0) ? 'Re: ' : '') . censor_text($post_data['post_subject']);
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
