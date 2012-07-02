<?php
/**
*
* @copyright (c) 2009, 2010, 2011 Quoord Systems Limited
* @license http://opensource.org/licenses/gpl-2.0.php GNU Public License (GPLv2)
*
*/

defined('IN_MOBIQUO') or exit;

function get_quote_post_func($xmlrpc_params)
{
    global $db, $auth, $user, $phpEx, $phpbb_root_path;
    
    $user->setup('posting');
    
    include($phpbb_root_path . 'includes/message_parser.' . $phpEx);

    $params = php_xmlrpc_decode($xmlrpc_params);
    
    $post_ids = explode('-', $params[0]);
    
    $quote_messages = array();
    foreach($post_ids as $post_id)
    {
        // get post id from parameters
        $post_id = intval($post_id);
        
        $post_data = array();
        
        // We need to know some basic information in all cases before we do anything.
        if (!$post_id) trigger_error('NO_POST');
        
        $sql = 'SELECT t.*, p.*, f.*, u.username
                FROM ' . POSTS_TABLE . ' p
                    LEFT JOIN ' . TOPICS_TABLE . ' t ON (p.topic_id = t.topic_id) 
                    LEFT JOIN ' . FORUMS_TABLE . ' f ON (f.forum_id = t.forum_id OR (t.topic_type = ' . POST_GLOBAL . ' AND f.forum_type = ' . FORUM_POST . '))
                    LEFT JOIN ' . USERS_TABLE  . ' u ON (p.poster_id = u.user_id)' . "
                WHERE p.post_id = $post_id";
        
        $result = $db->sql_query_limit($sql, 1);
        $post_data = $db->sql_fetchrow($result);
        $db->sql_freeresult($result);
        
        if (!$post_data) trigger_error('NO_POST');
        
        // Use post_row values in favor of submitted ones...
        $forum_id = (int) $post_data['forum_id'];
        $topic_id = (int) $post_data['topic_id'];
        $post_id  = (int) $post_data['post_id'];
    
        // Need to login to passworded forum first?
        if ($post_data['forum_password'] && !check_forum_password($forum_id))
        {
            trigger_error('LOGIN_FORUM');
        }
    
        // Is the user able to read within this forum?
        if (!$auth->acl_get('f_read', $forum_id))
        {
            trigger_error('USER_CANNOT_READ');
        }
        
        if (!$auth->acl_get('f_reply', $forum_id))
        {
            trigger_error('USER_CANNOT_REPLY');
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
            $message_parser->message = $post_data['post_text'];
            $message_parser->decode_message($post_data['bbcode_uid']);
            $message_parser->message = '[quote=&quot;' . $post_data['quote_username'] . '&quot;]' . censor_text(trim($message_parser->message)) . "[/quote]\n";
            //$post_data['post_subject'] = ((strpos($post_data['post_subject'], 'Re: ') !== 0) ? 'Re: ' : '') . censor_text($post_data['post_subject']);
            $quote_messages[] = $message_parser->message;
        }
    }
    
    return new xmlrpcresp(new xmlrpcval(array(
        'post_id'       => new xmlrpcval($params[0]),
        //'post_title'    => new xmlrpcval(basic_clean($post_data['post_subject']), 'base64'),
        'post_title'    => new xmlrpcval('', 'base64'),
        'post_content'  => new xmlrpcval(html_entity_decode(implode("\n\n", $quote_messages)), 'base64'),
    ), 'struct'));
}
