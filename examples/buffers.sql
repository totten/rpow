SET @foo = 123;

SET @@foo = "bar";

select @lastid := id from civicrm_contact;

create temporary table foo;

CREATE TEMPORARY TABLE foo (whiz bang);

CREATE TEMPORARY TABLE IF NOT EXISTS foo (whiz bang);

DROP TEMPORARY TABLE foo;

drop temporary table if exists foo;

Select * FROM hello_world where `name` = "foo" AND id in (select @bar := id FROM `other_ods`);

SELECT id, data INTO @x, @y FROM test.t1 LIMIT 1;
