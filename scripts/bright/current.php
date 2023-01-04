<?php

require_once __DIR__ . '/../../base.php';

$bright = new Bright(
    username: config_get('bright.username'),
    password: config_get('bright.password'),
);

$data = [];

foreach (config_get('bright.resourceIds') as $fuel => $resourceId)
{
    if ($fuel != 'gas')
        $data[$fuel] = $bright->current_usage($resourceId);
}

switch (strtolower($argv[2] ?? 'table'))
{
    case 'json':
        echo json_encode($data, JSON_PRETTY_PRINT), PHP_EOL;
        break;
    case 'csv':
        echo 'fuel,reading', PHP_EOL;
        foreach ($data as $fuel => $reading) echo $fuel, ',', $reading, PHP_EOL;
        break;
    case 'table':
    default:
        $table = new Console_Table();
        echo $table->fromArray(
            headers: ['fuel', 'reading'],
            data: collect($data)->map(fn($r, $f) => [$f, $r])->all()
        );
        break;
}
