#!/bin/bash

DB_NAME='spring';
echo $DB_NAME;
git submodule init
git submodule update
# Make a database, if we don't already have one
mysql -u root --password=root -e "CREATE DATABASE IF NOT EXISTS $DB_NAME; GRANT ALL PRIVILEGES ON $DB_NAME.* TO wp@localhost IDENTIFIED BY 'wp';"

DATA_IN_DB=`mysql -u root --password=root --skip-column-names -e "SHOW TABLES FROM $DB_NAME;"`
if [ "" == "$DATA_IN_DB" ]; then
	if [ ! -f mysql/init.sql ]
	then
		echo "DATABASE NOT INSTALLED, add mysql/init.sql file and run Vagrant provisioning again"
	else
		echo "Reloading the database"
		mysql -u root --password=root $DB_NAME < mysql/schema.sql

		cd wwwroot
		wp core install --url="test.springrts.com" --title="Spring RTS Engine" --admin_user="admin" --admin_password="password" --admin_email=admin@example.com
		wp option update siteurl "http://test.springrts.com/wordpress"

		wp plugin activate wp-super-cache fitvids-for-wordpress limit-login-attempts ml-slider responsive-lightbox responsive-select-menu table-of-contents-plus wp-rss-multi-importer


		wp theme activate roflcoptered

		#mysql -u root --password=root $DB_NAME < mysql/anonymized-copy-phpbb.sql
		#mysql -u root --password=root $DB_NAME < mysql/anonymized-copy-wiki.sql
		# Search replace from production data
		#for TO_SPLIT in ${SEARCH_REPLACE[@]}
		#do
		#	SEARCH=`echo $TO_SPLIT |cut -d '|' -f1 `
		#	REPLACE=`echo $TO_SPLIT |cut -d '|' -f2 `
		#	echo "wp search-replace $SEARCH $REPLACE"
		#	wp search-replace --allow-root $SEARCH $REPLACE
		#done
	fi
else
	echo "Database has data, skipping"
fi