<?php

    include_once('../springpw.php');

    $db = mysql_connect($spring_dbhost, $spring_dbuser, $spring_dbpass);
	if($db == false){
		$headertemplate = file_get_contents('templates/header.html');
		$starttemplate = file_get_contents('templates/pagestart.html');

		$html = $starttemplate;
		//$html .= str_replace('{PAGE_TITLE}', '<img src="/images/homie.gif" width="11" height="10" border="0" alt=""/>&nbsp;Home', $headertemplate);
		
		//$html .= file_get_contents('templates/footer.html');
		
		//
		$html .= "<div style=\"width:960px; margin:0 auto; margin-top:40px; text-align:left; display:block; padding:20px; font-family: arial, helvetiva, sans-serif; background:#fff; border-radius:5px; \"><h1>Not to worry</h1><p>The Database connection is down, but we're looking into it. Check back shortly!</p></div>";
		$html .= file_get_contents('templates/pageend.html');
		print($html);
		die();
	}
    mysql_select_db($spring_dbname);

?>
