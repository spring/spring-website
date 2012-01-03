<?php
/**
 * See skin.txt
 *
 * @todo document
 * @addtogroup Skins
 */

if( !defined( 'MEDIAWIKI' ) )
	die( -1 );

/**
 * @todo document
 * @addtogroup Skins
 */
class SkinSpringNew extends Skin {

	protected $searchboxes = '';
	// How many search boxes have we made?  Avoid duplicate id's.

	function getStylesheet() {
		return '../../skins/mediawiki/spring.css';
	}
	function getSkinName() {
		return "springnew";
	}

	function doBeforeContent() {
		global $wgOut;
		$s = "";
		$qb = $this->qbSetting();
		$mainPageObj = Title::newMainPage();

		$s .= file_get_contents('../templates/header.html');
		$title = htmlspecialchars($wgOut->getPageTitle());

		$s = str_replace('{PAGE_TITLE}', $title, $s);
		$s .= '<tr><td>';
		$s .= '<table border="0" cellpadding="0" cellspacing="0" width="760">';
		$s .= '<tr>';
		$s .= '<td bgcolor="#20292E" width="1"><img src="/images/pixel.gif" height="10" width="1" /><br /></td>';
		$s .= '<td bgcolor="#4C626F" width="758">';

		$s .= "\n<div id='content'>\n";


		//$s .= '<table border="0" cellpadding="0" cellspacing="0" width="758"><tr>';
		//$s .= '<td width="10"><img src="/images/pixel.gif" height="10" width="10" /><br /></td>';
		//$s .= '<td width="738">';

		$s .= "<div id='article'>";

		$notice = wfGetSiteNotice();
		if( $notice ) {
			$s .= "\n<div id='siteNotice'>$notice</div>\n";
		}
		//$s .= $this->pageTitle();
//		$s .= $this->pageSubtitle() . "\n";

		return $s;
	}

	function doAfterContent()
	{
		global $wgUser, $wgOut;

		$s = "\n</div><br clear='all' />\n";

		//$s .= '</td>';
		//$s .= '<td width="10"><img src="/images/pixel.gif" height="10" width="10" /><br /></td>';
		//$s .= '</tr></table>';

		// category fix
		$catstr = $this->getCategories();
		$catlinks = $this->getCategoryLinks();
		if (strlen($catlinks) > 2) {
			$s .= '<table border="0" cellpadding="0" cellspacing="0" width="100%" id="categories"><tr>';
			$s .= '<td width="10">&nbsp;</td><td>';

			$s .= '<table border="0" cellpadding="0" width="100%" id="toc"><tr><td>';
			$s .= $catstr;
			$s .= '</td></tr></table>';

			$s .= '</td><td width="10">&nbsp;</td></tr></table>';
		}


		$qb = $this->qbSetting();
		if ( 0 != $qb ) { $s .= $this->quickBar(); }

		$s .= "\n<div id='footer'>";
		$s .= "<table width='100%' border='0' cellspacing='0'><tr>";

		$s .= "<td class='bottom' align='left' valign='top'>&nbsp;&nbsp;";

		$s .= $this->searchForm(wfMsg("qbfind"));

		$s .= "</td>";
		//$s .= '<td class="bottom" align="right"><a href="http://www.mediawiki.org">MediaWiki</a>&nbsp;&nbsp;';
		//$s .= "</td>";
		$s .= "</tr></table>\n</div>\n";


		$s .= '</td>';
		$s .= '<td bgcolor="#20292E"><img src="/images/pixel.gif" height="10" width="1" /><br /></td>';
		$s .= '</tr></table>';


		$s .= '</tr></td>';
		$s .= file_get_contents('../templates/footer.html');

		return $s;
	}

	function sysLinks() {
		global $wgUser, $wgContLang, $wgTitle;
		$li = $wgContLang->specialPage("Userlogin");
		$lo = $wgContLang->specialPage("Userlogout");

		$rt = $wgTitle->getPrefixedURL();
		if ( 0 == strcasecmp( urlencode( $lo ), $rt ) ) {
			$q = "";
		} else {
			$q = "returnto={$rt}";
		}

		/* show links to different language variants */
		$s .= $this->variantLinks();
		$s .= $this->extensionTabLinks();

		$s .= " | ";
		if ( $wgUser->isLoggedIn() ) {
			$s .=  $this->makeKnownLink( $lo, wfMsg( "logout" ), $q );
		} else {
			$s .=  $this->makeKnownLink( $li, wfMsg( "login" ), $q );
		}

		return $s;
	}

	/**
	 * Compute the sidebar
	 * @access private
	 */
	function quickBar()
	{
		global $wgOut, $wgTitle, $wgUser, $wgLang, $wgContLang, $wgEnableUploads;

		$tns=$wgTitle->getNamespace();

		$s = "";
		//$s = "\n<div id='quickbar'>";

		$s .= '<table border="0" cellpadding="0" cellspacing="0" align="right" width="758">';
		$s .= '<tr><td width="7" rowspan="3"><img src="/images/pixel.gif" height="1" width="7" /><br /></td>';
		$s .= '<td height="25" class="toolbar" width="751" colspan="2">Page editing toolbox</td></tr>';
		$s .= '<tr><td bgcolor="#20292E"><img src="/images/pixel.gif" height="15" width="1" /><br /></td>';
		$s .= '<td bgcolor="#38474E" class="bottom">';

		$s .= '<table border="0" cellpadding="0" cellspacing="4" width="750"><tr valign="top"><td>';

		$sep = "<br />";
		//$s .= $this->menuHead( "qbfind" );
		//$s .= $this->searchForm();

		$s .= $this->menuHead( "qbbrowse" );

		# Use the first heading from the Monobook sidebar as the "browse" section
		$bar = $this->buildSidebar();
		$browseLinks = reset( $bar );

		foreach ( $browseLinks as $link ) {
			if ( $link['text'] != '-' ) {
				$s .= "<a href=\"{$link['href']}\">" .
					htmlspecialchars( $link['text'] ) . '</a>' . $sep;
			}
		}

		if ( $wgOut->isArticle() ) {
			$s .= '</td><td>';

			$s .= $this->menuHead( "qbedit" );
			$s .= "<strong>" . $this->editThisPage() . "</strong>";

			$s .= $sep . $this->makeKnownLink( wfMsgForContent( "edithelppage" ), wfMsg( "edithelp" ) );

			if( $wgUser->isLoggedIn() ) {
				$s .= $sep . $this->moveThisPage();
			}
			if ( $wgUser->isAllowed('delete') ) {
				$dtp = $this->deleteThisPage();
				if ( "" != $dtp ) {
					$s .= $sep . $dtp;
				}
			}
			if ( $wgUser->isAllowed('protect') ) {
				$ptp = $this->protectThisPage();
				if ( "" != $ptp ) {
					$s .= $sep . $ptp;
				}
			}
			$s .= $sep;
			$s .= '</td><td>';

			$s .= $this->menuHead( "qbpageoptions" );
			$s .= $this->talkLink()
			  . $sep . $this->commentLink()
			  . $sep . $this->printableLink();
			if ( $wgUser->isLoggedIn() ) {
				$s .= $sep . $this->watchThisPage();
			}

			$s .= $sep;
			$s .= '</td><td>';

			$s .= $this->menuHead("qbpageinfo")
			  . $this->historyLink()
			  . $sep . $this->whatLinksHere()
			  . $sep . $this->watchPageLinksLink();

			if( $tns == NS_USER || $tns == NS_USER_TALK ) {
				$id=User::idFromName($wgTitle->getText());
				if ($id != 0) {
					$s .= $sep . $this->userContribsLink();
					if( $this->showEmailUser( $id ) ) {
						$s .= $sep . $this->emailUserLink();
					}
				}
			}
			$s .= $sep;
		}
		$s .= '</td><td>';

		$s .= $this->menuHead( "qbmyoptions" );
		if ( $wgUser->isLoggedIn() ) {
			$name = $wgUser->getName();
			$tl = $this->makeKnownLinkObj( $wgUser->getTalkPage(),
				wfMsg( 'mytalk' ) );
			if ( $wgUser->getNewtalk() ) {
				$tl .= " *";
			}

			$s .= $this->makeKnownLinkObj( $wgUser->getUserPage(),
				wfMsg( "mypage" ) )
			  . $sep . $tl
			  . $sep . $this->specialLink( "watchlist" )
			  . $sep . $this->makeKnownLinkObj( SpecialPage::getSafeTitleFor( "Contributions", $wgUser->getName() ),
			  	wfMsg( "mycontris" ) )
		  	  . $sep . $this->specialLink( "preferences" )
		  	  . $sep . $this->specialLink( "userlogout" );
		} else {
			$s .= $this->specialLink( "userlogin" );
		}

		$s .= '</td><td>';

		$s .= $this->menuHead( "qbspecialpages" )
		  . $this->specialLink( "newpages" )
		  . $sep . $this->specialLink( "imagelist" )
		  . $sep . $this->specialLink( "statistics" );
//		  . $sep . $this->bugReportsLink();
		if ( $wgUser->isLoggedIn() && $wgEnableUploads ) {
			$s .= $sep . $this->specialLink( "upload" );
		}
		global $wgSiteSupportPage;
		if( $wgSiteSupportPage) {
			$s .= $sep."<a href=\"".htmlspecialchars($wgSiteSupportPage)."\" class =\"internal\">"
			      .wfMsg( "sitesupport" )."</a>";
		}

		$s .= $sep . $this->makeKnownLinkObj(
			SpecialPage::getTitleFor( 'Specialpages' ),
			wfMsg( 'moredotdotdot' ) );

		$s .= '</td></tr></table>';

		$s .= '</td></tr>';
		$s .= '<tr height="1"><td bgcolor="#20292E" colspan="2"><img src="/images/pixel.gif" height="1" width="10" /></td>';
		$s .= '</tr></table>';

		//$s .= $sep . "\n</div>\n";
		return $s;
	}

	function menuHead( $key )
	{
		$s = "\n<h6>" . wfMsg( $key ) . "</h6>";
		return $s;
	}

	function searchForm( $label = "" )
	{
		global $wgRequest;

		$search = $wgRequest->getText( 'search' );
		$action = $this->escapeSearchLink();
		$s = "<div id=\"cse\" style=\"width: 100%;\"><form id=\"searchform{$this->searchboxes}\" method=\"get\" class=\"inline\" action=\"$action\">";
		if ( "" != $label ) { $s .= "{$label}: "; }

		$s .= "<input type='text' id=\"searchInput{$this->searchboxes}\" class=\"mw-searchInput\" name=\"search\" size=\"14\" value=\""
		  . htmlspecialchars(substr($search,0,256)) . "\" /> "
		  . "<input type='submit' id=\"searchGoButton{$this->searchboxes}\" class=\"searchButton\" name=\"go\" value=\"" . htmlspecialchars( wfMsg( "searcharticle" ) ) . "\" />"
		  . "<input type='submit' id=\"mw-searchButton{$this->searchboxes}\" class=\"searchButton\" name=\"fulltext\" value=\"" . htmlspecialchars( wfMsg( "search" ) ) . "\" /></form></div>";

		// Ensure unique id's for search boxes made after the first
		$this->searchboxes = $this->searchboxes == '' ? 2 : $this->searchboxes + 1;

		$s .= <<<GOOGLESTRING
		<script src="http://www.google.com/jsapi" type="text/javascript"></script>
		<script type="text/javascript">  google.load('search', '1', {language : 'en'});  google.setOnLoadCallback(function() {    var customSearchControl = new google.search.CustomSearchControl('009330874965769538744:qyumpuo2xti');    customSearchControl.setResultSetSize(google.search.Search.FILTERED_CSE_RESULTSET);    customSearchControl.draw('cse');  }, true);</script>
		<!-- <link rel="stylesheet" href="http://www.google.com/cse/style/look/default.css" type="text/css" /> -->
GOOGLESTRING;

		return $s;
	}
}

?>
