# CiviCRM Replay-on-Write: Installation

## General/Abstract

* [Setup CiviCRM to store caches in Redis.](https://docs.civicrm.org/sysadmin/en/latest/setup/cache/)

* Download the extension, e.g.

  ```
  cd /var/www/sites/default/civicrm/ext
  git clone https://github.com/totten/rpow
  ```

* Setup the MySQL read-write and read-only databases -- and determine their
  DSNs.  That process is outside the scope of this README. See also: [MySQL 5.7 Reference Manual: Replication Howto](https://dev.mysql.com/doc/refman/5.7/en/replication-howto.html):

* Edit `civicrm.settings.php`. In lieu of setting `define('CIVICRM_DSN', '...')`, call this:

  ```php
  require_once '<RPOW-SRC>/autoload.php';
  rpow_init([
    'masters' => ['mysql://rw_user:rw_pass@rw_host/rw_db?new_link=true'],
    'slaves' => ['mysql://ro_user:ro_pass@ro_host/ro_db?new_link=true'],
  ]);
  ```

## Using rundb

If you're doing local development on a `civibuild` site, then you might use
the `rundb` scripts to launch two local instances of `mysqld` in
master-slave configuration.  These instances run on alternate, local-only
ports with insecure passwords.

* [Setup CiviCRM to store caches in Redis.](https://docs.civicrm.org/sysadmin/en/latest/setup/cache/)

* Download the extension, e.g.

  ```
  cd /var/www/sites/default/civicrm/ext
  git clone https://github.com/totten/rpow
  ```

* In a separate terminal, follow the [rundb Quick Start](https://github.com/totten/rundb#quick-start)

* Determine the path to your civibuild site (e.g. `~/buildkit/build/dmaster`).

* Copy the "civi" DB from civibuild/amp to the master+slave servers, e.g.

  ```
  cd rundb
  amp sql:dump -r ~/buildkit/build/dmaster -N civi | nix-shell -A master --command 'load-db civi'
  ```

* Edit `civicrm.settings.php`. In lieu of setting `define('CIVICRM_DSN', '...')`, call this:

  ```php
  require_once '<RPOW-SRC>/autoload.php';
  rpow_init([
    'masters' => ['mysql://root:@127.0.0.1:3330/civi?new_link=true'],
    'slaves' => ['mysql://reader:@127.0.0.1:3331/civi?new_link=true'],
  ]);
  ```

## Using rebuild-ro

If you're doing local development on a `civibuild` site, then you can simulate a
master/slave topology using the script the script `rebuild-ro` to make a read-only
copy of your database.

* [Setup CiviCRM to store caches in Redis.](https://docs.civicrm.org/sysadmin/en/latest/setup/cache/)

* Download the extension, e.g.

  ```
  cd /var/www/sites/default/civicrm/ext
  git clone https://github.com/totten/rpow
  ```

* Setup a config file (`rebuild-ro.conf`), esp `CIVIRO_PASS (for security) and `SITE_ROOT` (for convenience)

  ```
  cp etc/rebuild-ro.conf.example etc/rebuild-ro.conf
  vi etc/rebuild-ro.conf

* Create the read-only DB. Copy data from the main DB. Register the DSNs via civicrm.settings.d.

  ```
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
