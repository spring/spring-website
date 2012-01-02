<?php
/**
 * Piwik - Open source web analytics
 * 
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id: API.php 4448 2011-04-14 08:20:49Z matt $
 * 
 * @category Piwik_Plugins
 * @package Piwik_DBStats
 */

/**
 * DBStats API is used to request the overall status of the Mysql tables in use by Piwik.
 * 
 * @package Piwik_DBStats
 */
class Piwik_DBStats_API
{
	static private $instance = null;
	static public function getInstance()
	{
		if (self::$instance == null)
		{
			self::$instance = new self;
		}
		return self::$instance;
	}

 	public function getDBStatus()
	{
		Piwik::checkUserIsSuperUser();

		if(function_exists('mysql_connect'))
		{
			$configDb = Zend_Registry::get('config')->database->toArray();
			// we decode the password. Password is html encoded because it's enclosed between " double quotes
			$configDb['password'] = htmlspecialchars_decode($configDb['password']);
			if(!isset($configDb['port']))
			{
				// before 0.2.4 there is no port specified in config file
				$configDb['port'] = '3306';  
			}

			$link   = mysql_connect($configDb['host'], $configDb['username'], $configDb['password']);
			$status = mysql_stat($link);
			mysql_close($link);
			$status = explode("  ", $status);
		}
		else
		{
			$db = Zend_Registry::get('db');

			$fullStatus = $db->fetchAssoc('SHOW STATUS;');
			if(empty($fullStatus)) {
				throw new Exception('Error, SHOW STATUS failed');
			}

			$status = array(
				'Uptime' => $fullStatus['Uptime']['Value'],
				'Threads' => $fullStatus['Threads_running']['Value'],
				'Questions' => $fullStatus['Questions']['Value'],
				'Slow queries' => $fullStatus['Slow_queries']['Value'],
				'Flush tables' => $fullStatus['Flush_commands']['Value'],
				'Open tables' => $fullStatus['Open_tables']['Value'],
//				'Opens: ', // not available via SHOW STATUS
//				'Queries per second avg' =/ // not available via SHOW STATUS
			);
		}

		return $status;
	}
	
	public function getTableStatus($table, $field = '') 
	{
		Piwik::checkUserIsSuperUser();
		$db = Zend_Registry::get('db');
		// http://dev.mysql.com/doc/refman/5.1/en/show-table-status.html
		$tables = $db->fetchAll("SHOW TABLE STATUS LIKE ". $db->quote($table));

		if(!isset($tables[0])) {
			throw new Exception('Error, table or field not found');
		}
		if ($field == '')
		{
			return $tables[0];
		}
		else
		{
			return $tables[0][$field];
		}
	}

	public function getAllTablesStatus() 
	{
		Piwik::checkUserIsSuperUser();
		$db = Zend_Registry::get('db');
		// http://dev.mysql.com/doc/refman/5.1/en/show-table-status.html
		$tablesPiwik =  Piwik::getTablesInstalled();
		$total = array('Name' => 'Total', 'Data_length' => 0, 'Index_length' => 0, 'Rows' => 0);
		$table = array();
		foreach($tablesPiwik as $tableName) 
		{
			$t = $this->getTableStatus($tableName);
			$total['Data_length'] += $t['Data_length'];
			$total['Index_length'] += $t['Index_length'];
			$total['Rows'] += $t['Rows'];
			
			$t['Total_length'] = Piwik::getPrettySizeFromBytes($t['Index_length']+$t['Data_length']);
			$t['Data_length'] = Piwik::getPrettySizeFromBytes($t['Data_length']);
			$t['Index_length'] = Piwik::getPrettySizeFromBytes($t['Index_length']);
			$t['Rows'] = Piwik::getPrettySizeFromBytes($t['Rows']);
			$table[] = $t;
		}
		$total['Total_length'] = Piwik::getPrettySizeFromBytes($total['Data_length']+$total['Index_length']);
		$total['Data_length'] = Piwik::getPrettySizeFromBytes($total['Data_length']);
		$total['Index_length'] = Piwik::getPrettySizeFromBytes($total['Index_length']);
		$total['TotalRows'] = Piwik::getPrettySizeFromBytes($total['Rows']);
		$table['Total'] = $total;
		
		return $table;
	}
}
