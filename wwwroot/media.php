<?php

    include_once('includes/db.php');
    include_once('includes/thumbs.php');
        
    // Fetch all screenshots
    $sql = '';
    $sql .= 'select physical_filename, real_filename, topic_title, t.topic_id ';
    $sql .= 'from phpbb3_attachments as a, phpbb3_topics as t ';
    $sql .= "where t.forum_id = 35 and a.topic_id = t.topic_id ";
    $sql .= 'order by topic_time desc';

    $res = mysql_query($sql);    
    $media = '';
    $count = 0;
    while ($row = mysql_fetch_array($res)) {
        if ($count % 5 == 0)
            $media .= '<tr>';
        $media .= '<td>';
        
        $title = $row['topic_title'];
        $thumb = get_thumbnail($row['physical_filename'], 142, 80);
        $media .= '<a href="screenshot.php?topic=' . $row['topic_id'] . '" rel="lytebox[fpscreens]" title="' . $title . '">';
        $media .= '<img src="' . $thumb . '" width="142" height="80" border="0"><br /></a>';
                
        $media .= '</td>';
        if ($count % 5 == 4)
            $media .= '</tr>';

        $count++;
    }
                
    // Compose the page
    $fptemplate = file_get_contents('templates/media.html');
    $fp = str_replace('#MEDIA#', $media, $fptemplate);
        
    // Compose the final page
    $headertemplate = file_get_contents('templates/header.html');
    $starttemplate = file_get_contents('templates/pagestart.html');
    
    $html = $starttemplate;
    $html .= str_replace('{PAGE_TITLE}', 'Media', $headertemplate);
    $html .= $fp;
    $html .= file_get_contents('templates/footer.html');    
    $html .= file_get_contents('templates/pageend.html');    
    
    print($html);
?>
