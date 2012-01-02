<?php
/**
 * Piwik - Open source web analytics
 * 
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id: Weekly.php 3364 2010-11-25 22:38:40Z JulienM $
 * 
 * @category Piwik
 * @package Piwik
 */

/**
 * Piwik_ScheduledTime_Weekly class is used to schedule tasks every week.
 *
 * @see Piwik_ScheduledTask
 * @package Piwik
 * @subpackage Piwik_ScheduledTime
 */
class Piwik_ScheduledTime_Weekly extends Piwik_ScheduledTime
{
	
	public function getRescheduledTime()
	{
		$currentTime = $this->getTime();
		
		// Adds 7 days
		$rescheduledTime = mktime ( 	date('H', $currentTime), 
									date('i', $currentTime),
									date('s', $currentTime),
									date('n', $currentTime),
									date('j', $currentTime) + 7,
									date('Y', $currentTime)
									);
		
		// Adjusts the scheduled hour
		$rescheduledTime = $this->adjustHour($rescheduledTime);
		
		// Adjusts the scheduled day
		if ( $this->day !== null )
		{
			// Removes or adds a number of days to set the scheduled day to the one specified with setDay()
			$rescheduledTime = mktime ( date('H', $rescheduledTime),
										date('i', $rescheduledTime),
										date('s', $rescheduledTime),
										date('n', $rescheduledTime),
										date('j', $rescheduledTime) - (date('N', $rescheduledTime) - $this->day),
										date('Y', $rescheduledTime)
										);
		}
		
		return $rescheduledTime;
	}
	
	/*
	 * @param  _day the day to set, has to be >= 1 and < 8
	 * @throws Exception if parameter _day is invalid
	 */
	public function setDay($_day)
	{
		if (!($_day >=1 && $_day < 8))
		{
			throw new Exception ("Invalid day parameter, must be >=1 and < 8");
		}

		$this->day = $_day;
	}
}
