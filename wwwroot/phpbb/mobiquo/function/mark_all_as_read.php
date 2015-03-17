<?php
/**
*
* @copyright (c) 2009, 2010, 2011 Quoord Systems Limited
* @license http://opensource.org/licenses/gpl-2.0.php GNU Public License (GPLv2)
*
*/

defined('IN_MOBIQUO') or exit;

function mark_all_as_read_func()
{
    global $request_params, $db;
    
    if (!isset($request_params[0]) || $request_params[0] === 0)
    {
        markread('all');
    }
    else
    {
        $forum_id = intval($request_params[0]);
        $sql = 'SELECT forum_id, left_id, right_id, forum_type FROM ' . FORUMS_TABLE;
        $result = $db->sql_query($sql);
        
        $forums = array();
        while ($row = $db->sql_fetchrow($result))
        {
            if($row['forum_type'] == FORUM_POST)
            {
                $forums[$row['forum_id']]['left']  = $row['left_id'];
                $forums[$row['forum_id']]['right'] = $row['right_id'];
            }
        }
        $db->sql_freeresult($result);
        $left  = $forums[$forum_id]['left'];
        $right = $forums[$forum_id]['right'];
        
        foreach($forums as $key => $value)
        {
            if ($value['left'] > $left && $value['right'] < $right)
                markread('topics', $key);
        }
        markread('topics', $forum_id);
    }
    
    $response = array(
        'result' => true,
        'result_text' => '',
    );
    
    return $response;
}
