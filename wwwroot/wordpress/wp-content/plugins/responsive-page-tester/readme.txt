=== Responsive Page Tester ===
Contributors: linchpin_agency, desrosj, aware
Tags: responsive, design, test, layout, mobile, flexibility
Requires at least: 3.1
Tested up to: 3.6
Stable tag: 2.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Gives users with content creating permissions the ability to preview their site in a responsive testbed.

== Description ==

When viewing the front end of the site, a "Responsive" button is added to the WordPress Toolbar. Clicking that will toggle an overlay with your website presented in various sizes for easy responsive design testing.

This can be useful both during theme development, and when writing content to verify it will be presented properly.

**Planned Features**

1. WordPress Theme Customizer support.
2. Button on post edit screens to view responsive preview.
3. Support for post previews (before saving).

This plugin is a heavily modified version of [Matt Kersley's](http://mattkersley.com/ "Matt Kersley's") [Responsive Design Testing](http://github.com/mattkersley/Responsive-Design-Testing "Responsive Design Testing").

== Installation ==

1. Upload the plugin folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. View any front end page and click the Responsive button in the WordPress Toolbar.

== Frequently Asked Questions ==

= Can I add or remove screen sizes to the tester? =

Yes you can! In version 2.0, we added a filter that makes it very easy to add or delete screen sizes from the Responsive Page Tester.

`function mythemename_filter_rpt_sizes( $sizes ) {
	//Add a size
	$sizes['1024x600'] = array( 'width' => 1024, 'height' => 600, 'description' => '(Galaxy tablet)' );

	//Remove a size
	unset( $sizes['240x320'] );

	//Return our filtered sizes
	return $sizes;
}
add_filter( 'rpt_screen_sizes', 'mythemename_filter_rpt_sizes' );`

= I don't have a WordPress Toolbar on my front end pages. =

There is a setting on your profile edit screen labeled "Toolbar". This needs to be checked for you to see the WordPress Toolbar on the front end of your website.

If this is checked, and you still are not seeing the Toolbar, then your theme or another plugin is hiding it.

= My site does not change in the different sized iframes =

If the site presentation does not change to fit the width of the iframes, it can be one of two things.

1. Your theme is not a responsive WordPress theme. Perhaps try one from the [WordPress Theme Directory](http://wordpress.org/extend/themes/search.php?q=responsive "WordPress Theme Directory") that is!
2. You are using a browser that does not support [media queries](http://www.w3.org/TR/css3-mediaqueries/ "Media Queries"). While [media query support is pretty extensive](http://caniuse.com/#feat=css-mediaqueries "Media query support"), Internet Explorer did not add support until version 9.0.

= Will this perform any HiDPI screen responsiveness? =

No, it will not. This plugin does not actually do anything to make your site responsive. It merely presents it to you in different iframes to show you how it will show up. If you want to add some HiDPI support, maybe check out [@desrosj's](http://profiles.wordpress.org/desrosj "@desrosj's") [Simple WP Retina plugin](http://wordpress.org/extend/plugins/simple-wp-retina/ "Simple WP Retina plugin").

== Screenshots ==

1. Responsive Tester button in the WordPress Toolbar
2. Responsive Tester overlay - mobile sizes
3. Responsive Tester overlay - iPad sizes
4. Options
5. Width only setting
6. One Size Fits All option

== Changelog ==

= 2.0 =
* Added the rpt_screen_sizes filter allowing devs to add or take out sizes.
* Cleaned up the JavaScript &amp; CSS.
* Converted JavaScript to use the jQueryUI framework.
* Adjusted the CSS so theme styles are less likely to hijack the tester's styling.
* Redid the toolbar interface.
* You can now use the ESC key to close the Responsive Page Tester.
* Added a dropdown menu to the WordPress Toolbar menu item for quick size selecting.

= 1.0 =
* Hello world!
* Allows you to test your site in different device sizes to ensure it displays properly