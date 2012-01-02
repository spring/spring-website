<?php
/**
 * Piwik - Open source web analytics
 * 
 * Client to record visits, page views, Goals, Ecommerce activity (product views, add to carts, Ecommerce orders) in a Piwik server.
 * This is a PHP Version of the piwik.js standard Tracking API.
 * For more information, see http://piwik.org/docs/tracking-api/
 * 
 * This class requires: 
 *  - json extension (json_decode, json_encode) 
 *  - CURL or STREAM extensions (to issue the http request to Piwik)
 *  
 * @license released under BSD License http://www.opensource.org/licenses/bsd-license.php
 * @version $Id: PiwikTracker.php 5203 2011-09-22 09:22:02Z matt $
 * @link http://piwik.org/docs/tracking-api/
 *
 * @category Piwik
 * @package PiwikTracker
 */

/**
 * @package PiwikTracker
 */
class PiwikTracker
{
	/**
	 * Piwik base URL, for example http://example.org/piwik/
	 * Must be set before using the class by calling 
	 *  PiwikTracker::$URL = 'http://yourwebsite.org/piwik/';
	 * 
	 * @var string
	 */
	static public $URL = '';
	
	/**
	 * API Version
	 * 
	 * @ignore
	 * @var int
	 */
	const VERSION = 1;
	
	/**
	 * @ignore
	 */
	public $DEBUG_APPEND_URL = '';
	
	/**
	 * Visitor ID length
	 * 
	 * @ignore
	 */
	const LENGTH_VISITOR_ID = 16;
	
	/**
	 * Builds a PiwikTracker object, used to track visits, pages and Goal conversions 
	 * for a specific website, by using the Piwik Tracking API.
	 * 
	 * @param int $idSite Id site to be tracked
	 * @param string $apiUrl "http://example.org/piwik/" or "http://piwik.example.org/"
	 * 						 If set, will overwrite PiwikTracker::$URL
	 */
    function __construct( $idSite, $apiUrl = false )
    {
    	$this->cookieSupport = true;
    	$this->userAgent = false;
    	$this->localHour = false;
    	$this->localMinute = false;
    	$this->localSecond = false;
    	$this->hasCookies = false;
    	$this->plugins = false;
    	$this->visitorCustomVar = false;
    	$this->pageCustomVar = false;
    	$this->customData = false;
    	$this->forcedDatetime = false;
    	$this->token_auth = false;
    	$this->attributionInfo = false;
    	$this->ecommerceLastOrderTimestamp = false;
    	$this->ecommerceItems = array();

    	$this->requestCookie = '';
    	$this->idSite = $idSite;
    	$this->urlReferrer = @$_SERVER['HTTP_REFERER'];
    	$this->pageUrl = self::getCurrentUrl();
    	$this->ip = @$_SERVER['REMOTE_ADDR'];
    	$this->acceptLanguage = @$_SERVER['HTTP_ACCEPT_LANGUAGE'];
    	$this->userAgent = @$_SERVER['HTTP_USER_AGENT'];
    	if(!empty($apiUrl)) {
    		self::$URL = $apiUrl;
    	}
    	$this->visitorId = substr(md5(uniqid(rand(), true)), 0, self::LENGTH_VISITOR_ID);
    }
    
    /**
     * Sets the current URL being tracked
     * 
     * @param string Raw URL (not URL encoded)
     */
	public function setUrl( $url )
    {
    	$this->pageUrl = $url;
    }

    /**
     * Sets the URL referrer used to track Referrers details for new visits.
     * 
     * @param string Raw URL (not URL encoded)
     */
    public function setUrlReferrer( $url )
    {
    	$this->urlReferrer = $url;
    }
    
    /**
     * @deprecated 
     * @ignore
     */
    public function setUrlReferer( $url )
    {
    	$this->setUrlReferrer($url);
    }
    
    /**
     * Sets the attribution information to the visit, so that subsequent Goal conversions are 
     * properly attributed to the right Referrer URL, timestamp, Campaign Name & Keyword.
     * 
     * This must be a JSON encoded string that would typically be fetched from the JS API: 
     * piwikTracker.getAttributionInfo() and that you have JSON encoded via JSON2.stringify() 
     * 
     * @param string $jsonEncoded JSON encoded array containing Attribution info
     * @see function getAttributionInfo() in http://dev.piwik.org/trac/browser/trunk/js/piwik.js 
     */
    public function setAttributionInfo( $jsonEncoded )
    {
    	$decoded = json_decode($jsonEncoded, $assoc = true);
    	if(!is_array($decoded)) 
    	{
    		throw new Exception("setAttributionInfo() is expecting a JSON encoded string, $jsonEncoded given");
    	}
    	$this->attributionInfo = $decoded;
    }

    /**
     * Sets Visit Custom Variable.
     * See http://piwik.org/docs/custom-variables/
     * 
     * @param int Custom variable slot ID from 1-5
     * @param string Custom variable name
     * @param string Custom variable value
     * @param string Custom variable scope. Possible values: visit, page
     */
    public function setCustomVariable($id, $name, $value, $scope = 'visit')
    {
    	if(!is_int($id))
    	{
    		throw new Exception("Parameter id to setCustomVariable should be an integer");
    	}
    	if($scope == 'page')
    	{
    		$this->pageCustomVar[$id] = array($name, $value);
    	}
    	elseif($scope == 'visit')
    	{
    		$this->visitorCustomVar[$id] = array($name, $value);
    	}
    	else
    	{
    		throw new Exception("Invalid 'scope' parameter value");
    	}
    }
    
    /**
     * Returns the currently assigned Custom Variable stored in a first party cookie.
     * 
     * This function will only work if the user is initiating the current request, and his cookies
     * can be read by PHP from the $_COOKIE array.
     * 
     * @param int Custom Variable integer index to fetch from cookie. Should be a value from 1 to 5
     * @param string Custom variable scope. Possible values: visit, page
     * 
     * @return array|false An array with this format: array( 0 => CustomVariableName, 1 => CustomVariableValue )
     * @see Piwik.js getCustomVariable()
     */
    public function getCustomVariable($id, $scope = 'visit')
    {
    	if($scope == 'page')
    	{
    		return isset($this->pageCustomVar[$id]) ? $this->pageCustomVar[$id] : false;
    	}
    	else if($scope != 'visit')
    	{
    		throw new Exception("Invalid 'scope' parameter value");
    	}
    	if(!empty($this->visitorCustomVar[$id]))
    	{
    		return $this->visitorCustomVar[$id];
    	}
    	$customVariablesCookie = 'cvar.'.$this->idSite.'.';
    	$cookie = $this->getCookieMatchingName($customVariablesCookie);
    	if(!$cookie)
    	{
    		return false;
    	}
    	if(!is_int($id))
    	{
    		throw new Exception("Parameter to getCustomVariable should be an integer");
    	}
    	$cookieDecoded = json_decode($cookie, $assoc = true);
    	if(!is_array($cookieDecoded)
    		|| !isset($cookieDecoded[$id])
    		|| !is_array($cookieDecoded[$id])
    		|| count($cookieDecoded[$id]) != 2)
    	{
    		return false;
    	}
    	return $cookieDecoded[$id];
    }
    
    
    
    
    /**
     * Sets the Browser language. Used to guess visitor countries when GeoIP is not enabled
     * 
     * @param string For example "fr-fr"
     */
    public function setBrowserLanguage( $acceptLanguage )
    {
    	$this->acceptLanguage = $acceptLanguage;
    }

    /**
     * Sets the user agent, used to detect OS and browser.
     * If this function is not called, the User Agent will default to the current user agent.
     *  
     * @param string $userAgent
     */
    public function setUserAgent($userAgent)
    {
    	$this->userAgent = $userAgent;
    }
    

    /**
     * Tracks a page view
     * 
     * @param string $documentTitle Page title as it will appear in the Actions > Page titles report
     * @return string Response
     */
    public function doTrackPageView( $documentTitle )
    {
    	$url = $this->getUrlTrackPageView($documentTitle);
    	return $this->sendRequest($url);
    } 
    
    /**
     * Records a Goal conversion
     * 
     * @param int $idGoal Id Goal to record a conversion
     * @param int $revenue Revenue for this conversion
     * @return string Response
     */
    public function doTrackGoal($idGoal, $revenue = false)
    {
    	$url = $this->getUrlTrackGoal($idGoal, $revenue);
    	return $this->sendRequest($url);
    }
    
    /**
     * Tracks a download or outlink
     * 
     * @param string $actionUrl URL of the download or outlink
     * @param string $actionType Type of the action: 'download' or 'link'
     * @return string Response
     */
    public function doTrackAction($actionUrl, $actionType)
    {
        // Referrer could be udpated to be the current URL temporarily (to mimic JS behavior)
    	$url = $this->getUrlTrackAction($actionUrl, $actionType);
    	return $this->sendRequest($url); 
    }

    /**
     * Adds an item in the Ecommerce order.
     * 
     * This should be called before doTrackEcommerceOrder(), or before doTrackEcommerceCartUpdate().
     * This function can be called for all individual products in the cart (or order).
     * SKU parameter is mandatory. Other parameters are optional (set to false if value not known).
     * Ecommerce items added via this function are automatically cleared when doTrackEcommerceOrder() or getUrlTrackEcommerceOrder() is called.
     * 
     * @param string $sku (required) SKU, Product identifier 
     * @param string $name (optional) Product name
     * @param string|array $category (optional) Product category, or array of product categories (up to 5 categories can be specified for a given product)
     * @param float|int $price (optional) Individual product price (supports integer and decimal prices)
     * @param int $quantity (optional) Product quantity. If not specified, will default to 1 in the Reports 
     */
    public function addEcommerceItem($sku, $name = false, $category = false, $price = false, $quantity = false)
    {
    	if(empty($sku))
    	{
    		throw new Exception("You must specify a SKU for the Ecommerce item");
    	}
    	$this->ecommerceItems[$sku] = array( $sku, $name, $category, $price, $quantity );
    }
    
    /**
	 * Tracks a Cart Update (add item, remove item, update item).
	 * 
	 * On every Cart update, you must call addEcommerceItem() for each item (product) in the cart, 
	 * including the items that haven't been updated since the last cart update.
	 * Items which were in the previous cart and are not sent in later Cart updates will be deleted from the cart (in the database).
	 * 
	 * @param float $grandTotal Cart grandTotal (typically the sum of all items' prices)
	 */ 
    public function doTrackEcommerceCartUpdate($grandTotal)
    {
    	$url = $this->getUrlTrackEcommerceCartUpdate($grandTotal);
    	return $this->sendRequest($url); 
    }
    
    /**
	 * Tracks an Ecommerce order.
	 * 
	 * If the Ecommerce order contains items (products), you must call first the addEcommerceItem() for each item in the order.
	 * All revenues (grandTotal, subTotal, tax, shipping, discount) will be individually summed and reported in Piwik reports.
	 * Only the parameters $orderId and $grandTotal are required. 
	 * 
	 * @param string|int $orderId (required) Unique Order ID. 
	 * 				This will be used to count this order only once in the event the order page is reloaded several times.
	 * 				orderId must be unique for each transaction, even on different days, or the transaction will not be recorded by Piwik.
	 * @param float $grandTotal (required) Grand Total revenue of the transaction (including tax, shipping, etc.)
	 * @param float $subTotal (optional) Sub total amount, typically the sum of items prices for all items in this order (before Tax and Shipping costs are applied) 
	 * @param float $tax (optional) Tax amount for this order
	 * @param float $shipping (optional) Shipping amount for this order
	 * @param float $discount (optional) Discounted amount in this order
     */
    public function doTrackEcommerceOrder($orderId, $grandTotal, $subTotal = false, $tax = false, $shipping = false, $discount = false)
    {
    	$url = $this->getUrlTrackEcommerceOrder($orderId, $grandTotal, $subTotal, $tax, $shipping, $discount);
    	return $this->sendRequest($url); 
    }
    
    /**
     * Sets the current page view as an item (product) page view, or an Ecommerce Category page view.
     * 
     * This must be called before doTrackPageView() on this product/category page. 
     * It will set 3 custom variables of scope "page" with the SKU, Name and Category for this page view.
     * Note: Custom Variables of scope "page" slots 3, 4 and 5 will be used.
     *  
     * On a category page, you may set the parameter $category only and set the other parameters to false.
     * 
     * Tracking Product/Category page views will allow Piwik to report on Product & Categories 
     * conversion rates (Conversion rate = Ecommerce orders containing this product or category / Visits to the product or category)
     * 
     * @param string $sku Product SKU being viewed
     * @param string $name Product Name being viewed
     * @param string|array $category Category being viewed. On a Product page, this is the product's category. 
     * 								You can also specify an array of up to 5 categories for a given page view.
     * @param float $price Specify the price at which the item was displayed
     */
    public function setEcommerceView($sku = false, $name = false, $category = false, $price = false)
    {
    	if(!empty($category)) {
    		if(is_array($category)) {
    			$category = json_encode($category);
    		}
    	} else {
    		$category = "";
    	}
    	$this->pageCustomVar[5] = array('_pkc', $category);
    	
    	if(!empty($price)) {
    		$this->pageCustomVar[2] = array('_pkp', (float)$price);
    	}
    	
    	// On a category page, do not record "Product name not defined" 
    	if(empty($sku) && empty($name))
    	{
    		return;
    	}
    	if(!empty($sku)) {
    		$this->pageCustomVar[3] = array('_pks', $sku);
    	}
    	if(empty($name)) {
    		$name = "";
    	}
    	$this->pageCustomVar[4] = array('_pkn', $name);
    }
    
    /**
     * Returns URL used to track Ecommerce Cart updates
     * Calling this function will reinitializes the property ecommerceItems to empty array 
     * so items will have to be added again via addEcommerceItem()  
     * @ignore
     */
    public function getUrlTrackEcommerceCartUpdate($grandTotal)
    {
    	$url = $this->getUrlTrackEcommerce($grandTotal);
    	return $url;
    }
    
    /**
     * Returns URL used to track Ecommerce Orders
     * Calling this function will reinitializes the property ecommerceItems to empty array 
     * so items will have to be added again via addEcommerceItem()  
     * @ignore
     */
    public function getUrlTrackEcommerceOrder($orderId, $grandTotal, $subTotal = false, $tax = false, $shipping = false, $discount = false)
    {
    	if(empty($orderId))
    	{
    		throw new Exception("You must specifiy an orderId for the Ecommerce order");
    	}
    	$url = $this->getUrlTrackEcommerce($grandTotal, $subTotal, $tax, $shipping, $discount);
    	$url .= '&ec_id=' . urlencode($orderId);
    	$this->ecommerceLastOrderTimestamp = $this->getTimestamp();
    	return $url;
    }
    
    /**
     * Returns URL used to track Ecommerce orders
     * Calling this function will reinitializes the property ecommerceItems to empty array 
     * so items will have to be added again via addEcommerceItem()  
     * @ignore
     */
    protected function getUrlTrackEcommerce($grandTotal, $subTotal = false, $tax = false, $shipping = false, $discount = false)
    {
    	if(!is_numeric($grandTotal))
    	{
    		throw new Exception("You must specifiy a grandTotal for the Ecommerce order (or Cart update)");
    	}
    	
    	$url = $this->getRequest( $this->idSite );
    	$url .= '&idgoal=0';
    	if(!empty($grandTotal))
    	{
    		$url .= '&revenue='.$grandTotal;
    	}
    	if(!empty($subTotal))
    	{
    		$url .= '&ec_st='.$subTotal;
    	}
    	if(!empty($tax))
    	{
    		$url .= '&ec_tx='.$tax;
    	}
    	if(!empty($shipping))
    	{
    		$url .= '&ec_sh='.$shipping;
    	}
    	if(!empty($discount))
    	{
    		$url .= '&ec_dt='.$discount;
    	}
    	if(!empty($this->ecommerceItems))
    	{
    		// Removing the SKU index in the array before JSON encoding
    		$items = array();
    		foreach($this->ecommerceItems as $item)
    		{
    			$items[] = $item;
    		}
    		$url .= '&ec_items='. urlencode(json_encode($items));
    	}
    	$this->ecommerceItems = array();
    	return $url;
    }
    
    /**
     * @see doTrackPageView()
     * @param string $documentTitle Page view name as it will appear in Piwik reports
     * @return string URL to piwik.php with all parameters set to track the pageview
     */
    public function getUrlTrackPageView( $documentTitle = false )
    {
    	$url = $this->getRequest( $this->idSite );
    	if(!empty($documentTitle)) {
    		$url .= '&action_name=' . urlencode($documentTitle);
    	}
    	return $url;
    }
    
    /**
     * @see doTrackGoal()
     * @param int $idGoal Id Goal to record a conversion
     * @param int $revenue Revenue for this conversion
     * @return string URL to piwik.php with all parameters set to track the goal conversion
     */
    public function getUrlTrackGoal($idGoal, $revenue = false)
    {
    	$url = $this->getRequest( $this->idSite );
		$url .= '&idgoal=' . $idGoal;
    	if(!empty($revenue)) {
    		$url .= '&revenue=' . $revenue;
    	}
    	return $url;
    }
        
    /**
     * @see doTrackAction()
     * @param string $actionUrl URL of the download or outlink
     * @param string $actionType Type of the action: 'download' or 'link'
     * @return string URL to piwik.php with all parameters set to track an action
     */
    public function getUrlTrackAction($actionUrl, $actionType)
    {
    	$url = $this->getRequest( $this->idSite );
		$url .= '&'.$actionType.'=' . $actionUrl .
				'&redirect=0';
		
    	return $url;
    }

    /**
     * Overrides server date and time for the tracking requests. 
     * By default Piwik will track requests for the "current datetime" but this function allows you 
     * to track visits in the past. All times are in UTC.
     * 
     * Allowed only for Super User, must be used along with setTokenAuth()
	 * Set tracking_requests_require_authentication = 0 in config.ini.php [Tracker] section
	 * to change this security constraint.
     * @see setTokenAuth()
     * @param string Date with the format 'Y-m-d H:i:s', or a UNIX timestamp
     */
    public function setForceVisitDateTime($dateTime)
    {
    	$this->forcedDatetime = $dateTime;
    }
    
    /**
     * Overrides IP address
     * 
     * Allowed only for Super User, must be used along with setTokenAuth()
	 * Set tracking_requests_require_authentication = 0 in config.ini.php [Tracker] section
	 * to change this security constraint.
     * @see setTokenAuth()
     * @param string IP string, eg. 130.54.2.1
     */
    public function setIp($ip)
    {
    	$this->ip = $ip;
    }
    
    /**
     * Forces the requests to be recorded for the specified Visitor ID
     * rather than using the heuristics based on IP and other attributes.
     * 
     * This is typically used with the Javascript getVisitorId() function.
     * 
     * Allowed only for Super User, must be used along with setTokenAuth().
	 * Set tracking_requests_require_authentication = 0 in config.ini.php [Tracker] section
	 * to change this security constraint.
     * @see setTokenAuth()
     * @param string $visitorId 16 hexadecimal characters visitor ID, eg. "33c31e01394bdc63"
     */
    public function setVisitorId($visitorId)
    {
    	if(strlen($visitorId) != self::LENGTH_VISITOR_ID)
    	{
    		throw new Exception("setVisitorId() expects a ".self::LENGTH_VISITOR_ID." characters ID");
    	}
    	$this->forcedVisitorId = $visitorId;
    }
    
    /**
     * If the user initiating the request has the Piwik first party cookie, 
     * this function will try and return the ID parsed from this first party cookie (found in $_COOKIE).
     * 
     * If you call this function from a server, where the call is triggered by a cron or script
     * not initiated by the actual visitor being tracked, then it will return 
     * the random Visitor ID that was assigned to this visit object.
     * 
     * This can be used if you wish to record more visits, actions or goals for this visitor ID later on.
     * 
     * @return string 16 hex chars visitor ID string
     */
    public function getVisitorId()
    {
    	if(!empty($this->forcedVisitorId))
    	{
    		return $this->forcedVisitorId;
    	}
    	
    	$idCookieName = 'id.'.$this->idSite.'.';
    	$idCookie = $this->getCookieMatchingName($idCookieName);
    	if($idCookie !== false)
    	{
    		$visitorId = substr($idCookie, 0, strpos($idCookie, '.'));
    		if(strlen($visitorId) == self::LENGTH_VISITOR_ID)
    		{
    			return $visitorId;
    		}
    	}
    	return $this->visitorId;
    }

    /**
     * Returns the currently assigned Attribution Information stored in a first party cookie.
     * 
     * This function will only work if the user is initiating the current request, and his cookies
     * can be read by PHP from the $_COOKIE array.
     * 
     * @return string JSON Encoded string containing the Referer information for Goal conversion attribution.
     *                Will return false if the cookie could not be found
     * @see Piwik.js getAttributionInfo()
     */
    public function getAttributionInfo()
    {
    	$attributionCookieName = 'ref.'.$this->idSite.'.';
    	return $this->getCookieMatchingName($attributionCookieName);
    }
    
	/**
	 * Some Tracking API functionnality requires express authentication, using either the 
	 * Super User token_auth, or a user with 'admin' access to the website.
	 * 
	 * The following features require access:
	 * - force the visitor IP
	 * - force the date & time of the tracking requests rather than track for the current datetime
	 * - force Piwik to track the requests to a specific VisitorId rather than use the standard visitor matching heuristic
	 *
	 * Set tracking_requests_require_authentication = 0 in config.ini.php [Tracker] section
	 * to change this security constraint.
	 * @param string token_auth 32 chars token_auth string
	 */
	public function setTokenAuth($token_auth)
	{
		$this->token_auth = $token_auth;
	}

    /**
     * Sets local visitor time
     * 
     * @param string $time HH:MM:SS format
     */
    public function setLocalTime($time)
    {
    	list($hour, $minute, $second) = explode(':', $time);
    	$this->localHour = (int)$hour;
    	$this->localMinute = (int)$minute;
    	$this->localSecond = (int)$second;
    }
    
    /**
     * Sets user resolution width and height.
     *
     * @param int $width
     * @param int $height
     */
    public function setResolution($width, $height)
    {
    	$this->width = $width;
    	$this->height = $height;
    }
    
    /**
     * Sets if the browser supports cookies 
     * This is reported in "List of plugins" report in Piwik.
     *
     * @param bool $bool
     */
    public function setBrowserHasCookies( $bool )
    {
    	$this->hasCookies = $bool ;
    }
    
    /**
     * Will append a custom string at the end of the Tracking request. 
     * @param string $string
     */
    public function setDebugStringAppend( $string )
    {
    	$this->DEBUG_APPEND_URL = $string;
    }
    
    /**
     * Sets visitor browser supported plugins 
     *
     * @param bool $flash
     * @param bool $java
     * @param bool $director
     * @param bool $quickTime
     * @param bool $realPlayer
     * @param bool $pdf
     * @param bool $windowsMedia
     * @param bool $gears
     * @param bool $silverlight
     */
    public function setPlugins($flash = false, $java = false, $director = false, $quickTime = false, $realPlayer = false, $pdf = false, $windowsMedia = false, $gears = false, $silverlight = false)
    {
    	$this->plugins = 
    		'&fla='.(int)$flash.
    		'&java='.(int)$java.
    		'&dir='.(int)$director.
    		'&qt='.(int)$quickTime.
    		'&realp='.(int)$realPlayer.
    		'&pdf='.(int)$pdf.
    		'&wma='.(int)$windowsMedia.
    		'&gears='.(int)$gears.
    		'&ag='.(int)$silverlight
    	;
    }
    
    /**
     * By default, PiwikTracker will read third party cookies 
     * from the response and sets them in the next request.
     * This can be disabled by calling this function.
     * 
     * @return void
     */
    public function disableCookieSupport()
    {
    	$this->cookieSupport = false;
    }
    
    /**
     * @ignore
     */
    protected function sendRequest($url)
    {
		$timeout = 600; // Allow debug while blocking the request
		$response = '';

		if(!$this->cookieSupport)
		{
			$this->requestCookie = '';
		}
		if(function_exists('curl_init'))
		{
			$ch = curl_init();
			curl_setopt_array($ch, array(
				CURLOPT_URL => $url,
				CURLOPT_USERAGENT => $this->userAgent,
				CURLOPT_HEADER => true,
				CURLOPT_TIMEOUT => $timeout,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_HTTPHEADER => array(
					'Accept-Language: ' . $this->acceptLanguage,
					'Cookie: '. $this->requestCookie,
				),
			));
			ob_start();
			$response = @curl_exec($ch);
			ob_end_clean();
			$header = $content = '';
			if(!empty($response))
			{
				list($header,$content) = explode("\r\n\r\n", $response, $limitCount = 2);
			}
		}
		else if(function_exists('stream_context_create'))
		{
			$stream_options = array(
				'http' => array(
					'user_agent' => $this->userAgent,
					'header' => "Accept-Language: " . $this->acceptLanguage . "\r\n" .
					            "Cookie: ".$this->requestCookie. "\r\n" ,
					'timeout' => $timeout, // PHP 5.2.1
				)
			);
			$ctx = stream_context_create($stream_options);
			$response = file_get_contents($url, 0, $ctx);
			$header = implode("\r\n", $http_response_header); 
			$content = $response;
		}
		// The cookie in the response will be set in the next request
		preg_match_all('/^Set-Cookie: (.*?);/m', $header, $cookie);
		if(!empty($cookie[1]))
		{
			// in case several cookies returned, we keep only the latest one (ie. XDEBUG puts its cookie first in the list)
			if(is_array($cookie[1]))
			{
				$cookie = end($cookie[1]);
			}
			else
			{
				$cookie = $cookie[1];
			}
			if(strpos($cookie, 'XDEBUG') === false)
			{
				$this->requestCookie = $cookie;
			}
		}

		return $content;
    }
    
    /**
     * Returns current timestamp, or forced timestamp/datetime if it was set
     * @return string|int
     */
    protected function getTimestamp()
    {
    	return !empty($this->forcedDatetime) 
    		? strtotime($this->forcedDatetime) 
    		: time();
    }
    
    /**
     * @ignore
     */
    protected function getRequest( $idSite )
    {
    	if(empty(self::$URL))
    	{
    		throw new Exception('You must first set the Piwik Tracker URL by calling PiwikTracker::$URL = \'http://your-website.org/piwik/\';');
    	}
    	if(strpos(self::$URL, '/piwik.php') === false
    		&& strpos(self::$URL, '/proxy-piwik.php') === false)
    	{
    		self::$URL .= '/piwik.php';
    	}
    	
    	$url = self::$URL .
	 		'?idsite=' . $idSite .
			'&rec=1' .
			'&apiv=' . self::VERSION . 
	        '&r=' . substr(strval(mt_rand()), 2, 6) .
    	
    		// PHP DEBUGGING: Optional since debugger can be triggered remotely
    		(!empty($_GET['XDEBUG_SESSION_START']) ? '&XDEBUG_SESSION_START=' . @$_GET['XDEBUG_SESSION_START'] : '') . 
	        (!empty($_GET['KEY']) ? '&KEY=' . @$_GET['KEY'] : '') .
    	 
    		// Only allowed for Super User, token_auth required,
			// except when tracking_requests_require_authentication = 0 in config.ini.php [Tracker] section
			(!empty($this->ip) ? '&cip=' . $this->ip : '') .
    		(!empty($this->forcedVisitorId) ? '&cid=' . $this->forcedVisitorId : '&_id=' . $this->visitorId) . 
			(!empty($this->forcedDatetime) ? '&cdt=' . urlencode($this->forcedDatetime) : '') .
			(!empty($this->token_auth) ? '&token_auth=' . urlencode($this->token_auth) : '') .
	        
			// These parameters are set by the JS, but optional when using API
	        (!empty($this->plugins) ? $this->plugins : '') . 
			(($this->localHour !== false && $this->localMinute !== false && $this->localSecond !== false) ? '&h=' . $this->localHour . '&m=' . $this->localMinute  . '&s=' . $this->localSecond : '' ).
	        (!empty($this->width) && !empty($this->height) ? '&res=' . $this->width . 'x' . $this->height : '') .
	        (!empty($this->hasCookies) ? '&cookie=' . $this->hasCookies : '') .
	        (!empty($this->ecommerceLastOrderTimestamp) ? '&_ects=' . urlencode($this->ecommerceLastOrderTimestamp) : '') .
	        
	        // Various important attributes
	        (!empty($this->customData) ? '&data=' . $this->customData : '') . 
	        (!empty($this->visitorCustomVar) ? '&_cvar=' . urlencode(json_encode($this->visitorCustomVar)) : '') .
	        (!empty($this->pageCustomVar) ? '&cvar=' . urlencode(json_encode($this->pageCustomVar)) : '') .
	        
	        // URL parameters
	        '&url=' . urlencode($this->pageUrl) .
			'&urlref=' . urlencode($this->urlReferrer) .
	        
	        // Attribution information, so that Goal conversions are attributed to the right referrer or campaign
	        // Campaign name
    		(!empty($this->attributionInfo[0]) ? '&_rcn=' . urlencode($this->attributionInfo[0]) : '') .
    		// Campaign keyword
    		(!empty($this->attributionInfo[1]) ? '&_rck=' . urlencode($this->attributionInfo[1]) : '') .
    		// Timestamp at which the referrer was set
    		(!empty($this->attributionInfo[2]) ? '&_refts=' . $this->attributionInfo[2] : '') .
    		// Referrer URL
    		(!empty($this->attributionInfo[3]) ? '&_ref=' . urlencode($this->attributionInfo[3]) : '') .

    		// DEBUG 
	        $this->DEBUG_APPEND_URL
        ;
    	// Reset page level custom variables after this page view
    	$this->pageCustomVar = false;
    	
    	return $url;
    }
    
    
    /**
     * Returns a first party cookie which name contains $name
     * 
     * @param string $name
     * @return string String value of cookie, or false if not found
     * @ignore
     */
    protected function getCookieMatchingName($name)
    {
    	// Piwik cookie names use dots separators in piwik.js, 
    	// but PHP Replaces . with _ http://www.php.net/manual/en/language.variables.predefined.php#72571
    	$name = str_replace('.', '_', $name);
    	foreach($_COOKIE as $cookieName => $cookieValue)
    	{
    		if(strpos($cookieName, $name) !== false)
    		{
    			return $cookieValue;
    		}
    	}
    	return false;
    }

	/**
	 * If current URL is "http://example.org/dir1/dir2/index.php?param1=value1&param2=value2"
	 * will return "/dir1/dir2/index.php"
	 *
	 * @return string
     * @ignore
	 */
	static protected function getCurrentScriptName()
	{
		$url = '';
		if( !empty($_SERVER['PATH_INFO']) ) { 
			$url = $_SERVER['PATH_INFO'];
		} 
		else if( !empty($_SERVER['REQUEST_URI']) ) 	{
			if( ($pos = strpos($_SERVER['REQUEST_URI'], '?')) !== false ) {
				$url = substr($_SERVER['REQUEST_URI'], 0, $pos);
			} else {
				$url = $_SERVER['REQUEST_URI'];
			}
		} 
		if(empty($url)) {
			$url = $_SERVER['SCRIPT_NAME'];
		}

		if($url[0] !== '/')	{
			$url = '/' . $url;
		}
		return $url;
	}

	/**
	 * If the current URL is 'http://example.org/dir1/dir2/index.php?param1=value1&param2=value2"
	 * will return 'http'
	 *
	 * @return string 'https' or 'http'
     * @ignore
	 */
	static protected function getCurrentScheme()
	{
		if(isset($_SERVER['HTTPS'])
				&& ($_SERVER['HTTPS'] == 'on' || $_SERVER['HTTPS'] === true))
		{
			return 'https';
		}
		return 'http';
	}

	/**
	 * If current URL is "http://example.org/dir1/dir2/index.php?param1=value1&param2=value2"
	 * will return "http://example.org"
	 *
	 * @return string
     * @ignore
	 */
	static protected function getCurrentHost()
	{
		if(isset($_SERVER['HTTP_HOST'])) {
			return $_SERVER['HTTP_HOST'];
		}
		return 'unknown';
	}

	/**
	 * If current URL is "http://example.org/dir1/dir2/index.php?param1=value1&param2=value2"
	 * will return "?param1=value1&param2=value2"
	 *
	 * @return string
     * @ignore
	 */
	static protected function getCurrentQueryString()
	{
		$url = '';	
		if(isset($_SERVER['QUERY_STRING'])
			&& !empty($_SERVER['QUERY_STRING']))
		{
			$url .= '?'.$_SERVER['QUERY_STRING'];
		}
		return $url;
	}
	
	/**
	 * Returns the current full URL (scheme, host, path and query string.
	 *  
	 * @return string
     * @ignore
	 */
    static protected function getCurrentUrl()
    {
		return self::getCurrentScheme() . '://'
			. self::getCurrentHost()
			. self::getCurrentScriptName() 
			. self::getCurrentQueryString();
	}
}

function Piwik_getUrlTrackPageView( $idSite, $documentTitle = false )
{
	$tracker = new PiwikTracker($idSite);
	return $tracker->getUrlTrackPageView($documentTitle);
}
function Piwik_getUrlTrackGoal($idSite, $idGoal, $revenue = false)
{
	$tracker = new PiwikTracker($idSite);
	return $tracker->getUrlTrackGoal($idGoal, $revenue);
}

