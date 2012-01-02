<?php
/**
 * Piwik - Open source web analytics
 * 
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id: ColumnCallbackAddMetadata.php 4169 2011-03-23 01:59:57Z matt $
 * 
 * @category Piwik
 * @package Piwik
 */

/**
 * Add a new 'metadata' column to the table based on the value resulting 
 * from a callback function with the parameter being another column's value
 * 
 * For example from the "label" column we can to create an "icon" 'metadata' column 
 * with the icon URI built from the label (LINUX => UserSettings/icons/linux.png)
 * 
 * @package Piwik
 * @subpackage Piwik_DataTable
 */
class Piwik_DataTable_Filter_ColumnCallbackAddMetadata extends Piwik_DataTable_Filter
{
	private $columnToRead;
	private $functionToApply;
	private $functionParameters;
	private $metadataToAdd;
	
	public function __construct( $table, $columnToRead, $metadataToAdd, $functionToApply = null, $functionParameters = null )
	{
		parent::__construct($table);
		$this->functionToApply = $functionToApply;
		$this->functionParameters = $functionParameters;
		$this->columnToRead = $columnToRead;
		$this->metadataToAdd = $metadataToAdd;
	}
	
	public function filter($table)
	{
		foreach($table->getRows() as $key => $row)
		{
			$oldValue = $row->getColumn($this->columnToRead);
			$parameters = array($oldValue);
			if(!is_null($this->functionParameters))
			{
				$parameters = array_merge($parameters, $this->functionParameters);
			}
			if(!is_null($this->functionToApply))
			{
				$newValue = call_user_func_array( $this->functionToApply, $parameters);
			}
			else
			{
				$newValue = $oldValue;
			}
			$row->addMetadata($this->metadataToAdd, $newValue);
		}
	}
}
