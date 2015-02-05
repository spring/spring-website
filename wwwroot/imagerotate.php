<?php
	// We'd like the image to only be up for rotation every 2 minutes
	$rot_time = 120;
	$time_now = gmmktime();
	$time_now -= ($time_now % $rot_time);
	header("Etag: $time_now");

	if (array_key_exists('HTTP_IF_NONE_MATCH', $_SERVER)) {
		$timestamp = (int)$_SERVER['HTTP_IF_NONE_MATCH'];
		if ($timestamp + $rot_time > $time_now) {
			header("HTTP/1.1 304 Not Modified");
			exit();
		}
	}

	include_once('includes/db.php');

	// Find suitable images to rotate
	$banners_forum = 32;
	$sql = '';
	$sql .= 'select physical_filename, real_filename, topic_title ';
	$sql .= 'from phpbb3_attachments as a, phpbb3_topics as t ';
	$sql .= "where t.forum_id = $banners_forum and a.topic_id = t.topic_id ";
	$sql .= 'order by rand() limit 1';

	$res = mysql_query($sql);
	if (mysql_num_rows($res) != 1)
		exit();
	$row = mysql_fetch_array($res);

	// Find suitable images to rotate
	$banners_forum = 32;
	$sql = '';
	$sql .= 'select physical_filename, real_filename, attach_id ';
	$sql .= 'from phpbb3_attachments as a, phpbb3_topics as t ';
	$sql .= "where t.forum_id = $banners_forum and a.topic_id = t.topic_id ";
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

	header("HTTP/1.1 303 See Other");
	header("Content-Type: $mimetype");
	header('Location: ' . $_SERVER['SERVER_NAME'] . '/phpbb/download/file.php?id=' . $row['attach_id']);
