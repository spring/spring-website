<?php
/**
*
* @copyright (c) 2009, 2010, 2011 Quoord Systems Limited
* @license http://opensource.org/licenses/gpl-2.0.php GNU Public License (GPLv2)
*
*/

defined('IN_MOBIQUO') or exit;

function get_subscribed_topic_func($xmlrpc_params)
{
    global $config, $db, $user, $auth, $mobiquo_config;
    
    $user->setup('ucp');
    
    $params = php_xmlrpc_decode($xmlrpc_params);
    
    list($start, $limit) = process_page($params[0], $params[1]);
    
    if (!$user->data['is_registered']) trigger_error('LOGIN_EXPLAIN_UCP');
    
    $topic_list = array();
    if ($config['allow_topic_notify'])
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
                TOPICS_WATCH_TABLE => 'tw',
            ),
            
            'LEFT_JOIN' => array(
                array('FROM' => array(TOPICS_TABLE => 't'), 'ON' => 't.topic_id = tw.topic_id'),
                array('FROM' => array(FORUMS_TABLE => 'f'), 'ON' => 't.forum_id = f.forum_id'),
                array('FROM' => array(USERS_TABLE => 'u'), 'ON' => 't.topic_last_poster_id = u.user_id'),
            ),

            'WHERE' => 'tw.user_id = ' . $user->data['user_id'] . '
                AND ' . $db->sql_in_set('t.forum_id', $forbidden_forum_ary, true, true),
            'ORDER_BY'  => 't.topic_last_post_time DESC'
        );
        
        
        // count the number
        $sql_array_count = $sql_array;
        $sql_array_count['SELECT'] = 'count(*) as num_topics';
        $sql_array_count['ORDER_BY'] = '';
        $sql = $db->sql_build_query('SELECT', $sql_array_count);
        $result = $db->sql_query($sql);
        $topics_count = (int) $db->sql_fetchfield('num_topics');
        $db->sql_freeresult($result);
        
        
        $sql = $db->sql_build_query('SELECT', $sql_array);
        $result = $db->sql_query_limit($sql, $limit, $start);
        
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
            if ($forum_id) {
                $topic_tracking = get_complete_topic_tracking($forum_id, $topic_id);
                $new_post = $topic_tracking[$topic_id] < $row['topic_last_post_time'] ? true : false;
            } else {
                $new_post = false;
            }
            $user_avatar_url = get_user_avatar_url($row['user_avatar'], $row['user_avatar_type']);
            
            $allow_change_type = ($auth->acl_get('m_', $forum_id) || ($user->data['is_registered'] && $user->data['user_id'] == $row['topic_poster'])) ? true : false;
            
            if (empty($forum_id))
            {
                $user->setup('viewforum');
                $forum_id = 0;
                $row['forum_name'] = $user->lang['ANNOUNCEMENTS'];
            }
            
            $xmlrpc_topic = new xmlrpcval(array(
                'forum_id'          => new xmlrpcval($forum_id),
                'forum_name'        => new xmlrpcval(html_entity_decode($row['forum_name']), 'base64'),
                'topic_id'          => new xmlrpcval($topic_id),
                'topic_title'       => new xmlrpcval(html_entity_decode(strip_tags(censor_text($row['topic_title']))), 'base64'),
                'reply_number'      => new xmlrpcval(intval($replies), 'int'),
                'view_number'       => new xmlrpcval(intval($row['topic_views']), 'int'),
                'short_content'     => new xmlrpcval($short_content, 'base64'),
                'post_author_id'    => new xmlrpcval($row['topic_last_poster_id']),
                'post_author_name'  => new xmlrpcval(html_entity_decode($row['topic_last_poster_name']), 'base64'),
                'new_post'          => new xmlrpcval($new_post, 'boolean'),
                'post_time'         => new xmlrpcval(mobiquo_iso8601_encode($row['topic_last_post_time']), 'dateTime.iso8601'),
                'icon_url'          => new xmlrpcval($user_avatar_url),
                
                'can_delete'        => new xmlrpcval($auth->acl_get('m_delete', $forum_id), 'boolean'),
                'can_move'          => new xmlrpcval($auth->acl_get('m_move', $forum_id), 'boolean'),
                'can_close'         => new xmlrpcval($auth->acl_get('m_lock', $forum_id) || ($auth->acl_get('f_user_lock', $forum_id) && $user->data['is_registered'] && $user->data['user_id'] == $row['topic_poster']), 'boolean'),
                'is_closed'         => new xmlrpcval($row['topic_status'] == ITEM_LOCKED, 'boolean'),
                'can_stick'         => new xmlrpcval($allow_change_type && $auth->acl_get('f_sticky', $forum_id), 'boolean'),
                'is_sticky'         => new xmlrpcval($row['topic_type'] == POST_STICKY, 'boolean'),
                'can_approve'       => new xmlrpcval($auth->acl_get('m_approve', $forum_id) && !$row['topic_approved'], 'boolean'),
                'is_approved'       => new xmlrpcval($row['topic_approved'] ? true : false, 'boolean'),
            ), 'struct');
    
            $topic_list[] = $xmlrpc_topic;
        }
        $db->sql_freeresult($result);
    }
    
    $response = new xmlrpcval(
        array(
            'total_topic_num' => new xmlrpcval($topics_count, 'int'),
            'topics'    => new xmlrpcval($topic_list, 'array'),
        ),
        'struct'
    );

    return new xmlrpcresp($response);
}
