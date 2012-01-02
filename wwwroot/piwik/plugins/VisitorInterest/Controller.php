<?php
/**
 * Piwik - Open source web analytics
 * 
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id: Controller.php 4588 2011-04-28 10:48:23Z matt $
 * 
 * @category Piwik_Plugins
 * @package Piwik_VisitorInterest
 */

/**
 * @package Piwik_VisitorInterest
 */
class Piwik_VisitorInterest_Controller extends Piwik_Controller 
{
	function index()
	{
		$view = Piwik_View::factory('index');
		$view->dataTableNumberOfVisitsPerVisitDuration = $this->getNumberOfVisitsPerVisitDuration(true);
		$view->dataTableNumberOfVisitsPerPage = $this->getNumberOfVisitsPerPage(true);
		echo $view->render();
	}
	
	function getNumberOfVisitsPerVisitDuration( $fetch = false)
	{
		$view = Piwik_ViewDataTable::factory( 'cloud' );
		$view->init( $this->pluginName,  __FUNCTION__, "VisitorInterest.getNumberOfVisitsPerVisitDuration" );
		
		$view->setColumnsToDisplay( array('label','nb_visits') );
		$view->setSortedColumn( 'label', 'asc' );
		$view->setColumnTranslation('label', Piwik_Translate('VisitorInterest_ColumnVisitDuration'));
		$view->disableSort();
		$view->disableExcludeLowPopulation();
		$view->disableOffsetInformationAndPaginationControls();
		$view->disableSearchBox();
		$view->disableShowAllColumns();
		
		return $this->renderView($view, $fetch);
	}
	
	function getNumberOfVisitsPerPage( $fetch = false)
	{
		$view = Piwik_ViewDataTable::factory( 'cloud' );
		$view->init( $this->pluginName,  __FUNCTION__, "VisitorInterest.getNumberOfVisitsPerPage" );
		$view->setColumnsToDisplay( array('label','nb_visits') );
		$view->setSortedColumn( 'label', 'asc' );
		$view->setColumnTranslation('label', Piwik_Translate('VisitorInterest_ColumnPagesPerVisit'));
		$view->disableExcludeLowPopulation();
		$view->disableOffsetInformationAndPaginationControls();
		$view->disableSearchBox();
		$view->disableSort();
		$view->disableShowAllColumns();
		
		return $this->renderView($view, $fetch);
	}
}
