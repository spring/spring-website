<?php

    include_once('includes/db.php');
    include_once('includes/bbcode.php');
    include_once('includes/thumbs.php');
    
    // Prepare newsitems
    $sql = "";
    $sql .= "SELECT t.topic_id, topic_poster, p.post_text, u.username, u.user_email, t.topic_time, t.topic_replies, t.topic_title ";
    $sql .= "FROM phpbb3_topics AS t, phpbb3_users AS u, phpbb3_posts AS p ";
    $sql .= "WHERE t.forum_id = 2 AND t.topic_poster = u.user_id AND t.topic_id = p.topic_id AND t.topic_time = p.post_time ";
    $sql .= "ORDER  BY t.topic_time DESC ";
    $sql .= "LIMIT 3";    
    
    $res = mysql_query($sql);
    $newstemplate = file_get_contents('templates/newsitem.html');
    $news = "";
    $newskeys = array('#HEADLINE#', '#BODY#', '#POSTER#', '#POSTDATE#', '#COMMENTS#');

    while ($row = mysql_fetch_array($res))
    {
        $newstext = parse_bbcode($row['post_text']);        
        $poster = '<a href="http://spring.clan-sy.com/">' . $row['username'] . '</a>';
        $postdate = date("Y-m-d H:i", $row['topic_time']);
        $comments = '<a href="/phpbb/viewtopic.php?t=' . $row['topic_id'] . '">' . $row['topic_replies'] . ' comments</a>.';
        $newsdata = array($row['topic_title'], $newstext, $poster, $postdate, $comments);
        $news .= str_replace($newskeys, $newsdata, $newstemplate);
    }
    
    // Fetch 4 random screenshots
    $sql = '';
    $sql .= 'select physical_filename, real_filename, topic_title, t.topic_id ';
    $sql .= 'from phpbb3_attachments as a, phpbb3_topics as t ';
    $sql .= "where t.forum_id = 35 and a.topic_id = t.topic_id";

    $res = mysql_query($sql);
    $rowcount = mysql_num_rows($res);
    $screenthumbs = array();
    if ($rowcount >= 4) {
        $screenids = array();
        while (count($screenids) < 4) {
            $proposed = rand(0, $rowcount - 1);
            if (!array_key_exists($proposed, $screenids))
                $screenids[$proposed] = True;
        }
        
        // Retreive details for the 4 selected ones
        $screens = array();
        for ($i = 0; count($screens) < 4; $i++) {
            $row = mysql_fetch_array($res);
            if (array_key_exists($i, $screenids)) {
                $screens[] = $row;
            }
        }
        
        foreach ($screens as $screen) {            
            $thumb = get_thumbnail($screen['physical_filename'], 142, 80);
            $title = $screen['topic_title'];
            //$title .= ' - &lt;a href=&quot;screenshot.php?topic=' . $screen['topic_id'] . '&quot;&gt;Click here to see the original image&lt;/a&gt;';
            $imgline = '<a href="screenshot.php?topic=' . $screen['topic_id'] . '" rel="lytebox[fpscreens]" title="' . $title . '">';
            $imgline .= '<img src="' . $thumb . '" width="142" height="80" border="0"><br /></a>';
            $screenthumbs[] = $imgline;
        }        
    }
    else {
        // Not enough.. Should not usually happen.
    }
    
    // And a random video
    $sql = '';
    $sql .= 'select physical_filename, real_filename, topic_title, t.topic_id, extension ';
    $sql .= 'from phpbb3_attachments as a, phpbb3_topics as t ';
    $sql .= "where t.forum_id = 34 and a.topic_id = t.topic_id and extension = 'flv' ";
    $sql .= 'order by rand() limit 1';
    
    $res = mysql_query($sql);
    if (mysql_num_rows($res) == 1)
    {
        $row = mysql_fetch_array($res);        
        $videofile = '/jwvideo' . $row['topic_id'] . '.flv';
        $videoimage = '/jwimage' . $row['topic_id'] . '.jpg';
    }
    else {
        print('ho!');
    }
    
    //images/video18.flv
    //images/video18.jpg
                
    // Compose the frontpage
    $fptemplate = file_get_contents('templates/frontpage.html');
    $fpkeys = array('#NEWSITEMS#', '#SCREEN1#', '#SCREEN2#', '#SCREEN3#', '#SCREEN4#', '#VIDEOFILE#', '#VIDEOIMAGE#');
    $fpitems = array($news, $screenthumbs[0], $screenthumbs[1], $screenthumbs[2], $screenthumbs[3], $videofile, $videoimage);
    $fp = str_replace($fpkeys, $fpitems, $fptemplate);
        
    // Compose the final page
    $headertemplate = file_get_contents('templates/header.html');
    $starttemplate = file_get_contents('templates/pagestart.html');
    
    $html = $starttemplate;
    $html .= str_replace('{PAGE_TITLE}', '<img src="/images/homie.gif" width="11" height="10" border="0"/>&nbsp;Home', $headertemplate);
    $html .= $fp;
    $html .= file_get_contents('templates/footer.html');    
    $html .= file_get_contents('templates/pageend.html');    
    
    print($html);
?>
