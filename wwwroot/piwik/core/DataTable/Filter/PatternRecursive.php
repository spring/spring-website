<?php
/**
 * Piwik - Open source web analytics
 * 
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id: PatternRecursive.php 4169 2011-03-23 01:59:57Z matt $
 * 
 * @category Piwik
 * @package Piwik
 */

/**
 * Delete all rows for which 
 * - the given $columnToFilter do not contain the $patternToSearch 
 * - AND all the subTables associated to this row do not contain the $patternToSearch
 * 
 * This filter is to be used on columns containing strings. 
 * Example: from the pages viewed report, keep only the rows that contain "piwik" or for which a subpage contains "piwik".
 * 
 * @package Piwik
 * @subpackage Piwik_DataTable
 */
class Piwik_DataTable_Filter_PatternRecursive extends Piwik_DataTable_Filter
{
	private $columnToFilter;
	private $patternToSearch;
	private $patternToSearchQuoted;
	
	public function __construct( $table, $columnToFilter, $patternToSearch )
	{
		parent::__construct($table);
		$this->patternToSearch = $patternToSearch;
		$this->patternToSearchQuoted = Piwik_DataTable_Filter_Pattern::getPatternQuoted($patternToSearch);
		$this->patternToSearch = $patternToSearch;//preg_quote($patternToSearch);
		$this->columnToFilter = $columnToFilter;
	}
	
	public function filter( $table )
	{
		$rows = $table->getRows();
		
		foreach($rows as $key => $row)
		{
			// A row is deleted if
			// 1 - its label doesnt contain the pattern 
			// AND 2 - the label is not found in the children
			$patternNotFoundInChildren = false;
			
			try{
				$idSubTable = $row->getIdSubDataTable();
				$subTable = Piwik_DataTable_Manager::getInstance()->getTable($idSubTable);
				
				// we delete the row if we couldn't find the pattern in any row in the 
				// children hierarchy
				if( $this->filter($subTable) == 0 )
				{
					$patternNotFoundInChildren = true;
				}
			} catch(Exception $e) {
				// there is no subtable loaded for example
				$patternNotFoundInChildren = true;
			}

			if( $patternNotFoundInChildren
				&& !Piwik_DataTable_Filter_Pattern::match($this->patternToSearch, $this->patternToSearchQuoted, $row->getColumn($this->columnToFilter), $invertedMatch = false)	
			)
			{
				$table->deleteRow($key);
			}
		}
		
		return $table->getRowsCount();
	}
}
