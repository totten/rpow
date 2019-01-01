insert into foo (col1, col2) values (1, 2);

Insert into foo (col1, col2) values ("1", 'two');

INSERT into foo values ("one", "two");

insert into foo select * from bar 
on duplicate key update whiz = b(ang);

update foo set bar = 1 where whiz = b(ang);

Update Ignore foo Set bar = 1 Limit 10;

DELETE FROM foo;

delete from foo where whiz = b(ang);

delete from foo where id in (SELECT foo_id FROM bar);

TRUNCATE foo;

ALTER TABLE foo;

create TABLE foo (whiz bang);

create TABLE if Not Exists foo (whiz bang);

CREATE VIEW foo AS SELECT bar;

GRANT ALL on *.* TO `foo`@`bar`;

REVOKE foo;

SELECT a,b,a+b INTO OUTFILE '/tmp/result.txt'
  FIELDS TERMINATED BY ',' OPTIONALLY ENCLOSED BY '"'
  LINES TERMINATED BY '\n'
  FROM test_table;

SELECT * FROM t1 WHERE c1 = (SELECT c1 FROM t2) FOR UPDATE;

SELECT * FROM t1 WHERE c1 = (SELECT c1 FROM t2 FOR UPDATE) FOR UPDATE;

SELECT * FROM parent WHERE NAME = 'Jones' FOR SHARE;

SELECT
*
FROM t1 WHERE c1 = (SELECT c1 FROM t2 FOR UPDATE) FOR UPDATE;

select
get_lock
("foo", 123);

select (Get_Lock("foo", 123));

select "foo",is_FREE_lock("bar");

commit;

Commit Work;

rollback;

ROLLBACK AND NO CHAIN;

flush privileges;

rollback to foo;

UNRECOGNIZED ACTION;

unrecognized action;

SELECT 'mysql-rpow-force-write';

SELECT "mysql-rpow-force-write";
