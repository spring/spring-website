<?php
/**
 * Piwik - Open source web analytics
 * 
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id: Row.php 4311 2011-04-04 18:49:55Z vipsoft $
 * 
 * @category Piwik
 * @package Piwik
 */

/**
 * A DataTable is composed of rows.
 * 
 * A row is composed of:
 * - columns often at least a 'label' column containing the description 
 * 		of the row, and some numeric values ('nb_visits', etc.)
 * - metadata: other information never to be shown as 'columns'
 * - idSubtable: a row can be linked to a SubTable
 * 
 * IMPORTANT: Make sure that the column named 'label' contains at least one non-numeric character.
 *            Otherwise the method addDataTable() or sumRow() would fail because they would consider
 *            the 'label' as being a numeric column to sum.
 * 
 * PERFORMANCE: Do *not* add new fields except if necessary in this object. New fields will be 
 *              serialized and recorded in the DB millions of times. This object size is critical and must be under control.
 *              
 * @package Piwik
 * @subpackage Piwik_DataTable
 */
class Piwik_DataTable_Row
{
	/**
	 * This array contains the row information:
	 * - array indexed by self::COLUMNS contains the columns, pairs of (column names, value) 
	 * - (optional) array indexed by self::METADATA contains the metadata,  pairs of (metadata name, value)
	 * - (optional) integer indexed by self::DATATABLE_ASSOCIATED contains the ID of the Piwik_DataTable associated to this row. 
	 *   This ID can be used to read the DataTable from the DataTable_Manager.
	 * 
	 * @var array
	 * @see constructor for more information
	 */
	public $c = array();
	
	const COLUMNS = 0;
	const METADATA = 1;
	const DATATABLE_ASSOCIATED = 3;


	/**
	 * Efficient load of the Row structure from a well structured php array
	 * 
	 * @param array The row array has the structure
	 * 					array( 
	 * 						Piwik_DataTable_Row::COLUMNS => array( 
	 * 										'label' => 'Piwik',
	 * 										'column1' => 42,
	 * 										'visits' => 657,
	 * 										'time_spent' => 155744,	
	 * 									),
	 * 						Piwik_DataTable_Row::METADATA => array(
	 * 										'logo' => 'test.png'
	 * 									),
	 * 						Piwik_DataTable_Row::DATATABLE_ASSOCIATED => #Piwik_DataTable object (but in the row only the ID will be stored)
	 * 					)
	 */
	public function __construct( $row = array() )
	{
		$this->c[self::COLUMNS] = array();
		$this->c[self::METADATA] = array();
		$this->c[self::DATATABLE_ASSOCIATED] = null;
		
		if(isset($row[self::COLUMNS]))
		{
			$this->c[self::COLUMNS] = $row[self::COLUMNS];
		}
		if(isset($row[self::METADATA]))
		{
			$this->c[self::METADATA] = $row[self::METADATA];
		}
		if(isset($row[self::DATATABLE_ASSOCIATED])
			&& $row[self::DATATABLE_ASSOCIATED] instanceof Piwik_DataTable)
		{
			$this->c[self::DATATABLE_ASSOCIATED] = $row[self::DATATABLE_ASSOCIATED]->getId();
		}	
	}
	
	/**
	 * When destroyed, a row destroys its associated subTable if there is one
	 */
	public function __destruct()
	{
		$idSubtable = $this->c[self::DATATABLE_ASSOCIATED];
		if($idSubtable !== null)
		{
			Piwik_DataTable_Manager::getInstance()->deleteTable($idSubtable);
			$idSubtable = null;
		}
	}

	/**
	 * Applys a basic rendering to the Row and returns the output
	 *
	 * @return string characterizing the row. Example: - 1 ['label' => 'piwik', 'nb_uniq_visitors' => 1685, 'nb_visits' => 1861, 'nb_actions' => 2271, 'max_actions' => 13, 'sum_visit_length' => 920131, 'bounce_count' => 1599] [] [idsubtable = 1375]
	 */
	public function __toString()
	{
		$columns = array();
		foreach($this->getColumns() as $column => $value)
		{
			if(is_string($value)) $value = "'$value'";
			elseif(is_array($value)) $value = var_export($value, true);
			$columns[] = "'$column' => $value";
		}
		$columns = implode(", ", $columns);
		$metadata = array();
		foreach($this->getMetadata() as $name => $value)
		{
			if(is_string($value)) $value = "'$value'";
			elseif(is_array($value)) $value = var_export($value, true);
			$metadata[] = "'$name' => $value";
		}
		$metadata = implode(", ", $metadata);
		$output = "# [".$columns."] [".$metadata."] [idsubtable = " . $this->getIdSubDataTable()."]<br />\n";
		return $output;
	}
	
	/**
	 * Deletes the given column 
	 *
	 * @param string Column name
	 * @return bool True on success, false if the column didn't exist
	 */
	public function deleteColumn( $name )
	{
		if(!isset($this->c[self::COLUMNS][$name]))
		{
			return false;
		}
		unset($this->c[self::COLUMNS][$name]);
		return true;
	}
	
	public function renameColumn($oldName, $newName)
	{
		if(isset($this->c[self::COLUMNS][$oldName]))
		{
			$this->c[self::COLUMNS][$newName] = $this->c[self::COLUMNS][$oldName];
			unset($this->c[self::COLUMNS][$oldName]);
		}
	}
	
	/**
	 * Returns the given column
	 * 
	 * @param string Column name
	 * @return mixed|false The column value  
	 */
	public function getColumn( $name )
	{
		if(!isset($this->c[self::COLUMNS][$name]))
		{
			return false;
		}
		return $this->c[self::COLUMNS][$name];
	}
	
	/**
	 * Returns the array of all metadata,
	 * or the specified metadata  
	 * 
	 * @param string Metadata name
	 * @return mixed|array|false 
	 */
	public function getMetadata( $name = null )
	{
		if(is_null($name))
		{
			return $this->c[self::METADATA];
		}
		if(!isset($this->c[self::METADATA][$name]))
		{
			return false;
		}
		return $this->c[self::METADATA][$name];
	}
	
	/**
	 * Returns the array containing all the columns
	 * 
	 * @return array Example: array( 
	 * 					'column1' 	=> VALUE,
	 * 					'label' 	=> 'www.php.net'
	 * 					'nb_visits' => 15894,
	 * 			)
	 */
	public function getColumns()
	{
		return $this->c[self::COLUMNS];
	}
	
	/**
	 * Returns the ID of the subDataTable. 
	 * If there is no such a table, returns null.
	 * 
	 * @return int|null
	 */
	public function getIdSubDataTable()
	{
		return $this->c[self::DATATABLE_ASSOCIATED];
	}
	
	/**
	 * Sums a DataTable to this row subDataTable.
	 * If this row doesn't have a SubDataTable yet, we create a new one.
	 * Then we add the values of the given DataTable to this row's DataTable.
	 * 	 
	 * @param Piwik_DataTable Table to sum to this row's subDatatable
	 * @see Piwik_DataTable::addDataTable() for the algorithm used for the sum 
	 */
	public function sumSubtable(Piwik_DataTable $subTable)
	{
		$thisSubtableID = $this->getIdSubDataTable();
		if($thisSubtableID === null)
		{
			$thisSubTable = new Piwik_DataTable();
			$this->addSubtable($thisSubTable);
		}
		else
		{
			$thisSubTable = Piwik_DataTable_Manager::getInstance()->getTable( $thisSubtableID );
		}
		
		$thisSubTable->addDataTable($subTable);
	}
	
	
	/**
	 * Set a DataTable to be associated to this row.
	 * If the row already has a DataTable associated to it, throws an Exception.
	 * 
	 * @param Piwik_DataTable DataTable to associate to this row
	 * @throws Exception 
	 * 
	 */
	public function addSubtable(Piwik_DataTable $subTable)
	{
		if(!is_null($this->c[self::DATATABLE_ASSOCIATED]))
		{
			throw new Exception("Adding a subtable to the row, but it already has a subtable associated.");
		}
		$this->c[self::DATATABLE_ASSOCIATED] = $subTable->getId();
	}
	
	/**
	 * Set a DataTable to this row. If there is already 
	 * a DataTable associated, it is simply overwritten.
	 * 
	 * @param Piwik_DataTable DataTable to associate to this row
	 */
	public function setSubtable(Piwik_DataTable $subTable)
	{
		$this->c[self::DATATABLE_ASSOCIATED] = $subTable->getId();
	}
	
	/**
	 * Set all the columns at once. Overwrites previously set columns.
	 * 
	 * @param array array( 
	 * 					'label' 	=> 'www.php.net'
	 * 					'nb_visits' => 15894,
	 * 			)
	 */
	public function setColumns( $columns )
	{
		$this->c[self::COLUMNS] = $columns;
	}

	/**
	 * Set the value $value to the column called $name.
	 * 
	 * @param string $name of the column to set
	 * @param mixed $value of the column to set
	 */
	public function setColumn($name, $value)
	{
		$this->c[self::COLUMNS][$name] = $value;
	}
	
	/**
	 * Set the value $value to the metadata called $name.
	 * 
	 * @param string $name of the metadata to set
	 * @param mixed $value of the metadata to set
	 */
	public function setMetadata($name, $value)
	{
		$this->c[self::METADATA][$name] = $value;
	}
	
	/**
	 * Add a new column to the row. If the column already exists, throws an exception
	 * 
	 * @param string $name of the column to add
	 * @param mixed $value of the column to set
	 * @throws Exception
	 */
	public function addColumn($name, $value)
	{
		if(isset($this->c[self::COLUMNS][$name]))
		{
			debug_print_backtrace();
			throw new Exception("Column $name already in the array!");
		}
		$this->c[self::COLUMNS][$name] = $value;
	}
	
	/**
	 * Add columns to the row
	 *
	 * @param array $columns Name/Value pairs, e.g., array( name => value , ...)
	 * @return void
	 */
	public function addColumns($columns)
	{
		foreach($columns as $name => $value)
		{
			$this->addColumn($name, $value);
		}
	}
	
	/**
	 * Add a new metadata to the row. If the column already exists, throws an exception.
	 * 
	 * @param string $name of the metadata to add
	 * @param mixed $value of the metadata to set
	 * @throws Exception
	 */
	public function addMetadata($name, $value)
	{
		if(isset($this->c[self::METADATA][$name]))
		{
			throw new Exception("Metadata $name already in the array!");
		}
		$this->c[self::METADATA][$name] = $value;
	}

	/**
	 * Sums the given $row columns values to the existing row' columns values.
	 * It will sum only the int or float values of $row.
	 * It will not sum the column 'label' even if it has a numeric value.
	 * 
	 * If a given column doesn't exist in $this then it is added with the value of $row.
	 * If the column already exists in $this then we have
	 * 		this.columns[idThisCol] += $row.columns[idThisCol]
	 */
	public function sumRow( Piwik_DataTable_Row $rowToSum )
	{
		foreach($rowToSum->getColumns() as $columnToSumName => $columnToSumValue)
		{
			if($columnToSumName != 'label')
			{
				$thisColumnValue = $this->getColumn($columnToSumName);
				
				// Max operation
				if($columnToSumName == Piwik_Archive::INDEX_MAX_ACTIONS )
				{
					$newValue = max($thisColumnValue, $columnToSumValue);
				}
				else
				{
					$newValue = $this->sumRowArray($thisColumnValue, $columnToSumValue);
				}
				$this->setColumn( $columnToSumName, $newValue);
			}
		}
	}
	
	protected function sumRowArray( $thisColumnValue, $columnToSumValue )
	{
		if(is_numeric($columnToSumValue))
		{
			if($thisColumnValue === false)
			{
				$thisColumnValue = 0;
			}
			return $thisColumnValue + $columnToSumValue;
		}
		
		if(is_array($columnToSumValue))
		{
			if($thisColumnValue == false)
			{
				return $columnToSumValue;
			}
			$newValue = $thisColumnValue;
			foreach($columnToSumValue as $arrayIndex => $arrayValue)
			{
				if(!isset($newValue[$arrayIndex])) 
				{
					$newValue[$arrayIndex] = false;
				}
				$newValue[$arrayIndex] = $this->sumRowArray($newValue[$arrayIndex], $arrayValue);
			}
			return $newValue;
		}
		return 0;
	}
	
	/**
	 * Helper function to test if two rows are equal.
	 * 
	 * Two rows are equal 
	 * - if they have exactly the same columns / metadata
	 * - if they have a subDataTable associated, then we check that both of them are the same.
	 * 
	 * @param Piwik_DataTable_Row row1 to compare
	 * @param Piwik_DataTable_Row row2 to compare
	 * 
	 * @return bool
	 */
	static public function isEqual( Piwik_DataTable_Row $row1, Piwik_DataTable_Row $row2 )
	{		
		//same columns
		$cols1 = $row1->getColumns();
		$cols2 = $row2->getColumns();
		if(array_diff($cols1, $cols2) !== array_diff($cols2, $cols1))
		{
			return false;
		}
		
		$dets1 = $row1->getMetadata();
		$dets2 = $row2->getMetadata();
		
		ksort($dets1);
		ksort($dets2);
		
		if($dets1 != $dets2)
		{
			return false;
		}
		
		// either both are null
		// or both have a value
		if( !(is_null($row1->getIdSubDataTable()) 
				&& is_null($row2->getIdSubDataTable())
			)
		)
		{
			$subtable1 = Piwik_DataTable_Manager::getInstance()->getTable($row1->getIdSubDataTable());
			$subtable2 = Piwik_DataTable_Manager::getInstance()->getTable($row2->getIdSubDataTable());
			if(!Piwik_DataTable::isEqual($subtable1, $subtable2))
			{
				return false;
			}
		}
		return true;
	}
}
