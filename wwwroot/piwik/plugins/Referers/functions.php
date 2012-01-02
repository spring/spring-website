<?php
/**
 * Piwik - Open source web analytics
 * 
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id: functions.php 4183 2011-03-25 22:14:56Z vipsoft $
 * 
 * @category Piwik_Plugins
 * @package Piwik_Referers
 */

/**
 * Returns path component from a URL
 *
 * @param string $url
 * @return string path
 */
function Piwik_getPathFromUrl($url)
{
	$path = Piwik_Common::getPathAndQueryFromUrl($url);
	if(empty($path))
	{
		return 'index';
	}
	return $path;
}

/**
 * Return search engine URL by name
 *
 * @see core/DataFiles/SearchEnginges.php
 *
 * @param string $name
 * @return string URL
 */
function Piwik_getSearchEngineUrlFromName($name)
{
	$searchEngineNames = Piwik_Common::getSearchEngineNames();
	if(isset($searchEngineNames[$name]))
	{
		$url = 'http://'.$searchEngineNames[$name];
	}
	else
	{
		$url = 'URL unknown!';
	}
	return $url;
}

/**
 * Return search engine host in URL
 *
 * @param string $url
 * @return string host
 */
function Piwik_getSearchEngineHostFromUrl($url)
{
	$url = substr($url, strpos($url,'//') + 2);
	if(($p = strpos($url, '/')) !== false)
	{
		$url = substr($url, 0, $p);
	}
	return $url;
}

/**
 * Return search engine logo path by URL
 *
 * @param string $url
 * @return string path
 * @see plugins/Referers/images/searchEnginges/
 */
function Piwik_getSearchEngineLogoFromUrl($url)
{
	$pathInPiwik = 'plugins/Referers/images/searchEngines/%s.png';
	$pathWithCode = sprintf($pathInPiwik, Piwik_getSearchEngineHostFromUrl($url));
	$absolutePath = PIWIK_INCLUDE_PATH . '/' . $pathWithCode;
	if(file_exists($absolutePath))
	{
		return $pathWithCode;
	}
	return sprintf($pathInPiwik, 'xx');
}

/**
 * Return search engine host and path in URL
 *
 * @param string $url
 * @return string host
 */
function Piwik_getSearchEngineHostPathFromUrl($url)
{
	$url = substr($url, strpos($url,'//') + 2);
	return $url;
}

/**
 * Return search engine URL for URL and keyword
 *
 * @see core/DataFiles/SearchEnginges.php
 *
 * @param string $url Domain name, e.g., search.piwik.org
 * @param string $keyword Keyword, e.g., web+analytics
 * @return string URL, e.g., http://search.piwik.org/q=web+analytics
 */
function Piwik_getSearchEngineUrlFromUrlAndKeyword($url, $keyword)
{
	$searchEngineUrls = Piwik_Common::getSearchEngineUrls();
	$keyword = urlencode($keyword);
	$keyword = str_replace(urlencode('+'), urlencode(' '), $keyword);
	$path = @$searchEngineUrls[Piwik_getSearchEngineHostPathFromUrl($url)][2];
	if(empty($path))
	{
		return false;
	}
	$path = str_replace("{k}", $keyword, $path);
	return $url . (substr($url, -1) != '/' ? '/' : '') . $path;
}

/**
 * Return search engine URL for keyword and URL
 *
 * @see Piwik_getSearchEngineUrlFromUrlAndKeyword()
 *
 * @param string $keyword Keyword, e.g., web+analytics
 * @param string $url Domain name, e.g., search.piwik.org
 * @return string URL, e.g., http://search.piwik.org/q=web+analytics
 */
function Piwik_getSearchEngineUrlFromKeywordAndUrl($keyword, $url)
{
	return Piwik_getSearchEngineUrlFromUrlAndKeyword($url, $keyword);
}

/**
 * Return translated referrer type
 *
 * @param string $label
 * @return string Referrer type
 */
function Piwik_getRefererTypeLabel($label)
{
	$indexTranslation = '';
	switch($label)
	{
		case Piwik_Common::REFERER_TYPE_DIRECT_ENTRY:
			$indexTranslation = 'Referers_DirectEntry';
			break;
		case Piwik_Common::REFERER_TYPE_SEARCH_ENGINE:
			$indexTranslation = 'Referers_SearchEngines';
			break;
		case Piwik_Common::REFERER_TYPE_WEBSITE:
			$indexTranslation = 'Referers_Websites';
			break;
		case Piwik_Common::REFERER_TYPE_CAMPAIGN:
			$indexTranslation = 'Referers_Campaigns';
			break;
		default:
			// case of newsletter, partners, before Piwik 0.2.25
			$indexTranslation = 'General_Others';
			break;
	}
	return Piwik_Translate($indexTranslation);
}

/**
 * Works in both directions
 * @param mixed 
 */
function Piwik_getRefererTypeFromShortName($name)
{
	$map = array(
		Piwik_Common::REFERER_TYPE_SEARCH_ENGINE => 'search',
		Piwik_Common::REFERER_TYPE_WEBSITE => 'website',
		Piwik_Common::REFERER_TYPE_DIRECT_ENTRY => 'direct',
		Piwik_Common::REFERER_TYPE_CAMPAIGN => 'campaign',
	);
	if(isset($map[$name]))
	{
		return $map[$name];
	}
	if($found = array_search($name, $map))
	{
	    return $found;
	}
	throw new Exception("Referrer type '$name' is not valid.");
}
