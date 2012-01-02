<?php
/**
 * Piwik - Open source web analytics
 * 
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id: Array.php 5172 2011-09-16 06:28:10Z EZdesign $
 * 
 * @category Piwik
 * @package Piwik
 */

/**
 * The DataTable_Array is a way to store an array of dataTable.
 * The Piwik_DataTable_Array implements some of the features of the Piwik_DataTable such as queueFilter, getRowsCount.
 * 
 * @package Piwik
 * @subpackage Piwik_DataTable
 */
class Piwik_DataTable_Array
{
	/**
	 * Used to store additional information about the DataTable Array.
	 * For example if the Array is used to store multiple DataTable of UserCountry,
	 * we can add the metadata of the 'idSite' they refer to, so we can access it later if necessary.
	 *
	 * @var array of mixed
	 */
	public $metadata = array();
	
	/**
	 * Array containing the DataTable withing this Piwik_DataTable_Array
	 *
	 * @var array of Piwik_DataTable
	 */
	protected $array = array();
	
	/**
	 * This is the label used to index the tables.
	 * For example if the tables are indexed using the timestamp of each period
	 * eg. $this->array[1045886960] = new Piwik_DataTable();
	 * the keyName would be 'timestamp'.
	 * 
	 * This label is used in the Renderer (it becomes a column name or the XML description tag)
	 *
	 * @var string
	 */
	protected $keyName = 'defaultKeyName';
	
	/**
	 * Returns the keyName string @see self::$keyName
	 *
	 * @return string
	 */
	public function getKeyName()
	{
		return $this->keyName;
	}
	
	/**
	 * Set the keyName @see self::$keyName
	 *
	 * @param string $name
	 */
	public function setKeyName($name)
	{
		$this->keyName = $name;
	}
	
	/**
	 * Returns the number of DataTable in this DataTable_Array
	 *
	 * @return int
	 */
	public function getRowsCount()
	{
		return count($this->array);
	}
	
	/**
	 * Queue a filter to the DataTable_Array will queue this filter to every DataTable of the DataTable_Array.
	 *
	 * @param string $className Filter name, eg. Piwik_DataTable_Filter_Limit
	 * @param array $parameters Filter parameters, eg. array( 50, 10 )
	 */
	public function queueFilter( $className, $parameters = array() )
	{
		foreach($this->array as $table)
		{
			$table->queueFilter($className, $parameters);
		}
	}
	
	/**
	 * Apply the filters previously queued to each of the DataTable of this DataTable_Array.
	 */
	public function applyQueuedFilters()
	{
		foreach($this->array as $table)
		{
			$table->applyQueuedFilters();
		}
	}
	
	/**
	 * Apply a filter to all tables in the array
	 *
	 * @param string $className Name of filter class
	 * @param array $parameters Filter parameters
	 */
	public function filter($className, $parameters = array())
	{
		foreach($this->array as $id => $table)
		{
			$table->filter($className, $parameters);
		}
	}
	
	/**
	 * Returns the array of DataTable
	 *
	 * @return array of Piwik_DataTable
	 */
	public function getArray()
	{
		return $this->array;
	}
	
	/**
	 * Adds a new DataTable to the DataTable_Array
	 *
	 * @param Piwik_DataTable $table
	 * @param string $label Label used to index this table in the array
	 */
	public function addTable( $table, $label )
	{
		$this->array[$label] = $table;
	}
	
	/**
	 * Returns a string output of this DataTable_Array (applying the default renderer to every DataTable
	 * of this DataTable_Array).
	 *
	 * @return string
	 */
	public function __toString()
	{
		$renderer = new Piwik_DataTable_Renderer_Console();
		$renderer->setTable($this);
		return (string)$renderer;
	}

	/**
	 * @see Piwik_DataTable::enableRecursiveSort()
	 */
	public function enableRecursiveSort()
	{
		foreach($this->array as $table)
		{
			$table->enableRecursiveSort();
		}
	}
	
	public function renameColumn($oldName, $newName)
	{
		foreach($this->array as $table)
		{
			$table->renameColumn($oldName, $newName);
		}
	}
	
	public function deleteColumns($columns)
	{
		foreach($this->array as $table)
		{
			$table->deleteColumns($columns);
		}
	}

	/**
	 * Returns a Piwik_DataTable_Array whose sub tables are filtered by $label
	 * @see Piwik_DataTable::getFilteredTableFromLabel
	 *
	 * @param string $label Value of the column 'label' to search for
	 * @return Piwik_DataTable_Array
	 */
	public function getFilteredTableFromLabel($label)
	{
		$newTableArray = new Piwik_DataTable_Array;
		$newTableArray->metadata = $this->metadata;

		foreach ($this->array as $subTableLabel => $subTable)
		{
			$subTable = $subTable->getFilteredTableFromLabel($label);
			$newTableArray->addTable($subTable, $subTableLabel);
		}

		return $newTableArray;
	}

}
