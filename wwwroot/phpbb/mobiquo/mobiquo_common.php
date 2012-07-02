<?php
/**
*
* @copyright (c) 2009, 2010, 2011 Quoord Systems Limited
* @license http://opensource.org/licenses/gpl-2.0.php GNU Public License (GPLv2)
*
*/

defined('IN_MOBIQUO') or exit;

function process_page($start_num, $end)
{
    global $start, $limit;
    
    $start = intval($start_num);
    $end = intval($end);
    $start = empty($start) ? 0 : max($start, 0);
    $end = (empty($end) || $end < $start) ? ($start + 19) : max($end, $start);
    if ($end - $start >= 50) {
        $end = $start + 49;
    }
    $limit = $end - $start + 1;
    
    return array($start, $limit);
}

function xmlrpc_error_handler($errno, $msg_text, $errfile, $errline)
{
    global $db, $auth, $user, $msg_long_text;

    if (error_reporting() == 0 && $errno != E_USER_ERROR && $errno != E_USER_NOTICE) return;
    if (isset($msg_long_text) && $msg_long_text && !$msg_text) $msg_text = $msg_long_text;
    
    switch ($errno)
    {
        case E_USER_ERROR:
            if (!empty($user) && !empty($user->lang))
            {
                $msg_text = (!empty($user->lang[$msg_text])) ? $user->lang[$msg_text] : $msg_text;
            }
        break;

        case E_USER_NOTICE:
            if (empty($user->data)) $user->session_begin();
            $auth->acl($user->data);
            if (empty($user->lang)) $user->setup();
            $msg_text = (!empty($user->lang[$msg_text])) ? $user->lang[$msg_text] : $msg_text;
        break;
    }
    
    garbage_collection();
    
    $result = check_error_status($msg_text);
    if (MOBIQUO_DEBUG == -1) $msg_text .= " > $errfile, $errline";
    
    $response = new xmlrpcresp(
        new xmlrpcval(array(
            'result'        => new xmlrpcval($result, 'boolean'),
            'result_text'   => new xmlrpcval(basic_clean($msg_text), 'base64'),
        ),'struct')
    );
    
    echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n".$response->serialize('UTF-8');
    exit;
}


function basic_clean($str)
{
    $str = strip_tags($str);
    $str = trim($str);
    return html_entity_decode($str, ENT_QUOTES, 'UTF-8');
}


function mobi_parse_requrest()
{
    global $request_method, $request_params, $params_num;
    
    $ver = phpversion();
    if ($ver[0] >= 5) {
        $data = file_get_contents('php://input');
    } else {
        $data = isset($GLOBALS['HTTP_RAW_POST_DATA']) ? $GLOBALS['HTTP_RAW_POST_DATA'] : '';
    }
    
    if (count($_SERVER) == 0)
    {
        $r = new xmlrpcresp('', 15, 'XML-RPC: '.__METHOD__.': cannot parse request headers as $_SERVER is not populated');
        echo $r->serialize('UTF-8');
        exit;
    }
    
    if(isset($_SERVER['HTTP_CONTENT_ENCODING'])) {
        $content_encoding = str_replace('x-', '', $_SERVER['HTTP_CONTENT_ENCODING']);
    } else {
        $content_encoding = '';
    }
    
    if($content_encoding != '' && strlen($data)) {
        if($content_encoding == 'deflate' || $content_encoding == 'gzip') {
            // if decoding works, use it. else assume data wasn't gzencoded
            if(function_exists('gzinflate')) {
                if ($content_encoding == 'deflate' && $degzdata = @gzuncompress($data)) {
                    $data = $degzdata;
                } elseif ($degzdata = @gzinflate(substr($data, 10))) {
                    $data = $degzdata;
                }
            } else {
                $r = new xmlrpcresp('', 106, 'Received from client compressed HTTP request and cannot decompress');
                echo $r->serialize('UTF-8');
                exit;
            }
        }
    }
    
    $parsers = php_xmlrpc_decode_xml($data);
    $request_method = $parsers->methodname;
    $request_params = php_xmlrpc_decode(new xmlrpcval($parsers->params, 'array'));
    $params_num = count($request_params);
}

function get_short_content($post_id, $length = 200)
{
    global $db;
    
    $sql = 'SELECT post_text
            FROM ' . POSTS_TABLE . '
            WHERE post_id = ' . $post_id;
    $result = $db->sql_query($sql);
    $post_text = $db->sql_fetchfield('post_text');
    $db->sql_freeresult($result);
    
    return process_short_content($post_text, 200);
}

function process_short_content($post_text, $length = 200)
{
    $post_text = censor_text($post_text);
    $post_text = preg_replace('/\[url.*?\].*?\[\/url.*?\]/', '[url]', $post_text);
    $post_text = preg_replace('/\[img.*?\].*?\[\/img.*?\]/', '[img]', $post_text);
    $post_text = preg_replace('/[\n\r\t]+/', ' ', $post_text);
    strip_bbcode($post_text);
    $post_text = html_entity_decode($post_text, ENT_QUOTES, 'UTF-8');
    $post_text = function_exists('mb_substr') ? mb_substr($post_text, 0, $length) : substr($post_text, 0, $length);
    return $post_text;
}

function post_html_clean($str)
{
    global $phpbb_root_path, $phpbb_home, $mobiquo_config;
    
    $search = array(
        "/<a .*?href=\"(.*?)\".*?>(.*?)<\/a>/si",
        "/<img .*?src=\"(.*?)\".*?\/?>/si",
        "/<br\s*\/?>|<\/cite>|<\/dt>/si",
        "/<object .*?data=\"(http:\/\/www\.youtube\.com\/.*?)\" .*?>.*?<\/object>/si",
        "/<object .*?data=\"(http:\/\/video\.google\.com\/.*?)\" .*?>.*?<\/object>/si",
    );
    
    $replace = array(
        '[url=$1]$2[/url]',
        '[img]$1[/img]',
        "\n",
        '[url=$1]YouTube Video[/url]',
        '[url=$1]Google Video[/url]'
    );
    
    $str = preg_replace('/\n|\r/si', '', $str);
    // remove smile
    $str = preg_replace('/<img [^>]*?src=\"[^"]*?images\/smilies\/[^"]*?\"[^>]*?alt=\"([^"]*?)\"[^>]*?\/?>/', '$1', $str);
    $str = preg_replace('/<img [^>]*?alt=\"([^"]*?)\"[^>]*?src=\"[^"]*?images\/smilies\/[^"]*?\"[^>]*?\/?>/', '$1', $str);
    
    $str = preg_replace('/<null.*?\/>/', '', $str);
    $str = preg_replace($search, $replace, $str);
    $str = strip_tags($str);
    $str = html_entity_decode($str, ENT_QUOTES, 'UTF-8');
    
    // change relative path to absolute URL and encode url
    $str = preg_replace('/\[img\](.*?)\[\/img\]/sei', "'[img]'.url_encode('$1').'[/img]'", $str);
    
    // remove link on img
    $str = preg_replace('/\[url=[^\]]*?\]\s*(\[img\].*?\[\/img\])\s*\[\/url\]/si', '$1', $str);
    
    // cut quote content to 100 charactors
    if ($mobiquo_config['shorten_quote'])
    {
        $str = cut_quote($str, 100);
    }
    
    return parse_bbcode($str);
}

function parse_bbcode($str)
{
    $search = array(
        '#\[(b)\](.*?)\[/b\]#si',
        '#\[(u)\](.*?)\[/u\]#si',
        '#\[(i)\](.*?)\[/i\]#si',
        '#\[color=(\#[\da-fA-F]{3}|\#[\da-fA-F]{6}|[A-Za-z]{1,20}|rgb\(\d{1,3}, ?\d{1,3}, ?\d{1,3}\))\](.*?)\[/color\]#si',
    );
    
    if ($GLOBALS['return_html']) {
        $str = htmlspecialchars($str);
        $replace = array(
            '<$1>$2</$1>',
            '<$1>$2</$1>',
            '<$1>$2</$1>',
            '<font color="$1">$2</font>',
        );
        $str = str_replace("\n", '<br />', $str);
    } else {
        $replace = '$2';
    }
    
    return preg_replace($search, $replace, $str);
}

function parse_quote($str)
{
    $blocks = preg_split('/(<blockquote.*?>|<\/blockquote>)/i', $str, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
    
    $quote_level = 0;
    $message = '';
        
    foreach($blocks as $block)
    {
        if (preg_match('/<blockquote.*?>/i', $block)) {
            if ($quote_level == 0) $message .= '[quote]';
            $quote_level++;
        } else if (preg_match('/<\/blockquote>/i', $block)) {
            if ($quote_level <= 1) $message .= '[/quote]';
            if ($quote_level >= 1) {
                $quote_level--;
                $message .= "\n";
            }
        } else {
            if ($quote_level <= 1) $message .= $block;
        }
    }
    
    return $message;
}

function url_encode($url)
{
    global $phpbb_home, $phpbb_root_path;
    
    $url = rawurlencode($url);
    
    $from = array('/%3A/', '/%2F/', '/%3F/', '/%2C/', '/%3D/', '/%26/', '/%25/', '/%23/', '/%2B/', '/%3B/', '/%5C/');
    $to   = array(':',     '/',     '?',     ',',     '=',     '&',     '%',     '#',     '+',     ';',     '\\');
    $url = preg_replace($from, $to, $url);
    $root_path = preg_replace('/^\//', '', $phpbb_root_path);
    $url = preg_replace('#^\.\./|'.addslashes($root_path).'#si', '', $url);
    
    if (strpos($url, 'http') !== 0)
    {
        $url = $phpbb_home.$url;
    }
    
    return htmlspecialchars_decode($url);
}

function get_user_avatar_url($avatar, $avatar_type, $ignore_config = false)
{
    global $config, $phpbb_home, $phpEx;

    if (empty($avatar) || !$avatar_type || (isset($config['allow_avatar']) && !$config['allow_avatar'] && !$ignore_config))
    {
        return '';
    }
    
    $avatar_img = '';

    switch ($avatar_type)
    {
        case AVATAR_UPLOAD:
            if (isset($config['allow_avatar_upload']) && !$config['allow_avatar_upload'] && !$ignore_config)
            {
                return '';
            }
            $avatar_img = $phpbb_home . "download/file.$phpEx?avatar=";
        break;

        case AVATAR_GALLERY:
            if (isset($config['allow_avatar_local']) && !$config['allow_avatar_local'] && !$ignore_config)
            {
                return '';
            }
            $avatar_img = $phpbb_home . $config['avatar_gallery_path'] . '/';
        break;

        case AVATAR_REMOTE:
            if (isset($config['allow_avatar_remote']) && !$config['allow_avatar_remote'] && !$ignore_config)
            {
                return '';
            }
        break;
    }

    $avatar_img .= $avatar;
    $avatar_img = str_replace(' ', '%20', $avatar_img);
    
    return $avatar_img;
}


function mobiquo_iso8601_encode($timet)
{
    global $user;
    
    return $user->format_date($timet);
}


function get_user_id_by_name($username)
{
    global $db;
    
    if (!$username)
    {
        return false;
    }
    
    $username_clean = $db->sql_escape(utf8_clean_string($username));
    
    $sql = 'SELECT user_id
            FROM ' . USERS_TABLE . "
            WHERE username_clean = '$username_clean'";
    $result = $db->sql_query($sql);
    $user_id = $db->sql_fetchfield('user_id');
    $db->sql_freeresult($result);
    
    return $user_id;
}

function cut_quote($str, $keep_size)
{
    $str_array = preg_split('/(\[quote\].*?\[\/quote\])/is', $str, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
    
    $str = '';
    
    foreach($str_array as $block)
    {
        if (preg_match('/\[quote\](.*?)\[\/quote\]/is', $block, $block_matches))
        {
            $quote_array = preg_split('/(\[img\].*?\[\/img\]|\[url=.*?\].*?\[\/url\])/is', $block_matches[1], -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
            $short_str = '';
            $current_size = 0;
            $img_flag = true; // just keep at most one img in the quote
            for ($i = 0, $size = sizeof($quote_array); $i < $size; $i++)
            {
                if (preg_match('/^\[img\].*?\[\/img\]$/is', $quote_array[$i]))
                {
                    if ($img_flag)
                    {
                        $short_str .= $quote_array[$i];
                        $img_flag = false;
                    }
                }
                else if (preg_match('/^\[url=.*?\](.*?)\[\/url\]$/is', $quote_array[$i], $matches))
                {
                    $short_str .= $quote_array[$i];
                    $current_size += strlen($matches[1]);
                    if ($current_size > $keep_size)
                    {
                        $short_str .= "...";
                        break;
                    }
                }
                else
                {
                    if ($current_size + strlen($quote_array[$i]) > $keep_size)
                    {
                        $short_str .= substr($quote_array[$i], 0, $keep_size - $current_size);
                        $short_str .= "...";
                        break;
                    }
                    else
                    {
                        $short_str .= $quote_array[$i];
                        $current_size += strlen($quote_array[$i]);
                    }
                }
            }
            $str .= '[quote]' . $short_str . '[/quote]';
        } else {
            $str .= $block;
        }
    }

    return $str;
}

function video_bbcode_format($type, $url)
{
    $url = trim($url);
    
    switch (strtolower($type)) {
        case 'youtube':
            if (preg_match('#^(http://)?((www|m)\.)?(youtube\.com/(watch\?.*?v=|v/)|youtu\.be/)([-\w]+)#', $url, $matches)) {
                $key = $matches['6'];
                $image = '[img]http://i1.ytimg.com/vi/'.$key.'/hqdefault.jpg[/img]';
                $url_code = '[url='.$url.']YouTube Video[/url]';
                $message = $image.$url_code;
            } else if (preg_match('/^[-\w]+$/', $url)) {
                $key = $url;
                $url = 'http://www.youtube.com/watch?v='.$key;
                $image = '[img]http://i1.ytimg.com/vi/'.$key.'/hqdefault.jpg[/img]';
                $url_code = '[url='.$url.']YouTube Video[/url]';
                $message = $image.$url_code;
            } else {
                $message = '';
            }
            break;
        case 'video':
            if (preg_match('#^http(s)?://#', $url)) {
                $message = '[url='.$url.']Video[/url]';
            } else {
                $message = '';
            }
            break;
        case 'gvideo':
        case 'googlevideo':
            if (preg_match('#^http://video.google.com/(googleplayer.swf|videoplay)?docid=-#', $url)) {
                $message = '[url='.$url.']Google Video[/url]';
            } else if (preg_match('/^-?(\d+)/', $url, $matches)) {
                $message = '[url=http://video.google.com/videoplay?docid=-'.$matches['1'].']Google Video[/url]';
            } else {
                $message = '';
            }
            break;
        default: $message = '';
    }
    
    return $message;
}

if (!function_exists('itemstats_parse')) 
{
    function itemstats_parse($message)
    {
        return $message;
    }
}

function check_forum_password($forum_id)
{
    global $user, $db;
    
    $sql = 'SELECT forum_id
            FROM ' . FORUMS_ACCESS_TABLE . '
            WHERE forum_id = ' . $forum_id . '
                AND user_id = ' . $user->data['user_id'] . "
                AND session_id = '" . $db->sql_escape($user->session_id) . "'";
    $result = $db->sql_query($sql);
    $row = $db->sql_fetchrow($result);
    $db->sql_freeresult($result);

    if (!$row)
    {
        return false;
    }
    
    return true;
}

function get_participated_user_avatars($tids)
{
    global $db, $topic_users, $user_avatar;
    
    $topic_users = array();
    $user_avatar = array();
    if (!empty($tids))
    {
        $posters = array();
        foreach($tids as $tid)
        {
            $sql = 'SELECT poster_id, count(post_id) as num FROM ' . POSTS_TABLE . '
                    WHERE topic_id=' . $tid . '
                    GROUP BY poster_id
                    ORDER BY num DESC';
            $result = $db->sql_query_limit($sql, 10);
            while ($row = $db->sql_fetchrow($result))
            {
                $posters[$row['poster_id']] = $row['num'];
                $topic_users[$tid][] = $row['poster_id'];
            }
            $db->sql_freeresult($result);
        }
        
        if (!empty($posters))
        {
            $user_avatar = get_user_avatars(array_keys($posters));
        }
    }
}

function get_user_avatars($users, $is_username = false)
{
    global $db;
    
    if (empty($users)) return array();
    
    if (!is_array($users)) $users = array($users);
    
    if($is_username)
        foreach($users as $key => $username)
            $users[$key] = $db->sql_escape(utf8_clean_string($username));
    
    $sql = 'SELECT user_id, username, user_avatar, user_avatar_type 
            FROM ' . USERS_TABLE . '
            WHERE ' . $db->sql_in_set($is_username ? 'username_clean' : 'user_id', $users);
    $result = $db->sql_query($sql);
    $user_avatar = array();
    $user_key = $is_username ? 'username' : 'user_id';
    while ($row = $db->sql_fetchrow($result))
    {
        $user_avatar[$row[$user_key]] = get_user_avatar_url($row['user_avatar'], $row['user_avatar_type']);
    }
    $db->sql_freeresult($result);
    
    return $user_avatar;
}

function xmlresptrue()
{
    $result = new xmlrpcval(array(
        'result'        => new xmlrpcval(true, 'boolean'),
        'result_text'   => new xmlrpcval('', 'base64')
    ), 'struct');
    
    return new xmlrpcresp($result);
}

function check_error_status(&$str)
{
    global $user, $request_method;
    
    switch ($request_method) {
        case 'thank_post':
            if (strpos($str, $user->lang['THANKS_INFO_GIVE']) !== false) {
                $str = '';
                return true;
            } elseif (strpos($str, $user->lang['GLOBAL_INCORRECT_THANKS']) !== false) {
                $str = $user->lang['GLOBAL_INCORRECT_THANKS'];
                return false;
            } elseif (strpos($str, $user->lang['INCORRECT_THANKS']) !== false) {
                $str = $user->lang['INCORRECT_THANKS'];
                return false;
            } else {
                return false;
            }
            
        case 'm_stick_topic':
            if (strpos($str, $user->lang['TOPIC_TYPE_CHANGED']) === false)
                return false;
            else {
                $str = $user->lang['TOPIC_TYPE_CHANGED'];
                return true;
            }
        case 'm_close_topic':
            if (strpos($str, $user->lang['TOPIC_LOCKED_SUCCESS']) === false && strpos($str, $user->lang['TOPIC_UNLOCKED_SUCCESS']) === false)
                return false;
            elseif (strpos($str, $user->lang['TOPIC_LOCKED_SUCCESS']) !== false) {
                $str = $user->lang['TOPIC_LOCKED_SUCCESS'];
                return true;
            } else {
                $str = $user->lang['TOPIC_UNLOCKED_SUCCESS'];
                return true;
            }
        case 'm_delete_topic':
            if (strpos($str, $user->lang['TOPIC_DELETED_SUCCESS']) === false)
                return false;
            else {
                $str = $user->lang['TOPIC_DELETED_SUCCESS'];
                return true;
            }
        case 'm_delete_post':
            if (strpos($str, $user->lang['POST_DELETED_SUCCESS']) === false && strpos($str, $user->lang['TOPIC_DELETED_SUCCESS']) === false)
                return false;
            elseif (strpos($str, $user->lang['POST_DELETED_SUCCESS']) !== false) {
                $str = $user->lang['POST_DELETED_SUCCESS'];
                return true;
            } else {
                $str = $user->lang['TOPIC_DELETED_SUCCESS'];
                return true;
            }
        case 'm_move_topic':
            if (strpos($str, $user->lang['TOPIC_MOVED_SUCCESS']) === false)
                return false;
            else {
                $str = $user->lang['TOPIC_MOVED_SUCCESS'];
                return true;
            }
        case 'm_move_post':
            if (strpos($str, $user->lang['TOPIC_SPLIT_SUCCESS']) === false && strpos($str, $user->lang['POSTS_MERGED_SUCCESS']) === false)
                return false;
            elseif (strpos($str, $user->lang['TOPIC_SPLIT_SUCCESS']) !== false) {
                $str = $user->lang['TOPIC_SPLIT_SUCCESS'];
                return true;
            } else {
                $str = $user->lang['POSTS_MERGED_SUCCESS'];
                return true;
            }
        case 'm_merge_topic':
            if (strpos($str, $user->lang['POSTS_MERGED_SUCCESS']) === false)
                return false;
            else {
                $str = $user->lang['POSTS_MERGED_SUCCESS'];
                return true;
            }
        case 'm_approve_topic':
            if (strpos($str, $user->lang['TOPIC_APPROVED_SUCCESS']) === false)
                return false;
            else {
                $str = $user->lang['TOPIC_APPROVED_SUCCESS'];
                return true;
            }
        case 'm_approve_post':
            if (strpos($str, $user->lang['POST_APPROVED_SUCCESS']) === false)
                return false;
            else {
                $str = $user->lang['POST_APPROVED_SUCCESS'];
                return true;
            }
        case 'm_ban_user':
            if (strpos($str, $user->lang['BAN_UPDATE_SUCCESSFUL']) === false)
                return false;
            else {
                $str = $user->lang['BAN_UPDATE_SUCCESSFUL'];
                return true;
            }
    }
    
    return false;
}