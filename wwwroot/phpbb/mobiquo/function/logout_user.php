<?php
/**
*
* @copyright (c) 2009 Quoord Systems Limited
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

defined('IN_MOBIQUO') or exit;

function logout_user_func()
{
    global $user;
    
    $user->session_kill();

    return new xmlrpcresp(new xmlrpcval(true, 'boolean'));
} // End of logout_user_func
