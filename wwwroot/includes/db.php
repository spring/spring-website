<?php

	include_once('../springpw.php');

	$db = mysqli_connect($spring_dbhost, $spring_dbuser, $spring_dbpass, $spring_dbname);
	mysqli_set_charset('utf8'); // we except utf-8 to be returned

	if($db == false){
		$headertemplate = file_get_contents('templates/header.html');
		$starttemplate = file_get_contents('templates/pagestart.html');

		$html = $starttemplate;
		$html .= "<div style=\"width:960px; margin:0 auto; margin-top:40px; text-align:left; display:block; padding:20px; font-family: arial, helvetiva, sans-serif; background:#fff; border-radius:5px; \"><h1>Not to worry</h1><p>The Database connection is down, but we're looking into it. Check back shortly!</p></div>";
		$html .= file_get_contents('templates/pageend.html');
		print($html);
		die();
	}
