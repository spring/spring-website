<?php
/**
 * Piwik - Open source web analytics
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id: 1.6-rc1.php 5305 2011-10-14 06:25:09Z matt $
 *
 * @category Piwik
 * @package Updates
 */

/**
 * @package Updates
 */
class Piwik_Updates_1_6_rc1 extends Piwik_Updates
{
	static function update()
	{
		try {
			Piwik_PluginsManager::getInstance()->activatePlugin('ImageGraph');
		} catch(Exception $e) {
		}
	}
}

