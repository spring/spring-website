#!/bin/sh

# This script selects ALL phpbb3_attachments and puts them in a .tar.gz file.
# It assumes ~/.my.cnf contains proper user/pwd/database to select from.

# Path to the directory where phpBB attachments are stored.
PATH_TO_FILES=wwwroot/phpbb/files

if [ ! -d $PATH_TO_FILES ]; then
	echo "$PATH_TO_FILES does not exist or is not a directory!"
	echo "Are you sure you are running this script from the root of the repository?"
	exit 1
fi

rm -f attachments.tar.gz

tar cfz attachments.tar.gz $files `
	mysql -B -N -e 'select physical_filename from phpbb3_attachments' |
	sed "s@^@$PATH_TO_FILES/@g"
`
