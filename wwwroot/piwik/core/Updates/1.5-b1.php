<?php
/**
 * Piwik - Open source web analytics
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id: 1.5-b1.php 4766 2011-05-22 20:00:44Z matt $
 *
 * @category Piwik
 * @package Updates
 */

/**
 * @package Updates
 */
class Piwik_Updates_1_5_b1 extends Piwik_Updates
{
	static function getSql($schema = 'Myisam')
	{
		return array(
			'CREATE TABLE `'. Piwik_Common::prefixTable('log_conversion_item') .'` (
												  idsite int(10) UNSIGNED NOT NULL,
										  		  idvisitor BINARY(8) NOT NULL,
										          server_time DATETIME NOT NULL,
												  idvisit INTEGER(10) UNSIGNED NOT NULL,
												  idorder varchar(100) NOT NULL,
												  
												  idaction_sku INTEGER(10) UNSIGNED NOT NULL,
												  idaction_name INTEGER(10) UNSIGNED NOT NULL,
												  idaction_category INTEGER(10) UNSIGNED NOT NULL,
												  price FLOAT NOT NULL,
												  quantity INTEGER(10) UNSIGNED NOT NULL,
												  deleted TINYINT(1) UNSIGNED NOT NULL,
												  
												  PRIMARY KEY(idvisit, idorder, idaction_sku),
										          INDEX index_idsite_servertime ( idsite, server_time )
												)  DEFAULT CHARSET=utf8 '=> false,

			'ALTER IGNORE TABLE `'. Piwik_Common::prefixTable('log_visit') .'`
				 ADD  visitor_days_since_order SMALLINT(5) UNSIGNED NOT NULL AFTER visitor_days_since_last,
				 ADD  visit_goal_buyer TINYINT(1) NOT NULL AFTER visit_goal_converted' => false,

			'ALTER IGNORE TABLE `'. Piwik_Common::prefixTable('log_conversion') .'`
				 ADD visitor_days_since_order SMALLINT(5) UNSIGNED NOT NULL AFTER visitor_days_since_first,
				 ADD idorder varchar(100) default NULL AFTER buster,
				 ADD items SMALLINT UNSIGNED DEFAULT NULL,
				 ADD revenue_subtotal float default NULL,
				 ADD revenue_tax float default NULL,
				 ADD  revenue_shipping float default NULL,
				 ADD revenue_discount float default NULL,
				 ADD UNIQUE KEY unique_idsite_idorder (idsite, idorder),
				 MODIFY  idgoal int(10) NOT NULL' => false,
		);
	}

	static function update()
	{
		Piwik_Updater::updateDatabase(__FILE__, self::getSql());
	}
}
