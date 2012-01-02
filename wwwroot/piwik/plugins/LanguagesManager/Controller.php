<?php
/**
 * Piwik - Open source web analytics
 * 
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id: Controller.php 3553 2011-01-02 00:05:05Z vipsoft $
 * 
 * @category Piwik_Plugins
 * @package Piwik_LanguagesManager
 * 
 */

/**
 * @package Piwik_LanguagesManager
 */
class Piwik_LanguagesManager_Controller extends Piwik_Controller
{
	/**
	 * anonymous = in the session
	 * authenticated user = in the session and in DB
	 */
	public function saveLanguage()
	{
		$language = Piwik_Common::getRequestVar('language');
		Piwik_LanguagesManager::setLanguageForSession($language);
		if(Zend_Registry::isRegistered('access')) {
			$currentUser = Piwik::getCurrentUserLogin();
			if($currentUser && $currentUser !== 'anonymous')
			{
				Piwik_LanguagesManager_API::getInstance()->setLanguageForUser($currentUser, $language);
			}
		}
		Piwik_Url::redirectToReferer();
	}	
}
