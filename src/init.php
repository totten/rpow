<?php

/**
 * Initialize civirpow - setup class-loading and apply the configuration
 * options.
 *
 * This helper is intended for use in
 *
 * @param array $config
 *   Ex: [
 *    'slaves' => ['mysql://ro_user:ro_pass@ro_host/ro_db?new_link=true'],
 *    'masters' => ['mysql://rw_user:rw_pass@rw_host/rw_db?new_link=true'],
 *   ])
 */
function civirpow_init($config = []) {
  // PEAR DB classes
  set_include_path(dirname(__DIR__) . PATH_SEPARATOR . get_include_path());

  global $civirpow;
  $civirpow = $config;

  define('CIVICRM_DSN', 'civirpow://');
  // define('CIVICRM_DSN', $config['masters'][0]);
  // define('CIVICRM_DSN', $config['slaves'][0]);
}
