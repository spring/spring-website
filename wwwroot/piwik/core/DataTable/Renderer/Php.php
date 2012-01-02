<?php
/**
 * Piwik - Open source web analytics
 * 
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id: Php.php 3565 2011-01-03 05:49:45Z matt $
 * 
 * @category Piwik
 * @package Piwik
 */

/**
 * Returns the equivalent PHP array for a given DataTable.
 * You can specify in the constructor if you want the serialized version.
 * Please note that by default it will produce a flat version of the array.
 * See the method flatRender() for details. @see flatRender();
 * 
 * Works with recursive DataTable (when a row can be associated with a subDataTable).
 * 
 * @package Piwik
 * @subpackage Piwik_DataTable
 */
class Piwik_DataTable_Renderer_Php extends Piwik_DataTable_Renderer
{
	protected $prettyDisplay = false;
	protected $serialize = true;
	
	public function setSerialize( $bool )
	{
		$this->serialize = (bool)$bool;
	}
	
	public function setPrettyDisplay($bool)
	{
		$this->prettyDisplay = (bool)$bool;
	}
	
	public function __toString()
	{
		$data = $this->render();
		if(!is_string($data))
		{
			$data = serialize($data);
		}
		return $data;
	}

	public function render( $dataTable = null )
	{
		self::renderHeader();

		if(is_null($dataTable))
		{
			$dataTable = $this->table;
		}
		$toReturn = $this->flatRender( $dataTable );
		
		if( $this->prettyDisplay )
		{
			if(!is_array($toReturn))
			{
				$toReturn = unserialize($toReturn);
			}
			$toReturn =  "<pre>" . var_export($toReturn, true ) . "</pre>";
		}
		return $toReturn;
	}
	
	function renderException()
	{
		self::renderHeader();

		$exceptionMessage = self::renderHtmlEntities($this->exception->getMessage());
		
		$return = array('result' => 'error', 'message' => $exceptionMessage);
		
		if($this->serialize)
		{
			$return = serialize($return);
		}
		
		return $return;
	}
	
	/**
	 * Produces a flat php array from the DataTable, putting "columns" and "metadata" on the same level.
	 * 
	 * For example, when  a originalRender() would be 
	 * 	array( 'columns' => array( 'col1_name' => value1, 'col2_name' => value2 ),
	 * 	       'metadata' => array( 'metadata1_name' => value_metadata) )
	 * 
	 * a flatRender() is
	 * 	array( 'col1_name' => value1, 
	 * 	       'col2_name' => value2,
	 * 	       'metadata1_name' => value_metadata )
	 *  
	 * @return array Php array representing the 'flat' version of the datatable
	 *
	 */
	public function flatRender( $dataTable = null )
	{
		if(is_null($dataTable))
		{
			$dataTable = $this->table;
		}
		
		if($dataTable instanceof Piwik_DataTable_Array)
		{
			$flatArray = array();
			foreach($dataTable->getArray() as $keyName => $table)
			{
				$serializeSave = $this->serialize;
				$this->serialize = false;
				$flatArray[$keyName] = $this->flatRender($table);
				$this->serialize = $serializeSave;
			}
		}
		
		else if($dataTable instanceof Piwik_DataTable_Simple)
		{
			$flatArray = $this->renderSimpleTable($dataTable);
			
			// if we return only one numeric value then we print out the result in a simple <result> tag
			// keep it simple!
			if(count($flatArray) == 1)
			{
				$flatArray = current($flatArray);
			}
			
		}
		// A normal DataTable needs to be handled specifically
		else
		{
			$array = $this->renderTable($dataTable);
			$flatArray = $this->flattenArray($array);
		}
		
		if($this->serialize)
		{
			$flatArray = serialize($flatArray);
		}
		
		return $flatArray;
	}
	
	protected function flattenArray($array)
	{
		$flatArray = array();
		foreach($array as $row)
		{
			$newRow = $row['columns'] + $row['metadata'];
			if(isset($row['idsubdatatable'])
				&& $this->hideIdSubDatatable === false)
			{
				$newRow += array('idsubdatatable' => $row['idsubdatatable']);
			}
			if(isset($row['subtable']))
			{
				$newRow += array('subtable' => $this->flattenArray($row['subtable']) );
			}
			$flatArray[] = $newRow;
		}		
		return $flatArray;
	}
	
	public function originalRender()
	{
		if($this->table instanceof Piwik_DataTable_Simple)
		{
			$array = $this->renderSimpleTable($this->table);
		}
		elseif($this->table instanceof Piwik_DataTable)
		{
			$array = $this->renderTable($this->table);
		}
		else
		{
			throw new Exception("Unexpected data type to render.");
		}
				
		if($this->serialize)
		{
			$array = serialize($array);
		}
		return $array;
	}
	
	protected function renderTable($table)
	{
		$array = array();

		foreach($table->getRows() as $row)
		{
			$newRow = array(
				'columns' => $row->getColumns(),
				'metadata' => $row->getMetadata(),
				'idsubdatatable' => $row->getIdSubDataTable(),
			);
			
			if($this->isRenderSubtables()
				&& $row->getIdSubDataTable() !== null)
			{
				try{
					$subTable = $this->renderTable( Piwik_DataTable_Manager::getInstance()->getTable($row->getIdSubDataTable()));
					$newRow['subtable'] = $subTable;
					if($this->hideIdSubDatatable === false
						&& isset($newRow['metadata']['idsubdatatable_in_db']))
					{
						$newRow['columns']['idsubdatatable'] = $newRow['metadata']['idsubdatatable_in_db'];
					}
					unset($newRow['metadata']['idsubdatatable_in_db']);
				} catch (Exception $e) {
					// the subtables are not loaded we dont do anything 
				}
			}
			if($this->hideIdSubDatatable !== false)
			{
				unset($newRow['idsubdatatable']);
			}
					
			$array[] = $newRow;
		}
		return $array;
	}
	
	protected function renderSimpleTable($table)
	{
		$array = array();

		$row = $table->getFirstRow();
		if($row === false)
		{
			return $array;
		}
		foreach($row->getColumns() as $columnName => $columnValue)
		{
			$array[$columnName] = $columnValue;
		}
		return $array;
	}
}
