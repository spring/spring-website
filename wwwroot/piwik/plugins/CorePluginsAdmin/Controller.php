<?php
/**
 * Piwik - Open source web analytics
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id: Controller.php 4856 2011-06-03 15:19:10Z peterb $
 *
 * @category Piwik_Plugins
 * @package Piwik_CorePluginsAdmin
 */

/**
 *
 * @package Piwik_CorePluginsAdmin
 */
class Piwik_CorePluginsAdmin_Controller extends Piwik_Controller_Admin
{
	function index()
	{
		Piwik::checkUserIsSuperUser();

		$plugins = array();

		$listPlugins = Piwik_PluginsManager::getInstance()->readPluginsDirectory();
		foreach($listPlugins as $pluginName)
		{
			$oPlugin = Piwik_PluginsManager::getInstance()->loadPlugin($pluginName);
			$plugins[$pluginName] = array(
			 	'activated' => Piwik_PluginsManager::getInstance()->isPluginActivated($pluginName),
				'alwaysActivated' => Piwik_PluginsManager::getInstance()->isPluginAlwaysActivated($pluginName),
			);
		}
		Piwik_PluginsManager::getInstance()->loadPluginTranslations();

		$loadedPlugins = Piwik_PluginsManager::getInstance()->getLoadedPlugins();
		foreach($loadedPlugins as $oPlugin)
		{
			$pluginName = $oPlugin->getPluginName();
			$plugins[$pluginName]['info'] = $oPlugin->getInformation();
		}

		$view = Piwik_View::factory('manage');
		$view->pluginsName = $plugins;
		$this->setBasicVariablesView($view);
		$view->menu = Piwik_GetAdminMenu();
		if(!Zend_Registry::get('config')->isFileWritable())
		{
			$view->configFileNotWritable = true;
		}
		echo $view->render();
	}

	public function deactivate($redirectAfter = true)
	{
		Piwik::checkUserIsSuperUser();
		$this->checkTokenInUrl();
		$pluginName = Piwik_Common::getRequestVar('pluginName', null, 'string');
		Piwik_PluginsManager::getInstance()->deactivatePlugin($pluginName);
		if($redirectAfter)
		{
			Piwik_Url::redirectToReferer();
		}
	}

	public function activate($redirectAfter = true)
	{
		Piwik::checkUserIsSuperUser();
		$this->checkTokenInUrl();
		$pluginName = Piwik_Common::getRequestVar('pluginName', null, 'string');
		Piwik_PluginsManager::getInstance()->activatePlugin($pluginName);
		if($redirectAfter)
		{
			Piwik_Url::redirectToReferer();
		}
	}
}
