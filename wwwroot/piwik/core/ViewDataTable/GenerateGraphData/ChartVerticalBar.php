<?php
/**
 * Piwik - Open source web analytics
 * 
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id: ChartVerticalBar.php 2968 2010-08-20 15:26:33Z vipsoft $
 * 
 * @category Piwik
 * @package Piwik
 */

/**
 * Piwik_ViewDataTable_GenerateGraphData for the vertical bar graph, using Piwik_Visualization_Chart_VerticalBar
 * 
 * @package Piwik
 * @subpackage Piwik_ViewDataTable
 */
class Piwik_ViewDataTable_GenerateGraphData_ChartVerticalBar extends Piwik_ViewDataTable_GenerateGraphData
{
	protected $graphLimit = 6;
	
	protected function getViewDataTableId()
	{
		return 'generateDataChartVerticalBar';
	}
	
	function __construct()
	{
		$this->view = new Piwik_Visualization_Chart_VerticalBar();
	}
}
