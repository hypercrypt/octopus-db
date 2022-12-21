<?php

ini_set(option: 'memory_limit', value: '4G');

require_once __DIR__ . '/classes/OctopusEnergy.php';

$config = require  __DIR__ . '/config.php';

$page_limit = $config['octopus']['pageLimit'];

$database_connection = new PDO(...$config['db']);

$octopus = new OctopusEnergy(
    api_key: $config['octopus']['apiKey'],
    account_number: $config['octopus']['accountNumber'],
);

$agreements = [
    ...$octopus->electricity_agreements,
    ...$octopus->gas_agreements,
];

$tariff_statement = $database_connection
    ->prepare('REPLACE INTO `tariff` VALUES (:valid_from, :valid_to, :tariff, :offpeak)');

foreach ($agreements as $agreement) {
    $valid_from = strtotime($agreement->valid_from);
    $valid_to   = ($agreement->valid_to) ? strtotime($agreement->valid_to) : 2147483647;

    if ($valid_from !== $valid_to)
    {
        $tariff_statement->execute([
            'valid_from' => $valid_from,
            'valid_to'   => $valid_to,
            'tariff'     => $agreement->tariff_code,
            'offpeak'    => 10.0
        ]);
    }
}

if ($config['octopus']['powerHour'])
{
    $cheap_slots = [];

    try {
        $cheap_slots_data = json_decode((new GuzzleHttp\Client())
            ->get(uri: 'https://oeapi.hypercrypt.solutions/cheap-slots.json')
            ->getBody()
        );

        $tz_from = new DateTimeZone('Europe/London');
        foreach ([...$cheap_slots_data->upcoming, ...$cheap_slots_data->past] as $cheap_slot) {
            try {
                $start = new DateTime(datetime: $cheap_slot->start, timezone: $tz_from);
                $end = new DateTime(datetime: $cheap_slot->end, timezone: $tz_from);

                $cheap_slots[] = [
                    $start->getTimestamp(),
                    $end->getTimestamp(),
                    $cheap_slot->price_exc_vat,
                    $cheap_slot->price_inc_vat,
                    $cheap_slot->reason,
                ];
            } catch (Exception) {
            }
        }

        $reduced_rates_statement = $database_connection->prepare(
            "REPLACE INTO reduced_rates VALUES (
                    :valid_from,
                    :step,
                    :price_exc_vat,
                    :price_inc_vat,
                    :comment
                )"
        );
        foreach ($cheap_slots as $cheap_slot) {
            $valid_from = $cheap_slot[0];
            $valid_to = $cheap_slot[1];
            $step = $valid_from + 30 * 60;

            $price_exc_vat = $cheap_slot[2];
            $price_inc_vat = $cheap_slot[3];
            while ($step <= $valid_to) {
                $reduced_rates_statement->execute([
                    'valid_from' => $valid_from,
                    'step' => $step,
                    'price_exc_vat' => $price_exc_vat,
                    'price_inc_vat' => $price_inc_vat,
                    'comment' => $cheap_slot[4],
                ]);

                $valid_from = $step;
                $step += 30 * 60;
            }
        }
    } catch (Throwable) {}
}

$startTime = $database_connection
    ->query('SELECT min(`valid_from`) FROM `tariff` LIMIT 1')
    ->fetchColumn();

$tariffs = $database_connection
    ->query('SELECT DISTINCT `tariff` FROM `tariff`')
    ->fetchAll(PDO::FETCH_COLUMN);

$price_statement = $database_connection->prepare(
    'REPLACE INTO `price` VALUES (
         :start,
         :end,
         :value_exc_vat,
         :value_inc_vat,
         NULL,
         :tariff
     )'
);
foreach ($tariffs as $tariff)
{
    $page = 1;
    $hasNext = true;


    while ($hasNext && $page <= $page_limit) {
        $priceData = $octopus->get_prices($tariff, $page++);
        $hasNext = $priceData?->next != null;
        $prices = $priceData?->results ?? [];
        foreach ($prices as $price) {
            $end   = ($price->valid_to   == null) ? 2147483647 : strtotime($price->valid_to);
            $start = ($price->valid_from == null) ?          0 : strtotime($price->valid_from);

            if ($end < $start)
            {
                $hasNext = false;
                continue;
            }
            $q = "REPLACE INTO `price` VALUES ($start, $end, $price->value_exc_vat, $price->value_inc_vat, NULL, '$tariff');";
            $price_statement->execute([
                'start' => $start,
                'end' => $end,
                'value_exc_vat' => $price->value_exc_vat,
                'value_inc_vat' => $price->value_inc_vat,
                'tariff' => $tariff,
            ]);
        }
    }
}

$usage_statement = $database_connection->prepare('
                      REPLACE INTO `usage`
                      SELECT
                          :start,
                          :end,
                          :consumption,
                          :meter,
                          :tariff,
                          :fuel,
                          p.value_exc_vat,
                          p.value_inc_vat,
                          p.value_exc_vat * :consumption,
                          p.value_inc_vat * :consumption,
                          IFNULL(r.value_exc_vat, p.value_exc_vat),
                          IFNULL(r.value_inc_vat, p.value_inc_vat),
                          IFNULL(r.value_exc_vat, p.value_exc_vat) * :consumption,
                          IFNULL(r.value_inc_vat, p.value_inc_vat) * :consumption
                      FROM `price` AS p
                          LEFT JOIN `reduced_rates` AS r
                              ON  r.valid_from<=:start
                              AND r.valid_to>=:end
                      WHERE p.valid_from<=:start
                        AND p.valid_to>=:end
                        AND p.tariff=:tariff
                      LIMIT 1');
$tariff_data_statement = $database_connection->prepare('
                        SELECT `tariff`
                        FROM `tariff`
                        WHERE `valid_from` <= :start
                          AND `valid_to`   >= :end
                          AND SUBSTR(`tariff`, 1, 1) = :fuel
                        LIMIT 1'
);
foreach ($octopus->meters as $meter => $details)
{
    $fuel = $details[0];
    $mpoint = $details[1];
    $page = 1;
    $tariff = null;
    do {
        echo "fetching $meter's $fuel usage page #$page", PHP_EOL;
        $usageData = $octopus->get_usage(
            meter: $meter,
            page: $page++,
            fuel: $fuel,
            mpoint: $mpoint
        );

        if (isset($usageData->results))
        {
            $usages = $usageData->results;
            foreach ($usages as $usage)
            {
                $start = strtotime($usage->interval_start);
                $end   = strtotime($usage->interval_end);

                $tariff_data_statement->execute([
                    'start' => $start,
                    'end' => $end,
                    'fuel' => strtoupper(substr($fuel, 0, 1))
                ]);

                $usage_statement->execute([
                    'start' => $start,
                    'end' => $end,
                    'consumption' => $usage->consumption,
                    'meter' => $meter,
                    'tariff' => $tariff_data_statement->fetchColumn(),
                    'fuel' => $fuel
                ]);
            }
        }
    } while (isset($usageData->next) && $usageData->next && $page <= $page_limit);
}
