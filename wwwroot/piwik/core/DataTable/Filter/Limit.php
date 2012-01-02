<?php
/**
 * Piwik - Open source web analytics
 * 
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id: Limit.php 4169 2011-03-23 01:59:57Z matt $
 * 
 * @category Piwik
 * @package Piwik
 */

/**
 * Delete all rows from the table that are not in the offset,offset+limit range
 * 
 * @package Piwik
 * @subpackage Piwik_DataTable
 */
class Piwik_DataTable_Filter_Limit extends Piwik_DataTable_Filter
{
	/**
	 * Filter constructor.
	 * 
	 * @param Piwik_DataTable $table
	 * @param int $offset Starting row (indexed from 0)
	 * @param int $limit Number of rows to keep (specify -1 to keep all rows)
	 */
	public function __construct( $table, $offset, $limit = null )
	{
		parent::__construct($table);
		$this->offset = $offset;
		
		if(is_null($limit))
		{
			$limit = -1;
		}
		$this->limit = $limit;
	}	
	
	public function filter($table)
	{
		$table = $table;
		$table->setRowsCountBeforeLimitFilter();
		
		$rowsCount = $table->getRowsCount();
		
		// we delete from 0 to offset
		if($this->offset > 0) 
		{
			$table->deleteRowsOffset( 0, $this->offset );
		}
		// at this point the array has offset less elements. We delete from limit to the end
		if( $this->limit >= 0 )
		{
			$table->deleteRowsOffset( $this->limit );
		}
	}
}
