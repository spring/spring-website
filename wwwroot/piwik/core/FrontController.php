<?php
/**
 * Piwik - Open source web analytics
 * 
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id: FrontController.php 5202 2011-09-21 23:00:44Z matt $
 * 
 * @category Piwik
 * @package Piwik
 */

/**
 * @see core/PluginsManager.php
 * @see core/Translate.php
 * @see core/Option.php
 */
require_once PIWIK_INCLUDE_PATH . '/core/PluginsManager.php';
require_once PIWIK_INCLUDE_PATH . '/core/Translate.php';
require_once PIWIK_INCLUDE_PATH . '/core/Option.php';

/**
 * Front controller.
 * This is the class hit in the first place.
 * It dispatches the request to the right controller.
 * 
 * For a detailed explanation, see the documentation on http://piwik.org/docs/plugins/framework-overview
 * 
 * @package Piwik
 * @subpackage Piwik_FrontController
 */
class Piwik_FrontController
{
	/**
	 * Set to false and the Front Controller will not dispatch the request
	 *
	 * @var bool
	 */
	static public $enableDispatch = true;
	
	static private $instance = null;
	
	/**
	 * returns singleton
	 * 
	 * @return Piwik_FrontController
	 */
	static public function getInstance()
	{
		if (self::$instance == null)
		{
			self::$instance = new self;
		}
		return self::$instance;
	}
	
	/**
	 * Dispatches the request to the right plugin and executes the requested action on the plugin controller.
	 * 
	 * @throws Exception in case the plugin doesn't exist, the action doesn't exist, there is not enough permission, etc.
	 *
	 * @param string $module
	 * @param string $action
	 * @param array $parameters
	 * @return mixed The returned value of the calls, often nothing as the module print but don't return data
	 * @see fetchDispatch() 
	 */
	function dispatch( $module = null, $action = null, $parameters = null)
	{
		if( self::$enableDispatch === false)
		{
			return;
		}

		if(is_null($module))
		{
			$defaultModule = 'CoreHome';
			$module = Piwik_Common::getRequestVar('module', $defaultModule, 'string');
		}

		if(is_null($action))
		{
			$action = Piwik_Common::getRequestVar('action', false);
		}

		if(!Piwik_Session::isFileBasedSessions()
			&& ($module !== 'API' || ($action && $action !== 'index')))
		{
			Piwik_Session::start();
		}

		if(is_null($parameters))
		{
			$parameters = array();
		}
		
		if(!ctype_alnum($module))
		{
			throw new Exception("Invalid module name '$module'");
		}
		
		if( ! Piwik_PluginsManager::getInstance()->isPluginActivated( $module )) 
		{
			throw new Piwik_FrontController_PluginDeactivatedException($module);
		}

		$controllerClassName = 'Piwik_'.$module.'_Controller';

		// FrontController's autoloader
		if(!class_exists($controllerClassName, false))
		{
			$moduleController = PIWIK_INCLUDE_PATH . '/plugins/' . $module . '/Controller.php';
			if(!is_readable($moduleController))
			{
				throw new Exception("Module controller $moduleController not found!");
			}
			require_once $moduleController; // prefixed by PIWIK_INCLUDE_PATH
		}
		
		$controller = new $controllerClassName();
		if($action === false)
		{
			$action = $controller->getDefaultAction();
		}
		
//		Piwik::log("Dispatching $module / $action, parameters: ".var_export($parameters, $return = true));
		if( !is_callable(array($controller, $action)))
		{
			throw new Exception("Action $action not found in the controller $controllerClassName.");				
		}
		try {
			return call_user_func_array( array($controller, $action ), $parameters);
		} catch(Piwik_Access_NoAccessException $e) {
			Piwik_PostEvent('FrontController.NoAccessException', $e);					
		} catch(Exception $e) {
			$debugTrace = $e->getTraceAsString();
			Piwik_ExitWithMessage($e->getMessage(), Piwik::shouldLoggerLog() ? $debugTrace : '', true);
		}
	}
	
	/**
	 * Often plugins controller display stuff using echo/print.
	 * Using this function instead of dispatch() returns the output string form the actions calls.
	 *
	 * @param string $controllerName
	 * @param string $actionName
	 * @param array $parameters
	 * @return string
	 */
	function fetchDispatch( $controllerName = null, $actionName = null, $parameters = null)
	{
		ob_start();
		$output = $this->dispatch( $controllerName, $actionName, $parameters);
		// if nothing returned we try to load something that was printed on the screen
		if(empty($output))
		{
			$output = ob_get_contents();
		}
		ob_end_clean();
		return $output;
	}
	
	/**
	 * Called at the end of the page generation
	 *
	 */
	function __destruct()
	{
		try {
			Piwik::printSqlProfilingReportZend();
			Piwik::printQueryCount();
			Piwik::printTimer();
		} catch(Exception $e) {}
	}
	
	// Should we show exceptions messages directly rather than display an html error page?
	public static function shouldRethrowException()
	{
		// If we are in no dispatch mode, eg. a script reusing Piwik libs, 
		// then we should return the exception directly, rather than trigger the event "bad config file"
		// which load the HTML page of the installer with the error.
		// This is at least required for misc/cron/archive.php and useful to all other scripts
		return (defined('PIWIK_ENABLE_DISPATCH') && !PIWIK_ENABLE_DISPATCH)
				|| Piwik_Common::isPhpCliMode()
				|| Piwik_Common::isArchivePhpTriggered()
				;
	}
	
	/**
	 * Must be called before dispatch()
	 * - checks that directories are writable,
	 * - loads the configuration file,
	 * - loads the plugin, 
	 * - inits the DB connection,
	 * - etc.
	 */
	function init()
	{
		static $initialized = false;
		if($initialized)
		{
			return;
		}
		$initialized = true;
					
		
		try {
			Zend_Registry::set('timer', new Piwik_Timer);
			
			$directoriesToCheck = array(
					'/tmp/',
					'/tmp/templates_c/',
					'/tmp/cache/',
					'/tmp/assets/'
			);
			
			Piwik::checkDirectoriesWritableOrDie($directoriesToCheck);
			Piwik_Common::assignCliParametersToRequest();

			Piwik_Translate::getInstance()->loadEnglishTranslation();

			$exceptionToThrow = false;

			try {
				Piwik::createConfigObject();
			} catch(Exception $e) {
				Piwik_PostEvent('FrontController.NoConfigurationFile', $e, $info = array(), $pending = true);
				$exceptionToThrow = $e;
			}

			if(Piwik_Session::isFileBasedSessions())
			{
				Piwik_Session::start();
			}

			if(Zend_Registry::get('config')->General->maintenance_mode == 1
				&& !Piwik_Common::isPhpCliMode())
			{
				$format = Piwik_Common::getRequestVar('format', '');
				$exception = new Exception("Piwik is in scheduled maintenance. Please come back later.");
				if(empty($format))
				{
					throw $exception;
				}
				$response = new Piwik_API_ResponseBuilder( $format );
				echo $response->getResponseException( $exception );
				exit;
			}

			$pluginsManager = Piwik_PluginsManager::getInstance();
			$pluginsManager->loadPlugins( Zend_Registry::get('config')->Plugins->Plugins->toArray() );

			if($exceptionToThrow)
			{
				throw $exceptionToThrow;
			}

			try {
				Piwik::createDatabaseObject();
			} catch(Exception $e) {
				if(self::shouldRethrowException())
				{
					throw $e;
				}
				Piwik_PostEvent('FrontController.badConfigurationFile', $e, $info = array(), $pending = true);
				throw $e;
			}

			Piwik::createLogObject();
			
			// creating the access object, so that core/Updates/* can enforce Super User and use some APIs
			Piwik::createAccessObject();
			Piwik_PostEvent('FrontController.dispatchCoreAndPluginUpdatesScreen');

			Piwik_PluginsManager::getInstance()->installLoadedPlugins();
			Piwik::install();
			
			// ensure the current Piwik URL is known for later use
			if(method_exists('Piwik', 'getPiwikUrl'))
			{
				$host = Piwik::getPiwikUrl();
			}
			
			Piwik_PostEvent('FrontController.initAuthenticationObject');
			try {
				$authAdapter = Zend_Registry::get('auth');
			} catch(Exception $e){
				throw new Exception("Authentication object cannot be found in the Registry. Maybe the Login plugin is not activated?
									<br />You can activate the plugin by adding:<br />
									<code>Plugins[] = Login</code><br />
									under the <code>[Plugins]</code> section in your config/config.inc.php");
			}
			
			Zend_Registry::get('access')->reloadAccess($authAdapter);
			
			Piwik_Translate::getInstance()->reloadLanguage();

			Piwik::raiseMemoryLimitIfNecessary();

			$pluginsManager->postLoadPlugins();
			
			Piwik_PostEvent('FrontController.checkForUpdates');
		} catch(Exception $e) {
			
			if(self::shouldRethrowException())
			{
				throw $e;
			}
						
			Piwik_ExitWithMessage($e->getMessage(), false, true);
		}
		
//		Piwik::log('End FrontController->init() - Request: '. var_export($_REQUEST, true));
	}
}

/**
 * Exception thrown when the requested plugin is not activated in the config file
 *
 * @package Piwik
 * @subpackage Piwik_FrontController
 */
class Piwik_FrontController_PluginDeactivatedException extends Exception
{
	function __construct($module)
	{
		parent::__construct("The plugin '$module' is not activated. You can activate the plugin on the 'Plugins admin' page.");
	}
}

