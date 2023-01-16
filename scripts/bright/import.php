<?php

require_once __DIR__ . '/../../base.php';

$bright = new Bright(
    username: config_get('bright.username'),
    password: config_get('bright.password'),
);

$insert_statement = db()->prepare(query: "
    REPLACE INTO `usage` VALUES (
        :from,
        :to,
        :consumption,
        'api.glowmarkt.com',
        :tariff,
        :fuel,
        :rate_exc_vat,
        :rate_inc_vat,
        :price_exc_vat,
        :price_inc_vat,
        :reduced_rate_exc_vat,
        :reduced_rate_inc_vat,
        :reduced_price_exc_vat,
        :reduced_price_inc_vat
    )
");

foreach (config_get('bright.resourceIds') as $fuel => $resourceId)
{
    $start_time = db()
        ->query("
            SELECT REPLACE(FROM_UNIXTIME(MAX(`interval_end`)),' ','T') 
            FROM `usage`
            WHERE `meter` != 'api.glowmarkt.com' AND `fuel` = '$fuel'
            ORDER BY `interval_end` DESC 
            LIMIT 1
        ")
        ->fetchColumn();

    $now = new DateTimeImmutable();
    $end_time = $now->format('Y-m-d\TH:i:00');

    $usage_data = $bright->past_usage(
        resourceId: $resourceId,
        from: $start_time ?? $now->sub(new DateInterval('P9D'))->format('Y-m-d\TH:i:00'),
        to: $end_time
    );

    $tariff = OctopusEnergy::current_tariff($fuel);

    foreach ($usage_data as [$from, $consumption])
    {
        $to = $from + 30 * 60;

        $price = OctopusEnergy::select_price($from, $fuel);

        $insert_statement->execute(params: [
            'from'                  => $from,
            'to'                    => $to,
            'consumption'           => $consumption,
            'tariff'                => $tariff,
            'fuel'                  => $fuel,
            'rate_exc_vat'          => $price->rate_exc_vat,
            'rate_inc_vat'          => $price->rate_inc_vat,
            'price_exc_vat'         => $consumption * $price->rate_exc_vat,
            'price_inc_vat'         => $consumption * $price->rate_inc_vat,
            'reduced_rate_exc_vat'  => $price->reduced_rate_exc_vat,
            'reduced_rate_inc_vat'  => $price->reduced_rate_inc_vat,
            'reduced_price_exc_vat' => $consumption * $price->reduced_rate_exc_vat,
            'reduced_price_inc_vat' => $consumption * $price->reduced_rate_inc_vat,
        ]);
    }
}