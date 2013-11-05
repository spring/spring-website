<?php
/**
 * Generic Slider super class. Extended by library specific classes.
 *
 * This class handles all slider related functionality, including saving settings and outputting
 * the slider HTML (front end and back end)
 */
class MetaSlider {

    public $id = 0; // slider ID
    public $identifier = 0; // unique identifier
    public $slides = array(); //slides belonging to this slider
    public $settings = array(); // slider settings

    /**
     * Constructor
     */
    public function __construct($id) {
        $this->id = $id;
        $this->settings = $this->get_settings();
        $this->identifier = 'metaslider_' . $this->id;
        $this->save();
        $this->populate_slides();

        add_filter('metaslider_css', array($this, 'get_slider_css'), 10, 3);
    }

    /**
     * Return the unique identifier for the slider (used to avoid javascript conflicts)
     * 
     * @return string unique identifier for slider
     */
    protected function get_identifier() {
        return $this->identifier;
    }

    /**
     * Get settings for the current slider
     *
     * @return array slider settings
     */
    private function get_settings() {
        $settings = get_post_meta($this->id, 'ml-slider_settings', true);

        if (is_array($settings) && 
            isset($settings['type']) && 
            in_array($settings['type'], array('flex', 'coin', 'nivo', 'responsive'))) 
        {
            return $settings;
        } else {
            return $this->get_default_parameters();
        }
    }

    /**
     * Return an individual setting
     *
     * @param string $name Name of the setting
     * @return string setting value or 'false'
     */
    public function get_setting($name) {
        if (!isset($this->settings[$name])) {
            $defaults = $this->get_default_parameters();

            if (isset($defaults[$name])) {
                return $defaults[$name] ? $defaults[$name] : 'false';
            }
        } else {
            if (strlen($this->settings[$name]) > 0) {
                return $this->settings[$name];
            }
        }

        return 'false';
    }

    /**
     * Get the slider libary parameters, this lists all possible parameters and their
     * default values. Slider subclasses override this and disable/rename parameters
     * appropriately.
     *
     * @return string javascript options
     */
    public function get_default_parameters() {
        $params = array(
            'type' => 'flex',
            'random' => false,
            'cssClass' => '',
            'printCss' => true,
            'printJs' => true,
            'width' => 700,
            'height' => 300,
            'spw' => 7,
            'sph' => 5,
            'delay' => 3000,
            'sDelay' => 30,
            'opacity' => 0.7,
            'titleSpeed' => 500,
            'effect' => 'random',
            'navigation' => true,
            'links' => true,
            'hoverPause' => true,
            'theme' => 'default',
            'direction' => 'horizontal',
            'reverse' => false,
            'animationSpeed' => 600,
            'prevText' => '<',
            'nextText' => '>',
            'slices' => 15,
            'center' => false,
            'smartCrop' => true,
            'carouselMode' => false,
            'easing' => 'linear',
            'autoPlay' => true,
            'thumb_width' => 150,
            'thumb_height' => 100
        );
        
        return $params;
    }

    /**
     * Save the slider details and initiate the update of all slides associated with slider.
     */
    private function save() {
        if (!is_admin()) {
            return;
        }
        // make changes to slider
        if (isset($_POST['settings'])) {
            $this->update_settings($_POST['settings']);
        }
        if (isset($_POST['title'])) {
            $this->update_title($_POST['title']);
        }
        if (isset($_GET['deleteSlide'])) {
            $this->delete_slide(intval($_GET['deleteSlide']));
        }

        // make changes to slides
        if (isset($_POST['attachment'])) {
            $this->update_slides($_POST['attachment']);
        }
    }

    /**
     * Return slides for the current slider
     *
     * @return array collection of slides belonging to the current slider
     */
    private function populate_slides() {
        $slides = array();

        $args = array(
            'force_no_custom_order' => true,
            'orderby' => 'menu_order',
            'order' => 'ASC',
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'lang' => '', // polylang, ingore language filter
            'suppress_filters' => 1, // wpml, ignore language filter
            'posts_per_page' => -1,
            'tax_query' => array(
                array(
                    'taxonomy' => 'ml-slider',
                    'field' => 'slug',
                    'terms' => $this->id
                )
            )
        );

        $query = new WP_Query($args);

        $slides = array();

        while ($query->have_posts()) {
            $query->next_post();

            $type = get_post_meta($query->post->ID, 'ml-slider_type', true);
            $type = $type ? $type : 'image'; // backwards compatibility, fall back to 'image'

            if (has_filter("metaslider_get_{$type}_slide")) {
                $return = apply_filters("metaslider_get_{$type}_slide", $query->post->ID, $this->id);

                if (is_array($return)) {
                    $slides = array_merge($slides, $return);
                } else {
                    $slides[] = $return;
                }
            }
        }

        // apply random setting
        if ($this->get_setting('random') == 'true' && !is_admin()) {
            shuffle($slides);
        }

        $this->slides = $slides;

        return $this->slides;
    }

    /**
     * Render each slide belonging to the slider out to the screen
     */
    public function render_admin_slides() {
        foreach($this->slides as $slide) {
            echo $slide;
        }
    }

    /**
     * Output the HTML and Javascript for this slider
     *
     * @return string HTML & Javascrpt
     */
    public function render_public_slides() {
        $class = "metaslider metaslider-{$this->get_setting('type')} metaslider-{$this->id} ml-slider";

        // apply the css class setting
        if ($this->get_setting('cssClass') != 'false') {
            $class .= " " . $this->get_setting('cssClass');
        }

        // handle any custom classes
        $class = apply_filters('metaslider_css_classes', $class, $this->id, $this->settings);

        // carousels are always 100% wide
        if ($this->get_setting('carouselMode') != 'true') {
            $style = "max-width: {$this->get_setting('width')}px;";
        } else {
            $style = "width: 100%;";
        }

        // center align the slideshow
        if ($this->get_setting('center') != 'false') {
            $style .= " margin: 0 auto;";
        }

        // build the HTML
        $html  = "\n<!-- meta slider -->";
        $html .= "\n<div style=\"{$style}\" class=\"{$class}\">";
        $html .= "\n    " . $this->get_inline_css();
        $html .= "\n    <div id=\"metaslider_container_{$this->id}\">";
        $html .= "\n        " . $this->get_html(); 
        $html .= "\n    </div>";
        $html .= $this->get_inline_javascript();
        $html .= "\n</div>";
        $html .= "\n<!--// meta slider-->";

        return $html;
    }

    /**
     * Return the Javascript to kick off the slider. Code is wrapped in a timer
     * to allow for themes that load jQuery at the bottom of the page.
     * 
     * Delay execution of slider code until jQuery is ready (supports themes where
     * jQuery is loaded at the bottom of the page)
     *
     * @return string javascript
     */
    private function get_inline_javascript() {
        $identifier = $this->identifier;
        $type = $this->get_setting('type');

        $custom_js = apply_filters("metaslider_{$type}_slider_javascript", "", $this->id);

        $script  = "\n    <script type=\"text/javascript\">";
        $script .= "\n        var " . $identifier . " = function($) {";
        $script .= "\n            $('#" . $identifier . "')." . $this->js_function . "({ ";
        $script .= "\n                " . $this->get_javascript_parameters();
        $script .= "\n            });";
        if (strlen ($custom_js)) {
            $script .= "\n            {$custom_js}";
        }
        $script .= "\n        };";
        $script .= "\n        var timer_" . $identifier . " = function() {";
        $script .= "\n            var slider = !window.jQuery ? window.setTimeout(timer_{$identifier}, 100) : !jQuery.isReady ? window.setTimeout(timer_{$identifier}, 100) : {$identifier}(window.jQuery);";
        $script .= "\n        };";
        $script .= "\n        timer_" . $identifier . "();";
        $script .= "\n    </script>";

        return $script;
    }

    /**
     * Build the javascript parameter arguments for the slider.
     * 
     * @return string parameters
     */
    private function get_javascript_parameters() {
        $options = array();

        // construct an array of all parameters
        foreach ($this->get_default_parameters() as $name => $default) {
            if ($param = $this->get_param($name)) {
                $val = $this->get_setting($name);

                if (gettype($default) == 'integer' || $val == 'true' || $val == 'false') {
                    $options[$param] = $val;
                } else {
                    $options[$param] = '"' . $val . '"';
                }                
            }
        }

        // deal with any customised parameters
        $type = $this->get_setting('type');

        if (has_filter("metaslider_{$type}_slider_parameters")) {
            $options = apply_filters("metaslider_{$type}_slider_parameters", $options, $this->id, $this->settings);
        }

        // create key:value strings
        foreach ($options as $key => $value) {
            if (is_array($value)) {
                $pairs[] = "{$key}: function() {\n                " 
                            . implode("\n                ", $value) 
                            . "\n            }";
            } else {
                $pairs[] = "{$key}:{$value}";
            }
        }

        return implode(",\n                ", $pairs);
    }

    /**
     * Apply any custom inline styling
     * 
     * @return string
     */
    private function get_inline_css() {
        if (has_filter("metaslider_css")) {
            $css = apply_filters("metaslider_css", "", $this->settings, $this->id);

            // use this to add the scoped attribute for HTML5 validation (if needed)
            $attributes = apply_filters("metaslider_style_attributes", "", $this->settings, $this->id);

            if (strlen($css)) {
                return "<style type=\"text/css\"{$attributes}>{$css}\n    </style>";
            }
        }

        return "";
    }

    /**
     * 
     */
    public function get_slider_css($css, $settings, $slider_id) {
        if ($slider_id != $this->id) {
            return $css;
        }

        $imports = "";

        if ($this->get_setting('printCss') == 'true') {
            $stylesheets[] = "@import url('" . METASLIDER_ASSETS_URL . "metaslider/public.css?ver=" . METASLIDER_VERSION . "');";
            $stylesheets[] = "@import url('" . METASLIDER_ASSETS_URL . $this->css_path . "?ver=" . METASLIDER_VERSION . "');";
            $imports = "\n        " . implode("\n        ", $stylesheets);
        }

        return $css . $imports;
    }


    /**
     * Include slider assets, JS and CSS paths are specified by child classes.
     */
    public function enqueue_scripts() {
        if ($this->get_setting('printJs') == 'true') {
            wp_enqueue_script('metaslider-' . $this->get_setting('type') . '-slider', METASLIDER_ASSETS_URL . $this->js_path, array('jquery'), METASLIDER_VERSION);
        }

        do_action('metaslider_register_public_styles');
    }

    /**
     * Update the slider settings, converting checkbox values (on/off) to true or false.
     */
    public function update_settings($new_settings) {
        $old_settings = $this->get_settings();

        // convert submitted checkbox values from 'on' or 'off' to boolean values
        $checkboxes = array('hoverPause', 'links', 'reverse', 'random', 'printCss', 'printJs', 'smoothHeight', 'center', 'smartCrop', 'carouselMode', 'autoPlay');

        foreach ($checkboxes as $checkbox) {
            if (isset($new_settings[$checkbox]) && $new_settings[$checkbox] == 'on') {
                $new_settings[$checkbox] = "true";
            } else {
                $new_settings[$checkbox] = "false";
            }
        }

        // update the slider settings
        update_post_meta($this->id, 'ml-slider_settings', array_merge((array)$old_settings, $new_settings));

        $this->settings = $this->get_settings();
    }

    /**
     * Update the title of the slider
     */
    private function update_title($title) {
        $slide = array(
            'ID' => $this->id,
            'post_title' => $title
        );
        
        wp_update_post($slide);
    }

    /**
     * Delete a slide. This doesn't actually remove the slide from WordPress, simply untags
     * it from the slide taxonomy
     */
    private function delete_slide($slide_id) {
        // Get the existing terms and only keep the ones we don't want removed
        $new_terms = array();
        $current_terms = wp_get_object_terms($slide_id, 'ml-slider', array('fields' => 'ids'));
        $term = get_term_by('name', $this->id, 'ml-slider');

        foreach ($current_terms as $current_term) {
            if ($current_term != $term->term_id) {
                $new_terms[] = intval($current_term);
            }
        }
     
        return wp_set_object_terms($slide_id, $new_terms, 'ml-slider');
    }

    /**
     * Loop over each slide and call the save action on each
     */
    private function update_slides($data) {
        foreach ($data as $slide_id => $fields) {
            do_action("metaslider_save_{$fields['type']}_slide", $slide_id, $this->id, $fields); 
        }
    }
}
?>
