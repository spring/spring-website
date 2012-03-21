<?php
/**
*
* @copyright (c) 2009 Quoord Systems Limited
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

defined('IN_MOBIQUO') or exit;

function get_topic_func($xmlrpc_params)
{
    global $db, $auth, $user, $config;

    $params = php_xmlrpc_decode($xmlrpc_params);
    
    $start_num  = 0;
    $end_num    = 19;
    $topic_type = '';
    
    if (!isset($params[0]))     // forum id undefine
    {
        return get_error(1);
    }
    else if ($params[0] === 0)  // forum id equal 0
    {
        return get_error(3);
    }

    // get forum id from parameters
    $forum_id = $params[0];
    
    // get start index of topic from parameters
    if (isset($params[1]) && is_int($params[1]))
    {
        $start_num = $params[1];
    }
    
    // get end index of topic from parameters
    if (isset($params[2]) && is_int($params[2]))
    {
        $end_num = $params[2];
    }
    
    // check if topic index is out of range
    if ($start_num > $end_num)
    {
        return get_error(5);
    }
    
    // return at most 50 topics
    if ($end_num - $start_num >= 50)
    {
        $end_num = $start_num + 49;
    }

    // check if need sticky/announce topic only
    if (isset($params[3]))
    {
        // check if need sticky topic only
        if ($params[3] == 'TOP')
        {
            $topic_type = POST_STICKY;
            $start_num  = 0;
            $end_num    = 19;
        }
        // check if need announce topic only
        else if ($params[3] == 'ANN')
        {
            $topic_type = POST_ANNOUNCE . ', ' . POST_GLOBAL;
            $start_num  = 0;
            $end_num    = 19;
        }
    }

//    $default_sort_days  = (!empty($user->data['user_topic_show_days'])) ? $user->data['user_topic_show_days'] : 0;
//    $default_sort_key   = (!empty($user->data['user_topic_sortby_type'])) ? $user->data['user_topic_sortby_type'] : 't';
//    $default_sort_dir   = (!empty($user->data['user_topic_sortby_dir'])) ? $user->data['user_topic_sortby_dir'] : 'd';
//    
//    $sort_days  = request_var('st', $default_sort_days); // default to get all topic
//    $sort_key   = request_var('sk', $default_sort_key);  // default sort by last post time
//    $sort_dir   = request_var('sd', $default_sort_dir);  // default sort as DESC
    
    $sort_days = 0;
    $sort_key  = 't';
    $sort_dir  = 'd';

    //------- Grab appropriate forum data --------        
    $sql = "SELECT f.* FROM " . FORUMS_TABLE . " f WHERE f.forum_id = $forum_id";
    $result = $db->sql_query($sql);
    $forum_data = $db->sql_fetchrow($result);
    $db->sql_freeresult($result);    

    // Forum does not exist
    if (!$forum_data)
    {
        return get_error(3);
    }
    
    // Can not get topics from link forum
    if ($forum_data['forum_type'] == FORUM_LINK)
    {
        return get_error(4);
    }
    
    // Permissions check
    if (!$auth->acl_gets('f_list', 'f_read', $forum_id))
    {
        return get_error(2);
    }
    
    if (!$auth->acl_get('f_read', $forum_id))
    {
        return get_error(2);
    }
    
    
    // Forum is passworded
    if ($forum_data['forum_password'] && !check_forum_password($forum_id))
    {
        return get_error(6);
    }

    // Topic ordering options
    $sort_by_sql = array('a' => 't.topic_first_poster_name', 
                         't' => 't.topic_last_post_time',   // default one
                         'r' => 't.topic_replies', 
                         's' => 't.topic_title', 
                         'v' => 't.topic_views');
    
    // Limit topics to certain time frame, obtain correct topic count
    // global announcements must not be counted, normal announcements have to
    // be counted, as forum_topics(_real) includes them
    
    $sql_approved = ($auth->acl_get('m_approve', $forum_id)) ? '' : ' AND t.topic_approved = 1 ';

    // Get all shadow topics in this forum
    $sql = 'SELECT t.topic_moved_id, t.topic_id
            FROM ' . TOPICS_TABLE . ' t
            WHERE t.forum_id = ' . $forum_id . '
            AND t.topic_type IN (' . POST_NORMAL . ', ' . POST_STICKY . ', ' . POST_ANNOUNCE . ', ' . POST_GLOBAL . ')
            AND t.topic_status = ' . ITEM_MOVED . ' ' .
            $sql_approved;
    $result = $db->sql_query($sql);
    
    $shadow_topic_list = array();
    while ($row = $db->sql_fetchrow($result))
    {
        $shadow_topic_list[$row['topic_moved_id']] = $row['topic_id'];
    }
    $db->sql_freeresult($result);

    // Pick out those shadow topics that the user has no permission to access
    if (!empty($shadow_topic_list))
    {
        $sql = 'SELECT t.topic_id, t.forum_id
                FROM ' . TOPICS_TABLE . ' t
                WHERE ' . $db->sql_in_set('t.topic_id', array_keys($shadow_topic_list));
        $result = $db->sql_query($sql);
        
        while ($row = $db->sql_fetchrow($result))
        {
            if ($auth->acl_get('f_read', $row['forum_id']))
            {
                unset($shadow_topic_list[$row['topic_id']]);
            }
        }
        $db->sql_freeresult($result);
    }

    // Grab all topic data
    $topic_list = array();
    
    $sql_limit = $end_num - $start_num + 1;  // num of topics needs to be return, default is 20, at most 50
    $sql_sort_order = $sort_by_sql[$sort_key] . ' ' . (($sort_dir == 'd') ? 'DESC' : 'ASC');
    $sql_shadow_out = empty($shadow_topic_list) ? '' : 'AND ' . $db->sql_in_set('t.topic_moved_id', $shadow_topic_list, true);
        
    if (!empty($topic_type)) // get top 20 announce/sticky topics only if need
    {
        $sql = 'SELECT t.*, tw.notify_status, bm.topic_id as bookmarked
                FROM ' . TOPICS_TABLE . ' t
                    LEFT JOIN ' . TOPICS_WATCH_TABLE . ' tw ON (tw.user_id = ' . $user->data['user_id'] . ' AND t.topic_id = tw.topic_id) 
                    LEFT JOIN ' . BOOKMARKS_TABLE . ' bm ON (bm.user_id = ' . $user->data['user_id'] . ' AND t.topic_id = bm.topic_id) 
                WHERE t.forum_id IN (' . $forum_id . ', 0' . ')
                AND t.topic_type IN (' . $topic_type . ') ' .
                $sql_shadow_out . ' ' .
                $sql_approved . '
                ORDER BY ' . $sql_sort_order;
        $result = $db->sql_query_limit($sql, $sql_limit, $start_num);
    }
    else    // get normal topics from $start_num to $end_num
    {
        // get total number of normal topics
        $sql = 'SELECT count(t.topic_id) AS num_topics
                FROM ' . TOPICS_TABLE . ' t
                WHERE t.forum_id = ' . $forum_id.'
                AND t.topic_type = ' . POST_NORMAL . ' ' .
                $sql_shadow_out . ' ' .
                $sql_approved;
        $result = $db->sql_query($sql);
        $topics_count = (int) $db->sql_fetchfield('num_topics');
        $db->sql_freeresult($result);
                
        // If the user is trying to reach late pages, start searching from the end
        $store_reverse = false;

        if ($start_num > $topics_count / 2)
        {
            $store_reverse = true;
        
            if ($start_num + $sql_limit > $topics_count)
            {
                $sql_limit = min($sql_limit, max(1, $topics_count - $start_num));
            }
            
            // Select the sort order
            $sql_sort_order = $sort_by_sql[$sort_key] . ' ' . (($sort_dir == 'd') ? 'ASC' : 'DESC');
            $start_num = max(0, $topics_count - $sql_limit - $start_num);
        }
        
        $sql = 'SELECT t.*, u.user_avatar, u.user_avatar_type, tw.notify_status, bm.topic_id as bookmarked
                FROM (' . TOPICS_TABLE . ' t ,'. USERS_TABLE . ' u)
                    LEFT JOIN ' . TOPICS_WATCH_TABLE . ' tw ON (tw.user_id = ' . $user->data['user_id'] . ' AND t.topic_id = tw.topic_id) 
                    LEFT JOIN ' . BOOKMARKS_TABLE . ' bm ON (bm.user_id = ' . $user->data['user_id'] . ' AND t.topic_id = bm.topic_id) 
                WHERE t.forum_id = ' . $forum_id.'
                AND t.topic_type = ' . POST_NORMAL . ' 
                AND t.topic_poster = u.user_id ' .' '.
                $sql_shadow_out . ' ' .
                $sql_approved . '
                ORDER BY ' . $sql_sort_order;

        $result = $db->sql_query_limit($sql, $sql_limit, $start_num);
    }

    $rowset = array();
    while ($row = $db->sql_fetchrow($result))
    {
        $rowset[] = $row;
    }
    $db->sql_freeresult($result);
    
    $topic_list = array();
    foreach($rowset as $row)
    {        
        $replies = ($auth->acl_get('m_approve', $forum_id)) ? $row['topic_replies_real'] : $row['topic_replies'];
        $short_content = get_short_content($row['topic_first_post_id']);
        $user_avatar_url = get_user_avatar_url($row['user_avatar'], $row['user_avatar_type']);
        $topic_tracking = get_complete_topic_tracking($forum_id, $topic_id);
        $new_post = $topic_tracking[$topic_id] < $row['topic_last_post_time'] ? true : false;
        
        $allow_change_type = ($auth->acl_get('m_', $forum_id) || ($user->data['is_registered'] && $user->data['user_id'] == $row['topic_poster'])) ? true : false;

        $xmlrpc_topic = new xmlrpcval(array(
            'forum_id'          => new xmlrpcval($forum_id),
            'topic_id'          => new xmlrpcval($row['topic_moved_id'] ? $row['topic_moved_id'] : $row['topic_id']),
            'topic_title'       => new xmlrpcval(html_entity_decode(strip_tags(censor_text($row['topic_title'])), ENT_QUOTES, 'UTF-8'), 'base64'),
            'topic_author_id'   => new xmlrpcval($row['topic_first_post_id']),
            'topic_author_name' => new xmlrpcval(html_entity_decode($row['topic_first_poster_name']), 'base64'),
            'last_reply_time'   => new xmlrpcval(mobiquo_iso8601_encode($row['topic_last_post_time']),'dateTime.iso8601'),
            'reply_number'      => new xmlrpcval($replies, 'int'),
            'view_number'       => new xmlrpcval($row['topic_views'], 'int'),
            'short_content'     => new xmlrpcval($short_content, 'base64'),
            'new_post'          => new xmlrpcval($new_post, 'boolean'),
            'icon_url'          => new xmlrpcval($user_avatar_url),
            'can_delete'        => new xmlrpcval($auth->acl_get('m_delete', $forum_id), 'boolean'),
            'can_subscribe'     => new xmlrpcval(($config['email_enable'] || $config['jab_enable']) && $config['allow_topic_notify'] && $user->data['is_registered'], 'boolean'), 
            'can_bookmark'      => new xmlrpcval($user->data['is_registered'] && $config['allow_bookmarks'], 'boolean'),
            'issubscribed'      => new xmlrpcval(!is_null($row['notify_status']) && $row['notify_status'] !== '' ? true : false, 'boolean'),
            'is_subscribed'     => new xmlrpcval(!is_null($row['notify_status']) && $row['notify_status'] !== '' ? true : false, 'boolean'),
            'isbookmarked'      => new xmlrpcval($row['bookmarked'] ? true : false, 'boolean'),
            'can_close'         => new xmlrpcval($auth->acl_get('m_lock', $forum_id) || ($auth->acl_get('f_user_lock', $forum_id) && $user->data['is_registered'] && $user->data['user_id'] == $row['topic_poster']), 'boolean'),
            'is_closed'         => new xmlrpcval($row['topic_status'] == ITEM_LOCKED, 'boolean'),
            'can_stick'         => new xmlrpcval($allow_change_type && $auth->acl_get('f_sticky', $forum_id) && $row['topic_type'] != POST_STICKY, 'boolean'),
            'to_normal'         => new xmlrpcval($allow_change_type && $auth->acl_gets('f_sticky', 'f_announce', $forum_id) && $row['topic_type'] != POST_NORMAL, 'boolean'),
            'can_move'          => new xmlrpcval($auth->acl_get('m_move', $forum_id), 'boolean'),
            
            'attachment'        => new xmlrpcval($auth->acl_get('u_download') && $auth->acl_get('f_download', $forum_id) && $row['topic_attachment'] ? 1 : 0, 'string'),
        ), 'struct');
        
        $topic_list[] = $xmlrpc_topic;
        unset($xmlrpc_topic);
    }

    if ($store_reverse)
    {
        $topic_list = array_reverse($topic_list);
    }

    if (!empty($topic_type))
    {
        $topic_num = count($topic_list);
    }
    else
    {
        $topic_num = $topics_count;
    }
    
    $allowed = $auth->acl_get('f_attach', $forum_id) && $auth->acl_get('u_attach') && $config['allow_attachments'] && @ini_get('file_uploads') != '0' && strtolower(@ini_get('file_uploads')) != 'off';
    
    $response = new xmlrpcval(
        array(
            'total_topic_num' => new xmlrpcval($topic_num, 'int'),
            'forum_id'        => new xmlrpcval($forum_id, 'string'),
            'forum_name'      => new xmlrpcval(html_entity_decode($forum_data['forum_name']), 'base64'),
            'can_post'        => new xmlrpcval($auth->acl_get('f_post', $forum_id), 'boolean'),
            'can_upload'      => new xmlrpcval($allowed, 'boolean'),
            'topics'          => new xmlrpcval($topic_list, 'array'),
        ),
        'struct'
    );

    return new xmlrpcresp($response);
}
