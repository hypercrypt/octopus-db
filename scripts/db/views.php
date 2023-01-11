<?php

echo 'This may delete data in your database, are you sure? (y/N): ';
$sure = strtolower(fgets(fopen('php://stdin','r')));

if (str_starts_with($sure, 'y'))
{
    $sql = file_get_contents(__DIR__ . '/../../db/views.sql');
    db()->exec($sql);

    $billing_views = [
        'hour'  => ["CONCAT(SUBSTR(`period`, 1, 14), '00:00')", ''],
        'day'   => ["SUBSTR(`period`, 1, 10)", ''],
        'week'  => ["DATE_FORMAT(SUBSTR(`period`, 1, 10), '%xwk%v')", ", CONCAT(MIN(SUBSTR(`period`, 1, 10)), ' ~> ', MAX(SUBSTR(`period`, 1, 10))) AS `dates`"],
        'month' => ["SUBSTR(`period`, 1, 7)", ''],
        'year'  => ["SUBSTR(`period`, 1, 4)", ''],
    ];

    foreach ($billing_views as $name => [$period, $additional])
    {
        db()->exec("
            CREATE OR REPLACE VIEW `billing_e_$name` AS
            SELECT
                $period                                                                            AS `period`,
                SUM(`kwh_total`)                                                                   AS `kwh_total`,
                SUM(`kwh_peak`)                                                                    AS `kwh_peak`,
                SUM(`kWh_offpeak`)                                                                 AS `kWh_offpeak`,
                IF(SUM(`kwh_total`)=0, 0.0, ROUND(SUM(`kWh_offpeak`) / SUM(`kwh_total`) * 100, 1)) AS `percent_offpeak`,
                SUM(`cost`)                                                                        AS `cost`,
                ROUND(
                    IF(MIN(`ppkwh`) = MAX(`ppkwh`) || SUM(`kwh_total`) = 0,
                        AVG(`ppkwh`),
                        SUM(`cost`) / SUM(`kwh_total`) * 100
                    ),
                    2
                )                                                                                  AS `ppkwh`,
                GROUP_CONCAT(DISTINCT `tariffs`)                                                   AS `tariffs`
                $additional
            FROM billing_e_hh
            GROUP BY $period
            ORDER BY `period` DESC;
        ");
    }
}