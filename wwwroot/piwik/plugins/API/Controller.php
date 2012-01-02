<?php
/**
 * Piwik - Open source web analytics
 * 
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id: Controller.php 5151 2011-09-11 05:18:39Z matt $
 * 
 * @category Piwik_Plugins
 * @package Piwik_API
 */

/**
 * 
 * @package Piwik_API
 */
class Piwik_API_Controller extends Piwik_Controller
{
	function index()
	{
		// when calling the API through http, we limit the number of returned results
		if(!isset($_GET['filter_limit']))
		{
			$_GET['filter_limit'] = Zend_Registry::get('config')->General->API_datatable_default_limit;
		}
		$request = new Piwik_API_Request('token_auth='.Piwik_Common::getRequestVar('token_auth', 'anonymous', 'string'));
		echo $request->process();
	}

	public function listAllMethods()
	{
		$ApiDocumentation = new Piwik_API_DocumentationGenerator();
		echo $ApiDocumentation->getAllInterfaceString( $outputExampleUrls = true, $prefixUrls = Piwik_Common::getRequestVar('prefixUrl', '') );
	}
	
	public function listAllAPI()
	{
		$view = Piwik_View::factory("listAllAPI");
		$this->setGeneralVariablesView($view);
		
		$ApiDocumentation = new Piwik_API_DocumentationGenerator();
		$view->countLoadedAPI = Piwik_API_Proxy::getInstance()->getCountRegisteredClasses();
		$view->list_api_methods_with_links = $ApiDocumentation->getAllInterfaceString();
		echo $view->render();
	}
	
	public function listSegments()
	{
		$segments = Piwik_API_API::getInstance()->getSegmentsMetadata($this->idSite);
		
		$tableDimensions = $tableMetrics = '';
		$customVariables=0;
		$lastCategory=array();
		foreach($segments as $segment)
		{
			$onlyDisplay = array('customVariableName1', 'customVariableName2', 'customVariableValue1', 'customVariableValue2', 'customVariablePageName1', 'customVariablePageValue1');
			$customVariableWillBeDisplayed = in_array($segment['segment'], $onlyDisplay);
			// Don't display more than 4 custom variables name/value rows
			if($segment['category'] == 'Custom Variables'
				&& !$customVariableWillBeDisplayed)
			{ 
				continue;
			}
			
			$thisCategory = $segment['category'];
			$output = '';
			if(empty($lastCategory[$segment['type']]) 
				|| $lastCategory[$segment['type']] != $thisCategory)
			{
				$output .= '<tr><td class="segmentCategory" colspan="2"><b>'.$thisCategory.'</b></td></tr>';
			}
			
			$lastCategory[$segment['type']] = $thisCategory;
			
			$exampleValues = isset($segment['acceptedValues']) 
								? 'Example values: <code>'.$segment['acceptedValues'].'</code>' 
								: '';
			$restrictedToAdmin = isset($segment['permission']) ? '<br/>Note: This segment can only be used by an Admin user' : '';
			$output .= '<tr>
							<td class="segmentString">'.$segment['segment'].'</td>
							<td class="segmentName">'.$segment['name'] .$restrictedToAdmin.'<br/>'.$exampleValues.' </td>
						</tr>';
			
			// Show only 2 custom variables and display message for rest
			if($customVariableWillBeDisplayed)
			{
				$customVariables++;
    			if($customVariables == count($onlyDisplay))
    			{
    				$output .= '<tr><td colspan="2"> There are 5 custom variables available, so you can segment across any segment name and value range.
    						<br/>For example, <code>customVariableName1==Type;customVariableValue1==Customer</code>
    						<br/>Returns all visitors that have the Custom Variable "Type" set to "Customer".
    						<br/>Custom Variables of scope "page" can be queried separately. For example, to query the Custom Variable of scope "page",
    						<br/>stored in index 1, you would use the segment <code>customVariablePageName1==ArticleLanguage;customVariablePageValue1==FR</code>
    						</td></tr>';
    			}
			}
			
			
			if($segment['type'] == 'dimension') {
				$tableDimensions .= $output;
			} else {
				$tableMetrics .= $output;
			}
		}
		
		echo "
		<b>Dimensions</b>
		<table>
		$tableDimensions
		</table>
		<br/>
		<b>Metrics</b>
		<table>
		$tableMetrics
		</table>
		";
	}
}
