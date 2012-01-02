<?php
/**
 * Piwik - Open source web analytics
 * 
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id: SecurityInfo.php 2968 2010-08-20 15:26:33Z vipsoft $
 * 
 * @category Piwik_Plugins
 * @package Piwik_SecurityInfo
 */

/**
 *
 * @package Piwik_SecurityInfo
 */
class Piwik_SecurityInfo extends Piwik_Plugin
{	
	public function getInformation()
	{
		$info = array(
			'description' => Piwik_Translate('SecurityInfo_PluginDescription'),
			'author' => 'Piwik',
			'author_homepage' => 'http://piwik.org/',
			'version' => Piwik_Version::VERSION,
		);
		return $info;
	}
	
	function getListHooksRegistered()
	{
		return array(
			'AdminMenu.add' => 'addMenu',
		);
	}
	
	function addMenu()
	{
		Piwik_AddAdminMenu('SecurityInfo_Security', 
							array('module' => 'SecurityInfo', 'action' => 'index'),
							Piwik::isUserIsSuperUser(),
							$order = 10);		
	}
}
