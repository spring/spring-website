<?php
/**
 * Piwik - Open source web analytics
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id: View.php 5188 2011-09-19 01:41:27Z matt $
 *
 * @category Piwik
 * @package Piwik
 */

/**
 * Transition for pre-Piwik 0.4.4
 */
if(!defined('PIWIK_USER_PATH'))
{
	define('PIWIK_USER_PATH', PIWIK_INCLUDE_PATH);
}

/**
 * View class to render the user interface
 *
 * @package Piwik
 */
class Piwik_View implements Piwik_iView
{
	// view types
	const STANDARD = 0; // REGULAR, FULL, CLASSIC
	const MOBILE = 1;
	const CLI = 2;

	private $template = '';
	private $smarty = false;
	private $variables = array();
	private $contentType = 'text/html; charset=utf-8';
	private $xFrameOptions = null;
	protected $piwikUrl = false;
	
	public function __construct( $templateFile, $smConf = array(), $filter = true )
	{
		$this->template = $templateFile;
		$this->smarty = new Piwik_Smarty($smConf, $filter);

		// global value accessible to all templates: the piwik base URL for the current request
		$this->piwikUrl = Piwik_Common::sanitizeInputValue(Piwik_Url::getCurrentUrlWithoutFileName());
		$this->piwik_version = Piwik_Version::VERSION;
		$this->cacheBuster = md5(Piwik_Common::getSalt() . PHP_VERSION . Piwik_Version::VERSION);
	}
	
	/**
	 * Directly assigns a variable to the view script.
	 * VAR names may not be prefixed with '_'.
	 *
	 *	@param string $key The variable name.
	 *	@param mixed $val The variable value.
	 */
	public function __set($key, $val)
	{
		$this->smarty->assign($key, $val);
	}

	/**
	 * Retrieves an assigned variable.
	 * VAR names may not be prefixed with '_'.
	 *
	 *	@param string $key The variable name.
	 *	@return mixed The variable value.
	 */
	public function __get($key)
	{
		return $this->smarty->get_template_vars($key);
	}

	/**
	 * Renders the current view.
	 *
	 * @return string Generated template
	 */
	public function render()
	{
		try {
			$this->currentModule = Piwik::getModule();
			$this->currentAction = Piwik::getAction();
			$userLogin = Piwik::getCurrentUserLogin();
			$this->userLogin = $userLogin;
			
			// workaround for #1331
			$count = method_exists('Piwik', 'getWebsitesCountToDisplay') ? Piwik::getWebsitesCountToDisplay() : 1;

			$sites = Piwik_SitesManager_API::getInstance()->getSitesWithAtLeastViewAccess($count);
			usort($sites, create_function('$site1, $site2', 'return strcasecmp($site1["name"], $site2["name"]);'));
			$this->sites = $sites;
			$this->url = Piwik_Common::sanitizeInputValue(Piwik_Url::getCurrentUrl());
			$this->token_auth = Piwik::getCurrentUserTokenAuth();
			$this->userHasSomeAdminAccess = Piwik::isUserHasSomeAdminAccess();
			$this->userIsSuperUser = Piwik::isUserIsSuperUser();
			$this->latest_version_available = Piwik_UpdateCheck::isNewestVersionAvailable();
			$this->disableLink = Piwik_Common::getRequestVar('disableLink', 0, 'int');
			$this->isWidget = Piwik_Common::getRequestVar('widget', 0, 'int');
			if(Zend_Registry::get('config')->General->autocomplete_min_sites <= count($sites))
			{
				$this->show_autocompleter = true;
			}
			else
			{
				$this->show_autocompleter = false;
			}

			// workaround for #1331
			$this->loginModule = method_exists('Piwik', 'getLoginPluginName') ? Piwik::getLoginPluginName() : 'Login';
			
			$user = Piwik_UsersManager_API::getInstance()->getUser($userLogin);
			$this->userAlias = $user['alias'];
			
		} catch(Exception $e) {
			// can fail, for example at installation (no plugin loaded yet)
		}
		
		$this->totalTimeGeneration = Zend_Registry::get('timer')->getTime();
		try {
			$this->totalNumberOfQueries = Piwik::getQueryCount();
		}
		catch(Exception $e){
			$this->totalNumberOfQueries = 0;
		}
 
		// workaround for #1331
		if(method_exists('Piwik', 'overrideCacheControlHeaders'))
		{
			Piwik::overrideCacheControlHeaders('no-store');
		}
		@header('Content-Type: '.$this->contentType);
		if($this->xFrameOptions)
		{
			@header('X-Frame-Options: '.$this->xFrameOptions);
		}
		
		return $this->smarty->fetch($this->template);
	}

	/**
	 * Set Content-Type field in HTTP response.
	 * Since PHP 5.1.2, header() protects against header injection attacks.
	 *
	 * @param string $contentType
	 */
	public function setContentType( $contentType )
	{
		$this->contentType = $contentType;
	}

	/**
	 * Set X-Frame-Options field in the HTTP response.
	 *
	 * @param string $option ('deny' or 'sameorigin')
	 */
	public function setXFrameOptions( $option = 'deny' )
	{
		if($option === 'deny' || $option === 'sameorigin')
		{
			$this->xFrameOptions = $option;
		}
	}

	/**
	 * Add form to view
	 *
	 * @param Piwik_QuickForm2 $form
	 */
	public function addForm( $form )
	{
		if($form instanceof Piwik_QuickForm2)
		{
			static $registered = false;
			if(!$registered)
			{
				HTML_QuickForm2_Renderer::register('smarty', 'HTML_QuickForm2_Renderer_Smarty');
				$registered = true;
			}

			// Create the renderer object
			$renderer = HTML_QuickForm2_Renderer::factory('smarty');
			$renderer->setOption('group_errors', true);

			// build the HTML for the form
			$form->render($renderer);

			// assign array with form data
			$this->smarty->assign('form_data', $renderer->toArray());
			$this->smarty->assign('element_list', $form->getElementList());
		}
	}

	/**
	 * Assign value to a variable for use in Smarty template
	 *
	 * @param string|array $var
	 * @param mixed $value
	 */
	public function assign($var, $value=null)
	{
		if (is_string($var))
		{
			$this->smarty->assign($var, $value);
		}
		elseif (is_array($var))
		{
			foreach ($var as $key => $value)
			{
				$this->smarty->assign($key, $value);
			}
		}
	}

	/**
	 * Clear compiled Smarty templates
	 */
	static public function clearCompiledTemplates()
	{
		$view = new Piwik_View(null);
		$view->smarty->clear_compiled_tpl();
	}

/*
	public function isCached($template)
	{
		if ($this->smarty->is_cached($template))
		{
			return true;
		}
		return false;
	}
	public function setCaching($caching)
	{
		$this->smarty->caching = $caching;
	}
*/
	
	/**
	 * Render the single report template
	 *
	 * @param string $title Report title
	 * @param string $reportHtml Report body
	 * @param bool $fetch If true, return report contents as a string; else echo to screen
	 * @return string Report contents if $fetch == true
	 */
	static public function singleReport($title, $reportHtml, $fetch = false)
	{
		$view = new Piwik_View('CoreHome/templates/single_report.tpl');
		$view->title = $title;
		$view->report = $reportHtml;
		
		if ($fetch)
		{
			return $view->render();
		}
		echo $view->render();
	}

	/**
	 * View factory method
	 *
	 * @param string $templateName Template name (e.g., 'index')
	 * @param int $viewType     View type (e.g., Piwik_View::CLI)
	 */
	static public function factory( $templateName = null, $viewType = null)
	{
		Piwik_PostEvent('View.getViewType', $viewType);

		// get caller
		$bt = @debug_backtrace();
		if($bt === null || !isset($bt[0]))
		{
			throw new Exception("View factory cannot be invoked");
		}
		$path = dirname($bt[0]['file']);

		// determine best view type
		if($viewType === null)
		{
			if(Piwik_Common::isPhpCliMode())
			{
				$viewType = self::CLI;
			}
			else
			{
				$viewType = self::STANDARD;
			}
		}

		// get template filename
		if($viewType == self::CLI)
		{
			$templateFile = $path.'/templates/cli_'.$templateName.'.tpl';
			if(file_exists($templateFile))
			{
				return new Piwik_View($templateFile, array(), false);
			}

			$viewType = self::STANDARD;
		}

		if($viewType == self::MOBILE)
		{
			$templateFile = $path.'/templates/mobile_'.$templateName.'.tpl';
			if(!file_exists($templateFile))
			{
				$viewType = self::STANDARD;
			}
		}

		if($viewType != self::MOBILE)
		{
			$templateFile = $path.'/templates/'.$templateName.'.tpl';
		}
		
		// Specified template not found
		// We allow for no specified template
		if(!empty($templateName)
			&& !file_exists($templateFile))
		{
			throw new Exception('Template not found: '.$templateFile);
		}
		return new Piwik_View($templateFile);
	}
}
