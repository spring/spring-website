<?php

    // Should probably use some nice bbcode library
    function parse_bbcode($str)
    {
        $keys = array('{SMILIES_PATH}', "\n");
        $data = array('http://spring.clan-sy.com/phpbb/images/smilies', '<br />');
        return str_replace($keys, $data, $str);
    }
  
?>
