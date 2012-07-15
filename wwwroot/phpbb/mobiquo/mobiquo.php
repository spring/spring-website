<?php
/**
*
* @copyright (c) 2009, 2010, 2011 Quoord Systems Limited
* @license http://opensource.org/licenses/gpl-2.0.php GNU Public License (GPLv2)
*
*/

define('IN_PHPBB', true);
define('IN_MOBIQUO', true);
define('MOBIQUO_DEBUG', 0);

include('./config/config.php');
include('./mobiquo_common.php');

define('PHPBB_MSG_HANDLER', 'xmlrpc_error_handler');
register_shutdown_function('xmlrpc_shutdown');

include($phpbb_root_path . 'common.' . $phpEx);
//include($phpbb_root_path . 'includes/functions_display.' . $phpEx);

error_reporting(MOBIQUO_DEBUG);
if (MOBIQUO_DEBUG == 0) ob_start();

include('./include/xmlrpc.inc');
include('./include/xmlrpcs.inc');

require('./server_define.php');
require('./env_setting.php');
require('./xmlrpcresp.php');

if ($request_file && isset($server_param[$request_method]))
{
    if (strpos($request_file, 'm_') === 0)
        require('./function/moderation.php');
    else
        require('./function/'.$request_file.'.php');
}

$rpcServer = new xmlrpc_server($server_param, false);
$rpcServer->setDebug(1);
$rpcServer->compress_response = 'true';
$rpcServer->response_charset_encoding = 'UTF-8';
$rpcServer->service();
exit;

?>