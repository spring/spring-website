<?php
/**
 * Piwik - Open source web analytics
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id: 1.2.3.php 4322 2011-04-05 07:04:43Z matt $
 *
 * @category Piwik
 * @package Updates
 */

/**
 * @package Updates
 */
class Piwik_Updates_1_2_3 extends Piwik_Updates
{
	static function getSql($schema = 'Myisam')
	{
		return array(
			// LOAD DATA INFILE uses the database's charset
			'ALTER DATABASE `'. Zend_Registry::get('config')->database->dbname .'` DEFAULT CHARACTER SET utf8' => false,

			// Various performance improvements schema updates
			'ALTER TABLE `'. Piwik_Common::prefixTable('log_visit') .'` 
				DROP INDEX index_idsite_datetime_config,
				DROP INDEX index_idsite_idvisit,
				ADD INDEX index_idsite_config_datetime (idsite, config_id, visit_last_action_time),
				ADD INDEX index_idsite_datetime (idsite, visit_last_action_time)' => false,
		);
	}

	static function update()
	{
		Piwik_Updater::updateDatabase(__FILE__, self::getSql());
	}
}

