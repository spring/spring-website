<?php
/**
 * Piwik - Open source web analytics
 * 
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id: MetadataCallbackAddMetadata.php 4169 2011-03-23 01:59:57Z matt $
 * 
 * @category Piwik
 * @package Piwik
 */

/**
 * Add a new metadata to the table based on the value resulting 
 * from a callback function with the parameter being another metadata value
 * 
 * For example for the searchEngine we have a "metadata" information that gives 
 * the URL of the search engine. We use this URL to add a new "metadata" that gives 
 * the path of the logo for this search engine URL (which has the format URL.png). 
 * 
 * @package Piwik
 * @subpackage Piwik_DataTable
 */
class Piwik_DataTable_Filter_MetadataCallbackAddMetadata extends Piwik_DataTable_Filter
{
	private $metadataToRead;
	private $functionToApply;
	private $metadataToAdd;
	
	public function __construct( $table, $metadataToRead, $metadataToAdd, $functionToApply )
	{
		parent::__construct($table);
		$this->functionToApply = $functionToApply;
		$this->metadataToRead = $metadataToRead;
		$this->metadataToAdd = $metadataToAdd;
	}
	
	public function filter($table)
	{
		foreach($table->getRows() as $key => $row)
		{
			$oldValue = $row->getMetadata($this->metadataToRead);
			$newValue = call_user_func( $this->functionToApply, $oldValue);
			$row->addMetadata($this->metadataToAdd, $newValue);
		}
	}
}
