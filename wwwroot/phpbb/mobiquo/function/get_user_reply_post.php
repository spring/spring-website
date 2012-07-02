<?php
/**
*
* @copyright (c) 2009, 2010, 2011 Quoord Systems Limited
* @license http://opensource.org/licenses/gpl-2.0.php GNU Public License (GPLv2)
*
*/

defined('IN_MOBIQUO') or exit;

function get_user_reply_post_func($xmlrpc_params)
{
    global $db, $auth, $user, $config, $template, $cache, $phpEx, $phpbb_root_path, $mobiquo_config;
    
    $user->setup('search');
    
    $params = php_xmlrpc_decode($xmlrpc_params);
    $author = $params[0];
    
    $show_results = 'posts';       // need posts
    
    // ========================================================
    // Define initial vars
    $mode            = request_var('mode', '');
    $search_id        = request_var('search_id', '');
    $start            = max(request_var('start', 0), 0);
    $post_id        = request_var('p', 0);
    $topic_id        = request_var('t', 0);
    $view            = request_var('view', '');
    
    $submit            = request_var('submit', false);
    $keywords        = utf8_normalize_nfc(request_var('keywords', '', true));
    $add_keywords    = utf8_normalize_nfc(request_var('add_keywords', '', true));
    $author_id        = request_var('author_id', 0);
    $search_terms    = request_var('terms', 'all');
    $search_fields    = request_var('sf', 'all');
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
    
        // egosearch is an author search
        if ($search_id == 'egosearch')
        {
            $author_id = $user->data['user_id'];
    
            if ($user->data['user_id'] == ANONYMOUS)
            {
                trigger_error('LOGIN_EXPLAIN_EGOSEARCH');
            }
        }
    
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
    
        // if we search in an existing search result just add the additional keywords. But we need to use "all search terms"-mode
        // so we can keep the old keywords in their old mode, but add the new ones as required words
        if ($add_keywords)
        {
            if ($search_terms == 'all')
            {
                $keywords .= ' ' . $add_keywords;
            }
            else
            {
                $search_terms = 'all';
                $keywords = implode(' |', explode(' ', preg_replace('#\s+#u', ' ', $keywords))) . ' ' .$add_keywords;
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
    
        // let the search module split up the keywords
        if ($keywords)
        {
            $correct_query = $search->split_keywords($keywords, $search_terms);
            if (!$correct_query || (empty($search->search_query) && !sizeof($author_id_ary) && !$search_id))
            {
                $ignored = (sizeof($search->common_words)) ? sprintf($user->lang['IGNORED_TERMS_EXPLAIN'], implode(' ', $search->common_words)) . '<br />' : '';
                trigger_error($ignored . sprintf($user->lang['NO_KEYWORDS'], $search->word_length['min'], $search->word_length['max']));
            }
        }
    
        // define some variables needed for retrieving post_id/topic_id information
        $sort_by_sql = array('a' => 'u.username_clean', 't' => 'p.post_time', 'f' => 'f.forum_id', 'i' => 't.topic_title', 's' => 'p.post_subject');
    
        // pre-made searches
        $sql = $field = $l_search_title = '';
    
        // show_results should not change after this
        $per_page = 20;
        $total_match_count = 0;
    
        if ($search_id)
        {
            if ($sql)
            {
                // only return up to 1000 ids (the last one will be removed later)
                $result = $db->sql_query_limit($sql, 1001 - $start, $start);
    
                while ($row = $db->sql_fetchrow($result))
                {
                    $id_ary[] = $row[$field];
                }
                $db->sql_freeresult($result);
    
                $total_match_count = sizeof($id_ary) + $start;
                $id_ary = array_slice($id_ary, 0, $per_page);
            }
            else
            {
                $search_id = '';
            }
        }
    
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
                $total_match_count = $search->author_search($show_results, $firstpost_only, $sort_by_sql, $sort_key, $sort_dir, $sort_days, $ex_fid_ary, $m_approve_fid_ary, $topic_id, $author_id_ary,$aaa, $id_ary, $start, $per_page);
            } else {
                $total_match_count = $search->author_search($show_results, $firstpost_only, $sort_by_sql, $sort_key, $sort_dir, $sort_days, $ex_fid_ary, $m_approve_fid_ary, $topic_id, $author_id_ary, $id_ary, $start, $per_page);
            }
        }
    
        $sql_where = '';
    
        if (sizeof($id_ary))
        {
            $sql_where .= $db->sql_in_set('p.post_id', $id_ary);
            $sql_where .= (sizeof($ex_fid_ary)) ? ' AND (' . $db->sql_in_set('f.forum_id', $ex_fid_ary, true) . ' OR f.forum_id IS NULL)' : '';
            $sql_where .= $m_approve_fid_sql;
        }
        
        include($phpbb_root_path . 'includes/functions_posting.' . $phpEx);
    
        $user->add_lang('viewtopic');
    
        // Grab icons
        $icons = $cache->obtain_icons();
    
        // Output header
        if ($search_id && ($total_match_count > 1000))
        {
            // limit the number to 1000 for pre-made searches
            $total_match_count--;
            $l_search_matches = sprintf($user->lang['FOUND_MORE_SEARCH_MATCHES'], $total_match_count);
        }
        else
        {
            $l_search_matches = ($total_match_count == 1) ? sprintf($user->lang['FOUND_SEARCH_MATCH'], $total_match_count) : sprintf($user->lang['FOUND_SEARCH_MATCHES'], $total_match_count);
        }
    
        // define some vars for urls
        $hilit = implode('|', explode(' ', preg_replace('#\s+#u', ' ', str_replace(array('+', '-', '|', '(', ')', '&quot;'), ' ', $keywords))));
        // Do not allow *only* wildcard being used for hilight
        $hilit = (strspn($hilit, '*') === strlen($hilit)) ? '' : $hilit;
    
        $u_hilit = urlencode(htmlspecialchars_decode(str_replace('|', ' ', $hilit)));
        $u_show_results = '';
        $u_search_forum = implode('&amp;fid%5B%5D=', $search_forum);
    
        $u_search = append_sid("{$phpbb_root_path}search.$phpEx", $u_sort_param . $u_show_results);
        $u_search .= ($search_id) ? '&amp;search_id=' . $search_id : '';
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
            //'PAGINATION'        => generate_pagination($u_search, $total_match_count, $per_page, $start),
            //'PAGE_NUMBER'        => on_page($total_match_count, $per_page, $start),
            'TOTAL_MATCHES'        => $total_match_count,
            'SEARCH_IN_RESULTS'    => ($search_id) ? false : true,
    
            'S_SELECT_SORT_DIR'        => $s_sort_dir,
            'S_SELECT_SORT_KEY'        => $s_sort_key,
            'S_SELECT_SORT_DAYS'    => $s_limit_days,
            'S_SEARCH_ACTION'        => $u_search,
            'S_SHOW_TOPICS'            => false,
    
            'GOTO_PAGE_IMG'        => $user->img('icon_post_target', 'GOTO_PAGE'),
            'NEWEST_POST_IMG'    => $user->img('icon_topic_newest', 'VIEW_NEWEST_POST'),
            'REPORTED_IMG'        => $user->img('icon_topic_reported', 'TOPIC_REPORTED'),
            'UNAPPROVED_IMG'    => $user->img('icon_topic_unapproved', 'TOPIC_UNAPPROVED'),
            'LAST_POST_IMG'        => $user->img('icon_topic_latest', 'VIEW_LATEST_POST'),
    
            'U_SEARCH_WORDS'    => $u_search,
        ));
        
        if ($sql_where)
        {
            // @todo Joining this query to the one below?
            $sql = 'SELECT zebra_id, friend, foe
                FROM ' . ZEBRA_TABLE . '
                WHERE user_id = ' . $user->data['user_id'];
            $result = $db->sql_query($sql);

            $zebra = array();
            while ($row = $db->sql_fetchrow($result))
            {
                $zebra[($row['friend']) ? 'friend' : 'foe'][] = $row['zebra_id'];
            }
            $db->sql_freeresult($result);

            $sql = 'SELECT p.*, f.forum_id, f.forum_name, t.*, 
                           u.username, u.username_clean, u.user_sig_bbcode_uid, u.user_avatar, u.user_avatar_type
                    FROM ' . POSTS_TABLE . ' p
                        LEFT JOIN ' . TOPICS_TABLE . ' t ON (p.topic_id = t.topic_id)
                        LEFT JOIN ' . FORUMS_TABLE . ' f ON (p.forum_id = f.forum_id)
                        LEFT JOIN ' . USERS_TABLE . " u ON (t.topic_poster = u.user_id)
                    WHERE $sql_where";

            $sql .= ' ORDER BY ' . $sort_by_sql['t'] . ' ' . (($sort_dir == 'd') ? 'DESC' : 'ASC');
            $result = $db->sql_query($sql);
            $result_topic_id = 0;
            
            $rowset = array();
    
            $bbcode_bitfield = $text_only_message = '';
            $attach_list = array();

            while ($row = $db->sql_fetchrow($result))
            {
                // We pre-process some variables here for later usage
                $row['post_text'] = censor_text($row['post_text']);

                $text_only_message = $row['post_text'];
                // make list items visible as such
                if ($row['bbcode_uid'])
                {
                    $text_only_message = str_replace('[*:' . $row['bbcode_uid'] . ']', '&sdot;&nbsp;', $text_only_message);
                    // no BBCode in text only message
                    strip_bbcode($text_only_message, $row['bbcode_uid']);
                }

                if ($return_chars == -1 || utf8_strlen($text_only_message) < ($return_chars + 3))
                {
                    $row['display_text_only'] = false;
                    $bbcode_bitfield = $bbcode_bitfield | base64_decode($row['bbcode_bitfield']);

                    // Does this post have an attachment? If so, add it to the list
                    if ($row['post_attachment'] && $config['allow_attachments'])
                    {
                        $attach_list[$row['forum_id']][] = $row['post_id'];
                    }
                }
                else
                {
                    $row['post_text'] = $text_only_message;
                    $row['display_text_only'] = true;
                }

                $rowset[] = $row;
            }
            $db->sql_freeresult($result);

            unset($text_only_message);

            // Instantiate BBCode if needed
            if ($bbcode_bitfield !== '')
            {
                include_once($phpbb_root_path . 'includes/bbcode.' . $phpEx);
                $bbcode = new bbcode(base64_encode($bbcode_bitfield));
            }

            // Pull attachment data
            if (sizeof($attach_list))
            {
                $use_attach_list = $attach_list;
                $attach_list = array();

                foreach ($use_attach_list as $forum_id => $_list)
                {
                    if ($auth->acl_get('u_download') && $auth->acl_get('f_download', $forum_id))
                    {
                        $attach_list = array_merge($attach_list, $_list);
                    }
                }
            }

            if (sizeof($attach_list))
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
                }
                $db->sql_freeresult($result);
            }
            
            if ($hilit)
            {
                // Remove bad highlights
                $hilit_array = array_filter(explode('|', $hilit), 'strlen');
                foreach ($hilit_array as $key => $value)
                {
                    $hilit_array[$key] = str_replace('\*', '\w*?', preg_quote($value, '#'));
                    $hilit_array[$key] = preg_replace('#(^|\s)\\\\w\*\?(\s|$)#', '$1\w+?$2', $hilit_array[$key]);
                }
                $hilit = implode('|', $hilit_array);
            }
    
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
    
                if ((isset($zebra['foe']) && in_array($row['poster_id'], $zebra['foe'])) && (!$view || $view != 'show' || $post_id != $row['post_id']))
                {
                    $template->assign_block_vars('searchresults', array(
                        'S_IGNORE_POST' => true,

                        'L_IGNORE_POST' => sprintf($user->lang['POST_BY_FOE'], $row['username'], "<a href=\"$u_search&amp;start=$start&amp;p=" . $row['post_id'] . '&amp;view=show#p' . $row['post_id'] . '">', '</a>'))
                    );

                    continue;
                }

                // Replace naughty words such as farty pants
                $row['post_subject'] = censor_text($row['post_subject']);

                if ($row['display_text_only'])
                {
                    // now find context for the searched words
                    $row['post_text'] = get_context($row['post_text'], array_filter(explode('|', $hilit), 'strlen'), $return_chars);
                    $row['post_text'] = bbcode_nl2br($row['post_text']);
                }
                else
                {
                    // Second parse bbcode here
                    if ($row['bbcode_bitfield'])
                    {
                        $bbcode->bbcode_second_pass($row['post_text'], $row['bbcode_uid'], $row['bbcode_bitfield']);
                    }

                    $row['post_text'] = bbcode_nl2br($row['post_text']);
                    $row['post_text'] = smiley_text($row['post_text']);

                    if (!empty($attachments[$row['post_id']]))
                    {
                        parse_attachments($forum_id, $row['post_text'], $attachments[$row['post_id']], $update_count);

                        // we only display inline attachments
                        unset($attachments[$row['post_id']]);
                    }
                }
                
                $post_sort_dir   = (!empty($user->data['user_post_sortby_dir'])) ? $user->data['user_post_sortby_dir'] : 'a';
                
                $tpl_ary = array(
                    'POST_AUTHOR_FULL'   => get_username_string('full', $row['poster_id'], $row['username'], $row['user_colour'], $row['post_username']),
                    'POST_AUTHOR_COLOUR' => get_username_string('colour', $row['poster_id'], $row['username'], $row['user_colour'], $row['post_username']),
                    'POST_AUTHOR'        => get_username_string('username', $row['poster_id'], $row['username'], $row['user_colour'], $row['post_username']),
                    'U_POST_AUTHOR'      => get_username_string('profile', $row['poster_id'], $row['username'], $row['user_colour'], $row['post_username']),

                    'LAST_POST_TIME'     => $row['topic_last_post_time'],
                    'post_approved'      => $row['post_approved'],
                    'POSTER_ID'          => $row['poster_id'],
                    
                    'POST_SUBJECT'       => $row['post_subject'],
//                  'POST_DATE'          => (!empty($row['post_time'])) ? $user->format_date($row['post_time']) : '',
                    'POST_DATE'          => $row['post_time'],
                    'MESSAGE'            => $row['post_text']
                );
    
                $template->assign_block_vars('searchresults', array_merge($tpl_ary, array(
                    'FORUM_ID'      => $forum_id,
                    'TOPIC_ID'      => $result_topic_id,
                    'POST_ID'       => $row['post_id'],
                    'FORUM_TITLE'   => $row['forum_name'],
                    'TOPIC_TITLE'   => $topic_title,
                    'TOPIC_REPLIES' => $replies,
                    'TOPIC_VIEWS'   => $row['topic_views'],
    
                    'U_VIEW_TOPIC'  => $view_topic_url,
                    'U_VIEW_FORUM'  => append_sid("{$phpbb_root_path}viewforum.$phpEx", 'f=' . $forum_id),
                    'U_VIEW_POST'   => (!empty($row['post_id'])) ? append_sid("{$phpbb_root_path}viewtopic.$phpEx", "f=$forum_id&amp;t=" . $row['topic_id'] . '&amp;p=' . $row['post_id'] . (($u_hilit) ? '&amp;hilit=' . $u_hilit : '')) . '#p' . $row['post_id'] : '')
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
    $post_list = array();
    if(isset($template->_tpldata['searchresults']))
    {
        foreach($template->_tpldata['searchresults'] as $row)
        {
            $forum_id = $row['FORUM_ID'];
            $user_avatar_url = get_user_avatar_url($row['user_avatar'], $row['user_avatar_type']);
            $topic_tracking = get_complete_topic_tracking($forum_id, $row['TOPIC_ID']);
            $new_post = $topic_tracking[$row['TOPIC_ID']] < $row['LAST_POST_TIME'] ? true : false;
            $can_ban_user = $auth->acl_get('m_ban') && $row['POSTER_ID'] != $user->data['user_id'];
            
            if (empty($forum_id))
            {
                $user->setup('viewforum');
                $forum_id = 0;
                $row['FORUM_TITLE'] = $user->lang['ANNOUNCEMENTS'];
            }
            
            $xmlrpc_post = new xmlrpcval(array(
                'forum_id'          => new xmlrpcval($forum_id),
                'forum_name'        => new xmlrpcval(html_entity_decode($row['FORUM_TITLE']), 'base64'),
                'topic_id'          => new xmlrpcval($row['TOPIC_ID']),
                'topic_title'       => new xmlrpcval(html_entity_decode(strip_tags(censor_text($row['TOPIC_TITLE']))), 'base64'),
                'post_id'           => new xmlrpcval($row['POST_ID']),
                'post_title'        => new xmlrpcval(html_entity_decode(strip_tags(censor_text($row['POST_SUBJECT']))), 'base64'),
                'short_content'     => new xmlrpcval(get_short_content($row['POST_ID']), 'base64'),
                'icon_url'          => new xmlrpcval($user_avatar_url),
                'reply_number'      => new xmlrpcval($row['TOPIC_REPLIES']+1, 'int'),
                'view_number'       => new xmlrpcval(intval($row['TOPIC_VIEWS']), 'int'),
                'new_post'          => new xmlrpcval($new_post, 'boolean'),
                'post_time'         => new xmlrpcval(mobiquo_iso8601_encode($row['POST_DATE']), 'dateTime.iso8601'),
                'can_delete'        => new xmlrpcval($auth->acl_get('m_delete', $forum_id), 'boolean'),
                'can_move'          => new xmlrpcval($auth->acl_get('m_move', $forum_id), 'boolean'),
                'can_approve'       => new xmlrpcval($auth->acl_get('m_approve', $forum_id) && !$row['post_approved'], 'boolean'),
                'is_approved'       => new xmlrpcval($row['post_approved'] ? true : false, 'boolean'),
                'can_ban'           => new xmlrpcval($can_ban_user, 'boolean'),
            ), 'struct');
    
            $post_list[] = $xmlrpc_post;
        }
    }
    
    return new xmlrpcresp(new xmlrpcval($post_list, 'array'));
}
