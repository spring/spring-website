<?php
/**
 * Piwik - Open source web analytics
 * 
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id: IndexedBySite.php 4643 2011-05-05 21:26:21Z matt $
 * 
 * @category Piwik
 * @package Piwik
 */

/**
 * @package Piwik
 * @subpackage Piwik_Archive
 */
class Piwik_Archive_Array_IndexedBySite extends Piwik_Archive_Array 
{
	/**
	 * @param Piwik_Site $oSite 
	 * @param string $strPeriod eg. 'day' 'week' etc.
	 * @param string $strDate A date range, eg. 'last10', 'previous5' or 'YYYY-MM-DD,YYYY-MM-DD'
	 */
	function __construct($sites, $strPeriod, $strDate, Piwik_Segment $segment)
	{
		foreach($sites as $idSite)
		{
			$archive = Piwik_Archive::build($idSite, $strPeriod, $strDate, $segment->getString() );
			$archive->setSite(new Piwik_Site($idSite));
			$archive->setSegment($segment);
			$this->archives[$idSite] = $archive;
		}
		ksort( $this->archives );
	}
	
	protected function getIndexName()
	{
		return 'idSite';
	}
	
	protected function getDataTableLabelValue( $archive )
	{
		return $archive->getIdSite();
	}
	
	/**
	 * Given a list of fields defining numeric values, it will return a Piwik_DataTable_Array
	 * ordered by idsite
	 *
	 * @param array|string $fields array( fieldName1, fieldName2, ...)  Names of the mysql table fields to load
	 * @return Piwik_DataTable_Array
	 */
	public function getDataTableFromNumeric( $fields )
	{
		$tableArray = $this->getNewDataTableArray();
		if ($this->getFirstArchive() instanceof Piwik_Archive_Single)
		{
			$values = $this->getValues($fields);
			foreach($this->archives as $idSite => $archive)
			{
				$table = new Piwik_DataTable_Simple();
				if (array_key_exists($idSite, $values))
				{
					$table->addRowsFromArray($values[$idSite]);
				}
				$tableArray->addTable($table, $idSite);
			}
		}
		elseif ($this->getFirstArchive() instanceof Piwik_Archive_Array)
		{
			foreach($this->archives as $idSite => $archive)
			{
				$tableArray->addTable($archive->getDataTableFromNumeric($fields), $idSite);
			}
		}
		
		return $tableArray;
	}

	private function getValues($fields)
	{
		foreach($this->archives as $archive)
		{
			$archive->setRequestedReport( is_string($fields) ? $fields : current($fields) );
			$archive->prepareArchive();
		}
		
		$arrayValues = array();
		foreach($this->loadValuesFromDB($fields) as $value)
 		{
			$arrayValues[$value['idsite']][$value['name']] = $value['value'];
 		}
		return $arrayValues;
	}
	
	private function loadValuesFromDB($fields)
	{
		$inNames = Piwik_Common::getSqlStringFieldsArray($fields);
		$archiveIds = $this->getArchiveIds();
		if(empty($archiveIds))
		{
			return array();
		}
 		$sql = "SELECT value, name, idarchive, idsite
								FROM {$this->getNumericTableName()}
								WHERE idarchive IN ( $archiveIds )
									AND name IN ( $inNames )";
		return Piwik_FetchAll($sql, $fields);
	}

	private function getFirstArchive()
	{
		reset($this->archives);
		return current($this->archives);
	}

	private function getArchiveIds()
	{
		$archiveIds = array();
		foreach($this->archives as $archive)
 		{
 			if( !$archive->isThereSomeVisits )
 			{
 				continue;
 			}
 			
			$archiveIds[] = $archive->getIdArchive();
			
 			if( $this->getNumericTableName() != $archive->archiveProcessing->getTableArchiveNumericName())
			{
				throw new Exception("Piwik_Archive_Array_IndexedBySite::getDataTableFromNumeric() algorithm won't work if data is stored in different tables");
			}
 		}
		return implode(', ', array_filter($archiveIds));
	}
	
	private function getNumericTableName()
	{
		return $this->getFirstArchive()->archiveProcessing->getTableArchiveNumericName();
	}
}
