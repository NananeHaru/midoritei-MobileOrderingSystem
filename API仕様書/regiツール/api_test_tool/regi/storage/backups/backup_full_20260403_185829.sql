-- Backup created at 2026-04-03 18:58:29
SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------
-- Table: accounts
-- ------------------------------
DROP TABLE IF EXISTS `accounts`;
CREATE TABLE `accounts` (
  `account_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `login_id` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `account_name` varchar(100) NOT NULL,
  `role_type` enum('MASTER','STAFF') NOT NULL,
  `store_id` char(2) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_login_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`account_id`),
  UNIQUE KEY `uk_accounts_login_id` (`login_id`),
  KEY `idx_accounts_store_id` (`store_id`),
  CONSTRAINT `fk_accounts_store` FOREIGN KEY (`store_id`) REFERENCES `stores` (`store_id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `accounts` (`account_id`, `login_id`, `password_hash`, `account_name`, `role_type`, `store_id`, `email`, `is_active`, `last_login_at`, `created_at`, `updated_at`) VALUES ('1', 'admin1111', '$2y$10$ZvPXtznJpAb9Yy0vzUqJk.sOTbaWxOsODasPeEkDJ/jo3Nn/l4Gsu', '1111', 'STAFF', 'AA', NULL, '1', NULL, '2026-04-03 22:37:51', '2026-04-03 22:58:33');
INSERT INTO `accounts` (`account_id`, `login_id`, `password_hash`, `account_name`, `role_type`, `store_id`, `email`, `is_active`, `last_login_at`, `created_at`, `updated_at`) VALUES ('2', 'adimin0101', '$2y$10$c42sYSix31T2rmF4ZxA0hemO4eDP6hMgOCLYxTY3o4oyKHxdjG12e', '0101', 'STAFF', 'AB', 'midori2@oishi.com', '1', NULL, '2026-04-03 22:38:49', '2026-04-03 22:58:39');

-- ------------------------------
-- Table: backup_history
-- ------------------------------
DROP TABLE IF EXISTS `backup_history`;
CREATE TABLE `backup_history` (
  `backup_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `backup_type` enum('MANUAL','AUTO') NOT NULL,
  `backup_scope` enum('FULL','MASTER_ONLY') NOT NULL DEFAULT 'FULL',
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` bigint(20) unsigned DEFAULT NULL,
  `created_by_account_id` bigint(20) unsigned DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  `status` enum('SUCCESS','FAILED') NOT NULL DEFAULT 'SUCCESS',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`backup_id`),
  KEY `idx_backup_history_created_at` (`created_at`),
  KEY `fk_backup_history_account` (`created_by_account_id`),
  CONSTRAINT `fk_backup_history_account` FOREIGN KEY (`created_by_account_id`) REFERENCES `accounts` (`account_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `backup_history` (`backup_id`, `backup_type`, `backup_scope`, `file_name`, `file_path`, `file_size`, `created_by_account_id`, `note`, `status`, `created_at`) VALUES ('1', 'MANUAL', 'FULL', 'backup_full_20260403_184627.sql', 'C:\\xampp\\htdocs\\regi/storage/backups/backup_full_20260403_184627.sql', '29217', '1', NULL, 'SUCCESS', '2026-04-04 01:46:27');

-- ------------------------------
-- Table: bill
-- ------------------------------
DROP TABLE IF EXISTS `bill`;
CREATE TABLE `bill` (
  `bill_id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT '???vID?i?̔ԁj',
  `order_bill_id` bigint(20) NOT NULL,
  `store_id` char(2) NOT NULL COMMENT '?X??ID',
  `bill_time` datetime NOT NULL COMMENT '???v????????',
  `subtotal_amount` int(11) NOT NULL COMMENT '?Ŕ????v',
  `discount_amount` int(11) NOT NULL COMMENT '???????v',
  `tax_amount` int(11) NOT NULL COMMENT '??????',
  `total_amount` int(11) NOT NULL COMMENT '?ō????v',
  PRIMARY KEY (`bill_id`),
  KEY `idx_bill_order_bill_id` (`order_bill_id`),
  KEY `idx_bill_store_id` (`store_id`),
  KEY `idx_bill_bill_time` (`bill_time`),
  CONSTRAINT `fk_bill_order_bill` FOREIGN KEY (`order_bill_id`) REFERENCES `order_bill` (`order_bill_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_bill_store` FOREIGN KEY (`store_id`) REFERENCES `stores` (`store_id`) ON UPDATE CASCADE,
  CONSTRAINT `chk_bill_amounts_non_negative` CHECK (`subtotal_amount` >= 0 and `discount_amount` >= 0 and `tax_amount` >= 0 and `total_amount` >= 0)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='???v?w?b?_';

INSERT INTO `bill` (`bill_id`, `order_bill_id`, `store_id`, `bill_time`, `subtotal_amount`, `discount_amount`, `tax_amount`, `total_amount`) VALUES ('1', '1', 'AA', '2026-03-20 17:35:26', '100', '0', '10', '110');
INSERT INTO `bill` (`bill_id`, `order_bill_id`, `store_id`, `bill_time`, `subtotal_amount`, `discount_amount`, `tax_amount`, `total_amount`) VALUES ('2', '2', 'AA', '2026-03-20 21:11:09', '100', '0', '10', '110');
INSERT INTO `bill` (`bill_id`, `order_bill_id`, `store_id`, `bill_time`, `subtotal_amount`, `discount_amount`, `tax_amount`, `total_amount`) VALUES ('3', '3', 'AA', '2026-03-20 21:17:46', '100', '0', '10', '110');
INSERT INTO `bill` (`bill_id`, `order_bill_id`, `store_id`, `bill_time`, `subtotal_amount`, `discount_amount`, `tax_amount`, `total_amount`) VALUES ('4', '4', 'AA', '2026-03-21 14:28:35', '1000', '0', '100', '1100');
INSERT INTO `bill` (`bill_id`, `order_bill_id`, `store_id`, `bill_time`, `subtotal_amount`, `discount_amount`, `tax_amount`, `total_amount`) VALUES ('5', '5', 'AA', '2026-03-21 16:50:16', '1000', '0', '100', '1100');
INSERT INTO `bill` (`bill_id`, `order_bill_id`, `store_id`, `bill_time`, `subtotal_amount`, `discount_amount`, `tax_amount`, `total_amount`) VALUES ('6', '6', 'AA', '2026-03-22 19:22:22', '1000', '0', '100', '1100');
INSERT INTO `bill` (`bill_id`, `order_bill_id`, `store_id`, `bill_time`, `subtotal_amount`, `discount_amount`, `tax_amount`, `total_amount`) VALUES ('7', '7', 'AA', '2026-03-22 19:24:21', '1000', '0', '100', '1100');
INSERT INTO `bill` (`bill_id`, `order_bill_id`, `store_id`, `bill_time`, `subtotal_amount`, `discount_amount`, `tax_amount`, `total_amount`) VALUES ('8', '8', 'AA', '2026-03-22 19:30:46', '1000', '0', '100', '1100');
INSERT INTO `bill` (`bill_id`, `order_bill_id`, `store_id`, `bill_time`, `subtotal_amount`, `discount_amount`, `tax_amount`, `total_amount`) VALUES ('9', '9', 'AA', '2026-03-22 19:33:19', '1000', '0', '100', '1100');
INSERT INTO `bill` (`bill_id`, `order_bill_id`, `store_id`, `bill_time`, `subtotal_amount`, `discount_amount`, `tax_amount`, `total_amount`) VALUES ('10', '10', 'AA', '2026-03-22 20:37:38', '1100', '0', '110', '1210');
INSERT INTO `bill` (`bill_id`, `order_bill_id`, `store_id`, `bill_time`, `subtotal_amount`, `discount_amount`, `tax_amount`, `total_amount`) VALUES ('11', '11', 'AA', '2026-03-22 20:46:12', '5000', '0', '500', '5500');
INSERT INTO `bill` (`bill_id`, `order_bill_id`, `store_id`, `bill_time`, `subtotal_amount`, `discount_amount`, `tax_amount`, `total_amount`) VALUES ('12', '12', 'AA', '2026-03-22 20:46:40', '5000', '0', '500', '5500');
INSERT INTO `bill` (`bill_id`, `order_bill_id`, `store_id`, `bill_time`, `subtotal_amount`, `discount_amount`, `tax_amount`, `total_amount`) VALUES ('13', '13', 'AA', '2026-03-22 20:46:51', '1000', '0', '100', '1100');
INSERT INTO `bill` (`bill_id`, `order_bill_id`, `store_id`, `bill_time`, `subtotal_amount`, `discount_amount`, `tax_amount`, `total_amount`) VALUES ('14', '14', 'AA', '2026-03-24 17:50:24', '11000', '0', '900', '11900');
INSERT INTO `bill` (`bill_id`, `order_bill_id`, `store_id`, `bill_time`, `subtotal_amount`, `discount_amount`, `tax_amount`, `total_amount`) VALUES ('15', '15', 'AA', '2026-03-24 19:49:48', '5000', '0', '500', '5500');
INSERT INTO `bill` (`bill_id`, `order_bill_id`, `store_id`, `bill_time`, `subtotal_amount`, `discount_amount`, `tax_amount`, `total_amount`) VALUES ('16', '16', 'AA', '2026-03-24 19:49:54', '1000', '0', '100', '1100');
INSERT INTO `bill` (`bill_id`, `order_bill_id`, `store_id`, `bill_time`, `subtotal_amount`, `discount_amount`, `tax_amount`, `total_amount`) VALUES ('17', '17', 'AA', '2026-03-27 13:09:28', '1000', '6', '100', '1094');
INSERT INTO `bill` (`bill_id`, `order_bill_id`, `store_id`, `bill_time`, `subtotal_amount`, `discount_amount`, `tax_amount`, `total_amount`) VALUES ('18', '18', 'AA', '2026-03-27 13:09:56', '500', '3', '50', '547');
INSERT INTO `bill` (`bill_id`, `order_bill_id`, `store_id`, `bill_time`, `subtotal_amount`, `discount_amount`, `tax_amount`, `total_amount`) VALUES ('19', '19', 'AA', '2026-03-30 17:32:36', '1000', '0', '100', '1100');

-- ------------------------------
-- Table: bill_detail
-- ------------------------------
DROP TABLE IF EXISTS `bill_detail`;
CREATE TABLE `bill_detail` (
  `bill_detail_id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT '???v???׍sID?i?̔ԁj',
  `bill_id` bigint(20) NOT NULL COMMENT '???vID?iBILL.bill_id?j',
  `menu_name` varchar(64) NOT NULL COMMENT '???j???[??',
  `category_name` varchar(32) DEFAULT NULL COMMENT '?J?e?S????',
  `qty` int(11) NOT NULL COMMENT '????',
  `unit_price` int(11) NOT NULL COMMENT '?P???i?Ŕ??j',
  `amount` int(11) NOT NULL COMMENT '???z?iunit_price ?~ qty?j',
  `tax_rate` int(11) NOT NULL COMMENT '?ŗ??i0?`100?j',
  PRIMARY KEY (`bill_detail_id`),
  KEY `idx_bill_detail_bill_id` (`bill_id`),
  CONSTRAINT `fk_bill_detail_bill` FOREIGN KEY (`bill_id`) REFERENCES `bill` (`bill_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_bill_detail_qty_positive` CHECK (`qty` > 0),
  CONSTRAINT `chk_bill_detail_unit_price_non_negative` CHECK (`unit_price` >= 0),
  CONSTRAINT `chk_bill_detail_amount_non_negative` CHECK (`amount` >= 0),
  CONSTRAINT `chk_bill_detail_tax_rate` CHECK (`tax_rate` between 0 and 100)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='???v????';

INSERT INTO `bill_detail` (`bill_detail_id`, `bill_id`, `menu_name`, `category_name`, `qty`, `unit_price`, `amount`, `tax_rate`) VALUES ('1', '1', '手入力商品', '手入力', '1', '100', '100', '10');
INSERT INTO `bill_detail` (`bill_detail_id`, `bill_id`, `menu_name`, `category_name`, `qty`, `unit_price`, `amount`, `tax_rate`) VALUES ('2', '2', '手入力商品', '手入力', '1', '100', '100', '10');
INSERT INTO `bill_detail` (`bill_detail_id`, `bill_id`, `menu_name`, `category_name`, `qty`, `unit_price`, `amount`, `tax_rate`) VALUES ('3', '3', '手入力商品', '手入力', '1', '100', '100', '10');
INSERT INTO `bill_detail` (`bill_detail_id`, `bill_id`, `menu_name`, `category_name`, `qty`, `unit_price`, `amount`, `tax_rate`) VALUES ('4', '4', '手入力商品', '手入力', '1', '1000', '1000', '10');
INSERT INTO `bill_detail` (`bill_detail_id`, `bill_id`, `menu_name`, `category_name`, `qty`, `unit_price`, `amount`, `tax_rate`) VALUES ('5', '5', '手入力商品', '手入力', '1', '1000', '1000', '10');
INSERT INTO `bill_detail` (`bill_detail_id`, `bill_id`, `menu_name`, `category_name`, `qty`, `unit_price`, `amount`, `tax_rate`) VALUES ('6', '6', '手入力商品', '手入力', '1', '1000', '1000', '10');
INSERT INTO `bill_detail` (`bill_detail_id`, `bill_id`, `menu_name`, `category_name`, `qty`, `unit_price`, `amount`, `tax_rate`) VALUES ('7', '7', '手入力商品', '手入力', '1', '1000', '1000', '10');
INSERT INTO `bill_detail` (`bill_detail_id`, `bill_id`, `menu_name`, `category_name`, `qty`, `unit_price`, `amount`, `tax_rate`) VALUES ('8', '8', '手入力商品', '手入力', '1', '1000', '1000', '10');
INSERT INTO `bill_detail` (`bill_detail_id`, `bill_id`, `menu_name`, `category_name`, `qty`, `unit_price`, `amount`, `tax_rate`) VALUES ('9', '9', '手入力商品', '手入力', '1', '1000', '1000', '10');
INSERT INTO `bill_detail` (`bill_detail_id`, `bill_id`, `menu_name`, `category_name`, `qty`, `unit_price`, `amount`, `tax_rate`) VALUES ('10', '10', '手入力商品', '手入力', '1', '1000', '1000', '10');
INSERT INTO `bill_detail` (`bill_detail_id`, `bill_id`, `menu_name`, `category_name`, `qty`, `unit_price`, `amount`, `tax_rate`) VALUES ('11', '10', '手入力商品', '手入力', '1', '100', '100', '10');
INSERT INTO `bill_detail` (`bill_detail_id`, `bill_id`, `menu_name`, `category_name`, `qty`, `unit_price`, `amount`, `tax_rate`) VALUES ('12', '11', '手入力商品', '手入力', '1', '5000', '5000', '10');
INSERT INTO `bill_detail` (`bill_detail_id`, `bill_id`, `menu_name`, `category_name`, `qty`, `unit_price`, `amount`, `tax_rate`) VALUES ('13', '12', '手入力商品', '手入力', '1', '5000', '5000', '10');
INSERT INTO `bill_detail` (`bill_detail_id`, `bill_id`, `menu_name`, `category_name`, `qty`, `unit_price`, `amount`, `tax_rate`) VALUES ('14', '13', '手入力商品', '手入力', '1', '1000', '1000', '10');
INSERT INTO `bill_detail` (`bill_detail_id`, `bill_id`, `menu_name`, `category_name`, `qty`, `unit_price`, `amount`, `tax_rate`) VALUES ('15', '14', '手入力商品', '手入力', '1', '1000', '1000', '10');
INSERT INTO `bill_detail` (`bill_detail_id`, `bill_id`, `menu_name`, `category_name`, `qty`, `unit_price`, `amount`, `tax_rate`) VALUES ('16', '14', '手入力商品', '手入力', '1', '10000', '10000', '8');
INSERT INTO `bill_detail` (`bill_detail_id`, `bill_id`, `menu_name`, `category_name`, `qty`, `unit_price`, `amount`, `tax_rate`) VALUES ('17', '15', '手入力商品', '手入力', '1', '5000', '5000', '10');
INSERT INTO `bill_detail` (`bill_detail_id`, `bill_id`, `menu_name`, `category_name`, `qty`, `unit_price`, `amount`, `tax_rate`) VALUES ('18', '16', '手入力商品', '手入力', '1', '1000', '1000', '10');
INSERT INTO `bill_detail` (`bill_detail_id`, `bill_id`, `menu_name`, `category_name`, `qty`, `unit_price`, `amount`, `tax_rate`) VALUES ('19', '17', '手入力商品', '手入力', '1', '1000', '1000', '10');
INSERT INTO `bill_detail` (`bill_detail_id`, `bill_id`, `menu_name`, `category_name`, `qty`, `unit_price`, `amount`, `tax_rate`) VALUES ('20', '18', '手入力商品', '手入力', '1', '500', '500', '10');
INSERT INTO `bill_detail` (`bill_detail_id`, `bill_id`, `menu_name`, `category_name`, `qty`, `unit_price`, `amount`, `tax_rate`) VALUES ('21', '19', '手入力商品', '手入力', '1', '1000', '1000', '10');

-- ------------------------------
-- Table: bill_payment
-- ------------------------------
DROP TABLE IF EXISTS `bill_payment`;
CREATE TABLE `bill_payment` (
  `bill_payment_id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT '?x????????ID?i?̔ԁj',
  `bill_id` bigint(20) NOT NULL COMMENT '???vID?iBILL.bill_id?j',
  `pay_method` varchar(16) NOT NULL COMMENT '?x?????i?iCASH / CARD / ELECTRONIC_MONEY?j',
  `pay_amount` int(11) NOT NULL COMMENT '?x???z',
  `pay_time` datetime NOT NULL COMMENT '?x?????m?莞??',
  `received_amount` int(11) DEFAULT NULL COMMENT '???̋??z?i???????K?{?A?J?[?h??NULL?j',
  `change_amount` int(11) DEFAULT NULL COMMENT '???ނ??i???????K?{?A?J?[?h??NULL?j',
  `provider` varchar(32) DEFAULT NULL COMMENT '???ώ??ƎҖ??iPayPay?Ȃǁj',
  PRIMARY KEY (`bill_payment_id`),
  KEY `idx_bill_payment_bill_id` (`bill_id`),
  KEY `idx_bill_payment_pay_time` (`pay_time`),
  CONSTRAINT `fk_bill_payment_bill` FOREIGN KEY (`bill_id`) REFERENCES `bill` (`bill_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_bill_payment_method` CHECK (`pay_method` in ('CASH','CARD','ELECTRONIC_MONEY')),
  CONSTRAINT `chk_bill_payment_pay_amount_non_negative` CHECK (`pay_amount` >= 0),
  CONSTRAINT `chk_bill_payment_received_amount_non_negative` CHECK (`received_amount` is null or `received_amount` >= 0),
  CONSTRAINT `chk_bill_payment_change_amount_non_negative` CHECK (`change_amount` is null or `change_amount` >= 0),
  CONSTRAINT `chk_bill_payment_cash_rule` CHECK (`pay_method` = 'CASH' and `received_amount` is not null and `change_amount` is not null or `pay_method` in ('CARD','ELECTRONIC_MONEY'))
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='?x????????';

INSERT INTO `bill_payment` (`bill_payment_id`, `bill_id`, `pay_method`, `pay_amount`, `pay_time`, `received_amount`, `change_amount`, `provider`) VALUES ('1', '1', 'CASH', '110', '2026-03-20 17:35:26', '110', '0', NULL);
INSERT INTO `bill_payment` (`bill_payment_id`, `bill_id`, `pay_method`, `pay_amount`, `pay_time`, `received_amount`, `change_amount`, `provider`) VALUES ('2', '2', 'CASH', '110', '2026-03-20 21:11:09', '110', '0', NULL);
INSERT INTO `bill_payment` (`bill_payment_id`, `bill_id`, `pay_method`, `pay_amount`, `pay_time`, `received_amount`, `change_amount`, `provider`) VALUES ('3', '3', 'CASH', '110', '2026-03-20 21:17:46', '110', '0', NULL);
INSERT INTO `bill_payment` (`bill_payment_id`, `bill_id`, `pay_method`, `pay_amount`, `pay_time`, `received_amount`, `change_amount`, `provider`) VALUES ('4', '4', 'CASH', '1100', '2026-03-21 14:28:35', '1100', '0', NULL);
INSERT INTO `bill_payment` (`bill_payment_id`, `bill_id`, `pay_method`, `pay_amount`, `pay_time`, `received_amount`, `change_amount`, `provider`) VALUES ('5', '5', 'CASH', '1100', '2026-03-21 16:50:16', '1100', '0', NULL);
INSERT INTO `bill_payment` (`bill_payment_id`, `bill_id`, `pay_method`, `pay_amount`, `pay_time`, `received_amount`, `change_amount`, `provider`) VALUES ('6', '6', 'CASH', '1100', '2026-03-22 19:22:22', '1100', '0', NULL);
INSERT INTO `bill_payment` (`bill_payment_id`, `bill_id`, `pay_method`, `pay_amount`, `pay_time`, `received_amount`, `change_amount`, `provider`) VALUES ('7', '7', 'CASH', '500', '2026-03-22 19:23:52', '500', '0', NULL);
INSERT INTO `bill_payment` (`bill_payment_id`, `bill_id`, `pay_method`, `pay_amount`, `pay_time`, `received_amount`, `change_amount`, `provider`) VALUES ('8', '7', 'CASH', '600', '2026-03-22 19:24:18', '600', '0', NULL);
INSERT INTO `bill_payment` (`bill_payment_id`, `bill_id`, `pay_method`, `pay_amount`, `pay_time`, `received_amount`, `change_amount`, `provider`) VALUES ('9', '8', 'CASH', '550', '2026-03-22 19:30:25', '550', '0', NULL);
INSERT INTO `bill_payment` (`bill_payment_id`, `bill_id`, `pay_method`, `pay_amount`, `pay_time`, `received_amount`, `change_amount`, `provider`) VALUES ('10', '8', 'CASH', '550', '2026-03-22 19:30:31', '550', '0', NULL);
INSERT INTO `bill_payment` (`bill_payment_id`, `bill_id`, `pay_method`, `pay_amount`, `pay_time`, `received_amount`, `change_amount`, `provider`) VALUES ('11', '9', 'CASH', '1000', '2026-03-22 19:32:38', '1000', '0', NULL);
INSERT INTO `bill_payment` (`bill_payment_id`, `bill_id`, `pay_method`, `pay_amount`, `pay_time`, `received_amount`, `change_amount`, `provider`) VALUES ('12', '9', 'CASH', '100', '2026-03-22 19:32:45', '100', '0', NULL);
INSERT INTO `bill_payment` (`bill_payment_id`, `bill_id`, `pay_method`, `pay_amount`, `pay_time`, `received_amount`, `change_amount`, `provider`) VALUES ('13', '10', 'ELECTRONIC_MONEY', '1210', '2026-03-22 20:37:38', NULL, NULL, NULL);
INSERT INTO `bill_payment` (`bill_payment_id`, `bill_id`, `pay_method`, `pay_amount`, `pay_time`, `received_amount`, `change_amount`, `provider`) VALUES ('14', '11', 'CASH', '5500', '2026-03-22 20:46:12', '5500', '0', NULL);
INSERT INTO `bill_payment` (`bill_payment_id`, `bill_id`, `pay_method`, `pay_amount`, `pay_time`, `received_amount`, `change_amount`, `provider`) VALUES ('15', '12', 'CASH', '5500', '2026-03-22 20:46:40', '10000', '4500', NULL);
INSERT INTO `bill_payment` (`bill_payment_id`, `bill_id`, `pay_method`, `pay_amount`, `pay_time`, `received_amount`, `change_amount`, `provider`) VALUES ('16', '13', 'CARD', '1100', '2026-03-22 20:46:51', NULL, NULL, NULL);
INSERT INTO `bill_payment` (`bill_payment_id`, `bill_id`, `pay_method`, `pay_amount`, `pay_time`, `received_amount`, `change_amount`, `provider`) VALUES ('17', '14', 'CASH', '11900', '2026-03-24 17:50:24', '11900', '0', NULL);
INSERT INTO `bill_payment` (`bill_payment_id`, `bill_id`, `pay_method`, `pay_amount`, `pay_time`, `received_amount`, `change_amount`, `provider`) VALUES ('18', '15', 'CASH', '5500', '2026-03-24 19:49:48', '10000', '4500', NULL);
INSERT INTO `bill_payment` (`bill_payment_id`, `bill_id`, `pay_method`, `pay_amount`, `pay_time`, `received_amount`, `change_amount`, `provider`) VALUES ('19', '16', 'CASH', '1100', '2026-03-24 19:49:54', '6000', '4900', NULL);
INSERT INTO `bill_payment` (`bill_payment_id`, `bill_id`, `pay_method`, `pay_amount`, `pay_time`, `received_amount`, `change_amount`, `provider`) VALUES ('20', '17', 'CASH', '1094', '2026-03-27 13:09:28', '5000', '3906', NULL);
INSERT INTO `bill_payment` (`bill_payment_id`, `bill_id`, `pay_method`, `pay_amount`, `pay_time`, `received_amount`, `change_amount`, `provider`) VALUES ('21', '18', 'CASH', '547', '2026-03-27 13:09:56', '5000', '4453', NULL);
INSERT INTO `bill_payment` (`bill_payment_id`, `bill_id`, `pay_method`, `pay_amount`, `pay_time`, `received_amount`, `change_amount`, `provider`) VALUES ('22', '19', 'CASH', '1100', '2026-03-30 17:32:36', '1100', '0', NULL);

-- ------------------------------
-- Table: close_header
-- ------------------------------
DROP TABLE IF EXISTS `close_header`;
CREATE TABLE `close_header` (
  `close_id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT '???W??ID',
  `store_id` char(2) NOT NULL COMMENT '?X??ID',
  `target_from` datetime NOT NULL COMMENT '???ߑΏۊJ?n?????i?O?????ߌ??j',
  `target_to` datetime NOT NULL COMMENT '???ߑΏۏI???????i???????ߎ??_?j',
  `executed_at` datetime NOT NULL COMMENT '???W?????s????',
  `executed_by_name` varchar(32) NOT NULL COMMENT '???s?Җ??i?????́j',
  `bill_count` int(11) NOT NULL DEFAULT 0 COMMENT '???v?ό???',
  `subtotal_sum` int(11) NOT NULL DEFAULT 0 COMMENT '?Ŕ????v',
  `discount_sum` int(11) NOT NULL DEFAULT 0 COMMENT '???????v',
  `tax_amount_sum` int(11) NOT NULL DEFAULT 0 COMMENT '?????ō??v',
  `total_amount_sum` int(11) NOT NULL DEFAULT 0 COMMENT '???㍇?v',
  `cash_sum` int(11) NOT NULL DEFAULT 0 COMMENT '???????㍇?v',
  `card_sum` int(11) NOT NULL DEFAULT 0 COMMENT '?J?[?h???㍇?v',
  `electronic_money_sum` int(11) NOT NULL DEFAULT 0 COMMENT '?d?q?}?l?[???㍇?v',
  `register_start_amount` int(11) NOT NULL DEFAULT 0 COMMENT '???W?J?n???z',
  `expected_cash` int(11) NOT NULL DEFAULT 0 COMMENT '???_????',
  `actual_cash` int(11) NOT NULL DEFAULT 0 COMMENT '????????',
  `cash_diff` int(11) NOT NULL DEFAULT 0 COMMENT '?ߕs??',
  `open_order_count` int(11) NOT NULL DEFAULT 0 COMMENT '???W?????_?̎??t??????',
  `open_order_amount` int(11) NOT NULL DEFAULT 0 COMMENT '???W?????_?̎??t?????z',
  `created_at` datetime NOT NULL DEFAULT current_timestamp() COMMENT '?쐬????',
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT '?X?V????',
  PRIMARY KEY (`close_id`),
  KEY `idx_close_header_store_target_to` (`store_id`,`target_to`),
  KEY `idx_close_header_store_executed_at` (`store_id`,`executed_at`),
  CONSTRAINT `fk_close_header_store` FOREIGN KEY (`store_id`) REFERENCES `stores` (`store_id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='???W???w?b?_';


-- ------------------------------
-- Table: order_bill
-- ------------------------------
DROP TABLE IF EXISTS `order_bill`;
CREATE TABLE `order_bill` (
  `order_bill_id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT '???????vID?i?̔ԁj',
  `created_at` datetime NOT NULL COMMENT '?쐬????',
  PRIMARY KEY (`order_bill_id`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='???????v';

INSERT INTO `order_bill` (`order_bill_id`, `created_at`) VALUES ('1', '2026-03-20 17:35:26');
INSERT INTO `order_bill` (`order_bill_id`, `created_at`) VALUES ('2', '2026-03-20 21:11:09');
INSERT INTO `order_bill` (`order_bill_id`, `created_at`) VALUES ('3', '2026-03-20 21:17:46');
INSERT INTO `order_bill` (`order_bill_id`, `created_at`) VALUES ('4', '2026-03-21 14:28:35');
INSERT INTO `order_bill` (`order_bill_id`, `created_at`) VALUES ('5', '2026-03-21 16:50:16');
INSERT INTO `order_bill` (`order_bill_id`, `created_at`) VALUES ('6', '2026-03-22 19:22:22');
INSERT INTO `order_bill` (`order_bill_id`, `created_at`) VALUES ('7', '2026-03-22 19:24:21');
INSERT INTO `order_bill` (`order_bill_id`, `created_at`) VALUES ('8', '2026-03-22 19:30:46');
INSERT INTO `order_bill` (`order_bill_id`, `created_at`) VALUES ('9', '2026-03-22 19:33:19');
INSERT INTO `order_bill` (`order_bill_id`, `created_at`) VALUES ('10', '2026-03-22 20:37:38');
INSERT INTO `order_bill` (`order_bill_id`, `created_at`) VALUES ('11', '2026-03-22 20:46:12');
INSERT INTO `order_bill` (`order_bill_id`, `created_at`) VALUES ('12', '2026-03-22 20:46:40');
INSERT INTO `order_bill` (`order_bill_id`, `created_at`) VALUES ('13', '2026-03-22 20:46:51');
INSERT INTO `order_bill` (`order_bill_id`, `created_at`) VALUES ('14', '2026-03-24 17:50:24');
INSERT INTO `order_bill` (`order_bill_id`, `created_at`) VALUES ('15', '2026-03-24 19:49:48');
INSERT INTO `order_bill` (`order_bill_id`, `created_at`) VALUES ('16', '2026-03-24 19:49:54');
INSERT INTO `order_bill` (`order_bill_id`, `created_at`) VALUES ('17', '2026-03-27 13:09:28');
INSERT INTO `order_bill` (`order_bill_id`, `created_at`) VALUES ('18', '2026-03-27 13:09:56');
INSERT INTO `order_bill` (`order_bill_id`, `created_at`) VALUES ('19', '2026-03-30 17:32:36');

-- ------------------------------
-- Table: order_header
-- ------------------------------
DROP TABLE IF EXISTS `order_header`;
CREATE TABLE `order_header` (
  `order_id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT '????ID?i?̔ԁj',
  `order_bill_id` bigint(20) NOT NULL COMMENT '???????vID?iORDER_BILL.order_bill_id?j',
  `customer_id` char(7) NOT NULL COMMENT '?ڋqID',
  `entry_time` datetime NOT NULL COMMENT '???X????',
  `hash` varchar(64) NOT NULL COMMENT '?n?b?V??',
  `mos_update_status` int(11) DEFAULT NULL COMMENT 'API??MOS???ɓ`???????v????',
  `mos_error_code` varchar(100) DEFAULT NULL COMMENT 'API?ŃG???[?ɂȂ????ۂ̃G???[?R?[?h',
  `mos_error_message` varchar(255) DEFAULT NULL COMMENT 'API?ŃG???[?ɂȂ????ۂ̃G???[???b?Z?[?W',
  `mos_updated_at` datetime DEFAULT NULL COMMENT 'updateStatus???s????????',
  `created_at` datetime NOT NULL COMMENT '?쐬????',
  `updated_at` datetime NOT NULL COMMENT '?X?V????',
  PRIMARY KEY (`order_id`),
  KEY `idx_order_header_order_bill_id` (`order_bill_id`),
  KEY `idx_order_header_customer_id` (`customer_id`),
  KEY `idx_order_header_entry_time` (`entry_time`),
  CONSTRAINT `fk_order_header_order_bill` FOREIGN KEY (`order_bill_id`) REFERENCES `order_bill` (`order_bill_id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='????';


-- ------------------------------
-- Table: restore_history
-- ------------------------------
DROP TABLE IF EXISTS `restore_history`;
CREATE TABLE `restore_history` (
  `restore_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `backup_id` bigint(20) unsigned NOT NULL,
  `restore_scope` enum('FULL','MASTER_ONLY') NOT NULL,
  `executed_by_account_id` bigint(20) unsigned DEFAULT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `status` enum('SUCCESS','FAILED') NOT NULL DEFAULT 'SUCCESS',
  `executed_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`restore_id`),
  KEY `idx_restore_history_executed_at` (`executed_at`),
  KEY `fk_restore_history_backup` (`backup_id`),
  KEY `fk_restore_history_account` (`executed_by_account_id`),
  CONSTRAINT `fk_restore_history_account` FOREIGN KEY (`executed_by_account_id`) REFERENCES `accounts` (`account_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_restore_history_backup` FOREIGN KEY (`backup_id`) REFERENCES `backup_history` (`backup_id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ------------------------------
-- Table: stores
-- ------------------------------
DROP TABLE IF EXISTS `stores`;
CREATE TABLE `stores` (
  `store_id` char(2) NOT NULL COMMENT '?X??ID?i?A???t?@?x?b?g2?????j',
  `store_name` varchar(64) NOT NULL COMMENT '?X?ܖ?',
  `store_address` varchar(128) NOT NULL COMMENT '?Z??',
  `store_phone` varchar(13) NOT NULL COMMENT '?d?b?ԍ?',
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT '?L???X?܂?',
  `created_at` datetime NOT NULL DEFAULT current_timestamp() COMMENT '?쐬????',
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT '?X?V????',
  PRIMARY KEY (`store_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='?X?܃}?X?^';

INSERT INTO `stores` (`store_id`, `store_name`, `store_address`, `store_phone`, `is_active`, `created_at`, `updated_at`) VALUES ('AA', 'みどり亭 本店', '大阪市東成区中本1-5-21', '06-0000-0000', '1', '2026-03-31 01:17:16', '2026-04-03 22:12:01');
INSERT INTO `stores` (`store_id`, `store_name`, `store_address`, `store_phone`, `is_active`, `created_at`, `updated_at`) VALUES ('AB', 'みどり亭 2号店', '大阪市中央区1-1-1', '06-1111-1111', '1', '2026-03-31 01:17:16', '2026-04-03 01:58:52');
INSERT INTO `stores` (`store_id`, `store_name`, `store_address`, `store_phone`, `is_active`, `created_at`, `updated_at`) VALUES ('AC', 'みどり亭 3号店', '大阪府堺市北区2-2-2', '06-2222-2222', '1', '2026-03-31 01:17:16', '2026-04-03 01:58:53');
INSERT INTO `stores` (`store_id`, `store_name`, `store_address`, `store_phone`, `is_active`, `created_at`, `updated_at`) VALUES ('AD', 'みどり亭 生野店', '大阪市生野区新今里8-2-14', '06-6752-3266', '1', '2026-04-03 02:31:56', '2026-04-03 02:31:56');

SET FOREIGN_KEY_CHECKS = 1;
