<?php

    // Should probably use some nice bbcode library
    function parse_bbcode($str)
    {
	$keys = array();
	$data = array();
	foreach (array("r", "s", "e", "S", "E", "LI", "LIST") as $rep) {
		$keys[] = "/<$rep>/";
		$data[] = "";

		$keys[] = "/<\/$rep>/";
		$data[] = "";
	}

	foreach (array("URL", "IMG") as $rep) {
		$keys[] = '/<'.$rep.' \w+=".*">/';
		$data[] = "";

		$keys[] = "/<\/$rep>/";
		$data[] = "";
	}
	$keys[] = "/\r/";
	$data[] = "";

	$keys[] = "/\n/";
	$data[] = "";

        $str = preg_replace($keys, $data, $str);

        $keys = array('/{SMILIES_PATH}/',
                      '/\[b]/',
                      '/\[\/b]/',
                      '/\[u:\w*\]/',
                      '/\[\/u:\w*\]/',
                      '/\[s\]/',
                      '/\[\/s\]/',
                      '/\[i:\w*\]/',
                      '/\[\/i:\w*\]/',
                      '/\[url]/',
                      '/\[url=(.*?)\]/',
                      '/\[\/url\]/',
                      '/\[list]\n?/',
                      '/\[\*\]\n?/',
                      '/\[\/\*[:\w]*\]\n?/',
                      '/\[\/list[:\w]*\]\n?/',
                      '/\[img\]/',
                      '/\[\/img\]/',
                      '/\[size=(.*?):\w*\]/',
                      '/\[\/size:\w*\]/',
                      '/\[color=([^\]]+)\]/',
                      '/\[\/color\]/',
                      '/\n/',
                      '/\[code\]/',
                      '/\[\/code\]/',
                      );
        $data = array('/phpbb/images/smilies',
                      '<b>',
                      '</b>',
                      '<u>',
                      '</u>',
                      '<span style="text-decoration:line-through">',
                      '</span>',
                      '<i>',
                      '</i>',
                      '<a href="$1">$1</a>',
                      '<a href="$1">',
                      '</a>',
                      '<ul>',
                      '<li>',
                      '</li>',
                      '</ul>',
                      '<img alt="" src="',
                      '" />',
                      '',
                      '',
                      '<span style="color:$1">',
                      '</span>',
                      '<br />',
                      '<div class="codebox">',
                      '</div>',
                      );
        return preg_replace($keys, $data, $str);
    }

