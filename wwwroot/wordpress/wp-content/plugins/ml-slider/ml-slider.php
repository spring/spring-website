<?php
/*
 * Plugin Name: Meta Slider
 * Plugin URI: http://www.metaslider.com
 * Description: 4 sliders in 1! Choose from Nivo Slider, Flex Slider, Coin Slider or Responsive Slides.
 * Version: 2.6.2
 * Author: Matcha Labs
 * Author URI: http://www.matchalabs.com
 * License: GPLv2 or later
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */

// disable direct access
if (!defined('ABSPATH')) exit;

define('METASLIDER_VERSION', '2.6.2');
define('METASLIDER_BASE_URL', plugins_url('ml-slider') . '/'); //plugin_dir_url(__FILE__)
define('METASLIDER_ASSETS_URL', METASLIDER_BASE_URL . 'assets/');
define('METASLIDER_BASE_DIR_LONG', dirname(__FILE__));
define('METASLIDER_INC_DIR', METASLIDER_BASE_DIR_LONG . '/inc/');

// include slider classes
require_once( METASLIDER_INC_DIR . 'slider/metaslider.class.php' );
require_once( METASLIDER_INC_DIR . 'slider/metaslider.coin.class.php' );
require_once( METASLIDER_INC_DIR . 'slider/metaslider.flex.class.php' );
require_once( METASLIDER_INC_DIR . 'slider/metaslider.nivo.class.php' );
require_once( METASLIDER_INC_DIR . 'slider/metaslider.responsive.class.php' );

// include slide classes
require_once( METASLIDER_INC_DIR . 'slide/metaslide.class.php' );
require_once( METASLIDER_INC_DIR . 'slide/metaslide.image.class.php' );

// include image helper
require_once( METASLIDER_INC_DIR . 'metaslider.imagehelper.class.php' );

// include widget
require_once( METASLIDER_INC_DIR . 'metaslider.widget.class.php' );

// include system check
require_once( METASLIDER_INC_DIR . 'metaslider.systemcheck.class.php' );

/**
 * Register the plugin.
 *
 * Display the administration panel, insert JavaScript etc.
 */
class MetaSliderPlugin {

    /** Current Slider **/
    var $slider = null;

    /**
     * Constructor
     */
    public function __construct() {
        // create the admin menu/page
        add_action('admin_menu', array($this, 'register_admin_menu'), 9553);

        // register slider post type and taxonomy
        add_action('init', array($this, 'register_post_type'));
        add_action('init', array($this, 'register_taxonomy'));
        add_action('init', array($this, 'load_plugin_textdomain'));

        // register shortcodes
        add_shortcode('metaslider', array($this, 'register_shortcode'));
        add_shortcode('ml-slider', array($this, 'register_shortcode')); // backwards compatibility

        add_filter('media_upload_tabs', array($this,'custom_media_upload_tab_name'), 998);
        add_filter('media_view_strings', array($this, 'custom_media_uploader_tabs'), 5);
        add_action('media_upload_vimeo', array($this, 'metaslider_pro_tab'));
        add_action('media_upload_youtube', array($this, 'metaslider_pro_tab'));
        add_action('media_upload_post_feed', array($this, 'metaslider_pro_tab'));
        add_action('media_upload_layer', array($this, 'metaslider_pro_tab'));

        add_filter('media_buttons_context', array($this, 'insert_metaslider_button'));
        add_action('admin_footer', array($this, 'admin_footer'));
        
        // add 'go pro' link to plugin options
        $plugin = plugin_basename(__FILE__);
        add_filter("plugin_action_links_{$plugin}", array($this,'upgrade_to_pro'));

        $this->register_slide_types();
    }

    /**
     * Check our WordPress installation is compatible with Meta Slider
     */
    public function system_check(){
        $systemCheck = new MetaSliderSystemCheck();
        $systemCheck->check();
    }

    /**
     * Add settings link on plugin page
     */
    public function upgrade_to_pro($links) { 
        if (function_exists('is_plugin_active') && !is_plugin_active('ml-slider-pro/ml-slider-pro.php')) {
            $links[] = '<a href="http://www.metaslider.com/upgrade" target="_blank">' . __("Go Pro", "metaslider") . '</a>'; 
        }
        return $links; 
    }
     
    /**
     * Return the meta slider pro upgrade iFrame
     */
    public function metaslider_pro_tab() {
        if (function_exists('is_plugin_active') && !is_plugin_active('ml-slider-pro/ml-slider-pro.php')) {
            return wp_iframe(array($this, 'iframe'));
        }
    }

    /**
     * Media Manager iframe HTML
     */
    public function iframe() {
        wp_enqueue_style('metaslider-admin-styles', METASLIDER_ASSETS_URL . 'metaslider/admin.css', false, METASLIDER_VERSION);
        wp_enqueue_script('google-font-api', 'http://fonts.googleapis.com/css?family=PT+Sans:400,700');
        
        $link = apply_filters('metaslider_hoplink', 'http://www.metaslider.com/upgrade/');
        $link .= '?utm_source=lite&amp;utm_medium=more-slide-types&amp;utm_campaign=pro';

        echo "<div class='metaslider'>";
        echo "<p style='text-align: center; font-size: 1.2em; margin-top: 50px;'>Get the Pro Addon pack to add support for: <b>Post Feed</b> Slides, <b>YouTube</b> Slides, <b>HTML</b> Slides & <b>Vimeo</b> Slides</p>";
        echo "<p style='text-align: center; font-size: 1.2em;'><b>NEW: </b> Animated HTML <b>Layer</b> Slides (with an awesome Drag & Drop editor!)</p>";
        echo "<p style='text-align: center; font-size: 1.2em;'><b></b> Live Theme Editor!</p>";
        echo "<p style='text-align: center; font-size: 1.2em;'><b>NEW:</b> Thumbnail Navigation for Flex & Nivo Slider!</p>";
        echo "<a class='probutton' href='{$link}' target='_blank'>Get <span class='logo'><strong>Meta</strong>Slider</span><span class='super'>Pro</span></a>";
        echo "<span class='subtext'>Opens in a new window</span>";
        echo "</div>";
    }

    /**
     * Register our slide types
     */
    private function register_slide_types() {
        $image = new MetaImageSlide();
    }

    /**
     * Initialise translations
     */
    public function load_plugin_textdomain() {
        load_plugin_textdomain('metaslider', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }

    /**
     * Update the tab options in the media manager
     */
    public function custom_media_uploader_tabs( $strings ) {
        //update strings
        if ((isset($_GET['page']) && $_GET['page'] == 'metaslider')) {
            $strings['insertMediaTitle'] = __("Image", "metaslider");
            $strings['insertIntoPost'] = __("Add to slider", "metaslider");
            // remove options
            if (isset($strings['createGalleryTitle'])) unset($strings['createGalleryTitle']);
            if (isset($strings['insertFromUrlTitle'])) unset($strings['insertFromUrlTitle']);
        }
        return $strings;
    }

    /**
     * Add extra tabs to the default wordpress Media Manager iframe
     * 
     * @var array existing media manager tabs
     */
    public function custom_media_upload_tab_name($tabs) {
        $metaslider_tabs = array('post_feed', 'layer', 'youtube', 'vimeo');

        // restrict our tab changes to the meta slider plugin page
        if ((isset($_GET['page']) && $_GET['page'] == 'metaslider') || (isset($_GET['tab']) && in_array($_GET['tab'], $metaslider_tabs))) {
            $newtabs = array();

            if (function_exists('is_plugin_active') && !is_plugin_active('ml-slider-pro/ml-slider-pro.php')) {
                $newtabs = array( 
                    'post_feed' => __("Post Feed", "metaslider"),
                    'vimeo' => __("Vimeo", "metaslider"),
                    'youtube' => __("YouTube", "metaslider"),
                    'layer' => __("Layer Slide", "metaslider")
                );
            }

            if (isset($tabs['nextgen'])) unset($tabs['nextgen']);

            return array_merge($tabs, $newtabs);
        }

        return $tabs;
    }

    /**
     * Rehister admin styles
     */
    public function register_admin_styles() {
        wp_enqueue_style('metaslider-admin-styles', METASLIDER_ASSETS_URL . 'metaslider/admin.css', false, METASLIDER_VERSION);
        wp_enqueue_style('metaslider-colorbox-styles', METASLIDER_ASSETS_URL . 'colorbox/colorbox.css', false, METASLIDER_VERSION);
        wp_enqueue_style('metaslider-tipsy-styles', METASLIDER_ASSETS_URL . 'tipsy/tipsy.css', false, METASLIDER_VERSION);

        do_action('metaslider_register_admin_styles');
    }

    /**
     * Register admin JavaScript
     */
    public function register_admin_scripts() {
        if (wp_script_is('wp-auth-check', 'queue')) {
            // meta slider checks for active AJAX requests in order to show the spinner
            // .. but the auth-check runs an AJAX request every 15 seconds
            // deregister the script that displays the login panel if the user becomes logged
            // out at some point
            // todo: implement some more intelligent request checking
            wp_deregister_script('wp-auth-check');
            wp_register_script('wp-auth-check', null); // fix php notice
        }

        // media library dependencies
        wp_enqueue_media();

        // plugin dependencies
        wp_enqueue_script('jquery-ui-core', array('jquery'));
        wp_enqueue_script('jquery-ui-sortable', array('jquery', 'jquery-ui-core'));
        wp_enqueue_script('metaslider-colorbox', METASLIDER_ASSETS_URL . 'colorbox/jquery.colorbox-min.js', array('jquery'), METASLIDER_VERSION);
        wp_enqueue_script('metaslider-tipsy', METASLIDER_ASSETS_URL . 'tipsy/jquery.tipsy.js', array('jquery'), METASLIDER_VERSION);
        wp_enqueue_script('metaslider-admin-script', METASLIDER_ASSETS_URL . 'metaslider/admin.js', array('jquery', 'metaslider-tipsy', 'media-upload'), METASLIDER_VERSION);
        wp_enqueue_script('metaslider-admin-addslide', METASLIDER_ASSETS_URL . 'metaslider/image/image.js', array('metaslider-admin-script'), METASLIDER_VERSION);

        // localise the JS
        wp_localize_script( 'metaslider-admin-addslide', 'metaslider_image', array( 
            'addslide_nonce' => wp_create_nonce('metaslider_addslide')
        ));

        // localise the JS
        wp_localize_script( 'metaslider-admin-script', 'metaslider', array( 
            'url' => __("URL", "metaslider"), 
            'caption' => __("Caption", "metaslider"),
            'new_window' => __("New Window", "metaslider"),
            'confirm' => __("Are you sure?", "metaslider"),
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'resize_nonce' => wp_create_nonce('metaslider_resize'),
            'iframeurl' => METASLIDER_BASE_URL . 'preview.php',
            'useWithCaution' => __("Caution: This setting is for advanced developers only. If you're unsure, leave it checked.", "metaslider")
        ));

        do_action('metaslider_register_admin_scripts');
    }
    
    /**
     * Add the menu page
     */
    public function register_admin_menu() {
        $title = apply_filters('metaslider_menu_title', "Meta Slider");

        if ($title == "Meta Slider") {
            $title = "Meta Slider Lite";
        }

        $page = add_menu_page($title, $title, 'edit_others_posts', 'metaslider', array(
            $this, 'render_admin_page'
        ), METASLIDER_ASSETS_URL . 'metaslider/matchalabs.png', 9501);

        // ensure our JavaScript is only loaded on the Meta Slider admin page
        add_action('admin_print_scripts-' . $page, array($this, 'register_admin_scripts'));
        add_action('admin_print_styles-' . $page, array($this, 'register_admin_styles'));
        add_action('load-' . $page, array($this, 'help_tab'));
    }

    /**
     * Upgrade CTA.
     */
    public function go_pro_cta() {
        if (function_exists('is_plugin_active') && !is_plugin_active('ml-slider-pro/ml-slider-pro.php')) {
            $link = apply_filters('metaslider_hoplink', 'http://www.metaslider.com/upgrade/');

            $link .= '?utm_source=lite&amp;utm_medium=nag&amp;utm_campaign=pro';

            $goPro = "<div style='display: none;' id='screen-options-link-wrap'><a target='_blank' class='show-settings' href='{$link}'>Meta Slider Lite v" . METASLIDER_VERSION . " - " . 
                __('Upgrade to Pro $19', "metaslider") . 
                "</a></div>";

            echo $goPro;
        }
    }

    /**
     * 
     */
    public function help_tab() {
        $screen = get_current_screen();

        // documentation tab
        $screen->add_help_tab( array(
            'id'    => 'documentation',
            'title' => __('Documentation'),
            'content'   => "<p><a href='http://www.metaslider.com/documentation/' target='blank'>Meta Slider Documentation</a></p>",
        ) );
    }
    
    /**
     * Register ML Slider post type
     */
    public function register_post_type() {
        register_post_type('ml-slider', array(
            'query_var' => false,
            'rewrite' => false,
            'public' => true,
            'show_ui' => false,
            'labels' => array(
                'name' => 'Meta Slider'
            )
        ));
    }

    /**
     * Register taxonomy to store slider => slides relationship
     */
    public function register_taxonomy() {
        register_taxonomy( 'ml-slider', 'attachment', array(
            'hierarchical' => true,
            'public' => false,
            'query_var' => false,
            'rewrite' => false
        ));
    }

    /**
     * Shortcode used to display slideshow
     *
     * @return string HTML output of the shortcode
     */
    public function register_shortcode($atts) {
        if (!isset($atts['id'])) {
            return false;
        }

        // we have an ID to work with
        $slider = get_post($atts['id']);

        // check the slider is published
        if ($slider->post_status != 'publish') return false;

        // lets go
        $this->set_slider($atts['id'], $atts);
        $this->slider->enqueue_scripts();
        
        return $this->slider->render_public_slides();
    }

    /**
     * Set the current slider
     */
    public function set_slider($id, $shortcode_settings = array()) {
        $type = 'flex';

        $settings = array_merge(get_post_meta($id, 'ml-slider_settings', true), $shortcode_settings);

        if (isset($settings['type']) && in_array($settings['type'], array('flex', 'coin', 'nivo', 'responsive'))) {
            $type = $settings['type'];
        }

        $this->slider = $this->create_slider($type, $id, $shortcode_settings);
    }

    /**
     * Create a new slider based on the sliders type setting
     */
    private function create_slider($type, $id, $shortcode_settings) {
        switch ($type) {
            case('coin'):
                return new MetaCoinSlider($id, $shortcode_settings);
            case('flex'):
                return new MetaFlexSlider($id, $shortcode_settings);
            case('nivo'):
                return new MetaNivoSlider($id, $shortcode_settings);
            case('responsive'):
                return new MetaResponsiveSlider($id, $shortcode_settings);
            default:
                return new MetaFlexSlider($id, $shortcode_settings);
        }
    }

    /**
     * Handle slide uploads/changes
     */
    public function admin_process() {
        // default to the latest slider
        $slider_id = $this->find_slider('modified', 'DESC');

        // delete a slider
        if (isset($_GET['delete'])) {
            $slider_id = $this->delete_slider(intval($_GET['delete']));
        }

        // create a new slider
        if (isset($_GET['add'])) {
            $slider_id = $this->add_slider();
        }

        if (isset($_REQUEST['id'])) {
            $slider_id = $_REQUEST['id'];
        }

        if ($slider_id > 0) {
            $this->set_slider($slider_id);
        }
    }

    /**
     * Create a new slider
     */
    private function add_slider() {
        // check nonce
        check_admin_referer("metaslider_add_slider");

        $defaults = array();

        // if possible, take a copy of the last edited slider settings in place of default settings
        if ($last_modified = $this->find_slider('modified', 'DESC')) {
            $defaults = get_post_meta($last_modified, 'ml-slider_settings', true);
        }

        // use the default settings if we can't find anything more suitable.
        if (empty($defaults)) {
            $slider = new MetaSlider($id, array());
            $defaults = $slider->get_default_parameters();
        }

        // insert the post
        $id = wp_insert_post(array(
            'post_title' => __("New Slider", "metaslider"),
            'post_status' => 'publish',
            'post_type' => 'ml-slider'
        ));

        // insert the post meta
        add_post_meta($id, 'ml-slider_settings', $defaults, true);

        // create the taxonomy term, the term is the ID of the slider itself
        wp_insert_term($id, 'ml-slider');

        return $id;
    }

    /**
     * Delete a slider (send it to trash)
     *
     * @param int $id
     */
    private function delete_slider($id) {
        // check nonce
        check_admin_referer("metaslider_delete_slider");

        // send the post to trash
        wp_update_post(array(
            'ID' => $id,
            'post_status' => 'trash'
        ));

        return $this->find_slider('date', 'DESC');
    }

    /**
     * Find a single slider ID. For example, last edited, or first published.
     *
     * @param string $orderby field to order.
     * @param string $order direction (ASC or DESC).
     * @return int slider ID.
     */
    private function find_slider($orderby, $order) {
        $args = array(
            'force_no_custom_order' => true,
            'post_type' => 'ml-slider',
            'num_posts' => 1,
            'post_status' => 'publish',
            'suppress_filters' => 1, // wpml, ignore language filter
            'orderby' => $orderby,
            'order' => $order
        );

        $the_query = new WP_Query($args);
        
        while ($the_query->have_posts()) {
            $the_query->the_post();
            return $the_query->post->ID;
        }

        wp_reset_query();

        return false;
    }


    /**
     * Get sliders. Returns a nicely formatted array of currently
     * published sliders.
     *
     * @param string $sort_key
     * @return array all published sliders
     */
    private function all_meta_sliders($sort_key = 'date') {
        $sliders = false;
        
        // list the tabs
        $args = array(
            'post_type' => 'ml-slider',
            'post_status' => 'publish',
            'orderby' => $sort_key,
            'suppress_filters' => 1, // wpml, ignore language filter
            'order' => 'ASC',
            'posts_per_page' => -1
        );

        $args = apply_filters('metaslider_all_meta_sliders_args', $args);
        
        $the_query = new WP_Query($args);
        
        while ($the_query->have_posts()) {
            $the_query->the_post();
            $active = $this->slider && ($this->slider->id == $the_query->post->ID) ? true : false;
            
            $sliders[] = array(
                'active' => $active,
                'title' => get_the_title(),
                'id' => $the_query->post->ID
            );
        }

        wp_reset_query();
        
        return $sliders;
    }

    /**
     * Compare array values
     *
     * @param array $elem1
     * @param array $elem2
     * @return bool
     */
	private function compare_elems($elem1, $elem2) {
	    return $elem1['priority'] > $elem2['priority'];
	}

	/**
	 *
	 * @param array $aFields - array of field to render
	 * @return string
	 */
    public function build_settings_rows($aFields) {
    	// order the fields by priority
    	uasort($aFields, array($this, "compare_elems"));

    	$return = "";

    	// loop through the array and build the settings HTML
    	foreach ($aFields as $id => $row) {
    		// checkbox input type
    		if ($row['type'] == 'checkbox') {
    			$return .= "<tr><td class='tipsy-tooltip' title=\"{$row['helptext']}\">{$row['label']}</td><td><input class='option {$row['class']} {$id}' type='checkbox' name='settings[{$id}]' {$row['checked']} />";

    			if (isset($row['after'])) {
    				$return .= "<span class='after'>{$row['after']}</span>";
    			}

    			$return .= "</td></tr>";
    		}

    		// navigation row
    		if ($row['type'] == 'navigation') {
    			$navigation_row = "<tr class='{$row['type']}'><td class='tipsy-tooltip' title=\"{$row['helptext']}\">{$row['label']}</td><td>";

    			foreach ($row['options'] as $k => $v) {
    				$checked = checked($k, $row['value'], false);
    				$disabled = $k == 'thumbnails' ? 'disabled' : '';
    				$navigation_row .= "<label><input type='radio' name='settings[{$id}]' value='{$k}' {$checked} {$disabled}/>{$v['label']}</label><br />";
    			}

    			$navigation_row .= "</td></tr>";

    			$return .= apply_filters('metaslider_navigation_options', $navigation_row, $this->slider);
    		}

    		// navigation row
    		if ($row['type'] == 'radio') {
    			$navigation_row = "<tr class='{$row['type']}'><td class='tipsy-tooltip' title=\"{$row['helptext']}\">{$row['label']}</td><td>";

    			foreach ($row['options'] as $k => $v) {
    				$checked = checked($k, $row['value'], false);
    				$class = isset($v['class']) ? $v['class'] : "";
    				$navigation_row .= "<label><input type='radio' name='settings[{$id}]' value='{$k}' {$checked} class='radio {$class}'/>{$v['label']}</label><br />";
    			}

    			$navigation_row .= "</td></tr>";

    			$return .= apply_filters('metaslider_navigation_options', $navigation_row, $this->slider);
    		}

    		// header/divider row
    		if ($row['type'] == 'divider') {
    			$return .= "<tr class='{$row['type']}'><td colspan='2' class='divider'><b>{$row['value']}</b></td></tr>";
    		}

    		// slideshow select row
    		if ($row['type'] == 'slider-lib') {
    			$return .= "<tr class='{$row['type']}'><td colspan='2' class='slider-lib-row'>";

    			foreach ($row['options'] as $k => $v) {
    				$checked = checked($k, $row['value'], false);
    				$return .= "<input class='select-slider' id='{$k}' rel='{$k}' type='radio' name='settings[type]' value='{$k}' {$checked} />
    				<label for='{$k}'>{$v['label']}</label>";
    			}

			    $return .= "</td></tr>";
    		}

    		// number input type
    		if ($row['type'] == 'number') {
    			$return .= "<tr class='{$row['type']}'><td class='tipsy-tooltip' title=\"{$row['helptext']}\">{$row['label']}</td><td><input class='option {$row['class']} {$id}' type='number' min='{$row['min']}' max='{$row['max']}' step='{$row['step']}' name='settings[{$id}]' value='{$row['value']}' /><span class='after'>{$row['after']}</span></td></tr>";
    		}

    		// select drop down
    		if ($row['type'] == 'select') {
    			$return .= "<tr class='{$row['type']}'><td class='tipsy-tooltip' title=\"{$row['helptext']}\">{$row['label']}</td><td><select class='option {$row['class']} {$id}' name='settings[{$id}]'>";
    			foreach ($row['options'] as $k => $v) {
    				$selected = selected($k, $row['value'], false);
    				$return .= "<option class='{$v['class']}' value='{$k}' {$selected}>{$v['label']}</option>";
    			}
    			$return .= "</select></td></tr>";
    		}

    		// theme drop down
    		if ($row['type'] == 'theme') {
    			$return .= "<tr class='{$row['type']}'><td class='tipsy-tooltip' title=\"{$row['helptext']}\">{$row['label']}</td><td><select class='option {$row['class']} {$id}' name='settings[{$id}]'>";
    			$themes = "";

    			foreach ($row['options'] as $k => $v) {
    				$selected = selected($k, $row['value'], false);
    				$themes .= "<option class='{$v['class']}' value='{$k}' {$selected}>{$v['label']}</option>";
    			}

    			$return .= apply_filters('metaslider_get_available_themes', $themes, $this->slider->get_setting('theme'));

    			$return .= "</select></td></tr>";
    		}

    		// text input type
      		if ($row['type'] == 'text') {
    			$return .= "<tr class='{$row['type']}'><td class='tipsy-tooltip' title=\"{$row['helptext']}\">{$row['label']}</td><td><input class='option {$row['class']} {$id}' type='text' name='settings[{$id}]' value='{$row['value']}' /></td></tr>";
    		}

    		// text input type
      		if ($row['type'] == 'title') {
    			$return .= "<tr class='{$row['type']}'><td class='tipsy-tooltip' title=\"{$row['helptext']}\">{$row['label']}</td><td><input class='option {$row['class']} {$id}' type='text' name='{$id}' value='{$row['value']}' /></td></tr>";
    		}
    	}

    	return $return;
    }

    /**
     * Return an indexed array of all easing options
     *
     * @return array
     */
    private function get_easing_options() {
        $options = array(
            'linear','swing','jswing','easeInQuad','easeOutQuad','easeInOutQuad',
            'easeInCubic','easeOutCubic','easeInOutCubic','easeInQuart',
            'easeOutQuart','easeInOutQuart','easeInQuint','easeOutQuint',
            'easeInOutQuint','easeInSine','easeOutSine','easeInOutSine',
            'easeInExpo','easeOutExpo','easeInOutExpo','easeInCirc','easeOutCirc',
            'easeInOutCirc','easeInElastic','easeOutElastic','easeInOutElastic',
            'easeInBack','easeOutBack','easeInOutBack','easeInBounce','easeOutBounce',
            'easeInOutBounce'
        );

        foreach ($options as $option) {
            $return[$option] = array(
            	'label' => ucfirst(preg_replace('/(\w+)([A-Z])/U', '\\1 \\2', $option)),
            	'class' => ''
            );
        }

        return $return;
    }


    /**
     * Render the admin page (tabs, slides, settings)
     */
    public function render_admin_page() {
        $this->admin_process();
        $this->go_pro_cta();
        $this->system_check();
        $max_tabs = apply_filters('metaslider_max_tabs', 0);
        ?>

        <script type='text/javascript'>
            var metaslider_slider_id = <?php echo $this->slider->id; ?>;
            var metaslider_pro_active = <?php echo function_exists('is_plugin_active') && is_plugin_active('ml-slider-pro/ml-slider-pro.php') ? 'true' : 'false' ?>;
        </script>

        <div class="wrap metaslider">
            <form accept-charset="UTF-8" action="?page=metaslider&amp;id=<?php echo $this->slider->id ?>" method="post">
                <?php 
                    if ($this->slider) {
                        wp_nonce_field('metaslider_save_' . $this->slider->id);
                    }

                    $title = "";
                    $add_url = wp_nonce_url("?page=metaslider&amp;add=true", "metaslider_add_slider");

                    if ($tabs = $this->all_meta_sliders()) {
                        if ($max_tabs && count($tabs) > $max_tabs) {
                            if (isset($_GET['add']) && $_GET['add'] == 'true') {
                                echo "<div id='message' class='updated'><p>" . __("New slideshow created. Click 'Add Slide' to get started!", "metaslider") . "</p></div>";
                            }
                            echo "<div style='margin-top: 20px;'><label for='select-slider'>Select Slider: </label>";
                            echo "<select name='select-slider' onchange='if (this.value) window.location.href=this.value'>";

                            $tabs = $this->all_meta_sliders('title');

                            foreach ($tabs as $tab) {
                                $selected = $tab['active'] ? " selected" : "";

                                if ($tab['active']) {
                                    $title = $tab['title'];
                                }

                                echo "<option value='?page=metaslider&amp;id={$tab['id']}'{$selected}>{$tab['title']}</option>";

                            }
                            echo "</select> " . __('or', "metaslider") . " ";
                            echo "<a href='{$add_url}'>" . __('Add New Slideshow', "metaslider") . "</a></div>";
                        } else {
                            echo "<h3 class='nav-tab-wrapper'>";
                            foreach ($tabs as $tab) {
                                if ($tab['active']) {
                                    echo "<div class='nav-tab nav-tab-active'><input type='text' name='title'  value='" . $tab['title'] . "' onfocus='this.style.width = ((this.value.length + 1) * 9) + \"px\"' /></div>";
                                } else {
                                    echo "<a href='?page=metaslider&amp;id={$tab['id']}' class='nav-tab'>" . $tab['title'] . "</a>";
                                }
                            }
                            echo "<a href='{$add_url}' id='create_new_tab' class='nav-tab'>+</a>";
                            echo "</h3>";
                        }
                    } else {
                        echo "<h3 class='nav-tab-wrapper'>";
                        echo "<a href='{$add_url}' id='create_new_tab' class='nav-tab'>+</a>";
                        echo "<div class='bubble'>" . __("Create your first slideshow") . "</div>";
                        echo "</h3>";
                    }

                ?>

                <?php
                    if (!$this->slider) {
                        return;
                    }
                ?>
                <div id='poststuff'>
                    <div id='post-body' class='metabox-holder columns-2'>
                        <div id='postbox-container-1' class='postbox-container'>
	                        <div id="side-sortables" class="meta-box-sortables">
	                            <div class='right'>
	                            	<div class="postbox">
										<h3 class='configuration'>
											<?php _e("Settings", "metaslider") ?>
			                                <input class='alignright button button-primary' type='submit' name='save' id='save' value='<?php _e("Save", "metaslider") ?>' />
			                                <input class='alignright button button-primary' type='submit' name='preview' id='preview' value='<?php _e("Save & Preview", "metaslider") ?>' data-slider_id='<?php echo $this->slider->id ?>' data-slider_width='<?php echo $this->slider->get_setting('width') ?>' data-slider_height='<?php echo $this->slider->get_setting('height') ?>' />
			                                <span class="spinner"></span>
			                            </h3>
			                            <div class="inside">
			                                <table class="widefat settings">
			                                    <tbody>
			                                        <?php 
														$aFields = array(
															'type' => array(
																'priority' => 0,
																'type' => 'slider-lib',
																'value' => $this->slider->get_setting('type'),
																'options' => array(
																	'flex'       => array('label' => __("Flex Slider", "metaslider")),
																	'responsive' => array('label' => __("Responsive", "metaslider")),
																	'nivo'       => array('label' => __("Nivo Slider", "metaslider")),
																	'coin'       => array('label' => __("Coin Slider", "metaslider"))
																)
															),
															'width' => array(
																'priority' => 10,
																'type' => 'number',
																'size' => 3,
																'min' => 0,
																'max' => 9999,
																'step' => 1,
																'value' => $this->slider->get_setting('width'),
																'label' => __("Width", "metaslider"),
																'class' => 'coin flex responsive nivo',
																'helptext' => __("Slideshow width", "metaslider"),
																'after' => __("px", "metaslider")
															),
															'height' => array(
																'priority' => 20,
																'type' => 'number',
																'size' => 3,
																'min' => 0,
																'max' => 9999,
																'step' => 1,
																'value' => $this->slider->get_setting('height'),
																'label' => __("Height", "metaslider"),
																'class' => 'coin flex responsive nivo',
																'helptext' => __("Slideshow height", "metaslider"),
																'after' => __("px", "metaslider")
															),
															'effect' => array(
																'priority' => 30,
																'type' => 'select',
																'value' => $this->slider->get_setting('effect'),
																'label' => __("Effect", "metaslider"),
																'class' => 'effect coin flex responsive nivo',
																'helptext' => __("Slide transition effect", "metaslider"),
																'options' => array(
																	'random'             => array('class' => 'option coin nivo' , 'label' => __("Random", "metaslider")),
																	'swirl'              => array('class' => 'option coin', 'label' => __("Swirl", "metaslider")),
																	'rain'               => array('class' => 'option coin', 'label' => __("Rain", "metaslider")),
																	'straight'           => array('class' => 'option coin', 'label' => __("Straight", "metaslider")),
																	'sliceDown'          => array('class' => 'option nivo', 'label' => __("Slide Down", "metaslider")),
																	'sliceUp'            => array('class' => 'option nivo', 'label' => __("Slice Up", "metaslider")),
																	'sliceUpLeft'        => array('class' => 'option nivo', 'label' => __("Slide Up Left", "metaslider")),
																	'sliceUpDown'        => array('class' => 'option nivo', 'label' => __("Slice Up Down", "metaslider")),
																	'slideUpDownLeft'    => array('class' => 'option nivo', 'label' => __("Slide Up Down Left", "metaslider")),
																	'fold'               => array('class' => 'option nivo', 'label' => __("Fold", "metaslider")),
																	'fade'               => array('class' => 'option nivo flex responsive', 'label' => __("Fade", "metaslider")),
																	'slideInRight'       => array('class' => 'option nivo', 'label' => __("Slide In Right", "metaslider")),
																	'slideInLeft'        => array('class' => 'option nivo', 'label' => __("Slide In Left", "metaslider")),
																	'boxRandom'          => array('class' => 'option nivo', 'label' => __("Box Random", "metaslider")),
																	'boxRain'            => array('class' => 'option nivo', 'label' => __("Box Rain", "metaslider")),
																	'boxRainReverse'     => array('class' => 'option nivo', 'label' => __("Box Rain Reverse", "metaslider")),
																	'boxRainGrowReverse' => array('class' => 'option nivo', 'label' => __("Box Rain Grow Reverse", "metaslider")),
																	'slide'              => array('class' => 'option flex', 'label' => __("Slide", "metaslider"))
																),
															),
															'theme' => array(
																'priority' => 40,
																'type' => 'theme',
																'value' => $this->slider->get_setting('theme'),
																'label' => __("Theme", "metaslider"),
																'class' => 'effect coin flex responsive nivo',
																'helptext' => __("Slideshow theme", "metaslider"),
																'options' => array(
																	'default' => array('class' => 'option nivo flex coin responsive' , 'label' => __("Default", "metaslider")),
																	'dark'    => array('class' => 'option nivo', 'label' => __("Dark (Nivo)", "metaslider")),
																	'light'   => array('class' => 'option nivo', 'label' => __("Light (Nivo)", "metaslider")),
																	'bar'     => array('class' => 'option nivo', 'label' => __("Bar (Nivo)", "metaslider")),
																),
															),
															'links' => array(
																'priority' => 50,
																'type' => 'checkbox',
																'label' => __("Arrows", "metaslider"),
																'class' => 'option coin flex nivo responsive',
																'checked' => $this->slider->get_setting('links') == 'true' ? 'checked' : '',
																'helptext' => __("Show the previous/next arrows", "metaslider")
															),
															'navigation' => array(
																'priority' => 60,
																'type' => 'navigation',
																'label' => __("Navigation", "metaslider"),
																'class' => 'option coin flex nivo responsive',
																'value' => $this->slider->get_setting('navigation'),
																'helptext' => __("Show the slide navigation bullets", "metaslider"),
																'options' => array(
																	'false'      => array('label' => __("Hidden", "metaslider")),
																	'true'       => array('label' => __("Dots", "metaslider")),
																	'thumbnails' => array('label' => __("Thumbnails (Pro)", "metaslider"))
																)
															),
														);

				                                        if ($max_tabs && count($this->all_meta_sliders()) > $max_tabs) {
				                                        	$aFields['title'] = array(
				                                        		'type' => 'title',
				                                        		'priority' => 5,
				                                        		'class' => 'option flex nivo responsive coin',
				                                        		'value' => $title,
				                                        		'label' => __("Title", "metaslider"),
				                                        		'helptext' => __("Slideshow title", "metaslider")
				                                        	);
				                                        }

														$aFields = apply_filters('metaslider_basic_settings', $aFields, $this->slider);

				                                        echo $this->build_settings_rows($aFields);
			                                        ?>
			                                    </tbody>
			                                </table>
			                            </div>
			                        </div>

									<div class="postbox toggle closed">
										<div class="handlediv" title="Click to toggle"><br></div><h3 class="hndle"><span><?php _e("Advanced Settings", "metaslider") ?></span></h3>
										<div class="inside">
			                                <table>
			                                	<tbody>
			                                		<?php
														$aFields = array(
															'fullWidth' => array(
																'priority' => 5,
																'type' => 'checkbox',
																'label' => __("Stretch", "metaslider"),
																'class' => 'option flex nivo responsive',
																'after' => __("100% wide output", "metaslider"),
																'checked' => $this->slider->get_setting('fullWidth') == 'true' ? 'checked' : '',
																'helptext' => __("Stretch the slideshow output to fill it's parent container", "metaslider")
															),
															'center' => array(
																'priority' => 10,
																'type' => 'checkbox',
																'label' => __("Center align", "metaslider"),
																'class' => 'option coin flex nivo responsive',
																'checked' => $this->slider->get_setting('center') == 'true' ? 'checked' : '',
																'helptext' => __("Center align the slideshow", "metaslider")
															),
															'autoPlay' => array(
																'priority' => 20,
																'type' => 'checkbox',
																'label' => __("Auto play", "metaslider"),
																'class' => 'option flex nivo responsive',
																'checked' => $this->slider->get_setting('autoPlay') == 'true' ? 'checked' : '',
																'helptext' => __("Transition between slides automatically", "metaslider")
															),
															'smartCrop' => array(
																'priority' => 30,
																'type' => 'checkbox',
																'label' => __("Smart crop", "metaslider"),
																'class' => 'option coin flex nivo responsive',
																'checked' => $this->slider->get_setting('smartCrop') == 'true' ? 'checked' : '',
																'helptext' => __("Smart Crop ensures your responsive slides are cropped to a ratio that results in a consistent slideshow size", "metaslider")
															),
															'carouselMode' => array(
																'priority' => 40,
																'type' => 'checkbox',
																'label' => __("Carousel mode", "metaslider"),
																'class' => 'option flex',
																'checked' => $this->slider->get_setting('carouselMode') == 'true' ? 'checked' : '',
																'helptext' => __("Display multiple slides at once. Slideshow output will be 100% wide.", "metaslider")
															),
															'random' => array(
																'priority' => 50,
																'type' => 'checkbox',
																'label' => __("Random", "metaslider"),
																'class' => 'option coin flex nivo responsive',
																'checked' => $this->slider->get_setting('random') == 'true' ? 'checked' : '',
																'helptext' => __("Randomise the order of the slides", "metaslider")
															),
															'hoverPause' => array(
																'priority' => 60,
																'type' => 'checkbox',
																'label' => __("Hover pause", "metaslider"),
																'class' => 'option coin flex nivo responsive',
																'checked' => $this->slider->get_setting('hoverPause') == 'true' ? 'checked' : '',
																'helptext' => __("Pause the slideshow when hovering over slider, then resume when no longer hovering.", "metaslider")
															),
															'reverse' => array(
																'priority' => 70,
																'type' => 'checkbox',
																'label' => __("Reverse", "metaslider"),
																'class' => 'option flex',
																'checked' => $this->slider->get_setting('reverse') == 'true' ? 'checked' : '',
																'helptext' => __("Reverse the animation direction", "metaslider")
															),
															'delay' => array(
																'priority' => 80,
																'type' => 'number',
																'size' => 3,
																'min' => 500,
																'max' => 10000,
																'step' => 100,
																'value' => $this->slider->get_setting('delay'),
																'label' => __("Slide delay", "metaslider"),
																'class' => 'option coin flex responsive nivo',
																'helptext' => __("How long to display each slide, in milliseconds", "metaslider"),
																'after' => __("ms", "metaslider")
															),
															'animationSpeed' => array(
																'priority' => 90,
																'type' => 'number',
																'size' => 3,
																'min' => 0,
																'max' => 2000,
																'step' => 100,
																'value' => $this->slider->get_setting('animationSpeed'),
																'label' => __("Animation speed", "metaslider"),
																'class' => 'option flex responsive nivo',
																'helptext' => __("Set the speed of animations, in milliseconds", "metaslider"),
																'after' => __("ms", "metaslider")
															),
															'slices' => array(
																'priority' => 100,
																'type' => 'number',
																'size' => 3,
																'min' => 0,
																'max' => 20,
																'step' => 1,
																'value' => $this->slider->get_setting('slices'),
																'label' => __("Number of slices", "metaslider"),
																'class' => 'option nivo',
																'helptext' => __("Number of slices", "metaslider"),
																'after' => __("ms", "metaslider")
															),
															'spw' => array(
																'priority' => 110,
																'type' => 'number',
																'size' => 3,
																'min' => 0,
																'max' => 20,
																'step' => 1,
																'value' => $this->slider->get_setting('spw'),
																'label' => __("Number of squares", "metaslider") . " (" . __("Width", "metaslider") . ")",
																'class' => 'option nivo',
																'helptext' => __("Number of squares", "metaslider"),
																'after' => ''
															),
															'sph' => array(
																'priority' => 120,
																'type' => 'number',
																'size' => 3,
																'min' => 0,
																'max' => 20,
																'step' => 1,
																'value' => $this->slider->get_setting('sph'),
																'label' => __("Number of squares", "metaslider") . " (" . __("Height", "metaslider") . ")",
																'class' => 'option nivo',
																'helptext' => __("Number of squares", "metaslider"),
																'after' => ''
															),
															'direction' => array(
																'priority' => 130,
																'type' => 'select',
																'label' => __("Slide direction", "metaslider"),
																'class' => 'option flex',
																'helptext' => __("Select the sliding direction", "metaslider"),
																'value' => $this->slider->get_setting('direction'),
																'options' => array(
																	'horizontal' => array('label' => __("Horizontal", "metaslider"), 'class' => ''),
																	'vertical' => array('label' => __("Vertical", "metaslider"), 'class' => ''),
																)
															),
															'easing' => array(
																'priority' => 140,
																'type' => 'select',
																'label' => __("Easing", "metaslider"),
																'class' => 'option flex',
																'helptext' => __("Animation easing effect", "metaslider"),
																'value' => $this->slider->get_setting('easing'),
																'options' => $this->get_easing_options()
															),
															'prevText' => array(
																'priority' => 150,
																'type' => 'text',
																'label' => __("Previous text", "metaslider"),
																'class' => 'option coin flex responsive nivo',
																'helptext' => __("Set the text for the 'previous' direction item", "metaslider"),
																'value' => $this->slider->get_setting('prevText') == 'false' ? '' : $this->slider->get_setting('prevText')
															),
															'nextText' => array(
																'priority' => 160,
																'type' => 'text',
																'label' => __("Next text", "metaslider"),
																'class' => 'option coin flex responsive nivo',
																'helptext' => __("Set the text for the 'next' direction item", "metaslider"),
																'value' => $this->slider->get_setting('nextText') == 'false' ? '' : $this->slider->get_setting('nextText')
															),
															'sDelay' => array(
																'priority' => 170,
																'type' => 'number',
																'size' => 3,
																'min' => 0,
																'max' => 500,
																'step' => 10,
																'value' => $this->slider->get_setting('sDelay'),
																'label' => __("Square delay", "metaslider"),
																'class' => 'option coin',
																'helptext' => __("Delay between squares in ms", "metaslider"),
																'after' => __("ms", "metaslider")
															),
															'opacity' => array(
																'priority' => 180,
																'type' => 'number',
																'size' => 3,
																'min' => 0,
																'max' => 1,
																'step' => 0.1,
																'value' => $this->slider->get_setting('opacity'),
																'label' => __("Opacity", "metaslider"),
																'class' => 'option coin',
																'helptext' => __("Opacity of title and navigation", "metaslider"),
																'after' => ''
															),
															'titleSpeed' => array(
																'priority' => 190,
																'type' => 'number',
																'size' => 3,
																'min' => 0,
																'max' => 10000,
																'step' => 100,
																'value' => $this->slider->get_setting('titleSpeed'),
																'label' => __("Caption speed", "metaslider"),
																'class' => 'option coin',
																'helptext' => __("Set the fade in speed of the caption", "metaslider"),
																'after' => __("ms", "metaslider")
															),
															'developerOptions' => array(
																'priority' => 195,
																'type' => 'divider',
																'class' => 'option coin flex responsive nivo',
																'value' => __("Developer options", "metaslider")
															),
															'cssClass' => array(
																'priority' => 200,
																'type' => 'text',
																'label' => __("CSS classes", "metaslider"),
																'class' => 'option coin flex responsive nivo',
																'helptext' => __("Specify any custom CSS Classes you would like to be added to the slider wrapper", "metaslider"),
																'value' => $this->slider->get_setting('cssClass') == 'false' ? '' : $this->slider->get_setting('cssClass')
															),
															'printCss' => array(
																'priority' => 210,
																'type' => 'checkbox',
																'label' => __("Print CSS", "metaslider"),
																'class' => 'option coin flex responsive nivo useWithCaution',
																'checked' => $this->slider->get_setting('printCss') == 'true' ? 'checked' : '',
																'helptext' => __("Uncheck this is you would like to include your own CSS", "metaslider")
															),
															'printJs' => array(
																'priority' => 220,
																'type' => 'checkbox',
																'label' => __("Print JS", "metaslider"),
																'class' => 'option coin flex responsive nivo useWithCaution',
																'checked' => $this->slider->get_setting('printJs') == 'true' ? 'checked' : '',
																'helptext' => __("Uncheck this is you would like to include your own Javascript", "metaslider")
															),
															'noConflict' => array(
																'priority' => 230,
																'type' => 'checkbox',
																'label' => __("No conflict mode", "metaslider"),
																'class' => 'option flex',
																'checked' => $this->slider->get_setting('noConflict') == 'true' ? 'checked' : '',
																'helptext' => __("Delay adding the flexslider class to the slideshow", "metaslider")
															),
														);

														$aFields = apply_filters('metaslider_advanced_settings', $aFields, $this->slider);

														echo $this->build_settings_rows($aFields);
													?>
			                                    </tbody>
			                                </table>
										</div>
									</div>

									<div class="postbox shortcode toggle">
										<div class="handlediv" title="Click to toggle"><br></div><h3 class="hndle"><span><?php _e("Usage", "metaslider") ?></span></h3>
										<div class="inside">
											<ul class='tabs'>
												<li rel='tab-1' class='selected'><?php _e("Shortcode", "metaslider") ?></li>
												<li rel='tab-2'><?php _e("Template Include", "metaslider") ?></li>
											</ul>
											<div class='tabs-content'>
												<div class='tab tab-1'>
												<p><?php _e("Copy & paste the shortcode directly into any WordPress post or page.", "metaslider"); ?></p>
												<input readonly='readonly' type='text' value='[metaslider id=<?php echo $this->slider->id ?>]' /></div>
												<div class='tab tab-2' style='display: none'>
												<p><?php _e("Copy & paste this code into a template file to include the slideshow within your theme.", "metaslider"); ?></p>
												<textarea readonly='readonly'>&lt;?php &#13;&#10;    echo do_shortcode("[metaslider id=<?php echo $this->slider->id ?>]"); &#13;&#10;?></textarea></div>
											</div>
			                            </div>
			                        </div>

									<div class="postbox social">
										<div class="inside">
			                                <ul class='info'>
			                                    <li style='width: 33%;'>
			                                        <a href="https://twitter.com/share" class="twitter-share-button" data-url="http://www.metaslider.com" data-text="Check out Meta Slider, an easy to use slideshow plugin for WordPress" data-hashtags="metaslider, wordpress, slideshow">Tweet</a>
			                                        <script>!function(d,s,id){var js,fjs=d.getElementsByTagName(s)[0],p=/^http:/.test(d.location)?'http':'https';if(!d.getElementById(id)){js=d.createElement(s);js.id=id;js.src=p+'://platform.twitter.com/widgets.js';fjs.parentNode.insertBefore(js,fjs);}}(document, 'script', 'twitter-wjs');</script>
			                                    </li>
			                                    <li style='width: 34%;'>
			                                        <div class="g-plusone" data-size="medium" data-href="http://www.metaslider.com"></div>
			                                        <script type="text/javascript">
			                                          (function() {
			                                            var po = document.createElement('script'); po.type = 'text/javascript'; po.async = true;
			                                            po.src = 'https://apis.google.com/js/plusone.js';
			                                            var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(po, s);
			                                          })();
			                                        </script>
			                                    </li>
			                                    <li style='width: 33%;'>
			                                        <iframe style='border:none; overflow:hidden; width:80px; height:21px;' src="//www.facebook.com/plugins/like.php?href=http%3A%2F%2Fwww.metaslider.com&amp;send=false&amp;layout=button_count&amp;width=90&amp;show_faces=false&amp;font&amp;colorscheme=light&amp;action=like&amp;height=21&amp;appId=156668027835524" scrolling="no" frameborder="0" allowTransparency="true"></iframe>
			                                    </li>
			                                </ul>
			                            </div>
			                        </div>
			                        <a class='delete-slider alignright button-secondary confirm' href='<?php echo wp_nonce_url("?page=metaslider&amp;delete={$this->slider->id}", "metaslider_delete_slider"); ?>'><?php _e("Delete Slider", "metaslider") ?></a>
	                            </div>
                            </div>
                        </div>

                        <div id='postbox-container-2' class='postbox-container'>
                            <div class="left">
                                <table class="widefat sortable">
                                    <thead>
                                        <tr>
                                            <th style="width: 100px;">
                                                <h3><?php _e("Slides", "metaslider") ?></h3>
                                            </th>
                                            <th>
                                                <a href='#' class='button alignright add-slide' data-editor='content' title='<?php _e("Add Slide", "metaslider") ?>'>
                                                    <span class='wp-media-buttons-icon'></span> <?php _e("Add Slide", "metaslider") ?>
                                                </a>         
                                            </th>
                                        </tr>
                                    </thead>

                                    <tbody>
                                        <?php
                                            $this->slider->render_admin_slides();
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <?php
    }


	/**
	 * Append the 'Add Slider' button to selected admin pages
	 */
	public function insert_metaslider_button($context) {
		global $pagenow;

		if (in_array($pagenow, array( 'post.php', 'page.php', 'post-new.php', 'post-edit.php' ))) {
			$context .= '<a href="#TB_inline?&inlineId=choose-meta-slider" class="thickbox button" title="' . 
						__("Select slideshow to insert into post", "metaslider") . 
						'"><span class="wp-media-buttons-icon" style="background: url(' . METASLIDER_ASSETS_URL . 
						'/metaslider/matchalabs.png); background-repeat: no-repeat; background-position: left bottom;"></span> ' .
				 		__("Add slider", "metaslider") . '</a>';
		}

		return $context;
	}

	/**
	 * Append the 'Choose Meta Slider' thickbox content to the bottom of selected admin pages
	 */
	public function admin_footer() {
		global $pagenow;

		// Only run in post/page creation and edit screens
		if (in_array($pagenow, array('post.php', 'page.php', 'post-new.php', 'post-edit.php'))) {
			$sliders = $this->all_meta_sliders('title');
			?>

			<script type="text/javascript">
				jQuery(document).ready(function() {
				  jQuery('#insertMetaSlider').on('click', function() {
				  	var id = jQuery('#metaslider-select option:selected').val();
				  	window.send_to_editor('[metaslider id="' + id + '"]');
					tb_remove();
				  })
				});
			</script>

			<div id="choose-meta-slider" style="display: none;">
				<div class="wrap">
					<?php 
						if (count($sliders)) {
							echo "<h3 style='margin-bottom: 20px;'>" . __("Insert Meta Slider", "metaslider") . "</h3>";
							echo "<select id='metaslider-select'>";
							echo "<option disabled=disabled>" . __("Choose slideshow", "metaslider") . "</option>";
							foreach ($sliders as $slider) {
								echo "<option value='{$slider['id']}'>{$slider['title']}</option>";
							}
							echo "</select>";
							echo "<button class='button primary' id='insertMetaSlider'>Insert Slideshow</button>";
						} else {
							_e("No slideshows found", "metaslider");
						}
					?>
				</div>
			</div>
			<?php
		}
	}
}

$metaslider = new MetaSliderPlugin();

?>
