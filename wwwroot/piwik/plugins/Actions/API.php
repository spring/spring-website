<?php
/**
 * Piwik - Open source web analytics
 * 
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id: API.php 5235 2011-09-27 07:20:45Z matt $
 * 
 * @category Piwik_Plugins
 * @package Piwik_Actions
 */

/**
 * The Actions API lets you request reports for all your Visitor Actions: Page URLs, Page titles (Piwik Events),
 * File Downloads and Clicks on external websites.
 * 
 * For example, "getPageTitles" will return all your page titles along with standard <a href='http://piwik.org/docs/analytics-api/reference/#toc-metric-definitions' target='_blank'>Actions metrics</a> for each row.
 * 
 * It is also possible to request data for a specific Page Title with "getPageTitle" 
 * and setting the parameter pageName to the page title you wish to request. 
 * Similarly, you can request metrics for a given Page URL via "getPageUrl", a Download file via "getDownload" 
 * and an outlink via "getOutlink".
 * 
 * Note: pageName, pageUrl, outlinkUrl, downloadUrl parameters must be URL encoded before you call the API.
 * @package Piwik_Actions
 */
class Piwik_Actions_API
{
	static private $instance = null;
	
	static public function getInstance()
	{
		if (self::$instance == null)
		{
			self::$instance = new self;
		}
		return self::$instance;
	}
	

	/**
	 * Backward compatibility. Fallsback to getPageTitles() instead.
	 * @deprecated Deprecated since Piwik 0.5
	 * @ignore
	 */
	public function getActions( $idSite, $period, $date, $segment = false, $expanded = false, $idSubtable = false )
	{
	    return $this->getPageTitles( $idSite, $period, $date, $segment, $expanded, $idSubtable );
	}
	
	public function getPageUrls( $idSite, $period, $date, $segment = false, $expanded = false, $idSubtable = false )
	{
		$dataTable = Piwik_Archive::getDataTableFromArchive('Actions_actions_url', $idSite, $period, $date, $segment, $expanded, $idSubtable );
		$this->filterPageDatatable($dataTable);
		$this->filterActionsDataTable($dataTable, $expanded);
		return $dataTable;
	}
	
	public function getPageUrl( $pageUrl, $idSite, $period, $date, $segment = false)
	{
		$callBackParameters = array('Actions_actions_url', $idSite, $period, $date, $segment, $expanded = false, $idSubtable = false );
		$dataTable = $this->getFilterPageDatatableSearch($callBackParameters, $pageUrl, Piwik_Tracker_Action::TYPE_ACTION_URL);
		$this->filterPageDatatable($dataTable);
		$this->filterActionsDataTable($dataTable);
		return $dataTable;
	}
	
	public function getPageTitles( $idSite, $period, $date, $segment = false, $expanded = false, $idSubtable = false)
	{
		$dataTable = Piwik_Archive::getDataTableFromArchive('Actions_actions', $idSite, $period, $date, $segment, $expanded, $idSubtable);
		$this->filterPageDatatable($dataTable);
		$this->filterActionsDataTable($dataTable, $expanded);
		return $dataTable;
	}
	
	public function getPageTitle( $pageName, $idSite, $period, $date, $segment = false)
	{
		$callBackParameters = array('Actions_actions', $idSite, $period, $date, $segment, $expanded = false, $idSubtable = false );
		$dataTable = $this->getFilterPageDatatableSearch($callBackParameters, $pageName, Piwik_Tracker_Action::TYPE_ACTION_NAME);
		$this->filterActionsDataTable($dataTable);
		$this->filterPageDatatable($dataTable);
		return $dataTable;
	}
	
	public function getDownloads( $idSite, $period, $date, $segment = false, $expanded = false, $idSubtable = false )
	{
		$dataTable = Piwik_Archive::getDataTableFromArchive('Actions_downloads', $idSite, $period, $date, $segment, $expanded, $idSubtable );
		$this->filterActionsDataTable($dataTable, $expanded);
		return $dataTable;
	}

	public function getDownload( $downloadUrl, $idSite, $period, $date, $segment = false)
	{
		$callBackParameters = array('Actions_downloads', $idSite, $period, $date, $segment, $expanded = false, $idSubtable = false );
		$dataTable = $this->getFilterPageDatatableSearch($callBackParameters, $downloadUrl, Piwik_Tracker_Action::TYPE_DOWNLOAD);
		$this->filterActionsDataTable($dataTable);
		return $dataTable;
	}
	
	public function getOutlinks( $idSite, $period, $date, $segment = false, $expanded = false, $idSubtable = false )
	{
		$dataTable = Piwik_Archive::getDataTableFromArchive('Actions_outlink', $idSite, $period, $date, $segment, $expanded, $idSubtable );
		$this->filterActionsDataTable($dataTable, $expanded);
		return $dataTable;
	}

	public function getOutlink( $outlinkUrl, $idSite, $period, $date, $segment = false)
	{
		$callBackParameters = array('Actions_outlink', $idSite, $period, $date, $segment, $expanded = false, $idSubtable = false );
		$dataTable = $this->getFilterPageDatatableSearch($callBackParameters, $outlinkUrl, Piwik_Tracker_Action::TYPE_OUTLINK);
		$this->filterActionsDataTable($dataTable);
		return $dataTable;
	}
	
	/**
	 * Will search in the DataTable for a Label matching the searched string
	 * and return only the matching row, or an empty datatable
	 */
	protected function getFilterPageDatatableSearch($callBackParameters, $search, $actionType, $table = false,
			$searchTree = false)
	{
		if ($searchTree === false)
		{
			// build the query parts that are searched inside the tree
    		if($actionType == Piwik_Tracker_Action::TYPE_ACTION_NAME)
    		{
    			$searchedString = Piwik_Common::unsanitizeInputValue($search);
    		}
    		else
    		{
				$idSite = $callBackParameters[1];
				try {
					$searchedString = Piwik_Tracker_Action::excludeQueryParametersFromUrl($search, $idSite);
				} catch(Exception $e) {
					$searchedString = $search;
				}
    		}
			$searchTree = Piwik_Actions::getActionExplodedNames($searchedString, $actionType);
		}

		if ($table === false)
		{
			// fetch the data table
			$table = call_user_func_array(array('Piwik_Archive', 'getDataTableFromArchive'), $callBackParameters);
			
			if ($table instanceof Piwik_DataTable_Array)
			{
				// search an array of tables, e.g. when using date=last30
				// note that if the root is an array, we filter all children
				// if an array occurs inside the nested table, we only look for the first match (see below)
				$newTableArray = new Piwik_DataTable_Array;
				$newTableArray->metadata = $table->metadata;
				$newTableArray->setKeyName($table->getKeyName());
				
				foreach ($table->getArray() as $label => $subTable)
				{
					$subTable = $this->doFilterPageDatatableSearch($callBackParameters, $subTable, $searchTree);
					$newTableArray->addTable($subTable, $label);
				}
				
				return $newTableArray;
			}
			
		}

		return $this->doFilterPageDatatableSearch($callBackParameters, $table, $searchTree);
	}

	protected function doFilterPageDatatableSearch($callBackParameters, $table, $searchTree)
	{
		// filter a data table array
		if ($table instanceof Piwik_DataTable_Array)
		{
			foreach ($table->getArray() as $subTable)
			{
				$filteredSubTable = $this->doFilterPageDatatableSearch($callBackParameters,	$subTable, $searchTree);

				if ($filteredSubTable->getRowsCount() > 0)
				{
					// match found in a sub table, return and stop searching the others
					return $filteredSubTable;
				}
			}

			// nothing found in all sub tables
			return new Piwik_DataTable;
		}

		// filter regular data table
		if ($table instanceof Piwik_DataTable)
		{
			// search for the first part of the tree search
			$search = array_shift($searchTree);
			$row = $table->getRowFromLabel($search);
			if ($row === false)
			{
				// not found
				return new Piwik_DataTable;
			}

			// end of tree search reached
			if (count($searchTree) == 0)
			{
				$table = new Piwik_DataTable();
				$table->addRow($row);
				return $table;
			}

			// match found on this level and more levels remaining: go deeper
			$idSubTable = $row->getIdSubDataTable();
			$callBackParameters[6] = $idSubTable;
			$table = call_user_func_array(array('Piwik_Archive', 'getDataTableFromArchive'), $callBackParameters);
			return $this->doFilterPageDatatableSearch($callBackParameters, $table, $searchTree);
		}

		throw new Exception("For this API function, DataTable ".get_class($table)." is not supported");
	}
	
	/**
	 * Common filters for Page URLs and Page Titles
	 */
	protected function filterPageDatatable($dataTable)
	{
		// Average time on page = total time on page / number visits on that page
		$dataTable->queueFilter('ColumnCallbackAddColumnQuotient', array('avg_time_on_page', 'sum_time_spent', 'nb_visits', 0));
		
		// Bounce rate = single page visits on this page / visits started on this page
		$dataTable->queueFilter('ColumnCallbackAddColumnPercentage', array('bounce_rate', 'entry_bounce_count', 'entry_nb_visits', 0));
		
		// % Exit = Number of visits that finished on this page / visits on this page
		$dataTable->queueFilter('ColumnCallbackAddColumnPercentage', array('exit_rate', 'exit_nb_visits', 'nb_visits', 0));
	}
	
	/**
	 * Common filters for all Actions API getters
	 */
	protected function filterActionsDataTable($dataTable, $expanded = false)
	{
		// Must be applied before Sort in this case, since the DataTable can contain both int and strings indexes 
		// (in the transition period between pre 1.2 and post 1.2 datatable structure)
		$dataTable->filter('ReplaceColumnNames');
		$dataTable->filter('Sort', array('nb_visits', 'desc', $naturalSort = false, $expanded));
		
		$dataTable->queueFilter('ReplaceSummaryRowLabel');
	}
}

