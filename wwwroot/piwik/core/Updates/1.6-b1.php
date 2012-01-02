<?php
/**
 * Piwik - Open source web analytics
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id: 1.6-b1.php 5176 2011-09-18 07:25:19Z matt $
 *
 * @category Piwik
 * @package Updates
 */

/**
 * @package Updates
 */
class Piwik_Updates_1_6_b1 extends Piwik_Updates
{
	static function getSql($schema = 'Myisam')
	{
		return array(
			'ALTER TABLE `'. Piwik_Common::prefixTable('log_conversion_item') .'`
				 ADD idaction_category2 INTEGER(10) UNSIGNED NOT NULL AFTER idaction_category,
				 ADD idaction_category3 INTEGER(10) UNSIGNED NOT NULL,
				 ADD idaction_category4 INTEGER(10) UNSIGNED NOT NULL,
				 ADD idaction_category5 INTEGER(10) UNSIGNED NOT NULL' => false,
			'ALTER TABLE `'. Piwik_Common::prefixTable('log_visit') .'`
				 CHANGE custom_var_k1 custom_var_k1 VARCHAR(200) DEFAULT NULL,
				 CHANGE custom_var_v1 custom_var_v1 VARCHAR(200) DEFAULT NULL,
				 CHANGE custom_var_k2 custom_var_k2 VARCHAR(200) DEFAULT NULL,
				 CHANGE custom_var_v2 custom_var_v2 VARCHAR(200) DEFAULT NULL,
				 CHANGE custom_var_k3 custom_var_k3 VARCHAR(200) DEFAULT NULL,
				 CHANGE custom_var_v3 custom_var_v3 VARCHAR(200) DEFAULT NULL,
				 CHANGE custom_var_k4 custom_var_k4 VARCHAR(200) DEFAULT NULL,
				 CHANGE custom_var_v4 custom_var_v4 VARCHAR(200) DEFAULT NULL,
				 CHANGE custom_var_k5 custom_var_k5 VARCHAR(200) DEFAULT NULL,
				 CHANGE custom_var_v5 custom_var_v5 VARCHAR(200) DEFAULT NULL' => false,
			'ALTER TABLE `'. Piwik_Common::prefixTable('log_conversion') .'`
				 CHANGE custom_var_k1 custom_var_k1 VARCHAR(200) DEFAULT NULL,
				 CHANGE custom_var_v1 custom_var_v1 VARCHAR(200) DEFAULT NULL,
				 CHANGE custom_var_k2 custom_var_k2 VARCHAR(200) DEFAULT NULL,
				 CHANGE custom_var_v2 custom_var_v2 VARCHAR(200) DEFAULT NULL,
				 CHANGE custom_var_k3 custom_var_k3 VARCHAR(200) DEFAULT NULL,
				 CHANGE custom_var_v3 custom_var_v3 VARCHAR(200) DEFAULT NULL,
				 CHANGE custom_var_k4 custom_var_k4 VARCHAR(200) DEFAULT NULL,
				 CHANGE custom_var_v4 custom_var_v4 VARCHAR(200) DEFAULT NULL,
				 CHANGE custom_var_k5 custom_var_k5 VARCHAR(200) DEFAULT NULL,
				 CHANGE custom_var_v5 custom_var_v5 VARCHAR(200) DEFAULT NULL' => false,
			'ALTER TABLE `'. Piwik_Common::prefixTable('log_link_visit_action') .'`
				 CHANGE custom_var_k1 custom_var_k1 VARCHAR(200) DEFAULT NULL,
				 CHANGE custom_var_v1 custom_var_v1 VARCHAR(200) DEFAULT NULL,
				 CHANGE custom_var_k2 custom_var_k2 VARCHAR(200) DEFAULT NULL,
				 CHANGE custom_var_v2 custom_var_v2 VARCHAR(200) DEFAULT NULL,
				 CHANGE custom_var_k3 custom_var_k3 VARCHAR(200) DEFAULT NULL,
				 CHANGE custom_var_v3 custom_var_v3 VARCHAR(200) DEFAULT NULL,
				 CHANGE custom_var_k4 custom_var_k4 VARCHAR(200) DEFAULT NULL,
				 CHANGE custom_var_v4 custom_var_v4 VARCHAR(200) DEFAULT NULL,
				 CHANGE custom_var_k5 custom_var_k5 VARCHAR(200) DEFAULT NULL,
				 CHANGE custom_var_v5 custom_var_v5 VARCHAR(200) DEFAULT NULL' => false,
		);
	}

	static function update()
	{
		Piwik_Updater::updateDatabase(__FILE__, self::getSql());
	}
}
