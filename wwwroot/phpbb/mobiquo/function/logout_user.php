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
    $result = array(
        'result' => (boolean)1,
        'result_text' => '',
    );
    return $result;
} // End of logout_user_func
