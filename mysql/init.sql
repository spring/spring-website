create user 'spring'@'localhost' identified by 'some_pass';
create user 'spring_clean'@'localhost' identified by 'some_other_pass';
create database spring;
create database spring_clean;
grant all privileges on `spring`.* TO 'spring'@'localhost';
grant all privileges on `spring_clean`.* TO 'spring_clean'@'localhost';
grant select on `spring`.* TO 'spring_clean'@'localhost';
