<?php

/**
 * Generate a thumbnail of a given file from the phpbb directory
 * 
 * @return string the path of the file, images/screen1.jpg on failure
 */
function get_thumbnail( $name, $xsize, $ysize ) {
    $thumbname = 'thumbs/' . $name . '_thumb_' . $xsize . '_' . $ysize . '.jpg';
    $fname = 'phpbb/files/' . $name;
    
    // check if we can actually read the file
    if ( !is_readable( $fname ) ) {
        // we can't continue if we can't read the file
        return 'images/screen1.jpg';
    }

    // Ignore the race condition, should only cause unnecessary work to be done at times
    if ( !file_exists( $thumbname ) ) {

        // Try to open it up with GD
        $image = @imagecreatefromjpeg( $fname );
        if ( !$image ) {
            $image = @imagecreatefrompng( $fname );
        }
        if ( !$image ) {
            $image = @imagecreatefromgif( $fname );
        }

        // If we can't do it then this will have to suffice
        if ( !$image ) {
            return 'images/screen1.jpg';
        }

        $srcw = imagesx($image);
        $srch = imagesy($image);

        $clipx = 0;
        $clipy = 0;
        $clipw = $srcw;
        $cliph = $srch;
        if ( $xsize < ( $srcw * 0.5 ) ) {
            $maxclip = 0.8;
            $s = max($xsize / $srcw - $maxclip, 0.0) / (1.0 - $maxclip);
            $scale = $maxclip + (1.0 - $maxclip) * $s;
            $clipx = ($srcw * (1.0 - $scale)) / 2.0;
            $clipw = $srcw - ($clipx * 2);
        }
        if ( $ysize < ( $srch * 0.5 ) ) {
            $maxclip = 0.8;
            $s = max( $ysize / $srch - $maxclip, 0.0 ) / (1.0 - $maxclip);
            $scale = $maxclip + (1.0 - $maxclip ) * $s;
            $clipy = ( $srch * (1.0 - $scale ) ) / 2.0;
            $cliph = $srch - ( $clipy * 2 );
        }

        // imagecopyresampled assumes images are at gamma 1.0, while they probably are at 2.2 (sRGB)
        // details and examples: http://www.4p8.com/eric.brasseur/gamma.html 
        imagegammacorrect($image, 2.2, 1.0);
        $imageout = imagecreatetruecolor($xsize, $ysize);
        imagecopyresampled($imageout, $image, 0, 0, $clipx, $clipy, $xsize, $ysize, $clipw, $cliph);
        imagegammacorrect($imageout, 1.0, 2.2);
        imagejpeg($imageout, $thumbname);
    }

    return $thumbname;
}
