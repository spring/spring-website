<?php
/**
*
* @copyright (c) 2009 Quoord Systems Limited
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

defined('IN_MOBIQUO') or exit;

function unbookmark_topic_func($xmlrpc_params)
{
    global $db, $user;
    
    $params = php_xmlrpc_decode($xmlrpc_params);
    
    if (!isset($params[0]))     // topic id undefine
    {
        return get_error(1);
    }
    else if ($params[0] === 0)  // topic id equal 0
    {
        return get_error(7);
    }

    // get topic id from parameters
    $topic_id = $params[0];
    $user_id = $user->data['user_id'];
    $uns_result = false;

    if ($user->data['is_registered'])
	{
		$sql = 'DELETE FROM ' . BOOKMARKS_TABLE . "
			WHERE user_id = $user_id
				AND topic_id = $topic_id";
		$db->sql_query($sql);
		$uns_result = true;
	}
    
    $response = new xmlrpcval($uns_result, 'boolean');
    
    return new xmlrpcresp($response);
} // End of subscribe_topic_func

?>