<?php
/**
 * Piwik - Open source web analytics
 * 
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id: Proxy.php 5287 2011-10-12 07:16:25Z EZdesign $
 * 
 * @category Piwik
 * @package Piwik
 */

/**
 * To differentiate between "no value" and default value of null
 * 
 * @package Piwik
 * @subpackage Piwik_API
 */
class Piwik_API_Proxy_NoDefaultValue {}

/**
 * Proxy is a singleton that has the knowledge of every method available, their parameters 
 * and default values.
 * Proxy receives all the API calls requests via call() and forwards them to the right 
 * object, with the parameters in the right order. 
 * 
 * It will also log the performance of API calls (time spent, parameter values, etc.) if logger available
 * 
 * @package Piwik
 * @subpackage Piwik_API
 */
class Piwik_API_Proxy
{
	// array of already registered plugins names
	protected $alreadyRegistered = array();
	
	private $metadataArray = array();
	public $hideIgnoredFunctions = true;
	
	// when a parameter doesn't have a default value we use this
	private $noDefaultValue;

	static private $instance = null;
	protected function __construct()
	{
		$this->noDefaultValue = new Piwik_API_Proxy_NoDefaultValue();
	}
	
	/**
	 * Singleton, returns instance
	 *
	 * @return Piwik_API_Proxy
	 */
	static public function getInstance()
	{
		if (self::$instance == null)
		{
			self::$instance = new self;
		}
		return self::$instance;
	}
	
	/**
	 * Returns array containing reflection meta data for all the loaded classes
	 * eg. number of parameters, method names, etc.
	 * 
	 * @return array
	 */
	public function getMetadata()
	{
		ksort($this->metadataArray);
		return $this->metadataArray;
	}
	
	/**
	 * Registers the API information of a given module.
	 * 
	 * The module to be registered must be
	 * - a singleton (providing a getInstance() method)
	 * - the API file must be located in plugins/ModuleName/API.php
	 *   for example plugins/Referers/API.php
	 * 
	 * The method will introspect the methods, their parameters, etc. 
	 * 
	 * @param string ModuleName eg. "Piwik_UserSettings_API"
	 */
	public function registerClass( $className )
	{
		if(isset($this->alreadyRegistered[$className]))
		{
			return;
		}
		$this->includeApiFile( $className );
		$this->checkClassIsSingleton($className);
			
		$rClass = new ReflectionClass($className);
		foreach($rClass->getMethods() as $method)
		{
			$this->loadMethodMetadata($className, $method);
		}
		
		$this->setDocumentation($rClass, $className);
		$this->alreadyRegistered[$className] = true;
	}
	
	/**
	 * Will be displayed in the API page
	 * 
	 */
	private function setDocumentation($rClass, $className)
	{
		// Doc comment
		$doc = $rClass->getDocComment();
		$doc = str_replace(" * ".PHP_EOL, "<br>", $doc);
		
		// boldify the first line only if there is more than one line, otherwise too much bold 
		if(substr_count($doc, '<br>') > 1)
		{
			$firstLineBreak = strpos($doc, "<br>");
			$doc = "<div class='apiFirstLine'>".substr($doc, 0, $firstLineBreak)."</div>".substr($doc,$firstLineBreak+strlen("<br>"));
		}
		$doc = preg_replace("/(@package)[a-z _A-Z]*/", "", $doc);
		$doc = str_replace(array("\t","\n", "/**", "*/", " * "," *","  ", "\t*", "  *  @package"), " ", $doc);
		$this->metadataArray[$className]['__documentation'] = $doc;
	}
	
	/**
	 * Returns number of classes already loaded 
	 * @return int
	 */
	public function getCountRegisteredClasses()
	{
		return count($this->alreadyRegistered);
	}

	/**
	 * Will execute $className->$methodName($parametersValues)
	 * If any error is detected (wrong number of parameters, method not found, class not found, etc.)
	 * it will throw an exception
	 * 
	 * It also logs the API calls, with the parameters values, the returned value, the performance, etc.
	 * You can enable logging in config/global.ini.php (log_api_call)
	 * 
	 * @param string The class name (eg. Piwik_Referers_API)
	 * @param string The method name
	 * @param array The parameters pairs (name=>value)
	 * 
	 * @throws Piwik_Access_NoAccessException 
	 */
	public function call($className, $methodName, $parametersRequest )
	{
		$returnedValue = null;
		
		// Temporarily sets the Request array to this API call context
		$saveGET = $_GET;
		foreach($parametersRequest as $param => $value) {
			$_GET[$param] = $value;
		}
		
		try {
			$this->registerClass($className);

			// instanciate the object
			$object = call_user_func(array($className, "getInstance"));
			
			// check method exists
			$this->checkMethodExists($className, $methodName);
			
			// get the list of parameters required by the method
			$parameterNamesDefaultValues = $this->getParametersList($className, $methodName);

			// load parameters in the right order, etc.
			$finalParameters = $this->getRequestParametersArray( $parameterNamesDefaultValues, $parametersRequest );

			// start the timer
			$timer = new Piwik_Timer();
			
			// call the method
			$returnedValue = call_user_func_array(array($object, $methodName), $finalParameters);
			
			// allow plugins to manipulate the value
			if (substr($className, 0, 6) == 'Piwik_' && substr($className, -4) == '_API')
			{
				$pluginName = substr($className, 6, -4);
				Piwik_PostEvent('API.Proxy.processReturnValue', $returnedValue, array(
					'className' => $className,
					'module' => $pluginName,
					'action' => $methodName,
					'parameters' => &$parametersRequest
				));
			}
			
			// Restore the request 
			$_GET = $saveGET;
			
			// log the API Call
			try {
				Zend_Registry::get('logger_api_call')->logEvent(
								$className,
								$methodName,
								$parameterNamesDefaultValues,
								$finalParameters,
								$timer->getTimeMs(),
								$returnedValue
							);
			} catch (Exception $e) {
				// logger can fail (eg. Tracker request)
			}
		} catch (Exception $e) {
			$_GET = $saveGET;
			throw $e;
		}

		return $returnedValue;
	}
	
	/**
	 * Returns the parameters names and default values for the method $name 
	 * of the class $class
	 * 
	 * @param string The class name
	 * @param string The method name
	 * @return array Format array(
	 * 					'testParameter'		=> null, // no default value
	 * 					'life'				=> 42, // default value = 42
	 * 					'date'				=> 'yesterday',
	 * 				);
	 */
	public function getParametersList($class, $name)
	{
		return $this->metadataArray[$class][$name]['parameters'];
	}
	
	/**
	 * Returns the 'moduleName' part of 'Piwik_moduleName_API' classname 
	 * @param string "Piwik_Referers_API"
	 * @return string "Referers"
	 */ 
	public function getModuleNameFromClassName( $className )
	{
		return str_replace(array('Piwik_', '_API'), '', $className);
	}
	
	/**
	 * Returns an array containing the values of the parameters to pass to the method to call
	 *
	 * @param array array of (parameter name, default value)
	 * @return array values to pass to the function call
	 * @throws exception If there is a parameter missing from the required function parameters
	 */
	private function getRequestParametersArray( $requiredParameters, $parametersRequest )
	{
		$finalParameters = array();
		foreach($requiredParameters as $name => $defaultValue)
		{
			try{
				if($defaultValue instanceof Piwik_API_Proxy_NoDefaultValue)
				{
					$requestValue = Piwik_Common::getRequestVar($name, null, null, $parametersRequest);
				}
				else
				{
					try{
						$requestValue = Piwik_Common::getRequestVar($name, $defaultValue, null, $parametersRequest);
					} catch(Exception $e) {
						// Special case: empty parameter in the URL, should return the empty string
						if(isset($parametersRequest[$name]) 
							&& $parametersRequest[$name] === '')
						{
							$requestValue = '';
						}
						else
						{
							$requestValue = $defaultValue;
						}
					}
				}
			} catch(Exception $e) {
				throw new Exception(Piwik_TranslateException('General_PleaseSpecifyValue', array($name)));
			}
			$finalParameters[] = $requestValue;
		}
		return $finalParameters;
	}
	
	/**
	 * Includes the class Piwik_UserSettings_API by looking up plugins/UserSettings/API.php
	 *
	 * @param string api class name eg. "Piwik_UserSettings_API"
	 */
	private function includeApiFile($fileName)
	{
		$module = self::getModuleNameFromClassName($fileName);
		$path = PIWIK_INCLUDE_PATH . '/plugins/' . $module . '/API.php';

		if(is_readable($path))
		{
			require_once $path; // prefixed by PIWIK_INCLUDE_PATH
		}
		else
		{
			throw new Exception("API module $module not found.");
		}
	}

	private function loadMethodMetadata($class, $method)
	{
		if($method->isPublic() 
			&& !$method->isConstructor()
			&& $method->getName() != 'getInstance'
			&& false === strstr($method->getDocComment(), '@deprecated')
			&& (!$this->hideIgnoredFunctions || false === strstr($method->getDocComment(), '@ignore'))
			 )
		{
			$name = $method->getName();
			$parameters = $method->getParameters();
			
			$aParameters = array();
			foreach($parameters as $parameter)
			{
				$nameVariable = $parameter->getName();
				
				$defaultValue = $this->noDefaultValue;
				if($parameter->isDefaultValueAvailable())
				{
					$defaultValue = $parameter->getDefaultValue();
				}
				
				$aParameters[$nameVariable] = $defaultValue;
			}
			$this->metadataArray[$class][$name]['parameters'] = $aParameters;
			$this->metadataArray[$class][$name]['numberOfRequiredParameters'] = $method->getNumberOfRequiredParameters();
		}
	}

	/**
	 * Checks that the method exists in the class 
	 * 
	 * @param string The class name
	 * @param string The method name
	 * @throws exception If the method is not found
	 */	
	private function checkMethodExists($className, $methodName)
	{
		if(!$this->isMethodAvailable($className, $methodName))
		{
			throw new Exception(Piwik_TranslateException('General_ExceptionMethodNotFound', array($methodName,$className)));
		}
	}
	
	/**
	 * Returns the number of required parameters (parameters without default values).
	 * 
	 * @param string The class name
	 * @param string The method name
	 * @return int The number of required parameters
	 */
	private function getNumberOfRequiredParameters($class, $name)
	{
		return $this->metadataArray[$class][$name]['numberOfRequiredParameters'];
	}
	
	/**
	 * Returns true if the method is found in the API of the given class name. 
	 * 
	 * @param string The class name
	 * @param string The method name
	 * @return bool 
	 */
	private function isMethodAvailable( $className, $methodName)
	{
		return isset($this->metadataArray[$className][$methodName]);
	}
		
	/**
	 * Checks that the class is a Singleton (presence of the getInstance() method)
	 * 
	 * @param string The class name
	 * @throws exception If the class is not a Singleton
	 */
	private function checkClassIsSingleton($className)
	{
		if(!method_exists($className, "getInstance"))
		{
			throw new Exception("Objects that provide an API must be Singleton and have a 'static public function getInstance()' method.");
		}
	}
}
