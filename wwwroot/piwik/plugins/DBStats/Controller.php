<?php
/**
 * Piwik - Open source web analytics
 * 
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id: Controller.php 4451 2011-04-14 19:00:39Z vipsoft $
 * 
 * @category Piwik_Plugins
 * @package Piwik_DBStats
 */

/**
 *
 * @package Piwik_DBStats
 */
class Piwik_DBStats_Controller extends Piwik_Controller_Admin
{
	function index()
	{
		Piwik::checkUserIsSuperUser();
		$view = Piwik_View::factory('DBStats');
		$view->tablesStatus = Piwik_DBStats_API::getInstance()->getAllTablesStatus();
		$this->setBasicVariablesView($view);
		$view->menu = Piwik_GetAdminMenu();
		echo $view->render();		
	}
}
