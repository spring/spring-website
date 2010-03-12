# Some notes

Quick notes on how to set up a local development copy of the site:

Make sure you acquire from [here](http://springrts.com/dl/spring-website/), [here](http://www.springlobby.info/temp/spring_site/),
or from someone with sufficient access (in case both links are down or too old):

- a sample database dump (some_database_dump.sql)
- a set of attachments belonging to this dump (attachments.tar.gz)

Then do approximately this:

    git clone git://github.com/spring/spring-website.git
    cd spring-website
    tar xf attachments.tar.gz
    bin/fix_perms.sh   # may want to review the script and/or run it as root
    cp springpw_example.php springpw.php
    $EDITOR springpw.php   # set mysql user/pass/database

    create user 'spring'@'localhost' identified by 'some_pass';
    create database spring;
    grant all privileges on `spring`.* TO 'spring'@'localhost';
    use spring;
    source some_database_dump.sql

Quick notes on how to create a new clean database (user/database: spring_clean):

    create user 'spring_clean'@'localhost' identified by 'some_pass';
    create database spring_clean;
    grant all privileges on `spring_clean`.* TO 'spring_clean'@'localhost';
    grant select on `spring`.* TO 'spring_clean'@'localhost';
    use spring_clean;
    source mysql/schema.sql;
    source mysql/anonimized-copy.sql;   # this wants to select from database 'spring'
