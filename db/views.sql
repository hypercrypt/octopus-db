# ************************************************************
# MySQL dump
# ************************************************************

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
SET NAMES utf8mb4;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE='NO_AUTO_VALUE_ON_ZERO', SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

START TRANSACTION;

# Dump of view saving_session_h
# ------------------------------------------------------------
CREATE OR REPLACE VIEW `saving_session_h`
AS SELECT
   `saving_session`.`id` AS `id`,
   `saving_session`.`code` AS `code`,
   FROM_UNIXTIME(`saving_session`.`start_at`) AS `start_at`,
   FROM_UNIXTIME(`saving_session`.`end_at`) AS `end_at`,
   `saving_session`.`points` AS `points`,
   FLOOR((`saving_session`.`points` / 8)) AS `reward`
FROM `saving_session`
order by FROM_UNIXTIME(`saving_session`.`start_at`) desc;

# Dump of view price_h
# ------------------------------------------------------------
CREATE OR REPLACE VIEW `price_h`
AS SELECT
   FROM_UNIXTIME(`price`.`valid_from`) AS `valid_from`,
   FROM_UNIXTIME(`price`.`valid_to`) AS `valid_to`,
   `price`.`value_exc_vat` AS `value_exc_vat`,
   `price`.`value_inc_vat` AS `value_inc_vat`,
   `price`.`comment` AS `comment`,
   `price`.`tariff` AS `tariff`,
   `price`.`rate_type` AS `rate_type`
FROM `price`;

# Dump of view reduced_rates_h
# ------------------------------------------------------------
CREATE OR REPLACE VIEW `reduced_rates_h`
AS SELECT
   FROM_UNIXTIME(`reduced_rates`.`valid_from`) AS `valid_from`,
   FROM_UNIXTIME(`reduced_rates`.`valid_to`)   AS `valid_to`,
   `reduced_rates`.`value_exc_vat`             AS `value_exc_vat`,
   `reduced_rates`.`value_inc_vat`             AS `value_inc_vat`,
   `reduced_rates`.`comment`                   AS `comment`
FROM `reduced_rates`
order by FROM_UNIXTIME(`reduced_rates`.`valid_to`) desc;

# Dump of view tariff_h
# ------------------------------------------------------------
CREATE OR REPLACE VIEW `tariff_h`
AS SELECT
   from_unixtime(`tariff`.`valid_from`) AS `valid_from`,
   from_unixtime(`tariff`.`valid_to`) AS `valid_to`,
   `tariff`.`tariff` AS `tariff`,
   `tariff`.`offpeakThreshold` AS `offpeakThreshold`
FROM `tariff`;

# Dump of view usage_h
# ------------------------------------------------------------
CREATE OR REPLACE VIEW `usage_h`
AS SELECT
    FROM_UNIXTIME(`usage`.`interval_start`) AS `interval_start`,
    FROM_UNIXTIME(`usage`.`interval_end`) AS `interval_end`,
    `usage`.`consumption` AS `consumption`,
    `usage`.`meter` AS `meter`,
    `usage`.`tariff` AS `tariff`,
    `usage`.`fuel` AS `fuel`,
    `usage`.`rate_exc_vat` AS `rate_exc_vat`,
    `usage`.`rate_inc_vat` AS `rate_inc_vat`,
    `usage`.`price_exc_vat` AS `price_exc_vat`,
    `usage`.`price_inc_vat` AS `price_inc_vat`,
    `usage`.`reduced_rate_inc_vat` AS `reduced_rate_inc_vat`,
    `usage`.`reduced_rate_exc_vat` AS `reduced_rate_exc_vat`,
    `usage`.`reduced_price_exc_vat` AS `reduced_price_exc_vat`,
    `usage`.`reduced_price_inc_vat` AS `reduced_price_inc_vat`
FROM `usage`;



DROP VIEW IF EXISTS `billing_e_hh`;
CREATE VIEW `billing_e_hh` AS
SELECT
    FROM_UNIXTIME(`u`.`interval_start`)                                                      AS `period`,
    ROUND(`u`.`consumption`, 3)                                                              AS `kwh_total`,
    ROUND(IF((`u`.`reduced_rate_inc_vat` >  `t`.`offpeakThreshold`), `u`.`consumption`,0),3) AS `kwh_peak`,
    ROUND(IF((`u`.`reduced_rate_inc_vat` <= `t`.`offpeakThreshold`), `u`.`consumption`,0),3) AS `kWh_offpeak`,
    ROUND((`u`.`reduced_price_inc_vat` / 100),6)                                             AS `cost`,
    ROUND(`u`.`reduced_rate_inc_vat`,4)                                                      AS `ppkwh`,
    `u`.`tariff`                                                                             AS `tariffs`
FROM `usage` `u`
         LEFT JOIN `tariff` `t`
                   ON `u`.`tariff` = `t`.`tariff`
                       AND `u`.`interval_start` >= `t`.`valid_from`
                       AND `u`.`interval_end`   <= `t`.`valid_to`
WHERE (`u`.`fuel` = 'electricity')
ORDER BY `u`.`interval_start` DESC;

DROP VIEW IF EXISTS `billing_e_h`; # This is now called `billing_e_hour`
#
# CREATE OR REPLACE VIEW `billing_e_h` AS
# SELECT
#     CONCAT(SUBSTR(`period`, 1, 14), '00:00')                                           AS `period`,
#     SUM(`kwh_total`)                                                                   AS `kwh_total`,
#     SUM(`kwh_peak`)                                                                    AS `kwh_peak`,
#     SUM(`kWh_offpeak`)                                                                 AS `kWh_offpeak`,
#     IF(SUM(`kwh_total`)=0, 0.0, ROUND(SUM(`kWh_offpeak`) / SUM(`kwh_total`) * 100, 1)) AS `percent_offpeak`,
#     SUM(`cost`)                                                                        AS `cost`,
#     ROUND(
#             IF(MIN(`ppkwh`) = MAX(`ppkwh`) || SUM(`kwh_total`) = 0,
#                AVG(`ppkwh`),
#                SUM(`cost`) / SUM(`kwh_total`) * 100
#                 ),
#             2
#         )                                                                              AS `ppkwh`,
#     GROUP_CONCAT(DISTINCT `tariffs`)                                                   AS `tariffs`
# FROM billing_e_hh
# GROUP BY CONCAT(SUBSTR(`period`, 1, 14), '00:00')
# ORDER BY `period` DESC;
#
# DROP VIEW IF EXISTS `billing_e_day`;
# CREATE VIEW `billing_e_day` AS
# SELECT
#     SUBSTR(`period`, 1, 10)                                                          AS `period`,
#     SUM(`kwh_total`)                                                                 AS `kwh_total`,
#     SUM(`kwh_peak`)                                                                  AS `kwh_peak`,
#     SUM(`kWh_offpeak`)                                                               AS `kWh_offpeak`,
#     IF(SUM(`kwh_total`)=0, 0, ROUND(SUM(`kWh_offpeak`) / SUM(`kwh_total`) * 100, 1)) AS `percent_offpeak`,
#     SUM(`cost`)                                                                      AS `cost`,
#     IF(SUM(`kwh_total`)=0, 0, ROUND(SUM(`cost`) / SUM(`kwh_total`) * 100, 2))        AS `pperkwh`,
#     GROUP_CONCAT(DISTINCT `tariffs`)                                                 AS `tariffs`
# FROM billing_e_hh
# GROUP BY SUBSTR(`period`, 1, 10)
# ORDER BY `period` DESC;
#
# DROP VIEW IF EXISTS `billing_e_week`;
# CREATE VIEW `billing_e_week` AS
# SELECT
#     DATE_FORMAT(SUBSTR(`period`, 1, 10), '%xwk%v')                                   AS `period`,
#     SUM(`kwh_total`)                                                                 AS `kwh_total`,
#     SUM(`kwh_peak`)                                                                  AS `kwh_peak`,
#     SUM(`kWh_offpeak`)                                                               AS `kWh_offpeak`,
#     IF(SUM(`kwh_total`)=0, 0, ROUND(SUM(`kWh_offpeak`) / SUM(`kwh_total`) * 100, 1)) AS `percent_offpeak`,
#     SUM(`cost`)                                                                      AS `cost`,
#     IF(SUM(`kwh_total`)=0, 0, ROUND(SUM(`cost`) / SUM(`kwh_total`) * 100, 2))        AS `pperkwh`,
#     GROUP_CONCAT(DISTINCT `tariffs`)                                                 AS `tariffs`,
#     CONCAT(MIN(SUBSTR(`period`, 1, 10)), ' ~> ', MAX(SUBSTR(`period`, 1, 10)))       AS `dates`
# FROM billing_e_hh
# GROUP BY DATE_FORMAT(SUBSTR(`period`, 1, 10), '%xwk%v')
# ORDER BY `period` DESC;
#
# DROP VIEW IF EXISTS `billing_e_month`;
# CREATE VIEW `billing_e_month` AS
# SELECT
#     SUBSTR(`period`, 1, 7)                                                           AS `period`,
#     SUM(`kwh_total`)                                                                 AS `kwh_total`,
#     SUM(`kwh_peak`)                                                                  AS `kwh_peak`,
#     SUM(`kWh_offpeak`)                                                               AS `kWh_offpeak`,
#     IF(SUM(`kwh_total`)=0, 0, ROUND(SUM(`kWh_offpeak`) / SUM(`kwh_total`) * 100, 1)) AS `percent_offpeak`,
#     SUM(`cost`)                                                                      AS `cost`,
#     IF(SUM(`kwh_total`)=0, 0, ROUND(SUM(`cost`) / SUM(`kwh_total`) * 100, 2))        AS `pperkwh`,
#     GROUP_CONCAT(DISTINCT `tariffs`)                                                 AS `tariffs`
# FROM billing_e_hh
# GROUP BY SUBSTR(`period`, 1, 7)
# ORDER BY `period` DESC;
#
# DROP VIEW IF EXISTS `billing_e_year`;
# CREATE VIEW `billing_e_year` AS
# SELECT
#     SUBSTR(`period`, 1, 4)                                                           AS `period`,
#     SUM(`kwh_total`)                                                                 AS `kwh_total`,
#     SUM(`kwh_peak`)                                                                  AS `kwh_peak`,
#     SUM(`kWh_offpeak`)                                                               AS `kWh_offpeak`,
#     IF(SUM(`kwh_total`)=0, 0, ROUND(SUM(`kWh_offpeak`) / SUM(`kwh_total`) * 100, 1)) AS `percent_offpeak`,
#     SUM(`cost`)                                                                      AS `cost`,
#     IF(SUM(`kwh_total`)=0, 0, ROUND(SUM(`cost`) / SUM(`kwh_total`) * 100, 2))        AS `pperkwh`,
#     GROUP_CONCAT(DISTINCT `tariffs`)                                                 AS `tariffs`
# FROM billing_e_hh
# GROUP BY SUBSTR(`period`, 1, 4)
# ORDER BY `period` DESC;

/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

COMMIT;