=== Plugin Name ===
Contributors: matchalabs
Tags: widget,wordpress slider,slideshow,wordpress slideshow,image slider,flexslider,flex slider,nivoslider,nivo slider,responsiveslides,responsive,responsive slides,coinslider,coin slider,slideshow,carousel,responsive slider,vertical slides,ml slider,image rotator,metaslider,meta,ajax,metaslider pro
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=CQ84KC4X8YKW8
Requires at least: 3.5
Tested up to: 3.7
Stable tag: 2.4.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

4 sliders in 1! Choose from Nivo Slider, Flex Slider, Coin Slider or Responsive Slides.

== Description ==

http://www.youtube.com/watch?v=I7IfkV6iLqo

Meta Slider is a flexible, intuitive slideshow administration plugin that lets you choose between 4 jQuery sliders.

* **Nivo Slider** (Responsive, 16 transition effects, 4 themes)
* **Coin Slider** (4 transition effects)
* **Flex Slider 2** (Responsive, 2 transition effects, carousel mode)
* **Responsive Slides** (Responsive, fade effect only, incredibly light weight!)

Features:

* Create unlimited fully featured image slideshows
* Simple, easy to use interface
* Choose from 4 slideshow types
* Live admin preview
* Built in Widget and Shortcode to easily embed your slideshows into Posts, Pages & Templates
* Configure each slideshow individually *(speed, theme, hover pause, width, height, easing etc)*
* Fully integrated with the new style WordPress Media Manager
* Add captions (html supported) and URLs to slides
* Drag and drop reordering
* Intelligent image cropping
* Fully localised
* Multi Site compatible
* Lightweight - only the minimum JavaScript/CSS is outputted to your page
* Minimal upgrade nags(!)
* Lots more!

Want More? Get the **Meta Slider Pro** addon pack to add support for:

* YouTube & Vimeo slides
* HTML slides
* Layer slides with CSS3 animations
* Dynamic Post Feed/Featured Image Slides (content slider)
* Custom Themes
* Thumbnail Navigation (new!)

Meta Slider has been translated into the following languages:

* French (thanks to fb-graphiklab)
* Spanish (thanks to eltipografico)
* Polish (thanks to gordon34)
* Chinese (thanks to 断青丝)

Read more and thanks to:

* [http://flexslider.woothemes.com/](http://flexslider.woothemes.com/)
* [http://responsive-slides.viljamis.com/](http://responsive-slides.viljamis.com/)
* [http://workshop.rs/projects/coin-slider/](http://workshop.rs/projects/coin-slider/)
* [http://dev7studios.com/nivo-slider/](http://dev7studios.com/nivo-slider/)

Find out more at http://www.metaslider.com

== Installation ==

The easy way:

1. Go to the Plugins Menu in WordPress
1. Search for "Meta Slider"
1. Click 'Install'

The not so easy way:

1. Upload the `ml-slider` folder to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Manage your slideshows using the 'Meta Slider' menu option

== Frequently Asked Questions ==

http://www.metaslider.com/documentation/

= How do I include a slideshow in the header of my site? =

http://www.youtube.com/watch?v=gSsWgd66Jjk

You will need to paste the "Template Include" code into your theme (you can find this in the 'Usage' section underneath the slideshow settings)

* Go to Appearance > Editor in WordPress
* Edit a file called 'header.php' (or similar)
* Find the correct place to add it (for example above or below the logo)
* Paste in the code and save.

If you want to include the slideshow on your homepage only, use this code:

`<?php 
if (is_front_page() || is_home()) {
    echo do_shortcode("[metaslider id=123]"); //replace 123 with slider ID
} 
?>`

= It's not working - what can I do? =

Check out the troubleshooting page here: 

http://www.metaslider.com/documentation/troubleshooting

= Does Meta Slider work with translation plugins? =

We have tested with qTranslate and Polylang. We're working with the WPML team to bring in WMPL compatibility.

= Is it multi site compatible? =

Yes!

= Meta Slider is cropping my images in the wrong place - what can I do? =

Meta Slider uses responsive slideshow libraries. This means images will always be stretched or squeezed to 100% of the *width* of the slideshow. If you're trying to mix landscape and portrait images, you'll probably find that the top and bottom of your portrait images are being cropped. This is because Meta Slider wants to keep the slideshow a consistent height for all slides, and to do this it needs to crop your portrait images down in height so they match your landscape images.

The best way to fix this is to ensure your images are correctly sized before adding them into Meta Slider (or at least ensure they all have similar width:height ratios), then set the slideshow size to the same size as your images. In this case, Meta Slider will not crop your images. If you're looking for a gallery style slideshow then you might wish to 'pad' your portrait images with white bars to the left and right, to effectively turn your portrait images into landscape images.

= What is Smart Crop? =

Smart crop takes the largest, most central portion of an image that it can, whilst ensuring the width:height ratio of the image matches the width:height ratio of the slideshow itself. This is essential for ensuring your responsive slideshow height remains fixed when navigating between different sized images.

= Why is Coin Slider tiling my images? =

Coin Slider is not responsive. This means (unlike the other slideshows in Meta Slider) it will not stretch images to fill the slideshow - it will tile them instead. You need to ensure your source images are the same size (or larger) as your slideshow size.

== Screenshots ==

1. Meta Slider - for live demos see http://www.metaslider.com/examples/
2. Nivo Slider example
3. Coin Slider example
4. Flex Slider example
5. Carousel Example
6. Administration panel - selecting slides

== Changelog ==

= 2.4.2 [17/10/13] =
* Fix: qTranslate caption & URL parsing for image slides

= 2.4.1 [17/10/13] =
* Fix: PHP Warning (reported by & thanks to: fgirardey)

= 2.4 [16/10/13] =
* Fix: FlexSlider styling in twenty twelve theme
* Fix: IE10 - "Caption" placeholder text being saved as actual caption
* Improvement: Settings table tidied up
* Improvement: New slides are resized during addition to the slideshow
* Improvement: Default slideshow size increased to 700x300
* Improvement: Image filename now displayed for each slide (instead of image dimensions)
* Improvement: Replace deprecated 'live()' jQuery call with 'on()'
* Improvement: Polish Language pack added (thanks to gordon34)
* Improvement: Chinese language pack added (thanks to 断青丝)
* Improvement: 'metaslider_resized_image_url' filter added (could be used to disable cropping)
* Change: qTranslate support for slide URLs (see: http://screencast.com/t/FrsrptyhoT)
* Change: PolyLang fix to ensure slides are extracted for all languages (set up a new slideshow for each language)
* Change: WPML fix to ensure slides are extracted for all languages (set up a new slideshow for each language)


= 2.3 [18/09/13] =
* Improvement: Flex Slider upgraded to v2.2
* Improvement: Responsive Slides upgraded to v1.54
* Improvement: 'Create first slideshow' prompt added for new users
* Change: 'scoped' attribute removed from inline CSS tag until browsers catch up with supporting it properly. A new filter has been added: "metaslider_style_attributes" if you wish to add the scoped attribute back in.
* Change: wp_footer check removed due to confusion
* New Feature: 'metaslider_max_tabs' filter added to convert tab list to ordered drop down menu
* Fix: Remove 'Insert Media' tab from 'Add Slide' modal (WP 3.6 only)
* New Feature: Filters added to allow modification of image slide HTML
* Improvement: Settings area tidied up
* Improvement: Image URL Field less restrictive
* Improvement: HTML Output tidied up

= 2.2.2 [21/08/13] =
* Improvement: System check added with option to dismiss messages. Checks made for: role scoper plugin, wp_footer, wordpress version & GD/ImageMagick.

= 2.2.1 [08/08/13] =
* Fix: Responsive slides styling in FireFox (reported by and thanks to: dznr418)
* Fix: Flex Slider carousel causing browser to crash in some circumstances

= 2.2 [01/08/13] =
* Fix: Paragraph tags being added to output using Nivo Slider

= 2.1.6 [22/07/2013] =
* Fix: Use the original image file if the slideshow size is the same size as the image file
* Fix: Conflict with Advanced Post Types Order plugin
* Fix: Colorbox conflict when using resizable elements in lightbox
* Improvement: Refresh slides after clicking 'save'
* Improvement: Ensure taxonomy category exists before tagging slide to slideshow
* Fix: Only submit form when submit button is clicked (not all buttons)
* Fix: Coin slider caption width in FireFox
* Improvement: Added hook to adjust carousel image margin

= 2.1.5 [24/05/13] =
* Fix: HTML 5 Validation

= 2.1.4 [21/05/13] =
* Fix: Widget markup invalid (reported by and thanks to: CarlosCanvas)

= 2.1.3 [21/05/13] =
* Fix: User Access Manager Plugin incompatibility issues (reported by and thanks to: eltipografico)

= 2.1.2 [21/05/13] =
* Fix: Nivo Slider theme select dropdown (reported by and thanks to: macks)
* Fix: HTML5 Validation fix for inline styles
* Improvement: Title field added to widget (suggested by and thanks to: pa_esp)
* New feature: Spanish language pack (thanks to eltipografico)

= 2.1.1 [13/05/13] =
* Fix: PHP version compatibility

= 2.1 [12/05/13] =
* New feature: Widget added
* New feature: System check added (checks for required image libraries and WordPress version)
* Fix: Multiple CSS fixes added for popular themes
* Fix: Flex slider shows first slide when JS is disabled
* Improvement: Display warning message when unchecking Print JS and Print CSS options
* Improvement: Coinslider navigation centered

= 2.0.2 [02/05/13] =
* Fix: PHP Error when using slides the same size as the slideshow

= 2.0.1 [28/04/13] =
* New feature: French language pack (thanks to: fb-graphiklab)
* Fix: Use transparent background on default flexslider theme
* Fix: Set direction to LTR for flexslider viewport (fix for RTL languages)
* Fix: Nivoslider HTML Captions
* Fix: Responsive slides navigation positioning

= 2.0 [21/04/13] =
* Fix: Responsive slides navigation styling
* Fix: Update slide order on save
* Fix: Smart crop edge cases
* Fix: Flexslider navigation overflow

= 2.0-betaX [17/04/13] =
* Improvement: Error messages exposed in admin is Meta Slider cannot load the slides
* Improvement: Load default settings if original settings are corrupt/incomplete
* Fix: Smart Crop ratio
* Fix: UTF-8 characters in captions (reported by and thanks to: javitopo)
* Fix: JetPack Photo not loading images (reported by and thanks to: Jason)
* Fix: Double slash on jQuery easing path
* Fix: Paragraph tags outputted in JavaScript (reported by and thanks to: CrimsonRaddish)

= 2.0-beta =
* New feature: Preview slideshows in admin control panel
* New feature: 'Easing' options added to flex slider
* New feature: 'Carousel mode' option added for flex slider
* New feature: 'Auto play' option added
* New feature: 'Smart Crop' setting ensures your slideshow size remains consitent regardless of image dimensions
* New feature: 'Center align slideshow' option added for all sliders
* New feature: Coin Slider upgraded to latest version, new options now exposed in Meta Slider
* New feature: Captions now supported by responsive slides
* Improvement: Responsive AJAX powered administration screen
* Improvement: Code refactored
* Improvement: Flex Slider captions now sit over the slide
* Fix: Nivo slider invalid markup (reported by and thanks to: nellyshark)
* Fix: JS && encoding error (reported by and thanks to: neefje)

= 1.3 [28/02/13] =
* Renamed to Meta Slider (previously ML Slider)
* Improvement: Admin styling cleaned up
* Improvement: Code refactored
* Improvement: Plugin localised
* Improvement: Template include PHP code now displayed on slider edit page
* Improvement: jQuery tablednd replaced with jQuery sortable for reordering slides
* New feature: Open URL in new window option added
* Improvement: max-width css rule added to slider wrapper
* Fix: UTF-8 support in captions (reported by and thanks to: petergluk)
* Fix: JS && encoding error (reported by and thanks to: neefje)
* Fix: Editors now have permission to use MetaSlider (reported by and thanks to: rritsud)

= 1.2.1 [20/02/13] =
* Fix: Number of slides per slideshow limited to WordPress 'blog pages show at most' setting (reported by and thanks to: Kenny)
* Fix: Add warning when BMP file is added to slider (reported by and thanks to: MadBong)
* Fix: Allow images smaller than default thumbnail size to be added to slider (reported by and thanks to: MadBong)

= 1.2 [19/02/13] =
* Improvement: Code refactored
* Fix: Unable to assign the same image to more than one slider
* Fix: JavaScript error when jQuery is loaded in page footer
* Improvement: Warning notice when the slider has unsaved changes
* Fix: Captions not being escaped (reported by and thanks to: papabeers)
* Improvement: Add multiple files to slider from Media Browser

= 1.1 [18/02/13] =
* Improvement: Code refactored
* Fix: hitting [enter] brings up Media Library
* Improvement: Settings for new sliders now based on the last edited slider
* Improvement: More screenshots added

= 1.0.1 [17/02/13] =
* Fix: min version incorrect (should be 3.5)

= 1.0 [15/02/13] =
* Initial version

== Upgrade Notice ==
