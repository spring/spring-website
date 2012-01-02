<?php
/**
 * Piwik - Open source web analytics
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id: 0.6.2.php 2968 2010-08-20 15:26:33Z vipsoft $
 *
 * @category Piwik
 * @package Updates
 */

/**
 * @package Updates
 */
class Piwik_Updates_0_6_2 extends Piwik_Updates
{
	static function update()
	{
		$obsoleteFiles = array(
			PIWIK_INCLUDE_PATH . '/core/Db/Mysqli.php',
		);
		foreach($obsoleteFiles as $obsoleteFile)
		{
			if(file_exists($obsoleteFile))
			{
				@unlink($obsoleteFile);
			}
		}

		$obsoleteDirectories = array(
			PIWIK_INCLUDE_PATH . '/core/Db/Pdo',
		);
		foreach($obsoleteDirectories as $dir)
		{
			if(file_exists($dir))
			{
				Piwik::unlinkRecursive($dir, true);
			}
		}

		// force regeneration of cache files
		Piwik::setUserIsSuperUser();
		$allSiteIds = Piwik_SitesManager_API::getInstance()->getAllSitesId();
		Piwik_Common::regenerateCacheWebsiteAttributes($allSiteIds);
	}
}
