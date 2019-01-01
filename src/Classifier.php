<?php

namespace MysqlRpow;

class Classifier {

  /**
   * The SQL statement may be safely executed on a read-only slave.
   *
   * Ex: SELECT foo FROM BAR;
   */
  const TYPE_READ = 'R';

  /**
   * The SQL statement must be executed on the read-write master.
   *
   * Ex: INSERT INTO foo (bar) VALUES (123);
   */
  const TYPE_WRITE = 'W';

  /**
   * The SQL statement may be tentatively executed on a read-only slave; however,
   * if there are subsequent changes, then we must re-play on the read-write master.
   *
   * Ex: SET @active_contact_id = 123;
   */
  const TYPE_BUFFER = 'B';

  /**
   * Determine whether the SQL expression represents a simple read, a write, or a buffer-required read.
   *
   * @param string $rawSql
   *   An SQL statement
   * @return string
   *   TYPE_READ, TYPE_WRITE, or TYPE_BUFFER
   */
  public function classify($rawSql) {
    // Distill to a normalized SQL expression -- simplify whitespace and capitalization; remove user-supplied strings.
    $trimmedSql = preg_replace(';\s+;', ' ',
      mb_strtolower(
        trim($rawSql)
      )
    );
    $sql = $this->stripStrings($trimmedSql);

    // Micro-optimization: we'll execute most frequently in pure-read scenarios, so check those first.

    if (mb_substr($sql, 0, 7) === 'select ') {
      $isWrite = preg_match('(for update|for share|into outfile|into dumpfile)', $sql)
        || ($trimmedSql === 'select "mysql-rpow-force-write"')
        || ($trimmedSql === 'select \'mysql-rpow-force-write\'');
      if ($isWrite) {
        return self::TYPE_WRITE;
      }

      $isBuffer = preg_match(';@[a-zA-Z0-9_\s]+:=;', $sql) || (mb_strpos($sql, ' into @') !== FALSE);
      return $isBuffer ? self::TYPE_BUFFER : self::TYPE_READ;
    }

    if (preg_match(';^(desc|show|explain);', $sql)) {
      return self::TYPE_READ;
    }

    if (preg_match(';^(set|begin|start transaction|set autocommit|create temporary|drop temporary);', $sql)) {
      // "SET" and "SET autocommit" are technically redundant, but they should be considered logically distinct.
      return self::TYPE_BUFFER;
    }

    return self::TYPE_WRITE;
  }

  /**
   * Convert any escaped inline user-strings to empty-strings.
   *
   * @param string $sql
   *   Ex: SELECT * FROM foo WHERE bar = "loopdiloop" AND id > 10
   * @return string
   *   Ex: SELECT * FROM foo WHERE bar = "" AND id > 10
   */
  public function stripStrings($sql) {
    $PLAIN = -1;
    $SINGLE = '\'';
    $DOUBLE = '"';
    $BACK = '`';
    $ESCAPE = '\\';

    $buf = '';
    $len = strlen($sql);
    $mode = $PLAIN;
    $esc = FALSE;
    for ($i = 0; $i < $len; $i++) {
      $char = $sql{$i};
      // echo "check ($char) in mode ($mode) while buf=($buf)\n";

      switch ($mode) {
        case $PLAIN:
          $buf .= $char;

          if ($char === $SINGLE || $char === $DOUBLE || $char === $BACK) {
            // echo " -> switch to $char mode\n";
            $mode = $char;
          }
          break;

        case $SINGLE:
        case $DOUBLE:
        case $BACK:
          if ($char === $ESCAPE) {
            $esc = TRUE;
            continue;
          }
          elseif ($char === $mode && !$esc) {
            $mode = $PLAIN;
            $buf .= $char;
          }
          else {
            $esc = FALSE;
          }
          break;
      }
    }

    return $buf;
  }

}
