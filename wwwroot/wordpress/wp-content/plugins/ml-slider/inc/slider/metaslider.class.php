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
    public function __construct($id, $shortcode_settings) {
        $this->id = $id;
        $this->settings = array_merge($shortcode_settings, $this->get_settings());
        $this->identifier = 'metaslider_' . $this->id;
        $this->save();
        $this->populate_slides();
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
            'thumb_height' => 100,
            'fullWidth' => false,
            'noConflict' => false
        );

        $params = apply_filters('metaslider_default_parameters', $params);
        
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
            check_admin_referer('metaslider_save_' . $this->id);
            $this->update_settings($_POST['settings']);
        }
        if (isset($_POST['title'])) {
            check_admin_referer('metaslider_save_' . $this->id);
            $this->update_title($_POST['title']);
        }
        if (isset($_GET['deleteSlide'])) {
            $this->delete_slide(intval($_GET['deleteSlide']));
        }

        // make changes to slides
        if (isset($_POST['attachment'])) {
            check_admin_referer('metaslider_save_' . $this->id);
            $this->update_slides($_POST['attachment']);
        }
    }

    /**
     * The main query for extracting the slides for the slideshow
     */
	public function get_slides() {
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

        $args = apply_filters('metaslider_populate_slides_args', $args, $this->id, $this->settings);

        $query = new WP_Query($args);

        return $query;
	}

    /**
     * Return slides for the current slider
     *
     * @return array collection of slides belonging to the current slider
     */
    private function populate_slides() {
        $slides = array();

        $query = $this->get_slides();

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
        $html[] = '<!-- meta slider -->';
        $html[] = '<div style="' . $this->get_container_style() . '" class="' . $this->get_container_class() .'">';
        $html[] = '    ' . $this->get_inline_css();
        $html[] = '    <div id="' . $this->get_container_id() . '">';
        $html[] = '        ' . $this->get_html();
        $html[] = '        ' . $this->get_html_after();
        $html[] = '    </div>';
        $html[] = '    <script type="text/javascript">';
        $html[] = '        ' .  $this->get_inline_javascript();
        $html[] = '    </script>';
        $html[] = '</div>';
        $html[] = '<!--// meta slider-->';

        $slideshow = implode("\n", $html);

        $slideshow = apply_filters('metaslider_slideshow_output', $slideshow, $this->id, $this->settings);

        return $slideshow;
    }

    /**
     * Return the ID to use for the container
     */
    private function get_container_id() {
        $container_id = 'metaslider_container_' . $this->id;

        $id = apply_filters('metaslider_container_id', $container_id, $this->id, $this->settings);

        return $id;
    }

    /**
     * Return the classes to use for the slidehsow container
     */
    private function get_container_class() {
        $class = "metaslider metaslider-{$this->get_setting('type')} metaslider-{$this->id} ml-slider";

        // apply the css class setting
        if ($this->get_setting('cssClass') != 'false') {
            $class .= " " . $this->get_setting('cssClass');
        }

        // handle any custom classes
        $class = apply_filters('metaslider_css_classes', $class, $this->id, $this->settings);

        return $class;
    }

    /**
     * Return the inline CSS style for the slideshow container.
     */
    private function get_container_style() {
        // default
        $style = "max-width: {$this->get_setting('width')}px;";

        // carousels are always 100% wide
        if ($this->get_setting('carouselMode') == 'true' || ($this->get_setting('fullWidth') == 'true') && $this->get_setting('type') != 'coin') {
            $style = "width: 100%;";
        }

        // percentWidth showcode parameter takes precedence
        if ($this->get_setting('percentwidth') != 'false' && $this->get_setting('percentwidth') > 0) {
            $style = "width: {$this->get_setting('percentwidth')}%;";
        }

        // center align the slideshow
        if ($this->get_setting('center') != 'false') {
            $style .= " margin: 0 auto;";
        }

        // handle any custom container styles
        $style = apply_filters('metaslider_container_style', $style, $this->id, $this->settings);

        return $style;
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
        $custom_js_before = $this->get_custom_javascript_before();
        $custom_js_after = $this->get_custom_javascript_after();
        
        $script = "var " . $this->identifier . " = function($) {";
        $script .= $custom_js_before;
        $script .= "\n            $('#" . $this->identifier . "')." . $this->js_function . "({ ";
        $script .= "\n                " . $this->get_javascript_parameters();
        $script .= "\n            });";
        $script .= $custom_js_after;
        $script .= "\n        };";
        $script .= "\n        var timer_" . $this->identifier . " = function() {";
        $script .= "\n            var slider = !window.jQuery ? window.setTimeout(timer_{$this->identifier}, 100) : !jQuery.isReady ? window.setTimeout(timer_{$this->identifier}, 100) : {$this->identifier}(window.jQuery);";
        $script .= "\n        };";
        $script .= "\n        timer_" . $this->identifier . "();";

        return $script;
    }

    /**
     * Custom HTML to add immediately below the markup
     */
    private function get_html_after() {
        $type = $this->get_setting('type');

        $html = apply_filters("metaslider_{$type}_slider_html_after", "", $this->id, $this->settings);

        if (strlen($html)) {
            return "\n            {$html}";
        }

        return "";
    }

    /**
     * Custom JavaScript to execute immediately before the slideshow is initialized
     */
    private function get_custom_javascript_before() {
        $type = $this->get_setting('type');

        $custom_js = apply_filters("metaslider_{$type}_slider_javascript_before", "", $this->id);

        if (strlen($custom_js)) {
            return "\n            {$custom_js}";
        }

        return "";
    }

    /**
     * Custom Javascript to execute immediately after the slideshow is initialized
     */
    private function get_custom_javascript_after() {
        $type = $this->get_setting('type');

        $custom_js = apply_filters("metaslider_{$type}_slider_javascript", "", $this->id);

        if (strlen($custom_js)) {
            return "\n            {$custom_js}";
        }

        return "";
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
        $options = apply_filters("metaslider_{$type}_slider_parameters", $options, $this->id, $this->settings);

        // create key:value strings
        foreach ($options as $key => $value) {
            if (is_array($value)) {
                $pairs[] = "{$key}: function() {\n                " 
                            . implode("\n                ", $value) 
                            . "\n                }";
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
        $css = apply_filters("metaslider_css", "", $this->settings, $this->id);

        // use this to add the scoped attribute for HTML5 validation (if needed)
        $attributes = apply_filters("metaslider_style_attributes", "", $this->settings, $this->id);

        if (strlen($css)) {
            return "<style type=\"text/css\"{$attributes}>{$css}\n    </style>";
        }

        return "";
    }

    /**
     * Include slider assets, JS and CSS paths are specified by child classes.
     */
    public function enqueue_scripts() {
        if ($this->get_setting('printJs') == 'true') {
            wp_enqueue_script('metaslider-' . $this->get_setting('type') . '-slider', METASLIDER_ASSETS_URL . $this->js_path, array('jquery'), METASLIDER_VERSION);
        }

        if ($this->get_setting('printCss') == 'true') {
            // this will be added to the bottom of the page as <head> has already been processed by WordPress.
            // For HTML5 compatibility, use a minification plugin to move the CSS to the <head>
            wp_enqueue_style('metaslider-' . $this->get_setting('type') . '-slider', METASLIDER_ASSETS_URL . $this->css_path, false, METASLIDER_VERSION);
            wp_enqueue_style('metaslider-public', METASLIDER_ASSETS_URL . 'metaslider/public.css', false, METASLIDER_VERSION);
        }

        do_action('metaslider_register_public_styles');
    }

    /**
     * Update the slider settings, converting checkbox values (on/off) to true or false.
     */
    public function update_settings($new_settings) {
        $old_settings = $this->get_settings();

        // convert submitted checkbox values from 'on' or 'off' to boolean values
        $checkboxes = array('noConflict', 'fullWidth', 'hoverPause', 'links', 'reverse', 'random', 'printCss', 'printJs', 'smoothHeight', 'center', 'smartCrop', 'carouselMode', 'autoPlay');

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
     * it from the slide taxonomy.
     *
     * @param int $slide_id
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
     *
     * @param array $data - posted form data.
     */
    private function update_slides($data) {
        foreach ($data as $slide_id => $fields) {
            do_action("metaslider_save_{$fields['type']}_slide", $slide_id, $this->id, $fields); 
        }
    }
}
?>
