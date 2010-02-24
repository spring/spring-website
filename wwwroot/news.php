<?php

include_once('includes/news.php');

// Prepare newsitems
$news = get_news();

// Prepare community headlines
$cnews = get_community_news();

// Compose the frontpage
$fptemplate = file_get_contents('templates/news.html');
$fpkeys = array('#NEWSITEMS#', '#CNEWSITEMS#');
$fpitems = array($news, $cnews);
$fp = str_replace($fpkeys, $fpitems, $fptemplate);

// Compose the final page
$headertemplate = file_get_contents('templates/header.html');
$starttemplate = file_get_contents('templates/pagestart.html');

$html = $starttemplate;
//$html .= str_replace('{PAGE_TITLE}', '<img src="/images/homie.gif" width="11" height="10" border="0"/>&nbsp;Home', $headertemplate);
$html .= $fp;
//$html .= file_get_contents('templates/footer.html');
$html .= file_get_contents('templates/pageend.html');

print($html);
?>
