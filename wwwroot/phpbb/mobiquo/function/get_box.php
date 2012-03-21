<?php
/**
*
* @copyright (c) 2009 Quoord Systems Limited
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

defined('IN_MOBIQUO') or exit;

function get_box_func($xmlrpc_params)
{
    global $db, $user, $cache, $config, $phpbb_home, $phpbb_root_path, $phpEx;

    $params = php_xmlrpc_decode($xmlrpc_params);

    $start_num  = 0;            // default start index of pm
    $pm_limit   = 20;           // default request pm number is 20

    if (!isset($params[0]))     // folder id undefine
    {
        return get_error(1);
    }

    // get folder id from parameters
    $folder_id = $params[0];

    // get start index of topic from parameters
    if (isset($params[1]) && is_int($params[1]))
    {
        $start_num = $params[1];
    }

    // get end index of pm from parameters
    if (isset($params[2]) && is_int($params[2]))
    {
        $pm_limit = $params[2] - $start_num + 1;
    }

    // check if pm index is out of range
    if ($pm_limit < 1)
    {
        return get_error(5);
    }

    // return at most 20 pms
    if ($pm_limit > 20)
    {
        $pm_limit = 20;
    }

    // Only registered users can go beyond this point
    if (!$user->data['is_registered'])
    {
        return get_error(9);
    }

    // Is PM disabled?
    if (!$config['allow_privmsg'])
    {
        return get_error(21);
    }

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

                    $recipient_list[$ug_type][$row['id']] = array('name' => $row['name'], 'colour' => $row['colour']);
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
                    $address_list[$message_id][] = $recipient_list[$type][$ug_id]['name'];
                }
            }
        }
        unset($recipient_list, $address);
    }


    $sql = 'SELECT t.*, p.*, u.username, u.user_avatar, u.user_avatar_type
            FROM ' . PRIVMSGS_TO_TABLE . ' t, ' . PRIVMSGS_TABLE . ' p, ' . USERS_TABLE . " u
            WHERE t.user_id = $user_id
            AND p.author_id = u.user_id
            AND t.folder_id = $folder_id
            AND t.msg_id = p.msg_id
            ORDER BY p.message_time DESC";
    $result = $db->sql_query_limit($sql, $pm_limit, $start_num);

    $total_message_count = $total_unread_count = 0;
    while ($row = $db->sql_fetchrow($result))
    {
        $total_message_count++;
        $msg_state = 2; // message read
        if ($row['pm_unread'])
        {
            $total_unread_count++;
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
            $msg_to_list = array($user->data['username']);
        }

        $msg_to = array();
        foreach ($msg_to_list as $address)
        {
            $address = html_entity_decode($address);
            $msg_to[] = new xmlrpcval(array('username' => new xmlrpcval($address, 'base64')), 'struct');
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

        $pm_list[] = new xmlrpcval(array(
            'msg_id'        => new xmlrpcval($row['msg_id']),
            'msg_state'     => new xmlrpcval($msg_state, 'int'),
            'sent_date'     => new xmlrpcval($sent_date,'dateTime.iso8601'),
            'msg_from'      => new xmlrpcval(html_entity_decode($row['username']), 'base64'),
            'icon_url'      => new xmlrpcval($icon_url),
            'msg_to'        => new xmlrpcval($msg_to, 'array'),
            'msg_subject'   => new xmlrpcval($msg_subject, 'base64'),
            'short_content' => new xmlrpcval($short_content, 'base64')
        ), 'struct');
    }
    $db->sql_freeresult($result);



    $result = new xmlrpcval(array(
        'total_message_count' => new xmlrpcval($total_message_count, 'int'),
        'total_unread_count'  => new xmlrpcval($total_unread_count, 'int'),
        'list'                => new xmlrpcval($pm_list, 'array')
    ), 'struct');

    return new xmlrpcresp($result);
}
