<?php
/**
 * Piwik - Open source web analytics
 * 
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id: AddColumnsProcessedMetrics.php 5235 2011-09-27 07:20:45Z matt $
 * 
 * @category Piwik
 * @package Piwik
 */

/**
 * @package Piwik
 * @subpackage Piwik_DataTable
 */
class Piwik_DataTable_Filter_AddColumnsProcessedMetrics extends Piwik_DataTable_Filter
{
	protected $invalidDivision = 0;
	protected $roundPrecision = 2;
	protected $deleteRowsWithNoVisit = true;
	
	/**
	 * @param Piwik_DataTable $table
	 * @param bool $enable Automatically set to true when filter_add_columns_when_show_all_columns is found in the API request
	 * @return void
	 */
	public function __construct( $table, $deleteRowsWithNoVisit = true )
	{
		$this->deleteRowsWithNoVisit = $deleteRowsWithNoVisit;
		parent::__construct($table);
	}
	
	public function filter($table)
	{
		$rowsIdToDelete = array();	
		$bounceRateColumnWasSet = false;	
		foreach($table->getRows() as $key => $row)
		{
			$nbVisits = $this->getColumn($row, Piwik_Archive::INDEX_NB_VISITS);
			$nbActions = $this->getColumn($row, Piwik_Archive::INDEX_NB_ACTIONS);
			if($nbVisits == 0
				&& $nbActions == 0
				&& $this->deleteRowsWithNoVisit)
			{
				// case of keyword/website/campaign with a conversion for this day, 
				// but no visit, we don't show it  
				$rowsIdToDelete[] = $key;
				continue;
			}
			
			$nbVisitsConverted = (int)$this->getColumn($row, Piwik_Archive::INDEX_NB_VISITS_CONVERTED);
			if($nbVisitsConverted > 0)
			{
				$conversionRate = round(100 * $nbVisitsConverted / $nbVisits, $this->roundPrecision);
				$row->addColumn('conversion_rate', $conversionRate."%");
			}
			
			if($nbVisits == 0)
			{
				$actionsPerVisit = $averageTimeOnSite = $bounceRate = $this->invalidDivision;
			}
			else
			{
				// nb_actions / nb_visits => Actions/visit
				// sum_visit_length / nb_visits => Avg. Time on Site 
				// bounce_count / nb_visits => Bounce Rate
				$actionsPerVisit = round($nbActions / $nbVisits, $this->roundPrecision);
				$averageTimeOnSite = round($this->getColumn($row, Piwik_Archive::INDEX_SUM_VISIT_LENGTH) / $nbVisits, $rounding = 0);
				$bounceRate = round(100 * $this->getColumn($row, Piwik_Archive::INDEX_BOUNCE_COUNT) / $nbVisits, $this->roundPrecision);
			}
			
			$row->addColumn('nb_actions_per_visit', $actionsPerVisit);
			$row->addColumn('avg_time_on_site', $averageTimeOnSite);
			try {
				$row->addColumn('bounce_rate', $bounceRate."%");
			} catch(Exception $e) {
				$bounceRateColumnWasSet = true;
			}
			$this->filterSubTable($row);
		}
		$table->deleteRows($rowsIdToDelete);
	}
	
	/**
	 * Returns column from a given row.
	 * Will work with 2 types of datatable
	 * - raw datatables coming from the archive DB, which columns are int indexed
	 * - datatables processed resulting of API calls, which columns have human readable english names
	 * 
	 * @param Piwik_DataTable_Row $row
	 * @param int $columnIdRaw see consts in Piwik_Archive::
	 * @return mixed Value of column, false if not found
	 */
	protected function getColumn($row, $columnIdRaw, $mappingIdToName = false)
	{
		if(empty($mappingIdToName))
		{
			$mappingIdToName = Piwik_Archive::$mappingFromIdToName;
		}
		$columnIdReadable = $mappingIdToName[$columnIdRaw];
		if($row instanceof Piwik_DataTable_Row)
		{
    		$raw = $row->getColumn($columnIdRaw);
    		if($raw !== false)
    		{
    			return $raw;
    		}
    		return $row->getColumn($columnIdReadable);
		}
		if(isset($row[$columnIdRaw]))
		{
			return $row[$columnIdRaw];
		}
		if(isset($row[$columnIdReadable]))
		{
			return $row[$columnIdReadable];
		}
		return false;
	}
	
}
