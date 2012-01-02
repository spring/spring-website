<?php
/**
 * Piwik - Open source web analytics
 * 
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id: ExampleFeedburner.php 4528 2011-04-21 00:04:29Z vipsoft $
 * 
 * @category Piwik_Plugins
 * @package Piwik_ExampleFeedburner
 */

/**
 *
 * @package Piwik_ExampleFeedburner
 */
class Piwik_ExampleFeedburner extends Piwik_Plugin
{
	/**
	 * Return information about this plugin.
	 *
	 * @see Piwik_Plugin
	 *
	 * @return array
	 */
	public function getInformation()
	{
		return array(
			'description' => Piwik_Translate('ExampleFeedburner_PluginDescription'),
			'author' => 'Piwik',
			'author_homepage' => 'http://piwik.org/',
			'version' => '0.1',
		);
	}

	function install()
	{
		try{
			Piwik_Exec('ALTER TABLE '.Piwik_Common::prefixTable('site'). " ADD `feedburnerName` VARCHAR( 100 ) DEFAULT NULL");
		} catch(Exception $e){
			// mysql code error 1060: column already exists
			// if there is another error we throw the exception, otherwise it is OK as we are simply reinstalling the plugin
			if(!Zend_Registry::get('db')->isErrNo($e, '1060'))
			{
				throw $e;
			}
		}
	}
	
	function uninstall()
	{
		Piwik_Query('ALTER TABLE '.Piwik_Common::prefixTable('site'). " DROP `feedburnerName`");
	}
}

Piwik_AddWidget('Example Widgets', 'Feedburner statistics', 'ExampleFeedburner', 'feedburner');

/**
 *
 * @package Piwik_ExampleFeedburner
 */
class Piwik_ExampleFeedburner_Controller extends Piwik_Controller
{

	/**
	 * Simple feedburner statistics output
	 *
	 */
	function feedburner()
	{
		$view = Piwik_View::factory('feedburner');
		$idSite = Piwik_Common::getRequestVar('idSite',1,'int');
		$feedburnerFeedName = Piwik_FetchOne('SELECT feedburnerName 
											  FROM '.Piwik_Common::prefixTable('site').
											' WHERE idsite = ?', $idSite );
		if(empty($feedburnerFeedName))
		{
			$feedburnerFeedName = 'Piwik';
		}
		$view->feedburnerFeedName = $feedburnerFeedName;
		$view->idSite = $idSite;
		$view->fbStats = $this->getFeedData($feedburnerFeedName);
		echo $view->render();
	}
	

	/**
	 * Returns array of counts and images based on Feedburner URI
	 * 
	 * @param string $uri
	 * @return array()
	 */
	protected function getFeedData($uri)
	{
		// Awareness API only supports yesterday and back;
		// we get stats for previous two days;
		// @see http://code.google.com/apis/feedburner/awareness_api.html#dates
		$yesterday = Piwik_Date::factory('-1 day', 'America/Los_Angeles');
		$beforeYesterday = Piwik_Date::factory('-2 day', 'America/Los_Angeles');
		
		//create url to gather XML feed from
		$url = 'https://feedburner.google.com/api/awareness/1.0/GetFeedData?uri='.urlencode($uri).'&dates='.$beforeYesterday->toString().','.$yesterday->toString();
		$data = '';
		try {
			$data = Piwik_Http::sendHttpRequest($url, 5);

			// Feedburner errors are malformed
			if(strpos($data, 'The server encountered a temporary error') !== false)
			{
				throw new Exception('Unexpected Feedburner response');
			}
			$xml = new SimpleXMLElement($data);
		} catch(Exception $e) {
			return "Error parsing the data for feed <a href='http://feeds.feedburner.com/".urlencode($uri)."' target='_blank'>$uri</a>. Fetched data was: \n'". $data."'";
		}
		
		if(count($xml->feed->entry) != 2) {
			return "Feedburner stats didn't return as expected. \n" . strip_tags($data);
		}
		$data = array();
		$i = 0;
		foreach($xml->feed->entry as $feedDay){
			$data[0][$i] = (int)$feedDay['circulation'];
			$data[1][$i] = (int)$feedDay['hits'];
			$data[2][$i] = (int)$feedDay['reach'];
			$i++;
		}
	
		foreach($data as $key => $value) {
			if( $value[0] == $value[1]) {
				$img = 'stop.png';
			} else if($value[0] < $value[1]) {
				$img = 'arrow_up.png';
			} else {
				$img = 'arrow_down.png';
			}
			
			$prefixImage = '<img alt="" src="./plugins/MultiSites/images/';
			$suffixImage = '" />';
			$data[$key][2] = $prefixImage . $img . $suffixImage;
		}
		return $data;
	}
	
	/**
	 * Function called to save the Feedburner ID entered in the form
	 *
	 */
	function saveFeedburnerName()
	{
		// we save the value in the DB for an authenticated user
		if(Piwik::getCurrentUserLogin() != 'anonymous')
		{
			Piwik_Query('UPDATE '.Piwik_Common::prefixTable('site').' 
						 SET feedburnerName = ? WHERE idsite = ?', 
				array(Piwik_Common::getRequestVar('name','','string'), Piwik_Common::getRequestVar('idSite',1,'int'))
				);
		}
	}
}
