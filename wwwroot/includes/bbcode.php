<?php

    // Should probably use some nice bbcode library
    function parse_bbcode($str)
    {
        $keys = array('/{SMILIES_PATH}/',
                      '/\[b:\w*\]/',
                      '/\[\/b:\w*\]/',
                      '/\[i:\w*\]/',
                      '/\[\/i:\w*\]/',
                      '/\[url:\w*\](.*?)\[\/url:\w*\]/',
                      '/\[url=(.*?):\w*\]/',
                      '/\[\/url:\w*\]/',
                      '/\[list:\w*\]\n?/',
                      '/\[\*:\w*\]\n?/',
                      '/\[\/\*[:\w]*\]\n?/',
                      '/\[\/list[:\w]*\]\n?/',
                      '/\[img:\w*\]/',
                      '/\[\/img:\w*\]/',
                      '/\[size=(.*?):\w*\]/',
                      '/\[\/size:\w*\]/',
                      '/\[color=(.*?):\w*\]/',
                      '/\[\/color:\w*\]/',
                      '/\n/',
                      );
        $data = array('/phpbb/images/smilies',
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
                      '<br />',
                      );
        return preg_replace($keys, $data, $str);
    }

?>
