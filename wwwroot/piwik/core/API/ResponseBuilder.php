<?php
/**
 * Piwik - Open source web analytics
 * 
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id: ResponseBuilder.php 4900 2011-06-09 22:28:36Z JulienM $
 * 
 * @category Piwik
 * @package Piwik
 */

/**
 * @package Piwik
 * @subpackage Piwik_API
 */
class Piwik_API_ResponseBuilder
{
	private $request = null;
	private $outputFormat = null;
	
	public function __construct($outputFormat, $request = array())
	{
		$this->request = $request;
		$this->outputFormat = $outputFormat;
	}
	
	/**
	 * This method processes the data resulting from the API call.
	 * 
	 * - If the data resulted from the API call is a Piwik_DataTable then 
	 * 		- we apply the standard filters if the parameters have been found
	 * 		  in the URL. For example to offset,limit the Table you can add the following parameters to any API
	 *  	  call that returns a DataTable: filter_limit=10&filter_offset=20
	 * 		- we apply the filters that have been previously queued on the DataTable
	 *        @see Piwik_DataTable::queueFilter()
	 * 		- we apply the renderer that generate the DataTable in a given format (XML, PHP, HTML, JSON, etc.) 
	 * 		  the format can be changed using the 'format' parameter in the request.
	 *        Example: format=xml
	 * 
	 * - If there is nothing returned (void) we display a standard success message
	 * 
	 * - If there is a PHP array returned, we try to convert it to a dataTable 
	 *   It is then possible to convert this datatable to any requested format (xml/etc)
	 * 
	 * - If a bool is returned we convert to a string (true is displayed as 'true' false as 'false')
	 * 
	 * - If an integer / float is returned, we simply return it
	 * 
	 * @throws Exception If an object/resource is returned, if any of conversion fails, etc. 
	 * 
	 * @param mixed The initial returned value, before post process. If set to null, success response is returned. 
	 * @return mixed Usually a string, but can still be a PHP data structure if the format requested is 'original'
	 */
	public function getResponse($value = null)
	{ 
		// when null or void is returned from the api call, we handle it as a successful operation 
		if(!isset($value))
		{
			return $this->handleSuccess();
		}
		
		// If the returned value is an object DataTable we
		// apply the set of generic filters if asked in the URL
		// and we render the DataTable according to the format specified in the URL
		if($value instanceof Piwik_DataTable
			|| $value instanceof Piwik_DataTable_Array)
		{
			return $this->handleDataTable($value);
		}
		
		// Case an array is returned from the API call, we convert it to the requested format
		// - if calling from inside the application (format = original)
		//    => the data stays unchanged (ie. a standard php array or whatever data structure)
		// - if any other format is requested, we have to convert this data structure (which we assume 
		//   to be an array) to a DataTable in order to apply the requested DataTable_Renderer (for example XML)
		if(is_array($value))
		{
			return $this->handleArray($value);
		}
		
		// original data structure requested, we return without process
		if( $this->outputFormat == 'original' )
		{
			return $value;
		}
	
		if( is_object($value)
				|| is_resource($value))
		{
			return $this->getResponseException(new Exception('The API cannot handle this data structure.'));
		}
		
		// bool // integer // float // serialized object 
		return $this->handleScalar($value);
	}
	
	/**
	 * Returns an error $message in the requested $format 
	 *
	 * @param string $message
	 * @param string $format xml/json/php/csv
	 * @return string
	 */
	public function getResponseException(Exception $e)
	{
		$format = strtolower($this->outputFormat);
		
		if( $format == 'original' )
		{
			throw $e;
		}
		
		try
		{
			$renderer = Piwik_DataTable_Renderer::factory($format);
		
		} catch (Exception $e) {
			
			return "Error: " . $e->getMessage();
		}
		
		$renderer->setException($e);
		
		if($format == 'php')
		{
			$renderer->setSerialize($this->caseRendererPHPSerialize());
		}		
		
		return $renderer->renderException();
	}
	
	/**
	 * Returns true if the user requested to serialize the output data (&serialize=1 in the request)
	 *
	 * @param mixed $defaultSerializeValue Default value in case the user hasn't specified a value
	 * @return bool
	 */	
	protected function caseRendererPHPSerialize($defaultSerializeValue = 1)
	{
		$serialize = Piwik_Common::getRequestVar('serialize', $defaultSerializeValue, 'int', $this->request);
		if($serialize)
		{
			return true;
		}
		return false;	
	}
	
	/**
	 * Apply the specified renderer to the DataTable
	 * 
	 * @param Piwik_DataTable
	 * @return string
	 */
	protected function getRenderedDataTable($dataTable)
	{
		$format = strtolower($this->outputFormat);
		
		// if asked for original dataStructure
		if($format == 'original')
		{
			// if the original dataStructure is a simpleDataTable 
			// and has only one column, we return the value
			if($dataTable instanceof Piwik_DataTable_Simple)
			{
				$columns = $dataTable->getFirstRow()->getColumns();
				if(count($columns) == 1)
				{
					$values = array_values($columns);
					return $values[0];
				}
			}
			
			// by default "original" data is not serialized
			if($this->caseRendererPHPSerialize( $defaultSerialize = 0))
			{
				$dataTable = serialize($dataTable);
			}
			return $dataTable;
		}
		
		$renderer = Piwik_DataTable_Renderer::factory($format);
		$renderer->setTable($dataTable);
		$renderer->setRenderSubTables(Piwik_Common::getRequestVar('expanded', false, 'int', $this->request));
		$renderer->setHideIdSubDatableFromResponse(Piwik_Common::getRequestVar('hideIdSubDatable', false, 'int', $this->request));
		if($format == 'php')
		{
			$renderer->setSerialize( $this->caseRendererPHPSerialize());
			$renderer->setPrettyDisplay(Piwik_Common::getRequestVar('prettyDisplay', false, 'int', $this->request));
		}
		else if($format == 'html')
		{
			$renderer->setTableId($this->request['method']);
		}
		else if($format == 'csv')
		{
			$renderer->setConvertToUnicode( Piwik_Common::getRequestVar('convertToUnicode', true, 'int') );
		}
		
		return $renderer->render();
	}
	
	/**
	 * Returns a success $message in the requested $format 
	 *
	 * @param string $format xml/json/php/csv
	 * @param string $message
	 * @return string
	 */
	protected function handleSuccess( $message = 'ok' )
	{
		switch($this->outputFormat)
		{
			case 'xml':
				@header("Content-Type: text/xml;charset=utf-8");
				$return = 
					"<?xml version=\"1.0\" encoding=\"utf-8\" ?>\n" .
					"<result>\n".
					"\t<success message=\"".$message."\" />\n".
					"</result>";
			break;
			case 'json':
				@header( "Content-Type: application/json" );
				$return = '{"result":"success", "message":"'.$message.'"}';
			break;
			case 'php':
				$return = array('result' => 'success', 'message' => $message);
				if($this->caseRendererPHPSerialize())
				{
					$return = serialize($return);
				}
			break;
			
			case 'csv':
				@header("Content-Type: application/vnd.ms-excel");
				@header("Content-Disposition: attachment; filename=piwik-report-export.csv");	
				$return = "message\n".$message;
			break;
			
			default:
				$return = 'Success:'.$message;
			break;
		}
		return $return;
	}

	protected function handleScalar($scalar)
	{
		$dataTable = new Piwik_DataTable_Simple();
		$dataTable->addRowsFromArray( array($scalar) );
		return $this->getRenderedDataTable($dataTable);
	}

	protected function handleDataTable($datatable)
	{
		// if the flag disable_generic_filters is defined we skip the generic filters
		if('false' == Piwik_Common::getRequestVar('disable_generic_filters', 'false', 'string', $this->request))
		{
			$genericFilter = new Piwik_API_DataTableGenericFilter($this->request);
			$genericFilter->filter($datatable);
		}
		
		// we automatically safe decode all datatable labels (against xss) 
		$datatable->queueFilter('SafeDecodeLabel');
		
		// if the flag disable_queued_filters is defined we skip the filters that were queued
		if(Piwik_Common::getRequestVar('disable_queued_filters', 'false', 'string', $this->request) == 'false')
		{
			$datatable->applyQueuedFilters();
		}
		return $this->getRenderedDataTable($datatable);
	}
	
	protected function handleArray($array)
	{
		if($this->outputFormat == 'original')
		{
			// we handle the serialization. Because some php array have a very special structure that 
			// couldn't be converted with the automatic DataTable->addRowsFromSimpleArray
			// the user may want to request the original PHP data structure serialized by the API
			// in case he has to setup serialize=1 in the URL
			if($this->caseRendererPHPSerialize( $defaultSerialize = 0))
			{
				return serialize($array);
			}
			return $array;
		}
		$multiDimensional = $this->handleMultiDimensionalArray($array);
		if($multiDimensional !== false)
		{
			return $multiDimensional;
		}
		
		$dataTable = new Piwik_DataTable();
		$dataTable->addRowsFromSimpleArray($array);
		return $this->getRenderedDataTable($dataTable);
	}
	
	/**
	 * Is this a multi dimensional array? 
	 * Multi dim arrays are not supported by the Datatable renderer.
	 * We manually render these.
	 * 
	 * array(
	 * 		array(
	 * 			1,
	 * 			2 => array( 1,
	 * 						2
	 * 			)
	 *		), 
	 *		array( 2,
	 *			   3
	 *		)
	 *	);
	 * 
	 * @return String or false if it isn't a multidim array
	 */ 
	protected function handleMultiDimensionalArray($array)
	{
		$first = reset($array);
		foreach($array as $first)
		{
			if(is_array($first))
			{
				foreach($first as $key => $value)
				{
					// Yes, this is a multi dim array
					if(is_array($value))
					{
						switch($this->outputFormat)
						{
							case 'json':
								@header( "Content-Type: application/json" );
								return self::convertMultiDimensionalArrayToJson($array);
							break;
							
							case 'php':
								if($this->caseRendererPHPSerialize( $defaultSerialize = 0))
								{
									return serialize($array);
								}
								return $array;
								
							case 'xml':
								@header("Content-Type: text/xml;charset=utf-8");
								$xml = 
									"<?xml version=\"1.0\" encoding=\"utf-8\" ?>\n" .
									"<result>\n".
											self::convertMultiDimensionalArrayToXml($array).
									"\n</result>";
								return $xml;
							default:
							break;
						}
					}
				}
			}
		}
		return false;
	}

	/**
	 * Render a multidimensional array to XML
	 *
	 * @static
	 * @param $array can contain scalar, arrays, Piwik_DataTable and Piwik_DataTable_Array
	 * @param int $level
	 * @return string
	 */
	public static function convertMultiDimensionalArrayToXml($array, $level = 0)
	{ 
		$xml=""; 
		foreach ($array as $key=>$value)
		{
			if(is_numeric($key))
			{
				$key = "row";
			}

			$key = str_replace(' ', '_', $key);
			$marginLeft = str_repeat("\t", $level + 1);

			switch(true)
			{
				// Case dimension is a PHP array
				case (is_array($value)):

					if(empty($value))
					{
						$xml .= $marginLeft . "<$key/>\n";
					}
					else
					{
						$xml.=	$marginLeft .
							"<$key>\n".
								self::convertMultiDimensionalArrayToXml($value, $level + 1).
								"\n". $marginLeft .
							"</$key>\n";
					}
					break;

				// Case dimension is a Piwik_DataTable_Array or a Piwik_DataTable
				case ($value instanceof Piwik_DataTable_Array || $value instanceof Piwik_DataTable):

					if($value->getRowsCount() == 0)
					{
						$xml .= $marginLeft . "<$key/>\n";
					}
					else
					{
						$XMLRenderer = new Piwik_DataTable_Renderer_Xml();
						$XMLRenderer->setTable($value);
						$renderedReport = $XMLRenderer->render();

						$renderedReport = preg_replace("/<\?xml.*\?>\n/", "", $renderedReport);
						$markupToRemove = $value instanceof Piwik_DataTable_Array ? "results" : "result";
						$renderedReport = preg_replace("/\n?<\/?". $markupToRemove .">\n?/", "", $renderedReport);

						// Add one level of margin to each line
						$renderedReport = $marginLeft . preg_replace("/\n/", "\n" . $marginLeft, $renderedReport);

						$xml.=	$marginLeft . "<$key>\n";
						$xml.=	$renderedReport;
						$xml.=	"\n" . $marginLeft . "</$key>\n";
					}

					break;

				// Case scalar
				default:

					$xml.= $marginLeft . "<$key>".Piwik_DataTable_Renderer::formatValueXml($value)."</$key>\n";
					break;
			}
		} 
		return $xml; 
	}

	/**
	 * Render a multidimensional array to Json
	 * Handle Piwik_DataTable|Piwik_DataTable_Array elements in the first dimension only, following case does not work:
	 * array(
	 * 		array(
	 * 			Piwik_DataTable,
	 * 			2 => array(
	 * 				1,
	 * 				2
	 * 			),
	 *		),
	 *	);
	 *
	 * @static
	 * @param $array can contain scalar, arrays, Piwik_DataTable and Piwik_DataTable_Array
	 * @param int $level
	 * @return string
	 */
	public static function convertMultiDimensionalArrayToJson($array)
	{
		// Naive but works for our current use cases
		$arrayKeys = array_keys($array);
		$isAssociative = !is_numeric($arrayKeys[0]);

		if($isAssociative)
		{
			$json = "{";
		}
		else
		{
			$json = "[";
		}

		foreach ($array as $key=>$value)
		{
			if($isAssociative)
			{
				$json .= "\"".$key."\":";
			}

			switch(true)
			{
				// Case dimension is a PHP array
				case (is_array($value)):

					$json .= json_encode($value);
					break;

				// Case dimension is a Piwik_DataTable_Array or a Piwik_DataTable
				case ($value instanceof Piwik_DataTable_Array || $value instanceof Piwik_DataTable):

					$XMLRenderer = new Piwik_DataTable_Renderer_Json();
					$XMLRenderer->setTable($value);
					$renderedReport = $XMLRenderer->render();
					$json .= $renderedReport;
					break;

				// Case scalar
				default:

					$json .= json_encode($value);
					break;
			}

			$json .= ",";
		}

		// Remove trailing ","
		$json = substr ($json, 0, strlen($json) - 1);

		if($isAssociative)
		{
			$json .= "}";
		}
		else
		{
			$json .= "]";
		}
		return $json;
	}
}
