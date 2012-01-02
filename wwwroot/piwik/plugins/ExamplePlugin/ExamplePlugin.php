<?php
/**
 * Piwik - Open source web analytics
 * 
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id: ExamplePlugin.php 4421 2011-04-12 07:02:31Z matt $
 * 
 * @category Piwik_Plugins
 * @package Piwik_ExamplePlugin
 */

/**
 *
 * @package Piwik_ExamplePlugin
 */
class Piwik_ExamplePlugin extends Piwik_Plugin
{
	/**
	 * Return information about this plugin.
	 *
	 * @see Piwik_Plugin
	 *
	 * @return array
	 */
	public function getInformation()
	{
		return array(
			'description' => Piwik_Translate('ExamplePlugin_PluginDescription'),
			'homepage' => 'http://piwik.org/',
			'author' => 'Piwik',
			'author_homepage' => 'http://piwik.org/',
			'license' => 'GPL v3 or later',
			'license_homepage' => 'http://www.gnu.org/licenses/gpl.html',
			'version' => '0.1',
			'translationAvailable' => true,
		);
	}
	
	public function getListHooksRegistered()
	{
		return array(
//			'Controller.renderView' => 'addUniqueVisitorsColumnToGivenReport',
		);
	}
	
	function addUniqueVisitorsColumnToGivenReport($notification)
	{
		$view = $notification->getNotificationInfo();
		$view = $view['view'];
		if($view->getCurrentControllerName() == 'Referers'
			&& $view->getCurrentControllerAction() == 'getWebsites')
		{
			$view->addColumnToDisplay('nb_uniq_visitors');
		}
	}
	
	function postLoad()
	{
		// we register the widgets so they appear in the "Add a new widget" window in the dashboard
		// Note that the first two parameters can be either a normal string, or an index to a translation string
		Piwik_AddWidget('ExamplePlugin_exampleWidgets', 'ExamplePlugin_exampleWidget', 'ExamplePlugin', 'exampleWidget');
		Piwik_AddWidget('ExamplePlugin_exampleWidgets', 'ExamplePlugin_blogPiwikRss', 'ExamplePlugin', 'blogPiwik');
		Piwik_AddWidget('ExamplePlugin_exampleWidgets', 'ExamplePlugin_photostreamMatt', 'ExamplePlugin', 'photostreamMatt');
		Piwik_AddWidget('ExamplePlugin_exampleWidgets', 'ExamplePlugin_piwikForumVisits', 'ExamplePlugin', 'piwikDownloads');
	}
}

/**
 *
 * @package Piwik_ExamplePlugin
 */
class Piwik_ExamplePlugin_Controller extends Piwik_Controller
{	
	/**
	 * Go to /piwik/?module=ExamplePlugin&action=helloWorld to execute this method
	 *
	 */
	function helloWorld()
	{
		echo "<p>Hello world! <br />";
		echo "Happy coding with Piwik :)</p>";
	}
	
	/**
	 * See the result on piwik/?module=ExamplePlugin&action=exampleWidget
	 * or in the dashboard > Add a new widget 
	 *
	 */
	function exampleWidget()
	{
		echo "<p>Hello world! <br /> You can output whatever you want in widgets, and put them on dashboard or everywhere on the web (in your blog, website, etc.).
		<br />Widgets can include graphs, tables, flash, text, images, etc.
		<br />It's very easy to create a new plugin and widgets in Piwik. Have a look at this example file (/plugins/ExamplePlugin/ExamplePlugin.php).
		<div id='happycoding'><i>Happy coding!</i></div>
		<div id='jsenabled'>You can easily use Jquery in widgets</div>
		<p>
		<script type=\"text/javascript\">$('#happycoding').hide().fadeIn(5000);$('#jsenabled').hide().css({'color':'red'}).fadeIn(10000);</script>";
	}

	/**
	 * Embed Piwik.org blog using widgetbox.com widget code
	 */
	function blogPiwik()
	{
		echo '<object classid="clsid:d27cdb6e-ae6d-11cf-96b8-444553540000" codebase="http://fpdownload.macromedia.com/pub/shockwave/cabs/flash/swflash.cab#version=9,0,0,0" type="application/x-shockwave-flash" width="400px" height="343px" id="InsertWidget_0bf84c7c-70b5-41c1-adbc-6f4f823c598c" align="middle"><param name="movie" value="http://widgetserver.com/syndication/flash/wrapper/InsertWidget.swf"/><param name="quality" value="high" /><param name="wmode" value="transparent" /><param name="menu" value="false" /><param name="flashvars" value="r=2&appId=0bf84c7c-70b5-41c1-adbc-6f4f823c598c" /> <embed src="http://widgetserver.com/syndication/flash/wrapper/InsertWidget.swf"  name="InsertWidget_0bf84c7c-70b5-41c1-adbc-6f4f823c598c"  width="400px" height="343px" quality="high" menu="false" pluginspage="http://www.macromedia.com/go/getflashplayer" type="application/x-shockwave-flash" wmode="transparent" align="middle" flashvars="r=2&appId=0bf84c7c-70b5-41c1-adbc-6f4f823c598c" /></object>';
	}
	
	function photostreamMatt()
	{
		echo '<iframe align=center src=http://www.flickr.com/slideShow/index.gne?user_id=34965144@N00&set_id=72157602308487455 frameBorder=0 width=380 scrolling=no height=500></iframe> ';
	}
	
	/**
	 * this widgets shows how to make a remote API request to piwik.org 
	 * you find the main JS code in templates/piwikDownloadCount.tpl
	 */
	function piwikDownloads()
	{
		$view = Piwik_View::factory('piwikDownloads'); 
		$this->setGeneralVariablesView($view);
		echo $view->render();
	}
	
	/**
	 * This method displays a text containing an help about "How to build plugins for Piwik".
	 * This help is then used on http://piwik.org/docs/plugins/functions
	 *
	 */
	function index()
	{
		$out = '';
		$out .= '<i>This page aims to list the different functions you can use when programming plugins for Piwik.</i><br />';
		$out .= '<b>Be careful, the following APIs may change in the near future as Piwik is still in development.</b><br />';

		$out .= '<h2>General</h2>';
		$out .= '<h3>Accessible from your plugin controller</h3>';
		
		$out .= '<code>$this->date</code> = current selected <b>Piwik_Date</b> object (<a href="http://dev.piwik.org/trac/browser/trunk/core/Date.php">class</a>)<br />';
		$out .= '<code>$period = Piwik_Common::getRequestVar("period");</code> - Get the current selected period<br />';
		$out .= '<code>$idSite = Piwik_Common::getRequestVar("idSite");</code> - Get the selected idSite<br />';
		$out .= '<code>$site = new Piwik_Site($idSite);</code> - Build the Piwik_Site object (<a href="http://dev.piwik.org/trac/browser/trunk/core/Site.php">class</a>)<br />';
		$out .= '<code>$this->str_date</code> = current selected date in YYYY-MM-DD format<br />';
		
		$out .= '<h3>Misc</h3>';
		$out .= '<code>Piwik_AddMenu( $mainMenuName, $subMenuName, $url );</code> - Adds an entry to the menu in the Piwik interface (See the example in the <a href="http://dev.piwik.org/trac/browser/tags/1.0/plugins/UserCountry/UserCountry.php#L76">UserCountry Plugin file</a>)<br />';
		$out .= '<code>Piwik_AddWidget( $widgetCategory, $widgetName, $controllerName, $controllerAction, $customParameters = array());</code> - Adds a widget that users can add in the dashboard, or export using the Widgets link at the top of the screen. See the example in the <a href="http://dev.piwik.org/trac/browser/tags/1.0/plugins/UserCountry/UserCountry.php#L70">UserCountry Plugin file</a> or any other plugin)<br />';
		$out .= '<code>Piwik_Common::prefixTable("site")</code> = <b>' . Piwik_Common::prefixTable("site") . '</b><br />';
		
		
		$out .= '<h2>User access</h2>';
		$out .= '<code>Piwik::getCurrentUserLogin()</code> = <b>' . Piwik::getCurrentUserLogin() . '</b><br />';
		$out .= '<code>Piwik::isUserHasSomeAdminAccess()</code> = <b>' . self::boolToString(Piwik::isUserHasSomeAdminAccess()) . '</b><br />';
		$out .= '<code>Piwik::isUserHasAdminAccess( array $idSites = array(1,2) )</code> = <b>' . self::boolToString(Piwik::isUserHasAdminAccess(array(1,2) )) . '</b><br />';
		$out .= '<code>Piwik::isUserHasViewAccess( array $idSites = array(1) ) </code> = <b>' . self::boolToString(Piwik::isUserHasViewAccess(array(1))) . '</b><br />';
		$out .= '<code>Piwik::isUserIsSuperUser()</code> = <b>' . self::boolToString(Piwik::isUserIsSuperUser()) . '</b><br />';
		
		$out .= '<h2>Execute SQL queries</h2>';
		$txtQuery = "SELECT token_auth FROM ".Piwik_Common::prefixTable('user')." WHERE login = ?";
		$result = Piwik_FetchOne($txtQuery, array('anonymous'));
		$out .= '<code>Piwik_FetchOne("'.$txtQuery.'", array("anonymous"))</code> = <b>' . var_export($result,true) . '</b><br />';
		$out .= '<br />';
		
		$query = Piwik_Query($txtQuery, array('anonymous'));
		$fetched = $query->fetch();
		$token_auth = $fetched['token_auth'];
		
		$out .= '<code>$query = Piwik_Query("'.$txtQuery.'", array("anonymous"))</code><br />';
		$out .= '<code>$fetched = $query->fetch();</code><br />';
		$out .= 'At this point, we have: <code>$fetched[\'token_auth\'] == <b>'.var_export($token_auth,true) . '</b></code><br />';
		
  		$out .= '<h2>Example Sites information API</h2>';
		$out .= '<code>Piwik_SitesManager_API::getInstance()->getSitesWithViewAccess()</code> = <b><pre>' .var_export(Piwik_SitesManager_API::getInstance()->getSitesWithViewAccess(),true) . '</pre></b><br />';
		$out .= '<code>Piwik_SitesManager_API::getInstance()->getSitesWithAdminAccess()</code> = <b><pre>' .var_export(Piwik_SitesManager_API::getInstance()->getSitesWithAdminAccess(),true) . '</pre></b><br />';

		$out .= '<h2>Example API  Users information</h2>';
		$out .= 'View the list of API methods you can call on <a href="http://piwik.org/docs/analytics-api/reference">API reference</a><br />';
		$out .= 'For example you can try <code>Piwik_UsersManager_API::getInstance()->getUsersSitesFromAccess("view");</code> or <code>Piwik_UsersManager_API::getInstance()->deleteUser("userToDelete");</code><br />';
		
		$out .= '<h2>Javascript in Piwik</h2>';
		$out .= '<h3>i18n internationalization</h3>';
		$out .= 'In order to translate strings within Javascript code, you can use the javascript function _pk_translate( token );.
				<ul><li>The "token" parameter is the string unique key found in the translation file. For this token string to be available in Javascript, you must
				suffix your token by "_js" in the language file. For example, you can add <code>\'Goals_AddGoal_js\' => \'Add Goal\',</code> in the lang/en.php file</li>
				<li>You then need to instruct Piwik to load your Javascript translations for your plugin; by default, all translation strings are not loaded in Javascript for performance reasons. This can be done by calling a custom-made Smarty modifier before the Javascript code requiring translations, eg. 
					<code>{loadJavascriptTranslations plugins=\'$YOUR_PLUGIN_NAME\'}</code>. In our previous example, the $YOUR_PLUGIN_NAME being Goals, we would write <code>{loadJavascriptTranslations plugins=\'Goals\'}</code>
					</li><li>You can then print this string from your JS code by doing <code>_pk_translate(\'Goals_AddGoal_js\');</code>.
					</li></ul>';

		$out .= '<h3>Reload a widget in the dashboard</h3>';
		$out .= 'It is sometimes useful to reload one widget in the dashboard (for example, every 20 seconds for a real time widget, or after a setting change). 
					You can easily force your widget to reload in the dashboard by calling the helper function <code>piwik.dashboardObject.reloadEnclosingWidget($(this));</code>.';
		
		$out .= '<h2>Smarty plugins</h2>';
		$out .= 'There are some builtin plugins for Smarty especially developped for Piwik. <br />
				You can find them on the <a href="http://dev.piwik.org/trac/browser/trunk/core/SmartyPlugins">SVN at /trunk/core/SmartyPlugins</a>. <br />
				More documentation to come about smarty plugins.<br />';
		
		echo $out;
	}
	
	static private function boolToString($bool)
	{
		return $bool ? "true" : "false";
	}
}
