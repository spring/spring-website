<?php
/**
 * Piwik - Open source web analytics
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id: Controller.php 4634 2011-05-05 08:56:37Z EZdesign $
 *
 * @category Piwik_Plugins
 * @package Piwik_VisitsSummary
 */

/**
 *
 * @package Piwik_VisitsSummary
 */
class Piwik_VisitsSummary_Controller extends Piwik_Controller
{
	public function index()
	{
		$view = Piwik_View::factory('index');
		$this->setPeriodVariablesView($view);
		$view->graphEvolutionVisitsSummary = $this->getEvolutionGraph( true, array('nb_visits') );
		$this->setSparklinesAndNumbers($view);
		echo $view->render();
	}
	
	public function getSparklines()
	{
		$view = Piwik_View::factory('sparklines');
		$this->setPeriodVariablesView($view);
		$this->setSparklinesAndNumbers($view);
		echo $view->render();
	}

	public function getEvolutionGraph( $fetch = false, $columns = false)
	{
		$view = $this->getLastUnitGraph($this->pluginName, __FUNCTION__, "VisitsSummary.get");
		if(empty($columns))
		{
			$columns = Piwik_Common::getRequestVar('columns');
		}
		$columns = !is_array($columns) ? array($columns) : $columns;
		$view->setColumnsToDisplay($columns);
		
		$doc = Piwik_Translate('VisitsSummary_VisitsSummaryDocumentation').'<br />'
		     . Piwik_Translate('General_BrokenDownReportDocumentation').'<br /><br />'
		     
		     . '<b>'.Piwik_Translate('General_ColumnNbVisits').':</b> '
		     . Piwik_Translate('General_ColumnNbVisitsDocumentation').'<br />'
		     
		     . '<b>'.Piwik_Translate('General_ColumnNbUniqVisitors').':</b> '
		     . Piwik_Translate('General_ColumnNbUniqVisitorsDocumentation').'<br />'
		     
		     . '<b>'.Piwik_Translate('General_ColumnNbActions').':</b> '
		     . Piwik_Translate('General_ColumnNbActionsDocumentation').'<br />'
		     
		     . '<b>'.Piwik_Translate('General_ColumnActionsPerVisit').':</b> '
		     . Piwik_Translate('General_ColumnActionsPerVisitDocumentation');
		
		$view->setReportDocumentation($doc);
		
		return $this->renderView($view, $fetch);
	}

	static public function getVisitsSummary()
	{
		$requestString =	"method=VisitsSummary.get".
							"&format=original".
							// we disable filters for example "search for pattern", in the case this method is called
							// by a method that already calls the API with some generic filters applied
							"&disable_generic_filters=1";
		$request = new Piwik_API_Request($requestString);
		return $request->process();
	}

	static public function getVisits()
	{
		$requestString = 	"method=VisitsSummary.getVisits".
							"&format=original".
							"&disable_generic_filters=1";
		$request = new Piwik_API_Request($requestString);
		return $request->process();
	}
	
	protected function setSparklinesAndNumbers($view)
	{
		$view->urlSparklineNbVisits 		= $this->getUrlSparkline( 'getEvolutionGraph', array('columns' => array('nb_visits')));
		$view->urlSparklineNbActions 		= $this->getUrlSparkline( 'getEvolutionGraph', array('columns' => array('nb_actions')));
		$view->urlSparklineAvgVisitDuration = $this->getUrlSparkline( 'getEvolutionGraph', array('columns' => array('avg_time_on_site')));
		$view->urlSparklineMaxActions 		= $this->getUrlSparkline( 'getEvolutionGraph', array('columns' => array('max_actions')));
		$view->urlSparklineActionsPerVisit 	= $this->getUrlSparkline( 'getEvolutionGraph', array('columns' => array('nb_actions_per_visit')));
		$view->urlSparklineBounceRate 		= $this->getUrlSparkline( 'getEvolutionGraph', array('columns' => array('bounce_rate')));
		
		$dataTableVisit = self::getVisitsSummary();
		$dataRow = $dataTableVisit->getFirstRow();
		$view->urlSparklineNbUniqVisitors 	= $this->getUrlSparkline( 'getEvolutionGraph', array('columns' => array('nb_uniq_visitors')));
		$view->nbUniqVisitors = $dataRow->getColumn('nb_uniq_visitors');
		$nbVisits = $dataRow->getColumn('nb_visits');
		$view->nbVisits = $nbVisits;
		$view->nbActions = $dataRow->getColumn('nb_actions');
		$view->averageVisitDuration = $dataRow->getColumn('avg_time_on_site');
		$nbBouncedVisits = $dataRow->getColumn('bounce_count');
		$view->bounceRate = Piwik::getPercentageSafe($nbBouncedVisits, $nbVisits);
		$view->maxActions = $dataRow->getColumn('max_actions');
		$view->nbActionsPerVisit = $dataRow->getColumn('nb_actions_per_visit');
	}
}
