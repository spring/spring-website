<?php
/**
 * Piwik - Open source web analytics
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id: Controller.php 5295 2011-10-13 22:53:49Z matt $
 *
 * @category Piwik_Plugins
 * @package Piwik_Goals
 */

/**
 *
 * @package Piwik_Goals
 */
class Piwik_Goals_Controller extends Piwik_Controller
{
	const CONVERSION_RATE_PRECISION = 1;
	
	protected $goalColumnNameToLabel = array(
		'avg_order_revenue' => 'General_AverageOrderValue',
		'nb_conversions' => 'Goals_ColumnConversions',
		'conversion_rate'=> 'General_ColumnConversionRate',
		'revenue' => 'General_TotalRevenue',
		'items' => 'General_PurchasedProducts',
	);
	
	private function formatConversionRate($conversionRate)
	{
		return sprintf('%.' . self::CONVERSION_RATE_PRECISION . 'f%%', $conversionRate);
	}

	public function __construct()
	{
		parent::__construct();
		$this->idSite = Piwik_Common::getRequestVar('idSite', null, 'int');
		$this->goals = Piwik_Goals_API::getInstance()->getGoals($this->idSite);
		foreach($this->goals as &$goal)
		{
			$goal['name'] = Piwik_Common::sanitizeInputValue($goal['name']);
			if(isset($goal['pattern']))
			{
				$goal['pattern'] = Piwik_Common::sanitizeInputValue($goal['pattern']);
			}
		}
	}
	
	public function widgetGoalReport()
	{
		$view = $this->getGoalReportView($idGoal = Piwik_Common::getRequestVar('idGoal', null, 'string'));
		$view->displayFullReport = false;
		echo $view->render();
	}
	
	public function goalReport()
	{
		$view = $this->getGoalReportView($idGoal = Piwik_Common::getRequestVar('idGoal', null, 'string'));
		$view->displayFullReport = true;
        $view->goalDimensions = Piwik_Goals::getReportsWithGoalMetrics();
		echo $view->render();
	}
	
	public function ecommerceReport()
	{
		$view = $this->getGoalReportView($idGoal = Piwik_Archive::LABEL_ECOMMERCE_ORDER);
		$view->displayFullReport = true;
		$view->goalDimensions = Piwik_Goals::getReportsWithGoalMetrics();
		echo $view->render();
	}
	protected function getItemsView($fetch, $type, $function, $api, $abandonedCart = false)
	{
		$saveGET = $_GET;
		$label = Piwik_Translate($type);
		$abandonedCart = Piwik_Common::getRequestVar('viewDataTable', 'ecommerceOrder', 'string') == 'ecommerceAbandonedCart';
		
		// Products in Ecommerce Orders
		if($abandonedCart === false)
		{
			$view = new Piwik_ViewDataTable_HtmlTable_EcommerceOrder();
			$columns = Piwik_Goals::getProductReportColumns();
			$view->setMetricDocumentation('revenue', Piwik_Translate('Goals_ColumnRevenueDocumentation', Piwik_Translate('Goals_DocumentationRevenueGeneratedByProductSales')));
			$view->setMetricDocumentation('quantity', Piwik_Translate('Goals_ColumnQuantityDocumentation', $label));
			$view->setMetricDocumentation('orders', Piwik_Translate('Goals_ColumnOrdersDocumentation', $label));
			$view->setMetricDocumentation('avg_price', Piwik_Translate('Goals_ColumnAveragePriceDocumentation', $label));
			$view->setMetricDocumentation('avg_quantity', Piwik_Translate('Goals_ColumnAverageQuantityDocumentation', $label));
			$view->setMetricDocumentation('nb_visits', Piwik_Translate('Goals_ColumnVisitsProductDocumentation', $label));
			$view->setMetricDocumentation('conversion_rate', Piwik_Translate('Goals_ColumnConversionRateProductDocumentation', $label));
		}
		// Products in Abandoned Carts
		else
		{
			$view = new Piwik_ViewDataTable_HtmlTable_EcommerceAbandonedCart();
			$columns = Piwik_Goals::getProductReportColumns();
			$columns['abandoned_carts'] = Piwik_Translate('General_AbandonedCarts');
			$columns['revenue'] = Piwik_Translate('Goals_LeftInCart', Piwik_Translate('General_ProductRevenue'));
			$columns['quantity'] = Piwik_Translate('Goals_LeftInCart', Piwik_Translate('General_Quantity'));
			$columns['avg_quantity'] = Piwik_Translate('Goals_LeftInCart', Piwik_Translate('General_AverageQuantity'));
			unset($columns['orders']);
			unset($columns['nb_visits']);
			unset($columns['conversion_rate']);
			$_GET['abandonedCarts'] = 1;
		}
		
		$view->init( $this->pluginName, $function, $api );
		$view->enableShowEcommerce();
		$view->disableShowAllViewsIcons();
		$view->disableShowTable();
		$view->disableExcludeLowPopulation();
		$view->disableShowAllColumns();
		$this->setPeriodVariablesView($view);
		$view->setLimit( 10 );
		
		$view->setColumnsTranslations(array_merge(
			array('label' => $label),
			$columns
		));
		$columnsToDisplay = array_merge(array('label'), array_keys($columns));
		$view->setColumnsToDisplay($columnsToDisplay);
		$view->setSortedColumn('revenue', 'desc');
		foreach(array('revenue', 'avg_price') as $column)
		{
			$view->queueFilter('ColumnCallbackReplace', array($column, array("Piwik", "getPrettyMoney"), array($this->idSite)));
		}
		$return = $this->renderView($view, $fetch);
		$_GET = $saveGET;
		return $return;
	}

	public function getItemsSku($fetch = false)
	{
		return $this->getItemsView($fetch, 'Goals_ProductSKU', __FUNCTION__, "Goals.getItemsSku");
	}
	
	public function getItemsName($fetch = false)
	{
		return $this->getItemsView($fetch, 'Goals_ProductName', __FUNCTION__, "Goals.getItemsName");
	}
	
	public function getItemsCategory($fetch = false)
	{
		return $this->getItemsView($fetch, 'Goals_ProductCategory', __FUNCTION__, "Goals.getItemsCategory");
	}
	
	public function getEcommerceLog($fetch = false)
	{
		$saveGET = $_GET;
		$_GET['filterEcommerce'] = 1;
		$_GET['widget'] = 1;
		$_GET['segment'] = 'visitEcommerceStatus!=none';
		$output = Piwik_FrontController::getInstance()->dispatch('Live', 'getVisitorLog', array($fetch));
		$_GET = $saveGET;
		return $output;
	}
	
	protected function getGoalReportView($idGoal = false)
	{
		$view = Piwik_View::factory('single_goal');
		if($idGoal == Piwik_Archive::LABEL_ECOMMERCE_ORDER)
		{
			$goalDefinition['name'] = Piwik_Translate('Goals_Ecommerce');
			$goalDefinition['allow_multiple'] = true;
			$view->ecommerce = true;
		}
		else
		{
			if(!isset($this->goals[$idGoal]))
			{
				Piwik::redirectToModule('Goals', 'index', array('idGoal' => null));
			}
			$goalDefinition = $this->goals[$idGoal];
		}
		$this->setGeneralVariablesView($view);
		$goal = $this->getMetricsForGoal($idGoal);
		foreach($goal as $name => $value)
		{
			$view->$name = $value;
		}
		if($idGoal == Piwik_Archive::LABEL_ECOMMERCE_ORDER)
		{
			$goal = $this->getMetricsForGoal(Piwik_Archive::LABEL_ECOMMERCE_CART);
			foreach($goal as $name => $value)
			{
				$name = 'cart_'.$name;
				$view->$name = $value;
			}
		}		
		$view->idGoal = $idGoal;
		$view->goalName = $goalDefinition['name'];
		$view->goalAllowMultipleConversionsPerVisit = $goalDefinition['allow_multiple'];
		$view->graphEvolution = $this->getEvolutionGraph(true, array('nb_conversions'), $idGoal);
		$view->nameGraphEvolution = 'GoalsgetEvolutionGraph'.$idGoal;
		$view->topDimensions = $this->getTopDimensions($idGoal);
		
		// conversion rate for new and returning visitors
		$segment = 'visitorType==returning,visitorType==returningCustomer';
		$conversionRateReturning = Piwik_Goals_API::getInstance()->getConversionRate($this->idSite, Piwik_Common::getRequestVar('period'), Piwik_Common::getRequestVar('date'), $segment, $idGoal);
		$view->conversion_rate_returning = $this->formatConversionRate($conversionRateReturning);
		$segment = 'visitorType==new';
		$conversionRateNew = Piwik_Goals_API::getInstance()->getConversionRate($this->idSite, Piwik_Common::getRequestVar('period'), Piwik_Common::getRequestVar('date'), $segment, $idGoal);
		$view->conversion_rate_new = $this->formatConversionRate($conversionRateNew);
		return $view;
	}
	
	public function index()
	{
		$view = $this->getOverviewView();
		$view->goalsJSON = json_encode($this->goals);
        $view->goalDimensions = Piwik_Goals::getReportsWithGoalMetrics();
		$view->userCanEditGoals = Piwik::isUserHasAdminAccess($this->idSite);
		$view->ecommerceEnabled = $this->site->isEcommerceEnabled();
		$view->displayFullReport = true;
		echo $view->render();
	}
	
	public function widgetGoalsOverview( )
	{
		$view = $this->getOverviewView();
		$view->displayFullReport = false;
		echo $view->render();
	}

	protected function getOverviewView()
	{
		$view = Piwik_View::factory('overview');
		$this->setGeneralVariablesView($view);
		
		$view->graphEvolution = $this->getEvolutionGraph(true, array('nb_conversions'));
		$view->nameGraphEvolution = 'GoalsgetEvolutionGraph';

		// sparkline for the historical data of the above values
		$view->urlSparklineConversions		= $this->getUrlSparkline('getEvolutionGraph', array('columns' => array('nb_conversions')));
		$view->urlSparklineConversionRate 	= $this->getUrlSparkline('getEvolutionGraph', array('columns' => array('conversion_rate')));
		$view->urlSparklineRevenue 			= $this->getUrlSparkline('getEvolutionGraph', array('columns' => array('revenue')));

		// Pass empty idGoal will return Goal overview
		$request = new Piwik_API_Request("method=Goals.get&format=original&idGoal=");
		$datatable = $request->process();
		$dataRow = $datatable->getFirstRow();
		$view->nb_conversions = $dataRow->getColumn('nb_conversions');
		$view->nb_visits_converted = $dataRow->getColumn('nb_visits_converted');
		$view->conversion_rate = $this->formatConversionRate($dataRow->getColumn('conversion_rate'));
		$view->revenue = $dataRow->getColumn('revenue');
		
		$goalMetrics = array();
		foreach($this->goals as $idGoal => $goal)
		{
			$goalMetrics[$idGoal] = $this->getMetricsForGoal($idGoal);
			$goalMetrics[$idGoal]['name'] = $goal['name'];
			$goalMetrics[$idGoal]['goalAllowMultipleConversionsPerVisit'] = $goal['allow_multiple'];
		}
		
		$view->goalMetrics = $goalMetrics;
		$view->goals = $this->goals;
		return $view;
	}

	public function getLastNbConversionsGraph( $fetch = false )
	{
		$view = $this->getLastUnitGraph($this->pluginName, __FUNCTION__, 'Goals.getConversions');
		return $this->renderView($view, $fetch);
	}
	
	public function getLastConversionRateGraph( $fetch = false )
	{
		$view = $this->getLastUnitGraph($this->pluginName, __FUNCTION__, 'Goals.getConversionRate');
		return $this->renderView($view, $fetch);
	}

	public function getLastRevenueGraph( $fetch = false )
	{
		$view = $this->getLastUnitGraph($this->pluginName, __FUNCTION__, 'Goals.getRevenue');
		return $this->renderView($view, $fetch);
	}
	
	public function addNewGoal()
	{
		$view = Piwik_View::factory('add_new_goal');
		$this->setGeneralVariablesView($view);
		$view->userCanEditGoals = Piwik::isUserHasAdminAccess($this->idSite);
		$view->onlyShowAddNewGoal = true;
		echo $view->render();
	}

	public function getEvolutionGraph( $fetch = false, $columns = false, $idGoal = false)
	{
		if(empty($columns))
		{
			$columns = Piwik_Common::getRequestVar('columns');
		}

		$columns = !is_array($columns) ? array($columns) : $columns;

		if(empty($idGoal))
		{
			$idGoal = Piwik_Common::getRequestVar('idGoal', false, 'string');
		}
		$view = $this->getLastUnitGraph($this->pluginName, __FUNCTION__, 'Goals.get');
		$view->setParametersToModify(array('idGoal' => $idGoal));
	
		$nameToLabel = $this->goalColumnNameToLabel;
		if($idGoal == Piwik_Archive::LABEL_ECOMMERCE_ORDER)
		{
			$nameToLabel['nb_conversions'] = 'General_EcommerceOrders';
		}
		elseif($idGoal == Piwik_Archive::LABEL_ECOMMERCE_CART)
		{
			$nameToLabel['nb_conversions'] = Piwik_Translate('General_VisitsWith', Piwik_Translate('Goals_AbandonedCart'));
			$nameToLabel['conversion_rate'] = $nameToLabel['nb_conversions'];
			$nameToLabel['revenue'] = Piwik_Translate('Goals_LeftInCart', Piwik_Translate('Goals_ColumnRevenue'));
			$nameToLabel['items'] = Piwik_Translate('Goals_LeftInCart', Piwik_Translate('Goals_Products'));
		}
		
		foreach($columns as $columnName)
		{
			$columnTranslation = '';
			// find the right translation for this column, eg. find 'revenue' if column is Goal_1_revenue
			foreach($nameToLabel as $metric => $metricTranslation)
			{
				if(strpos($columnName, $metric) !== false)
				{
					$columnTranslation = Piwik_Translate($metricTranslation);
					break;
				}
			}
			
			if(!empty($idGoal) && isset($this->goals[$idGoal]))
			{
				$goalName = $this->goals[$idGoal]['name'];
				$columnTranslation = "$columnTranslation (".Piwik_Translate('Goals_GoalX', "$goalName").")";
			}
			$view->setColumnTranslation($columnName, $columnTranslation);
		}
		$view->setColumnsToDisplay($columns);
		
		$langString = $idGoal ? 'Goals_SingleGoalOverviewDocumentation' : 'Goals_GoalsOverviewDocumentation';
		$view->setReportDocumentation(Piwik_Translate($langString, '<br />'));
		
		return $this->renderView($view, $fetch);
	}
	
	
	protected function getTopDimensions($idGoal)
	{
		$columnNbConversions = 'goal_'.$idGoal.'_nb_conversions';
		$columnConversionRate = 'goal_'.$idGoal.'_conversion_rate';
		
		$topDimensionsToLoad = array(
			'country' => 'UserCountry.getCountry',
			'keyword' => 'Referers.getKeywords',
			'website' => 'Referers.getWebsites',
		);
		
		$topDimensions = array();
		foreach($topDimensionsToLoad as $dimensionName => $apiMethod)
		{
			$request = new Piwik_API_Request("method=$apiMethod
												&format=original
												&filter_update_columns_when_show_all_goals=1
												&filter_only_display_idgoal=". Piwik_DataTable_Filter_AddColumnsProcessedMetricsGoal::GOALS_FULL_TABLE ."
												&filter_sort_order=desc
												&filter_sort_column=$columnNbConversions
												&filter_limit=3");
			$datatable = $request->process();
			$topDimension = array();
			foreach($datatable->getRows() as $row)
			{
				$conversions = $row->getColumn($columnNbConversions);
				if($conversions > 0)
				{
    				$topDimension[] = array (
    					'name' => $row->getColumn('label'),
    					'nb_conversions' => $conversions,
						'conversion_rate' => $this->formatConversionRate($row->getColumn($columnConversionRate)),
    					'metadata' => $row->getMetadata(),
    				);
				}
			}
			$topDimensions[$dimensionName] = $topDimension;
		}
		return $topDimensions;
	}
	
	protected function getMetricsForGoal($idGoal)
	{
		$request = new Piwik_API_Request("method=Goals.get&format=original&idGoal=$idGoal");
		$datatable = $request->process();
		$dataRow = $datatable->getFirstRow();
		$nbConversions = $dataRow->getColumn('nb_conversions');
		$nbVisitsConverted = $dataRow->getColumn('nb_visits_converted');
		// Backward compatibilty before 1.3, this value was not processed
		if(empty($nbVisitsConverted))
		{
			$nbVisitsConverted = $nbConversions;
		}
		$return = array (
				'id'				=> $idGoal,
				'nb_conversions' 	=> $nbConversions,
				'nb_visits_converted' => $nbVisitsConverted,
				'conversion_rate'	=> $this->formatConversionRate($dataRow->getColumn('conversion_rate')),
				'revenue'			=> $dataRow->getColumn('revenue'),
				'urlSparklineConversions' 		=> $this->getUrlSparkline('getEvolutionGraph', array('columns' => array('nb_conversions'), 'idGoal' => $idGoal)),
				'urlSparklineConversionRate' 	=> $this->getUrlSparkline('getEvolutionGraph', array('columns' => array('conversion_rate'), 'idGoal' => $idGoal)),
				'urlSparklineRevenue' 			=> $this->getUrlSparkline('getEvolutionGraph', array('columns' => array('revenue'), 'idGoal' => $idGoal)),
		);
		
		if($idGoal == Piwik_Archive::LABEL_ECOMMERCE_ORDER)
		{
			$return = array_merge($return, array(
				'revenue_subtotal' => $dataRow->getColumn('revenue_subtotal'),
				'revenue_tax' => $dataRow->getColumn('revenue_tax'),
				'revenue_shipping' => $dataRow->getColumn('revenue_shipping'),
				'revenue_discount' => $dataRow->getColumn('revenue_discount'),
			
				'items' => $dataRow->getColumn('items'),
				'avg_order_revenue' => $dataRow->getColumn('avg_order_revenue'),
				'urlSparklinePurchasedProducts' => $this->getUrlSparkline('getEvolutionGraph', array('columns' => array('items'), 'idGoal' => $idGoal)),
				'urlSparklineAverageOrderValue' => $this->getUrlSparkline('getEvolutionGraph', array('columns' => array('avg_order_revenue'), 'idGoal' => $idGoal)),
			));
		}
		return $return;
	}
}


// Used so that the template knows which datatable is being currently viewed 
class Piwik_ViewDataTable_HtmlTable_EcommerceOrder extends Piwik_ViewDataTable_HtmlTable {
	protected function getViewDataTableId()
	{
		return Piwik_Archive::LABEL_ECOMMERCE_ORDER;
	}
}
class Piwik_ViewDataTable_HtmlTable_EcommerceAbandonedCart extends Piwik_ViewDataTable_HtmlTable {
	protected function getViewDataTableId()
	{
		return Piwik_Archive::LABEL_ECOMMERCE_CART;
	}
}