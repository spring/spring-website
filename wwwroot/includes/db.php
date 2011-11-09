<?php

    include_once('../springpw.php');

    $db = mysql_connect($spring_dbhost, $spring_dbuser, $spring_dbpass);
    mysql_select_db($spring_dbname);

?>
