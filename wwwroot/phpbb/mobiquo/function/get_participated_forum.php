<?php
/**
*
* @copyright (c) 2009, 2010, 2011 Quoord Systems Limited
* @license http://opensource.org/licenses/gpl-2.0.php GNU Public License (GPLv2)
*
*/

defined('IN_MOBIQUO') or exit;

function get_participated_forum_func()
{
    global $config, $db, $user, $auth, $mobiquo_config, $phpbb_home;
    
    $user->setup('ucp');
    
    if (!$user->data['is_registered']) trigger_error('LOGIN_EXPLAIN_UCP');

    $forbidden_forums = $auth->acl_getf('!f_read', true);
    $forbidden_forums = array_unique(array_keys($forbidden_forums));
    
    if (isset($mobiquo_config['hide_forum_id']))
    {
        $forbidden_forums = array_unique(array_merge($forbidden_forums, $mobiquo_config['hide_forum_id']));
    }

    $sql_array = array(
        'SELECT' => 'DISTINCT f.forum_id, f.*',

        'FROM'   => array(
            POSTS_TABLE     => 'p',
            FORUMS_TABLE    => 'f'
        ),

        'WHERE'  => 'p.poster_id = ' . $user->data['user_id'] . '
            AND f.forum_id = p.forum_id
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
            'new_post'      => new xmlrpcval($unread_forum, 'boolean'),
        ), 'struct');

        $forum_list[] = $xmlrpc_forum;
    }
    $db->sql_freeresult($result);

    $forum_num = count($forum_list);
    
    $response = new xmlrpcval(array(
        'total_forums_num' => new xmlrpcval($forum_num, 'int'),
        'forums'    => new xmlrpcval($forum_list, 'array'),
    ), 'struct');

    return new xmlrpcresp($response);
}
