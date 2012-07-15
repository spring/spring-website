<?php
/**
*
* @copyright (c) 2009, 2010, 2011 Quoord Systems Limited
* @license http://opensource.org/licenses/gpl-2.0.php GNU Public License (GPLv2)
*
*/

defined('IN_MOBIQUO') or exit;

function get_thread_func()
{
    global $template, $user, $auth, $phpbb_home, $config, $attachment_by_id, $forum_id, $topic_id, $support_post_thanks, $topic_data, $total_posts, $can_subscribe;
    
    $post_list = array();
    foreach($template->_tpldata['postrow'] as $row)
    {
        $attachments = array();
        if ($row['S_HAS_ATTACHMENTS'])
        {
            foreach($row['attachment'] as $attachment)
            {
                if(preg_match('/<img src=\".*?\/(download\/file\.php\?id=(\d+).*?)\"/is', $attachment['DISPLAY_ATTACHMENT'], $matches))
                {
                    $attach_id = $matches[2];

                    $file_url = basic_clean($phpbb_home.$matches[1]);
                    $thumbnail_url = '';

                    if ($config['img_create_thumbnail'] && $attachment_by_id[$attach_id]['thumbnail_orig'])
                    {
                        $thumbnail_url = preg_replace('/file\.php\?/is', 'file.php?t=1&', $file_url);
                    }

                    unset($matches);

                    if (strpos($attachment_by_id[$attach_id]['mimetype'], 'image') === 0)
                        $content_type = 'image';
                    else
                        $content_type = $attachment_by_id[$attach_id]['extension'];

                    $xmlrpc_attachment = new xmlrpcval(array(
                        'filename'      => new xmlrpcval($attachment_by_id[$attach_id]['real_filename'], 'base64'),
                        'filesize'      => new xmlrpcval($attachment_by_id[$attach_id]['filesize'], 'int'),
                        'content_type'  => new xmlrpcval($content_type),
                        'thumbnail_url' => new xmlrpcval($thumbnail_url),
                        'url'           => new xmlrpcval($file_url)
                    ), 'struct');
                    $attachments[] = $xmlrpc_attachment;
                }
            }
        }

        if ($row['S_IGNORE_POST'])
        {
            $row['MESSAGE'] = '[color=Gray]' . $row['L_IGNORE_POST'] . '[/color]' . "[spoiler]{$row[MESSAGE]}[/spoiler]";
        }

        $can_ban_user = $auth->acl_get('m_ban') && $row['POSTER_ID'] != $user->data['user_id'];

        $xmlrpc_post = array(
            'topic_id'          => new xmlrpcval($topic_id),
            'post_id'           => new xmlrpcval($row['POST_ID']),
            'post_title'        => new xmlrpcval(basic_clean($row['POST_SUBJECT']), 'base64'),
            'post_content'      => new xmlrpcval(post_html_clean($row['MESSAGE']), 'base64'),
            'post_author_id'    => new xmlrpcval($row['POSTER_ID']),
            'post_author_name'  => new xmlrpcval(basic_clean($row['POST_AUTHOR']), 'base64'),
            'icon_url'          => new xmlrpcval($row['POSTER_AVATAR']),
            'post_time'         => new xmlrpcval($row['POST_DATE'], 'dateTime.iso8601'),
            'timestamp'         => new xmlrpcval($row['POST_TIMESTAMP'], 'string'),
            'attachments'       => new xmlrpcval($attachments, 'array'),
            'is_online'         => new xmlrpcval($row['S_ONLINE'], 'boolean'),
            'can_edit'          => new xmlrpcval($row['U_EDIT'], 'boolean'),
            'can_delete'        => new xmlrpcval($row['U_DELETE'], 'boolean'),
            'can_approve'       => new xmlrpcval($auth->acl_get('m_approve', $forum_id) && !$row['post_approved'], 'boolean'),
            'is_approved'       => new xmlrpcval($row['post_approved'] ? true : false, 'boolean'),
            'can_move'          => new xmlrpcval($auth->acl_get('m_split', $forum_id), 'boolean'),
            'can_ban'           => new xmlrpcval($can_ban_user, 'boolean'),
            'allow_smilies'     => new xmlrpcval($row['enable_smilies'] ? true : false, 'boolean'),
        );

        if ($support_post_thanks)
        {
            if ((!$row['S_FIRST_POST_ONLY'] || (!$start && $row['S_ROW_COUNT']))
                && !$row['S_GLOBAL_POST_THANKS']
                && !$row['S_POST_ANONYMOUS']
                && $auth->acl_get('f_thanks', $forum_id)
                && $user->data['user_id'] != ANONYMOUS
                && $user->data['user_id'] != $row['POSTER_ID']
                && !$row['S_ALREADY_THANKED']
            ) {
                $xmlrpc_post['can_thank'] = new xmlrpcval(true, 'boolean');
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
                        $thank_list[] = new xmlrpcval(array(
                            'userid'    => new xmlrpcval($thanker['user_id'], 'string'),
                            'username'  => new xmlrpcval(basic_clean($thanker['username']), 'base64'),
                        ), 'struct');

                        $count++;
                    }
                }

                if (!empty($thank_list))
                    $xmlrpc_post['thanks_info'] = new xmlrpcval($thank_list, 'array');
            }
        }

        $post_list[] = new xmlrpcval($xmlrpc_post, 'struct');
    }
    
    $allow_change_type = ($auth->acl_get('m_', $forum_id) || ($user->data['is_registered'] && $user->data['user_id'] == $topic_data['topic_poster'])) ? true : false;
    $allowed = $config['max_attachments'] && $auth->acl_get('f_attach', $forum_id) && $auth->acl_get('u_attach') && $config['allow_attachments'] && @ini_get('file_uploads') != '0' && strtolower(@ini_get('file_uploads')) != 'off';
    $max_attachment = ($auth->acl_get('a_') || $auth->acl_get('m_', $forum_id)) ? 99 : ($allowed ? $config['max_attachments'] : 0);
    $max_png_size = ($auth->acl_get('a_') || $auth->acl_get('m_', $forum_id)) ? 10485760 : ($allowed ? ($config['max_filesize'] === '0' ? 10485760 : $config['max_filesize']) : 0);
    $max_jpg_size = ($auth->acl_get('a_') || $auth->acl_get('m_', $forum_id)) ? 10485760 : ($allowed ? ($config['max_filesize'] === '0' ? 10485760 : $config['max_filesize']) : 0);
    
    $result = array(
        'total_post_num' => new xmlrpcval($total_posts, 'int'),
        'forum_id'       => new xmlrpcval($forum_id),
        'forum_name'     => new xmlrpcval(basic_clean($topic_data['forum_name']), 'base64'),
        'topic_id'       => new xmlrpcval($topic_id),
        'topic_title'    => new xmlrpcval(basic_clean(censor_text($topic_data['topic_title'])), 'base64'),
        'position'       => new xmlrpcval($topic_data['prev_posts'] + 1, 'int'),

        'can_reply'      => new xmlrpcval($auth->acl_get('f_reply', $forum_id) && $topic_data['forum_status'] != ITEM_LOCKED && $topic_data['topic_status'] != ITEM_LOCKED, 'boolean'),
        'can_upload'     => new xmlrpcval($allowed, 'boolean'),
        'can_delete'     => new xmlrpcval($auth->acl_get('m_delete', $forum_id), 'boolean'),
        'can_move'       => new xmlrpcval($auth->acl_get('m_move', $forum_id), 'boolean'),
        'can_subscribe'  => new xmlrpcval($can_subscribe, 'boolean'),
        'is_subscribed'  => new xmlrpcval(isset($topic_data['notify_status']) && !is_null($topic_data['notify_status']) && $topic_data['notify_status'] !== '' ? true : false, 'boolean'),
        'can_stick'      => new xmlrpcval($allow_change_type && $auth->acl_get('f_sticky', $forum_id), 'boolean'),
        'is_sticky'      => new xmlrpcval($topic_data['topic_type'] == POST_STICKY, 'boolean'),
        'can_close'      => new xmlrpcval($auth->acl_get('m_lock', $forum_id) || ($auth->acl_get('f_user_lock', $forum_id) && $user->data['is_registered'] && $user->data['user_id'] == $topic_data['topic_poster']), 'boolean'),
        'is_closed'      => new xmlrpcval($topic_data['topic_status'] == ITEM_LOCKED, 'boolean'),
        'can_approve'    => new xmlrpcval($auth->acl_get('m_approve', $forum_id) && !$topic_data['topic_approved'], 'boolean'),
        'is_approved'    => new xmlrpcval($topic_data['topic_approved'] ? true : false, 'boolean'),

        'max_attachment' => new xmlrpcval($max_attachment, 'int'),
        'max_png_size'   => new xmlrpcval($max_png_size, 'int'),
        'max_jpg_size'   => new xmlrpcval($max_jpg_size, 'int'),

        'posts'          => new xmlrpcval($post_list, 'array'),
    );
    
    
    
    return new xmlrpcresp(new xmlrpcval($result, 'struct'));
}

function search_func()
{
    global $db, $template, $user, $auth, $config, $can_subscribe, $show_results, $include_topic_num, $total_match_count;
    
    $topic_list = $subscribed_tids = array();
    
    if($show_results == 'topics' && $user->data['user_id'])
    {
        $sql = 'SELECT topic_id
            FROM ' . TOPICS_WATCH_TABLE . '
            WHERE user_id = ' . $user->data['user_id'];
        $result = $db->sql_query($sql);
        
        while ($row = $db->sql_fetchrow($result))
        {
            $subscribed_tids[] = (int) $row['topic_id'];
        }
        $db->sql_freeresult($result);
    }
    
    foreach($template->_tpldata['searchresults'] as $item)
    {
        $forum_id = $item['FORUM_ID'];
        
        if($show_results == 'topics')
        {
            $lastpost = $item['lastpost'];
            $isbanned = $lastpost['isbanned'];
            
            $return_item = array(
                'forum_id'              => new xmlrpcval($item['FORUM_ID'], 'string'),
                'forum_name'            => new xmlrpcval(basic_clean($item['FORUM_TITLE']), 'base64'),
                'topic_id'              => new xmlrpcval($item['TOPIC_ID'], 'string'),
                'topic_title'           => new xmlrpcval(basic_clean($item['TOPIC_TITLE']), 'base64'),
                
                'post_author_id'        => new xmlrpcval($item['LAST_POSTER_ID'], 'string'),
                'post_author_name'      => new xmlrpcval(basic_clean($item['LAST_POST_AUTHOR']), 'base64'),
                'post_time'             => new xmlrpcval($item['LAST_POST_TIME'], 'dateTime.iso8601'),
                'timestamp'             => new xmlrpcval($item['LAST_POST_TIMESTAMP'], 'string'),
                'icon_url'              => new xmlrpcval($item['LAST_POSTER_AVATAR'], 'string'),
                'short_content'         => new xmlrpcval(basic_clean($item['LAST_POST_PREV']), 'base64'),
                
                // compatibility data
                'last_reply_author_id'  => new xmlrpcval($item['LAST_POSTER_ID'], 'string'),
              'last_reply_author_name'  => new xmlrpcval(basic_clean($item['LAST_POST_AUTHOR']), 'base64'),
                'last_reply_time'       => new xmlrpcval($item['LAST_POST_TIME'], 'dateTime.iso8601'),
                
                'reply_number'          => new xmlrpcval($item['TOPIC_REPLIES'], 'int'),
                'view_number'           => new xmlrpcval($item['TOPIC_VIEWS'], 'int'),
                'can_subscribe'         => new xmlrpcval($can_subscribe, 'boolean'),
                'is_approved'           => new xmlrpcval(!$item['S_TOPIC_UNAPPROVED'], 'boolean'),
            );
            
            $allow_change_type = ($auth->acl_get('m_', $forum_id) || ($user->data['is_registered'] && $user->data['user_id'] == $topic_data['topic_poster']));
            $can_close  = $auth->acl_get('m_lock', $forum_id) || ($auth->acl_get('f_user_lock', $forum_id) && $user->data['is_registered'] && $user->data['user_id'] == $item['TOPIC_AUTHOR_ID']);
            $can_delete = $auth->acl_get('m_delete', $forum_id);
            $can_stick  = $allow_change_type && $auth->acl_get('f_sticky', $forum_id);
            $can_move   = $auth->acl_get('m_move', $forum_id);
            $can_approve= $auth->acl_get('m_approve', $forum_id) && $item['S_TOPIC_UNAPPROVED'];
            $can_ban    = $auth->acl_get('m_ban') && $item['LAST_POSTER_ID'] != $user->data['user_id'];
            $can_rename = ($user->data['is_registered'] && ($auth->acl_get('m_edit', $forum_id) || (
                $user->data['user_id'] == $item['TOPIC_AUTHOR_ID'] &&
                $auth->acl_get('f_edit', $forum_id) &&
                //!$item['post_edit_locked'] &&
                ($item['FIRST_POST_TIMESTAMP'] > time() - ($config['edit_time'] * 60) || !$config['edit_time'])
            )));
            
            $can_subscribe = ($config['email_enable'] || $config['jab_enable']) && $config['allow_topic_notify'] && $user->data['is_registered'];
            $is_subscribed = in_array($item['TOPIC_ID'], $subscribed_tids);
            
            if ($can_close)     $return_item['can_close']     = new xmlrpcval(true, 'boolean');
            if ($can_delete)    $return_item['can_delete']    = new xmlrpcval(true, 'boolean');
            if ($can_stick)     $return_item['can_stick']     = new xmlrpcval(true, 'boolean');
            if ($can_move)      $return_item['can_move']      = new xmlrpcval(true, 'boolean');
            if ($can_approve)   $return_item['can_approve']   = new xmlrpcval(true, 'boolean');
            if ($can_rename)    $return_item['can_rename']    = new xmlrpcval(true, 'boolean');
            if ($can_ban)       $return_item['can_ban']       = new xmlrpcval(true, 'boolean');
          //if ($is_ban)        $return_item['is_ban']        = new xmlrpcval(true, 'boolean');
            if ($can_subscribe) $return_item['can_subscribe'] = new xmlrpcval(true, 'boolean');
            if ($is_subscribed) $return_item['is_subscribed'] = new xmlrpcval(true, 'boolean');
            if ($item['S_UNREAD_TOPIC'])    $return_item['new_post']  = new xmlrpcval(true, 'boolean');
            if ($item['S_TOPIC_LOCKED'])    $return_item['is_closed'] = new xmlrpcval(true, 'boolean');
            if ($item['S_POST_STICKY'])     $return_item['is_sticky'] = new xmlrpcval(true, 'boolean');
            
            $return_list[] = new xmlrpcval($return_item, 'struct');
        }
        else
        {
            $return_item = array(
                'forum_id'          => new xmlrpcval($item['FORUM_ID'], 'string'),
                'forum_name'        => new xmlrpcval(basic_clean($item['FORUM_TITLE']), 'base64'),
                'topic_id'          => new xmlrpcval($item['TOPIC_ID'], 'string'),
                'topic_title'       => new xmlrpcval(basic_clean($item['TOPIC_TITLE']), 'base64'),
                'post_id'           => new xmlrpcval($item['POST_ID'], 'string'),
                'post_title'        => new xmlrpcval(basic_clean($item['POST_SUBJECT']), 'base64'),
                'post_author_id'    => new xmlrpcval($item['POST_AUTHOR_ID'], 'string'),
                'post_author_name'  => new xmlrpcval(basic_clean($item['POST_AUTHOR']), 'base64'),
                'post_time'         => new xmlrpcval($item['POST_DATE'], 'dateTime.iso8601'),
                'timestamp'         => new xmlrpcval($item['POST_TIMESTAMP'], 'string'),
                'icon_url'          => new xmlrpcval($item['POSTER_AVATAR'], 'string'),
                'short_content'     => new xmlrpcval(basic_clean($item['MESSAGE']), 'base64'),
                'is_approved'       => new xmlrpcval($item['POST_APPROVED'], 'boolean'),
            );
            
            $can_approve = $auth->acl_get('m_approve', $forum_id) && !$item['POST_APPROVED'];
            $can_move = $auth->acl_get('m_split', $forum_id);
            $can_ban = $auth->acl_get('m_ban') && $item['POST_AUTHOR_ID'] != $user->data['user_id'];
            $can_delete = ($user->data['is_registered'] && ($auth->acl_get('m_delete', $forum_id) || (
                $user->data['user_id'] == $item['POST_AUTHOR_ID'] &&
                $auth->acl_get('f_delete', $forum_id) &&
                $item['TOPIC_LAST_POST_ID'] == $item['POST_ID'] &&
                ($item['POST_TIMESTAMP'] > time() - ($config['delete_time'] * 60) || !$config['delete_time']) &&
                // we do not want to allow removal of the last post if a moderator locked it!
                !$item['POST_EDIT_LOCKED']
            )));
            
            if ($can_approve)   $return_item['can_approve'] = new xmlrpcval(true, 'boolean');
            if ($can_delete)    $return_item['can_delete']  = new xmlrpcval(true, 'boolean');
            if ($can_move)      $return_item['can_move']    = new xmlrpcval(true, 'boolean');
            if ($can_ban)       $return_item['can_ban']     = new xmlrpcval(true, 'boolean');
          //if ($is_ban])       $return_item['is_ban']      = new xmlrpcval(true, 'boolean');
            
            $return_list[] = new xmlrpcval($return_item, 'struct');
        }
    }
    
    if ($include_topic_num) {
        if($show_results == 'topics') {
            return new xmlrpcresp(new xmlrpcval(array(
                'result'            => new xmlrpcval(true, 'boolean'),
                'total_topic_num'   => new xmlrpcval($total_match_count, 'int'),
                'topics'            => new xmlrpcval($return_list, 'array'),
            ), 'struct'));
        } else {
            return new xmlrpcresp(new xmlrpcval(array(
                'result'            => new xmlrpcval(true, 'boolean'),
                'total_post_num'    => new xmlrpcval($total_match_count, 'int'),
                'posts'             => new xmlrpcval($return_list, 'array'),
            ), 'struct'));
        }
    } else {
        return new xmlrpcresp(new xmlrpcval($return_list, 'array'));
    }
}

function xmlresptrue()
{
    $result = new xmlrpcval(array(
        'result'        => new xmlrpcval(true, 'boolean'),
        'result_text'   => new xmlrpcval('', 'base64')
    ), 'struct');
    
    return new xmlrpcresp($result);
}