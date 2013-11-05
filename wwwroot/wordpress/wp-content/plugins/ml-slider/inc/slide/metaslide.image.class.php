<?php
/**
 * Generic Slider super class. Extended by library specific classes.
 */
class MetaImageSlide extends MetaSlide {

    /**
     * Register slide type
     */
    public function __construct() {
        add_filter('metaslider_get_image_slide', array($this, 'get_slide'), 10, 2);
        add_action('metaslider_save_image_slide', array($this, 'save_slide'), 5, 3);
        add_action('wp_ajax_create_image_slide', array($this, 'ajax_create_slide'));
    }

    /**
     * Create a new slide and echo the admin HTML
     */
    public function ajax_create_slide() {
        $slide_id = intval($_POST['slide_id']);
        $slider_id = intval($_POST['slider_id']);

        $this->set_slide($slide_id);
        $this->set_slider($slider_id);
        $this->tag_slide_to_slider();

        $this->add_or_update_or_delete_meta($slide_id, 'type', 'image');

        $settings = get_post_meta($slider_id, 'ml-slider_settings', true);

        // create a copy of the correct sized image
        $imageHelper = new MetaSliderImageHelper(
            $slide_id,
            $settings['width'], 
            $settings['height'], 
            isset($settings['smartCrop']) ? $settings['smartCrop'] : 'false'
        );

        $url = $imageHelper->get_image_url();

        echo $this->get_admin_slide();
        die();
    }

    /**
     * Return the HTML used to display this slide in the admin screen
     * 
     * @return string slide html
     */
    protected function get_admin_slide() {
        // get some slide settings
        $thumb   = $this->get_thumb();
        $full    = wp_get_attachment_image_src($this->slide->ID, 'full');
        $filename = basename($full[0]);
        $url     = get_post_meta($this->slide->ID, 'ml-slider_url', true);
        $target  = get_post_meta($this->slide->ID, 'ml-slider_new_window', true) ? 'checked=checked' : '';
        $caption = htmlentities($this->slide->post_excerpt, ENT_QUOTES, 'UTF-8');

        // localisation
        $str_caption    = __("Caption", 'metaslider');
        $str_new_window = __("New Window", 'metaslider');
        $str_url        = __("URL", 'metaslider');

        // slide row HTML
        $row  = "<tr class='slide flex responsive nivo coin'>";
        $row .= "    <td class='col-1'>";
        $row .= "        <div class='thumb' style='background-image: url({$thumb})'>";
        $row .= "            <a class='delete-slide confirm' href='?page=metaslider&id={$this->slider->ID}&deleteSlide={$this->slide->ID}'>x</a>";
        $row .= "            <span class='slide-details'>Image {$filename}</span>";
        $row .= "        </div>";
        $row .= "    </td>";
        $row .= "    <td class='col-2'>";
        $row .= "        <textarea name='attachment[{$this->slide->ID}][post_excerpt]' placeholder='{$str_caption}'>{$caption}</textarea>";
        $row .= "        <input class='url' type='text' name='attachment[{$this->slide->ID}][url]' placeholder='{$str_url}' value='{$url}' />";
        $row .= "        <div class='new_window'>";
        $row .= "            <label>{$str_new_window}<input type='checkbox' name='attachment[{$this->slide->ID}][new_window]' {$target} /></label>";
        $row .= "        </div>";
        $row .= "        <input type='hidden' name='attachment[{$this->slide->ID}][type]' value='image' />";
        $row .= "        <input type='hidden' class='menu_order' name='attachment[{$this->slide->ID}][menu_order]' value='{$this->slide->menu_order}' />";
        $row .= "    </td>";
        $row .= "</tr>";

        return $row;
    }

    /**
     * Returns the HTML for the public slide
     * 
     * @return string slide html
     */
    protected function get_public_slide() {
        // get the image url (and handle cropping)
        $imageHelper = new MetaSliderImageHelper(
            $this->slide->ID,
            $this->settings['width'], 
            $this->settings['height'], 
            isset($this->settings['smartCrop']) ? $this->settings['smartCrop'] : 'false'
        );

        $thumb = $imageHelper->get_image_url();

        // store the slide details
        $slide = array(
            'id' => $this->slide->ID,
            'thumb' => $thumb,
            'url' => __(get_post_meta($this->slide->ID, 'ml-slider_url', true)),
            'alt' => __(get_post_meta($this->slide->ID, '_wp_attachment_image_alt', true)),
            'target' => get_post_meta($this->slide->ID, 'ml-slider_new_window', true) ? '_blank' : '_self', 
            'caption' => __(html_entity_decode($this->slide->post_excerpt, ENT_NOQUOTES, 'UTF-8')),
            'caption_raw' => __($this->slide->post_excerpt),
            'class' => "slider-{$this->slider->ID} slide-{$this->slide->ID}",
            'rel' => "",
            'data-thumb' => ""
        );

        // fix slide URLs
        if (strpos($slide['url'], 'www.') === 0) {
            $slide['url'] = 'http://' . $slide['url'];
        }

        $slide = apply_filters('metaslider_image_slide_attributes', $slide, $this->slider->ID, $this->settings);

        // return the slide HTML
        switch($this->settings['type']) {
            case "coin":
                return $this->get_coin_slider_markup($slide);
            case "flex":
                return $this->get_flex_slider_markup($slide);
            case "nivo":
                return $this->get_nivo_slider_markup($slide);
            case "responsive":
                return $this->get_responsive_slides_markup($slide);
            default:
                return $this->get_flex_slider_markup($slide);
        }
    }


    /**
     * Build image HTML
     * 
     * @param array $attributes
     * @return string image HTML
     */
    private function build_image_tag($attributes) {
        $html = "<img";

        foreach ($attributes as $att => $val) {
            if (strlen($val)) {
                $html .= " " . $att . '="' . $val . '"';
            }
        }

        $html .= " />";

        return $html;
    }

    /**
     * Generate nivo slider markup
     * 
     * @return string slide html
     */
    private function get_nivo_slider_markup($slide) {
        $attributes = array(
            'src' => $slide['thumb'],
            'height' => $this->settings['height'],
            'width' => $this->settings['width'],
            'title' => htmlentities($slide['caption_raw'], ENT_QUOTES, 'UTF-8'),
            'data-thumb' => $slide['data-thumb'],
            'alt' => $slide['alt'],
            'rel' => $slide['rel'],
            'class' => $slide['class']
        );

        $html = $this->build_image_tag($attributes);

        if (strlen($slide['url'])) {
            $html = '<a href="' . $slide['url'] . '" target="' . $slide['target'] . '">' . $html . '</a>';
        }

        return apply_filters('metaslider_image_nivo_slider_markup', $html, $slide, $this->settings);
    }

    /**
     * Generate flex slider markup
     * 
     * @return string slide html
     */
    private function get_flex_slider_markup($slide) {
        $attributes = array(
            'src' => $slide['thumb'],
            'height' => $this->settings['height'],
            'width' => $this->settings['width'],
            'alt' => $slide['alt'],
            'rel' => $slide['rel'],
            'class' => $slide['class']
        );

        $html = $this->build_image_tag($attributes);

        if (strlen($slide['url'])) {
            $html = '<a href="' . $slide['url'] . '" target="' . $slide['target'] . '">' . $html . '</a>';
        }

        if (strlen($slide['caption'])) {
            $html .= '<div class="caption-wrap"><div class="caption">' . $slide['caption'] . '</div></div>';
        }

        $thumb = isset($slide['data-thumb']) && strlen($slide['data-thumb']) ? " data-thumb=\"{$slide['data-thumb']}\"" : "";
        
        $html = '<li style="display: none;"' . $thumb . '>' . $html . '</li>';

        return apply_filters('metaslider_image_flex_slider_markup', $html, $slide, $this->settings);
    }

    /**
     * Generate coin slider markup
     * 
     * @return string slide html
     */
    private function get_coin_slider_markup($slide) {
        $url = strlen($slide['url']) ? $slide['url'] : 'javascript:void(0)'; // coinslider always wants a URL

        $attributes = array(
            'src' => $slide['thumb'],
            'height' => $this->settings['height'],
            'width' => $this->settings['width'],
            'alt' => $slide['alt'],
            'rel' => $slide['rel'],
            'class' => $slide['class']
        );

        $html = $this->build_image_tag($attributes);

        if (strlen($slide['caption'])) {
            $html .= "<span>{$slide['caption']}</span>";
        }

        $html  = '<a href="' . $url . '" style="display: none;">"' . $html . '</a>';

        return apply_filters('metaslider_image_coin_slider_markup', $html, $slide, $this->settings);
    }

    /**
     * Generate responsive slides markup
     * 
     * @return string slide html
     */
    private function get_responsive_slides_markup($slide) {
        $attributes = array(
            'src' => $slide['thumb'],
            'height' => $this->settings['height'],
            'width' => $this->settings['width'],
            'alt' => $slide['alt'],
            'rel' => $slide['rel'],
            'class' => $slide['class']
        );

        $html = $this->build_image_tag($attributes);

        if (strlen($slide['caption'])) {
            $html .= '<div class="caption-wrap"><div class="caption">' . $slide['caption'] . '</div></div>';
        }

        if (strlen($slide['url'])) {
            $html = '<a href="' . $slide['url'] . '" target="' . $slide['target'] . '">'. $html . '</a>';
        }

        return apply_filters('metaslider_image_responsive_slider_markup', $html, $slide, $this->settings);
    }

    /**
     * Save
     */
    protected function save($fields) {
        // update the slide
        wp_update_post(array(
            'ID' => $this->slide->ID,
            'post_excerpt' => $fields['post_excerpt'],
            'menu_order' => $fields['menu_order']
        ));

        // store the URL as a meta field against the attachment
        $this->add_or_update_or_delete_meta($this->slide->ID, 'url', $fields['url']);

        // store the 'new window' setting
        $new_window = isset($fields['new_window']) && $fields['new_window'] == 'on' ? 'true' : 'false';

        $this->add_or_update_or_delete_meta($this->slide->ID, 'new_window', $new_window);
    }
}
?>