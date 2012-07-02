<?php
/**
*
* @copyright (c) 2009, 2010, 2011 Quoord Systems Limited
* @license http://opensource.org/licenses/gpl-2.0.php GNU Public License (GPLv2)
*
*/

defined('IN_MOBIQUO') or exit;

function logout_user_func()
{
    global $user;
    
    $user->session_kill();

    return new xmlrpcresp(new xmlrpcval(true, 'boolean'));
} // End of logout_user_func
