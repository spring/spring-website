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
                
            $imageout = imagecreatetruecolor($xsize, $ysize);
            imagecopyresampled($imageout, $image, 0, 0, 0, 0, $xsize, $ysize, imagesx($image), imagesy($image));
            imagejpeg($imageout, $thumbname);
        }
                
        return $thumbname;
    }
  
?>
