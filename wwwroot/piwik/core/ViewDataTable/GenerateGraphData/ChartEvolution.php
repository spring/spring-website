<?php
/**
 * Piwik - Open source web analytics
 * 
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id: ChartEvolution.php 5296 2011-10-14 02:52:44Z matt $
 * 
 * @category Piwik
 * @package Piwik
 */

/**
 * Piwik_ViewDataTable_GenerateGraphData for the Evolution graph (eg. Last 30 days visits) using Piwik_Visualization_Chart_Evolution
 * 
 * @package Piwik
 * @subpackage Piwik_ViewDataTable
 */
class Piwik_ViewDataTable_GenerateGraphData_ChartEvolution extends Piwik_ViewDataTable_GenerateGraphData
{
	protected function checkStandardDataTable()
	{
		// DataTable_Array and DataTable allowed for the evolution chart
		if(!($this->dataTable instanceof Piwik_DataTable_Array)
			&& !($this->dataTable instanceof Piwik_DataTable))
		{
			throw new Exception("Unexpected data type to render.");
		}
	}
	
	protected function getViewDataTableId()
	{
		return 'generateDataChartEvolution';
	}
	
	function __construct()
	{
		$this->view = new Piwik_Visualization_Chart_Evolution();
	}
	
	protected function guessUnitFromRequestedColumnNames($requestedColumnNames, $idSite)
	{
		$nameToUnit = array(
			'_rate' => '%',
			'_revenue' => Piwik::getCurrency($idSite),
		);
		foreach($requestedColumnNames as $columnName)
		{
			foreach($nameToUnit as $pattern => $type)
			{
				if(strpos($columnName, $pattern) !== false)
				{
					return $type;
				}
			}
		}
		return false;
	}
	
	protected function loadDataTableFromAPI()
	{
		$period = Piwik_Common::getRequestVar('period');
		// period will be overriden when 'range' is requested in the UI
		// but the graph will display for each day of the range. 
		// Default 'range' behavior is to return the 'sum' for the range
		if($period == 'range')
		{
			$_GET['period'] = 'day';
		}
		// throws exception if no view access
		parent::loadDataTableFromAPI();
		if($period == 'range')
		{
			$_GET['period'] = $period;
		}
	}
	
	protected function initChartObjectData()
	{
		// if the loaded datatable is a simple DataTable, it is most likely a plugin plotting some custom data
		// we don't expect plugin developers to return a well defined Piwik_DataTable_Array 
		if($this->dataTable instanceof Piwik_DataTable)
		{
			return parent::initChartObjectData();
		}
		
		$this->dataTable->applyQueuedFilters();
		if(!($this->dataTable instanceof Piwik_DataTable_Array))
		{
			throw new Exception("Expecting a DataTable_Array with custom format to draw an evolution chart");
		}
		
		// the X label is extracted from the 'period' object in the table's metadata
		$xLabels = $uniqueIdsDataTable = array();
		foreach($this->dataTable->metadata as $idDataTable => $metadataDataTable)
		{
			//eg. "Aug 2009"
			$xLabels[] = $metadataDataTable['period']->getLocalizedShortString();
			// we keep track of all unique data table that we need to set a Y value for
			$uniqueIdsDataTable[] = $idDataTable;
		}
		
		$requestedColumnNames = $this->getColumnsToDisplay();
		$yAxisLabelToValue = array();
		foreach($this->dataTable->getArray() as $idDataTable => $dataTable)
		{
			foreach($dataTable->getRows() as $row)
			{
				$rowLabel = $row->getColumn('label');
				foreach($requestedColumnNames as $requestedColumnName)
				{
					$metricLabel = $this->getColumnTranslation($requestedColumnName);
					if($rowLabel !== false)
					{
						// eg. "Yahoo! (Visits)"
						$yAxisLabel = "$rowLabel ($metricLabel)";
					}
					else
					{
						// eg. "Visits"
						$yAxisLabel = $metricLabel;
					}
					if(($columnValue = $row->getColumn($requestedColumnName)) !== false)
					{
						$yAxisLabelToValue[$yAxisLabel][$idDataTable] = $columnValue;
					} 
				}
			}
		}
		
		// make sure all column values are set to at least zero (no gap in the graph) 
		$yAxisLabelToValueCleaned = array();
		$yAxisLabels = array();
		foreach($uniqueIdsDataTable as $uniqueIdDataTable)
		{
			foreach($yAxisLabelToValue as $yAxisLabel => $idDataTableToColumnValue)
			{
				$yAxisLabels[$yAxisLabel] = $yAxisLabel;
				if(isset($idDataTableToColumnValue[$uniqueIdDataTable]))
				{
					$columnValue = $idDataTableToColumnValue[$uniqueIdDataTable];
				}
				else
				{
					$columnValue = 0;
				}
				$yAxisLabelToValueCleaned[$yAxisLabel][] = $columnValue;
			}
		}
		$idSite = Piwik_Common::getRequestVar('idSite', null, 'int');
		
		$unit = $this->yAxisUnit;
		if(empty($unit))
		{
			$unit = $this->guessUnitFromRequestedColumnNames($requestedColumnNames, $idSite);
		}
		
		$this->view->setAxisXLabels($xLabels);
		$this->view->setAxisYValues($yAxisLabelToValueCleaned);
		$this->view->setAxisYLabels($yAxisLabels);
		$this->view->setAxisYUnit($unit);
		
		$countGraphElements = $this->dataTable->getRowsCount();
		$firstDatatable = reset($this->dataTable->metadata);
		$period = $firstDatatable['period'];
		switch($period->getLabel()) {
			case 'day': $steps = 7; break;
			case 'week': $steps = 10; break;
			case 'month': $steps = 6; break;
			case 'year': $steps = 2; break;
			default: $steps = 10; break;
		}
		// For Custom Date Range, when the number of elements plotted can be small, make sure the X legend is useful
		if($countGraphElements <= 20 ) 
		{
			$steps = 2;
		}
		
		$this->view->setXSteps($steps);
		
		if($this->isLinkEnabled())
		{
			$axisXOnClick = array();
			$queryStringAsHash = $this->getQueryStringAsHash();
			foreach($this->dataTable->metadata as $idDataTable => $metadataDataTable)
			{
				$period = $metadataDataTable['period'];
				$dateInUrl = $period->getDateStart();
				$parameters = array(
							'idSite' => $idSite,
							'period' => $period->getLabel(),
							'date' => $dateInUrl->toString()
				);
				$hash = '';
				if(!empty($queryStringAsHash))
				{
					$hash = '#' . Piwik_Url::getQueryStringFromParameters( $queryStringAsHash + $parameters);
				}
				$link = 'index.php?' .
						Piwik_Url::getQueryStringFromParameters( array(
							'module' => 'CoreHome',
							'action' => 'index',
						) + $parameters)
						. $hash;
				$axisXOnClick[] = $link;
			}
			$this->view->setAxisXOnClick($axisXOnClick);
		}
	}
	
	/**
	 * We link the graph dots to the same report as currently being displayed (only the date would change).
	 * 
	 * In some cases the widget is loaded within a report that doesn't exist as such.
	 * For example, the dashboards loads the 'Last visits graph' widget which can't be directly linked to.
	 * Instead, the graph must link back to the dashboard. 
	 * 
	 * In other cases, like Visitors>Overview or the Goals graphs, we can link the graph clicks to the same report.
	 * 
	 * To detect whether or not we can link to a report, we simply check if the current URL from which it was loaded
	 * belongs to the menu or not. If it doesn't belong to the menu, we do not append the hash to the URL, 
	 * which results in loading the dashboard.
	 * 
	 * @return array Query string array to append to the URL hash or false if the dashboard should be displayed
	 */
	private function getQueryStringAsHash()
	{
		$queryString = Piwik_Url::getArrayFromCurrentQueryString();
		$piwikParameters = array('idSite', 'date', 'period', 'XDEBUG_SESSION_START', 'KEY');
		foreach($piwikParameters as $parameter)
		{
			unset($queryString[$parameter]);
		}
		if(Piwik_IsMenuUrlFound($queryString))
		{
			return $queryString;
		}
		return false;
	}

	private function isLinkEnabled() 
	{
		static $linkEnabled;
		if(!isset($linkEnabled)) 
		{
			// 1) Custom Date Range always have link disabled, otherwise 
			// the graph data set is way too big and fails to display
			// 2) disableLink parameter is set in the Widgetize "embed" code
			$linkEnabled = !Piwik_Common::getRequestVar('disableLink', 0, 'int')
							&& Piwik_Common::getRequestVar('period', 'day') != 'range';
		}
		return $linkEnabled;
	}
}
