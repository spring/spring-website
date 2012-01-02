<?php
/**
 * Piwik - Open source web analytics
 * 
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id: Rss.php 3565 2011-01-03 05:49:45Z matt $
 * 
 * @category Piwik
 * @package Piwik
 */

/**
 * RSS Feed. 
 * The RSS renderer can be used only on Piwik_DataTable_Array that are arrays of Piwik_DataTable.
 * A RSS feed contains one dataTable per element in the Piwik_DataTable_Array.
 * 
 * @package Piwik
 * @subpackage Piwik_DataTable
 */
class Piwik_DataTable_Renderer_Rss extends Piwik_DataTable_Renderer
{
	function render()
	{
		self::renderHeader();
		return $this->renderTable($this->table);
	}
	
	function renderException()
	{
		self::renderHeader();
		$exceptionMessage = self::renderHtmlEntities($this->exception->getMessage());
		return 'Error: '.$exceptionMessage;
	}
	
	protected function renderTable($table)
	{
		if(!($table instanceof Piwik_DataTable_Array)
			|| $table->getKeyName() != 'date')
		{
			throw new Exception("RSS Feed only used on Piwik_DataTable_Array with keyName = 'date'");
		}
		
		$idSite = Piwik_Common::getRequestVar('idSite', 1, 'int');
		$period = Piwik_Common::getRequestVar('period');
		
		$piwikUrl = Piwik_Url::getCurrentUrlWithoutFileName() 
						. "?module=CoreHome&action=index&idSite=" . $idSite . "&period=" . $period;
		$out = "";
		$moreRecentFirst = array_reverse($table->getArray(), true);
		foreach($moreRecentFirst as $date => $subtable )
		{
			$timestamp = $table->metadata[$date]['timestamp'];
			$site = $table->metadata[$date]['site'];
	
			$pudDate = date('r', $timestamp);
			 
			$dateInSiteTimezone = Piwik_Date::factory($timestamp)->setTimezone($site->getTimezone())->toString('Y-m-d');
			$thisPiwikUrl = Piwik_Common::sanitizeInputValue($piwikUrl . "&date=$dateInSiteTimezone");
			$siteName = $site->getName();
			$title = $siteName . " on ". $date;
			
			$out .= "\t<item>
		<pubDate>$pudDate</pubDate>
		<guid>$thisPiwikUrl</guid>
		<link>$thisPiwikUrl</link>
		<title>$title</title>
		<author>http://piwik.org</author>
		<description>";	
			
			$out .= Piwik_Common::sanitizeInputValue( $this->renderDataTable($subtable) );
			$out .= "</description>\n\t</item>\n";
		}
		
		$header = $this->getRssHeader();
		$footer = $this->getRssFooter();
		
		return $header . $out . $footer;
	}

	protected static function renderHeader()
	{
		@header('Content-Type: text/xml; charset=utf-8');
	}

	protected function getRssFooter()
	{
		return "\t</channel>\n</rss>";
	}

	protected function getRssHeader()
	{
		$generationDate = date('r');
		$header = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
<rss version=\"2.0\">
  <channel>
    <title>piwik statistics - RSS</title>
    <link>http://piwik.org</link>
    <description>Piwik RSS feed</description>
    <pubDate>$generationDate</pubDate>
    <generator>piwik</generator>
    <language>en</language>
    <lastBuildDate>$generationDate</lastBuildDate>";
    	return $header;
	}
		
	protected function renderDataTable($table)
	{
		if($table->getRowsCount() == 0)
		{
			return "<b><i>Empty table</i></b><br />\n";
		}

		$i = 1;		
		$tableStructure = array();
		
		/*
		 * table = array
		 * ROW1 = col1 | col2 | col3 | metadata | idSubTable
		 * ROW2 = col1 | col2 (no value but appears) | col3 | metadata | idSubTable
		 * 		subtable here
		 */
		$allColumns = array();
		foreach($table->getRows() as $row)
		{
			foreach($row->getColumns() as $column => $value)
			{
				// for example, goals data is array: not supported in export RSS
				// in the future we shall reuse ViewDataTable for html exports in RSS anyway
				if(is_array($value))
				{
					continue;
				}
				$allColumns[$column] = true;
				$tableStructure[$i][$column] = $value;
			}
			$i++;
		}
		$html = "\n";
		$html .= "<table border=1 width=70%>";
		$html .= "\n<tr>";
		foreach($allColumns as $name => $toDisplay)
		{
			if($toDisplay !== false)
			{
				$html .= "\n\t<td><b>$name</b></td>";
			}
		}
		$html .= "\n</tr>";
		$colspan = count($allColumns);
		
		foreach($tableStructure as $row)
		{
			$html .= "\n\n<tr>";
			foreach($allColumns as $columnName => $toDisplay)
			{
				if($toDisplay !== false)
				{
					$value = "-";
					if(isset($row[$columnName]))
					{
						$value = urldecode($row[$columnName]);
					}
					
					$html .= "\n\t<td>$value</td>";
				}
			}
			$html .= "</tr>";
			
		}
		$html .= "\n\n</table>";
		return $html;
	}
}
