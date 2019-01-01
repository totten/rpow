# Replay-on-Write Helper (mysql-rpow)

This is a small utility for CiviCRM route MySQL requests to (a) read-only
slave and/or (b) read-write master.  The general idea is to connect to the
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

## Classifications

Every SQL statement is (potentially) classified into one of three buckets:

* `TYPE_READ` (Ex: `SELECT * FROM foo`): The SQL statement has no side-effects; it simply reads data.
* `TYPE_BUFFER` (Ex: `SET @user_id = 123`): The SQL statement has no long-term, persistent side-effects; it can,
  however, have temporary side-effects during the present MySQL session.
* `TYPE_WRITE` (Ex: `TRUNCATE foo`): The SQL statement has long-term, persistent side-effects and must be
   executed on the master. (Generally, if we can't demonstrate that something is `READ` or `BUFFER`,
   then we assume it is `WRITE`.)

For more detailed examples of each category, browse [tests/examples](tests/examples).

## Connection Stages

* In the first stage, we connect to the read-only slave. We stay connected
  as long as the SQL queries are read-oriented (`TYPE_READ`). Statements
  with localized side-effects (`TYPE_BUFFER`) are stored in the a buffer.
* In the second stage, we encounter the first straight-up write statement
  (`TYPE_WRITE`).  We switch to read-write master, where we replay the buffer
  along with the write statement.
* In the third/final stage, all statements of any time (`TYPE_READ`,
  `TYPE_BUFFER`, `TYPE_WRITE`) are executed on the read-write master.

## Consistency

mysql-rpow provides *some* consistency, but it also has some limitations.

*Within a MySQL given session*, you can mix various read+write operations --
for example, insert a record, read the record, then update it, and then read
it again.  Once you start writing, all requests are handled by the master --
which should provide a fair degree of consistency.

However, there is one notable source of inconsistency: at the beginning of
the connection (before the first write), you'll read data from a slave
(instead of the master) -- so it may start with a dirty-read. A few
mitigating considerstions:

* Hopefully, some dirty-reads are acceptable.  If dirty-reads are a total show-stopper, then you
  might be looking in the wrong place.

* If you know that a use-case will be writing and must have fresh reads, you can give a hint
  to force it into write mode; either
    * Call `$stateMachine->forceWriteMode()`, or...
    * (If you don't have access to `$stateMachine`) Issue a request for the dummy query
      `SELECT "mysql-rpow-force-write"`.

* If your environment regularly has a perceptible propagation delay between the master+slave (e.g.  30sec), then users
  may be more sensitive to dirty-reads within the propagation period (e.g.  30sec).  Use an HTTP cookie or HTTP
  session-variable to force them on enable `forceWriteMode()` for new page-views in the subsequent 30-60 sec.  (TODO:
  Example code)

## Unit Tests

Simply run `phpunit` without any arguments.

## Setup (General)

* [Setup CiviCRM to store caches in Redis.](https://docs.civicrm.org/sysadmin/en/latest/setup/cache/)

* Download this project and its dependencies. The code may live anywhere
  (inside or outside the site-root).

  ```
  git clone https://github.com/totten/mysql-rpow ~/src/rpow
  cd ~/src/rpow
  composer install
  ```

* Edit `civicrm.settings.php`. In lieu of setting `CIVICRM_DSN`, call this:
  ```php
  require_once '/home/myuser/src/rpow/vendor/autoload.php';
  civirpow_init([
    'slaves' => ['mysql://ro_user:ro_pass@ro_host/ro_db?new_link=true'],
    'masters' => ['mysql://rw_user:rw_pass@rw_host/rw_db?new_link=true'],
  ]);
  ```

* Setup the MySQL read-write and read-only databases -- and determine their
  DSNs.  That process is outside the scope of this README.

## Setup (Development)

If you have created a local D7 development site using `civibuild`, and if you've already
[configured Redis](https://docs.civicrm.org/sysadmin/en/latest/setup/cache/), then you can simulate
a master/slave toplogy using `rebuild-ro`.

```
## Get the code
git clone https://github.com/totten/mysql-rpow ~/src/rpow
cd ~/src/rpow
composer install

## Setup a config file, esp:
## - CIVIRO_PASS (for security)
## - SITE_ROOT (for convenience)
cp etc/rebuild-ro.conf.example etc/rebuild-ro.conf

## Create a read-only DB. Register the DSN via civicrm.settings.d.
./bin/rebuild-ro
```

The `rebuild-ro` script will:

* Make a new database
* Copy the CiviCRM tables to the new database
* Add a user with read-only permission for the new database
* Create a file `civicrm.settings.d/pre.d/100-civirpow.php` 
  to call `civirpow_init()` with the appropriate credentials
  for the `masters` and `slaves`.

This is handy for simulating master=>slave replication manually. It does
not require any special mysqld options. Whenever you want the read-only
slave to update, call `rebuild-ro` again.

When you are done doing development, you can go back to a standard
configuration by deleting `civicrm.settings.d/pre.d/100-civirpow.php`.

## TODO

Add integration tests covering DB_civirpow

Add sticky reconnect feature -- for (eg) 2 minutes after a write, all
subsequent connections should continue going to the read-write master.

Determine how to classify these statements:

```
SELECT GET_LOCK('${seqname}_lock',10"

SELECT RELEASE_LOCK('${seqname}_lock'
```
