<?php
/**
 * Piwik - Open source web analytics
 * 
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id: Xml.php 4776 2011-05-22 23:34:58Z matt $
 * 
 * @category Piwik
 * @package Piwik
 */

/**
 * XML export of a given DataTable.
 * See the tests cases for more information about the XML format (/tests/core/DataTable/Renderer.test.php)
 * Or have a look at the API calls examples.
 * 
 * Works with recursive DataTable (when a row can be associated with a subDataTable).
 * 
 * @package Piwik
 * @subpackage Piwik_DataTable
 */
class Piwik_DataTable_Renderer_Xml extends Piwik_DataTable_Renderer
{
	function render()
	{
		self::renderHeader();
		return '<?xml version="1.0" encoding="utf-8" ?>' .  "\n" . $this->renderTable($this->table);
	}
	
	function renderException()
	{
		self::renderHeader();

		$exceptionMessage = self::renderHtmlEntities($this->exception->getMessage());
		
		$return = '<?xml version="1.0" encoding="utf-8" ?>' . "\n" .
			"<result>\n".
			"\t<error message=\"".$exceptionMessage."\" />\n".
			"</result>";		
		
		return $return;
	}
	
	protected function getArrayFromDataTable($table)
	{
		$renderer = new Piwik_DataTable_Renderer_Php();
		$renderer->setRenderSubTables($this->isRenderSubtables());
		$renderer->setSerialize(false);
		$renderer->setTable($table);
		$renderer->setHideIdSubDatableFromResponse($this->hideIdSubDatatable);
		return $renderer->flatRender();
	}
	
	protected function renderTable($table, $returnOnlyDataTableXml = false, $prefixLines = '')
	{
		$array = $this->getArrayFromDataTable($table);
//		var_dump($array);exit;
		if($table instanceof Piwik_DataTable_Array)
		{
			$out = $this->renderDataTableArray($table, $array, $prefixLines);
			
			if($returnOnlyDataTableXml)
			{
				return $out;
			}
			$out = "<results>\n$out</results>";
			return $out;
		}
	
		// integer value of ZERO is a value we want to display
		if($array != 0 && empty($array))
		{
			if($returnOnlyDataTableXml)
			{
				throw new Exception("Illegal state, what xml shall we return?");
			}
			$out = "<result />";
			return $out;
		}
		if($table instanceof Piwik_DataTable_Simple)
		{
			if(is_array($array))
			{
				$out = $this->renderDataTableSimple($array);
			}
			else
			{
				$out = $array;
			}
			if($returnOnlyDataTableXml)
			{
				return $out;
			}
			
			if(is_array($array))
			{
				$out = "<result>\n".$out."</result>";
			}
			else
			{
				$value = self::formatValueXml($out);
				if($value === '') 
				{
					$out = "<result />";
				} 
				else 
				{
					$out = "<result>".$value."</result>";
				}
			}
			return $out;
		}
		
		if($table instanceof Piwik_DataTable)
		{
			$out = $this->renderDataTable($array);
			if($returnOnlyDataTableXml)
			{
				return $out;
			}
			$out = "<result>\n$out</result>";
			return $out;
		}
	}
	
	protected function renderDataTableArray($table, $array, $prefixLines = "")
	{
		// CASE 1
		//array
  		//  'day1' => string '14' (length=2)
  		//  'day2' => string '6' (length=1)
		$firstTable = current($array);
		if(!is_array( $firstTable ))
		{
			$xml = '';
	  		$nameDescriptionAttribute = $table->getKeyName();
	  		foreach($array as $valueAttribute => $value)
	  		{
	  			if(empty($value))
	  			{
		  			$xml .= $prefixLines . "\t<result $nameDescriptionAttribute=\"$valueAttribute\" />\n";	  				
	  			}
	  			elseif($value instanceof Piwik_DataTable_Array )
	  			{
	  				$out = $this->renderTable($value, true);
		  			//TODO somehow this code is not tested, cover this case
	  				$xml .= "\t<result $nameDescriptionAttribute=\"$valueAttribute\">\n$out</result>\n";
	  			}
	  			else
	  			{
		  			$xml .= $prefixLines . "\t<result $nameDescriptionAttribute=\"$valueAttribute\">".self::formatValueXml($value)."</result>\n";	  				
	  			}
	  		}
	  		return $xml;
		}
	
		$subTables = $table->getArray();
		$firstTable = current($subTables);
		
		// CASE 2
		//array
  		//  'day1' => 
  		//    array
  		//      'nb_uniq_visitors' => string '18'
  		//      'nb_visits' => string '101' 
  		//  'day2' => 
  		//    array
  		//      'nb_uniq_visitors' => string '28' 
  		//      'nb_visits' => string '11' 
		if( $firstTable instanceof Piwik_DataTable_Simple)
		{
			$xml = '';
			$nameDescriptionAttribute = $table->getKeyName();
	  		foreach($array as $valueAttribute => $dataTableSimple)
	  		{
	  			if(count($dataTableSimple) == 0)
				{
					$xml .= $prefixLines . "\t<result $nameDescriptionAttribute=\"$valueAttribute\" />\n";
				}
	  			else
	  			{
		  			if(is_array($dataTableSimple))
		  			{
			  			$dataTableSimple = "\n" . $this->renderDataTableSimple($dataTableSimple, $prefixLines . "\t") . $prefixLines . "\t";
		  			}
		  			$xml .= $prefixLines . "\t<result $nameDescriptionAttribute=\"$valueAttribute\">".$dataTableSimple. "</result>\n";
	  			}
	  		}
	  		return $xml;
		}
		
		// CASE 3
		//array
		//  'day1' => 
		//    array
		//      0 => 
		//        array
		//          'label' => string 'phpmyvisites'
		//          'nb_uniq_visitors' => int 11
		//          'nb_visits' => int 13
		//      1 => 
		//        array
		//          'label' => string 'phpmyvisits'
		//          'nb_uniq_visitors' => int 2
		//          'nb_visits' => int 2
		//  'day2' => 
		//    array
		//      0 => 
		//        array
		//          'label' => string 'piwik'
		//          'nb_uniq_visitors' => int 121
		//          'nb_visits' => int 130
		//      1 => 
		//        array
		//          'label' => string 'piwik bis'
		//          'nb_uniq_visitors' => int 20
		//          'nb_visits' => int 120
		if($firstTable instanceof Piwik_DataTable)
		{
			$xml = '';
			$nameDescriptionAttribute = $table->getKeyName();
			foreach($array as $keyName => $arrayForSingleDate)
			{
				$dataTableOut = $this->renderDataTable( $arrayForSingleDate, $prefixLines . "\t" );
				if(empty($dataTableOut))
				{
					$xml .= $prefixLines . "\t<result $nameDescriptionAttribute=\"$keyName\" />\n";
				}
				else
				{
					$xml .= $prefixLines . "\t<result $nameDescriptionAttribute=\"$keyName\">\n";
					$xml .= $dataTableOut;
					$xml .= $prefixLines . "\t</result>\n";
				}
			}
			return $xml;
		}
		
		if($firstTable instanceof Piwik_DataTable_Array)
		{
			$xml = '';
			$tables = $table->getArray();
			$nameDescriptionAttribute = $table->getKeyName();
			foreach( $tables as $valueAttribute => $tableInArray)
			{
				$out = $this->renderTable($tableInArray, true, $prefixLines . "\t");
				$xml .= $prefixLines . "\t<result $nameDescriptionAttribute=\"$valueAttribute\">\n".$out.$prefixLines."\t</result>\n";
	  			
			}
			return $xml;
		}
	}
	
	protected function renderDataTable( $array, $prefixLine = "" )
	{
		$out = '';
		foreach($array as $rowId => $row)
		{
			if(!is_array($row))
			{
				$value = self::formatValueXml($row);
				if(strlen($value) == 0)
				{
					$out .= $prefixLine."\t\t<$rowId />\n";
				}
				else
				{
					$out .= $prefixLine."\t\t<$rowId>".$value."</$rowId>\n";
				}
				continue;
			}

			// Handing case idgoal=7, creating a new array for that one
			$rowAttribute = '';
			if(($equalFound = strstr($rowId, '=')) !== false)
			{
				$rowAttribute = explode('=', $rowId);
				$rowAttribute = " " . $rowAttribute[0] . "='" . $rowAttribute[1] . "'";
			}
			$out .= $prefixLine."\t<row$rowAttribute>";
			
			if(count($row) === 1
				&& key($row) === 0)
			{
				$value = current($row);
				$out .= $prefixLine . $value;				
			}
			else
			{
				$out .= "\n";
				foreach($row as $name => $value)
				{
					// handle the recursive dataTable case by XML outputting the recursive table
					if(is_array($value))
					{
						$value = "\n".$this->renderDataTable($value, $prefixLine."\t\t");
						$value .= $prefixLine."\t\t"; 
					}
					else
					{
						$value = self::formatValueXml($value);
					}
					if(strlen($value) == 0)
					{
						$out .= $prefixLine."\t\t<$name />\n";
					}
					else
					{
						$out .= $prefixLine."\t\t<$name>".$value."</$name>\n";
					}
				} 
				$out .= "\t";
			}
			$out .= $prefixLine."</row>\n";
		}
		return $out;
	}
	
	protected function renderDataTableSimple( $array, $prefixLine = "")
	{
		$out = '';
		foreach($array as $keyName => $value)
		{
			$xmlValue = self::formatValueXml($value);
			if(strlen($xmlValue) == 0)
			{
				$out .= $prefixLine."\t<$keyName />\n";
			}
			else
			{
				$out .= $prefixLine."\t<$keyName>".$xmlValue."</$keyName>\n";
			}
		}
		return $out;
	}
	
	protected static function renderHeader()
	{
		// silent fail because otherwise it throws an exception in the unit tests
		@header('Content-Type: text/xml; charset=utf-8');
	}
}
