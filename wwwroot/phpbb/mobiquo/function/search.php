<?php

defined('IN_MOBIQUO') or exit;

// Start session management
$user->session_begin();
$auth->acl($user->data);
$user->setup('search');

$phpbb_home = generate_board_url().'/';

header('Mobiquo_is_login:'.($user->data['is_registered'] ? 'true' : 'false'));

if ($user->data['user_new_privmsg'])
{
    include_once($phpbb_root_path . 'includes/functions_privmsgs.' . $phpEx);
    place_pm_into_folder($global_privmsgs_rules);
}

ob_clean();

global $phpbb_extension_manager;
if (!$phpbb_extension_manager->is_enabled('tapatalk/tapatalk') && $request_file !== 'get_config')
{
    $user->add_lang('acp/extensions');
    trigger_error($user->lang('EXTENSION_DISABLED','Tapatalk'));
};

$phpbb_root_path = (defined('PHPBB_ROOT_PATH')) ? PHPBB_ROOT_PATH : './';
$phpEx = substr(strrchr(__FILE__, '.'), 1);

// Define initial vars
$mode           = request_var('mode', '');
$search_id      = request_var('search_id', '');
$start          = max(request_var('start', 0), 0);
$post_id        = request_var('p', 0);
$topic_id       = request_var('t', 0);
$view           = request_var('view', '');

$submit         = request_var('submit', false);
$keywords       = utf8_normalize_nfc(request_var('keywords', '', true));
$add_keywords   = utf8_normalize_nfc(request_var('add_keywords', '', true));
$author         = request_var('author', '', true);
$author_id      = request_var('author_id', 0);
$show_results   = ($topic_id) ? 'posts' : request_var('sr', 'posts');
$show_results   = ($show_results == 'posts') ? 'posts' : 'topics';
$search_terms   = request_var('terms', 'all');
$search_fields  = request_var('sf', 'all');
$search_child   = request_var('sc', true);

$sort_days      = request_var('st', 0);
$sort_key       = request_var('sk', 't');
$sort_dir       = request_var('sd', 'd');

$return_chars   = request_var('ch', ($topic_id) ? -1 : 300);
//$search_forum   = request_var('fid', array(0));

// We put login boxes for the case if search_id is newposts, egosearch or unreadposts
// because a guest should be able to log in even if guests search is not permitted

//translate Tapatalk parameters to system's
$exclude_forum	= request_var('exclude', array(0));
$search_forum   = request_var('fid', array('0'));
if(is_array($search_forum))
{
    $sf = array();
    foreach($search_forum as $value)
    {
        if (is_numeric($value))
            $sf[] = $value;
        else
        {
            $sf[] = tt_get_forum_id_by_name($value);
        }
    }
    $search_forum = $sf;
}


switch ($search_id)
{
    // Egosearch is an author search
    case 'egosearch':
        $author_id = $user->data['user_id'];
        if ($user->data['user_id'] == ANONYMOUS)
        {
            trigger_error($user->lang['LOGIN_EXPLAIN_EGOSEARCH']);
        }
    break;

    // Search for unread posts needs to be allowed and user to be logged in if topics tracking for guests is disabled
    case 'unreadposts':
        if (!$config['load_unreads_search'])
        {
            trigger_error('NO_SEARCH_UNREADS');
        }
        else if (!$config['load_anon_lastread'] && !$user->data['is_registered'])
        {
            trigger_error($user->lang['LOGIN_EXPLAIN_UNREADSEARCH']);
        }
    break;

    // The "new posts" search uses user_lastvisit which is user based, so it should require user to log in.
    case 'newposts':
        if ($user->data['user_id'] == ANONYMOUS)
        {
            trigger_error($user->lang['LOGIN_EXPLAIN_NEWPOSTS']);
        }
    break;

    default:
        // There's nothing to do here for now ;)
    break;
}

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

// It is applicable if the configuration setting is non-zero, and the user cannot
// ignore the flood setting, and the search is a keyword search.
$interval = ($user->data['user_id'] == ANONYMOUS) ? $config['search_anonymous_interval'] : $config['search_interval'];
if ($interval && !in_array($search_id, array('unreadposts', 'unanswered', 'active_topics', 'egosearch')) && !$auth->acl_get('u_ignoreflood'))
{
    if ($user->data['user_last_search'] > time() - $interval)
    {
        trigger_error($user->lang('NO_SEARCH_TIME', (int) ($user->data['user_last_search'] + $interval - time())));
    }
}

// Define some vars
$limit_days     = array(0 => $user->lang['ALL_RESULTS'], 1 => $user->lang['1_DAY'], 7 => $user->lang['7_DAYS'], 14 => $user->lang['2_WEEKS'], 30 => $user->lang['1_MONTH'], 90 => $user->lang['3_MONTHS'], 180 => $user->lang['6_MONTHS'], 365 => $user->lang['1_YEAR']);
$sort_by_text   = array('a' => $user->lang['SORT_AUTHOR'], 't' => $user->lang['SORT_TIME'], 'f' => $user->lang['SORT_FORUM'], 'i' => $user->lang['SORT_TOPIC_TITLE'], 's' => $user->lang['SORT_POST_SUBJECT']);

$s_limit_days = $s_sort_key = $s_sort_dir = $u_sort_param = '';
gen_sort_selects($limit_days, $sort_by_text, $sort_days, $sort_key, $sort_dir, $s_limit_days, $s_sort_key, $s_sort_dir, $u_sort_param);

$phpbb_content_visibility = $phpbb_container->get('content.visibility');
$pagination = $phpbb_container->get('pagination');

if ($keywords || $author || $author_id || $search_id || $submit)
{
    // clear arrays
    $id_ary = array();

    // If we are looking for authors get their ids
    $author_id_ary = array();
    $sql_author_match = '';
    if ($author_id)
    {
        $author_id_ary[] = $author_id;
    }
    else if ($author)
    {
        if ((strpos($author, '*') !== false) && (utf8_strlen(str_replace(array('*', '%'), '', $author)) < $config['min_search_author_chars']))
        {
            trigger_error($user->lang('TOO_FEW_AUTHOR_CHARS', (int) $config['min_search_author_chars']));
        }

        $sql_where = (strpos($author, '*') !== false) ? ' username_clean ' . $db->sql_like_expression(str_replace('*', $db->get_any_char(), utf8_clean_string($author))) : " username_clean = '" . $db->sql_escape(utf8_clean_string($author)) . "'";

        $sql = 'SELECT user_id
            FROM ' . USERS_TABLE . "
            WHERE $sql_where
                AND user_type <> " . USER_IGNORE;
        $result = $db->sql_query_limit($sql, 100);

        while ($row = $db->sql_fetchrow($result))
        {
            $author_id_ary[] = (int) $row['user_id'];
        }
        $db->sql_freeresult($result);

        $sql_where = (strpos($author, '*') !== false) ? ' post_username ' . $db->sql_like_expression(str_replace('*', $db->get_any_char(), utf8_clean_string($author))) : " post_username = '" . $db->sql_escape(utf8_clean_string($author)) . "'";

        $sql = 'SELECT 1 as guest_post
            FROM ' . POSTS_TABLE . "
            WHERE $sql_where
                AND poster_id = " . ANONYMOUS;
        $result = $db->sql_query_limit($sql, 1);
        $found_guest_post = $db->sql_fetchfield('guest_post');
        $db->sql_freeresult($result);

        if ($found_guest_post)
        {
            $author_id_ary[] = ANONYMOUS;
            $sql_author_match = (strpos($author, '*') !== false) ? ' ' . $db->sql_like_expression(str_replace('*', $db->get_any_char(), utf8_clean_string($author))) : " = '" . $db->sql_escape(utf8_clean_string($author)) . "'";
        }

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
    
    // add for tapatalk
    if (isset($config['mobiquo_hide_forum_id']))
    {
        $hideforum = explode(',',$config['mobiquo_hide_forum_id']);
        $ex_fid_ary = array_unique(array_merge($ex_fid_ary, $hideforum));
    }
    if ($exclude_forum)
    {
        $ex_fid_ary = array_unique(array_merge($ex_fid_ary, $exclude_forum));
    }
    
    $not_in_fid = (sizeof($ex_fid_ary)) ? 'WHERE ' . $db->sql_in_set('f.forum_id', $ex_fid_ary, true) . " OR (f.forum_password <> '' AND fa.user_id <> " . (int) $user->data['user_id'] . ')' : "";

    $sql = 'SELECT f.forum_id, f.forum_name, f.parent_id, f.forum_type, f.right_id, f.forum_password, f.forum_flags, fa.user_id
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

        // Exclude forums from active topics
        if (!($row['forum_flags'] & FORUM_FLAG_ACTIVE_TOPICS) && ($search_id == 'active_topics'))
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

    // find out in which forums the user is allowed to view posts
    $m_approve_posts_fid_sql = $phpbb_content_visibility->get_global_visibility_sql('post', $ex_fid_ary, 'p.');
    $m_approve_topics_fid_sql = $phpbb_content_visibility->get_global_visibility_sql('topic', $ex_fid_ary, 't.');

    if ($reset_search_forum)
    {
        $search_forum = array();
    }

    // Select which method we'll use to obtain the post_id or topic_id information
    $search_type = $config['search_type'];
    if (!class_exists($search_type))
    {
        trigger_error('NO_SUCH_SEARCH_MODULE');
    }
    // We do some additional checks in the module to ensure it can actually be utilised
    $error = false;
    $search = new $search_type($error, $phpbb_root_path, $phpEx, $auth, $config, $db, $user);

    if ($error)
    {
        trigger_error($error);
    }

    $common_words = $search->get_common_words();

    // let the search module split up the keywords
    if ($keywords)
    {
        $correct_query = $search->split_keywords($keywords, $search_terms);
        if (!$correct_query || (!$search->get_search_query() && !sizeof($author_id_ary) && !$search_id))
        {
            $ignored = (sizeof($common_words)) ? sprintf($user->lang['IGNORED_TERMS_EXPLAIN'], implode(' ', $common_words)) . '<br />' : '';
            $word_length = $search->get_word_length();
            if ($word_length)
            {
                trigger_error($ignored . $user->lang('NO_KEYWORDS', $user->lang('CHARACTERS', (int) $word_length['min']), $user->lang('CHARACTERS', (int) $word_length['max'])));
            }
            else
            {
                trigger_error($ignored);
            }
        }
    }

    if (!$keywords && sizeof($author_id_ary))
    {
        // if it is an author search we want to show topics by default
        $show_results = ($topic_id) ? 'posts' : request_var('sr', ($search_id == 'egosearch') ? 'topics' : 'posts');
        $show_results = ($show_results == 'posts') ? 'posts' : 'topics';
    }

    // define some variables needed for retrieving post_id/topic_id information
    $sort_by_sql = array('a' => 'u.username_clean', 't' => (($show_results == 'posts') ? 'p.post_time' : 't.topic_last_post_time'), 'f' => 'f.forum_id', 'i' => 't.topic_title', 's' => (($show_results == 'posts') ? 'p.post_subject' : 't.topic_title'));

    // pre-made searches
    $sql = $field = $l_search_title = '';
    if ($search_id)
    {
        switch ($search_id)
        {
            // Oh holy Bob, bring us some activity...
            case 'active_topics':
                $l_search_title = $user->lang['SEARCH_ACTIVE_TOPICS'];
                $show_results = 'topics';
                $sort_key = 't';
                $sort_dir = 'd';
                $sort_days = request_var('st', 7);
                $sort_by_sql['t'] = 't.topic_last_post_time';

                gen_sort_selects($limit_days, $sort_by_text, $sort_days, $sort_key, $sort_dir, $s_limit_days, $s_sort_key, $s_sort_dir, $u_sort_param);
                $s_sort_key = $s_sort_dir = '';

                $last_post_time_sql = ($sort_days) ? ' AND t.topic_last_post_time > ' . (time() - ($sort_days * 24 * 3600)) : '';

                $sql = 'SELECT t.topic_last_post_time, t.topic_id
                    FROM ' . TOPICS_TABLE . " t
                    WHERE t.topic_moved_id = 0
                        $last_post_time_sql
                        AND " . $m_approve_topics_fid_sql . '
                        ' . ((sizeof($ex_fid_ary)) ? ' AND ' . $db->sql_in_set('t.forum_id', $ex_fid_ary, true) : '') . '
                    ORDER BY t.topic_last_post_time DESC';
                $field = 'topic_id';
            break;

            case 'unreadposts':
                $l_search_title = $user->lang['SEARCH_UNREAD'];
                // force sorting
                $show_results = 'topics';
                $sort_key = 't';
                $sort_by_sql['t'] = 't.topic_last_post_time';
                $sql_sort = 'ORDER BY ' . $sort_by_sql[$sort_key] . (($sort_dir == 'a') ? ' ASC' : ' DESC');

                $sql_where = 'AND t.topic_moved_id = 0
                    AND ' . $m_approve_topics_fid_sql . '
                    ' . ((sizeof($ex_fid_ary)) ? 'AND ' . $db->sql_in_set('t.forum_id', $ex_fid_ary, true) : '');

                gen_sort_selects($limit_days, $sort_by_text, $sort_days, $sort_key, $sort_dir, $s_limit_days, $s_sort_key, $s_sort_dir, $u_sort_param);
                $s_sort_key = $s_sort_dir = $u_sort_param = $s_limit_days = '';
            break;
            
            case 'subscribedtopics':
                // force sorting
                $show_results = 'topics';
                $sort_key = 't';
                $sort_dir = 'd';
                $sort_by_sql['t'] = 't.topic_last_post_time';
                $sql_sort = 'ORDER BY ' . $sort_by_sql[$sort_key] . (($sort_dir == 'a') ? ' ASC' : ' DESC');

                gen_sort_selects($limit_days, $sort_by_text, $sort_days, $sort_key, $sort_dir, $s_limit_days, $s_sort_key, $s_sort_dir, $u_sort_param);
                $s_sort_key = $s_sort_dir = $u_sort_param = $s_limit_days = '';

                $sql = 'SELECT tw.topic_id
                    FROM ' . TOPICS_WATCH_TABLE . ' tw
                        LEFT JOIN ' . TOPICS_TABLE . ' t ON (tw.topic_id = t.topic_id)
                        LEFT JOIN ' . FORUMS_TABLE . ' f ON (t.forum_id = f.forum_id)
                    WHERE tw.user_id = ' . $user->data['user_id'] . ' AND
                        ' . str_replace(array('p.', 'post_'), array('t.', 'topic_'), $m_approve_topics_fid_sql) . '
                        ' . ((sizeof($ex_fid_ary)) ? 'AND ' . $db->sql_in_set('t.forum_id', $ex_fid_ary, true) : '') . "
                    $sql_sort";
                $field = 'topic_id';
                break;
                
            case 'newposts':
                $l_search_title = $user->lang['SEARCH_NEW'];
                // force sorting
                $show_results = (request_var('sr', 'topics') == 'posts') ? 'posts' : 'topics';
                $sort_key = 't';
                $sort_dir = 'd';
                $sort_by_sql['t'] = ($show_results == 'posts') ? 'p.post_time' : 't.topic_last_post_time';
                $sql_sort = 'ORDER BY ' . $sort_by_sql[$sort_key] . (($sort_dir == 'a') ? ' ASC' : ' DESC');

                gen_sort_selects($limit_days, $sort_by_text, $sort_days, $sort_key, $sort_dir, $s_limit_days, $s_sort_key, $s_sort_dir, $u_sort_param);
                $s_sort_key = $s_sort_dir = $u_sort_param = $s_limit_days = '';

                if ($show_results == 'posts')
                {
                    $sql = 'SELECT p.post_id
                        FROM ' . POSTS_TABLE . ' p
                        WHERE p.post_time > ' . $user->data['user_lastvisit'] . '
                            AND ' . $m_approve_posts_fid_sql . '
                            ' . ((sizeof($ex_fid_ary)) ? ' AND ' . $db->sql_in_set('p.forum_id', $ex_fid_ary, true) : '') . "
                        $sql_sort";
                    $field = 'post_id';
                }
                else
                {
                    $sql = 'SELECT t.topic_id
                        FROM ' . TOPICS_TABLE . ' t
                        WHERE t.topic_last_post_time > ' . $user->data['user_lastvisit'] . '
                            AND t.topic_moved_id = 0
                            AND ' . $m_approve_topics_fid_sql . '
                            ' . ((sizeof($ex_fid_ary)) ? 'AND ' . $db->sql_in_set('t.forum_id', $ex_fid_ary, true) : '') . "
                        $sql_sort";

                    $field = 'topic_id';
                }
            break;

            case 'egosearch':
                $l_search_title = $user->lang['SEARCH_SELF'];
            break;
        }
    }

    // show_results should not change after this
    $per_page = ($show_results == 'posts') ? $config['posts_per_page'] : $config['topics_per_page'];
    $total_match_count = 0;

    // Set limit for the $total_match_count to reduce server load
    $total_matches_limit = 1000;
    $found_more_search_matches = false;

    if ($search_id)
    {
        if ($sql)
        {
            // Only return up to $total_matches_limit+1 ids (the last one will be removed later)
            $result = $db->sql_query_limit($sql, $total_matches_limit + 1);

            while ($row = $db->sql_fetchrow($result))
            {
                $id_ary[] = (int) $row[$field];
            }
            $db->sql_freeresult($result);
        }
        else if ($search_id == 'unreadposts')
        {
            // Only return up to $total_matches_limit+1 ids (the last one will be removed later)
            $id_ary = array_keys(get_unread_topics($user->data['user_id'], $sql_where, $sql_sort, $total_matches_limit + 1));
        }
        else
        {
            $search_id = '';
        }

        $total_match_count = sizeof($id_ary);
        if ($total_match_count)
        {
            // Limit the number to $total_matches_limit for pre-made searches
            if ($total_match_count > $total_matches_limit)
            {
                $found_more_search_matches = true;
                $total_match_count = $total_matches_limit;
            }

            // Make sure $start is set to the last page if it exceeds the amount
            $start = $pagination->validate_start($start, $per_page, $total_match_count);

            $id_ary = array_slice($id_ary, $start, $per_page);
        }
        else
        {
            // Set $start to 0 if no matches were found
            $start = 0;
        }
    }

    // make sure that some arrays are always in the same order
    sort($ex_fid_ary);
    sort($author_id_ary);

    if ($search->get_search_query())
    {
        $total_match_count = $search->keyword_search($show_results, $search_fields, $search_terms, $sort_by_sql, $sort_key, $sort_dir, $sort_days, $ex_fid_ary, $m_approve_posts_fid_sql, $topic_id, $author_id_ary, $sql_author_match, $id_ary, $start, $per_page);
    }
    else if (sizeof($author_id_ary))
    {
        $firstpost_only = ($search_fields === 'firstpost' || $search_fields == 'titleonly') ? true : false;
        $total_match_count = $search->author_search($show_results, $firstpost_only, $sort_by_sql, $sort_key, $sort_dir, $sort_days, $ex_fid_ary, $m_approve_posts_fid_sql, $topic_id, $author_id_ary, $sql_author_match, $id_ary, $start, $per_page);
    }

    $sql_where = '';

    if (sizeof($id_ary))
    {
        $sql_where .= $db->sql_in_set(($show_results == 'posts') ? 'p.post_id' : 't.topic_id', $id_ary);
        $sql_where .= (sizeof($ex_fid_ary)) ? ' AND (' . $db->sql_in_set('f.forum_id', $ex_fid_ary, true) . ' OR f.forum_id IS NULL)' : '';
        $sql_where .= ' AND ' . (($show_results == 'posts') ? $m_approve_posts_fid_sql : $m_approve_topics_fid_sql);
    }

    if ($sql_where)
    {
        if ($show_results == 'posts')
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

            $sql_array = array(
                'SELECT'    => 'p.*, f.forum_id, f.forum_name, t.*, u.username, u.username_clean, u.user_sig, u.user_sig_bbcode_uid, u.user_colour',
                'FROM'      => array(
                    POSTS_TABLE     => 'p',
                ),
                'LEFT_JOIN' => array(
                    array(
                        'FROM'  => array(TOPICS_TABLE => 't'),
                        'ON'    => 'p.topic_id = t.topic_id',
                    ),
                    array(
                        'FROM'  => array(FORUMS_TABLE => 'f'),
                        'ON'    => 'p.forum_id = f.forum_id',
                    ),
                    array(
                        'FROM'  => array(USERS_TABLE => 'u'),
                        'ON'    => 'p.poster_id = u.user_id',
                    ),
                ),
                'WHERE' => $sql_where,
                'ORDER_BY' => $sort_by_sql[$sort_key] . ' ' . (($sort_dir == 'd') ? 'DESC' : 'ASC'),
            );

            $sql = $db->sql_build_query('SELECT', $sql_array);
        }
        else
        {
            $sql_from = TOPICS_TABLE . ' t
                LEFT JOIN ' . FORUMS_TABLE . ' f ON (f.forum_id = t.forum_id)
                ' . (($sort_key == 'a') ? ' LEFT JOIN ' . USERS_TABLE . ' u ON (u.user_id = t.topic_poster) ' : '');
            $sql_select = 't.*, f.forum_id, f.forum_name';

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
                $tracking_topics = $request->variable($config['cookie_name'] . '_track', '', true, \phpbb\request\request_interface::COOKIE);
                $tracking_topics = ($tracking_topics) ? tracking_unserialize($tracking_topics) : array();
            }

            $sql = "SELECT $sql_select
                FROM $sql_from
                WHERE $sql_where";
            $sql .= ' ORDER BY ' . $sort_by_sql[$sort_key] . ' ' . (($sort_dir == 'd') ? 'DESC' : 'ASC');
        }
        $result = $db->sql_query($sql);
        $result_topic_id = 0;

        $rowset = array();

        if ($show_results == 'topics')
        {
            $forums = $rowset = $shadow_topic_list = array();
            while ($row = $db->sql_fetchrow($result))
            {
                $row['forum_id'] = (int) $row['forum_id'];
                $row['topic_id'] = (int) $row['topic_id'];

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
                        'topic_status'      => $rowset[$orig_topic_id]['topic_status'],
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
                    $topic_tracking_info[$forum_id] = get_topic_tracking($forum_id, $forum['topic_list'], $forum['rowset'], array($forum_id => $forum['mark_time']));
                }
                else if ($config['load_anon_lastread'] || $user->data['is_registered'])
                {
                    $topic_tracking_info[$forum_id] = get_complete_topic_tracking($forum_id, $forum['topic_list']);

                    if (!$user->data['is_registered'])
                    {
                        $user->data['user_lastmark'] = (isset($tracking_topics['l'])) ? (int) (base_convert($tracking_topics['l'], 36, 10) + $config['board_startdate']) : 0;
                    }
                }
            }
            unset($forums);
        }
        else
        {
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
        }
        
        $results = array();
        foreach ($rowset as $row)
        {
            $forum_id = $row['forum_id'];
            $result_topic_id = $row['topic_id'];
            $topic_title = censor_text($row['topic_title']);
            $replies = $phpbb_content_visibility->get_count('topic_posts', $row, $forum_id) - 1;

            $view_topic_url_params = "f=$forum_id&amp;t=$result_topic_id" . (($u_hilit) ? "&amp;hilit=$u_hilit" : '');
            $view_topic_url = append_sid("{$phpbb_root_path}viewtopic.$phpEx", $view_topic_url_params);

            if ($show_results == 'topics')
            {
                if ($config['load_db_track'] && $author_id === $user->data['user_id'])
                {
                    $row['topic_posted'] = 1;
                }

                $folder_img = $folder_alt = $topic_type = '';
                
                $unread_topic = (isset($topic_tracking_info[$forum_id][$row['topic_id']]) && $row['topic_last_post_time'] > $topic_tracking_info[$forum_id][$row['topic_id']]) ? true : false;

                $topic_unapproved = (($row['topic_visibility'] == ITEM_UNAPPROVED || $row['topic_visibility'] == ITEM_REAPPROVE) && $auth->acl_get('m_approve', $forum_id)) ? true : false;
                $posts_unapproved = ($row['topic_visibility'] == ITEM_APPROVED && $row['topic_posts_unapproved'] && $auth->acl_get('m_approve', $forum_id)) ? true : false;
                $topic_deleted = $row['topic_visibility'] == ITEM_DELETED;
                $u_mcp_queue = ($topic_unapproved || $posts_unapproved) ? append_sid("{$phpbb_root_path}mcp.$phpEx", 'i=queue&amp;mode=' . (($topic_unapproved) ? 'approve_details' : 'unapproved_posts') . "&amp;t=$result_topic_id", true, $user->session_id) : '';
                $u_mcp_queue = (!$u_mcp_queue && $topic_deleted) ? append_sid("{$phpbb_root_path}mcp.$phpEx", "i=queue&amp;mode=deleted_topics&amp;t=$result_topic_id", true, $user->session_id) : '';

                $row['topic_title'] = preg_replace('#(?!<.*)(?<!\w)(' . $hilit . ')(?!\w|[^<>]*(?:</s(?:cript|tyle))?>)#is', '<span class="posthilit">$1</span>', $row['topic_title']);

            }
            else
            {
                if ((isset($zebra['foe']) && in_array($row['poster_id'], $zebra['foe'])) && (!$view || $view != 'show' || $post_id != $row['post_id']))
                {
                    continue;
                }
                // Replace naughty words such as farty pants
                $row['post_subject'] = censor_text($row['post_subject']);
            }
            if($show_results == 'posts')
            {
                $forum_id = $forum_id;
                $author_name = get_username_string('username', $row['poster_id'], $row['username'], $row['user_colour'], $row['post_username']);
                $post_author = tt_get_user_by_name($author_name);
                $author_id = $post_author['user_id'];
                
                $avatars = get_user_avatars($author_id);
                
                $can_approve = $auth->acl_get('m_approve', $forum_id) && !$row['post_visibility'];
                $can_move = $auth->acl_get('m_split', $forum_id);
                $can_ban = $auth->acl_get('m_ban') && $author_id != $user->data['user_id'];
                $can_delete = ($user->data['is_registered'] && ($auth->acl_get('m_delete', $forum_id) || (
                    $user->data['user_id'] == $author_id &&
                    $auth->acl_get('f_delete', $forum_id) &&
                    $row['topic_last_post_id'] == $row['post_id'] &&
                    ($row['post_time'] > time() - ($config['delete_time'] * 60) || !$config['delete_time']) &&
                    // we do not want to allow removal of the last post if a moderator locked it!
                    !$row['post_edit_locked']
                )));
                
                if ($row['topic_status'] == ITEM_MOVED)
                {
                    $topic_id = $row['topic_moved_id'];
                    $unread_topic = false;
                }
                else
                {
                    $unread_topic = (isset($topic_tracking_info[$topic_id]) && $row['topic_last_post_time'] > $topic_tracking_info[$topic_id]) ? true : false;
                }
                        
                $post = array(
                    'forum_id'         => (string)$forum_id,
                    'forum_name'       => basic_clean($row['forum_name']),
                    'topic_id'         => (string)$row['topic_id'],
                    'topic_title'      => basic_clean($topic_title),
                    'post_id'          => (string)$row['post_id'],
                    'post_title'       => basic_clean($row['post_subject']),
                    'post_author_id'   => $author_id,
                    'post_author_name' => basic_clean($author_name),
                    'post_time'        => mobiquo_iso8601_encode($row['post_time']),
                    'timestamp'        => $row['post_time'],
                    'icon_url'         => $avatars[$author_id],
                    'short_content'    => get_short_content($row['post_id']),
                    'is_approved'      => (boolean)$row['post_visibility'],
                );
                //if ($can_approve)   $post['can_approve'] = true;
                //if ($can_delete)    $post['can_delete']  = true;
                //if ($can_move)      $post['can_move']    = true;
                //if ($can_ban)       $post['can_ban']     = true;
                $results[] = $post; 
            }
            else
            {
                $forum_id = $forum_id;
                $author_name = $row['topic_last_poster_name'];
                $author_id = $row['topic_last_poster_id'];
                
                $avatars = get_user_avatars($author_id);
                
                $can_approve = $auth->acl_get('m_approve', $forum_id) && !$row['post_visibility'];
                $can_move = $auth->acl_get('m_split', $forum_id);
                $can_ban = $auth->acl_get('m_ban') && $author_id != $user->data['user_id'];
                $can_delete = ($user->data['is_registered'] && ($auth->acl_get('m_delete', $forum_id) || (
                    $user->data['user_id'] == $author_id &&
                    $auth->acl_get('f_delete', $forum_id) &&
                    $row['topic_last_post_id'] == $row['post_id'] &&
                    ($row['post_time'] > time() - ($config['delete_time'] * 60) || !$config['delete_time']) &&
                    // we do not want to allow removal of the last post if a moderator locked it!
                    !$row['post_edit_locked']
                )));
                $last_post_id = $row['topic_last_post_id'];
                $topic = array(
                    'forum_id'         => (string)$forum_id,
                    'forum_name'       => basic_clean($row['forum_name']),
                    'topic_id'         => (string)$result_topic_id,
                    'topic_title'      => basic_clean($row['topic_title']),
                    'post_author_id'   => (string)$author_id,
                    'post_author_name' => basic_clean($author_name),
                    'post_time'        => mobiquo_iso8601_encode($row['topic_last_post_time']),
                    'timestamp'        => $row['topic_last_post_time'],
                    'icon_url'         => $avatars[$author_id],
                    'short_content'    => get_short_content($last_post_id),
                    'is_approved'      => $topic_unapproved ? false : true,
                    'reply_number'     => (int)$replies,
                    'view_number'      => (int)$row['topic_views'],
                    'is_closed'        => (boolean)($row['topic_status'] == ITEM_LOCKED),
                    'new_post'         => $unread_topic,
                );
                
                $allow_change_type = ($auth->acl_get('m_', $forum_id) || ($user->data['is_registered'] && $user->data['user_id'] == $item['TOPIC_AUTHOR_ID']));
                $can_close  = $auth->acl_get('m_lock', $forum_id) || ($auth->acl_get('f_user_lock', $forum_id) && $user->data['is_registered'] && $user->data['user_id'] == $item['TOPIC_AUTHOR_ID']);
                $can_delete = $auth->acl_get('m_delete', $forum_id);
                $can_stick  = $allow_change_type && $auth->acl_get('f_sticky', $forum_id);
                $can_move   = $auth->acl_get('m_move', $forum_id);
                $can_approve= $auth->acl_get('m_approve', $forum_id) && $topic_unapproved;
                $can_ban    = $auth->acl_get('m_ban') && $row['topic_last_poster_id'] != $user->data['user_id'];
                $can_rename = ($user->data['is_registered'] && ($auth->acl_get('m_edit', $forum_id) || (
                    $user->data['user_id'] == $row['topic_poster'] &&
                    $auth->acl_get('f_edit', $forum_id) &&
                    //!$item['post_edit_locked'] &&
                    ($row['topic_time'] > time() - ($config['edit_time'] * 60) || !$config['edit_time'])
                )));
                
                $can_merge = $auth->acl_get('m_merge', $forum_id);
                $subscribed_tids = tt_get_subscribed_topic_by_id($user->data['user_id']);
                $can_subscribe = ($config['email_enable'] || $config['jab_enable']) && $config['allow_topic_notify'] && $user->data['is_registered'];
                $is_subscribed = is_array($subscribed_tids) ? in_array($result_topic_id, $subscribed_tids) : false ;
                
                //if ($can_close)     $topic['can_close']     = true;
                //if ($can_delete)    $topic['can_delete']    = true;
                //if ($can_stick)     $topic['can_stick']     = true;
                //if ($can_move)      $topic['can_move']      = true;
                //if ($can_approve)   $topic['can_approve']   = true;
                //if ($can_rename)    $topic['can_rename']    = true;
                //if ($can_ban)       $topic['can_ban']       = true;
              //if ($is_ban)        $topic['is_ban']        = true;
                //if ($can_merge)     $topic['can_merge']     = true;
                if ($can_subscribe) $topic['can_subscribe'] = true;
                if ($is_subscribed) $topic['is_subscribed'] = true;
                //if ($can_approve)   $topic['can_approve'] = true;
                //if ($can_delete)    $topic['can_delete']  = true;
                //if ($can_move)      $topic['can_move']    = true;
                //if ($can_ban)       $topic['can_ban']     = true;
                if ($unread_topic)                          $topic['new_post']  = true;
                if ($row['topic_status'] == ITEM_LOCKED)    $topic['is_closed'] = true;
                if ($row['topic_type'] == POST_STICKY)      $topic['is_sticky'] = true;
                $results[] = $topic; 
            }
        }
    }
    unset($rowset);
    if (empty($results))$results = array();
    global $search_method;
    switch($search_method)
    {
        case 'get_user_reply_post':
        case 'get_user_topic':
            $result = $results;
            break;
        default:
            if($show_results == 'posts')
            {
                $result = array(
                    'result'         => (boolean)1,
                    'total_post_num' => (int)$total_match_count,
                    'posts'          => $results,
                );
            }
            else 
            {
                $result = array(
                    'result'         => (boolean)1,
                    'total_topic_num'  => (int)$total_match_count,
                    'topics'          => $results,
                );
            }
    }
    
    mobi_resp($result);
    exit;
}
