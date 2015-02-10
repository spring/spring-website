<?php
/*======================================================================*\
|| #################################################################### ||
|| # Copyright &copy;2009 Quoord Systems Ltd. All Rights Reserved.    # ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # This file is part of the Tapatalk package and should not be used # ||
|| # and distributed for any other purpose that is not approved by    # ||
|| # Quoord Systems Ltd.                                              # ||
|| # http://www.tapatalk.com | https://tapatalk.com/license.php       # ||
|| #################################################################### ||
\*======================================================================*/
defined('IN_MOBIQUO') or exit;

function xmlrpc_shutdown()
{
    if (function_exists('error_get_last'))
    {
        $error = error_get_last();
    
        if(!empty($error)){
            switch($error['type']){
                case E_ERROR:
                case E_CORE_ERROR:
                case E_COMPILE_ERROR:
                case E_USER_ERROR:
                case E_PARSE:
                    $xmlrpcresp = xmlresperror("Server error occurred: '{$error['message']} (".basename($error['file']).":{$error['line']})'");
                    echo $xmlrpcresp;
                    break;
            }
        }
    }
}

function xmlresperror($error_message)
{
    @ob_clean();
    
    if (defined('HTTP_10'))
    {
        @header("HTTP/1.0 200 OK" );
    }
    else
    {
        @header("HTTP/1.1 200 OK" );
    }
    @header('Content-Type: text/xml');
    
    $result = array(
        'result'        => (boolean)false, 
        'result_text'   => basic_clean($error_message),
    );
    
    return mobi_xmlrpc_encode($result,true);
}

function xmlrpc_error_handler($errno, $msg_text, $errfile, $errline)
{
    global $auth, $user, $msg_long_text;
	
    // Do not display notices if we suppress them via @
    if (MOBIQUO_DEBUG == 0 && $errno != E_USER_ERROR && $errno != E_USER_WARNING && $errno != E_USER_NOTICE)
    {
        return;
    }
    /*if(strpos($errfile, 'session.php') !== false)
    {
        return ;
    }*/
    if ($msg_text == 'NO_SEARCH_RESULTS')
    {
        $response = search_func();
        echo $response;
        exit;
    }
	
    if(strstr(strip_tags($msg_text),$user->lang['REPORTS_CLOSED_SUCCESS']) || strstr(strip_tags($msg_text),$user->lang['REPORT_CLOSED_SUCCESS']))
    {
    	$response = array(
            'result'        => true,
            'result_text'   => basic_clean($user->lang['REPORTS_CLOSED_SUCCESS']),
        );
        $response= mobi_xmlrpc_encode($response,true);
        echo $response;
        exit;
    }
    
    // Message handler is stripping text. In case we need it, we are possible to define long text...
    if (isset($msg_long_text) && $msg_long_text && !$msg_text)
    {
        $msg_text = $msg_long_text;
    }

    if (!defined('E_DEPRECATED'))
    {
        define('E_DEPRECATED', 8192);
    }

    switch ($errno)
    {
        case E_NOTICE:
        case E_WARNING:

            // Check the error reporting level and return if the error level does not match
            // If DEBUG is defined the default level is E_ALL
            if (MOBIQUO_DEBUG == 0)
            {
                return;
            }

            if (strpos($errfile, 'cache') === false && strpos($errfile, 'template.') === false)
            {
                $errfile = phpbb_filter_root_path($errfile);
                $msg_text = phpbb_filter_root_path($msg_text);
                $error_name = ($errno === E_WARNING) ? 'PHP Warning' : 'PHP Notice';
                echo '[phpBB Debug] ' . $error_name . ': in file ' . $errfile . ' on line ' . $errline . ': ' . $msg_text . "\n";
            }

            return;

        break;

        case E_USER_ERROR:

            if (!empty($user) && !empty($user->lang))
            {
                $msg_text = (!empty($user->lang[$msg_text])) ? $user->lang[$msg_text] : $msg_text;
            }
            garbage_collection();
        break;

        case E_USER_WARNING:
        case E_USER_NOTICE:

            define('IN_ERROR_HANDLER', true);

            if (empty($user->data))
            {
                $user->session_begin();
            }

            // We re-init the auth array to get correct results on login/logout
            $auth->acl($user->data);

            if (empty($user->lang))
            {
                $user->setup();
            }

            if ($msg_text == 'ERROR_NO_ATTACHMENT' || $msg_text == 'NO_FORUM' || $msg_text == 'NO_TOPIC' || $msg_text == 'NO_USER')
            {
                //send_status_line(404, 'Not Found');
            }

            $msg_text = (!empty($user->lang[$msg_text])) ? $user->lang[$msg_text] : $msg_text;
        break;

        // PHP4 compatibility
        case E_DEPRECATED:
            return true;
        break;
    }
    
    if (in_array($errno, array(E_USER_ERROR, E_USER_WARNING, E_USER_NOTICE)))
    {
        $result = check_error_status($msg_text);
        if (MOBIQUO_DEBUG == -1) $msg_text .= " > $errfile, $errline";
        
        $response = array(
            'result'        => (boolean)$result,
            'result_text'   => basic_clean($msg_text),
        );
        $response = mobi_xmlrpc_encode($response,true);
        
        echo $response;
        exit;
    }
    
    // If we notice an error not handled here we pass this back to PHP by returning false
    // This may not work for all php versions
    return false;
}

function basic_clean($str)
{
    $str = preg_replace('/<br\s*\/?>/si', "\n", $str);
    $str = strip_tags($str);
    $str = trim($str);
    return html_entity_decode($str, ENT_QUOTES, 'UTF-8');
}

function tp_get_forum_icon($id, $type = 'forum', $lock = false, $new = false)
{
    if ($type == 'link')
    {
        if ($filename = tp_get_forum_icon_by_name('link'))
            return $filename;
    }
    else
    {
        if ($lock && $new && $filename = tp_get_forum_icon_by_name('lock_new_'.$id))
            return $filename;
        if ($lock && $filename = tp_get_forum_icon_by_name('lock_'.$id))
            return $filename;
        if ($new && $filename = tp_get_forum_icon_by_name('new_'.$id))
            return $filename;
        if ($filename = tp_get_forum_icon_by_name($id))
            return $filename;
        
        if ($type == 'category')
        {
            if ($lock && $new && $filename = tp_get_forum_icon_by_name('category_lock_new'))
                return $filename;
            if ($lock && $filename = tp_get_forum_icon_by_name('category_lock'))
                return $filename;
            if ($new && $filename = tp_get_forum_icon_by_name('category_new'))
                return $filename;
            if ($filename = tp_get_forum_icon_by_name('category'))
                return $filename;
        }
        else
        {
            if ($lock && $new && $filename = tp_get_forum_icon_by_name('forum_lock_new'))
                return $filename;
            if ($lock && $filename = tp_get_forum_icon_by_name('forum_lock'))
                return $filename;
            if ($new && $filename = tp_get_forum_icon_by_name('forum_new'))
                return $filename;
            if ($filename = tp_get_forum_icon_by_name('forum'))
                return $filename;
        }
        
        if ($lock && $new && $filename = tp_get_forum_icon_by_name('lock_new'))
            return $filename;
        if ($lock && $filename = tp_get_forum_icon_by_name('lock'))
            return $filename;
        if ($new && $filename = tp_get_forum_icon_by_name('new'))
            return $filename;
    }
    
    return tp_get_forum_icon_by_name('default');
}

function tp_get_forum_icon_by_name($icon_name)
{
    $tapatalk_forum_icon_dir = './forum_icons/';
    
    if (file_exists($tapatalk_forum_icon_dir.$icon_name.'.png'))
        return $icon_name.'.png';
    
    if (file_exists($tapatalk_forum_icon_dir.$icon_name.'.jpg'))
        return $icon_name.'.jpg';
    
    return '';
}

function tt_get_ignore_users($user_id)
{
	global $db;
	$user_id = intval($user_id);
	
	$sql_and = 'z.foe = 1';
	$sql = 'SELECT z.*
		FROM ' . ZEBRA_TABLE . ' z
		WHERE z.user_id = ' . $user_id . "
			AND $sql_and ";
	$result = $db->sql_query($sql);
	
	$ignore_users = array();
	while ($row = $db->sql_fetchrow($result))
	{
		$ignore_users[] = $row['zebra_id'];
	}
	$db->sql_freeresult($result);
	return $ignore_users;
}

function get_user_avatar_url($row, $ignore_config = false)
{
    global $config, $phpEx;
    
    $phpbb_home = generate_board_url().'/';
    $avatar      = $row['user_avatar'];
    $avatar_type = $row['user_avatar_type'];

    if (empty($avatar) || (isset($config['allow_avatar']) && !$config['allow_avatar'] && !$ignore_config))
    {
        return '';
    }
    
    $avatar_img = '';

    switch ($avatar_type)
    {
        case 'avatar.driver.upload':
            if (isset($config['allow_avatar_upload']) && !$config['allow_avatar_upload'] && !$ignore_config)
            {
                return '';
            }
            $avatar_img = $phpbb_home . "download/file.$phpEx?avatar=";
        break;

        case 'avatar.driver.local':
            if (isset($config['allow_avatar_local']) && !$config['allow_avatar_local'] && !$ignore_config)
            {
                return '';
            }
            $avatar_img = $phpbb_home . $config['avatar_gallery_path'] . '/';
        break;

        case 'avatar.driver.remote':
            if (isset($config['allow_avatar_remote']) && !$config['allow_avatar_remote'] && !$ignore_config)
            {
                return '';
            }
        break;
        default:
        	return $avatar;
        	break;
    }

    $avatar_img .= $avatar;
    $avatar_img = str_replace(' ', '%20', $avatar_img);
    
    return $avatar_img;
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
        $user_avatar[$row[$user_key]] = get_user_avatar_url($row);
    }
    $db->sql_freeresult($result);
    
    return $user_avatar;
}

function tt_get_user_by_name($username)
{
    global $db;
    
    if (!$username)
    {
        return false;
    }
    $username_clean = $db->sql_escape(utf8_clean_string($username));
    $username_clean = htmlspecialchars($username_clean, ENT_COMPAT, 'UTF-8');
    $sql = 'SELECT *
            FROM ' . USERS_TABLE . "
            WHERE username_clean = '$username_clean'";
    $result = $db->sql_query($sql);    
    $row = $db->sql_fetchrow($result);
    $db->sql_freeresult($result);
    return $row;
}

function check_error_status(&$str)
{
    global $user, $request_method;
    
    switch ($request_method) {
        case 'thank_post':
            if (strpos($str, $user->lang['THANKS_INFO_GIVE']) !== false || $str == "Insert thanks") {
                $str = '';
                return true;
            } elseif (strpos($str, $user->lang['GLOBAL_INCORRECT_THANKS']) !== false) {
                $str = $user->lang['GLOBAL_INCORRECT_THANKS'];
                return false;
            } elseif (strpos($str, $user->lang['INCORRECT_THANKS']) !== false) {
                $str = $user->lang['INCORRECT_THANKS'];
                return false;
            } 
            else if(strpos($str, 'Tried to redirect to potentially insecure url')  !== false )
            {
                $str = '';
                return true;
            }
            else {
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
            if (strpos($str, $user->lang['TOPIC_MOVED_SUCCESS']) === false && strpos($str, $user->lang['TOPICS_MOVED_SUCCESS']) === false)
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

function mobiquo_iso8601_encode($timet)
{
    global $user;
    $zone_offset = $user->timezone->getOffset(new DateTime('UTC'));
    $time_format = 'Ymd\TH:i:s';
    $time = @gmdate($time_format, $timet + $zone_offset);
    $time .= sprintf("%+03d:%02d", intval($zone_offset/3600), abs($zone_offset - 3600 * intval($zone_offset/3600))/60);
    return $time;
}

function video_bbcode_format($type, $url)
{
	$url = trim($url);
	$url = html_entity_decode($url);
	switch (strtolower($type)) {
		case 'yt':
		case 'youtube':
			if (preg_match('#^(http(s|)://)?((www|m)\.)?(youtube\.com/(watch\?.*?v=|v/)|youtu\.be/)([-\w]+)#', $url, $matches)) {
				$url = preg_replace("/^https:/i", "http:", $url);
				$message = '[url='.$url.']YouTube Video[/url]';
			} else if (preg_match('/^[-\w]+$/', $url)) {
				$url = 'http://www.youtube.com/watch?v='.$url;
				$message = '[url='.$url.']YouTube Video[/url]';
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
        
function process_page($start_num, $end)
{
    global $start, $limit, $page;
    
    $start = intval($start_num);
    $end = intval($end);
    $start = empty($start) ? 0 : max($start, 0);
    $end = (empty($end) || $end < $start) ? ($start + 19) : max($end, $start);
    if ($end - $start >= 50) {
        $end = $start + 49;
    }
    $limit = $end - $start + 1;
    $page = intval($start/$limit) + 1;
    return array($start, $limit, $page);
}

function get_short_content($post_id, $length = 200)
{
    global $db;
    
    $post_id = intval($post_id);
    if (empty($post_id)) return '';
    
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
    $array_reg = array(
        array('reg' => '/\[quote(.*?)\](.*?)\[\/quote(.*?)\]/si','replace' => '[quote]'),
        array('reg' => '/\[code(.*?)\](.*?)\[\/code(.*?)\]/si','replace' => ''),
        array('reg' => '/\[url=(.*?):(.*?)\](.*?)\[\/url(.*?)\]/sei','replace' => "mobi_url_convert('$1','$3')"),
        array('reg' => '/\[video(.*?)\](.*?)\[\/video(.*?)\]/si','replace' => '[V]'),
        array('reg' => '/\[attachment(.*?)\](.*?)\[\/attachment(.*?)\]/si','replace' => '[attach]'),
        array('reg' => '/\[url.*?\].*?\[\/url.*?\]/','replace' => '[url]'),
        array('reg' => '/\[img.*?\].*?\[\/img.*?\]/','replace' => '[img]'),
        array('reg' => '/[\n\r\t]+/','replace' => ' '),
        array('reg' => '/\[flash(.*?)\](.*?)\[\/flash(.*?)\]/si','replace' => '[V]'),
        array('reg' => '/\[spoiler(.*?)\](.*?)\[\/spoiler(.*?)\]/si','replace' => '[spoiler]'),
        array('reg' => '/\[spoil(.*?)\](.*?)\[\/spoil(.*?)\]/si','replace' => '[spoiler]'),
    );
    //echo $post_text;die();
    foreach ($array_reg as $arr)
    {
        $post_text = preg_replace($arr['reg'], $arr['replace'], $post_text);
    }
    strip_bbcode($post_text);
    $post_text = html_entity_decode($post_text, ENT_QUOTES, 'UTF-8');
    $post_text = function_exists('mb_substr') ? mb_substr($post_text, 0, $length) : substr($post_text, 0, $length);
    $post_text = trim(strip_tags($post_text));
    $post_text = preg_replace('/\\s+|\\r|\\n/', ' ', $post_text);
    return $post_text;
}

function post_html_clean($str)
{
    
    global $phpbb_root_path, $mobiquo_config,$config;
    
    $phpbb_home = generate_board_url().'/';
    
    $search = array(
        "/<strong>(.*?)<\/strong>/si",
        "/<em>(.*?)<\/em>/si",
        "/<img .*?src=\"(.*?)\".*?\/?>/si",
        "/<a .*?href=\"(.*?)\"(.*?)?>(.*?)<\/a>/sei",
        "/<br\s*\/?>|<\/cite>|<\/dt>|<\/dd>/si",
        "/<object .*?data=\"(http:\/\/www\.youtube\.com\/.*?)\" .*?>.*?<\/object>/si",
        "/<object .*?data=\"(http:\/\/video\.google\.com\/.*?)\" .*?>.*?<\/object>/si",
        "/<iframe .*?src=\"(http.*?)\" .*?>.*?<\/iframe>/si",
        "/<script( [^>]*)?>([^<]*?)<\/script>/si",
        "/<param name=\"movie\" value=\"(.*?)\" \/>/si"
    );
    
    $replace = array(
        '[b]$1[/b]',
        '[i]$1[/i]',
        '[img]$1[/img]',
        "'[url='.url_encode('$1').']$3[/url]'",
        "\n",
        '[url=$1]YouTube Video[/url]',
        '[url=$1]Google Video[/url]',
        '[url=$1]$1[/url]',
        '',
        '[url=$1]Flash Video[/url]',
    );
    
    //$str = preg_replace('/\n|\r/si', '', $str);
    $str = preg_replace('/>\s+</si', '><', $str);
    // remove smile
    $str = preg_replace('/<img [^>]*?src=\"[^"]*?images\/smilies\/[^"]*?\"[^>]*?alt=\"([^"]*?)\"[^>]*?\/?>/', '$1', $str);
    $str = preg_replace('/<img [^>]*?alt=\"([^"]*?)\"[^>]*?src=\"[^"]*?images\/smilies\/[^"]*?\"[^>]*?\/?>/', '$1', $str);

    $str = preg_replace('/<null.*?\/>/', '', $str);
   
    $str = preg_replace($search, $replace, $str);
   
    $str = strip_tags($str);
   
    $str = preg_replace('/\[code\](.*?)\[\/code\]/sie', "'[code]'.base64_encode('$1').'[/code]'", $str);
    $str = html_entity_decode($str, ENT_QUOTES, 'UTF-8');
    
    // remove attach icon image
    $str = preg_replace('/\[img\][^\[\]]+icon_topic_attach\.gif\[\/img\]/si', '', $str);
    
    // change relative path to absolute URL and encode url
    $str = preg_replace('/\[img\](.*?)\[\/img\]/sei', "'[img]'.url_encode('$1').'[/img]'", $str);
    
    $str = preg_replace('/\[\/img\]\s*/si', "[/img]\n", $str);
    
    $str = preg_replace('/\[\/img\]\s+\[img\]/si', '[/img][img]', $str);
    
    // remove link on img
    //$str = preg_replace('/\[url=[^\]]*?\]\s*(\[img\].*?\[\/img\])\s*\[\/url\]/si', '$1', $str);
    
    // change url to image resource to img bbcode
    $str = preg_replace('/\[url\](http[^\[\]]+\.(jpg|png|bmp|gif))\[\/url\]/si', '[img]$1[/img]', $str);
    $str = preg_replace('/\[url=(http[^\]]+\.(jpg|png|bmp|gif))\]([^\[\]]+)\[\/url\]/si', '[img]$1[/img]', $str);
    
    // cut quote content to 100 charactors
    if (isset($mobiquo_config['shorten_quote']) && $mobiquo_config['shorten_quote'])
    {
        $str = cut_quote($str, 100);
    }
    
    return parse_bbcode($str);
}

function parse_bbcode($str)
{
    global $config ,$return_html;
    $search = array(
        '#\[(b)\](.*?)\[/b\]#si',
        '#\[(u)\](.*?)\[/u\]#si',
        '#\[(i)\](.*?)\[/i\]#si',
        '#\[color=(\#[\da-fA-F]{3}|\#[\da-fA-F]{6}|[A-Za-z]{1,20}|rgb\(\d{1,3}, ?\d{1,3}, ?\d{1,3}\))\](.*?)\[/color\]#sie',
    );
    if ($return_html) {
        $str = htmlspecialchars($str, ENT_NOQUOTES);
        $replace = array(
            '<$1>$2</$1>',
            '<$1>$2</$1>',
            '<$1>$2</$1>',
            "mobi_color_convert('$1', '$2')",
        );
        $str = str_replace("\n", '<br />', $str);
    } else {
        $replace = array('$2', '$2', '$2', "'$2'");
    }
    
    $str = preg_replace($search, $replace, $str);
    $str = preg_replace('/\[code\](.*?)\[\/code\]/sie', "'[code]'.html_entity_decode(base64_decode('$1'), ENT_QUOTES, 'UTF-8').'[/code]'", $str);
    return $str;
}

function url_encode($url)
{
    global $phpbb_root_path,$request;
    
    $phpbb_home = generate_board_url().'/';
    
	//check is domain
    $is_domain = false;
    if(preg_match('/^\//', $url) && !preg_match('/download\/file\.php/', $url))
    {
    	$is_domain = true;
    	$server_url = $request->server('HTTP_HOST','');
    }
    $url = rawurlencode($url);
    
    $from = array('/%3A/', '/%2F/', '/%3F/', '/%2C/', '/%3D/', '/%26/', '/%25/', '/%23/', '/%2B/', '/%3B/', '/%5C/', '/%20/');
    $to   = array(':',     '/',     '?',     ',',     '=',     '&',     '%',     '#',     '+',     ';',     '\\',    ' ');
    $url = preg_replace($from, $to, $url);
    $root_path = preg_replace('/^\//', '', $phpbb_root_path);
    if($root_path == '/')
    {
        $url = preg_replace('#^\.\./|^/#si', '', $url);
    }
    else 
    {
        $url = preg_replace('#^\.\./|^/|'.addslashes($root_path).'#si', '', $url);
    }
    
    $url = preg_replace('#^.*?(?=download/file\.php)#si', '', $url);
 	
    
    
    if (strpos($url, 'http') !== 0 && strpos($url, 'https') !== 0 && strpos($url, 'mailto') !== 0)
    {
    	if(!$is_domain)
        	$url = $phpbb_home.$url;
        else 
        	$url = "http://".$server_url.'/'.$url;
    }
    
    return htmlspecialchars_decode($url);
}

function check_return_user_type($user_id)
{
    global $db, $user, $config;
    //$session = new user();
    $user_id = intval($user_id);
    $user_row = tt_get_user_by_id($user_id);
    $sql = "SELECT group_name FROM " . USER_GROUP_TABLE . " AS ug LEFT JOIN " .GROUPS_TABLE. " AS g ON ug.group_id = g.group_id WHERE user_id = " . $user_id;
    $query = $db->sql_query($sql);
    $is_ban = $user->check_ban($user_id,false,false,true);
    $user_groups = array();
    while($row = $db->sql_fetchrow($query))
    {
        $user_groups[] = $row['group_name'];
    }
    if(!empty($is_ban ))
    {
        $user_type = 'banned';
    }
    else if(in_array('ADMINISTRATORS', $user_groups))
    {
        $user_type = 'admin';
    }
    else if(in_array('GLOBAL_MODERATORS', $user_groups))
    {
        $user_type = 'mod';
    }
    else if($user_row['user_type'] == USER_INACTIVE && $config['require_activation'] == USER_ACTIVATION_ADMIN)
    {
    	$user_type = 'unapproved';
    }
    else if($user_row['user_type'] == USER_INACTIVE)
    {
    	$user_type = 'inactive';
    }
    else
    {
        $user_type = 'normal';
    }
    return basic_clean($user_type);
}

function tt_get_user_by_id($uid)
{
    global $db;
    $uid = intval($uid);
    $sql = 'SELECT *
        FROM ' . USERS_TABLE . "
        WHERE user_id = '" . $db->sql_escape($uid) . "'";
    $result = $db->sql_query($sql);
    $row = $db->sql_fetchrow($result);
    $db->sql_freeresult($result);
    return $row;
}

function tt_get_session_by_id($uid)
{
    global $db;
    $uid = intval($uid);
    $sql = 'SELECT *
        FROM ' . SESSIONS_TABLE . "
        WHERE session_user_id = '" . $db->sql_escape($uid) . "'";
    $result = $db->sql_query($sql);
    $row = $db->sql_fetchrow($result);
    $db->sql_freeresult($result);
    return $row;
}

function tt_get_attachment_by_id($aid)
{
    global $db;
    $aid = intval($aid);
    $sql = 'SELECT *
        FROM ' . ATTACHMENTS_TABLE . '
        WHERE attach_id = '.$aid;
    $result = $db->sql_query($sql);
    
    $row = $db->sql_fetchrow($result);
    $db->sql_freeresult($result);
    return $row;
}

function tt_get_subscribed_topic_by_id($uid)
{
    global $db;
    $uid = intval($uid);
    $sql = 'SELECT topic_id
        FROM ' . TOPICS_WATCH_TABLE . '
        WHERE user_id = ' . $uid;
    $result = $db->sql_query($sql);
    
    while ($row = $db->sql_fetchrow($result))
    {
        $subscribed_tids[] = (int) $row['topic_id'];
    }
    $db->sql_freeresult($result);
    
    return $subscribed_tids;
}

function tt_get_forum_name_by_id($fid)
{
    global $db;
    $fid = intval($fid);
    $sql = 'SELECT forum_name
        FROM ' . FORUMS_TABLE . '
        WHERE forum_id = ' . $fid;
    $result = $db->sql_query($sql);
    
    $title = '';
    while ($row = $db->sql_fetchrow($result))
    {
        $title = $row['forum_name'];
        }
    $db->sql_freeresult($result);
    return $title;
}

function tt_get_forum_id_by_name($fname)
{
    global $db;
    $fname = basic_clean(trim($fname));
    $sql = 'SELECT forum_id
        FROM ' . FORUMS_TABLE . '
        WHERE forum_name = "' . $fname .'"';
    $result = $db->sql_query($sql);
    
    $title = '';
    while ($row = $db->sql_fetchrow($result))
    {
        $id = $row['forum_id'];
    }
    $db->sql_freeresult($result);
    return $id;
}


function tt_get_usergroup_by_id($uid)
{
    global $db, $user, $auth;
    
    $user_id = intval($uid);
	// Get group memberships
	// Also get visiting user's groups to determine hidden group memberships if necessary.
	$auth_hidden_groups = ($user_id === (int) $user->data['user_id'] || $auth->acl_gets('a_group', 'a_groupadd', 'a_groupdel')) ? true : false;
	$sql_uid_ary = ($auth_hidden_groups) ? array($user_id) : array($user_id, (int) $user->data['user_id']);

	// Do the SQL thang
	$sql = 'SELECT g.group_id, g.group_name, g.group_type, ug.user_id
		FROM ' . GROUPS_TABLE . ' g, ' . USER_GROUP_TABLE . ' ug
		WHERE ' . $db->sql_in_set('ug.user_id', $sql_uid_ary) . '
			AND g.group_id = ug.group_id
			AND ug.user_pending = 0';
	$result = $db->sql_query($sql);

	// Divide data into profile data and current user data
	$profile_groups = $user_groups = array();
	while ($row = $db->sql_fetchrow($result))
	{
		$row['user_id'] = (int) $row['user_id'];
		$row['group_id'] = (int) $row['group_id'];

		if ($row['user_id'] == $user_id)
		{
			$profile_groups[] = $row;
		}
		else
		{
			$user_groups[$row['group_id']] = $row['group_id'];
		}
	}
	$db->sql_freeresult($result);

	// Filter out hidden groups and sort groups by name
	$group_data = $group_sort = array();
	foreach ($profile_groups as $row)
	{
		if ($row['group_type'] == GROUP_SPECIAL)
		{
			// Lookup group name in language dictionary
			if (isset($user->lang['G_' . $row['group_name']]))
			{
				$row['group_name'] = $user->lang['G_' . $row['group_name']];
			}
		}
		else if (!$auth_hidden_groups && $row['group_type'] == GROUP_HIDDEN && !isset($user_groups[$row['group_id']]))
		{
			// Skip over hidden groups the user cannot see
			continue;
		}

		$group_sort[$row['group_id']] = utf8_clean_string($row['group_name']);
		$group_data[$row['group_id']] = $row;
	}
	unset($profile_groups);
	unset($user_groups);
	asort($group_sort);
    foreach($group_sort as $key => $value)
        $return_row[]=$value;
	unset($group_data);
	unset($group_sort);
	return $return_row;
}

function mobi_url_convert($a,$b)
{
    if(html_entity_decode(trim($a)) == trim($b))
    {
        return '[url]';
    }
    else 
    {
        return $b;
    }
}

function mobi_color_convert($color, $str)
{
    static $colorlist;
    
    if (preg_match('/#[\da-fA-F]{6}/is', $color))
    {
        if (!$colorlist)
        {
            $colorlist = array(
                '#000000' => 'Black',             '#708090' => 'SlateGray',       '#C71585' => 'MediumVioletRed', '#FF4500' => 'OrangeRed',
                '#000080' => 'Navy',              '#778899' => 'LightSlateGrey',  '#CD5C5C' => 'IndianRed',       '#FF6347' => 'Tomato',
                '#00008B' => 'DarkBlue',          '#778899' => 'LightSlateGray',  '#CD853F' => 'Peru',            '#FF69B4' => 'HotPink',
                '#0000CD' => 'MediumBlue',        '#7B68EE' => 'MediumSlateBlue', '#D2691E' => 'Chocolate',       '#FF7F50' => 'Coral',
                '#0000FF' => 'Blue',              '#7CFC00' => 'LawnGreen',       '#D2B48C' => 'Tan',             '#FF8C00' => 'Darkorange',
                '#006400' => 'DarkGreen',         '#7FFF00' => 'Chartreuse',      '#D3D3D3' => 'LightGrey',       '#FFA07A' => 'LightSalmon',
                '#008000' => 'Green',             '#7FFFD4' => 'Aquamarine',      '#D3D3D3' => 'LightGray',       '#FFA500' => 'Orange',
                '#008080' => 'Teal',              '#800000' => 'Maroon',          '#D87093' => 'PaleVioletRed',   '#FFB6C1' => 'LightPink',
                '#008B8B' => 'DarkCyan',          '#800080' => 'Purple',          '#D8BFD8' => 'Thistle',         '#FFC0CB' => 'Pink',
                '#00BFFF' => 'DeepSkyBlue',       '#808000' => 'Olive',           '#DA70D6' => 'Orchid',          '#FFD700' => 'Gold',
                '#00CED1' => 'DarkTurquoise',     '#808080' => 'Grey',            '#DAA520' => 'GoldenRod',       '#FFDAB9' => 'PeachPuff',
                '#00FA9A' => 'MediumSpringGreen', '#808080' => 'Gray',            '#DC143C' => 'Crimson',         '#FFDEAD' => 'NavajoWhite',
                '#00FF00' => 'Lime',              '#87CEEB' => 'SkyBlue',         '#DCDCDC' => 'Gainsboro',       '#FFE4B5' => 'Moccasin',
                '#00FF7F' => 'SpringGreen',       '#87CEFA' => 'LightSkyBlue',    '#DDA0DD' => 'Plum',            '#FFE4C4' => 'Bisque',
                '#00FFFF' => 'Aqua',              '#8A2BE2' => 'BlueViolet',      '#DEB887' => 'BurlyWood',       '#FFE4E1' => 'MistyRose',
                '#00FFFF' => 'Cyan',              '#8B0000' => 'DarkRed',         '#E0FFFF' => 'LightCyan',       '#FFEBCD' => 'BlanchedAlmond',
                '#191970' => 'MidnightBlue',      '#8B008B' => 'DarkMagenta',     '#E6E6FA' => 'Lavender',        '#FFEFD5' => 'PapayaWhip',
                '#1E90FF' => 'DodgerBlue',        '#8B4513' => 'SaddleBrown',     '#E9967A' => 'DarkSalmon',      '#FFF0F5' => 'LavenderBlush',
                '#20B2AA' => 'LightSeaGreen',     '#8FBC8F' => 'DarkSeaGreen',    '#EE82EE' => 'Violet',          '#FFF5EE' => 'SeaShell',
                '#228B22' => 'ForestGreen',       '#90EE90' => 'LightGreen',      '#EEE8AA' => 'PaleGoldenRod',   '#FFF8DC' => 'Cornsilk',
                '#2E8B57' => 'SeaGreen',          '#9370D8' => 'MediumPurple',    '#F08080' => 'LightCoral',      '#FFFACD' => 'LemonChiffon',
                '#2F4F4F' => 'DarkSlateGrey',     '#9400D3' => 'DarkViolet',      '#F0E68C' => 'Khaki',           '#FFFAF0' => 'FloralWhite',
                '#2F4F4F' => 'DarkSlateGray',     '#98FB98' => 'PaleGreen',       '#F0F8FF' => 'AliceBlue',       '#FFFAFA' => 'Snow',
                '#32CD32' => 'LimeGreen',         '#9932CC' => 'DarkOrchid',      '#F0FFF0' => 'HoneyDew',        '#FFFF00' => 'Yellow',
                '#3CB371' => 'MediumSeaGreen',    '#9ACD32' => 'YellowGreen',     '#F0FFFF' => 'Azure',           '#FFFFE0' => 'LightYellow',
                '#40E0D0' => 'Turquoise',         '#A0522D' => 'Sienna',          '#F4A460' => 'SandyBrown',      '#FFFFF0' => 'Ivory',
                '#4169E1' => 'RoyalBlue',         '#A52A2A' => 'Brown',           '#F5DEB3' => 'Wheat',           '#FFFFFF' => 'White',
                '#4682B4' => 'SteelBlue',         '#A9A9A9' => 'DarkGrey',        '#F5F5DC' => 'Beige',
                '#483D8B' => 'DarkSlateBlue',     '#A9A9A9' => 'DarkGray',        '#F5F5F5' => 'WhiteSmoke',
                '#48D1CC' => 'MediumTurquoise',   '#ADD8E6' => 'LightBlue',       '#F5FFFA' => 'MintCream',
                '#4B0082' => 'Indigo',            '#ADFF2F' => 'GreenYellow',     '#F8F8FF' => 'GhostWhite',
                '#556B2F' => 'DarkOliveGreen',    '#AFEEEE' => 'PaleTurquoise',   '#FA8072' => 'Salmon',
                '#5F9EA0' => 'CadetBlue',         '#B0C4DE' => 'LightSteelBlue',  '#FAEBD7' => 'AntiqueWhite',
                '#6495ED' => 'CornflowerBlue',    '#B0E0E6' => 'PowderBlue',      '#FAF0E6' => 'Linen',
                '#66CDAA' => 'MediumAquaMarine',  '#B22222' => 'FireBrick',       '#FAFAD2' => 'LightGoldenRodYellow',
                '#696969' => 'DimGrey',           '#B8860B' => 'DarkGoldenRod',   '#FDF5E6' => 'OldLace',
                '#696969' => 'DimGray',           '#BA55D3' => 'MediumOrchid',    '#FF0000' => 'Red',
                '#6A5ACD' => 'SlateBlue',         '#BC8F8F' => 'RosyBrown',       '#FF00FF' => 'Fuchsia',
                '#6B8E23' => 'OliveDrab',         '#BDB76B' => 'DarkKhaki',       '#FF00FF' => 'Magenta',
                '#708090' => 'SlateGrey',         '#C0C0C0' => 'Silver',          '#FF1493' => 'DeepPink',
            );
        }
        
        if (isset($colorlist[strtoupper($color)])) $color = $colorlist[strtoupper($color)];
    }
    
    return "<font color=\"$color\">$str</font>";
}

function process_bbcode($message, $uid)
{
    global $user,$config;

    //add custom replace
	if(!empty($config['tapatalk_custom_replace']))
    {
        $replace_arr = explode("\n", $config['tapatalk_custom_replace']);
        foreach ($replace_arr as $replace)
        {
            preg_match('/^\s*(\'|")((\#|\/|\!).+\3[ismexuADUX]*?)\1\s*,\s*(\'|")(.*?)\4\s*$/', $replace,$matches);         
            if(count($matches) == 6)
            {         
                $temp_str = $message;
                $message = @preg_replace($matches[2], $matches[5], $message);
                if(empty($message))
                {
                    $message = $temp_str;
                }
            }   
        }
    }
   
    // prcess bbcode: list
    $message = preg_replace('/\[\*:'.$uid.'\]/si', '[*]', $message);
    $message = preg_replace('/\[\/\*:(m:)?'.$uid.'\]/si', '', $message);
    $message = tt_covert_list($message, '/\[list:'.$uid.'\](.*?)\[\/list:u:'.$uid.'\]/si', '1', $uid);
    $message = tt_covert_list($message, '/\[list=[^\]]*?:'.$uid.'\](.*?)\[\/list:o:'.$uid.'\]/si', '2', $uid);
    // process video bbcode\    
    $message = preg_replace('/\[(youtube|yt|video|googlevideo|gvideo):'.$uid.'\](.*?)\[\/\1:'.$uid.'\]/sie', "video_bbcode_format('$1', '$2')", $message);
    $message = preg_replace('/\[(BBvideo)[\d, ]+:'.$uid.'\](.*?)\[\/\1:'.$uid.'\]/si', "[url=$2]YouTube Video[/url]", $message);
    $message = preg_replace('/\[(spoil|spoiler):'.$uid.'\](.*?)\[\/\1:'.$uid.'\]/si', "[spoiler]$2[/spoiler]", $message);
    $message = preg_replace('/\[HiddenText=(.*?)\](.*?)\[\/HiddenText\]/si', '[spoiler]$2[/spoiler]', $message);
    $message = preg_replace('/\[b:'.$uid.'\](.*?)\[\/b:'.$uid.'\]/si', '[b]$1[/b]', $message);
    $message = preg_replace('/\[i:'.$uid.'\](.*?)\[\/i:'.$uid.'\]/si', '[i]$1[/i]', $message);
    $message = preg_replace('/\[u:'.$uid.'\](.*?)\[\/u:'.$uid.'\]/si', '[u]$1[/u]', $message);
    $message = preg_replace('/\[s:'.$uid.'\](.*?)\[\/s:'.$uid.'\]/si', '[s]$1[/<s></s>]', $message);
    $message = preg_replace('/\[color=(\#[\da-fA-F]{3}|\#[\da-fA-F]{6}|[A-Za-z]{1,20}|rgb\(\d{1,3}, ?\d{1,3}, ?\d{1,3}\)):'.$uid.'\](.*?)\[\/color:'.$uid.'\]/si', '[color=$1]$2[/color]', $message);
    $message = preg_replace('/\[mp3preview:'.$uid.'\](.*?)\[\/mp3preview:'.$uid.'\]/si', '[url=$1]MP3 Preview[/url]', $message);
    
    // process other bbcode
    $message = preg_replace("/:$uid/si",'',$message);
    $message = preg_replace('/&quot;/si','"',$message);
    $message = preg_replace('/\[quote=\"(.*?)\"\]/si','[quote name="$1" ]',$message);
    $message = preg_replace('/\[size(.*?)\](.*?)\[\/size\]/si',"$2",$message);
    
    return $message;
}

function tt_covert_list($message,$preg,$type,$uid)
{
    while(preg_match($preg, $message, $blocks))
    {
        $list_str = "";
        $list_arr = explode('[*]', $blocks[1]);
        foreach ($list_arr as $key => $value)
        {
            $value = trim($value);
            if(!empty($value) && $key != 0)
            {
                if($type == '1')
                {
                    $key = ' * ';
                }
                else 
                {
                    $key = $key.'.';
                }
                $list_str .= $key.$value ."\n";
            }
            else if(!empty($value))
            {
                $list_str .= $value ."\n";
            }           
        }
        $message = str_replace($blocks[0], $list_str, $message);
    }
    return $message;
}

function tt_get_sticky_num_by_id($fid)
{
    global $db;
    $fid = intval($fid);
    $sql = 'SELECT COUNT(*)
        FROM ' . TOPICS_TABLE . '
        WHERE forum_id = '.$fid. '
        AND topic_type = '.POST_STICKY;
    $result = $db->sql_query($sql);
    
    $count = $db->sql_fetchrow($result);
    $db->sql_freeresult($result);
    return $count['COUNT(*)'];
}