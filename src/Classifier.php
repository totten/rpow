<?php

namespace MysqlRpow;

class Classifier {

  const TYPE_READ = 'R', TYPE_WRITE = 'W', TYPE_BUFFER = 'B';

  /**
   * Determine whether the SQL expression represents a simple read, a write, or a buffer-required read.
   *
   * @param string $sql
   *   An SQL statement
   * @return string
   *   TYPE_READ, TYPE_WRITE, or TYPE_BUFFER
   */
  public function classify($sql) {
    $sql = $this->stripStrings(
      preg_replace(';\s+;', ' ',
        mb_strtolower(
          trim($sql)
        )
      )
    );

    if (mb_substr($sql, 0, 6) === 'select') {
      $isWrite = preg_match('(for update|for share|into outfile|into dumpfile)', $sql);
      if ($isWrite) {
        return self::TYPE_WRITE;
      }

      $isBuffer = preg_match(';@[a-zA-Z0-9_\s]+:=;', $sql) || preg_match('(into @)', $sql);
      return $isBuffer ? self::TYPE_BUFFER : self::TYPE_READ;
    }

    if (preg_match(';^(set|create temporary|drop temporary)\s;', $sql)) {
      return self::TYPE_BUFFER;
    }

    if (preg_match(';(desc|show)\s;', $sql)) {
      return self::TYPE_READ;
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
