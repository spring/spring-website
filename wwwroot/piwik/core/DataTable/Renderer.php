<?php
/**
 * Piwik - Open source web analytics
 * 
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id: Renderer.php 3565 2011-01-03 05:49:45Z matt $
 * 
 * @category Piwik
 * @package Piwik
 */

/**
 * A DataTable Renderer can produce an output given a DataTable object.
 * All new Renderers must be copied in DataTable/Renderer and added to the factory() method.
 * To use a renderer, simply do:
 *  $render = new Piwik_DataTable_Renderer_Xml();
 *  $render->setTable($dataTable);
 *  echo $render;
 * 
 * @package Piwik
 * @subpackage Piwik_DataTable
 */
abstract class Piwik_DataTable_Renderer
{
	protected $table;
	protected $exception;
	protected $renderSubTables = false;
	protected $hideIdSubDatatable = false;
	
	public function __construct()
	{
	}
	
	public function setRenderSubTables($enableRenderSubTable)
	{
		$this->renderSubTables = (bool)$enableRenderSubTable;
	}

	public function setHideIdSubDatableFromResponse($bool)
	{
		$this->hideIdSubDatatable = (bool)$bool;
	}
	
	protected function isRenderSubtables()
	{
		return $this->renderSubTables;
	}

	/**
	 * Output HTTP Content-Type header
	 */
	protected static function renderHeader()
	{
		@header('Content-Type: text/html; charset=utf-8');
	}

	/**
	 * Computes the dataTable output and returns the string/binary
	 * 
	 * @return string
	 */
	abstract public function render();
	
	/**
	 * Computes the exception output and returns the string/binary
	 * 
	 * @return string
	 */
	abstract public function renderException();	
	
	/**
	 * @see render()
	 * @return string
	 */
	public function __toString()
	{
		return $this->render();
	}
	
	/**
	 * Set the DataTable to be rendered
	 * @param Piwik_DataTable|Piwik_DataTable_Simple|Piwik_DataTable_Array $table to be rendered
	 */
	public function setTable($table)
	{
		if(!($table instanceof Piwik_DataTable)
			&& !($table instanceof Piwik_DataTable_Array))
		{
			throw new Exception("The renderer accepts only a Piwik_DataTable or an array of DataTable (Piwik_DataTable_Array) object.");
		}
		$this->table = $table;
	}
	
	/**
	 * Set the Exception to be rendered
	 * @param Exception $exception to be rendered
	 */
	public function setException($exception)
	{
		if(!($exception instanceof Exception))
		{
			throw new Exception("The exception renderer accepts only an Exception object.");
		}
		$this->exception = $exception;
	}
	

	static protected $availableRenderers = array(   'xml', 
        											'json', 
        											'csv', 
        											'tsv', 
        											'html', 
        											'php' 
	);
	
	static public function getRenderers()
	{
		return self::$availableRenderers;
	}
	
	/**
	 * Returns the DataTable associated to the output format $name
	 * 
	 * @throws exception If the renderer is unknown
	 * @return Piwik_DataTable_Renderer
	 */
	static public function factory( $name )
	{
		$name = ucfirst(strtolower($name));
		$className = 'Piwik_DataTable_Renderer_' . $name;
		
		try {
			Piwik_Loader::loadClass($className);
			return new $className;			
		} catch(Exception $e) {
			$availableRenderers = implode(', ', self::getRenderers());
			self::renderHeader();
			throw new Exception(Piwik_TranslateException('General_ExceptionInvalidRendererFormat', array($name, $availableRenderers)));
		}		
	}
	
	/**
	 * Returns $rawData after all applicable characters have been converted to HTML entities.
	 * 
	 * @param String $rawData to be converted
	 * @return String
	 */
	static protected function renderHtmlEntities( $rawData )
	{
		return self::formatValueXml($rawData);
	}

	public static function formatValueXml($value)
	{
		if(is_string($value)
			&& !is_numeric($value)) 
		{
			$value = html_entity_decode($value, ENT_COMPAT, 'UTF-8');
			$value = htmlspecialchars($value, ENT_COMPAT, 'UTF-8');
			$htmlentities = array( "&nbsp;","&iexcl;","&cent;","&pound;","&curren;","&yen;","&brvbar;","&sect;","&uml;","&copy;","&ordf;","&laquo;","&not;","&shy;","&reg;","&macr;","&deg;","&plusmn;","&sup2;","&sup3;","&acute;","&micro;","&para;","&middot;","&cedil;","&sup1;","&ordm;","&raquo;","&frac14;","&frac12;","&frac34;","&iquest;","&Agrave;","&Aacute;","&Acirc;","&Atilde;","&Auml;","&Aring;","&AElig;","&Ccedil;","&Egrave;","&Eacute;","&Ecirc;","&Euml;","&Igrave;","&Iacute;","&Icirc;","&Iuml;","&ETH;","&Ntilde;","&Ograve;","&Oacute;","&Ocirc;","&Otilde;","&Ouml;","&times;","&Oslash;","&Ugrave;","&Uacute;","&Ucirc;","&Uuml;","&Yacute;","&THORN;","&szlig;","&agrave;","&aacute;","&acirc;","&atilde;","&auml;","&aring;","&aelig;","&ccedil;","&egrave;","&eacute;","&ecirc;","&euml;","&igrave;","&iacute;","&icirc;","&iuml;","&eth;","&ntilde;","&ograve;","&oacute;","&ocirc;","&otilde;","&ouml;","&divide;","&oslash;","&ugrave;","&uacute;","&ucirc;","&uuml;","&yacute;","&thorn;","&yuml;","&euro;");
            $xmlentities = array(  "&#162;","&#163;","&#164;","&#165;","&#166;","&#167;","&#168;","&#169;","&#170;","&#171;","&#172;","&#173;","&#174;","&#175;","&#176;","&#177;","&#178;","&#179;","&#180;","&#181;","&#182;","&#183;","&#184;","&#185;","&#186;","&#187;","&#188;","&#189;","&#190;","&#191;","&#192;","&#193;","&#194;","&#195;","&#196;","&#197;","&#198;","&#199;","&#200;","&#201;","&#202;","&#203;","&#204;","&#205;","&#206;","&#207;","&#208;","&#209;","&#210;","&#211;","&#212;","&#213;","&#214;","&#215;","&#216;","&#217;","&#218;","&#219;","&#220;","&#221;","&#222;","&#223;","&#224;","&#225;","&#226;","&#227;","&#228;","&#229;","&#230;","&#231;","&#232;","&#233;","&#234;","&#235;","&#236;","&#237;","&#238;","&#239;","&#240;","&#241;","&#242;","&#243;","&#244;","&#245;","&#246;","&#247;","&#248;","&#249;","&#250;","&#251;","&#252;","&#253;","&#254;","&#255;","&#8364;"  );
            $value = str_replace($htmlentities,$xmlentities,$value); 
		}
		elseif($value===false)
		{
			$value = 0;
		}
		return $value;
	}
}
