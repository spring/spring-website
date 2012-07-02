<?php
/**
*
* @copyright (c) 2009, 2010, 2011 Quoord Systems Limited
* @license http://opensource.org/licenses/gpl-2.0.php GNU Public License (GPLv2)
*
*/

defined('IN_MOBIQUO') or exit;

function get_topic_func($xmlrpc_params)
{
    global $db, $auth, $user, $config;
    
    $user->setup('viewforum');
    
    $params = php_xmlrpc_decode($xmlrpc_params);
    
    list($start, $limit) = process_page($params[1], $params[2]);
    
    // get forum id from parameters
    $forum_id = intval($params[0]);
    if (!$forum_id) trigger_error('NO_FORUM');
    
    // check if need sticky/announce topic only
    $topic_type = '';
    if (isset($params[3]))
    {
        // check if need sticky topic only
        if ($params[3] == 'TOP')
        {
            $topic_type = POST_STICKY;
            $start = 0;
            $limit = 20;
        }
        // check if need announce topic only
        else if ($params[3] == 'ANN')
        {
            $topic_type = POST_ANNOUNCE . ', ' . POST_GLOBAL;
            $start = 0;
            $limit = 20;
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
    if (!$forum_data) trigger_error('NO_FORUM');
    
    // Can not get topics from link forum
    if ($forum_data['forum_type'] == FORUM_LINK)
    {
        trigger_error('NO_FORUM');
    }
    
    // Permissions check
    if (!$auth->acl_gets('f_list', 'f_read', $forum_id) || ($forum_data['forum_type'] == FORUM_LINK && $forum_data['forum_link'] && !$auth->acl_get('f_read', $forum_id)))
    {
        if ($user->data['user_id'] != ANONYMOUS)
        {
            trigger_error('SORRY_AUTH_READ');
        }
        
        trigger_error('LOGIN_VIEWFORUM');
    }
    
    
    // Forum is passworded
    if ($forum_data['forum_password'] && !check_forum_password($forum_id))
    {
        trigger_error('LOGIN_FORUM');
    }

    if (!$auth->acl_get('f_read', $forum_id))
    {
        trigger_error('NO_READ_ACCESS');
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
    
    $sql_limit = $limit;  // num of topics needs to be return, default is 20, at most 50
    $sql_sort_order = $sort_by_sql[$sort_key] . ' ' . (($sort_dir == 'd') ? 'DESC' : 'ASC');
    $sql_shadow_out = empty($shadow_topic_list) ? '' : 'AND ' . $db->sql_in_set('t.topic_moved_id', $shadow_topic_list, true);
    
    $unread_sticky_num = $unread_announce_count = 0;
    if (!empty($topic_type)) // get top 20 announce/sticky topics only if need
    {
        $sql = 'SELECT t.*, u.user_avatar, u.user_avatar_type, tw.notify_status, bm.topic_id as bookmarked
                FROM ' . TOPICS_TABLE . ' t
                    LEFT JOIN ' . USERS_TABLE . ' u ON (t.topic_poster = u.user_id)
                    LEFT JOIN ' . TOPICS_WATCH_TABLE . ' tw ON (tw.user_id = ' . $user->data['user_id'] . ' AND t.topic_id = tw.topic_id) 
                    LEFT JOIN ' . BOOKMARKS_TABLE . ' bm ON (bm.user_id = ' . $user->data['user_id'] . ' AND t.topic_id = bm.topic_id) 
                WHERE t.forum_id IN (' . $forum_id . ', 0)
                AND t.topic_type IN (' . $topic_type . ') ' .
                $sql_shadow_out . ' ' .
                $sql_approved . '
                ORDER BY ' . $sql_sort_order;
        $result = $db->sql_query_limit($sql, $sql_limit, $start);
    }
    else
    {
        // get total number of unread sticky topics number
        $sql = 'SELECT t.topic_id, t.topic_last_post_time
                FROM ' . TOPICS_TABLE . ' t
                WHERE t.forum_id = ' . $forum_id.'
                AND t.topic_type = ' . POST_STICKY . ' ' .
                $sql_shadow_out . ' ' .
                $sql_approved;
        $result = $db->sql_query($sql);
        while ($row = $db->sql_fetchrow($result))
        {
            $topic_tracking = get_complete_topic_tracking($forum_id, $row['topic_id']);
            if ($topic_tracking[$row['topic_id']] < $row['topic_last_post_time'])
                $unread_sticky_num++;
        }
        $db->sql_freeresult($result);
        
        // get total number of unread announce topics number
        $sql = 'SELECT t.topic_id, t.topic_last_post_time
                FROM ' . TOPICS_TABLE . ' t
                WHERE t.forum_id IN (' . $forum_id . ', 0)
                AND t.topic_type IN (' . POST_ANNOUNCE . ', ' . POST_GLOBAL . ') ' .
                $sql_shadow_out . ' ' .
                $sql_approved;
        $result = $db->sql_query($sql);
        while ($row = $db->sql_fetchrow($result))
        {
            $topic_tracking = get_complete_topic_tracking($forum_id, $row['topic_id']);
            if ($topic_tracking[$row['topic_id']] < $row['topic_last_post_time'])
                $unread_announce_count++;
        }
        $db->sql_freeresult($result);
        
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

        if ($start > $topics_count / 2)
        {
            $store_reverse = true;
        
            if ($start + $sql_limit > $topics_count)
            {
                $sql_limit = min($sql_limit, max(1, $topics_count - $start));
            }
            
            // Select the sort order
            $sql_sort_order = $sort_by_sql[$sort_key] . ' ' . (($sort_dir == 'd') ? 'ASC' : 'DESC');
            $start = max(0, $topics_count - $sql_limit - $start);
        }
        
        $sql = 'SELECT t.*, u.user_avatar, u.user_avatar_type, tw.notify_status, bm.topic_id as bookmarked
                FROM ' . TOPICS_TABLE . ' t
                    LEFT JOIN ' . USERS_TABLE . ' u ON (t.topic_poster = u.user_id)
                    LEFT JOIN ' . TOPICS_WATCH_TABLE . ' tw ON (tw.user_id = ' . $user->data['user_id'] . ' AND t.topic_id = tw.topic_id) 
                    LEFT JOIN ' . BOOKMARKS_TABLE . ' bm ON (bm.user_id = ' . $user->data['user_id'] . ' AND t.topic_id = bm.topic_id) 
                WHERE t.forum_id = ' . $forum_id.'
                AND t.topic_type = ' . POST_NORMAL . ' ' .
                $sql_shadow_out . ' ' .
                $sql_approved . '
                ORDER BY ' . $sql_sort_order;

        $result = $db->sql_query_limit($sql, $sql_limit, $start);
    }
    
    $tids = array();
    $rowset = array();
    while ($row = $db->sql_fetchrow($result))
    {
        $rowset[] = $row;
        $tids[] = $row['topic_moved_id'] ? $row['topic_moved_id'] : $row['topic_id'];
    }
    $db->sql_freeresult($result);
    
    // get participated users of each topic
//    get_participated_user_avatars($tids);
//    global $topic_users, $user_avatar;
    
    $topic_list = array();
    foreach($rowset as $row)
    {
        $replies = ($auth->acl_get('m_approve', $forum_id)) ? $row['topic_replies_real'] : $row['topic_replies'];
        $short_content = get_short_content($row['topic_first_post_id']);
        $user_avatar_url = get_user_avatar_url($row['user_avatar'], $row['user_avatar_type']);
        $topic_tracking = get_complete_topic_tracking($forum_id, $row['topic_id']);
        $new_post = $topic_tracking[$row['topic_id']] < $row['topic_last_post_time'] ? true : false;
        
        $allow_change_type = ($auth->acl_get('m_', $forum_id) || ($user->data['is_registered'] && $user->data['user_id'] == $row['topic_poster'])) ? true : false;
        
        $topic_id = $row['topic_moved_id'] ? $row['topic_moved_id'] : $row['topic_id'];
//        $icon_urls = array();
//        foreach($topic_users[$topic_id] as $posterid){
//            $icon_urls[] = new xmlrpcval($user_avatar[$posterid], 'string');
//        }
        
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
//            'icon_urls'         => new xmlrpcval($icon_urls, 'array'),
            'attachment'        => new xmlrpcval($auth->acl_get('u_download') && $auth->acl_get('f_download', $forum_id) && $row['topic_attachment'] ? 1 : 0, 'string'),
            
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
    
    $allowed = $config['max_attachments'] && $auth->acl_get('f_attach', $forum_id) && $auth->acl_get('u_attach') && $config['allow_attachments'] && @ini_get('file_uploads') != '0' && strtolower(@ini_get('file_uploads')) != 'off';
    $max_attachment = ($auth->acl_get('a_') || $auth->acl_get('m_', $forum_id)) ? 99 : ($allowed ? $config['max_attachments'] : 0);
    $max_png_size = ($auth->acl_get('a_') || $auth->acl_get('m_', $forum_id)) ? 10485760 : ($allowed ? ($config['max_filesize'] === '0' ? 10485760 : $config['max_filesize']) : 0);
    $max_jpg_size = ($auth->acl_get('a_') || $auth->acl_get('m_', $forum_id)) ? 10485760 : ($allowed ? ($config['max_filesize'] === '0' ? 10485760 : $config['max_filesize']) : 0);
    
    $response = new xmlrpcval(
        array(
            'total_topic_num' => new xmlrpcval($topic_num, 'int'),
            'unread_sticky_count'   => new xmlrpcval($unread_sticky_num, 'int'),
            'unread_announce_count' => new xmlrpcval($unread_announce_count, 'int'),
            'forum_id'        => new xmlrpcval($forum_id, 'string'),
            'forum_name'      => new xmlrpcval(html_entity_decode($forum_data['forum_name']), 'base64'),
            'can_post'        => new xmlrpcval($auth->acl_get('f_post', $forum_id), 'boolean'),
            'can_upload'      => new xmlrpcval($allowed, 'boolean'),
            'max_attachment'  => new xmlrpcval($max_attachment, 'int'),
            'max_png_size'    => new xmlrpcval($max_png_size, 'int'),
            'max_jpg_size'    => new xmlrpcval($max_jpg_size, 'int'),
            'topics'          => new xmlrpcval($topic_list, 'array'),
        ),
        'struct'
    );

    return new xmlrpcresp($response);
}
