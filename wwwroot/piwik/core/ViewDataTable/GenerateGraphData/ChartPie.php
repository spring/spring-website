<?php
/**
 * Piwik - Open source web analytics
 * 
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id: ChartPie.php 2968 2010-08-20 15:26:33Z vipsoft $
 * 
 * @category Piwik
 * @package Piwik
 */

/**
 * Piwik_ViewDataTable_GenerateGraphData for the pie chart, using Piwik_Visualization_Chart_Pie
 * 
 * @package Piwik
 * @subpackage Piwik_ViewDataTable
 */
class Piwik_ViewDataTable_GenerateGraphData_ChartPie extends Piwik_ViewDataTable_GenerateGraphData
{
	protected $graphLimit = 6;
	
	protected function getViewDataTableId()
	{
		return 'generateDataChartPie';
	}
	
	function __construct()
	{
		$this->view = new Piwik_Visualization_Chart_Pie();
	}
}
