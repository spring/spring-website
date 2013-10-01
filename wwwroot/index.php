<?php

    include_once('includes/db.php');
    include_once('includes/bbcode.php');
    include_once('includes/cache.php');
    include_once('includes/thumbs.php');
    include_once('includes/news.php');

    // Prepare newsitems
    $news = get_news();

    // Prepare community headlines
    $cnews = get_community_news();

    // Get a random welcome image
    $sql = '';
    $sql .= 'select a.attach_id ';
    $sql .= 'from phpbb3_attachments as a, phpbb3_topics as t ';
    $sql .= "where t.forum_id = 33 and a.topic_id = t.topic_id ";
    $sql .= "and (extension = 'gif' or extension = 'jpg' or extension = 'jpeg' or extension = 'png')";
    $sql .= 'order by rand() limit 1';
    $res = mysql_query($sql);
    $row = mysql_fetch_array($res);
    $welcome = '/screenshot.php?id=' . $row['attach_id'];

    // Fetch 4 random screenshots
    $sql = '';
    $sql .= 'select a.attach_id, physical_filename, topic_title ';
    $sql .= 'from phpbb3_attachments as a, phpbb3_topics as t ';
    $sql .= "where t.forum_id = 35 and a.topic_id = t.topic_id ";
    $sql .= "and (extension = 'gif' or extension = 'jpg' or extension = 'jpeg' or extension = 'png')";
    $sql .= 'order by rand() limit 4';

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
            $imgline = '<a href="screenshot.php?id=' . $screen['attach_id'] . '" rel="lytebox[fpscreens]" title="' . $title . '">';
            $imgline .= '<img src="' . $thumb . '" width="142" height="80" border="0" alt="" /><br /></a>';
            $screenthumbs[] = $imgline;
        }
    }
    else {
        // Not enough.. Should not usually happen.
    }


    $videos = array(
			"http://www.youtube.com/embed/vkZaLLyhEgI?rel=0", #zero-k trailer
			"http://www.youtube.com/embed/2mKhQD2SVqw?rel=0", #spring rts trailer
			"http://www.youtube.com/embed/GAM_vcVJiL4?rel=0", #spring showcase
			"http://www.youtube.com/embed/e0R2QsMwc98?rel=0", #NOTA trailer
			"http://www.youtube.com/embed/3F7F7NGDDFU?rel=0", #Evolution RTS trailer
			"http://www.youtube.com/embed/vuP63IobLps?rel=0", #NOTA "Action Trailer"
		);
    $videofile = $videos[array_rand($videos)];

    // Compose the frontpage
    $fptemplate = file_get_contents('templates/frontpage.html');
    $fpkeys = array('#NEWSITEMS#', '#CNEWSITEMS#', '#WELCOME#', '#SCREEN1#', '#SCREEN2#', '#SCREEN3#', '#SCREEN4#', '#VIDEOFILE#');
    $fpitems = array($news, $cnews, $welcome, $screenthumbs[0], $screenthumbs[1], $screenthumbs[2], $screenthumbs[3], $videofile);
    $fp = str_replace($fpkeys, $fpitems, $fptemplate);

    // Compose the final page
    $headertemplate = file_get_contents('templates/header.html');
    $starttemplate = file_get_contents('templates/pagestart.html');

    $html = $starttemplate;
    $html .= str_replace('{PAGE_TITLE}', '<img src="/images/homie.gif" width="11" height="10" border="0" alt=""/>&nbsp;Home', $headertemplate);
    $html .= $fp;
    $html .= file_get_contents('templates/footer.html');
    $html .= file_get_contents('templates/pageend.html');

    print($html);
?>
