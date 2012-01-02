<?php
/**
 * Piwik - Open source web analytics
 * 
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id: UpdateCheck.php 5278 2011-10-10 05:09:42Z vipsoft $
 * 
 * @category Piwik
 * @package Piwik
 */

/**
 * Class to check if a newer version of Piwik is available
 *
 * @package Piwik
 */
class Piwik_UpdateCheck 
{
	const CHECK_INTERVAL = 28800; // every 8 hours
	const LAST_TIME_CHECKED = 'UpdateCheck_LastTimeChecked';
	const LATEST_VERSION = 'UpdateCheck_LatestVersion';
	const SOCKET_TIMEOUT = 2;

	/**
	 * Check for a newer version
	 *
	 * @param bool $force Force check
	 */
	public static function check($force = false)
	{
		$lastTimeChecked = Piwik_GetOption(self::LAST_TIME_CHECKED);
		if($force
			|| $lastTimeChecked === false
			|| time() - self::CHECK_INTERVAL > $lastTimeChecked )
		{
			// set the time checked first, so that parallel Piwik requests don't all trigger the http requests
			Piwik_SetOption(self::LAST_TIME_CHECKED, time(), $autoload = 1);
			$parameters = array(
				'piwik_version' => Piwik_Version::VERSION,
				'php_version' => PHP_VERSION,
				'url' => Piwik_Url::getCurrentUrlWithoutQueryString(),
				'trigger' => Piwik_Common::getRequestVar('module','','string'),
				'timezone' => Piwik_SitesManager_API::getInstance()->getDefaultTimezone(),
			);

			$url = Zend_Registry::get('config')->General->api_service_url
				. '/1.0/getLatestVersion/'
				. '?' . http_build_query($parameters, '', '&');
			$timeout = self::SOCKET_TIMEOUT;
			try {
				$latestVersion = Piwik_Http::sendHttpRequest($url, $timeout);
				if (!preg_match('~^[0-9][0-9a-zA-Z_.-]*$~D', $latestVersion))
				{
					$latestVersion = '';
				}
			} catch(Exception $e) {
				// e.g., disable_functions = fsockopen; allow_url_open = Off
				$latestVersion = '';
			}
			Piwik_SetOption(self::LATEST_VERSION, $latestVersion);
		}
	}
	
	/**
	 * Returns version number of a newer Piwik release.
	 *
	 * @return string|false false if current version is the latest available, 
	 * 	 or the latest version number if a newest release is available
	 */
	public static function isNewestVersionAvailable()
	{
		$latestVersion = Piwik_GetOption(self::LATEST_VERSION);
		if(!empty($latestVersion)
			&& version_compare(Piwik_Version::VERSION, $latestVersion) == -1)
		{
			return $latestVersion;
		}
		return false;
	}
}
