<?php
/**
 * Piwik - Open source web analytics
 * 
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id: Controller.php 4514 2011-04-19 23:28:50Z matt $
 * 
 * @category Piwik_Plugins
 * @package Piwik_UsersManager
 */

/**
 * 
 * @package Piwik_UsersManager
 */
class Piwik_UsersManager_Controller extends Piwik_Controller_Admin
{
	/**
	 * The "Manage Users and Permissions" Admin UI screen
	 */
	function index()
	{
		$view = Piwik_View::factory('UsersManager');
		
		$IdSitesAdmin = Piwik_SitesManager_API::getInstance()->getSitesIdWithAdminAccess();
		$idSiteSelected = 1;
		
		if(count($IdSitesAdmin) > 0)
		{
			$defaultWebsiteId = $IdSitesAdmin[0];
			$idSiteSelected = Piwik_Common::getRequestVar('idsite', $defaultWebsiteId);
		}
		
		if($idSiteSelected==='all')
		{
			$usersAccessByWebsite = array();
		}
		else
		{
			$usersAccessByWebsite = Piwik_UsersManager_API::getInstance()->getUsersAccessFromSite( $idSiteSelected );
		}
		 
		// we dont want to display the user currently logged so that the user can't change his settings from admin to view...
		$currentlyLogged = Piwik::getCurrentUserLogin();
		$usersLogin = Piwik_UsersManager_API::getInstance()->getUsersLogin();
		foreach($usersLogin as $login)
		{
			if(!isset($usersAccessByWebsite[$login]))
			{
				$usersAccessByWebsite[$login] = 'noaccess';
			}
		}
		unset($usersAccessByWebsite[$currentlyLogged]);

		
		// $usersAccessByWebsite is not supposed to contain unexistant logins, but it does when upgrading from some old Piwik version
		foreach($usersAccessByWebsite as $login => $access)
		{
		    if(!in_array($login, $usersLogin))
		    {
		        unset($usersAccessByWebsite[$login]);
		        continue;
		    }
		}
		
		ksort($usersAccessByWebsite);
		
		$users = array();
		$usersAliasByLogin = array(); 
		if(Piwik::isUserHasSomeAdminAccess())
		{
			$users = Piwik_UsersManager_API::getInstance()->getUsers();
			foreach($users as $user)
			{
			    $usersAliasByLogin[$user['login']] = $user['alias'];
			}
		}
		
		$view->idSiteSelected = $idSiteSelected;
		$view->users = $users;
		$view->usersAliasByLogin = $usersAliasByLogin;
		$view->usersCount = count($users) - 1;
		$view->usersAccessByWebsite = $usersAccessByWebsite;
		$websites = Piwik_SitesManager_API::getInstance()->getSitesWithAdminAccess();
    	function orderByName($a, $b) { return strcmp($a['name'], $b['name']); }
		uasort($websites, 'orderByName');
		$view->websites = $websites;
		$this->setBasicVariablesView($view);
		$view->menu = Piwik_GetAdminMenu();
		echo $view->render();
	}
	
	/**
	 * Returns default date for Piwik reports
	 *
	 * @param string $user
	 * @return string today, yesterday, week, month, year
	 */
	protected function getDefaultDateForUser($user)
	{
		$userSettingsDate = Piwik_UsersManager_API::getInstance()->getUserPreference($user, Piwik_UsersManager_API::PREFERENCE_DEFAULT_REPORT_DATE);
		if($userSettingsDate === false)
		{
			return Zend_Registry::get('config')->General->default_day;
		}
		return $userSettingsDate;
	}

	/**
	 * The "User Settings" admin UI screen view
	 */
	public function userSettings()
	{
		$view = Piwik_View::factory('userSettings');
		
		$userLogin = Piwik::getCurrentUserLogin();
		if(Piwik::isUserIsSuperUser())
		{
			$view->userAlias = $userLogin;
			$view->userEmail = Piwik::getSuperUserEmail();
			if(!Zend_Registry::get('config')->isFileWritable())
			{
				$view->configFileNotWritable = true;
			}
		}
		else
		{
			$user = Piwik_UsersManager_API::getInstance()->getUser($userLogin);
			$view->userAlias = $user['alias'];
	 		$view->userEmail = $user['email'];
		}
		
		$defaultReport = Piwik_UsersManager_API::getInstance()->getUserPreference($userLogin, Piwik_UsersManager_API::PREFERENCE_DEFAULT_REPORT);
		if($defaultReport === false)
		{
			$defaultReport = $this->getDefaultWebsiteId();
		}
		$view->defaultReport = $defaultReport;

		$view->defaultDate = $this->getDefaultDateForUser($userLogin);
		$view->availableDefaultDates = array(
			'today' => Piwik_Translate('General_Today'),
			'yesterday' => Piwik_Translate('General_Yesterday'),
			'previous7' => Piwik_Translate('General_PreviousDays', 7),
			'previous30' => Piwik_Translate('General_PreviousDays', 30),
			'last7' => Piwik_Translate('General_LastDays', 7),
			'last30' => Piwik_Translate('General_LastDays', 30),
			'week' => Piwik_Translate('General_CurrentWeek'),
			'month' => Piwik_Translate('General_CurrentMonth'),
			'year' => Piwik_Translate('General_CurrentYear'),
		);
		
		$view->ignoreCookieSet = Piwik_Tracker_IgnoreCookie::isIgnoreCookieFound();
		$this->initViewAnonymousUserSettings($view);
		$view->piwikHost = Piwik_Url::getCurrentHost();
		$this->setBasicVariablesView($view);
		$view->menu = Piwik_GetAdminMenu();
		echo $view->render();
	}
	
	public function setIgnoreCookie()
	{
		Piwik::checkUserHasSomeViewAccess();
		Piwik::checkUserIsNotAnonymous();
		$this->checkTokenInUrl();
		Piwik_Tracker_IgnoreCookie::setIgnoreCookie();
		Piwik::redirectToModule('UsersManager', 'userSettings');
	}

	/**
	 * The Super User can modify Anonymous user settings
	 * @param Piwik_View $view
	 */
	protected function initViewAnonymousUserSettings($view)
	{
		if(!Piwik::isUserIsSuperUser())
		{
			return;
		}
		$userLogin = 'anonymous';
		
		// Which websites are available to the anonymous users?
		$anonymousSitesAccess = Piwik_UsersManager_API::getInstance()->getSitesAccessFromUser($userLogin);
		$anonymousSites = array();
		foreach($anonymousSitesAccess as $info) 
		{
			$idSite = $info['site'];
			$anonymousSites[$idSite] = Piwik_SitesManager_API::getInstance()->getSiteFromId($idSite);
		}
		$view->anonymousSites = $anonymousSites;
		
		// Which report is displayed by default to the anonymous user?
		$anonymousDefaultReport = Piwik_UsersManager_API::getInstance()->getUserPreference($userLogin, Piwik_UsersManager_API::PREFERENCE_DEFAULT_REPORT);
		if($anonymousDefaultReport === false)
		{
			if(empty($anonymousSites))
			{
				$anonymousDefaultReport = Piwik::getLoginPluginName();
			}
			else
			{
				// we manually imitate what would happen, in case the anonymous user logs in 
				// and is redirected to the first website available to him in the list
				// @see getDefaultWebsiteId()
				reset($anonymousSites);
				$anonymousDefaultReport = key($anonymousSites);
			} 
		}
		$view->anonymousDefaultReport = $anonymousDefaultReport;

		$view->anonymousDefaultDate = $this->getDefaultDateForUser($userLogin);
	}

	/**
	 * Records settings for the anonymous users (default report, default date)
	 */
	public function recordAnonymousUserSettings()
	{
		$response = new Piwik_API_ResponseBuilder(Piwik_Common::getRequestVar('format'));
		try {
			Piwik::checkUserIsSuperUser();
			$this->checkTokenInUrl();
			$anonymousDefaultReport = Piwik_Common::getRequestVar('anonymousDefaultReport');
			$anonymousDefaultDate = Piwik_Common::getRequestVar('anonymousDefaultDate');
			$userLogin = 'anonymous';
			Piwik_UsersManager_API::getInstance()->setUserPreference($userLogin, 
																Piwik_UsersManager_API::PREFERENCE_DEFAULT_REPORT, 
																$anonymousDefaultReport);
			Piwik_UsersManager_API::getInstance()->setUserPreference($userLogin, 
																Piwik_UsersManager_API::PREFERENCE_DEFAULT_REPORT_DATE, 
																$anonymousDefaultDate);
			$toReturn = $response->getResponse();
		} catch(Exception $e ) {
			$toReturn = $response->getResponseException( $e );
		}
		echo $toReturn;
	}
	
	/**
	 * Records settings from the "User Settings" page
	 */
	public function recordUserSettings()
	{
		$response = new Piwik_API_ResponseBuilder(Piwik_Common::getRequestVar('format'));
		try {
			$this->checkTokenInUrl();
			$alias = Piwik_Common::getRequestVar('alias');
			$email = Piwik_Common::getRequestVar('email');
			$defaultReport = Piwik_Common::getRequestVar('defaultReport');
			$defaultDate = Piwik_Common::getRequestVar('defaultDate');

			$newPassword = false;
			$password = Piwik_Common::getRequestvar('password', false);
			$passwordBis = Piwik_Common::getRequestvar('passwordBis', false);
			if(!empty($password)
				|| !empty($passwordBis))
			{
				if($password != $passwordBis)
				{
					throw new Exception(Piwik_Translate('Login_PasswordsDoNotMatch'));
				}
				$newPassword = $password;
			}
			
			$userLogin = Piwik::getCurrentUserLogin();
			if(Piwik::isUserIsSuperUser())
			{
				$superUser = Zend_Registry::get('config')->superuser;
				$updatedSuperUser = false;

				if($newPassword !== false)
				{
					$newPassword = Piwik_Common::unsanitizeInputValue($newPassword);
					$md5PasswordSuperUser = md5($newPassword);
					$superUser->password = $md5PasswordSuperUser;
					$updatedSuperUser = true;
				}
	 			if($superUser->email != $email)
				{
					$superUser->email = $email;
	 				$updatedSuperUser = true;
				}
				if($updatedSuperUser)
				{
					Zend_Registry::get('config')->superuser = $superUser->toArray();
				}
			}
			else
			{
				Piwik_UsersManager_API::getInstance()->updateUser($userLogin, $newPassword, $email, $alias);
				if($newPassword !== false)
				{
					$newPassword = Piwik_Common::unsanitizeInputValue($newPassword);
				}
			}

			// logs the user in with the new password
			if($newPassword !== false)
			{
				$info = array(
					'login' => $userLogin, 
					'md5Password' => md5($newPassword),
					'rememberMe' => false,
				);
				Piwik_PostEvent('Login.initSession', $info);
			}

			Piwik_UsersManager_API::getInstance()->setUserPreference($userLogin, 
																Piwik_UsersManager_API::PREFERENCE_DEFAULT_REPORT, 
																$defaultReport);
			Piwik_UsersManager_API::getInstance()->setUserPreference($userLogin, 
																Piwik_UsersManager_API::PREFERENCE_DEFAULT_REPORT_DATE, 
																$defaultDate);
			$toReturn = $response->getResponse();
		} catch(Exception $e ) {
			$toReturn = $response->getResponseException( $e );
		}
		echo $toReturn;
	}
}
