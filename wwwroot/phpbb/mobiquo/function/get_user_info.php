<?php
/**
*
* @copyright (c) 2009, 2010, 2011 Quoord Systems Limited
* @license http://opensource.org/licenses/gpl-2.0.php GNU Public License (GPLv2)
*
*/

defined('IN_MOBIQUO') or exit;

function get_user_info_func($xmlrpc_params)
{
    global $db, $user, $auth, $template, $config, $phpbb_root_path, $phpEx;
    
    $user->setup(array('memberlist', 'groups'));
    
    if (!$auth->acl_gets('u_viewprofile', 'a_user', 'a_useradd', 'a_userdel'))
    {
        if ($user->data['user_id'] != ANONYMOUS)
        {
            trigger_error('NO_VIEW_USERS');
        }
        
        trigger_error('LOGIN_EXPLAIN_VIEWPROFILE');
    }
    
    $params = php_xmlrpc_decode($xmlrpc_params);
    $username = $params[0];
    if (isset($params[1]) && !empty($params[1])){
        $user_id = $params[1];
    }
    elseif (isset($params[0]) && !empty($params[0]))
    {
        $username = $params[0];
        $user_id = get_user_id_by_name($username);
    }
    else
    {
        $user_id = $user->data['user_id'];
    }
    
    $user_id = intval($user_id);
    
    // Display a profile
    if (!$user_id) trigger_error('NO_USER');
    
    // Get user...
    $sql = 'SELECT *
        FROM ' . USERS_TABLE . "
        WHERE user_id = '$user_id'";
    $result = $db->sql_query($sql);
    $member = $db->sql_fetchrow($result);
    $db->sql_freeresult($result);
    
    if (!$member) trigger_error('NO_USER');
    
    // a_user admins and founder are able to view inactive users and bots to be able to manage them more easily
    // Normal users are able to see at least users having only changed their profile settings but not yet reactivated.
    if (!$auth->acl_get('a_user') && $user->data['user_type'] != USER_FOUNDER)
    {
        if ($member['user_type'] == USER_IGNORE)
        {
            trigger_error('NO_USER');
        }
        else if ($member['user_type'] == USER_INACTIVE && $member['user_inactive_reason'] != INACTIVE_PROFILE)
        {
            trigger_error('NO_USER');
        }
    }
    
    $user_id = (int) $member['user_id'];
    
    // Do the SQL thang
    $sql = 'SELECT g.group_id, g.group_name, g.group_type
        FROM ' . GROUPS_TABLE . ' g, ' . USER_GROUP_TABLE . " ug
        WHERE ug.user_id = $user_id
            AND g.group_id = ug.group_id" . ((!$auth->acl_gets('a_group', 'a_groupadd', 'a_groupdel')) ? ' AND g.group_type <> ' . GROUP_HIDDEN : '') . '
            AND ug.user_pending = 0
        ORDER BY g.group_type, g.group_name';
    $result = $db->sql_query($sql);
    
    $group_options = '';
    while ($row = $db->sql_fetchrow($result))
    {
        if ($row['group_type'] == GROUP_SPECIAL)
        {
            // Lookup group name in language dictionary
            if (isset($user->lang['G_' . $row['group_name']]))
            {
                $row['group_name'] = $user->lang['G_' . $row['group_name']];
            }
        }
        else if (!$auth_hidden_groups && $row['group_type'] == GROUP_HIDDEN && !isset($user_groups[$row['group_id']]))
        {
            // Skip over hidden groups the user cannot see
            continue;
        }
        
        $group_options .= ($row['group_id'] == $member['group_id']) ? $row['group_name']." *\n" : $row['group_name']."\n";
    }
    $group_options = trim($group_options);
    $db->sql_freeresult($result);
    
    // What colour is the zebra
    $sql = 'SELECT friend, foe
        FROM ' . ZEBRA_TABLE . "
        WHERE zebra_id = $user_id
            AND user_id = {$user->data['user_id']}";
    
    $result = $db->sql_query($sql);
    $row = $db->sql_fetchrow($result);
    $foe = ($row['foe']) ? true : false;
    $friend = ($row['friend']) ? true : false;
    $db->sql_freeresult($result);
    
    if ($config['load_onlinetrack'])
    {
        $sql = 'SELECT MAX(session_time) AS session_time, MIN(session_viewonline) AS session_viewonline, session_page, session_forum_id
            FROM ' . SESSIONS_TABLE . "
            WHERE session_user_id = $user_id
            GROUP BY session_page, session_forum_id
            ORDER BY session_time DESC";

        $result = $db->sql_query_limit($sql, 1);
        $row = $db->sql_fetchrow($result);
        $db->sql_freeresult($result);
    
        $member['session_time'] = (isset($row['session_time'])) ? $row['session_time'] : 0;
        $member['session_viewonline'] = (isset($row['session_viewonline'])) ? $row['session_viewonline'] :    0;
        $member['session_page'] = (isset($row['session_page'])) ? $row['session_page'] : 0;
        $member['session_forum_id'] = (isset($row['session_forum_id'])) ? $row['session_forum_id'] : 0;
        unset($row);
    }
    
    if ($config['load_user_activity'])
    {
        display_user_activity($member);
    }
    
    // Do the relevant calculations
    $memberdays = max(1, round((time() - $member['user_regdate']) / 86400));
    $posts_per_day = $member['user_posts'] / $memberdays;
    $percentage = ($config['num_posts']) ? min(100, ($member['user_posts'] / $config['num_posts']) * 100) : 0;
    
    
    if ($member['user_sig'])
    {
        $member['user_sig'] = censor_text($member['user_sig']);
    
        if ($member['user_sig_bbcode_bitfield'])
        {
            include_once($phpbb_root_path . 'includes/bbcode.' . $phpEx);
            $bbcode = new bbcode();
            $bbcode->bbcode_second_pass($member['user_sig'], $member['user_sig_bbcode_uid'], $member['user_sig_bbcode_bitfield']);
        }
    
        $member['user_sig'] = bbcode_nl2br($member['user_sig']);
        $member['user_sig'] = smiley_text($member['user_sig']);
    }
    
    $poster_avatar = get_user_avatar($member['user_avatar'], $member['user_avatar_type'], $member['user_avatar_width'], $member['user_avatar_height']);
    
    // We need to check if the modules 'zebra' ('friends' & 'foes' mode),  'notes' ('user_notes' mode) and  'warn' ('warn_user' mode) are accessible to decide if we can display appropriate links
    $zebra_enabled = $friends_enabled = $foes_enabled = $user_notes_enabled = $warn_user_enabled = false;
    
    // Only check if the user is logged in
    if ($user->data['is_registered'])
    {
        if (!class_exists('p_master'))
        {
            include($phpbb_root_path . 'includes/functions_module.' . $phpEx);
        }
        $module = new p_master();
    
        $module->list_modules('ucp');
        $module->list_modules('mcp');
    
        $user_notes_enabled = ($module->loaded('notes', 'user_notes')) ? true : false;
        $warn_user_enabled = ($module->loaded('warn', 'warn_user')) ? true : false;
        $zebra_enabled = ($module->loaded('zebra')) ? true : false;
        $friends_enabled = ($module->loaded('zebra', 'friends')) ? true : false;
        $foes_enabled = ($module->loaded('zebra', 'foes')) ? true : false;
    
        unset($module);
    }
    
    $template->assign_vars(show_profile($member, $user_notes_enabled, $warn_user_enabled));
    
    // Custom Profile Fields
    $profile_fields = array();
    if ($config['load_cpf_viewprofile'])
    {
        include_once($phpbb_root_path . 'includes/functions_profile_fields.' . $phpEx);
        $cp = new custom_profile();
        $profile_fields = $cp->generate_profile_fields_template('grab', $user_id);
        $profile_fields = (isset($profile_fields[$user_id])) ? $cp->generate_profile_fields_template('show', false, $profile_fields[$user_id]) : array();
    }
    
    // If the user has m_approve permission or a_user permission, then list then display unapproved posts
    if ($auth->acl_getf_global('m_approve') || $auth->acl_get('a_user'))
    {
        $sql = 'SELECT COUNT(post_id) as posts_in_queue
            FROM ' . POSTS_TABLE . '
            WHERE poster_id = ' . $user_id . '
                AND post_approved = 0';
        $result = $db->sql_query($sql);
        $member['posts_in_queue'] = (int) $db->sql_fetchfield('posts_in_queue');
        $db->sql_freeresult($result);
    }
    else
    {
        $member['posts_in_queue'] = 0;
    }
    
    $template->assign_vars(array(
        //'L_POSTS_IN_QUEUE'    => $user->lang('NUM_POSTS_IN_QUEUE', $member['posts_in_queue']),
    
        //'POSTS_DAY'            => sprintf($user->lang['POST_DAY'], $posts_per_day),
        //'POSTS_PCT'            => sprintf($user->lang['POST_PCT'], $percentage),
    
        'OCCUPATION'    => (!empty($member['user_occ'])) ? censor_text($member['user_occ']) : '',
        'INTERESTS'        => (!empty($member['user_interests'])) ? censor_text($member['user_interests']) : '',
        'SIGNATURE'        => $member['user_sig'],
        'POSTS_IN_QUEUE'=> $member['posts_in_queue'],
    
        'AVATAR_IMG'    => $poster_avatar,
        'PM_IMG'        => $user->img('icon_contact_pm', $user->lang['SEND_PRIVATE_MESSAGE']),
        'EMAIL_IMG'        => $user->img('icon_contact_email', $user->lang['EMAIL']),
        'WWW_IMG'        => $user->img('icon_contact_www', $user->lang['WWW']),
        'ICQ_IMG'        => $user->img('icon_contact_icq', $user->lang['ICQ']),
        'AIM_IMG'        => $user->img('icon_contact_aim', $user->lang['AIM']),
        'MSN_IMG'        => $user->img('icon_contact_msnm', $user->lang['MSNM']),
        'YIM_IMG'        => $user->img('icon_contact_yahoo', $user->lang['YIM']),
        'JABBER_IMG'    => $user->img('icon_contact_jabber', $user->lang['JABBER']),
        'SEARCH_IMG'    => $user->img('icon_user_search', $user->lang['SEARCH']),
    
        'S_PROFILE_ACTION'    => append_sid("{$phpbb_root_path}memberlist.$phpEx", 'mode=group'),
        'S_GROUP_OPTIONS'    => $group_options,
        'S_CUSTOM_FIELDS'    => (isset($profile_fields['row']) && sizeof($profile_fields['row'])) ? true : false,
    
        'U_USER_ADMIN'            => ($auth->acl_get('a_user')) ? append_sid("{$phpbb_root_path}adm/index.$phpEx", 'i=users&amp;mode=overview&amp;u=' . $user_id, true, $user->session_id) : '',
        'U_USER_BAN'            => ($auth->acl_get('m_ban') && $user_id != $user->data['user_id']) ? append_sid("{$phpbb_root_path}mcp.$phpEx", 'i=ban&amp;mode=user&amp;u=' . $user_id, true, $user->session_id) : '',
        'U_MCP_QUEUE'            => ($auth->acl_getf_global('m_approve')) ? append_sid("{$phpbb_root_path}mcp.$phpEx", 'i=queue', true, $user->session_id) : '',
    
        'U_SWITCH_PERMISSIONS'    => ($auth->acl_get('a_switchperm') && $user->data['user_id'] != $user_id) ? append_sid("{$phpbb_root_path}ucp.$phpEx", "mode=switch_perm&amp;u={$user_id}&amp;hash=" . generate_link_hash('switchperm')) : '',
    
        'S_USER_NOTES'        => ($user_notes_enabled) ? true : false,
        'S_WARN_USER'        => ($warn_user_enabled) ? true : false,
        'S_ZEBRA'            => ($user->data['user_id'] != $user_id && $user->data['is_registered'] && $zebra_enabled) ? true : false,
        'U_ADD_FRIEND'        => (!$friend && !$foe && $friends_enabled) ? append_sid("{$phpbb_root_path}ucp.$phpEx", 'i=zebra&amp;add=' . urlencode(htmlspecialchars_decode($member['username']))) : '',
        'U_ADD_FOE'            => (!$friend && !$foe && $foes_enabled) ? append_sid("{$phpbb_root_path}ucp.$phpEx", 'i=zebra&amp;mode=foes&amp;add=' . urlencode(htmlspecialchars_decode($member['username']))) : '',
        'U_REMOVE_FRIEND'    => ($friend && $friends_enabled) ? append_sid("{$phpbb_root_path}ucp.$phpEx", 'i=zebra&amp;remove=1&amp;usernames[]=' . $user_id) : '',
        'U_REMOVE_FOE'        => ($foe && $foes_enabled) ? append_sid("{$phpbb_root_path}ucp.$phpEx", 'i=zebra&amp;remove=1&amp;mode=foes&amp;usernames[]=' . $user_id) : '',
    ));
    
    if (!empty($profile_fields['row']))
    {
        $template->assign_vars($profile_fields['row']);
    }
    
    if (!empty($profile_fields['blockrow']))
    {
        foreach ($profile_fields['blockrow'] as $field_data)
        {
            $template->assign_block_vars('custom_fields', $field_data);
        }
    }
    
    // Inactive reason/account?
    if ($member['user_type'] == USER_INACTIVE)
    {
        $user->add_lang('acp/common');
    
        $inactive_reason = $user->lang['INACTIVE_REASON_UNKNOWN'];
    
        switch ($member['user_inactive_reason'])
        {
            case INACTIVE_REGISTER:
                $inactive_reason = $user->lang['INACTIVE_REASON_REGISTER'];
            break;
    
            case INACTIVE_PROFILE:
                $inactive_reason = $user->lang['INACTIVE_REASON_PROFILE'];
            break;
    
            case INACTIVE_MANUAL:
                $inactive_reason = $user->lang['INACTIVE_REASON_MANUAL'];
            break;
    
            case INACTIVE_REMIND:
                $inactive_reason = $user->lang['INACTIVE_REASON_REMIND'];
            break;
        }
    
        $template->assign_vars(array(
            'S_USER_INACTIVE'        => true,
            'USER_INACTIVE_REASON'    => $inactive_reason)
        );
    }
    
    $custom_fields_list = get_custom_fields();
    $user_avatar_url = get_user_avatar_url($member['user_avatar'], $member['user_avatar_type']);
    
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
    
    
    // get user current activity
    preg_match('#^([a-z0-9/_-]+)#i', $member['session_page'], $on_page);
    if (!sizeof($on_page))
    {
        $on_page[1] = '';
    }

    switch ($on_page[1])
    {
        case 'index':
            $location = $user->lang['INDEX'];
        break;

        case 'adm/index':
            $location = $user->lang['ACP'];
        break;

        case 'posting':
        case 'viewforum':
        case 'viewtopic':
            $forum_id = $member['session_forum_id'];
            
            if ($forum_id && $auth->acl_get('f_list', $forum_id))
            {
                $location = '';

                if ($forum_data[$forum_id]['forum_type'] == FORUM_LINK)
                {
                    $location = sprintf($user->lang['READING_LINK'], $forum_data[$forum_id]['forum_name']);
                    break;
                }

                switch ($on_page[1])
                {
                    case 'posting':
                        preg_match('#mode=([a-z]+)#', $member['session_page'], $on_page);
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
            }
        break;

        case 'search':
            $location = $user->lang['SEARCHING_FORUMS'];
        break;

        case 'faq':
            $location = $user->lang['VIEWING_FAQ'];
        break;

        case 'viewonline':
            $location = $user->lang['VIEWING_ONLINE'];
        break;

        case 'memberlist':
            $location = (strpos($member['session_page'], 'mode=viewprofile') !== false) ? $user->lang['VIEWING_MEMBER_PROFILE'] : $user->lang['VIEWING_MEMBERS'];
        break;

        case 'mcp':
            $location = $user->lang['VIEWING_MCP'];
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
                if (strpos($member['session_page'], $param) !== false)
                {
                    $location = $user->lang[$lang];
                    break;
                }
            }

        break;

        case 'download/file':
            $location = $user->lang['DOWNLOADING_FILE'];
        break;

        case 'report':
            $location = $user->lang['REPORTING_POST'];
        break;
        
        case 'mobiquo/mobiquo':
            $location = 'On Tapatalk';
        break;

        default:
            $location = $user->lang['INDEX'];
        break;
    }
    
    $xmlrpc_user_info = new xmlrpcval(array(
        'user_id'            => new xmlrpcval($member['user_id']),
        'username'           => new xmlrpcval($member['username'], 'base64'),
        'post_count'         => new xmlrpcval($member['user_posts'], 'int'),
        'reg_time'           => new xmlrpcval(mobiquo_iso8601_encode($member['user_regdate']), 'dateTime.iso8601'),
        'last_activity_time' => new xmlrpcval(mobiquo_iso8601_encode($template->_rootref['VISITED']), 'dateTime.iso8601'),
        'is_online'          => new xmlrpcval($template->_rootref['S_ONLINE'], 'boolean'),
        'accept_pm'          => new xmlrpcval($template->_rootref['U_PM'] ? true : false, 'boolean'),
        'display_text'       => new xmlrpcval('', 'base64'),
        'icon_url'           => new xmlrpcval($user_avatar_url),
        'current_activity'   => new xmlrpcval($location, 'base64'),
        'custom_fields_list' => new xmlrpcval($custom_fields_list, 'array'),
        
        //'can_ban'            => new xmlrpcval($auth->acl_get('m_ban') && $user_id != $user->data['user_id'] ? true : false, 'boolean'),
    ), 'struct');

    return new xmlrpcresp($xmlrpc_user_info);
} // End of get_user_info_func


/**
* Prepare profile data
*/
function show_profile($data, $user_notes_enabled = false, $warn_user_enabled = false)
{
    global $config, $auth, $template, $user, $phpEx, $phpbb_root_path;

    $username = $data['username'];
    $user_id = $data['user_id'];

    $rank_title = $rank_img = $rank_img_src = '';
    get_user_rank($data['user_rank'], (($user_id == ANONYMOUS) ? false : $data['user_posts']), $rank_title, $rank_img, $rank_img_src);

    if (!empty($data['user_allow_viewemail']) || $auth->acl_get('a_user'))
    {
        $email = ($config['board_email_form'] && $config['email_enable']) ? '' : (($config['board_hide_emails'] && !$auth->acl_get('a_user')) ? '' : $data['user_email']);
    }
    else
    {
        $email = '';
    }

    if ($config['load_onlinetrack'])
    {
        $update_time = $config['load_online_time'] * 60;
        $online = (time() - $update_time < $data['session_time'] && ((isset($data['session_viewonline']) && $data['session_viewonline']) || $auth->acl_get('u_viewonline'))) ? true : false;
    }
    else
    {
        $online = false;
    }

    if ($data['user_allow_viewonline'] || $auth->acl_get('u_viewonline'))
    {
        $last_visit = (!empty($data['session_time'])) ? $data['session_time'] : $data['user_lastvisit'];
    }
    else
    {
        $last_visit = '';
    }

    $age = '';

    if ($config['allow_birthdays'] && $data['user_birthday'])
    {
        list($bday_day, $bday_month, $bday_year) = array_map('intval', explode('-', $data['user_birthday']));

        if ($bday_year)
        {
            $now = getdate(time() + $user->timezone + $user->dst - date('Z'));

            $diff = $now['mon'] - $bday_month;
            if ($diff == 0)
            {
                $diff = ($now['mday'] - $bday_day < 0) ? 1 : 0;
            }
            else
            {
                $diff = ($diff < 0) ? 1 : 0;
            }

            $age = (int) ($now['year'] - $bday_year - $diff);
        }
    }

    // Dump it out to the template
    return array(
        'AGE'            => $age,
        'RANK_TITLE'    => $rank_title,
        'JOINED'        => $user->format_date($data['user_regdate']),
        'VISITED'        => $last_visit,
        'POSTS'            => ($data['user_posts']) ? $data['user_posts'] : 0,
        'WARNINGS'        => isset($data['user_warnings']) ? $data['user_warnings'] : 0,

        'USERNAME_FULL'        => get_username_string('full', $user_id, $username, $data['user_colour']),
        'USERNAME'            => get_username_string('username', $user_id, $username, $data['user_colour']),
        'USER_COLOR'        => get_username_string('colour', $user_id, $username, $data['user_colour']),
        'U_VIEW_PROFILE'    => get_username_string('profile', $user_id, $username, $data['user_colour']),

        'A_USERNAME'        => addslashes(get_username_string('username', $user_id, $username, $data['user_colour'])),

        'AVATAR_IMG'        => get_user_avatar($data['user_avatar'], $data['user_avatar_type'], $data['user_avatar_width'], $data['user_avatar_height']),
        'ONLINE_IMG'        => (!$config['load_onlinetrack']) ? '' : (($online) ? $user->img('icon_user_online', 'ONLINE') : $user->img('icon_user_offline', 'OFFLINE')),
        'S_ONLINE'            => ($config['load_onlinetrack'] && $online) ? true : false,
        'RANK_IMG'            => $rank_img,
        'RANK_IMG_SRC'        => $rank_img_src,
        'ICQ_STATUS_IMG'    => (!empty($data['user_icq'])) ? '<img src="http://web.icq.com/whitepages/online?icq=' . $data['user_icq'] . '&amp;img=5" width="18" height="18" />' : '',
        'S_JABBER_ENABLED'    => ($config['jab_enable']) ? true : false,

        'S_WARNINGS'    => ($auth->acl_getf_global('m_') || $auth->acl_get('m_warn')) ? true : false,

        'U_SEARCH_USER'    => ($auth->acl_get('u_search')) ? append_sid("{$phpbb_root_path}search.$phpEx", "author_id=$user_id&amp;sr=posts") : '',
        'U_NOTES'        => ($user_notes_enabled && $auth->acl_getf_global('m_')) ? append_sid("{$phpbb_root_path}mcp.$phpEx", 'i=notes&amp;mode=user_notes&amp;u=' . $user_id, true, $user->session_id) : '',
        'U_WARN'        => ($warn_user_enabled && $auth->acl_get('m_warn')) ? append_sid("{$phpbb_root_path}mcp.$phpEx", 'i=warn&amp;mode=warn_user&amp;u=' . $user_id, true, $user->session_id) : '',
        'U_PM'            => ($config['allow_privmsg'] && $auth->acl_get('u_sendpm') && ($data['user_allow_pm'] || $auth->acl_gets('a_', 'm_') || $auth->acl_getf_global('m_'))) ? append_sid("{$phpbb_root_path}ucp.$phpEx", 'i=pm&amp;mode=compose&amp;u=' . $user_id) : '',
        'U_EMAIL'        => $email,
        'U_WWW'            => (!empty($data['user_website'])) ? $data['user_website'] : '',
        'U_SHORT_WWW'            => (!empty($data['user_website'])) ? ((strlen($data['user_website']) > 55) ? substr($data['user_website'], 0, 39) . ' ... ' . substr($data['user_website'], -10) : $data['user_website']) : '',
        'U_ICQ'            => ($data['user_icq']) ? 'http://www.icq.com/people/webmsg.php?to=' . urlencode($data['user_icq']) : '',
        'U_AIM'            => ($data['user_aim'] && $auth->acl_get('u_sendim')) ? append_sid("{$phpbb_root_path}memberlist.$phpEx", 'mode=contact&amp;action=aim&amp;u=' . $user_id) : '',
        'U_YIM'            => ($data['user_yim']) ? 'http://edit.yahoo.com/config/send_webmesg?.target=' . urlencode($data['user_yim']) . '&amp;.src=pg' : '',
        'U_MSN'            => ($data['user_msnm'] && $auth->acl_get('u_sendim')) ? append_sid("{$phpbb_root_path}memberlist.$phpEx", 'mode=contact&amp;action=msnm&amp;u=' . $user_id) : '',
        'U_JABBER'        => ($data['user_jabber'] && $auth->acl_get('u_sendim')) ? append_sid("{$phpbb_root_path}memberlist.$phpEx", 'mode=contact&amp;action=jabber&amp;u=' . $user_id) : '',
        'LOCATION'        => ($data['user_from']) ? $data['user_from'] : '',

        'USER_ICQ'            => $data['user_icq'],
        'USER_AIM'            => $data['user_aim'],
        'USER_YIM'            => $data['user_yim'],
        'USER_MSN'            => $data['user_msnm'],
        'USER_JABBER'        => $data['user_jabber'],
        'USER_JABBER_IMG'    => ($data['user_jabber']) ? $user->img('icon_contact_jabber', $data['user_jabber']) : '',

        'L_VIEWING_PROFILE'    => sprintf($user->lang['VIEWING_PROFILE'], $username),
    );
}

function get_custom_fields()
{
    global $user, $template;
    
    $custom_fields = array();
    
    if ($template->_rootref['RANK_TITLE']) {
        $custom_fields[] = new xmlrpcval(array(
            'name'  => new xmlrpcval($user->lang['RANK'], 'base64'),
            'value' => new xmlrpcval($template->_rootref['RANK_TITLE'], 'base64')
        ), 'struct');
    }
    
    if ($template->_rootref['S_USER_INACTIVE']) {
        $custom_fields[] = new xmlrpcval(array(
            'name'  => new xmlrpcval($user->lang['USER_IS_INACTIVE'], 'base64'),
            'value' => new xmlrpcval($template->_rootref['USER_INACTIVE_REASON'], 'base64')
        ), 'struct');
    }
    
    if ($template->_rootref['LOCATION']) {
        $custom_fields[] = new xmlrpcval(array(
            'name'  => new xmlrpcval($user->lang['LOCATION'], 'base64'),
            'value' => new xmlrpcval($template->_rootref['LOCATION'], 'base64')
        ), 'struct');
    }
    
    if ($template->_rootref['AGE']) {
        $custom_fields[] = new xmlrpcval(array(
            'name'  => new xmlrpcval($user->lang['AGE'], 'base64'),
            'value' => new xmlrpcval($template->_rootref['AGE'], 'base64')
        ), 'struct');
    }
    
    if ($template->_rootref['OCCUPATION']) {
        $custom_fields[] = new xmlrpcval(array(
            'name'  => new xmlrpcval($user->lang['OCCUPATION'], 'base64'),
            'value' => new xmlrpcval($template->_rootref['OCCUPATION'], 'base64')
        ), 'struct');
    }
    
    if ($template->_rootref['INTERESTS']) {
        $custom_fields[] = new xmlrpcval(array(
            'name'  => new xmlrpcval($user->lang['INTERESTS'], 'base64'),
            'value' => new xmlrpcval($template->_rootref['INTERESTS'], 'base64')
        ), 'struct');
    }
    
    if ($template->_rootref['S_GROUP_OPTIONS']) {
        $custom_fields[] = new xmlrpcval(array(
            'name'  => new xmlrpcval($user->lang['USERGROUPS'], 'base64'),
            'value' => new xmlrpcval($template->_rootref['S_GROUP_OPTIONS'], 'base64')
        ), 'struct');
    }
    
    if ($template->_rootref['U_EMAIL']) {
        $custom_fields[] = new xmlrpcval(array(
            'name'  => new xmlrpcval($user->lang['EMAIL_ADDRESS'], 'base64'),
            'value' => new xmlrpcval($template->_rootref['U_EMAIL'], 'base64')
        ), 'struct');
    }
    
    if ($template->_rootref['U_WWW']) {
        $custom_fields[] = new xmlrpcval(array(
            'name'  => new xmlrpcval($user->lang['WEBSITE'], 'base64'),
            'value' => new xmlrpcval($template->_rootref['U_WWW'], 'base64')
        ), 'struct');
    }
    
    if ($template->_rootref['USER_MSN']) {
        $custom_fields[] = new xmlrpcval(array(
            'name'  => new xmlrpcval($user->lang['MSNM'], 'base64'),
            'value' => new xmlrpcval($template->_rootref['USER_MSN'], 'base64')
        ), 'struct');
    }
    
    if ($template->_rootref['USER_YIM']) {
        $custom_fields[] = new xmlrpcval(array(
            'name'  => new xmlrpcval($user->lang['YIM'], 'base64'),
            'value' => new xmlrpcval($template->_rootref['USER_YIM'], 'base64')
        ), 'struct');
    }
    
    if ($template->_rootref['USER_AIM']) {
        $custom_fields[] = new xmlrpcval(array(
            'name'  => new xmlrpcval($user->lang['AIM'], 'base64'),
            'value' => new xmlrpcval($template->_rootref['USER_AIM'], 'base64')
        ), 'struct');
    }
    
    if ($template->_rootref['USER_ICQ']) {
        $custom_fields[] = new xmlrpcval(array(
            'name'  => new xmlrpcval($user->lang['ICQ'], 'base64'),
            'value' => new xmlrpcval($template->_rootref['USER_ICQ'], 'base64')
        ), 'struct');
    }
    
    if ($template->_rootref['USER_JABBER']) {
        $custom_fields[] = new xmlrpcval(array(
            'name'  => new xmlrpcval($user->lang['JABBER'], 'base64'),
            'value' => new xmlrpcval($template->_rootref['USER_JABBER'], 'base64')
        ), 'struct');
    }
    
    if ($template->_rootref['S_WARNINGS']) {
        $custom_fields[] = new xmlrpcval(array(
            'name'  => new xmlrpcval($user->lang['WARNINGS'], 'base64'),
            'value' => new xmlrpcval($template->_rootref['WARNINGS'], 'base64')
        ), 'struct');
    }
    
    if ($template->_rootref['S_SHOW_ACTIVITY'] && $template->_rootref['POSTS']) {
        if ($template->_rootref['ACTIVE_FORUM']) {
            $custom_fields[] = new xmlrpcval(array(
                'name'  => new xmlrpcval($user->lang['ACTIVE_IN_FORUM'], 'base64'),
                'value' => new xmlrpcval($template->_rootref['ACTIVE_FORUM']."\n(".$template->_rootref['ACTIVE_FORUM_POSTS'].' / '.$template->_rootref['ACTIVE_FORUM_PCT'].')', 'base64')
            ), 'struct');
        }
        
        if ($template->_rootref['ACTIVE_TOPIC']) {
            $custom_fields[] = new xmlrpcval(array(
                'name'  => new xmlrpcval($user->lang['ACTIVE_IN_TOPIC'], 'base64'),
                'value' => new xmlrpcval($template->_rootref['ACTIVE_TOPIC']."\n(".$template->_rootref['ACTIVE_TOPIC_POSTS'].' / '.$template->_rootref['ACTIVE_TOPIC_PCT'].')', 'base64')
            ), 'struct');
        }
    }
    
    return $custom_fields;
    
}