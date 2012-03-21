<?php
/**
*
* @copyright (c) 2009 Quoord Systems Limited
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

defined('IN_MOBIQUO') or exit;

function bookmark_topic_func($xmlrpc_params)
{
    global $db, $user, $config;
    
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
    $s_result = false;

    if ($config['allow_bookmarks'] && $user_id != ANONYMOUS)
	{
		$sql = 'INSERT INTO ' . BOOKMARKS_TABLE . ' ' . $db->sql_build_array('INSERT', array(
			'user_id'	=> $user_id,
			'topic_id'	=> $topic_id,
		));
		$db->sql_query($sql);
		$s_result = true;
	}
    
    $response = new xmlrpcval($s_result, 'boolean');
    
    return new xmlrpcresp($response);
} // End of subscribe_topic_func

?>