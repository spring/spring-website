<?php
define('IN_MOBIQUO',1);
define('IN_PHPBB', true);
//error_reporting(0);
$forum_root = dirname(dirname(__FILE__));
$mobiquo_root = dirname(__FILE__);
if(!$forum_root)
{
	$forum_root = '.';
}
if(!$mobiquo_root)
{
	$mobiquo_root = '.';
}

include($mobiquo_root . '/config/config.php');
include($forum_root . '/common.' . $phpEx);
require_once $mobiquo_root . '/mobiquo_common.php';
$uid = request_var('user_id','');

if(empty($uid))
{
    $uname = request_var('username','');
    if(empty($uname)){
        die('Invalid params!');
    }
	$uname = base64_decode($uname);
	$uname = trim($uname);	
	$user = tt_get_user_by_name($uname);
	$uid = $user['user_id'];
}
$avatars = get_user_avatars($uid);
$icon_url = $avatars[$uid];
header("Location:$icon_url");