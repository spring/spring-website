<?php

    include_once('includes/db.php');

    $topic = (int)$_GET['topic'];    
    $type = (int)$_GET['video'];
            
    // Force the topic id to belong to the screenshots or videos forum
    $sql = '';
    $sql .= 'select physical_filename, real_filename, topic_title, extension ';
    $sql .= 'from phpbb3_attachments as a, phpbb3_topics as t ';
    $sql .= "where (t.forum_id = 35 or t.forum_id = 34) and a.topic_id = t.topic_id and a.topic_id = $topic ";
    if (!$type)
        $sql .= "and (extension = 'gif' or extension = 'jpg' or extension = 'jpeg' or extension = 'png')";
    else
        $sql .= "and (extension = 'flv')";

    $res = mysql_query($sql);
    if (mysql_num_rows($res) != 1) {
        die('No such screenshot!');
    }
    $row = mysql_fetch_array($res);
    $fname = 'phpbb/files/' . $row['physical_filename'];    
    
    $picext = $row['extension'];
        
    $mimetype = 'image/png';
    if ($picext == 'png')    
        $mimetype = 'image/png';
    elseif (($picext == 'jpg') || ($picext == 'jpeg'))
        $mimetype = 'image/jpeg';
    elseif ($picext == 'gif')
        $mimetype = 'image/gif';
    elseif ($picext == 'flv')
        $mimetype = 'video/x-flv';
            
    header("Content-Type: $mimetype");  
    header('Content-Length: ' . filesize($fname));
    readfile($fname);
    exit();
?>
