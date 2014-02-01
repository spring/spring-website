<?php
/**
 * Slide class represting a single slide. This is extended by type specific 
 * slides (eg, MetaImageSlide, MetaYoutubeSlide (pro only), etc)
 */
class MetaSlide {

    public $slide = 0;
    public $slider = 0;
    public $settings = array(); // slideshow settings

    /**
     * Set the slide
     */
    public function set_slide($id) {
        $this->slide = get_post($id);
    }

    /**
     * Set the slide (that this slide belongs to)
     */
    public function set_slider($id) {
        $this->slider = get_post($id);
        $this->settings = get_post_meta($id, 'ml-slider_settings', true);
    }

    /**
     * Return the HTML for the slide
     * 
     * @return array complete array of slides
     */
    public function get_slide($slide_id, $slider_id) {
        $this->set_slider($slider_id);
        $this->set_slide($slide_id);
        return $this->get_slide_html();
    }

    /**
     * Save the slide
     */
    public function save_slide($slide_id, $slider_id, $fields) {
        $this->set_slider($slider_id);
        $this->set_slide($slide_id);
        $this->save($fields);
    }

    /**
     * Return the correct slide HTML based on whether we're viewing the slides in the 
     * admin panel or on the front end.
     * 
     * @return string slide html
     */
    public function get_slide_html() {
        if (is_admin() && isset($_GET['page']) && $_GET['page'] == 'metaslider-theme-editor') {
            return $this->get_public_slide();
        }
        
        if (is_admin() && !isset($_GET['slider_id'])) {
            return $this->get_admin_slide();
        }

        return $this->get_public_slide();
    }
    
    /**
     *
     */
    public function slide_exists_in_slideshow($slider_id, $slide_id) {
        return has_term("{$slider_id}", 'ml-slider', $slide_id);
    }

    /**
     *
     */
    public function slide_is_unassigned_or_image_slide($slider_id, $slide_id) {
        $type = get_post_meta($slide_id, 'ml-slider_type', true);
        return !strlen($type) || $type == 'image';
    }


    /**
     * Build image HTML
     * 
     * @param array $attributes
     * @return string image HTML
     */
    public function build_image_tag($attributes) {
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
     * Build image HTML
     * 
     * @param array $attributes
     * @return string image HTML
     */
    public function build_anchor_tag($attributes, $content) {
        $html = "<a";

        foreach ($attributes as $att => $val) {
            if (strlen($val)) {
                $html .= " " . $att . '="' . $val . '"';
            }
        }

        $html .= ">" . $content . "</a>";

        return $html;
    }
    /**
     * Tag the slide attachment to the slider tax category
     */
    public function tag_slide_to_slider() {
        if (!term_exists($this->slider->ID, 'ml-slider')) {
            // create the taxonomy term, the term is the ID of the slider itself
            wp_insert_term($this->slider->ID, 'ml-slider');            
        }

        // get the term thats name is the same as the ID of the slider
        $term = get_term_by('name', $this->slider->ID, 'ml-slider');
        // tag this slide to the taxonomy term
        wp_set_post_terms($this->slide->ID, $term->term_id, 'ml-slider', true);

        $this->update_menu_order();
    }

    /**
     * Ensure slides are added to the slideshow in the correct order.
     *
     * Find the highest slide menu_order in the slideshow, increment, then
     * update the new slides menu_order.
     */
    public function update_menu_order() {
        $menu_order = 0;

        // get the slide with the highest menu_order so far
        $args = array(
            'force_no_custom_order' => true,
            'orderby' => 'menu_order',
            'order' => 'DESC',
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'lang' => '', // polylang, ingore language filter
            'suppress_filters' => 1, // wpml, ignore language filter
            'posts_per_page' => 1,
            'tax_query' => array(
                array(
                    'taxonomy' => 'ml-slider',
                    'field' => 'slug',
                    'terms' => $this->slider->ID
                )
            )
        );

        $query = new WP_Query($args);

        while ($query->have_posts()) {
            $query->next_post();
            $menu_order = $query->post->menu_order;
        }

        wp_reset_query();

        // increment
        $menu_order = $menu_order + 1;

        // update the slide
        wp_update_post(array(
            'ID' => $this->slide->ID,
            'menu_order' => $menu_order
        ));
    }

    /**
     * If the meta doesn't exist, add it
     * If the meta exists, but the value is empty, delete it
     * If the meta exists, update it
     */
    public function add_or_update_or_delete_meta($post_id, $name, $value) {
        $key = "ml-slider_" . $name;

        if ($value == 'false' || $value == "" || !$value) {
            if (get_post_meta($post_id, $key)) {
                delete_post_meta($post_id, $key);
            }
        } else {
            if (get_post_meta($post_id, $key)) {
                update_post_meta($post_id, $key, $value);
            } else {
                add_post_meta($post_id, $key, $value, true);
            }
        }
    }

    /**
     * Get the thumbnail for the slide
     */
    public function get_thumb() {
        $imageHelper = new MetaSliderImageHelper($this->slide->ID, 150,150,'false');
        return $imageHelper->get_image_url();
    }
}
?>