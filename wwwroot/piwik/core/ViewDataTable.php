<?php
/**
 * Piwik - Open source web analytics
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id: ViewDataTable.php 5296 2011-10-14 02:52:44Z matt $
 *
 * @category Piwik
 * @package Piwik
 */

/**
 * This class is used to load (from the API) and customize the output of a given DataTable.
 * The main() method will create an object Piwik_iView
 * You can customize the dataTable using the disable* methods.
 *
 * Example:
 * In the Controller of the plugin VisitorInterest
 * <pre>
 * 	function getNumberOfVisitsPerVisitDuration( $fetch = false)
 *  {
 * 		$view = Piwik_ViewDataTable::factory( 'cloud' );
 * 		$view->init( $this->pluginName,  __FUNCTION__, 'VisitorInterest.getNumberOfVisitsPerVisitDuration' );
 * 		$view->setColumnsToDisplay( array('label','nb_visits') );
 * 		$view->disableSort();
 * 		$view->disableExcludeLowPopulation();
 * 		$view->disableOffsetInformation();
 *
 *		return $this->renderView($view, $fetch);
 * 	}
 * </pre>
 *
 * @see factory() for all the available output (cloud tags, html table, pie chart, vertical bar chart)
 * @package Piwik
 * @subpackage Piwik_ViewDataTable
 */
abstract class Piwik_ViewDataTable
{
	/**
	 * Template file that will be loaded for this view.
	 * Usually set in the Piwik_ViewDataTable_*
	 *
	 * @var string eg. 'CoreHome/templates/cloud.tpl'
	 */
	protected $dataTableTemplate = null;
	
	/**
	 * Flag used to make sure the main() is only executed once
	 *
	 * @var bool
	 */
	protected $mainAlreadyExecuted = false;
	
	/**
	 * Contains the values set for the parameters
	 *
	 * @see getJavascriptVariablesToSet()
	 * @var array
	 */
	protected $variablesDefault = array();
	
	/**
	 * Array of properties that are available in the view (from smarty)
	 * Used to store UI properties, eg. "show_footer", "show_search", etc.
	 *
	 * @var array
	 */
	protected $viewProperties = array();
	
	/**
	 * If the current dataTable refers to a subDataTable (eg. keywordsBySearchEngineId for id=X) this variable is set to the Id
	 *
	 * @var bool|int
	 */
	protected $idSubtable = false;
	
	/**
	 * DataTable loaded from the API for this ViewDataTable.
	 *
	 * @var Piwik_DataTable
	 */
	protected $dataTable = null;
		
	
	/**
	 * List of filters to apply after the data has been loaded from the API
	 *
	 * @var array
	 */
	protected $queuedFilters = array();
	
	/**
	 * List of filter to apply just before the 'Generic' filters
	 * These filters should delete rows from the table
	 * @var array
	 */
	protected $queuedFiltersPriority = array();
	
	/**
	 * @see init()
	 * @var string
	 */
	protected $currentControllerAction;
	
	/**
	 * @see init()
	 * @var string
	 */
	protected $currentControllerName;
	
	/**
	 * @see init()
	 * @var string
	 */
	protected $controllerActionCalledWhenRequestSubTable = null;
	
	/**
	 * @see init()
	 * @var string
	 */
	protected $apiMethodToRequestDataTable;
	
	/**
	 * This view should be an implementation of the Interface Piwik_iView
	 * The $view object should be created in the main() method.
	 *
	 * @var Piwik_iView
	 */
	protected $view = null;
	
	/**
	 * Array of columns names translations
	 *
	 * @var array
	 */
	protected $columnsTranslations = array();
	
	/**
	 * Documentation for the metrics used in the current report.
	 * Received from the Plugin API, used for inline help.
	 *
	 * @var array
	 */
	protected $metricsDocumentation = false;
	
	/**
	 * Documentation for the report.
	 * Received from the Plugin API, used for inline help.
	 *
	 * @var array
	 */
	protected $documentation = false;
	
	/**
	 * Array of columns set to display
	 *
	 * @var array
	 */
	protected $columnsToDisplay = array();

	/**
	 * Variable that is used as the DIV ID in the rendered HTML
	 *
	 * @var string
	 */
	protected $uniqIdTable = null;
	
	/**
	 * Method to be implemented by the ViewDataTable_*.
	 * This method should create and initialize a $this->view object @see Piwik_iView
	 *
	 * @return mixed either prints the result or returns the output string
	 */
	abstract public function main();
	
	/**
	 * Unique string ID that defines the format of the dataTable, eg. "pieChart", "table", etc.
	 *
	 * @return string
	 */
	abstract protected function getViewDataTableId();
	
	/**
	 * Returns a Piwik_ViewDataTable_* object.
	 * By default it will return a ViewDataTable_Html
	 * If there is a viewDataTable parameter in the URL, a ViewDataTable of this 'viewDataTable' type will be returned.
	 * If defaultType is specified and if there is no 'viewDataTable' in the URL, a ViewDataTable of this $defaultType will be returned.
	 * If force is set to true, a ViewDataTable of the $defaultType will be returned in all cases.
	 *
	 * @param string defaultType Any of these: table, cloud, graphPie, graphVerticalBar, graphEvolution, sparkline, generateDataChart*
	 * @param bool force If set to true, returns a ViewDataTable of the $defaultType
	 * @return Piwik_ViewDataTable
	 */
	static public function factory( $defaultType = null, $force = false)
	{
		if(is_null($defaultType))
		{
			$defaultType = 'table';
		}
		
		if($force === true)
		{
			$type = $defaultType;
		}
		else
		{
			$type = Piwik_Common::getRequestVar('viewDataTable', $defaultType, 'string');
		}
		switch($type)
		{
			case 'cloud':
				return new Piwik_ViewDataTable_Cloud();
			break;
			
			case 'graphPie':
				return new Piwik_ViewDataTable_GenerateGraphHTML_ChartPie();
			break;
			
			case 'graphVerticalBar':
				return new Piwik_ViewDataTable_GenerateGraphHTML_ChartVerticalBar();
			break;
			
			case 'graphEvolution':
				return new Piwik_ViewDataTable_GenerateGraphHTML_ChartEvolution();
			break;
			
			case 'sparkline':
				return new Piwik_ViewDataTable_Sparkline();
			break;
			
			case 'generateDataChartVerticalBar':
				return new Piwik_ViewDataTable_GenerateGraphData_ChartVerticalBar();
			break;
						
			case 'generateDataChartPie':
				return new Piwik_ViewDataTable_GenerateGraphData_ChartPie();
			break;
			
			case 'generateDataChartEvolution':
				return new Piwik_ViewDataTable_GenerateGraphData_ChartEvolution();
			break;
				
			case 'tableAllColumns':
				return new Piwik_ViewDataTable_HtmlTable_AllColumns();
			break;
			
			case 'tableGoals':
				return new Piwik_ViewDataTable_HtmlTable_Goals();
			break;
			
			case 'table':
			default:
				return new Piwik_ViewDataTable_HtmlTable();
			break;
		}
	}
	
	/**
	 * Inits the object given the $currentControllerName, $currentControllerAction of
	 * the calling controller action, eg. 'Referers' 'getLongListOfKeywords'.
	 * The initialization also requires the $apiMethodToRequestDataTable of the API method
	 * to call in order to get the DataTable, eg. 'Referers.getKeywords'.
	 * The optional $controllerActionCalledWhenRequestSubTable defines the method name of the API to call when there is a idSubtable.
	 * This value would be used by the javascript code building the GET request to the API.
	 *
	 * Example:
	 * 	For the keywords listing, a click on the row loads the subTable of the Search Engines for this row.
	 *  In this case $controllerActionCalledWhenRequestSubTable = 'getSearchEnginesFromKeywordId'.
	 *  The GET request will hit 'Referers.getSearchEnginesFromKeywordId'.
	 *
	 * @param string $currentControllerName eg. 'Referers'
	 * @param string $currentControllerAction eg. 'getKeywords'
	 * @param string $apiMethodToRequestDataTable eg. 'Referers.getKeywords'
	 * @param string $controllerActionCalledWhenRequestSubTable eg. 'getSearchEnginesFromKeywordId'
	 */
	public function init( $currentControllerName,
						$currentControllerAction,
						$apiMethodToRequestDataTable,
						$controllerActionCalledWhenRequestSubTable = null)
	{
		$this->currentControllerName = $currentControllerName;
		$this->currentControllerAction = $currentControllerAction;
		$this->apiMethodToRequestDataTable = $apiMethodToRequestDataTable;
		$this->controllerActionCalledWhenRequestSubTable = $controllerActionCalledWhenRequestSubTable;
		$this->idSubtable = Piwik_Common::getRequestVar('idSubtable', false, 'int');

		$this->viewProperties['show_goals'] = false;
		$this->viewProperties['show_ecommerce'] = false;
		$this->viewProperties['show_search'] = Piwik_Common::getRequestVar('show_search', true);
		$this->viewProperties['show_table'] = Piwik_Common::getRequestVar('show_table', true);
		$this->viewProperties['show_table_all_columns'] = Piwik_Common::getRequestVar('show_table_all_columns', true);
		$this->viewProperties['show_all_views_icons'] = Piwik_Common::getRequestVar('show_all_views_icons', true);
		$this->viewProperties['show_export_as_image_icon'] = Piwik_Common::getRequestVar('show_export_as_image_icon', false);
		$this->viewProperties['show_export_as_rss_feed'] = Piwik_Common::getRequestVar('show_export_as_rss_feed', true);
		$this->viewProperties['show_exclude_low_population'] = Piwik_Common::getRequestVar('show_exclude_low_population', true);
		$this->viewProperties['show_offset_information'] = Piwik_Common::getRequestVar('show_offset_information', true);
		$this->viewProperties['show_pagination_control'] = Piwik_Common::getRequestVar('show_pagination_control', true);
		$this->viewProperties['show_footer'] = Piwik_Common::getRequestVar('show_footer', true);
		$this->viewProperties['show_footer_icons'] = ($this->idSubtable == false);
		$this->viewProperties['apiMethodToRequestDataTable'] = $this->apiMethodToRequestDataTable;
		$this->viewProperties['uniqueId'] = $this->getUniqueIdViewDataTable();
		
		$standardColumnNameToTranslation = array_merge(
			Piwik_API_API::getInstance()->getDefaultMetrics(),
			Piwik_API_API::getInstance()->getDefaultProcessedMetrics()
		);
		$this->setColumnsTranslations($standardColumnNameToTranslation);
	}

	/**
	 * Forces the View to use a given template.
	 * Usually the template to use is set in the specific ViewDataTable_*
	 * eg. 'CoreHome/templates/cloud.tpl'
	 * But some users may want to force this template to some other value
	 *
	 * @param string $tpl eg .'MyPlugin/templates/templateToUse.tpl'
	 */
	public function setTemplate( $tpl )
	{
		$this->dataTableTemplate = $tpl;
	}
		
	/**
	 * Returns the iView.
	 * You can then call render() on this object.
	 *
	 * @return Piwik_iView
	 * @throws exception if the view object was not created
	 */
	public function getView()
	{
		if(is_null($this->view))
		{
			throw new Exception('The $this->view object has not been created.
					It should be created in the main() method of the Piwik_ViewDataTable_* subclass you are using.');
		}
		return $this->view;
	}

	public function getCurrentControllerAction()
	{
		return $this->currentControllerAction;
	}
	
	public function getCurrentControllerName()
	{
		return $this->currentControllerName;
	}
	
	public function getApiMethodToRequestDataTable()
	{
		return $this->apiMethodToRequestDataTable;
	}

	public function getControllerActionCalledWhenRequestSubTable()
	{
		return $this->controllerActionCalledWhenRequestSubTable;
	}
	
	/**
	 * Returns the DataTable loaded from the API
	 *
	 * @return Piwik_DataTable
	 * @throws exception if not yet defined
	 */
	public function getDataTable()
	{
		if(is_null($this->dataTable))
		{
			throw new Exception("The DataTable object has not yet been created");
		}
		return $this->dataTable;
	}
	
	/**
	 * Function called by the ViewDataTable objects in order to fetch data from the API.
	 * The function init() must have been called before, so that the object knows which API module and action to call.
	 * It builds the API request string and uses Piwik_API_Request to call the API.
	 * The requested Piwik_DataTable object is stored in $this->dataTable.
	 */
	protected function loadDataTableFromAPI()
	{
		// we build the request string (URL) to call the API
		$requestString = $this->getRequestString();
		
		// we make the request to the API
		$request = new Piwik_API_Request($requestString);
		
		// and get the DataTable structure
		$dataTable = $request->process();

		$this->dataTable = $dataTable;
	}
	
	/**
	 * Checks that the API returned a normal DataTable (as opposed to DataTable_Array)
	 * @throws Exception
	 * @return void
	 */
	protected function checkStandardDataTable()
	{
		if(!($this->dataTable instanceof Piwik_DataTable))
		{
			throw new Exception("Unexpected data type to render.");
		}
	}
	
	/**
	 * Hook called after the dataTable has been loaded from the API
	 * Can be used to add, delete or modify the data freshly loaded
	 */
	protected function postDataTableLoadedFromAPI()
	{
		if(empty($this->dataTable))
		{
			return false;
		}
		// First, filters that delete rows
		foreach($this->queuedFiltersPriority as $filter)
		{
			$filterName = $filter[0];
			$filterParameters = $filter[1];
			$this->dataTable->filter($filterName, $filterParameters);
		}
		
		if('false' == Piwik_Common::getRequestVar('disable_generic_filters', 'false', 'string'))
		{
			// Second, generic filters (Sort, Limit, Replace Column Names, etc.)
			$requestString = $this->getRequestString();
			$request = Piwik_API_Request::getRequestArrayFromString($requestString);
			
			if(!empty($this->variablesDefault['enable_sort'])
				&& $this->variablesDefault['enable_sort'] === 'false')
			{
				$request['filter_sort_column'] = $request['filter_sort_order'] = '';
			}
			$genericFilter = new Piwik_API_DataTableGenericFilter($request);
			$genericFilter->filter($this->dataTable);
		}
		
		// Finally, apply datatable filters that were queued (should be 'presentation' filters that do not affect the number of rows)
		foreach($this->queuedFilters as $filter)
		{
			$filterName = $filter[0];
			$filterParameters = $filter[1];
			$this->dataTable->filter($filterName, $filterParameters);
		}
	}
	
	/**
	 * @return string URL to call the API, eg. "method=Referers.getKeywords&period=day&date=yesterday"...
	 */
	protected function getRequestString()
	{
		// we prepare the string to give to the API Request
		// we setup the method and format variable
		// - we request the method to call to get this specific DataTable
		// - the format = original specifies that we want to get the original DataTable structure itself, not rendered
		$requestString  = 'method='.$this->apiMethodToRequestDataTable;
		$requestString .= '&format=original';
		$requestString .= '&disable_generic_filters=1';
		
		$toSetEventually = array(
			'filter_limit',
			'filter_sort_column',
			'filter_sort_order',
			'filter_excludelowpop',
			'filter_excludelowpop_value',
			'filter_column',
			'filter_pattern',
			'disable_queued_filters',
		);

		foreach($toSetEventually as $varToSet)
		{
			$value = $this->getDefaultOrCurrent($varToSet);
			if( false !== $value )
			{
				if( is_array($value) )
				{
					foreach($value as $v)
					{
						$requestString .= "&".$varToSet.'[]='.$v;
					}
				}
				else
				{
					$requestString .= '&'.$varToSet.'='.$value;
				}
			}
		}
		return $requestString;
	}
	
	/**
	 * For convenience, the client code can call methods that are defined in a specific children class
	 * without testing the children class type, which would trigger an error with a different children class.
	 *
	 * Example:
	 *  ViewDataTable/Html.php defines a setColumnsToDisplay(). The client code calls this methods even if
	 *  the ViewDataTable object is a ViewDataTable_Cloud instance (he doesn't know because of the factory()).
	 *  But ViewDataTable_Cloud doesn't define the setColumnsToDisplay() method.
	 *  Because we don't want to force users to test for the object type we simply catch these
	 *  calls when they are not defined in the child and do nothing.
	 *
	 * @param string $function
	 * @param array $args
	 */
	public function __call($function, $args)
	{
	}
	
	/**
	 * Returns a unique ID for this ViewDataTable.
	 * This unique ID is used in the Javascript code:
	 *  Any ajax loaded data is loaded within a DIV that has id=$unique_id
	 *  The jquery code then replaces the existing html div id=$unique_id in the code with this data.
	 *
	 * @see datatable.js
	 * @return string
	 */
	protected function loadUniqueIdViewDataTable()
	{
		// if we request a subDataTable the $this->currentControllerAction DIV ID is already there in the page
		// we make the DIV ID really unique by appending the ID of the subtable requested
		if( $this->idSubtable != 0 // parent DIV has a idSubtable = 0 but the html DIV must have the name of the module.action
			&&  $this->idSubtable !== false // case there is no idSubtable
			)
		{
			// see also datatable.js (the ID has to match with the html ID created to be replaced by the result of the ajax call)
			$uniqIdTable = 'subDataTable_' . $this->idSubtable;
		}
		else
		{
			// the $uniqIdTable variable is used as the DIV ID in the rendered HTML
			// we use the current Controller action name as it is supposed to be unique in the rendered page
			$uniqIdTable = $this->currentControllerName . $this->currentControllerAction;
		}
		return $uniqIdTable;
	}

	/**
	 *  Sets the $uniqIdTable variable that is used as the DIV ID in the rendered HTML
	 */
	public function setUniqueIdViewDataTable($uniqIdTable)
	{
		$this->viewProperties['uniqueId'] = $uniqIdTable;
		$this->uniqIdTable = $uniqIdTable;
	}

	/**
	 *  Returns current value of $uniqIdTable variable that is used as the DIV ID in the rendered HTML
	 */
	public function getUniqueIdViewDataTable()
	{
		if( $this->uniqIdTable == null )
		{
			$this->uniqIdTable = $this->loadUniqueIdViewDataTable();
		}
		return $this->uniqIdTable;
	}
	
	/**
	 * Returns array of properties, eg. "show_footer", "show_search", etc.
	 *
	 * @return array of boolean
	 */
	protected function getViewProperties()
	{
		return $this->viewProperties;
	}
	
	/**
	 * This functions reads the customization values for the DataTable and returns an array (name,value) to be printed in Javascript.
	 * This array defines things such as:
	 * - name of the module & action to call to request data for this table
	 * - optional filters information, eg. filter_limit and filter_offset
	 * - etc.
	 *
	 * The values are loaded:
	 * - from the generic filters that are applied by default @see Piwik_API_DataTableGenericFilter.php::getGenericFiltersInformation()
	 * - from the values already available in the GET array
	 * - from the values set using methods from this class (eg. setSearchPattern(), setLimit(), etc.)
	 *
	 * @return array eg. array('show_offset_information' => 0, 'show_...
	 */
	protected function getJavascriptVariablesToSet()
	{
		// build javascript variables to set
		$javascriptVariablesToSet = array();
		
		$genericFilters = Piwik_API_DataTableGenericFilter::getGenericFiltersInformation();
		foreach($genericFilters as $filter)
		{
			foreach($filter as $filterVariableName => $filterInfo)
			{
				// if there is a default value for this filter variable we set it
				// so that it is propagated to the javascript
				if(isset($filterInfo[1]))
				{
					$javascriptVariablesToSet[$filterVariableName] = $filterInfo[1];
					
					// we set the default specified column and Order to sort by
					// when this javascript variable is not set already
					// for example during an AJAX call this variable will be set in the URL
					// so this will not be executed (and the default sorted not be used as the sorted column might have changed in the meanwhile)
					if( false !== ($defaultValue = $this->getDefault($filterVariableName)))
					{
						$javascriptVariablesToSet[$filterVariableName] = $defaultValue;
					}
				}
			}
		}
		
		foreach($_GET as $name => $value)
		{
			try {
				$requestValue = Piwik_Common::getRequestVar($name);
			}
			catch(Exception $e) {
				$requestValue = '';
			}
			$javascriptVariablesToSet[$name] = $requestValue;
		}
		
		// at this point there are some filters values we  may have not set,
		// case of the filter without default values and parameters set directly in this class
		// for example setExcludeLowPopulation
		// we go through all the $this->variablesDefault array and set the variables not set yet
		foreach($this->variablesDefault as $name => $value)
		{
			if(!isset($javascriptVariablesToSet[$name] ))
			{
				$javascriptVariablesToSet[$name] = $value;
			}
		}

		if($this->dataTable instanceof Piwik_DataTable)
		{
			// we override the filter_sort_column with the column used for sorting,
			// which can be different from the one specified (eg. if the column doesn't exist)
			$javascriptVariablesToSet['filter_sort_column'] = $this->dataTable->getSortedByColumnName();
			// datatable can return "2" but we want to write "nb_visits" in the js
			if(isset(Piwik_Archive::$mappingFromIdToName[$javascriptVariablesToSet['filter_sort_column']]))
			{
				$javascriptVariablesToSet['filter_sort_column'] = Piwik_Archive::$mappingFromIdToName[$javascriptVariablesToSet['filter_sort_column']];
			}
		}
		
		$javascriptVariablesToSet['module'] = $this->currentControllerName;
		$javascriptVariablesToSet['action'] = $this->currentControllerAction;
		$javascriptVariablesToSet['viewDataTable'] = $this->getViewDataTableId();
		$javascriptVariablesToSet['controllerActionCalledWhenRequestSubTable'] = $this->controllerActionCalledWhenRequestSubTable;
		
		if($this->dataTable)
		{
			$javascriptVariablesToSet['totalRows'] = $this->dataTable->getRowsCountBeforeLimitFilter();
		}
		
		// we escape the values that will be displayed in the javascript footer of each datatable
		// to make sure there is malicious code injected (the value are already htmlspecialchar'ed as they
		// are loaded with Piwik_Common::getRequestVar()
		foreach($javascriptVariablesToSet as &$value)
		{
			if(is_array($value))
			{
				$value = array_map('addslashes',$value);
			}
			else
			{
				$value = addslashes($value);
			}
		}
		
		$deleteFromJavascriptVariables = array(
						'filter_excludelowpop',
						'filter_excludelowpop_value',
		);
		foreach($deleteFromJavascriptVariables as $name)
		{
			if(isset($javascriptVariablesToSet[$name]))
			{
				unset($javascriptVariablesToSet[$name]);
			}
		}
		return $javascriptVariablesToSet;
	}
	
	/**
	 * Returns, for a given parameter, the value of this parameter in the REQUEST array.
	 * If not set, returns the default value for this parameter @see getDefault()
	 *
	 * @param string $nameVar
	 * @return string|mixed Value of this parameter
	 */
	protected function getDefaultOrCurrent( $nameVar )
	{
		if(isset($_GET[$nameVar]))
		{
			return Piwik_Common::sanitizeInputValue($_GET[$nameVar]);
		}
		$default = $this->getDefault($nameVar);
		return $default;
	}

	/**
	 * Returns the default value for a given parameter.
	 * For example, these default values can be set using the disable* methods.
	 *
	 * @param string $nameVar
	 * @return mixed
	 */
	protected function getDefault($nameVar)
	{
		if(!isset($this->variablesDefault[$nameVar]))
		{
			return false;
		}
		return $this->variablesDefault[$nameVar];
	}
	
	/**
	 * The generic filters (limit, offset, sort by visit desc) will not be applied to this datatable.
	 */
	public function disableGenericFilters()
	{
		$this->variablesDefault['disable_generic_filters'] = true;
	}
	
	/**
	 * The queued filters (replace column names, enhance column with percentage signs, add logo metadata information, etc.)
	 * will not be applied to this datatable. They can be manually applied by calling applyQueuedFilters on the datatable.
	 */
	public function disableQueuedFilters()
	{
		$this->variablesDefault['disable_queued_filters'] = true;
	}
	
	/**
	 * The "X-Y of Z" and the "< Previous / Next >"-Buttons won't be displayed under this table
	 */
	public function disableOffsetInformationAndPaginationControls()
	{
		$this->viewProperties['show_offset_information'] = false;
		$this->viewProperties['show_pagination_control'] = false;
	}
	
	/**
	 * The "< Previous / Next >"-Buttons won't be displayed under this table
	 */
	public function disableShowPaginationControl()
	{
		$this->viewProperties['show_pagination_control'] = false;
	}
	
	/**
	 * The "X-Y of Z" won't be displayed under this table
	 */
	public function disableOffsetInformation()
	{
		$this->viewProperties['show_offset_information'] = false;
	}
	
	/**
	 * The search box won't be displayed under this table
	 */
	public function disableSearchBox()
	{
		$this->viewProperties['show_search'] = false;
	}

	/**
	 * Do not sort this table, leave it as it comes out of the API
	 */
	public function disableSort()
	{
		$this->variablesDefault['enable_sort'] = 'false';
	}
	
	/**
	 * Do not show the footer icons (show all columns icon, "plus" icon)
	 */
	public function disableFooterIcons()
	{
		$this->viewProperties['show_footer_icons'] = false;
	}
	
	/**
	 * When this method is called, the output will not contain the template datatable_footer.tpl
	 */
	public function disableFooter()
	{
		$this->viewProperties['show_footer'] = false;
	}
	
	/**
	 * The "Include low population" link won't be displayed under this table
	 */
	public function disableExcludeLowPopulation()
	{
		$this->viewProperties['show_exclude_low_population'] = false;
	}
	
	/**
	 * Whether or not to show the "View table" icon
	 */
	public function disableShowTable()
	{
		$this->viewProperties['show_table'] = false;
	}
	
	/**
	 * Whether or not to show the "View more data" icon
	 */
	public function disableShowAllColumns()
	{
		$this->viewProperties['show_table_all_columns'] = false;
	}
	
	/**
	 * Whether or not to show the tag cloud,  pie charts, bar chart icons
	 */
	public function disableShowAllViewsIcons()
	{
		$this->viewProperties['show_all_views_icons'] = false;
	}
	
	/**
	 * Whether or not to show the export to RSS feed icon
	 */
	public function disableShowExportAsRssFeed()
	{
		$this->viewProperties['show_export_as_rss_feed'] = false;
	}

	/**
	 * Whether or not to show the "goal" icon
	 */
	public function enableShowGoals()
	{
		if(Piwik_PluginsManager::getInstance()->isPluginActivated('Goals'))
		{
			$this->viewProperties['show_goals'] = true;
		}
	}

	/**
	 * Whether or not to show the "Ecommerce orders/cart" icons
	 */
	public function enableShowEcommerce()
	{
		$this->viewProperties['show_ecommerce'] = true;
	}
	
	/**
	 * Sets the value to use for the Exclude low population filter.
	 *
	 * @param int|float If a row value is less than this value, it will be removed from the dataTable
	 * @param string The name of the column for which we compare the value to $minValue
	 */
	public function setExcludeLowPopulation( $columnName = null, $minValue = null )
	{
		if(is_null($columnName))
		{
			$columnName = 'nb_visits';
		}
		$this->variablesDefault['filter_excludelowpop'] = $columnName;
		$this->variablesDefault['filter_excludelowpop_value'] = $minValue;
	}
	
	/**
	 * Sets the pattern to look for in the table (only rows matching the pattern will be kept)
	 *
	 * @param string $pattern to look for
	 * @param string $column to compare the pattern to
	 */
	public function setSearchPattern($pattern, $column)
	{
		$this->variablesDefault['filter_pattern'] = $pattern;
		$this->variablesDefault['filter_column'] = $column;
	}

	/**
	 * Sets the maximum number of rows of the table
	 *
	 * @param int $limit
	 */
	public function setLimit( $limit )
	{
		if($limit !== 0)
		{
			$this->variablesDefault['filter_limit'] = $limit;
		}
	}
	
	/**
	 * Will display a message in the DataTable footer.
	 *
	 * @param string $message Message
	 */
	public function setFooterMessage( $message )
	{
		$this->viewProperties['show_footer_message'] = $message;
	}
	
	/**
	 * Sets the dataTable column to sort by. This sorting will be applied before applying the (offset, limit) filter.
	 *
	 * @param int|string $columnId eg. 'nb_visits' for some tables, or Piwik_Archive::INDEX_NB_VISITS for others
	 * @param string $order desc or asc
	 */
	public function setSortedColumn( $columnId, $order = 'desc')
	{
		$this->variablesDefault['filter_sort_column'] = $columnId;
		$this->variablesDefault['filter_sort_order'] = $order;
	}
	
	/**
	 * Returns the column name on which the table will be sorted
	 *
	 * @return string
	 */
	public function getSortedColumn()
	{
		return $this->variablesDefault['filter_sort_column'];
	}

	/**
	 * Sets translation string for given column
	 *
	 * @param string $columnName column name
	 * @param string $columnTranslation column name translation
	 */
	public function setColumnTranslation( $columnName, $columnTranslation )
	{
		if(empty($columnTranslation))
		{
			throw new Exception('Unknown column: '.$columnName);
		}

		$this->columnsTranslations[$columnName] = $columnTranslation;
	}
	
	/**
	 * Returns column translation if available, in other case given column name
	 *
	 * @param string $columnName column name
	 */
	public function getColumnTranslation( $columnName )
	{
		if( isset($this->columnsTranslations[$columnName]) )
		{
			return $this->columnsTranslations[$columnName];
		}
		return $columnName;
	}
	
	/**
	 * Set the documentation of a metric used in the report.
	 * Please note, that the default way of doing this is by using
	 * getReportMetadata. Only use this method, if you have a good
	 * reason to do so.
	 *
	 * @param string $metricIdentifier The idenentifier string of
	 * 					the metric
	 * @param string $documentation The metric documentation as a
	 * 					translated string
	 */
	public function setMetricDocumentation($metricIdentifier, $documentation) {
		$this->metricsDocumentation[$metricIdentifier] = $documentation;
	}
	
	/**
	 * Returns metric documentation, or false
	 * @param string $columnName column name
	 */
	public function getMetricDocumentation($columnName)
	{
		if ($this->metricsDocumentation === false)
		{
			$this->loadDocumentation();
		}
		
		if (!empty($this->metricsDocumentation[$columnName]))
		{
			return $this->metricsDocumentation[$columnName];
		}
		
		return false;
	}
	
	/**
	 * Set the documentation of the report.
	 * Please note, that the default way of doing this is by using
	 * getReportMetadata. Only use this method, if you have a good
	 * reason to do so.
	 *
	 * @param string $documentation The report documentation as a
	 * 					translated string
	 */
	public function setReportDocumentation($documentation) {
		$this->documentation = $documentation;
	}
	
	/** Returns report documentation, or false */
	public function getReportDocumentation()
	{
		if ($this->metricsDocumentation === false)
		{
			$this->loadDocumentation();
		}
		
		return $this->documentation;
	}
	
	/** Load documentation from the API */
	private function loadDocumentation() 
	{
		$this->metricsDocumentation = array();
		
		$report = Piwik_API_API::getInstance()->getMetadata(0, $this->currentControllerName, $this->currentControllerAction);
		$report = $report[0];
		
		if (isset($report['metricsDocumentation']))
		{
			$this->metricsDocumentation = $report['metricsDocumentation'];
		}
		
		if (isset($report['documentation']))
		{
			$this->documentation = $report['documentation'];
		}
	}

	/**
	 * Sets the columns that will be displayed in the HTML output
	 * By default all columns are displayed ($columnsNames = array() will display all columns)
	 *
	 * @param array $columnsNames Array of column names eg. array('nb_visits','nb_hits')
	 */
	public function setColumnsToDisplay( $columnsNames )
	{
		if(!is_array($columnsNames))
		{
			$columnsNames = array($columnsNames);
		}
		$this->columnsToDisplay = $columnsNames;
	}

	/**
	 * Returns columns names to display, in order.
	 * If no columns were specified to be displayed, return all columns found in the first row.
	 * @param array PHP array conversion of the data table
	 * @return array
	 */
	public function getColumnsToDisplay()
	{
		if(empty($this->columnsToDisplay))
		{
			$row = $this->dataTable->getFirstRow();
			if(empty($row))
			{
				return array();
			}

			return array_keys($row->getColumns());
		}

		$this->columnsToDisplay = array_filter($this->columnsToDisplay);
		return $this->columnsToDisplay;
	}
	
	/**
	 * Sets columns translations array.
	 *
	 * @param array $columnsTranslations An associative array indexed by column names, eg. array('nb_visit'=>"Numer of visits")
	 */
	public function setColumnsTranslations( $columnsTranslations )
	{
		$this->columnsTranslations += $columnsTranslations;
	}
	
	/**
	 * Sets a custom parameter, that will be printed in the javascript array associated with each datatable
	 *
	 * @param string parameter name
	 * @param mixed $value
	 */
	public function setCustomParameter($parameter, $value)
	{
		if(isset($this->variablesDefault[$parameter]))
		{
			throw new Exception("$parameter is already defined for this DataTable.");
		}
		$this->variablesDefault[$parameter] = $value;
	}
	
	/**
	 * Queues a Datatable filter, that will be applied once the datatable is loaded from the API.
	 * Useful when the controller needs to add columns, or decorate existing columns, when these filters don't
	 * necessarily make sense directly in the API.
	 *
	 * @param string $filterName
	 * @param mixed $parameters
	 * @param bool $runBeforeGenericFilters Set to true if the filter will delete rows from the table,
	 * 									and should therefore be ran before Sort, Limit, etc.
	 * @return void
	 */
	public function queueFilter($filterName, $parameters, $runBeforeGenericFilters = false)
	{
		if($runBeforeGenericFilters)
		{
			$this->queuedFiltersPriority[] = array($filterName, $parameters);
		}
		else
		{
			$this->queuedFilters[] = array($filterName, $parameters);
		}
	}
}
