<?php
/**
*
* @copyright (c) 2009, 2010, 2011 Quoord Systems Limited
* @license http://opensource.org/licenses/gpl-2.0.php GNU Public License (GPLv2)
*
*/

defined('IN_MOBIQUO') or exit;

function get_id_by_url_func($xmlrpc_params)
{    
    global $phpbb_home;
    
    $params = php_xmlrpc_decode($xmlrpc_params);
    $url = trim($params[0]);
    
    if (strpos($url, $phpbb_home) === 0)
    {
        $path = '/' . substr($url, strlen($phpbb_home));
        $fid = $tid = $pid = "";
        
        // get forum id
        if (preg_match('/(\?|&|;)(f|fid|board)=(\d+)(\W|$)/', $path, $match)) {
            $fid = $match['3'];
        } elseif (preg_match('/\W(f|forum)-?(\d+)(\W|$)/', $path, $match)) {
            $fid = $match['2'];
        } elseif (preg_match('/\/forum\/(\d+)-(\w|-)+(\W|$)/', $path, $match)) {
            $fid = $match['1'];
            $path = str_replace($match[0], $match[3], $path);
        } elseif (preg_match('/forumdisplay\.php(\?|\/)(\d+)(\W|$)/', $path, $match)) {
            $fid = $match['2'];
            $path = str_replace($match[0], $match[3], $path);
        } elseif (preg_match('/(index\.php\?|\/)forums\/.+\.(\d+)/', $path, $match)) {
            $fid = $match['2'];
        }
        
        // get topic id
        if (preg_match('/(\?|&|;)(t|tid|topic)=(\d+)(\W|$)/', $path, $match)) {
            $tid = $match['3'];
        } elseif (preg_match('/\W(t|(\w|-)+-t_|topic|article)-?(\d+)(\W|$)/', $path, $match)) {
            $tid = $match['3'];
        } elseif (preg_match('/showthread\.php(\?|\/)(\d+)(\W|$)/', $path, $match)) {
            $tid = $match['2'];
        } elseif (preg_match('/(\?|\/)(\d+)-(\w|-)+(\.|\/|$)/', $path, $match)) {
            $tid = $match['2'];
        } elseif (preg_match('/(\?|\/)(\w|-)+-(\d+)(\.|\/|$)/', $path, $match)) {
            $tid = $match['3'];
        } elseif (preg_match('/(index\.php\?|\/)threads\/.+\.(\d+)/', $path, $match)) {
            $tid = $match['2'];
        }
        
        // get post id
        if (preg_match('/(\?|&|;)(p|pid)=(\d+)(\W|$)/', $path, $match)) {
            $pid = $match['3'];
        } elseif (preg_match('/\W(p|(\w|-)+-p|post|msg)(-|_)?(\d+)(\W|$)/', $path, $match)) {
            $pid = $match['4'];
        } elseif (preg_match('/__p__(\d+)(\W|$)/', $path, $match)) {
            $pid = $match['1'];
        }
    }
    
    $result = array();
    if ($fid) $result['forum_id'] = new xmlrpcval($fid, 'string');
    if ($tid) $result['topic_id'] = new xmlrpcval($tid, 'string');
    if ($pid) $result['post_id'] = new xmlrpcval($pid, 'string');
    
    $response = new xmlrpcval($result, 'struct');
    
    return new xmlrpcresp($response);
}