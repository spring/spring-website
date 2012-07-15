<?php
/**
*
* @copyright (c) 2009, 2010, 2011 Quoord Systems Limited
* @license http://opensource.org/licenses/gpl-2.0.php GNU Public License (GPLv2)
*
*/

defined('IN_MOBIQUO') or exit;

include($phpbb_root_path . 'includes/functions_admin.' . $phpEx);
require($phpbb_root_path . 'includes/functions_module.' . $phpEx);

$user->setup('mcp');

$module = new p_master();

// Basic parameter data
$id = request_var('i', '');

if (isset($_REQUEST['mode']) && is_array($_REQUEST['mode']))
{
    $mode = request_var('mode', array(''));
    list($mode, ) = each($mode);
}
else
{
    $mode = request_var('mode', '');
}

// Only Moderators can go beyond this point
if (!$user->data['is_registered'])
{
    trigger_error('LOGIN_EXPLAIN_MCP');
}

$quickmod = (isset($_REQUEST['quickmod'])) ? true : false;
$action = request_var('action', '');
$action_ary = request_var('action', array('' => 0));

$forum_action = request_var('forum_action', '');
if ($forum_action !== '' && !empty($_POST['sort']))
{
    $action = $forum_action;
}

if (sizeof($action_ary))
{
    list($action, ) = each($action_ary);
}
unset($action_ary);

if ($mode == 'topic_logs')
{
    $id = 'logs';
    $quickmod = false;
}

$post_id = request_var('p', 0);
$topic_id = request_var('t', 0);
$forum_id = request_var('f', 0);
$report_id = request_var('r', 0);
$user_id = request_var('u', 0);
$username = utf8_normalize_nfc(request_var('username', '', true));

if ($post_id)
{
    // We determine the topic and forum id here, to make sure the moderator really has moderative rights on this post
    $sql = 'SELECT topic_id, forum_id
        FROM ' . POSTS_TABLE . "
        WHERE post_id = $post_id";
    $result = $db->sql_query($sql);
    $row = $db->sql_fetchrow($result);
    $db->sql_freeresult($result);

    $_REQUEST['t'] = $topic_id = (int) $row['topic_id'];
    $_REQUEST['f'] = $forum_id = (int) ($row['forum_id']) ? $row['forum_id'] : $forum_id;
}
else if ($topic_id)
{
    $sql = 'SELECT forum_id, topic_first_post_id
        FROM ' . TOPICS_TABLE . "
        WHERE topic_id = $topic_id";
    $result = $db->sql_query($sql);
    $row = $db->sql_fetchrow($result);
    $db->sql_freeresult($result);

    $_REQUEST['f'] = $forum_id = (int) $row['forum_id'];
    if ($request_method == 'm_approve_topic') $_REQUEST['post_id_list'] = array($row['topic_first_post_id']);
}

// transform username to ban id
if ($request_method == 'm_ban_user' && $request_params[1] == 2) {
    $ban_userid = get_user_id_by_name($request_params[0]);
    $sql = 'SELECT ban_id
            FROM ' . BANLIST_TABLE . '
            WHERE ban_userid = ' . $ban_userid;
    $result = $db->sql_query_limit($sql, 1);
    $ban_id = $db->sql_fetchfield('ban_id');
    $db->sql_freeresult($result);
    unset($sql);
    $_REQUEST['unban'] = array($ban_id);
}

// If the user doesn't have any moderator powers (globally or locally) he can't access the mcp
if (!$auth->acl_getf_global('m_'))
{
    // Except he is using one of the quickmod tools for users
    $user_quickmod_actions = array(
        'lock'            => 'f_user_lock',
        'make_sticky'    => 'f_sticky',
        'make_announce'    => 'f_announce',
        'make_global'    => 'f_announce',
        'make_normal'    => array('f_announce', 'f_sticky')
    );

    $allow_user = false;
    if ($quickmod && isset($user_quickmod_actions[$action]) && $user->data['is_registered'] && $auth->acl_gets($user_quickmod_actions[$action], $forum_id))
    {
        $topic_info = get_topic_data(array($topic_id));
        if ($topic_info[$topic_id]['topic_poster'] == $user->data['user_id'])
        {
            $allow_user = true;
        }
    }

    if (!$allow_user)
    {
        trigger_error('NOT_AUTHORISED');
    }
}

// if the user cannot read the forum he tries to access then we won't allow mcp access either
if ($forum_id && !$auth->acl_get('f_read', $forum_id))
{
    trigger_error('NOT_AUTHORISED');
}

if ($forum_id)
{
    $module->acl_forum_id = $forum_id;
}

// Instantiate module system and generate list of available modules
$module->list_modules('mcp');

if ($quickmod)
{
    $mode = 'quickmod';

    switch ($action)
    {
        case 'lock':
        case 'unlock':
        case 'lock_post':
        case 'unlock_post':
        case 'make_sticky':
        case 'make_announce':
        case 'make_global':
        case 'make_normal':
        case 'fork':
        case 'move':
        case 'delete_post':
        case 'delete_topic':
            $module->load('mcp', 'main', 'quickmod');
            return;
        break;

        case 'topic_logs':
            // Reset start parameter if we jumped from the quickmod dropdown
            if (request_var('start', 0))
            {
                $_REQUEST['start'] = 0;
            }

            $module->set_active('logs', 'topic_logs');
        break;

        case 'merge_topic':
            $module->set_active('main', 'forum_view');
        break;

        case 'split':
        case 'merge':
            $module->set_active('main', 'topic_view');
        break;

        default:
            trigger_error("$action not allowed as quickmod", E_USER_ERROR);
        break;
    }
}
else
{
    // Select the active module
    $module->set_active($id, $mode);
}

$module->load_active();


function m_get_moderate_topic_func()
{
    global $template, $auth, $user;
    
    $posts = array();
    foreach($template->_tpldata['postrow'] as $postinfo) {
        $posts[] = $postinfo['POST_ID'];
    }
    
    $posts = get_post_data($posts);
    
    $post_list = array();
    foreach($template->_tpldata['postrow'] as $postinfo)
    {
        $post = $posts[$postinfo['POST_ID']];
        
        if (empty($post['forum_id']))
        {
            $post['forum_id'] = 0;
            $post['forum_name'] = $user->lang['ANNOUNCEMENTS'];
        }
        
        $post_list[] = new xmlrpcval(array(
            'forum_id'          => new xmlrpcval($post['forum_id']),
            'forum_name'        => new xmlrpcval(basic_clean($post['forum_name']), 'base64'),
            'topic_id'          => new xmlrpcval($post['topic_id']),
            'topic_title'       => new xmlrpcval(basic_clean($post['topic_title']), 'base64'),
            'post_id'           => new xmlrpcval($post['post_id']),
            'post_title'        => new xmlrpcval(basic_clean($post['post_subject']), 'base64'),
            'topic_author_name' => new xmlrpcval(basic_clean($post['username']), 'base64'),
            'icon_url'          => new xmlrpcval(get_user_avatar_url($post['user_avatar'], $post['user_avatar_type'])),
            'post_time'         => new xmlrpcval(mobiquo_iso8601_encode($post['post_time']), 'dateTime.iso8601'),
            'short_content'     => new xmlrpcval(process_short_content($post['post_text']), 'base64'),
            'can_delete'        => new xmlrpcval($auth->acl_get('m_delete', $post['forum_id']), 'boolean'),
        ), 'struct');
    }
    
    preg_match('/(\d+)\D/', $template->_rootref['TOTAL'], $total);
    
    $response = new xmlrpcval(
        array(
            'total_topic_num' => new xmlrpcval($total[1], 'int'),
            'topics'          => new xmlrpcval($post_list, 'array'),
        ), 'struct'
    );

    return new xmlrpcresp($response);
}

function m_get_moderate_post_func()
{
    global $template, $auth, $user;
    
    $posts = array();
    foreach($template->_tpldata['postrow'] as $postinfo) {
        $posts[] = $postinfo['POST_ID'];
    }
    
    $posts = get_post_data($posts);
    
    $post_list = array();
    foreach($template->_tpldata['postrow'] as $postinfo)
    {
        $post = $posts[$postinfo['POST_ID']];
        
        if (empty($post['forum_id']))
        {
            $post['forum_id'] = 0;
            $post['forum_name'] = $user->lang['ANNOUNCEMENTS'];
        }
        
        $post_list[] = new xmlrpcval(array(
            'forum_id'          => new xmlrpcval($post['forum_id']),
            'forum_name'        => new xmlrpcval(basic_clean($post['forum_name']), 'base64'),
            'topic_id'          => new xmlrpcval($post['topic_id']),
            'topic_title'       => new xmlrpcval(basic_clean($post['topic_title']), 'base64'),
            'post_id'           => new xmlrpcval($post['post_id']),
            'post_title'        => new xmlrpcval(basic_clean($post['post_subject']), 'base64'),
            'post_author_name'  => new xmlrpcval(basic_clean($post['username']), 'base64'),
            'icon_url'          => new xmlrpcval(get_user_avatar_url($post['user_avatar'], $post['user_avatar_type'])),
            'post_time'         => new xmlrpcval(mobiquo_iso8601_encode($post['post_time']), 'dateTime.iso8601'),
            'short_content'     => new xmlrpcval(process_short_content($post['post_text']), 'base64'),
            'can_delete'        => new xmlrpcval($auth->acl_get('m_delete', $forum_id), 'boolean'),
        ), 'struct');
    }
    
    preg_match('/(\d+)\D/', $template->_rootref['TOTAL'], $total);
    
    $response = new xmlrpcval(
        array(
            'total_post_num' => new xmlrpcval($total[1], 'int'),
            'posts'          => new xmlrpcval($post_list, 'array'),
        ), 'struct'
    );

    return new xmlrpcresp($response);
}

function m_get_report_post_func()
{
    global $template, $auth, $user;
    
    $posts = array();
    foreach($template->_tpldata['postrow'] as $postinfo) {
        $posts[] = $postinfo['POST_ID'];
    }
    
    $posts = get_post_data($posts);
    
    $post_list = array();
    foreach($template->_tpldata['postrow'] as $postinfo)
    {
        $post = $posts[$postinfo['POST_ID']];
        
        if (empty($post['forum_id']))
        {
            $user->setup('viewforum');
            $post['forum_id'] = 0;
            $post['forum_name'] = $user->lang['ANNOUNCEMENTS'];
        }
        
        $post_list[] = new xmlrpcval(array(
            'forum_id'          => new xmlrpcval($post['forum_id']),
            'forum_name'        => new xmlrpcval(basic_clean($post['forum_name']), 'base64'),
            'topic_id'          => new xmlrpcval($post['topic_id']),
            'topic_title'       => new xmlrpcval(basic_clean($post['topic_title']), 'base64'),
            'post_id'           => new xmlrpcval($post['post_id']),
            'post_title'        => new xmlrpcval(basic_clean($post['post_subject']), 'base64'),
            'post_author_name'  => new xmlrpcval(basic_clean($post['username']), 'base64'),
            'icon_url'          => new xmlrpcval(get_user_avatar_url($post['user_avatar'], $post['user_avatar_type'])),
            'post_time'         => new xmlrpcval(mobiquo_iso8601_encode($post['post_time']), 'dateTime.iso8601'),
            'short_content'     => new xmlrpcval(process_short_content($post['post_text']), 'base64'),
            'can_delete'        => new xmlrpcval($auth->acl_get('m_delete', $forum_id), 'boolean'),
        ), 'struct');
    }
    
    $response = new xmlrpcval(
        array(
            'total_report_num' => new xmlrpcval($template->_rootref['TOTAL'], 'int'),
            'reports'          => new xmlrpcval($post_list, 'array'),
        ), 'struct'
    );

    return new xmlrpcresp($response);
}


function extra_url()
{
    global $forum_id, $topic_id, $post_id, $report_id, $user_id;
    
    $url_extra = '';
    $url_extra .= ($forum_id) ? "&amp;f=$forum_id" : '';
    $url_extra .= ($topic_id) ? "&amp;t=$topic_id" : '';
    $url_extra .= ($post_id) ? "&amp;p=$post_id" : '';
    $url_extra .= ($user_id) ? "&amp;u=$user_id" : '';
    $url_extra .= ($report_id) ? "&amp;r=$report_id" : '';
    
    return $url_extra;
}

/**
* Get simple topic data
*/
function get_topic_data($topic_ids, $acl_list = false, $read_tracking = false)
{
    global $auth, $db, $config, $user;
    static $rowset = array();

    $topics = array();

    if (!sizeof($topic_ids))
    {
        return array();
    }

    // cache might not contain read tracking info, so we can't use it if read
    // tracking information is requested
    if (!$read_tracking)
    {
        $cache_topic_ids = array_intersect($topic_ids, array_keys($rowset));
        $topic_ids = array_diff($topic_ids, array_keys($rowset));
    }
    else
    {
        $cache_topic_ids = array();
    }

    if (sizeof($topic_ids))
    {
        $sql_array = array(
            'SELECT'    => 't.*, f.*',

            'FROM'        => array(
                TOPICS_TABLE    => 't',
            ),

            'LEFT_JOIN'    => array(
                array(
                    'FROM'    => array(FORUMS_TABLE => 'f'),
                    'ON'    => 'f.forum_id = t.forum_id'
                )
            ),

            'WHERE'        => $db->sql_in_set('t.topic_id', $topic_ids)
        );

        if ($read_tracking && $config['load_db_lastread'])
        {
            $sql_array['SELECT'] .= ', tt.mark_time, ft.mark_time as forum_mark_time';

            $sql_array['LEFT_JOIN'][] = array(
                'FROM'    => array(TOPICS_TRACK_TABLE => 'tt'),
                'ON'    => 'tt.user_id = ' . $user->data['user_id'] . ' AND t.topic_id = tt.topic_id'
            );

            $sql_array['LEFT_JOIN'][] = array(
                'FROM'    => array(FORUMS_TRACK_TABLE => 'ft'),
                'ON'    => 'ft.user_id = ' . $user->data['user_id'] . ' AND t.forum_id = ft.forum_id'
            );
        }

        $sql = $db->sql_build_query('SELECT', $sql_array);
        $result = $db->sql_query($sql);

        while ($row = $db->sql_fetchrow($result))
        {
            if (!$row['forum_id'])
            {
                // Global Announcement?
                $row['forum_id'] = request_var('f', 0);
            }

            $rowset[$row['topic_id']] = $row;

            if ($acl_list && !$auth->acl_gets($acl_list, $row['forum_id']))
            {
                continue;
            }

            $topics[$row['topic_id']] = $row;
        }
        $db->sql_freeresult($result);
    }

    foreach ($cache_topic_ids as $id)
    {
        if (!$acl_list || $auth->acl_gets($acl_list, $rowset[$id]['forum_id']))
        {
            $topics[$id] = $rowset[$id];
        }
    }

    return $topics;
}

/**
* Get simple post data
*/
function get_post_data($post_ids, $acl_list = false, $read_tracking = false)
{
    global $db, $auth, $config, $user;

    $rowset = array();

    if (!sizeof($post_ids))
    {
        return array();
    }

    $sql_array = array(
        'SELECT'    => 'p.*, u.*, t.*, f.*',

        'FROM'        => array(
            USERS_TABLE        => 'u',
            POSTS_TABLE        => 'p',
            TOPICS_TABLE    => 't',
        ),

        'LEFT_JOIN'    => array(
            array(
                'FROM'    => array(FORUMS_TABLE => 'f'),
                'ON'    => 'f.forum_id = t.forum_id'
            )
        ),

        'WHERE'        => $db->sql_in_set('p.post_id', $post_ids) . '
            AND u.user_id = p.poster_id
            AND t.topic_id = p.topic_id',
    );

    if ($read_tracking && $config['load_db_lastread'])
    {
        $sql_array['SELECT'] .= ', tt.mark_time, ft.mark_time as forum_mark_time';

        $sql_array['LEFT_JOIN'][] = array(
            'FROM'    => array(TOPICS_TRACK_TABLE => 'tt'),
            'ON'    => 'tt.user_id = ' . $user->data['user_id'] . ' AND t.topic_id = tt.topic_id'
        );

        $sql_array['LEFT_JOIN'][] = array(
            'FROM'    => array(FORUMS_TRACK_TABLE => 'ft'),
            'ON'    => 'ft.user_id = ' . $user->data['user_id'] . ' AND t.forum_id = ft.forum_id'
        );
    }

    $sql = $db->sql_build_query('SELECT', $sql_array);
    $result = $db->sql_query($sql);
    unset($sql_array);

    while ($row = $db->sql_fetchrow($result))
    {
        if (!$row['forum_id'])
        {
            // Global Announcement?
            $row['forum_id'] = request_var('f', 0);
        }

        if ($acl_list && !$auth->acl_gets($acl_list, $row['forum_id']))
        {
            continue;
        }

        if (!$row['post_approved'] && !$auth->acl_get('m_approve', $row['forum_id']))
        {
            // Moderators without the permission to approve post should at least not see them. ;)
            continue;
        }

        $rowset[$row['post_id']] = $row;
    }
    $db->sql_freeresult($result);

    return $rowset;
}

/**
* Get simple forum data
*/
function get_forum_data($forum_id, $acl_list = 'f_list', $read_tracking = false)
{
    global $auth, $db, $user, $config;

    $rowset = array();

    if (!is_array($forum_id))
    {
        $forum_id = array($forum_id);
    }

    if (!sizeof($forum_id))
    {
        return array();
    }

    if ($read_tracking && $config['load_db_lastread'])
    {
        $read_tracking_join = ' LEFT JOIN ' . FORUMS_TRACK_TABLE . ' ft ON (ft.user_id = ' . $user->data['user_id'] . '
            AND ft.forum_id = f.forum_id)';
        $read_tracking_select = ', ft.mark_time';
    }
    else
    {
        $read_tracking_join = $read_tracking_select = '';
    }

    $sql = "SELECT f.* $read_tracking_select
        FROM " . FORUMS_TABLE . " f$read_tracking_join
        WHERE " . $db->sql_in_set('f.forum_id', $forum_id);
    $result = $db->sql_query($sql);

    while ($row = $db->sql_fetchrow($result))
    {
        if ($acl_list && !$auth->acl_gets($acl_list, $row['forum_id']))
        {
            continue;
        }

        if ($auth->acl_get('m_approve', $row['forum_id']))
        {
            $row['forum_topics'] = $row['forum_topics_real'];
        }

        $rowset[$row['forum_id']] = $row;
    }
    $db->sql_freeresult($result);

    return $rowset;
}

/**
* Get simple pm data
*/
function get_pm_data($pm_ids)
{
    global $db, $auth, $config, $user;

    $rowset = array();

    if (!sizeof($pm_ids))
    {
        return array();
    }

    $sql_array = array(
        'SELECT'    => 'p.*, u.*',

        'FROM'        => array(
            USERS_TABLE            => 'u',
            PRIVMSGS_TABLE        => 'p',
        ),

        'WHERE'        => $db->sql_in_set('p.msg_id', $pm_ids) . '
            AND u.user_id = p.author_id',
    );

    $sql = $db->sql_build_query('SELECT', $sql_array);
    $result = $db->sql_query($sql);
    unset($sql_array);

    while ($row = $db->sql_fetchrow($result))
    {
        $rowset[$row['msg_id']] = $row;
    }
    $db->sql_freeresult($result);

    return $rowset;
}

function mcp_sorting($mode, &$sort_days, &$sort_key, &$sort_dir, &$sort_by_sql, &$sort_order_sql, &$total, $forum_id = 0, $topic_id = 0, $where_sql = 'WHERE')
{
    global $db, $user, $auth, $template, $config;

    $sort_days = request_var('st', 0);
    $min_time = ($sort_days) ? time() - ($sort_days * 86400) : 0;

    switch ($mode)
    {
        case 'viewforum':
            $type = 'topics';
            $default_key = 't';
            $default_dir = 'd';

            $sql = 'SELECT COUNT(topic_id) AS total
                FROM ' . TOPICS_TABLE . "
                $where_sql forum_id = $forum_id
                    AND topic_type NOT IN (" . POST_ANNOUNCE . ', ' . POST_GLOBAL . ")
                    AND topic_last_post_time >= $min_time";

            if (!$auth->acl_get('m_approve', $forum_id))
            {
                $sql .= 'AND topic_approved = 1';
            }
        break;

        case 'viewtopic':
            $type = 'posts';
            $default_key = 't';
            $default_dir = 'a';

            $sql = 'SELECT COUNT(post_id) AS total
                FROM ' . POSTS_TABLE . "
                $where_sql topic_id = $topic_id
                    AND post_time >= $min_time";

            if (!$auth->acl_get('m_approve', $forum_id))
            {
                $sql .= 'AND post_approved = 1';
            }
        break;

        case 'unapproved_posts':
            $type = 'posts';
            $default_key = 't';
            $default_dir = 'd';
            $where_sql .= ($topic_id) ? ' p.topic_id = ' . $topic_id . ' AND' : '';

            $sql = 'SELECT COUNT(p.post_id) AS total
                FROM ' . POSTS_TABLE . ' p, ' . TOPICS_TABLE . " t
                $where_sql " . $db->sql_in_set('p.forum_id', ($forum_id) ? array($forum_id) : array_intersect(get_forum_list('f_read'), get_forum_list('m_approve'))) . '
                    AND p.post_approved = 0
                    AND t.topic_id = p.topic_id
                    AND t.topic_first_post_id <> p.post_id';

            if ($min_time)
            {
                $sql .= ' AND post_time >= ' . $min_time;
            }
        break;

        case 'unapproved_topics':
            $type = 'topics';
            $default_key = 't';
            $default_dir = 'd';

            $sql = 'SELECT COUNT(topic_id) AS total
                FROM ' . TOPICS_TABLE . "
                $where_sql " . $db->sql_in_set('forum_id', ($forum_id) ? array($forum_id) : array_intersect(get_forum_list('f_read'), get_forum_list('m_approve'))) . '
                    AND topic_approved = 0';

            if ($min_time)
            {
                $sql .= ' AND topic_time >= ' . $min_time;
            }
        break;

        case 'pm_reports':
        case 'pm_reports_closed':
        case 'reports':
        case 'reports_closed':
            $pm = (strpos($mode, 'pm_') === 0) ? true : false;

            $type = ($pm) ? 'pm_reports' : 'reports';
            $default_key = 't';
            $default_dir = 'd';
            $limit_time_sql = ($min_time) ? "AND r.report_time >= $min_time" : '';

            if ($topic_id)
            {
                $where_sql .= ' p.topic_id = ' . $topic_id . ' AND ';
            }
            else if ($forum_id)
            {
                $where_sql .= ' p.forum_id = ' . $forum_id . ' AND ';
            }
            else if (!$pm)
            {
                $where_sql .= ' ' . $db->sql_in_set('p.forum_id', get_forum_list(array('!f_read', '!m_report')), true, true) . ' AND ';
            }

            if ($mode == 'reports' || $mode == 'pm_reports')
            {
                $where_sql .= ' r.report_closed = 0 AND ';
            }
            else
            {
                $where_sql .= ' r.report_closed = 1 AND ';
            }

            if ($pm)
            {
                $sql = 'SELECT COUNT(r.report_id) AS total
                    FROM ' . REPORTS_TABLE . ' r, ' . PRIVMSGS_TABLE . " p
                    $where_sql r.post_id = 0
                        AND p.msg_id = r.pm_id
                        $limit_time_sql";
            }
            else
            {
                $sql = 'SELECT COUNT(r.report_id) AS total
                    FROM ' . REPORTS_TABLE . ' r, ' . POSTS_TABLE . " p
                    $where_sql " . (version_compare($config['version'], '3.0.6', '<') ? '1' : " r.pm_id = 0") . "
                        AND p.post_id = r.post_id
                        $limit_time_sql";
            }
        break;

        case 'viewlogs':
            $type = 'logs';
            $default_key = 't';
            $default_dir = 'd';

            $sql = 'SELECT COUNT(log_id) AS total
                FROM ' . LOG_TABLE . "
                $where_sql " . $db->sql_in_set('forum_id', ($forum_id) ? array($forum_id) : array_intersect(get_forum_list('f_read'), get_forum_list('m_'))) . '
                    AND log_time >= ' . $min_time . '
                    AND log_type = ' . LOG_MOD;
        break;
    }

    $sort_key = request_var('sk', $default_key);
    $sort_dir = request_var('sd', $default_dir);
    $sort_dir_text = array('a' => $user->lang['ASCENDING'], 'd' => $user->lang['DESCENDING']);

    switch ($type)
    {
        case 'topics':
            $limit_days = array(0 => $user->lang['ALL_TOPICS'], 1 => $user->lang['1_DAY'], 7 => $user->lang['7_DAYS'], 14 => $user->lang['2_WEEKS'], 30 => $user->lang['1_MONTH'], 90 => $user->lang['3_MONTHS'], 180 => $user->lang['6_MONTHS'], 365 => $user->lang['1_YEAR']);
            $sort_by_text = array('a' => $user->lang['AUTHOR'], 't' => $user->lang['POST_TIME'], 'tt' => $user->lang['TOPIC_TIME'], 'r' => $user->lang['REPLIES'], 's' => $user->lang['SUBJECT'], 'v' => $user->lang['VIEWS']);

            $sort_by_sql = array('a' => 't.topic_first_poster_name', 't' => 't.topic_last_post_time', 'tt' => 't.topic_time', 'r' => (($auth->acl_get('m_approve', $forum_id)) ? 't.topic_replies_real' : 't.topic_replies'), 's' => 't.topic_title', 'v' => 't.topic_views');
            $limit_time_sql = ($min_time) ? "AND t.topic_last_post_time >= $min_time" : '';
        break;

        case 'posts':
            $limit_days = array(0 => $user->lang['ALL_POSTS'], 1 => $user->lang['1_DAY'], 7 => $user->lang['7_DAYS'], 14 => $user->lang['2_WEEKS'], 30 => $user->lang['1_MONTH'], 90 => $user->lang['3_MONTHS'], 180 => $user->lang['6_MONTHS'], 365 => $user->lang['1_YEAR']);
            $sort_by_text = array('a' => $user->lang['AUTHOR'], 't' => $user->lang['POST_TIME'], 's' => $user->lang['SUBJECT']);
            $sort_by_sql = array('a' => 'u.username_clean', 't' => 'p.post_time', 's' => 'p.post_subject');
            $limit_time_sql = ($min_time) ? "AND p.post_time >= $min_time" : '';
        break;

        case 'reports':
            $limit_days = array(0 => $user->lang['ALL_REPORTS'], 1 => $user->lang['1_DAY'], 7 => $user->lang['7_DAYS'], 14 => $user->lang['2_WEEKS'], 30 => $user->lang['1_MONTH'], 90 => $user->lang['3_MONTHS'], 180 => $user->lang['6_MONTHS'], 365 => $user->lang['1_YEAR']);
            $sort_by_text = array('a' => $user->lang['AUTHOR'], 'r' => $user->lang['REPORTER'], 'p' => $user->lang['POST_TIME'], 't' => $user->lang['REPORT_TIME'], 's' => $user->lang['SUBJECT']);
            $sort_by_sql = array('a' => 'u.username_clean', 'r' => 'ru.username', 'p' => 'p.post_time', 't' => 'r.report_time', 's' => 'p.post_subject');
        break;

        case 'pm_reports':
            $limit_days = array(0 => $user->lang['ALL_REPORTS'], 1 => $user->lang['1_DAY'], 7 => $user->lang['7_DAYS'], 14 => $user->lang['2_WEEKS'], 30 => $user->lang['1_MONTH'], 90 => $user->lang['3_MONTHS'], 180 => $user->lang['6_MONTHS'], 365 => $user->lang['1_YEAR']);
            $sort_by_text = array('a' => $user->lang['AUTHOR'], 'r' => $user->lang['REPORTER'], 'p' => $user->lang['POST_TIME'], 't' => $user->lang['REPORT_TIME'], 's' => $user->lang['SUBJECT']);
            $sort_by_sql = array('a' => 'u.username_clean', 'r' => 'ru.username', 'p' => 'p.message_time', 't' => 'r.report_time', 's' => 'p.message_subject');
        break;

        case 'logs':
            $limit_days = array(0 => $user->lang['ALL_ENTRIES'], 1 => $user->lang['1_DAY'], 7 => $user->lang['7_DAYS'], 14 => $user->lang['2_WEEKS'], 30 => $user->lang['1_MONTH'], 90 => $user->lang['3_MONTHS'], 180 => $user->lang['6_MONTHS'], 365 => $user->lang['1_YEAR']);
            $sort_by_text = array('u' => $user->lang['SORT_USERNAME'], 't' => $user->lang['SORT_DATE'], 'i' => $user->lang['SORT_IP'], 'o' => $user->lang['SORT_ACTION']);

            $sort_by_sql = array('u' => 'u.username_clean', 't' => 'l.log_time', 'i' => 'l.log_ip', 'o' => 'l.log_operation');
            $limit_time_sql = ($min_time) ? "AND l.log_time >= $min_time" : '';
        break;
    }

    if (!isset($sort_by_sql[$sort_key]))
    {
        $sort_key = $default_key;
    }

    $sort_order_sql = $sort_by_sql[$sort_key] . ' ' . (($sort_dir == 'd') ? 'DESC' : 'ASC');

    $s_limit_days = $s_sort_key = $s_sort_dir = $sort_url = '';
    gen_sort_selects($limit_days, $sort_by_text, $sort_days, $sort_key, $sort_dir, $s_limit_days, $s_sort_key, $s_sort_dir, $sort_url);

    $template->assign_vars(array(
        'S_SELECT_SORT_DIR'        => $s_sort_dir,
        'S_SELECT_SORT_KEY'        => $s_sort_key,
        'S_SELECT_SORT_DAYS'    => $s_limit_days)
    );

    if (($sort_days && $mode != 'viewlogs') || in_array($mode, array('reports', 'unapproved_topics', 'unapproved_posts')) || $where_sql != 'WHERE')
    {
        $result = $db->sql_query($sql);
        $total = (int) $db->sql_fetchfield('total');
        $db->sql_freeresult($result);
    }
    else
    {
        $total = -1;
    }
}

function check_ids(&$ids, $table, $sql_id, $acl_list = false, $single_forum = false)
{
    global $db, $auth;

    if (!is_array($ids) || empty($ids))
    {
        return false;
    }

    $sql = "SELECT $sql_id, forum_id FROM $table
        WHERE " . $db->sql_in_set($sql_id, $ids);
    $result = $db->sql_query($sql);

    $ids = array();
    $forum_id = false;

    while ($row = $db->sql_fetchrow($result))
    {
        if ($acl_list && $row['forum_id'] && !$auth->acl_gets($acl_list, $row['forum_id']))
        {
            continue;
        }

        if ($acl_list && !$row['forum_id'] && !$auth->acl_getf_global($acl_list))
        {
            continue;
        }

        // Limit forum? If not, just assign the id.
        if ($single_forum === false)
        {
            $ids[] = $row[$sql_id];
            continue;
        }

        // Limit forum to a specific forum id?
        // This can get really tricky, because we do not want to create a failure on global topics. :)
        if ($row['forum_id'])
        {
            if ($single_forum !== true && $row['forum_id'] == (int) $single_forum)
            {
                $forum_id = (int) $single_forum;
            }
            else if ($forum_id === false)
            {
                $forum_id = $row['forum_id'];
            }

            if ($row['forum_id'] == $forum_id)
            {
                $ids[] = $row[$sql_id];
            }
        }
        else
        {
            // Always add a global topic
            $ids[] = $row[$sql_id];
        }
    }
    $db->sql_freeresult($result);

    if (!sizeof($ids))
    {
        return false;
    }

    // If forum id is false and ids populated we may have only global announcements selected (returning 0 because of (int) $forum_id)

    return ($single_forum === false) ? true : (int) $forum_id;
}
