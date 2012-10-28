<?php
/**
 * Piwik - Open source web analytics
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id: 1.7.2-rc7.php 6146 2012-04-01 06:42:16Z matt $
 *
 * @category Piwik
 * @package Updates
 */

/**
 * @package Updates
 */
class Piwik_Updates_1_7_2_rc7 extends Piwik_Updates
{
	static function getSql($schema = 'Myisam')
	{
		return array(
		    'ALTER TABLE `'. Piwik_Common::prefixTable('user_dashboard') .'`
		        ADD `name` VARCHAR( 100 ) NULL DEFAULT NULL AFTER  `iddashboard`' => false,
		);
	}

	static function update()
	{
		try {
			$dashboards = Piwik_FetchAll('SELECT * FROM `'. Piwik_Common::prefixTable('user_dashboard') .'`');
			foreach($dashboards AS $dashboard) {
				$idDashboard = $dashboard['iddashboard'];
				$login       = $dashboard['login'];
				$layout      = $dashboard['layout'];
				$layout      = html_entity_decode($layout);
				$layout      = str_replace("\\\"", "\"", $layout);
				Piwik_Query('UPDATE `'. Piwik_Common::prefixTable('user_dashboard') .'` SET layout = ? WHERE iddashboard = ? AND login = ?', array($layout, $idDashboard, $login));
			}
			Piwik_Updater::updateDatabase(__FILE__, self::getSql());
		}
		catch(Exception $e){}
	}
}
