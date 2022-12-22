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

# Dump of table price
# ------------------------------------------------------------

DROP TABLE IF EXISTS `price`;

CREATE TABLE `price` (
  `valid_from` int unsigned NOT NULL,
  `valid_to` int unsigned NOT NULL,
  `value_exc_vat` decimal(8,4) NOT NULL,
  `value_inc_vat` decimal(8,4) NOT NULL,
  `comment` char(128) DEFAULT NULL,
  `tariff` char(128) NOT NULL,
  PRIMARY KEY (`valid_from`,`valid_to`,`tariff`),
  KEY `valid_from` (`valid_from`),
  KEY `valid_to` (`valid_to`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

# Dump of table reduced_rates
# ------------------------------------------------------------

DROP TABLE IF EXISTS `reduced_rates`;

CREATE TABLE `reduced_rates` (
  `valid_from` int unsigned NOT NULL,
  `valid_to` int unsigned NOT NULL,
  `value_exc_vat` decimal(8,4) NOT NULL,
  `value_inc_vat` decimal(8,4) NOT NULL,
  `comment` varchar(128) DEFAULT NULL,
  PRIMARY KEY (`valid_from`),
  UNIQUE KEY `valid_to` (`valid_to`),
  KEY `value_exc_vat` (`value_exc_vat`),
  KEY `value_exc_vat_2` (`value_exc_vat`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

# Dump of table saving_session
# ------------------------------------------------------------

DROP TABLE IF EXISTS `saving_session`;

CREATE TABLE `saving_session` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `code` char(50) NOT NULL,
  `start_at` int NOT NULL,
  `end_at` int NOT NULL,
  `octopoints_per_kwh` int NOT NULL DEFAULT '1800',
  `total_participants` int DEFAULT NULL,
  `points` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=860 DEFAULT CHARSET=utf8mb3;

# Dump of table tariff
# ------------------------------------------------------------

DROP TABLE IF EXISTS `tariff`;

CREATE TABLE `tariff` (
  `valid_from` int unsigned NOT NULL,
  `valid_to` int unsigned NOT NULL,
  `tariff` varchar(128) NOT NULL,
  `offpeakThreshold` decimal(8,4) NOT NULL DEFAULT '0.0000',
  PRIMARY KEY (`valid_from`,`valid_to`,`tariff`),
  KEY `valid_from` (`valid_from`),
  KEY `valid_to` (`valid_to`),
  KEY `tariff` (`tariff`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

# Dump of table usage
# ------------------------------------------------------------

DROP TABLE IF EXISTS `usage`;

CREATE TABLE `usage` (
  `interval_start` int unsigned NOT NULL,
  `interval_end` int unsigned NOT NULL,
  `consumption` decimal(12,4) NOT NULL,
  `meter` char(128) NOT NULL,
  `tariff` char(128) NOT NULL,
  `fuel` enum('electricity','gas') NOT NULL,
  `rate_exc_vat` decimal(8,4) DEFAULT NULL,
  `rate_inc_vat` decimal(8,4) DEFAULT NULL,
  `price_exc_vat` decimal(8,4) DEFAULT NULL,
  `price_inc_vat` decimal(8,4) DEFAULT NULL,
  `reduced_rate_exc_vat` decimal(8,4) DEFAULT NULL,
  `reduced_rate_inc_vat` decimal(8,4) DEFAULT NULL,
  `reduced_price_exc_vat` decimal(8,4) DEFAULT NULL,
  `reduced_price_inc_vat` decimal(8,4) DEFAULT NULL,
  PRIMARY KEY (`interval_start`,`interval_end`,`tariff`),
  KEY `tariff` (`tariff`),
  KEY `fuel` (`fuel`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

COMMIT;