<?php

error_reporting(E_ALL);
ini_set('display_startup_errors',1);
ini_set('display_errors', 1);

ini_set(option: 'memory_limit', value: '4G');
require_once __DIR__ . '/base.php';

$msg = $argv[0] . ' is deprecated. Please use `octo import/octopus`. This file will be removed in version 0.1.0.' . PHP_EOL . PHP_EOL;

echo $msg;
require_once __DIR__ . '/scripts/db/upgrade.php';
require_once __DIR__ . '/scripts/octopus/import.php';
echo $msg;
