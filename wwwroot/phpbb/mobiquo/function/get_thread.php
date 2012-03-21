<?php
/**
*
* @copyright (c) 2009 Quoord Systems Limited
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

defined('IN_MOBIQUO') or exit;

function get_thread_func($xmlrpc_params)
{
    global $db, $auth, $user, $config, $template, $cache, $phpEx, $phpbb_root_path, $phpbb_home;
    
    include($phpbb_root_path . 'includes/bbcode.' . $phpEx);

    $params = php_xmlrpc_decode($xmlrpc_params);
    
    $start_num  = 0;            // default start index of posts
    $end_num    = 19;           // default request posts number is 20

    if (!isset($params[0]))     // topic id undefine
    {
        return get_error(1);
    }
    else if ($params[0] === 0)  // topic id equal 0
    {
        return get_error(7);
    }

    // get topic id from parameters
    $topic_id = $params[0];
    
    // get start index of post from parameters
    if (isset($params[1]) && is_int($params[1]))
    {
        $start_num = $params[1];
        $end_num = $start_num + 19;
    }
    
    // get end index of post from parameters
    if (isset($params[2]) && is_int($params[2]))
    {
        $end_num = $params[2];
    }
    
    // check if post index is out of range
    if ($start_num > $end_num)
    {
        return get_error(5);
    }
    
    // return at most 50 posts
    if ($end_num - $start_num >= 50)
    {
        $end_num = $start_num + 49;
    }
    
    // Initial var setup
    $post_id  = request_var('p', 0);
    $voted_id = request_var('vote_id', array('' => 0));
    
    $start = $start_num;
    $config['posts_per_page'] = $end_num - $start_num + 1;
    
    $default_sort_days = (!empty($user->data['user_post_show_days'])) ? $user->data['user_post_show_days'] : 0;
    $default_sort_key  = (!empty($user->data['user_post_sortby_type'])) ? $user->data['user_post_sortby_type'] : 't';
    $default_sort_dir  = (!empty($user->data['user_post_sortby_dir'])) ? $user->data['user_post_sortby_dir'] : 'a';
    
    $sort_days = request_var('st', $default_sort_days);
    $sort_key  = request_var('sk', $default_sort_key);
    $sort_dir  = request_var('sd', $default_sort_dir);    
    
    // This rather complex gaggle of code handles querying for topics but
    // also allows for direct linking to a post (and the calculation of which
    // page the post is on and the correct display of viewtopic)
    $sql_array = array(
        'SELECT'    => 't.*, f.*',
        'FROM'      => array(FORUMS_TABLE => 'f'),
    );
    
    
    // Topics table need to be the last in the chain
    $sql_array['FROM'][TOPICS_TABLE] = 't';
    
    if ($user->data['is_registered'])
    {
        $sql_array['SELECT'] .= ', tw.notify_status';
        $sql_array['LEFT_JOIN'] = array();
    
        $sql_array['LEFT_JOIN'][] = array(
            'FROM'    => array(TOPICS_WATCH_TABLE => 'tw'),
            'ON'    => 'tw.user_id = ' . $user->data['user_id'] . ' AND t.topic_id = tw.topic_id'
        );
    
        if ($config['allow_bookmarks'])
        {
            $sql_array['SELECT'] .= ', bm.topic_id as bookmarked';
            $sql_array['LEFT_JOIN'][] = array(
                'FROM'    => array(BOOKMARKS_TABLE => 'bm'),
                'ON'    => 'bm.user_id = ' . $user->data['user_id'] . ' AND t.topic_id = bm.topic_id'
            );
        }
    
        if ($config['load_db_lastread'])
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
    }
    
    if (!$post_id)
    {
        $sql_array['WHERE'] = "t.topic_id = $topic_id";
    }
    
    $sql_array['WHERE'] .= ' AND (f.forum_id = t.forum_id';
    
    if (!$forum_id)
    {
        // If it is a global announcement make sure to set the forum id to a postable forum
        $sql_array['WHERE'] .= ' OR (t.topic_type = ' . POST_GLOBAL . '
            AND f.forum_type = ' . FORUM_POST . ')';
    }
    else
    {
        $sql_array['WHERE'] .= ' OR (t.topic_type = ' . POST_GLOBAL . "
            AND f.forum_id = $forum_id)";
    }
    
    $sql_array['WHERE'] .= ')';
    
    // Join to forum table on topic forum_id unless topic forum_id is zero
    // whereupon we join on the forum_id passed as a parameter ... this
    // is done so navigation, forum name, etc. remain consistent with where
    // user clicked to view a global topic
    $sql = $db->sql_build_query('SELECT', $sql_array);
    $result = $db->sql_query($sql);
    $topic_data = $db->sql_fetchrow($result);
    $db->sql_freeresult($result);
    
    if (!$topic_data)
    {
        return get_error(7);        
    }
    
    $forum_id = (int) $topic_data['forum_id'];
    $topic_id = (int) $topic_data['topic_id'];
    
    //
    $topic_replies = ($auth->acl_get('m_approve', $forum_id)) ? $topic_data['topic_replies_real'] : $topic_data['topic_replies'];
    
    // Check sticky/announcement time limit
    if (($topic_data['topic_type'] == POST_STICKY || $topic_data['topic_type'] == POST_ANNOUNCE) && $topic_data['topic_time_limit'] && ($topic_data['topic_time'] + $topic_data['topic_time_limit']) < time())
    {
        $sql = 'UPDATE ' . TOPICS_TABLE . '
            SET topic_type = ' . POST_NORMAL . ', topic_time_limit = 0
            WHERE topic_id = ' . $topic_id;
        $db->sql_query($sql);
    
        $topic_data['topic_type'] = POST_NORMAL;
        $topic_data['topic_time_limit'] = 0;
    }
    
    // Setup look and feel
    $user->setup('viewtopic', $topic_data['forum_style']);
    
    if (!$topic_data['topic_approved'] && !$auth->acl_get('m_approve', $forum_id))
    {
//        trigger_error('NO_TOPIC');
        return get_error(7);
    }
    
    // Start auth check
    if (!$auth->acl_get('f_read', $forum_id))
    {
//        if ($user->data['user_id'] != ANONYMOUS)
//        {
//            trigger_error('SORRY_AUTH_READ');
            return get_error(2);
//        }
    
//        login_box('', $user->lang['LOGIN_VIEWFORUM']);
    }
    
    // Forum is passworded ... check whether access has been granted to this
    // user this session, if not show login box
    if ($topic_data['forum_password'] && !check_forum_password($forum_id))
    {
        return get_error(6);
    }
    
    
    // Get topic tracking info
    if (!isset($topic_tracking_info))
    {
        $topic_tracking_info = array();
    
        // Get topic tracking info
        if ($config['load_db_lastread'] && $user->data['is_registered'])
        {
            $tmp_topic_data = array($topic_id => $topic_data);
            $topic_tracking_info = get_topic_tracking($forum_id, $topic_id, $tmp_topic_data, array($forum_id => $topic_data['forum_mark_time']));
            unset($tmp_topic_data);
        }
        else if ($config['load_anon_lastread'] || $user->data['is_registered'])
        {
            $topic_tracking_info = get_complete_topic_tracking($forum_id, $topic_id);
        }
    }
    
    // Post ordering options
    $limit_days = array(0 => $user->lang['ALL_POSTS'], 1 => $user->lang['1_DAY'], 7 => $user->lang['7_DAYS'], 14 => $user->lang['2_WEEKS'], 30 => $user->lang['1_MONTH'], 90 => $user->lang['3_MONTHS'], 180 => $user->lang['6_MONTHS'], 365 => $user->lang['1_YEAR']);
    
    $sort_by_text = array('a' => $user->lang['AUTHOR'], 't' => $user->lang['POST_TIME'], 's' => $user->lang['SUBJECT']);
    $sort_by_sql = array('a' => 'u.username_clean', 't' => 'p.post_time', 's' => 'p.post_subject');
    
    $s_limit_days = $s_sort_key = $s_sort_dir = $u_sort_param = '';
    
    gen_sort_selects($limit_days, $sort_by_text, $sort_days, $sort_key, $sort_dir, $s_limit_days, $s_sort_key, $s_sort_dir, $u_sort_param, $default_sort_days, $default_sort_key, $default_sort_dir);
    
    // Obtain correct post count and ordering SQL if user has
    // requested anything different
    if ($sort_days)
    {
        $min_post_time = time() - ($sort_days * 86400);
    
        $sql = 'SELECT COUNT(post_id) AS num_posts
            FROM ' . POSTS_TABLE . "
            WHERE topic_id = $topic_id
                AND post_time >= $min_post_time
            " . (($auth->acl_get('m_approve', $forum_id)) ? '' : 'AND post_approved = 1');
        $result = $db->sql_query($sql);
        $total_posts = (int) $db->sql_fetchfield('num_posts');
        $db->sql_freeresult($result);
    
        $limit_posts_time = "AND p.post_time >= $min_post_time ";
    
        if (isset($_POST['sort']))
        {
            $start = 0;
        }
    }
    else
    {
        $total_posts = $topic_replies + 1;
        $limit_posts_time = '';
    }
    
    // Make sure $start is set to the last page if it exceeds the amount
    if ($start < 0 || $start >= $total_posts)
    {
        $start = ($start < 0) ? 0 : floor(($total_posts - 1) / $config['posts_per_page']) * $config['posts_per_page'];
    }
    
    // General Viewtopic URL for return links
    $viewtopic_url = append_sid("{$phpbb_root_path}viewtopic.$phpEx", "f=$forum_id&amp;t=$topic_id&amp;start=$start" . ((strlen($u_sort_param)) ? "&amp;$u_sort_param" : ''));
    
    // Are we watching this topic?
    $s_watching_topic = array(
        'link'          => '',
        'title'         => '',
        'is_watching'   => false,
    );
    
//    if (($config['email_enable'] || $config['jab_enable']) && $config['allow_topic_notify'] && $user->data['is_registered'])
//    {
//        watch_topic_forum('topic', $s_watching_topic, $user->data['user_id'], $forum_id, $topic_id, $topic_data['notify_status'], $start);
//    
//        // Reset forum notification if forum notify is set
//        if ($config['allow_forum_notify'] && $auth->acl_get('f_subscribe', $forum_id))
//        {
//            $s_watching_forum = $s_watching_topic;
//            watch_topic_forum('forum', $s_watching_forum, $user->data['user_id'], $forum_id, 0);
//        }
//    }
//    
    // Grab ranks
    $ranks = $cache->obtain_ranks();
    
    // Grab icons
    $icons = $cache->obtain_icons();
    
    // Grab extensions
    $extensions = array();
    if ($topic_data['topic_attachment'])
    {
        $extensions = $cache->obtain_attach_extensions($forum_id);
    }
    
    // Forum rules listing
    $s_forum_rules = '';
    gen_forum_auth_level('topic', $forum_id, $topic_data['forum_status']);
    
    // Quick mod tools
    $allow_change_type = ($auth->acl_get('m_', $forum_id) || ($user->data['is_registered'] && $user->data['user_id'] == $topic_data['topic_poster'])) ? true : false;
    
//    $topic_mod = '';
//    $topic_mod .= ($auth->acl_get('m_lock', $forum_id) || ($auth->acl_get('f_user_lock', $forum_id) && $user->data['is_registered'] && $user->data['user_id'] == $topic_data['topic_poster'] && $topic_data['topic_status'] == ITEM_UNLOCKED)) ? (($topic_data['topic_status'] == ITEM_UNLOCKED) ? '<option value="lock">' . $user->lang['LOCK_TOPIC'] . '</option>' : '<option value="unlock">' . $user->lang['UNLOCK_TOPIC'] . '</option>') : '';
//    $topic_mod .= ($auth->acl_get('m_delete', $forum_id)) ? '<option value="delete_topic">' . $user->lang['DELETE_TOPIC'] . '</option>' : '';
//    $topic_mod .= ($auth->acl_get('m_move', $forum_id) && $topic_data['topic_status'] != ITEM_MOVED) ? '<option value="move">' . $user->lang['MOVE_TOPIC'] . '</option>' : '';
//    $topic_mod .= ($auth->acl_get('m_split', $forum_id)) ? '<option value="split">' . $user->lang['SPLIT_TOPIC'] . '</option>' : '';
//    $topic_mod .= ($auth->acl_get('m_merge', $forum_id)) ? '<option value="merge">' . $user->lang['MERGE_POSTS'] . '</option>' : '';
//    $topic_mod .= ($auth->acl_get('m_merge', $forum_id)) ? '<option value="merge_topic">' . $user->lang['MERGE_TOPIC'] . '</option>' : '';
//    $topic_mod .= ($auth->acl_get('m_move', $forum_id)) ? '<option value="fork">' . $user->lang['FORK_TOPIC'] . '</option>' : '';
//    $topic_mod .= ($allow_change_type && $auth->acl_gets('f_sticky', 'f_announce', $forum_id) && $topic_data['topic_type'] != POST_NORMAL) ? '<option value="make_normal">' . $user->lang['MAKE_NORMAL'] . '</option>' : '';
//    $topic_mod .= ($allow_change_type && $auth->acl_get('f_sticky', $forum_id) && $topic_data['topic_type'] != POST_STICKY) ? '<option value="make_sticky">' . $user->lang['MAKE_STICKY'] . '</option>' : '';
//    $topic_mod .= ($allow_change_type && $auth->acl_get('f_announce', $forum_id) && $topic_data['topic_type'] != POST_ANNOUNCE) ? '<option value="make_announce">' . $user->lang['MAKE_ANNOUNCE'] . '</option>' : '';
//    $topic_mod .= ($allow_change_type && $auth->acl_get('f_announce', $forum_id) && $topic_data['topic_type'] != POST_GLOBAL) ? '<option value="make_global">' . $user->lang['MAKE_GLOBAL'] . '</option>' : '';
//    $topic_mod .= ($auth->acl_get('m_', $forum_id)) ? '<option value="topic_logs">' . $user->lang['VIEW_TOPIC_LOGS'] . '</option>' : '';
//    
//    // If we've got a hightlight set pass it on to pagination.
//    $pagination = generate_pagination(append_sid("{$phpbb_root_path}viewtopic.$phpEx", "f=$forum_id&amp;t=$topic_id" . ((strlen($u_sort_param)) ? "&amp;$u_sort_param" : '')), $total_posts, $config['posts_per_page'], $start);
//    
//    // Moderators
//    $forum_moderators = array();
//    get_moderators($forum_moderators, $forum_id);
    
    // This is only used for print view so ...
    $server_path = $phpbb_root_path;
    
    // Replace naughty words in title
    $topic_data['topic_title'] = censor_text($topic_data['topic_title']);
    
    // Send vars to template
    $template->assign_vars(array(
        'FORUM_ID'              => $forum_id,
        'FORUM_NAME'            => $topic_data['forum_name'],
        'FORUM_DESC'            => generate_text_for_display($topic_data['forum_desc'], $topic_data['forum_desc_uid'], $topic_data['forum_desc_bitfield'], $topic_data['forum_desc_options']),
        'TOPIC_ID'              => $topic_id,
        'TOPIC_TITLE'           => $topic_data['topic_title'],
        'TOPIC_POSTER'          => $topic_data['topic_poster'],
    
        'TOPIC_AUTHOR_FULL'     => get_username_string('full', $topic_data['topic_poster'], $topic_data['topic_first_poster_name'], $topic_data['topic_first_poster_colour']),
        'TOPIC_AUTHOR_COLOUR'   => get_username_string('colour', $topic_data['topic_poster'], $topic_data['topic_first_poster_name'], $topic_data['topic_first_poster_colour']),
        'TOPIC_AUTHOR'          => get_username_string('username', $topic_data['topic_poster'], $topic_data['topic_first_poster_name'], $topic_data['topic_first_poster_colour']),
    
        //'PAGINATION'            => $pagination,
        'PAGE_NUMBER'           => on_page($total_posts, $config['posts_per_page'], $start),
        'TOTAL_POSTS'           => ($total_posts == 1) ? $user->lang['VIEW_TOPIC_POST'] : sprintf($user->lang['VIEW_TOPIC_POSTS'], $total_posts),
        'U_MCP'                 => ($auth->acl_get('m_', $forum_id)) ? append_sid("{$phpbb_root_path}mcp.$phpEx", "i=main&amp;mode=topic_view&amp;f=$forum_id&amp;t=$topic_id&amp;start=$start" . ((strlen($u_sort_param)) ? "&amp;$u_sort_param" : ''), true, $user->session_id) : '',
        'MODERATORS'            => (isset($forum_moderators[$forum_id]) && sizeof($forum_moderators[$forum_id])) ? implode(', ', $forum_moderators[$forum_id]) : '',
    
        'POST_IMG'              => ($topic_data['forum_status'] == ITEM_LOCKED) ? $user->img('button_topic_locked', 'FORUM_LOCKED') : $user->img('button_topic_new', 'POST_NEW_TOPIC'),
        'QUOTE_IMG'             => $user->img('icon_post_quote', 'REPLY_WITH_QUOTE'),
        'REPLY_IMG'             => ($topic_data['forum_status'] == ITEM_LOCKED || $topic_data['topic_status'] == ITEM_LOCKED) ? $user->img('button_topic_locked', 'TOPIC_LOCKED') : $user->img('button_topic_reply', 'REPLY_TO_TOPIC'),
        'EDIT_IMG'              => $user->img('icon_post_edit', 'EDIT_POST'),
        'DELETE_IMG'            => $user->img('icon_post_delete', 'DELETE_POST'),
        'INFO_IMG'              => $user->img('icon_post_info', 'VIEW_INFO'),
        'PROFILE_IMG'           => $user->img('icon_user_profile', 'READ_PROFILE'),
        'SEARCH_IMG'            => $user->img('icon_user_search', 'SEARCH_USER_POSTS'),
        'PM_IMG'                => $user->img('icon_contact_pm', 'SEND_PRIVATE_MESSAGE'),
        'EMAIL_IMG'             => $user->img('icon_contact_email', 'SEND_EMAIL'),
        'WWW_IMG'               => $user->img('icon_contact_www', 'VISIT_WEBSITE'),
        'ICQ_IMG'               => $user->img('icon_contact_icq', 'ICQ'),
        'AIM_IMG'               => $user->img('icon_contact_aim', 'AIM'),
        'MSN_IMG'               => $user->img('icon_contact_msnm', 'MSNM'),
        'YIM_IMG'               => $user->img('icon_contact_yahoo', 'YIM'),
        'JABBER_IMG'            => $user->img('icon_contact_jabber', 'JABBER') ,
        'REPORT_IMG'            => $user->img('icon_post_report', 'REPORT_POST'),
        'REPORTED_IMG'          => $user->img('icon_topic_reported', 'POST_REPORTED'),
        'UNAPPROVED_IMG'        => $user->img('icon_topic_unapproved', 'POST_UNAPPROVED'),
        'WARN_IMG'              => $user->img('icon_user_warn', 'WARN_USER'),
    
        'S_IS_LOCKED'           =>($topic_data['topic_status'] == ITEM_UNLOCKED) ? false : true,
        'S_SELECT_SORT_DIR'     => $s_sort_dir,
        'S_SELECT_SORT_KEY'     => $s_sort_key,
        'S_SELECT_SORT_DAYS'    => $s_limit_days,
        'S_SINGLE_MODERATOR'    => (!empty($forum_moderators[$forum_id]) && sizeof($forum_moderators[$forum_id]) > 1) ? false : true,
        'S_TOPIC_ACTION'        => append_sid("{$phpbb_root_path}viewtopic.$phpEx", "f=$forum_id&amp;t=$topic_id&amp;start=$start"),
        //'S_TOPIC_MOD'           => ($topic_mod != '') ? '<select name="action" id="quick-mod-select">' . $topic_mod . '</select>' : '',
        'S_MOD_ACTION'          => append_sid("{$phpbb_root_path}mcp.$phpEx", "f=$forum_id&amp;t=$topic_id&amp;quickmod=1&amp;redirect=" . urlencode(str_replace('&amp;', '&', $viewtopic_url)), true, $user->session_id),
    
        'S_VIEWTOPIC'           => true,
        'S_DISPLAY_SEARCHBOX'   => ($auth->acl_get('u_search') && $auth->acl_get('f_search', $forum_id) && $config['load_search']) ? true : false,
        'S_SEARCHBOX_ACTION'    => append_sid("{$phpbb_root_path}search.$phpEx", 't=' . $topic_id),
    
        'S_DISPLAY_POST_INFO'   => ($topic_data['forum_type'] == FORUM_POST && ($auth->acl_get('f_post', $forum_id) || $user->data['user_id'] == ANONYMOUS)) ? true : false,
        'S_DISPLAY_REPLY_INFO'  => ($topic_data['forum_type'] == FORUM_POST && ($auth->acl_get('f_reply', $forum_id) || $user->data['user_id'] == ANONYMOUS)) ? true : false,
    
        'U_TOPIC'               => "{$server_path}viewtopic.$phpEx?f=$forum_id&amp;t=$topic_id",
        'U_FORUM'               => $server_path,
        'U_VIEW_TOPIC'          => $viewtopic_url,
        'U_VIEW_FORUM'          => append_sid("{$phpbb_root_path}viewforum.$phpEx", 'f=' . $forum_id),
        'U_VIEW_OLDER_TOPIC'    => append_sid("{$phpbb_root_path}viewtopic.$phpEx", "f=$forum_id&amp;t=$topic_id&amp;view=previous"),
        'U_VIEW_NEWER_TOPIC'    => append_sid("{$phpbb_root_path}viewtopic.$phpEx", "f=$forum_id&amp;t=$topic_id&amp;view=next"),
        'U_PRINT_TOPIC'         => ($auth->acl_get('f_print', $forum_id)) ? $viewtopic_url . '&amp;view=print' : '',
        'U_EMAIL_TOPIC'         => ($auth->acl_get('f_email', $forum_id) && $config['email_enable']) ? append_sid("{$phpbb_root_path}memberlist.$phpEx", "mode=email&amp;t=$topic_id") : '',
    
        'U_WATCH_TOPIC'         => $s_watching_topic['link'],
        'L_WATCH_TOPIC'         => $s_watching_topic['title'],
        'S_WATCHING_TOPIC'      => $s_watching_topic['is_watching'],
    
       
        'L_BOOKMARK_TOPIC'      => ($user->data['is_registered'] && $config['allow_bookmarks'] && $topic_data['bookmarked']) ? $user->lang['BOOKMARK_TOPIC_REMOVE'] : $user->lang['BOOKMARK_TOPIC'],
    
        'U_POST_NEW_TOPIC'      => ($auth->acl_get('f_post', $forum_id) || $user->data['user_id'] == ANONYMOUS) ? append_sid("{$phpbb_root_path}posting.$phpEx", "mode=post&amp;f=$forum_id") : '',
        'U_POST_REPLY_TOPIC'    => ($auth->acl_get('f_reply', $forum_id) || $user->data['user_id'] == ANONYMOUS) ? append_sid("{$phpbb_root_path}posting.$phpEx", "mode=reply&amp;f=$forum_id&amp;t=$topic_id") : '',
      )
    );
    
    // Does this topic contain a poll?
    if (!empty($topic_data['poll_start']))
    {
        $sql = 'SELECT o.*, p.bbcode_bitfield, p.bbcode_uid
            FROM ' . POLL_OPTIONS_TABLE . ' o, ' . POSTS_TABLE . " p
            WHERE o.topic_id = $topic_id
                AND p.post_id = {$topic_data['topic_first_post_id']}
                AND p.topic_id = o.topic_id
            ORDER BY o.poll_option_id";
        $result = $db->sql_query($sql);
    
        $poll_info = array();
        while ($row = $db->sql_fetchrow($result))
        {
            $poll_info[] = $row;
        }
        $db->sql_freeresult($result);
    
        $cur_voted_id = array();
        if ($user->data['is_registered'])
        {
            $sql = 'SELECT poll_option_id
                FROM ' . POLL_VOTES_TABLE . '
                WHERE topic_id = ' . $topic_id . '
                    AND vote_user_id = ' . $user->data['user_id'];
            $result = $db->sql_query($sql);
    
            while ($row = $db->sql_fetchrow($result))
            {
                $cur_voted_id[] = $row['poll_option_id'];
            }
            $db->sql_freeresult($result);
        }
        else
        {
            // Cookie based guest tracking ... I don't like this but hum ho
            // it's oft requested. This relies on "nice" users who don't feel
            // the need to delete cookies to mess with results.
            if (isset($_COOKIE[$config['cookie_name'] . '_poll_' . $topic_id]))
            {
                $cur_voted_id = explode(',', $_COOKIE[$config['cookie_name'] . '_poll_' . $topic_id]);
                $cur_voted_id = array_map('intval', $cur_voted_id);
            }
        }
    
        $s_can_vote = (((!sizeof($cur_voted_id) && $auth->acl_get('f_vote', $forum_id)) ||
            ($auth->acl_get('f_votechg', $forum_id) && $topic_data['poll_vote_change'])) &&
            (($topic_data['poll_length'] != 0 && $topic_data['poll_start'] + $topic_data['poll_length'] > time()) || $topic_data['poll_length'] == 0) &&
            $topic_data['topic_status'] != ITEM_LOCKED &&
            $topic_data['forum_status'] != ITEM_LOCKED) ? true : false;
        $s_display_results = (!$s_can_vote || ($s_can_vote && sizeof($cur_voted_id))) ? true : false;
    
        $poll_total = 0;
        foreach ($poll_info as $poll_option)
        {
            $poll_total += $poll_option['poll_option_total'];
        }
    
        if ($poll_info[0]['bbcode_bitfield'])
        {
            $poll_bbcode = new bbcode();
        }
        else
        {
            $poll_bbcode = false;
        }
    
        for ($i = 0, $size = sizeof($poll_info); $i < $size; $i++)
        {
            $poll_info[$i]['poll_option_text'] = censor_text($poll_info[$i]['poll_option_text']);
    
            if ($poll_bbcode !== false)
            {
                $poll_bbcode->bbcode_second_pass($poll_info[$i]['poll_option_text'], $poll_info[$i]['bbcode_uid'], $poll_option['bbcode_bitfield']);
            }
    
            $poll_info[$i]['poll_option_text'] = bbcode_nl2br($poll_info[$i]['poll_option_text']);
            $poll_info[$i]['poll_option_text'] = smiley_text($poll_info[$i]['poll_option_text']);
        }
    
        $topic_data['poll_title'] = censor_text($topic_data['poll_title']);
    
        if ($poll_bbcode !== false)
        {
            $poll_bbcode->bbcode_second_pass($topic_data['poll_title'], $poll_info[0]['bbcode_uid'], $poll_info[0]['bbcode_bitfield']);
        }
    
        $topic_data['poll_title'] = bbcode_nl2br($topic_data['poll_title']);
        $topic_data['poll_title'] = smiley_text($topic_data['poll_title']);
    
        unset($poll_bbcode);
    
        foreach ($poll_info as $poll_option)
        {
            $option_pct = ($poll_total > 0) ? $poll_option['poll_option_total'] / $poll_total : 0;
            $option_pct_txt = sprintf("%.1d%%", round($option_pct * 100));
    
            $template->assign_block_vars('poll_option', array(
                'POLL_OPTION_ID'         => $poll_option['poll_option_id'],
                'POLL_OPTION_CAPTION'     => $poll_option['poll_option_text'],
                'POLL_OPTION_RESULT'     => $poll_option['poll_option_total'],
                'POLL_OPTION_PERCENT'     => $option_pct_txt,
                'POLL_OPTION_PCT'        => round($option_pct * 100),
                'POLL_OPTION_IMG'         => $user->img('poll_center', $option_pct_txt, round($option_pct * 250)),
                'POLL_OPTION_VOTED'        => (in_array($poll_option['poll_option_id'], $cur_voted_id)) ? true : false)
            );
        }
    
        $poll_end = $topic_data['poll_length'] + $topic_data['poll_start'];
    
        $template->assign_vars(array(
            'POLL_QUESTION'         => $topic_data['poll_title'],
            'TOTAL_VOTES'           => $poll_total,
            'POLL_LEFT_CAP_IMG'     => $user->img('poll_left'),
            'POLL_RIGHT_CAP_IMG'    => $user->img('poll_right'),
    
            'L_MAX_VOTES'           => ($topic_data['poll_max_options'] == 1) ? $user->lang['MAX_OPTION_SELECT'] : sprintf($user->lang['MAX_OPTIONS_SELECT'], $topic_data['poll_max_options']),
            'L_POLL_LENGTH'         => ($topic_data['poll_length']) ? sprintf($user->lang[($poll_end > time()) ? 'POLL_RUN_TILL' : 'POLL_ENDED_AT'], $user->format_date($poll_end)) : '',
    
            'S_HAS_POLL'            => true,
            'S_CAN_VOTE'            => $s_can_vote,
            'S_DISPLAY_RESULTS'     => $s_display_results,
            'S_IS_MULTI_CHOICE'     => ($topic_data['poll_max_options'] > 1) ? true : false,
            'S_POLL_ACTION'         => $viewtopic_url,
    
            'U_VIEW_RESULTS'        => $viewtopic_url . '&amp;view=viewpoll')
        );
    
        unset($poll_end, $poll_info, $voted_id);
    }
    
    // If the user is trying to reach the second half of the topic, fetch it starting from the end
    $store_reverse = false;
    $sql_limit = $config['posts_per_page'];
    
    if ($start > $total_posts / 2)
    {
        $store_reverse = true;
    
        if ($start + $config['posts_per_page'] > $total_posts)
        {
            $sql_limit = min($config['posts_per_page'], max(1, $total_posts - $start));
        }
    
        // Select the sort order
        $sql_sort_order = $sort_by_sql[$sort_key] . ' ' . (($sort_dir == 'd') ? 'ASC' : 'DESC');
        $sql_start = max(0, $total_posts - $sql_limit - $start);
    }
    else
    {
        // Select the sort order
        $sql_sort_order = $sort_by_sql[$sort_key] . ' ' . (($sort_dir == 'd') ? 'DESC' : 'ASC');
        $sql_start = $start;
    }
    
    // Container for user details, only process once
    $post_list = $user_cache = $id_cache = $attachments = $attach_list = $rowset = $update_count = $post_edit_list = array();
    $has_attachments = $display_notice = false;
    $bbcode_bitfield = '';
    $i = $i_total = 0;
    
    // Go ahead and pull all data for this topic
    $sql = 'SELECT p.post_id
        FROM ' . POSTS_TABLE . ' p' . (($sort_by_sql[$sort_key][0] == 'u') ? ', ' . USERS_TABLE . ' u': '') . "
        WHERE p.topic_id = $topic_id
            " . ((!$auth->acl_get('m_approve', $forum_id)) ? 'AND p.post_approved = 1' : '') . "
            " . (($sort_by_sql[$sort_key][0] == 'u') ? 'AND u.user_id = p.poster_id': '') . "
            $limit_posts_time
        ORDER BY $sql_sort_order";
    $result = $db->sql_query_limit($sql, $sql_limit, $sql_start);
    
    $i = ($store_reverse) ? $sql_limit - 1 : 0;
    while ($row = $db->sql_fetchrow($result))
    {
        $post_list[$i] = $row['post_id'];
        ($store_reverse) ? $i-- : $i++;
    }
    $db->sql_freeresult($result);
    
    if (!sizeof($post_list))
    {
//        if ($sort_days)
//        {
//            trigger_error('NO_POSTS_TIME_FRAME');
//        }
//        else
//        {
//            trigger_error('NO_TOPIC');
//        }
        return get_error();
    }
    
    // Holding maximum post time for marking topic read
    // We need to grab it because we do reverse ordering sometimes
    $max_post_time = 0;
    
    $sql = $db->sql_build_query('SELECT', array(
        'SELECT'    => 'u.*, z.friend, z.foe, p.*',
    
        'FROM'      => array(
            USERS_TABLE => 'u',
            POSTS_TABLE => 'p',
        ),
    
        'LEFT_JOIN' => array(
            array(
                'FROM'  => array(ZEBRA_TABLE => 'z'),
                'ON'    => 'z.user_id = ' . $user->data['user_id'] . ' AND z.zebra_id = p.poster_id'
            )
        ),
    
        'WHERE' => $db->sql_in_set('p.post_id', $post_list) . '
        AND u.user_id = p.poster_id'
    ));
    
    $result = $db->sql_query($sql);
    
    $now = getdate(time() + $user->timezone + $user->dst - date('Z'));
    
    // Posts are stored in the $rowset array while $attach_list, $user_cache
    // and the global bbcode_bitfield are built
    while ($row = $db->sql_fetchrow($result))
    {
        // Set max_post_time
        if ($row['post_time'] > $max_post_time)
        {
            $max_post_time = $row['post_time'];
        }
    
        $poster_id = $row['poster_id'];
    
        // Does post have an attachment? If so, add it to the list
        if ($row['post_attachment'] && $config['allow_attachments'])
        {
            $attach_list[] = $row['post_id'];
    
            if ($row['post_approved'])
            {
                $has_attachments = true;
            }
        }
    
        $rowset[$row['post_id']] = array(
            'hide_post'         => $row['foe'] ? true : false,
            'post_id'           => $row['post_id'],
            'post_time'         => $row['post_time'],
            'user_id'           => $row['user_id'],
            'username'          => $row['username'],
            'user_colour'       => $row['user_colour'],
            'topic_id'          => $row['topic_id'],
            'forum_id'          => $row['forum_id'],
            'post_subject'      => $row['post_subject'],
            'post_edit_count'   => $row['post_edit_count'],
            'post_edit_time'    => $row['post_edit_time'],
            'post_edit_reason'  => $row['post_edit_reason'],
            'post_edit_user'    => $row['post_edit_user'],
            'post_edit_locked'  => $row['post_edit_locked'],
    
            // Make sure the icon actually exists
            'icon_id'           => (isset($icons[$row['icon_id']]['img'], $icons[$row['icon_id']]['height'], $icons[$row['icon_id']]['width'])) ? $row['icon_id'] : 0,
            'post_attachment'   => $row['post_attachment'],
            'post_approved'     => $row['post_approved'],
            'post_reported'     => $row['post_reported'],
            'post_username'     => $row['post_username'],
            'post_text'         => $row['post_text'],
            'bbcode_uid'        => $row['bbcode_uid'],
            'bbcode_bitfield'   => $row['bbcode_bitfield'],
            'enable_smilies'    => $row['enable_smilies'],
            'enable_sig'        => $row['enable_sig'],
            'friend'            => $row['friend'],
            'foe'               => $row['foe'],
        );
    
        // Define the global bbcode bitfield, will be used to load bbcodes
        $bbcode_bitfield = $bbcode_bitfield | base64_decode($row['bbcode_bitfield']);
    
        // Is a signature attached? Are we going to display it?
        if ($row['enable_sig'] && $config['allow_sig'] && $user->optionget('viewsigs'))
        {
            $bbcode_bitfield = $bbcode_bitfield | base64_decode($row['user_sig_bbcode_bitfield']);
        }
    
        // Cache various user specific data ... so we don't have to recompute
        // this each time the same user appears on this page
        if (!isset($user_cache[$poster_id]))
        {
            if ($poster_id == ANONYMOUS)
            {
                $user_cache[$poster_id] = array(
                    'joined'    => '',
                    'posts'     => '',
                    'from'      => '',
    
                    'sig'       => '',
                    'sig_bbcode_uid'        => '',
                    'sig_bbcode_bitfield'   => '',
    
                    'online'            => false,
                    'avatar'            => ($user->optionget('viewavatars')) ? get_user_avatar_url($row['user_avatar'], $row['user_avatar_type']) : '',
                    'rank_title'        => '',
                    'rank_image'        => '',
                    'rank_image_src'    => '',
                    'sig'               => '',
                    'profile'           => '',
                    'pm'                => '',
                    'email'             => '',
                    'www'               => '',
                    'icq_status_img'    => '',
                    'icq'               => '',
                    'aim'               => '',
                    'msn'               => '',
                    'yim'               => '',
                    'jabber'            => '',
                    'search'            => '',
                    'age'               => '',
    
                    'username'          => $row['username'],
                    'user_colour'       => $row['user_colour'],
    
                    'warnings'          => 0,
                    'allow_pm'          => 0,
                );
    
                //get_user_rank($row['user_rank'], false, $user_cache[$poster_id]['rank_title'], $user_cache[$poster_id]['rank_image'], $user_cache[$poster_id]['rank_image_src']);
            }
            else
            {
                $user_sig = '';
    
                // We add the signature to every posters entry because enable_sig is post dependant
                if ($row['user_sig'] && $config['allow_sig'] && $user->optionget('viewsigs'))
                {
                    $user_sig = $row['user_sig'];
                }
    
                $id_cache[] = $poster_id;
    
                $user_cache[$poster_id] = array(
                    'joined'    => $user->format_date($row['user_regdate']),
                    'posts'     => $row['user_posts'],
                    'warnings'  => (isset($row['user_warnings'])) ? $row['user_warnings'] : 0,
                    'from'      => (!empty($row['user_from'])) ? $row['user_from'] : '',
    
                    'sig'       => $user_sig,
                    'sig_bbcode_uid'        => (!empty($row['user_sig_bbcode_uid'])) ? $row['user_sig_bbcode_uid'] : '',
                    'sig_bbcode_bitfield'   => (!empty($row['user_sig_bbcode_bitfield'])) ? $row['user_sig_bbcode_bitfield'] : '',
    
                    'viewonline'        => $row['user_allow_viewonline'],
                    'allow_pm'          => $row['user_allow_pm'],
    
                    'avatar'            => ($user->optionget('viewavatars')) ? get_user_avatar_url($row['user_avatar'], $row['user_avatar_type']) : '',
                    'age'               => '',
    
                    'rank_title'        => '',
                    'rank_image'        => '',
                    'rank_image_src'    => '',
    
                    'username'      => $row['username'],
                    'user_colour'   => $row['user_colour'],
    
                    'online'    => false,
                    'profile'   => append_sid("{$phpbb_root_path}memberlist.$phpEx", "mode=viewprofile&amp;u=$poster_id"),
                    'www'       => $row['user_website'],
                    'aim'       => ($row['user_aim'] && $auth->acl_get('u_sendim')) ? append_sid("{$phpbb_root_path}memberlist.$phpEx", "mode=contact&amp;action=aim&amp;u=$poster_id") : '',
                    'msn'       => ($row['user_msnm'] && $auth->acl_get('u_sendim')) ? append_sid("{$phpbb_root_path}memberlist.$phpEx", "mode=contact&amp;action=msnm&amp;u=$poster_id") : '',
                    'yim'       => ($row['user_yim']) ? 'http://edit.yahoo.com/config/send_webmesg?.target=' . urlencode($row['user_yim']) . '&amp;.src=pg' : '',
                    'jabber'    => ($row['user_jabber'] && $auth->acl_get('u_sendim')) ? append_sid("{$phpbb_root_path}memberlist.$phpEx", "mode=contact&amp;action=jabber&amp;u=$poster_id") : '',
                    'search'    => ($auth->acl_get('u_search')) ? append_sid("{$phpbb_root_path}search.$phpEx", "author_id=$poster_id&amp;sr=posts") : '',
                );
    
                //get_user_rank($row['user_rank'], $row['user_posts'], $user_cache[$poster_id]['rank_title'], $user_cache[$poster_id]['rank_image'], $user_cache[$poster_id]['rank_image_src']);
    
                if (!empty($row['user_allow_viewemail']) || $auth->acl_get('a_email'))
                {
                    $user_cache[$poster_id]['email'] = ($config['board_email_form'] && $config['email_enable']) ? append_sid("{$phpbb_root_path}memberlist.$phpEx", "mode=email&amp;u=$poster_id") : (($config['board_hide_emails'] && !$auth->acl_get('a_email')) ? '' : 'mailto:' . $row['user_email']);
                }
                else
                {
                    $user_cache[$poster_id]['email'] = '';
                }
    
                if (!empty($row['user_icq']))
                {
                    $user_cache[$poster_id]['icq'] = 'http://www.icq.com/people/webmsg.php?to=' . $row['user_icq'];
                    $user_cache[$poster_id]['icq_status_img'] = '<img src="http://web.icq.com/whitepages/online?icq=' . $row['user_icq'] . '&amp;img=5" width="18" height="18" alt="" />';
                }
                else
                {
                    $user_cache[$poster_id]['icq_status_img'] = '';
                    $user_cache[$poster_id]['icq'] = '';
                }
    
                if ($config['allow_birthdays'] && !empty($row['user_birthday']))
                {
                    list($bday_day, $bday_month, $bday_year) = array_map('intval', explode('-', $row['user_birthday']));
    
                    if ($bday_year)
                    {
                        $diff = $now['mon'] - $bday_month;
                        if ($diff == 0)
                        {
                            $diff = ($now['mday'] - $bday_day < 0) ? 1 : 0;
                        }
                        else
                        {
                            $diff = ($diff < 0) ? 1 : 0;
                        }
    
                        $user_cache[$poster_id]['age'] = (int) ($now['year'] - $bday_year - $diff);
                    }
                }
            }
        }
    }
    $db->sql_freeresult($result);
    
    // Load custom profile fields
    if ($config['load_cpf_viewtopic'])
    {
        include($phpbb_root_path . 'includes/functions_profile_fields.' . $phpEx);
        $cp = new custom_profile();
    
        // Grab all profile fields from users in id cache for later use - similar to the poster cache
        $profile_fields_cache = $cp->generate_profile_fields_template('grab', $id_cache);
    }
        
    // Generate online information for user
    if ($config['load_onlinetrack'] && sizeof($id_cache))
    {
        $sql = 'SELECT session_user_id, MAX(session_time) as online_time, MIN(session_viewonline) AS viewonline
                FROM ' . SESSIONS_TABLE . '
                WHERE ' . $db->sql_in_set('session_user_id', $id_cache) . '
                GROUP BY session_user_id';
        $result = $db->sql_query($sql);
    
        $update_time = $config['load_online_time'] * 60;
        while ($row = $db->sql_fetchrow($result))
        {
            $user_cache[$row['session_user_id']]['online'] = (time() - $update_time < $row['online_time'] && (($row['viewonline']) || $auth->acl_get('u_viewonline'))) ? true : false;
        }
        $db->sql_freeresult($result);
    }
    unset($id_cache);
    
    // add for mobiquo
    $attachment_by_id = array();
    
    // Pull attachment data
    if (sizeof($attach_list))
    {
        if ($auth->acl_get('u_download') && $auth->acl_get('f_download', $forum_id))
        {
            $sql = 'SELECT *
                FROM ' . ATTACHMENTS_TABLE . '
                WHERE ' . $db->sql_in_set('post_msg_id', $attach_list) . '
                    AND in_message = 0
                ORDER BY filetime DESC, post_msg_id ASC';
            $result = $db->sql_query($sql);
    
            while ($row = $db->sql_fetchrow($result))
            {
                $attachments[$row['post_msg_id']][] = $row;
                // add for moboquo
                $attachment_by_id[$row['attach_id']] = $row;
            }
            $db->sql_freeresult($result);
    
            // No attachments exist, but post table thinks they do so go ahead and reset post_attach flags
            if (!sizeof($attachments))
            {
                $sql = 'UPDATE ' . POSTS_TABLE . '
                    SET post_attachment = 0
                    WHERE ' . $db->sql_in_set('post_id', $attach_list);
                $db->sql_query($sql);
    
                // We need to update the topic indicator too if the complete topic is now without an attachment
                if (sizeof($rowset) != $total_posts)
                {
                    // Not all posts are displayed so we query the db to find if there's any attachment for this topic
                    $sql = 'SELECT a.post_msg_id as post_id
                        FROM ' . ATTACHMENTS_TABLE . ' a, ' . POSTS_TABLE . " p
                        WHERE p.topic_id = $topic_id
                            AND p.post_approved = 1
                            AND p.topic_id = a.topic_id";
                    $result = $db->sql_query_limit($sql, 1);
                    $row = $db->sql_fetchrow($result);
                    $db->sql_freeresult($result);
    
                    if (!$row)
                    {
                        $sql = 'UPDATE ' . TOPICS_TABLE . "
                            SET topic_attachment = 0
                            WHERE topic_id = $topic_id";
                        $db->sql_query($sql);
                    }
                }
                else
                {
                    $sql = 'UPDATE ' . TOPICS_TABLE . "
                        SET topic_attachment = 0
                        WHERE topic_id = $topic_id";
                    $db->sql_query($sql);
                }
            }
            else if ($has_attachments && !$topic_data['topic_attachment'])
            {
                // Topic has approved attachments but its flag is wrong
                $sql = 'UPDATE ' . TOPICS_TABLE . "
                    SET topic_attachment = 1
                    WHERE topic_id = $topic_id";
                $db->sql_query($sql);
    
                $topic_data['topic_attachment'] = 1;
            }
        }
        else
        {
            $display_notice = true;
        }
    }
    
    // Instantiate BBCode if need be
    if ($bbcode_bitfield !== '')
    {
        $bbcode = new bbcode(base64_encode($bbcode_bitfield));
    }
    
    $i_total = sizeof($rowset) - 1;
    $prev_post_id = '';
    
    $template->assign_vars(array(
        'S_NUM_POSTS' => sizeof($post_list))
    );
    
    // Output the posts
    $first_unread = $post_unread = false;
    for ($i = 0, $end = sizeof($post_list); $i < $end; ++$i)
    {
        // A non-existing rowset only happens if there was no user present for the entered poster_id
        // This could be a broken posts table.
        if (!isset($rowset[$post_list[$i]]))
        {
            continue;
        }
    
        $row =& $rowset[$post_list[$i]];
        $poster_id = $row['user_id'];
    
        // End signature parsing, only if needed
        if ($user_cache[$poster_id]['sig'] && $row['enable_sig'] && empty($user_cache[$poster_id]['sig_parsed']))
        {
            $user_cache[$poster_id]['sig'] = censor_text($user_cache[$poster_id]['sig']);
    
            if ($user_cache[$poster_id]['sig_bbcode_bitfield'])
            {
                $bbcode->bbcode_second_pass($user_cache[$poster_id]['sig'], $user_cache[$poster_id]['sig_bbcode_uid'], $user_cache[$poster_id]['sig_bbcode_bitfield']);
            }
    
            $user_cache[$poster_id]['sig'] = bbcode_nl2br($user_cache[$poster_id]['sig']);
            $user_cache[$poster_id]['sig'] = smiley_text($user_cache[$poster_id]['sig']);
            $user_cache[$poster_id]['sig_parsed'] = true;
        }
    
        // Parse the message and subject
        $message = censor_text($row['post_text']);
        
        // =============== add for quote issue
        $quote_wrote_string = $user->lang['WROTE'];
        $message = str_replace('[/quote:'.$row['bbcode_uid'].']', '[/quote]', $message);
        $message = preg_replace('/\[quote(?:=&quot;(.*?)&quot;)?:'.$row['bbcode_uid'].'\]/ise', "'[quote]' . ('$1' ? '$1' . ' $quote_wrote_string:\n' : '\n')", $message);
        $blocks = preg_split('/(\[\/?quote\])/i', $message, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
        
        $quote_level = 0;
        $message = '';
            
        foreach($blocks as $block)
        {
            if ($block == '[quote]') {
                if ($quote_level == 0) $message .= $block;
                $quote_level++;
            } else if ($block == '[/quote]') {
                if ($quote_level <= 1) $message .= $block;
                if ($quote_level >= 1) $quote_level--;            
            } else {
                if ($quote_level <= 1) $message .= $block;
            }
        }
        //================ add for quote issue
        
        // video bbcode encode
        
        $message = preg_replace('/\[(youtube|video|googlevideo|gvideo):'.$row['bbcode_uid'].'\](.*?)\[\/\1:'.$row['bbcode_uid'].'\]/sie', "video_bbcode_format('$1', '$2')", $message);
        
        // Second parse bbcode here
        if ($row['bbcode_bitfield'])
        {
            $bbcode->bbcode_second_pass($message, $row['bbcode_uid'], $row['bbcode_bitfield']);
        }
        
        $message = bbcode_nl2br($message);
        $message = smiley_text($message);
        
        if (!empty($attachments[$row['post_id']]))
        {
            parse_attachments($forum_id, $message, $attachments[$row['post_id']], $update_count);
        }
        
        // Replace naughty words such as farty pants
        $row['post_subject'] = censor_text($row['post_subject']);
    
        // Editing information
        if (($row['post_edit_count'] && $config['display_last_edited']) || $row['post_edit_reason'])
        {
            // Get usernames for all following posts if not already stored
            if (!sizeof($post_edit_list) && ($row['post_edit_reason'] || ($row['post_edit_user'] && !isset($user_cache[$row['post_edit_user']]))))
            {
                // Remove all post_ids already parsed (we do not have to check them)
                $post_storage_list = (!$store_reverse) ? array_slice($post_list, $i) : array_slice(array_reverse($post_list), $i);
    
                $sql = 'SELECT DISTINCT u.user_id, u.username, u.user_colour
                    FROM ' . POSTS_TABLE . ' p, ' . USERS_TABLE . ' u
                    WHERE ' . $db->sql_in_set('p.post_id', $post_storage_list) . '
                        AND p.post_edit_count <> 0
                        AND p.post_edit_user <> 0
                        AND p.post_edit_user = u.user_id';
                $result2 = $db->sql_query($sql);
                while ($user_edit_row = $db->sql_fetchrow($result2))
                {
                    $post_edit_list[$user_edit_row['user_id']] = $user_edit_row;
                }
                $db->sql_freeresult($result2);
    
                unset($post_storage_list);
            }
    
            $l_edit_time_total = ($row['post_edit_count'] == 1) ? $user->lang['EDITED_TIME_TOTAL'] : $user->lang['EDITED_TIMES_TOTAL'];
    
            if ($row['post_edit_reason'])
            {
                // User having edited the post also being the post author?
                if (!$row['post_edit_user'] || $row['post_edit_user'] == $poster_id)
                {
                    $display_username = get_username_string('full', $poster_id, $row['username'], $row['user_colour'], $row['post_username']);
                }
                else
                {
                    $display_username = get_username_string('full', $row['post_edit_user'], $post_edit_list[$row['post_edit_user']]['username'], $post_edit_list[$row['post_edit_user']]['user_colour']);
                }
    
                $l_edited_by = sprintf($l_edit_time_total, $display_username, $user->format_date($row['post_edit_time'], false, true), $row['post_edit_count']);
            }
            else
            {
                if ($row['post_edit_user'] && !isset($user_cache[$row['post_edit_user']]))
                {
                    $user_cache[$row['post_edit_user']] = $post_edit_list[$row['post_edit_user']];
                }
    
                // User having edited the post also being the post author?
                if (!$row['post_edit_user'] || $row['post_edit_user'] == $poster_id)
                {
                    $display_username = get_username_string('full', $poster_id, $row['username'], $row['user_colour'], $row['post_username']);
                }
                else
                {
                    $display_username = get_username_string('full', $row['post_edit_user'], $user_cache[$row['post_edit_user']]['username'], $user_cache[$row['post_edit_user']]['user_colour']);
                }
    
                $l_edited_by = sprintf($l_edit_time_total, $display_username, $user->format_date($row['post_edit_time'], false, true), $row['post_edit_count']);
            }
        }
        else
        {
            $l_edited_by = '';
        }
    
        // Bump information
        if ($topic_data['topic_bumped'] && $row['post_id'] == $topic_data['topic_last_post_id'] && isset($user_cache[$topic_data['topic_bumper']]) )
        {
            // It is safe to grab the username from the user cache array, we are at the last
            // post and only the topic poster and last poster are allowed to bump.
            // Admins and mods are bound to the above rules too...
            $l_bumped_by = '<br /><br />' . sprintf($user->lang['BUMPED_BY'], $user_cache[$topic_data['topic_bumper']]['username'], $user->format_date($topic_data['topic_last_post_time']));
        }
        else
        {
            $l_bumped_by = '';
        }
    
        $cp_row = array();
    
        //
        if ($config['load_cpf_viewtopic'])
        {
            $cp_row = (isset($profile_fields_cache[$poster_id])) ? $cp->generate_profile_fields_template('show', false, $profile_fields_cache[$poster_id]) : array();
        }
    
        $post_unread = (isset($topic_tracking_info[$topic_id]) && $row['post_time'] > $topic_tracking_info[$topic_id]) ? true : false;
    
        $s_first_unread = false;
        if (!$first_unread && $post_unread)
        {
            $s_first_unread = $first_unread = true;
        }
        
        $postrow = array(
            'enable_smilies'    => $row['enable_smilies'],
            
            'POST_AUTHOR_FULL'        => get_username_string('full', $poster_id, $row['username'], $row['user_colour'], $row['post_username']),
            'POST_AUTHOR_COLOUR'    => get_username_string('colour', $poster_id, $row['username'], $row['user_colour'], $row['post_username']),
            'POST_AUTHOR'            => get_username_string('username', $poster_id, $row['username'], $row['user_colour'], $row['post_username']),
            'U_POST_AUTHOR'            => get_username_string('profile', $poster_id, $row['username'], $row['user_colour'], $row['post_username']),
    
            'RANK_TITLE'        => $user_cache[$poster_id]['rank_title'],
            'RANK_IMG'            => $user_cache[$poster_id]['rank_image'],
            'RANK_IMG_SRC'        => $user_cache[$poster_id]['rank_image_src'],
            'POSTER_JOINED'        => $user_cache[$poster_id]['joined'],
            'POSTER_POSTS'        => $user_cache[$poster_id]['posts'],
            'POSTER_FROM'        => $user_cache[$poster_id]['from'],
            'POSTER_AVATAR'        => $user_cache[$poster_id]['avatar'],
            'POSTER_WARNINGS'    => $user_cache[$poster_id]['warnings'],
            'POSTER_AGE'        => $user_cache[$poster_id]['age'],
    
            'POST_DATE'         => $row['post_time'],
//            'POST_DATE'            => $user->format_date($row['post_time']),
            'POST_SUBJECT'        => $row['post_subject'],
            'MESSAGE'            => $message,
            'SIGNATURE'            => ($row['enable_sig']) ? $user_cache[$poster_id]['sig'] : '',
            'EDITED_MESSAGE'    => $l_edited_by,
            'EDIT_REASON'        => $row['post_edit_reason'],
            'BUMPED_MESSAGE'    => $l_bumped_by,
    
            'MINI_POST_IMG'            => ($post_unread) ? $user->img('icon_post_target_unread', 'NEW_POST') : $user->img('icon_post_target', 'POST'),
            'POST_ICON_IMG'            => ($topic_data['enable_icons'] && !empty($row['icon_id'])) ? $icons[$row['icon_id']]['img'] : '',
            'POST_ICON_IMG_WIDTH'    => ($topic_data['enable_icons'] && !empty($row['icon_id'])) ? $icons[$row['icon_id']]['width'] : '',
            'POST_ICON_IMG_HEIGHT'    => ($topic_data['enable_icons'] && !empty($row['icon_id'])) ? $icons[$row['icon_id']]['height'] : '',
            'ICQ_STATUS_IMG'        => $user_cache[$poster_id]['icq_status_img'],
            'ONLINE_IMG'            => ($poster_id == ANONYMOUS || !$config['load_onlinetrack']) ? '' : (($user_cache[$poster_id]['online']) ? $user->img('icon_user_online', 'ONLINE') : $user->img('icon_user_offline', 'OFFLINE')),
            'S_ONLINE'                => ($poster_id == ANONYMOUS || !$config['load_onlinetrack']) ? false : (($user_cache[$poster_id]['online']) ? true : false),
    
            'U_EDIT'            => (!$user->data['is_registered']) ? '' : ((($user->data['user_id'] == $poster_id && $auth->acl_get('f_edit', $forum_id) && ($row['post_time'] > time() - ($config['edit_time'] * 60) || !$config['edit_time'])) || $auth->acl_get('m_edit', $forum_id)) ? append_sid("{$phpbb_root_path}posting.$phpEx", "mode=edit&amp;f=$forum_id&amp;p={$row['post_id']}") : ''),
            'U_QUOTE'            => ($auth->acl_get('f_reply', $forum_id)) ? append_sid("{$phpbb_root_path}posting.$phpEx", "mode=quote&amp;f=$forum_id&amp;p={$row['post_id']}") : '',
            'U_INFO'            => ($auth->acl_get('m_info', $forum_id)) ? append_sid("{$phpbb_root_path}mcp.$phpEx", "i=main&amp;mode=post_details&amp;f=$forum_id&amp;p=" . $row['post_id'], true, $user->session_id) : '',
            'U_DELETE'            => (!$user->data['is_registered']) ? '' : ((($user->data['user_id'] == $poster_id && $auth->acl_get('f_delete', $forum_id) && $topic_data['topic_last_post_id'] == $row['post_id'] && !$row['post_edit_locked'] && ($row['post_time'] > time() - ($config['edit_time'] * 60) || !$config['edit_time'])) || $auth->acl_get('m_delete', $forum_id)) ? append_sid("{$phpbb_root_path}posting.$phpEx", "mode=delete&amp;f=$forum_id&amp;p={$row['post_id']}") : ''),
            'post_edit_locked'  => $row['post_edit_locked'],
    
            'U_PROFILE'        => $user_cache[$poster_id]['profile'],
            'U_SEARCH'        => $user_cache[$poster_id]['search'],
            'U_PM'            => ($poster_id != ANONYMOUS && $config['allow_privmsg'] && $auth->acl_get('u_sendpm') && ($user_cache[$poster_id]['allow_pm'] || $auth->acl_gets('a_', 'm_') || $auth->acl_getf_global('m_'))) ? append_sid("{$phpbb_root_path}ucp.$phpEx", 'i=pm&amp;mode=compose&amp;action=quotepost&amp;p=' . $row['post_id']) : '',
            'U_EMAIL'        => $user_cache[$poster_id]['email'],
            'U_WWW'            => $user_cache[$poster_id]['www'],
            'U_ICQ'            => $user_cache[$poster_id]['icq'],
            'U_AIM'            => $user_cache[$poster_id]['aim'],
            'U_MSN'            => $user_cache[$poster_id]['msn'],
            'U_YIM'            => $user_cache[$poster_id]['yim'],
            'U_JABBER'        => $user_cache[$poster_id]['jabber'],
    
            'U_REPORT'            => ($auth->acl_get('f_report', $forum_id)) ? append_sid("{$phpbb_root_path}report.$phpEx", 'f=' . $forum_id . '&amp;p=' . $row['post_id']) : '',
            'U_MCP_REPORT'        => ($auth->acl_get('m_report', $forum_id)) ? append_sid("{$phpbb_root_path}mcp.$phpEx", 'i=reports&amp;mode=report_details&amp;f=' . $forum_id . '&amp;p=' . $row['post_id'], true, $user->session_id) : '',
            'U_MCP_APPROVE'        => ($auth->acl_get('m_approve', $forum_id)) ? append_sid("{$phpbb_root_path}mcp.$phpEx", 'i=queue&amp;mode=approve_details&amp;f=' . $forum_id . '&amp;p=' . $row['post_id'], true, $user->session_id) : '',
            'U_MINI_POST'        => append_sid("{$phpbb_root_path}viewtopic.$phpEx", 'p=' . $row['post_id']) . (($topic_data['topic_type'] == POST_GLOBAL) ? '&amp;f=' . $forum_id : '') . '#p' . $row['post_id'],
            'U_NEXT_POST_ID'    => ($i < $i_total && isset($rowset[$post_list[$i + 1]])) ? $rowset[$post_list[$i + 1]]['post_id'] : '',
            'U_PREV_POST_ID'    => $prev_post_id,
            'U_NOTES'            => ($auth->acl_getf_global('m_')) ? append_sid("{$phpbb_root_path}mcp.$phpEx", 'i=notes&amp;mode=user_notes&amp;u=' . $poster_id, true, $user->session_id) : '',
            'U_WARN'            => ($auth->acl_get('m_warn') && $poster_id != $user->data['user_id'] && $poster_id != ANONYMOUS) ? append_sid("{$phpbb_root_path}mcp.$phpEx", 'i=warn&amp;mode=warn_post&amp;f=' . $forum_id . '&amp;p=' . $row['post_id'], true, $user->session_id) : '',
    
            'POST_ID'            => $row['post_id'],
            'POSTER_ID'            => $poster_id,
    
            'S_HAS_ATTACHMENTS'    => (!empty($attachments[$row['post_id']])) ? true : false,
            'S_POST_UNAPPROVED'    => ($row['post_approved']) ? false : true,
            'S_POST_REPORTED'    => ($row['post_reported'] && $auth->acl_get('m_report', $forum_id)) ? true : false,
            'S_DISPLAY_NOTICE'    => $display_notice && $row['post_attachment'],
            'S_FRIEND'            => ($row['friend']) ? true : false,
            'S_UNREAD_POST'        => $post_unread,
            'S_FIRST_UNREAD'    => $s_first_unread,
            'S_CUSTOM_FIELDS'    => (isset($cp_row['row']) && sizeof($cp_row['row'])) ? true : false,
            'S_TOPIC_POSTER'    => ($topic_data['topic_poster'] == $poster_id) ? true : false,
    
            'S_IGNORE_POST'        => ($row['hide_post']) ? true : false,
            'L_IGNORE_POST'        => ($row['hide_post']) ? sprintf($user->lang['POST_BY_FOE'], get_username_string('full', $poster_id, $row['username'], $row['user_colour'], $row['post_username']), '<a href="' . $viewtopic_url . "&amp;p={$row['post_id']}&amp;view=show#p{$row['post_id']}" . '">', '</a>') : '',
        );
    
        if (isset($cp_row['row']) && sizeof($cp_row['row']))
        {
            $postrow = array_merge($postrow, $cp_row['row']);
        }
    
        // Dump vars into template
        $template->assign_block_vars('postrow', $postrow);
    
        if (!empty($cp_row['blockrow']))
        {
            foreach ($cp_row['blockrow'] as $field_data)
            {
                $template->assign_block_vars('postrow.custom_fields', $field_data);
            }
        }
        
        // Display not already displayed Attachments for this post, we already parsed them. ;)
        if (!empty($attachments[$row['post_id']]))
        {
            foreach ($attachments[$row['post_id']] as $attachment)
            {
                $template->assign_block_vars('postrow.attachment', array(
                    'DISPLAY_ATTACHMENT'    => $attachment)
                );
            }
        }
    
        $prev_post_id = $row['post_id'];
    
        unset($rowset[$post_list[$i]]);
        unset($attachments[$row['post_id']]);
    }
    unset($rowset, $user_cache);
    
    // Update topic view and if necessary attachment view counters ... but only for humans and if this is the first 'page view'
    if (isset($user->data['session_page']) && !$user->data['is_bot'] && strpos($user->data['session_page'], '&t=' . $topic_id) === false)
    {
        $sql = 'UPDATE ' . TOPICS_TABLE . '
            SET topic_views = topic_views + 1, topic_last_view_time = ' . time() . "
            WHERE topic_id = $topic_id";
        $db->sql_query($sql);
    
        // Update the attachment download counts
        if (sizeof($update_count))
        {
            $sql = 'UPDATE ' . ATTACHMENTS_TABLE . '
                SET download_count = download_count + 1
                WHERE ' . $db->sql_in_set('attach_id', array_unique($update_count));
            $db->sql_query($sql);
        }
    }
    
    // Only mark topic if it's currently unread. Also make sure we do not set topic tracking back if earlier pages are viewed.
    if (isset($topic_tracking_info[$topic_id]) && $topic_data['topic_last_post_time'] > $topic_tracking_info[$topic_id] && $max_post_time > $topic_tracking_info[$topic_id])
    {
        markread('topic', $forum_id, $topic_id, $max_post_time);
    
        // Update forum info
        $all_marked_read = update_forum_tracking_info($forum_id, $topic_data['forum_last_post_time'], (isset($topic_data['forum_mark_time'])) ? $topic_data['forum_mark_time'] : false, false);
    }
    else
    {
        $all_marked_read = true;
    }
    
    // If there are absolutely no more unread posts in this forum and unread posts shown, we can savely show the #unread link
    if ($all_marked_read)
    {
        if ($post_unread)
        {
            $template->assign_vars(array(
                'U_VIEW_UNREAD_POST'    => '#unread',
            ));
        }
        else if (isset($topic_tracking_info[$topic_id]) && $topic_data['topic_last_post_time'] > $topic_tracking_info[$topic_id])
        {
            $template->assign_vars(array(
                'U_VIEW_UNREAD_POST'    => append_sid("{$phpbb_root_path}viewtopic.$phpEx", "f=$forum_id&amp;t=$topic_id&amp;view=unread") . '#unread',
            ));
        }
    }
    else if (!$all_marked_read)
    {
        $last_page = ((floor($start / $config['posts_per_page']) + 1) == max(ceil($total_posts / $config['posts_per_page']), 1)) ? true : false;
    
        // What can happen is that we are at the last displayed page. If so, we also display the #unread link based in $post_unread
        if ($last_page && $post_unread)
        {
            $template->assign_vars(array(
                'U_VIEW_UNREAD_POST'    => '#unread',
            ));
        }
        else if (!$last_page)
        {
            $template->assign_vars(array(
                'U_VIEW_UNREAD_POST'    => append_sid("{$phpbb_root_path}viewtopic.$phpEx", "f=$forum_id&amp;t=$topic_id&amp;view=unread") . '#unread',
            ));
        }
    }
    
    // We overwrite $_REQUEST['f'] if there is no forum specified
    // to be able to display the correct online list.
    // One downside is that the user currently viewing this topic/post is not taken into account.
    if (empty($_REQUEST['f']))
    {
        $_REQUEST['f'] = $forum_id;
    }
    
    // Mobiquo start here
    $post_list = array();
    foreach($template->_tpldata['postrow'] as $row)
    {
        $attachments = array();
        if ($row['S_HAS_ATTACHMENTS'])
        {            
            foreach($row['attachment'] as $attachment)
            {               
                if(preg_match('/<img src=\".*?(\/download\/file.php\?id=(\d+).*?)\"/is', $attachment['DISPLAY_ATTACHMENT'], $matches))
                {
                    $file_url = html_entity_decode($phpbb_home.$matches[1]);
                    $attach_id = $matches[2];
                    unset($matches);
                
                    $xmlrpc_attachment = new xmlrpcval(array(
                        'filename'      => new xmlrpcval($attachment_by_id[$attach_id]['real_filename'], 'base64'),
                        'filesize'      => new xmlrpcval($attachment_by_id[$attach_id]['filesize'], 'int'),
                        'content_type'  => new xmlrpcval('image'),
                        'thumbnail_url' => new xmlrpcval(''),
                        'url'           => new xmlrpcval($file_url)
                    ), 'struct');
                    $attachments[] = $xmlrpc_attachment;
                }
                
            }
        }
        
        $post_content = post_html_clean($row['MESSAGE']);
        $post_time    = mobiquo_iso8601_encode($row['POST_DATE']);
        
        $edit_allowed = ($user->data['is_registered'] && ($auth->acl_get('m_edit', $forum_id) || (
            $user->data['user_id'] == $row['POSTER_ID'] &&
            $auth->acl_get('f_edit', $forum_id) &&
            !$row['post_edit_locked'] &&
            ($row['POST_DATE'] > time() - ($config['edit_time'] * 60) || !$config['edit_time'])
        )));
    
        $delete_allowed = ($user->data['is_registered'] && ($auth->acl_get('m_delete', $forum_id) || (
            $user->data['user_id'] == $row['POSTER_ID'] &&
            $auth->acl_get('f_delete', $forum_id) &&
            $topic_data['topic_last_post_id'] == $row['POST_ID'] &&
            ($row['POST_DATE'] > time() - ($config['delete_time'] * 60) || !$config['delete_time']) &&
            // we do not want to allow removal of the last post if a moderator locked it!
            !$row['post_edit_locked']
        )));
        
        $xmlrpc_post = new xmlrpcval(array(
            'topic_id'          => new xmlrpcval($topic_id),
            'post_id'           => new xmlrpcval($row['POST_ID']),
            'post_title'        => new xmlrpcval(html_entity_decode(strip_tags($row['POST_SUBJECT']), ENT_QUOTES, 'UTF-8'), 'base64'),
            'post_content'      => new xmlrpcval($post_content, 'base64'),
            'post_author_id'    => new xmlrpcval($row['POSTER_ID']),
            'post_author_name'  => new xmlrpcval(html_entity_decode($row['POST_AUTHOR']), 'base64'),
            'icon_url'          => new xmlrpcval($row['POSTER_AVATAR']),
            'post_time'         => new xmlrpcval($post_time, 'dateTime.iso8601'),
            'attachments'       => new xmlrpcval($attachments, 'array'),
            'is_online'         => new xmlrpcval($row['S_ONLINE'], 'boolean'),
            'can_edit'          => new xmlrpcval($edit_allowed, 'boolean'),
            'can_delete'        => new xmlrpcval($delete_allowed, 'boolean'),
            'allow_smilies'     => new xmlrpcval($row['enable_smilies'] ? true : false, 'boolean'),
        ), 'struct');
        
        $post_list[] = $xmlrpc_post;
    }
    
    $allowed = $auth->acl_get('f_attach', $forum_id) && $auth->acl_get('u_attach') && $config['allow_attachments'] && @ini_get('file_uploads') != '0' && strtolower(@ini_get('file_uploads')) != 'off';
    
    return new xmlrpcresp(
        new xmlrpcval(array(
                'total_post_num' => new xmlrpcval($total_posts, 'int'),
                'forum_id'          => new xmlrpcval($forum_id),
                'forum_name'        => new xmlrpcval(html_entity_decode($topic_data['forum_name']), 'base64'),
                'topic_id'          => new xmlrpcval($topic_id),
                'topic_title'       => new xmlrpcval(html_entity_decode(strip_tags(censor_text($topic_data['topic_title']))), 'base64'),
                'can_reply'      => new xmlrpcval($auth->acl_get('f_reply', $forum_id) && $topic_data['forum_status'] != ITEM_LOCKED && $topic_data['topic_status'] != ITEM_LOCKED, 'boolean'),
                'can_delete'     => new xmlrpcval($auth->acl_get('m_delete', $forum_id), 'boolean'),
                'can_upload'     => new xmlrpcval($allowed, 'boolean'),
                'can_subscribe'  => new xmlrpcval(($config['email_enable'] || $config['jab_enable']) && $config['allow_topic_notify'] && $user->data['is_registered'], 'boolean'), 
                'can_bookmark'   => new xmlrpcval($user->data['is_registered'] && $config['allow_bookmarks'], 'boolean'),
                'issubscribed'   => new xmlrpcval(isset($topic_data['notify_status']) && !is_null($topic_data['notify_status']) && $topic_data['notify_status'] !== '' ? true : false, 'boolean'),
                'is_subscribed'  => new xmlrpcval(isset($topic_data['notify_status']) && !is_null($topic_data['notify_status']) && $topic_data['notify_status'] !== '' ? true : false, 'boolean'),
                'isbookmarked'   => new xmlrpcval(isset($topic_data['bookmarked']) && $topic_data['bookmarked'] ? true : false, 'boolean'),
                'can_stick'      => new xmlrpcval($allow_change_type && $auth->acl_get('f_sticky', $forum_id) && $topic_data['topic_type'] != POST_STICKY, 'boolean'),
                'can_close'      => new xmlrpcval($auth->acl_get('m_lock', $forum_id) || ($auth->acl_get('f_user_lock', $forum_id) && $user->data['is_registered'] && $user->data['user_id'] == $topic_data['topic_poster']), 'boolean'),
                'is_closed'      => new xmlrpcval($topic_data['topic_status'] == ITEM_LOCKED, 'boolean'),
                'posts'          => new xmlrpcval($post_list, 'array'),
            ),
            'struct'
        )
    );
}
