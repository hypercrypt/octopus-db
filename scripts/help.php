octo Help

This help is not yet complete

./octo [command] [options]
php octo [command] [options]

For this help run 'octo help'

<?php

require_once __DIR__ . '/../vendor/autoload.php';

$table = new Console_Table();

$table->setHeaders(headers: ['Command', 'Parameters', 'Notes']);
$table->addRow(['db/upgrade','','Updates the database to the latest schema']);

$table->addSeparator();
$table->addRow([
        'octopus/import',

        'pageLimit - optional - overwrite the value from setting' . PHP_EOL .
        'powerHour - optional - overwrite the value from settings',

        'Import data from Octopus API'
]);

$table->addSeparator();
$table->addRow([
    'bright/import',
    '',
    'Import data from the Bright API.'
]);

die();

?>