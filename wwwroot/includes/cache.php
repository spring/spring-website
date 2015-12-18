<?php

function cached_file_get_contents($url, $maxage = 300)
{
	$file = $_SERVER['DOCUMENT_ROOT'] . '/../cache/' . urlencode($url);
	$mtime = @filemtime($file);

	if (!$mtime || (time() - $mtime) > $maxage) {

		// Touch the cache to stop other scripts from refreshing the cache too.
		touch($file);

		$data = @file_get_contents($url) or '';

		// Use temporary file and rename to make the cache refresh atomic.
		file_put_contents($file . '.tmp', $data);
		rename($file . '.tmp', $file);

		return $data;
	}
	else {
		return file_get_contents($file);
	}
}
