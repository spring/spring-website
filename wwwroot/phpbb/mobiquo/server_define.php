<?php
/**
*
* @copyright (c) 2009, 2010, 2011 Quoord Systems Limited
* @license http://opensource.org/licenses/gpl-2.0.php GNU Public License (GPLv2)
*
*/

defined('IN_MOBIQUO') or exit;

$server_param = array(

    'login' => array(
        'function'  => 'login_func',
        'signature' => array(array($xmlrpcStruct, $xmlrpcBase64, $xmlrpcBase64),
                             array($xmlrpcStruct, $xmlrpcBase64, $xmlrpcBase64, $xmlrpcBoolean),
                             array($xmlrpcStruct, $xmlrpcBase64, $xmlrpcBase64, $xmlrpcBoolean,$xmlrpcString)),
        'docstring' => 'login need two parameters,the first is user name(Base64), second is password(Base64).',
    ),

    'get_forum' => array(
        'function' => 'get_forum_func',
        'signature' => array(array($xmlrpcArray),
                             array($xmlrpcArray, $xmlrpcBoolean),
                             array($xmlrpcArray, $xmlrpcBoolean, $xmlrpcString)),
    ),

    'get_topic' => array(
        'function'  => 'get_topic_func',
        'signature' => array(array($xmlrpcStruct, $xmlrpcString, $xmlrpcInt, $xmlrpcInt, $xmlrpcString),
                             array($xmlrpcStruct, $xmlrpcString, $xmlrpcInt, $xmlrpcInt),
                             array($xmlrpcStruct, $xmlrpcString, $xmlrpcInt),
                             array($xmlrpcStruct, $xmlrpcString)),
        'docstring' => 'parameter should be array(string,int,int,string)',
    ),

    'get_thread' => array(
        'function'  => 'get_thread_func',
        'signature' => array(array($xmlrpcStruct, $xmlrpcString, $xmlrpcInt, $xmlrpcInt, $xmlrpcBoolean),
                             array($xmlrpcStruct, $xmlrpcString, $xmlrpcInt, $xmlrpcInt),
                             array($xmlrpcStruct, $xmlrpcString)),
    ),

    'get_thread_by_unread' => array(
        'function'  => 'get_thread_func',
        'signature' => array(array($xmlrpcStruct, $xmlrpcString, $xmlrpcInt, $xmlrpcBoolean),
                             array($xmlrpcStruct, $xmlrpcString, $xmlrpcInt),
                             array($xmlrpcStruct, $xmlrpcString)),
    ),

    'get_thread_by_post' => array(
        'function'  => 'get_thread_func',
        'signature' => array(array($xmlrpcStruct, $xmlrpcString, $xmlrpcInt, $xmlrpcBoolean),
                             array($xmlrpcStruct, $xmlrpcString, $xmlrpcInt),
                             array($xmlrpcStruct, $xmlrpcString)),
    ),

    'get_raw_post' => array(
        'function'  => 'get_raw_post_func',
        'signature' => array(array($xmlrpcStruct, $xmlrpcString)),
        'docstring' => 'parameter should be array(string)',
    ),

    'save_raw_post' => array(
        'function'  => 'save_raw_post_func',
        'signature' => array(array($xmlrpcStruct, $xmlrpcString, $xmlrpcBase64, $xmlrpcBase64),
                             array($xmlrpcStruct, $xmlrpcString, $xmlrpcBase64, $xmlrpcBase64, $xmlrpcBoolean),
                             array($xmlrpcStruct, $xmlrpcString, $xmlrpcBase64, $xmlrpcBase64, $xmlrpcBoolean, $xmlrpcArray,$xmlrpcString),
                             array($xmlrpcStruct, $xmlrpcString, $xmlrpcBase64, $xmlrpcBase64, $xmlrpcBoolean, $xmlrpcArray,$xmlrpcString, $xmlrpcBase64),
                       ),
        'docstring' => 'parameter should be array(string, base64, base64)',
    ),

    'get_quote_post' => array(
        'function'  => 'get_quote_post_func',
        'signature' => array(array($xmlrpcStruct, $xmlrpcString)),
        'docstring' => 'parameter should be array(string)',
    ),

    'get_quote_pm' => array(
        'function'  => 'get_quote_pm_func',
        'signature' => array(array($xmlrpcStruct, $xmlrpcString)),
        'docstring' => 'parameter should be array(string)',
    ),

    'get_user_topic' => array(
        'function'  => 'search_func',
        'signature' => array(array($xmlrpcStruct),
                             array($xmlrpcStruct, $xmlrpcBase64),
                             array($xmlrpcStruct, $xmlrpcBase64, $xmlrpcString)),
    ),

    'get_user_reply_post' => array(
        'function'  => 'search_func',
        'signature' => array(array($xmlrpcStruct),
                             array($xmlrpcStruct, $xmlrpcBase64),
                             array($xmlrpcStruct, $xmlrpcBase64, $xmlrpcString)),
    ),

    'get_latest_topic' => array(
        'function' => 'search_func',
        'signature' => array(array($xmlrpcArray),
                             array($xmlrpcArray, $xmlrpcInt, $xmlrpcInt),
                             array($xmlrpcArray, $xmlrpcInt, $xmlrpcInt, $xmlrpcString),
                             array($xmlrpcArray, $xmlrpcInt, $xmlrpcInt, $xmlrpcString, $xmlrpcStruct)),
    ),

    'get_unread_topic' => array(
        'function' => 'search_func',
        'signature' => array(array($xmlrpcArray),
                             array($xmlrpcArray, $xmlrpcInt, $xmlrpcInt),
                             array($xmlrpcArray, $xmlrpcInt, $xmlrpcInt, $xmlrpcString),
                             array($xmlrpcArray, $xmlrpcInt, $xmlrpcInt, $xmlrpcString, $xmlrpcStruct)),
    ),

    'get_participated_topic' => array(
        'function' => 'search_func',
        'signature' => array(array($xmlrpcArray, $xmlrpcBase64, $xmlrpcInt, $xmlrpcInt, $xmlrpcString, $xmlrpcString),
                             array($xmlrpcArray, $xmlrpcBase64, $xmlrpcInt, $xmlrpcInt, $xmlrpcString),
                             array($xmlrpcArray, $xmlrpcBase64, $xmlrpcInt, $xmlrpcInt),
                             array($xmlrpcArray, $xmlrpcBase64),
                             array($xmlrpcArray)),
    ),

    'get_subscribed_topic' => array(
        'function'  => 'search_func',
        'signature' => array(array($xmlrpcArray),
                             array($xmlrpcStruct, $xmlrpcInt, $xmlrpcInt)),
        'docstring' => 'no need parameters for get_subscribed_topic',
    ),

    'get_subscribed_forum' => array(
        'function'  => 'get_subscribed_forum_func',
        'signature' => array(array($xmlrpcArray)),
        'docstring' => 'no need parameters for get_subscribed_forum',
    ),

    'get_user_info' => array(
        'function' => 'get_user_info_func',
        'signature' => array(array($xmlrpcStruct),
                             array($xmlrpcStruct, $xmlrpcBase64),
                             array($xmlrpcStruct, $xmlrpcBase64, $xmlrpcString)),
    ),
	
    'get_contact'   => array(
    	'function' => 'get_contact_func',
        'signature' => array(array($xmlrpcStruct,$xmlrpcString)),
    ),
    
    'get_config' => array(
        'function'  => 'get_config_func',
        'signature' => array(array($xmlrpcArray)),
        'docstring' => 'no need parameters for get_forum',
    ),

    'logout_user' => array(
        'function'  => 'logout_user_func',
        'signature' => array(array($xmlrpcArray)),
        'docstring' => 'no need parameters for logout',
    ),

    'new_topic' => array(
        'function'  => 'new_topic_func',
        'signature' => array(array($xmlrpcStruct, $xmlrpcString, $xmlrpcBase64, $xmlrpcBase64),
                             array($xmlrpcStruct, $xmlrpcString, $xmlrpcBase64, $xmlrpcBase64, $xmlrpcString),
                             array($xmlrpcStruct, $xmlrpcString, $xmlrpcBase64, $xmlrpcBase64, $xmlrpcString, $xmlrpcArray),
                             array($xmlrpcStruct, $xmlrpcString, $xmlrpcBase64, $xmlrpcBase64, $xmlrpcString, $xmlrpcArray, $xmlrpcString)),
        'docstring' => 'parameter should be array(string,byte,byte,[string],[array])',
    ),

    'reply_post' => array(
        'function'  => 'reply_post_func',
        'signature' => array(array($xmlrpcStruct, $xmlrpcString, $xmlrpcString, $xmlrpcBase64, $xmlrpcBase64),
                             array($xmlrpcStruct, $xmlrpcString, $xmlrpcString, $xmlrpcBase64, $xmlrpcBase64, $xmlrpcArray, $xmlrpcString),
                             array($xmlrpcStruct, $xmlrpcString, $xmlrpcString, $xmlrpcBase64, $xmlrpcBase64, $xmlrpcArray, $xmlrpcString, $xmlrpcBoolean)),
        'docstring' => 'parameter should be array(int,string,string)',
    ),

    'subscribe_topic' => array(
        'function'  => 'subscribe_topic_func',
        'signature' => array(array($xmlrpcStruct, $xmlrpcString)),
        'docstring' => 'subscribe_topic need one parameters as topic id.',
    ),

    'unsubscribe_topic' => array(
        'function'  => 'unsubscribe_topic_func',
        'signature' => array(array($xmlrpcStruct, $xmlrpcString)),
        'docstring' => 'unsubscribe_topic need one parameters as topic id.',
    ),

    'subscribe_forum' => array(
        'function'  => 'subscribe_forum_func',
        'signature' => array(array($xmlrpcStruct, $xmlrpcString)),
        'docstring' => 'subscribe_topic need one parameters as forum id.',
    ),

    'unsubscribe_forum' => array(
        'function'  => 'unsubscribe_forum_func',
        'signature' => array(array($xmlrpcStruct, $xmlrpcString)),
        'docstring' => 'unsubscribe_topic need one parameters as forum id.',
    ),

    'get_inbox_stat' => array(
        'function'  => 'get_inbox_stat_func',
        'signature' => array(array($xmlrpcArray)),
        'docstring' => 'no parameter but need login first',
    ),

    'get_box_info' => array(
        'function'  => 'get_box_info_func',
        'signature' => array(array($xmlrpcArray)),
        'docstring' => 'no parameter but need login first',
    ),

    'get_box' => array(
        'function'  => 'get_box_func',
        'signature' => array(array($xmlrpcStruct, $xmlrpcString, $xmlrpcInt, $xmlrpcInt, $xmlrpcDateTime),
                             array($xmlrpcStruct, $xmlrpcString, $xmlrpcInt, $xmlrpcInt),
                             array($xmlrpcStruct, $xmlrpcString, $xmlrpcInt),
                             array($xmlrpcStruct, $xmlrpcString)),
        'docstring' => 'parameter should be array(string,int,int,date)',
    ),

    'get_message' => array(
        'function'  => 'get_message_func',
        'signature' => array(array($xmlrpcStruct, $xmlrpcString),
                             array($xmlrpcStruct, $xmlrpcString, $xmlrpcString),
                             array($xmlrpcStruct, $xmlrpcString, $xmlrpcString, $xmlrpcBoolean)),
        'docstring' => 'get_message need one parameter as message id'
    ),

    'delete_message' => array(
        'function'  => 'delete_message_func',
        'signature' => array(array($xmlrpcStruct, $xmlrpcString),
                             array($xmlrpcStruct, $xmlrpcString, $xmlrpcString)),
        'docstring' => 'get_message need one parameter as message id'
    ),

    'create_message' => array(
        'function'  => 'create_message_func',
        'signature' => array(array($xmlrpcStruct, $xmlrpcArray, $xmlrpcBase64, $xmlrpcBase64),
                             array($xmlrpcStruct, $xmlrpcArray, $xmlrpcBase64, $xmlrpcBase64, $xmlrpcInt, $xmlrpcString)),
        'docstring' => 'parameter should be array(array,string,string,[int, string])',
    ),

    'get_board_stat' => array(
        'function'  => 'get_board_stat_func',
        'signature' => array(array($xmlrpcStruct)),
        'docstring' => 'no parameter',
    ),

    'get_online_users' => array(
        'function'  => 'get_online_users_func',
        'signature' => array(array($xmlrpcStruct)),
        'docstring' => 'no parameter',
    ),

    'mark_all_as_read' => array(
        'function'  => 'mark_all_as_read_func',
        'signature' => array(array($xmlrpcArray),
                             array($xmlrpcStruct, $xmlrpcString)),
        'docstring' => 'no need parameters for mark_all_as_read',
    ),

    'search' => array(
        'function' => 'search_func',
        'signature' => array(array($xmlrpcStruct, $xmlrpcStruct)),
    ),

    'search_topic' => array(
        'function'  => 'search_func',
        'signature' => array(array($xmlrpcStruct, $xmlrpcBase64, $xmlrpcInt, $xmlrpcInt, $xmlrpcString),
                             array($xmlrpcStruct, $xmlrpcBase64, $xmlrpcInt, $xmlrpcInt),
                             array($xmlrpcStruct, $xmlrpcBase64)),
        'docstring' => 'parameter should be array(base64,[int,int])',
    ),

    'search_post' => array(
        'function'  => 'search_func',
        'signature' => array(array($xmlrpcStruct, $xmlrpcBase64, $xmlrpcInt, $xmlrpcInt, $xmlrpcString),
                             array($xmlrpcStruct, $xmlrpcBase64, $xmlrpcInt, $xmlrpcInt),
                             array($xmlrpcStruct, $xmlrpcBase64)),
        'docstring' => 'parameter should be array(base64,[int,int])',
    ),

    'report_post' => array(
        'function'  => 'report_post_func',
        'signature' => array(array($xmlrpcStruct, $xmlrpcString, $xmlrpcBase64),
                             array($xmlrpcStruct, $xmlrpcString)),
        'docstring' => 'parameter should be)',
    ),

    'report_pm' => array(
        'function'  => 'report_pm_func',
        'signature' => array(array($xmlrpcStruct, $xmlrpcString, $xmlrpcBase64),
                             array($xmlrpcStruct, $xmlrpcString)),
        'docstring' => 'parameter should be)',
    ),

    'get_participated_forum' => array(
        'function'  => 'get_participated_forum_func',
        'signature' => array(array($xmlrpcArray)),
    ),

    'login_forum' => array(
        'function'  => 'login_forum_func',
        'signature' => array(array($xmlrpcStruct, $xmlrpcString, $xmlrpcBase64)),
        'docstring' => 'parameter should be)',
    ),

    'upload_attach' => array(
        'function'  => 'upload_attach_func',
        'signature' => array(array($xmlrpcStruct)),
        'docstring' => 'parameter should be',
    ),

    'upload_avatar' => array(
        'function'  => 'upload_avatar_func',
        'signature' => array(array($xmlrpcStruct)),
        'docstring' => 'parameter should be',
    ),

    'remove_attachment' => array(
        'function'  => 'remove_attachment_func',
        'signature' => array(array($xmlrpcStruct, $xmlrpcString, $xmlrpcString, $xmlrpcString),
                             array($xmlrpcStruct, $xmlrpcString, $xmlrpcString, $xmlrpcString, $xmlrpcString)),
        'docstring' => 'parameter should be',
    ),

    'get_id_by_url' => array(
        'function'  => 'get_id_by_url_func',
        'signature' => array(array($xmlrpcStruct, $xmlrpcString)),
        'docstring' => 'get_id_by_url need one parameters as url.',
    ),

    'thank_post' => array(
        'function' => 'xmlresptrue',
        'signature' => array(array($xmlrpcStruct, $xmlrpcString)),
    ),


    // Moderation functions
    'm_stick_topic' => array(
        'function'  => 'xmlresptrue',
        'signature' => array(array($xmlrpcArray, $xmlrpcString, $xmlrpcInt)),
        'docstring' => '',
    ),
    'm_close_topic' => array(
        'function'  => 'xmlresptrue',
        'signature' => array(array($xmlrpcArray, $xmlrpcString, $xmlrpcInt)),
        'docstring' => '',
    ),
    'm_delete_topic' => array(
        'function'  => 'xmlresptrue',
        'signature' => array(array($xmlrpcArray, $xmlrpcString, $xmlrpcInt, $xmlrpcBase64)),
        'docstring' => '',
    ),
    'm_delete_post' => array(
        'function'  => 'xmlresptrue',
        'signature' => array(array($xmlrpcArray, $xmlrpcString, $xmlrpcInt, $xmlrpcBase64)),
        'docstring' => '',
    ),
    'm_move_topic' => array(
        'function'  => 'xmlresptrue',
        'signature' => array(array($xmlrpcArray, $xmlrpcString, $xmlrpcString),
    						 array($xmlrpcArray, $xmlrpcString, $xmlrpcString, $xmlrpcBoolean),
    				   ),
        'docstring' => '',
    ),
    'm_move_post' => array(
        'function'  => 'xmlresptrue',
        'signature' => array(array($xmlrpcArray, $xmlrpcString, $xmlrpcString),
                             array($xmlrpcArray, $xmlrpcString, $xmlrpcString, $xmlrpcBase64, $xmlrpcString)),
        'docstring' => '',
    ),
    'm_merge_topic' => array(
        'function'  => 'xmlresptrue',
        'signature' => array(array($xmlrpcArray, $xmlrpcString, $xmlrpcString)),
        'docstring' => '',
    ),
    'm_get_moderate_topic' => array(
        'function'  => 'm_get_moderate_topic_func',
        'signature' => array(array($xmlrpcArray),
                             array($xmlrpcArray, $xmlrpcInt),
                             array($xmlrpcArray, $xmlrpcInt, $xmlrpcInt),
                             array($xmlrpcArray, $xmlrpcInt, $xmlrpcInt, $xmlrpcInt)),
        'docstring' => '',
    ),
    'm_get_moderate_post' => array(
        'function'  => 'm_get_moderate_post_func',
        'signature' => array(array($xmlrpcArray),
                             array($xmlrpcArray, $xmlrpcInt),
                             array($xmlrpcArray, $xmlrpcInt, $xmlrpcInt),
                             array($xmlrpcArray, $xmlrpcInt, $xmlrpcInt, $xmlrpcInt)),
        'docstring' => '',
    ),
    'm_get_report_post' => array(
        'function'  => 'm_get_report_post_func',
        'signature' => array(array($xmlrpcArray),
                             array($xmlrpcArray, $xmlrpcInt, $xmlrpcInt)),
        'docstring' => '',
    ),
    'm_get_report_pm' => array(
        'function'  => 'm_get_report_pm_func',
        'signature' => array(array($xmlrpcArray),
                             array($xmlrpcArray, $xmlrpcInt, $xmlrpcInt)),
        'docstring' => '',
    ),
    'm_approve_topic' => array(
        'function'  => 'xmlresptrue',
        'signature' => array(array($xmlrpcArray, $xmlrpcString, $xmlrpcInt)),
        'docstring' => '',
    ),
    'm_approve_post' => array(
        'function'  => 'xmlresptrue',
        'signature' => array(array($xmlrpcArray, $xmlrpcString, $xmlrpcInt)),
        'docstring' => '',
    ),
    'm_ban_user' => array(
        'function'  => 'xmlresptrue',
        'signature' => array(
    		array($xmlrpcArray, $xmlrpcBase64, $xmlrpcInt, $xmlrpcBase64),
    		array($xmlrpcArray, $xmlrpcBase64, $xmlrpcInt, $xmlrpcBase64, $xmlrpcInt),
    	),
        'docstring' => '',
    ),
    'm_rename_topic' => array(
        'function'  => 'm_rename_topic_func',
        'signature' => array(
    						array($xmlrpcArray,$xmlrpcString,$xmlrpcBase64),
    						array($xmlrpcArray,$xmlrpcString,$xmlrpcBase64,$xmlrpcString),
    						),
        'docstring' => '',
    ),
    'm_close_report' => array(
    	'function'  => 'm_close_report_func',
        'signature' => array(array($xmlrpcArray, $xmlrpcString))
    ),
    
    'update_push_status' => array(
        'function' => 'update_push_status_func',
        'signature' => array(array($xmlrpcStruct,$xmlrpcStruct),
                       array($xmlrpcStruct,$xmlrpcStruct,$xmlrpcBase64, $xmlrpcBase64)),
    ),
    'get_alert' => array(
    	'function' => 'get_alert_func',
    	'signature' => array(array($xmlrpcStruct),
    						 array($xmlrpcStruct, $xmlrpcInt),
    						 array($xmlrpcStruct, $xmlrpcInt, $xmlrpcInt)),
    ),
    
    'register' => array (
    	'function' => 'register_func',
    	'signature' => array(array($xmlrpcStruct),
                             array($xmlrpcStruct, $xmlrpcBase64,$xmlrpcBase64,$xmlrpcBase64),
    						 array($xmlrpcStruct, $xmlrpcBase64,$xmlrpcBase64,$xmlrpcBase64,$xmlrpcString,$xmlrpcString),
    						 ),
    ),
    
    'update_password' => array (
    	'function' => 'update_password_func',
    	'signature' => array(array($xmlrpcStruct),
    						 array($xmlrpcStruct, $xmlrpcBase64,$xmlrpcBase64),
    						 array($xmlrpcStruct, $xmlrpcBase64,$xmlrpcString ,$xmlrpcString),
    						 ),
    ),
    
    'update_email' => array (
    	'function' => 'update_password_func',
    	'signature' => array(array($xmlrpcStruct),
    						 array($xmlrpcStruct, $xmlrpcBase64,$xmlrpcBase64),
    						 ),
    ),
    
    'forget_password' => array (
    	'function' => 'forget_password_func',
    	'signature' => array(array($xmlrpcStruct),
    						 array($xmlrpcStruct,$xmlrpcBase64),
    						 array($xmlrpcStruct, $xmlrpcBase64,$xmlrpcString ,$xmlrpcString),
    						 ),
    ),
    
    'sign_in' => array (
    	'function' => 'sign_in_func',
    	'signature' => array(
                             array($xmlrpcStruct, $xmlrpcString,$xmlrpcString),
    						 array($xmlrpcStruct, $xmlrpcString,$xmlrpcString,$xmlrpcBase64),
    						 array($xmlrpcStruct, $xmlrpcString,$xmlrpcString,$xmlrpcBase64,$xmlrpcBase64),
    						 array($xmlrpcStruct, $xmlrpcString,$xmlrpcString,$xmlrpcBase64,$xmlrpcBase64,$xmlrpcBase64),
    						 ),
    ), 
    
    'prefetch_account' => array(
    	'function' => 'prefetch_account_func',
    	'signature' => array(
                             array($xmlrpcStruct, $xmlrpcBase64),
    						 ),
    ),
    
    'mark_pm_unread' => array(
        'function'  => 'mark_pm_unread_func',
        'signature' => array(array($xmlrpcArray),
                             array($xmlrpcStruct, $xmlrpcString)),
        'docstring' => 'message id',
    ),
    
    'mark_pm_read' => array(
        'function'  => 'mark_pm_read_func',
        'signature' => array(array($xmlrpcArray),
                             array($xmlrpcStruct, $xmlrpcString)),
        'docstring' => 'message id',
    ),
    
    'search_user' => array(
    	'function' => 'search_user_func',
    	'signature' => array(array($xmlrpcStruct),
    						 array($xmlrpcStruct, $xmlrpcBase64 ),
    						 array($xmlrpcStruct, $xmlrpcBase64,$xmlrpcInt ,$xmlrpcInt),
    						 ),
    ),
    
    'get_recommended_user' => array(
    	'function' => 'get_recommended_user_func',
    	'signature' => array(array($xmlrpcStruct),
    						 array($xmlrpcStruct,$xmlrpcInt ,$xmlrpcInt),
    						 array($xmlrpcStruct,$xmlrpcInt ,$xmlrpcInt,$xmlrpcInt),
    						 ),
    ),
    
    'ignore_user' => array(
    	'function' => 'ignore_user_func',
    	'signature' => array(
    						 array($xmlrpcStruct,$xmlrpcString ,$xmlrpcInt),
    						 ),
    ),
);
