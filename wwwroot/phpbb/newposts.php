<?php
require_once("config.php");
include("feedcreator.class.php");
$db = mysql_connect($dbhost,$dbuser,$dbpasswd);
mysql_select_db($dbname,$db);
$query = "SELECT * FROM ".$table_prefix."posts WHERE forum_id NOT IN (3, 4, 5, 8, 17, 18, 28, 29, 62, 24, 25, 40, 30) ORDER BY post_id DESC LIMIT 0,10";
$res = mysql_query($query);

if ($res == FALSE)
{
  echo "Mysql error on query: $query";
  die();
  
}
$rss = new UniversalFeedCreator();
$rss->title = "SpringRTS Forum RSS Feed";
$rss->description = "Latest posts on spring forum";
$rss->link = "http://www.springrts.com/newposts.php";
$rss->syndicationURL = "http://www.springrts.com/".$PHP_SELF;

while ( $row = mysql_fetch_array($res))
{
  //echo "\t\t<item>\n";
  $item = new FeedItem();
  $author = "";
  $query = "SELECT username FROM ".$table_prefix."users WHERE user_id = ".$row["poster_id"];
  $res2 = mysql_query($query);

  if ($res2 == FALSE)
  {
    echo "Mysql error on query: $query";
    die();
    
  }
  $r2 = mysql_fetch_array($res2);
  $author = $r2["username"];
  $query = "SELECT topic_title FROM ".$table_prefix."topics WHERE topic_id = ".$row["topic_id"];
  $res2 = mysql_query($query);

  if ($res2 == FALSE)
  {
    echo "Mysql error on query: $query";
    die();
    
  }
  $r2 = mysql_fetch_array($res2);
  $thread = $r2["topic_title"];
  $fid = $row["forum_id"];
  $forum = "";
  $it = 0;
  do {
  $query = "SELECT forum_name,parent_id FROM ".$table_prefix."forums WHERE forum_id = ".$fid;
  $res2 = mysql_query($query);

  if ($res2 == FALSE)
  {
    echo "Mysql error on query: $query";
    die();
    
  }
  $r2 = mysql_fetch_array($res2);
  $fid = $r2["parent_id"];
  $forum = $r2["forum_name"].(($forum == "") ? "" : " -> ").$forum;
  $it++;
  if ($it > 100)
  {
    die("Too many iterations when fetching forum category");
  }
  }while ($fid != "0");
  $title = "( ".$author." ) ".$forum." -> ".$thread;
  $link = "http://springrts.com/phpbb/viewtopic.php?p=".$row["post_id"]."#p".$row["post_id"];
  $item->title = $title;
  $item->link = $link;
  $item->author = $author;
  $rss->addItem($item);
}
//echo "\t</channel>\n";
//echo "</rss>\n";
echo $rss->createFeed("RSS2.0");


?>
