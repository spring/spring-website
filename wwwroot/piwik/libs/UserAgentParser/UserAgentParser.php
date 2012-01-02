<?php
/**
 * Copyright 2009, 2010  Matthieu Aubry
 * All rights reserved.
 *
 * @link http://dev.piwik.org/trac/browser/trunk/libs/UserAgentParser
 * @license http://www.opensource.org/licenses/bsd-license.php BSD License
 * @version $Id: UserAgentParser.php 5273 2011-10-08 17:15:06Z vipsoft $
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are
 * met:
 *
 * * Redistributions of source code must retain the above copyright
 *   notice, this list of conditions and the following disclaimer.
 *
 * * Redistributions in binary form must reproduce the above copyright
 *   notice, this list of conditions and the following disclaimer in the
 *   documentation and/or other materials provided with the distribution.
 *
 * * Neither the name of Matthieu Aubry nor the names of its contributors
 *   may be used to endorse or promote products derived from this
 *   software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

/**
 * Example usage
 * 
 * Browser info:
 * var_dump(UserAgentParser::getBrowser($_SERVER['HTTP_USER_AGENT']));
 * 
 * Outputs:
 * array
 * 	'id' => 'FF' 
 *  'name' => 'Firefox'
 *  'short_name' => 'Firefox'
 *  'version' => '3.0'
 *  'major_number' => '3'
 *  'minor_number' => '0'
 * 
 * Operating System info:
 * var_dump(UserAgentParser::getOperatingSystem($_SERVER['HTTP_USER_AGENT']));
 *
 * Outputs:
 * array
 *  'id' => 'WXP'
 * 	'name' => 'Windows XP'
 * 	'short_name' => 'Win XP'
 * 
 */
class UserAgentParser 
{
	// browser regex => browser ID
	// if there are aliases, the common name should be last
	static protected $browsers = array(
			'abrowse'						=> 'AB',
			'amaya'							=> 'AM',
			'amigavoyager'					=> 'AV',
			'amiga-aweb'					=> 'AW',
			'arora'							=> 'AR',
			'beonex'						=> 'BE',

			// BlackBerry smartphones and tablets
			'blackberry'					=> 'BB', // BlackBerry 6 and PlayBook adopted webkit
			'playbook'						=> 'BP',

			'browsex'						=> 'BX',

			// Camino (and earlier incarnation)
			'chimera'						=> 'CA',
			'camino'						=> 'CA',

			'cheshire'						=> 'CS',

			// Chrome, Chromium, and ChromePlus
			'chrome'						=> 'CH',

			'cometbird'						=> 'CO',
			'dillo'							=> 'DI',
			'elinks'						=> 'EL',
			'epiphany'						=> 'EP',
			'fennec'						=> 'FE',

			// Dolfin (or Dolphin)
			'dolfin'						=> 'DF',

			// Firefox (in its many incarnations and rebranded versions)
			'phoenix'						=> 'PX',
			'mozilla firebird'				=> 'FB',
			'firebird'						=> 'FB',
			'bonecho'						=> 'FF',
			'minefield'						=> 'FF',
			'namoroka'						=> 'FF',
			'shiretoko'						=> 'FF',
			'granparadiso'					=> 'FF',
			'iceweasel'						=> 'FF',
			'icecat'						=> 'FF',
			'firefox'						=> 'FF',

			'flock'							=> 'FL',
			'fluid'							=> 'FD',
			'galeon'						=> 'GA',
			'google earth'					=> 'GE',
			'hana'							=> 'HA',
			'hotjava'						=> 'HJ',
			'ibrowse'						=> 'IB',
			'icab'							=> 'IC',

			// IE (including shells: Acoo, AOL, Avant, Crazy Browser, Green Browser, KKMAN, Maxathon)
			'msie'							=> 'IE',
			'microsoft internet explorer'	=> 'IE',
			'internet explorer'				=> 'IE',

			'iron'							=> 'IR',
			'kapiko'						=> 'KP',
			'kazehakase'					=> 'KZ',
			'k-meleon'						=> 'KM',
			'konqueror'						=> 'KO',
			'links'							=> 'LI',
			'lynx'							=> 'LX',
			'midori'						=> 'MI',

			// SeaMonkey (formerly Mozilla Suite) (and rebranded versions)
			'mozilla'						=> 'MO',
			'gnuzilla'						=> 'SM',
			'iceape'						=> 'SM',
			'seamonkey'						=> 'SM',

			// NCSA Mosaic (and incarnations)
			'mosaic'						=> 'MC',
			'ncsa mosaic'					=> 'MC',

			// Netscape Navigator
			'navigator'						=> 'NS',
			'netscape6'						=> 'NS',
			'netscape'						=> 'NS',

			'omniweb'						=> 'OW',

			// Opera
			'nitro) opera'					=> 'OP',
			'opera'							=> 'OP',

			// Safari
			'safari'						=> 'SF',
			'applewebkit'					=> 'SF',

			'titanium'						=> 'TI',

			'webos'							=> 'WO',
			'webpro'						=> 'WP',
		);

	// browser family (by layout engine)
	static protected $browserType = array(
			'ie'	 => array('IE'),
			'gecko'  => array('NS', 'PX', 'FF', 'FB', 'CA', 'GA', 'KM', 'MO', 'SM', 'CO', 'FE', 'KP', 'KZ'),
			'khtml'  => array('KO'),
			'webkit' => array('SF', 'CH', 'OW', 'AR', 'EP', 'FL', 'WO', 'AB', 'IR', 'CS', 'FD', 'HA', 'MI', 'GE', 'DF', 'BB', 'BP', 'TI'),
			'opera'  => array('OP'),
		);

	// WebKit version numbers to Apple Safari version numbers (if Version/X.Y.Z not present)
	static protected $safariVersions = array(
			'534.50'	=> array('5', '1'),
			'533.16'	=> array('5', '0'),
			'533.4'		=> array('4', '1'),
			'526.11.2'	=> array('4', '0'),
			'525.26'	=> array('3', '2'),
			'525.13'	=> array('3', '1'),
			'522.11'	=> array('3', '0'),
			'412'		=> array('2', '0'),
			'312'		=> array('1', '3'),
			'125'		=> array('1', '2'),
			'100'		=> array('1', '1'),
			'85'		=> array('1', '0'),
			'73'		=> array('0', '9'),
			'48'		=> array('0', '8'),
		);

	// OmniWeb build numbers to OmniWeb version numbers (if Version/X.Y.Z not present)
	static protected $omniWebVersions = array(
			'622.15'	=> array('5', '11'),
			'622.10'	=> array('5', '10'),
			'622.8'		=> array('5', '9'),
			'622.3'		=> array('5', '8'),
			'621'		=> array('5', '7'),
			'613'		=> array('5', '6'),
			'607'		=> array('5', '5'),
			'563.34'	=> array('5', '1'),
			'558.36'	=> array('5', '0'),
			'496'		=> array('4', '5'),
		);

	// OS regex => OS ID
	static protected $operatingSystems = array(
			'Android'				=> 'AND',
			'Maemo'					=> 'MAE',
			'Linux'					=> 'LIN',

			'WP7'					=> 'WP7',

			'CYGWIN_NT-6.1'			=> 'WI7',
			'Windows NT 6.1'		=> 'WI7',
			'Windows 7'				=> 'WI7',
			'CYGWIN_NT-6.0'			=> 'WVI',
			'Windows NT 6.0'		=> 'WVI',
			'Windows Vista'			=> 'WVI',
			'CYGWIN_NT-5.2'			=> 'WS3',
			'Windows NT 5.2'		=> 'WS3',
			'Windows Server 2003 / XP x64' => 'WS3',
			'CYGWIN_NT-5.1'			=> 'WXP',
			'Windows NT 5.1'		=> 'WXP',
			'Windows XP'			=> 'WXP',
			'CYGWIN_NT-5.0'			=> 'W2K',
			'Windows NT 5.0'		=> 'W2K',
			'Windows 2000'			=> 'W2K',
			'CYGWIN_NT-4.0'			=> 'WNT',
			'Windows NT 4.0'		=> 'WNT',
			'WinNT'					=> 'WNT',
			'Windows NT'			=> 'WNT',
			'CYGWIN_ME-4.90'		=> 'WME',
			'Win 9x 4.90'			=> 'WME',
			'Windows ME'			=> 'WME',
			'CYGWIN_98-4.10'		=> 'W98',
			'Win98'					=> 'W98',
			'Windows 98'			=> 'W98',
			'CYGWIN_95-4.0'			=> 'W95',
			'Win32'					=> 'W95',
			'Win95'					=> 'W95',		
			'Windows 95'			=> 'W95',

			'Windows Phone OS 7.0'	=> 'WP7',
			'Windows Mobile 6.5'	=> 'W65',
			'Windows Mobile 6.1'	=> 'W61',
			'Windows CE'			=> 'WCE',

			'iPod'					=> 'IPD',
			'iPad'					=> 'IPA',
			'iPhone'				=> 'IPH',
//			'iOS'					=> 'IOS',
			'Darwin'				=> 'MAC',
			'Macintosh'				=> 'MAC',
			'Power Macintosh'		=> 'MAC',
			'Mac_PowerPC'			=> 'MAC', 
			'Mac PPC'				=> 'MAC',
			'PPC'					=> 'MAC',
			'Mac PowerPC'			=> 'MAC',
			'Mac OS'				=> 'MAC',

			'webOS'					=> 'WOS',
			'Palm webOS'			=> 'WOS',
			'PalmOS'				=> 'POS',
			'Palm OS'				=> 'POS',

			'BlackBerry'			=> 'BLB',
			'RIM Tablet OS'			=> 'QNX',
			'QNX'					=> 'QNX',

			'SymbOS'				=> 'SYM',
			'Symbian OS'			=> 'SYM',
			'SymbianOS'				=> 'SYM',

			'bada'					=> 'SBA',

			'SunOS'					=> 'SOS',
			'AIX'					=> 'AIX',
			'HP-UX'					=> 'HPX',
			'OpenVMS'				=> 'VMS',

			'FreeBSD'				=> 'BSD',
			'NetBSD'				=> 'NBS',
			'OpenBSD'				=> 'OBS',
			'DragonFly'				=> 'DFB',
			'Syllable'				=> 'SYL',

			'Nintendo Wii'			=> 'WII',
			'Nitro'					=> 'NDS',
			'Nintendo DS '			=> 'NDS',
			'Nintendo DSi'			=> 'DSI',
			'PlayStation Portable'	=> 'PSP',
			'PlayStation 3'			=> 'PS3',

			'IRIX'					=> 'IRI',
			'OSF1'					=> 'T64',
			'OS/2'					=> 'OS2',
			'BEOS'					=> 'BEO',
			'Amiga'					=> 'AMI',
			'AmigaOS'				=> 'AMI',
		);

	static protected $browserIdToName;
	static protected $browserIdToShortName;
	static protected $operatingSystemsIdToName;
	static protected $operatingSystemsIdToShortName;
	static private $init = false;
	
	/**
	 * Returns an array of the OS for the submitted user agent 
	 *		'id' => '',
	 *		'name' => '',
	 *		'short_name' => '',
	 * 
	 * @param string $userAgent
	 * @return string false if OS couldn't be identified, or 3 letters ID (eg. WXP)
	 * @see UserAgentParser/OperatingSystems.php for the list of OS (also available in self::$operatingSystems)
	 */
	static public function getOperatingSystem($userAgent)
	{
		self::init();
		$info = array(
			'id' => '',
			'name' => '',
			'short_name' => '',
		);
		foreach(self::$operatingSystems as $key => $value) {
			if (stristr($userAgent, $key) !== false) {
				$info['id'] = $value;
				break;
			}
		}
		if(empty($info['id'])) {
			return false;
		}
		$info['name'] = self::getOperatingSystemNameFromId($info['id']);
		$info['short_name'] = self::getOperatingSystemShortNameFromId($info['id']);
		return $info;
	}
	
	/**
	 * Returns the browser information array, given a user agent string.
	 * 
	 * @param string $userAgent
	 * @return array false if the browser is "unknown", or 
	 * 				array(		'id' 			=> '', // 2 letters ID, eg. FF 
	 * 							'name' 			=> '', // 2 letters ID, eg. FF 
	 * 							'short_name'	=> '', // 2 letters ID, eg. FF 
	 * 							'major_number' 	=> '', // 2 in firefox 2.0.12
	 * 							'minor_number' 	=> '', // 0 in firefox 2.0.12
	 * 							'version' 		=> '', // major_number.minor_number
	 * 				);
	 * @see self::$browsers for the list of OS 
	 */
	static public function getBrowser($userAgent)
	{
		self::init();

		$info = array(
			'id' 			=> '',
			'name'			=> '',
			'short_name'	=> '',
			'major_number' 	=> '',
			'minor_number' 	=> '',
			'version' 		=> '',
		);

		$browsers = self::$browsers;

		// derivative browsers often clone the base browser's useragent
		unset($browsers['firefox']);
		unset($browsers['mozilla']);
		unset($browsers['safari']);
		unset($browsers['applewebkit']);

		$browsersPattern = str_replace(')', '\)', implode('|', array_keys($browsers)));

		$results = array();

		// Misbehaving IE add-ons
		$userAgent = preg_replace('/[; ]Mozilla\/[0-9.]+ \([^)]+\)/', '', $userAgent);

		// Clean-up BlackBerry device UAs
		$userAgent = preg_replace('~^BlackBerry\d+/~', 'BlackBerry/', $userAgent);

		if (preg_match_all("/($browsersPattern)[\/\sa-z(]*([0-9]+)([\.0-9a-z]+)?/i", $userAgent, $results)
			|| (strpos($userAgent, 'Shiira') === false && preg_match_all("/(firefox|safari)[\/\sa-z(]*([0-9]+)([\.0-9a-z]+)?/i", $userAgent, $results))
			|| preg_match_all("/(applewebkit)[\/\sa-z(]*([0-9]+)([\.0-9a-z]+)?/i", $userAgent, $results)
			|| preg_match_all("/^(mozilla)\/([0-9]+)([\.0-9a-z-]+)?(?: \[[a-z]{2}\])? (?:\([^)]*\))$/i", $userAgent, $results)
			|| preg_match_all("/^(mozilla)\/[0-9]+(?:[\.0-9a-z-]+)?\s\(.* rv:([0-9]+)([.0-9a-z]+)\) gecko(\/[0-9]{8}|$)(?:.*)/i", $userAgent, $results)
			)
		 {
			// browser code (usually the first match)
			$count = 0;
			$info['id'] = self::$browsers[strtolower($results[1][0])];

			// sometimes there's a better match at the end
			if(($info['id'] == 'IE' || $info['id'] == 'LX') && (count($results[0]) > 1)) {
				$count = count($results[0]) - 1;
				$info['id'] = self::$browsers[strtolower($results[1][$count])];
			}

			// Netscape fix
			if($info['id'] == 'MO' && $count == 0) {
				if(stripos($userAgent, 'PlayStation') !== false) {
					return false;
				}
				if(count($results) == 4) {
				 	$info['id'] = 'NS';
				}
			}
			// BlackBerry devices
			else if(strpos($userAgent, 'BlackBerry') !== false) {
				$info['id'] = 'BB';
			}
			else if(strpos($userAgent, 'RIM Tablet OS') !== false) {
				$info['id'] = 'BP';
			}

			// Version/X.Y.Z override
			if(preg_match_all("/(version)[\/\sa-z(]*([0-9]+)([\.0-9a-z]+)?/i", $userAgent, $newResults))
			{
				$results = $newResults;
				$count = count($results[0])-1;
			}

		 	// major version number (1 in mozilla 1.7)
		 	$info['major_number'] = $results[2][$count];
		 		
		 	// is an minor version number ? If not, 0
		 	$match = array();
		 		
		 	preg_match('/([.\0-9]+)?([\.a-z0-9]+)?/i', $results[3][$count], $match);
		 		
		 	if(isset($match[1])) {
		 		// find minor version number (7 in mozilla 1.7, 9 in firefox 0.9.3)
		 		$dot = strpos(substr($match[1], 1), '.');
		 		if($dot !== false) {
		 			$info['minor_number'] = substr($match[1], 1, $dot);
		 		} 
		 		else {
		 			$info['minor_number'] = substr($match[1], 1);
		 		}
		 	}
		 	else {
		 		$info['minor_number'] = '0';
		 	}
		 	$info['version'] = $info['major_number'] . '.' . $info['minor_number'];

			// IE compatibility mode
			if($info['id'] == 'IE'
				&& strncmp($userAgent, 'Mozilla/4.0', 11) == 0
				&& preg_match('~ Trident/([0-9]+)\.[0-9]+~', $userAgent, $tridentVersion))
			{
				$info['major_number'] = $tridentVersion[1] + 4;
				$info['minor_number'] = '0';
				$info['version'] = $info['major_number'] . '.' .$info['minor_number'];
			}

			// Safari fix
			if($info['id'] == 'SF') {
				foreach(self::$safariVersions as $buildVersion => $productVersion) {
					if(version_compare($info['version'], $buildVersion) >= 0) {
						$info['major_number'] = $productVersion[0];
						$info['minor_number'] = $productVersion[1];
						$info['version'] = $info['major_number'] . '.' . $info['minor_number'];
						break;
					}
				}
			}

			// OmniWeb fix
			if($info['id'] == 'OW') {
				foreach(self::$omniWebVersions as $buildVersion => $productVersion) {
					if(version_compare($info['version'], $buildVersion) >= 0) {
						$info['major_number'] = $productVersion[0];
						$info['minor_number'] = $productVersion[1];
						$info['version'] = $info['major_number'] . '.' . $info['minor_number'];
						break;
					}
				}
			}

			// SeaMonkey fix
			if($info['id'] == 'MO' && $info['version'] == '1.9') {
				$info['id'] = 'SM';
			}

		 	$info['name'] = self::getBrowserNameFromId($info['id']);
		 	$info['short_name'] = self::getBrowserShortNameFromId($info['id']);

		 	return $info;
		 }
		 return false;
	}
	
	static protected function init() {
		if(self::$init) {
			return;
		}
		self::$init = true;
		
		// init browser names and short names
		self::$browserIdToName = array_map('ucwords',array_flip(self::$browsers));
		self::$browserIdToName['AB'] = 'ABrowse';
		self::$browserIdToName['AV'] = 'AmigaVoyager';
		self::$browserIdToName['AW'] = 'Amiga AWeb';
		self::$browserIdToName['BB'] = 'BlackBerry';
		self::$browserIdToName['BP'] = 'PlayBook';
		self::$browserIdToName['BX'] = 'BrowseX';
		self::$browserIdToName['CO'] = 'CometBird';
		self::$browserIdToName['EL'] = 'ELinks';
		self::$browserIdToName['FF'] = 'Firefox';
		self::$browserIdToName['HJ'] = 'HotJava';
		self::$browserIdToName['IB'] = 'IBrowse';
		self::$browserIdToName['IC'] = 'iCab';
		self::$browserIdToName['KM'] = 'K-Meleon';
		self::$browserIdToName['MC'] = 'NCSA Mosaic';
		self::$browserIdToName['OW'] = 'OmniWeb';
		self::$browserIdToName['SF'] = 'Safari';
		self::$browserIdToName['SM'] = 'SeaMonkey';
		self::$browserIdToName['WO'] = 'Palm webOS';
		self::$browserIdToName['WP'] = 'WebPro';
		
		self::$browserIdToShortName = self::$browserIdToName;
		self::$browserIdToShortName['AW'] = 'AWeb';
		self::$browserIdToShortName['FB'] = 'Firebird';
		self::$browserIdToShortName['IE'] = 'IE';
		self::$browserIdToShortName['MC'] = 'Mosaic';
		self::$browserIdToShortName['BP'] = 'PlayBook';
		self::$browserIdToShortName['WO'] = 'webOS';
		
		// init OS names and short names
		self::$operatingSystemsIdToName = array_merge(array_flip(self::$operatingSystems), array(
			'IPD' => 'iPod',
			'IPA' => 'iPad',
			'WME' => 'Windows Me',
			'BEO' => 'BeOS',
			'T64' => 'Tru64',
			'NDS' => 'Nintendo DS',
		));
		self::$operatingSystemsIdToShortName = array_merge(self::$operatingSystemsIdToName, array(
			'PS3' => 'PS3',
			'PSP' => 'PSP',
			'WII' => 'Wii',
			'NDS' => 'DS',
			'DSI' => 'DSi',
			'WI7' => 'Win 7',
			'WVI' => 'Win Vista',
			'WS3' => 'Win S2003',
			'WXP' => 'Win XP',
			'W98' => 'Win 98',
			'W2K' => 'Win 2000', 
			'WNT' => 'Win NT',
			'WME' => 'Win Me',
			'W95' => 'Win 95',
			'WP7' => 'WinPhone 7',
			'W65' => 'WinMo 6.5',
			'W61' => 'WinMo 6.1',
			'WCE' => 'Win CE',
			'WOS' => 'webOS',
			'UNK' => 'Unknown',
		));
	}

	static public function getBrowserNameFromId($browserId)
	{
		self::init();
		if(isset(self::$browserIdToName[$browserId])) {
			return self::$browserIdToName[$browserId];
		}
		return false;
	}

	static public function getBrowserShortNameFromId($browserId)
	{
		self::init();
		if(isset(self::$browserIdToShortName[$browserId])) {
			return self::$browserIdToShortName[$browserId];
		}
		return false;
	}

	static public function getBrowserFamilyFromId($browserId)
	{
		self::init();
		$familyNameToUse = 'unknown';
		foreach(self::$browserType as $familyName => $aBrowsers)
		{			
			if(in_array($browserId, $aBrowsers))
			{
				$familyNameToUse = $familyName;
				break;				
			}
		}
		return $familyNameToUse;	
	}

	static public function getOperatingSystemNameFromId($osId)
	{
		self::init();
		if(isset(self::$operatingSystemsIdToName[$osId])) {
			return self::$operatingSystemsIdToName[$osId];
		}
		return false;
	}
	
	static public function getOperatingSystemShortNameFromId($osId)
	{
		self::init();
		if(isset(self::$operatingSystemsIdToShortName[$osId])) {
			return self::$operatingSystemsIdToShortName[$osId];
		}
		return false;
	}
}
