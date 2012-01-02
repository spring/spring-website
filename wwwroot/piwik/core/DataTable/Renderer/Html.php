<?php
/**
 * Piwik - Open source web analytics
 * 
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id: Html.php 4829 2011-05-29 22:08:33Z matt $
 * 
 * @category Piwik
 * @package Piwik
 */

/**
 * Simple HTML output
 * Does not work with recursive DataTable (i.e., when a row can be associated with a subDataTable).
 * 
 * @package Piwik
 * @subpackage Piwik_DataTable
 */
class Piwik_DataTable_Renderer_Html extends Piwik_DataTable_Renderer
{
	protected $tableId;
	protected $allColumns;
	protected $tableStructure;
	protected $i;

	function setTableId($id)
	{
		$this->tableId = str_replace('.', '_', $id);
	}

	function render()
	{
		self::renderHeader();
		$this->tableStructure = array();
		$this->allColumns = array();
		$this->i = 0;

		return $this->renderTable($this->table);
	}
	
	function renderException()
	{
		self::renderHeader();
		$exceptionMessage = self::renderHtmlEntities($this->exception->getMessage());
		return nl2br($exceptionMessage);
	}
	
	protected function renderTable($table)
	{
		if($table instanceof Piwik_DataTable_Array)
		{
			foreach($table->getArray() as $date => $subtable )
			{
				if ($subtable->getRowsCount()) {
					$this->buildTableStructure($subtable, '_'. $table->getKeyName(), $date);
				}
			}
		}
		else // Piwik_DataTable_Simple
		{
			if($table->getRowsCount())
			{
				$this->buildTableStructure($table);
			}
		}

		$out = $this->renderDataTable();
		return $out;
	}	

	protected function buildTableStructure($table, $columnToAdd = null, $valueToAdd = null)
	{
		$i = $this->i;
		$someMetadata = false;
		$someIdSubTable = false;
		
		/*
		 * table = array
		 * ROW1 = col1 | col2 | col3 | metadata | idSubTable
		 * ROW2 = col1 | col2 (no value but appears) | col3 | metadata | idSubTable
		 */
		if(!($table instanceof Piwik_DataTable))
		{
			throw new Exception("HTML Renderer does not work with this combination of parameters");
		}
		foreach($table->getRows() as $row)
		{
			if(isset($columnToAdd) && isset($valueToAdd))
			{
				$this->allColumns[$columnToAdd] = true;
				$this->tableStructure[$i][$columnToAdd] = $valueToAdd;
			}

			foreach($row->getColumns() as $column => $value)
			{
				$this->allColumns[$column] = true;
				$this->tableStructure[$i][$column] = $value;
			}

			$metadata = array();
			foreach($row->getMetadata() as $name => $value)
			{
				if(is_string($value)) $value = "'$value'";
				$metadata[] = "'$name' => $value";
			}

			if(count($metadata) != 0)
			{
				$someMetadata = true;
				$metadata = implode("<br />", $metadata);
				$this->tableStructure[$i]['_metadata'] = $metadata;
			}
			
			$idSubtable = $row->getIdSubDataTable();
			if(!is_null($idSubtable))
			{
				$someIdSubTable = true;
				$this->tableStructure[$i]['_idSubtable'] = $idSubtable;
			}

			$i++;
		}
		$this->i = $i;

		$this->allColumns['_metadata'] = $someMetadata;
		$this->allColumns['_idSubtable'] = $someIdSubTable;
	}

	protected function renderDataTable()
	{
		$html = "<table ". ($this->tableId ? "id=\"{$this->tableId}\" " : "") ."border=\"1\">\n<thead>\n\t<tr>\n";

		foreach($this->allColumns as $name => $toDisplay)
		{
			if($toDisplay !== false)
			{
				if($name === 0)
				{
					$name = 'value';
				}
				$html .= "\t\t<th>$name</th>\n";
			}
		}

		$html .= "\t</tr>\n</thead>\n<tbody>\n";

		foreach($this->tableStructure as $row)
		{
			$html .= "\t<tr>\n";
			foreach($this->allColumns as $name => $toDisplay)
			{
				if($toDisplay !== false)
				{
					$value = "-";
					if(isset($row[$name]))
					{
						if(is_array($row[$name]))
						{
							$value = "<pre>".self::formatValueXml(var_export($row[$name], true)) . "</pre>";
						}
						else
						{
							$value = self::formatValueXml($row[$name]);
						}
					}
					
					$html .= "\t\t<td>$value</td>\n";
				}
			}
			$html .= "\t</tr>\n";
		}

		$html .= "</tbody>\n</table>\n";
		
		return $html;
	}
}
