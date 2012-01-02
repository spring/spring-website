<?php
/**
 * Piwik - Open source web analytics
 * 
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id: CorePluginsAdmin.php 2968 2010-08-20 15:26:33Z vipsoft $
 * 
 * @category Piwik_Plugins
 * @package Piwik_CorePluginsAdmin
 */

/**
 *
 * @package Piwik_CorePluginsAdmin
 */
class Piwik_CorePluginsAdmin extends Piwik_Plugin
{
	public function getInformation()
	{
		return array(
			'description' => Piwik_Translate('CorePluginsAdmin_PluginDescription'),
			'author' => 'Piwik',
			'author_homepage' => 'http://piwik.org/',
			'version' => Piwik_Version::VERSION,
		);
	}
	
	function getListHooksRegistered()
	{
		return array('AdminMenu.add' => 'addMenu');
	}
	
	function addMenu()
	{
		Piwik_AddAdminMenu('CorePluginsAdmin_MenuPlugins', 
							array('module' => 'CorePluginsAdmin', 'action' => 'index'),
							Piwik::isUserIsSuperUser(),
							$order = 7);		
	}
}
