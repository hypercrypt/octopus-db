<?php

echo 'This may delete data in your database, are you sure? (y/N): ';
$sure = strtolower(fgets(fopen('php://stdin','r')));

if (str_starts_with($sure, 'y'))
{
    $sql = file_get_contents(__DIR__ . '/../../db/schema.sql');
    db()->exec($sql);
}