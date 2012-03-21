<?php
/**
*
* @copyright (c) 2009, 2010, 2011 Quoord Systems Limited
* @license http://opensource.org/licenses/gpl-2.0.php GNU Public License (GPLv2)
*
*/

defined('IN_MOBIQUO') or exit;

function get_box_info_func()
{
    global $db, $user, $config, $phpbb_root_path, $phpEx;
    $user->setup('ucp');
    
    if (!$user->data['is_registered']) trigger_error('LOGIN_EXPLAIN_UCP');
    if (!$config['allow_privmsg']) trigger_error('Module not accessible');

    $folder = array();
    $user_id = $user->data['user_id'];
    // Get folder information
    $sql = 'SELECT folder_id, COUNT(msg_id) as num_messages, SUM(pm_unread) as num_unread
            FROM ' . PRIVMSGS_TO_TABLE . "
            WHERE user_id = $user_id
            AND folder_id <> " . PRIVMSGS_NO_BOX . '
            GROUP BY folder_id';
    $result = $db->sql_query($sql);

    $num_messages = $num_unread = array();
    while ($row = $db->sql_fetchrow($result))
    {
        $num_messages[(int) $row['folder_id']] = $row['num_messages'];
        $num_unread[(int) $row['folder_id']] = $row['num_unread'];
    }
    $db->sql_freeresult($result);

    // Make sure the default boxes are defined
    $available_folder = array(PRIVMSGS_INBOX, PRIVMSGS_OUTBOX, PRIVMSGS_SENTBOX);

    foreach ($available_folder as $default_folder)
    {
        if (!isset($num_messages[$default_folder]))
        {
            $num_messages[$default_folder] = 0;
        }

        if (!isset($num_unread[$default_folder]))
        {
            $num_unread[$default_folder] = 0;
        }
    }

    // Adjust unread status for outbox
    $num_unread[PRIVMSGS_OUTBOX] = $num_messages[PRIVMSGS_OUTBOX];

    $folder[PRIVMSGS_INBOX] = array(
        'folder_id'         => 0,
        'folder_name'       => $user->lang['PM_INBOX'],
        'num_messages'      => $num_messages[PRIVMSGS_INBOX],
        'unread_messages'   => $num_unread[PRIVMSGS_INBOX],
        'folder_type'       => 'INBOX'
    );

    // Custom Folder
    $sql = 'SELECT folder_id, folder_name, pm_count
            FROM ' . PRIVMSGS_FOLDER_TABLE . "
            WHERE user_id = $user_id";
    $result = $db->sql_query($sql);

    while ($row = $db->sql_fetchrow($result))
    {
        $folder[$row['folder_id']] = array(
            'folder_id'         => $row['folder_id'],
            'folder_name'       => $row['folder_name'],
            'num_messages'      => $row['pm_count'],
            'unread_messages'   => ((isset($num_unread[$row['folder_id']])) ? $num_unread[$row['folder_id']] : 0)
        );
    }
    $db->sql_freeresult($result);

    $folder[PRIVMSGS_OUTBOX] = array(
        'folder_id'         => -2,
        'folder_name'       => $user->lang['PM_OUTBOX'],
        'num_messages'      => $num_messages[PRIVMSGS_OUTBOX],
        'unread_messages'   => $num_unread[PRIVMSGS_OUTBOX],
        'folder_type'       => 'OUTBOX'
    );

    $folder[PRIVMSGS_SENTBOX] = array(
        'folder_id'         => -1,
        'folder_name'       => $user->lang['PM_SENTBOX'],
        'num_messages'      => $num_messages[PRIVMSGS_SENTBOX],
        'unread_messages'   => $num_unread[PRIVMSGS_SENTBOX],
        'folder_type'       => 'SENT'
    );
    
    $box_list = array();
    foreach($folder as $box)
    {
        $box_list[] = new xmlrpcval(array(
            'box_id'        => new xmlrpcval($box['folder_id'],"string"),
            'box_name'      => new xmlrpcval($box['folder_name'], 'base64'),
            'msg_count'     => new xmlrpcval($box['num_messages'], 'int'),
            'unread_count'  => new xmlrpcval($box['unread_messages'], 'int'),
            'box_type'      => new xmlrpcval(isset($box['folder_type']) ? $box['folder_type'] : '')
        ), 'struct');
        
    }
    
    //include_once($phpbb_root_path. 'includes/functions_privmsgs.' . $phpEx);
    mobi_set_user_message_limit();
    $message_room_count = ($user->data['message_limit']) ? $user->data['message_limit'] - $num_messages[PRIVMSGS_INBOX] : 0;
    
    $result = new xmlrpcval(array(
        'message_room_count' => new xmlrpcval($message_room_count, 'int'),
        'list'               => new xmlrpcval($box_list, 'array') 
    ), 'struct');

    return new xmlrpcresp($result);
}

function mobi_set_user_message_limit()
{
	global $user, $db, $config;

	// Get maximum about from user memberships - if it is 0, there is no limit set and we use the maximum value within the config.
	$sql = 'SELECT MAX(g.group_message_limit) as max_message_limit
		FROM ' . GROUPS_TABLE . ' g, ' . USER_GROUP_TABLE . ' ug
		WHERE ug.user_id = ' . $user->data['user_id'] . '
			AND ug.user_pending = 0
			AND ug.group_id = g.group_id';
	$result = $db->sql_query($sql);
	$message_limit = (int) $db->sql_fetchfield('max_message_limit');
	$db->sql_freeresult($result);

	$user->data['message_limit'] = (!$message_limit) ? $config['pm_max_msgs'] : $message_limit;
}