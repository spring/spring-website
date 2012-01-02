<?php
/**
 * Piwik - Open source web analytics
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id: 0.4.4.php 2968 2010-08-20 15:26:33Z vipsoft $
 *
 * @category Piwik
 * @package Updates
 */

/**
 * @package Updates
 */
class Piwik_Updates_0_4_4 extends Piwik_Updates
{
	static function update()
	{
		$obsoleteFile = PIWIK_DOCUMENT_ROOT . '/libs/open-flash-chart/php-ofc-library/ofc_upload_image.php';
		if(file_exists($obsoleteFile))
		{
			$rc = @unlink($obsoleteFile);
			if(!$rc)
			{
				throw new Exception(Piwik_TranslateException('General_ExceptionUndeletableFile', array($obsoleteFile))); 
			}
		}
	}
}
