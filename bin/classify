<?php

if (PHP_SAPI !== 'cli') {
  throw new \RuntimException("classify.php can only be run from command line.");
}

require_once dirname(__DIR__) . '/vendor/autoload.php';

$parts = $argv;
array_shift($parts);
$expr = implode(' ', $parts);

$c = new \MysqlRpow\Classifier();

printf("EXPRESSION: %s\nCLASSIFICATION:%s\n", $expr, $c->classify($expr));
