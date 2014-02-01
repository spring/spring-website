<?php
/**
 * Flex Slider specific markup, javascript, css and settings.
 */
class MetaFlexSlider extends MetaSlider {

    protected $js_function = 'flexslider';
    protected $js_path = 'sliders/flexslider/jquery.flexslider-min.js';
    protected $css_path = 'sliders/flexslider/flexslider.css';
    protected $carousel_item_margin = 5;

    /**
     * Constructor
     * 
     * @param integer $id slideshow ID
     */
    public function __construct($id, $shortcode_settings) {
        parent::__construct($id, $shortcode_settings);

        add_filter('metaslider_flex_slider_parameters', array($this, 'enable_carousel_mode'), 10, 2);
        add_filter('metaslider_flex_slider_parameters', array($this, 'enable_easing'), 10, 2);
        add_filter('metaslider_flex_slider_javascript_before', array($this, 'no_conflict_add_flexslider_class'), 9, 2);
        add_filter('metaslider_css', array($this, 'get_carousel_css'), 11, 3);
        add_filter('metaslider_css_classes', array($this, 'remove_bottom_margin'), 11, 3);
        
        $this->carousel_item_margin = apply_filters('metaslider_carousel_margin', $this->carousel_item_margin, $id);
    }

    /**
     * Adjust the slider parameters so they're comparible with the carousel mode
     * 
     * @param array $options
     * @param integer $slider_id
     * @return array $options
     */
    public function enable_carousel_mode($options, $slider_id) {
        if (isset($options["carouselMode"])) {
            if ($options["carouselMode"] == "true") {
                $options["itemWidth"] = $this->get_setting('width');
                $options["animation"] = "'slide'";
                $options["direction"] = "'horizontal'";
                $options["minItems"] = 1;
                $options["itemMargin"] = $this->carousel_item_margin;
            }

            unset($options["carouselMode"]);
        }
        
        // we don't want this filter hanging around if there's more than one slideshow on the page
        remove_filter('metaslider_flex_slider_parameters', array($this, 'enable_carousel_mode'), 10, 2);
        
        return $options;
    }

    /**
     * If 'No Conflict' mode is enabled, the slideshow is output without the 'flexslider' class.
     * This stops themes and plugins from being able to call $('.flexslider').flexslider({}); on Meta
     * Slider slideshows. Only once the Meta Slider javascript is executed is the flexslider class 
     * added to the container, so not to break custom CSS for existing users.
     *
     * @since 2.6-beta
     * @param array $options
     * @param integer $slider_id
     * @return array $options
     */
    public function no_conflict_add_flexslider_class($js, $slider_id) {
    	if ($this->get_setting('noConflict') == 'true') {
    		$js .= "$('#metaslider_{$slider_id}').addClass('flexslider'); // theme/plugin conflict avoidance";
    	}

        // we don't want this filter hanging around if there's more than one slideshow on the page
        remove_filter('metaslider_flex_slider_javascript_before', array($this, 'add_flexslider_class'), 9, 2);
        
        return $js;
    }

    /**
     * Ensure CSS transitions are disabled when easing is enabled.
     * 
     * @param array $options
     * @param integer $slider_id
     * @return array $options
     */
    public function enable_easing($options, $slider_id) {
        if (isset($options["easing"])) {
            $options['useCSS'] = 'false';
        }
        
        // we don't want this filter hanging around if there's more than one slideshow on the page
        remove_filter('metaslider_flex_slider_parameters', array($this, 'enable_easing'), 10, 2);

        return $options;
    }

    /**
     * Add a 'nav-hidden' class to slideshows where the navigation is hidden.
     * 
     * @param string $css
     * @param array $settings
     * @param integer $slider_id
     * @return string $css
     */
    public function remove_bottom_margin($class, $id, $settings) {
        if (isset($settings["navigation"]) && $settings['navigation'] == 'false') {
            return $class .= " nav-hidden";
        }

        // we don't want this filter hanging around if there's more than one slideshow on the page
        remove_filter('metaslider_css_classes', array($this, 'remove_bottom_margin'), 11, 3);

        return $class;
    }

    /**
     * Return css to ensure our slides are rendered correctly in the carousel
     * 
     * @param string $css
     * @param array $settings
     * @param integer $slider_id
     * @return string $css
     */
    public function get_carousel_css($css, $settings, $slider_id) {
        if (isset($settings["carouselMode"]) && $settings['carouselMode'] == 'true') {
            $css .= "\n        #metaslider_{$slider_id}.flexslider li {margin-right: {$this->carousel_item_margin}px;}";
        }

        // we don't want this filter hanging around if there's more than one slideshow on the page
        remove_filter('metaslider_css', array($this, 'get_carousel_css'), 11, 3);

        return $css;
    }

    /**
     * Enable the parameters that are accepted by the slider
     * 
     * @param string $param
     * @return array|boolean enabled parameters (false if parameter doesn't exist)
     */
    protected function get_param($param) {
        $params = array(
            'effect' => 'animation',
            'direction' => 'direction',
            'prevText' => 'prevText',
            'nextText' => 'nextText',
            'delay' => 'slideshowSpeed',
            'animationSpeed' => 'animationSpeed',
            'hoverPause' => 'pauseOnHover',
            'reverse' => 'reverse',
            'navigation' => 'controlNav',
            'links' =>'directionNav',
            'carouselMode' => 'carouselMode',
            'easing' => 'easing',
            'autoPlay' => 'slideshow'
        );

        if (isset($params[$param])) {
            return $params[$param];
        }

        return false;
    }

    /**
     * Include slider assets
     */
    public function enqueue_scripts() {
        parent::enqueue_scripts();

        if ($this->get_setting('printJs') == 'true') {
            wp_enqueue_script('metaslider-easing', METASLIDER_ASSETS_URL . 'easing/jQuery.easing.min.js', array('jquery'), METASLIDER_VERSION);
        }
    }
    
    /**
     * Build the HTML for a slider.
     *
     * @return string slider markup.
     */
    protected function get_html() {
    	$class = $this->get_setting('noConflict') == 'true' ? "" : ' class="flexslider"';

        $return_value = '<div id="' . $this->get_identifier() . '"' . $class . '>';
        $return_value .= "\n            <ul class=\"slides\">";

        foreach ($this->slides as $slide) {
            // backwards compatibility with older versions of Meta Slider Pro (< v2.0)
            // MS Pro < 2.0 does not include the <li>
            // MS Pro 2.0+ returns the <li>
            if (strpos($slide, '<li') === 0) {
                $return_value .= "\n                " . $slide;
            } else {
                $return_value .= "\n                <li style=\"display: none;\">" . $slide . "</li>";
            }
        }
        
        $return_value .= "\n            </ul>";
        $return_value .= "\n        </div>";

        return $return_value;
    }
}
?>