<?php
/**
 * The Header for our theme.
 *
 * Displays all of the <head> section and everything up till <main id="main">
 *
 * @package roflcoptered
 
 <h1 class="site-title"><a href="<?php echo esc_url( home_url( '/' ) ); ?>" title="<?php echo esc_attr( get_bloginfo( 'name', 'display' ) ); ?>" rel="home"><?php bloginfo( 'name' ); ?></a></h1>
			<h2 class="site-description"><?php bloginfo( 'description' ); ?></h2>
 
 */
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<!-- Place this tag in your head or just before your close body tag. -->
	<script type="text/javascript" src="https://apis.google.com/js/plusone.js"></script>

	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width">
	<title><?php wp_title( '|', true, 'right' ); ?></title>
	<link rel="profile" href="http://gmpg.org/xfn/11">
	<link rel="pingback" href="<?php bloginfo( 'pingback_url' ); ?>">
	<link rel="icon" type="image/png" href="http://test.springrts.com/wordpress/wp-content/uploads/2013/10/springlogofavicon2.png">

	<!-- Start Shared Tags Across all Sites -->
	<META NAME="Title" CONTENT="The Spring Project">
	<meta name="description" content="Spring is a free RTS engine developed for Windows, Linux and Mac OS X!" >
	<meta name="keywords" content="spring,engine,strategy,tactics,game,design,free,windows,linux,osx,rts,real,time,community,developers,github,oss,open,source,foss">
	<!-- End Shared Tags Across all Sites -->

	<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>

<div id="sitewrapper">
<div id="headercontent">
	<?php do_action( 'before' ); ?>
	<header id="masthead" class="site-header" role="banner">
		<div class="site-branding">
			<div class="logopositioning">
				<div class="logo">
					<img src="<?php echo get_template_directory_uri(); ?>/images/logo.png" alt="The Spring Project Logo">
				</div>
			</div>
			<?php
			if ( shortcode_exists( 'metaslider' ) ) {
				$posts = get_posts( array(
	   				'post_type' => 'ml-slider',
	   				'name' => 'header'
	   			));
	   			if ( empty( $posts ) ) {
	   				echo '<p>No header slider has been set, go to the meta slider page and create a slider with the name "header"</p>';
	   			} else {
	   				foreach ( $posts as $p ) {
						echo do_shortcode("[metaslider id=".$p->ID."]");
						break;
					}
				}
			} else {
				echo '<p>Error: metaslider plugin is not activated</p>';
			}
			?>
		</div>
	</header><!-- #masthead -->
</div>
<div id="topnav">
	<nav id="site-navigation" class="main-navigation" role="navigation">
			<div class="screen-reader-text skip-link"><a href="#content" title="<?php esc_attr_e( 'Skip to content', 'roflcoptered' ); ?>"><?php _e( 'Skip to content', 'roflcoptered' ); ?></a></div>

			<?php wp_nav_menu( array( 'theme_location' => 'primary' ) ); ?>
	</nav><!-- #site-navigation -->
</div>	
<div style="clear:both"></div>
<div id="page" class="hfeed site">
	<div id="content" class="site-content">