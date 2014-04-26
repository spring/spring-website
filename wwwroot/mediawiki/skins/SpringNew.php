<?php
/**
 * SpringNew skin
 *
 * @ingroup Skins
 */

if( !defined( 'MEDIAWIKI' ) )
	die( 1 );

class SkinSpringNew extends SkinLegacy {

	var $useHeadElement = true;

	function initPage(OutputPage $out) {
		parent::initPage($out);
		$this->skinname = 'springnew';
		$this->stylename = 'springnew';
		$this->template = 'SpringNewTemplate';
	}

	function setupSkinUserCss(OutputPage $out) {
		parent::setupSkinUserCss($out);
		$out->addStyle('../../skins/mediawiki/spring.css');
	}
};


class SpringNewTemplate extends LegacyTemplate {

	function getStylesheet() {
		return '../../skins/mediawiki/spring.css';
	}
	function getSkinName() {
		return "springnew";
	}

	function doBeforeContent() {
		global $wgOut;
		$s = "";
		$qb = $this->getSkin()->qbSetting();
		$mainPageObj = Title::newMainPage();

		$s .= file_get_contents('../templates/header.html');
		$title = htmlspecialchars($wgOut->getPageTitle());

		$s = str_replace('{PAGE_TITLE}', $title, $s);
		$s .= '<tr><td>';
		$s .= "\n<div id='content'>";
		$s .= "\n<div id='article'>";

		$notice = $this->data['sitenotice'];
		if( $notice ) {
			$s .= "\n<div id='siteNotice'>$notice</div>\n";
		}

		return $s;
	}

	function doAfterContent()
	{
		global $wgUser, $wgOut;

		$s = "\n</div>\n";

		// category fix
		$catlinks = $this->getSkin()->getCategoryLinks();
		if (strlen($catlinks) > 2) {
			$s .= '<table border="0" cellpadding="0" cellspacing="0" width="100%" id="categories">';
				// Mediawiki 1.19 adds the ul and li tags, which mediawiki 1.16 did not have.
				$catstr = $this->getSkin()->getCategories();
				$catstr = str_replace('<ul>', '', str_replace('</ul>', '', $catstr));
				$catstr = str_replace('<li>', '', str_replace('</li>', ' ', $catstr));
				$s .= "<tr><td>$catstr</td></tr>";
			$s .= '</table>';
		}


		$qb = $this->getSkin()->qbSetting();
		if ( 0 != $qb ) { $s .= $this->quickBar(); }

		$s .= "\n<div id='footer'>";
			$s .= "<table width='100%' border='0' cellspacing='0'>";
				$s .= "<tr><td class='bottom' align='left' valign='top'>&nbsp;&nbsp;";
				$s .= $this->searchForm(wfMsg("qbfind"));
				$s .= "</td></tr>";
			$s .= "</table>\n";
		$s .= "</div>\n";
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
			$s .= Linker::makeKnownLinkObj( $lo, wfMsg( "logout" ), $q );
		} else {
			$s .= Linker::makeKnownLinkObj( $li, wfMsg( "login" ), $q );
		}

		return $s;
	}

	/**
	 * Compute the sidebar
	 * @access private
	 */
	private function quickBar()
	{
		global $wgOut, $wgTitle, $wgUser, $wgLang, $wgContLang, $wgEnableUploads;

		$tns=$wgTitle->getNamespace();

		$s  = '<div id="toolbar">';
		$s .= '<div class="toolbartitle">Page editing toolbox</div>';
		$s .= '<table border="0" cellpadding="0" cellspacing="4" width="100%"><tr valign="top">';
		$sep = "<br/>";

		// browse section
		$section = "";
		$browseLinks = reset($this->data['sidebar']);
		foreach ( $browseLinks as $link ) {
			if ( $link['text'] != '-' ) {
				$section .= "<a href=\"{$link['href']}\">" .
					htmlspecialchars( $link['text'] ) . '</a>' . $sep;
			}
		}
		$s .= $this->AddToolbarSection("qbbrowse", $section);

		// page related sections
		if ( $wgOut->isArticle() ) {
			$section = "";
			$section .= "<strong>" . $this->editThisPage() . "</strong>";

			$section .= $sep . Linker::makeKnownLinkObj( Title::newFromText( wfMsgForContent("edithelppage") ), wfMsg( "edithelp" ) );

			if( $wgUser->isLoggedIn() ) {
				$section .= $sep . $this->moveThisPage();
			}
			if ( $wgUser->isAllowed('delete') ) {
				$dtp = $this->deleteThisPage();
				if ( "" != $dtp ) {
					$section .= $sep . $dtp;
				}
			}
			if ( $wgUser->isAllowed('protect') ) {
				$ptp = $this->protectThisPage();
				if ( "" != $ptp ) {
					$section .= $sep . $ptp;
				}
			}
			$section .= $sep;
			$s .= $this->AddToolbarSection("qbedit", $section);

			$section = "";
			$section .= $this->talkLink()
			  . $sep . $this->commentLink()
			  . $sep . $this->printableLink();
			if ( $wgUser->isLoggedIn() ) {
				$section .= $sep . $this->watchThisPage();
			}

			$section .= $sep;
			$s .= $this->AddToolbarSection("qbpageoptions", $section);

			$section = "";
			$section .= $this->historyLink()
			  . $sep . $this->whatLinksHere()
			  . $sep . $this->watchPageLinksLink();

			if( $tns == NS_USER || $tns == NS_USER_TALK ) {
				$id=User::idFromName($wgTitle->getText());
				if ($id != 0) {
					$section .= $sep . $this->userContribsLink();
					if( $this->getSkin()->showEmailUser( $id ) ) {
						$section .= $sep . $this->emailUserLink();
					}
				}
			}
			$section .= $sep;
			$s .= $this->AddToolbarSection("qbpageinfo", $section);
		}

		// login/user section
		$section = "";
		if ( $wgUser->isLoggedIn() ) {
			$name = $wgUser->getName();
			$tl = Linker::makeKnownLinkObj($wgUser->getTalkPage(), wfMsg( 'mytalk' ) );
			if ( $wgUser->getNewtalk() ) {
				$tl .= " *";
			}

			$section .= Linker::makeKnownLinkObj( $wgUser->getUserPage(),
				wfMsg( "mypage" ) )
			  . $sep . $tl
			  . $sep . Linker::specialLink( "watchlist" )
			  . $sep . Linker::makeKnownLinkObj( SpecialPage::getSafeTitleFor( "Contributions", $wgUser->getName() ),
			  	wfMsg( "mycontris" ) )
			  . $sep . Linker::specialLink( "preferences" )
			  . $sep . Linker::specialLink( "userlogout" );
		} else {
			$section .= Linker::specialLink( "userlogin" );
		}
		$s .= $this->AddToolbarSection("qbmyoptions", $section);

		// special spages section
		$section = "";
		$section .= Linker::specialLink( "newpages" )
		   . $sep . Linker::specialLink( "imagelist" )
		   . $sep . Linker::specialLink( "statistics" );
		if ( $wgUser->isLoggedIn() && $wgEnableUploads ) {
			$section .= $sep . Linker::specialLink( "upload" );
		}
		global $wgSiteSupportPage;
		if( $wgSiteSupportPage) {
			$section .= $sep."<a href=\"".htmlspecialchars($wgSiteSupportPage)."\" class =\"internal\">"
			      .wfMsg( "sitesupport" )."</a>";
		}
		$section .= $sep . Linker::makeKnownLinkObj(
			SpecialPage::getTitleFor( 'Specialpages' ),
			wfMsg( 'moredotdotdot' ) );
		$s .= $this->AddToolbarSection("qbspecialpages", $section);

		$s .= '</tr></table>';
		$s .= '</div>';
		return $s;
	}

	private static function AddToolbarSection( $key, $content )
	{
		$s = "\n<td><div class='toolbarsection' id='$key'><h6>" . wfMsg( $key ) . "</h6>$content</td>";
		return $s;
	}

	function searchForm( $label = "" )
	{
		global $wgRequest;

		$search = $wgRequest->getText( 'search' );
		$action = $this->getSkin()->escapeSearchLink();
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
