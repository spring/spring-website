<?php
/**
*
* @copyright (c) 2009 Quoord Systems Limited
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

defined('IN_MOBIQUO') or exit;

function get_forum_func()
{
    global $db, $auth, $user, $config, $mobiquo_config, $phpbb_home;
    
    $unread = array();
//    if ($user->data['is_registered'])
//    {    
//        $ex_fid_ary = array_unique(array_merge(array_keys($auth->acl_getf('!f_read', true)), array_keys($auth->acl_getf('!f_search', true))));
//        $not_in_fid = (sizeof($ex_fid_ary)) ? 'WHERE ' . $db->sql_in_set('f.forum_id', $ex_fid_ary, true) . " OR (f.forum_password <> '' AND fa.user_id <> " . (int) $user->data['user_id'] . ')' : "";
//        
//        $sql = 'SELECT f.forum_id, f.forum_name, f.parent_id, f.forum_type, f.right_id, f.forum_password, fa.user_id
//                FROM ' . FORUMS_TABLE . ' f
//                LEFT JOIN ' . FORUMS_ACCESS_TABLE . " fa ON (fa.forum_id = f.forum_id
//                AND fa.session_id = '" . $db->sql_escape($user->session_id) . "')
//                $not_in_fid
//                ORDER BY f.left_id";
//        $result = $db->sql_query($sql);
//        
//        while ($row = $db->sql_fetchrow($result))
//        {
//            if ($row['forum_password'] && $row['user_id'] != $user->data['user_id'])
//            {
//                $ex_fid_ary[] = (int) $row['forum_id'];
//                continue;
//            }
//        }
//        $db->sql_freeresult($result);
//    
//        // find out in which forums the user is allowed to view approved posts
//        if ($auth->acl_get('m_approve'))
//        {
//            $m_approve_fid_sql = '';
//        }
//        else if ($auth->acl_getf_global('m_approve'))
//        {
//            $m_approve_fid_ary = array_diff(array_keys($auth->acl_getf('!m_approve', true)), $ex_fid_ary);
//            $m_approve_fid_sql = ' AND (t.topic_approved = 1' . ((sizeof($m_approve_fid_ary)) ? ' OR ' . $db->sql_in_set('t.forum_id', $m_approve_fid_ary, true) : '') . ')';
//        }
//        else
//        {
//            $m_approve_fid_sql = ' AND t.topic_approved = 1';
//        }
//    
//        $sql = 'SELECT t.topic_id, t.forum_id, t.topic_last_post_time FROM ' . TOPICS_TABLE . ' t
//                WHERE t.topic_last_post_time > ' . $user->data['user_lastvisit'] . '
//                AND t.topic_moved_id = 0 ' . $m_approve_fid_sql .
//                ((sizeof($ex_fid_ary)) ? ' AND ' . $db->sql_in_set('t.forum_id', $ex_fid_ary, true) : '');
//        $result = $db->sql_query($sql);
//        
//        while ($row = $db->sql_fetchrow($result))
//        {
//            $topic_id = $row['topic_id'];
//            $forum_id = $row['forum_id'];
//            $topic_tracking = get_complete_topic_tracking($forum_id, $topic_id);
//            if ($topic_tracking[$topic_id] < $row['topic_last_post_time'])
//            {
//                $unread[$forum_id] += 1;
//            }
//        }
//        $db->sql_freeresult($result);
//    }
    
    $sql = 'SELECT f.* '. ($user->data['is_registered'] ? ', fw.notify_status' : '') . '
            FROM ' . FORUMS_TABLE . ' f ' .
            ($user->data['is_registered'] ? ' LEFT JOIN ' . FORUMS_WATCH_TABLE . ' fw ON (fw.forum_id = f.forum_id AND fw.user_id = ' . $user->data['user_id'] . ')' : '') . '
            ORDER BY f.left_id ASC';
    $result = $db->sql_query($sql, 600);
    
    $forum_rows = array();
    $forum_rows[0] = array('forum_id' => 0, 'parent_id' => -1, 'child' => array());
    while ($row = $db->sql_fetchrow($result))
    {
        $forum_id = $row['forum_id'];
        
        if ($row['forum_type'] == FORUM_CAT && ($row['left_id'] + 1 == $row['right_id']))
        {
            // Non-postable forum with no subforums, don't display
            continue;
        }

        // Skip branch
        if (isset($right_id))
        {
            if ($row['left_id'] < $right_id)
            {
                continue;
            }
            unset($right_id);
        }

        if (!$auth->acl_get('f_list', $forum_id) || (isset($mobiquo_config['hide_forum_id']) && in_array($forum_id, $mobiquo_config['hide_forum_id'])))
        {
            // if the user does not have permissions to list this forum, skip everything until next branch
            $right_id = $row['right_id'];
            continue;
        }
        
        $row['unread_count'] = 0;
        
        if ($user->data['is_registered'] && ($config['email_enable'] || $config['jab_enable']) && $config['allow_forum_notify'] && $row['forum_type'] == FORUM_POST && $auth->acl_get('f_subscribe', $forum_id))
        {
            $row['can_subscribe'] = true;
            $row['is_subscribed'] = isset($row['notify_status']) && !is_null($row['notify_status']) && $row['notify_status'] !== '' ? true : false;
        } else {
            $row['can_subscribe'] = false;
            $row['is_subscribed'] = false;
        }
        
        $forum_rows[$forum_id] = $row;
    }
    $db->sql_freeresult($result);


    $forum_rows[0] = array('parent_id' => -1, 'child' => array());
    while(empty($forum_rows[0]['child']) && count($forum_rows) > 1)
    {
        $current_parent_id = -1;
        $leaves_forum = array();
        foreach($forum_rows as $row)
        {            
            $row_parent_id = $row['parent_id'];
    
            if ($row_parent_id != $current_parent_id)
            {
                if(isset($leaves_forum[$row_parent_id]))
                {
                    $leaves_forum[$row_parent_id] = array();
                }
                else
                {
                    if(isset($leaves_forum[$forum_rows[$row_parent_id]['parent_id']]))
                    {
                        $leaves_forum[$forum_rows[$row_parent_id]['parent_id']] = array();
                    }
                    $leaves_forum[$row_parent_id][] = $row['forum_id'];
                }
                $current_parent_id = $row_parent_id;
            }
            else if ($row_parent_id == $current_parent_id)
            {
                if(!empty($leaves_forum[$row_parent_id]))
                {
                    $leaves_forum[$row_parent_id][] = $row['forum_id'];
                }
            }
        }
        
        foreach($leaves_forum as $node_forum_id => $leaves)
        {
            foreach($leaves as $forum_id)
            {
                $logo_url = '';
                if (file_exists("./forum_icons/$forum_id.png"))
                {
                    $logo_url = $phpbb_home."mobiquo/forum_icons/$forum_id.png";
                }
                else if (file_exists("./forum_icons/$forum_id.jpg"))
                {
                    $logo_url = $phpbb_home."mobiquo/forum_icons/$forum_id.jpg";
                }
                else if (file_exists("./forum_icons/default.png"))
                {
                    $logo_url = $phpbb_home."mobiquo/forum_icons/default.png";
                }
                else if ($forum_rows[$forum_id]['forum_image'])
                {
                    $logo_url = $phpbb_home.$forum_rows[$forum_id]['forum_image'];
                }
                
                $unread_count = count(get_unread_topics(false, "AND t.forum_id = $forum_id"));
                $forum_rows[$forum_id]['unread_count'] += $unread_count;
                if ($forum_rows[$forum_id]['unread_count'])
                {
                    $forum_rows[$forum_rows[$forum_id]['parent_id']]['unread_count'] += $forum_rows[$forum_id]['unread_count'];
                }
                
                $xmlrpc_forum = new xmlrpcval(array(
                    'forum_id'      => new xmlrpcval($forum_id),
                    'forum_name'    => new xmlrpcval(html_entity_decode($forum_rows[$forum_id]['forum_name']), 'base64'),
                    'description'   => new xmlrpcval(html_entity_decode($forum_rows[$forum_id]['forum_desc']), 'base64'),
                    'parent_id'     => new xmlrpcval($node_forum_id),
                    'logo_url'      => new xmlrpcval($logo_url),
                    'new_post'      => new xmlrpcval($forum_rows[$forum_id]['unread_count'] ? true : false, 'boolean'),
                    'unread_count'  => new xmlrpcval($forum_rows[$forum_id]['unread_count'], 'int'),
                    'is_protected'  => new xmlrpcval($forum_rows[$forum_id]['forum_password'] ? true : false, 'boolean'),
                    'url'           => new xmlrpcval($forum_rows[$forum_id]['forum_link']),
                    'sub_only'      => new xmlrpcval(($forum_rows[$forum_id]['forum_type'] == FORUM_POST) ? false : true, 'boolean'),
                    
                    'can_subscribe' => new xmlrpcval($forum_rows[$forum_id]['can_subscribe'], 'boolean'),
                    'is_subscribed' => new xmlrpcval($forum_rows[$forum_id]['is_subscribed'], 'boolean'),
                ), 'struct');
                
                if (isset($forum_rows[$forum_id]['child']))
                {
                    $xmlrpc_forum->addStruct(array('child' => new xmlrpcval($forum_rows[$forum_id]['child'], 'array')));
                }
                
                $forum_rows[$node_forum_id]['child'][] = $xmlrpc_forum;
                unset($forum_rows[$forum_id]);
            }
        }
    }
    
    $response = new xmlrpcval($forum_rows[0]['child'], 'array');
    
    return new xmlrpcresp($response);
} // End of get_forum_func

if (!function_exists(get_unread_topics))
{
    function get_unread_topics($user_id = false, $sql_extra = '', $sql_sort = '', $sql_limit = 1001)
    {
        global $config, $db, $user;
    
        $user_id = ($user_id === false) ? (int) $user->data['user_id'] : (int) $user_id;
    
        // Data array we're going to return
        $unread_topics = array();
    
        if (empty($sql_sort))
        {
            $sql_sort = 'ORDER BY t.topic_last_post_time DESC';
        }
    
        if ($config['load_db_lastread'] && $user->data['is_registered'])
        {
            // Get list of the unread topics
            $last_mark = $user->data['user_lastmark'];
    
            $sql_array = array(
                'SELECT'        => 't.topic_id, t.topic_last_post_time, tt.mark_time as topic_mark_time, ft.mark_time as forum_mark_time',
    
                'FROM'            => array(TOPICS_TABLE => 't'),
    
                'LEFT_JOIN'        => array(
                    array(
                        'FROM'    => array(TOPICS_TRACK_TABLE => 'tt'),
                        'ON'    => "tt.user_id = $user_id AND t.topic_id = tt.topic_id",
                    ),
                    array(
                        'FROM'    => array(FORUMS_TRACK_TABLE => 'ft'),
                        'ON'    => "ft.user_id = $user_id AND t.forum_id = ft.forum_id",
                    ),
                ),
    
                'WHERE'            => "
                    (
                    (tt.mark_time IS NOT NULL AND t.topic_last_post_time > tt.mark_time) OR
                    (tt.mark_time IS NULL AND ft.mark_time IS NOT NULL AND t.topic_last_post_time > ft.mark_time) OR
                    (tt.mark_time IS NULL AND ft.mark_time IS NULL AND t.topic_last_post_time > $last_mark)
                    )
                    $sql_extra
                    $sql_sort",
            );
    
            $sql = $db->sql_build_query('SELECT', $sql_array);
            $result = $db->sql_query_limit($sql, $sql_limit);
    
            while ($row = $db->sql_fetchrow($result))
            {
                $topic_id = (int) $row['topic_id'];
                $unread_topics[$topic_id] = ($row['topic_mark_time']) ? (int) $row['topic_mark_time'] : (($row['forum_mark_time']) ? (int) $row['forum_mark_time'] : $last_mark);
            }
            $db->sql_freeresult($result);
        }
        else if ($config['load_anon_lastread'] || $user->data['is_registered'])
        {
            global $tracking_topics;
    
            if (empty($tracking_topics))
            {
                $tracking_topics = request_var($config['cookie_name'] . '_track', '', false, true);
                $tracking_topics = ($tracking_topics) ? tracking_unserialize($tracking_topics) : array();
            }
    
            if (!$user->data['is_registered'])
            {
                $user_lastmark = (isset($tracking_topics['l'])) ? base_convert($tracking_topics['l'], 36, 10) + $config['board_startdate'] : 0;
            }
            else
            {
                $user_lastmark = (int) $user->data['user_lastmark'];
            }
    
            $sql = 'SELECT t.topic_id, t.forum_id, t.topic_last_post_time
                FROM ' . TOPICS_TABLE . ' t
                WHERE t.topic_last_post_time > ' . $user_lastmark . "
                $sql_extra
                $sql_sort";
            $result = $db->sql_query_limit($sql, $sql_limit);
    
            while ($row = $db->sql_fetchrow($result))
            {
                $forum_id = (int) $row['forum_id'];
                $topic_id = (int) $row['topic_id'];
                $topic_id36 = base_convert($topic_id, 10, 36);
    
                if (isset($tracking_topics['t'][$topic_id36]))
                {
                    $last_read = base_convert($tracking_topics['t'][$topic_id36], 36, 10) + $config['board_startdate'];
    
                    if ($row['topic_last_post_time'] > $last_read)
                    {
                        $unread_topics[$topic_id] = $last_read;
                    }
                }
                else if (isset($tracking_topics['f'][$forum_id]))
                {
                    $mark_time = base_convert($tracking_topics['f'][$forum_id], 36, 10) + $config['board_startdate'];
    
                    if ($row['topic_last_post_time'] > $mark_time)
                    {
                        $unread_topics[$topic_id] = $mark_time;
                    }
                }
                else
                {
                    $unread_topics[$topic_id] = $user_lastmark;
                }
            }
            $db->sql_freeresult($result);
        }
    
        return $unread_topics;
    }
}
