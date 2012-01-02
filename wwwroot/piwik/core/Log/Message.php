<?php
/**
 * Piwik - Open source web analytics
 * 
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id: Message.php 5087 2011-08-09 07:25:23Z matt $
 * 
 * @category Piwik
 * @package Piwik
 */

/**
 * Class used to log a standard message event.
 * 
 * @package Piwik
 * @subpackage Piwik_Log
 */
class Piwik_Log_Message extends Piwik_Log
{
	const ID = 'logger_message.htm';
	function __construct()
	{
		$logToFileFilename = self::ID;
		$logToDatabaseTableName = self::ID;
		$logToDatabaseColumnMapping = null;
		$screenFormatter = new Piwik_Log_Message_Formatter_ScreenFormatter();
		$fileFormatter = new Piwik_Log_Formatter_FileFormatter();
		
		parent::__construct($logToFileFilename, 
							$fileFormatter,
							$screenFormatter,
							$logToDatabaseTableName, 
							$logToDatabaseColumnMapping );
	}
	
	public function logEvent($message)
	{
		$event = array();
		$event['message'] = $message;
		parent::log($event, Piwik_Log::INFO, null);
	}
}

/**
 * Format a standard message event to be displayed on the screen.
 * The message can be a PHP array or a string.
 * 
 * @package Piwik
 * @subpackage Piwik_Log
 */
class Piwik_Log_Message_Formatter_ScreenFormatter extends Piwik_Log_Formatter_ScreenFormatter 
{
	/**
     * Formats data into a single line to be written by the writer.
     *
     * @param  array    $event    event data
     * @return string             formatted line to write to the log
     */
    public function format($event)
    {
    	if(is_array($event['message']))
    	{
    		$message = "<pre>".var_export($event['message'], true)."</pre>";
    	}
    	else
    	{
    		$message = $event['message'];
    	}
    	if(!Piwik_Common::isPhpCliMode())
    	{
    		$message .= "<br/>";
    	}
    	$message .= "\n";
    	
    	$message = '['. $event['timestamp'] . '] ['.$event['requestKey'].'] ['.Piwik::getMemoryUsage(). '] ' .$message;
    	return parent::format($message);
    }
}
