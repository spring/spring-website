<?php
/**
*
* @copyright (c) 2009 Quoord Systems Limited
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

defined('IN_MOBIQUO') or exit;

function get_bookmarked_topic_func()
{
    global $config, $db, $user, $auth, $mobiquo_config;
    
    // Only registered users can go beyond this point
    if (!$user->data['is_registered'])
    {
        return get_error(9);
    }
    
    $topic_list = array();
    if ($config['allow_bookmarks'])
    {
        $forbidden_forums = $auth->acl_getf('!f_read', true);
        $forbidden_forums = array_unique(array_keys($forbidden_forums));
        
        if (isset($mobiquo_config['hide_forum_id']))
        {
            $forbidden_forums = array_unique(array_merge($forbidden_forums, $mobiquo_config['hide_forum_id']));
        }
        
        $sql_array = array(
            'SELECT'    => 't.*, 
                            f.forum_name,
                            u.user_avatar,
                            u.user_avatar_type',
            'FROM'      => array(
                BOOKMARKS_TABLE  => 'tw',
                TOPICS_TABLE        => 't',
            	USERS_TABLE         => 'u',
            ),

            'WHERE' => 'tw.user_id = ' . $user->data['user_id'] . '
                AND t.topic_id = tw.topic_id
                AND u.user_id = t.topic_last_poster_id
                AND ' . $db->sql_in_set('t.forum_id', $forbidden_forum_ary, true, true),
            'ORDER_BY'  => 't.topic_last_post_time DESC'
        );
        
        $sql_array['LEFT_JOIN'] = array();
        $sql_array['LEFT_JOIN'][] = array('FROM' => array(FORUMS_TABLE => 'f'), 'ON' => 't.forum_id = f.forum_id');
        
        $sql_array['SELECT'] .= ', twt.notify_status';
        
        $sql_array['LEFT_JOIN'][] = array(
            'FROM'  => array(TOPICS_WATCH_TABLE => 'twt'),
            'ON'    => 'twt.user_id = ' . $user->data['user_id'] . ' AND t.topic_id = twt.topic_id'
        );
        
        
        $sql = $db->sql_build_query('SELECT', $sql_array);
        $result = $db->sql_query_limit($sql, 20);
        
        $topic_list = array();
        while ($row = $db->sql_fetchrow($result))
        {
            $forum_id = $row['forum_id'];
            $topic_id = (isset($row['b_topic_id'])) ? $row['b_topic_id'] : $row['topic_id'];

            // Replies
            $replies = ($auth->acl_get('m_approve', $forum_id)) ? $row['topic_replies_real'] : $row['topic_replies'];

            if ($row['topic_status'] == ITEM_MOVED && !empty($row['topic_moved_id']))
            {
                $topic_id = $row['topic_moved_id'];
            }

            // Get folder img, topic status/type related information
            $folder_img = $folder_alt = $topic_type = '';
            topic_status($row, $replies, $unread_topic, $folder_img, $folder_alt, $topic_type);
            
            $short_content = get_short_content($row['topic_last_post_id']);
            $topic_tracking = get_complete_topic_tracking($forum_id, $topic_id);
            $new_post = $topic_tracking[$topic_id] < $row['topic_last_post_time'] ? true : false;
            $user_avatar_url = get_user_avatar_url($row['user_avatar'], $row['user_avatar_type']);
            
            $allow_change_type = ($auth->acl_get('m_', $forum_id) || ($user->data['is_registered'] && $user->data['user_id'] == $row['topic_poster'])) ? true : false;

            $xmlrpc_topic = new xmlrpcval(array(
                'forum_id'          => new xmlrpcval($forum_id),
                'forum_name'        => new xmlrpcval(html_entity_decode($row['forum_name']), 'base64'),
                'topic_id'          => new xmlrpcval($topic_id),
                'topic_title'       => new xmlrpcval(html_entity_decode(strip_tags(censor_text($row['topic_title']))), 'base64'),
                'reply_number'      => new xmlrpcval($replies, 'int'),
                'short_content'     => new xmlrpcval($short_content, 'base64'),
                'post_author_id'    => new xmlrpcval($row['topic_last_poster_id']),
                'post_author_name'  => new xmlrpcval(html_entity_decode($row['topic_last_poster_name']), 'base64'),
                'new_post'          => new xmlrpcval($new_post, 'boolean'),
                'post_time'         => new xmlrpcval(mobiquo_iso8601_encode($row['topic_last_post_time']), 'dateTime.iso8601'),
                'icon_url'          => new xmlrpcval($user_avatar_url),
                'can_delete'        => new xmlrpcval($auth->acl_get('m_delete', $forum_id), 'boolean'),
                'can_subscribe'     => new xmlrpcval(($config['email_enable'] || $config['jab_enable']) && $config['allow_topic_notify'] && $user->data['is_registered'], 'boolean'), 
                'issubscribed'      => new xmlrpcval(!is_null($row['notify_status']) && $row['notify_status'] !== '' ? true : false, 'boolean'),
                'is_subscribed'     => new xmlrpcval(!is_null($row['notify_status']) && $row['notify_status'] !== '' ? true : false, 'boolean'),
                'can_close'         => new xmlrpcval($auth->acl_get('m_lock', $forum_id) || ($auth->acl_get('f_user_lock', $forum_id) && $user->data['is_registered'] && $user->data['user_id'] == $row['topic_poster']), 'boolean'),
                'is_closed'         => new xmlrpcval($row['topic_status'] == ITEM_LOCKED, 'boolean'),
                'can_stick'         => new xmlrpcval($allow_change_type && $auth->acl_get('f_sticky', $forum_id) && $row['topic_type'] != POST_STICKY, 'boolean'),
            ), 'struct');
    
            $topic_list[] = $xmlrpc_topic;
        }
        $db->sql_freeresult($result);
    }
    
    $topic_num = count($topic_list);
    
    $response = new xmlrpcval(
        array(
            'topic_num' => new xmlrpcval($topic_num, 'int'),
            'topics'    => new xmlrpcval($topic_list, 'array'),
        ),
        'struct'
    );

    return new xmlrpcresp($response);
} // End of get_subscribed_topic_func

?>