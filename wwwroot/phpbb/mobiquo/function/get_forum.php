<?php
/**
*
* @copyright (c) 2009, 2010, 2011 Quoord Systems Limited
* @license http://opensource.org/licenses/gpl-2.0.php GNU Public License (GPLv2)
*
*/

defined('IN_MOBIQUO') or exit;

function get_forum_func()
{
    global $db, $auth, $user, $config, $mobiquo_config, $phpbb_home, $request_params;
    $return_description = (isset($request_params[0]) && intval($request_params[0])!==0) ? boolean($request_params[0]) : false;
    $root_forum_id = isset($request_params[1]) ? intval($request_params[1]) : 0;
    
    $user_watch_array = array();
    if($user->data['is_registered'])
    {
        $sql = "SELECT notify_status,forum_id FROM " . FORUMS_WATCH_TABLE . " WHERE user_id = '".$user->data['user_id']."'";
        $result_watch = $db->sql_query($sql);
        while($row_watch = $db->sql_fetchrow($result_watch))
        {
            if(isset($row_watch['notify_status']) && !is_null($row_watch['notify_status']) && $row_watch['notify_status'] !== '')
            {
                $user_watch_array[] = $row_watch['forum_id'];
            }
        }
    }
    
    $forum_rows = array();
    $forum_hide_forum_arr = !empty($config['mobiquo_hide_forum_id']) ? explode(',',$config['mobiquo_hide_forum_id']) : array();
    $sql = 'SELECT f.*  FROM ' . FORUMS_TABLE . ' f ' .  '
            ORDER BY f.left_id ASC';
    $result = $db->sql_query($sql, 600);
    
    while ($row = $db->sql_fetchrow($result))
    {
        $forum_id   = $row['forum_id'];
        
        if ($row['forum_type'] == FORUM_CAT && ($row['left_id'] + 1 == $row['right_id']))
        {
            // Non-postable forum with no subforums, don't display
            continue;
        }
        
        // Skip branch
        if (isset($right_id))
        {
            if ($row['left_id'] < $right_id)
            {
                continue;
            }
            unset($right_id);
        }
        if (!$auth->acl_getf('f_list', $forum_id) || (isset($mobiquo_config['hide_forum_id']) && in_array($forum_id, $mobiquo_config['hide_forum_id'])))
        {
            // if the user does not have permissions to list this forum, skip everything until next branch
            $right_id = $row['right_id'];
            continue;
        }
        if(in_array($forum_id, $forum_hide_forum_arr))
        {
            continue;
        }
        if(!$auth->acl_get('f_read', $forum_id))
        {
            continue;
        }
        
        $parent_id  = $row['parent_id']; 
        $forum_type = $forum['forum_link'] ? 'link' : ($forum['forum_type'] != FORUM_POST ? 'category' : 'forum');
        
        if ($logo_icon_name = tp_get_forum_icon($forum_id, $forum_type, $forum['forum_status'], $forum['unread_count']))
            $logo_url = $phpbb_home.$config['tapatalkdir'] .'/forum_icons/'.$logo_icon_name;
        else if ($forum['forum_image'])
        {
            if (preg_match('#^https?://#i', $forum['forum_image']))
                $logo_url = $forum['forum_image'];
            else
                $logo_url = $phpbb_home.$forum['forum_image'];
        }
        else $logo_url = '';
            
        if ($return_description)
        {
            $description = smiley_text($row['forum_desc'], true);
            $description = generate_text_for_display($description, $row['forum_desc_uid'], $row['forum_desc_bitfield'], $row['forum_desc_options']);
            $description = basic_clean(preg_replace('/<br *?\/?>/i', "\n", $description));
        }
        else $description = '';
        
        if ($user->data['is_registered'] && ($config['email_enable'] || $config['jab_enable']) && $config['allow_forum_notify'] && $row['forum_type'] == FORUM_POST && $auth->acl_get('f_subscribe', $forum_id))
        {
            $row['can_subscribe'] = true;
            $row['is_subscribed'] = in_array($row['forum_id'], $user_watch_array) ? true : false;
        } else {
            $row['can_subscribe'] = false;
            $row['is_subscribed'] = false;
        }
        
        $node['forum_id']     = $forum_id;
        $node['forum_name']   = basic_clean($row['forum_name']);
        $node['parent_id']    = $row['parent_id'];
        $node['description']  = $description;
        $node['logo_url']     = $logo_url;
        $node['unread_count'] = 0;//undo
        $node['new_post']     = ($node['unread_count']) ? true : false;
        $node['is_protected'] = ($row['forum_password']) ? true : false ;
        $node['is_subscribed']= $row['is_subscribed'];
        $node['can_subscribe']= $row['can_subscribe'];
        $node['url']          = $row['forum_link'];
        $node['sub_only']     = ($row['forum_type'] != FORUM_POST) ? true : false ; 
        //$node['child']        = array();
        
        $forum_rows[$parent_id]['child'][] = $node;
        $count = count($forum_rows[$parent_id]['child']);
        $forum_rows[$forum_id] = &$forum_rows[$parent_id]['child'][$count-1];
    }
    //print_r($forum_rows);
    $db->sql_freeresult($result);
    if($root_forum_id !== 0)
        return $forum_rows[$root_forum_id];
    else 
    {
        $result = array();
        foreach($forum_rows['0']['child'] as $key => $value)
            $result[] = $value;
        return $result;
    }
} // End of get_forum_func



