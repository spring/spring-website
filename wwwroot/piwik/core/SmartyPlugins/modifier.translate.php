<?php
/**
 * Piwik - Open source web analytics
 * 
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id: modifier.translate.php 2968 2010-08-20 15:26:33Z vipsoft $
 * 
 * @category Piwik
 * @package SmartyPlugins
 */

/**
 * Translates in the currently selected language the specified translation $stringToken
 * Translations strings are located either in /lang/xx.php or within the plugin lang directory.
 * 
 * Usage:
 *  {'General_Unknown'|translate} will be translated as 'Unknown' (see the entry in /lang/en.php)
 * 
 * Usage with multiple substrings to be replaced in the translation string:
 * 		- in lang/en.php you would find: 
 * 			'VisitorInterest_BetweenXYMinutes' => '%1s-%2s min',
 *  	- in the smarty template you would then translate the string, passing the two parameters: 
 *  		{'VisitorInterest_BetweenXYMinutes'|translate:$min:$max}
 *  
 * @return string The translated string, with optional substrings parameters replaced
 */
function smarty_modifier_translate($stringToken)
{
	if(func_num_args() <= 1)
	{
		$aValues = array();
	}
	else
	{
		$aValues = func_get_args();
		array_shift($aValues);
	}
	
	try {
		$stringTranslated = Piwik_Translate($stringToken, $aValues);
	} catch( Exception $e) {
		$stringTranslated = $stringToken; 
	}
	return $stringTranslated;
}
