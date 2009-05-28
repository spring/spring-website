<?php

    include_once('includes/db.php');
    
    $topic = (int)$_GET['topic'];    
    $type = (int)$_GET['video'];
    $id = (int)$_GET['id'];
            
    // Force the topic id to belong to the screenshots, videos or welcome image forum
    $sql = '';
    $sql .= 'select physical_filename, topic_title, extension ';
    $sql .= 'from phpbb3_attachments as a, phpbb3_topics as t ';
    $sql .= "where (t.forum_id = 35 or t.forum_id = 34 or t.forum_id = 33) and a.topic_id = t.topic_id ";
    if ($topic > 0)
        $sql .= "and a.topic_id = $topic ";
    elseif ($id > 0)
        $sql .= "and a.attach_id = $id ";
    else
        die('no id or topic');
        
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
  
    // Perhaps the browser already has the image  
    $last_modified_time = filemtime($fname);
    $etag = md5($fname . $last_modified_time . $topic . $type);

    header("Last-Modified: " . gmdate("D, d M Y H:i:s", $last_modified_time) . " GMT");
    header("Etag: $etag");

    if (@strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) == $last_modified_time ||
        trim($_SERVER['HTTP_IF_NONE_MATCH']) == $etag) 
    {
        header("HTTP/1.1 304 Not Modified");
        exit();
    }     
    
    // Ok then, transfer it!
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
