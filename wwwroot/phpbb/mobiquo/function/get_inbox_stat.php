<?php
/**
*
* @copyright (c) 2009, 2010, 2011 Quoord Systems Limited
* @license http://opensource.org/licenses/gpl-2.0.php GNU Public License (GPLv2)
*
*/

defined('IN_MOBIQUO') or exit;

function get_inbox_stat_func()
{
    global $db, $user, $config, $auth, $mobiquo_config;
    
    $user->setup('ucp');
    if (!$user->data['is_registered']) trigger_error('LOGIN_EXPLAIN_UCP');
    
    // get unread number of subscribed topic
    $forbidden_forums = $auth->acl_getf('!f_read', true);
    $forbidden_forums = array_unique(array_keys($forbidden_forums));
    
    if (isset($mobiquo_config['hide_forum_id']))
    {
        $forbidden_forums = array_unique(array_merge($forbidden_forums, $mobiquo_config['hide_forum_id']));
    }
    
    $sql = 'SELECT t.topic_id, t.forum_id, t.topic_last_post_time
            FROM ' . TOPICS_WATCH_TABLE . ' tw
            LEFT JOIN ' . TOPICS_TABLE . ' t ON tw.topic_id=t.topic_id
            WHERE tw.user_id = ' . $user->data['user_id'] . '
                AND ' . $db->sql_in_set('t.forum_id', $forbidden_forum_ary, true, true) . '
                AND t.topic_last_post_time > ' . $user->data['user_lastvisit'];
    $result = $db->sql_query($sql);
    $subscribed_topic_unread_count = 0;
    while ($row = $db->sql_fetchrow($result))
    {
        $topic_id = $row['topic_id'];
        $forum_id = $row['forum_id'];
        $topic_tracking = get_complete_topic_tracking($forum_id, $topic_id);
        if ($topic_tracking[$topic_id] < $row['topic_last_post_time'])
        {
            $subscribed_topic_unread_count++;
        }
    }
    $db->sql_freeresult($result);
    
    $inbox_unread_count = $user->data['user_unread_privmsg'] ? $user->data['user_unread_privmsg'] : 0;
    
    $result = new xmlrpcval(array(
        'inbox_unread_count' => new xmlrpcval($inbox_unread_count, 'int'),
        'subscribed_topic_unread_count' => new xmlrpcval($subscribed_topic_unread_count, 'int'),
    ), 'struct');

    return new xmlrpcresp($result);
}
