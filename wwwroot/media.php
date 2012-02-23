<?php

    include_once('includes/db.php');
    include_once('includes/thumbs.php');

    // Returns a suitable link to the media page with the request params added/changed
    function get_link($params)
    {
        $paramstart = strpos($_SERVER['REQUEST_URI'], '?');
        foreach ($params as $key => $value)
            if ($value)
                $params[$key] = urlencode($value);
        if ($paramstart !== false) {
            foreach (explode('&', substr($_SERVER['REQUEST_URI'], $paramstart + 1)) as $param) {
                $pair = explode('=', $param, 2);
                if (!array_key_exists($pair[0], $params))
                    $params[$pair[0]] = $pair[1];
            }
        }
        $paramstrs = array();
        foreach ($params as $key => $value)
            if ($value)
                $paramstrs[] = $key . '=' . $value;
        return substr($_SERVER['REQUEST_URI'], 0, $paramstart) . '?' . implode('&', $paramstrs);
    }

    $type = '';
    if (array_key_exists('type', $_GET))
        $type = $_GET['type'];

    // Fetch all media of the specified type
    $sql = '';
    $sql .= 'select attach_id, physical_filename, topic_title, t.topic_id, p.post_text, t.forum_id ';
    $sql .= 'from phpbb3_attachments as a, phpbb3_topics as t, phpbb3_posts as p ';
    if ($type == 'images')
        $sql .= "where (t.forum_id = 35) ";
    elseif ($type == 'videos')
        $sql .= "where (t.forum_id = 34) ";
    else
        $sql .= "where (t.forum_id = 35 or t.forum_id = 34) ";
    $sql .= "and a.topic_id = t.topic_id and p.post_id = a.post_msg_id ";
    $sql .= "and (extension = 'gif' or extension = 'jpg' or extension = 'jpeg' or extension = 'png') ";
    $sql .= 'order by topic_time desc';

    $res = mysql_query($sql);
    $media = '<tr>';
    $count = 0;

    $tags = array();

    $itemtemplate = file_get_contents('templates/mediaitem.html');
    if (array_key_exists('tag', $_GET))
        $wantedtag = $_GET['tag'];
    else
        $wantedtag = false;

    while ($row = mysql_fetch_array($res)) {

        // Start with tag processing
        $foundwanted = false;
        foreach (explode("\n", $row['post_text']) as $tag) {
            $tag = trim($tag);
            if (array_key_exists($tag, $tags))
                $tags[$tag]++;
            else
                $tags[$tag] = 1;
            if ($tag == $wantedtag)
                $foundwanted = true;
        }
        if ($wantedtag && !$foundwanted)
            continue;

        // We should display this item, go ahead and produce html
        $media .= '<td>';

        $title = $row['topic_title'];
        $thumb = get_thumbnail($row['physical_filename'], 174, 98);

        if ($row['forum_id'] == 35) {
            $item = '<a href="screenshot.php?id=' . $row['attach_id'] . '" rel="lytebox[fpscreens]" title="' . $title . '">';
            $item .= '<img src="' . $thumb . '" width="174" height="98" border="0" alt="" /><br /></a>';
        }
        elseif ($row['forum_id'] == 34) {
            $item = '<a href="' . get_link(array('play' => $row['topic_id'])) . '">';
            $item .= '<img src="' . $thumb . '" width="174" height="98" border="0" alt="" /><br /></a>';
        }

        $media .= str_replace('#ITEM#', $item, $itemtemplate);
        $media .= '</td>';

        if ($count % 3 == 2)
            $media .= '</tr><tr>';

        $count++;
    }

    // Compensate for very little content
    for ($i = $count; $i < 3; $i++)
        $media .= '<td><img src="images/pixel.gif" height="16" width="174" /></td>';
    $media .= '</tr>';

    arsort($tags);
    $tagstr = '';
    if (array_key_exists('tag', $_GET)) {
        $tagstr .= '<li><a href="' . get_link(array('tag' => false, 'play' => false)) . '">Show all content (' . mysql_num_rows($res) . ")</a></li>\n";
        $tagstr .= "</ul><ul class=\"taglist\"><br/>\n";
    }
    foreach ($tags as $tag => $count) {
        $tagstr .= '<li><a href="' . get_link(array('tag' => $tag, 'play' => false)) . '">' . $tag . ' (' . $count . ")</a></li>\n";
    }

    // Check if the video player has been invoked
    $video = 0;
    if (array_key_exists('play', $_GET))
        $video = (int)$_GET['play'];
    if ($video > 0) {
        $videotemplate = file_get_contents('templates/mediaplayer.html');
        $videofile = '/jwvideo' . $video . '.flv';
        $videoimage = '/jwimage' . $video . '.jpg';
        $videokeys = array('#VIDEOFILE#', '#VIDEOIMAGE#');
        $videoitems = array($videofile, $videoimage);
        $videoplayer = str_replace($videokeys, $videoitems, $videotemplate);
    }
    else
        $videoplayer = '';

    // Write a short help line about currently selected filters
    $help = array();
    if ($type == 'videos')
        $help[] = 'showing videos only';
    elseif ($type == 'images')
        $help[] = 'showing images only';
    if (array_key_exists('tag', $_GET))
        $help[] = 'filtering by the tag ' . $_GET['tag'];

    if (count($help) > 0) {
        $helptext = 'Currently ' . implode(', ', $help) . '. Click <a href="/media.php">here</a> to show all content.';
    }
    else
        $helptext = '&nbsp;';

    // Compose the page
    $imagelink = get_link(array('type' => 'images', 'play' => false));
    $videolink = get_link(array('type' => 'videos', 'play' => false));
    $fptemplate = file_get_contents('templates/media.html');
    $fpkeys = array('#MEDIA#', '#TAGS#', '#VIDEOPLAYER#', '#IMAGELINK#', '#VIDEOLINK#', '#HELP#');
    $fpitems = array($media, $tagstr, $videoplayer, $imagelink, $videolink, $helptext);
    $fp = str_replace($fpkeys, $fpitems, $fptemplate);

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
