<?php

/**
 * (Untested/WIP) Adapter for use with mysqlnd_ms_set_user_pick_server
 */
function mysql_rpow_ms_adapter($connected, $query, $masters, $slaves, $last_used_connection, $in_transaction) {
  static $sm = NULL;
  if ($sm === NULL) {
    $sm = new \MysqlRpow\StateMachine();
  }
  switch ($sm->handle($query)) {
    case 'ro':
      throw new Exception('return (one of $slaves)');

    case 'rw':
      throw new Exception('return (one of $masters)');

    case 'rp':
      // throw new Exception('replay $sm->getBuffer');
      throw new Exception('return (one of $masters)');

  }
}
