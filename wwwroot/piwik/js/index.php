<?php
/**
 * Piwik - Open source web analytics
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id: index.php 4642 2011-05-05 21:23:12Z matt $
 */

/**
 * Tracker proxy
 */
if($_SERVER['REQUEST_METHOD'] == 'POST' || !empty($_SERVER['QUERY_STRING'])) {
	include '../piwik.php';
	exit;
}

/**
 * piwik.js proxy
 *
 * @see core/Piwik.php
 */
define('PIWIK_INCLUDE_PATH', '..');
define('PIWIK_DOCUMENT_ROOT', '..');
define('PIWIK_USER_PATH', '..');

require_once PIWIK_INCLUDE_PATH . '/libs/upgradephp/upgrade.php';
require_once PIWIK_INCLUDE_PATH . '/core/Piwik.php';

$file = '../piwik.js';

// There is no cache buster parameter so we don't set Expires: header 
$expireFarFuture = false;
Piwik::serveStaticFile($file, "application/javascript; charset=UTF-8", $expireFarFuture);

exit;
