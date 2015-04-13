#!/bin/sh

## script to ease migration from the ubuntu 12.04 to ubuntu 14.04 server

# to migrate do:
# 0. at new server (before dns change!)
#       service cron stop
#       service apache2 stop
#       service stop mumble-server
#	killall python (buildbot, lobby server, irc bridge)
#       killall perl (irc-bridge)
#       killall node (etherpad-lite)
# 1. at old server:
#       (all steps from 0)
#       /etc/cron.daily/backup-dump-mysql
# 2. at new server:
#       /root/migrate.sh
#       start lobby server 
#       start irc bridge
#       start buildbot
#       service start apache2
#       service start cron
#       service start mumble-server
# 3. at old server:
#       enable redirect mode for lobby server
#       enable proxy for http server 
#       disable cron jobs (packages, buildbot, reboot jobs, update):
#          check /var/spool/cron/crontabs
#       disable backup script
#       service start cron
# 4. at new server:
#       start etherpad lite
#       enable cron jobs for packages
#       check /var/spool/cron/crontabs
#       cp /root/etc/cron.daily/backup-dump-mysql /etc/cron.daily/backup-dump-mysql

set -e
# bandwith limit to use (kb/s)
BWLIMIT=5000

EXCLUDE_PATHS="
usp-opava
chris
bestie
majkl
kto
tobi
aegis
team
licho
basic
komi
luc
jazcash
bestie
thor
car
lurker
ikinz
tgchan
maackey
svn
"

for i in $EXCLUDE_PATHS; do
        EXCLUDE="$EXCLUDE --exclude=$i"
done

rsync -av ${EXCLUDE} --bwlimit=${BWLIMIT} --delete --delete-excluded root@94.23.170.70:/home/ /home/

# copy /etc
rsync -av --delete root@94.23.170.70:/etc/ /root/etc/
rsync -av --delete root@94.23.170.70:/root/block-tor-iptables/ /root/block-tor-iptables/
rsync -av --delete root@94.23.170.70:/var/lib/mumble-server/ /var/lib/mumble-server/

# mysql db dumps
rsync -av root@94.23.170.70:/var/backups/mysql/ /var/backups/mysql/

# rsync crontabs (in the case of one was missed...)
rsync -av root@94.23.170.70:/var/spool/cron/ /root/cron/

cp -v /root/etc/rc.local /etc/rc.local
cp -v /root/etc/cron.daily/block-tor /etc/cron.daily/block-tor



SRC=/var/backups/mysql

for i in $SRC/*; do
	i=$(basename $i)
	echo importing $i
	zcat $SRC/$i/current.sql.gz | mysql $i
done

# upgrade buildbot database
sudo -u buildbot buildbot upgrade-master /home/buildbot/master

