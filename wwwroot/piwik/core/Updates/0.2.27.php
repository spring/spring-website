<?php
/**
 * Piwik - Open source web analytics
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id: 0.2.27.php 4771 2011-05-22 21:49:27Z vipsoft $
 *
 * @category Piwik
 * @package Updates
 */

/**
 * @package Updates
 */
class Piwik_Updates_0_2_27 extends Piwik_Updates
{
	static function getSql($schema = 'Myisam')
	{
		$sqlarray = array(
			'ALTER TABLE `'. Piwik_Common::prefixTable('log_visit') .'`
				ADD `visit_goal_converted` VARCHAR( 1 ) NOT NULL AFTER `visit_total_time`' => false,
			// 0.2.27 [826]
			'ALTER IGNORE TABLE `'. Piwik_Common::prefixTable('log_visit') .'`
				CHANGE `visit_goal_converted` `visit_goal_converted` TINYINT(1) NOT NULL' => false,

			'CREATE TABLE `'. Piwik_Common::prefixTable('goal') ."` (
				`idsite` int(11) NOT NULL,
				`idgoal` int(11) NOT NULL,
				`name` varchar(50) NOT NULL,
				`match_attribute` varchar(20) NOT NULL,
				`pattern` varchar(255) NOT NULL,
				`pattern_type` varchar(10) NOT NULL,
				`case_sensitive` tinyint(4) NOT NULL,
				`revenue` float NOT NULL,
				`deleted` tinyint(4) NOT NULL default '0',
				PRIMARY KEY  (`idsite`,`idgoal`)
			)" => false,

			'CREATE TABLE `'. Piwik_Common::prefixTable('log_conversion') .'` (
				`idvisit` int(10) unsigned NOT NULL,
				`idsite` int(10) unsigned NOT NULL,
				`visitor_idcookie` char(32) NOT NULL,
				`server_time` datetime NOT NULL,
				`visit_server_date` date NOT NULL,
				`idaction` int(11) NOT NULL,
				`idlink_va` int(11) NOT NULL,
				`referer_idvisit` int(10) unsigned default NULL,
				`referer_visit_server_date` date default NULL,
				`referer_type` int(10) unsigned default NULL,
				`referer_name` varchar(70) default NULL,
				`referer_keyword` varchar(255) default NULL,
				`visitor_returning` tinyint(1) NOT NULL,
				`location_country` char(3) NOT NULL,
				`location_continent` char(3) NOT NULL,
				`url` text NOT NULL,
				`idgoal` int(10) unsigned NOT NULL,
				`revenue` float default NULL,
				PRIMARY KEY  (`idvisit`,`idgoal`),
				KEY `index_idsite_date` (`idsite`,`visit_server_date`)
			)' => false,
		);

		$tables = Piwik::getTablesInstalled();
		foreach($tables as $tableName)
		{
			if(preg_match('/archive_/', $tableName) == 1)
			{
				$sqlarray[ 'CREATE INDEX index_all ON '. $tableName .' (`idsite`,`date1`,`date2`,`name`,`ts_archived`)' ] = false;
			}
		}

		return $sqlarray;
	}

	static function update()
	{
		Piwik_Updater::updateDatabase(__FILE__, self::getSql());
	}
}
