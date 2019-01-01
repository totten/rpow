# PHP-MySQL Replay-on-Write Helper (RPOW)

This is a small library to help route MySQL requests to (a) read-only slave
and/or (b) read-write master.  The general idea is to connect to the
read-only slave in the typical read-only use-case...  then switch to the
read-write master *if* there is an actual write.

Dynamically switching to the read-write master is not quite as simple as it
sounds because the original read-only session may have some significant local
state (such as session-variables or temporary-tables) that feed into the
write operations. To mitigate this, we use a buffer to track statements
which affect session-state. Such statements are replayed on the master.

This is designed for use-cases in which:

* *Most* users/page-views can be served *entirely* by the read-only slave.
* *Some* users/page-views need to work with the read-write master.
* There is no simpler or more correct way to predict which users/page-views will need read-write operations.
  (Or: you need a fallback in case the predictions are imperfect.)

## Classification

Every SQL statement can be classified into one of three buckets:

* `READ` (Ex: `SELECT * FROM foo`): The SQL statement has no side-effects; it simply reads data.
* `BUFFER` (Ex: `SET @user_id = 123`): The SQL statement has no long-term, persistent side-effects; it can,
  however, have temporary side-effects during the present MySQL session.
* `WRITE` (Ex: `TRUNCATE foo`): The SQL statement has long-term, persistent side-effects and must be
   executed on the master. (Generally, if we can't demonstrate that something is `READ` or `BUFFER`,
   then we assume it is `WRITE`.)

## Stages

* In the first stage, we execute straight-up read statements on the read-only slave.
  Statements with connection-local side-effects (eg "SET @user_id=123" or "CREATE TEMPORARY TABLE...")
  are be stored in a buffer.
* In the second stage, we encounter the first straight-up write statement.
  We switch to read-write master, where we replay the buffer along with the write statement.
* In the third/final stage, all statements are executed on the read-write master.

## Consistency

RPOW provides *some* consistency, but it also has some limitations.

*Within a MySQL given session*, you can mix various read+write operations --
for example, insert a record, read the record, then update it, and then read
it again.  Once you start writing, all requests are handled by the master --
which should provide a fair degree of consistency.

However, there is one notable source of inconsistency: at the beginning of
the connection (before the first write), you'll read data from a slave
(instead of the master) -- so it may start with a dirty-read. A few
mitigating considerstions:

* Hopefully, some dirty-reads are acceptable.  If dirty-reads are a significant problem, you
  probably wouldn't have chosen an architecture based on single-write-master/multiple-read-slaves!
* If you know that a use-case will be writing and must have fresh reads, you
  can either call `$stateMachine->forceWriteMode()` or (if you don't have
  access to `$stateMachine`) issue a request for the constant `SELECT "mysql-rpow-force-write"`.
* If you have a propagation delay between the master+slave (e.g. 30sec), then users may be more
  sensitive to dirty-reads within the propagation period (e.g. 30sec). Use an HTTP cookie or HTTP session-variable
  to force them on enable `forceWriteMode()` for new page-views in the subsequent 30-60 sec.
  (TODO: Example code)

## Usage (WIP)

```php
require_once 'vendor/autoload.php';
mysqlnd_ms_set_user_pick_server('mysql_rpow_ms_adapter');
```
