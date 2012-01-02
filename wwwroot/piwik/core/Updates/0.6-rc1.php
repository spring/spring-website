<?php
/**
 * Piwik - Open source web analytics
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id: 0.6-rc1.php 3175 2010-09-25 03:52:17Z vipsoft $
 *
 * @category Piwik
 * @package Updates
 */

/**
 * @package Updates
 */
class Piwik_Updates_0_6_rc1 extends Piwik_Updates
{
	static function getSql($schema = 'Myisam')
	{
		$defaultTimezone = 'UTC';
		$defaultCurrency = 'USD';
		return array(
			'ALTER TABLE ' . Piwik_Common::prefixTable('user') . ' CHANGE date_registered date_registered TIMESTAMP NULL' => false,
			'ALTER TABLE ' . Piwik_Common::prefixTable('site') . ' CHANGE ts_created ts_created TIMESTAMP NULL' => false,
			'ALTER TABLE ' . Piwik_Common::prefixTable('site') . ' ADD `timezone` VARCHAR( 50 ) NOT NULL AFTER `ts_created` ;' => false,
			'UPDATE ' . Piwik_Common::prefixTable('site') . ' SET `timezone` = "'.$defaultTimezone.'";' => false,
			'ALTER TABLE ' . Piwik_Common::prefixTable('site') . ' ADD currency CHAR( 3 ) NOT NULL AFTER `timezone` ;' => false,
			'UPDATE ' . Piwik_Common::prefixTable('site') . ' SET `currency` = "'.$defaultCurrency.'";' => false,
			'ALTER TABLE ' . Piwik_Common::prefixTable('site') . ' ADD `excluded_ips` TEXT NOT NULL AFTER `currency` ;' => false,
			'ALTER TABLE ' . Piwik_Common::prefixTable('site') . ' ADD excluded_parameters VARCHAR( 255 ) NOT NULL AFTER `excluded_ips` ;' => false,
			'ALTER TABLE ' . Piwik_Common::prefixTable('log_visit') . ' ADD INDEX `index_idsite_datetime_config`  ( `idsite` , `visit_last_action_time`  , `config_md5config` ( 8 ) ) ;' => false,
			'ALTER TABLE ' . Piwik_Common::prefixTable('log_visit') . ' ADD INDEX index_idsite_idvisit (idsite, idvisit) ;' => false,
			'ALTER TABLE ' . Piwik_Common::prefixTable('log_conversion') . ' DROP INDEX index_idsite_date' => false,
			'ALTER TABLE ' . Piwik_Common::prefixTable('log_conversion') . ' DROP visit_server_date;' => false,
			'ALTER TABLE ' . Piwik_Common::prefixTable('log_conversion') . ' ADD INDEX index_idsite_datetime ( `idsite` , `server_time` )' => false,
		);
	}

	static function update()
	{
		// first we disable the plugins and keep an array of warnings messages 
		$pluginsToDisableMessage = array(
			'SearchEnginePosition' => "SearchEnginePosition plugin was disabled, because it is not compatible with the new Piwik 0.6. \n You can download the latest version of the plugin, compatible with Piwik 0.6.\n<a target='_blank' href='?module=Proxy&action=redirect&url=http://dev.piwik.org/trac/ticket/502'>Click here.</a>", 
			'GeoIP' => "GeoIP plugin was disabled, because it is not compatible with the new Piwik 0.6. \nYou can download the latest version of the plugin, compatible with Piwik 0.6.\n<a target='_blank' href='?module=Proxy&action=redirect&url=http://dev.piwik.org/trac/ticket/45'>Click here.</a>"
		);
		$disabledPlugins = array();
		foreach($pluginsToDisableMessage as $pluginToDisable => $warningMessage) 
		{
			if(Piwik_PluginsManager::getInstance()->isPluginActivated($pluginToDisable))
			{
				Piwik_PluginsManager::getInstance()->deactivatePlugin($pluginToDisable);
				$disabledPlugins[] = $warningMessage;
			}
		}
		
		// Run the SQL
		Piwik_Updater::updateDatabase(__FILE__, self::getSql());
		
		// Outputs warning message, pointing users to the plugin download page
		if(!empty($disabledPlugins))
		{
			throw new Exception("The following plugins were disabled during the upgrade:"
							."<ul><li>" . 
								implode('</li><li>', $disabledPlugins) . 
							"</li></ul>");
		}
	}
}
