<?php
/**
 * Piwik - Open source web analytics
 * 
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id: modifier.urlRewriteWithParameters.php 6300 2012-05-23 21:19:25Z SteveG $
 * 
 * @category Piwik
 * @package SmartyPlugins
 */

/**
 * Rewrites the given URL and modify the given parameters.
 * @see Piwik_Url::getCurrentQueryStringWithParametersModified()
 *
 * @param $parameters
 * @return string
 */
function smarty_modifier_urlRewriteWithParameters($parameters)
{
	$parameters['updated'] = null;
	$url = Piwik_Url::getCurrentQueryStringWithParametersModified($parameters);
	return Piwik_Common::sanitizeInputValue($url);
}
