<?php

    // Generates a thumbnail of the given file from the phpbb files dir, returning the name
    function get_thumbnail($name, $xsize, $ysize)
    {
        $thumbname = 'thumbs/' . $name . '_thumb_' . $xsize . '_' . $ysize . '.jpg';
        $fname = 'phpbb/files/' . $name;

        // Ignore the race condition, should only cause unnecessary work to be done at times
        if (!file_exists($thumbname)) {

            // Try to open it up with GD
            $image = @imagecreatefromjpeg($fname);
            if (!$image)
                $image = @imagecreatefrompng($fname);
            if (!$image)
                $image = @imagecreatefromgif($fname);

            // If we can't do it then this will have to suffice
            if (!$image)
                return 'images/screen1.jpg';

            // imagecopyresampled assumes images are at gamma 1.0, while they probably are at 2.2 (sRGB)
            // details and examples: http://www.4p8.com/eric.brasseur/gamma.html 
            imagegammacorrect($image, 2.2, 1.0);
            $imageout = imagecreatetruecolor($xsize, $ysize);
            imagecopyresampled($imageout, $image, 0, 0, 0, 0, $xsize, $ysize, imagesx($image), imagesy($image));
            imagegammacorrect($imageout, 1.0, 2.2);
            imagejpeg($imageout, $thumbname);
        }

        return $thumbname;
    }
  
?>
