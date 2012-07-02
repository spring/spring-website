<?php
/**
*
* @copyright (c) 2009, 2010, 2011 Quoord Systems Limited
* @license http://opensource.org/licenses/gpl-2.0.php GNU Public License (GPLv2)
*
*/

defined('IN_MOBIQUO') or exit;

function subscribe_forum_func($xmlrpc_params)
{
    global $db, $user, $config, $auth;
    
    $user->setup('viewforum');
    
    $params = php_xmlrpc_decode($xmlrpc_params);
    
    $forum_id = intval($params[0]);
    if (!$forum_id) trigger_error('NO_FORUM');
    $user_id = $user->data['user_id'];

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
    
    // Forum is passworded ... check whether access has been granted to this
    // user this session, if not show login box
    if ($forum_data['forum_password'] && !check_forum_password($forum_id))
        trigger_error('LOGIN_FORUM');
    
    // Is this forum a link? ... User got here either because the
    // number of clicks is being tracked or they guessed the id
    if ($forum_data['forum_type'] == FORUM_LINK && $forum_data['forum_link'])
    {
        trigger_error('NO_FORUM');
    }
    
    // Not postable forum or showing active topics?
    if (!($forum_data['forum_type'] == FORUM_POST || (($forum_data['forum_flags'] & FORUM_FLAG_ACTIVE_TOPICS) && $forum_data['forum_type'] == FORUM_CAT)))
    {
        trigger_error('NO_FORUM');
    }
    
    // Ok, if someone has only list-access, we only display the forum list.
    // We also make this circumstance available to the template in case we want to display a notice. ;)
    if (!$auth->acl_get('f_read', $forum_id))
    {
        trigger_error('SORRY_AUTH_READ');
    }
    
    if (($config['email_enable'] || $config['jab_enable']) && $config['allow_forum_notify'] && $forum_data['forum_type'] == FORUM_POST && $auth->acl_get('f_subscribe', $forum_id))
    {
        $notify_status = (isset($forum_data['notify_status'])) ? $forum_data['notify_status'] : 'unset';
        
        $table_sql = FORUMS_WATCH_TABLE;
        $where_sql = 'forum_id';
        $match_id = $forum_id;
    
        // Is user watching this thread?
        if ($user_id != ANONYMOUS)
        {
            if ($notify_status == 'unset')
            {
                $sql = "SELECT notify_status
                    FROM $table_sql
                    WHERE $where_sql = $match_id
                        AND user_id = $user_id";
                $result = $db->sql_query($sql);
    
                $notify_status = ($row = $db->sql_fetchrow($result)) ? $row['notify_status'] : NULL;
                $db->sql_freeresult($result);
            }
    
            if (!is_null($notify_status) && $notify_status !== '')
            {
                if ($notify_status)
                {
                    $sql = 'UPDATE ' . $table_sql . "
                        SET notify_status = 0
                        WHERE $where_sql = $match_id
                            AND user_id = $user_id";
                    $db->sql_query($sql);
                }
            }
            else
            {
                $sql = 'INSERT INTO ' . $table_sql . " (user_id, $where_sql, notify_status)
                    VALUES ($user_id, $match_id, 0)";
                $db->sql_query($sql);
            }
            $s_result = true;
        }
        else
        {
            trigger_error('LOGIN_VIEWFORUM');
        }
    }
    else
    {
        $s_result = false;
    }
    
    $response = new xmlrpcval(
        array(
            'result'        => new xmlrpcval($s_result, 'boolean'),
            'result_text'   => new xmlrpcval($s_result ? '' : 'Subscribe failed', 'base64'),
        ),
        'struct'
    );
    
    return new xmlrpcresp($response);
}
