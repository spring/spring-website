<?php
/**
*
* @copyright (c) 2009 Quoord Systems Limited
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

defined('IN_MOBIQUO') or exit;

function log_it($log_data, $is_begin = false)
{
    global $mobiquo_config;
    
    if(!$mobiquo_config['keep_log'] || !$log_data)
    {
        return;
    }
    
    $log_file = './log/'.date('Ymd_H').'.log';
    
    if ($is_begin)
    {
        global $user;
        $method_name = $log_data;
        $log_data = "\nSTART ======================================== $method_name\n";
        $log_data .= "TIME: ".date('Y-m-d H:i:s')."\n";
        $log_data .= "USER ID: ".$user->data['user_id']."\n";
        $log_data .= "USER NAME: ".$user->data['username']."\n";
        $log_data .= "PARAMETER:\n";
    }
    
    file_put_contents($log_file, print_r($log_data, true), FILE_APPEND);
}


function get_method_name()
{
    $ver = phpversion();
    if ($ver[0] >= 5) {
        $data = file_get_contents('php://input');
    } else {
        $data = isset($GLOBALS['HTTP_RAW_POST_DATA']) ? $GLOBALS['HTTP_RAW_POST_DATA'] : '';
    }
    $parsers = php_xmlrpc_decode_xml($data);
    return trim($parsers->methodname);
}


function get_error($error_code = 99, $error_message = '')
{
    global $mobiquo_error_code;
    
    if(isset($mobiquo_error_code[$error_code]) && $error_message == '')
    {
        $error_message = $mobiquo_error_code[$error_code];
    }
    
    return new xmlrpcresp('', 18, $error_message); // for test purpose
    //return new xmlrpcresp('', $error_code, $mobiquo_error_code[$error_code]);
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
        "/<img .*?src=\"(.*?)\".*?\/?>/sei",
        "/<br\s*\/?>|<\/cite>/si",
        "/<object .*?data=\"(http:\/\/www\.youtube\.com\/.*?)\" .*?>.*?<\/object>/si",
        "/<object .*?data=\"(http:\/\/video\.google\.com\/.*?)\" .*?>.*?<\/object>/si",
    );
    
    $replace = array(
        '[url=$1]$2[/url]',
        "'[img]'.url_encode('$1').'[/img]'",
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
    
    // change relative path to absolute URL 
    $str = preg_replace('/\[img\]\.\.\/(.*?)\[\/img\]/si', "[img]$phpbb_home/$1[/img]", $str);
    $str = preg_replace('#\[img\]'.addslashes($phpbb_root_path).'(.*?)\[/img\]#si', "[img]$phpbb_home$1[/img]", $str);
    // remove link on img
    $str = preg_replace('/\[url=[^\]]*?\]\s*(\[img\].*?\[\/img\])\s*\[\/url\]/si', '$1', $str);
    
    // cut quote content to 100 charactors
    if ($mobiquo_config['shorten_quote'])
    {
        $str = cut_quote($str, 100);
    }
    
    return $str;
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
    $url = rawurlencode($url);
    
    $from = array('/%3A/', '/%2F/', '/%3F/', '/%2C/', '/%3D/', '/%26/', '/%25/', '/%23/', '/%2B/', '/%3B/', '/%5C/');
    $to   = array(':',     '/',     '?',     ',',     '=',     '&',     '%',     '#',     '+',     ';',     '\\');
    $url = preg_replace($from, $to, $url);
    
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
    
    $timezone = ($user->timezone)/3600;
    $t = gmdate("Ymd\TH:i:s", $timet + $user->timezone + $user->dst);
    
    if($timezone >= 0){
        $timezone = sprintf("%02d", $timezone);         
        $timezone = '+'.$timezone;
    }
    else{
        $timezone = $timezone * (-1);
        $timezone = sprintf("%02d",$timezone);
        $timezone = '-'.$timezone;
    }
    $t = $t.$timezone.':00';
    
    return $t;
}


function get_user_id_by_name($username)
{
    global $db;
    
    if (!$username)
    {
        return false;
    }
    
    $sql = 'SELECT user_id
            FROM ' . USERS_TABLE . "
            WHERE username = '$username'";
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
    switch (strtolower($type)) {
        case 'youtube':
            if (preg_match('#^\s*http://(www\.)?youtube\.com/watch\?v=(\w+)\s*$#', $url, $matches)) {
                $key = $matches['2'];
                $image = '[img]http://i1.ytimg.com/vi/'.$key.'/hqdefault.jpg[/img]';
                $url_code = '[url='.$url.']YouTube Video[/url]';
                $message = $image.$url_code;
            } else if (preg_match('/^\w+$/', $url)) {
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
            if (preg_match('#^\s*http(s)?://#', $url)) {
                $message = '[url='.$url.']Video[/url]';
            } else {
                $message = '';
            }
            break;
        case 'gvideo':
        case 'googlevideo':
            if (preg_match('#^\s*http://video.google.com/(googleplayer.swf|videoplay)?docid=-#', $url)) {
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