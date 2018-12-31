SELECT 1;

select 2;

Select * FROM hello_world where `name` = "foo" 
AND id in (select id FROM `other_ods`);

Select * FROM hello_world 
where `name` = "foo" 
AND id in (select id FROM `other_ods`);

SELECT * FROM `hello_world` where nAmE = "update";

SELECT * FROM `hello_world` where lastinsert(123) > updated;

show create table whiz;

SHOW CREATE TABLE whiz;

DESC foobar;

SELECT "INSERT INTO foo";

SELECT "CREATE TEMPORARY TABLE foo";

SELECT "ALTER TABLE foo";

SELECT "@foo := 123";
