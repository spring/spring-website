<?php
/**
 * Piwik - Open source web analytics
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id: FormLogin.php 2968 2010-08-20 15:26:33Z vipsoft $
 *
 * @category Piwik_Plugins
 * @package Piwik_Login
 */

/**
 *
 * @package Piwik_Login
 */
class Piwik_Login_FormLogin extends Piwik_QuickForm2
{
	function __construct( $id = 'loginform', $method = 'post', $attributes = null, $trackSubmit = false)
	{
		parent::__construct($id,  $method, $attributes, $trackSubmit);
	}

	function init()
	{
		$this->addElement('text', 'form_login')
		     ->addRule('required', Piwik_Translate('General_Required', Piwik_Translate('General_Username')));

		$this->addElement('password', 'form_password')
		     ->addRule('required', Piwik_Translate('General_Required', Piwik_Translate('Login_Password')));

		$this->addElement('hidden', 'form_nonce');

		$this->addElement('checkbox', 'form_rememberme');

		$this->addElement('submit', 'submit');

		// default values
		$this->addDataSource(new HTML_QuickForm2_DataSource_Array(array(
			'form_rememberme' => 0,
		)));
	}
}
