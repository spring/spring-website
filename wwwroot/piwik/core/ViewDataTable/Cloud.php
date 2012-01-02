<?php
/**
 * Piwik - Open source web analytics
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id: Cloud.php 4634 2011-05-05 08:56:37Z EZdesign $
 *
 * @category Piwik
 * @package Piwik
 */

/**
 * Reads the requested DataTable from the API, and prepares the data to give
 * to Piwik_Visualization_Cloud that will display the tag cloud (via the template cloud.tpl).
 *
 * @package Piwik
 * @subpackage Piwik_ViewDataTable
 */
class Piwik_ViewDataTable_Cloud extends Piwik_ViewDataTable
{
	protected $displayLogoInsteadOfLabel = false;

	public function setDisplayLogoInTagCloud($bool)
	{
		$this->displayLogoInsteadOfLabel = $bool;
	}
	
	protected function getViewDataTableId()
	{
		return 'cloud';
	}
		
	/**
	 * @see Piwik_ViewDataTable::init()
	 */
	function init($currentControllerName,
						$currentControllerAction,
						$apiMethodToRequestDataTable,
						$controllerActionCalledWhenRequestSubTable = null)
	{
		parent::init($currentControllerName,
						$currentControllerAction,
						$apiMethodToRequestDataTable,
						$controllerActionCalledWhenRequestSubTable);
		$this->dataTableTemplate = 'CoreHome/templates/cloud.tpl';
		$this->disableOffsetInformation();
		$this->disableExcludeLowPopulation();
	}
	
	/**
	 * @see Piwik_ViewDataTable::main()
	 *
	 */
	public function main()
	{
		$this->setLimit( 30 );
		if($this->mainAlreadyExecuted)
		{
			return;
		}
		$this->mainAlreadyExecuted = true;

		$this->isDataAvailable = true;
		try {
			$this->loadDataTableFromAPI();
		} catch(Exception $e) {
			$this->isDataAvailable = false;
		}
		$this->checkStandardDataTable();
		$this->view = $this->buildView();
	}
	
	function getColumnToDisplay()
	{
		$columns = parent::getColumnsToDisplay();
		// not label, but the first numeric column
		return $columns[1];
	}
	
	protected function buildView()
	{
		$view = new Piwik_View($this->dataTableTemplate);
		if(!$this->isDataAvailable)
		{
			$view->cloudValues = array();
		}
		else
		{
			$columnToDisplay = $this->getColumnToDisplay();
			$columnTranslation = $this->getColumnTranslation($columnToDisplay);
			$values = $this->dataTable->getColumn($columnToDisplay);
			$labels  = $this->dataTable->getColumn('label');
			$labelMetadata = array();
			foreach($this->dataTable->getRows() as $row)
			{
				$logo = false;
				if($this->displayLogoInsteadOfLabel)
				{
					$logo =  $row->getMetadata('logo');
				}
				$labelMetadata[$row->getColumn('label')] = array(
					'logo' => $logo,
					'url' => $row->getMetadata('url'),
				);
			}
			$cloud = new Piwik_Visualization_Cloud();
			foreach($labels as $i => $label)
			{
				$cloud->addWord($label, $values[$i]);
			}
			$cloudValues  = $cloud->render('array');
			foreach($cloudValues as &$value)
			{
				$value['logoWidth'] = round(max(16, $value['percent']));
			}
			$view->columnTranslation = $columnTranslation;
			$view->labelMetadata = $labelMetadata;
			$view->cloudValues = $cloudValues;
		}
		$view->javascriptVariablesToSet = $this->getJavascriptVariablesToSet();
		$view->properties = $this->getViewProperties();
		$view->reportDocumentation = $this->getReportDocumentation();
		return $view;
	}
}
