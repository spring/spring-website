<?php
/**
 * Piwik - Open source web analytics
 * 
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id: functions.php 4181 2011-03-25 20:02:40Z vipsoft $
 * 
 * @category Piwik_Plugins
 * @package Piwik_Provider
 */

/**
 * Return hostname portion of a domain name
 *
 * @param string $in
 * @return string Host name, IP (if IP address didn't resolve), or Unknown
 */
function Piwik_getHostnameName($in)
{
	if(empty($in))
	{
		return Piwik_Translate('General_Unknown');
	}
	if(strtolower($in) === 'ip')
	{
		return "IP";
	}
	if(($positionDot = strpos($in, '.')) !== false)
	{
		return ucfirst(substr($in, 0, $positionDot));
	}
	return $in;
}

/**
 * Return URL for a given domain name
 *
 * @param string $in hostname
 * @return string URL
 */
function Piwik_getHostnameUrl($in)
{
	if($in == Piwik_DataTable::LABEL_SUMMARY_ROW) 
	{ 
		return false;
	}
	if(empty($in)
		|| strtolower($in) === 'ip')
	{
		// link to "what does 'IP' mean?"
		return "http://piwik.org/faq/general/#faq_52";
	}
	return "http://www.".$in."/";
}
