<?php
/**
 * Piwik - Open source web analytics
 * 
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id: API.php 5235 2011-09-27 07:20:45Z matt $
 * 
 * @category Piwik_Plugins
 * @package Piwik_CustomVariables
 */

/**
 * The Custom Variables API lets you access reports for your <a href='http://piwik.org/docs/custom-variables/' target='_blank'>Custom Variables</a> names and values.
 * 
 * @package Piwik_CustomVariables
 */
class Piwik_CustomVariables_API 
{
	static private $instance = null;
	
	/**
	 * @return Piwik_CustomVariables_API
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
	 * @return Piwik_DataTable
	 */
	protected function getDataTable($idSite, $period, $date, $segment, $expanded, $idSubtable)
	{
	    $dataTable = Piwik_Archive::getDataTableFromArchive('CustomVariables_valueByName', $idSite, $period, $date, $segment, $expanded, $idSubtable);
		$dataTable->filter('Sort', array(Piwik_Archive::INDEX_NB_VISITS, 'desc', $naturalSort = false, $expanded));
		$dataTable->queueFilter('ReplaceColumnNames');
	    return $dataTable;
	}

	/**
	 * @return Piwik_DataTable
	 */
	public function getCustomVariables($idSite, $period, $date, $segment = false, $expanded = false, $_leavePiwikCoreVariables = false)
	{
	    $dataTable = $this->getDataTable($idSite, $period, $date, $segment, $expanded, $idSubtable = null);
	    
	    if($dataTable instanceof Piwik_DataTable
	    	&& !$_leavePiwikCoreVariables)
	    {
	    	$mapping = array(
	    		'_pks' => Piwik_Translate('Goals_ProductSKU'),
	    		'_pkn' => Piwik_Translate('Goals_ProductName'),
	    		'_pkc' => Piwik_Translate('Goals_ProductCategory'),
	    		'_pkp' => 'do not display price values in UI'
	    	);
	    	foreach($mapping as $core => $friendly)
	    	{
	    		$row = $dataTable->getRowFromLabel($core);
	    		if($row)
	    		{
	    			$row->setColumn('label', $friendly);
		    		if($core == '_pkp') 
		    		{
		    			$dataTable->deleteRow($dataTable->getRowIdFromLabel($core));
		    		}
	    		}
	    	}
	    }
		return $dataTable;
	}

	/**
	 * @return Piwik_DataTable
	 */
	public function getCustomVariablesValuesFromNameId($idSite, $period, $date, $idSubtable, $segment = false, $_leavePriceViewedColumn = false)
	{
	    $dataTable = $this->getDataTable($idSite, $period, $date, $segment, $expanded = false, $idSubtable);
	    
	    
    	if(!$_leavePriceViewedColumn)
    	{
    		$dataTable->deleteColumn('price_viewed');
    	}
    	else
    	{
		    // Hack Ecommerce product price tracking to display correctly
		    $dataTable->renameColumn('price_viewed', 'price');
    	}
    	$dataTable->queueFilter('ColumnCallbackReplace', array('label', create_function('$label', '
    					return $label == Piwik_CustomVariables::LABEL_CUSTOM_VALUE_NOT_DEFINED 
    						? "'. Piwik_Translate( 'General_NotDefined', Piwik_Translate('CustomVariables_ColumnCustomVariableValue')) .'" 
    						: $label;')));
		return $dataTable;
	}
}

