<?php
/**
*
* @copyright (c) 2009, 2010, 2011 Quoord Systems Limited
* @license http://opensource.org/licenses/gpl-2.0.php GNU Public License (GPLv2)
*
*/

defined('IN_MOBIQUO') or exit;

function mark_all_as_read_func($xmlrpc_params)
{
    $params = php_xmlrpc_decode($xmlrpc_params);
    
    if (!isset($params[0]) || $params[0] === 0)
    {
        markread('all');
    }
    else
    {
        $forum_id = intval($params[0]);
        markread('topics', $forum_id);
    }
    
    $response = new xmlrpcval(
        array(
            'result'        => new xmlrpcval(true, 'boolean'),
            'result_text'   => new xmlrpcval('', 'base64'),
        ),
        'struct'
    );
    
    return new xmlrpcresp($response);
}
