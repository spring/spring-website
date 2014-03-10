# Responsive Select Menu - WordPress Plugin #

**Author URI:** http://sevenspark.com

**Contributors:** sevenspark

**Donate link:** http://bit.ly/DonateResponsiveSelect

**Tags:** responsive, menu, select, drop down, 

**Requires at least:** 3.6

**Tested up to:** 3.8

**Stable tag:** trunk

**License:** GPLv2 or later

**License URI:** http://www.gnu.org/licenses/gpl-2.0.html

The Responsive Select Menu plugin automatically turns any WordPress 3 Menu into a select box / dropdown on mobile devices.

## Description ##

[View Demo 1](http://wpmegamenu.com/responsive-select-menu/)

[View Demo 2 in a Responsive WordPress Theme](http://agility.sevenspark.com/responsive-select-menu)

One common UI paradigm for navigation menus with responsive design is to display a select box (form element) 
for mobile devices.  This plugin allows you to turn your WordPress 3 menu into a select box below a width of your choice.

** Features **

* Takes up less screen real estate on mobile devices
* Easier navigation for touch screens
* Works automatically - no need to add extra PHP code


** Highly Configurable **

Through the Responsive Select Menu Control Panel you can:

*	New in 1.2 - select which theme locations to apply the menu to 
*   Set your width breakpoint (where your menu switches from your standard menu to a select box)
*   Configure how many levels you wish to include in the select menu. Set it to 1 to include only the top-level menu items.
*   Choose the character used to indent your submenu items within the select box
*   Choose whether or not to exclude “dummy” items that don’t have links
*   Set the text of the first menu item

The demo is built with [UberMenu - Wordpress Mega Menu Plugin](http://wpmegamenu.com/) , but it is designed to work with any UL-based WordPress 3 menu.

Based on the excellent tutorial from [Chris Coyier and CSS Tricks](http://css-tricks.com/convert-menu-to-dropdown/) - thanks!

## Installation ##

This section describes how to install the plugin and get it working.

1. Upload the plugin zip through your WordPress admin
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Navigate to Appearance > Responsive Select to configure your menu


## Frequently Asked Questions ##

** Do I have to have a "Navigate" item as my first item **

You can change the name of this item, but it has to exist.  Otherwise, you won't be able to navigate to the first item in the menu
if you're not using the "Show currently selected item" - even if you have that option enabled, the issue would still exist on pages not 
in the menu.

** It doesn't work **

If your theme creates a menu the standard way with wp_nav_menu, it should work.  If not, make sure you're using 1.3+, as this resolves the most common "it doesn't work" issue.



## Screenshots ##

1. Responsive select menu on the iPhone/iPod Touch
2. Responsive select menu Control Panel

## Changelog ##

**1.5**

* Improves compatibility with latest version of UberMenu

**1.4**

* Handles blank menu items better

** 1.3 **

* Better compatibility with themes that remove the wp_nav_menu 'container' parameter.

**1.2**

* Added option to select specific theme locations to apply the responsive select menu to.

**1.1**

* Fixed option closing tag order for valid HTML markup

**1.0**

* Initial version
