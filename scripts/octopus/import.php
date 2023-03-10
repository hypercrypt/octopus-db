<?php

require_once __DIR__ . '/../../base.php';

$page_limit = config_get('octopus.pageLimit', allow_override: true);

$octopus = new OctopusEnergy(
    api_key: config_get('octopus.apiKey'),
    account_number: config_get('octopus.accountNumber'),
);

$octopus->graphql_auth();

$agreements = [
    ...$octopus->electricity_agreements,
    ...$octopus->gas_agreements,
];

$tariff_statement = db()
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

if (config_get('octopus.powerHour', default: false, allow_override: true))
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

        $reduced_rates_statement = db()->prepare(
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

$startTime = db()
    ->query('SELECT min(`valid_from`) FROM `tariff` LIMIT 1')
    ->fetchColumn();

$tariffs = db()
    ->query('SELECT DISTINCT `tariff` FROM `tariff`')
    ->fetchAll(PDO::FETCH_COLUMN);

$price_statement = db()->prepare('
    REPLACE INTO `price` VALUES (
        :start,
        :end,
        :value_exc_vat,
        :value_inc_vat,
        NULL,
        :tariff,
        :type
    )
');

foreach ($tariffs as $tariff)
{
    $page = 1;
    $hasNext = true;

    while ($hasNext && $page <= $page_limit) {
        $priceDataArray = $octopus->get_prices($tariff, $page++);

        foreach ($priceDataArray as $type => $priceData)
        {
            $hasNext = $priceData?->next != null;
            $prices = $priceData?->results ?? [];
            foreach ($prices as $price) {
                $end = ($price->valid_to == null) ? 2147483647 : strtotime($price->valid_to);
                $start = ($price->valid_from == null) ? 0 : strtotime($price->valid_from);

                if ($end < $start) {
                    $hasNext = false;
                    continue;
                }

                $price_statement->execute([
                    'start' => $start,
                    'end' => $end,
                    'value_exc_vat' => $price->value_exc_vat,
                    'value_inc_vat' => $price->value_inc_vat,
                    'tariff' => $tariff,
                    'type' => $type,
                ]);
            }
        }
    }
}

$usage_statement = db()->prepare('
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
                              ON  r.valid_from <= :start
                              AND r.valid_to   >= :end
                      WHERE p.valid_from <= :start
                        AND p.valid_to   >= :end
                        AND p.tariff      = :tariff
                        AND p.rate_type   = :rate_type
                      LIMIT 1');
$tariff_data_statement = db()->prepare('
                        SELECT `tariff`
                        FROM `tariff`
                        WHERE `valid_from` <= :start
                          AND `valid_to`   >= :end
                          AND SUBSTR(`tariff`, 1, 1) = :fuel
                        LIMIT 1
');

try {
    $e7_start = time_as_decimal(config_get('octopus.e7.start','00:30'));
    $e7_end = time_as_decimal(config_get('octopus.e7.end', '07:30')) - 0.5; // start time of last 30m slot
} catch (Throwable $throwable) {
    die($throwable);
}

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

                $tariff = $tariff_data_statement->fetchColumn();

                $rate_type = 'standard';

                if (str_starts_with(
                    haystack: $tariff,
                    needle: 'E-2R'
                ))
                {
                    try {
                        $rate_type = is_time_between($e7_start, $e7_end, time_as_decimal($start)) ? 'night' : 'day';
                    } catch (Exception) {
                        continue;
                    }
                }

                $usage_statement->execute([
                    'start' => $start,
                    'end' => $end,
                    'consumption' => $usage->consumption,
                    'meter' => $meter,
                    'tariff' => $tariff,
                    'fuel' => $fuel,
                    'rate_type' => $rate_type
                ]);
            }
        }
    } while (isset($usageData->next) && $usageData->next && $page <= $page_limit);
}

/**
 * @throws Exception
 */
function time_as_decimal(string|int|DateTimeInterface $date_or_string_or_int): float
{
    if (is_string($date_or_string_or_int))
    {
        $parts = explode(
            separator: ':',
            string: $date_or_string_or_int,
            limit: 2
        );

        if (count($parts) === 2)
        {
            return ((int)$parts[0]) + ((int)$parts[1]) / 60.0;
        }
        else
        {
            throw new Exception(
                message: "\$date_or_string_or_int needs to be in the format 00:00[:00], $date_or_string_or_int found."
            );
        }
    }
    elseif (is_int($date_or_string_or_int))
    {
        return ($date_or_string_or_int % 86_400) / 3_600.0;
    }
    else
    {
        return time_as_decimal($date_or_string_or_int->getTimestamp());
    }
}

/**
 * @throws Exception
 */
function is_time_between(float|string $start, float|string $end, float|string $needle): bool
{
    if (is_string($start))  $start  = time_as_decimal($start);
    if (is_string($end))    $end    = time_as_decimal($end);
    if (is_string($needle)) $needle = time_as_decimal($needle);

    if ($start < $end)
    {
        return (
            ($start <= $needle) &&
            ($end   >= $needle)
        );
    }
    elseif ($start === $end)
    {
        return false;
    }
    else // e.g. 22:30 - 04:30
    {
        return ($needle >= $start) || ($needle <= $end);
    }
}

