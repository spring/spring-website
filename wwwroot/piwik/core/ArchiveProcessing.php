<?php
/**
 * Piwik - Open source web analytics
 * 
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id: ArchiveProcessing.php 5134 2011-09-07 12:37:20Z matt $
 * 
 * @category Piwik
 * @package Piwik
 */

/**
 * The ArchiveProcessing module is a module that reads the Piwik logs from the DB and
 * compute all the reports, which are then stored in the database.
 * 
 * The ArchiveProcessing class is used by the Archive object to make sure the given Archive is processed and available in the DB.
 * 
 * A record in the Database for a given report is defined by
 * - idarchive	= unique ID that is associated to all the data of this archive (idsite+period+date)
 * - idsite		= the ID of the website 
 * - date1 		= starting day of the period
 * - date2 		= ending day of the period
 * - period 	= integer that defines the period (day/week/etc.). @see period::getId()
 * - ts_archived = timestamp when the archive was processed (UTC)
 * - name 		= the name of the report (ex: uniq_visitors or search_keywords_by_search_engines)
 * - value 		= the actual data
 * 
 * @package Piwik
 * @subpackage Piwik_ArchiveProcessing
 */
abstract class Piwik_ArchiveProcessing
{
	/**
	 * Flag stored at the end of the archiving
	 *
	 * @var int
	 */
	const DONE_OK = 1;
	
	/**
	 * Flag stored at the start of the archiving
	 * When requesting an Archive, we make sure that non-finished archive are not considered valid
	 *
	 * @var int
	 */
	const DONE_ERROR = 2;
	
	/**
	 * Flag indicates the archive is over a period that is not finished, eg. the current day, current week, etc.
	 * Archives flagged will be regularly purged from the DB.
	 * 
	 * @var int
	 */
	const DONE_OK_TEMPORARY = 3;

	/**
	 * Idarchive in the DB for the requested archive
	 *
	 * @var int
	 */
	protected $idArchive;
	
	/**
	 * Period id @see Piwik_Period::getId()
	 *
	 * @var int
	 */
	protected $periodId;
	
	/**
	 * Timestamp for the first date of the period
	 *
	 * @var int unix timestamp
	 */
	protected $timestampDateStart = null;
	
	/**
	 * Starting date of the archive
	 * 
	 * @var Piwik_Date
	 */
	protected $dateStart;
	
	/**
	 * Ending date of the archive
	 * 
	 * @var Piwik_Date
	 */
	protected $dateEnd;
	
	/**
	 * Object used to generate (depending on the $dateStart) the name of the DB table to use to store numeric values
	 * 
	 * @var Piwik_TablePartitioning
	 */
	protected $tableArchiveNumeric;
	
	/**
	 * Object used to generate (depending on the $dateStart)  the name of the DB table to use to store numeric values
	 * 
	 * @var Piwik_TablePartitioning
	 */
	protected $tableArchiveBlob;
	
	/**
	 * Minimum timestamp looked at for processed archives  
	 *
	 * @var int
	 */
	protected $minDatetimeArchiveProcessedUTC = false;

	/**
	 * Compress blobs
	 *
	 * @var bool
	 */
	protected $compressBlob;
	
	/**
	 * Is the current archive temporary. ie.
	 * - today 
	 * - current week / month / year
	 */
	protected $temporaryArchive;
	
	/**
	 * Id of the current site
	 * Can be accessed by plugins (that is why it's public)
	 * 
	 * @var int
	 */
	public $idsite	= null;
	
	/**
	 * Period of the current archive
	 * Can be accessed by plugins (that is why it's public)
	 * 
	 * @var Piwik_Period
	 */
	public $period 	= null;
	
	/**
	 * Site of the current archive
	 * Can be accessed by plugins (that is why it's public)
	 * 
	 * @var Piwik_Site
	 */
	public $site 	= null;
	
	/**
	 * @var Piwik_Segment
	 */
	protected $segment = null;
	
	/**
	 * Current time.
	 * This value is cached.
	 *
	 * @var int
	 */
	public $time	= null;

	/**
	 * Starting datetime in UTC
	 *
	 * @var string
	 */
	public $startDatetimeUTC;
	
	/**
	 * Ending date in UTC
	 *
	 * @var string
	 */
	public $strDateEnd;
	
	/**
	 * Name of the DB table _log_visit
	 *
	 * @var string
	 */
	public $logTable;
	
	/**
	 * When set to true, we always archive, even if the archive is already available.
	 * You can change this settings automatically in the config/global.ini.php always_archive_data under the [Debug] section
	 *
	 * @var bool
	 */
	public $debugAlwaysArchive = false;
	
	/**
	 * If the archive has at least 1 visit, this is set to true.
	 *
	 * @var bool
	 */
	public $isThereSomeVisits = null;
	
	protected $startTimestampUTC;
	protected $endTimestampUTC;
	
	protected $segmentsToProcess = null;
	
	/**
	 * Constructor
	 */
	public function __construct()
	{
		$this->time = time();
	}
	
	/**
	 * Returns the Piwik_ArchiveProcessing_Day or Piwik_ArchiveProcessing_Period object
	 * depending on $name period string
	 *
	 * @param string $name day|week|month|year
	 * @return Piwik_ArchiveProcessing Piwik_ArchiveProcessing_Day|Piwik_ArchiveProcessing_Period
	 */
	static function factory($name)
	{
		switch($name)
		{
			case 'day':
				$process = new Piwik_ArchiveProcessing_Day();		
				$process->debugAlwaysArchive = Zend_Registry::get('config')->Debug->always_archive_data_day;
			break;
			
			case 'week':
			case 'month':
			case 'year':
				$process = new Piwik_ArchiveProcessing_Period();
				$process->debugAlwaysArchive = Zend_Registry::get('config')->Debug->always_archive_data_period;
			break;

			case 'range':
				$process = new Piwik_ArchiveProcessing_Period();
				$process->debugAlwaysArchive = Zend_Registry::get('config')->Debug->always_archive_data_range;
			break;
			
			default:
				throw new Exception("Unknown Archiving period specified '$name'");
			break;
		}
		return $process;
	}
	
	const OPTION_TODAY_ARCHIVE_TTL = 'todayArchiveTimeToLive';
	const OPTION_BROWSER_TRIGGER_ARCHIVING = 'enableBrowserTriggerArchiving';

	static public function getCoreMetrics()
	{
		return array(
			'nb_uniq_visitors', 
			'nb_visits',
			'nb_actions', 
			'sum_visit_length',
			'bounce_count',
			'nb_visits_converted',
		);
	}
	
	static public function setTodayArchiveTimeToLive($timeToLiveSeconds)
	{
		$timeToLiveSeconds = (int)$timeToLiveSeconds;
		if($timeToLiveSeconds <= 0)
		{
			throw new Exception(Piwik_TranslateException('General_ExceptionInvalidArchiveTimeToLive'));
		}
		Piwik_SetOption(self::OPTION_TODAY_ARCHIVE_TTL, $timeToLiveSeconds, $autoload = true);
	}
	
	static public function getTodayArchiveTimeToLive()
	{
		$timeToLive = Piwik_GetOption(self::OPTION_TODAY_ARCHIVE_TTL);
		if($timeToLive !== false)
		{
			return $timeToLive;
		}
		return Zend_Registry::get('config')->General->time_before_today_archive_considered_outdated;
	}

	static public function setBrowserTriggerArchiving($enabled)
	{
		if(!is_bool($enabled))
		{
			throw new Exception('Browser trigger archiving must be set to true or false.');
		}
		Piwik_SetOption(self::OPTION_BROWSER_TRIGGER_ARCHIVING, (int)$enabled, $autoload = true);
		Piwik_Common::clearCacheGeneral();
	}
	static public function isBrowserTriggerArchivingEnabled()
	{
		$browserArchivingEnabled = Piwik_GetOption(self::OPTION_BROWSER_TRIGGER_ARCHIVING);
		if($browserArchivingEnabled !== false)
		{
			return (bool)$browserArchivingEnabled;
		}
		return (bool)Zend_Registry::get('config')->General->enable_browser_archiving_triggering;
	}
	
	public function getIdArchive()
	{
		return $this->idArchive;
	}
	
	/**
	 * Sets object attributes that will be used throughout the process
	 */
	public function init()
	{
		$this->idsite = $this->site->getId();
		$this->periodId = $this->period->getId();

		$dateStartLocalTimezone = $this->period->getDateStart();
		$dateEndLocalTimezone = $this->period->getDateEnd();
		
		$this->tableArchiveNumeric = new Piwik_TablePartitioning_Monthly('archive_numeric');
		$this->tableArchiveNumeric->setIdSite($this->idsite);
		$this->tableArchiveNumeric->setTimestamp($dateStartLocalTimezone->getTimestamp());
		$this->tableArchiveBlob = new Piwik_TablePartitioning_Monthly('archive_blob');
		$this->tableArchiveBlob->setIdSite($this->idsite);	
		$this->tableArchiveBlob->setTimestamp($dateStartLocalTimezone->getTimestamp());

		$dateStartUTC = $dateStartLocalTimezone->setTimezone($this->site->getTimezone());
		$dateEndUTC = $dateEndLocalTimezone->setTimezone($this->site->getTimezone());
		$this->startDatetimeUTC = $dateStartUTC->getDateStartUTC();
		$this->endDatetimeUTC = $dateEndUTC->getDateEndUTC();
		$this->startTimestampUTC = $dateStartUTC->getTimestamp();
		$this->endTimestampUTC = strtotime($this->endDatetimeUTC);
		
		$this->minDatetimeArchiveProcessedUTC = $this->getMinTimeArchivedProcessed();
		$db = Zend_Registry::get('db');
		$this->compressBlob = $db->hasBlobDataType();
	}

	public function getStartDatetimeUTC()
	{
		return $this->startDatetimeUTC;
	}
	
	public function getEndDatetimeUTC()
	{
		return $this->endDatetimeUTC;
	}
	
	public function isArchiveTemporary()
	{
		return $this->temporaryArchive;
	}
	
	/**
	 * Returns the minimum archive processed datetime to look at
	 *  
	 * @return string Datetime string, or false if must look at any archive available
	 */
	public function getMinTimeArchivedProcessed()
	{
		$this->temporaryArchive = false;
		// if the current archive is a DAY and if it's today,
		// we set this minDatetimeArchiveProcessedUTC that defines the lifetime value of today's archive
		if( $this->period->getNumberOfSubperiods() == 0
			&& ($this->startTimestampUTC <= $this->time && $this->endTimestampUTC > $this->time)
			)
		{
			$this->temporaryArchive = true;
			$minDatetimeArchiveProcessedUTC = $this->time - self::getTodayArchiveTimeToLive();
			// see #1150; if new archives are not triggered from the browser, 
			// we still want to try and return the latest archive available for today (rather than return nothing)
			if($this->isArchivingDisabled())
			{
				return false;
			}
		}
		// - if the period we are looking for is finished, we look for a ts_archived that 
		//   is greater than the last day of the archive 
		elseif($this->endTimestampUTC <= $this->time)
		{
			$minDatetimeArchiveProcessedUTC = $this->endTimestampUTC+1;
		}
		// - if the period we're looking for is not finished, we look for a recent enough archive
		else
		{
			$this->temporaryArchive = true;
			
			// We choose to only look at archives that are newer than the specified timeout
			$minDatetimeArchiveProcessedUTC = $this->time - self::getTodayArchiveTimeToLive();
			
			// However, if archiving is disabled for this request, we shall 
			// accept any archive that was processed today after 00:00:01 this morning
			if($this->isArchivingDisabled())
			{
				$timezone = $this->site->getTimezone();
				$minDatetimeArchiveProcessedUTC = Piwik_Date::factory(Piwik_Date::factory('now', $timezone)->getDateStartUTC())->setTimezone($timezone)->getTimestamp();
			}
		}
		return $minDatetimeArchiveProcessedUTC;
	}
	
	/**
	 * This method returns the idArchive ; if necessary, it triggers the archiving process.
	 * 
	 * If the archive was not processed yet, it will launch the archiving process.
	 * If the current archive needs sub-archives (eg. a month archive needs all the days archive)
	 *  it will recursively launch the archiving (using this loadArchive() on the sub-periods)
	 *
	 * @return int|false The idarchive of the archive, false if the archive is not archived yet
	 */
	public function loadArchive()
	{
		$this->init();
		if($this->debugAlwaysArchive)
		{
			return false;
		}
		$this->idArchive = $this->isArchived();
	
		if($this->idArchive === false)
		{
			return false;
		}
		return $this->idArchive;
	}
	
	/**
	 * @see loadArchive()
	 */
	public function launchArchiving()
	{
		if (!Piwik::getArchiveProcessingLock($this->idsite, $this->period, $this->segment))
		{
			// unable to get lock
			Piwik::log('Unable to get lock for idSite = ' . $this->idsite
				. ', period = ' . $this->period->getLabel()
				. ', UTC datetime [' . $this->startDatetimeUTC . ' -> ' . $this->endDatetimeUTC . ' ]...');
			return;
		}

		$this->initCompute();
		$this->compute();
		$this->postCompute();
		// we execute again the isArchived that does some initialization work
		$this->idArchive = $this->isArchived();
		Piwik::releaseArchiveProcessingLock($this->idsite, $this->period, $this->segment);
	}
	
	/**
	 * This methods reads the subperiods if necessary, 
	 * and computes the archive of the current period.
	 */
	abstract protected function compute();
	
	abstract public function isThereSomeVisits();
	
	protected function getDoneStringFlag($flagArchiveAsAllPlugins = false)
	{
		$segment = $this->getSegment()->getHash();
		if(!$this->shouldProcessReportsAllPlugins($this->getSegment(), $this->period))
		{
			$pluginProcessed = self::getPluginBeingProcessed($this->getRequestedReport());
//			Piwik::log("Plugin processed: $pluginProcessed");
			if(!Piwik_PluginsManager::getInstance()->isPluginLoaded($pluginProcessed)
				|| $flagArchiveAsAllPlugins 
				)
			{
				$pluginProcessed = 'all';
			}
			$segment .= '.'.$pluginProcessed;
		}
	    return 'done' . $segment;
	}
	
	/**
	 * Init the object before launching the real archive processing
	 */
	protected function initCompute()
	{
		$this->loadNextIdarchive();
		$done = $this->getDoneStringFlag();
		$this->insertNumericRecord($done, Piwik_ArchiveProcessing::DONE_ERROR);
		
		// Can be removed when GeoIp is in core
		$this->logTable = Piwik_Common::prefixTable('log_visit');
		
		$temporary = 'definitive archive';
		if($this->isArchiveTemporary())
		{
			$temporary = 'temporary archive';
		}
		Piwik::log("'" . $this->period->getLabel() . "'" 
								.", idSite = ". $this->idsite." ($temporary)" 
								.", segment = '". $this->getSegment()->getString()."'"
								.", report = '". $this->getRequestedReport()."'" 
								.", UTC datetime [".$this->startDatetimeUTC." -> ".$this->endDatetimeUTC." ]...");
	}
	
	/**
	 * Post processing called at the end of the main archive processing.
	 * Makes sure the new archive is marked as "successful" in the DB
	 * 
	 * We also try to delete some stuff from memory but really there is still a lot...
	 */
	protected function postCompute()
	{
		// delete the first done = ERROR 
		$done = $this->getDoneStringFlag();
		Piwik_Query("DELETE FROM ".$this->tableArchiveNumeric->getTableName()." 
					WHERE idarchive = ? AND name = '".$done."'",
					array($this->idArchive)
		);
		
		$flag = Piwik_ArchiveProcessing::DONE_OK;
		if($this->isArchiveTemporary())
		{
			$flag = Piwik_ArchiveProcessing::DONE_OK_TEMPORARY;
		}
		$this->insertNumericRecord($done, $flag);
	}
	
	/**
	 * Returns the name of the numeric table where the archive numeric values are stored
	 *
	 * @return string 
	 */
	public function getTableArchiveNumericName()
	{
		return $this->tableArchiveNumeric->getTableName();
	}
	
	/**
	 * Returns the name of the blob table where the archive blob values are stored
	 *
	 * @return string 
	 */
	public function getTableArchiveBlobName()
	{
		return $this->tableArchiveBlob->getTableName();
	}
	
	/**
	 * Set the period
	 *
	 * @param Piwik_Period $period
	 */
	public function setPeriod( Piwik_Period $period ) 
	{
		$this->period = $period;
	}
	
	public function setSegment( Piwik_Segment $segment) 
	{
	    $this->segment = $segment;
	}
	
	public function getSegment()
	{
	    return $this->segment;
	}
	/**
	 * Set the site
	 *
	 * @param Piwik_Site $site
	 */
	public function setSite( Piwik_Site $site )
	{
		$this->site = $site;
	}
	
	public function setRequestedReport($requestedReport)
	{
		$this->requestedReport = $requestedReport;
	}

	protected function getRequestedReport()
	{
   		return $this->requestedReport;
	}

	static public function getPluginBeingProcessed( $requestedReport )
	{
		return substr($requestedReport, 0, strpos($requestedReport, '_'));
	}
	
	/**
	 * Returns the timestamp of the first date of the period
	 *
	 * @return int
	 */
	public function getTimestampStartDate()
	{
		return $this->timestampDateStart;
	}

	// exposing the number of visits publicly (number used to compute conversions rates)
	protected $nb_visits = null;
	protected $nb_visits_converted = null;
	
	protected function setNumberOfVisits($nb_visits)
	{
		$this->nb_visits = $nb_visits;
	}
	public function getNumberOfVisits()
	{
		return $this->nb_visits;
	}
	protected function setNumberOfVisitsConverted($nb_visits_converted)
	{
		$this->nb_visits_converted = $nb_visits_converted;
	}
	public function getNumberOfVisitsConverted()
	{
		return $this->nb_visits_converted;
	}
	
	/**
	 * Returns the idArchive we will use for the current archive
	 *
	 * @return int IdArchive to use when saving the current Archive
	 */
	protected function loadNextIdarchive()
	{
		$db = Zend_Registry::get('db');
		$id = $db->fetchOne("SELECT max(idarchive) 
							FROM ".$this->tableArchiveNumeric->getTableName());
		if(empty($id))
		{
			$id = 0;
		}
		$this->idArchive = $id + 1;
		
	}

	/**
	 * @param string $name
	 * @param int|float $value
	 */
	public function insertNumericRecord($name, $value)
	{
		$value = round($value, 2);
		return $this->insertRecord($name, $value);
	}
	
	/**
	 * @param string $name
	 * @param string|array of string $aValues
	 * @return true 
	 */
	public function insertBlobRecord($name, $values)
	{
		if(is_array($values))
		{
			$clean = array();
			foreach($values as $id => $value)
			{
				// for the parent Table we keep the name
				// for example for the Table of searchEngines we keep the name 'referer_search_engine'
				// but for the child table of 'Google' which has the ID = 9 the name would be 'referer_search_engine_9'
				$newName = $name;
				if($id != 0)
				{
					$newName = $name . '_' . $id;
				}
				
				if($this->compressBlob)
				{
					$value = $this->compress($value);
				}
				$clean[] = array($newName, $value);
			}
			return $this->insertBulkRecords($clean);
		}
		
		if($this->compressBlob)
		{
			$values = $this->compress($values);
		}

		$this->insertRecord($name, $values);
		return array($name => $values);
	}
	
	protected function compress($data)
	{
		return gzcompress($data);
	}
	
	protected function insertBulkRecords($records)
	{
		// Using standard plain INSERT if there is only one record to insert
		if($DEBUG_DO_NOT_USE_BULK_INSERT = false
			|| count($records) == 1)
		{
			foreach($records as $record)
			{
				$this->insertRecord($record[0], $record[1]);
			}
			return ;
		}
		$bindSql = $this->getBindArray();
		$values = array();

		foreach($records as $record)
		{
			// don't record zero
			if(empty($record[1])) continue;

			$bind = $bindSql;
			$bind[] = $record[0]; // name
			$bind[] = $record[1]; // value
			$values[] = $bind;
			
		}
		if(empty($values)) return ;
		
		if(is_numeric($record[1]))
		{
			$table = $this->tableArchiveNumeric;
		}
		else
		{
			$table = $this->tableArchiveBlob;
		}

		Piwik::tableInsertBatch($table->getTableName(), $this->getInsertFields(), $values);
		return true;
	}
	
	protected function getBindArray()
	{
		return array(	$this->idArchive,
						$this->idsite, 
						$this->period->getDateStart()->toString('Y-m-d'), 
						$this->period->getDateEnd()->toString('Y-m-d'), 
						$this->periodId, 
						date("Y-m-d H:i:s"));
	}
	
	protected function getInsertFields()
	{
		return array('idarchive', 'idsite', 'date1', 'date2', 'period', 'ts_archived', 'name', 'value');
	}
	
	/**
	 * Inserts a record in the right table (either NUMERIC or BLOB)
	 *
	 */
	protected function insertRecord($name, $value)
	{
		// table to use to save the data
		if(is_numeric($value))
		{
			// We choose not to record records with a value of 0 
			if($value == 0)
			{
				return;
			}
			$table = $this->tableArchiveNumeric;
		}
		else
		{
			$table = $this->tableArchiveBlob;
		}
		
		// duplicate idarchives are Ignored, see http://dev.piwik.org/trac/ticket/987
		
		$query = "INSERT IGNORE INTO ".$table->getTableName()." 
					(". implode(", ", $this->getInsertFields()).")
					VALUES (?,?,?,?,?,?,?,?)";
		$bindSql = $this->getBindArray();
		$bindSql[] = $name;
		$bindSql[] = $value;
//		var_dump($bindSql);
		Piwik_Query($query, $bindSql);
	}
	
	/**
	 * Returns the idArchive if the archive is available in the database.
	 * Returns false if the archive needs to be computed.
	 * 
	 * An archive is available if
	 * - for today, the archive was computed less than minDatetimeArchiveProcessedUTC seconds ago
	 * - for any other day, if the archive was computed once this day was finished
	 * - for other periods, if the archive was computed once the period was finished
	 *
	 * @return int|false
	 */
	protected function isArchived()
	{
		$bindSQL = array(	$this->idsite, 
							$this->period->getDateStart()->toString('Y-m-d'), 
							$this->period->getDateEnd()->toString('Y-m-d'), 
							$this->periodId, 
		);
		
		$timeStampWhere = '';
		
		if($this->minDatetimeArchiveProcessedUTC)
		{
			$timeStampWhere = " AND ts_archived >= ? ";
			$bindSQL[] = Piwik_Date::factory($this->minDatetimeArchiveProcessedUTC)->getDatetime();
		}

		// When a Segment is specified, we try and only process the requested report in the archive
		// As a limitation, we don't know all the time which plugin should process which report
		// There is a catch all flag 'all' appended to archives containing all reports already
		// We look for this 'done.ABCDEFG.all', or for an archive that contains only our plugin data 'done.ABDCDEFG.Referers'
		$done = $this->getDoneStringFlag();
		$doneAllPluginsProcessed = $this->getDoneStringFlag($flagArchiveAsAllPlugins = true);
		
		$sqlSegmentsFindArchiveAllPlugins = '';
		
		if($done != $doneAllPluginsProcessed)
		{
			$sqlSegmentsFindArchiveAllPlugins = "OR (name = '".$doneAllPluginsProcessed."' AND value = ".Piwik_ArchiveProcessing::DONE_OK.")
					OR (name = '".$doneAllPluginsProcessed."' AND value = ".Piwik_ArchiveProcessing::DONE_OK_TEMPORARY.")";
		}
		$sqlQuery = "	SELECT idarchive, value, name, date1 as startDate
						FROM ".$this->tableArchiveNumeric->getTableName()."
						WHERE idsite = ?
							AND date1 = ?
							AND date2 = ?
							AND period = ?
							AND ( (name = '".$done."' AND value = ".Piwik_ArchiveProcessing::DONE_OK.")
									OR (name = '".$done."' AND value = ".Piwik_ArchiveProcessing::DONE_OK_TEMPORARY.")
									$sqlSegmentsFindArchiveAllPlugins
									OR name = 'nb_visits')
							$timeStampWhere
						ORDER BY ts_archived DESC";
		$results = Piwik_FetchAll($sqlQuery, $bindSQL );
		if(empty($results))
		{
			return false;
		}
		
		$idarchive = false;
		// we look for the more recent idarchive
		foreach($results as $result)
		{
			if($result['name'] == $done
				|| $result['name'] == $doneAllPluginsProcessed)
			{
				$idarchive = $result['idarchive'];
				$this->timestampDateStart = Piwik_Date::factory($result['startDate'])->getTimestamp();
				break;
			}
		}
		
		// case when we have a nb_visits entry in the archive, but the process is not finished yet or failed to finish
		// therefore we don't have the done=OK
		if($idarchive === false)
		{
			return false;
		}
		
		if($this->getPluginBeingProcessed($this->getRequestedReport()) == 'VisitsSummary')
		{
			$this->isThereSomeVisits = false;
		}
		
		// we look for the nb_visits result for this most recent archive
		foreach($results as $result)
		{
			if($result['name'] == 'nb_visits' 
				&& $result['idarchive'] == $idarchive)
			{
				$this->isThereSomeVisits = ($result['value'] > 0);
				$this->setNumberOfVisits($result['value']);
				break;
			}
		}
		return $idarchive;
	}

	/**
	 * Returns true if, for some reasons, triggering the archiving is disabled.
	 * Note that when a segment is passed to the function, archiving will always occur 
	 * (since segments are by default not pre-processed)
	 *
	 * @return bool
	 */
	public function isArchivingDisabled()
	{
		$processOneReportOnly = !$this->shouldProcessReportsAllPlugins($this->getSegment(), $this->period);
		if($processOneReportOnly)
		{
			// When there is a segment, archiving is not necessary allowed
			// If browser archiving is allowed, then archiving is enabled
			// if browser archiving is not allowed, then archiving is disabled
			if(!$this->getSegment()->isEmpty()
				&& !$this->isRequestAuthorizedToArchive()
				&& Zend_Registry::get('config')->General->browser_archiving_disabled_enforce
			)
			{
				Piwik::log("Archiving is disabled because of config setting browser_archiving_disabled_enforce=1");
				return true;
			}
			return false;
		}
		$isDisabled = !$this->isRequestAuthorizedToArchive();
		return $isDisabled;
	}
	
	protected function isRequestAuthorizedToArchive()
	{
		return self::isBrowserTriggerArchivingEnabled()
				|| Piwik_Common::isPhpCliMode()
				|| (Piwik::isUserIsSuperUser() 
					&& Piwik_Common::isArchivePhpTriggered())
					;
	}
	
	/**
	 * Returns true when 
	 * - there is no segment and period is not range 
	 * - there is a segment that is part of the preprocessed [Segments] list
	 */
	protected function shouldProcessReportsAllPlugins($segment, $period)
	{
		if($segment->isEmpty() && $period->getLabel() != 'range')
		{
			return true;
		}
		
		if(is_null($this->segmentsToProcess))
		{
			$this->segmentsToProcess = Piwik::getKnownSegmentsToArchive();
		}
		if(!empty($this->segmentsToProcess))
		{
			// If the requested segment is one of the segments to pre-process
			// we ensure that any call to the API will trigger archiving of all reports for this segment
			$segment = $this->getSegment()->getString();
			if(in_array($segment, $this->segmentsToProcess))
			{
				return true;
			}
		}
		return false;
	}
	
	/**
	 * When a segment is set, we shall only process the requested report (no more).
	 * The requested data set will return a lot faster if we only process these reports rather than all plugins.
	 * Similarly, when a period=range is requested, we shall only process the requested report for the range itself.
	 * 
	 * @param string $pluginName
	 * @return bool
	 */
	public function shouldProcessReportsForPlugin($pluginName)
	{
		if($this->shouldProcessReportsAllPlugins($this->getSegment(), $this->period))
		{
			return true;
		}
		
		// If any other segment, only process if the requested report belong to this plugin
		// or process all plugins if the requested report plugin couldn't be guessed
		$pluginBeingProcessed = self::getPluginBeingProcessed($this->getRequestedReport());
		return $pluginBeingProcessed == $pluginName
				|| !Piwik_PluginsManager::getInstance()->isPluginLoaded($pluginBeingProcessed)
				; 
	}
	
}
