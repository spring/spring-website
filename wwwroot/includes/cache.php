<?php

/**
 * Returns an empty string on failure
 */
function cached_file_get_contents( $url, $maxage = 300 ) {
	$file = $_SERVER['DOCUMENT_ROOT'] . '/../cache/' . urlencode($url);
	if ( is_readable( $file ) ) {
		$mtime = filemtime( $file );
	
		if (!$mtime || (time() - $mtime) > $maxage) {
	
			// Touch the cache to stop other scripts from refreshing the cache too.
			touch( $file );
	
			$data = file_get_contents( $url ) or '';
			if ( $data == false ) {
				return "";
			}
			// Use temporary file and rename to make the cache refresh atomic.
			if ( file_put_contents( $file . '.tmp', $data ) != false ) {
				rename($file . '.tmp', $file);
			}
			return $data;
		} else {
			$data = file_get_contents( $file );
			if ( $data ) {
				return $data;
			}
			return "";
		}
	}
}
