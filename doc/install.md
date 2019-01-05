# CiviCRM Replay-on-Write: Installation (General)

* [Setup CiviCRM to store caches in Redis.](https://docs.civicrm.org/sysadmin/en/latest/setup/cache/)

* Download the extension, e.g.

  ```
  cd /var/www/sites/default/civicrm/ext
  git clone https://github.com/totten/rpow
  ```

* Setup the MySQL read-write and read-only databases -- and determine their
  DSNs.  That process is outside the scope of this README. You should
  aim to setup automatic propagation of changes. (NOTE: If you're just doing
  development, then see [develop.md](develop.md) for steps to simulate this
  configuration.)

* Edit `civicrm.settings.php`. In lieu of setting `define('CIVICRM_DSN', '...')`, call this:

  ```php
  require_once '/PATH/TO/rpow/autoload.php';
  rpow_init([
    'slaves' => ['mysql://ro_user:ro_pass@ro_host/ro_db?new_link=true'],
    'masters' => ['mysql://rw_user:rw_pass@rw_host/rw_db?new_link=true'],
  ]);
  ```
