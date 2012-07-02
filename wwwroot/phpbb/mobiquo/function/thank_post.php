<?php

/**
*
* @copyright (c) 2009, 2010, 2011 Quoord Systems Limited
* @license http://opensource.org/licenses/gpl-2.0.php GNU Public License (GPLv2)
*
*/

defined('IN_MOBIQUO') or exit;

function thank_post_func($xmlrpc_params)
{
    require('./function/get_thread_by_post.php');
    return get_thread_by_post_func($xmlrpc_params);
}