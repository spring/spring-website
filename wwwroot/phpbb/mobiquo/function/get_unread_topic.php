<?php
/**
*
* @copyright (c) 2009, 2010, 2011 Quoord Systems Limited
* @license http://opensource.org/licenses/gpl-2.0.php GNU Public License (GPLv2)
*
*/

defined('IN_MOBIQUO') or exit;

function get_unread_topic_func($xmlrpc_params)
{
    global $db, $auth, $user, $config, $mobiquo_config;
    
    $user->setup('ucp');
    if (!$user->data['is_registered']) trigger_error('LOGIN_EXPLAIN_UCP');
    
    $params = php_xmlrpc_decode($xmlrpc_params);

    list($start, $sql_limit) = process_page($params[0], $params[1]);
    
    $ex_fid_ary = array_unique(array_merge(array_keys($auth->acl_getf('!f_read', true)), array_keys($auth->acl_getf('!f_search', true))));
    
    if (isset($mobiquo_config['hide_forum_id']))
    {
        $ex_fid_ary = array_unique(array_merge($ex_fid_ary, $mobiquo_config['hide_forum_id']));
    }
    
    $not_in_fid = (sizeof($ex_fid_ary)) ? 'WHERE ' . $db->sql_in_set('f.forum_id', $ex_fid_ary, true) . " OR (f.forum_password <> '' AND fa.user_id <> " . (int) $user->data['user_id'] . ')' : "";

        $sql = 'SELECT f.forum_id, f.forum_name, f.parent_id, f.forum_type, f.right_id, f.forum_password, fa.user_id
                    FROM ' . FORUMS_TABLE . ' f
                    LEFT JOIN ' . FORUMS_ACCESS_TABLE . " fa ON (fa.forum_id = f.forum_id
                        AND fa.session_id = '" . $db->sql_escape($user->session_id) . "')
                    $not_in_fid
                    ORDER BY f.left_id";
        $result = $db->sql_query($sql);

    while ($row = $db->sql_fetchrow($result))
    {
        if ($row['forum_password'] && $row['user_id'] != $user->data['user_id'])
        {
            $ex_fid_ary[] = (int) $row['forum_id'];
            continue;
        }
    }
    $db->sql_freeresult($result);

    // find out in which forums the user is allowed to view approved posts
    if ($auth->acl_get('m_approve'))
    {
        $m_approve_fid_sql = '';
    }
    else if ($auth->acl_getf_global('m_approve'))
    {
        $m_approve_fid_ary = array_diff(array_keys($auth->acl_getf('!m_approve', true)), $ex_fid_ary);
        $m_approve_fid_sql = ' AND (t.topic_approved = 1' . ((sizeof($m_approve_fid_ary)) ? ' OR ' . $db->sql_in_set('t.forum_id', $m_approve_fid_ary, true) : '') . ')';
    }
    else
    {
        $m_approve_fid_sql = ' AND t.topic_approved = 1';
    }

    $sql = 'SELECT t.topic_id, t.forum_id, t.topic_last_post_time FROM ' . TOPICS_TABLE . ' t
            WHERE t.topic_last_post_time > ' . $user->data['user_lastvisit'] . '
            AND t.topic_moved_id = 0 ' . $m_approve_fid_sql .
            ((sizeof($ex_fid_ary)) ? ' AND ' . $db->sql_in_set('t.forum_id', $ex_fid_ary, true) : '');
    $result = $db->sql_query($sql);
    
    $unread_tids = array();
    while ($row = $db->sql_fetchrow($result))
    {
        $topic_id = $row['topic_id'];
        $forum_id = $row['forum_id'];
        $topic_tracking = get_complete_topic_tracking($forum_id, $topic_id);
        if ($topic_tracking[$topic_id] < $row['topic_last_post_time'])
        {
            $unread_tids[] = $topic_id;
        }
    }
    $db->sql_freeresult($result);
    
    $topic_list = array();
    
    if(count($unread_tids)) {
        $sql = 'SELECT f.forum_id,
                       f.forum_name,
                       t.topic_id,
                       t.topic_title,
                       t.topic_replies,
                       t.topic_views,
                       t.topic_poster,
                       t.topic_status,
                       t.topic_type,
                       t.topic_last_post_id,
                       t.topic_last_poster_id,
                       t.topic_last_poster_name,
                       t.topic_last_post_time,
                       t.topic_approved,
                       u.user_avatar,
                       u.user_avatar_type,
                       tw.notify_status
                FROM '. TOPICS_TABLE .' t
                    LEFT JOIN ' . FORUMS_TABLE .' f ON (t.forum_id = f.forum_id)
                    LEFT JOIN ' . USERS_TABLE . ' u ON (t.topic_last_poster_id = u.user_id)
                    LEFT JOIN ' . TOPICS_WATCH_TABLE . ' tw ON (tw.user_id = ' . $user->data['user_id'] . ' AND t.topic_id = tw.topic_id)
                WHERE ' . $db->sql_in_set('t.topic_id', $unread_tids) . '
                ORDER BY t.topic_last_post_time DESC';
        
        $result = $db->sql_query_limit($sql, $sql_limit, $start);
        
        $tids = array();
        $rowset = array();
        while ($row = $db->sql_fetchrow($result))
        {
            $rowset[] = $row;
            $tids[] = $row['topic_id'];
        }
        $db->sql_freeresult($result);
        
        // get participated users of each topic
//        get_participated_user_avatars($tids);
//        global $topic_users, $user_avatar;
        
        foreach ($rowset as $row)
        {
            $topic_id = $row['topic_id'];
            $forum_id = $row['forum_id'];
            
            $short_content = get_short_content($row['topic_last_post_id']);
            $user_avatar_url = get_user_avatar_url($row['user_avatar'], $row['user_avatar_type']);
            
            $allow_change_type = ($auth->acl_get('m_', $forum_id) || ($user->data['is_registered'] && $user->data['user_id'] == $row['topic_poster'])) ? true : false;
            
//            $icon_urls = array();
//            foreach($topic_users[$topic_id] as $posterid){
//                $icon_urls[] = new xmlrpcval($user_avatar[$posterid], 'string');
//            }
            
            if (empty($forum_id))
            {
                $user->setup('viewforum');
                $forum_id = 0;
                $row['forum_name'] = $user->lang['ANNOUNCEMENTS'];
            }
            
            if (empty($row['forum_name'])) $row['forum_name'] = 'Forum';
            
            $xmlrpc_topic = new xmlrpcval(array(
                'forum_id'          => new xmlrpcval($forum_id),
                'forum_name'        => new xmlrpcval(html_entity_decode($row['forum_name']), 'base64'),
                'topic_id'          => new xmlrpcval($topic_id),
                'topic_title'       => new xmlrpcval(html_entity_decode(strip_tags(censor_text($row['topic_title']))), 'base64'),
                'reply_number'      => new xmlrpcval($row['topic_replies'], 'int'),
                'new_post'          => new xmlrpcval(true, 'boolean'),
                'view_number'       => new xmlrpcval($row['topic_views'], 'int'),
                'short_content'     => new xmlrpcval($short_content, 'base64'),
                'post_author_id'    => new xmlrpcval($row['topic_last_poster_id']),
                'post_author_name'  => new xmlrpcval(html_entity_decode($row['topic_last_poster_name']), 'base64'),
                'post_time'         => new xmlrpcval(mobiquo_iso8601_encode($row['topic_last_post_time']), 'dateTime.iso8601'),
                'icon_url'          => new xmlrpcval($user_avatar_url),
//                'icon_urls'         => new xmlrpcval($icon_urls, 'array'),
                
                'can_delete'        => new xmlrpcval($auth->acl_get('m_delete', $forum_id), 'boolean'),
                'can_move'          => new xmlrpcval($auth->acl_get('m_move', $forum_id), 'boolean'),
                'can_subscribe'     => new xmlrpcval(($config['email_enable'] || $config['jab_enable']) && $config['allow_topic_notify'] && $user->data['is_registered'], 'boolean'), 
                'is_subscribed'     => new xmlrpcval(!is_null($row['notify_status']) && $row['notify_status'] !== '' ? true : false, 'boolean'),
                'can_close'         => new xmlrpcval($auth->acl_get('m_lock', $forum_id) || ($auth->acl_get('f_user_lock', $forum_id) && $user->data['is_registered'] && $user->data['user_id'] == $row['topic_poster']), 'boolean'),
                'is_closed'         => new xmlrpcval($row['topic_status'] == ITEM_LOCKED, 'boolean'),
                'can_stick'         => new xmlrpcval($allow_change_type && $auth->acl_get('f_sticky', $forum_id), 'boolean'),
                'is_sticky'         => new xmlrpcval($row['topic_type'] == POST_STICKY, 'boolean'),
                'can_approve'       => new xmlrpcval($auth->acl_get('m_approve', $forum_id) && !$row['topic_approved'], 'boolean'),
                'is_approved'       => new xmlrpcval($row['topic_approved'] ? true : false, 'boolean'),
            ), 'struct');
    
            $topic_list[] = $xmlrpc_topic;
        }
    }

    $response = new xmlrpcval(
        array(
            'total_topic_num' => new xmlrpcval(count($unread_tids), 'int'),
            'topics'          => new xmlrpcval($topic_list, 'array'),
        ),
        'struct'
    );

    return new xmlrpcresp($response);
}
