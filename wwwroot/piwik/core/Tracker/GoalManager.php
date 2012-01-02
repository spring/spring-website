<?php
/**
 * Piwik - Open source web analytics
 * 
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id: GoalManager.php 5266 2011-10-04 10:38:36Z matt $
 * 
 * @category Piwik
 * @package Piwik
 */

/**
 * @package Piwik
 * @subpackage Piwik_Tracker
 */
class Piwik_Tracker_GoalManager 
{
	// log_visit.visit_goal_buyer
	const TYPE_BUYER_NONE = 0;
	const TYPE_BUYER_ORDERED = 1;
	const TYPE_BUYER_OPEN_CART = 2;
	const TYPE_BUYER_ORDERED_AND_OPEN_CART = 3;

	// log_conversion.idorder is NULLable, but not log_conversion_item which defaults to zero for carts
	const ITEM_IDORDER_ABANDONED_CART = 0;
	
	// log_conversion.idgoal special values
	const IDGOAL_CART = -1;
	const IDGOAL_ORDER = 0;
	
	const REVENUE_PRECISION = 2;
	
	const MAXIMUM_PRODUCT_CATEGORIES = 5;
	public $idGoal;
	public $requestIsEcommerce;
	public $isGoalAnOrder;
	
	/**
	 * @var Piwik_Tracker_Action
	 */
	protected $action = null;
	protected $convertedGoals = array();
	protected $isThereExistingCartInVisit = false;
	protected $request;
	protected $orderId;
	
	function init($request)
	{
		$this->request = $request;
		$this->orderId = Piwik_Common::getRequestVar('ec_id', false, 'string', $this->request);
		$this->isGoalAnOrder = !empty($this->orderId);
		$this->idGoal = Piwik_Common::getRequestVar('idgoal', -1, 'int', $this->request);
		$this->requestIsEcommerce = ($this->idGoal == 0);
	}

	function getBuyerType($existingType = self::TYPE_BUYER_NONE)
	{
		// Was there a Cart for this visit prior to the order?
		$this->isThereExistingCartInVisit = in_array($existingType, 
											array(	Piwik_Tracker_GoalManager::TYPE_BUYER_OPEN_CART,
													Piwik_Tracker_GoalManager::TYPE_BUYER_ORDERED_AND_OPEN_CART));
		
		if(!$this->requestIsEcommerce)
		{
			return $existingType;
		}
		if($this->isGoalAnOrder)
		{
			return self::TYPE_BUYER_ORDERED;
		}
		// request is Add to Cart
		if($existingType == self::TYPE_BUYER_ORDERED
			|| $existingType == self::TYPE_BUYER_ORDERED_AND_OPEN_CART)
		{
			return self::TYPE_BUYER_ORDERED_AND_OPEN_CART;
		}
		return self::TYPE_BUYER_OPEN_CART;
	}
	
	static public function getGoalDefinitions( $idSite )
	{
		$websiteAttributes = Piwik_Common::getCacheWebsiteAttributes( $idSite );
		if(isset($websiteAttributes['goals']))
		{
			return $websiteAttributes['goals'];
		}
		return array();
	}

	static public function getGoalDefinition( $idSite, $idGoal )
	{
		$goals = self::getGoalDefinitions( $idSite );
		foreach($goals as $goal)
		{
			if($goal['idgoal'] == $idGoal)
			{
				return $goal;
			}
		}
		throw new Exception('Goal not found');
	}

	static public function getGoalIds( $idSite )
	{
		$goals = self::getGoalDefinitions( $idSite );
		$goalIds = array();
		foreach($goals as $goal)
		{
			$goalIds[] = $goal['idgoal'];
		}
		return $goalIds;
	}

	/**
	 * Look at the URL or Page Title and sees if it matches any existing Goal definition
	 * 
	 * @param int $idSite
	 * @param Piwik_Tracker_Action $action
	 * @return int Number of goals matched
	 */
	function detectGoalsMatchingUrl($idSite, $action)
	{
		if(!Piwik_Common::isGoalPluginEnabled())
		{
			return false;
		}

		$decodedActionUrl = $action->getActionUrl();
		$actionType = $action->getActionType();
		$goals = $this->getGoalDefinitions($idSite);
		foreach($goals as $goal)
		{
			$attribute = $goal['match_attribute'];
			// if the attribute to match is not the type of the current action
			if(		($actionType == Piwik_Tracker_Action::TYPE_ACTION_URL && $attribute != 'url' && $attribute != 'title')
				||	($actionType == Piwik_Tracker_Action::TYPE_DOWNLOAD && $attribute != 'file')
				||	($actionType == Piwik_Tracker_Action::TYPE_OUTLINK && $attribute != 'external_website')
				||	($attribute == 'manually')
				)
			{
				continue;
			}
			
			$url = $decodedActionUrl;
			// Matching on Page Title
			if($attribute == 'title')
			{
				$url = $action->getActionName();
			}
			$pattern_type = $goal['pattern_type'];

			switch($pattern_type)
			{
				case 'regex':
					$pattern = $goal['pattern'];
					if(strpos($pattern, '/') !== false 
						&& strpos($pattern, '\\/') === false)
					{
						$pattern = str_replace('/', '\\/', $pattern);
					}
					$pattern = '/' . $pattern . '/'; 
					if(!$goal['case_sensitive'])
					{
						$pattern .= 'i';
					}
					$match = (@preg_match($pattern, $url) == 1);
					break;
				case 'contains':
					if($goal['case_sensitive'])
					{
						$matched = strpos($url, $goal['pattern']);
					}
					else
					{
						$matched = stripos($url, $goal['pattern']);
					}
					$match = ($matched !== false);
					break;
				case 'exact':
					if($goal['case_sensitive'])
					{
						$matched = strcmp($goal['pattern'], $url);
					}
					else
					{
						$matched = strcasecmp($goal['pattern'], $url);
					}
					$match = ($matched == 0);
					break;
				default:
					throw new Exception(Piwik_TranslateException('General_ExceptionInvalidGoalPattern', array($pattern_type)));
					break;
			}
			if($match)
			{
				$goal['url'] = $decodedActionUrl;
				$this->convertedGoals[] = $goal;
			}
		}
//		var_dump($this->convertedGoals);exit;
		return count($this->convertedGoals) > 0;
	}

	function detectGoalId($idSite)
	{
		if(!Piwik_Common::isGoalPluginEnabled())
		{
			return false;
		}
		$goals = $this->getGoalDefinitions($idSite);
		if(!isset($goals[$this->idGoal]))
		{
			return false;
		}
		$goal = $goals[$this->idGoal];
		
		$url = Piwik_Common::getRequestVar( 'url', '', 'string', $this->request);
		$goal['url'] = Piwik_Tracker_Action::excludeQueryParametersFromUrl($url, $idSite);
		$goal['revenue'] = $this->getRevenue(Piwik_Common::getRequestVar('revenue', $goal['revenue'], 'float', $this->request));
		$this->convertedGoals[] = $goal;
		return true;
	}

	/**
	 * Records one or several goals matched in this request.
	 */
	public function recordGoals($idSite, $visitorInformation, $visitCustomVariables, $action, $referrerTimestamp, $referrerUrl, $referrerCampaignName, $referrerCampaignKeyword)
	{
		$location_country = isset($visitorInformation['location_country']) 
							? $visitorInformation['location_country'] 
							: Piwik_Common::getCountry( 
										Piwik_Common::getBrowserLanguage(), 
										$enableLanguageToCountryGuess = Piwik_Tracker_Config::getInstance()->Tracker['enable_language_to_country_guess'], $visitorInformation['location_ip'] 
							);
							
		$location_continent = isset($visitorInformation['location_continent']) 
								? $visitorInformation['location_continent'] 
								: Piwik_Common::getContinent($location_country);

		$goal = array(
			'idvisit' 			=> $visitorInformation['idvisit'],
			'idsite' 			=> $idSite,
			'idvisitor' 		=> $visitorInformation['idvisitor'],
			'server_time' 		=> Piwik_Tracker::getDatetimeFromTimestamp($visitorInformation['visit_last_action_time']),
			'location_country'  => $location_country,
			'location_continent'=> $location_continent,
			'visitor_returning' => $visitorInformation['visitor_returning'],
			'visitor_days_since_first' => $visitorInformation['visitor_days_since_first'],
			'visitor_days_since_order' => $visitorInformation['visitor_days_since_order'],
			'visitor_count_visits' => $visitorInformation['visitor_count_visits'],
		);

		// Copy Custom Variables from Visit row to the Goal conversion
		for($i=1; $i<=Piwik_Tracker::MAX_CUSTOM_VARIABLES; $i++) 
		{
			if(!empty($visitorInformation['custom_var_k'.$i]))
			{
				$goal['custom_var_k'.$i] = $visitorInformation['custom_var_k'.$i];
			}
			if(!empty($visitorInformation['custom_var_v'.$i]))
			{
				$goal['custom_var_v'.$i] = $visitorInformation['custom_var_v'.$i];
			}
		}
		// Otherwise, set the Custom Variables found in the cookie sent with this request
		$goal += $visitCustomVariables;
			
		// Attributing the correct Referrer to this conversion. 
		// Priority order is as follows:
		// 1) Campaign name/kwd parsed in the JS
		// 2) Referrer URL stored in the _ref cookie
		// 3) If no info from the cookie, attribute to the current visit referrer
		
		// 3) Default values: current referrer
        $type = $visitorInformation['referer_type'];
        $name = $visitorInformation['referer_name'];
        $keyword = $visitorInformation['referer_keyword'];
        $time = $visitorInformation['visit_first_action_time'];
        
        // 1) Campaigns from 1st party cookie
		if(!empty($referrerCampaignName))
		{
			$type = Piwik_Common::REFERER_TYPE_CAMPAIGN;
			$name = $referrerCampaignName;
			$keyword = $referrerCampaignKeyword;
			$time = $referrerTimestamp;
		}
		// 2) Referrer URL parsing
		elseif(!empty($referrerUrl))
		{
			$referrer = new Piwik_Tracker_Visit_Referer();  
            $referrer = $referrer->getRefererInformation($referrerUrl, $currentUrl = '', $idSite);
            
            // if the parsed referer is interesting enough, ie. website or search engine 
            if(in_array($referrer['referer_type'], array(Piwik_Common::REFERER_TYPE_SEARCH_ENGINE, Piwik_Common::REFERER_TYPE_WEBSITE)))
            {
            	$type = $referrer['referer_type'];
            	$name = $referrer['referer_name'];
            	$keyword = $referrer['referer_keyword'];
				$time = $referrerTimestamp;
            }
		}
		$goal += array(
			'referer_type' 				=> $type,
			'referer_name' 				=> $name,
			'referer_keyword' 			=> $keyword,
			// this field is currently unused
			'referer_visit_server_date' => date("Y-m-d", $time),
		);
		
		// some goals are converted, so must be ecommerce Order or Cart Update 
		if($this->requestIsEcommerce)
		{
			$this->recordEcommerceGoal($goal, $visitorInformation);
		}
		else
		{
			$this->recordStandardGoals($goal, $action, $visitorInformation);
		}
	}
	
	/**
	 * Returns rounded decimal revenue, or if revenue is integer, then returns as is.
	 * 
	 * @param int|float $revenue
	 * @return int|float
	 */
	protected function getRevenue($revenue)
	{
		if(round($revenue) == $revenue)
		{
			return $revenue;
		}
		return round($revenue, self::REVENUE_PRECISION);
	}
	
	/**
	 * Records an Ecommerce conversion in the DB. Deals with Items found in the request.
	 * Will deal with 2 types of conversions: Ecommerce Order and Ecommerce Cart update (Add to cart, Update Cart etc).
	 * 
	 * @param array $goal
	 * @param array $visitorInformation
	 */
	protected function recordEcommerceGoal($goal, $visitorInformation)
	{
		// Is the transaction a Cart Update or an Ecommerce order?
		$updateWhere = array(
			'idvisit' => $visitorInformation['idvisit'],
			'idgoal' => self::IDGOAL_CART,
			'buster' => 0,
		);
		
		if($this->isThereExistingCartInVisit)
		{
			printDebug("There is an existing cart for this visit");
		}
		if($this->isGoalAnOrder)
		{
			$orderIdNumeric = Piwik_Common::hashStringToInt($this->orderId);
			$goal['idgoal'] = self::IDGOAL_ORDER;
			$goal['idorder'] = $this->orderId; 
			$goal['buster'] = $orderIdNumeric;
			$goal['revenue_subtotal'] = $this->getRevenue(Piwik_Common::getRequestVar('ec_st', false, 'float', $this->request));
			$goal['revenue_tax'] = $this->getRevenue(Piwik_Common::getRequestVar('ec_tx', false, 'float', $this->request));
			$goal['revenue_shipping'] = $this->getRevenue(Piwik_Common::getRequestVar('ec_sh', false, 'float', $this->request));
			$goal['revenue_discount'] = $this->getRevenue(Piwik_Common::getRequestVar('ec_dt', false, 'float', $this->request));
			
			$debugMessage = 'The conversion is an Ecommerce order';
		}
		// If Cart update, select current items in the previous Cart
		else
		{
			$goal['buster'] = 0;
			$goal['idgoal'] = self::IDGOAL_CART;
			$debugMessage = 'The conversion is an Ecommerce Cart Update';
		}
		$goal['revenue'] = $this->getRevenue(Piwik_Common::getRequestVar('revenue', 0, 'float', $this->request)); 
		
		printDebug($debugMessage . ':' . var_export($goal, true));

		// INSERT or Sync items in the Cart / Order for this visit & order
		$items = $this->getEcommerceItemsFromRequest();
		if($items === false)
		{
			return;
		}
		
		$itemsCount = 0;
		foreach($items as $item)
		{
			$itemsCount += $item[self::INTERNAL_ITEM_QUANTITY];
		}
		$goal['items'] = $itemsCount;
		
		// If there is already a cart for this visit
		// 1) If conversion is Order, we update the cart into an Order
		// 2) If conversion is Cart Update, we update the cart
		$recorded = $this->recordGoal($goal, $this->isThereExistingCartInVisit, $updateWhere);
		if($recorded)
		{
			$this->recordEcommerceItems($goal, $items);
		}
	}
	
	/**
	 * Returns Items read from the request string
	 * @return array|false
	 */
	protected function getEcommerceItemsFromRequest()
	{
		$items = Piwik_Common::unsanitizeInputValue(Piwik_Common::getRequestVar('ec_items', '', 'string', $this->request));
		if(empty($items))
		{
			printDebug("There are no Ecommerce items in the request");
			// we still record an Ecommerce order without any item in it
			return array();
		}
		$items = json_decode($items, $assoc = true);
		if(!is_array($items))
		{
			printDebug("Error while json_decode the Ecommerce items = ".var_export($items, true));
			return false;
		}
		
		$cleanedItems = $this->getCleanedEcommerceItems($items);
		return $cleanedItems;
	}
	
	/**
	 * Loads the Ecommerce items from the request and records them in the DB
	 * 
	 * @param array $goal
	 * @return int $items Number of items in the cart
	 */
	protected function recordEcommerceItems($goal, $items)
	{
		$itemInCartBySku = array();
		foreach($items as $item)
		{
			$itemInCartBySku[$item[0]] = $item;
		}
//		var_dump($items); echo "Items by SKU:";var_dump($itemInCartBySku);

		// Select all items currently in the Cart if any
		$sql = "SELECT idaction_sku, idaction_name, idaction_category, idaction_category2, idaction_category3, idaction_category4, idaction_category5, price, quantity, deleted, idorder as idorder_original_value 
				FROM ". Piwik_Common::prefixTable('log_conversion_item') . "
				WHERE idvisit = ?
					AND (idorder = ? OR idorder = ?)";
		
		$bind = array(	$goal['idvisit'], 
						isset($goal['idorder']) ? $goal['idorder'] : self::ITEM_IDORDER_ABANDONED_CART,
						self::ITEM_IDORDER_ABANDONED_CART
		);
		
		$itemsInDb = Piwik_Tracker::getDatabase()->fetchAll($sql, $bind);
		
		printDebug("Items found in current cart, for conversion_item (visit,idorder)=" . var_export($bind,true));
		printDebug($itemsInDb);
		// Look at which items need to be deleted, which need to be added or updated, based on the SKU
		$skuFoundInDb = $itemsToUpdate = array();
		foreach($itemsInDb as $itemInDb)
		{
			$skuFoundInDb[] = $itemInDb['idaction_sku'];
			
			// Ensure price comparisons will have the same assumption
			$itemInDb['price'] = $this->getRevenue($itemInDb['price']);
			$itemInDbOriginal = $itemInDb;
			$itemInDb = array_values($itemInDb);
			
			// Cast all as string, because what comes out of the fetchAll() are strings
			$itemInDb = $this->getItemRowCast($itemInDb);
		
			//Item in the cart in the DB, but not anymore in the cart
			if(!isset($itemInCartBySku[$itemInDb[0]]))
			{
				$itemToUpdate = array_merge($itemInDb,
						array(	'deleted' => 1, 
								'idorder_original_value' => $itemInDbOriginal['idorder_original_value']
						)
				);
				
				$itemsToUpdate[] = $itemToUpdate;
				printDebug("Item found in the previous Cart, but no in the current cart/order");
				printDebug($itemToUpdate);
				continue;
			}
			
			$newItem = $itemInCartBySku[$itemInDb[0]];
			$newItem = $this->getItemRowCast($newItem);
			
			if(count($itemInDb) != count($newItem))
			{
				printDebug("ERROR: Different format in items from cart and DB");
				throw new Exception(" Item in DB and Item in cart have a different format, this is not expected... ".var_export($itemInDb, true) . var_export($newItem, true));
			}
			printDebug("Item has changed since the last cart. Previous item stored in cart in database:");
			printDebug($itemInDb);
			printDebug("New item to UPDATE the previous row:");
			$newItem['idorder_original_value'] = $itemInDbOriginal['idorder_original_value'];
			printDebug($newItem);
			$itemsToUpdate[] = $newItem;
		}
		
		// Items to UPDATE
		//var_dump($itemsToUpdate);
		$this->updateEcommerceItems($goal, $itemsToUpdate);
		
		// Items to INSERT
		$itemsToInsert = array();
		foreach($items as $item)
		{
			if(!in_array($item[0], $skuFoundInDb))
			{
				$itemsToInsert[] = $item;
			}
		}
		$this->insertEcommerceItems($goal, $itemsToInsert);
	}
	
	// In the GET items parameter, each item has the following array of information 
	const INDEX_ITEM_SKU = 0;
	const INDEX_ITEM_NAME = 1;
	const INDEX_ITEM_CATEGORY = 2;
	const INDEX_ITEM_PRICE = 3;
	const INDEX_ITEM_QUANTITY = 4;
	
	// Used in the array of items, internally to this class
	const INTERNAL_ITEM_SKU = 0;
	const INTERNAL_ITEM_NAME = 1;
	const INTERNAL_ITEM_CATEGORY = 2;
	const INTERNAL_ITEM_CATEGORY2 = 3;
	const INTERNAL_ITEM_CATEGORY3 = 4;
	const INTERNAL_ITEM_CATEGORY4 = 5;
	const INTERNAL_ITEM_CATEGORY5 = 6;
	const INTERNAL_ITEM_PRICE = 7;
	const INTERNAL_ITEM_QUANTITY = 8;
	
	/**
	 * Reads items from the request, then looks up the names from the lookup table 
	 * and returns a clean array of items ready for the database.
	 * 
	 * @param array $items
	 * @return array $cleanedItems
	 */
	protected function getCleanedEcommerceItems($items)
	{
		// Clean up the items array
		$cleanedItems = array();
		foreach($items as $item)
		{
			$name = $category = $category2 = $category3 = $category4 = $category5 = false;
			$price = 0;
			$quantity = 1;
			// items are passed in the request as an array: ( $sku, $name, $category, $price, $quantity )
			if(empty($item[self::INDEX_ITEM_SKU])) { 
				continue; 
			}
			
			$sku = $item[self::INDEX_ITEM_SKU];
			if(!empty($item[self::INDEX_ITEM_NAME])) {
				$name = $item[self::INDEX_ITEM_NAME];
			}
			
			if(!empty($item[self::INDEX_ITEM_CATEGORY])) {
				$category = $item[self::INDEX_ITEM_CATEGORY];
			} 	
			
			if(!empty($item[self::INDEX_ITEM_PRICE]) 
				&& is_numeric($item[self::INDEX_ITEM_PRICE])) { 
					$price = $this->getRevenue($item[self::INDEX_ITEM_PRICE]); 
			}
			if(!empty($item[self::INDEX_ITEM_QUANTITY]) 
				&& is_numeric($item[self::INDEX_ITEM_QUANTITY])) { 
					$quantity = (int)$item[self::INDEX_ITEM_QUANTITY];
			}
			
			// self::INDEX_ITEM_* are in order
			$cleanedItems[] = array( 
				self::INTERNAL_ITEM_SKU => $sku, 
				self::INTERNAL_ITEM_NAME => $name, 
				self::INTERNAL_ITEM_CATEGORY => $category, 
				self::INTERNAL_ITEM_CATEGORY2 => $category2, 
				self::INTERNAL_ITEM_CATEGORY3 => $category3, 
				self::INTERNAL_ITEM_CATEGORY4 => $category4, 
				self::INTERNAL_ITEM_CATEGORY5 => $category5, 
				self::INTERNAL_ITEM_PRICE => $price, 
				self::INTERNAL_ITEM_QUANTITY => $quantity 
			);
		}
		
		// Lookup Item SKUs, Names & Categories Ids
		$actionsToLookupAllItems = array();
		
		// Each item has 7 potential "ids" to lookup in the lookup table
		$columnsInEachRow = 1 + 1 + self::MAXIMUM_PRODUCT_CATEGORIES;
		
		foreach($cleanedItems as $item)
		{
			$actionsToLookup = array();
			list($sku, $name, $category, $price, $quantity) = $item;
			$actionsToLookup[] = array(trim($sku), Piwik_Tracker_Action::TYPE_ECOMMERCE_ITEM_SKU);
			$actionsToLookup[] = array(trim($name), Piwik_Tracker_Action::TYPE_ECOMMERCE_ITEM_NAME);

			// Only one category
			if(!is_array($category))
			{
				$actionsToLookup[] = array(trim($category), Piwik_Tracker_Action::TYPE_ECOMMERCE_ITEM_CATEGORY);
			}
			// Multiple categories
			else
			{ 
				$countCategories = 0;
				foreach($category as $productCategory) {
					$productCategory = trim($productCategory);
					if(empty($productCategory)) {
						continue;
					}
					$countCategories++;
					if($countCategories > self::MAXIMUM_PRODUCT_CATEGORIES) {
						break;
					}
					$actionsToLookup[] = array($productCategory, Piwik_Tracker_Action::TYPE_ECOMMERCE_ITEM_CATEGORY);
				}
			}
			// Ensure that each row has the same number of columns, fill in the blanks
			for($i = count($actionsToLookup); $i < $columnsInEachRow; $i++) {
				$actionsToLookup[] = array(false, Piwik_Tracker_Action::TYPE_ECOMMERCE_ITEM_CATEGORY);
			}
			$actionsToLookupAllItems = array_merge($actionsToLookupAllItems, $actionsToLookup);
		}
		
		$actionsLookedUp = Piwik_Tracker_Action::loadActionId($actionsToLookupAllItems);
//		var_dump($actionsLookedUp);

		
		// Replace SKU, name & category by their ID action
		foreach($cleanedItems as $index => &$item)
		{
			list($sku, $name, $category, $price, $quantity) = $item;
			
			// SKU
			$item[0] = $actionsLookedUp[ $index * $columnsInEachRow + 0][2];
			// Name
			$item[1] = $actionsLookedUp[ $index * $columnsInEachRow + 1][2];
			// Categories
			$item[2] = $actionsLookedUp[ $index * $columnsInEachRow + 2][2];
			$item[3] = $actionsLookedUp[ $index * $columnsInEachRow + 3][2];
			$item[4] = $actionsLookedUp[ $index * $columnsInEachRow + 4][2];
			$item[5] = $actionsLookedUp[ $index * $columnsInEachRow + 5][2];
			$item[6] = $actionsLookedUp[ $index * $columnsInEachRow + 6][2];
		}
		return $cleanedItems;
	}
	
	/**
	 * Updates the cart items in the DB 
	 * that have been modified since the last cart update
	 */
	protected function updateEcommerceItems($goal, $itemsToUpdate)
	{
		if(empty($itemsToUpdate))
		{
			return;
		}
		printDebug("Goal data used to update ecommerce items:");
		printDebug($goal);
		
		foreach($itemsToUpdate as $item)
		{
			$newRow = $this->getItemRowEnriched($goal, $item);
			printDebug($newRow);
			$updateParts = $sqlBind = array();
			foreach($newRow AS $name => $value)
			{
				$updateParts[] = $name." = ?";
				$sqlBind[] = $value;
			}
			$sql = 'UPDATE ' . Piwik_Common::prefixTable('log_conversion_item') . "	
					SET ".implode($updateParts, ', ')."
						WHERE idvisit = ?
							AND idorder = ? 
							AND idaction_sku = ?";
			$sqlBind[] = $newRow['idvisit'];
			$sqlBind[] = $item['idorder_original_value'];
			$sqlBind[] = $newRow['idaction_sku'];
			Piwik_Tracker::getDatabase()->query($sql, $sqlBind);
		}
	}
	
	/**
	 * Inserts in the cart in the DB the new items 
	 * that were not previously in the cart
	 */
	protected function insertEcommerceItems($goal, $itemsToInsert)
	{
		if(empty($itemsToInsert))
		{
			return;
		}
		printDebug("Ecommerce items that are added to the cart/order");
		printDebug($itemsToInsert);
		
		$sql = "INSERT INTO " . Piwik_Common::prefixTable('log_conversion_item') . "
					(idaction_sku, idaction_name, idaction_category, idaction_category2, idaction_category3, idaction_category4, idaction_category5, price, quantity, deleted, 
					idorder, idsite, idvisitor, server_time, idvisit) 
					VALUES ";
		$i = 0;
		$bind = array();
		foreach($itemsToInsert as $item)
		{
			if($i > 0) { $sql .= ','; }
			$newRow = array_values($this->getItemRowEnriched($goal, $item));
			$sql .= " ( ". Piwik_Common::getSqlStringFieldsArray($newRow) . " ) ";
			$i++;
			$bind = array_merge($bind, $newRow);
		}
		Piwik_Tracker::getDatabase()->query($sql, $bind);
		printDebug($sql);printDebug($bind);
	}
	
	protected function getItemRowEnriched($goal, $item)
	{
		$newRow = array(
			'idaction_sku' => (int)$item[self::INTERNAL_ITEM_SKU],
			'idaction_name' => (int)$item[self::INTERNAL_ITEM_NAME],
			'idaction_category' => (int)$item[self::INTERNAL_ITEM_CATEGORY],
			'idaction_category2' => (int)$item[self::INTERNAL_ITEM_CATEGORY2],
			'idaction_category3' => (int)$item[self::INTERNAL_ITEM_CATEGORY3],
			'idaction_category4' => (int)$item[self::INTERNAL_ITEM_CATEGORY4],
			'idaction_category5' => (int)$item[self::INTERNAL_ITEM_CATEGORY5],
			'price' => $item[self::INTERNAL_ITEM_PRICE],
			'quantity' => $item[self::INTERNAL_ITEM_QUANTITY],
			'deleted' => isset($item['deleted']) ? $item['deleted'] : 0, //deleted
			'idorder' => isset($goal['idorder']) ? $goal['idorder'] : self::ITEM_IDORDER_ABANDONED_CART, //idorder = 0 in log_conversion_item for carts
			'idsite' => $goal['idsite'],
			'idvisitor' => $goal['idvisitor'],
			'server_time' => $goal['server_time'],
			'idvisit' => $goal['idvisit']
		);
		return $newRow;
	}
	/**
	 * Records a standard non-Ecommerce goal in the DB (URL/Title matching), 
	 * linking the conversion to the action that triggered it
	 */
	protected function recordStandardGoals($goal, $action, $visitorInformation)
	{
		foreach($this->convertedGoals as $convertedGoal)
		{
			printDebug("- Goal ".$convertedGoal['idgoal'] ." matched. Recording...");
			$newGoal = $goal;
			$newGoal['idgoal'] = $convertedGoal['idgoal'];
			$newGoal['url'] = $convertedGoal['url'];
			$newGoal['revenue'] = $this->getRevenue($convertedGoal['revenue']);
			
			if(!is_null($action))
			{
				$newGoal['idaction_url'] = (int)$action->getIdActionUrl();
				$newGoal['idlink_va'] = $action->getIdLinkVisitAction();
			}

			// If multiple Goal conversions per visit, set a cache buster 
			$newGoal['buster'] = $convertedGoal['allow_multiple'] == 0 
										? '0' 
										: $visitorInformation['visit_last_action_time'];
										
			$this->recordGoal($newGoal);
		}
	}
	/**
	 * Helper function used by other record* methods which will INSERT or UPDATE the conversion in the DB
	 * 
	 * @param array $newGoal
	 * @param bool $mustUpdateNotInsert If set to true, the previous conversion will be UPDATEd. This is used for the Cart Update conversion (only one cart per visit)
	 * @param array $updateWhere
	 */
	protected function recordGoal($newGoal, $mustUpdateNotInsert = false, $updateWhere = array())
	{
		$newGoalDebug = $newGoal;
		$newGoalDebug['idvisitor'] = bin2hex($newGoalDebug['idvisitor']);
		printDebug($newGoalDebug);

		$fields = implode(", ", array_keys($newGoal));
		$bindFields = Piwik_Common::getSqlStringFieldsArray($newGoal);
		
		if($mustUpdateNotInsert)
		{
			$updateParts = $sqlBind = $updateWhereParts = array();
			foreach($newGoal AS $name => $value)
			{
				$updateParts[] = $name." = ?";
				$sqlBind[] = $value;
			}
			foreach($updateWhere as $name => $value)
			{
				$updateWhereParts[] = $name." = ?";
				$sqlBind[] = $value;
			}
			$sql = 'UPDATE  ' . Piwik_Common::prefixTable('log_conversion') . "	
					SET ".implode($updateParts, ', ')."
						WHERE ".implode($updateWhereParts, ' AND ');
			Piwik_Tracker::getDatabase()->query($sql, $sqlBind);
			return true;
		}
		else
		{
			$sql = 'INSERT IGNORE INTO ' . Piwik_Common::prefixTable('log_conversion') . "	
					($fields) VALUES ($bindFields) ";
			$bind = array_values($newGoal);
			$result = Piwik_Tracker::getDatabase()->query($sql, $bind);
			
			// If a record was inserted, we return true
			return Piwik_Tracker::getDatabase()->rowCount($result) > 0;
		}
	}
	
	/**
	 * Casts the item array so that array comparisons work nicely
	 */
	protected function getItemRowCast($row)
	{			
		return array(
				(string)(int)$row[self::INTERNAL_ITEM_SKU],
				(string)(int)$row[self::INTERNAL_ITEM_NAME],
				(string)(int)$row[self::INTERNAL_ITEM_CATEGORY],
				(string)(int)$row[self::INTERNAL_ITEM_CATEGORY2],
				(string)(int)$row[self::INTERNAL_ITEM_CATEGORY3],
				(string)(int)$row[self::INTERNAL_ITEM_CATEGORY4],
				(string)(int)$row[self::INTERNAL_ITEM_CATEGORY5],
				(string)$row[self::INTERNAL_ITEM_PRICE],
				(string)$row[self::INTERNAL_ITEM_QUANTITY],
		);
	}
}
