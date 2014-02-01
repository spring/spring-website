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
        add_action('wp_ajax_resize_image_slide', array($this, 'ajax_resize_slide'));
    }

    /**
     * Create a new slide and echo the admin HTML
     */
    public function ajax_create_slide() {
        // security check
        if (!wp_verify_nonce($_REQUEST['_wpnonce'], 'metaslider_addslide')) {
            echo "<tr><td colspan='2'>" . __("Security check failed. Refresh page and try again.", 'metaslider') . "</td></tr>";
            die();
        }

        $slider_id = intval($_POST['slider_id']);
        $selection = $_POST['selection'];

        if (is_array($selection) && count($selection) && $slider_id > 0) {
            foreach ($selection as $slide_id) {
                $this->set_slide($slide_id);
                $this->set_slider($slider_id);

                if ($this->slide_exists_in_slideshow($slider_id, $slide_id)) {
                    echo "<tr><td colspan='2'>ID: {$slide_id} \"" . get_the_title($slide_id) . "\" - " . __("Failed to add slide. Slide already exists in slideshow.", 'metaslider') . "</td></tr>";
                } else if (!$this->slide_is_unassigned_or_image_slide($slider_id, $slide_id)) {
                    echo "<tr><td colspan='2'>ID: {$slide_id} \"" . get_the_title($slide_id) . "\" - " . __("Failed to add slide. Slide is not of type 'image'.", 'metaslider') . "</td></tr>";
                }else {
                    $this->tag_slide_to_slider();
                    $this->add_or_update_or_delete_meta($slide_id, 'type', 'image');

                    // override the width and height to kick off the AJAX image resizing on save
                    $this->settings['width'] = 0;
                    $this->settings['height'] = 0;

                    echo $this->get_admin_slide();                   
                }
            }
        }

        die();
    }

    /**
     * Create a new slide and echo the admin HTML
     */
    public function ajax_resize_slide() {
        check_admin_referer('metaslider_resize');

        $slider_id = intval($_POST['slider_id']);
        $slide_id = intval($_POST['slide_id']);

        $this->set_slide($slide_id);
        $this->set_slider($slider_id);

        $settings = get_post_meta($slider_id, 'ml-slider_settings', true);

        // create a copy of the correct sized image
        $imageHelper = new MetaSliderImageHelper(
            $slide_id,
            $settings['width'], 
            $settings['height'], 
            isset($settings['smartCrop']) ? $settings['smartCrop'] : 'false',
            $this->use_wp_image_editor()
        );

        $url = $imageHelper->get_image_url();

        echo $url . " (" . $settings['width'] . 'x' . $settings['height'] . ")";

        die();
    }

    /**
     * Return the HTML used to display this slide in the admin screen
     * 
     * @return string slide html
     */
    protected function get_admin_slide() {
        // get some slide settings
        $imageHelper = new MetaSliderImageHelper($this->slide->ID, 150, 150, 'false', $this->use_wp_image_editor());
        $thumb       = $imageHelper->get_image_url();
        $url         = get_post_meta($this->slide->ID, 'ml-slider_url', true);
        $title       = get_post_meta($this->slide->ID, 'ml-slider_title', true);
        $alt         = get_post_meta($this->slide->ID, '_wp_attachment_image_alt', true);
        $target      = get_post_meta($this->slide->ID, 'ml-slider_new_window', true) ? 'checked=checked' : '';
        $caption     = htmlentities($this->slide->post_excerpt, ENT_QUOTES, 'UTF-8');

        // localisation
        $str_caption    = __("Caption", 'metaslider');
        $str_new_window = __("New Window", 'metaslider');
        $str_url        = __("URL", 'metaslider');

        // slide row HTML
        $row  = "<tr class='slide image flex responsive nivo coin'>";
        $row .= "    <td class='col-1'>";
        $row .= "        <div class='thumb' style='background-image: url({$thumb})'>";
        $row .= "            <a class='delete-slide confirm' href='?page=metaslider&amp;id={$this->slider->ID}&amp;deleteSlide={$this->slide->ID}'>x</a>";
        $row .= "            <span class='slide-details'>" . __("Image Slide", "metaslider") . "</span>";
        $row .= "        </div>";
        $row .= "    </td>";
        $row .= "    <td class='col-2'>";
        $row .= "        <ul class='tabs'>";
        $row .= "            <li class='selected' rel='tab-1'>" . __("General", "metaslider") . "</li>";
        $row .= "            <li rel='tab-2'>" . __("SEO", "metaslider") . "</li>";
        $row .= "        </ul>";
        $row .= "        <div class='tabs-content'>"; 
        $row .= "            <div class='tab tab-1'>";
        if (!$this->is_valid_image()) {
            $row .= "<div class='warning'>" . __('Warning: Image data does not exist. Please re-upload the image.') . "</div>";
        }
        $row .= "                <textarea name='attachment[{$this->slide->ID}][post_excerpt]' placeholder='{$str_caption}'>{$caption}</textarea>";
        $row .= "                <input class='url' type='text' name='attachment[{$this->slide->ID}][url]' placeholder='{$str_url}' value='{$url}' />";
        $row .= "                <div class='new_window'>";
        $row .= "                    <label>{$str_new_window}<input type='checkbox' name='attachment[{$this->slide->ID}][new_window]' {$target} /></label>";
        $row .= "                </div>";
        $row .= "            </div>";
        $row .= "            <div class='tab tab-2' style='display: none;'>";
        $row .= "                <div class='row'><label>" . __("Image Title Text", "metaslider") . "</label></div>";
        $row .= "                <div class='row'><input type='text' size='50' name='attachment[{$this->slide->ID}][title]' value='{$title}' /></div>";
        $row .= "                <div class='row'><label>" . __("Image Alt Text", "metaslider") . "</label></div>";
        $row .= "                <div class='row'><input type='text' size='50' name='attachment[{$this->slide->ID}][alt]' value='{$alt}' /></div>";
        $row .= "            </div>";
        $row .= "        </div>";
        $row .= "        <input type='hidden' name='attachment[{$this->slide->ID}][type]' value='image' />";
        $row .= "        <input type='hidden' class='menu_order' name='attachment[{$this->slide->ID}][menu_order]' value='{$this->slide->menu_order}' />";
        $row .= "        <input type='hidden' name='resize_slide_id' data-slide_id='{$this->slide->ID}' data-width='{$this->settings['width']}' data-height='{$this->settings['height']}' />";
        $row .= "    </td>";
        $row .= "</tr>";

        return $row;
    }

    /**
     * Check to see if metadata exists for this image. Assume the image is
     * valid if metadata and a size exists for it (generated during initial
     * upload to WordPress).
     *
     * @return bool, true if metadata and size exists.
     */
    public function is_valid_image() {
        $meta = wp_get_attachment_metadata($this->slide->ID);
        return isset($meta['width'], $meta['height']);
    }

    /**
     * Disable/enable image editor
     *
     * @return bool
     */
    public function use_wp_image_editor() {
        return apply_filters('metaslider_use_image_editor', $this->is_valid_image());
    }

    /**
     * Returns the HTML for the public slide
     * 
     * @return string slide html
     */
    protected function get_public_slide() {
        // get the image url (and handle cropping)
        // disable wp_image_editor if metadata does not exist for the slide
        $imageHelper = new MetaSliderImageHelper(
            $this->slide->ID,
            $this->settings['width'], 
            $this->settings['height'], 
            isset($this->settings['smartCrop']) ? $this->settings['smartCrop'] : 'false',
            $this->use_wp_image_editor()
        );

        $thumb = $imageHelper->get_image_url();

        // store the slide details
        $slide = array(
            'id' => $this->slide->ID,
            'url' => __(get_post_meta($this->slide->ID, 'ml-slider_url', true)),
            'title' => __(get_post_meta($this->slide->ID, 'ml-slider_title', true)),
            'target' => get_post_meta($this->slide->ID, 'ml-slider_new_window', true) ? '_blank' : '_self', 
            'src' => $thumb,
            'thumb' => $thumb, // backwards compatibility with Vantage
            'width' => $this->settings['width'],
            'height' => $this->settings['height'],
            'alt' => __(get_post_meta($this->slide->ID, '_wp_attachment_image_alt', true)),
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
     * Generate nivo slider markup
     * 
     * @return string slide html
     */
    private function get_nivo_slider_markup($slide) {
        $attributes = apply_filters('metaslider_nivo_slider_image_attributes', array(
            'src' => $slide['src'],
            'height' => $slide['height'],
            'width' => $slide['width'],
            'data-title' => htmlentities($slide['caption_raw'], ENT_QUOTES, 'UTF-8'),
            'data-thumb' => $slide['data-thumb'],
            'title' => $slide['title'],
            'alt' => $slide['alt'],
            'rel' => $slide['rel'],
            'class' => $slide['class']
        ), $slide, $this->slider->ID);

        $html = $this->build_image_tag($attributes);

        $anchor_attributes = apply_filters('metaslider_nivo_slider_anchor_attributes', array(
            'href' => $slide['url'],
            'target' => $slide['target']
        ), $slide, $this->slider->ID);

        if (strlen($anchor_attributes['href'])) {
            $html = $this->build_anchor_tag($anchor_attributes, $html);
        }

        return apply_filters('metaslider_image_nivo_slider_markup', $html, $slide, $this->settings);
    }

    /**
     * Generate flex slider markup
     * 
     * @return string slide html
     */
    private function get_flex_slider_markup($slide) {
        $attributes = apply_filters('metaslider_flex_slider_image_attributes', array(
            'src' => $slide['src'],
            'height' => $slide['height'],
            'width' => $slide['width'],
            'alt' => $slide['alt'],
            'rel' => $slide['rel'],
            'class' => $slide['class'],
            'title' => $slide['title']
        ), $slide, $this->slider->ID);

        $html = $this->build_image_tag($attributes);

        $anchor_attributes = apply_filters('metaslider_flex_slider_anchor_attributes', array(
            'href' => $slide['url'],
            'target' => $slide['target']
        ), $slide, $this->slider->ID);

        if (strlen($anchor_attributes['href'])) {
            $html = $this->build_anchor_tag($anchor_attributes, $html);
        }

        // add caption
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
        $attributes = apply_filters('metaslider_coin_slider_image_attributes', array(
            'src' => $slide['src'],
            'height' => $slide['height'],
            'width' => $slide['width'],
            'alt' => $slide['alt'],
            'rel' => $slide['rel'],
            'class' => $slide['class'],
            'title' => $slide['title'],
            'style' => 'display: none;'
        ), $slide, $this->slider->ID);

        $html = $this->build_image_tag($attributes);

        if (strlen($slide['caption'])) {
            $html .= "<span>{$slide['caption']}</span>";
        }

        $attributes = apply_filters('metaslider_coin_slider_anchor_attributes', array(
            'href' => strlen($slide['url']) ? $slide['url'] : 'javascript:void(0)'
        ), $slide, $this->slider->ID);

        $html = $this->build_anchor_tag($attributes, $html);

        return apply_filters('metaslider_image_coin_slider_markup', $html, $slide, $this->settings);
    }

    /**
     * Generate responsive slides markup
     * 
     * @return string slide html
     */
    private function get_responsive_slides_markup($slide) {
        $attributes = apply_filters('metaslider_responsive_slider_image_attributes', array(
            'src' => $slide['src'],
            'height' => $slide['height'],
            'width' => $slide['width'],
            'alt' => $slide['alt'],
            'rel' => $slide['rel'],
            'class' => $slide['class'],
            'title' => $slide['title']
        ), $slide, $this->slider->ID);

        $html = $this->build_image_tag($attributes);

        if (strlen($slide['caption'])) {
            $html .= '<div class="caption-wrap"><div class="caption">' . $slide['caption'] . '</div></div>';
        }

        $anchor_attributes = apply_filters('metaslider_responsive_slider_anchor_attributes', array(
            'href' => $slide['url'],
            'target' => $slide['target']
        ), $slide, $this->slider->ID);

        if (strlen($anchor_attributes['href'])) {
            $html = $this->build_anchor_tag($anchor_attributes, $html);
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

        $this->add_or_update_or_delete_meta($this->slide->ID, 'title', $fields['title']);

        if (isset($fields['alt'])) {
        	update_post_meta($this->slide->ID, '_wp_attachment_image_alt', $fields['alt']);
        }

        // store the 'new window' setting
        $new_window = isset($fields['new_window']) && $fields['new_window'] == 'on' ? 'true' : 'false';

        $this->add_or_update_or_delete_meta($this->slide->ID, 'new_window', $new_window);
    }
}
?>