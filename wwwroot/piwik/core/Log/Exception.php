<?php
/**
 * Piwik - Open source web analytics
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id: Exception.php 2968 2010-08-20 15:26:33Z vipsoft $
 *
 * @category Piwik
 * @package Piwik
 */

/**
 * Class used to log an exception event.
 * Displays the exception with a user friendly error message, suggests to get support from piwik.org
 *
 * @package Piwik
 * @subpackage Piwik_Log
 */
class Piwik_Log_Exception extends Piwik_Log
{
	const ID = 'logger_exception';
	function __construct()
	{
		$logToFileFilename = self::ID;
		$logToDatabaseTableName = self::ID;
		$logToDatabaseColumnMapping = null;
		$screenFormatter = new Piwik_Log_Exception_Formatter_ScreenFormatter();
		$fileFormatter = new Piwik_Log_Formatter_FileFormatter();

		parent::__construct($logToFileFilename,
							$fileFormatter,
							$screenFormatter,
							$logToDatabaseTableName,
							$logToDatabaseColumnMapping );
	}

	function addWriteToScreen()
	{
		parent::addWriteToScreen();
		$writerScreen = new Zend_Log_Writer_Stream('php://stderr');
		$writerScreen->setFormatter( $this->screenFormatter );
		$this->addWriter($writerScreen);
	}

	public function logEvent($exception)
	{
		$event = array();
		$event['errno'] 	= $exception->getCode();
		$event['message'] 	= $exception->getMessage();
		$event['errfile'] 	= $exception->getFile();
		$event['errline'] 	= $exception->getLine();
		$event['backtrace'] = $exception->getTraceAsString();

		parent::log($event, Piwik_Log::CRIT, null);
	}
}

/**
 * Format an exception event to be displayed on the screen.
 *
 * @package Piwik
 * @subpackage Piwik_Log
 */
class Piwik_Log_Exception_Formatter_ScreenFormatter extends Piwik_Log_Formatter_ScreenFormatter
{
	/**
	 * Formats data into a single line to be written by the writer.
	 *
	 * @param  array    $event    event data
	 * @return string             formatted line to write to the log
	 */
	public function format($event)
	{
    	$event = parent::formatEvent($event);
		$errno = $event['errno'] ;
		$errstr = $event['message'] ;
		$errfile = $event['errfile'] ;
		$errline = $event['errline'] ;
		$backtrace = $event['backtrace'] ;

		$outputFormat = strtolower(Piwik_Common::getRequestVar('format', 'html', 'string'));
		$response = new Piwik_API_ResponseBuilder($outputFormat);
		$message = $response->getResponseException(new Exception($errstr));
		return parent::format($message);
	}
}
