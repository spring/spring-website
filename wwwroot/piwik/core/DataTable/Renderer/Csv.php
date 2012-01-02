<?php
/**
 * Piwik - Open source web analytics
 * 
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id: Csv.php 5297 2011-10-14 04:11:34Z matt $
 * 
 * @category Piwik
 * @package Piwik
 */

/**
 * CSV export
 * 
 * When rendered using the default settings, a CSV report has the following characteristics:
 * The first record contains headers for all the columns in the report.
 * All rows have the same number of columns.
 * The default field delimiter string is a comma (,).
 * Formatting and layout are ignored.
 * 
 * Note that CSV output doesn't handle recursive dataTable. It will output only the first parent level of the tables.
 * 
 * @package Piwik
 * @subpackage Piwik_DataTable
 */
class Piwik_DataTable_Renderer_Csv extends Piwik_DataTable_Renderer
{
	/**
	 * Column separator
	 *
	 * @var string
	 */
	public $separator = ",";
	
	/**
	 * Line end 
	 *
	 * @var string
	 */
	public $lineEnd = "\n";
	
	/**
	 * 'metadata' columns will be exported, prefixed by 'metadata_'
	 *
	 * @var bool
	 */
	public $exportMetadata = true;
	
	/**
	 * Converts the content to unicode so that UTF8 characters (eg. chinese) can be imported in Excel
	 *
	 * @var bool
	 */
	public $convertToUnicode = true;
	
	/**
	 * idSubtable will be exported in a column called 'idsubdatatable'
	 *
	 * @var bool
	 */
	public $exportIdSubtable = true;
	
	public function render()
	{
		$str = $this->renderTable($this->table);
		if(empty($str))
		{
			return 'No data available';
		}

		self::renderHeader();

		if($this->convertToUnicode 
			&& function_exists('mb_convert_encoding'))
		{
			$str = chr(255) . chr(254) . mb_convert_encoding($str, 'UTF-16LE', 'UTF-8');
		}
		return $str;
	}
	
	function renderException()
	{
		$exceptionMessage = self::renderHtmlEntities($this->exception->getMessage());
		return 'Error: '.$exceptionMessage;
	}
	
	public function setConvertToUnicode($bool)
	{
		$this->convertToUnicode = $bool;
	}
	
	public function setSeparator($separator)
	{
		$this->separator = $separator;
	}
	
	protected function renderTable($table)
	{
		if($table instanceof Piwik_DataTable_Array)
		{
			$str = $header = '';
			$prefixColumns = $table->getKeyName() . $this->separator;
			foreach($table->getArray() as $currentLinePrefix => $dataTable)
			{
				$returned = explode("\n",$this->renderTable($dataTable));
				// get the columns names
				if(empty($header))
				{
					$header = $returned[0];
				}
				$returned = array_slice($returned,1);
				
				// case empty datatable we dont print anything in the CSV export
				// when in xml we would output <result date="2008-01-15" />
				if(!empty($returned))
				{
					foreach($returned as &$row)
					{
						$row = $currentLinePrefix . $this->separator . $row;
					}
					$str .= "\n" .  implode("\n", $returned);
				}
			}
			if(!empty($header))
			{
				$str = $prefixColumns . $header . $str;
			}
		}
		else
		{
			$str = $this->renderDataTable($table);
		}
		return $str;
	}
	
	protected function renderDataTable( $table )
	{	
		if($table instanceof Piwik_DataTable_Simple)
		{
			$row = $table->getFirstRow();
			if($row !== false)
			{
				$columnNameToValue = $row->getColumns();
				if(count($columnNameToValue) == 1)
				{
					$value = array_values($columnNameToValue);
					$str = 'value' . $this->lineEnd . $this->formatValue($value[0]);
					return $str;
				}
			}
		}
		$csv = $allColumns = array();
		foreach($table->getRows() as $row)
		{
			$csvRow = array();
			
			$columns = $row->getColumns();
			foreach($columns as $name => $value)
			{
				//goals => array( 'idgoal=1' =>array(..), 'idgoal=2' => array(..))
				if(is_array($value))
				{
					foreach($value as $key => $subValues)
					{
						if(is_array($subValues))
						{
							foreach($subValues as $subKey => $subValue)
							{
								// goals_idgoal=1
								$columnName = $name . "_" . $key . "_" . $subKey;
								$allColumns[$columnName] = true;
								$csvRow[$columnName] = $subValue;
							}
						}
					}
				}
				else
				{
					$allColumns[$name] = true;
					$csvRow[$name] = $value;
				}
			}

			if($this->exportMetadata)
			{
				$metadata = $row->getMetadata();
				foreach($metadata as $name => $value)
				{
					//if a metadata and a column have the same name make sure they dont overwrite
					$name = 'metadata_'.$name;
					
					$allColumns[$name] = true;
					$csvRow[$name] = $value;
				}
			}		
			
			if($this->exportIdSubtable)
			{
				$idsubdatatable = $row->getIdSubDataTable();
				if($idsubdatatable !== false
					&& $this->hideIdSubDatatable === false)
				{
					$csvRow['idsubdatatable'] = $idsubdatatable;
				}
			}
			
			$csv[] = $csvRow;
		}
		
		// now we make sure that all the rows in the CSV array have all the columns
		foreach($csv as &$row)
		{
			foreach($allColumns as $columnName => $true)
			{
				if(!isset($row[$columnName]))
				{
					$row[$columnName] = '';
				}
			}
		}
		$str = '';		
		
		// specific case, we have only one column and this column wasn't named properly (indexed by a number)
		// we don't print anything in the CSV file => an empty line
		if(sizeof($allColumns) == 1 
			&& reset($allColumns) 
			&& !is_string(key($allColumns)))
		{
			$str .= '';
		}
		else
		{
			$keys = array_keys($allColumns);
			$str .= implode($this->separator, $keys);
			$str .= $this->lineEnd;
		}
		
		// we render the CSV
		foreach($csv as $theRow)
		{
			$rowStr = '';
			foreach($allColumns as $columnName => $true)
			{
				$rowStr .= $this->formatValue($theRow[$columnName]) . $this->separator;
			}
			// remove the last separator
			$rowStr = substr_replace($rowStr,"",-strlen($this->separator));
			$str .= $rowStr . $this->lineEnd;
		}
		$str = substr($str, 0, -strlen($this->lineEnd));
		return $str;
	}

	protected function formatValue($value)
	{
		if(is_string($value)
			&& !is_numeric($value)) 
		{
			$value = html_entity_decode($value, ENT_COMPAT, 'UTF-8');
		}
		elseif($value === false)
		{
			$value = 0;
		}
		if(is_string($value)
			&& (strpos($value, '"') !== false 
				|| strpos($value, $this->separator) !== false )
		)
		{
			$value = '"'. str_replace('"', '""', $value). '"';
		}
		return $value;
	}
	
	protected static function renderHeader()
	{
		// silent fail otherwise unit tests fail
		@header('Content-Type: application/vnd.ms-excel');
		@header('Content-Disposition: attachment; filename=piwik-report-export.csv');
		Piwik::overrideCacheControlHeaders();
	}
}
