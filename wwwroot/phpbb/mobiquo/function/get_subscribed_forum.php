<?php
/**
*
* @copyright (c) 2009 Quoord Systems Limited
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

defined('IN_MOBIQUO') or exit;

function get_subscribed_forum_func()
{
    global $config, $db, $user, $auth, $mobiquo_config, $phpbb_home;
    
    if (!$user->data['is_registered'])
    {
        return get_error(20, 'Please login first');
    }
    
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
            $tracking_topics = (isset($_COOKIE[$config['cookie_name'] . '_track'])) ? ((STRIP) ? stripslashes($_COOKIE[$config['cookie_name'] . '_track']) : $_COOKIE[$config['cookie_name'] . '_track']) : '';
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
    
            // Which folder should we display?
            if ($row['forum_status'] == ITEM_LOCKED)
            {
                $folder_image = ($unread_forum) ? 'forum_unread_locked' : 'forum_read_locked';
                $folder_alt = 'FORUM_LOCKED';
            }
            else
            {
                $folder_image = ($unread_forum) ? 'forum_unread' : 'forum_read';
                $folder_alt = ($unread_forum) ? 'NEW_POSTS' : 'NO_NEW_POSTS';
            }
    
            // Create last post link information, if appropriate
            if ($row['forum_last_post_id'])
            {
                $last_post_time = $row['forum_last_post_time'];
                $last_post_url = append_sid("{$phpbb_root_path}viewtopic.$phpEx", "f=$forum_id&amp;p=" . $row['forum_last_post_id']) . '#p' . $row['forum_last_post_id'];
            }
            else
            {
                $last_post_time = $last_post_url = '';
            }
    
//            $template->assign_block_vars('forumrow', array(
//                'FORUM_ID'                => $forum_id,
//                'FORUM_FOLDER_IMG'        => $user->img($folder_image, $folder_alt),
//                'FORUM_FOLDER_IMG_SRC'    => $user->img($folder_image, $folder_alt, false, '', 'src'),
//                'FORUM_IMAGE'            => ($row['forum_image']) ? '<img src="' . $phpbb_root_path . $row['forum_image'] . '" alt="' . $user->lang[$folder_alt] . '" />' : '',
//                'FORUM_IMAGE_SRC'        => ($row['forum_image']) ? $phpbb_root_path . $row['forum_image'] : '',
//                'FORUM_NAME'            => $row['forum_name'],
//                'FORUM_DESC'            => generate_text_for_display($row['forum_desc'], $row['forum_desc_uid'], $row['forum_desc_bitfield'], $row['forum_desc_options']),
//                'LAST_POST_SUBJECT'        => $row['forum_last_post_subject'],
//                'LAST_POST_TIME'        => $last_post_time,
//    
//                'LAST_POST_AUTHOR'            => get_username_string('username', $row['forum_last_poster_id'], $row['forum_last_poster_name'], $row['forum_last_poster_colour']),
//                'LAST_POST_AUTHOR_COLOUR'    => get_username_string('colour', $row['forum_last_poster_id'], $row['forum_last_poster_name'], $row['forum_last_poster_colour']),
//                'LAST_POST_AUTHOR_FULL'        => get_username_string('full', $row['forum_last_poster_id'], $row['forum_last_poster_name'], $row['forum_last_poster_colour']),
//                'U_LAST_POST_AUTHOR'        => get_username_string('profile', $row['forum_last_poster_id'], $row['forum_last_poster_name'], $row['forum_last_poster_colour']),
//    
//                'U_LAST_POST'            => $last_post_url,
//                'U_VIEWFORUM'            => append_sid("{$phpbb_root_path}viewforum.$phpEx", 'f=' . $row['forum_id']))
//            );
            
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
            else if ($row['forum_image'])
            {
                $logo_url = $phpbb_home.$row['forum_image'];
            }
            
            $xmlrpc_forum = new xmlrpcval(array(
                'forum_id'      => new xmlrpcval($forum_id),
                'forum_name'    => new xmlrpcval(html_entity_decode($row['forum_name']), 'base64'),
                'icon_url'      => new xmlrpcval($logo_url),
                'is_protected'  => new xmlrpcval($row['forum_password'] ? true : false, 'boolean'),
                'sub_only'      => new xmlrpcval(($row['forum_type'] == FORUM_POST) ? false : true, 'boolean'),
            ), 'struct');
    
            $forum_list[] = $xmlrpc_forum;
        }
        $db->sql_freeresult($result);
    }

    $forum_num = count($forum_list);
    
    $response = new xmlrpcval(
        array(
            'total_forums_num' => new xmlrpcval($forum_num, 'int'),
            'forums'    => new xmlrpcval($forum_list, 'array'),
        ),
        'struct'
    );

    return new xmlrpcresp($response);
}
