<?php

    include_once('includes/db.php');

    $category = 32;

    // We'd like the image to only be up for rotation every 2 minutes
    $time_now = gmmktime();
    $time_now -= ($time_now % 120);
    header("Etag: " . $time_now);
    
    if (array_key_exists('HTTP_IF_NONE_MATCH', $_SERVER)) {
        $timestamp = (int)$_SERVER['HTTP_IF_NONE_MATCH'];
        if ($timestamp + 120 > $time_now) {
            header("HTTP/1.1 304 Not Modified");
            exit();            
        }
    }    
            
    // Find suitable images to rotate
    $sql = '';
    $sql .= 'select physical_filename, real_filename, topic_title ';
    $sql .= 'from phpbb3_attachments as a, phpbb3_topics as t ';
    $sql .= "where t.forum_id = $category and a.topic_id = t.topic_id ";
    $sql .= 'order by rand() limit 1';

    $res = mysql_query($sql);
    if (mysql_num_rows($res) != 1)
        exit();
    $row = mysql_fetch_array($res);
        
    $fname = 'phpbb/files/' . $row['physical_filename'];    
    
    $picname = $row['real_filename'];
    $picext_array = explode('.', strtolower($picname));
    $picext = $picext_array[count($picext_array) - 1];
        
    $mimetype = 'image/png';
    if ($picext == 'png')    
        $mimetype = 'image/png';
    elseif (($picext == 'jpg') || ($picext == 'jpeg'))
        $mimetype = 'image/jpeg';
    elseif ($picext == 'gif')
        $mimetype = 'image/gif';
            
    header("Content-Type: $mimetype");  
    header('Content-Length: ' . filesize($fname));       
    readfile($fname);
    exit();
?>
