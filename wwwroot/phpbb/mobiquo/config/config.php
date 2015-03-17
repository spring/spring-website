<?php
/**
*
* @copyright (c) 2009 Quoord Systems Limited
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

defined('IN_MOBIQUO') or exit;

foreach($_COOKIE as $key => $value) $_REQUEST[$key] = $value;

$mobiquo_config = get_mobiquo_config();
$phpEx = $mobiquo_config['php_extension'];
$phpbb_root_path = dirname(dirname(dirname(__FILE__))).'/';
$mobiquo_root_path = dirname(dirname(__FILE__)).'/';

define('PHPBB_ROOT_PATH',$phpbb_root_path);
define('MOBIQUO_ROOT_PATH',$mobiquo_root_path);

function get_mobiquo_config() 
{
    $config_file = './config/config.txt';
    file_exists($config_file) or exit('config.txt does not exists');
    
    if(function_exists('file_get_contents')){
        $tmp = file_get_contents($config_file);
    }else{
        $handle = fopen($config_file, 'rb');
        $tmp = fread($handle, filesize($config_file));
        fclose($handle);
    }
    
    // remove comments by /*xxxx*/ or //xxxx
    $tmp = preg_replace('/\/\*.*?\*\/|\/\/.*?(\n)/si','$1',$tmp);
    $tmpData = preg_split("/\s*\n/", $tmp, -1, PREG_SPLIT_NO_EMPTY);
    
    $mobiquo_config = array();
    foreach ($tmpData as $d){
        list($key, $value) = preg_split("/=/", $d, 2); // value string may also have '='
        $key = trim($key);
        $value = trim($value);
        if ($key == 'hide_forum_id')
        {
            $value = preg_split('/\s*,\s*/', $value, -1, PREG_SPLIT_NO_EMPTY);
            if (is_array($value))
            {
                $_value = array();
                foreach($value as $v)
                    $_value[] = (int)$v;
                $value = $_value;
            }
            else $value = (int)$value;
            count($value) and $mobiquo_config[$key] = $value;
        }
        else
        {
            strlen($value) and $mobiquo_config[$key] = $value;
        }
    }
    
    
    return $mobiquo_config;
}
