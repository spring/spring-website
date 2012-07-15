<?php
/**
*
* @copyright (c) 2009, 2010, 2011 Quoord Systems Limited
* @license http://opensource.org/licenses/gpl-2.0.php GNU Public License (GPLv2)
*
*/

defined('IN_MOBIQUO') or exit;

function get_box_func($xmlrpc_params)
{
    global $db, $auth, $user, $cache, $config, $phpbb_home, $phpbb_root_path, $phpEx;

    $params = php_xmlrpc_decode($xmlrpc_params);
    
    $user->setup('ucp');
    if (!$user->data['is_registered']) trigger_error('LOGIN_EXPLAIN_UCP');
    if (!$config['allow_privmsg']) trigger_error('Module not accessible');
    if (!isset($params[0])) trigger_error('UNKNOWN_FOLDER');
    
    // get folder id from parameters
    $folder_id = intval($params[0]);
    if (PRIVMSGS_INBOX !== $folder_id)
        $folder_id = PRIVMSGS_SENTBOX;
    
    list($start, $limit, $page) = process_page($params[1], $params[2]);
    
    // Grab icons
    //$icons = $cache->obtain_icons();
    $user_id = $user->data['user_id'];

    include_once($phpbb_root_path . 'includes/functions_privmsgs.' . $phpEx);
    $folder = get_folder($user_id, $folder_id);

    include($phpbb_root_path . 'includes/ucp/ucp_pm_viewfolder.' . $phpEx);
    $folder_info = get_pm_from($folder_id, $folder, $user_id);

    $address_list = array();

    // Build Recipient List if in outbox/sentbox - max two additional queries
    if ($folder_id == PRIVMSGS_OUTBOX || $folder_id == PRIVMSGS_SENTBOX)
    {
        $recipient_list = $address = array();

        foreach ($folder_info['rowset'] as $message_id => $row)
        {
            $address[$message_id] = rebuild_header(array('to' => $row['to_address'], 'bcc' => $row['bcc_address']));
            $_save = array('u', 'g');
            foreach ($_save as $save)
            {
                if (isset($address[$message_id][$save]) && sizeof($address[$message_id][$save]))
                {
                    foreach (array_keys($address[$message_id][$save]) as $ug_id)
                    {
                        $recipient_list[$save][$ug_id] = array('name' => $user->lang['NA'], 'colour' => '');
                    }
                }
            }
        }

        $_types = array('u', 'g');
        foreach ($_types as $ug_type)
        {
            if (!empty($recipient_list[$ug_type]))
            {
                if ($ug_type == 'u')
                {
                    $sql = 'SELECT user_id as id, username as name, user_colour as colour
                        FROM ' . USERS_TABLE . '
                        WHERE ';
                }
                else
                {
                    $sql = 'SELECT group_id as id, group_name as name, group_colour as colour, group_type
                        FROM ' . GROUPS_TABLE . '
                        WHERE ';
                }
                $sql .= $db->sql_in_set(($ug_type == 'u') ? 'user_id' : 'group_id', array_map('intval', array_keys($recipient_list[$ug_type])));

                $result = $db->sql_query($sql);

                while ($row = $db->sql_fetchrow($result))
                {
                    if ($ug_type == 'g')
                    {
                        $row['name'] = ($row['group_type'] == GROUP_SPECIAL) ? $user->lang['G_' . $row['name']] : $row['name'];
                    }

                    $recipient_list[$ug_type][$row['id']] = array('id' => $row['id'], 'name' => $row['name'], 'colour' => $row['colour']);
                }
                $db->sql_freeresult($result);
            }
        }

        foreach ($address as $message_id => $adr_ary)
        {
            foreach ($adr_ary as $type => $id_ary)
            {
                foreach ($id_ary as $ug_id => $_id)
                {
                    $address_list[$message_id][] = $recipient_list[$type][$ug_id];
                }
            }
        }
        unset($recipient_list, $address);
    }
    
    // get unread count in inbox only
    if (PRIVMSGS_INBOX === $folder_id)
    {
        $sql = 'SELECT COUNT(msg_id) as num_messages
                FROM ' . PRIVMSGS_TO_TABLE . '
                WHERE pm_unread = 1
                    AND folder_id = ' . PRIVMSGS_INBOX . '
                    AND user_id = ' . $user->data['user_id'];
        $result = $db->sql_query($sql);
        $unread_num = (int) $db->sql_fetchfield('num_messages');
        $db->sql_freeresult($result);
    } else {
        $unread_num = 0;
    }
    
    $sql = 'SELECT COUNT(msg_id) as num_messages
            FROM ' . PRIVMSGS_TO_TABLE . '
            WHERE folder_id = ' . $folder_id . '
                AND user_id = ' . $user->data['user_id'];
    $result = $db->sql_query($sql);
    $total_num = (int) $db->sql_fetchfield('num_messages');
    $db->sql_freeresult($result);
    
    $sql = 'SELECT t.*, p.*, u.username, u.user_avatar, u.user_avatar_type, u.user_id
            FROM ' . PRIVMSGS_TO_TABLE . ' t, ' . PRIVMSGS_TABLE . ' p, ' . USERS_TABLE . " u
            WHERE t.user_id = $user_id
            AND p.author_id = u.user_id
            AND t.folder_id = $folder_id
            AND t.msg_id = p.msg_id
            ORDER BY p.message_time DESC";
    $result = $db->sql_query_limit($sql, $limit, $start);
    
    $total_message_count = $total_unread_count = 0;
    $online_cache = array();
    while ($row = $db->sql_fetchrow($result))
    {
        $msg_state = 2; // message read
        if ($row['pm_unread'])
        {
            $msg_state = 1;
        }
        else if ($row['pm_replied'])
        {
            $msg_state = 3;
        }
        else if ($row['pm_forwarded'])
        {
            $msg_state = 4;
        }

        if ($folder_id == PRIVMSGS_OUTBOX || $folder_id == PRIVMSGS_SENTBOX)
        {
            $msg_to_list = $address_list[$row['msg_id']];
        }
        else
        {
            $msg_to_list = array(array('id' => $user->data['user_id'], 'name' => $user->data['username']));
        }

        $msg_to = array();
        foreach ($msg_to_list as $address)
        {
            $msg_to[] = new xmlrpcval(array(
                'user_id'  => new xmlrpcval($address['id'], 'string'),
                'username' => new xmlrpcval(basic_clean($address['name']), 'base64'),
            ), 'struct');
        }

        $sent_date  = mobiquo_iso8601_encode($row['message_time']);
        //$icon_url   = (!empty($icons[$row['icon_id']])) ? $phpbb_home . $config['icons_path'] . '/' . $icons[$row['icon_id']]['img'] : '';
        $icon_url   = ($user->optionget('viewavatars')) ? get_user_avatar_url($row['user_avatar'], $row['user_avatar_type']) : '';
        $msg_subject = html_entity_decode(strip_tags(censor_text($row['message_subject'])));

        $short_content = censor_text($row['message_text']);
        $short_content = preg_replace('/\[url.*?\].*?\[\/url.*?\]/', '[url]', $short_content);
        $short_content = preg_replace('/\[img.*?\].*?\[\/img.*?\]/', '[img]', $short_content);
        $short_content = preg_replace('/[\n\r\t]+/', ' ', $short_content);
        strip_bbcode($short_content);
        $short_content = html_entity_decode($short_content);
        $short_content = substr($short_content, 0, 200);
        
        if ($config['load_onlinetrack'] && !isset($online_cache[$row['user_id']])) {
            $sql = 'SELECT session_user_id, MAX(session_time) as online_time, MIN(session_viewonline) AS viewonline
                    FROM ' . SESSIONS_TABLE . '
                    WHERE session_user_id=' . $row['user_id'] . '
                    GROUP BY session_user_id';
            $online_result = $db->sql_query($sql);
            $online_info = $db->sql_fetchrow($online_result);
            $db->sql_freeresult($online_result);
            
            $update_time = $config['load_online_time'] * 60;
            $online_cache[$row['user_id']] = (time() - $update_time < $online_info['online_time'] && (($online_info['viewonline']) || $auth->acl_get('u_viewonline'))) ? true : false;
        }
        
        $is_online = isset($online_cache[$row['user_id']]) ? $online_cache[$row['user_id']] : false;
        
        $pm_list[] = new xmlrpcval(array(
            'msg_id'        => new xmlrpcval($row['msg_id']),
            'msg_state'     => new xmlrpcval($msg_state, 'int'),
            'sent_date'     => new xmlrpcval($sent_date, 'dateTime.iso8601'),
            'timestamp'     => new xmlrpcval($row['message_time'], 'string'),
            'msg_from'      => new xmlrpcval(basic_clean($row['username']), 'base64'),
            'msg_from_id'   => new xmlrpcval($row['user_id']),
            'icon_url'      => new xmlrpcval($icon_url),
            'msg_to'        => new xmlrpcval($msg_to, 'array'),
            'msg_subject'   => new xmlrpcval($msg_subject, 'base64'),
            'short_content' => new xmlrpcval($short_content, 'base64'),
            'is_online'     => new xmlrpcval($is_online, 'boolean'),
        ), 'struct');
    }
    $db->sql_freeresult($result);
    
    $result = new xmlrpcval(array(
        'total_message_count' => new xmlrpcval($total_num, 'int'),
        'total_unread_count'  => new xmlrpcval($unread_num, 'int'),
        'list'                => new xmlrpcval($pm_list, 'array')
    ), 'struct');

    return new xmlrpcresp($result);
}
