#!/bin/sh

files="
 cache
 wwwroot/thumbs
 wwwroot/phpbb/cache
 wwwroot/phpbb/files
 wwwroot/phpbb/store
 wwwroot/phpbb/images/avatars/upload
 wwwroot/mediawiki/images
"

if [ -f /etc/redhat-release ]; then
	group=apache    # Red Hat/Fedora
else
	group=www-data  # Debian/Ubuntu
fi

for f in $files; do
	if [ ! -e $f ]; then
		echo "file/directory not found: $f"
		echo "did you run this script in the right directory?"
		exit 1
	fi
done

chgrp $group $files
chmod g+rwx $files

for f in $files; do
	find $f -type f ! \( -name .htaccess -o -name index.\* -o -name \*.php \) -exec chmod 660 '{}' \;
	find $f -type f ! \( -name .htaccess -o -name index.\* -o -name \*.php \) -exec chgrp $group '{}' \;
	find $f -type d ! \( -name .htaccess -o -name index.\* -o -name \*.php \) -exec chmod 775 '{}' \;
	find $f -type d ! \( -name .htaccess -o -name index.\* -o -name \*.php \) -exec chgrp $group '{}' \;
done

if [ "$1" = "--selinux" ]; then
	semanage fcontext -a -t httpd_sys_content_t "$PWD/wwwroot(/.*)?"
	restorecon -R wwwroot
	for f in $files; do
		semanage fcontext -a -t httpd_sys_content_rw_t "$PWD/$f(/.*)?"
		restorecon -R $f
	done
fi
