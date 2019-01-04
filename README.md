# CiviCRM Replay-on-Write Helper (civirpow)

This is a small utility for CiviCRM which routes MySQL requests to (a) read-only
slave DB and/or (b) read-write master DB.  The general idea is to connect to the
read-only slave optimistically (expecting a typical read-only use-case)...  then switch to the
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

civirpow provides *some* consistency, but it also has some limitations.

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
      `SELECT "civirpow-force-write"`.

* If your environment regularly has a perceptible propagation delay between the master+slave (e.g.  30sec), then users
  may be more sensitive to dirty-reads within the propagation period (e.g.  30sec).  Use an HTTP cookie or HTTP
  session-variable to force them on enable `forceWriteMode()` for new page-views in the subsequent 30-60 sec.  (TODO:
  Example code)

## Unit Tests

Simply run `phpunit` without any arguments.

## Setup (General)

* [Setup CiviCRM to store caches in Redis.](https://docs.civicrm.org/sysadmin/en/latest/setup/cache/)

* Download the extension, e.g.

  ```
  cd /var/www/sites/default/civicrm/ext
  git clone https://github.com/totten/rpow
  ```

* Edit `civicrm.settings.php`. In lieu of setting `CIVICRM_DSN`, call this:

  ```php
  require_once '/PATH/TO/rpow/autoload.php';
  rpow_init([
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
cd /var/www/sites/default/civicrm/ext
git clone https://github.com/totten/rpow
cd rpow

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
  to call `rpow_init()` with the appropriate credentials
  for the `masters` and `slaves`.

This is handy for simulating master=>slave replication manually. It does
not require any special mysqld options, but it does assume that you have a
`civibuild`-based environment.

## Usage Example (Development)

Here are some example steps to see it working in development:

* In the browser
    * Navigate to your CiviCRM dev site
    * Do a search for all contacts
    * View a contact
    * Edit their name and save
    * Make a mental note of the CID. (Ex: `123`)
    * __Note__: The saved changes do not currently appear in the UI. Why?
      Because they were saved to the read-write master, but we're viewing data from the
      read-only slave.
* In the CLI
    * Lookup the contact record (ex: `123`) in both the master (`civi`) and slave (`civiro`) databases.
      You should see that the write went to the master (`civi`) but not the slave (`civiro`).
      ```
      SQL="select id, display_name from civicrm_contact where id = 123;"
      echo $SQL | amp sql -N civi ; echo; echo; echo $SQL | amp sql -N civiro
      ```
    * Update the slave.
      ```
      ./bin/rebuild-ro
      ```

TIP: When you are done doing development, delete the file
`civicrm.settings.d/pre.d/100-civirpow.php`.  This will put your dev site back
into a normal configuration with a single MySQL DSN.

## TODO

Add integration tests covering DB_civirpow

Add sticky reconnect feature -- for (eg) 2 minutes after a write, all
subsequent connections should continue going to the read-write master.

debug toolbar is wonky, and it's hard to tell if it's ux or underlying
behavior. change ux to be a full-width bar at the bottom which displays
all available info. (instead of requiring extra clicks to drilldown)

optimistic-locking doesn't work -- it always reads the timestamp from rodb
before reconnecting to rwdb. any use-case that does optimistic-locking needs
a hint to force the reconnect beforehand.

packaging as a separate project makes it feel a bit sketchy to drop hints
into civicrm-core.  consider ways to deal with this (e.g.  package as part
of core s.t.  the hint notation is built-in; e.g.  figure out a way to make
the hint-notation abstract...  like with a listner/dispatcher pattern...
but tricky b/c DB and some caches come online during bootstrap, before we've
setup our regular dispatch subsystem)
