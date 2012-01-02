<?php
/**
 * Piwik - Open source web analytics
 * 
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id: functions.php 4181 2011-03-25 20:02:40Z vipsoft $
 * 
 * @category Piwik_Plugins
 * @package Piwik_UserCountry
 */

/**
 * Return the flag image path for a given country
 *
 * @param string $code ISO country code
 * @return string Flag image path
 */
function Piwik_getFlagFromCode($code)
{
	$pathInPiwik = 'plugins/UserCountry/flags/%s.png';
	$pathWithCode = sprintf($pathInPiwik, $code);
	$absolutePath = PIWIK_INCLUDE_PATH . '/' . $pathWithCode;
	if(file_exists($absolutePath))
	{
		return $pathWithCode;
	}
	return sprintf($pathInPiwik, 'xx');			
}

/**
 * Returns the translated continent name for a given continent code
 *
 * @param string $label Continent code
 * @return string Continent name
 */
function Piwik_ContinentTranslate($label)
{
	if($label == 'unk' || $label == '')
	{
		return Piwik_Translate('General_Unknown');
	}
	return Piwik_Translate('UserCountry_continent_'. $label);
}

/**
 * Returns the translated country name for a given country code
 *
 * @param string $label country code
 * @return string Country name
 */
function Piwik_CountryTranslate($label)
{
	if($label == 'xx' || $label == '')
	{
		return Piwik_Translate('General_Unknown');
	}
	return Piwik_Translate('UserCountry_country_'. $label);
}
