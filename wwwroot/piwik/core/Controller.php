<?php
/**
 * Piwik - Open source web analytics
 * 
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id: Controller.php 5296 2011-10-14 02:52:44Z matt $
 * 
 * @category Piwik
 * @package Piwik
 */

/**
 * Parent class of all plugins Controllers (located in /plugins/PluginName/Controller.php
 * It defines some helper functions controllers can use.
 * 
 * @package Piwik
 */
abstract class Piwik_Controller
{
	/**
	 * Plugin name, eg. Referers
	 * @var string
	 */
	protected $pluginName;
	
	/**
	 * Date string
	 * 
	 * @var string
	 */
	protected $strDate;
	
	/**
	 * Piwik_Date object or null if the requested date is a range
	 * 
	 * @var Piwik_Date|null 
	 */
	protected $date;
	protected $idSite;
	
	/**
	 * @var Piwik_Site
	 */
	protected $site = null;
	
	/**
	 * Builds the controller object, reads the date from the request, extracts plugin name from 
	 */
	function __construct()
	{
		$this->init();
	}
	
	protected function init()
	{
		$aPluginName = explode('_', get_class($this));
		$this->pluginName = $aPluginName[1];
		$date = Piwik_Common::getRequestVar('date', 'yesterday', 'string');
		try {
			$this->idSite = Piwik_Common::getRequestVar('idSite', false, 'int');
			$this->site = new Piwik_Site($this->idSite);
			$date = $this->getDateParameterInTimezone($date, $this->site->getTimezone());
			$this->setDate($date);
		} catch(Exception $e){
			// the date looks like YYYY-MM-DD,YYYY-MM-DD or other format
			$this->date = null;
		}
	}
	
	/**
	 * Helper method to convert "today" or "yesterday" to the default timezone specified.
	 * If the date is absolute, ie. YYYY-MM-DD, it will not be converted to the timezone
	 * @param string $date today, yesterday, YYYY-MM-DD
	 * @param string $defaultTimezone
	 * @return Piwik_Date
	 */
	protected function getDateParameterInTimezone($date, $defaultTimezone )
	{
		$timezone = null;
		// if the requested date is not YYYY-MM-DD, we need to ensure
		//  it is relative to the website's timezone
		if(in_array($date, array('today', 'yesterday')))
		{
			// today is at midnight; we really want to get the time now, so that
			// * if the website is UTC+12 and it is 5PM now in UTC, the calendar will allow to select the UTC "tomorrow"
			// * if the website is UTC-12 and it is 5AM now in UTC, the calendar will allow to select the UTC "yesterday" 
			if($date == 'today')
			{
				$date = 'now';
			}
			elseif($date == 'yesterday')
			{
				$date = 'yesterdaySameTime';
			}
			$timezone = $defaultTimezone;
		}
		return Piwik_Date::factory($date, $timezone);
	}

	/**
	 * Sets the date to be used by all other methods in the controller.
	 * If the date has to be modified, it should be called just after the controller construct
	 * @param Piwik_Date $date
	 * @return void
	 */
	protected function setDate(Piwik_Date $date)
	{
		$this->date = $date;
		$strDate = $this->date->toString();
		$this->strDate = $strDate;
	}
	
	/**
	 * Returns the name of the default method that will be called 
	 * when visiting: index.php?module=PluginName without the action parameter
	 * 
	 * @return string
	 */
	function getDefaultAction()
	{
		return 'index';
	}

	/**
	 * Given an Object implementing Piwik_iView interface, we either:
	 * - echo the output of the rendering if fetch = false
	 * - returns the output of the rendering if fetch = true
	 *
	 * @param Piwik_ViewDataTable $view
	 * @param bool $fetch
	 * @return string|void
	 */
	protected function renderView( Piwik_ViewDataTable $view, $fetch = false)
	{
		Piwik_PostEvent(	'Controller.renderView', 
							$this, 
							array(	'view' => $view,
									'controllerName' => $view->getCurrentControllerName(),
									'controllerAction' => $view->getCurrentControllerAction(),
									'apiMethodToRequestDataTable' => $view->getApiMethodToRequestDataTable(),
									'controllerActionCalledWhenRequestSubTable' => $view->getControllerActionCalledWhenRequestSubTable(),
							)
				);

		$view->main();
		$rendered = $view->getView()->render();
		if($fetch)
		{
			return $rendered;
		}
		echo $rendered;
	}
	
	/**
	 * Returns a ViewDataTable object of an Evolution graph 
	 * for the last30 days/weeks/etc. of the current period, relative to the current date.
	 *
	 * @param string $currentModuleName
	 * @param string $currentControllerAction
	 * @param string $apiMethod
	 * @return Piwik_ViewDataTable_GenerateGraphHTML_ChartEvolution
	 */
	protected function getLastUnitGraph($currentModuleName, $currentControllerAction, $apiMethod)
	{
		$view = Piwik_ViewDataTable::factory('graphEvolution');
		$view->init( $currentModuleName, $currentControllerAction, $apiMethod );
		
		// if the date is not yet a nicely formatted date range ie. YYYY-MM-DD,YYYY-MM-DD we build it
		// otherwise the current controller action is being called with the good date format already so it's fine
		// see constructor
		if( !is_null($this->date))
		{
			$view->setParametersToModify( 
				$this->getGraphParamsModified( array('date' => $this->strDate))
				);
		}
		
		return $view;
	}

	/**
	 * Returns the array of new processed parameters once the parameters are applied.
	 * For example: if you set range=last30 and date=2008-03-10, 
	 *  the date element of the returned array will be "2008-02-10,2008-03-10"
	 * 
	 * Parameters you can set:
	 * - range: last30, previous10, etc.
	 * - date: YYYY-MM-DD, today, yesterday
	 * - period: day, week, month, year
	 * 
	 * @param array  paramsToSet = array( 'date' => 'last50', 'viewDataTable' =>'sparkline' )
	 */
	protected function getGraphParamsModified($paramsToSet = array())
	{
		if(!isset($paramsToSet['period']))
		{
			$period = Piwik_Common::getRequestVar('period');
		}
		else
		{
			$period = $paramsToSet['period'];
		}
		if($period == 'range')
		{
			return $paramsToSet;
		}
		if(!isset($paramsToSet['range']))
		{
			$range = 'last30';
		}
		else
		{
			$range = $paramsToSet['range'];
		}
		
		if(!isset($paramsToSet['date']))
		{
			$endDate = $this->strDate;
		}
		else
		{
			$endDate = $paramsToSet['date'];
		}
		
		if(is_null($this->site))
		{
			throw new Piwik_Access_NoAccessException("Website not initialized, check that you are logged in and/or using the correct token_auth.");
		}
		$paramDate = self::getDateRangeRelativeToEndDate($period, $range, $endDate, $this->site);
		
		$params = array_merge($paramsToSet , array(	'date' => $paramDate ) );
		return $params;
	}
	
	/**
	 * Given for example, $period = month, $lastN = 'last6', $endDate = '2011-07-01', 
	 * It will return the $date = '2011-01-01,2011-07-01' which is useful to draw graphs for the last N periods
	 * 
	 * @param string $period
	 * @param string $lastN
	 * @param string $endDate
	 * @param Piwik_Site $site
	 */
	static public function getDateRangeRelativeToEndDate($period, $lastN, $endDate, $site )
	{
		$last30Relative = new Piwik_Period_Range($period, $lastN, $site->getTimezone() );
		$last30Relative->setDefaultEndDate(Piwik_Date::factory($endDate));
		$date = $last30Relative->getDateStart()->toString() . "," . $last30Relative->getDateEnd()->toString();
		return $date;
	}
	
	/**
	 * Returns a numeric value from the API.
	 * Works only for API methods that originally returns numeric values (there is no cast here)
	 *
	 * @param string $methodToCall Name of method to call, eg. Referers.getNumberOfDistinctSearchEngines
	 * @return int|float
	 */
	protected function getNumericValue( $methodToCall )
	{
		$requestString = 'method='.$methodToCall.'&format=original';
		$request = new Piwik_API_Request($requestString);
		return $request->process();
	}

	/**
	 * Returns the current URL to use in a img src=X to display a sparkline.
	 * $action must be the name of a Controller method that requests data using the Piwik_ViewDataTable::factory
	 * It will automatically build a sparkline by setting the viewDataTable=sparkline parameter in the URL.
	 * It will also computes automatically the 'date' for the 'last30' days/weeks/etc. 
	 *
	 * @param string $action Method name of the controller to call in the img src
	 * @param array Array of name => value of parameters to set in the generated GET url 
	 * @return string The generated URL
	 */
	protected function getUrlSparkline( $action, $customParameters = array() )
	{
		$params = $this->getGraphParamsModified( 
					array(	'viewDataTable' => 'sparkline', 
							'action' => $action,
							'module' => $this->pluginName)
					+ $customParameters
				);
		// convert array values to comma separated
		foreach($params as &$value)
		{
			if(is_array($value))
			{
				$value = implode(',', $value);
			}
		}
		$url = Piwik_Url::getCurrentQueryStringWithParametersModified($params);
		return $url;
	}
	
	/**
	 * Sets the first date available in the calendar
	 * @param Piwik_Date $minDate
	 * @param Piwik_View $view
	 * @return void
	 */
	protected function setMinDateView(Piwik_Date $minDate, $view)
	{
		$view->minDateYear = $minDate->toString('Y');
		$view->minDateMonth = $minDate->toString('m');
		$view->minDateDay = $minDate->toString('d');
	}
	
	/**
	 * Sets "today" in the calendar. Today does not always mean "UTC" today, eg. for websites in UTC+12.
	 * @param Piwik_Date $maxDate
	 * @param Piwik_View $view
	 * @return void
	 */
	protected function setMaxDateView(Piwik_Date $maxDate, $view)
	{
		$view->maxDateYear = $maxDate->toString('Y');
		$view->maxDateMonth = $maxDate->toString('m');
		$view->maxDateDay = $maxDate->toString('d');
	}
	
	/**
	 * Sets general variables to the view that are used by various templates and Javascript.
	 * If any error happens, displays the login screen
	 * @param Piwik_View $view
	 * @return void
	 */
	protected function setGeneralVariablesView($view)
	{
		$view->date = $this->strDate;
		
		try {
			$view->idSite = $this->idSite;
			if(empty($this->site) || empty($this->idSite))
			{
				throw new Exception("The requested website idSite is not found in the request, or is invalid.
				Please check that you are logged in Piwik and have permission to access the specified website.");
			}
			$this->setPeriodVariablesView($view);
			
			$rawDate = Piwik_Common::getRequestVar('date');
			$periodStr = Piwik_Common::getRequestVar('period');
			if($periodStr != 'range')
			{
				$date = Piwik_Date::factory($this->strDate);
				$period = Piwik_Period::factory($periodStr, $date);
			}
			else
			{
				$period = new Piwik_Period_Range($periodStr, $rawDate, $this->site->getTimezone());
			}
			$view->rawDate = $rawDate;
			$view->prettyDate = $period->getPrettyString();
			$view->siteName = $this->site->getName();
			$view->siteMainUrl = $this->site->getMainUrl();
			
			$datetimeMinDate = $this->site->getCreationDate()->getDatetime();
			$minDate = Piwik_Date::factory($datetimeMinDate, $this->site->getTimezone());
			$this->setMinDateView($minDate, $view);

			$maxDate = Piwik_Date::factory('now', $this->site->getTimezone());
			$this->setMaxDateView($maxDate, $view);
			
			// Setting current period start & end dates, for pre-setting the calendar when "Date Range" is selected 
			$dateStart = $period->getDateStart();
			if($dateStart->isEarlier($minDate)) { $dateStart = $minDate; } 
			$dateEnd = $period->getDateEnd();
			if($dateEnd->isLater($maxDate)) { $dateEnd = $maxDate; }
			
			$view->startDate = $dateStart;
			$view->endDate = $dateEnd;
			
			$this->setBasicVariablesView($view);
		} catch(Exception $e) {
			Piwik_ExitWithMessage($e->getMessage());
		}
	}
	
	/**
	 * Set the minimal variables in the view object
	 * 
	 * @param Piwik_View $view
	 */
	protected function setBasicVariablesView($view)
	{
		$view->topMenu = Piwik_GetTopMenu();
		$view->debugTrackVisitsInsidePiwikUI = Zend_Registry::get('config')->Debug->track_visits_inside_piwik_ui;
		$view->isSuperUser = Zend_Registry::get('access')->isSuperUser();
		$view->isCustomLogo = Zend_Registry::get('config')->branding->use_custom_logo;
		$view->logoHeader = Piwik_API_API::getInstance()->getHeaderLogoUrl();
		$view->logoLarge = Piwik_API_API::getInstance()->getLogoUrl();
		$view->piwikUrl = Piwik::getPiwikUrl();
	}
	
	/**
	 * Sets general period variables (available periods, current period, period labels) used by templates 
	 * @param Piwik_View $view
	 * @return void
	 */
	public static function setPeriodVariablesView($view)
	{
		if(isset($view->period))
		{
			return;
		}
		
		$currentPeriod = Piwik_Common::getRequestVar('period');
		$view->displayUniqueVisitors = Piwik::isUniqueVisitorsEnabled($currentPeriod);
		$availablePeriods = array('day', 'week', 'month', 'year', 'range');
		if(!in_array($currentPeriod,$availablePeriods))
		{
			throw new Exception("Period must be one of: ".implode(",",$availablePeriods));
		}
		$periodNames = array(
			'day' => array('singular' => Piwik_Translate('CoreHome_PeriodDay'), 'plural' => Piwik_Translate('CoreHome_PeriodDays')),
			'week' => array('singular' => Piwik_Translate('CoreHome_PeriodWeek'), 'plural' => Piwik_Translate('CoreHome_PeriodWeeks')),
			'month' => array('singular' => Piwik_Translate('CoreHome_PeriodMonth'), 'plural' => Piwik_Translate('CoreHome_PeriodMonths')),
			'year' => array('singular' => Piwik_Translate('CoreHome_PeriodYear'), 'plural' => Piwik_Translate('CoreHome_PeriodYears')),
			// Note: plural is not used for date range
			'range' => array('singular' => Piwik_Translate('General_DateRangeInPeriodList'), 'plural' => Piwik_Translate('General_DateRangeInPeriodList') ),
		);
		
		$found = array_search($currentPeriod,$availablePeriods);
		if($found !== false)
		{
			unset($availablePeriods[$found]);
		}
		$view->period = $currentPeriod;
		$view->otherPeriods = $availablePeriods;
		$view->periodsNames = $periodNames;
	}
	
	/**
	 * Helper method used to redirect the current http request to another module/action
	 * If specified, will also redirect to a given website, period and /or date
	 * 
	 * @param string $moduleToRedirect Module, eg. "MultiSites"
	 * @param string $actionToRedirect Action, eg. "index"
	 * @param string $websiteId Website ID, eg. 1
	 * @param string $defaultPeriod Default period, eg. "day"
	 * @param string $defaultDate Default date, eg. "today"
	 */
	function redirectToIndex($moduleToRedirect, $actionToRedirect, $websiteId = null, $defaultPeriod = null, $defaultDate = null, $parameters = array())
	{
		if(is_null($websiteId))
		{
			$websiteId = $this->getDefaultWebsiteId();
		}
		if(is_null($defaultDate))
		{
			$defaultDate = $this->getDefaultDate();
		}
		if(is_null($defaultPeriod))
		{
			$defaultPeriod = $this->getDefaultPeriod();
		}
		$parametersString = '';
		if(!empty($parameters))
		{
			$parametersString = '&' . Piwik_Url::getQueryStringFromParameters($parameters);
		}

		if($websiteId) {
			$url = "Location: index.php?module=".$moduleToRedirect
									."&action=".$actionToRedirect
									."&idSite=".$websiteId
									."&period=".$defaultPeriod
									."&date=".$defaultDate
									.$parametersString;
			header($url);
			exit;
		}
		
		if(Piwik::isUserIsSuperUser())
		{
			Piwik_ExitWithMessage("Error: no website was found in this Piwik installation. 
			<br />Check the table '". Piwik_Common::prefixTable('site') ."' that should contain your Piwik websites.", false, true);
		}
		
		$currentLogin = Piwik::getCurrentUserLogin();
		if(!empty($currentLogin)
			&& $currentLogin != 'anonymous')
		{
			$errorMessage = sprintf(Piwik_Translate('CoreHome_NoPrivilegesAskPiwikAdmin'), $currentLogin, "<br/><a href='mailto:".Piwik::getSuperUserEmail()."?subject=Access to Piwik for user $currentLogin'>", "</a>");
			$errorMessage .= "<br /><br />&nbsp;&nbsp;&nbsp;<b><a href='index.php?module=". Zend_Registry::get('auth')->getName() ."&amp;action=logout'>&rsaquo; ". Piwik_Translate('General_Logout'). "</a></b><br />";
			Piwik_ExitWithMessage($errorMessage, false, true);
		}

		Piwik_FrontController::getInstance()->dispatch(Piwik::getLoginPluginName(), false);
		exit;
	}
	

	/**
	 * Returns default website that Piwik should load 
	 * @return Piwik_Site
	 */
	protected function getDefaultWebsiteId()
	{
		$defaultWebsiteId = false;
	
		// User preference: default website ID to load
		$defaultReport = Piwik_UsersManager_API::getInstance()->getUserPreference(Piwik::getCurrentUserLogin(), Piwik_UsersManager_API::PREFERENCE_DEFAULT_REPORT);
		if(is_numeric($defaultReport)) 
		{
			$defaultWebsiteId = $defaultReport;
		}
		
		Piwik_PostEvent( 'Controller.getDefaultWebsiteId', $defaultWebsiteId );
		
		if($defaultWebsiteId) 
		{
			return $defaultWebsiteId;
		}
		
		$sitesId = Piwik_SitesManager_API::getInstance()->getSitesIdWithAtLeastViewAccess();
		if(!empty($sitesId))
		{
			return $sitesId[0];
		}
		return false;
	}

	/**
	 * Returns default date for Piwik reports
	 * @return string today, 2010-01-01, etc.
	 */
	protected function getDefaultDate()
	{
		// NOTE: a change in this function might mean a change in plugins/UsersManager/templates/userSettings.js as well
		$userSettingsDate = Piwik_UsersManager_API::getInstance()->getUserPreference(Piwik::getCurrentUserLogin(), Piwik_UsersManager_API::PREFERENCE_DEFAULT_REPORT_DATE);
		if($userSettingsDate === false)
		{
			return Zend_Registry::get('config')->General->default_day;
		}
		if($userSettingsDate == 'yesterday')
		{
			return $userSettingsDate;
		}
		// if last7, last30, etc.
		if(strpos($userSettingsDate, 'last') === 0
			|| strpos($userSettingsDate, 'previous') === 0)
		{
			return $userSettingsDate;
		}
		return 'today';
	}
	
	/**
	 * Returns default date for Piwik reports
	 * @return string today, 2010-01-01, etc.
	 */
	protected function getDefaultPeriod()
	{
		$userSettingsDate = Piwik_UsersManager_API::getInstance()->getUserPreference(Piwik::getCurrentUserLogin(), Piwik_UsersManager_API::PREFERENCE_DEFAULT_REPORT_DATE);
		if($userSettingsDate === false)
		{
			return Zend_Registry::get('config')->General->default_period;
		}
		if(in_array($userSettingsDate, array('today','yesterday')))
		{
			return 'day';
		}
		if(strpos($userSettingsDate, 'last') === 0
			|| strpos($userSettingsDate, 'previous') === 0)
		{
			return 'range';
		}
		return $userSettingsDate;
	}
	
	/**
	 * Checks that the specified token matches the current logged in user token.
	 * Note: this protection against CSRF should be limited to controller
	 * actions that are either invoked via AJAX or redirect to a page
	 * within the site.  The token should never appear in the browser's
	 * address bar.
	 * 
	 * @return throws exception if token doesn't match
	 */
	protected function checkTokenInUrl()
	{
		if(Piwik_Common::getRequestVar('token_auth', false) != Piwik::getCurrentUserTokenAuth()) {
			throw new Piwik_Access_NoAccessException(Piwik_TranslateException('General_ExceptionInvalidToken'));
		}
	}
}

/**
 * Parent class of all plugins Controllers with admin functions
 * 
 * @package Piwik
 */
abstract class Piwik_Controller_Admin extends Piwik_Controller
{
	/**
	 * Used by Admin screens
	 * 
	 * @param Piwik_View $view
	 */
	protected function setBasicVariablesView($view)
	{
		parent::setBasicVariablesView($view);

		$view->currentAdminMenuName = Piwik_GetCurrentAdminMenuName();

		$view->enableFrames = Zend_Registry::get('config')->General->enable_framed_settings;
		if(!$view->enableFrames)
		{
			$view->setXFrameOptions('sameorigin');
		}
	}
}
