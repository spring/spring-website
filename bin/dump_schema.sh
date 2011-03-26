#!/bin/sh
# see also: http://bugs.mysql.com/bug.php?id=20786
mysqldump --skip-dump-date --no-data spring | sed -r '/^\) ENGINE=.*;$/ s/ AUTO_INCREMENT=[0-9]+//g' > mysql/schema.sql
