<?php
/**
 * Piwik - Open source web analytics
 * 
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id: CoreAdminHome.php 4660 2011-05-08 21:32:07Z SteveG $
 * 
 * @category Piwik_Plugins
 * @package Piwik_CoreAdminHome
 */

/**
 *
 * @package Piwik_CoreAdminHome
 */
class Piwik_CoreAdminHome extends Piwik_Plugin
{
	public function getInformation()
	{
		return array(
			'description' => Piwik_Translate('CoreAdminHome_PluginDescription'),
			'author' => 'Piwik',
			'author_homepage' => 'http://piwik.org/',
			'version' => Piwik_Version::VERSION,
		);
	}

	public function getListHooksRegistered()
	{
		return array( 
			'AssetManager.getCssFiles' => 'getCssFiles',
			'AssetManager.getJsFiles' => 'getJsFiles',
			'AdminMenu.add' => 'addMenu',
			'TaskScheduler.getScheduledTasks' => 'getScheduledTasks',
		);
	}
	
	function getScheduledTasks ( $notification )
	{
		$tasks = &$notification->getNotificationObject();
		$optimizeArchiveTableTask = new Piwik_ScheduledTask ( $this, 
															'optimizeArchiveTable',
															new Piwik_ScheduledTime_Daily() );
		$tasks[] = $optimizeArchiveTableTask;
	}
	
	function getCssFiles( $notification )
	{
		$cssFiles = &$notification->getNotificationObject();
		
		$cssFiles[] = "libs/jquery/themes/base/jquery-ui.css";	
		$cssFiles[] = "plugins/CoreAdminHome/templates/menu.css";	
		$cssFiles[] = "themes/default/common.css";
		$cssFiles[] = "plugins/CoreAdminHome/templates/styles.css";
	}
	
	function getJsFiles ( $notification ) {
	
		$jsFiles = &$notification->getNotificationObject();
		
		$jsFiles[] = "libs/jquery/jquery.js";
		$jsFiles[] = "libs/jquery/jquery-ui.js";
		$jsFiles[] = "libs/javascript/sprintf.js";
		$jsFiles[] = "themes/default/common.js";
		$jsFiles[] = "libs/jquery/jquery.history.js";
		$jsFiles[] = "plugins/CoreHome/templates/broadcast.js";
		$jsFiles[] = "plugins/CoreAdminHome/templates/generalSettings.js";
	}
	
	function addMenu()
	{
		Piwik_AddAdminMenu('CoreAdminHome_MenuGeneralSettings', 
							array('module' => 'CoreAdminHome', 'action' => 'generalSettings'),
							Piwik::isUserHasSomeAdminAccess(),
							$order = 6);
	}
	
	function optimizeArchiveTable()
	{
		$tablesPiwik = Piwik::getTablesInstalled();
		$archiveTables = array_filter ($tablesPiwik, array("Piwik_CoreAdminHome", "isArchiveTable"));
		if(empty($archiveTables))
		{
			return;
		}
		$query = "OPTIMIZE TABLE " . implode(",", $archiveTables);
		Piwik_Query( $query );
	}
	
	private function isArchiveTable ( $tableName )
	{
		return preg_match ( '/'.Piwik_Common::prefixTable('archive_').'/', $tableName ) > 0;
	}
}
