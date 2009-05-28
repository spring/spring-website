<?php

    // Should probably use some nice bbcode library
    function parse_bbcode($str)
    {
        $keys = array('/{SMILIES_PATH}/', 
                      "/\n/",
                      '/\[b:\w*\]/',
                      '/\[\/b:\w*\]/',
                      '/\[i:\w*\]/',
                      '/\[\/i:\w*\]/',
                      '/\[url:\w*\](.*?)\[\/url:\w*\]/',
                      '/\[url=(.*?):\w*\]/',
                      '/\[\/url:\w*\]/',
                      '/\[list:\w*\]/',
                      '/\[\*:\w*\]/',
                      '/\[\/\*[:\w]*\]/',
                      '/\[\/list[:\w]*\]/',
                      '/\[img:\w*\]/',
                      '/\[\/img:\w*\]/',
                      '/\[size=(.*?):\w*\]/',
                      '/\[\/size:\w*\]/',
                      '/\[color=(.*?):\w*\]/',
                      '/\[\/color:\w*\]/',
                      );
        $data = array('http://spring.clan-sy.com/phpbb/images/smilies',
                      '<br />',
                      '<b>',
                      '</b>',
                      '<i>',
                      '</i>',
                      '<a href="$1">$1</a>',
                      '<a href="$1">',
                      '</a>',
                      '<ul>',
                      '<li>',
                      '</li>',
                      '</ul>',
                      '<img src="',
                      '" />',
                      '',
                      '',
                      '',
                      '',
                      );
        return preg_replace($keys, $data, $str);
    }

?>
