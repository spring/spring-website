<?php
/**
 * Piwik - Open source web analytics
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id: HTMLPurifier.php 3562 2011-01-02 19:58:22Z vipsoft $
 *
 * @category Piwik
 * @package Piwik
 */

/**
 * HTML Purifier class wrapper.
 *
 * @package Piwik
 */
class Piwik_HTMLPurifier
{
	static private $instance = null;

	/**
	 * Returns the singleton HTMLPurifier or a mock object
	 *
	 * @return HTMLPurifier|Piwik_HTMLPurifier
	 */
	static public function getInstance()
	{
		if (self::$instance == null)
		{
			if(file_exists(PIWIK_INCLUDE_PATH . '/libs/HTMLPurifier.php'))
			{
				if(!class_exists('HTMLPurifier_Bootstrap', false))
				{
					HTMLPurifier_Bootstrap::registerAutoload();
				}

				$config = HTMLPurifier_Config::createDefault();
				$config->set('Cache.SerializerPath', PIWIK_USER_PATH . '/tmp/purifier');

				self::$instance = new HTMLPurifier($config);
			}
			else
			{
				$c = __CLASS__;
				self::$instance = new $c();
			}
		}
		return self::$instance;
	}

	public function purify($html, $config = null)
	{
		return $html;
	}
}
