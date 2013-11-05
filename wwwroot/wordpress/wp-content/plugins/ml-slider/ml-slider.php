<?php
/*
 * Plugin Name: Meta Slider
 * Plugin URI: http://www.metaslider.com
 * Description: 4 sliders in 1! Choose from Nivo Slider, Flex Slider, Coin Slider or Responsive Slides.
 * Version: 2.4.2
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

define('METASLIDER_VERSION', '2.4.2');
define('METASLIDER_BASE_URL', plugin_dir_url(__FILE__));
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
        add_action('init', array($this, 'register_post_type' ));
        add_action('init', array($this, 'register_taxonomy' ));
        add_action('init', array($this, 'load_plugin_textdomain'));

        // register shortcodes
        add_shortcode('metaslider', array($this, 'register_shortcode'));
        add_shortcode('ml-slider', array($this, 'register_shortcode')); // backwards compatibility

        add_filter('media_upload_tabs', array($this,'custom_media_upload_tab_name'), 998);
        add_filter('media_view_strings', array($this, 'custom_media_uploader_tabs'), 5);
        add_action('media_upload_metaslider_pro', array($this, 'metaslider_pro_tab'));
        
        // add 'go pro' link to plugin options
        $plugin = plugin_basename(__FILE__);
        add_filter("plugin_action_links_{$plugin}", array($this,'upgrade_to_pro') );

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
        $links[] = '<a href="http://www.metaslider.com/upgrade" target="_blank">' . __("Go Pro", 'metaslider') . '</a>'; 
        return $links; 
    }
     
    /**
     * Return the meta slider pro upgrade iFrame
     */
    public function metaslider_pro_tab() {
        return wp_iframe( array($this, 'iframe'));
    }

    /**
     * Media Manager iframe HTML
     */
    public function iframe() {
        wp_enqueue_style('metaslider-admin-styles', METASLIDER_ASSETS_URL . 'metaslider/admin.css', false, METASLIDER_VERSION);
        wp_enqueue_script('google-font-api', 'http://fonts.googleapis.com/css?family=PT+Sans:400,700');
        
        $link = apply_filters('metaslider_hoplink', 'http://www.metaslider.com/upgrade/');
        $link .= '?utm_source=lite&utm_medium=more-slide-types&utm_campaign=pro';

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
            $strings['insertMediaTitle'] = __("Image", 'metaslider');
            $strings['insertIntoPost'] = __("Add to slider", 'metaslider');

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
    public function custom_media_upload_tab_name( $tabs ) {
        // restrict our tab changes to the meta slider plugin page
        if ((isset($_GET['page']) && $_GET['page'] == 'metaslider') || isset($_GET['tab']) == 'metaslider_pro') {

            $newtabs = array( 
                'metaslider_pro' => __("More Slide Types", 'metaslider')
            );

            if (isset($tabs['nextgen'])) unset($tabs['nextgen']);

            return array_merge( $tabs, $newtabs );
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
        wp_localize_script( 'metaslider-admin-script', 'metaslider', array( 
            'url' => __("URL", 'metaslider'), 
            'caption' => __("Caption", 'metaslider'),
            'new_window' => __("New Window", 'metaslider'),
            'confirm' => __("Are you sure?", 'metaslider'),
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'iframeurl' => METASLIDER_BASE_URL . 'preview.php',
            'useWithCaution' => __("Caution: This setting is for advanced developers only. If you're unsure, leave it checked.", 'metaslider')
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
        if (!is_plugin_active('ml-slider-pro/ml-slider-pro.php')) {
            $link = apply_filters('metaslider_hoplink', 'http://www.metaslider.com/upgrade/');

            $link .= '?utm_source=lite&utm_medium=nag&utm_campaign=pro';

            $goPro = "<div id='ms-pro-meta-link-wrap'><a target='_blank' href='{$link}'>Meta Slider Lite v" . METASLIDER_VERSION . " - " . 
                __('Upgrade to Pro $19', 'metaslider') . 
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
        extract(shortcode_atts(array('id' => null), $atts));

        if ($id == null) return;

        // we have an ID to work with
        $slider = get_post($id);

        // check the slider is published
        if ($slider->post_status != 'publish') return false;

        // lets go
        $this->set_slider($id);
        $this->slider->enqueue_scripts();
        
        return $this->slider->render_public_slides();
    }

    /**
     * Set the current slider
     */
    public function set_slider($id) {
        $type = 'flex';
        $settings = get_post_meta($id, 'ml-slider_settings', true);

        if (isset($settings['type']) && in_array($settings['type'], array('flex', 'coin', 'nivo', 'responsive'))) {
            $type = $settings['type'];
        }

        $this->slider = $this->create_slider($type, $id);
    }

    /**
     * Create a new slider based on the sliders type setting
     */
    private function create_slider($type, $id) {
        switch ($type) {
            case('coin'):
                return new MetaCoinSlider($id);
            case('flex'):
                return new MetaFlexSlider($id);
            case('nivo'):
                return new MetaNivoSlider($id);
            case('responsive'):
                return new MetaResponsiveSlider($id);
            default:
                return new MetaFlexSlider($id);
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
            $this->delete_slider(intval($_GET['delete']));
            $slider_id = $this->find_slider('date', 'DESC');
        }

        // create a new slider
        if (isset($_GET['add'])) {
            $this->add_slider();
            $slider_id = $this->find_slider('date', 'DESC');
        }

        if (isset($_REQUEST['id'])) {
            $slider_id = $_REQUEST['id'];
        }

        $this->set_slider($slider_id);
    }

    /**
     * Create a new slider
     */
    private function add_slider() {
        $defaults = array();

        // if possible, take a copy of the last edited slider settings in place of default settings
        if ($last_modified = $this->find_slider('modified', 'DESC')) {
            $defaults = get_post_meta($last_modified, 'ml-slider_settings', true);
        }

        // insert the post
        $id = wp_insert_post(array(
            'post_title' => __("New Slider", 'metaslider'),
            'post_status' => 'publish',
            'post_type' => 'ml-slider'
        ));

        // use the default settings if we can't find anything more suitable.
        if (empty($defaults)) {
            $slider = new MetaSlider($id);
            $defaults = $slider->get_default_parameters();
        }

        // insert the post meta
        add_post_meta($id, 'ml-slider_settings', $defaults, true);

        // create the taxonomy term, the term is the ID of the slider itself
        wp_insert_term($id, 'ml-slider');
    }

    /**
     * Delete a slider (send it to trash)
     */
    private function delete_slider($id) {
        $slide = array(
            'ID' => $id,
            'post_status' => 'trash'
        );
        
        wp_update_post($slide);
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

        return false;
    }


    /**
     * Get sliders. Returns a nicely formatted array of currently
     * published sliders.
     *
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
        
        $the_query = new WP_Query($args);
        
        while ($the_query->have_posts()) {
            $the_query->the_post();
            $active = $this->slider->id == $the_query->post->ID ? true : false;
            
            $sliders[] = array(
                'active' => $active,
                'title' => get_the_title(),
                'id' => $the_query->post->ID
            );
        }
        
        return $sliders;
    }

    public function get_library_details($version, $responsive, $size, $mobile) {
         $details  = __("Version", 'metaslider') . ": " . $version . "<br />";
         $details .= __("Responsive", 'metaslider') . ": ";
         $details .= $responsive ? __("Yes", 'metaslider') : __("No", 'metaslider');
         $details .= "<br />";
         $details .= __("Size", 'metaslider') . ": " . $size . __("kb", 'metaslider') ."<br />";
         $details .= __("Mobile Friendly", 'metaslider') . ": ";
         $details .= $mobile ? __("Yes", 'metaslider') : __("No", 'metaslider') . "<br />";

         return $details;
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
        </script>

        <div class="wrap metaslider">
            <form accept-charset="UTF-8" action="?page=metaslider&id=<?php echo $this->slider->id ?>" method="post">
                <?php
                    $title = "";

                    if ($tabs = $this->all_meta_sliders()) {
                        if ($max_tabs && count($tabs) > $max_tabs) {
                            if (isset($_GET['add']) && $_GET['add'] == 'true') {
                                echo "<div id='message' class='updated'><p>" . __("New slideshow created. Click 'Add Slide' to get started!", 'metaslider') . "</p></div>";
                            }
                            echo "<div style='margin-top: 20px;'><label for='select-slider'>Select Slider: </label>";
                            echo "<select name='select-slider' onchange='if (this.value) window.location.href=this.value'>";

                            $tabs = $this->all_meta_sliders('title');

                            foreach ($tabs as $tab) {
                                $selected = $tab['active'] ? " selected='selected'" : "";

                                if ($tab['active']) {
                                    $title = $tab['title'];
                                }

                                echo "<option value='?page=metaslider&id={$tab['id']}'{$selected}>{$tab['title']}</option>";

                            }
                            echo "</select> " . __('or', 'metaslider') . " ";
                            echo "<a href='?page=metaslider&add=true'>" . __('Add New Slideshow', 'metaslider') . "</a></div>";
                        } else {
                            echo "<h2 class='nav-tab-wrapper'>";
                            foreach ($tabs as $tab) {
                                if ($tab['active']) {
                                    echo "<div class='nav-tab nav-tab-active'><input type='text' name='title'  value='" . $tab['title'] . "' onkeypress='this.style.width = ((this.value.length + 1) * 9) + \"px\"' /></div>";
                                } else {
                                    echo "<a href='?page=metaslider&id={$tab['id']}' class='nav-tab'>" . $tab['title'] . "</a>";
                                }
                            }
                            echo "<a href='?page=metaslider&add=true' id='create_new_tab' class='nav-tab'>+</a>";
                            echo "</h2>";
                        }
                    } else {
                        echo "<h2 class='nav-tab-wrapper'>";
                        echo "<a href='?page=metaslider&add=true' id='create_new_tab' class='nav-tab'>+</a>";
                        echo "<div class='bubble'>" . __("Create your first slideshow") . "</div>";
                        echo "</h2>";
                    }

                ?>

                <?php
                    if (!$this->slider->id) {
                        return;
                    }
                ?>

                <div class="left">
                    <table class="widefat sortable">
                        <thead>
                            <tr>
                                <th style="width: 100px;">
                                    <?php _e("Slides", 'metaslider') ?>
                                </th>
                                <th>
                                    <a href='#' class='button alignright add-slide' data-editor='content' title='<?php _e("Add Slide", 'metaslider') ?>'>
                                        <span class='wp-media-buttons-icon'></span> <?php _e("Add Slide", 'metaslider') ?>
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

                <div class='right'>
                    <table class="widefat settings">
                        <thead>
                            <tr>
                                <th colspan='2'>
                                    <span class='configuration'><?php _e("Settings", 'metaslider') ?></span>
                                    <input class='alignright button button-primary' type='submit' name='save' id='save' value='<?php _e("Save", 'metaslider') ?>' />
                                    <input class='alignright button button-primary' type='submit' name='preview' id='preview' value='<?php _e("Save & Preview", 'metaslider') ?>' id='quickview' data-slider_id='<?php echo $this->slider->id ?>' data-slider_width='<?php echo $this->slider->get_setting('width') ?>' data-slider_height='<?php echo $this->slider->get_setting('height') ?>' />
                                    <span class="spinner"></span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan='2' class='slider-lib-row'>
                                    <div class='slider-lib flex'>
                                        <label for='flex' title='<?php echo $this->get_library_details(2.2, true, 17, true); ?>' class='tipsy-tooltip-top'>FlexSlider</label>
                                        <input class="select-slider" id='flex' rel='flex' type='radio' name="settings[type]" <?php if ($this->slider->get_setting('type') == 'flex') echo 'checked=checked' ?> value='flex' />
                                    </div>
                                    <div class='slider-lib responsive'>
                                        <label for='responsive' title='<?php echo $this->get_library_details(1.54, true, 3, true); ?>' class='tipsy-tooltip-top'>Responsive</label>
                                        <input class="select-slider" id='responsive' rel='responsive' type='radio' name="settings[type]" <?php if ($this->slider->get_setting('type') == 'responsive') echo 'checked=checked' ?> value='responsive' />
                                    </div>
                                    <div class='slider-lib nivo'>
                                        <label for='nivo' title='<?php echo $this->get_library_details(3.2, true, 12, true); ?>' class='tipsy-tooltip-top'>NivoSlider</label>
                                        <input class="select-slider" id='nivo' rel='nivo' type='radio' name="settings[type]" <?php if ($this->slider->get_setting('type') == 'nivo') echo 'checked=checked' ?> value='nivo' />
                                    </div>
                                    <div class='slider-lib coin'>
                                        <label for='coin' title='<?php echo $this->get_library_details(1.0, false, 8, true); ?>' class='tipsy-tooltip-top'>CoinSlider</label>
                                        <input class="select-slider" id='coin' rel='coin' type='radio' name="settings[type]" <?php if ($this->slider->get_setting('type') == 'coin') echo 'checked=checked' ?> value='coin' />
                                    </div>
                                </td>
                            </tr>
                            <?php if ($max_tabs && count($this->all_meta_sliders()) > $max_tabs) { ?>
                            <tr>
                                <td class='tipsy-tooltip' title="<?php _e("Slideshow title", 'metaslider') ?>">
                                    <?php _e("Title", 'metaslider') ?>
                                </td>
                                <td>
                                    <input type='text' class="title tipsytop" name="title" value='<?php echo $title ?>' />
                                </td>
                            </tr>
                            <?php } ?>
                            <tr>
                                <td class='tipsy-tooltip' title="<?php _e("Set the initial size for the slides (width x height)", 'metaslider') ?>">
                                    <?php _e("Size", 'metaslider') ?> (<?php _e("px", 'metaslider') ?>)
                                </td>
                                <td>
                                        <?php _e("Width", 'metaslider') ?>:
                                        <input type='number' min='0' max='9999' class="width tipsy-tooltip-top" title='<?php _e("Width", 'metaslider') ?>' name="settings[width]" value='<?php echo $this->slider->get_setting('width') ?>' />
                                        <?php _e("Height", 'metaslider') ?>:
                                        <input type='number' min='0' max='9999' class="height tipsy-tooltip-top" title='<?php _e("Height", 'metaslider') ?>' name="settings[height]" value='<?php echo $this->slider->get_setting('height') ?>' />
                                </td>
                            </tr>
                            <tr>
                                <td class='tipsy-tooltip' title="<?php _e("Slide transition effect", 'metaslider') ?>">
                                    <?php _e("Effect", 'metaslider') ?>
                                </td>
                                <td>
                                    <select name="settings[effect]" class='effect option coin nivo flex'>
                                        <option class='option coin nivo' value='random' <?php if ($this->slider->get_setting('effect') == 'random') echo 'selected=selected' ?>><?php _e("Random", 'metaslider') ?></option>
                                        <option class='option coin' value='swirl' <?php if ($this->slider->get_setting('effect') == 'swirl') echo 'selected=selected' ?>><?php _e("Swirl", 'metaslider') ?></option>
                                        <option class='option coin' value='rain' <?php if ($this->slider->get_setting('effect') == 'rain') echo 'selected=selected' ?>><?php _e("Rain", 'metaslider') ?></option>
                                        <option class='option coin' value='straight' <?php if ($this->slider->get_setting('effect') == 'straight') echo 'selected=selected' ?>><?php _e("Straight", 'metaslider') ?></option>
                                        <option class='option nivo' value='sliceDown' <?php if ($this->slider->get_setting('effect') == 'sliceDown') echo 'selected=selected' ?>><?php _e("Slide Down", 'metaslider') ?></option>
                                        <option class='option nivo' value='sliceUp' <?php if ($this->slider->get_setting('effect') == 'sliceUp') echo 'selected=selected' ?>><?php _e("Slice Up", 'metaslider') ?></option>
                                        <option class='option nivo' value='sliceUpLeft' <?php if ($this->slider->get_setting('effect') == 'sliceUpLeft') echo 'selected=selected' ?>><?php _e("Slide Up Left", 'metaslider') ?></option>
                                        <option class='option nivo' value='sliceUpDown' <?php if ($this->slider->get_setting('effect') == 'sliceUpDown') echo 'selected=selected' ?>><?php _e("Slice Up Down", 'metaslider') ?></option>
                                        <option class='option nivo' value='sliceUpDownLeft' <?php if ($this->slider->get_setting('effect') == 'sliceUpDownLeft') echo 'selected=selected' ?>><?php _e("Slide Up Down Left", 'metaslider') ?></option>
                                        <option class='option nivo' value='fold' <?php if ($this->slider->get_setting('effect') == 'fold') echo 'selected=selected' ?>><?php _e("Fold", 'metaslider') ?></option>
                                        <option class='option nivo flex' value='fade' <?php if ($this->slider->get_setting('effect') == 'fade') echo 'selected=selected' ?>><?php _e("Fade", 'metaslider') ?></option>
                                        <option class='option nivo' value='slideInRight' <?php if ($this->slider->get_setting('effect') == 'slideInRight') echo 'selected=selected' ?>><?php _e("Slide In Right", 'metaslider') ?></option>
                                        <option class='option nivo' value='slideInLeft' <?php if ($this->slider->get_setting('effect') == 'slideInLeft') echo 'selected=selected' ?>><?php _e("Slide In Left", 'metaslider') ?></option>
                                        <option class='option nivo' value='boxRandom' <?php if ($this->slider->get_setting('effect') == 'boxRandom') echo 'selected=selected' ?>><?php _e("Box Random", 'metaslider') ?></option>
                                        <option class='option nivo' value='boxRain' <?php if ($this->slider->get_setting('effect') == 'boxRain') echo 'selected=selected' ?>><?php _e("Box Rain", 'metaslider') ?></option>
                                        <option class='option nivo' value='boxRainReverse' <?php if ($this->slider->get_setting('effect') == 'boxRainReverse') echo 'selected=selected' ?>><?php _e("Box Rain Reverse", 'metaslider') ?></option>
                                        <option class='option nivo' value='boxRainGrowReverse' <?php if ($this->slider->get_setting('effect') == 'boxRainGrowReverse') echo 'selected=selected' ?>><?php _e("Box Rain Grow Reverse", 'metaslider') ?></option>
                                        <option class='option flex' value='slide' <?php if ($this->slider->get_setting('effect') == 'slide') echo 'selected=selected' ?>><?php _e("Slide", 'metaslider') ?></option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <td class='tipsy-tooltip' title="<?php _e("Change the slider style", 'metaslider') ?>">
                                    <?php _e("Theme", 'metaslider') ?>
                                </td>
                                <td>
                                    <select name="settings[theme]" class='theme option coin nivo flex responsive'>
                                        <?php 
                                            $defaultSelected = $this->slider->get_setting('theme') == 'default' ? 'selected=selected' : '';
                                            $darkSelected = $this->slider->get_setting('theme') == 'dark' ? 'selected=selected' : '';
                                            $lightSelected = $this->slider->get_setting('theme') == 'light' ? 'selected=selected' : '';
                                            $barSelected = $this->slider->get_setting('theme') == 'bar' ? 'selected=selected' : '';

                                            $themes =  "<option value='default' class='option nivo flex coin responsive' {$defaultSelected}>Default</option>
                                                        <option value='dark' class='option nivo' {$darkSelected}>Dark (Nivo)</option>
                                                        <option value='light' class='option nivo' {$lightSelected}>Light (Nivo)</option>
                                                        <option value='bar' class='option nivo' {$barSelected}>Bar (Nivo)</option>";

                                            echo apply_filters('metaslider_get_available_themes', $themes, $this->slider->get_setting('theme')); 
                                        ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <td colspan='2' class='highlight'><?php _e("Controls", 'metaslider') ?></td>
                            </tr>
                            <tr>
                                <td class='tipsy-tooltip' title="<?php _e("Show slide navigation row", 'metaslider') ?>">
                                    <?php _e("Arrows", 'metaslider') ?>
                                </td>
                                <td>
                                    <label class='option coin responsive nivo flex' ><input type='checkbox' name="settings[links]" <?php if ($this->slider->get_setting('links') == 'true') echo 'checked=checked' ?> /></label>
                                </td>
                            </tr>

                            <?php

                                $falseChecked = $this->slider->get_setting('navigation') == 'false' ? 'checked' : '';
                                $trueChecked = $this->slider->get_setting('navigation') == 'true' ? 'checked' : '';

                                $navigation_row = "<tr>
                                                        <td class='tipsy-tooltip' title='" . __("Show slide navigation row", 'metaslider') . "'>
                                                            " . __("Navigation", 'metaslider')  . "
                                                        </td>
                                                        <td style='padding: 0 8px 8px 8px;'>
                                                            <label><input type='radio' name='settings[navigation]' value='false' {$falseChecked} />" . __("Hidden", 'metaslider') . "</option></label><br />
                                                            <label><input type='radio' name='settings[navigation]' value='true' {$trueChecked} />" . __("Dots", 'metaslider') . "</option></label><br />
                                                            <label><input type='radio' disabled='disabled' /><span style='color: #c0c0c0'>" . __("Thumbnails (Pro)", 'metaslider') . "</span></option></label>
                                                        </td>
                                                    </tr>";

                                echo apply_filters('metaslider_navigation_options', $navigation_row, $this->slider);
                            ?>
                            <tr>
                                <td colspan='2' class='highlight'><?php _e("Advanced Settings", 'metaslider') ?></td>
                            </tr>
                            <tr>
                                <td class='tipsy-tooltip' title="<?php _e("Center align the slideshow", 'metaslider') ?>">
                                    <?php _e("Center align", 'metaslider') ?>
                                </td>
                                <td>
                                    <input class='option coin responsive nivo flex' type='checkbox' name="settings[center]" <?php if ($this->slider->get_setting('center') == 'true') echo 'checked=checked' ?> />
                                </td>
                            </tr>
                            <tr>
                                <td class='tipsy-tooltip' title="<?php _e("Start the slideshow on page load", 'metaslider') ?>">
                                    <?php _e("Auto play", 'metaslider') ?>
                                </td>
                                <td>
                                    <input class='option responsive nivo flex' type='checkbox' name="settings[autoPlay]" <?php if ($this->slider->get_setting('autoPlay') == 'true') echo 'checked=checked' ?> />
                                </td>
                            </tr>
                            <tr>
                                <td class='tipsy-tooltip' title="<?php _e("Smart Crop ensures your responsive slides are cropped to a ratio that results in a consistent slideshow size", 'metaslider') ?>">
                                    <?php _e("Smart crop", 'metaslider') ?>
                                </td>
                                <td>
                                    <input class='option coin responsive nivo flex' type='checkbox' name="settings[smartCrop]" <?php if ($this->slider->get_setting('smartCrop') !== 'false') echo 'checked=checked' ?> />
                                </td>
                            </tr>
                            <tr>
                                <td class='tipsy-tooltip' title="<?php _e("Display as carousel - when selected the effect and direction options will be ignored.", 'metaslider') ?>">
                                    <?php _e("Carousel mode", 'metaslider') ?>
                                </td>
                                <td>
                                    <input class='option flex' id='carouselMode' type='checkbox' id='carouselMode' name="settings[carouselMode]" <?php if ($this->slider->get_setting('carouselMode') == 'true') echo 'checked=checked' ?> />
                                </td>
                            </tr>
                            <tr>
                                <td class='tipsy-tooltip' title="<?php _e("Randomise the order of the slides", 'metaslider') ?>">
                                    <?php _e("Random", 'metaslider') ?>
                                </td>
                                <td>
                                    <input type='checkbox' name="settings[random]" <?php if ($this->slider->get_setting('random') == 'true') echo 'checked=checked' ?> />
                                </td>
                            </tr>
                            <tr>
                                <td class='tipsy-tooltip' title="<?php _e("Pause the slideshow when hovering over slider, then resume when no longer hovering", 'metaslider') ?>">
                                    <?php _e("Hover pause", 'metaslider') ?>
                                </td>
                                <td>
                                    <input class='option coin flex responsive nivo' type='checkbox' name="settings[hoverPause]" <?php if ($this->slider->get_setting('hoverPause') == 'true') echo 'checked=checked' ?> />
                                </td>
                            </tr>
                            <tr>
                                <td class='tipsy-tooltip' title="<?php _e("Reverse the animation direction", 'metaslider') ?>">
                                    <?php _e("Reverse", 'metaslider') ?>
                                </td>
                                <td>
                                    <input class='option flex' type='checkbox' name="settings[reverse]" <?php if ($this->slider->get_setting('reverse') == 'true') echo 'checked=checked' ?> />
                                </td>
                            </tr>
                            <tr>
                                <td class='tipsy-tooltip' title="<?php _e("How long to display each slide, in milliseconds", 'metaslider') ?>">
                                    <?php _e("Slide delay", 'metaslider') ?> (<?php _e("ms", 'metaslider') ?>)
                                </td>
                                <td>
                                    <input class='option coin flex responsive nivo' type='number' min='500' max='10000' step='100' name="settings[delay]" value='<?php echo $this->slider->get_setting('delay') ?>' />
                                </td>
                            </tr>
                            <tr>
                                <td class='tipsy-tooltip' title="<?php _e("Set the speed of animations, in milliseconds", 'metaslider') ?>">
                                    <?php _e("Animation speed", 'metaslider') ?> (<?php _e("ms", 'metaslider') ?>)
                                </td>
                                <td>
                                    <input class='option flex responsive nivo' type='number' min='0' max='2000' step='100' name="settings[animationSpeed]" value='<?php echo $this->slider->get_setting('animationSpeed') ?>' />
                                </td>
                            </tr>
                            <tr>
                                <td class='tipsy-tooltip' title="<?php _e("Number of squares (width x height)", 'metaslider') ?>">
                                    <?php _e("Number of squares", 'metaslider') ?>
                                </td>
                                <td>
                                    <input class='option coin nivo' type='number' min='1' max='20' step='1' name="settings[spw]" value='<?php echo $this->slider->get_setting('spw') ?>' /> x 
                                    <input class='option coin nivo' type='number' min='1' max='20' step='1' name="settings[sph]" value='<?php echo $this->slider->get_setting('sph') ?>' />
                                </td>
                            </tr>
                            <tr>
                                <td class='tipsy-tooltip' title="<?php _e("Number of slices", 'metaslider') ?>">
                                    <?php _e("Number of slices", 'metaslider') ?>
                                </td>
                                <td>
                                    <input class='option nivo' type='number' min='1' max='20' step='1' name="settings[slices]" value='<?php echo $this->slider->get_setting('slices') ?>' />
                                </td>
                            </tr>
                            <tr>
                                <td class='tipsy-tooltip' title="<?php _e("Select the sliding direction", 'metaslider') ?>"><?php _e("Slide direction", 'metaslider') ?></td>
                                <td>
                                    <select class='option flex' name="settings[direction]">
                                        <option value='horizontal' <?php if ($this->slider->get_setting('direction') == 'horizontal') echo 'selected=selected' ?>><?php _e("Horizontal", 'metaslider') ?></option>
                                        <option value='vertical' <?php if ($this->slider->get_setting('direction') == 'vertical') echo 'selected=selected' ?>><?php _e("Vertical", 'metaslider') ?></option>
                                    </select>                       
                                </td>
                            </tr>
                            <tr>
                                <td class='tipsy-tooltip' title="<?php _e("Animation easing effect", 'metaslider') ?>">
                                    <?php _e("Easing", 'metaslider') ?>
                                </td>
                                <td>
                                    <select name="settings[easing]" class='option flex'>
                                        <?php 
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
                                                echo "<option value='{$option}'";
                                                if ($this->slider->get_setting('easing') == $option) {
                                                    echo 'selected=selected';
                                                }
                                                echo ">" . ucfirst(preg_replace('/(\w+)([A-Z])/U', '\\1 \\2', $option)) . "</option>";
                                            }
                                        ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <td class='tipsy-tooltip' title="<?php _e("Set the text for the 'previous' direction item", 'metaslider') ?>">
                                    <?php _e("Previous text", 'metaslider') ?>
                                </td>
                                <td>
                                    <input class='option coin flex responsive nivo' type='text' name="settings[prevText]" value='<?php if ($this->slider->get_setting('prevText') != 'false') echo $this->slider->get_setting('prevText') ?>' />
                                </td>
                            </tr>
                            <tr>
                                <td class='tipsy-tooltip' title="<?php _e("Set the text for the 'next' direction item", 'metaslider') ?>">
                                    <?php _e("Next text", 'metaslider') ?>
                                </td>
                                <td>
                                    <input class='option coin flex responsive nivo' type='text' name="settings[nextText]" value='<?php if ($this->slider->get_setting('nextText') != 'false') echo $this->slider->get_setting('nextText') ?>' />
                                </td>
                            </tr>
                            <tr>
                                <td class='tipsy-tooltip' title="<?php _e("Delay beetwen squares in ms", 'metaslider') ?>">
                                    <?php _e("Square delay", 'metaslider') ?> (<?php _e("ms", 'metaslider') ?>)
                                </td>
                                <td>
                                    <input class='option coin' type='number' min='0' max='500' step='10' name="settings[sDelay]" value='<?php echo $this->slider->get_setting('sDelay') ?>' />
                                </td>
                            </tr>
                            <tr>
                                <td class='tipsy-tooltip' title="<?php _e("Opacity of title and navigation", 'metaslider') ?>">
                                    <?php _e("Opacity", 'metaslider') ?>
                                </td>
                                <td>
                                    <input class='option coin' type='number' min='0.1' max='1.0' step='0.1' name="settings[opacity]" value='<?php echo $this->slider->get_setting('opacity') ?>' />
                                </td>
                            </tr>
                            <tr>
                                <td class='tipsy-tooltip' title="<?php _e("Set the fade in speed of the caption", 'metaslider') ?>">
                                    <?php _e("Caption speed", 'metaslider') ?> (<?php _e("ms", 'metaslider') ?>)
                                </td>
                                <td>
                                    <input class='option coin' type='number' min='0' max='10000' step='100' name="settings[titleSpeed]" value='<?php echo $this->slider->get_setting('titleSpeed') ?>' />
                                </td>
                            </tr>
                            <tr>
                                <td colspan='2' class='highlight'><?php _e("Developer Options", 'metaslider') ?></td>
                            </tr>
                            <tr>
                                <td class='tipsy-tooltip' title="<?php _e("Specify any custom CSS Classes you would like to be added to the slider wrapper", 'metaslider') ?>">
                                    <?php _e("CSS classes", 'metaslider') ?>
                                </td>
                                <td>
                                    <input type='text' name="settings[cssClass]" value='<?php if ($this->slider->get_setting('cssClass') != 'false') echo $this->slider->get_setting('cssClass') ?>' />
                                </td>
                            </tr>
                            <tr>
                                <td class='tipsy-tooltip' title="<?php _e("Uncheck this is you would like to include your own Javascript", 'metaslider') ?>">
                                    <?php _e("Print Scripts", 'metaslider') ?>
                                </td>
                                <td>
                                    <input type='checkbox' class='useWithCaution' name="settings[printCss]" <?php if ($this->slider->get_setting('printCss') == 'true') echo 'checked=checked' ?> />
                                    <span class='tipsy-tooltip' title="<?php _e("Uncheck this is you would like to include your own CSS", 'metaslider') ?>">
                                        <?php _e("CSS", 'metaslider') ?>
                                    </span>
                                    <input type='checkbox' class='useWithCaution' name="settings[printJs]" <?php if ($this->slider->get_setting('printJs') == 'true') echo 'checked=checked' ?> />
                                    <span class='tipsy-tooltip' title="<?php _e("Uncheck this is you would like to include your own Javascript", 'metaslider') ?>">
                                        <?php _e("JavaScript", 'metaslider') ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td colspan='2'>
                                    <a class='alignright delete-slider button-secondary confirm' href="?page=metaslider&delete=<?php echo $this->slider->id ?>"><?php _e("Delete Slider", 'metaslider') ?></a>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <table class="widefat shortcode">
                        <thead>
                            <tr>
                                <th><?php _e("Usage", 'metaslider') ?></th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr>
                                <td class='highlight'><?php _e("Shortcode", 'metaslider') ?></td>
                            </tr>
                            <tr>
                                <td><input readonly='readonly' type='text' value='[metaslider id=<?php echo $this->slider->id ?>]' /></td>
                            </tr>
                            <tr>
                                <td class='highlight'><?php _e("Template Include", 'metaslider') ?></td>
                            </tr>
                            <tr>
                                <td><input readonly='readonly' type='text' value='&lt;?php echo do_shortcode("[metaslider id=<?php echo $this->slider->id ?>]"); ?>' /></td>
                            </tr>
                        </tbody>

                    </table>

                    <ul class='info'>
                        <li>
                            <a href="https://twitter.com/share" class="twitter-share-button" data-url="http://www.metaslider.com" data-text="I'm using Meta Slider, you should check it out!" data-hashtags="metaslider, wordpress, slideshow">Tweet</a>
                            <script>!function(d,s,id){var js,fjs=d.getElementsByTagName(s)[0],p=/^http:/.test(d.location)?'http':'https';if(!d.getElementById(id)){js=d.createElement(s);js.id=id;js.src=p+'://platform.twitter.com/widgets.js';fjs.parentNode.insertBefore(js,fjs);}}(document, 'script', 'twitter-wjs');</script>                </li>
                        <li>
                            <div class="g-plusone" data-size="medium" data-href="http://www.metaslider.com"></div>
                            <script type="text/javascript">
                              (function() {
                                var po = document.createElement('script'); po.type = 'text/javascript'; po.async = true;
                                po.src = 'https://apis.google.com/js/plusone.js';
                                var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(po, s);
                              })();
                            </script>
                        </li>
                        <li>
                            <iframe style='border:none; overflow:hidden; width:96px; height:21px;' src="//www.facebook.com/plugins/like.php?href=http%3A%2F%2Fwww.metaslider.com&amp;send=false&amp;layout=button_count&amp;width=90&amp;show_faces=false&amp;font&amp;colorscheme=light&amp;action=like&amp;height=21&amp;appId=156668027835524" scrolling="no" frameborder="0" style="border:none; overflow:hidden; width:450px; height:21px;" allowTransparency="true"></iframe>
                        </li>
                    </ul>
                </div>
            </form>
        </div>
        <?php
    }
}

$metaslider = new MetaSliderPlugin();

?>