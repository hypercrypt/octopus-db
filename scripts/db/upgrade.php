<?php

try {

    if (count(db()->query("SHOW COLUMNS FROM `price` LIKE 'rate_type'")->fetchAll()) === 0)
    {
        db()->exec("
            ALTER TABLE `price`
            DROP PRIMARY KEY,
            ADD `rate_type` ENUM('day','night','standard') NOT NULL DEFAULT 'standard' AFTER `tariff`,
            ADD PRIMARY KEY (`valid_from`, `valid_to`, `tariff`, `rate_type`)
        ");
    }

    if (count(db()->query("SHOW TABLE STATUS WHERE `Name` = '__tokens__'")->fetchAll()) === 0)
    {
        db()->exec("
            CREATE TABLE `__tokens__` (
              `service` char(10) NOT NULL,
              `username` varchar(128) NOT NULL,
              `token` varchar(10000) DEFAULT NULL,
              `refresh_token` varchar(5000) DEFAULT NULL,
              `other_info` varchar(5000) DEFAULT NULL,
              PRIMARY KEY (`service`,`username`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
        ");
    }
} catch (Throwable $throwable) {
    echo $throwable, PHP_EOL;
}
