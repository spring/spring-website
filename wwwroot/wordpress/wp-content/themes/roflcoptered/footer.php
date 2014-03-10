<?php
/**
 * The template for displaying the footer.
 *
 * Contains the closing of the id=main div and all content after
 *
 * @package roflcoptered
 */
?>

	</div><!-- #content -->

	<div id="footer-sidebar-container">
		<div id="footer-sidebar1" class="clearfix" role="complementary">
				
					<?php if ( is_active_sidebar( 'footer-sidebar1' ) ) : ?>

						<?php dynamic_sidebar( 'footer-sidebar1' ); ?>

					<?php else : ?>
						<!-- This content shows up if there are no widgets defined in the backend. -->
					<?php endif; ?>

		</div>
		<div id="footer-sidebar2" class="clearfix" role="complementary">

					<?php if ( is_active_sidebar( 'footer-sidebar2' ) ) : ?>

						<?php dynamic_sidebar( 'footer-sidebar2' ); ?>

					<?php else : ?>
						<!-- This content shows up if there are no widgets defined in the backend. -->
					<?php endif; ?>

		</div>
	</div>
	<div style="clear:both"></div>
	
	<footer id="colophon" class="site-footer" role="contentinfo">
		<div class="site-info">
			<?php do_action( 'roflcoptered_credits' ); ?>
			<?php printf( __( '<strong>Theme:</strong> %1$s by %2$s.', 'roflcoptered' ), 'roflcoptered', '<a href="http://www.it-magique.com" rel="designer">IT Magique</a> (Based upon an original design by <a href="http://springrts.com/phpbb/memberlist.php?mode=viewprofile&u=4760" alt="Visit Roflcopter\'s user profile"> Roflcopter</a>)' ); ?>
		</div><!-- .site-info -->
	</footer><!-- #colophon -->
</div><!-- #page -->
</div><!-- #sitewrapper -->
<?php wp_footer(); ?>

</body>
</html>