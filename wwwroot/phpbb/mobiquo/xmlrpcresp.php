<?php
/**
*
* @copyright (c) 2009, 2010, 2011 Quoord Systems Limited
* @license http://opensource.org/licenses/gpl-2.0.php GNU Public License (GPLv2)
*
*/

defined('IN_MOBIQUO') or exit;

function login_func($data)
{
    global $user, $auth, $config, $request_params;
    
    $auth->acl($user->data);
    
    $group_id = array();
    $group_id[] = $user->data['group_id'];
    
    $can_readpm = $config['allow_privmsg'] && $auth->acl_get('u_readpm') && ($user->data['user_allow_pm'] || $auth->acl_gets('a_', 'm_') || $auth->acl_getf_global('m_'));
    $can_sendpm = $config['allow_privmsg'] && $auth->acl_get('u_sendpm') && ($user->data['user_allow_pm'] || $auth->acl_gets('a_', 'm_') || $auth->acl_getf_global('m_'));
    $can_mod    = $auth->acl_get('m_') || $auth->acl_getf_global('m_');
    $can_upload = ($config['allow_avatar_upload'] && file_exists($phpbb_root_path . $config['avatar_path']) && (function_exists('phpbb_is_writable') ? phpbb_is_writable($phpbb_root_path . $config['avatar_path']) : 1) && $auth->acl_get('u_chgavatar') && (@ini_get('file_uploads') || strtolower(@ini_get('file_uploads')) == 'on')) ? true : false;
    $can_search = $auth->acl_get('u_search') && $auth->acl_getf_global('f_search') && $config['load_search'];
    $can_whosonline = $auth->acl_gets('u_viewprofile', 'a_user', 'a_useradd', 'a_userdel');
    $max_filesize   = ($config['max_filesize'] === '0' || $config['max_filesize'] > 10485760) ? 10485760 : $config['max_filesize'];
    
    $userPushType = array('pm' => 1,'newtopic' => 1,'sub' => 1,'tag' => 1,'quote' => 1);
    $push_type = array();
    foreach ($userPushType as $name => $value)
    {
        $push_type[] = array(
            'name'  => $name,
            'value' => (boolean)$value,                    
            );
    }   
    
    $flood_interval = 0;
    if ($config['flood_interval'] && !$auth->acl_get('u_ignoreflood'))
    {
        $flood_interval = intval($config['flood_interval']);
    }

    $result = array(
        'result'       => true,
        'user_id'      => (string)$user->data['user_id'],
        'username'     => basic_clean($user->data['username']),
        'login_name'   => basic_clean($user->data['username']),
        'email'        => $user->data['user_email'],
        'user_type'    => $user->data['user_type'],
        'usergroup_id' => $group_id,
        'ignored_uids' => implode(',', tt_get_ignore_users($user->data['user_id'])),
        'icon_url'     => get_user_avatar_url($user->data),
        'post_count'   => (int)$user->data['user_posts'],
        'can_pm'       => false,//$can_readpm,
        'can_send_pm'  => false,//$can_sendpm, 
        'can_moderate'      => false,//$can_mod,
        'can_search'        => $can_search,
        'can_whosonline'    => $can_whosonline ? true : false, 
        'can_upload_avatar' => false,//$can_upload,
        'max_attachment'=> (int)$config['max_attachments'],
        'max_png_size'  => (int)$max_filesize, 
        'max_jpg_size'  => (int)$max_filesize, 
        'push_type'         => $push_type,  
        'post_countdown'    => $flood_interval,
    );

    mobi_resp($result);
    exit;
}

function get_topic_sub_func($data)
{
    global $config, $auth, $user, $request_params, $phpbb_container, $results, $perpage ;
    $topic_list = $data['topic_list'];
    $rowset     = $data['rowset'];
    $forum_id   = $request_params['0'];
    $watch_row = tt_get_subscribed_topic_by_id($user->data['user_id']);

    $allowed = $config['max_attachments'] && $auth->acl_get('f_attach', $forum_id) && $auth->acl_get('u_attach') && $config['allow_attachments'] && @ini_get('file_uploads') != '0' && strtolower(@ini_get('file_uploads')) != 'off';
    $max_attachment = ($auth->acl_get('a_') || $auth->acl_get('m_', $forum_id)) ? 99 : ($allowed ? $config['max_attachments'] : 0);
    $max_png_size   = ($auth->acl_get('a_') || $auth->acl_get('m_', $forum_id)) ? 10485760 : ($allowed ? ($config['max_filesize'] === '0' ? 10485760 : $config['max_filesize']) : 0);
    $max_jpg_size   = ($auth->acl_get('a_') || $auth->acl_get('m_', $forum_id)) ? 10485760 : ($allowed ? ($config['max_filesize'] === '0' ? 10485760 : $config['max_filesize']) : 0);
    
    $read_only_forums = explode(",", $config['tapatalk_forum_read_only']);
    $can_post = true;
    if(empty($read_only_forums) || !is_array($read_only_forums))
    {
        $read_only_forums = array();
    }
    if(!$auth->acl_get('f_post', $forum_id) || in_array($forum_id, $read_only_forums))
    {
        $can_post = false;
    }
    
    $topic_forum_list = array();
	foreach ($rowset as $t_id => $row)
	{
		if (isset($forum_tracking_info[$row['forum_id']]))
		{
			$row['forum_mark_time'] = $forum_tracking_info[$row['forum_id']];
		}

		$topic_forum_list[$row['forum_id']]['forum_mark_time'] = ($config['load_db_lastread'] && $user->data['is_registered'] && isset($row['forum_mark_time'])) ? $row['forum_mark_time'] : 0;
		$topic_forum_list[$row['forum_id']]['topics'][] = (int) $t_id;
	}
	
    $topic_tracking_info = array();
    if ($config['load_anon_lastread'] || $user->data['is_registered'])
	{
		foreach ($topic_forum_list as $f_id => $topic_row)
		{
			$topic_tracking_info += get_complete_topic_tracking($f_id, $topic_row['topics']);
		}
	}
    $topic_lists = array();
    $unread_sticky_count = 0;
    $unread_ann_count    = 0;
    foreach ($topic_list as $topic_id)
    {
        $row = $rowset[$topic_id];
        
		$topic_forum_id = ($row['forum_id']) ? (int) $row['forum_id'] : $forum_id;
		
        if ($row['topic_status'] == ITEM_MOVED)
		{
			$topic_id = $row['topic_moved_id'];
			$unread_topic = false;
		}
		else
		{
			$unread_topic = (isset($topic_tracking_info[$topic_id]) && $row['topic_last_post_time'] > $topic_tracking_info[$topic_id]) ? true : false;
		}
		$sticky   = (boolean)($row['topic_type'] == POST_STICKY);
		$announce = (boolean)($row['topic_type'] == POST_ANNOUNCE||$row['topic_type'] == POST_GLOBAL);
		$locked   = (boolean)($row['topic_status'] == ITEM_LOCKED);
		$moved    = (boolean)($row['topic_status'] == ITEM_MOVED);
		$topic_unapproved = (($row['topic_visibility'] == ITEM_UNAPPROVED || $row['topic_visibility'] == ITEM_REAPPROVE) && $auth->acl_get('m_approve', $row['forum_id']));
		$unread   = (boolean)$unread_topic;
		
		if($announce)  $ann++;
        if($sticky && $unread)$unread_sticky_count++;
        if($announce && $unread)$unread_ann_count++;
        
        if($request_params[3] == 'ANN')
        {
            if (!$announce) continue;
        }
        else if($request_params[3] == 'TOP')
        {
            continue;
        }
        else if($announce)
        {
            continue;
        }
        
        $author_id = $row['topic_poster'];

        $allow_change_type = ($auth->acl_get('m_', $forum_id) || ($user->data['is_registered'] && $user->data['user_id'] == $author_id)) ? true : false;
        
        $can_rename = (
            $user->data['is_registered'] && (
                $auth->acl_get('m_edit', $forum_id) || (
                    $user->data['user_id'] == $author_id &&
                    $auth->acl_get('f_edit', $forum_id) && 
                    //!$item['post_edit_locked'] &&
                    ($row['topic_time'] > time() - ($config['edit_time'] * 60) || !$config['edit_time'])
                )
            )
        );
        
        
        $phpbb_content_visibility = $phpbb_container->get('content.visibility');
        $replies = $phpbb_content_visibility->get_count('topic_posts', $row, $topic_forum_id) - 1;
        
        $avatars = get_user_avatars($author_id);
        $avatar = $avatars[$author_id];
        
        $last_post_id = $row['topic_last_post_id'];
        //print_r($row);
        $topic_info = array(
            'forum_id'          => (string)$forum_id,
            'topic_id'          => (string)$topic_id,
            'topic_title'       => html_entity_decode(strip_tags(censor_text($row['topic_title'])), ENT_QUOTES, 'UTF-8'),
            'topic_author_id'   => $author_id,
            'topic_author_name' => html_entity_decode($row['topic_first_poster_name']),
            'last_reply_time'   => mobiquo_iso8601_encode($row['topic_last_post_time']),
            'timestamp'         => $row['topic_last_post_time'],
            'reply_number'      => $replies,
            'view_number'       => (int)$row['topic_views'],
            'short_content'     => get_short_content($row['topic_first_post_id']),
            'new_post'          => $unread,
            'icon_url'          => $avatar,
            'attachment'        => (string)(($auth->acl_get('u_download') && $auth->acl_get('f_download', $row['forum_id']) && $row['topic_attachment']) ? '1' : '0'),
            
            'can_delete'        => false,//(boolean)$auth->acl_get('m_delete', $forum_id),
            'is_deleted'        => (boolean)((int)$row['topic_delete_time']!==0),
            'can_move'          => false,//(boolean)$auth->acl_get('m_move', $forum_id),
            'can_subscribe'     => (boolean)(($config['email_enable'] || $config['jab_enable']) && $config['allow_topic_notify'] && $user->data['is_registered']), 
            'is_subscribed'     => (boolean)((in_array($topic_id,$watch_row)) ? true : false),
            'can_close'         => false,//(boolean)($auth->acl_get('m_lock', $forum_id) || ($auth->acl_get('f_user_lock', $forum_id) && $user->data['is_registered'] && $user->data['user_id'] == $author_id)),
            'is_closed'         => $locked,
            'can_stick'         => false,//(boolean)($allow_change_type && $auth->acl_get('f_sticky', $forum_id)),
            'is_sticky'         => $sticky ,
            'can_approve'       => false,//$topic_unapproved ,
            'is_approved'       => (boolean)($topic_unapproved ? 0 : 1),
            'can_rename'        => false,//(boolean)($can_rename),
            'can_merge'         => false,//(boolean)($auth->acl_get('m_merge', $forum_id)),
            'is_moved'          => $moved,
            'real_topic_id'     => (string)$topic_id,
        );
        $topic_lists[] = $topic_info;
    }
    
    

    $results = array(
        'total_topic_num'        => ($request_params[3] == 'TOP') ? 0 : (int)$data['total_topic_count'],
        'unread_sticky_count'    => $unread_sticky_count,
        'unread_announce_count'  => $unread_ann_count,
        'forum_id'               => $forum_id,
        'forum_name'             => '',
        'can_post'               => $can_post,
        'can_upload'             => false,//$allowed,
        'max_attachment'         => $max_attachment,
        'max_png_size'           => $max_png_size,
        'max_jpg_size'           => $max_jpg_size,
        'topics'                 => $topic_lists,
    );
}
function get_topic_func()
{
    global $results, $request_params;
    $results['forum_name'] = basic_clean(tt_get_forum_name_by_id(trim($request_params[0])));
    mobi_resp($results);
}

function get_thread_sub_func($data)
{
    global $auth, $config, $user, $results, $phpEx;
    $topic_data = $data['topic_data'];
    $user_cache = $data['user_cache'];
    $forum_id = $data['forum_id'];
    $topic_id = $data['topic_id'];
    $phpbb_home = generate_board_url().'/';
    
    $can_subscribe = ($config['email_enable'] || $config['jab_enable']) && $config['allow_topic_notify'] && $user->data['is_registered'];
    $allow_change_type = ($auth->acl_get('m_', $forum_id) || ($user->data['is_registered'] && $user->data['user_id'] == $topic_data['topic_poster'])) ? true : false;
    $allowed = $config['max_attachments'] && $auth->acl_get('f_attach', $forum_id) && $auth->acl_get('u_attach') && $config['allow_attachments'] && @ini_get('file_uploads') != '0' && strtolower(@ini_get('file_uploads')) != 'off';
    $max_attachment = ($auth->acl_get('a_') || $auth->acl_get('m_', $forum_id)) ? 99 : ($allowed ? $config['max_attachments'] : 0);
    $max_png_size = ($auth->acl_get('a_') || $auth->acl_get('m_', $forum_id)) ? 10485760 : ($allowed ? ($config['max_filesize'] === '0' ? 10485760 : $config['max_filesize']) : 0);
    $max_jpg_size = ($auth->acl_get('a_') || $auth->acl_get('m_', $forum_id)) ? 10485760 : ($allowed ? ($config['max_filesize'] === '0' ? 10485760 : $config['max_filesize']) : 0);
    $can_rename = (
        $user->data['is_registered'] && (
            $auth->acl_get('m_edit', $forum_id) || (
                $user->data['user_id'] == $row['topic_poster'] &&
                $auth->acl_get('f_edit', $forum_id) &&
                //!$item['post_edit_locked'] &&
                ($topic_data['topic_time'] > time() - ($config['edit_time'] * 60) || !$config['edit_time'])
            )
        )
    );
    $is_poll = !empty($topic_data['poll_title']) ? true : false;
    
    $post_list = array();
    foreach($data['rowset'] as $key => $row)
    {
        $poster_id = $row['user_id'];
        
        if ($row['foe'])
        {
            $l_ignore_post = sprintf($user->lang['POST_BY_FOE'], get_username_string('full', $poster_id, $row['username'], $row['user_colour'], $row['post_username']));
            $row['post_text'] = '[color=Gray]' . $l_ignore_post . '[/color]' . "[spoiler]{$row['post_text']}[/spoiler]";
        }

        $can_ban_user = $auth->acl_get('m_ban') && $row['post_id'] != $user->data['user_id'];
        
        $attachments = array();
        if (!empty($data['attachments'][$key]))
        {
            foreach($data['attachments'][$key] as $attachKey => $attachValue)
            {
                $attach_id = $attachValue['attach_id'];
                $file_url = $phpbb_home."download/file.$phpEx?id=$attach_id";
                
                if (strpos($attachValue['mimetype'], 'image') === 0)
                    $content_type = 'image';
                else
                    $content_type = $attachValue['extension'];
                
                $thumbnail_url = '';
                if ($config['img_create_thumbnail'] && $attachValue['thumbnail'])
                {
                    $thumbnail_url = preg_replace('/file\.php\?/is', 'file.php?t=1&', $file_url);
                }
                    
                $arrachment = array(
                    'filename'      => $attachValue['real_filename'],
                    'filesize'      => (int)$attachValue['filesize'],
                    'content_type'  => $content_type,
                    'thumbnail_url' => $thumbnail_url,
                    'url'           => $file_url,
                );
                $attachments[] = $arrachment;
            }
        }
        
        $s_cannot_edit = !$auth->acl_get('f_edit', $forum_id) || $user->data['user_id'] != $poster_id;
        $s_cannot_edit_time = $config['edit_time'] && $row['post_time'] <= time() - ($config['edit_time'] * 60);
        $s_cannot_edit_locked = $topic_data['topic_status'] == ITEM_LOCKED || $row['post_edit_locked'];
    
        $s_cannot_delete = $user->data['user_id'] != $poster_id || (
                !$auth->acl_get('f_delete', $forum_id) &&
                (!$auth->acl_get('f_softdelete', $forum_id) || $row['post_visibility'] == ITEM_DELETED)
        );
        $s_cannot_delete_lastpost = $topic_data['topic_last_post_id'] != $row['post_id'];
        $s_cannot_delete_time = $config['delete_time'] && $row['post_time'] <= time() - ($config['delete_time'] * 60);
        // we do not want to allow removal of the last post if a moderator locked it!
        $s_cannot_delete_locked = $topic_data['topic_status'] == ITEM_LOCKED || $row['post_edit_locked'];
        
        $edit_allowed = ($user->data['is_registered'] && ($auth->acl_get('m_edit', $forum_id) || (
            !$s_cannot_edit &&
            !$s_cannot_edit_time &&
            !$s_cannot_edit_locked
        )));
        
        $quote_allowed = $auth->acl_get('m_edit', $forum_id) || ($topic_data['topic_status'] != ITEM_LOCKED &&
            ($user->data['user_id'] == ANONYMOUS || $auth->acl_get('f_reply', $forum_id))
        );
    
        $delete_allowed = $force_delete_allowed || ($user->data['is_registered'] && (
            ($auth->acl_get('m_delete', $forum_id) || ($auth->acl_get('m_softdelete', $forum_id) && $row['post_visibility'] != ITEM_DELETED)) ||
            (!$s_cannot_delete && !$s_cannot_delete_lastpost && !$s_cannot_delete_time && !$s_cannot_delete_locked)
        ));
        
        $parse_flags = ($row['bbcode_bitfield'] ? OPTION_FLAG_BBCODE : 0) | OPTION_FLAG_SMILIES;
        //$message = generate_text_for_display($row['post_text'], $row['bbcode_uid'], $row['bbcode_bitfield'], $parse_flags, true);
        //quote and code
        $message = process_bbcode($row['post_text'], $row['bbcode_uid']);
        
        //parse template parameters
        if(!empty($config['smilies_path']))
            $message = preg_replace("/\{SMILIES_PATH\}/", $config['smilies_path'],$message);
        else 
            $message = preg_replace("/\{SMILIES_PATH\}/", 'images/smilies',$message);  

        $avatars = get_user_avatars($row['user_id']);
        $post = array(
            'topic_id'          => $topic_id,
            'post_id'           => $row['post_id'],
            'post_title'        => basic_clean($row['post_subject']),
            'post_content'      => post_html_clean($message),
            'post_author_id'    => $row['user_id'],
            'post_author_name'  => basic_clean( $row['username']),
            'icon_url'          => $avatars[$row['user_id']],
            'post_time'         => mobiquo_iso8601_encode($row['post_time']),
            'timestamp'         => $row['post_time'],
            'attachments'       => $attachments,
            'is_online'         => ($row['post_id'] == ANONYMOUS || !$config['load_onlinetrack']) ? false : (($user_cache[$poster_id]['online']) ? true : false),
            'can_edit'          => (boolean)$edit_allowed,
            'can_delete'        => false,//(boolean)$delete_allowed,
            'is_deleted'        => (boolean)((int)$row['post_delete_time']!==0),
            'can_approve'       => false,//(boolean)($auth->acl_get('m_approve', $forum_id) && !$row['post_visibility']),
            'is_approved'       => (boolean)($row['post_visibility'] ? true : false),
            'can_move'          => false,//(boolean)($auth->acl_get('m_split', $forum_id)),
            'can_ban'           => false,//(boolean)(($auth->acl_get('m_ban') && $poster_id != $user->data['user_id']) ? true : false),
            'is_ban'            => (boolean)($user->check_ban($poster_id,false,false,true)),
            'allow_smilies'     => (boolean)($row['enable_smilies'] ? true : false),
        );
        
        if(!empty($row['EDITER_UID']) && $config['display_last_edited'])
        {
            //add edit info
            $edit_info = array(
                'editor_id'   => $row['EDITER_UID'],
                'editor_name' => $row['EDITER_USERNAME'],
                'edit_time'   => $row['EDIT_TIME'],
            );
            if(!empty($row['EDIT_REASON']))
            {
                $edit_info['edit_reason'] = $row['EDIT_REASON'];
            }
            $xmlrpc_post = array_merge($xmlrpc_post,$edit_info);
        }

        /*undo
        if ($support_post_thanks)
        {
            if (
                !$row['S_GLOBAL_POST_THANKS']
                && !$row['S_POST_ANONYMOUS']
                && $auth->acl_get('f_thanks', $forum_id)
                && $user->data['user_id'] != ANONYMOUS
                && $user->data['user_id'] != $row['POSTER_ID']
                && !$row['S_ALREADY_THANKED']
            ) {
                if(!empty($config['thanks_only_first_post']) && $key == 0)
                {
                    
                    $xmlrpc_post['can_thank'] = true, 'boolean');
                }
                else if(!empty($config['thanks_only_first_post']))
                {
                    $xmlrpc_post['can_thank'] = false, 'boolean');
                }
                else 
                {
                    $xmlrpc_post['can_thank'] = true, 'boolean');
                }
                
                
            }
            if ($row['THANKS'] && $row['THANKS_POSTLIST_VIEW'] && !$row['S_POST_ANONYMOUS'] && empty($user->data['is_bot']))
            {
                global $thankers;

                $count = 0;
                $thank_list = array();
                $maxcount = isset($config['thanks_number_post']) ? $config['thanks_number_post'] : (
                            isset($config['thanks_number']) ? $config['thanks_number'] : 10);
                foreach($thankers as $thanker)
                {
                    if ($count >= $maxcount) break;

                    if ($thanker['post_id'] == $row['POST_ID'])
                    {
                        $thank_list[] = array(
                            'userid'    => $thanker['user_id'], 'string'),
                            'username'  => basic_clean($thanker['username']), 
                            'user_type' => check_return_user_type($thanker['user_id']),
                            //'tapatalk'  => is_tapatalk_user($row['user_id']), 'string'),
                        ), 

                        $count++;
                    }
                }

                if (!empty($thank_list))
                    $xmlrpc_post['thanks_info'] = $thank_list, 'array');
            }
        }
        */
        $post_list[] = $post;
    }
    
    $total_posts = $topic_data['topic_posts_approved']+$topic_data['topic_posts_unapproved'];
    $result = array(
        'total_post_num' => $total_posts,
        'forum_id'       => $forum_id,
        'forum_name'     => basic_clean($topic_data['forum_name']),
        'topic_id'       => $topic_id,
        'topic_title'    => basic_clean(censor_text($topic_data['topic_title'])),
        'position'       => isset($topic_data['prev_posts']) ? (int)($topic_data['prev_posts']+1) : 1,//undo
        
        'can_reply'      => (boolean)($auth->acl_get('f_reply', $forum_id) && $topic_data['forum_status'] != ITEM_LOCKED && $topic_data['topic_status'] != ITEM_LOCKED),
        'can_report'     => false,//true,
        'can_upload'     => false,//(boolean)($allowed),
        'can_delete'     => false,//(boolean)($auth->acl_get('m_delete', $forum_id)),
        'can_move'       => false,//(boolean)($auth->acl_get('m_move', $forum_id)),
        'can_subscribe'  => (boolean)($can_subscribe),
        'can_rename'     => false,//(boolean)($can_rename),
        'can_merge'      => false,//(boolean)($auth->acl_get('m_merge', $forum_id)),
        'is_subscribed'  => (boolean)(isset($topic_data['notify_status']) && !is_null($topic_data['notify_status']) && $topic_data['notify_status'] !== '' ? true : false),
        'can_stick'      => false,//(boolean)($allow_change_type && $auth->acl_get('f_sticky', $forum_id)),
        'is_sticky'      => (boolean)($topic_data['topic_type'] == POST_STICKY),
        'can_close'      => false,//(boolean)($auth->acl_get('m_lock', $forum_id) || ($auth->acl_get('f_user_lock', $forum_id) && $user->data['is_registered'] && $user->data['user_id'] == $topic_data['topic_poster'])),
        'is_closed'      => (boolean)($topic_data['topic_status'] == ITEM_LOCKED),
        'can_approve'    => false,//(boolean)($auth->acl_get('m_approve', $forum_id) && $topic_data['topic_posts_unapproved']),
        'is_approved'    => (boolean)((!$topic_data['topic_posts_unapproved']) ? true : false),
        'is_poll'        => (boolean)($is_poll),
        'can_ban'        => false,//(boolean)(($auth->acl_get('m_ban') && $topic_data['topic_poster'] != $user->data['user_id']) ? true : false),
        'is_ban'         => (boolean)($user->check_ban($topic_data['topic_poster'],false,false,true)),
        'max_attachment' => (int)($max_attachment),
        'max_png_size'   => (int)($max_png_size),
        'max_jpg_size'   => (int)($max_jpg_size),
        'view_number'    => (int)($topic_data['topic_views']),
        'posts'          => $post_list,
    );
    $results = $result;
}

function get_thread_func()
{
    global $results;
    mobi_resp($results);
}

function new_topic_func($data)
{
    $result = array(
        'result'   => (boolean)1,
        'topic_id' => (string)$data['data']['topic_id'],
    );
    mobi_resp($result);
    exit;
}

function reply_post_func($data)
{
    global $user, $config, $auth, $request_params, $topic_subscribed;
    preg_match('/(?<=&p=)[0-9]*/si',$data['url'],$matches);
    $post_id  = $matches[0];
    
    $forum_id = $request_params[0]; 
    $current_time = request_var('current_time',0);
    
    $bbcode_status  = ($config['allow_bbcode'] && $auth->acl_get('f_bbcode', $forum_id)) ? true : false;
    $smilies_status = ($bbcode_status && $config['allow_smilies'] && $auth->acl_get('f_smilies', $forum_id)) ? true : false;
    
    $approved = true;
    if ((($config['enable_queue_trigger'] && $user->data['user_posts'] < $config['queue_trigger_posts']) || !$auth->acl_get('f_noapprove', $forum_id)) && !$auth->acl_get('m_approve', $forum_id))
    {
        $approved = false;
    }
    
    $edit_allowed = ($auth->acl_get('m_edit', $forum_id) || (
        $auth->acl_get('f_edit', $forum_id) &&
        !$data['post_edit_locked'] &&
        ($current_time > time() - ($config['edit_time'] * 60) || !$config['edit_time'])
    ));
    
    $delete_allowed = ($auth->acl_get('m_delete', $forum_id) || (
        $auth->acl_get('f_delete', $forum_id) &&
        ($current_time > time() - ($config['delete_time'] * 60) || !$config['delete_time']) &&
        // we do not want to allow removal of the last post if a moderator locked it!
        !$data['post_edit_locked']
    ));
    
    $uid = $user->data['user_id']; 
    $avatars = get_user_avatars($uid);
    $avatar = $avatars[$uid];
    
    $attachments = array();
    $result = array(
        'result'           => (boolean)1,
        'post_id'          => (string)$post_id,
        'state'            => (int)($approved ? 0 : 1),
        'post_title'       => html_entity_decode(strip_tags(censor_text($request_params[2])), ENT_QUOTES, 'UTF-8'),
        'post_content'     => $request_params[3],
        'post_author_name' => html_entity_decode($user->data['username']),
        'is_online'        => true,
        'can_edit'         => $edit_allowed,
        'icon_url'         => ($user->optionget('viewavatars')) ? $avatar : '',
        'post_time'        => mobiquo_iso8601_encode($current_time),
        'can_delete'       => false,//(boolean)$delete_allowed,
        'allow_smilies'    => (boolean)($smilies_status ? true : false),
        'attachments'      => $attachments,
    );
    
    //If the topic was subscribed, we set 'notify' on it again
    if($topic_subscribed!==0)
    {
        //print_r($topic_subscribed);exit;
        require_once('./function/subscribe_topic.php');
        $request_params[0]=$topic_subscribed;
        subscribe_topic_func();
    }
    mobi_resp($result);
    exit;
}

function save_raw_post_func($data)
{
    global $user, $auth, $request_params;
    
    $approved = true;
    if ((($config['enable_queue_trigger'] && $user->data['user_posts'] < $config['queue_trigger_posts']) || !$auth->acl_get('f_noapprove', $forum_id)) && !$auth->acl_get('m_approve', $forum_id))
    {
        $approved = false;
    }
    
    $result = array(
        'result'           => (boolean)1,
        'state'            => (int)($approved ? 0 : 1),
        'post_content'     => post_html_clean($request_params[2]),
    );
    mobi_resp($result);
    exit;
}

function get_quote_post_func($data)
{
    global $request_params;
    $result = array(
        'post_id'      => $request_params[0],
        'post_title'    => $data['post_data']['post_subject'],
        'post_content'  => basic_clean($data['post_data']['post_text']),
    );
    mobi_resp($result);
    exit;
}

function get_user_info_func($data)
{
    global $request_params, $user, $auth, $config;
    $username =  basic_clean($data['member']['username']);
    $row = tt_get_user_by_name($username);
    $user_id = $row['user_id'];
    $user_session =  tt_get_session_by_id($user_id);
    
    // get user current activity
    preg_match('#^([a-z0-9/_-]+)#i', $user_session['session_page'], $on_page);
    if (!sizeof($on_page))
    {
        $on_page[1] = '';
    }
    
    switch ($on_page[1])
    {
        case 'index':
            $location = $user->lang['INDEX'];
        break;

        case 'adm/index':
            $location = $user->lang['ACP'];
        break;

        case 'posting':
        case 'viewforum':
        case 'viewtopic':
            $forum_id = $member['session_forum_id'];
            
            if ($forum_id && $auth->acl_get('f_list', $forum_id))
            {
                $location = '';

                if ($forum_data[$forum_id]['forum_type'] == FORUM_LINK)
                {
                    $location = sprintf($user->lang['READING_LINK'], $forum_data[$forum_id]['forum_name']);
                    break;
                }

                switch ($on_page[1])
                {
                    case 'posting':
                        preg_match('#mode=([a-z]+)#', $member['session_page'], $on_page);
                        $posting_mode = (!empty($on_page[1])) ? $on_page[1] : '';

                        switch ($posting_mode)
                        {
                            case 'reply':
                            case 'quote':
                                $location = sprintf($user->lang['REPLYING_MESSAGE'], $forum_data[$forum_id]['forum_name']);
                            break;

                            default:
                                $location = sprintf($user->lang['POSTING_MESSAGE'], $forum_data[$forum_id]['forum_name']);
                            break;
                        }
                    break;

                    case 'viewtopic':
                        $location = sprintf($user->lang['READING_TOPIC'], $forum_data[$forum_id]['forum_name']);
                    break;

                    case 'viewforum':
                        $location = sprintf($user->lang['READING_FORUM'], $forum_data[$forum_id]['forum_name']);
                    break;
                }
            }
            else
            {
                $location = $user->lang['INDEX'];
            }
        break;

        case 'search':
            $location = $user->lang['SEARCHING_FORUMS'];
        break;

        case 'faq':
            $location = $user->lang['VIEWING_FAQ'];
        break;

        case 'viewonline':
            $location = $user->lang['VIEWING_ONLINE'];
        break;

        case 'memberlist':
            $location = (strpos($member['session_page'], 'mode=viewprofile') !== false) ? $user->lang['VIEWING_MEMBER_PROFILE'] : $user->lang['VIEWING_MEMBERS'];
        break;

        case 'mcp':
            $location = $user->lang['VIEWING_MCP'];
        break;

        case 'ucp':
            $location = $user->lang['VIEWING_UCP'];

            // Grab some common modules
            $url_params = array(
                'mode=register'        => 'VIEWING_REGISTER',
                'i=pm&mode=compose'    => 'POSTING_PRIVATE_MESSAGE',
                'i=pm&'                => 'VIEWING_PRIVATE_MESSAGES',
                'i=profile&'        => 'CHANGING_PROFILE',
                'i=prefs&'            => 'CHANGING_PREFERENCES',
            );

            foreach ($url_params as $param => $lang)
            {
                if (strpos($member['session_page'], $param) !== false)
                {
                    $location = $user->lang[$lang];
                    break;
                }
            }

        break;

        case 'download/file':
            $location = $user->lang['DOWNLOADING_FILE'];
        break;

        case 'report':
            $location = $user->lang['REPORTING_POST'];
        break;
        
        case 'mobiquo/mobiquo':
            $location = 'On Tapatalk';
        break;

        default:
            $location = $user->lang['INDEX'];
        break;
    }
    
    //custom fields
    $custom_fields = array();
    if(!empty($data['profile_fields']['blockrow']))
    {
        $profile = $data['profile_fields']['blockrow'];
        foreach($profile as $key => $value)
        {
            $custom_fields[] = array(
            'name'  => $value['PROFILE_FIELD_NAME'],
            'value' => basic_clean(!empty($value['PROFILE_FIELD_CONTACT']) ? $value['PROFILE_FIELD_CONTACT'] : $value['PROFILE_FIELD_VALUE']),
            );
        }
    }
    
    if (!empty($data['member']['user_sig']))
        $custom_fields[] = array(
            'name'  => $user->lang['SIGNATURE'],
            'value' => basic_clean($data['member']['user_sig']),
        );
        
    if (!empty($data['member']['user_rank'])){
        get_user_rank($data['member']['user_rank'], (($data['member']['user_id'] == ANONYMOUS) ? false : $data['member']['user_posts']), $rank_title, $rank_img, $rank_img_src);
        $custom_fields[] = array(
            'name'  => $user->lang['RANK'],
            'value' => basic_clean($rank_title),
        );
    }
    if ($data['member']['user_type'] == USER_INACTIVE) {
       
        $user->add_lang('acp/common');
        $inactive_reason = $user->lang['INACTIVE_REASON_UNKNOWN'];

        switch ($data['member']['user_inactive_reason'])
        {
            case INACTIVE_REGISTER:
                $inactive_reason = $user->lang['INACTIVE_REASON_REGISTER'];
            break;

            case INACTIVE_PROFILE:
                $inactive_reason = $user->lang['INACTIVE_REASON_PROFILE'];
            break;

            case INACTIVE_MANUAL:
                $inactive_reason = $user->lang['INACTIVE_REASON_MANUAL'];
            break;

            case INACTIVE_REMIND:
                $inactive_reason = $user->lang['INACTIVE_REASON_REMIND'];
            break;
        }
        
        $custom_fields[] = array(
            'name'  => $user->lang['USER_IS_INACTIVE'], 
            'value' => $inactive_reason, 
        ); 
    }
    
    if ($config['allow_birthdays'] && $data['member']['user_birthday']) {
        
        list($bday_day, $bday_month, $bday_year) = array_map('intval', explode('-', $data['member']['user_birthday']));
        if ($bday_year)
        {
            $now = $user->create_datetime();
            $now = phpbb_gmgetdate($now->getTimestamp() + $now->getOffset());

            $diff = $now['mon'] - $bday_month;
            if ($diff == 0)
            {
                $diff = ($now['mday'] - $bday_day < 0) ? 1 : 0;
            }
            else
            {
                $diff = ($diff < 0) ? 1 : 0;
            }
            $age = max(0, (int) ($now['year'] - $bday_year - $diff));
        }
        $custom_fields[] = array(
            'name'  => $user->lang['AGE'], 
            'value' => $age, 
        ); 
    }
    
    if (!empty($data['member']['user_occ'])) {
        $custom_fields[] = array(
            'name'  => $user->lang['OCCUPATION'], 
            'value' => censor_text($data['member']['user_occ']), 
        );
    }
    
    if (!empty($data['member']['user_interests'])) {
        $custom_fields[] = array(
            'name'  => $user->lang['INTERESTS'], 
            'value' => $data['member']['user_interests'], 
        );
    }
    
    $group = implode(tt_get_usergroup_by_id($data['member']['user_id']),"\n");
    $custom_fields[] = array(
        'name'  => $user->lang['USERGROUPS'], 
        'value' => $group, 
    );
    
    
    if ($data['member']['user_jabber']) {
        $custom_fields[] = array(
            'name'  => $user->lang['JABBER'], 
            'value' => $data['member']['user_jabber'], 
        );
    }
    
    if ($data['member']['user_warnings']) {
        $custom_fields[] = array(
            'name'  => $user->lang['WARNINGS'], 
            'value' => $data['member']['user_warnings'], 
        );
    }
    
    if ($data['member']['user_posts']) {
        
        $l_active_pct = ($data['member']['user_id'] != ANONYMOUS && $data['member']['user_id'] == $user->data['user_id']) ? $user->lang['POST_PCT_ACTIVE_OWN'] : $user->lang['POST_PCT_ACTIVE'];
        if ($data['member']['active_f_row']) {
            $active_f_count = $data['member']['active_f_row']['num_posts'];
            $active_f_pct = ($data['member']['user_posts']) ? ($active_f_count / $data['member']['user_posts']) * 100 : 0;
            $ACTIVE_FORUM_PCT = sprintf($l_active_pct, $active_f_pct);
            $custom_fields[] = array(
                'name'  => $user->lang['ACTIVE_IN_FORUM'], 
                'value' => $data['member']['active_f_row']['forum_name']."\n(".$data['member']['active_f_row']['num_posts'].' / '.$ACTIVE_FORUM_PCT.')', 
            );
        }
        
        if ($data['member']['active_t_row']) {
            $active_t_count =$data['member']['active_t_row']['num_posts'];
            $active_t_pct = ($data['member']['user_posts']) ? ($active_t_count / $data['member']['user_posts']) * 100 : 0;
            $ACTIVE_TOPIC_PCT = sprintf($l_active_pct, $active_t_pct);
            $custom_fields[] = array(
                'name'  => $user->lang['ACTIVE_IN_TOPIC'], 
                'value' => $data['member']['active_t_row']['topic_title']."\n(".$data['member']['active_t_row']['num_posts'].' / '.$ACTIVE_TOPIC_PCT.')', 
            );
        }
    }
    
    $avatars = get_user_avatars($user_id);
    
    if ($config['load_onlinetrack'])
    {
        $update_time = $config['load_online_time'] * 60;
        $online = (time() - $update_time < $data['member']['session_time'] && ((isset($data['member']['session_viewonline']) && $data['member']['session_viewonline']) || $auth->acl_get('u_viewonline'))) ? true : false;
    }
    else
    {
        $online = false;
    }
    
    $user_info = array(
        'user_id'            => $user_id,
        'username'           => $username,
        'user_type'          => check_return_user_type($user_id),
        //'tapatalk'           => is_tapatalk_user($member['user_id']), 'string'),
        'post_count'         => (int)$data['member']['user_posts'],
        'reg_time'           => mobiquo_iso8601_encode($data['member']['user_regdate']),
        'timestamp_reg'      => $data['member']['user_regdate'],
        'last_activity_time' => mobiquo_iso8601_encode($data['member']['user_lastvisit']),//undo
        'timestamp'          => $data['member']['user_lastvisit'],//undo
        'is_online'          => (boolean)(($config['load_onlinetrack'] && $online) ? true : false),
        'accept_pm'          => ($config['allow_privmsg'] && $auth->acl_get('u_sendpm') && ($data['member']['user_allow_pm'] || $auth->acl_gets('a_', 'm_') || $auth->acl_getf_global('m_')))  ? true : false,
        'display_text'       => '',
        'icon_url'           => $avatars[$user_id],
        'current_activity'   => $location,
        'custom_fields_list' => $custom_fields,
        'can_ban'            => false,//$auth->acl_get('m_ban') && $user_id != $user->data['user_id'],
        'is_ban'             => $user->check_ban($user_id,false,false,true),
    );
    mobi_resp($user_info);
    exit;
}

function get_raw_post_func($data)//not complete
{
    global $request_params, $auth, $config, $phpEx;
    $phpbb_home = generate_board_url().'/';
    $forum_id   = $data['post_data']['forum_id'];
    $attach     = $data['message_parser']->attachment_data;
    
    $attachments = array();
    foreach($attach as $key => $value)
    {
        $attach_id = $value->attach_id;
        $attachValue = tt_get_attachment_by_id($attach_id);
        
        $file_url = $phpbb_home."download/file.$phpEx?id=$attach_id";
        
        if (strpos($attachValue['mimetype'], 'image') === 0)
            $content_type = 'image';
        else
            $content_type = $attachValue['extension'];
        
        $thumbnail_url = '';
        if ($config['img_create_thumbnail'] && $attachValue['thumbnail'])
        {
            $thumbnail_url = preg_replace('/file\.php\?/is', 'file.php?t=1&', $file_url);
        }
            
        $arrachment = array(
            'filename'      => $attachValue['real_filename'],
            'filesize'      => (int)$attachValue['filesize'],
            'content_type'  => $content_type,
            'thumbnail_url' => $thumbnail_url,
            'url'           => $file_url,
        );
        $attachments[] = $arrachment;
        
    }
    $result = array(
        'post_id'       => $request_params[0],
        'post_title'    => $data['post_data']['post_subject'],
        'post_content'  => basic_clean($data['post_data']['post_text']),
        'show_reason'   => false,//$auth->acl_get('m_edit', $forum_id) ? true : false,
        'edit_reason'   => '',//$data['EDIT_REASON'],
        'group_id'      => base64_encode(serialize($attach)),
        'attachments'   => $attachments,
    );
    mobi_resp($result);
    exit;
}

function get_online_users_sub_func($data)
{
    global $results;
    $avatars = get_user_avatars($data['row']['user_id']);
    
    if (strstr('mobiquo',$data['row']['session_page']))
        $mobi = 1;
    
    $result =array(
        'user_id'       => $data['row']['user_id'],
        'username'      => basic_clean($data['row']['username']),
        'user_name'     => basic_clean($data['row']['username']),
        'from'          => '',//undo
        'user_type'     => check_return_user_type($data['row']['user_id']),
        //'tapatalk'      => new xmlrpcval(is_tapatalk_user($row['user_id']), 'string'),
        'icon_url'      => $avatars[$data['row']['user_id']],
        'display_text'  => '',//isset($mobi) ? $data['location'] : 'On Tapatalk',
        );
    $results[]=$result;
}

function get_online_users_func($data)
{
    global $results, $guest_count;

    $online_users = array(
    'member_count' => (int)count($results),
    'guest_count'  => (int)$guest_count,
    'list'         => $results,
    );
    mobi_resp($online_users);
}

function login_forum_func($data)
{
    $result = array(
        'result'       => (boolean)1,
        'result_text'  => '',   
    );
    mobi_resp($result);
    exit;
}
/*
function search_sub_func($data)
{
    global $auth, $user, $config, $results;
    
    $showposts = request_var('sr','posts');
    if(strstr($showposts,'posts'))
    {
        $item = $data['tpl_ary'];
        $row  = $data['row'];
        
        $forum_id = $item['FORUM_ID'];
        
        $post_author = tt_get_user_by_name($item['POST_AUTHOR']);
        $author_id = $post_author['user_id'];
        
        $avatars = get_user_avatars($author_id);
        
        $can_approve = $auth->acl_get('m_approve', $forum_id) && !$row['post_visibility'];
        $can_move = $auth->acl_get('m_split', $forum_id);
        $can_ban = $auth->acl_get('m_ban') && $author_id != $user->data['user_id'];
        $can_delete = ($user->data['is_registered'] && ($auth->acl_get('m_delete', $forum_id) || (
            $user->data['user_id'] == $author_id &&
            $auth->acl_get('f_delete', $forum_id) &&
            $row['topic_last_post_id'] == $item['POST_ID'] &&
            ($row['post_time'] > time() - ($config['delete_time'] * 60) || !$config['delete_time']) &&
            // we do not want to allow removal of the last post if a moderator locked it!
            !$row['post_edit_locked']
        )));
        $post = array(
            'forum_id'         => (string)$forum_id,
            'forum_name'       => basic_clean($item['FORUM_TITLE']),
            'topic_id'         => (string)$item['TOPIC_ID'],
            'topic_title'      => basic_clean($item['TOPIC_TITLE']),
            'post_id'          => (string)$item['POST_ID'],
            'post_title'       => basic_clean($item['POST_SUBJECT']),
            'post_author_id'   => (string)$author_id,
            'post_author_name' => basic_clean($item['POST_AUTHOR']),
            'post_time'        => mobiquo_iso8601_encode($row['post_time']),
            'timestamp'        => $row['post_time'],
            'icon_url'         => $avatars[$author_id],
            'short_content'    => basic_clean($item['MESSAGE']),
            'is_approved'      => (boolean)$row['post_visibility'],
        );
        if ($can_approve)   $post['can_approve'] = true;
        if ($can_delete)    $post['can_delete']  = true;
        if ($can_move)      $post['can_move']    = true;
        if ($can_ban)       $post['can_ban']     = true;
        $results[] = $post; 
    }
    else
    {
        $item = $data['tpl_ary'];
        $row  = $data['row'];
        
        $forum_id = $item['FORUM_ID'];
        
        $author_id = $row['topic_last_poster_id'];
        $avatars = get_user_avatars($author_id);
        
        $can_approve = $auth->acl_get('m_approve', $forum_id) && !$row['post_visibility'];
        $can_move = $auth->acl_get('m_split', $forum_id);
        $can_ban = $auth->acl_get('m_ban') && $author_id != $user->data['user_id'];
        $can_delete = ($user->data['is_registered'] && ($auth->acl_get('m_delete', $forum_id) || (
            $user->data['user_id'] == $author_id &&
            $auth->acl_get('f_delete', $forum_id) &&
            $row['topic_last_post_id'] == $item['POST_ID'] &&
            ($row['post_time'] > time() - ($config['delete_time'] * 60) || !$config['delete_time']) &&
            // we do not want to allow removal of the last post if a moderator locked it!
            !$row['post_edit_locked']
        )));
        $last_post_id = $row['topic_last_post_id'];
        $topic = array(
            'forum_id'         => (string)$forum_id,
            'forum_name'       => basic_clean($item['FORUM_TITLE']),
            'topic_id'         => (string)$item['TOPIC_ID'],
            'topic_title'      => basic_clean($item['TOPIC_TITLE']),
            'post_author_id'   => $author_id,
            'post_author_name' => basic_clean($item['LAST_POST_AUTHOR']),
            'post_time'        => mobiquo_iso8601_encode($row['topic_last_post_time']),
            'timestamp'        => $row['topic_last_post_time'],
            'icon_url'         => $avatars[$author_id],
            'short_content'    => get_short_content($last_post_id),
            'is_approved'      => $item['S_TOPIC_UNAPPROVED'] ?  false : true,
            'reply_number'     => (int)$item['TOPIC_REPLIES'],
            'view_number'      => (int)$item['TOPIC_VIEWS'],
        );
        
        $allow_change_type = ($auth->acl_get('m_', $forum_id) || ($user->data['is_registered'] && $user->data['user_id'] == $item['TOPIC_AUTHOR_ID']));
        $can_close  = $auth->acl_get('m_lock', $forum_id) || ($auth->acl_get('f_user_lock', $forum_id) && $user->data['is_registered'] && $user->data['user_id'] == $item['TOPIC_AUTHOR_ID']);
        $can_delete = $auth->acl_get('m_delete', $forum_id);
        $can_stick  = $allow_change_type && $auth->acl_get('f_sticky', $forum_id);
        $can_move   = $auth->acl_get('m_move', $forum_id);
        $can_approve= $auth->acl_get('m_approve', $forum_id) && $item['S_TOPIC_UNAPPROVED'];
        $can_ban    = $auth->acl_get('m_ban') && $row['topic_last_poster_id'] != $user->data['user_id'];
        $can_rename = ($user->data['is_registered'] && ($auth->acl_get('m_edit', $forum_id) || (
            $user->data['user_id'] == $item['TOPIC_AUTHOR_ID'] &&
            $auth->acl_get('f_edit', $forum_id) &&
            //!$item['post_edit_locked'] &&
            ($row['topic_time'] > time() - ($config['edit_time'] * 60) || !$config['edit_time'])
        )));
        
		$can_merge = $auth->acl_get('m_merge', $forum_id);
        $subscribed_tids = tt_get_subscribed_topic_by_id($user->data['user_id']);
        $can_subscribe = ($config['email_enable'] || $config['jab_enable']) && $config['allow_topic_notify'] && $user->data['is_registered'];
        $is_subscribed = is_array($subscribed_tids) ? in_array($item['TOPIC_ID'], $subscribed_tids) : false ;
        
        if ($can_close)     $topic['can_close']     = true;
        if ($can_delete)    $topic['can_delete']    = true;
        if ($can_stick)     $topic['can_stick']     = true;
        if ($can_move)      $topic['can_move']      = true;
        if ($can_approve)   $topic['can_approve']   = true;
        if ($can_rename)    $topic['can_rename']    = true;
        if ($can_ban)       $topic['can_ban']       = true;
      //if ($is_ban)        $topic['is_ban']        = true;
		if ($can_merge)     $topic['can_merge']     = true;
        if ($can_subscribe) $topic['can_subscribe'] = true;
        if ($is_subscribed) $topic['is_subscribed'] = true;
        if ($can_approve)   $topic['can_approve'] = true;
        if ($can_delete)    $topic['can_delete']  = true;
        if ($can_move)      $topic['can_move']    = true;
        if ($can_ban)       $topic['can_ban']     = true;
        if ($item['S_UNREAD_TOPIC'])                $topic['new_post']  = true;
        if ($row['topic_status'] == ITEM_LOCKED)    $topic['is_closed'] = true;
        if ($row['topic_type'] == POST_STICKY)      $topic['is_sticky'] = true;
        $results[] = $topic; 
    }
}

function search_func($data)
{
    global $results, $search_method;
    if(empty($results))
        $results = array();
    
    switch($search_method)
    {
        case 'get_user_reply_post':
        case 'get_user_topic':
            $result = $results;
            break;
        default:
            $showposts = request_var('sr','posts');
            if(strstr($showposts,'posts'))
            {
                $result = array(
                    'result' => (boolean)1,
                    'total_post_num' => (int)intval($data['TOTAL_MATCHES']),
                    'posts'          => $results,
                );
            }
            else 
            {   
               
                $result = array(
                    'result' => (boolean)1,
                    'total_topic_num'  => (int)intval($data['TOTAL_MATCHES']),
                    'topics'          => $results,
                );
            }
    }
    
    
    mobi_resp($result);
    exit;
}
*/