<?php
/**
 * Piwik - Open source web analytics
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id: ChartEvolution.php 4813 2011-05-26 09:28:38Z EZdesign $
 *
 * @category Piwik
 * @package Piwik
 */

/**
 * Generates HTML embed for the Evolution graph
 *
 * @package Piwik
 * @subpackage Piwik_ViewDataTable
 */

class Piwik_ViewDataTable_GenerateGraphHTML_ChartEvolution extends Piwik_ViewDataTable_GenerateGraphHTML
{
	
	protected $height = 170;
	protected $graphType = 'evolution';

	protected function getViewDataTableId()
	{
		return 'graphEvolution';
	}
	
	protected function getViewDataTableIdToLoad()
	{
		return 'generateDataChartEvolution';
	}
	
	function init($currentControllerName,
						$currentControllerAction,
						$apiMethodToRequestDataTable,
						$controllerActionCalledWhenRequestSubTable = null)
	{
		parent::init($currentControllerName,
						$currentControllerAction,
						$apiMethodToRequestDataTable,
						$controllerActionCalledWhenRequestSubTable);
		
		$this->setParametersToModify(array('date' => Piwik_Common::getRequestVar('date', 'last30', 'string')));
		$this->disableShowAllViewsIcons();
		$this->disableShowTable();
		$this->disableShowAllColumns();
	}
	
	
	/**
     * We ensure that the graph for a given Goal has a different ID than the 'Goals Overview' graph
     * so that both can display on the dashboard at the same time
     */
	public function getUniqueIdViewDataTable()
	{
		$id = parent::getUniqueIdViewDataTable();
		if (!empty($this->parametersToModify['idGoal']))
		{
			$id .= $this->parametersToModify['idGoal'];
		}
		return $id;
	}
	
	/**
	 * Sets the columns that will be displayed on output evolution chart
	 * By default all columns are displayed ($columnsNames = array() will display all columns)
	 *
	 * @param array $columnsNames Array of column names eg. array('nb_visits','nb_hits')
	 */
	public function setColumnsToDisplay($columnsNames)
	{
		if (!is_array($columnsNames))
		{
			if (strpos($columnsNames, ',') !== false)
			{
        		// array values are comma separated
        		$columnsNames = explode(',', $columnsNames);
			}
			else
			{
				$columnsNames = array($columnsNames);
			}
		}
		$this->setParametersToModify(array('columns' => $columnsNames));
	}
	
}
