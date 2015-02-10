<?php
/**
*
* @copyright (c) 2009, 2010, 2011 Quoord Systems Limited
* @license http://opensource.org/licenses/gpl-2.0.php GNU Public License (GPLv2)
*
*/
define('IN_PHPBB', true);
define('IN_MOBIQUO', true);
define('TT_ROOT', getcwd() . DIRECTORY_SEPARATOR);

if (isset($_SERVER['HTTP_DEBUG']) && $_SERVER['HTTP_DEBUG'] && file_exists('debug.on'))
{
    define('MOBIQUO_DEBUG', -1);
    @ini_set('display_errors', 1);
}
else
    define('MOBIQUO_DEBUG', 0);
if ( isset( $_SERVER['SERVER_PROTOCOL'] ) AND strstr( $_SERVER['SERVER_PROTOCOL'], '/1.0' ) )
    define('HTTP_10', 1);
define('TAPATALK_DIR', basename(dirname(__FILE__)));
if (isset($_GET['welcome']))
{
    include('./smartbanner/app.php');
    exit;
}

error_reporting(MOBIQUO_DEBUG);
ob_start();

include('./include/xmlrpc-master/src/Xmlrpc.php');
include('./include/mobi_xmlrpc_io.inc');
require('./mobiquo_common.php');
require('./config/config.php');

if(isset($_POST['session']) && isset($_POST['api_key']) && isset($_POST['subject']) && isset($_POST['body']) || isset($_POST['email_target']))
{
    include("./function/invitation.php");
}//undo

define('PHPBB_MSG_HANDLER', 'xmlrpc_error_handler');
register_shutdown_function('xmlrpc_shutdown');

require('./server_define.php');
require('./env_setting.php');


if ($request_file && isset($server_param[$request_method]))
{
    if ($tapatalk_handle =='system')
    {
        include('./xmlrpcresp.php');
        require($request_file);
    }
    else
    {
        require($phpbb_root_path . 'common.' . $phpEx);
        require('./include/user.class.php');
        if($request_file !== 'search')
        {
            $user = new tapa_user;
            $user->session_begin();
            $auth->acl($user->data);
            $user->setup();
            $phpbb_home = generate_board_url().'/';
            
            //$can_subscribe = ($config['email_enable'] || $config['jab_enable']) && $config['allow_topic_notify'] && $user->data['is_registered'];
            
            header('Mobiquo_is_login:'.($user->data['is_registered'] ? 'true' : 'false'));
            
            if ($user->data['user_new_privmsg'])
            {
                include_once($phpbb_root_path . 'includes/functions_privmsgs.' . $phpEx);
                place_pm_into_folder($global_privmsgs_rules);
            }
            
            ob_clean();
            
            global $phpbb_extension_manager;
            if (!$phpbb_extension_manager->is_enabled('tapatalk/tapatalk') && $request_file !== 'get_config')
            {
                $user->add_lang('acp/extensions');
                trigger_error($user->lang('EXTENSION_DISABLED','Tapatalk'));
            };
        }
        
        error_reporting(MOBIQUO_DEBUG);
        if (MOBIQUO_DEBUG == 0) ob_start();
        if (strpos($request_file, 'm_') === 0)
            require('./function/moderation.php');
        else
            require('./function/'.$request_file.'.php');
    }
}
else 
{
    require ('web.php');
	exit;
}

//Final response
mobi_shutdown();
exit;

?>