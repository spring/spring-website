<?php
if (!defined('IN_PHPBB'))
{
	exit;
}
if (empty($lang) || !is_array($lang))
{
	$lang = array();
}
$lang = array_merge($lang, array(

	// UMIL stuff
	'ACP_MOBIQUO_TITLE'				=> 'Tapatalk',
	'ACP_MOBIQUO_TITLE_EXPLAIN'		=> 'A Tapatalk plugin for your forum',
	'MOBIQUO_TABLE_DELETED'			=> 'The Tapatalk table was successfully deleted',
	'MOBIQUO_TABLE_CREATED'			=> 'The Tapatalk table was successfully created',
	'MOBIQUO_TABLE_UPDATED'			=> 'The Tapatalk table was successfully updated',
	'MOBIQUO_NOTHING_TO_UPDATE'		=> 'Nothing to do....continuing',
	'ACP_MOBIQUO'                   => 'Tapatalk Settings',
    'ACP_MOBIQUO_SETTINGS'          => 'Tapatalk General',
	'ACP_MOBIQUO_SETTINGS_EXPLAIN'  => 'Default Tapatalk general settings can be changed here.',
	'ACP_MOBIQUO_MOD_VER'           => 'MOD version',
	'LOG_CONFIG_MOBIQUO'            => 'Update tapatalk settings',
	'acl_a_mobiquo'                 => array('lang' => 'Can manage Tapatalk settings', 'cat' => 'misc'),

	'TP_PUSHENABLED'                => 'push enabled',
	'TP_PUSHENABLED_EXPLAIN' 		=> 'If push enabled,you will push message to users',
	'MOBIQUO_HIDE_FORUM_ID' 		=> 'Hide Forums',
	'MOBIQUO_HIDE_FORUM_ID_EXPLAIN' => 'Hide forums you don\'t want them to be listed in Tapatalk app.',
	'MOBIQUO_NAME' 					=> 'Tapatalk plugin directory',
	'MOBIQUO_NAME_EXPLAIN'			=> 'Never change it if you did not rename the Tapatalk plugin directory. And the default value is \'mobiquo\'. If you renamed the Tapatalk plugin directory, you also need to update the same setting for this forum in tapatalk forum owner area.(http://tapatalk.com/landing.php)',
	'MOBIQUO_REG_URL' 				=> 'Registration URL',
	'MOBIQUO_REG_URL_EXPLAIN' 		=> 'This field is required if you select "Redirect to External Registration URL" under "Registration Options". You do not need to include the forum root URL.',
	'TAPATALK_PUSH_KEY' 			=> 'Tapatalk API Key',
    'TAPATALK_PUSH_KEY_EXPLAIN' 	=> 'Formerly known as Push Key. This key is now required for secure connection between your community and Tapatalk server. Features such as Push Notification and Single Sign-On requires this key to work.',

	'ACP_TAPATALK_REBRANDING'          => 'Tapatalk BYO Options',
	'ACP_TAPATALK_REBRANDING_EXPLAIN'  => 'Tapatalk - Build Your Own - Options',
	'TAPATALK_FORUM_READ_ONLY'         => 'Disable New Topic' ,
	'TAPATALK_FORUM_READ_ONLY_EXPLAIN' => 'Prevent Tapatalk users to create new topic in the selected sub-forums. This feature is useful if certain forums requires additional topic fields or permission that Tapatalk does not support.',

	'TAPATALK_ANDROID_URL'          => 'Android Product ID',
	'TAPATALK_KINDLE_URL'           => 'Kindle Fire Product URL',
	'TAPATALK_ANDROID_URL_EXPLAIN'          => 'Enter your BYO App ID from Google Play, to be used on Android device. E.g. "com.quoord.tapatalkpro.activity".',
	'TAPATALK_KINDLE_URL_EXPLAIN'           => 'Enter your BYO App URL from Amazon App Store, to be used on Kindle Fire device.',
	
	'TAPATALK_ALLOW_APP_ADS'               => 'Mobile Welcome Screen',
	'TAPATALK_ALLOW_APP_ADS_EXPLAIN'       => 'Tapatalk will show a one time welcoming screen to mobile users to download the free app, with a button to get the free app.',
	
	'TAPATALK_CUSTOM_REPLACE'               => 'Thread Content Replacement (Advanced)',
	'TAPATALK_CUSTOM_REPLACE_EXPLAIN'       => 'Ability to match and replace thread content using PHP preg_replace function(http://www.php.net/manual/en/function.preg-replace.php). E.g. "\'pattern\',\'replacement\'" . You can define more than one replace rule on each line.',
	
	
	'TAPATALK_APP_BANNER_MSG'               => 'BYO App Banner Message',
	'TAPATALK_APP_IOS_ID'                   => 'BYO iOS App ID',
	'TAPATALK_APP_BANNER_MSG_EXPLAIN'       => 'E.g. "Follow {your_forum_name} with {app_name} for [os_platform]". Do not change the [os_platform] tag as it is displayed dynamically based on user\'s device platform.',
	'TAPATALK_APP_IOS_ID_EXPLAIN'           => 'Enter your BYO product ID in Apple App Store, to be used on iOS device.',
	
	'ACP_MOBIQUO_REGISTER_SETTINGS'  => 'Tapatalk - In App Registration',   
	'ACP_MOBIQUO_REGISTER_SETTINGS_EXPLAIN'  => 'Tapatalk - In App Registration Settings',  
	'TAPATALK_REGISTER_GROUP' => 'User Group Assignment', 
	'TAPATALK_REGISTER_GROUP_EXPLAIN' => 'You can assign users registered with Tapatalk to specific user groups. If you do not assign them to a specific group, they will be assigned a default group.', 
	'TAPATALK_REGISTER_STATUS' => 'Registration Options',
	'TAPATALK_REGISTER_STATUS_EXPLAIN' => 'Native Registration and Social Sign On (Recommended) - Facebook users can register for your forum using their Facebook credentials, and those not connected to Facebook can register for your forum via an in-app form.<br/>
Native Registration Only - No SSO available for Facebook users. All users must register for the forum via an in-app form.<br/>
Redirect to External Registration URL - All users registering for your forum will be redirected to a web browser outside of the app to continue registration. ',
	

	'TAPATALK_REGISTER_STATUS_SSO' => 'Native Registration and Social Sign On (Recommended)',
	'TAPATALK_REGISTER_STATUS_NATIVE' => 'Native Registration Only',
	'TAPATALK_REGISTER_STATUS_URL' => 'Redirect to External Registration URL',

	'TAPATALK_SPAM_STATUS' => 'Spam Prevention',
	'TAPATALK_SPAM_STATUS_EXPLAIN' => 'By enabling StopForumSpam integration, new user registration from Tapatalk app and/or from web will be screened with StopForumSpam database to prevent existing black-listed spammers.',
	'TAPATALK_SPAM_STATUS_0' => 'Disable',
	'TAPATALK_SPAM_STATUS_1' => 'Enable StopForumSpam in Tapatalk in-app registration',
	'TAPATALK_SPAM_STATUS_2' => 'Enable StopForumSpam in web registration',
	'TAPATALK_SPAM_STATUS_3' => 'Enable both',
	'LOG_CONFIG_REBRANDING' => 'Update Tapatalk rebranding settings',
	'LOG_CONFIG_REGISTER' => 'Update Tapatalk register settings',
	'TAPATALK_ALLOW_APP_BANNER' => 'Mobile Smart Banner',
	'TAPATALK_ALLOW_APP_BANNER_EXPLAIN' => 'Tapatalk will show a smart banner to mobile users, when your forum is viewed by a mobile web browser. The smart banner will contain two buttons: "Open in app" and "Install".',
	'TAPATALK_AD_FILTER' => 'Disable Ads for Group',
	'TAPATALK_AD_FILTER_EXPLAIN' => 'This option will prevent Tapatalk from displaying advertisements. Users in the selected groups will not be served ads when using the Tapatalk app.',

	)
);
?>