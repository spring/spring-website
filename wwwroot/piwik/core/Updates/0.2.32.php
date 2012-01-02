<?php
/**
 * Piwik - Open source web analytics
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id: 0.2.32.php 2968 2010-08-20 15:26:33Z vipsoft $
 *
 * @category Piwik
 * @package Updates
 */

/**
 * @package Updates
 */
class Piwik_Updates_0_2_32 extends Piwik_Updates
{
	static function getSql($schema = 'Myisam')
	{
		return array(
			// 0.2.32 [941]
			'ALTER TABLE `'. Piwik_Common::prefixTable('access') .'`
				CHANGE `login` `login` VARCHAR( 100 ) NOT NULL' => false,
			'ALTER TABLE `'. Piwik_Common::prefixTable('user') .'`
				CHANGE `login` `login` VARCHAR( 100 ) NOT NULL' => false,
			'ALTER TABLE `'. Piwik_Common::prefixTable('user_dashboard') .'`
				CHANGE `login` `login` VARCHAR( 100 ) NOT NULL' => '1146',
			'ALTER TABLE `'. Piwik_Common::prefixTable('user_language') .'`
				CHANGE `login` `login` VARCHAR( 100 ) NOT NULL' => '1146',
		);
	}

	static function update()
	{
		Piwik_Updater::updateDatabase(__FILE__, self::getSql());
	}
}
