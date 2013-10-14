<?php
/**
 * Override parent 'MetaSlider' class with CoinSlider specific markup,
 * javascript, css and settings.
 */
class MetaCoinSlider extends MetaSlider {

    protected $js_function = 'coinslider';
    protected $js_path = 'sliders/coinslider/coin-slider.min.js';
    protected $css_path = 'sliders/coinslider/coin-slider-styles.css';

    /**
     * Enable the parameters that are accepted by the slider
     * 
     * @return array enabled parameters
     */
    protected function get_param($param) {
        $params = array(
            'effect' => 'animation',
            'width' => 'width',
            'height' => 'height',
            'sph' => 'sph',
            'spw' => 'spw',
            'delay' => 'delay',
            'sDelay' => 'sDelay',
            'opacity' => 'opacity',
            'titleSpeed' => 'titleSpeed',
            'hoverPause' => 'hoverPause',
            'navigation' => 'showNavigationButtons',
            'links' => 'showNavigationPrevNext',
            'prevText' => 'prevText',
            'nextText' => 'nextText'
        );

        if (isset($params[$param])) {
            return $params[$param];
        }

        return false;
    }

    /**
     * Build the HTML for a slider.
     *
     * @return string slider markup.
     */
    protected function get_html() {
        $retVal = "<div id='" . $this->get_identifier() . "' class='coin-slider'>";
        
        foreach ($this->slides as $slide) {
            $retVal .= "\n" . $slide;
        }
        
        $retVal .= "\n        </div>";
        
        return $retVal;
    }
}
?>