<?php
/**
 * Piwik - Open source web analytics
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id: 1.2-rc1.php 4731 2011-05-20 05:36:08Z matt $
 *
 * @category Piwik
 * @package Updates
 */

/**
 * @package Updates
 */
class Piwik_Updates_1_2_rc1 extends Piwik_Updates
{
	static function getSql($schema = 'Myisam')
	{
		return array(
			// Various performance improvements schema updates
		    'ALTER TABLE `'. Piwik_Common::prefixTable('log_visit') .'` 
			    DROP `visit_server_date`,
			    DROP INDEX `index_idsite_date_config`,
			    DROP INDEX `index_idsite_datetime_config`,
		    	ADD `visit_entry_idaction_name` INT UNSIGNED NOT NULL AFTER `visit_entry_idaction_url`,
			    ADD `visit_exit_idaction_name` INT UNSIGNED NOT NULL AFTER `visit_exit_idaction_url`,
			    CHANGE `visit_exit_idaction_url` `visit_exit_idaction_url` INT UNSIGNED NOT NULL, 
			    CHANGE `visit_entry_idaction_url` `visit_entry_idaction_url` INT UNSIGNED NOT NULL,
			    CHANGE `referer_type` `referer_type` TINYINT UNSIGNED NULL DEFAULT NULL,
			    ADD `idvisitor` BINARY(8) NOT NULL AFTER `idsite`, 
			    ADD visitor_count_visits SMALLINT(5) UNSIGNED NOT NULL AFTER `visitor_returning`,
			    ADD visitor_days_since_last SMALLINT(5) UNSIGNED NOT NULL,
			    ADD visitor_days_since_first SMALLINT(5) UNSIGNED NOT NULL,
			    ADD `config_id` BINARY(8) NOT NULL AFTER `config_md5config`,
			    ADD custom_var_k1 VARCHAR(100) DEFAULT NULL,
    			ADD custom_var_v1 VARCHAR(100) DEFAULT NULL,
    			ADD custom_var_k2 VARCHAR(100) DEFAULT NULL,
    			ADD custom_var_v2 VARCHAR(100) DEFAULT NULL,
    			ADD custom_var_k3 VARCHAR(100) DEFAULT NULL,
    			ADD custom_var_v3 VARCHAR(100) DEFAULT NULL,
    			ADD custom_var_k4 VARCHAR(100) DEFAULT NULL,
    			ADD custom_var_v4 VARCHAR(100) DEFAULT NULL,
    			ADD custom_var_k5 VARCHAR(100) DEFAULT NULL,
    			ADD custom_var_v5 VARCHAR(100) DEFAULT NULL
			   ' => false,
		    'ALTER TABLE `'. Piwik_Common::prefixTable('log_link_visit_action') .'` 
				ADD `idsite` INT( 10 ) UNSIGNED NOT NULL AFTER `idlink_va` , 
				ADD `server_time` DATETIME AFTER `idsite`,
				ADD `idvisitor` BINARY(8) NOT NULL AFTER `idsite`,
				ADD `idaction_name_ref` INT UNSIGNED NOT NULL AFTER `idaction_name`,
				ADD INDEX `index_idsite_servertime` ( `idsite` , `server_time` )
			   ' => false,

		    'ALTER TABLE `'. Piwik_Common::prefixTable('log_conversion') .'` 
			    DROP `referer_idvisit`,
			    ADD `idvisitor` BINARY(8) NOT NULL AFTER `idsite`,
			    ADD visitor_count_visits SMALLINT(5) UNSIGNED NOT NULL,
			    ADD visitor_days_since_first SMALLINT(5) UNSIGNED NOT NULL,
			    ADD custom_var_k1 VARCHAR(100) DEFAULT NULL,
    			ADD custom_var_v1 VARCHAR(100) DEFAULT NULL,
    			ADD custom_var_k2 VARCHAR(100) DEFAULT NULL,
    			ADD custom_var_v2 VARCHAR(100) DEFAULT NULL,
    			ADD custom_var_k3 VARCHAR(100) DEFAULT NULL,
    			ADD custom_var_v3 VARCHAR(100) DEFAULT NULL,
    			ADD custom_var_k4 VARCHAR(100) DEFAULT NULL,
    			ADD custom_var_v4 VARCHAR(100) DEFAULT NULL,
    			ADD custom_var_k5 VARCHAR(100) DEFAULT NULL,
    			ADD custom_var_v5 VARCHAR(100) DEFAULT NULL
			   ' => false,
		
			// Migrate 128bits IDs inefficiently stored as 8bytes (256 bits) into 64bits
    		'UPDATE '.Piwik_Common::prefixTable('log_visit') .'
    			SET idvisitor = binary(unhex(substring(visitor_idcookie,1,16))),
    				config_id = binary(unhex(substring(config_md5config,1,16)))
	   			' => false,	
    		'UPDATE '.Piwik_Common::prefixTable('log_conversion') .'
    			SET idvisitor = binary(unhex(substring(visitor_idcookie,1,16)))
	   			' => false,	
			
			// Drop migrated fields
		    'ALTER TABLE `'. Piwik_Common::prefixTable('log_visit') .'` 
		    	DROP visitor_idcookie, 
		    	DROP config_md5config
		    	' => false,
		    'ALTER TABLE `'. Piwik_Common::prefixTable('log_conversion') .'` 
		    	DROP visitor_idcookie
		    	' => false,
		
			// Recreate INDEX on new field
		    'ALTER TABLE `'. Piwik_Common::prefixTable('log_visit') .'` 
		    	ADD INDEX `index_idsite_datetime_config` (idsite, visit_last_action_time, config_id)
		    	' => false,
		
			// Backfill action logs as best as we can
			'UPDATE '.Piwik_Common::prefixTable('log_link_visit_action') .' as action, 
				  	'.Piwik_Common::prefixTable('log_visit') .'  as visit
                SET action.idsite = visit.idsite, 
                	action.server_time = visit.visit_last_action_time, 
                	action.idvisitor = visit.idvisitor
                WHERE action.idvisit=visit.idvisit
                ' => false, 
		
		    'ALTER TABLE `'. Piwik_Common::prefixTable('log_link_visit_action') .'` 
				CHANGE `server_time` `server_time` DATETIME NOT NULL
			   ' => false,

			// New index used max once per request, in case this table grows significantly in the future
			'ALTER TABLE `'. Piwik_Common::prefixTable('option') .'` ADD INDEX ( `autoload` ) ' => false,
		
		    // new field for websites
		    'ALTER TABLE `'. Piwik_Common::prefixTable('site') .'` ADD `group` VARCHAR( 250 ) NOT NULL' => false,
		);
	}

	static function update()
	{
		// first we disable the plugins and keep an array of warnings messages 
		$pluginsToDisableMessage = array(
			'GeoIP' => "GeoIP plugin was disabled, because it is not compatible with the new Piwik 1.2. \nYou can download the latest version of the plugin, compatible with Piwik 1.2.\n<a target='_blank' href='?module=Proxy&action=redirect&url=http://dev.piwik.org/trac/ticket/45'>Click here.</a>",
			'EntryPage' => "EntryPage plugin is not compatible with this version of Piwik, it was disabled.",
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

