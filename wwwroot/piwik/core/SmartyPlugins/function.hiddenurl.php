<?php
/**
 * Piwik - Open source web analytics
 * 
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id: function.hiddenurl.php 2968 2010-08-20 15:26:33Z vipsoft $
 * 
 * @category Piwik
 * @package SmartyPlugins
 */

/**
 * Smarty {hiddenurl} function plugin.
 * Writes an input Hidden field for every parameter in the URL.
 * Useful when using GET forms because we need to print the current parameters 
 * in hidden input so they are to the next URL after the form is submitted.
 *
 * 
 * Examples:
 * <pre>
 * {hiddenurl module="API"} with a URL 'index.php?action=test&module=CoreHome' will output
 *  <input type=hidden name=action value=test>
 *  <input type=hidden name=module value=API>
 * </pre>
 * 
 * Set a value to null if you want this value not to be passed in the submitted form.
 * 
 * @param	array
 * @param	Smarty
 * @return	string
 */
function smarty_function_hiddenurl($params, &$smarty)
{
	$queryStringModified = Piwik_Url::getCurrentQueryStringWithParametersModified( $params );
	$urlValues = Piwik_Common::getArrayFromQueryString($queryStringModified);
	
	$out = '';
	foreach($urlValues as $name => $value)
	{
		$out .= '<input type="hidden" name="'.$name.'" value="'.$value.'" />';
	}
	return $out;
}
