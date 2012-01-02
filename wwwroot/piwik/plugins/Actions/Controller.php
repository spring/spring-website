<?php
/**
 * Piwik - Open source web analytics
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id: Controller.php 4634 2011-05-05 08:56:37Z EZdesign $
 *
 * @category Piwik_Plugins
 * @package Piwik_Actions
 */

/**
 * Actions controller
 *
 * @package Piwik_Actions
 */
class Piwik_Actions_Controller extends Piwik_Controller
{
	const ACTIONS_REPORT_ROWS_DISPLAY = 100;
	
	protected function getPageUrlsView($currentAction, $controllerActionSubtable)
	{
		$view = Piwik_ViewDataTable::factory();
		$view->init(  	$this->pluginName,
						$currentAction,
						'Actions.getPageUrls',
						$controllerActionSubtable );
		$view->setColumnTranslation('label', Piwik_Translate('Actions_ColumnPageURL'));
		return $view;
	}
	
	
	/**
	 * PAGES
	 */
	
	public function indexPageUrls($fetch = false)
	{
		return Piwik_View::singleReport(
				Piwik_Translate('Actions_SubmenuPages'),
				$this->getPageUrls(true), $fetch);
	}
	
	public function getPageUrls($fetch = false)
	{
		$view = $this->getPageUrlsView(__FUNCTION__, 'getPageUrlsSubDataTable');
		$this->configureViewPages($view);
		$this->configureViewActions($view);
		return $this->renderView($view, $fetch);
	}
	
	public function getPageUrlsSubDataTable($fetch = false)
	{
		$view = $this->getPageUrlsView(__FUNCTION__, 'getPageUrlsSubDataTable');
		$this->configureViewPages($view);
		$this->configureViewActions($view);
		return $this->renderView($view, $fetch);
	}

	protected function configureViewPages($view)
	{
		$view->setColumnsToDisplay( array('label','nb_hits','nb_visits', 'bounce_rate', 'avg_time_on_page', 'exit_rate') );
	}
	
	
	/**
	 * ENTRY PAGES
	 */
	
	public function indexEntryPageUrls($fetch = false)
	{
		return Piwik_View::singleReport(
				Piwik_Translate('Actions_SubmenuPagesEntry'),
				$this->getEntryPageUrls(true), $fetch);
	}
	
	public function getEntryPageUrls($fetch = false) {
		$view = $this->getPageUrlsView(__FUNCTION__, 'getEntryPageUrlsSubDataTable');
		$this->configureViewEntryPageUrls($view);
		$this->configureViewActions($view);
		return $this->renderView($view, $fetch);
	}
	
	public function getEntryPageUrlsSubDataTable($fetch = false)
	{
		$view = $this->getPageUrlsView(__FUNCTION__, 'getEntryPageUrlsSubDataTable');
		$this->configureViewEntryPageUrls($view);
		$this->configureViewActions($view);
		return $this->renderView($view, $fetch);
	}
	
	protected function configureViewEntryPageUrls($view)
	{
		$view->setSortedColumn('entry_nb_visits');
		$view->setColumnsToDisplay( array('label','entry_nb_visits', 'entry_bounce_count', 'bounce_rate') );
		$view->setColumnTranslation('entry_bounce_count', Piwik_Translate('General_ColumnBounces'));
		$view->setColumnTranslation('entry_nb_visits', Piwik_Translate('General_ColumnEntrances'));
		// remove pages that are not entry pages
		$view->queueFilter('ColumnCallbackDeleteRow', array('entry_nb_visits', 'strlen'), $priorityFilter = true);
		
		$view->setMetricDocumentation('entry_nb_visits', Piwik_Translate('General_ColumnEntrancesDocumentation'));
		$view->setMetricDocumentation('entry_bounce_count', Piwik_Translate('General_ColumnBouncesDocumentation'));
		$view->setMetricDocumentation('bounce_rate', Piwik_Translate('General_ColumnBounceRateForPageDocumentation'));
		$view->setReportDocumentation(Piwik_Translate('Actions_EntryPagesReportDocumentation', '<br />').' '
				.Piwik_Translate('General_UsePlusMinusIconsDocumentation'));
	}
	
	
	/**
	 * EXIT PAGES
	 */

	public function indexExitPageUrls($fetch = false)
	{
		return Piwik_View::singleReport(
				Piwik_Translate('Actions_SubmenuPagesExit'),
				$this->getExitPageUrls(true), $fetch);
	}
	
	public function getExitPageUrls($fetch = false)
	{
		$view = $this->getPageUrlsView(__FUNCTION__, 'getExitPageUrlsSubDataTable');
		$this->configureViewExitPageUrls($view);
		$this->configureViewActions($view);
		return $this->renderView($view, $fetch);
	}
	
	public function getExitPageUrlsSubDataTable($fetch = false)
	{
		$view = $this->getPageUrlsView(__FUNCTION__, 'getExitPageUrlsSubDataTable');
		$this->configureViewExitPageUrls($view);
		$this->configureViewActions($view);
		return $this->renderView($view, $fetch);
	}
	
	protected function configureViewExitPageUrls($view)
	{
		$view->setSortedColumn('exit_nb_visits');
		$view->setColumnsToDisplay( array('label', 'exit_nb_visits', 'nb_visits', 'exit_rate') );
		$view->setColumnTranslation('exit_nb_visits', Piwik_Translate('General_ColumnExits'));
		// remove pages that are not exit pages
		$view->queueFilter('ColumnCallbackDeleteRow', array('exit_nb_visits', 'strlen'), $priorityFilter = true);
		
		$view->setMetricDocumentation('exit_nb_visits', Piwik_Translate('General_ColumnExitsDocumentation'));
		$view->setMetricDocumentation('nb_visits', Piwik_Translate('General_ColumnUniquePageviewsDocumentation'));
		$view->setMetricDocumentation('exit_rate', Piwik_Translate('General_ColumnExitRateDocumentation'));
		$view->setReportDocumentation(Piwik_Translate('Actions_ExitPagesReportDocumentation', '<br />').' '
				.Piwik_Translate('General_UsePlusMinusIconsDocumentation'));
	}
	
	
	/**
	 * PAGE TITLES
	 */
	
	public function indexPageTitles($fetch = false)
	{
		return Piwik_View::singleReport(
				Piwik_Translate('Actions_SubmenuPageTitles'),
				$this->getPageTitles(true), $fetch);
	}
	
	public function getPageTitles($fetch = false)
	{
		$view = Piwik_ViewDataTable::factory();
		$view->init(  	$this->pluginName,
						__FUNCTION__,
						'Actions.getPageTitles',
						'getPageTitlesSubDataTable' );
		$view->setColumnTranslation('label', Piwik_Translate('Actions_ColumnPageName'));
		$this->configureViewPages($view);
		$this->configureViewActions($view);
		return $this->renderView($view, $fetch);
	}

	public function getPageTitlesSubDataTable($fetch = false)
	{
		$view = Piwik_ViewDataTable::factory();
		$view->init(  	$this->pluginName,
						__FUNCTION__,
						'Actions.getPageTitles',
						'getPageTitlesSubDataTable'  );
		$this->configureViewPages($view);
		$this->configureViewActions($view);
		return $this->renderView($view, $fetch);
	}

	
	/**
	 * DOWNLOADS
	 */
	
	public function indexDownloads($fetch = false)
	{
		return Piwik_View::singleReport(
				Piwik_Translate('Actions_SubmenuDownloads'),
				$this->getDownloads(true), $fetch);
	}
	
	public function getDownloads($fetch = false)
	{
		$view = Piwik_ViewDataTable::factory();
		$view->init(  	$this->pluginName,
						__FUNCTION__,
						'Actions.getDownloads',
						'getDownloadsSubDataTable' );
		
		$this->configureViewDownloads($view);
		return $this->renderView($view, $fetch);
	}
	
	public function getDownloadsSubDataTable($fetch = false)
	{
		$view = Piwik_ViewDataTable::factory();
		$view->init(  	$this->pluginName,
						__FUNCTION__,
						'Actions.getDownloads',
						'getDownloadsSubDataTable');
		$this->configureViewDownloads($view);
		return $this->renderView($view, $fetch);
	}
	
	
	/**
	 * OUTLINKS
	 */

	public function indexOutlinks($fetch = false)
	{
		return Piwik_View::singleReport(
				Piwik_Translate('Actions_SubmenuOutlinks'),
				$this->getOutlinks(true), $fetch);
	}
	
	public function getOutlinks($fetch = false)
	{
		$view = Piwik_ViewDataTable::factory();
		$view->init(  	$this->pluginName,
						__FUNCTION__,
						'Actions.getOutlinks',
						'getOutlinksSubDataTable' );
		$this->configureViewOutlinks($view);
		return $this->renderView($view, $fetch);
	}
	
	public function getOutlinksSubDataTable($fetch = false)
	{
		$view = Piwik_ViewDataTable::factory();
		$view->init(	$this->pluginName,
						__FUNCTION__,
						'Actions.getOutlinks',
						'getOutlinksSubDataTable');
		$this->configureViewOutlinks($view);
		return $this->renderView($view, $fetch);
	}

	/*
	 * Page titles & Page URLs reports
	 */
	protected function configureViewActions($view)
	{
		$view->setColumnTranslation('nb_hits', Piwik_Translate('General_ColumnPageviews'));
		$view->setColumnTranslation('nb_visits', Piwik_Translate('General_ColumnUniquePageviews'));
		$view->setColumnTranslation('avg_time_on_page', Piwik_Translate('General_ColumnAverageTimeOnPage'));
		$view->setColumnTranslation('bounce_rate', Piwik_Translate('General_ColumnBounceRate'));
		$view->setColumnTranslation('exit_rate', Piwik_Translate('General_ColumnExitRate'));
		$view->queueFilter('ColumnCallbackReplace', array('avg_time_on_page', array('Piwik', 'getPrettyTimeFromSeconds')));
		
		if(Piwik_Common::getRequestVar('enable_filter_excludelowpop', '0', 'string' ) != '0')
		{
			// computing minimum value to exclude
			$visitsInfo = Piwik_VisitsSummary_Controller::getVisitsSummary();
			$visitsInfo = $visitsInfo->getFirstRow();
			$nbActions = $visitsInfo->getColumn('nb_actions');
			$nbActionsLowPopulationThreshold = floor(0.02 * $nbActions); // 2 percent of the total number of actions
			// we remove 1 to make sure some actions/downloads are displayed in the case we have a very few of them
			// and each of them has 1 or 2 hits...
			$nbActionsLowPopulationThreshold = min($visitsInfo->getColumn('max_actions')-1, $nbActionsLowPopulationThreshold-1);
			
			$view->setExcludeLowPopulation( 'nb_hits', $nbActionsLowPopulationThreshold );
		}

		$this->configureGenericViewActions($view);
		return $view;
	}
	
	/*
	 * Downloads report
	 */
	protected function configureViewDownloads($view)
	{
		$view->setColumnsToDisplay( array('label','nb_visits','nb_hits') );
		$view->setColumnTranslation('label', Piwik_Translate('Actions_ColumnDownloadURL'));
		$view->setColumnTranslation('nb_visits', Piwik_Translate('Actions_ColumnUniqueDownloads'));
		$view->setColumnTranslation('nb_hits', Piwik_Translate('Actions_ColumnDownloads'));
		$view->disableExcludeLowPopulation();
		$this->configureGenericViewActions($view);
	}
	
	/*
	 * Outlinks report
	 */
	protected function configureViewOutlinks($view)
	{
		$view->setColumnsToDisplay( array('label','nb_visits','nb_hits') );
		$view->setColumnTranslation('label', Piwik_Translate('Actions_ColumnClickedURL'));
		$view->setColumnTranslation('nb_visits', Piwik_Translate('Actions_ColumnUniqueClicks'));
		$view->setColumnTranslation('nb_hits', Piwik_Translate('Actions_ColumnClicks'));
		$view->disableExcludeLowPopulation();
		$this->configureGenericViewActions($view);
	}

	/*
	 * Common to all Actions reports, how to use the custom Actions Datatable html
	 */
	protected function configureGenericViewActions($view)
	{
		$view->setTemplate('CoreHome/templates/datatable_actions.tpl');
		if(Piwik_Common::getRequestVar('idSubtable', -1) != -1)
		{
			$view->setTemplate('CoreHome/templates/datatable_actions_subdatable.tpl');
		}
		$currentlySearching = $view->setSearchRecursive();
		if($currentlySearching)
		{
			$view->setTemplate('CoreHome/templates/datatable_actions_recursive.tpl');
		}
		// disable Footer icons
		$view->disableShowAllViewsIcons();
		$view->disableShowAllColumns();
		
		$view->setLimit( self::ACTIONS_REPORT_ROWS_DISPLAY );
		$view->main();
		// we need to rewrite the phpArray so it contains all the recursive arrays
		if($currentlySearching)
		{
			$phpArrayRecursive = $this->getArrayFromRecursiveDataTable($view->getDataTable());
			$view->getView()->arrayDataTable = $phpArrayRecursive;
		}
	}
	
	protected function getArrayFromRecursiveDataTable( $dataTable, $depth = 0 )
	{
		$table = array();
		foreach($dataTable->getRows() as $row)
		{
			$phpArray = array();
			if(($idSubtable = $row->getIdSubDataTable()) !== null)
			{
				$subTable = Piwik_DataTable_Manager::getInstance()->getTable( $idSubtable );
					
				if($subTable->getRowsCount() > 0)
				{
					$phpArray = $this->getArrayFromRecursiveDataTable( $subTable, $depth + 1 );
				}
			}
			
			$label = $row->getColumn('label');
			$newRow = array(
				'level' => $depth,
				'columns' => $row->getColumns(),
				'metadata' => $row->getMetadata(),
				'idsubdatatable' => $row->getIdSubDataTable()
				);
			$table[] = $newRow;
			if(count($phpArray) > 0)
			{
				$table = array_merge( $table,  $phpArray);
			}
		}
		return $table;
	}
}
