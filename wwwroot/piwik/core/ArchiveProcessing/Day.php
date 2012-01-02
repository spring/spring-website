<?php
/**
 * Piwik - Open source web analytics
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id: Day.php 5286 2011-10-12 07:14:38Z EZdesign $
 *
 * @category Piwik
 * @package Piwik
 */

/**
 * Handles the archiving process for a day.
 * The class provides generic helper methods to manipulate data from the DB,
 * easily create Piwik_DataTable objects from running SELECT ... GROUP BY on the log_visit table.
 *
 * All the logic of the archiving is done inside the plugins listening to the event 'ArchiveProcessing_Day.compute'
 *
 * @package Piwik
 * @subpackage Piwik_ArchiveProcessing
 */
class Piwik_ArchiveProcessing_Day extends Piwik_ArchiveProcessing
{
	function __construct()
	{
		parent::__construct();
		$this->db = Zend_Registry::get('db');
	}

	/**
	 * Main method to process logs for a day. The only logic done here is computing the number of visits, actions, etc.
	 * All the other reports are computed inside plugins listening to the event 'ArchiveProcessing_Day.compute'.
	 * See some of the plugins for an example eg. 'Provider'
	 */
	protected function compute()
	{
		if(!$this->isThereSomeVisits())
		{
			return;
		}
		Piwik_PostEvent('ArchiveProcessing_Day.compute', $this);
	}
	
	/**
	 * Returns true if there are logs for the current archive.
	 *
	 * If the current archive is for a specific plugin (for example, Referers),
	 *   (for example when a Segment is defined and the Keywords report is requested)
	 * Then the function will create the Archive for the Core metrics 'VisitsSummary' which will in turn process the number of visits
	 *
	 *  If there is no specified segment, the SQL query will always run.
	 */
	public function isThereSomeVisits()
	{
		if (!is_null($this->isThereSomeVisits))
		{
			if ($this->isThereSomeVisits && is_null($this->nb_visits))
			{
				debug_print_backtrace();
				exit;
			}
			return $this->isThereSomeVisits;
		}
		
		// prepare segmentation
		$segment = $this->getSegment();
		
		// We check if there is visits for the requested date / site / segment
		//  If no specified Segment
		//  Or if a segment is passed and we specifically process VisitsSummary
		//   Then we check the logs. This is to ensure that this query is ran only once for this day/site/segment (rather than running it for every plugin)
		$reportType = self::getPluginBeingProcessed($this->getRequestedReport());
		if ($this->shouldProcessReportsAllPlugins($this->getSegment(), $this->period)
			|| ($reportType == 'VisitsSummary'))
		{
			// build query parts
			$select = "count(distinct log_visit.idvisitor) as nb_uniq_visitors,
				count(*) as nb_visits,
				sum(log_visit.visit_total_actions) as nb_actions,
				max(log_visit.visit_total_actions) as max_actions,
				sum(log_visit.visit_total_time) as sum_visit_length,
				sum(case log_visit.visit_total_actions when 1 then 1 else 0 end) as bounce_count,
				sum(case log_visit.visit_goal_converted when 1 then 1 else 0 end) as nb_visits_converted
			";
			$from = "log_visit";
			$where = "log_visit.visit_last_action_time >= ?
				AND log_visit.visit_last_action_time <= ?
				AND log_visit.idsite = ?
			";
			
			$bind = array($this->getStartDatetimeUTC(), $this->getEndDatetimeUTC(), $this->idsite);
			$query = $segment->getSelectQuery($select, $from, $where, $bind);
			
			$bind = $query['bind'];
			$sql = $query['sql'];
			
			$data = $this->db->fetchRow($sql, $bind);
			
			// no visits found
			if (!is_array($data) || $data['nb_visits'] == 0)
			{
				return $this->isThereSomeVisits = false;
			}
			
			// visits found: set attribtues
			foreach ($data as $name => $value)
			{
				$this->insertNumericRecord($name, $value);
			}
			
			$this->setNumberOfVisits($data['nb_visits']);
			$this->setNumberOfVisitsConverted($data['nb_visits_converted']);
			
			return $this->isThereSomeVisits = true;
		}
		
		return $this->redirectRequestToVisitsSummary();
	}
	
	/**
	 * If a segment is specified but a plugin other than 'VisitsSummary' is being requested,
	 * we create an archive for processing VisitsSummary Core Metrics, which will in turn
	 * execute the query above (in isThereSomeVisits)
	 */
	private function redirectRequestToVisitsSummary()
	{
		$archive = new Piwik_Archive_Single();
		$archive->setSite($this->site);
		$archive->setPeriod($this->period);
		$archive->setSegment($this->getSegment());
		$archive->setRequestedReport('VisitsSummary');
		
		$nbVisits = $archive->getNumeric('nb_visits');
		$this->isThereSomeVisits = $nbVisits > 0;
		
		if ($this->isThereSomeVisits)
		{
			$nbVisitsConverted = $archive->getNumeric('nb_visits_converted');
			$this->setNumberOfVisits($nbVisits);
			$this->setNumberOfVisitsConverted($nbVisitsConverted);
		}
		
		return $this->isThereSomeVisits;
	}
	
	/**
	 * Helper function that returns a DataTable containing the $select fields / value pairs.
	 * IMPORTANT: The $select must return only one row!!
	 *
	 * Example $select = "count(distinct( config_os )) as countDistinctOs,
	 * 						sum( config_flash ) / count(distinct(idvisit)) as percentFlash "
	 * 		   $labelCount = "test_column_name"
	 * will return a dataTable that looks like
	 * 		label  				test_column_name
	 * 		CountDistinctOs 	9
	 * 		PercentFlash 		0.5676
	 *
	 *
	 * @param string $select
	 * @param string $labelCount
	 * @return Piwik_DataTable
	 */
	public function getSimpleDataTableFromSelect($select, $labelCount)
	{
		$from = "log_visit";
		$where = "log_visit.visit_last_action_time >= ?
				AND log_visit.visit_last_action_time <= ?
	 			AND log_visit.idsite = ?";
		
		$bind = array($this->getStartDatetimeUTC(), $this->getEndDatetimeUTC(), $this->idsite);
		
		$query = $this->getSegment()->getSelectQuery($select, $from, $where, $bind);
		
		$data = $this->db->fetchRow($query['sql'], $query['bind']);
		
		foreach($data as $label => &$count)
		{
			$count = array($labelCount => $count);
		}
		$table = new Piwik_DataTable();
		$table->addRowsFromArrayWithIndexLabel($data);
		return $table;
	}

	public function queryActionsByDimension($label, $where = '')
	{
	    if(is_array($label))
	    {
	    	$label2 = $label;
	    	foreach($label2 as &$field) { $field = 'log_link_visit_action.'. $field; }
	        $groupBy = implode(", ", $label2);
	    	foreach($label2 as $id => &$field) { $field = "$field AS ".$label[$id]; }
	        $select = implode(", ", $label2);
	        
	        // IF we query Custom Variables scope "page" either: Product SKU, Product Name, 
	        // then we also query the "Product page view" price which was possibly recorded.
	        if(in_array(reset($label), array('custom_var_k3','custom_var_k4','custom_var_k5')))
	        {
	        	$select .= ", ".self::getSqlRevenue("AVG(log_link_visit_action.custom_var_v2)")." as `". Piwik_Archive::INDEX_ECOMMERCE_ITEM_PRICE_VIEWED ."`";
	        }
	    }
	    else
	    {
	        $select = $label . " AS label ";
	        $groupBy = 'label';
	    }
	    
	    if(!empty($where))
	    {
	    	$where = sprintf($where, "log_link_visit_action", "log_link_visit_action");
	        $where = ' AND '.$where;
	    }
	    
		/*
		 * Page URLs and Page names, general stats
		 */
		$select = "$select,
				count(distinct log_link_visit_action.idvisit) as `". Piwik_Archive::INDEX_NB_VISITS ."`,
				count(distinct log_link_visit_action.idvisitor) as `". Piwik_Archive::INDEX_NB_UNIQ_VISITORS ."`,
				count(*) as `". Piwik_Archive::INDEX_NB_ACTIONS ."`";

		$from = "log_link_visit_action";
		
		$where = "log_link_visit_action.server_time >= ?
				AND log_link_visit_action.server_time <= ?
				AND log_link_visit_action.idsite = ?
				$where";
				 		
		$bind = array($this->getStartDatetimeUTC(), $this->getEndDatetimeUTC(), $this->idsite);
        
		$query = $this->getSegment()->getSelectQuery($select, $from, $where, $bind, $orderBy=false, $groupBy);
		
		return $this->db->query($query['sql'], $query['bind']);
	}
	
	/**
	 * Query visits by dimension
	 *
	 * @param string $label mixed Can be a string, eg. "referer_name", will be aliased as 'label' in the returned rows
	 * 				Can also be an array of strings, when the dimension spans multiple fields, eg. array("referer_name", "referer_keyword")
	 * @param string $where Additional condition for WHERE clause
	 */
	public function queryVisitsByDimension($label, $where = '')
	{
	    if(is_array($label))
	    {
	        $groupBy = "log_visit.".implode(", log_visit.", $label);
	    	foreach($label as &$field)
	    	{
	    		$field = 'log_visit.'.$field.' AS '.$field;
	    	}
	        $select = implode(", ", $label);
	    }
	    else
	    {
	        $select = $label . " AS label ";
	        $groupBy = 'label';
	    }
	    
	    if(!empty($where))
	    {
	    	$where = sprintf($where, "log_visit", "log_visit");
	        $where = ' AND '.$where;
	    }
	    
	    $select = "$select,
				count(distinct log_visit.idvisitor) as `". Piwik_Archive::INDEX_NB_UNIQ_VISITORS ."`,
				count(*) as `". Piwik_Archive::INDEX_NB_VISITS ."`,
				sum(log_visit.visit_total_actions) as `". Piwik_Archive::INDEX_NB_ACTIONS ."`,
				max(log_visit.visit_total_actions) as `". Piwik_Archive::INDEX_MAX_ACTIONS ."`,
				sum(log_visit.visit_total_time) as `". Piwik_Archive::INDEX_SUM_VISIT_LENGTH ."`,
				sum(case log_visit.visit_total_actions when 1 then 1 else 0 end) as `". Piwik_Archive::INDEX_BOUNCE_COUNT ."`,
				sum(case log_visit.visit_goal_converted when 1 then 1 else 0 end) as `". Piwik_Archive::INDEX_NB_VISITS_CONVERTED ."`";
	    
	    $from = "log_visit";
	    
	    $where = "log_visit.visit_last_action_time >= ?
				AND log_visit.visit_last_action_time <= ?
				AND log_visit.idsite = ?
				$where";
		
		$bind = array($this->getStartDatetimeUTC(), $this->getEndDatetimeUTC(), $this->idsite);
		
		$query = $this->getSegment()->getSelectQuery($select, $from, $where, $bind, $orderBy=false, $groupBy);
		
		return $this->db->query($query['sql'], $query['bind']);
	}
	
	/**
	 * @see queryVisitsByDimension() Similar to this function,
	 * but queries metrics for the requested dimensions,
	 * for each Goal conversion
	 */
	public function queryConversionsByDimension($label, $where = '')
	{
		if(empty($label))
		{
			$select = "";
			$groupBy = "";
		}
	    elseif(is_array($label))
	    {
	        $groupBy = "log_conversion.".implode(", log_conversion.", $label);
	    	foreach($label as &$field)
	    	{
	    		$field = 'log_conversion.'.$field.' AS '.$field ;
	    	}
	        $select =  implode(", ", $label) . ", ";
	    }
	    else
	    {
	        $select = $label . " AS label, ";
	        $groupBy = 'label';
	    }
	    if(!empty($where))
	    {
	    	$where = sprintf($where, "log_conversion", "log_conversion");
	        $where = ' AND '.$where;
	    }
	    
		$select .= 	self::getSqlRevenue('SUM(log_conversion.revenue_subtotal)')." as `". Piwik_Archive::INDEX_GOAL_ECOMMERCE_REVENUE_SUBTOTAL ."`,".
		    		self::getSqlRevenue('SUM(log_conversion.revenue_tax)')." as `". Piwik_Archive::INDEX_GOAL_ECOMMERCE_REVENUE_TAX ."`,".
		    		self::getSqlRevenue('SUM(log_conversion.revenue_shipping)')." as `". Piwik_Archive::INDEX_GOAL_ECOMMERCE_REVENUE_SHIPPING ."`,".
		    		self::getSqlRevenue('SUM(log_conversion.revenue_discount)')." as `". Piwik_Archive::INDEX_GOAL_ECOMMERCE_REVENUE_DISCOUNT ."`,".
		    		"SUM(log_conversion.items) as `". Piwik_Archive::INDEX_GOAL_ECOMMERCE_ITEMS ."`, ";
		    		
	    $groupBy = !empty($groupBy) ? ", $groupBy" : '';
	    
	    $select = "$select
				log_conversion.idgoal,
				count(*) as `". Piwik_Archive::INDEX_GOAL_NB_CONVERSIONS ."`,
				".self::getSqlRevenue('SUM(log_conversion.revenue)')." as `". Piwik_Archive::INDEX_GOAL_REVENUE ."`,
				count(distinct log_conversion.idvisit) as `". Piwik_Archive::INDEX_GOAL_NB_VISITS_CONVERTED."`";
	    
	    $from = "log_conversion";
	    
	    $where = "log_conversion.server_time >= ?
				AND log_conversion.server_time <= ?
	 			AND log_conversion.idsite = ?
	 			$where";
	 			
	 	$groupBy = "log_conversion.idgoal $groupBy";
		
		$bind = array($this->getStartDatetimeUTC(), $this->getEndDatetimeUTC(), $this->idsite);
		                     
        $query = $this->getSegment()->getSelectQuery($select, $from, $where, $bind, $orderBy=false, $groupBy);
        
        return $this->db->query($query['sql'], $query['bind']);
	}
	
	public function queryEcommerceItems($field)
	{
		$query = "SELECT
						name as label,
						".self::getSqlRevenue('SUM(quantity * price)')." as `". Piwik_Archive::INDEX_ECOMMERCE_ITEM_REVENUE ."`,
						".self::getSqlRevenue('SUM(quantity)')." as `". Piwik_Archive::INDEX_ECOMMERCE_ITEM_QUANTITY ."`,
						".self::getSqlRevenue('SUM(price)')." as `". Piwik_Archive::INDEX_ECOMMERCE_ITEM_PRICE ."`,
						count(distinct idorder) as `". Piwik_Archive::INDEX_ECOMMERCE_ORDERS."`,
						count(idvisit) as `". Piwik_Archive::INDEX_NB_VISITS."`,
						case idorder when '0' then ".Piwik_Tracker_GoalManager::IDGOAL_CART." else ".Piwik_Tracker_GoalManager::IDGOAL_ORDER." end as ecommerceType
			 	FROM ".Piwik_Common::prefixTable('log_conversion_item')."
			 		LEFT JOIN ".Piwik_Common::prefixTable('log_action')."
			 		ON $field = idaction
			 	WHERE server_time >= ?
						AND server_time <= ?
			 			AND idsite = ?
			 			AND deleted = 0
			 	GROUP BY ecommerceType, $field
				ORDER BY NULL";
						
		$bind = array( $this->getStartDatetimeUTC(),
                       $this->getEndDatetimeUTC(),
                       $this->idsite
        );
		$query = $this->db->query($query, $bind);
		return $query;
	}
	
	static public function getSqlRevenue($field)
	{
		return "ROUND(".$field.",".Piwik_Tracker_GoalManager::REVENUE_PRECISION.")";
	}
	
	public function getDataTableFromArray( $array )
	{
		$table = new Piwik_DataTable();
		$table->addRowsFromArrayWithIndexLabel($array);
		return $table;
	}
	
	/**
	 * Output:
	 * 		array(
	 * 			LABEL => array(
	 * 						Piwik_Archive::INDEX_NB_UNIQ_VISITORS 	=> 0,
	 *						Piwik_Archive::INDEX_NB_VISITS 			=> 0
	 *					),
	 *			LABEL2 => array(
	 *					[...]
	 *					)
	 * 		)
 	 *
	 * Helper function that returns an array with common statistics for a given database field distinct values.
	 *
	 * The statistics returned are:
	 *  - number of unique visitors
	 *  - number of visits
	 *  - number of actions
	 *  - maximum number of action for a visit
	 *  - sum of the visits' length in sec
	 *  - count of bouncing visits (visits with one page view)
	 *
	 * For example if $label = 'config_os' it will return the statistics for every distinct Operating systems
	 * The returned array will have a row per distinct operating systems,
	 * and a column per stat (nb of visits, max  actions, etc)
	 *
	 * 'label'	Piwik_Archive::INDEX_NB_UNIQ_VISITORS	Piwik_Archive::INDEX_NB_VISITS	etc.
	 * Linux	27	66	...
	 * Windows XP	12	...
	 * Mac OS	15	36	...
	 *
	 * @param string $label Table log_visit field name to be use to compute common stats
	 * @return array
	 */
	public function getArrayInterestForLabel($label)
	{
	    $query = $this->queryVisitsByDimension($label);
		$interest = array();
		while($row = $query->fetch())
		{
			if(!isset($interest[$row['label']])) $interest[$row['label']]= $this->getNewInterestRow();
			$this->updateInterestStats( $row, $interest[$row['label']]);
		}
		return $interest;
	}
	
	/**
	 * Generates a dataTable given a multidimensional PHP array that associates LABELS to Piwik_DataTableRows
	 * This is used for the "Actions" DataTable, where a line is the aggregate of all the subtables
	 * Example: the category /blog has 3 visits because it has /blog/index (2 visits) + /blog/about (1 visit)
	 *
	 * @param array $table
	 * @return Piwik_DataTable
	 */
	static public function generateDataTable( $table, $parents=array() )
	{
		$dataTableToReturn = new Piwik_DataTable();
		foreach($table as $label => $maybeDatatableRow)
		{
			// case the aInfo is a subtable-like array
			// it means that we have to go recursively and process it
			// then we build the row that is an aggregate of all the children
			// and we associate this row to the subtable
			if( !($maybeDatatableRow instanceof Piwik_DataTable_Row) )
			{
				array_push($parents, array($dataTableToReturn->getId(), $label));
				
				$subTable = self::generateDataTable($maybeDatatableRow, $parents);
				$subTable->setParents($parents);
				$row = new Piwik_DataTable_Row_DataTableSummary( $subTable );
				$row->setColumns( array('label' => $label) + $row->getColumns());
				$row->addSubtable($subTable);
				
				array_pop($parents);
			}
			// if aInfo is a simple Row we build it
			else
			{
				$row = $maybeDatatableRow;
			}
			
			$dataTableToReturn->addRow($row);
		}
		return $dataTableToReturn;
	}
	
	/**
	 * Helper function that returns the serialized DataTable of the given PHP array.
	 * The array must have the format of Piwik_DataTable::addRowsFromArrayWithIndexLabel()
	 * Example: 	array (
	 * 	 				LABEL => array(col1 => X, col2 => Y),
	 * 	 				LABEL2 => array(col1 => X, col2 => Y),
	 * 				)
	 *
	 * @param array $array at the given format
	 * @return array Array with one element: the serialized data table string
	 */
	public function getDataTableSerialized( $array )
	{
		$table = new Piwik_DataTable();
		$table->addRowsFromArrayWithIndexLabel($array );
		$toReturn = $table->getSerialized();
		return $toReturn;
	}
	
	
	/**
	 * Helper function that returns the multiple serialized DataTable of the given PHP array.
	 * The DataTable here associates a subtable to every row of the level 0 array.
	 * This is used for example for search engines.
	 * Every search engine (level 0) has a subtable containing the keywords.
	 *
	 * The $arrayLevel0 must have the format
	 * Example: 	array (
	 * 					// Yahoo.com => array( kwd1 => stats, kwd2 => stats )
	 * 	 				LABEL => array(col1 => X, col2 => Y),
	 * 	 				LABEL2 => array(col1 => X, col2 => Y),
	 * 				)
	 *
	 * The $subArrayLevel1ByKey must have the format
	 * Example: 	array(
	 * 					// Yahoo.com => array( stats )
	 * 					LABEL => #Piwik_DataTable_ForLABEL,
	 * 					LABEL2 => #Piwik_DataTable_ForLABEL2,
	 * 				)
	 *
	 *
	 * @param array $arrayLevel0
	 * @param array $subArrayLevel1ByKey Array of Piwik_DataTable
	 * @return array Array with N elements: the strings of the datatable serialized
	 */
	public function getDataTableWithSubtablesFromArraysIndexedByLabel( $arrayLevel0, $subArrayLevel1ByKey )
	{
		$parentTableLevel0 = new Piwik_DataTable();
		
		$tablesByLabel = array();
		foreach($arrayLevel0 as $label => $aAllRowsForThisLabel)
		{
			$table = new Piwik_DataTable();
			$table->addRowsFromArrayWithIndexLabel($aAllRowsForThisLabel);
			$tablesByLabel[$label] = $table;
		}
		$parentTableLevel0->addRowsFromArrayWithIndexLabel($subArrayLevel1ByKey, $tablesByLabel);

		return $parentTableLevel0;
	}
	
	/**
	 * Returns an empty row containing default values for the common stat
	 *
	 * @return array
	 */
	public function getNewInterestRow($onlyMetricsAvailableInActionsTable = false, $doNotSumVisits = false)
	{
		if($onlyMetricsAvailableInActionsTable)
		{
			if($doNotSumVisits)
			{
					return array(Piwik_Archive::INDEX_NB_ACTIONS => 0 );
			}
			return array(	
					Piwik_Archive::INDEX_NB_UNIQ_VISITORS 	=> 0,
					Piwik_Archive::INDEX_NB_VISITS 			=> 0,
					Piwik_Archive::INDEX_NB_ACTIONS 		=> 0 );
		}
		return array(	Piwik_Archive::INDEX_NB_UNIQ_VISITORS 	=> 0,
						Piwik_Archive::INDEX_NB_VISITS 			=> 0,
						Piwik_Archive::INDEX_NB_ACTIONS 		=> 0,
						Piwik_Archive::INDEX_MAX_ACTIONS 		=> 0,
						Piwik_Archive::INDEX_SUM_VISIT_LENGTH 	=> 0,
						Piwik_Archive::INDEX_BOUNCE_COUNT 		=> 0,
						Piwik_Archive::INDEX_NB_VISITS_CONVERTED=> 0,
		);
	}
	
	
	/**
	 * Returns a Piwik_DataTable_Row containing default values for common stat,
	 * plus a column 'label' with the value $label
	 *
	 * @param string $label
	 * @return Piwik_DataTable_Row
	 */
	public function getNewInterestRowLabeled( $label )
	{
		return new Piwik_DataTable_Row(
				array(
					Piwik_DataTable_Row::COLUMNS => 		array(	'label' => $label)
															+ $this->getNewInterestRow()
					)
				);
	}
	
	/**
	 * Adds the given row $newRowToAdd to the existing  $oldRowToUpdate passed by reference
	 *
	 * The rows are php arrays Name => value
	 *
	 * @param array $newRowToAdd
	 * @param array $oldRowToUpdate
	 */
	public function updateInterestStats( $newRowToAdd, &$oldRowToUpdate, $onlyMetricsAvailableInActionsTable = false, $doNotSumVisits = false)
	{
		// Pre 1.2 format: string indexed rows are returned from the DB
		// Left here for Backward compatibility with plugins doing custom SQL queries using these metrics as string
		if(!isset($newRowToAdd[Piwik_Archive::INDEX_NB_VISITS]))
		{
			if(!$doNotSumVisits)
			{
	    		$oldRowToUpdate[Piwik_Archive::INDEX_NB_UNIQ_VISITORS]		+= $newRowToAdd['nb_uniq_visitors'];
	    		$oldRowToUpdate[Piwik_Archive::INDEX_NB_VISITS] 			+= $newRowToAdd['nb_visits'];
			}
			$oldRowToUpdate[Piwik_Archive::INDEX_NB_ACTIONS] 			+= $newRowToAdd['nb_actions'];
    		if($onlyMetricsAvailableInActionsTable)
    		{
    			return;
    		}
    		$oldRowToUpdate[Piwik_Archive::INDEX_MAX_ACTIONS] 		 	= (float)max($newRowToAdd['max_actions'], $oldRowToUpdate[Piwik_Archive::INDEX_MAX_ACTIONS]);
    		$oldRowToUpdate[Piwik_Archive::INDEX_SUM_VISIT_LENGTH]		+= $newRowToAdd['sum_visit_length'];
    		$oldRowToUpdate[Piwik_Archive::INDEX_BOUNCE_COUNT] 			+= $newRowToAdd['bounce_count'];
    		$oldRowToUpdate[Piwik_Archive::INDEX_NB_VISITS_CONVERTED] 	+= $newRowToAdd['nb_visits_converted'];
    		return;
		}
		if(!$doNotSumVisits)
		{
			$oldRowToUpdate[Piwik_Archive::INDEX_NB_UNIQ_VISITORS]		+= $newRowToAdd[Piwik_Archive::INDEX_NB_UNIQ_VISITORS];
			$oldRowToUpdate[Piwik_Archive::INDEX_NB_VISITS] 			+= $newRowToAdd[Piwik_Archive::INDEX_NB_VISITS];
		}
		$oldRowToUpdate[Piwik_Archive::INDEX_NB_ACTIONS] 			+= $newRowToAdd[Piwik_Archive::INDEX_NB_ACTIONS];
		
		// Hack for Price tracking on Ecommerce product/category pages
		// The price is not summed, but AVG is taken in the SQL query 
		$index = Piwik_Archive::INDEX_ECOMMERCE_ITEM_PRICE_VIEWED;
		if(!empty($newRowToAdd[$index]))
		{
			$oldRowToUpdate[$index] = (float)$newRowToAdd[$index];
		}
		
    	if($onlyMetricsAvailableInActionsTable)
    	{
    		return;
    	}
		$oldRowToUpdate[Piwik_Archive::INDEX_MAX_ACTIONS] 		 	= (float)max($newRowToAdd[Piwik_Archive::INDEX_MAX_ACTIONS], $oldRowToUpdate[Piwik_Archive::INDEX_MAX_ACTIONS]);
		$oldRowToUpdate[Piwik_Archive::INDEX_SUM_VISIT_LENGTH]		+= $newRowToAdd[Piwik_Archive::INDEX_SUM_VISIT_LENGTH];
		$oldRowToUpdate[Piwik_Archive::INDEX_BOUNCE_COUNT] 			+= $newRowToAdd[Piwik_Archive::INDEX_BOUNCE_COUNT];
		$oldRowToUpdate[Piwik_Archive::INDEX_NB_VISITS_CONVERTED] 	+= $newRowToAdd[Piwik_Archive::INDEX_NB_VISITS_CONVERTED];
		
	}
	
	/**
	 * Given an array of stats, it will process the sum of goal conversions
	 * and sum of revenue and add it in the stats array in two new fields.
	 *
	 * @param array $interestByLabel Passed by reference, it will be modified as follows:
	 * Input:
	 * 		array(
	 * 			LABEL  => array( Piwik_Archive::INDEX_NB_VISITS => X,
	 * 							 Piwik_Archive::INDEX_GOALS => array(
	 * 								idgoal1 => array( [...] ),
	 * 								idgoal2 => array( [...] ),
	 * 							),
	 * 							[...] ),
	 * 			LABEL2 => array( Piwik_Archive::INDEX_NB_VISITS => Y, [...] )
	 * 			);
	 *
	 *
	 * Output:
	 * 		array(
	 * 			LABEL  => array( Piwik_Archive::INDEX_NB_VISITS => X,
	 * 							 Piwik_Archive::INDEX_NB_CONVERSIONS => Y, // sum of all conversions
	 * 							 Piwik_Archive::INDEX_REVENUE => Z, // sum of all revenue
	 * 							 Piwik_Archive::INDEX_GOALS => array(
	 * 								idgoal1 => array( [...] ),
	 * 								idgoal2 => array( [...] ),
	 * 							),
	 * 							[...] ),
	 * 			LABEL2 => array( Piwik_Archive::INDEX_NB_VISITS => Y, [...] )
	 * 			);
	 * 		)
	 *
	 * @param array $interestByLabel Passed by reference, will be modified
	 */
	function enrichConversionsByLabelArray(&$interestByLabel)
	{
		foreach($interestByLabel as $label => &$values)
		{
			if(isset($values[Piwik_Archive::INDEX_GOALS]))
			{
				// When per goal metrics are processed, general 'visits converted' is not meaningful because
				// it could differ from the sum of each goal conversions
				unset($values[Piwik_Archive::INDEX_NB_VISITS_CONVERTED]);
				$revenue = $conversions = 0;
				foreach($values[Piwik_Archive::INDEX_GOALS] as $idgoal => $goalValues)
				{
					// Do not sum Cart revenue since it is a lost revenue
					if($idgoal >= Piwik_Tracker_GoalManager::IDGOAL_ORDER)
					{
						$revenue += $goalValues[Piwik_Archive::INDEX_GOAL_REVENUE];
						$conversions += $goalValues[Piwik_Archive::INDEX_GOAL_NB_CONVERSIONS];
					}
				}
				$values[Piwik_Archive::INDEX_NB_CONVERSIONS] = $conversions;
				
				// 25.00 recorded as 25
				if(round($revenue) == $revenue)
				{
					$revenue = round($revenue);
				}
				$values[Piwik_Archive::INDEX_REVENUE] = $revenue;
			}
		}
	}

	/**
	 * @param array $interestByLabelAndSubLabel Passed by reference, will be modified
	 */
	function enrichConversionsByLabelArrayHasTwoLevels(&$interestByLabelAndSubLabel)
	{
		foreach($interestByLabelAndSubLabel as $mainLabel => &$interestBySubLabel)
		{
			$this->enrichConversionsByLabelArray($interestBySubLabel);
		}
	}

	function updateGoalStats($newRowToAdd, &$oldRowToUpdate)
	{
		$oldRowToUpdate[Piwik_Archive::INDEX_GOAL_NB_CONVERSIONS]	+= $newRowToAdd[Piwik_Archive::INDEX_GOAL_NB_CONVERSIONS];
		$oldRowToUpdate[Piwik_Archive::INDEX_GOAL_NB_VISITS_CONVERTED]	+= $newRowToAdd[Piwik_Archive::INDEX_GOAL_NB_VISITS_CONVERTED];
		$oldRowToUpdate[Piwik_Archive::INDEX_GOAL_REVENUE] 			+= $newRowToAdd[Piwik_Archive::INDEX_GOAL_REVENUE];
		
		// Cart & Order
		if(isset($oldRowToUpdate[Piwik_Archive::INDEX_GOAL_ECOMMERCE_ITEMS]))
		{
			$oldRowToUpdate[Piwik_Archive::INDEX_GOAL_ECOMMERCE_ITEMS] += $newRowToAdd[Piwik_Archive::INDEX_GOAL_ECOMMERCE_ITEMS];
			
			// Order only
			if(isset($oldRowToUpdate[Piwik_Archive::INDEX_GOAL_ECOMMERCE_REVENUE_SUBTOTAL]))
			{
				$oldRowToUpdate[Piwik_Archive::INDEX_GOAL_ECOMMERCE_REVENUE_SUBTOTAL] += $newRowToAdd[Piwik_Archive::INDEX_GOAL_ECOMMERCE_REVENUE_SUBTOTAL];
				$oldRowToUpdate[Piwik_Archive::INDEX_GOAL_ECOMMERCE_REVENUE_TAX] += $newRowToAdd[Piwik_Archive::INDEX_GOAL_ECOMMERCE_REVENUE_TAX];
				$oldRowToUpdate[Piwik_Archive::INDEX_GOAL_ECOMMERCE_REVENUE_SHIPPING] += $newRowToAdd[Piwik_Archive::INDEX_GOAL_ECOMMERCE_REVENUE_SHIPPING];
				$oldRowToUpdate[Piwik_Archive::INDEX_GOAL_ECOMMERCE_REVENUE_DISCOUNT] += $newRowToAdd[Piwik_Archive::INDEX_GOAL_ECOMMERCE_REVENUE_DISCOUNT];
			}
		}
	}
	
	function getNewGoalRow($idGoal)
	{
		if($idGoal > Piwik_Tracker_GoalManager::IDGOAL_ORDER)
		{
			return array(	Piwik_Archive::INDEX_GOAL_NB_CONVERSIONS 	=> 0,
							Piwik_Archive::INDEX_GOAL_NB_VISITS_CONVERTED => 0,
							Piwik_Archive::INDEX_GOAL_REVENUE 			=> 0,
						);
		}
		if($idGoal == Piwik_Tracker_GoalManager::IDGOAL_ORDER)
		{
			return array(	Piwik_Archive::INDEX_GOAL_NB_CONVERSIONS 	=> 0,
							Piwik_Archive::INDEX_GOAL_NB_VISITS_CONVERTED => 0,
							Piwik_Archive::INDEX_GOAL_REVENUE 			=> 0,
							Piwik_Archive::INDEX_GOAL_ECOMMERCE_REVENUE_SUBTOTAL => 0,
							Piwik_Archive::INDEX_GOAL_ECOMMERCE_REVENUE_TAX => 0,
							Piwik_Archive::INDEX_GOAL_ECOMMERCE_REVENUE_SHIPPING => 0,
							Piwik_Archive::INDEX_GOAL_ECOMMERCE_REVENUE_DISCOUNT => 0,
							Piwik_Archive::INDEX_GOAL_ECOMMERCE_ITEMS => 0,
			);
		}
		// $row['idgoal'] == Piwik_Tracker_GoalManager::IDGOAL_CART
		return array(	Piwik_Archive::INDEX_GOAL_NB_CONVERSIONS 	=> 0,
						Piwik_Archive::INDEX_GOAL_NB_VISITS_CONVERTED => 0,
						Piwik_Archive::INDEX_GOAL_REVENUE 			=> 0,
						Piwik_Archive::INDEX_GOAL_ECOMMERCE_ITEMS => 0,
		);
	}
}
