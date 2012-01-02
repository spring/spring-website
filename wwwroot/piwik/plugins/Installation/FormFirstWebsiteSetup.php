<?php
/**
 * Piwik - Open source web analytics
 * 
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id: FormFirstWebsiteSetup.php 4741 2011-05-21 05:41:22Z matt $
 * 
 * @category Piwik_Plugins
 * @package Piwik_Installation
 */

/**
 * 
 * @package Piwik_Installation
 */
class Piwik_Installation_FormFirstWebsiteSetup extends Piwik_QuickForm2
{
	function __construct( $id = 'websitesetupform', $method = 'post', $attributes = null, $trackSubmit = false)
	{
		parent::__construct($id,  $method, $attributes, $trackSubmit);
	}

	function init()
	{
		HTML_QuickForm2_Factory::registerRule('checkTimezone', 'Piwik_Installation_FormFirstWebsiteSetup_Rule_isValidTimezone');

		$urlExample = 'http://example.org';
		$javascriptOnClickUrlExample = "javascript:if(this.value=='$urlExample'){this.value='http://';} this.style.color='black';";

		$timezones = Piwik_SitesManager_API::getInstance()->getTimezonesList();
		$timezones = array_merge(array('No timezone' => Piwik_Translate('SitesManager_SelectACity')), $timezones);

		$this->addElement('text', 'siteName')
		     ->setLabel(Piwik_Translate('Installation_SetupWebSiteName'))
		     ->addRule('required', Piwik_Translate('General_Required', Piwik_Translate('Installation_SetupWebSiteName')));

		$url = $this->addElement('text', 'url')
		            ->setLabel(Piwik_Translate('Installation_SetupWebSiteURL'));
		$url->setAttribute('style', 'color:rgb(153, 153, 153);');
		$url->setAttribute('onfocus', $javascriptOnClickUrlExample);
		$url->setAttribute('onclick', $javascriptOnClickUrlExample);
		$url->addRule('required', Piwik_Translate('General_Required', Piwik_Translate('Installation_SetupWebSiteURL')));

		$tz = $this->addElement('select', 'timezone')
		           ->setLabel(Piwik_Translate('Installation_Timezone'))
		           ->loadOptions($timezones);
		$tz->addRule('required', Piwik_Translate('General_Required', Piwik_Translate('Installation_Timezone')));
		$tz->addRule('checkTimezone', Piwik_Translate('General_NotValid', Piwik_Translate('Installation_Timezone')));
		$tz = $this->addElement('select', 'ecommerce')
		           ->setLabel(Piwik_Translate('Goals_Ecommerce'))
		           ->loadOptions(array(
		           					0 => Piwik_Translate('SitesManager_NotAnEcommerceSite'),
		           					1 => Piwik_Translate('SitesManager_EnableEcommerce'),
		           					));

		$this->addElement('submit', 'submit', array('value' => Piwik_Translate('General_Next').' »', 'class' => 'submit'));

		// default values
		$this->addDataSource(new HTML_QuickForm2_DataSource_Array(array(
			'url' => $urlExample,
		)));
	}	
}

/**
 * Timezone validation rule
 *
 * @package Piwik_Installation
 */
class Piwik_Installation_FormFirstWebsiteSetup_Rule_isValidTimezone extends HTML_QuickForm2_Rule
{
	function validateOwner()
	{
		try {
    		$timezone = $this->owner->getValue();
    		if(!empty($timezone))
    		{
    			Piwik_SitesManager_API::getInstance()->setDefaultTimezone($timezone);
    		}
		} catch(Exception $e) {
			return false;
		}
		return true;
	}
}
