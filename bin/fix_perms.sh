#!/bin/sh

files="
 wwwroot/thumbs/
 wwwroot/phpbb/cache/
 wwwroot/phpbb/files/
 wwwroot/phpbb/store/
 wwwroot/phpbb/images/avatars/upload/
 wwwroot/mediawiki/images/
"

for f in $files; do
	if [ ! -e $f ]; then
		echo "file/directory not found: $f"
		echo "did you run this script in the right directory?"
		exit 1
	fi
done

chgrp www-data $files
chmod g+rwx $files

for f in $files; do
	find $f -type f ! \( -name .htaccess -o -name index.\* -o -name \*.php \) -exec chmod 660 '{}' \;
	find $f -type f ! \( -name .htaccess -o -name index.\* -o -name \*.php \) -exec chgrp www-data '{}' \;
	find $f -type d ! \( -name .htaccess -o -name index.\* -o -name \*.php \) -exec chmod 775 '{}' \;
	find $f -type d ! \( -name .htaccess -o -name index.\* -o -name \*.php \) -exec chgrp www-data '{}' \;
done
