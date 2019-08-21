<?php

include_once('includes/db.php');
include_once('includes/bbcode.php');
include_once('includes/cache.php');
include_once('includes/thumbs.php');
include_once('includes/news.php');

// Prepare newsitems
$news = get_news($db);

// Get a random welcome image
$sql = 'select a.attach_id ';
$sql .= 'from phpbb3_attachments as a, phpbb3_topics as t ';
$sql .= "where t.forum_id = 33 and a.topic_id = t.topic_id ";
$sql .= "and (extension = 'gif' or extension = 'jpg' or extension = 'jpeg' or extension = 'png')";
$sql .= 'order by rand() limit 1';
$res = mysqli_query($db, $sql);
$row = mysqli_fetch_array($res);
$welcome = '/screenshot.php?id=' . intval( $row['attach_id'] );

// Fetch 4 random screenshots
$sql = 'select a.attach_id, physical_filename, topic_title ';
$sql .= 'from phpbb3_attachments as a, phpbb3_topics as t ';
$sql .= "where t.forum_id = 35 and a.topic_id = t.topic_id ";
$sql .= "and (extension = 'gif' or extension = 'jpg' or extension = 'jpeg' or extension = 'png')";
$sql .= 'order by rand() limit 4';

$res = mysqli_query($db, $sql);
$rowcount = mysqli_num_rows($res);
$screenthumbs = array();
if ( $rowcount >= 4 ) {
    $screenids = array();
    while ( count( $screenids ) < 4 ) {
        $proposed = rand(0, $rowcount - 1);
        if (!array_key_exists($proposed, $screenids))
            $screenids[$proposed] = True;
    }

    // Retreive details for the 4 selected ones
    $screens = array();
    for ( $i = 0; count($screens) < 4; $i++ ) {
        $row = mysqli_fetch_array($res);
        if (array_key_exists( $i, $screenids ) ) {
            $screens[] = $row;
        }
    }

    foreach ($screens as $screen) {
        $thumb = get_thumbnail($screen['physical_filename'], 237, 119);
        $title = $screen['topic_title'];
        $imgline = '<a href="screenshot.php?id=' . intval( $screen['attach_id'] ) . '" rel="lytebox[fpscreens]" title="' . $title . '">';
        $imgline .= '<img class="frontscreenshot" src="' . $thumb . '" width="237" height="119" border="0" alt="" /></a>';
        $screenthumbs[] = $imgline;
    }
} else {
    // Not enough.. Should not usually happen.
}


$videos = array(
		"https://www.youtube.com/embed/R6DSXTIcwzI?rel=0&autohide=1", #zero-k trailer
		"https://www.youtube.com/embed/2mKhQD2SVqw?rel=0&autohide=1", #spring rts trailer
		"https://www.youtube.com/embed/GAM_vcVJiL4?rel=0&autohide=1", #spring showcase
		"https://www.youtube.com/embed/vuP63IobLps?rel=0&autohide=1", #NOTA "Action Trailer"
	);
$videofile = str_replace( "&", "&amp;", $videos[array_rand($videos)] );

// Compose the frontpage
$fptemplate = file_get_contents('templates/frontpage.html');
$fpkeys = array('#NEWSITEMS#', '#WELCOME#', '#SCREEN1#', '#SCREEN2#', '#SCREEN3#', '#SCREEN4#', '#VIDEOFILE#');
$fpitems = array($news, $welcome, $screenthumbs[0], $screenthumbs[1], $screenthumbs[2], $screenthumbs[3], $videofile);
$fp = str_replace($fpkeys, $fpitems, $fptemplate);

// Compose the final page
$headertemplate = file_get_contents('templates/header-offsite.html');
$starttemplate = file_get_contents('templates/pagestart.html');
$metatemplate = file_get_contents('templates/meta.html');

$html  = $starttemplate;
$html .= str_replace('{META}', '<link href="/index.css?v=2" rel="stylesheet" type="text/css" />
								<link href="/header-navbar-mainsite-overrides.css" rel="stylesheet" type="text/css" />', $metatemplate);
$html .= "<title>Spring RTS Engine</title>\n</head><body class=\"spring-homepage\">";
$html .= str_replace('{PAGE_TITLE}', '<img src="/images/homie.gif" width="11" height="10" border="0" alt=""/>&nbsp;Home', $headertemplate);
$html .= $fp;
$html .= file_get_contents('templates/footer.html');
$html .= file_get_contents('templates/pageend.html');

print($html);
