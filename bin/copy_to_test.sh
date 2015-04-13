#!/bin/sh

# quick and dirty migration script to copy real website from backup 
# to test site
# copies all data outside the git repo and the db

set -e

if ! [ $(id -u) -eq 0 ]; then
	echo "$0 has to be run as root!"
	exit 1
fi

SOURCE=/home/spring/www/wwwroot
DEST=/home/springtest/www/wwwroot

for i in /phpbb/files/ /mediawiki/images/; do
	echo syncing $SOURCE to $DEST
	rsync -av $SOURCE/$i $DEST/$i
done

SQLBACKUFILE=/var/backups/mysql/spring/current.sql.gz
MYSQLCLI="mysql --defaults-file=/home/springtest/.my.cnf"

# needs to be run as user springtest
echo importing db, this will take a while...
zcat $SQLBACKUFILE | $MYSQLCLI
# disable user registration (to block spammers on test site)
echo "UPDATE springtest.phpbb3_config set config_value=3 WHERE config_name='require_activation'" | $MYSQLCLI

