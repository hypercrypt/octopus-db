<?php

$config = require  __DIR__ . '/../config.php';

$database_connection = new PDO(...$config['db']);

if (count($database_connection->query("SHOW COLUMNS FROM `price` LIKE 'rate_type'")->fetchAll()) === 0)
{
    $database_connection->exec("
        ALTER TABLE `price`
        DROP PRIMARY KEY,
        ADD `rate_type` ENUM('day','night','standard') NOT NULL DEFAULT 'standard' AFTER `tariff`,
        ADD PRIMARY KEY (`valid_from`, `valid_to`, `tariff`, `rate_type`)"
    );
}