<?php
/**
 * Piwik - Open source web analytics
 * 
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id: Period.php 4311 2011-04-04 18:49:55Z vipsoft $
 * 
 * @category Piwik
 * @package Piwik
 */

/**
 * Creating a new Piwik_Period subclass: 
 * 
 * Every overloaded method must start with the code
 * 		if(!$this->subperiodsProcessed)
 *		{
 *			$this->generate();
 *		}
 *	that checks whether the subperiods have already been computed.
 *	This is for performance improvements, computing the subperiods is done a per demand basis.
 *
 * @package Piwik
 * @subpackage Piwik_Period
 */
abstract class Piwik_Period
{
	protected $subperiods = array();
	protected $subperiodsProcessed = false;
	protected $label = null;
	protected $date = null;
	static protected $errorAvailablePeriods = 'day, week, month, year, range';
	
	public function __construct( $date )
	{	
		$this->checkInputDate( $date );
		$this->date = clone $date;
	}
	
	/**
	 * @param string $strPeriod "day", "week", "month", "year"
	 * @param Piwik_Date $date Piwik_Date object
	 * @return Piwik_Period
	 */
	static public function factory($strPeriod, Piwik_Date $date)
	{
		switch ($strPeriod) {
			case 'day':
				return new Piwik_Period_Day($date); 
				break;
		
			case 'week':
				return new Piwik_Period_Week($date); 
				break;
				
			case 'month':
				return new Piwik_Period_Month($date); 
				break;
				
			case 'year':
				return new Piwik_Period_Year($date); 
				break;
				
			default:
				throw new Exception(Piwik_TranslateException('General_ExceptionInvalidPeriod', array($strPeriod, self::$errorAvailablePeriods))); 
				break;
		}
	}

	
	/**
	 * Returns the first day of the period
	 *
	 * @return Piwik_Date First day of the period
	 */
	public function getDateStart()
	{
		if(!$this->subperiodsProcessed)
		{
			$this->generate();
		}
		if(count($this->subperiods) == 0)
		{
			return $this->getDate();
		}
		$periods = $this->getSubperiods();
		$currentPeriod = $periods[0];
		while( $currentPeriod->getNumberOfSubperiods() > 0 )
		{
			$periods = $currentPeriod->getSubperiods();
			$currentPeriod = $periods[0];
		}
		return $currentPeriod->getDate();
	}
	
	/**
	 * Returns the last day of the period ; can be a date in the future
	 *
	 * @return Piwik_Date Last day of the period 
	 */
	public function getDateEnd()
	{
		if(!$this->subperiodsProcessed)
		{
			$this->generate();
		}
		if(count($this->subperiods) == 0)
		{
			return $this->getDate();
		}
		$periods = $this->getSubperiods();
		$currentPeriod = $periods[count($periods)-1];
		while( $currentPeriod->getNumberOfSubperiods() > 0 )
		{
			$periods = $currentPeriod->getSubperiods();
			$currentPeriod = $periods[count($periods)-1];
		}
		return $currentPeriod->getDate();
	}
	
	public function getId()
	{
		return Piwik::$idPeriods[$this->getLabel()];
	}

	public function getLabel()
	{
		return $this->label;
	}
	
	/**
	 * @return Piwik_Date
	 */
	protected function getDate()
	{
		return $this->date;
	}	
	
	protected function checkInputDate($date)
	{
		if( !($date instanceof Piwik_Date))
		{
			throw new Exception("The date must be a Piwik_Date object. " . var_export($date,true));
		}
	}
	
	protected function generate()
	{
		$this->subperiodsProcessed = true;
	}
	
	public function getNumberOfSubperiods()
	{
		if(!$this->subperiodsProcessed)
		{
			$this->generate();
		}
		return count($this->subperiods);
	}
	
	/**
	 * Returns Period_Day for a period made of days (week, month),
	 * 			Period_Month for a period made of months (year) 
	 * 
	 * @return array Piwik_Period
	 */
	public function getSubperiods()
	{
		if(!$this->subperiodsProcessed)
		{
			$this->generate();
		}
		return $this->subperiods;
	}

	/**
	 * Add a date to the period.
	 * 
	 * Protected because it not yet supported to add periods after the initialization
	 * 
	 * @param Piwik_Date Valid Piwik_Date object
	 */
	protected function addSubperiod( $date )
	{
		$this->subperiods[] = $date;
	}
	
	public function toString($format = "Y-m-d")
	{
		if(!$this->subperiodsProcessed)
		{
			$this->generate();
		}
		$dateString = array();
		foreach($this->subperiods as $period)
		{
			$dateString[] = $period->toString($format);
		}
		return $dateString;
	}
	
	public function __toString()
	{
		return implode(",", $this->toString());
	}
	
	public function get( $part= null )
	{
		if(!$this->subperiodsProcessed)
		{
			$this->generate();
		}
		return $this->date->toString($part);
	}
	
	abstract public function getPrettyString();
	abstract public function getLocalizedShortString();
	abstract public function getLocalizedLongString();
}
