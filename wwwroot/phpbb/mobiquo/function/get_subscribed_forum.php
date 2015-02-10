<?php
/**
*
* @copyright (c) 2009, 2010, 2011 Quoord Systems Limited
* @license http://opensource.org/licenses/gpl-2.0.php GNU Public License (GPLv2)
*
*/

defined('IN_MOBIQUO') or exit;

function get_subscribed_forum_func()
{
    global $config, $db, $user, $auth, $mobiquo_config, $phpbb_home, $request;

    $user->setup('ucp');
    
    if (!$user->data['is_registered']) trigger_error('LOGIN_EXPLAIN_UCP');
    
    $forum_list = array();
    if ($config['allow_forum_notify'])
    {
        $forbidden_forums = $auth->acl_getf('!f_read', true);
        $forbidden_forums = array_unique(array_keys($forbidden_forums));
        
        if (isset($mobiquo_config['hide_forum_id']))
        {
            $forbidden_forums = array_unique(array_merge($forbidden_forums, $mobiquo_config['hide_forum_id']));
        }
    
        $sql_array = array(
            'SELECT' => 'f.*',
    
            'FROM'   => array(
                FORUMS_WATCH_TABLE    => 'fw',
                FORUMS_TABLE        => 'f'
            ),
    
            'WHERE'  => 'fw.user_id = ' . $user->data['user_id'] . '
                AND f.forum_id = fw.forum_id
                AND ' . $db->sql_in_set('f.forum_id', $forbidden_forums, true, true),
    
            'ORDER_BY'    => 'left_id'
        );
    
        if ($config['load_db_lastread'])
        {
            $sql_array['LEFT_JOIN'] = array(
                array(
                    'FROM'    => array(FORUMS_TRACK_TABLE => 'ft'),
                    'ON'    => 'ft.user_id = ' . $user->data['user_id'] . ' AND ft.forum_id = f.forum_id'
                )
            );
    
            $sql_array['SELECT'] .= ', ft.mark_time ';
        }
        else
        {
            $TTcookieName = $config['cookie_name'] . '_track';
            $tracking_topics = $request->variable($TTcookieName,'',false,\phpbb\request\request_interface::COOKIE);
            if(isset($tracking_topics)) 
                $tracking_topics = (STRIP) ? stripslashes($tracking_topics) : $tracking_topics;
            $tracking_topics = ($tracking_topics) ? tracking_unserialize($tracking_topics) : array();
        }
    
        $sql = $db->sql_build_query('SELECT', $sql_array);
        $result = $db->sql_query($sql);
        
        $forum_list = array();
        while ($row = $db->sql_fetchrow($result))
        {
            $forum_id = $row['forum_id'];
            
            if ($config['load_db_lastread'])
            {
                $forum_check = (!empty($row['mark_time'])) ? $row['mark_time'] : $user->data['user_lastmark'];
            }
            else
            {
                $forum_check = (isset($tracking_topics['f'][$forum_id])) ? (int) (base_convert($tracking_topics['f'][$forum_id], 36, 10) + $config['board_startdate']) : $user->data['user_lastmark'];
            }
    
            $unread_forum = ($row['forum_last_post_time'] > $forum_check) ? true : false;
            
            $logo_url = '';
            if (file_exists("./forum_icons/$forum_id.png"))
            {
                $logo_url = $phpbb_home.$config['tapatalkdir']."/forum_icons/$forum_id.png";
            }
            else if (file_exists("./forum_icons/$forum_id.jpg"))
            {
                $logo_url = $phpbb_home.$config['tapatalkdir']."/forum_icons/$forum_id.jpg";
            }
            else if (file_exists("./forum_icons/default.png"))
            {
                $logo_url = $phpbb_home.$config['tapatalkdir']."/forum_icons/default.png";
            }
            else if ($row['forum_image'])
            {
                $logo_url = $phpbb_home.$row['forum_image'];
            }
            
            $xmlrpc_forum = array(
                'forum_id'      => (string)$forum_id,
                'forum_name'    => html_entity_decode($row['forum_name']),
                'icon_url'      => $logo_url,
                'is_protected'  => $row['forum_password'] ? true : false,
                'sub_only'      => ($row['forum_type'] == FORUM_POST) ? false : true,
                'new_post'      => $unread_forum,
            );
    
            $forum_list[] = $xmlrpc_forum;
        }
        $db->sql_freeresult($result);
    }

    $forum_num = count($forum_list);
    
    $response = array(
        'total_forums_num' => (int)$forum_num, 
        'forums'    => $forum_list,
    );

    return $response;
}
