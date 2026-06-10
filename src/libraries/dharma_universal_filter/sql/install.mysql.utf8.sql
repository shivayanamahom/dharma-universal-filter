CREATE TABLE IF NOT EXISTS `#__dharma_universal_filter_index` (
	`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	`category_id` INT UNSIGNED NOT NULL DEFAULT 0,
	`item_id` INT UNSIGNED NOT NULL DEFAULT 0,
	`field_name` VARCHAR(191) NOT NULL DEFAULT '',
	`field_value` VARCHAR(255) NOT NULL DEFAULT '',
	`field_value_hash` CHAR(40) NOT NULL DEFAULT '',
	`language` VARCHAR(7) NOT NULL DEFAULT '*',
	`in_stock` TINYINT NOT NULL DEFAULT 0,
	PRIMARY KEY (`id`),
	KEY `idx_duf_category_field_value` (`category_id`, `field_name`, `field_value_hash`),
	KEY `idx_duf_category_item` (`category_id`, `item_id`),
	KEY `idx_duf_field_item` (`field_name`, `item_id`),
	KEY `idx_duf_category_item_language` (`category_id`, `item_id`, `language`, `field_name`, `field_value_hash`),
	KEY `idx_duf_category_language_field_value_item` (`category_id`, `language`, `field_name`, `field_value`, `item_id`),
	KEY `idx_duf_category_field_value_item` (`category_id`, `field_name`, `field_value`, `item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__dharma_universal_filter_price_index` (
	`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	`category_id` INT UNSIGNED NOT NULL DEFAULT 0,
	`item_id` INT UNSIGNED NOT NULL DEFAULT 0,
	`currency` VARCHAR(32) NOT NULL DEFAULT '',
	`price_min` DECIMAL(20,6) NOT NULL DEFAULT 0,
	`price_max` DECIMAL(20,6) NOT NULL DEFAULT 0,
	`language` VARCHAR(7) NOT NULL DEFAULT '*',
	`in_stock` TINYINT NOT NULL DEFAULT 0,
	PRIMARY KEY (`id`),
	KEY `idx_duf_price_category_currency` (`category_id`, `currency`),
	KEY `idx_duf_price_category_item` (`category_id`, `item_id`),
	KEY `idx_duf_price_category_currency_item_language` (`category_id`, `currency`, `item_id`, `language`),
	KEY `idx_duf_price_category_currency_language_values` (`category_id`, `currency`, `language`, `price_min`, `price_max`, `item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
