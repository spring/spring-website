<?php
/**
 * Piwik - Open source web analytics
 * 
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id: Controller.php 2968 2010-08-20 15:26:33Z vipsoft $
 * 
 * @category Piwik_Plugins
 * @package Piwik_Provider
 */

/**
 *
 * @package Piwik_Provider
 */
class Piwik_Provider_Controller extends Piwik_Controller 
{	
	/**
	 * Provider
	 */
	function getProvider($fetch = false)
	{
		$view = Piwik_ViewDataTable::factory();
		$view->init( $this->pluginName,  __FUNCTION__, "Provider.getProvider" );
	
		$this->setPeriodVariablesView($view);
		$column = 'nb_visits';
		if($view->period == 'day')
		{
			$column = 'nb_uniq_visitors';
		}
		$view->setColumnsToDisplay( array('label',$column) );
		$view->setColumnTranslation('label', Piwik_Translate('Provider_ColumnProvider'));
		$view->setSortedColumn( $column	 );
		$view->setLimit( 5 );
		return $this->renderView($view, $fetch);
	}
	
}

