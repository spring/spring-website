<?php
/**
 * Piwik - Open source web analytics
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id: Controller.php 5235 2011-09-27 07:20:45Z matt $
 *
 * @category Piwik_Plugins
 * @package Piwik_CustomVariables
 */

/**
 *
 * @package Piwik_CustomVariables
 */
class Piwik_CustomVariables_Controller extends Piwik_Controller
{
	
	function index($fetch = false)
	{
		return Piwik_View::singleReport(
				Piwik_Translate('CustomVariables_CustomVariables'),
				$this->getCustomVariables(true), $fetch);
	}
	
	function getCustomVariables($fetch = false)
	{
		$view = Piwik_ViewDataTable::factory();
		$view->init( $this->pluginName,  __FUNCTION__, "CustomVariables.getCustomVariables", "getCustomVariablesValuesFromNameId" );
	
		$this->setPeriodVariablesView($view);
		$view->enableShowGoals();
		
		$view->setColumnsToDisplay( array('label','nb_visits', 'nb_actions') );
		$view->setColumnTranslation('label', Piwik_Translate('CustomVariables_ColumnCustomVariableName'));
		$view->setSortedColumn( 'nb_visits'	 );
		$view->setLimit( 10 );
		$view->setFooterMessage( 'Help: <a target="_blank" href="http://piwik.org/docs/custom-variables/">Tracking Custom Variables in Piwik</a>');
		return $this->renderView($view, $fetch);
	}

	function getCustomVariablesValuesFromNameId( $fetch = false)
	{
		$view = Piwik_ViewDataTable::factory();
		$view->init( $this->pluginName, __FUNCTION__, 'CustomVariables.getCustomVariablesValuesFromNameId' );

		$view->disableSearchBox();
		$view->enableShowGoals();
		$view->disableExcludeLowPopulation();
		$view->setColumnsToDisplay( array('label','nb_visits', 'nb_actions') );
		$view->setColumnTranslation('label', Piwik_Translate('CustomVariables_ColumnCustomVariableValue'));

		return $this->renderView($view, $fetch);
	}
	
}

