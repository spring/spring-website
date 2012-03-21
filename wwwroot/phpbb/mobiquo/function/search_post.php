<?php
/**
*
* @copyright (c) 2009 Quoord Systems Limited
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/
defined('IN_MOBIQUO') or exit;

function search_post_func($xmlrpc_params)
{
    global $config;
    $params = php_xmlrpc_decode($xmlrpc_params);

    $keywords = $params[0];

    $start_num  = 0;
    $end_num    = 19;
    if (isset($params[1]) && is_int($params[1]))
    {
        $start_num = $params[1];
    }

    // get end index of topic from parameters
    if (isset($params[2]) && is_int($params[2]))
    {
        $end_num = $params[2];
    }

    // check if topic index is out of range
    if ($start_num > $end_num)
    {
        return get_error(5);
    }

    // return at most 50 topics
    if ($end_num - $start_num >= 50)
    {
        $end_num = $start_num + 49;
    }
    $config['topics_per_page'] = $end_num - $start_num + 1;
    
    $_GET['keywords'] = $keywords;
    $_GET['terms'] = 'all';
    $_GET['author'] = '';
    $_GET['sc'] = 1;
    $_GET['sf'] = 'all';
    $_GET['sr'] = 'posts';
    $_GET['sk'] = 't';
    $_GET['sd'] = 'd';
    $_GET['st'] = $start_num;
    $_GET['ch'] = 300;
    $_GET['t'] = 0;
    $_GET['submit'] = 'Search';
    
    $_REQUEST = array_unique(array_merge($_GET, $_REQUEST));

    include('./function/search.php');
    return search_func();

}
