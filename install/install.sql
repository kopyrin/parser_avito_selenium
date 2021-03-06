CREATE TABLE IF NOT EXISTS `ads` (
`id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
`url` VARCHAR(255) NOT NULL DEFAULT '',
`name` VARCHAR(32) NOT NULL DEFAULT '',
`seller` VARCHAR(32) NOT NULL DEFAULT '',
`item_id` CHAR(32) NOT NULL DEFAULT '',
`description` VARCHAR(5120) NOT NULL DEFAULT '',
`price` int(11) NOT NULL DEFAULT 0,
`phone` CHAR(16) NOT NULL DEFAULT '',
`show` int(11)  NOT NULL DEFAULT 0,
`show_today` int(11)  NOT NULL DEFAULT 0,
PRIMARY KEY (`id`),
UNIQUE KEY `idx_case` (`name`, `url`)
) ENGINE = INNODB DEFAULT CHARSET=utf8;