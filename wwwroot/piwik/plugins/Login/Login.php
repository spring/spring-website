<?php
/**
 * Piwik - Open source web analytics
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id: Login.php 4406 2011-04-11 16:17:23Z vipsoft $
 *
 * @category Piwik_Plugins
 * @package Piwik_Login
 */

/**
 *
 * @package Piwik_Login
 */
class Piwik_Login extends Piwik_Plugin
{
	public function getInformation()
	{
		$info = array(
			'description' => Piwik_Translate('Login_PluginDescription'),
			'author' => 'Piwik',
			'author_homepage' => 'http://piwik.org/',
			'version' => Piwik_Version::VERSION,
		);
		return $info;
	}

	function getListHooksRegistered()
	{
		$hooks = array(
			'FrontController.initAuthenticationObject'	=> 'initAuthenticationObject',
			'FrontController.NoAccessException'		=> 'noAccess',
			'API.Request.authenticate' => 'ApiRequestAuthenticate',
			'Login.initSession' => 'initSession',
		);
		return $hooks;
	}

	/**
	 * Redirects to Login form with error message.
	 * Listens to FrontController.NoAccessException hook.
	 *
	 * @param Piwik_Event_Notification $notification
	 */
	function noAccess( $notification )
	{
		$exception  = $notification->getNotificationObject();
		$exceptionMessage = $exception->getMessage();

		$controller = new Piwik_Login_Controller();
		$controller->login($exceptionMessage);
	}

	/**
	 * Set login name and autehntication token for authentication request.
	 * Listens to API.Request.authenticate hook.
	 *
	 * @param Piwik_Event_Notification $notification
	 */
	function ApiRequestAuthenticate($notification)
	{
		$tokenAuth = $notification->getNotificationObject();
		Zend_Registry::get('auth')->setLogin($login = null);
		Zend_Registry::get('auth')->setTokenAuth($tokenAuth);
	}

	/**
	 * Initializes the authentication object.
	 * Listens to FrontController.initAuthenticationObject hook.
	 *
	 * @param Piwik_Event_Notification $notification
	 */
	function initAuthenticationObject($notification)
	{
		$auth = new Piwik_Login_Auth();
		Zend_Registry::set('auth', $auth);

		$action = Piwik::getAction();
		if(Piwik::getModule() === 'API'
			&& (empty($action) || $action == 'index'))
		{
			return;
		}

		$authCookieName = Zend_Registry::get('config')->General->login_cookie_name;
		$authCookieExpiry = 0;
		$authCookiePath = Zend_Registry::get('config')->General->login_cookie_path;
		$authCookie = new Piwik_Cookie($authCookieName, $authCookieExpiry, $authCookiePath);
		$defaultLogin = 'anonymous';
		$defaultTokenAuth = 'anonymous';
		if($authCookie->isCookieFound())
		{
			$defaultLogin = $authCookie->get('login');
			$defaultTokenAuth = $authCookie->get('token_auth');
		}
		$auth->setLogin($defaultLogin);
		$auth->setTokenAuth($defaultTokenAuth);
	}
	
	/**
	 * Authenticate user and initializes the session.
	 * Listens to Login.initSession hook.
	 *
	 * @param Piwik_Event_Notification $notification
	 */
	function initSession($notification)
	{
		$info = $notification->getNotificationObject();
		$login = $info['login'];
		$md5Password = $info['md5Password'];
		$rememberMe = $info['rememberMe'];
		
		$tokenAuth = Piwik_UsersManager_API::getInstance()->getTokenAuth($login, $md5Password);

		$auth = Zend_Registry::get('auth');
		$auth->setLogin($login);
		$auth->setTokenAuth($tokenAuth);
		$authResult = $auth->authenticate();

		$authCookieName = Zend_Registry::get('config')->General->login_cookie_name;
		$authCookieExpiry = $rememberMe ? time() + Zend_Registry::get('config')->General->login_cookie_expire : 0;
		$authCookiePath = Zend_Registry::get('config')->General->login_cookie_path;
		$cookie = new Piwik_Cookie($authCookieName, $authCookieExpiry, $authCookiePath);
		if(!$authResult->isValid())

		{
			$cookie->delete();
			throw new Exception(Piwik_Translate('Login_LoginPasswordNotCorrect'));
		}

		$cookie->set('login', $login);
		$cookie->set('token_auth', $auth->getHashTokenAuth($login, $authResult->getTokenAuth()));
		$cookie->setSecure(Piwik::isHttps());
		$cookie->setHttpOnly(true);
		$cookie->save();

		@Piwik_Session::regenerateId();
	}
}
