<?php
/**
*
* @copyright (c) 2009, 2010, 2011 Quoord Systems Limited
* @license http://opensource.org/licenses/gpl-2.0.php GNU Public License (GPLv2)
*
*/

defined('IN_MOBIQUO') or exit;

function get_online_users_func() 
{    
    global $db, $auth, $config, $user;
    
    $user->setup('memberlist');

    // Get and set some variables
    $mode        = '';
    $session_id    = '';
    $start        = 0;
    $sort_key    = 'b';
    $sort_dir    = 'd';
    $show_guests= 0;
    
    if (!$user->data['is_registered']) 
        trigger_error('LOGIN_EXPLAIN_VIEWONLINE');
    if (!$auth->acl_gets('u_viewprofile', 'a_user', 'a_useradd', 'a_userdel'))
        trigger_error('NO_VIEW_USERS');
    
    $sort_key_sql = array('a' => 'u.username_clean', 'b' => 's.session_time', 'c' => 's.session_page');
    
    $order_by = $sort_key_sql[$sort_key] . ' ' . (($sort_dir == 'a') ? 'ASC' : 'DESC');
    
    // Forum info
    $sql = 'SELECT forum_id, forum_name, parent_id, forum_type, left_id, right_id
        FROM ' . FORUMS_TABLE . '
        ORDER BY left_id ASC';
    $result = $db->sql_query($sql, 600);
    
    $forum_data = array();
    while ($row = $db->sql_fetchrow($result))
    {
        $forum_data[$row['forum_id']] = $row;
    }
    $db->sql_freeresult($result);
    
    $guest_counter = 0;
    
    // Get number of online guests (if we do not display them)
    if (!$show_guests)
    {
        switch ($db->sql_layer)
        {
            case 'sqlite':
                $sql = 'SELECT COUNT(session_ip) as num_guests
                    FROM (
                        SELECT DISTINCT session_ip
                            FROM ' . SESSIONS_TABLE . '
                            WHERE session_user_id = ' . ANONYMOUS . '
                                AND session_time >= ' . (time() - ($config['load_online_time'] * 60)) .
                    ')';
            break;
    
            default:
                $sql = 'SELECT COUNT(DISTINCT session_ip) as num_guests
                    FROM ' . SESSIONS_TABLE . '
                    WHERE session_user_id = ' . ANONYMOUS . '
                        AND session_time >= ' . (time() - ($config['load_online_time'] * 60));
            break;
        }
        $result = $db->sql_query($sql);
        $guest_counter = (int) $db->sql_fetchfield('num_guests');
        $db->sql_freeresult($result);
    }
    
    // Get user list
    $sql = 'SELECT u.user_id, u.username, u.username_clean, u.user_type, u.user_avatar, u.user_avatar_type, s.session_id, s.session_time, s.session_page, s.session_ip, s.session_browser, s.session_viewonline, s.session_forum_id
        FROM ' . USERS_TABLE . ' u, ' . SESSIONS_TABLE . ' s
        WHERE u.user_id = s.session_user_id
            AND s.session_time >= ' . (time() - ($config['load_online_time'] * 60)) .
            ((!$show_guests) ? ' AND s.session_user_id <> ' . ANONYMOUS : '') . '
        ORDER BY ' . $order_by;
    $result = $db->sql_query($sql);
    
    $prev_id = $prev_ip = $user_list = array();
    $logged_visible_online = $logged_hidden_online = $counter = 0;
    
    while ($row = $db->sql_fetchrow($result))
    {
        if ($row['user_id'] != ANONYMOUS && !isset($prev_id[$row['user_id']]))
        {
            $view_online = $s_user_hidden = false;
   
            if (!$row['session_viewonline'])
            {
                $view_online = ($auth->acl_get('u_viewonline')) ? true : false;
                $logged_hidden_online++;
    
                $s_user_hidden = true;
            }
            else
            {
                $view_online = true;
                $logged_visible_online++;
            }
    
            $prev_id[$row['user_id']] = 1;
    
            if ($view_online)
            {
                $counter++;
            }
    
            if (!$view_online || $counter > $start + 100 || $counter <= $start)
            {
                continue;
            }
        }
        else if ($show_guests && $row['user_id'] == ANONYMOUS && !isset($prev_ip[$row['session_ip']]))
        {
            $prev_ip[$row['session_ip']] = 1;
            $guest_counter++;
            $counter++;
    
            if ($counter > $start + 100 || $counter <= $start)
            {
                continue;
            }
    
            $s_user_hidden = false;
        }
        else
        {
            continue;
        }
    
        preg_match('#^([a-z0-9/_-]+)#i', $row['session_page'], $on_page);
        if (!sizeof($on_page))
        {
            $on_page[1] = '';
        }
    
        switch ($on_page[1])
        {
            case 'index':
                $location = $user->lang['INDEX'];
                $location_url = append_sid("{$phpbb_root_path}index.$phpEx");
            break;
    
            case 'adm/index':
                $location = $user->lang['ACP'];
                $location_url = append_sid("{$phpbb_root_path}index.$phpEx");
            break;
    
            case 'posting':
            case 'viewforum':
            case 'viewtopic':
                $forum_id = $row['session_forum_id'];
    
                if ($forum_id && $auth->acl_get('f_list', $forum_id))
                {
                    $location = '';
                    $location_url = append_sid("{$phpbb_root_path}viewforum.$phpEx", 'f=' . $forum_id);
    
                    if ($forum_data[$forum_id]['forum_type'] == FORUM_LINK)
                    {
                        $location = sprintf($user->lang['READING_LINK'], $forum_data[$forum_id]['forum_name']);
                        break;
                    }
    
                    switch ($on_page[1])
                    {
                        case 'posting':
                            preg_match('#mode=([a-z]+)#', $row['session_page'], $on_page);
                            $posting_mode = (!empty($on_page[1])) ? $on_page[1] : '';
    
                            switch ($posting_mode)
                            {
                                case 'reply':
                                case 'quote':
                                    $location = sprintf($user->lang['REPLYING_MESSAGE'], $forum_data[$forum_id]['forum_name']);
                                break;
    
                                default:
                                    $location = sprintf($user->lang['POSTING_MESSAGE'], $forum_data[$forum_id]['forum_name']);
                                break;
                            }
                        break;
    
                        case 'viewtopic':
                            $location = sprintf($user->lang['READING_TOPIC'], $forum_data[$forum_id]['forum_name']);
                        break;
    
                        case 'viewforum':
                            $location = sprintf($user->lang['READING_FORUM'], $forum_data[$forum_id]['forum_name']);
                        break;
                    }
                }
                else
                {
                    $location = $user->lang['INDEX'];
                    $location_url = append_sid("{$phpbb_root_path}index.$phpEx");
                }
            break;
    
            case 'search':
                $location = $user->lang['SEARCHING_FORUMS'];
                $location_url = append_sid("{$phpbb_root_path}search.$phpEx");
            break;
    
            case 'faq':
                $location = $user->lang['VIEWING_FAQ'];
                $location_url = append_sid("{$phpbb_root_path}faq.$phpEx");
            break;
    
            case 'viewonline':
                $location = $user->lang['VIEWING_ONLINE'];
                $location_url = append_sid("{$phpbb_root_path}viewonline.$phpEx");
            break;
    
            case 'memberlist':
                $location = (strpos($row['session_page'], 'mode=viewprofile') !== false) ? $user->lang['VIEWING_MEMBER_PROFILE'] : $user->lang['VIEWING_MEMBERS'];
                $location_url = append_sid("{$phpbb_root_path}memberlist.$phpEx");
            break;
    
            case 'mcp':
                $location = $user->lang['VIEWING_MCP'];
                $location_url = append_sid("{$phpbb_root_path}index.$phpEx");
            break;
    
            case 'ucp':
                $location = $user->lang['VIEWING_UCP'];
    
                // Grab some common modules
                $url_params = array(
                    'mode=register'        => 'VIEWING_REGISTER',
                    'i=pm&mode=compose'    => 'POSTING_PRIVATE_MESSAGE',
                    'i=pm&'                => 'VIEWING_PRIVATE_MESSAGES',
                    'i=profile&'        => 'CHANGING_PROFILE',
                    'i=prefs&'            => 'CHANGING_PREFERENCES',
                );
    
                foreach ($url_params as $param => $lang)
                {
                    if (strpos($row['session_page'], $param) !== false)
                    {
                        $location = $user->lang[$lang];
                        break;
                    }
                }
    
                $location_url = append_sid("{$phpbb_root_path}index.$phpEx");
            break;
    
            case 'download/file':
                $location = $user->lang['DOWNLOADING_FILE'];
                $location_url = append_sid("{$phpbb_root_path}index.$phpEx");
            break;
    
            case 'report':
                $location = $user->lang['REPORTING_POST'];
                $location_url = append_sid("{$phpbb_root_path}index.$phpEx");
            break;
            
            case 'mobiquo/mobiquo':
                $location = 'via Tapatalk';
            break;
    
            default:
                $location = $user->lang['INDEX'];
                $location_url = append_sid("{$phpbb_root_path}index.$phpEx");
            break;
        }
        
        $user_avatar_url = get_user_avatar_url($row['user_avatar'], $row['user_avatar_type']);
        
        $user_list[] = new xmlrpcval(array(
            'user_id'       => new xmlrpcval($row['user_id'], 'string'),
            'username'      => new xmlrpcval($row['username'], 'base64'),
            'user_name'     => new xmlrpcval($row['username'], 'base64'),
            'icon_url'      => new xmlrpcval($user_avatar_url),
            'display_text'  => new xmlrpcval($location, 'base64')
        ), 'struct');
        
    }
    $db->sql_freeresult($result);
    unset($prev_id, $prev_ip);
    
    $online_users = array(
        'member_count' => new xmlrpcval($logged_visible_online + $logged_hidden_online, 'int'),
        'guest_count'  => new xmlrpcval($guest_counter, 'int'),
        'list'         => new xmlrpcval($user_list, 'array')
    );
    
    $response = new xmlrpcval($online_users, 'struct');

    return new xmlrpcresp($response);
}
