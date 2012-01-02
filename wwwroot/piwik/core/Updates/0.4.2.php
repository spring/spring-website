<?php
/**
 * Piwik - Open source web analytics
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id: 0.4.2.php 2968 2010-08-20 15:26:33Z vipsoft $
 *
 * @category Piwik
 * @package Updates
 */

/**
 * @package Updates
 */
class Piwik_Updates_0_4_2 extends Piwik_Updates
{
	static function getSql($schema = 'Myisam')
	{
		return array(
			'ALTER TABLE `'. Piwik_Common::prefixTable('log_visit') .'`
				ADD `config_java` TINYINT(1) NOT NULL AFTER `config_flash`' => '1060',
			'ALTER TABLE `'. Piwik_Common::prefixTable('log_visit') .'`
				ADD `config_quicktime` TINYINT(1) NOT NULL AFTER `config_director`' => '1060',
			'ALTER TABLE `'. Piwik_Common::prefixTable('log_visit') .'`
				ADD `config_gears` TINYINT(1) NOT NULL AFTER  `config_windowsmedia`,
				ADD `config_silverlight` TINYINT(1) NOT NULL AFTER `config_gears`' => false,
		);
	}

	// when restoring (possibly) previousy dropped columns, ignore mysql code error 1060: duplicate column
	static function update()
	{
		Piwik_Updater::updateDatabase(__FILE__, self::getSql());
	}
}
