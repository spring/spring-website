<?php 
/*
Plugin Name: Responsive Google+ Comment
Plugin URI: http://responsivecodes.com/plugins/responsive-google-comments-widget/
Description: Google+ launched comments for Blogger.com users. Although only offered to Blogger.com directly. This WordPress plugin makes it easy for you to add Google+ comments to your site!
Version: 1.0
Author: bhardwaja
Author URI: http://bhardwaja.com
License: GPL3
*/
/*  
* 	Copyright (C) 2013  Bhardwaja
*	http://bhardwaja.com
*	http://responsivecodes.com/plugins/responsive-google-comments-widget/
*
*	This program is free software: you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation, either version 3 of the License, or
*	(at your option) any later version.
*
*	This program is distributed in the hope that it will be useful,
*	but WITHOUT ANY WARRANTY; without even the implied warranty of
*	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*	GNU General Public License for more details.
*
*	You should have received a copy of the GNU General Public License
*	along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/
/********************************************************************/
/*                                                                  */
/*                         Global variables                         */
/*                                                                  */
/********************************************************************/
$WIDTH=500;
/********************************************************************/
/*                                                                  */
/*            Do not change anything below this.                    */
/*                                                                  */
/********************************************************************/
define( 'GPLUSCOMMENTS_CURRENT_VERSION', '1.0' );
define( 'GPLUSCOMMENTS_DEBUG', false);

//Set Defaults.
if(get_option('googlePluscomment-credit')=== false) {
	update_option( 'googlePluscomment-credit', 0 );
}
if(get_option('googlePluscomment-dynamicWidth')=== false) {
	update_option( 'googlePluscomment-dynamicWidth', 1 );
}
if(get_option('googlePluscomment-width')=== false) {
	update_option( 'googlePluscomment-width', 500 );
}
function googleplus_comments_template($value) {
    return dirname(__FILE__) . '/comments.php';
}
add_filter('comments_template', 'googleplus_comments_template');

function googleplus_pre_comment_on_post($comment_post_ID) {
	wp_die( dsq_i('Sorry, the built-in commenting system is disabled because Gogole+ Commenting is active.') );
}
add_action('pre_comment_on_post', 'googleplus_pre_comment_on_post');
function googleplus_plugin_action_links($links, $file) {
    $plugin_file = basename(__FILE__);
    if (basename($file) == $plugin_file) {
           $settings_link = '<a href="edit-comments.php?page=responsivegooglepluscommentsetup">Configure</a>';
        array_unshift($links, $settings_link);
    }
    return $links;
}
add_filter('plugin_action_links', 'googleplus_plugin_action_links', 10, 2);

function googleplus_add_pages() {
     add_submenu_page(
         'edit-comments.php',
         'Google+ Comments',
         'Google+ Comments',
         'moderate_comments',
         'responsivegooglepluscommentsetup',
         'googlepluscomments_manage'
     );
}
add_action('admin_menu', 'googleplus_add_pages', 10);

function googlepluscomments_manage() {
	include_once(dirname(__FILE__) . '/manage.php');
}

// a little jQuery goodness to get comments menu working as desired
function googleplus_menu_admin_head() {
?>
<script type="text/javascript">
jQuery(function($) {
    // fix menu
    var mc = $('#menu-comments');
    mc.find('a.wp-has-submenu').attr('href', 'edit-comments.php?page=responsivegooglepluscommentsetup').end().find('.wp-submenu  li:has(a[href="edit-comments.php?page=responsivegooglepluscommentsetup"])').prependTo(mc.find('.wp-submenu ul'));
    // fix admin bar
    $('#wp-admin-bar-comments').find('a.ab-item').attr('href', 'edit-comments.php?page=responsivegooglepluscommentsetup');
});
</script>
<?php }
add_action('admin_head', 'googleplus_menu_admin_head');
?>