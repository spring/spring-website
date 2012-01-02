<?php
/**
 * Piwik - Open source web analytics
 * 
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id: DocumentationGenerator.php 4691 2011-05-15 22:14:48Z matt $
 * 
 * @category Piwik
 * @package Piwik
 */

/**
 * @package Piwik
 * @subpackage Piwik_API
 */
class Piwik_API_DocumentationGenerator
{
	protected $modulesToHide = array('CoreAdminHome', 'DBStats');
	protected $countPluginsLoaded = 0;

	/**
	 * trigger loading all plugins with an API.php file in the Proxy 
	 */
	public function __construct()
	{
		$plugins = Piwik_PluginsManager::getInstance()->getLoadedPluginsName();
		foreach( $plugins as $plugin )
		{		
			$plugin = Piwik::unprefixClass($plugin);
			try {
				Piwik_API_Proxy::getInstance()->registerClass('Piwik_'.$plugin.'_API');
			}
			catch(Exception $e){
			}
		}
	}
	
	/**
	 * Returns a HTML page containing help for all the successfully loaded APIs.
	 *  For each module it will return a mini help with the method names, parameters to give, 
	 * links to get the result in Xml/Csv/etc
	 *
	 * @return string
	 */
	public function getAllInterfaceString( $outputExampleUrls = true, $prefixUrls = '' )
	{
		$str = $toc = '';
		$token_auth = "&token_auth=" . Piwik::getCurrentUserTokenAuth();
		$parametersToSet = array(
								'idSite' 	=> Piwik_Common::getRequestVar('idSite', 1, 'int'),
								'period' 	=> Piwik_Common::getRequestVar('period', 'day', 'string'),
								'date'		=> Piwik_Common::getRequestVar('date', 'today', 'string')
							);
		
		
		foreach(Piwik_API_Proxy::getInstance()->getMetadata() as $class => $info)
		{
			$moduleName = Piwik_API_Proxy::getInstance()->getModuleNameFromClassName($class);
			if(in_array($moduleName, $this->modulesToHide))
			{
				continue;
			}
			$toc .= "<a href='#$moduleName'>$moduleName</a><br/>";
			$str .= "\n<h2 name='$moduleName' id='$moduleName'>Module ".$moduleName."</h2>";
			$str .= "<div class='apiDescription'> ".$info['__documentation'] . " </div>";
			foreach($info as $methodName => $infoMethod)
			{
				if($methodName=='__documentation')
				{
					continue;
				}
				$params = $this->getParametersString($class, $methodName);
				$str .= "\n <div class='apiMethod'>- <b>$moduleName.$methodName </b>" . $params . "";
				$str .= '<small>';
				
				if($outputExampleUrls)
				{
					// we prefix all URLs with $prefixUrls
					// used when we include this output in the Piwik official documentation for example
					$str .= "<span class=\"example\">";
					$exampleUrl = $this->getExampleUrl($class, $methodName, $parametersToSet);
					if($exampleUrl !== false)
					{
						$lastNUrls = '';
						if( preg_match('/(&period)|(&date)/',$exampleUrl))
						{
							$exampleUrlRss1 = $prefixUrls . $this->getExampleUrl($class, $methodName, array('date' => 'last10', 'period' => 'day') + $parametersToSet) ;
							$exampleUrlRss2 = $prefixUrls . $this->getExampleUrl($class, $methodName, array('date' => 'last5','period' => 'week',) + $parametersToSet );
							$lastNUrls = ",	RSS of the last <a target=_blank href='$exampleUrlRss1&format=rss$token_auth'>10 days</a>";
						}
						$exampleUrl = $prefixUrls . $exampleUrl ;
						$str .= " [ Example in  
									<a target=_blank href='$exampleUrl&format=xml$token_auth'>XML</a>, 
									<a target=_blank href='$exampleUrl&format=JSON$token_auth'>Json</a>, 
									<a target=_blank href='$exampleUrl&format=Tsv$token_auth'>Tsv (Excel)</a> 
									$lastNUrls
									]";
					}
					else
					{
						$str .= " [ No example available ]";
					}
					$str .= "</span>";
				}
				$str .= '</small>';
				$str .= "</div>\n";
			}
		}
		
		$str = "<h2>Quick access to APIs</h2> 
				$toc 
				$str";
		return $str;
	}
	
	/**
	 * Returns a string containing links to examples on how to call a given method on a given API
	 * It will export links to XML, CSV, HTML, JSON, PHP, etc.
	 * It will not export links for methods such as deleteSite or deleteUser 
	 *
	 * @param string the class 
	 * @param methodName the method
	 * @return string|false when not possible
	 */
	public function getExampleUrl($class, $methodName, $parametersToSet = array())
	{
		$knowExampleDefaultParametersValues = array(
			'access' => 'view',
			'userLogin' => 'test',
			'passwordMd5ied' => 'passwordExample',
			'email' => 'test@example.org',
		
			'languageCode' => 'fr',
			'url' => 'http://forum.piwik.org/',
			'apiModule' => 'UserCountry',
			'apiAction' => 'getCountry',
			'lastMinutes' => '30',
			'abandonedCarts' => '0',
		);
		
		foreach($parametersToSet as $name => $value)
		{
			$knowExampleDefaultParametersValues[$name] = $value;
		}
		
		// no links for these method names
		$doNotPrintExampleForTheseMethods = array(
			//Sites
			'deleteSite',
			'addSite',
			'updateSite',
			'addSiteAliasUrls',
			//Users
			'deleteUser',
			'addUser',
			'updateUser',
			'setUserAccess',
			//Goals
			'addGoal',
			'updateGoal',
			'deleteGoal',
		);
		
		if(in_array($methodName,$doNotPrintExampleForTheseMethods))
		{
			return false;
		}
		
		// we try to give an URL example to call the API
		$aParameters = Piwik_API_Proxy::getInstance()->getParametersList($class, $methodName);
		// Kindly force some known generic parameters to appear in the final list
		// the parameter 'format' can be set to all API methods (used in tests)
		// the parameter 'hideIdSubDatable' is used for integration tests only
		// the parameter 'serialize' sets php outputs human readable, used in integration tests and debug
		// the parameter 'language' sets the language for the response (eg. country names)
		$aParameters['format'] = false;
		$aParameters['hideIdSubDatable'] = false;
		$aParameters['serialize'] = false;
		$aParameters['language'] = false;
		
		$moduleName = Piwik_API_Proxy::getInstance()->getModuleNameFromClassName($class);
		$urlExample = '?module=API&method='.$moduleName.'.'.$methodName.'&';
		foreach($aParameters as $nameVariable=> $defaultValue)
		{
			if(isset($knowExampleDefaultParametersValues[$nameVariable]))
			{
				$exampleValue = $knowExampleDefaultParametersValues[$nameVariable];
				$urlExample .= $nameVariable . '=' . $exampleValue . '&';
			}
			// if there isn't a default value for a given parameter, 
			// we need a 'know default value' or we can't generate the link
			elseif($defaultValue instanceof Piwik_API_Proxy_NoDefaultValue)
			{
				return false;
			}
		}
		return substr($urlExample,0,-1);
	}
	
	
	/**
	 * Returns the methods $class.$name parameters (and default value if provided) as a string.
	 * 
	 * @param string The class name
	 * @param string The method name
	 * @return string For example "(idSite, period, date = 'today')"
	 */
	public function getParametersString($class, $name)
	{
		$aParameters = Piwik_API_Proxy::getInstance()->getParametersList($class, $name);
		$asParameters = array();
		foreach($aParameters as $nameVariable=> $defaultValue)
		{
			// Do not show API parameters starting with _ 
			// They are supposed to be used only in internal API calls
			if(strpos($nameVariable, '_') === 0)
			{
				continue;
			}
			$str = $nameVariable;
			if(!($defaultValue instanceof Piwik_API_Proxy_NoDefaultValue))
			{
				$str .= " = '$defaultValue'";
			}
			$asParameters[] = $str;
		}
		$sParameters = implode(", ", $asParameters);
		return "($sParameters)";
	}	
}
