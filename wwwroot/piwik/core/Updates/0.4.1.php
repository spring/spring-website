<?php
/**
 * Piwik - Open source web analytics
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id: 0.4.1.php 2968 2010-08-20 15:26:33Z vipsoft $
 *
 * @category Piwik
 * @package Updates
 */

/**
 * @package Updates
 */
class Piwik_Updates_0_4_1 extends Piwik_Updates
{
	static function getSql($schema = 'Myisam')
	{
		return array(
			'ALTER TABLE `'. Piwik_Common::prefixTable('log_conversion') .'`
				CHANGE `idlink_va` `idlink_va` INT(11) DEFAULT NULL' => false,
			'ALTER TABLE `'. Piwik_Common::prefixTable('log_conversion') .'`
				CHANGE `idaction` `idaction` INT(11) DEFAULT NULL' => '1054',
		);
	}

	static function update()
	{
		Piwik_Updater::updateDatabase(__FILE__, self::getSql());
	}
}
