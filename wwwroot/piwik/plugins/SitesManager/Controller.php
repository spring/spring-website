<?php
/**
 * Piwik - Open source web analytics
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id: Controller.php 5188 2011-09-19 01:41:27Z matt $
 *
 * @category Piwik_Plugins
 * @package Piwik_SitesManager
 */

/**
 *
 * @package Piwik_SitesManager
 */
class Piwik_SitesManager_Controller extends Piwik_Controller_Admin
{
	/*
	 * Main view showing listing of websites and settings
	 */
	function index()
	{
		$view = Piwik_View::factory('SitesManager');
		$sites = Piwik_SitesManager_API::getInstance()->getSitesWithAdminAccess();
		$sitesIndexedById = array();
		foreach($sites as $site)
		{
			$sitesIndexedById[$site['idsite']] = $site;
		}
		Piwik_Site::setSites($sitesIndexedById);
		foreach($sites as &$site)
		{
			$site['alias_urls'] = Piwik_SitesManager_API::getInstance()->getSiteUrlsFromId($site['idsite']);
			$site['excluded_ips'] = str_replace(',','<br/>', $site['excluded_ips']);
			$site['excluded_parameters'] = str_replace(',','<br/>', $site['excluded_parameters']);
		}
		$view->adminSites = $sites;
		$view->adminSitesCount = count($sites);

		$timezones = Piwik_SitesManager_API::getInstance()->getTimezonesList();
		$view->timezoneSupported = Piwik::isTimezoneSupportEnabled();
		$view->timezones = json_encode($timezones);
		$view->defaultTimezone = Piwik_SitesManager_API::getInstance()->getDefaultTimezone();

		$view->currencies = json_encode(Piwik_SitesManager_API::getInstance()->getCurrencyList());
		$view->defaultCurrency = Piwik_SitesManager_API::getInstance()->getDefaultCurrency();

		$view->utcTime = Piwik_Date::now()->getDatetime();
		$excludedIpsGlobal = Piwik_SitesManager_API::getInstance()->getExcludedIpsGlobal();
		$view->globalExcludedIps = str_replace(',',"\n", $excludedIpsGlobal);
		$excludedQueryParametersGlobal = Piwik_SitesManager_API::getInstance()->getExcludedQueryParametersGlobal();
		$view->globalExcludedQueryParameters = str_replace(',',"\n", $excludedQueryParametersGlobal);
		$view->currentIpAddress = Piwik_IP::getIpFromHeader();

		$view->showAddSite = (boolean) Piwik_Common::getRequestVar('showaddsite', false);
		
		$this->setBasicVariablesView($view);
		$view->menu = Piwik_GetAdminMenu();
		echo $view->render();
	}

	/*
	 * Records Global settings when user submit changes
	 */
	function setGlobalSettings()
	{
		$response = new Piwik_API_ResponseBuilder(Piwik_Common::getRequestVar('format'));

		try {
			$this->checkTokenInUrl();
			$timezone = Piwik_Common::getRequestVar('timezone', false);
			$excludedIps = Piwik_Common::getRequestVar('excludedIps', false);
			$excludedQueryParameters = Piwik_Common::getRequestVar('excludedQueryParameters', false);
			$currency = Piwik_Common::getRequestVar('currency', false);
			Piwik_SitesManager_API::getInstance()->setDefaultTimezone($timezone);
			Piwik_SitesManager_API::getInstance()->setDefaultCurrency($currency);
			Piwik_SitesManager_API::getInstance()->setGlobalExcludedQueryParameters($excludedQueryParameters);
			Piwik_SitesManager_API::getInstance()->setGlobalExcludedIps($excludedIps);
			$toReturn = $response->getResponse();
		} catch(Exception $e ) {
			$toReturn = $response->getResponseException( $e );
		}
		echo $toReturn;
	}

	/**
	 * Displays the admin UI page showing all tracking tags
	 * @return unknown_type
	 */
	function displayJavascriptCode()
	{
		$idSite = Piwik_Common::getRequestVar('idSite');
		Piwik::checkUserHasViewAccess($idSite);
		$jsTag = Piwik::getJavascriptCode($idSite, Piwik_Url::getCurrentUrlWithoutFileName());
		$view = Piwik_View::factory('Tracking');
		$this->setBasicVariablesView($view);
		$view->menu = Piwik_GetAdminMenu();
		$view->idSite = $idSite;
		$site = new Piwik_Site($idSite);
		$view->displaySiteName = $site->getName();
		$view->jsTag = $jsTag;
		echo $view->render();
	}

	/*
	 *  User will download a file called PiwikTracker.php that is the content of the actual script
	 */
	function downloadPiwikTracker()
	{
		$path = PIWIK_INCLUDE_PATH . '/libs/PiwikTracker/';
		$filename = 'PiwikTracker.php';
		header('Content-type: text/php');
		header('Content-Disposition: attachment; filename="'.$filename.'"');
		echo file_get_contents( $path . $filename);
	}

	/**
	 * Used to generate the doc at http://piwik.org/docs/tracking-api/
	 */
	function displayAlternativeTagsHelp()
	{
		$view = Piwik_View::factory('DisplayAlternativeTags');
		$view->idSite = Piwik_Common::getRequestVar('idSite');
		$view->piwikUrlRequest = Piwik_Common::getRequestVar('piwikUrl', $view->piwikUrl, 'string');
		$view->calledExternally = true;
		echo $view->render();
	}

	function getSitesForAutocompleter()
	{
		$pattern = Piwik_Common::getRequestVar('term');
		$sites = Piwik_SitesManager_API::getInstance()->getPatternMatchSites($pattern);
		$pattern = str_replace('%', '', $pattern);
		if(!count($sites))
		{
			$results[] = array('label' => Piwik_Translate('SitesManager_NotFound')."&nbsp;<font class='autocompleteMatched'>$pattern</font>.", 'id' => '#');
		}
		else
		{
			if(strpos($pattern, '/') !== false 
				&& strpos($pattern, '\\/') === false)
			{
				$pattern = str_replace('/', '\\/', $pattern);
			}
			foreach($sites as $s)
			{
				$hl_name = $s['name'];
				if(strlen($pattern) > 0)
				{
					@preg_match_all("/$pattern+/i", $hl_name, $matches);
					if (is_array($matches[0]) && count($matches[0]) >= 1)
					{
						foreach ($matches[0] as $match)
						{
							$hl_name = str_replace($match, '<font class="autocompleteMatched">'.$match.'</font>', $s['name']);
						}
					}
				}
				$results[] = array('label' => $hl_name, 'id' => $s['idsite'], 'name' => $s['name'] );
			}
		}

		print json_encode($results);
	}
}
