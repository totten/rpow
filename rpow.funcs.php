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
 *    'cookieSigningKey' => 'asdf12344321fdsa',
 *   ])
 */
function civirpow_init($config = []) {
  // PEAR DB classes
  set_include_path(dirname(__DIR__) . PATH_SEPARATOR . get_include_path());

  $defaultCookieSigningKey = md5(json_encode([
    $_SERVER['HTTP_HOST'],
    $config['masters'],
    $config['slaves'],
    $_SERVER['HTTP_HOST'],
  ]));
  $defaults = [
    'onReconnect' => [
      '_civirpow_update_cookie',
    ],
    'cookieSigningKey' => $defaultCookieSigningKey,
    'cookieName' => 'rpow' . substr(md5('cookie::' . $defaultCookieSigningKey), 0, 8),
    'cookieTtl' => 10 * 60,
    'stateMachine' => new CRM_Rpow_StateMachine(),
    'debug' => 1,
  ];
  $config = array_merge($defaults, $config);

  global $civirpow;
  $civirpow = $config;

  // FIXME: cookie expires relative to first edit; should be relative to last edit
  if (_civirpow_has_cookie($config)) {
    $civirpow['forceWrite'] = 1;
  }

  define('CIVICRM_DSN', 'civirpow://');
  // define('CIVICRM_DSN', $config['masters'][0]);
  // define('CIVICRM_DSN', $config['slaves'][0]);
}

function _civirpow_signer($config) {
  return new \CRM_Utils_Signer($config['cookieSigningKey'], ['exp']);
}

function _civirpow_has_cookie($config) {
  if (isset($_COOKIE[$config['cookieName']])) {
    $cookie = json_decode($_COOKIE[$config['cookieName']], TRUE);
  }
  else {
    $cookie = NULL;
  }

  if (isset($cookie['exp']) && $cookie['exp'] > time() && _civirpow_signer($config)->validate($cookie['sig'], $cookie)) {
    return TRUE;
  }
  else {
    return FALSE;
  }
}

function _civirpow_update_cookie($config, $db) {
  $signer = _civirpow_signer($config);
  $expires = time() + $config['cookieTtl'];
  $buffer = '';
  if ($config['debug']) {
    foreach ($config['stateMachine']->getBuffer() as $i => $line) {
      $buffer .= "$i: $line\n";
    }
  }
  $value = json_encode([
    'exp' => $expires,
    'sig' => $signer->sign(['exp' => $expires]),
    'cause' => $buffer,
  ]);
  setcookie($config['cookieName'], $value, $expires, '/');
}
