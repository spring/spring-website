<?php
/**
 * Piwik - Open source web analytics
 * 
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id: Controller.php 3565 2011-01-03 05:49:45Z matt $
 * 
 * @category Piwik_Plugins
 * @package Piwik_Widgetize
 */

/**
 * 
 * @package Piwik_Widgetize
 */
class Piwik_Widgetize_Controller extends Piwik_Controller
{
	function index()
	{
		$view = Piwik_View::factory('index');
		$view->availableWidgets = json_encode(Piwik_GetWidgetsList());
		$this->setGeneralVariablesView($view);
		echo $view->render();
	}

	function testJsInclude1()
	{
		$view = Piwik_View::factory('test_jsinclude');
		$view->url1 = '?module=Widgetize&action=js&moduleToWidgetize=UserSettings&actionToWidgetize=getBrowser&idSite=1&period=day&date=yesterday';
		$view->url2 = '?module=Widgetize&action=js&moduleToWidgetize=API&actionToWidgetize=index&method=ExamplePlugin.getGoldenRatio&format=original';
		echo $view->render();
	}
	
	function testJsInclude2()
	{
		$view = Piwik_View::factory('test_jsinclude2');
		$view->url1 = '?module=Widgetize&action=js&moduleToWidgetize=UserSettings&actionToWidgetize=getBrowser&idSite=1&period=day&date=yesterday';
		$view->url2 = '?module=Widgetize&action=js&moduleToWidgetize=UserCountry&actionToWidgetize=getCountry&idSite=1&period=day&date=yesterday&viewDataTable=cloud&show_footer=0';
		$view->url3 = '?module=Widgetize&action=js&moduleToWidgetize=Referers&actionToWidgetize=getKeywords&idSite=1&period=day&date=yesterday&viewDataTable=table&show_footer=0';
		echo $view->render();
	}
	
	/**
	 * Disabled for now, not obvious that this is useful (iframe sounds like a better solution)
	 */
	private function js()
	{
		Piwik_API_Request::reloadAuthUsingTokenAuth();
		$controllerName = Piwik_Common::getRequestVar('moduleToWidgetize');
		$actionName = Piwik_Common::getRequestVar('actionToWidgetize');
		$parameters = array ( $fetch = true );
		$content = Piwik_FrontController::getInstance()->fetchDispatch( $controllerName, $actionName, $parameters);
		$view = Piwik_View::factory('js');
		$content = str_replace(array("\t","\n","\r\n","\r"), "", $content);
		$view->content = $content;
		echo $view->render();
	}

	function iframe()
	{		
		Piwik_API_Request::reloadAuthUsingTokenAuth();
		$this->init();
		$controllerName = Piwik_Common::getRequestVar('moduleToWidgetize');
		$actionName = Piwik_Common::getRequestVar('actionToWidgetize');
		$parameters = array ( $fetch = true );
		$outputDataTable = Piwik_FrontController::getInstance()->fetchDispatch( $controllerName, $actionName, $parameters);
		$view = Piwik_View::factory('iframe');
		$this->setGeneralVariablesView($view);
		$view->content = $outputDataTable;
		echo $view->render();
	}
}
