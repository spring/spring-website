<?php
/**
 * Helper class for resizing images, returning the correct URL to the image etc
 */
class MetaSliderImageHelper {

    private $smart_crop = 'false';
    private $container_width; // slideshow width
    private $container_height; // slideshow height
    private $id; // slide/attachment ID
    private $url;
    private $path; // path to attachment on server

    /**
     * Constructor
     */
    public function __construct($slide_id, $width, $height, $smart_crop) {
        $this->id = $slide_id;

        $upload_dir = wp_upload_dir();
        $this->url = $upload_dir['baseurl'] . "/" . get_post_meta($slide_id, '_wp_attached_file', true);

        $this->container_width = $width;
        $this->container_height = $height;
        $this->smart_crop = $smart_crop;
    }

    /**
     * Return the crop dimensions.
     * 
     * Smart Crop: If the image is smaller than the container width or height, then return
     * dimensions that respect the container size ratio. This ensures image displays in a 
     * sane manner in responsive sliders
     * 
     * @return array image dimensions
     */
    private function get_crop_dimensions($image_width, $image_height) {
        if ($this->smart_crop == 'false') {
            return array('width' => (int)$this->container_width, 'height' => (int)$this->container_height);
        }

        $container_width = $this->container_width;
        $container_height = $this->container_height;

        /**
         * Slideshow Width == Slide Width
         */
        if ($image_width == $container_width && $image_height == $container_height) {
            $new_slide_width = $container_width;
            $new_slide_height = $container_height;
        }

        if ($image_width == $container_width && $image_height < $container_height) {
            $new_slide_height = $image_height;
            $new_slide_width = $container_width / ($container_height / $image_height);
        }

        if ($image_width == $container_width && $image_height > $container_height) {
            $new_slide_width = $container_width;
            $new_slide_height = $container_height;
        }

        /**
         * Slideshow Width < Slide Width
         */
        if ($image_width < $container_width && $image_height == $container_height) {
            $new_slide_width = $image_width;
            $new_slide_height = $image_height / ($container_width / $image_width);
        }

        /**
         * Slide is smaller than slidehow - both width and height
         */
        if ($image_width < $container_width && $image_height < $container_height) {
            if ($container_width > $container_height) {
                // wide

                if ($image_width > $image_height) {
                    // wide
                    $new_slide_height = $image_height;
                    $new_slide_width = $container_width / ($container_height / $image_height);

                    if ($new_slide_width > $image_width) {
                        $new_slide_width = $image_width;
                        $new_slide_height = $container_height / ($container_width / $image_width);
                    }
                } else {
                    // tall
                    $new_slide_width = $image_width;
                    $new_slide_height = $container_height / ($container_width / $image_width);

                    if ($new_slide_height > $image_height) {
                        $new_slide_height = $image_height;
                        $new_slide_width = $container_width / ($container_height / $image_height);
                    }
                }
            } else {
                // tall
                if ($image_width > $image_height) {
                    // wide
                    $new_slide_height = $image_height;
                    $new_slide_width = $container_width / ($container_height / $image_height);

                    if ($new_slide_width > $image_width) {
                        $new_slide_width = $image_width;
                        $new_slide_height = $container_height / ($container_width / $image_width);
                    }
                } else {
                    // tall
                    $new_slide_width = $image_width;
                    $new_slide_height = $container_height / ($container_width / $image_width);

                    if ($new_slide_height > $image_height) {
                        $new_slide_height = $image_height;
                        $new_slide_width = $container_width / ($container_height / $image_height);
                    }
                }
            }
        }

        if ($image_width < $container_width && $image_height > $container_height) {
            $new_slide_width = $image_width;
            $new_slide_height = $container_height / ($container_width / $image_width);
        }

        /**
         * Slideshow Width > Slide Width
         */
        if ($image_width > $container_width && $image_height == $container_height) {
            $new_slide_width = $container_width;
            $new_slide_height = $container_height;
        }

        if ($image_width > $container_width && $image_height < $container_height) {
            $new_slide_height = $image_height;
            $new_slide_width = $container_width / ($container_height / $image_height);
        }

        if ($image_width > $container_width && $image_height > $container_height) {
            $new_slide_width = $container_width;
            $new_slide_height = $container_height;
        }

        return array('width' => floor($new_slide_width), 'height' => floor($new_slide_height));
    }

    /**
     * Return the image URL, crop the image to the correct dimensions if required
     * 
     * @return string resized image URL
     */
    function get_image_url() {
        // Get the image file path
        $file_path = get_attached_file($this->id);

        // load image
        $image = wp_get_image_editor($file_path);

        // editor will return an error if the path is invalid
        if (is_wp_error($image)) {
            if (is_admin()) {
                echo '<div id="message" class="error">';
                echo "<p><strong>ERROR</strong> " . $image->get_error_message() . " Check <a href='http://codex.wordpress.org/Changing_File_Permissions' target='_blank'>file permissions</a></p>";
                echo "<button class='toggle'>Show Details</button>";
                echo "<div class='message' style='display: none;'><br />Slide ID: {$this->id}<pre>";
                var_dump($image); 
                echo "</pre></div>";
                echo "</div>";
            }
            
            return $this->url;
        }

        // get the original image size
        $size = $image->get_size();
        $orig_width = $size['width'];
        $orig_height = $size['height'];

        // get the crop size
        $size = $this->get_crop_dimensions($orig_width, $orig_height);
        $dest_width = $size['width'];
        $dest_height = $size['height'];

        // check if a resize is needed
        if ($dest_width == $orig_width && $dest_height == $orig_height) {
            return $this->url;
        }

        // image info
        $info = pathinfo( $file_path );
        $dir = $info['dirname'];
        $ext = $info['extension'];
        $name = wp_basename($file_path, ".$ext");
        $dest_file_name = "{$dir}/{$name}-{$dest_width}x{$dest_height}.{$ext}";

        // URL to destination file
        $url = str_replace(basename($this->url), basename($dest_file_name), $this->url);

        // crop needed
        if (!file_exists($dest_file_name)) {
            $dims = image_resize_dimensions($orig_width, $orig_height, $dest_width, $dest_height, true);
            
            if ($dims) {
                list($dst_x, $dst_y, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h) = $dims;
                $image->crop($src_x, $src_y, $src_w, $src_h, $dst_w, $dst_h);
            }

            $saved = $image->save($dest_file_name);

            // Record the new size so that the file is correctly removed when the media file is deleted.
            $backup_sizes = get_post_meta($this->id,'_wp_attachment_backup_sizes',true);

            if (!is_array($backup_sizes)) {
                $backup_sizes = array();
            }

            $backup_sizes["resized-{$dest_width}x{$dest_height}"] = $saved;
            update_post_meta($this->id,'_wp_attachment_backup_sizes', $backup_sizes);

            $url = str_replace(basename($this->url), basename($saved['path']), $this->url);
        }

        return $url;
    }
}
?>