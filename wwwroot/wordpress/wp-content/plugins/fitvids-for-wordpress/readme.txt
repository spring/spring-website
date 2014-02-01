=== FitVids for WordPress ===
Contributors: kevindees
Tags: videos, fitvids, responsive
Requires at least: 3.2
Tested up to: 3.4
Stable Tag: 2.1

This plugin makes videos responsive using the FitVids jQuery plugin on WordPress.

== Description ==

This plugin makes videos responsive using the FitVids jQuery plugin on WordPress.

The options page is located on the dashboard menu under Appearance as "FitVids". From there you can add your selector and turn jQuery on and off.

== Installation ==

Upload the fitvids-for-wordpress plugin to your blog, Activate it!

1, 2: You're done!

== Screenshots ==

1. screenshot-1.png

== Changelog ==

= 2.1 =

* Add support for custom video players
* Update FitVids (bug fixes on github)
* Fix load order with add_action('wp_print_footer_scripts', array($this, 'add_fitthem'))
* Added better help text

= 2.0.1 =

* Fix define('DISALLOW_FILE_EDIT',true) bug by changing edit_themes to switch_themes

= 2.0 =

* Added Security Fix
* Changed capabilities to edit_themes instead of administrator
* Added New Version of FitVids
* Added jQuery 1.7.2 from Google CDN
* Added better readme
* Redesigned settings page

= 1.0.1 =

* Fixed readme description
* Changed saving feature in php
* Added uninstall.php to remove options

= 1.0 =

* Make videos responsive using FitVids.js