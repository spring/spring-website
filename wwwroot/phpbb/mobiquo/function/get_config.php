<?php
/**
*
* @copyright (c) 2009 Quoord Systems Limited
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

defined('IN_MOBIQUO') or exit;

function get_config_func() 
{    
    global $mobiquo_config, $config;
    
    $config_list = array(
        'is_open'    => new xmlrpcval($mobiquo_config['is_open'] ? true : false, 'boolean'),
        'guest_okay' => new xmlrpcval($mobiquo_config['guest_okay'] ? true : false, 'boolean'),
    );
    
    foreach($mobiquo_config as $key => $value)
    {
        if (!in_array($key, array('is_open', 'guest_okay', 'php_extension', 'keep_log', 'shorten_quote', 'hide_forum_id', 'check_dnsbl')))
        {
            $config_list[$key] = new xmlrpcval($value, 'string');
        }
    }
    
    $response = new xmlrpcval($config_list, 'struct');
    
    return new xmlrpcresp($response);
}
