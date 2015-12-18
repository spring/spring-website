<?php

// Redirects incoming requests to the servers defined here
$servers[] = array("url" => "https://springrts.com/dl/installer/");
$servers[] = array("url" => "http://springmirror.jonanin.com/installer/");
//  $servers[] = array("url" => "http://64.86.28.46/spring/installer/");


// Server overloaded
//$servers[] = array("url" => "http://evolutionrts.info/installermirror/");
//$servers[] = array("url" => "http://tasclient.it-l.eu/installer/");

if ( !array_key_exists('file', $_GET ) ) {
	die('Need to provide a filename!');
}
$filename = $_GET['file'];
$server = $servers[ rand( 0, count( $servers ) - 1 )];

$url = $server["url"] . $filename;
header("Location: ".$url);
exit();
