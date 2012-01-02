<?php
/**
 * Piwik - Open source web analytics
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id: 1.2.5-rc7.php 4369 2011-04-08 04:44:20Z matt $
 *
 * @category Piwik
 * @package Updates
 */

/**
 * @package Updates
 */
class Piwik_Updates_1_2_5_rc7 extends Piwik_Updates
{
	static function getSql($schema = 'Myisam')
	{
		return array(
		    'ALTER TABLE `'. Piwik_Common::prefixTable('log_visit') .'` 
		    	ADD INDEX index_idsite_idvisitor (idsite, idvisitor)' => false,
		);
	}

	static function update()
	{
		Piwik_Updater::updateDatabase(__FILE__, self::getSql());
	}
}


