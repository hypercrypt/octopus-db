<?php

require_once __DIR__ . '/../../base.php';

$bright = new Bright(
    username: config_get('bright.username'),
    password: config_get('bright.password'),
);

$table = new Console_Table();

echo $table->fromArray(
    headers: ['uuid', 'name'],
    data: $bright->find_virtualentity()
);

