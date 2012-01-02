<?php
/**
 * Piwik - Open source web analytics
 * 
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id: Controller.php 5296 2011-10-14 02:52:44Z matt $
 * 
 * @category Piwik_Plugins
 * @package Piwik_ImageGraph
 */

class Piwik_ImageGraph_Controller extends Piwik_Controller
{
	// Call metadata reports, and draw the default graph for each report.
	public function index()
	{
		Piwik::checkUserHasSomeAdminAccess();
		$idSite	= Piwik_Common::getRequestVar('idSite', 1, 'int');
		$period = Piwik_Common::getRequestVar('period', 'day', 'string');
		$date = Piwik_Common::getRequestVar('date', 'today', 'string');
		$_GET['token_auth'] = Piwik::getCurrentUserTokenAuth();
		$reports = Piwik_API_API::getInstance()->getReportMetadata($idSite, $period, $date);
		$plot = array();
		foreach($reports as $report)
		{
			if(!empty($report['imageGraphUrl']))
			{
				$plot[] = array(
					// Title
					$report['category'] . ' › ' . $report['name'],
					//URL
					Piwik::getPiwikUrl() . $report['imageGraphUrl'] . '&height=150&width=460'
				);
			}
		}
		$view = Piwik_View::factory('index');
		$view->titleAndUrls = $plot;
		echo $view->render(); 
	}
	
	// Draw graphs for all sizes (DEBUG)
    public function testAllSizes()
	{
		Piwik::checkUserIsSuperUser();
		
		$view = Piwik_View::factory('debug_graphs_all_sizes');
		$this->setGeneralVariablesView($view);
		
		$availableReports = Piwik_API_API::getInstance()->getReportMetadata($this->idSite);
		$view->availableReports = $availableReports;
		$view->graphTypes = array(
			'evolution',
			'verticalBar',
			'pie',
			'3dPie'
		);
		$view->graphSizes = array(
			array(600, 250), // standard graph size
			array(460, 150), // standard phone
			array(300, 150), // standard phone 2
			array(240, 150), // smallest mobile display
			array(800, 150), // landscape mode
			array(600, 300, $fontSize = 18, 300, 150), // iphone requires bigger font, then it will be scaled down by ios
		
		);
		echo $view->render();
	}

}