<?php
/**
 * CSS extension - A parser-function for adding CSS files, article or inline rules to articles
 *
 * See http://www.mediawiki.org/wiki/Extension:CSS for installation and usage details
 *
 * @file
 * @ingroup Extensions
 * @author Aran Dunkley [http://www.organicdesign.co.nz/nad User:Nad]
 * @copyright © 2007-2010 Aran Dunkley
 * @licence GNU General Public Licence 2.0 or later
 */

if ( !defined( 'MEDIAWIKI') ) die('Not an entry point.' );

define( 'CSS_VERSION', '1.0.7, 2010-10-20' );

$wgCSSMagic = "css";
$wgHooks['ParserFirstCallInit'][] = 'wfCSSParserFirstCallInit';
$wgHooks['LanguageGetMagic'][] = 'wfCSSLanguageGetMagic';

$wgExtensionCredits['parserhook'][] = array(
	'path'           => __FILE__,
	'name'           => 'CSS',
	'author'         => '[http://www.organicdesign.co.nz/nad User:Nad]',
	'descriptionmsg' => 'css-desc',
	'url'            => 'http://www.mediawiki.org/wiki/Extension:CSS',
	'version'        => CSS_VERSION,
);

$dir = dirname( __FILE__ ) . '/';
$wgExtensionMessagesFiles['CSS'] = $dir . 'CSS.i18n.php';

function wfCSSRender( &$parser, $css ) {
	global $wgOut, $wgRequest;

	$parser->mOutput->mCacheTime = -1;
	$url = false;
	if( preg_match( '|\\{|', $css ) ) {

		# Inline CSS
		$css = htmlspecialchars( trim( Sanitizer::checkCss( $css ) ) );
		$parser->mOutput->addHeadItem( <<<EOT
<style type="text/css">
/*<![CDATA[*/
{$css}
/*]]>*/
</style>
EOT
		);
	} elseif ( $css{0} == '/' ) {

		# File
		$url = $css;

	} else {

		# Article?
		$title = Title::newFromText( $css );
		if( is_object( $title ) ) {
			$url = $title->getLocalURL( 'action=raw&ctype=text/css' );
			$url = str_replace( "&", "&amp;", $url );
		}
	}

	if( $url ) $wgOut->addScript( "<link rel=\"stylesheet\" type=\"text/css\" href=\"$url\" />" );
	return '';
}

function wfCSSParserFirstCallInit( $parser ) {
	global $wgCSSMagic;
	$parser->setFunctionHook( $wgCSSMagic, 'wfCSSRender' );
	return true;
}

function wfCSSLanguageGetMagic( &$magicWords, $langCode = 0 ) {
	global $wgCSSMagic;
	$magicWords[$wgCSSMagic] = array( $langCode, $wgCSSMagic );
	return true;
}
