<?php
/*
Template Name: Home Page
*/

get_header(); ?>

<div id="primary" class="content-area">
	<main id="main" class="site-main" role="main">

	<header class="entry-header">
     		<h1 class="entry-title"><?php the_title(); ?></h1>
	</header><!-- .entry-header -->
	<div class="welcome-banner-text-overlay-relative">
		<div class="welcome-banner-text-overlay-absolute">
			<p class="banner-overlay-title">Welcome to the Spring Engine website!</p>
			<p class="banner-overlay-subtitle">Spring is a free RTS engine developed for <a title="Download the Spring Engine for Windows!" href="http://springrts.com/wiki/Download">Windows</a>, <a title="Download the Spring Engine for Linux!" href="http://springrts.com/wiki/Download">Linux</a> and <a title="Download the Spring Engine for MAX OS X!" href="http://springrts.com/wiki/Download">Mac OS X</a></p>

		</div>
		<?php 
   			echo do_shortcode("[metaslider id=41]"); 
		?>
		<div style="clear: both;"></div>
		<div class="image-preload-home"><img alt="Image Preload" src="http://test.springrts.com/wordpress/wp-content/themes/roflcoptered/images/jpg/learnmore-active.jpg" />
			<img alt="Image Preload" src="http://test.springrts.com/wordpress/wp-content/themes/roflcoptered/images/jpg/downloadandplay-active.jpg" />
			<img alt="Image Preload" src="http://test.springrts.com/wordpress/wp-content/themes/roflcoptered/images/jpg/seethegames-active.jpg" />
		</div>
		<div class="playlist-learn-download-see-wrapper">
			<div class="youtube-playlist-homepage"><iframe src="//www.youtube.com/embed/videoseries?list=PLV6BY9sXCO3ovrZdn5e1E7CqrUh9XUbDz" height="174" width="293" allowfullscreen="true" frameborder="0"></iframe></div>
			<div class="learn-download-see">
				<div class="learn-more"><a title="Learn More" href="http://springrts.com/wiki/About" alt="Learn more about the Spring Project!">Learn More</a></div>
				<div class="download-and-play"><a title="Download and Play" href="http://springrts.com/wiki/Download" alt="Download the Spring RTS Engine!">Download and Play</a></div>
				<div class="see-the-games"><a title="See the Games" href="http://test.springrts.com/games/" alt="Check out some of the games that have been created using the Spring RTS Engine!">See the games</a></div>
			</div>
		</div>

		<?php 
			echo do_shortcode("[wp_rss_multi_importer hdsize=24px]"); 
		?>

		<div style="clear: both;"></div>
		
	</div>

		</main><!-- #main -->
</div><!-- #primary -->

<?php get_footer(); ?>