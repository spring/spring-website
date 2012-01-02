<?php
/**
 * Piwik - Open source web analytics
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id: Visit.php 5266 2011-10-04 10:38:36Z matt $
 *
 * @category Piwik
 * @package Piwik
 */

/**
 * @package Piwik
 * @subpackage Piwik_Tracker
 */
interface Piwik_Tracker_Visit_Interface {
	function setRequest($requestArray);
	function handle();
}

/**
 * Class used to handle a Visit.
 * A visit is either NEW or KNOWN.
 * - If a visit is NEW then we process the visitor information (settings, referers, etc.) and save
 * a new line in the log_visit table.
 * - If a visit is KNOWN then we update the visit row in the log_visit table, updating the number of pages
 * views, time spent, etc.
 *
 * Whether a visit is NEW or KNOWN we also save the action in the DB.
 * One request to the piwik.php script is associated to one action.
 *
 * @package Piwik
 * @subpackage Piwik_Tracker
 */
class Piwik_Tracker_Visit implements Piwik_Tracker_Visit_Interface
{
	/**
	 * @var Piwik_Cookie
	 */
	protected $cookie = null;
	protected $visitorInfo = array();
	protected $userSettingsInformation = null;
	protected $idsite;
	protected $visitorKnown;
	protected $request;

	// can be overwritten in constructor
	protected $timestamp;
	protected $ip;
	// via setForcedVisitorId()
	protected $forcedVisitorId;
	
	// Set to true when we set some custom variables from the cookie
	protected $customVariablesSetFromRequest = false;
	
	/**
	 * @var Piwik_Tracker_GoalManager
	 */
	protected $goalManager;

	const TIME_IN_PAST_TO_SEARCH_FOR_VISITOR = 86400;

	public function __construct($forcedIpString = null, $forcedDateTime = null)
	{
		$this->timestamp = time();
		if(!empty($forcedDateTime))
		{
			if(!is_numeric($forcedDateTime))
			{
				$forcedDateTime = strtotime($forcedDateTime);
			}
			$this->timestamp = $forcedDateTime;
		}
		$ipString = $forcedIpString;
		if(empty($ipString))
		{
			$ipString = Piwik_IP::getIpFromHeader();
		}

		$ip = Piwik_IP::P2N($ipString);
		Piwik_PostEvent('Tracker.Visit.setVisitorIp', $ip);
		$this->ip = $ip;
	}

	function setForcedVisitorId($visitorId)
	{
		$this->forcedVisitorId = $visitorId;
	}

	function setRequest($requestArray)
	{
		$this->request = $requestArray;

		$idsite = Piwik_Common::getRequestVar('idsite', 0, 'int', $this->request);
		Piwik_PostEvent('Tracker.setRequest.idSite', $idsite, $requestArray);
		if($idsite <= 0)
		{
			Piwik_Tracker_ExitWithException(new Exception('Invalid idSite'));
		}
		$this->idsite = $idsite;

		// When the 'url' and referer url parameter are not given, we might be in the 'Simple Image Tracker' mode.
		// The URL can default to the Referer, which will be in this case
		// the URL of the page containing the Simple Image beacon
		if(empty($this->request['urlref'])
			&& empty($this->request['url']))
		{
			$this->request['url'] = @$_SERVER['HTTP_REFERER'];
		}
	}

	/**
	 *	Main algorithm to handle the visit.
	 *
	 *  Once we have the visitor information, we have to determine if the visit is a new or a known visit.
	 *
	 * 1) When the last action was done more than 30min ago,
	 * 	  or if the visitor is new, then this is a new visit.
	 *
	 * 2) If the last action is less than 30min ago, then the same visit is going on.
	 *	Because the visit goes on, we can get the time spent during the last action.
	 *
	 * NB:
	 *  - In the case of a new visit, then the time spent
	 *	during the last action of the previous visit is unknown.
	 *
	 *	- In the case of a new visit but with a known visitor,
	 *	we can set the 'returning visitor' flag.
	 *
	 * In all the cases we set a cookie to the visitor with the new information.
	 */
	public function handle()
	{
		// the IP is needed by isExcluded() and GoalManager->recordGoals()
		$this->visitorInfo['location_ip'] = $this->ip;

		if($this->isExcluded())
		{
			return;
		}
		$this->visitorCustomVariables = self::getCustomVariables($scope = 'visit', $this->request);
		if(!empty($this->visitorCustomVariables))
		{
			$this->customVariablesSetFromRequest = true;
		}
	
		$this->goalManager = new Piwik_Tracker_GoalManager();
		
		$someGoalsConverted = $visitIsConverted = false;
		$idActionUrl = $idActionName = false;
		$action = null;

		$this->goalManager->init($this->request);
		
		$requestIsManualGoalConversion = ($this->goalManager->idGoal > 0);
		$requestIsEcommerce = $this->goalManager->requestIsEcommerce;
		if($requestIsEcommerce)
		{
			$someGoalsConverted = true;
			
			// Mark the visit as Converted only if it is an order (not for a Cart update)
			if($this->goalManager->isGoalAnOrder)
			{
				$visitIsConverted = true;
			}
		}
		// this request is from the JS call to piwikTracker.trackGoal()
		elseif($requestIsManualGoalConversion)
		{
			$someGoalsConverted = $this->goalManager->detectGoalId($this->idsite);
			$visitIsConverted = $someGoalsConverted;
			// if we find a idgoal in the URL, but then the goal is not valid, this is most likely a fake request
			if(!$someGoalsConverted)
			{
				printDebug('Invalid goal tracking request for goal id = '.$this->goalManager->idGoal);
				unset($this->goalManager);
				return;
			}
		}
		// normal page view, potentially triggering a URL matching goal
		else
		{
			$action = $this->newAction();
			$this->handleAction($action);
			$someGoalsConverted = $this->goalManager->detectGoalsMatchingUrl($this->idsite, $action);
			$visitIsConverted = $someGoalsConverted;
			
			$action->loadIdActionNameAndUrl();
			$idActionUrl = (int)$action->getIdActionUrl();
			$idActionName = (int)$action->getIdActionName();
		}

		// the visitor and session
		$this->recognizeTheVisitor();

		$isLastActionInTheSameVisit = $this->isLastActionInTheSameVisit();

		// Known visit when:
		// ( - the visitor has the Piwik cookie with the idcookie ID used by Piwik to match the visitor
		//   OR
		//   - the visitor doesn't have the Piwik cookie but could be match using heuristics @see recognizeTheVisitor()
		// )
		// AND
		// - the last page view for this visitor was less than 30 minutes ago @see isLastActionInTheSameVisit()
		if( $this->isVisitorKnown()
			&& $isLastActionInTheSameVisit)
		{
			$idRefererActionUrl = $this->visitorInfo['visit_exit_idaction_url'];
			$idRefererActionName = $this->visitorInfo['visit_exit_idaction_name'];
			try {
				$this->handleKnownVisit($idActionUrl, $idActionName, $visitIsConverted);
				if(!is_null($action))
				{
					$action->record( 	$this->visitorInfo['idvisit'],
										$this->visitorInfo['idvisitor'],
										$idRefererActionUrl,
										$idRefererActionName,
										$this->visitorInfo['time_spent_ref_action']
								);
				}
			} catch(Piwik_Tracker_Visit_VisitorNotFoundInDatabase $e) {

				// There is an edge case when:
				// - two manual goal conversions happen in the same second
				// - which result in handleKnownVisit throwing the exception
				//   because the UPDATE didn't affect any rows (one row was found, but not updated since no field changed)
				// - the exception is caught here and will result in a new visit incorrectly
				// In this case, we cancel the current conversion to be recorded:
				if($requestIsManualGoalConversion
					|| $requestIsEcommerce)
				{
					$someGoalsConverted = $visitIsConverted = false;
				}
				// When the row wasn't found in the logs, and this is a pageview or
				// goal matching URL, we force a new visitor
				else
				{
					$this->visitorKnown = false;
				}
			}
		}

		// New visit when:
		// - the visitor has the Piwik cookie but the last action was performed more than 30 min ago @see isLastActionInTheSameVisit()
		// - the visitor doesn't have the Piwik cookie, and couldn't be matched in @see recognizeTheVisitor()
		// - the visitor does have the Piwik cookie but the idcookie and idvisit found in the cookie didn't match to any existing visit in the DB
		if(!$this->isVisitorKnown()
			|| !$isLastActionInTheSameVisit)
		{
			$this->handleNewVisit($idActionUrl, $idActionName, $visitIsConverted);
			if(!is_null($action))
			{
				$action->record( $this->visitorInfo['idvisit'], $this->visitorInfo['idvisitor'], 0, 0, 0 );
			}
		}

		// update the cookie with the new visit information
		$this->setThirdPartyCookie();

		// record the goals if applicable
		if($someGoalsConverted)
		{
			$refererTimestamp = Piwik_Common::getRequestVar('_refts', 0, 'int', $this->request);
			$refererUrl = Piwik_Common::getRequestVar('_ref', '', 'string', $this->request);
			$refererCampaignName = Piwik_Common::getRequestVar('_rcn', '', 'string', $this->request);
			$refererCampaignKeyword = Piwik_Common::getRequestVar('_rck', '', 'string', $this->request);

			$this->goalManager->recordGoals( 
										$this->idsite,
										$this->visitorInfo,
										$this->visitorCustomVariables,
										$action,
										$refererTimestamp,
										$refererUrl,
										$refererCampaignName,
										$refererCampaignKeyword
			);
		}
		unset($this->goalManager);
		unset($action);
		$this->printCookie();
	}

	protected function printCookie()
	{
		printDebug($this->cookie);
	}

	protected function handleAction($action)
	{
		$action->setIdSite($this->idsite);
		$action->setRequest($this->request);
		$action->setTimestamp($this->getCurrentTimestamp());
		$action->init();
		
		if($this->detectActionIsOutlinkOnAliasHost($action))
		{
			printDebug("Info: The outlink URL host is one of the known host for this website. ");
		}
		if(isset($GLOBALS['PIWIK_TRACKER_DEBUG']) && $GLOBALS['PIWIK_TRACKER_DEBUG'])
		{
			$type = Piwik_Tracker_Action::getActionTypeName($action->getActionType());
			printDebug("Action is a $type,
						Action name =  ". $action->getActionName() . ",
						Action URL = ". $action->getActionUrl() );
		}
	}

	/**
	 * In the case of a known visit, we have to do the following actions:
	 *
	 * 1) Insert the new action
	 * 2) Update the visit information
	 *
	 * This method triggers two events:
	 *
	 * Tracker.knownVisitorUpdate is triggered before the visit information is updated
	 * Event data is an array with the values to be updated (could be changed by plugins)
	 *
	 * Tracker.knownVisitorInformation is triggered after saving the new visit data
	 * Even data is an array with updated information about the visit
	 */
	protected function handleKnownVisit($idActionUrl, $idActionName, $visitIsConverted)
	{
		// gather information that needs to be updated
		$valuesToUpdate = array();

		$sqlActionUpdate = '';
		if($idActionUrl !== false)
		{
			$valuesToUpdate['visit_exit_idaction_url'] = $idActionUrl;
			$sqlActionUpdate = "visit_total_actions = visit_total_actions + 1, ";
			$valuesToUpdate['visit_exit_idaction_name'] = (int)$idActionName;
		}

		$datetimeServer = Piwik_Tracker::getDatetimeFromTimestamp($this->getCurrentTimestamp());
		printDebug("Visit is known (IP = ".Piwik_IP::N2P($this->getVisitorIp()).")");

		$visitTotalTime = $this->getCurrentTimestamp() - $this->visitorInfo['visit_first_action_time'];
		$valuesToUpdate['visit_last_action_time'] = $datetimeServer;
		$valuesToUpdate['visit_total_time'] = $visitTotalTime + 1;
	
		// Goal conversion
		if($visitIsConverted)
		{
			$valuesToUpdate['visit_goal_converted'] = 1;
			// If a pageview and goal conversion in the same second, with previously a goal conversion recorded
			// the request would not "update" the row since all values are the same as previous
			// therefore the request below throws exception, instead we make sure the UPDATE will affect the row
			$valuesToUpdate['visit_total_time'] += (int)$this->goalManager->idGoal
														// +2 to offset idgoal=-1 and idgoal=0 
														+ 2 ;
		}
		
		// Update the idvisitor to the latest known value, in case the cookie value changed for some reasons,
		// safer to always rely on the most recent values
		if($this->shouldUseThirdPartyCookie())
		{
			$idVisitor = $this->cookie->get(0);
		}
		else
		{
			$idVisitor = Piwik_Common::getRequestVar('_id', '', 'string', $this->request);
		}
		if(strlen($idVisitor) == Piwik_Tracker::LENGTH_HEX_ID_STRING)
		{
			$valuesToUpdate['idvisitor'] = Piwik_Common::hex2bin($idVisitor);
		}

		// Ecommerce buyer status
		$valuesToUpdate['visit_goal_buyer'] = $this->goalManager->getBuyerType($this->visitorInfo['visit_goal_buyer']);
		
		// Custom Variables overwrite previous values on each page view
		$valuesToUpdate = array_merge($valuesToUpdate, $this->visitorCustomVariables);

		// trigger event before update
		Piwik_PostEvent('Tracker.knownVisitorUpdate', $valuesToUpdate);

		// Will be updated in cookie
		$timeSpentRefererAction = $this->getCurrentTimestamp() - $this->visitorInfo['visit_last_action_time'];
		if($timeSpentRefererAction > Piwik_Tracker_Config::getInstance()->Tracker['visit_standard_length'])
		{
			$timeSpentRefererAction = 0;
		}
		$this->visitorInfo['time_spent_ref_action'] = $timeSpentRefererAction;

		// update visitorInfo
		foreach($valuesToUpdate AS $name => $value)
		{
			$this->visitorInfo[$name] = $value;
		}

		// build sql query
		$updateParts = $sqlBind = array();

		foreach($valuesToUpdate AS $name => $value)
		{
			$updateParts[] = $name." = ?";
			$sqlBind[] = $value;
		}
		$sqlQuery = "UPDATE ". Piwik_Common::prefixTable('log_visit')."
						SET $sqlActionUpdate ".implode($updateParts, ', ')."
						WHERE idsite = ?
							AND idvisit = ?";
		array_push($sqlBind, $this->idsite, (int)$this->visitorInfo['idvisit']  );

		$result = Piwik_Tracker::getDatabase()->query($sqlQuery, $sqlBind);

		$this->visitorInfo['visit_last_action_time'] = $this->getCurrentTimestamp();

		// Debug output
		if(isset($valuesToUpdate['idvisitor']))
		{
			$valuesToUpdate['idvisitor'] = bin2hex($valuesToUpdate['idvisitor']);
		}
		printDebug('Updating existing visit: '. var_export($valuesToUpdate, true) );

		if(Piwik_Tracker::getDatabase()->rowCount($result) == 0)
		{
			printDebug("Visitor with this idvisit wasn't found in the DB.");
			printDebug("$sqlQuery --- ");printDebug($sqlBind);
			throw new Piwik_Tracker_Visit_VisitorNotFoundInDatabase(
						"The visitor with idvisitor=".bin2hex($this->visitorInfo['idvisitor'])." and idvisit=".$this->visitorInfo['idvisit']
						." wasn't found in the DB, we fallback to a new visitor");
		}

		Piwik_PostEvent('Tracker.knownVisitorInformation', $this->visitorInfo);
	}

	protected function isTimestampValid($time)
	{
		return $time <= $this->getCurrentTimestamp()
			&& $time > $this->getCurrentTimestamp() - 10*365*86400;
	}

	/**
	 * In the case of a new visit, we have to do the following actions:
	 *
	 * 1) Insert the new action
	 *
	 * 2) Insert the visit information
	 */
	protected function handleNewVisit($idActionUrl, $idActionName, $visitIsConverted)
	{
		printDebug("New Visit (IP = ".Piwik_IP::N2P($this->getVisitorIp()).")");

		$localTimes = array(
			'h' => (string) Piwik_Common::getRequestVar( 'h', $this->getCurrentDate("H"), 'int', $this->request),
			'i' => (string) Piwik_Common::getRequestVar( 'm', $this->getCurrentDate("i"), 'int', $this->request),
			's' => (string) Piwik_Common::getRequestVar( 's', $this->getCurrentDate("s"), 'int', $this->request)
		);
		foreach($localTimes as $k => $time)
		{
			if(strlen($time) == 1)
			{
				$localTimes[$k] = '0' . $time;
			}
		}
		$localTime = $localTimes['h'] .':'. $localTimes['i'] .':'. $localTimes['s'];

		$idcookie = $this->getVisitorIdcookie();

		$defaultTimeOnePageVisit = Piwik_Tracker_Config::getInstance()->Tracker['default_time_one_page_visit'];

		// Days since first visit
		$cookieFirstVisitTimestamp = Piwik_Common::getRequestVar('_idts', 0, 'int', $this->request);
		if(!$this->isTimestampValid($cookieFirstVisitTimestamp))
		{
			$cookieFirstVisitTimestamp = $this->getCurrentTimestamp();
		}
		$daysSinceFirstVisit = round(($this->getCurrentTimestamp() - $cookieFirstVisitTimestamp)/86400, $precision = 0);
		if($daysSinceFirstVisit < 0) $daysSinceFirstVisit = 0;

		// Number of Visits
		$visitCount = Piwik_Common::getRequestVar('_idvc', 1, 'int', $this->request);
		if($visitCount < 1) $visitCount = 1;

		// Days since last visit
		$daysSinceLastVisit = 0;
		$lastVisitTimestamp = Piwik_Common::getRequestVar('_viewts', 0, 'int', $this->request);
		if($this->isTimestampValid($lastVisitTimestamp))
		{
			$daysSinceLastVisit = round(($this->getCurrentTimestamp() - $lastVisitTimestamp)/86400, $precision = 0);
			if($daysSinceLastVisit < 0) $daysSinceLastVisit = 0;
		}
		
		$daysSinceLastOrder = 0;
		$isReturningCustomer = false;
		$lastOrderTimestamp = Piwik_Common::getRequestVar('_ects', 0, 'int', $this->request);
		if($this->isTimestampValid($lastOrderTimestamp))
		{
			$daysSinceLastOrder = round(($this->getCurrentTimestamp() - $lastOrderTimestamp)/86400, $precision = 0);
			if($daysSinceLastOrder < 0)
			{
				$daysSinceLastOrder = 0;
			} 
			$isReturningCustomer = true;
		}

		// User settings
		$userInfo = $this->getUserSettingsInformation();
		$country = Piwik_Common::getCountry($userInfo['location_browser_lang'],
											$enableLanguageToCountryGuess = Piwik_Tracker_Config::getInstance()->Tracker['enable_language_to_country_guess'],
											$this->getVisitorIp());

		// Referrer data
		$referrer = new Piwik_Tracker_Visit_Referer();
		$refererUrl	= Piwik_Common::getRequestVar( 'urlref', '', 'string', $this->request);
		$currentUrl	= Piwik_Common::getRequestVar( 'url', '', 'string', $this->request);
		$refererInfo = $referrer->getRefererInformation($refererUrl, $currentUrl, $this->idsite);

		/**
		 * Save the visitor
		 */
		$this->visitorInfo = array(
			'idsite' 					=> $this->idsite,
			'visitor_localtime' 		=> $localTime,
			'idvisitor' 				=> $idcookie,
			'visitor_returning' 		=> $isReturningCustomer ? 2 : ($visitCount > 1 || $this->isVisitorKnown() ? 1 : 0),
			'visitor_count_visits'		=> $visitCount,
			'visitor_days_since_last'	=> $daysSinceLastVisit,
			'visitor_days_since_order'	=> $daysSinceLastOrder,
			'visitor_days_since_first' 	=> $daysSinceFirstVisit,
			'visit_first_action_time' 	=> Piwik_Tracker::getDatetimeFromTimestamp($this->getCurrentTimestamp()),
			'visit_last_action_time' 	=> Piwik_Tracker::getDatetimeFromTimestamp($this->getCurrentTimestamp()),
			'visit_entry_idaction_url' 	=> (int)$idActionUrl,
			'visit_entry_idaction_name' => (int)$idActionName,
			'visit_exit_idaction_url' 	=> (int)$idActionUrl,
			'visit_exit_idaction_name' 	=> (int)$idActionName,
			'visit_total_actions' 		=> 1,
			'visit_total_time' 			=> $defaultTimeOnePageVisit,
			'visit_goal_converted'  	=> $visitIsConverted ? 1: 0,
			'visit_goal_buyer'			=> $this->goalManager->getBuyerType(),
			'referer_type' 				=> $refererInfo['referer_type'],
			'referer_name' 				=> $refererInfo['referer_name'],
			'referer_url' 				=> $refererInfo['referer_url'],
			'referer_keyword' 			=> $refererInfo['referer_keyword'],
			'config_id' 				=> $userInfo['config_id'],
			'config_os' 				=> $userInfo['config_os'],
			'config_browser_name' 		=> $userInfo['config_browser_name'],
			'config_browser_version' 	=> $userInfo['config_browser_version'],
			'config_resolution' 		=> $userInfo['config_resolution'],
			'config_pdf' 				=> $userInfo['config_pdf'],
			'config_flash' 				=> $userInfo['config_flash'],
			'config_java' 				=> $userInfo['config_java'],
			'config_director' 			=> $userInfo['config_director'],
			'config_quicktime'			=> $userInfo['config_quicktime'],
			'config_realplayer' 		=> $userInfo['config_realplayer'],
			'config_windowsmedia' 		=> $userInfo['config_windowsmedia'],
			'config_gears'	 			=> $userInfo['config_gears'],
			'config_silverlight'		=> $userInfo['config_silverlight'],
			'config_cookie' 			=> $userInfo['config_cookie'],
			'location_ip' 				=> $this->getVisitorIp(),
			'location_browser_lang'		=> $userInfo['location_browser_lang'],
			'location_country' 			=> $country,
		);

		// Add Custom variable key,value to the visitor array
		$this->visitorInfo = array_merge($this->visitorInfo, $this->visitorCustomVariables);

		Piwik_PostEvent('Tracker.newVisitorInformation', $this->visitorInfo);

		$debugVisitInfo = $this->visitorInfo;
		$debugVisitInfo['idvisitor'] = bin2hex($debugVisitInfo['idvisitor']);
		$debugVisitInfo['config_id'] = bin2hex($debugVisitInfo['config_id']);
		printDebug($debugVisitInfo);

		$this->saveVisitorInformation();
	}

	/**
	 * Save new visitor information to log_visit table.
	 * Provides pre- and post- event hooks (Tracker.saveVisitorInformation and Tracker.saveVisitorInformation.end) for plugins
	 */
	protected function saveVisitorInformation()
	{
		Piwik_PostEvent('Tracker.saveVisitorInformation', $this->visitorInfo);

		if(empty($this->visitorInfo['location_country']))
		{
			$this->visitorInfo['location_country'] = 'xx';
		}
		$this->visitorInfo['location_continent'] = Piwik_Common::getContinent( $this->visitorInfo['location_country'] );
		$this->visitorInfo['location_browser_lang'] = substr($this->visitorInfo['location_browser_lang'], 0, 20);
		$this->visitorInfo['referer_name'] = substr($this->visitorInfo['referer_name'], 0, 70);
		$this->visitorInfo['referer_keyword'] = substr($this->visitorInfo['referer_keyword'], 0, 255);
		$this->visitorInfo['config_resolution'] = substr($this->visitorInfo['config_resolution'], 0, 9);

		$fields = implode(", ", array_keys($this->visitorInfo));
		$values = Piwik_Common::getSqlStringFieldsArray($this->visitorInfo);

		$sql = "INSERT INTO ".Piwik_Common::prefixTable('log_visit'). " ($fields) VALUES ($values)";
		$bind = array_values($this->visitorInfo);
		Piwik_Tracker::getDatabase()->query( $sql, $bind);

		$idVisit = Piwik_Tracker::getDatabase()->lastInsertId();
		$this->visitorInfo['idvisit'] = $idVisit;

		$this->visitorInfo['visit_first_action_time'] = $this->getCurrentTimestamp();
		$this->visitorInfo['visit_last_action_time'] = $this->getCurrentTimestamp();

		Piwik_PostEvent('Tracker.saveVisitorInformation.end', $this->visitorInfo);
	}

	/**
	 *  Returns visitor cookie
	 *
	 *  @return binary
	 */
	protected function getVisitorIdcookie()
	{
		if($this->isVisitorKnown())
		{
			return $this->visitorInfo['idvisitor'];
		}
		// If the visitor had a first party ID cookie, then we use this value
		if(!empty($this->visitorInfo['idvisitor'])
			&& strlen($this->visitorInfo['idvisitor']) == Piwik_Tracker::LENGTH_BINARY_ID)
		{
			return $this->visitorInfo['idvisitor'];
		}

		// Return Random UUID
		$uniqueId = substr($this->getVisitorUniqueId(), 0, Piwik_Tracker::LENGTH_HEX_ID_STRING);
		return Piwik_Common::hex2bin($uniqueId);
	}

	/**
	 * Returns the visitor's IP address
	 *
	 * @return long
	 */
	protected function getVisitorIp()
	{
		return $this->visitorInfo['location_ip'];
	}

	/**
	 * Returns the visitor's browser (user agent)
	 *
	 * @return string
	 */
	protected function getUserAgent()
	{
		return @$_SERVER['HTTP_USER_AGENT'];
	}

	/**
	 * Returns the current date in the "Y-m-d" PHP format
	 *
	 * @return string
	 */
	protected function getCurrentDate( $format = "Y-m-d")
	{
		return date($format, $this->getCurrentTimestamp() );
	}

	/**
	 * Returns the current Timestamp
	 *
	 * @return int
	 */
	protected function getCurrentTimestamp()
	{
		return $this->timestamp;
	}

	/**
	 * Test if the current visitor is excluded from the statistics.
	 *
	 * Plugins can for example exclude visitors based on the
	 * - IP
	 * - If a given cookie is found
	 *
	 * @return bool True if the visit must not be saved, false otherwise
	 */
	protected function isExcluded()
	{
		$excluded = false;

		$ip = $this->getVisitorIp();
		$ua = $this->getUserAgent();

		/*
		 * Live/Bing/MSN bot and Googlebot are evolving to detect cloaked websites.
		 * As a result, these sophisticated bots exhibit characteristics of
		 * browsers (cookies enabled, executing JavaScript, etc).
		 */
		if ( strpos($ua, 'Googlebot') !== false					// Googlebot
				|| strpos($ua, 'Google Web Preview') !== false	// Google Instant
				|| strpos($ua, 'bingbot') !== false				// Bingbot
				|| strpos($ua, 'YottaaMonitor') !== false		// Yottaa
				|| Piwik_IP::isIpInRange($ip,
					array(
						'64.4.0.0/18',
						'65.52.0.0/14',
						'157.54.0.0/15',
						'157.56.0.0/14',
						'157.60.0.0/16',
						'207.46.0.0/16',
						'207.68.128.0/18',
						'207.68.192.0/20',
					))) // Live/Bing/MSN
		{
			printDebug('Search bot detected, visit excluded');
			$excluded = true;
		}

		/*
		 * Requests built with piwik.js will contain a rec=1 parameter. This is used as
		 * an indication that the request is made by a JS enabled device. By default, Piwik
		 * doesn't track non-JS visitors.
		 */
		if(!$excluded)
		{
			$parameterForceRecord = 'rec';
			$toRecord = Piwik_Common::getRequestVar($parameterForceRecord, false, 'int', $this->request);
			if(!$toRecord)
			{
				printDebug($_SERVER['REQUEST_METHOD'].' parameter '.$parameterForceRecord.' not found in URL, request excluded');
				$excluded = true;
			}
		}

		/* custom filters can override the built-in filters above */
		Piwik_PostEvent('Tracker.Visit.isExcluded', $excluded);

		/*
		 * Following exclude operations happen after the hook.
		 * These are of higher priority and should not be overwritten by plugins.
		 */

		// Checking if the Piwik ignore cookie is set
		if(!$excluded)
		{
			$excluded = $this->isIgnoreCookieFound();
		}

		// Checking for excluded IPs
		if(!$excluded)
		{
			$excluded = $this->isVisitorIpExcluded($ip);
		}

		if($excluded)
		{
			printDebug("Visitor excluded.");
			return true;
		}

		return false;
	}

	/**
	 * Looks for the ignore cookie that users can set in the Piwik admin screen.
	 * @return bool
	 */
	protected function isIgnoreCookieFound()
	{
		if(Piwik_Tracker_IgnoreCookie::isIgnoreCookieFound())
		{
			printDebug('Piwik ignore cookie was found, visit not tracked.');
			return true;
		}
		return false;
	}

	/**
	 * Checks if the visitor ip is in the excluded list
	 *
	 * @param string $ip Long IP
	 * @return bool
	 */
	protected function isVisitorIpExcluded($ip)
	{
		$websiteAttributes = Piwik_Common::getCacheWebsiteAttributes( $this->idsite );
		if(!empty($websiteAttributes['excluded_ips']))
		{
			if(Piwik_IP::isIpInRange($ip, $websiteAttributes['excluded_ips']))
			{
				printDebug('Visitor IP '.Piwik_IP::N2P($ip).' is excluded from being tracked');
				return true;
			}
		}
		return false;
	}

	/**
	 * Returns the cookie name used for the Piwik Tracker cookie
	 *
	 * @return string
	 */
	protected function getCookieName()
	{
		return Piwik_Tracker_Config::getInstance()->Tracker['cookie_name'];
	}

	/**
	 * Returns the cookie expiration date.
	 *
	 * @return int
	 */
	protected function getCookieExpire()
	{
		return $this->getCurrentTimestamp() + Piwik_Tracker_Config::getInstance()->Tracker['cookie_expire'];
	}

	/**
	 * Returns cookie path
	 *
	 * @return string
	 */
	protected function getCookiePath()
	{
		return Piwik_Tracker_Config::getInstance()->Tracker['cookie_path'];
	}

	protected function shouldUseThirdPartyCookie()
	{
		return (bool)Piwik_Tracker_Config::getInstance()->Tracker['use_third_party_id_cookie'];
	}

	/**
	 * This methods tries to see if the visitor has visited the website before.
	 *
	 * We have to split the visitor into one of the category
	 * - Known visitor
	 * - New visitor
	 *
	 * A known visitor is a visitor that has already visited the website in the current month.
	 * We define a known visitor using the algorithm:
	 *
	 * 1) Checking if a cookie contains
	 * 		// a unique id for the visitor
	 * 		- id_visitor
	 *
	 * 		// the timestamp of the last action in the most recent visit
	 * 		- timestamp_last_action
	 *
 	 *  	// the timestamp of the first action in the most recent visit
	 * 		- timestamp_first_action
	 *
	 * 		// the ID of the most recent visit (which could be in the past or the current visit)
	 * 		- id_visit
	 *
	 * 		// the ID of the most recent action
	 * 		- id_last_action
	 *
	 * 2) If the visitor doesn't have a cookie, we try to look for a similar visitor configuration.
	 * 	  We search for a visitor with the same plugins/OS/Browser/Resolution for today for this website.
	 */
	protected function recognizeTheVisitor()
	{
		$this->visitorKnown = false;
		$this->setCookie( new Piwik_Cookie(
								$this->getCookieName(),
								$this->getCookieExpire(),
								$this->getCookiePath()) );

		$this->printCookie();

		$found = $forcedVisitorId = false;

		// Was a Visitor ID "forced" (@see Tracking API setVisitorId()) for this request?
		$idVisitor = $this->forcedVisitorId;
		if(!empty($idVisitor))
		{
			if(strlen($idVisitor) != Piwik_Tracker::LENGTH_HEX_ID_STRING)
			{
				throw new Exception("Visitor ID (cid) must be ".Piwik_Tracker::LENGTH_HEX_ID_STRING." characters long");
			}
			printDebug("Request will be forced to record for this idvisitor = ".$idVisitor);
			$forcedVisitorId = true;
			$found = true;
		}

		if(!$found)
		{
			// - If set to use 3rd party cookies for Visit ID, read the cookies
			// - By default, reads the first party cookie ID
			$useThirdPartyCookie = $this->shouldUseThirdPartyCookie();
			if($useThirdPartyCookie)
			{
				$idVisitor = $this->cookie->get(0);
				if($idVisitor !== false
					&& strlen($idVisitor) == Piwik_Tracker::LENGTH_HEX_ID_STRING)
				{
					$found = true;
				}
			}
		}
		// If a third party cookie was not found, we default to the first party cookie
		if(!$found)
		{
			$idVisitor = Piwik_Common::getRequestVar('_id', '', 'string', $this->request);
			$found = strlen($idVisitor) >= Piwik_Tracker::LENGTH_HEX_ID_STRING;
		}

		// Does the cookie contain a Visitor ID?
		if( $found )
		{
			$this->visitorInfo['idvisitor'] = Piwik_Common::hex2bin($idVisitor);
			printDebug("The visitor has the piwik cookie (idvisitor = ".$idVisitor.") ");
		}
		else
		{
			printDebug("Visitor doesn't have the piwik cookie.");
		}

		$userInfo = $this->getUserSettingsInformation();
		$configId = $userInfo['config_id'];
		$timeLookBack = date('Y-m-d H:i:s', $this->getCurrentTimestamp() - self::TIME_IN_PAST_TO_SEARCH_FOR_VISITOR);

		$where = "visit_last_action_time >= ?
					AND idsite = ?";
		$bindSql = array( $timeLookBack, $this->idsite);

		$forcedVisitorId = $forcedVisitorId || Piwik_Tracker_Config::getInstance()->Tracker['trust_visitors_cookies'];
		
		// we always match on the config_id, except if the current request forces the visitor id
		if(!$forcedVisitorId)
		{
			$where .= ' AND config_id = ? ';
			$bindSql[] = $configId;
		}
		// We force to match a visitor ID
		// 1) If the visitor cookies should be trusted (ie. intranet) - config file setting
		// 2) or if the Visitor ID was forced via the Tracking API setVisitorId()
		else if(!empty($this->visitorInfo['idvisitor']))
		{
			printDebug("Matching the visitor based on his idcookie: ".bin2hex($this->visitorInfo['idvisitor']) ."...");

			$where .= ' AND idvisitor = ?';
			$bindSql[] = $this->visitorInfo['idvisitor'];
		}
		else
		{
			// Forced idvisitor, but empty idvisitor  
			return;
		}

		$selectCustomVariables = '';
		// No custom var were found in the request, so let's copy the previous one in a potential conversion later
		if(!$this->customVariablesSetFromRequest)
		{
			$selectCustomVariables = '
				, custom_var_k1, custom_var_v1,
				custom_var_k2, custom_var_v2,
				custom_var_k3, custom_var_v3,
				custom_var_k4, custom_var_v4,
				custom_var_k5, custom_var_v5';
		}
		
		$sql = " SELECT  	idvisitor,
							visit_last_action_time,
							visit_first_action_time,
							idvisit,
							visit_exit_idaction_url,
							visit_exit_idaction_name,
							visitor_returning,
							visitor_days_since_first,
							visitor_days_since_order,
							referer_name,
							referer_keyword,
							referer_type,
							visitor_count_visits,
							visit_goal_buyer
							$selectCustomVariables
				FROM ".Piwik_Common::prefixTable('log_visit').
				" WHERE ".$where."
				ORDER BY visit_last_action_time DESC
				LIMIT 1";
		$visitRow = Piwik_Tracker::getDatabase()->fetch($sql, $bindSql);

		if( !Piwik_Tracker_Config::getInstance()->Debug['tracker_always_new_visitor']
			&& $visitRow
			&& count($visitRow) > 0)
		{
			// These values will be used throughout the request
			$this->visitorInfo['visit_last_action_time'] = strtotime($visitRow['visit_last_action_time']);
			$this->visitorInfo['visit_first_action_time'] = strtotime($visitRow['visit_first_action_time']);
			$this->visitorInfo['idvisitor'] = $visitRow['idvisitor'];
			$this->visitorInfo['idvisit'] = $visitRow['idvisit'];
			$this->visitorInfo['visit_exit_idaction_url'] = $visitRow['visit_exit_idaction_url'];
			$this->visitorInfo['visit_exit_idaction_name'] = $visitRow['visit_exit_idaction_name'];
			$this->visitorInfo['visitor_returning'] = $visitRow['visitor_returning'];
			$this->visitorInfo['visitor_days_since_first'] = $visitRow['visitor_days_since_first'];
			$this->visitorInfo['visitor_days_since_order'] = $visitRow['visitor_days_since_order'];
			$this->visitorInfo['visitor_count_visits'] = $visitRow['visitor_count_visits'];
			$this->visitorInfo['visit_goal_buyer'] = $visitRow['visit_goal_buyer'];
			
			// Referer information will be potentially used for Goal Conversion attribution
			$this->visitorInfo['referer_name'] = $visitRow['referer_name'];
			$this->visitorInfo['referer_keyword'] = $visitRow['referer_keyword'];
			$this->visitorInfo['referer_type'] = $visitRow['referer_type'];

			// Custom Variables copied from Visit in potential later conversion
			if(!empty($selectCustomVariables))
			{
				for($i=1; $i<=Piwik_Tracker::MAX_CUSTOM_VARIABLES; $i++) 
				{
					if(!empty($visitRow['custom_var_k'.$i]))
					{
						$this->visitorInfo['custom_var_k'.$i] = $visitRow['custom_var_k'.$i];
					}
					if(!empty($visitRow['custom_var_v'.$i]))
					{
						$this->visitorInfo['custom_var_v'.$i] = $visitRow['custom_var_v'.$i];
					}
				}
			}
			
			$this->visitorKnown = true;
			printDebug("The visitor is known (idvisitor = ".bin2hex($this->visitorInfo['idvisitor']).",
						config_id = ".bin2hex($configId).",
						idvisit = {$this->visitorInfo['idvisit']},
						last action = ".date("r", $this->visitorInfo['visit_last_action_time']).",
						first action = ".date("r", $this->visitorInfo['visit_first_action_time']) .",
						visit_goal_buyer' = ".$this->visitorInfo['visit_goal_buyer'].")");
		}
		else
		{
			printDebug("The visitor was not matched with an existing visitor...");
		}
	}

	static public function getCustomVariables($scope, $request)
	{
		if($scope == 'visit')
		{
			$parameter = '_cvar';
			$debug = 'Visit level';
		}
		else
		{
			$parameter = 'cvar';
			$debug = 'Page level';
		}
		
		$customVar = Piwik_Common::unsanitizeInputValue(Piwik_Common::getRequestVar( $parameter, '', 'string', $request));
		$customVar = @json_decode($customVar, $assoc = true);

		if(!is_array($customVar))
		{
			return array();
		}
		$customVariables = array();
		foreach($customVar as $id => $keyValue)
		{
			$id = (int)$id;
			if($id < 1
				|| $id > Piwik_Tracker::MAX_CUSTOM_VARIABLES
				|| count($keyValue) != 2
				|| (!is_string($keyValue[0]) && !is_numeric($keyValue[0]))
			)
			{
				printDebug("Invalid custom variables detected (id=$id)");
				continue;
			}
			if(empty($keyValue[1]))
			{
				$keyValue[1] = "";
			}
			// We keep in the URL when Custom Variable have empty names
			// and values, as it means they can be deleted server side

			$key = self::truncateCustomVariable($keyValue[0]);
			$value = self::truncateCustomVariable($keyValue[1]);
			$customVariables['custom_var_k'.$id] = $key;
			$customVariables['custom_var_v'.$id] = $value;
		}
		if(!empty($customVariables))
		{
			printDebug("$debug Custom Variables: ");
			printDebug($customVariables);
		}
		return $customVariables;
	}

	static public function truncateCustomVariable($input)
	{
		return substr($input, 0, Piwik_Tracker::MAX_LENGTH_CUSTOM_VARIABLE);
	}

	/**
	 * Gets the UserSettings information and returns them in an array of name => value
	 *
	 * @return array
	 */
	protected function getUserSettingsInformation()
	{
		// we already called this method before, simply returns the result
		if(is_array($this->userSettingsInformation))
		{
			return $this->userSettingsInformation;
		}
		require_once PIWIK_INCLUDE_PATH . '/libs/UserAgentParser/UserAgentParser.php';

		$plugin_Flash 			= Piwik_Common::getRequestVar( 'fla', 0, 'int', $this->request);
		$plugin_Java 			= Piwik_Common::getRequestVar( 'java', 0, 'int', $this->request);
		$plugin_Director 		= Piwik_Common::getRequestVar( 'dir', 0, 'int', $this->request);
		$plugin_Quicktime		= Piwik_Common::getRequestVar( 'qt', 0, 'int', $this->request);
		$plugin_RealPlayer 		= Piwik_Common::getRequestVar( 'realp', 0, 'int', $this->request);
		$plugin_PDF 			= Piwik_Common::getRequestVar( 'pdf', 0, 'int', $this->request);
		$plugin_WindowsMedia 	= Piwik_Common::getRequestVar( 'wma', 0, 'int', $this->request);
		$plugin_Gears			= Piwik_Common::getRequestVar( 'gears', 0, 'int', $this->request);
		$plugin_Silverlight		= Piwik_Common::getRequestVar( 'ag', 0, 'int', $this->request);
		$plugin_Cookie 			= Piwik_Common::getRequestVar( 'cookie', 0, 'int', $this->request);

		$userAgent		= Piwik_Common::sanitizeInputValues($this->getUserAgent());
		$aBrowserInfo	= UserAgentParser::getBrowser($userAgent);

		$browserName	= ($aBrowserInfo !== false && $aBrowserInfo['id'] !== false) ? $aBrowserInfo['id'] : 'UNK';
		$browserVersion	= ($aBrowserInfo !== false && $aBrowserInfo['version'] !== false) ? $aBrowserInfo['version'] : '';

		$os				= UserAgentParser::getOperatingSystem($userAgent);
		$os				= $os === false ? 'UNK' : $os['id'];

		$resolution		= Piwik_Common::getRequestVar('res', 'unknown', 'string', $this->request);

		$browserLang	= Piwik_Common::getBrowserLanguage();

		$configurationHash = $this->getConfigHash(
												$os,
												$browserName,
												$browserVersion,
												$resolution,
												$plugin_Flash,
												$plugin_Java,
												$plugin_Director,
												$plugin_Quicktime,
												$plugin_RealPlayer,
												$plugin_PDF,
												$plugin_WindowsMedia,
												$plugin_Gears,
												$plugin_Silverlight,
												$plugin_Cookie,
												$this->getVisitorIp(),
												$browserLang);

		$this->userSettingsInformation = array(
			'config_id' 			=> $configurationHash,
			'config_os' 			=> $os,
			'config_browser_name' 	=> $browserName,
			'config_browser_version' => $browserVersion,
			'config_resolution' 	=> $resolution,
			'config_pdf' 			=> $plugin_PDF,
			'config_flash' 			=> $plugin_Flash,
			'config_java' 			=> $plugin_Java,
			'config_director' 		=> $plugin_Director,
			'config_quicktime' 		=> $plugin_Quicktime,
			'config_realplayer' 	=> $plugin_RealPlayer,
			'config_windowsmedia' 	=> $plugin_WindowsMedia,
			'config_gears'	 		=> $plugin_Gears,
			'config_silverlight'	=> $plugin_Silverlight,
			'config_cookie' 		=> $plugin_Cookie,
			'location_browser_lang' => $browserLang,
		);

		return $this->userSettingsInformation;
	}

	/**
	 * Returns true if the last action was done during the last 30 minutes
	 * @return bool
	 */
	protected function isLastActionInTheSameVisit()
	{
		return isset($this->visitorInfo['visit_last_action_time'])
					&& ($this->visitorInfo['visit_last_action_time']
						> ($this->getCurrentTimestamp() - Piwik_Tracker_Config::getInstance()->Tracker['visit_standard_length']));
	}

	/**
	 * Returns true if the recognizeTheVisitor() method did recognize the visitor
	 */
	protected function isVisitorKnown()
	{
		return $this->visitorKnown === true;
	}

	/**
	 * Update the cookie information.
	 */
	protected function setThirdPartyCookie()
	{
		if(!$this->shouldUseThirdPartyCookie())
		{
			return;
		}
		printDebug("We manage the cookie...");

		// idcookie has been generated in handleNewVisit or we simply propagate the old value
		$this->cookie->set(0, bin2hex($this->visitorInfo['idvisitor']) );
		$this->cookie->save();
	}

	/**
	 * Returns an object able to handle the current action
	 * Plugins can return an override Action that for example, does not record the action in the DB
	 *
	 * @return Piwik_Tracker_Action child or fake but with same public interface
	 */
	protected function newAction()
	{
		$action = null;
		Piwik_PostEvent('Tracker.newAction', $action);

		if(is_null($action))
		{
			$action = new Piwik_Tracker_Action();
		}
		elseif(!($action instanceof Piwik_Tracker_Action_Interface))
		{
			throw new Exception("The Action object set in the plugin must implement the interface Piwik_Tracker_Action_Interface");
		}
		return $action;
	}

	/**
	 * Detect whether action is an outlink given host aliases
	 *
	 * @param Piwik_Tracker_Action_Interface $action
	 * @return bool true if the outlink the visitor clicked on points to one of the known hosts for this website
	 */
	protected function detectActionIsOutlinkOnAliasHost(Piwik_Tracker_Action_Interface $action)
	{
		if($action->getActionType() != Piwik_Tracker_Action_Interface::TYPE_OUTLINK)
		{
			return false;
		}
		$decodedActionUrl = $action->getActionUrl();
		$actionUrlParsed = @parse_url($decodedActionUrl);
		if(!isset($actionUrlParsed['host']))
		{
			return false;
		}
		return Piwik_Tracker_Visit::isHostKnownAliasHost($actionUrlParsed['host'], $this->idsite);
	}

	/**
	 * Returns a 64-bit hash of all the configuration settings
	 * @return string
	 */
	protected function getConfigHash( $os, $browserName, $browserVersion, $resolution, $plugin_Flash, $plugin_Java, $plugin_Director, $plugin_Quicktime, $plugin_RealPlayer, $plugin_PDF, $plugin_WindowsMedia, $plugin_Gears, $plugin_Silverlight, $plugin_Cookie, $ip, $browserLang)
	{
		$hash = md5( $os . $browserName . $browserVersion . $plugin_Flash . $plugin_Java . $plugin_Director . $plugin_Quicktime . $plugin_RealPlayer . $plugin_PDF . $plugin_WindowsMedia . $plugin_Gears . $plugin_Silverlight . $plugin_Cookie . $ip . $browserLang, $raw_output = true );
		$substr = function_exists('mb_orig_substr') ? 'mb_orig_substr' : 'substr';
		return $substr( $hash, 0, Piwik_Tracker::LENGTH_BINARY_ID );
	}

	/**
	 * Returns either
	 * - "-1" for a known visitor
	 * - at least 16 char identifier in hex @see Piwik_Common::generateUniqId()
	 */
	protected function getVisitorUniqueId()
	{
		if($this->isVisitorKnown())
		{
			return -1;
		}
		return Piwik_Common::generateUniqId();
	}

	protected function setCookie( $cookie )
	{
		$this->cookie = $cookie;
	}

	// is the referer host any of the registered URLs for this website?
	static public function isHostKnownAliasHost($urlHost, $idSite)
	{
		$websiteData = Piwik_Common::getCacheWebsiteAttributes($idSite);
		if(isset($websiteData['hosts']))
		{
			$canonicalHosts = array();
			foreach($websiteData['hosts'] as $host) {
				$canonicalHosts[] = str_replace('www.', '' , mb_strtolower($host, 'UTF-8'));
			}
			$canonicalHost = str_replace('www.', '', mb_strtolower($urlHost, 'UTF-8'));
			if(in_array($canonicalHost, $canonicalHosts))
			{
				return true;
			}
		}
		return false;
	}
}

/**
 * @package Piwik
 * @subpackage Piwik_Tracker
 */
class Piwik_Tracker_Visit_Referer
{
	// @see detect*() referer methods
	protected $typeRefererAnalyzed;
	protected $nameRefererAnalyzed;
	protected $keywordRefererAnalyzed;
	protected $refererHost;
	protected $refererUrl;
	protected $refererUrlParse;
	protected $currentUrlParse;
	protected $idsite;

	/**
	 * Returns an array containing the following information:
	 * - referer_type
	 *		- direct			-- absence of referer URL OR referer URL has the same host
	 *		- site				-- based on the referer URL
	 *		- search_engine		-- based on the referer URL
	 *		- campaign			-- based on campaign URL parameter
	 *
	 * - referer_name
	 * 		- ()
	 * 		- piwik.net			-- site host name
	 * 		- google.fr			-- search engine host name
	 * 		- adwords-search	-- campaign name
	 *
	 * - referer_keyword
	 * 		- ()
	 * 		- ()
	 * 		- my keyword
	 * 		- my paid keyword
	 * 		- ()
	 * 		- ()
	 *
	 * - referer_url : the same for all the referer types
	 *
	 * @param URLs must be URL Encoded
	 */
	public function getRefererInformation($refererUrl, $currentUrl, $idSite)
	{
		$this->idsite = $idSite;

		// default values for the referer_* fields
		$this->refererUrl = Piwik_Common::unsanitizeInputValue($refererUrl);
		$this->refererUrlParse = @parse_url($this->refererUrl);
		$this->currentUrlParse = @parse_url(Piwik_Common::unsanitizeInputValue($currentUrl));
		$this->typeRefererAnalyzed = Piwik_Common::REFERER_TYPE_DIRECT_ENTRY;
		$this->nameRefererAnalyzed = '';
		$this->keywordRefererAnalyzed = '';
		$this->refererHost = '';

		if(isset($this->refererUrlParse['host']))
		{
			$this->refererHost = $this->refererUrlParse['host'];
		}

		$refererDetected = false;

		if( !empty($this->currentUrlParse['host'])
			&&	$this->detectRefererCampaign() )
		{
			$refererDetected = true;
		}

		if(!$refererDetected)
		{
			if( $this->detectRefererDirectEntry()
				|| $this->detectRefererSearchEngine() )
			{
				$refererDetected = true;
			}
		}

		if(!empty($this->refererHost)
			&& !$refererDetected)
		{
			$this->typeRefererAnalyzed = Piwik_Common::REFERER_TYPE_WEBSITE;
			$this->nameRefererAnalyzed = mb_strtolower($this->refererHost, 'UTF-8');
		}

		$refererInformation = array(
			'referer_type' 		=> $this->typeRefererAnalyzed,
			'referer_name' 		=> $this->nameRefererAnalyzed,
			'referer_keyword' 	=> $this->keywordRefererAnalyzed,
			'referer_url' 		=> $this->refererUrl,
		);

		return $refererInformation;
	}

	/*
	 * Search engine detection
	 */
	protected function detectRefererSearchEngine()
	{
		$searchEngineInformation = Piwik_Common::extractSearchEngineInformationFromUrl($this->refererUrl);
		Piwik_PostEvent('Tracker.detectRefererSearchEngine', $searchEngineInformation, $this->refererUrl);
		if($searchEngineInformation === false)
		{
			return false;
		}
		$this->typeRefererAnalyzed = Piwik_Common::REFERER_TYPE_SEARCH_ENGINE;
		$this->nameRefererAnalyzed = $searchEngineInformation['name'];
		$this->keywordRefererAnalyzed = $searchEngineInformation['keywords'];
		return true;
	}

	/*
	 * Campaign analysis
	 */
	protected function detectRefererCampaign()
	{
		if(isset($this->currentUrlParse['query']))
		{
			$campaignParameters = Piwik_Common::getCampaignParameters();

			$campaignNames = $campaignParameters[0];
			foreach($campaignNames as $campaignNameParameter)
			{
				$campaignName = Piwik_Common::getParameterFromQueryString($this->currentUrlParse['query'], $campaignNameParameter);
				if( !empty($campaignName))
				{
					break;
				}
			}

			if(!empty($campaignName))
			{
				$this->typeRefererAnalyzed = Piwik_Common::REFERER_TYPE_CAMPAIGN;
				$this->nameRefererAnalyzed = $campaignName;

				$campaignKeywords = $campaignParameters[1];
				foreach($campaignKeywords as $campaignKeywordParameter)
				{
					$campaignKeyword = Piwik_Common::getParameterFromQueryString($this->currentUrlParse['query'], $campaignKeywordParameter);
					if( !empty($campaignKeyword))
					{
						$this->keywordRefererAnalyzed = $campaignKeyword;
						break;
					}
				}
				return true;
			}
		}
		return false;
	}

	/*
	 * We have previously tried to detect the campaign variables in the URL
	 * so at this stage, if the referer host is the current host,
	 * or if the referer host is any of the registered URL for this website,
	 * it is considered a direct entry
	 */
	protected function detectRefererDirectEntry()
	{
		if(!empty($this->refererHost))
		{
			// is the referer host the current host?
			if(isset($this->currentUrlParse['host']))
			{
				$currentHost = mb_strtolower($this->currentUrlParse['host'], 'UTF-8');
				if($currentHost == mb_strtolower($this->refererHost, 'UTF-8'))
				{
					$this->typeRefererAnalyzed = Piwik_Common::REFERER_TYPE_DIRECT_ENTRY;
					return true;
				}
			}
			if(Piwik_Tracker_Visit::isHostKnownAliasHost($this->refererHost, $this->idsite))
			{
				$this->typeRefererAnalyzed = Piwik_Common::REFERER_TYPE_DIRECT_ENTRY;
				return true;
			}
		}
		return false;
	}
}

/**
 * @package Piwik
 * @subpackage Piwik_Tracker
 */
class Piwik_Tracker_Visit_VisitorNotFoundInDatabase extends Exception {
}

/**
 * @package Piwik
 * @subpackage Piwik_Tracker
 */
class Piwik_Tracker_Visit_Excluded extends Exception {
}
