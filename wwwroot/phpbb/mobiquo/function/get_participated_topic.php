<?php
/**
*
* @copyright (c) 2009, 2010, 2011 Quoord Systems Limited
* @license http://opensource.org/licenses/gpl-2.0.php GNU Public License (GPLv2)
*
*/

defined('IN_MOBIQUO') or exit;

function get_participated_topic_func($xmlrpc_params)
{
    global $db, $auth, $user, $config, $template, $cache, $phpEx, $phpbb_root_path, $mobiquo_config;
    
    $user->setup('search');
    
    $params = php_xmlrpc_decode($xmlrpc_params);
    $author = $params[0];
    
    list($start, $per_page) = process_page($params[1], $params[2]);
    
    $show_results = 'topics';       // need topics
    $search_fields = 'all';   // only search for topics
    
    // ========================================================
    // Define initial vars
    $mode            = request_var('mode', '');
    $search_id        = request_var('search_id', '');
    $post_id        = request_var('p', 0);
    $topic_id        = request_var('t', 0);
    $view            = request_var('view', '');
    
    $submit            = request_var('submit', false);
    $keywords        = utf8_normalize_nfc(request_var('keywords', '', true));
    $author_id        = request_var('author_id', 0);
    $search_terms    = request_var('terms', 'all');
    //$search_fields    = request_var('sf', 'all');
    $search_child    = request_var('sc', true);
    
    $sort_days        = request_var('st', 0);
    $sort_key        = request_var('sk', 't');
    $sort_dir        = request_var('sd', 'd');
    
    $return_chars    = request_var('ch', ($topic_id) ? -1 : 300);
    $search_forum    = request_var('fid', array(0));
    
    // Is user able to search? Has search been disabled?
    if (!$auth->acl_get('u_search') || !$auth->acl_getf_global('f_search') || !$config['load_search'])
    {
        trigger_error('NO_SEARCH');
    }
    
    // Check search load limit
    if ($user->load && $config['limit_search_load'] && ($user->load > doubleval($config['limit_search_load'])))
    {
        trigger_error('NO_SEARCH_TIME');
    }
    
    // Define some vars
    $limit_days        = array(0 => $user->lang['ALL_RESULTS'], 1 => $user->lang['1_DAY'], 7 => $user->lang['7_DAYS'], 14 => $user->lang['2_WEEKS'], 30 => $user->lang['1_MONTH'], 90 => $user->lang['3_MONTHS'], 180 => $user->lang['6_MONTHS'], 365 => $user->lang['1_YEAR']);
    $sort_by_text    = array('a' => $user->lang['SORT_AUTHOR'], 't' => $user->lang['SORT_TIME'], 'f' => $user->lang['SORT_FORUM'], 'i' => $user->lang['SORT_TOPIC_TITLE'], 's' => $user->lang['SORT_POST_SUBJECT']);
    
    $s_limit_days = $s_sort_key = $s_sort_dir = $u_sort_param = '';
    gen_sort_selects($limit_days, $sort_by_text, $sort_days, $sort_key, $sort_dir, $s_limit_days, $s_sort_key, $s_sort_dir, $u_sort_param);
    $sort_key = 't';
    
    if ($keywords || $author || $author_id || $search_id || $submit)
    {
        // clear arrays
        $id_ary = array();
    
        // If we are looking for authors get their ids
        $author_id_ary = array();
        if ($author_id)
        {
            $author_id_ary[] = $author_id;
        }
        else if ($author)
        {
            if ((strpos($author, '*') !== false) && (utf8_strlen(str_replace(array('*', '%'), '', $author)) < $config['min_search_author_chars']))
            {
                trigger_error(sprintf($user->lang['TOO_FEW_AUTHOR_CHARS'], $config['min_search_author_chars']));
            }
    
            $sql_where = (strpos($author, '*') !== false) ? ' username_clean ' . $db->sql_like_expression(str_replace('*', $db->any_char, utf8_clean_string($author))) : " username_clean = '" . $db->sql_escape(utf8_clean_string($author)) . "'";
    
            $sql = 'SELECT user_id
                    FROM ' . USERS_TABLE . "
                    WHERE $sql_where
                    AND user_type IN (" . USER_NORMAL . ', ' . USER_FOUNDER . ')';
            $result = $db->sql_query_limit($sql, 100);
    
            while ($row = $db->sql_fetchrow($result))
            {
                $author_id_ary[] = (int) $row['user_id'];
            }
            $db->sql_freeresult($result);
    
            if (!sizeof($author_id_ary))
            {
                trigger_error('NO_SEARCH_RESULTS');
            }
        }
        
        // Which forums should not be searched? Author searches are also carried out in unindexed forums
        if (empty($keywords) && sizeof($author_id_ary))
        {
            $ex_fid_ary = array_keys($auth->acl_getf('!f_read', true));
        }
        else
        {
            $ex_fid_ary = array_unique(array_merge(array_keys($auth->acl_getf('!f_read', true)), array_keys($auth->acl_getf('!f_search', true))));
        }
        
        if (isset($mobiquo_config['hide_forum_id']))
        {
            $ex_fid_ary = array_unique(array_merge($ex_fid_ary, $mobiquo_config['hide_forum_id']));
        }
    
        $not_in_fid = (sizeof($ex_fid_ary)) ? 'WHERE ' . $db->sql_in_set('f.forum_id', $ex_fid_ary, true) . " OR (f.forum_password <> '' AND fa.user_id <> " . (int) $user->data['user_id'] . ')' : "";
    
        $sql = 'SELECT f.forum_id, f.forum_name, f.parent_id, f.forum_type, f.right_id, f.forum_password, fa.user_id
                FROM ' . FORUMS_TABLE . ' f
                LEFT JOIN ' . FORUMS_ACCESS_TABLE . " fa ON (fa.forum_id = f.forum_id
                AND fa.session_id = '" . $db->sql_escape($user->session_id) . "')
                $not_in_fid
                ORDER BY f.left_id";
        $result = $db->sql_query($sql);
    
        $right_id = 0;
        $reset_search_forum = true;
        while ($row = $db->sql_fetchrow($result))
        {
            if ($row['forum_password'] && $row['user_id'] != $user->data['user_id'])
            {
                $ex_fid_ary[] = (int) $row['forum_id'];
                continue;
            }
    
            if (sizeof($search_forum))
            {
                if ($search_child)
                {
                    if (in_array($row['forum_id'], $search_forum) && $row['right_id'] > $right_id)
                    {
                        $right_id = (int) $row['right_id'];
                    }
                    else if ($row['right_id'] < $right_id)
                    {
                        continue;
                    }
                }
    
                if (!in_array($row['forum_id'], $search_forum))
                {
                    $ex_fid_ary[] = (int) $row['forum_id'];
                    $reset_search_forum = false;
                }
            }
        }
        $db->sql_freeresult($result);
            
        // find out in which forums the user is allowed to view approved posts
        if ($auth->acl_get('m_approve'))
        {
            $m_approve_fid_ary = array(-1);
            $m_approve_fid_sql = '';
        }
        else if ($auth->acl_getf_global('m_approve'))
        {
            $m_approve_fid_ary = array_diff(array_keys($auth->acl_getf('!m_approve', true)), $ex_fid_ary);
            $m_approve_fid_sql = ' AND (p.post_approved = 1' . ((sizeof($m_approve_fid_ary)) ? ' OR ' . $db->sql_in_set('p.forum_id', $m_approve_fid_ary, true) : '') . ')';
        }
        else
        {
            $m_approve_fid_ary = array();
            $m_approve_fid_sql = ' AND p.post_approved = 1';
        }
    
        if ($reset_search_forum)
        {
            $search_forum = array();
        }
    
        // Select which method we'll use to obtain the post_id or topic_id information
        $search_type = basename($config['search_type']);
    
        if (!file_exists($phpbb_root_path . 'includes/search/' . $search_type . '.' . $phpEx))
        {
            trigger_error('NO_SUCH_SEARCH_MODULE');
        }
    
        require("{$phpbb_root_path}includes/search/$search_type.$phpEx");
    
        // We do some additional checks in the module to ensure it can actually be utilised
        $error = false;
        $search = new $search_type($error);
    
        if ($error)
        {
            trigger_error($error);
        }
    
        // define some variables needed for retrieving post_id/topic_id information
        $sort_by_sql = array('a' => 'u.username_clean', 't' => 't.topic_last_post_time', 'f' => 'f.forum_id', 'i' => 't.topic_title', 's' => 't.topic_title');
    
        // pre-made searches
        $sql = $field = $l_search_title = '';
    
        // $per_page = $config['topics_per_page'];
        $total_match_count = 0;
    
        // make sure that some arrays are always in the same order
        sort($ex_fid_ary);
        sort($m_approve_fid_ary);
        sort($author_id_ary);
    
        if (!empty($search->search_query))
        {
            $total_match_count = $search->keyword_search($show_results, $search_fields, $search_terms, $sort_by_sql, $sort_key, $sort_dir, $sort_days, $ex_fid_ary, $m_approve_fid_ary, $topic_id, $author_id_ary, $id_ary, $start, $per_page);
        }
        else if (sizeof($author_id_ary))
        {
            $firstpost_only = ($search_fields === 'firstpost' || $search_fields == 'titleonly') ? true : false;
            if($config['version'] == '3.0.8' || $config['version'] == '3.0.6' || $config['version'] == '3.0.7' || $config['version'] == '3.0.7-PL1' || $config['version'] == '3.0.9' || $config['version'] == '3.0.10'){
                $total_match_count = $search->author_search($show_results, $firstpost_only, $sort_by_sql, $sort_key, $sort_dir, $sort_days, $ex_fid_ary, $m_approve_fid_ary, $topic_id, $author_id_ary, $aaa, $id_ary, $start, $per_page);
            } else {
                $total_match_count = $search->author_search($show_results, $firstpost_only, $sort_by_sql, $sort_key, $sort_dir, $sort_days, $ex_fid_ary, $m_approve_fid_ary, $topic_id, $author_id_ary, $id_ary, $start, $per_page);
            }
        }
    
        $sql_where = '';
    
        if (sizeof($id_ary))
        {
            $sql_where .= $db->sql_in_set('t.topic_id', $id_ary);
            $sql_where .= (sizeof($ex_fid_ary)) ? ' AND (' . $db->sql_in_set('f.forum_id', $ex_fid_ary, true) . ' OR f.forum_id IS NULL)' : '';
            $sql_where .= str_replace(array('p.post_approved', 'p.forum_id'), array('t.topic_approved', 't.forum_id'), $m_approve_fid_sql);
        }
        
        $user->add_lang('viewtopic');
    
        // Grab icons
        $icons = $cache->obtain_icons();
    
        $l_search_matches = ($total_match_count == 1) ? sprintf($user->lang['FOUND_SEARCH_MATCH'], $total_match_count) : sprintf($user->lang['FOUND_SEARCH_MATCHES'], $total_match_count);
    
        // define some vars for urls
        $hilit = implode('|', explode(' ', preg_replace('#\s+#u', ' ', str_replace(array('+', '-', '|', '(', ')', '&quot;'), ' ', $keywords))));
        // Do not allow *only* wildcard being used for hilight
        $hilit = (strspn($hilit, '*') === strlen($hilit)) ? '' : $hilit;
    
        $u_hilit = urlencode(htmlspecialchars_decode(str_replace('|', ' ', $hilit)));
        $u_show_results = '&amp;sr=' . $show_results;
        $u_search_forum = implode('&amp;fid%5B%5D=', $search_forum);
    
        $u_search = append_sid("{$phpbb_root_path}search.$phpEx", $u_sort_param . $u_show_results);
        $u_search .= ($u_hilit) ? '&amp;keywords=' . urlencode(htmlspecialchars_decode($search->search_query)) : '';
        $u_search .= ($search_terms != 'all') ? '&amp;terms=' . $search_terms : '';
        $u_search .= ($topic_id) ? '&amp;t=' . $topic_id : '';
        $u_search .= ($author) ? '&amp;author=' . urlencode(htmlspecialchars_decode($author)) : '';
        $u_search .= ($author_id) ? '&amp;author_id=' . $author_id : '';
        $u_search .= ($u_search_forum) ? '&amp;fid%5B%5D=' . $u_search_forum : '';
        $u_search .= (!$search_child) ? '&amp;sc=0' : '';
        $u_search .= ($search_fields != 'all') ? '&amp;sf=' . $search_fields : '';
        $u_search .= ($return_chars != 300) ? '&amp;ch=' . $return_chars : '';
    
        $template->assign_vars(array(
            'SEARCH_TITLE'        => $l_search_title,
            'SEARCH_MATCHES'    => $l_search_matches,
            'SEARCH_WORDS'        => $search->search_query,
            'IGNORED_WORDS'        => (sizeof($search->common_words)) ? implode(' ', $search->common_words) : '',
            'PAGINATION'        => generate_pagination($u_search, $total_match_count, $per_page, $start),
            'PAGE_NUMBER'        => on_page($total_match_count, $per_page, $start),
            'TOTAL_MATCHES'        => $total_match_count,
            'SEARCH_IN_RESULTS'    => true,
    
            'S_SELECT_SORT_DIR'        => $s_sort_dir,
            'S_SELECT_SORT_KEY'        => $s_sort_key,
            'S_SELECT_SORT_DAYS'    => $s_limit_days,
            'S_SEARCH_ACTION'        => $u_search,
            'S_SHOW_TOPICS'            => true,
    
            'GOTO_PAGE_IMG'        => $user->img('icon_post_target', 'GOTO_PAGE'),
            'NEWEST_POST_IMG'    => $user->img('icon_topic_newest', 'VIEW_NEWEST_POST'),
            'REPORTED_IMG'        => $user->img('icon_topic_reported', 'TOPIC_REPORTED'),
            'UNAPPROVED_IMG'    => $user->img('icon_topic_unapproved', 'TOPIC_UNAPPROVED'),
            'LAST_POST_IMG'        => $user->img('icon_topic_latest', 'VIEW_LATEST_POST'),
    
            'U_SEARCH_WORDS'    => $u_search,
        ));
        
        if ($sql_where)
        {
            $sql_from = TOPICS_TABLE . ' t
                LEFT JOIN ' . FORUMS_TABLE . ' f ON (f.forum_id = t.forum_id)
                LEFT JOIN ' . USERS_TABLE . ' u ON (u.user_id = t.topic_last_poster_id) 
                LEFT JOIN ' . TOPICS_WATCH_TABLE . ' tw ON (tw.user_id = ' . $user->data['user_id'] . ' AND t.topic_id = tw.topic_id)';
            $sql_select = 't.*, f.forum_name, u.user_avatar, u.user_avatar_type, tw.notify_status';

            if ($user->data['is_registered'])
            {
                if ($config['load_db_track'] && $author_id !== $user->data['user_id'])
                {
                    $sql_from .= ' LEFT JOIN ' . TOPICS_POSTED_TABLE . ' tp ON (tp.user_id = ' . $user->data['user_id'] . '
                        AND t.topic_id = tp.topic_id)';
                    $sql_select .= ', tp.topic_posted';
                }

                if ($config['load_db_lastread'])
                {
                    $sql_from .= ' LEFT JOIN ' . TOPICS_TRACK_TABLE . ' tt ON (tt.user_id = ' . $user->data['user_id'] . '
                            AND t.topic_id = tt.topic_id)
                        LEFT JOIN ' . FORUMS_TRACK_TABLE . ' ft ON (ft.user_id = ' . $user->data['user_id'] . '
                            AND ft.forum_id = f.forum_id)';
                    $sql_select .= ', tt.mark_time, ft.mark_time as f_mark_time';
                }
            }

            if ($config['load_anon_lastread'] || ($user->data['is_registered'] && !$config['load_db_lastread']))
            {
                $tracking_topics = (isset($_COOKIE[$config['cookie_name'] . '_track'])) ? ((STRIP) ? stripslashes($_COOKIE[$config['cookie_name'] . '_track']) : $_COOKIE[$config['cookie_name'] . '_track']) : '';
                $tracking_topics = ($tracking_topics) ? tracking_unserialize($tracking_topics) : array();
            }

            $sql = "SELECT $sql_select
                FROM $sql_from
                WHERE $sql_where 
                ORDER BY $sort_by_sql[t] DESC";
            $result = $db->sql_query($sql);
            $result_topic_id = 0;
            
            $rowset = array();
    
            $forums = $rowset = $shadow_topic_list = array();
            while ($row = $db->sql_fetchrow($result))
            {
                if ($row['topic_status'] == ITEM_MOVED)
                {
                    $shadow_topic_list[$row['topic_moved_id']] = $row['topic_id'];
                }

                $rowset[$row['topic_id']] = $row;

                if (!isset($forums[$row['forum_id']]) && $user->data['is_registered'] && $config['load_db_lastread'])
                {
                    $forums[$row['forum_id']]['mark_time'] = $row['f_mark_time'];
                }
                $forums[$row['forum_id']]['topic_list'][] = $row['topic_id'];
                $forums[$row['forum_id']]['rowset'][$row['topic_id']] = &$rowset[$row['topic_id']];
            }
            $db->sql_freeresult($result);

            // If we have some shadow topics, update the rowset to reflect their topic information
            if (sizeof($shadow_topic_list))
            {
                $sql = 'SELECT *
                    FROM ' . TOPICS_TABLE . '
                    WHERE ' . $db->sql_in_set('topic_id', array_keys($shadow_topic_list));
                $result = $db->sql_query($sql);

                while ($row = $db->sql_fetchrow($result))
                {
                    $orig_topic_id = $shadow_topic_list[$row['topic_id']];

                    // We want to retain some values
                    $row = array_merge($row, array(
                        'topic_moved_id'    => $rowset[$orig_topic_id]['topic_moved_id'],
                        'topic_status'        => $rowset[$orig_topic_id]['topic_status'],
                        'forum_name'        => $rowset[$orig_topic_id]['forum_name'])
                    );

                    $rowset[$orig_topic_id] = $row;
                }
                $db->sql_freeresult($result);
            }
            unset($shadow_topic_list);

            foreach ($forums as $forum_id => $forum)
            {
                if ($user->data['is_registered'] && $config['load_db_lastread'])
                {
                    $topic_tracking_info[$forum_id] = get_topic_tracking($forum_id, $forum['topic_list'], $forum['rowset'], array($forum_id => $forum['mark_time']), ($forum_id) ? false : $forum['topic_list']);
                }
                else if ($config['load_anon_lastread'] || $user->data['is_registered'])
                {
                    $topic_tracking_info[$forum_id] = get_complete_topic_tracking($forum_id, $forum['topic_list'], ($forum_id) ? false : $forum['topic_list']);

                    if (!$user->data['is_registered'])
                    {
                        $user->data['user_lastmark'] = (isset($tracking_topics['l'])) ? (int) (base_convert($tracking_topics['l'], 36, 10) + $config['board_startdate']) : 0;
                    }
                }
            }
            unset($forums);
    
            foreach ($rowset as $row)
            {
                $forum_id = $row['forum_id'];
                $result_topic_id = $row['topic_id'];
                $topic_title = censor_text($row['topic_title']);
    
                // we need to select a forum id for this global topic
                if (!$forum_id)
                {
                    if (!isset($g_forum_id))
                    {
                        // Get a list of forums the user cannot read
                        $forum_ary = array_unique(array_keys($auth->acl_getf('!f_read', true)));
    
                        // Determine first forum the user is able to read (must not be a category)
                        $sql = 'SELECT forum_id
                                FROM ' . FORUMS_TABLE . '
                                WHERE forum_type = ' . FORUM_POST;
    
                        if (sizeof($forum_ary))
                        {
                            $sql .= ' AND ' . $db->sql_in_set('forum_id', $forum_ary, true);
                        }
    
                        $result = $db->sql_query_limit($sql, 1);
                        $g_forum_id = (int) $db->sql_fetchfield('forum_id');
                    }
                    $u_forum_id = $g_forum_id;
                }
                else
                {
                    $u_forum_id = $forum_id;
                }
                
                $view_topic_url = append_sid("{$phpbb_root_path}viewtopic.$phpEx", "f=$u_forum_id&amp;t=$result_topic_id" . (($u_hilit) ? "&amp;hilit=$u_hilit" : ''));
    
                $replies = ($auth->acl_get('m_approve', $forum_id)) ? $row['topic_replies_real'] : $row['topic_replies'];
    
                if ($config['load_db_track'] && $author_id === $user->data['user_id'])
                {
                    $row['topic_posted'] = 1;
                }

                $folder_img = $folder_alt = $topic_type = '';
                topic_status($row, $replies, (isset($topic_tracking_info[$forum_id][$row['topic_id']]) && $row['topic_last_post_time'] > $topic_tracking_info[$forum_id][$row['topic_id']]) ? true : false, $folder_img, $folder_alt, $topic_type);

                $unread_topic = (isset($topic_tracking_info[$forum_id][$row['topic_id']]) && $row['topic_last_post_time'] > $topic_tracking_info[$forum_id][$row['topic_id']]) ? true : false;

                $topic_unapproved = (!$row['topic_approved'] && $auth->acl_get('m_approve', $forum_id)) ? true : false;
                $posts_unapproved = ($row['topic_approved'] && $row['topic_replies'] < $row['topic_replies_real'] && $auth->acl_get('m_approve', $forum_id)) ? true : false;
                $u_mcp_queue = ($topic_unapproved || $posts_unapproved) ? append_sid("{$phpbb_root_path}mcp.$phpEx", 'i=queue&amp;mode=' . (($topic_unapproved) ? 'approve_details' : 'unapproved_posts') . "&amp;t=$result_topic_id", true, $user->session_id) : '';

                $row['topic_title'] = preg_replace('#(?!<.*)(?<!\w)(' . $hilit . ')(?!\w|[^<>]*(?:</s(?:cript|tyle))?>)#is', '<span class="posthilit">$1</span>', $row['topic_title']);

                $tpl_ary = array(
                    'TOPIC_AUTHOR'                => get_username_string('username', $row['topic_poster'], $row['topic_first_poster_name'], $row['topic_first_poster_colour']),
                    'TOPIC_AUTHOR_COLOUR'        => get_username_string('colour', $row['topic_poster'], $row['topic_first_poster_name'], $row['topic_first_poster_colour']),
                    'TOPIC_AUTHOR_FULL'            => get_username_string('full', $row['topic_poster'], $row['topic_first_poster_name'], $row['topic_first_poster_colour']),
                    'FIRST_POST_TIME'            => $user->format_date($row['topic_time']),
                    'LAST_POST_SUBJECT'            => $row['topic_last_post_subject'],
//                  'LAST_POST_TIME'            => $user->format_date($row['topic_last_post_time']),
                    'LAST_POST_TIME'            => $row['topic_last_post_time'],
                    'LAST_VIEW_TIME'            => $user->format_date($row['topic_last_view_time']),
                    'LAST_POST_AUTHOR'            => get_username_string('username', $row['topic_last_poster_id'], $row['topic_last_poster_name'], $row['topic_last_poster_colour']),
                    'LAST_POST_AUTHOR_COLOUR'    => get_username_string('colour', $row['topic_last_poster_id'], $row['topic_last_poster_name'], $row['topic_last_poster_colour']),
                    'LAST_POST_AUTHOR_FULL'        => get_username_string('full', $row['topic_last_poster_id'], $row['topic_last_poster_name'], $row['topic_last_poster_colour']),

                    'PAGINATION'        => topic_generate_pagination($replies, $view_topic_url),
                    'TOPIC_TYPE'        => $topic_type,
                    
                    'user_avatar'       => $row['user_avatar'],
                    'user_avatar_type'  => $row['user_avatar_type'],
                    'notify_status'     => $row['notify_status'],
                    'topic_poster'      => $row['topic_poster'],
                    'topic_status'      => $row['topic_status'],
                    'topic_type'        => $row['topic_type'],
                    'topic_approved'    => $row['topic_approved'],

                    'TOPIC_FOLDER_IMG'        => $user->img($folder_img, $folder_alt),
                    'TOPIC_FOLDER_IMG_SRC'    => $user->img($folder_img, $folder_alt, false, '', 'src'),
                    'TOPIC_FOLDER_IMG_ALT'    => $user->lang[$folder_alt],
                    'TOPIC_FOLDER_IMG_WIDTH'=> $user->img($folder_img, '', false, '', 'width'),
                    'TOPIC_FOLDER_IMG_HEIGHT'    => $user->img($folder_img, '', false, '', 'height'),

                    'TOPIC_ICON_IMG'        => (!empty($icons[$row['icon_id']])) ? $icons[$row['icon_id']]['img'] : '',
                    'TOPIC_ICON_IMG_WIDTH'    => (!empty($icons[$row['icon_id']])) ? $icons[$row['icon_id']]['width'] : '',
                    'TOPIC_ICON_IMG_HEIGHT'    => (!empty($icons[$row['icon_id']])) ? $icons[$row['icon_id']]['height'] : '',
                    'ATTACH_ICON_IMG'        => ($auth->acl_get('u_download') && $auth->acl_get('f_download', $forum_id) && $row['topic_attachment']) ? $user->img('icon_topic_attach', $user->lang['TOTAL_ATTACHMENTS']) : '',
                    'UNAPPROVED_IMG'        => ($topic_unapproved || $posts_unapproved) ? $user->img('icon_topic_unapproved', ($topic_unapproved) ? 'TOPIC_UNAPPROVED' : 'POSTS_UNAPPROVED') : '',

                    'S_TOPIC_GLOBAL'        => (!$forum_id) ? true : false,
                    'S_TOPIC_TYPE'            => $row['topic_type'],
                    'S_USER_POSTED'            => (!empty($row['mark_type'])) ? true : false,
                    'S_UNREAD_TOPIC'        => $unread_topic,

                    'S_TOPIC_REPORTED'        => (!empty($row['topic_reported']) && $auth->acl_get('m_report', $forum_id)) ? true : false,
                    'S_TOPIC_UNAPPROVED'    => $topic_unapproved,
                    'S_POSTS_UNAPPROVED'    => $posts_unapproved,
                    
                    'U_LAST_POST'            => $view_topic_url . '&amp;p=' . $row['topic_last_post_id'] . '#p' . $row['topic_last_post_id'],
                    'U_LAST_POST_AUTHOR'    => get_username_string('profile', $row['topic_last_poster_id'], $row['topic_last_poster_name'], $row['topic_last_poster_colour']),
                    'U_TOPIC_AUTHOR'        => get_username_string('profile', $row['topic_poster'], $row['topic_first_poster_name'], $row['topic_first_poster_colour']),
                    'U_NEWEST_POST'            => $view_topic_url . '&amp;view=unread#unread',
                    'U_MCP_REPORT'            => append_sid("{$phpbb_root_path}mcp.$phpEx", 'i=reports&amp;mode=reports&amp;t=' . $result_topic_id, true, $user->session_id),
                    'U_MCP_QUEUE'            => $u_mcp_queue,
                );

                $template->assign_block_vars('searchresults', array_merge($tpl_ary, array(
                    'FORUM_ID'          => $forum_id,
                    'TOPIC_ID'          => $result_topic_id,
                    //'POST_ID'         => false,
                    'POST_ID'           => $row['topic_last_post_id'],
                    'FORUM_TITLE'       => $row['forum_name'],
                    'TOPIC_TITLE'       => $topic_title,
                    'TOPIC_REPLIES'     => $replies,
                    'TOPIC_VIEWS'       => $row['topic_views'],
    
                    'U_VIEW_TOPIC'      => $view_topic_url,
                    'U_VIEW_FORUM'      => append_sid("{$phpbb_root_path}viewforum.$phpEx", 'f=' . $forum_id),
                    'U_VIEW_POST'       => (!empty($row['post_id'])) ? append_sid("{$phpbb_root_path}viewtopic.$phpEx", "f=$forum_id&amp;t=" . $row['topic_id'] . '&amp;p=' . $row['post_id'] . (($u_hilit) ? '&amp;hilit=' . $u_hilit : '')) . '#p' . $row['post_id'] : '')
                ));
            }
    
            if ($topic_id && ($topic_id == $result_topic_id))
            {
                $template->assign_vars(array(
                    'SEARCH_TOPIC'        => $topic_title,
                    'U_SEARCH_TOPIC'    => $view_topic_url
                ));
            }
        }
        unset($rowset);
    }
    
    
    // Mobiquo start here
    $topic_list = array();
    $total_unread_num = 0;
    if(isset($template->_tpldata['searchresults']))
    {
        $tids = array();
        foreach ($template->_tpldata['searchresults'] as $row)
            $tids[] = $row['TOPIC_ID'];
        
        // get participated users of each topic
//        get_participated_user_avatars($tids);
//        global $topic_users, $user_avatar;
        
        foreach($template->_tpldata['searchresults'] as $row)
        {
            $forum_id = $row['FORUM_ID'];
            $short_content = get_short_content($row['POST_ID']);
            $user_avatar_url = get_user_avatar_url($row['user_avatar'], $row['user_avatar_type']);
            $topic_tracking = get_complete_topic_tracking($forum_id, $row['TOPIC_ID']);
            $new_post = $topic_tracking[$row['TOPIC_ID']] < $row['LAST_POST_TIME'] ? true : false;
            $new_post && $total_unread_num++;
            
            $allow_change_type = ($auth->acl_get('m_', $forum_id) || ($user->data['is_registered'] && $user->data['user_id'] == $row['topic_poster'])) ? true : false;
            
//            $icon_urls = array();
//            foreach($topic_users[$row['TOPIC_ID']] as $posterid){
//                $icon_urls[] = new xmlrpcval($user_avatar[$posterid], 'string');
//            }
            
            if (empty($forum_id))
            {
                $user->setup('viewforum');
                $forum_id = 0;
                $row['FORUM_TITLE'] = $user->lang['ANNOUNCEMENTS'];
            }
            
            if (empty($row['FORUM_TITLE'])) $row['FORUM_TITLE'] = 'Forum';
            
            $xmlrpc_topic = new xmlrpcval(array(
                'forum_id'          => new xmlrpcval($forum_id),
                'forum_name'        => new xmlrpcval(html_entity_decode($row['FORUM_TITLE']), 'base64'),
                'topic_id'          => new xmlrpcval($row['TOPIC_ID']),
                'topic_title'       => new xmlrpcval(html_entity_decode(strip_tags(censor_text($row['TOPIC_TITLE']))), 'base64'),
                'icon_url'          => new xmlrpcval($user_avatar_url),
//                'icon_urls'         => new xmlrpcval($icon_urls, 'array'),
                'topic_author_name' => new xmlrpcval(html_entity_decode($row['TOPIC_AUTHOR']), 'base64'),
                'post_author_name'  => new xmlrpcval(html_entity_decode($row['LAST_POST_AUTHOR']), 'base64'),
                'post_time'         => new xmlrpcval(mobiquo_iso8601_encode($row['LAST_POST_TIME']), 'dateTime.iso8601'),
                'reply_number'      => new xmlrpcval($row['TOPIC_REPLIES'], 'int'),
                'view_number'       => new xmlrpcval($row['TOPIC_VIEWS'], 'int'),
                'new_post'          => new xmlrpcval($new_post, 'boolean'),
                'short_content'     => new xmlrpcval($short_content, 'base64'),
                
                'can_delete'        => new xmlrpcval($auth->acl_get('m_delete', $forum_id), 'boolean'),
                'can_move'          => new xmlrpcval($auth->acl_get('m_move', $forum_id), 'boolean'),
                'can_subscribe'     => new xmlrpcval(($config['email_enable'] || $config['jab_enable']) && $config['allow_topic_notify'] && $user->data['is_registered'], 'boolean'), 
                'is_subscribed'     => new xmlrpcval(!is_null($row['notify_status']) && $row['notify_status'] !== '' ? true : false, 'boolean'),
                'can_close'         => new xmlrpcval($auth->acl_get('m_lock', $forum_id) || ($auth->acl_get('f_user_lock', $forum_id) && $user->data['is_registered'] && $user->data['user_id'] == $row['topic_poster']), 'boolean'),
                'is_closed'         => new xmlrpcval($row['topic_status'] == ITEM_LOCKED, 'boolean'),
                'can_stick'         => new xmlrpcval($allow_change_type && $auth->acl_get('f_sticky', $forum_id), 'boolean'),
                'is_sticky'         => new xmlrpcval($row['topic_type'] == POST_STICKY, 'boolean'),
                'can_approve'       => new xmlrpcval($auth->acl_get('m_approve', $forum_id) && !$row['topic_approved'], 'boolean'),
                'is_approved'       => new xmlrpcval($row['topic_approved'] ? true : false, 'boolean'),
            ), 'struct');
    
            $topic_list[] = $xmlrpc_topic;
        }
    }
    
    $response = new xmlrpcval(
        array(
            'total_topic_num'   => new xmlrpcval($total_match_count, 'int'),
            'total_unread_num'  => new xmlrpcval($total_unread_num, 'int'),
            'topics'            => new xmlrpcval($topic_list, 'array'),
        ),
        'struct'
    );

    return new xmlrpcresp($response);
}
