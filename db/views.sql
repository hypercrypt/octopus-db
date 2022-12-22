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

DROP TABLE IF EXISTS `saving_session_h`; DROP VIEW IF EXISTS `saving_session_h`;

CREATE VIEW `saving_session_h`
AS SELECT
   `saving_session`.`id` AS `id`,
   `saving_session`.`code` AS `code`,
   from_unixtime(`saving_session`.`start_at`) AS `start_at`,
   from_unixtime(`saving_session`.`end_at`) AS `end_at`,
   `saving_session`.`points` AS `points`,
   floor((`saving_session`.`points` / 8)) AS `reward`
FROM `saving_session`
order by from_unixtime(`saving_session`.`start_at`) desc;

# Dump of view price_h
# ------------------------------------------------------------

DROP TABLE IF EXISTS `price_h`; DROP VIEW IF EXISTS `price_h`;

CREATE VIEW `price_h`
AS SELECT
   from_unixtime(`price`.`valid_from`) AS `valid_from`,
   from_unixtime(`price`.`valid_to`) AS `valid_to`,
   `price`.`value_exc_vat` AS `value_exc_vat`,
   `price`.`value_inc_vat` AS `value_inc_vat`,
   `price`.`comment` AS `comment`,
   `price`.`tariff` AS `tariff`,
   `price`.`rate_type` AS `rate_type`
FROM `price`;

# Dump of view reduced_rates_h
# ------------------------------------------------------------

DROP TABLE IF EXISTS `reduced_rates_h`; DROP VIEW IF EXISTS `reduced_rates_h`;

CREATE VIEW `reduced_rates_h`
AS SELECT
   from_unixtime(`reduced_rates`.`valid_from`) AS `valid_from`,
   from_unixtime(`reduced_rates`.`valid_to`) AS `valid_to`,
   `reduced_rates`.`value_exc_vat` AS `value_exc_vat`,
   `reduced_rates`.`value_inc_vat` AS `value_inc_vat`,
   `reduced_rates`.`comment` AS `comment`
FROM `reduced_rates`
order by from_unixtime(`reduced_rates`.`valid_to`) desc;

# Dump of view tariff_h
# ------------------------------------------------------------

DROP TABLE IF EXISTS `tariff_h`; DROP VIEW IF EXISTS `tariff_h`;

CREATE VIEW `tariff_h`
AS SELECT
   from_unixtime(`tariff`.`valid_from`) AS `valid_from`,
   from_unixtime(`tariff`.`valid_to`) AS `valid_to`,
   `tariff`.`tariff` AS `tariff`,
   `tariff`.`offpeakThreshold` AS `offpeakThreshold`
FROM `tariff`;

# Dump of view usage_h
# ------------------------------------------------------------

DROP TABLE IF EXISTS `usage_h`; DROP VIEW IF EXISTS `usage_h`;

CREATE VIEW `usage_h`
AS SELECT
   from_unixtime(`usage`.`interval_start`) AS `interval_start`,
   from_unixtime(`usage`.`interval_end`) AS `interval_end`,
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



# Dump of view billing_e_day
# ------------------------------------------------------------

DROP TABLE IF EXISTS `billing_e_day`; DROP VIEW IF EXISTS `billing_e_day`;

CREATE VIEW `billing_e_day`
AS SELECT
   cast(from_unixtime(`u`.`interval_start`) as date) AS `date`,
   round(sum(if((`u`.`reduced_rate_inc_vat` <= 10),`u`.`consumption`,0)),3) AS `off-peak kWh`,
   round(sum(if((`u`.`reduced_rate_inc_vat` > 10),`u`.`consumption`,0)),3) AS `peak kWh`,
   round(sum(`u`.`consumption`),3) AS `total kWh`,
   round((sum(`u`.`reduced_price_inc_vat`) / 100),2) AS `cost`,
   round((sum(`u`.`reduced_price_inc_vat`) / sum(`u`.`consumption`)),2) AS `p/kWh`,
   group_concat(distinct `u`.`tariff` separator ',') AS `tariffs`
FROM `usage` `u`
where (`u`.`fuel` = 'electricity')
group by `date`
order by `date` desc;

# Dump of view billing_e_year
# ------------------------------------------------------------

DROP TABLE IF EXISTS `billing_e_year`; DROP VIEW IF EXISTS `billing_e_year`;

CREATE VIEW `billing_e_year`
AS SELECT
   date_format(`billing_e_day`.`date`,'%Y') AS `year`,
   round(sum(`billing_e_day`.`off-peak kWh`),3) AS `off-peak kWh`,
   round(sum(`billing_e_day`.`peak kWh`),3) AS `peak kWh`,
   round(sum(`billing_e_day`.`total kWh`),3) AS `total kWh`,
   round(sum(`billing_e_day`.`cost`),2) AS `cost`,
   round(((sum(`billing_e_day`.`cost`) / sum(`billing_e_day`.`total kWh`)) * 100),2) AS `p/kWh`,
   group_concat(distinct `billing_e_day`.`tariffs` separator ',') AS `tariffs`
FROM `billing_e_day`
group by `year`
order by `year` desc;

# Dump of view billing_e_hh
# ------------------------------------------------------------

DROP TABLE IF EXISTS `billing_e_hh`; DROP VIEW IF EXISTS `billing_e_hh`;

CREATE VIEW `billing_e_hh`
AS SELECT
   from_unixtime(`u`.`interval_start`) AS `time`,
   round(if((`u`.`reduced_rate_inc_vat` <= 10),`u`.`consumption`,0),4) AS `off-peak kWh`,
   round(if((`u`.`reduced_rate_inc_vat` > 10),`u`.`consumption`,0),4) AS `peak kWh`,
   round(`u`.`consumption`,3) AS `total kWh`,
   round((`u`.`reduced_price_inc_vat` / 100),6) AS `cost`,
   round(`u`.`reduced_rate_inc_vat`,4) AS `p/kWh`,
   `u`.`tariff` AS `tariffs`
FROM `usage` `u`
where (`u`.`fuel` = 'electricity')
order by `u`.`interval_start` desc;

# Dump of view billing_e_month
# ------------------------------------------------------------

DROP TABLE IF EXISTS `billing_e_month`; DROP VIEW IF EXISTS `billing_e_month`;

CREATE VIEW `billing_e_month`
AS SELECT
   date_format(`billing_e_day`.`date`,'%Y-%m') AS `month`,
   round(sum(`billing_e_day`.`off-peak kWh`),3) AS `off-peak kWh`,
   round(sum(`billing_e_day`.`peak kWh`),3) AS `peak kWh`,
   round(sum(`billing_e_day`.`total kWh`),3) AS `total kWh`,
   round(((100 * sum(`billing_e_day`.`off-peak kWh`)) / sum(`billing_e_day`.`total kWh`)),1) AS `percent off-peak`,
   round(sum(`billing_e_day`.`cost`),2) AS `cost`,
   round(((sum(`billing_e_day`.`cost`) / sum(`billing_e_day`.`total kWh`)) * 100),2) AS `p/kWh`,
   group_concat(distinct `billing_e_day`.`tariffs` separator ',') AS `tariffs`
FROM `billing_e_day`
group by `month`
order by `month` desc;

# Dump of view billing_e_h
# ------------------------------------------------------------

DROP TABLE IF EXISTS `billing_e_h`; DROP VIEW IF EXISTS `billing_e_h`;

CREATE VIEW `billing_e_h`
AS SELECT
   concat(substr(from_unixtime(`u`.`interval_start`),1,14),'00') AS `hour`,
   round(sum(if((`u`.`reduced_rate_inc_vat` <= 7.5),`u`.`consumption`,0)),3) AS `off-peak kWh`,
   round(sum(if((`u`.`reduced_rate_inc_vat` > 7.5),`u`.`consumption`,0)),3) AS `peak kWh`,
   round(sum(`u`.`consumption`),3) AS `total kWh`,
   round((sum(`u`.`reduced_price_inc_vat`) / 100),2) AS `cost`,
   round((sum(`u`.`reduced_price_inc_vat`) / sum(`u`.`consumption`)),2) AS `p/kWh`,
   group_concat(distinct `u`.`tariff` separator ',') AS `tariffs`
FROM `usage` `u`
where (`u`.`fuel` = 'electricity')
group by `hour`
order by `hour` desc;

# Dump of view billing_e_week
# ------------------------------------------------------------

DROP TABLE IF EXISTS `billing_e_week`; DROP VIEW IF EXISTS `billing_e_week`;

CREATE VIEW `billing_e_week`
AS SELECT
   date_format(`billing_e_day`.`date`,'%xwk%v') AS `week`,
   round(sum(`billing_e_day`.`off-peak kWh`),3) AS `off-peak kWh`,
   round(sum(`billing_e_day`.`peak kWh`),3) AS `peak kWh`,
   round(sum(`billing_e_day`.`total kWh`),3) AS `total kWh`,
   round(sum(`billing_e_day`.`cost`),2) AS `cost`,
   round(((sum(`billing_e_day`.`cost`) / sum(`billing_e_day`.`total kWh`)) * 100),2) AS `p/kWh`,
   group_concat(distinct `billing_e_day`.`tariffs` separator ',') AS `tariffs`,
   concat(min(`billing_e_day`.`date`),' ~> ',max(`billing_e_day`.`date`)) AS `dates`
FROM `billing_e_day` group by `week` order by `week` desc;

/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

COMMIT;