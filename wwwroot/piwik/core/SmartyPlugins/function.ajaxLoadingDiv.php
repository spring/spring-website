<?php
/**
 * Piwik - Open source web analytics
 * 
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id: function.ajaxLoadingDiv.php 2968 2010-08-20 15:26:33Z vipsoft $
 * 
 * @category Piwik
 * @package SmartyPlugins
 */

/**
 * Outputs the generic Ajax Loading div (displayed when ajax requests are triggered)
 * 
 * @param id=$ID_NAME ID of the HTML div, defaults to ajaxLoading
 * @return	string Html of the Loading... div
 */
function smarty_function_ajaxLoadingDiv($params, &$smarty)
{
	if(empty($params['id'])) 
	{
		$id = 'ajaxLoading';
	}
	else
	{
		$id = $params['id'];
	}
	return '<div id="'.$id.'" style="display:none">'.
				'<div class="loadingPiwik"><img src="themes/default/images/loading-blue.gif" alt="" /> '. 
					Piwik_Translate('General_LoadingData') .
				' </div>'.
			'</div>';
	;
}
