<?php

    include_once('../springpw.php');

    $db = mysql_connect("localhost", $spring_dbuser, $spring_dbpass);
    mysql_select_db($spring_dbname);
  
?>
