<?php
/**
 * Piwik - Open source web analytics
 * 
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id: ColumnCallbackReplace.php 4169 2011-03-23 01:59:57Z matt $
 * 
 * @category Piwik
 * @package Piwik
 */

/**
 * Replace a column value with a new value resulting 
 * from the function called with the column's value
 * 
 * @package Piwik
 * @subpackage Piwik_DataTable
 */
class Piwik_DataTable_Filter_ColumnCallbackReplace extends Piwik_DataTable_Filter
{
	private $columnToFilter;
	private $functionToApply;
	
	public function __construct( $table, $columnToFilter, $functionToApply, $functionParameters = null )
	{
		parent::__construct($table);
		$this->functionToApply = $functionToApply;
		$this->functionParameters = $functionParameters;
		$this->columnToFilter = $columnToFilter;
	}
	
	public function filter($table)
	{
		foreach($table->getRows() as $key => $row)
		{
			// when a value is not defined, we set it to zero by default (rather than displaying '-')
			$value = $this->getElementToReplace($row, $this->columnToFilter);
			if($value === false)
			{
				$value = 0;
			}
			$parameters = array($value);
			if(!is_null($this->functionParameters))
			{
				$parameters = array_merge($parameters, $this->functionParameters);
			}
			$newValue = call_user_func_array( $this->functionToApply, $parameters);
			$this->setElementToReplace($row, $this->columnToFilter, $newValue);
			$this->filterSubTable($row);
		}
	}
	
	protected function setElementToReplace($row, $columnToFilter, $newValue)
	{
		$row->setColumn($columnToFilter, $newValue);
	}
	protected function getElementToReplace($row, $columnToFilter)
	{
		return $row->getColumn($columnToFilter);
	}
}
