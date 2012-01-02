<?php
/**
 * Piwik - Open source web analytics
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id: CoreHome.php 5209 2011-09-22 19:53:11Z SteveG $
 *
 * @category Piwik_Plugins
 * @package Piwik_CoreHome
 */

/**
 *
 * @package Piwik_CoreHome
 */
class Piwik_CoreHome extends Piwik_Plugin
{
	public function getInformation()
	{
		return array(
			'description' => Piwik_Translate('CoreHome_PluginDescription'),
			'author' => 'Piwik',
			'author_homepage' => 'http://piwik.org/',
			'version' => Piwik_Version::VERSION,
		);
	}
	
	function getListHooksRegistered()
	{
		return array(
			'AssetManager.getCssFiles' => 'getCssFiles',
			'AssetManager.getJsFiles' => 'getJsFiles'
		);
	}

	function getCssFiles( $notification )
	{
		$cssFiles = &$notification->getNotificationObject();
		
		$cssFiles[] = "libs/jquery/themes/base/jquery-ui.css";
		$cssFiles[] = "themes/default/common.css";
		$cssFiles[] = "plugins/CoreHome/templates/styles.css";
		$cssFiles[] = "plugins/CoreHome/templates/menu.css";
		$cssFiles[] = "plugins/CoreHome/templates/datatable.css";
		$cssFiles[] = "plugins/CoreHome/templates/cloud.css";
		$cssFiles[] = "plugins/CoreHome/templates/jquery.ui.autocomplete.css";
		$cssFiles[] = "plugins/CoreHome/templates/jqplot.css";
	}

	function getJsFiles( $notification )
	{
		$jsFiles = &$notification->getNotificationObject();
		
		$jsFiles[] = "libs/jquery/jquery.js";
		$jsFiles[] = "libs/jquery/jquery-ui.js";
		$jsFiles[] = "libs/jquery/jquery.tooltip.js";
		$jsFiles[] = "libs/jquery/jquery.truncate.js";
		$jsFiles[] = "libs/jquery/jquery.scrollTo.js";
		$jsFiles[] = "libs/jquery/jquery.history.js";
		$jsFiles[] = "libs/swfobject/swfobject.js";
		$jsFiles[] = "libs/javascript/sprintf.js";
		$jsFiles[] = "themes/default/common.js";
		$jsFiles[] = "plugins/CoreHome/templates/datatable.js";
		$jsFiles[] = "plugins/CoreHome/templates/broadcast.js";
		$jsFiles[] = "plugins/CoreHome/templates/menu.js";
		$jsFiles[] = "plugins/CoreHome/templates/calendar.js";
		$jsFiles[] = "plugins/CoreHome/templates/date.js";
		$jsFiles[] = "plugins/CoreHome/templates/autocomplete.js";
		$jsFiles[] = "plugins/CoreHome/templates/sparkline.js";
		
		$jqplot = 'libs/jqplot/';
		$jsFiles[] = "plugins/CoreHome/templates/jqplot.js";
		$jsFiles[] = $jqplot."jqplot.core.js";
		$jsFiles[] = $jqplot."jqplot.linearAxisRenderer.js";
		$jsFiles[] = $jqplot."jqplot.axisTickRenderer.js";
		$jsFiles[] = $jqplot."jqplot.axisLabelRenderer.js";
		$jsFiles[] = $jqplot."jqplot.tableLegendRenderer.js";
		$jsFiles[] = $jqplot."jqplot.lineRenderer.js";
		$jsFiles[] = $jqplot."jqplot.markerRenderer.js";
		$jsFiles[] = $jqplot."jqplot.divTitleRenderer.js";
		$jsFiles[] = $jqplot."jqplot.canvasGridRenderer.js";
		$jsFiles[] = $jqplot."jqplot.shadowRenderer.js";
		$jsFiles[] = $jqplot."jqplot.shapeRenderer.js";
		$jsFiles[] = $jqplot."jqplot.sprintf.js";
		$jsFiles[] = $jqplot."jqplot.themeEngine.js";
		$jsFiles[] = $jqplot."plugins/jqplot.pieRenderer.js";
		$jsFiles[] = $jqplot."plugins/jqplot.barRenderer.js";
		$jsFiles[] = $jqplot."plugins/jqplot.categoryAxisRenderer.js";
		$jsFiles[] = $jqplot."plugins/jqplot.canvasTextRenderer.js";
		$jsFiles[] = $jqplot."plugins/jqplot.canvasAxisTickRenderer.js";
	}
	
}
