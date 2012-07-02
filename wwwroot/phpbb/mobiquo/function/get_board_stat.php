<?php
/**
*
* @copyright (c) 2009, 2010, 2011 Quoord Systems Limited
* @license http://opensource.org/licenses/gpl-2.0.php GNU Public License (GPLv2)
*
*/

defined('IN_MOBIQUO') or exit;

function get_board_stat_func() 
{    
    global $config;
    
    $online_info = obtain_users_online();
    
    $board_stat = array(
        'total_threads' => new xmlrpcval($config['num_topics'], 'int'),
        'total_posts'   => new xmlrpcval($config['num_posts'], 'int'),
        'total_members' => new xmlrpcval($config['num_users'], 'int'),
        'guest_online'  => new xmlrpcval($online_info['guests_online'], 'int'),
        'total_online'  => new xmlrpcval($online_info['total_online'], 'int')
    );
    
    $response = new xmlrpcval($board_stat, 'struct');
    
    return new xmlrpcresp($response);
}
