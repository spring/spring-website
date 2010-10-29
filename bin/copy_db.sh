#!/bin/sh

# This script copies data from the spring database to the springtest database.
# The schema of both databases must be identical!

# inspired by:
# http://stackoverflow.com/questions/25794/mysql-copy-duplicate-database

DB_SOURCE=spring
DB_TARGET=springtest

for TABLE in `echo "SHOW TABLES" | mysql | tail -n +2`; do
	echo ${TABLE}

mysql <<EOD
ALTER TABLE ${DB_TARGET}.${TABLE} DISABLE KEYS;
TRUNCATE TABLE ${DB_TARGET}.${TABLE};
INSERT INTO ${DB_TARGET}.${TABLE} SELECT * FROM ${DB_SOURCE}.${TABLE};
ALTER TABLE ${DB_TARGET}.${TABLE} ENABLE KEYS;
EOD

done
