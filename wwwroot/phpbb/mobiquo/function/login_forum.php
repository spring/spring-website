<?php
/**
*
* @copyright (c) 2009, 2010, 2011 Quoord Systems Limited
* @license http://opensource.org/licenses/gpl-2.0.php GNU Public License (GPLv2)
*
*/

defined('IN_MOBIQUO') or exit;

function login_forum_func($xmlrpc_params)
{
    global $db, $auth, $user, $config;
    
    $user->setup('viewforum');
    
    $params = php_xmlrpc_decode($xmlrpc_params);
    
    $forum_id = intval($params[0]);
    $password = $params[1];
    
    if (!$forum_id) trigger_error('NO_FORUM');
    
    $sql_from = FORUMS_TABLE . ' f';
    $lastread_select = '';
    
    // Grab appropriate forum data
    if ($config['load_db_lastread'] && $user->data['is_registered'])
    {
        $sql_from .= ' LEFT JOIN ' . FORUMS_TRACK_TABLE . ' ft ON (ft.user_id = ' . $user->data['user_id'] . '
            AND ft.forum_id = f.forum_id)';
        $lastread_select .= ', ft.mark_time';
    }
    
    if ($user->data['is_registered'])
    {
        $sql_from .= ' LEFT JOIN ' . FORUMS_WATCH_TABLE . ' fw ON (fw.forum_id = f.forum_id AND fw.user_id = ' . $user->data['user_id'] . ')';
        $lastread_select .= ', fw.notify_status';
    }
    
    $sql = "SELECT f.* $lastread_select
        FROM $sql_from
        WHERE f.forum_id = $forum_id";
    $result = $db->sql_query($sql);
    $forum_data = $db->sql_fetchrow($result);
    $db->sql_freeresult($result);
    
    if (!$forum_data) trigger_error('NO_FORUM');
    
    // Permissions check
    if (!$auth->acl_gets('f_list', 'f_read', $forum_id) || ($forum_data['forum_type'] == FORUM_LINK && $forum_data['forum_link'] && !$auth->acl_get('f_read', $forum_id)))
    {
        if ($user->data['user_id'] != ANONYMOUS)
        {
            trigger_error('SORRY_AUTH_READ');
        }
    
        trigger_error('LOGIN_VIEWFORUM');
    }
    
    $login_status = false;
    // Forum is passworded ... check whether access has been granted to this
    // user this session, if not show login box
    if ($forum_data['forum_password'])
    {
        $sql = 'SELECT forum_id
            FROM ' . FORUMS_ACCESS_TABLE . '
            WHERE forum_id = ' . $forum_data['forum_id'] . '
                AND user_id = ' . $user->data['user_id'] . "
                AND session_id = '" . $db->sql_escape($user->session_id) . "'";
        $result = $db->sql_query($sql);
        $row = $db->sql_fetchrow($result);
        $db->sql_freeresult($result);
    
        if ($row)
        {
            $login_status = true;
        }
        elseif ($password)
        {
            // Remove expired authorised sessions
            $sql = 'SELECT f.session_id
                FROM ' . FORUMS_ACCESS_TABLE . ' f
                LEFT JOIN ' . SESSIONS_TABLE . ' s ON (f.session_id = s.session_id)
                WHERE s.session_id IS NULL';
            $result = $db->sql_query($sql);
    
            if ($row = $db->sql_fetchrow($result))
            {
                $sql_in = array();
                do
                {
                    $sql_in[] = (string) $row['session_id'];
                }
                while ($row = $db->sql_fetchrow($result));
    
                // Remove expired sessions
                $sql = 'DELETE FROM ' . FORUMS_ACCESS_TABLE . '
                    WHERE ' . $db->sql_in_set('session_id', $sql_in);
                $db->sql_query($sql);
            }
            $db->sql_freeresult($result);
    
            if (phpbb_check_hash($password, $forum_data['forum_password']))
            {
                $sql_ary = array(
                    'forum_id'        => (int) $forum_data['forum_id'],
                    'user_id'        => (int) $user->data['user_id'],
                    'session_id'    => (string) $user->session_id,
                );
    
                $db->sql_query('INSERT INTO ' . FORUMS_ACCESS_TABLE . ' ' . $db->sql_build_array('INSERT', $sql_ary));
    
                $login_status = true;
            }
        }
    }
    
    $response = new xmlrpcval(
        array(
            'result'        => new xmlrpcval($login_status, 'boolean'),
            'result_text'   => new xmlrpcval($login_status ? '' : 'Password is wrong', 'base64'),
        ),
        'struct'
    );

    return new xmlrpcresp($response);
}
