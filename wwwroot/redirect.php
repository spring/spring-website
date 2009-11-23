<?php {

 // Redirects incoming requests to the servers defined here
  $servers[] = array("url" => "http://springrts.com/dl/installer/");
  $servers[] = array("url" => "http://springmirror.jonanin.com/installer/");
  $servers[] = array("url" => "http://spring.darkstars.co.uk/installer/");
//  $servers[] = array("url" => "http://64.86.28.46/spring/installer/");


// Server overloaded
 //$servers[] = array("url" => "http://evolutionrts.info/installermirror/");
 //$servers[] = array("url" => "http://tasclient.it-l.eu/installer/");


// UF down:
//  $servers[] = array("url" => "http://server6.unknown-files.net/~lordmatt/");
//  $servers[] = array("url" => "http://buildbot.no-ip.org/~lordmatt/");
// echo $_GET['file'];
// exit();
  if (!array_key_exists('file', $_GET)) 
    die('Need to provide a filename!');

  $filename = $_GET['file'];
  $server = $servers[rand(0, count($servers) - 1)];

  $url = $server["url"] . $filename;
  header("Location: $url");
  exit();

} ?>
