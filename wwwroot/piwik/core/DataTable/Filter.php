<?php
/**
 * Piwik - Open source web analytics
 * 
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id: Filter.php 4169 2011-03-23 01:59:57Z matt $
 * 
 * @category Piwik
 * @package Piwik
 */

/**
 * A filter is applied instantly to a given DataTable and can 
 * - remove rows 
 * - change columns values (lowercase the strings, truncate, etc.)
 * - add/remove columns or metadata (compute percentage values, add an 'icon' metadata based on the label, etc.)
 * - add/remove/edit sub DataTable associated to some rows
 * - whatever you can imagine
 * 
 * The concept is very simple: the filter is given the DataTable 
 * and can do whatever is necessary on the data (in the filter() method).
 * 
 * @package Piwik
 * @subpackage Piwik_DataTable
 */
abstract class Piwik_DataTable_Filter
{
	public function __construct($table)
	{
		if(!($table instanceof Piwik_DataTable))
		{
			throw new Exception("The filter accepts only a Piwik_DataTable object.");
		}
	}
	
	abstract public function filter($table);
	
	protected $enableRecursive = false;
	
	public function enableRecursive($bool)
	{
		$this->enableRecursive = (bool)$bool;
	}
	
	public function filterSubTable(Piwik_DataTable_Row $row)
	{
		if(!$this->enableRecursive)
		{
			return;
		}
		try {
			$subTable = Piwik_DataTable_Manager::getInstance()->getTable( $row->getIdSubDataTable() );
			$this->filter($subTable);
		} catch(Exception $e) {
			// case idSubTable == null, or if the table is not loaded in memory
		}
	}
}
