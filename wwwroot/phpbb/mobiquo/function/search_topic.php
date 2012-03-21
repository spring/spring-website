<?php
/**
*
* @copyright (c) 2009, 2010, 2011 Quoord Systems Limited
* @license http://opensource.org/licenses/gpl-2.0.php GNU Public License (GPLv2)
*
*/
defined('IN_MOBIQUO') or exit;

function search_topic_func($xmlrpc_params)
{
    global $config;
    $params = php_xmlrpc_decode($xmlrpc_params);

    $keywords = $params[0];

    list($start, $limit) = process_page($params[1], $params[2]);
    
    $config['topics_per_page'] = $limit;
    
    $_GET['keywords'] = $keywords;
    $_GET['terms'] = 'all';
    $_GET['author'] = '';
    $_GET['sc'] = 1;
    $_GET['sf'] = 'all';
    $_GET['sr'] = 'topics';
    $_GET['sk'] = 't';
    $_GET['sd'] = 'd';
    $_GET['st'] = $start;
    $_GET['ch'] = 300;
    $_GET['t'] = 0;
    $_GET['submit'] = 'Search';
    
    $_REQUEST = array_unique(array_merge($_GET, $_REQUEST));
    
    include('./function/search.php');
    return search_func();
}
