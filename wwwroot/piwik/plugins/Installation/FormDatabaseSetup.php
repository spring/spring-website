<?php
/**
 * Piwik - Open source web analytics
 * 
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id: FormDatabaseSetup.php 3678 2011-01-09 04:03:18Z matt $
 * 
 * @category Piwik_Plugins
 * @package Piwik_Installation
 */

/**
 * 
 * @package Piwik_Installation
 */
class Piwik_Installation_FormDatabaseSetup extends Piwik_QuickForm2
{
	function __construct( $id = 'databasesetupform', $method = 'post', $attributes = null, $trackSubmit = false)
	{
		parent::__construct($id,  $method, $attributes = array('autocomplete' => 'off'), $trackSubmit);
	}

	function init()
	{
		$availableAdapters = Piwik_Db_Adapter::getAdapters();
		$adapters = array();
		foreach($availableAdapters as $adapter => $port)
		{
			$adapters[$adapter] = $adapter;
		}

		$this->addElement('text', 'host')
		     ->setLabel(Piwik_Translate('Installation_DatabaseSetupServer'))
		     ->addRule('required', Piwik_Translate('General_Required', Piwik_Translate('Installation_DatabaseSetupServer')));

		$this->addElement('text', 'username')
		     ->setLabel(Piwik_Translate('Installation_DatabaseSetupLogin'))
		     ->addRule('required', Piwik_Translate('General_Required', Piwik_Translate('Installation_DatabaseSetupLogin')));

		$this->addElement('password', 'password')
		     ->setLabel(Piwik_Translate('Installation_DatabaseSetupPassword'));

		$this->addElement('text', 'dbname')
		     ->setLabel(Piwik_Translate('Installation_DatabaseSetupDatabaseName'))
		     ->addRule('required', Piwik_Translate('General_Required', Piwik_Translate('Installation_DatabaseSetupDatabaseName')));

		$this->addElement('text', 'tables_prefix')
		     ->setLabel(Piwik_Translate('Installation_DatabaseSetupTablePrefix'));

		$this->addElement('select', 'adapter')
		     ->setLabel(Piwik_Translate('Installation_DatabaseSetupAdapter'))
		     ->loadOptions($adapters)
		     ->addRule('required', Piwik_Translate('General_Required', Piwik_Translate('Installation_DatabaseSetupAdapter')));

		$this->addElement('submit', 'submit', array('value' => Piwik_Translate('General_Next') .' »', 'class' => 'submit'));

		// default values
		$this->addDataSource(new HTML_QuickForm2_DataSource_Array(array(
			'host' => '127.0.0.1',
			'tables_prefix' => 'piwik_',
		)));
	}
}
