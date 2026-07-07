-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- ホスト: 127.0.0.1
-- 生成日時: 2026-07-01 15:45:01
-- サーバのバージョン： 10.4.32-MariaDB
-- PHP のバージョン: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- データベース: `regi_system`
--

-- --------------------------------------------------------

--
-- テーブルの構造 `accounts`
--

CREATE TABLE `accounts` (
  `account_id` bigint(20) UNSIGNED NOT NULL,
  `login_id` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `account_name` varchar(100) NOT NULL,
  `role_type` enum('MASTER','STAFF') NOT NULL,
  `store_id` char(2) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_login_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- テーブルのデータのダンプ `accounts`
--

INSERT INTO `accounts` (`account_id`, `login_id`, `password_hash`, `account_name`, `role_type`, `store_id`, `email`, `is_active`, `last_login_at`, `created_at`, `updated_at`) VALUES
(1, 'staff0001', '$2y$10$ZvPXtznJpAb9Yy0vzUqJk.sOTbaWxOsODasPeEkDJ/jo3Nn/l4Gsu', '緑橋店01', 'STAFF', 'MH', NULL, 1, '2026-06-29 02:26:22', '2026-04-03 22:37:51', '2026-06-29 02:26:22'),
(2, 'staff0002', '$2y$10$c42sYSix31T2rmF4ZxA0hemO4eDP6hMgOCLYxTY3o4oyKHxdjG12e', '森ノ宮店01', 'STAFF', 'MN', 'midori2@oishi.com', 1, '2026-04-06 03:37:15', '2026-04-03 22:38:49', '2026-04-25 23:20:48'),
(3, 'master', '$2y$10$ZvPXtznJpAb9Yy0vzUqJk.sOTbaWxOsODasPeEkDJ/jo3Nn/l4Gsu', 'マスター管理者', 'MASTER', NULL, 'master@example.com', 1, '2026-07-01 21:56:31', '2026-04-14 00:13:05', '2026-07-01 21:56:31'),
(5, 'staff0003', '$2y$10$fJFDH4l0KhRwTTcBQEXk4OmTrCs025.xUhV2MlKBni1uc.fWhA4zC', '玉造店01', 'STAFF', 'TM', NULL, 1, NULL, '2026-04-25 23:39:27', '2026-04-25 23:39:27'),
(6, 'staff0004', '$2y$10$ZBKtL5PGuKXBczHWemBqyuu.NUaUAEh6ot9SHNT76j673BLvUycnm', '鶴橋店01', 'STAFF', 'TH', NULL, 1, NULL, '2026-04-25 23:39:52', '2026-04-25 23:39:52'),
(7, 'staff0005', '$2y$10$Py9.Y6RxtHIGKMFxJ42F/OdbjVILhKY6rOYPkCPb6jqGjntZe8D/u', '今里店01', 'STAFF', 'IM', NULL, 1, NULL, '2026-04-25 23:40:20', '2026-04-25 23:40:20'),
(8, 'staff0006', '$2y$10$mFl1KgtgT6MMJNChx9tPke.l/hyXF4GnliX3e4NOmN4vlYh/J5y6O', '深江橋店01', 'STAFF', 'FB', NULL, 1, NULL, '2026-04-25 23:40:49', '2026-04-25 23:40:49'),
(9, 'staff0007', '$2y$10$flEK3epheSCKfBVk3VDhveGwFE5JmDFi8v8QfDHjs2YJk7gjalmEG', '谷町四丁目店01', 'STAFF', 'TY', NULL, 1, NULL, '2026-04-25 23:41:20', '2026-04-25 23:41:20'),
(10, 'staff0008', '$2y$10$COR0UgpGszPWtN0nz20KquKJ0yoZb.4DO7a3Buf/AJ1Hts8W5fRX.', '本町店01', 'STAFF', 'HM', NULL, 1, NULL, '2026-04-25 23:41:45', '2026-04-25 23:41:45'),
(11, 'staff0009', '$2y$10$vvZmXg/rTyZ9IL07Rgxw5uCGDuEPXsqESy0zt6sw7yrhHNwOt0C36', '京橋店01', 'STAFF', 'KB', NULL, 1, NULL, '2026-04-25 23:42:14', '2026-04-25 23:42:14'),
(12, 'staff0010', '$2y$10$19yrkAQl0jN6rTrz4ICfUeMzdKOcKazmC5YQa9HlOps/5dtK4Yzo6', 'なんば店', 'STAFF', 'NB', NULL, 1, NULL, '2026-04-25 23:42:40', '2026-04-25 23:42:40');

-- --------------------------------------------------------

--
-- テーブルの構造 `backup_history`
--

CREATE TABLE `backup_history` (
  `backup_id` bigint(20) UNSIGNED NOT NULL,
  `backup_type` enum('MANUAL','AUTO') NOT NULL,
  `backup_scope` enum('FULL','MASTER_ONLY') NOT NULL DEFAULT 'FULL',
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` bigint(20) UNSIGNED DEFAULT NULL,
  `created_by_account_id` bigint(20) UNSIGNED DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  `status` enum('SUCCESS','FAILED') NOT NULL DEFAULT 'SUCCESS',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- テーブルのデータのダンプ `backup_history`
--

INSERT INTO `backup_history` (`backup_id`, `backup_type`, `backup_scope`, `file_name`, `file_path`, `file_size`, `created_by_account_id`, `note`, `status`, `created_at`) VALUES
(1, 'MANUAL', 'FULL', 'backup_full_20260403_184627.sql', 'C:\\xampp\\htdocs\\regi/storage/backups/backup_full_20260403_184627.sql', 29217, 1, NULL, 'SUCCESS', '2026-04-04 01:46:27'),
(2, 'MANUAL', 'FULL', 'backup_full_20260403_185829.sql', 'C:\\xampp\\htdocs\\regi/storage/backups/backup_full_20260403_185829.sql', 29601, 1, 'ああ', 'SUCCESS', '2026-04-04 01:58:29'),
(3, 'MANUAL', 'FULL', 'backup_full_20260403_200406.sql', 'C:\\xampp\\htdocs\\regi/storage/backups/backup_full_20260403_200406.sql', 29972, 1, 'ああ', 'SUCCESS', '2026-04-04 03:04:06'),
(4, 'MANUAL', 'FULL', 'backup_full_20260425_164302.sql', 'C:\\xampp\\htdocs\\regi/storage/backups/backup_full_20260425_164302.sql', 40880, 3, NULL, 'SUCCESS', '2026-04-25 23:43:02'),
(5, 'MANUAL', 'FULL', 'backup_full_20260515_041222.sql', 'C:\\xampp\\htdocs\\regi/storage/backups/backup_full_20260515_041222.sql', 53835, 3, NULL, 'SUCCESS', '2026-05-15 04:12:22'),
(6, 'MANUAL', 'FULL', 'backup_full_20260515_045550.sql', 'C:\\xampp\\htdocs\\regi/storage/backups/backup_full_20260515_045550.sql', 54194, 3, NULL, 'SUCCESS', '2026-05-15 04:55:50'),
(7, 'MANUAL', 'FULL', 'backup_full_20260515_052249.sql', 'C:\\xampp\\htdocs\\regi/storage/backups/backup_full_20260515_052249.sql', 54561, 3, NULL, 'SUCCESS', '2026-05-15 05:22:49'),
(8, 'MANUAL', 'FULL', 'backup_full_20260515_052437.sql', 'C:\\xampp\\htdocs\\regi/storage/backups/backup_full_20260515_052437.sql', 54928, 3, NULL, 'SUCCESS', '2026-05-15 05:24:37'),
(9, 'MANUAL', 'FULL', 'backup_full_20260525_014451.sql', 'C:\\xampp\\htdocs\\regi/storage/backups/backup_full_20260525_014451.sql', 65570, 3, NULL, 'SUCCESS', '2026-05-25 01:44:51'),
(10, 'MANUAL', 'FULL', 'backup_full_20260629_003525.sql', 'C:\\xampp\\htdocs\\regi/storage/backups/backup_full_20260629_003525.sql', 72070, 3, NULL, 'SUCCESS', '2026-06-29 00:35:25');

-- --------------------------------------------------------

--
-- テーブルの構造 `bill`
--

CREATE TABLE `bill` (
  `bill_id` bigint(20) NOT NULL COMMENT '???vID?i?̔ԁj',
  `order_bill_id` bigint(20) NOT NULL,
  `store_id` char(2) NOT NULL COMMENT '?X??ID',
  `bill_time` datetime NOT NULL COMMENT '???v????????',
  `subtotal_amount` int(11) NOT NULL COMMENT '?Ŕ????v',
  `discount_amount` int(11) NOT NULL COMMENT '???????v',
  `tax_amount` int(11) NOT NULL COMMENT '??????',
  `total_amount` int(11) NOT NULL COMMENT '?ō????v',
  `split_mode` varchar(20) NOT NULL DEFAULT 'NONE' COMMENT '会計分割方法（NONE、EQUAL、ITEM）'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- テーブルのデータのダンプ `bill`
--

INSERT INTO `bill` (`bill_id`, `order_bill_id`, `store_id`, `bill_time`, `subtotal_amount`, `discount_amount`, `tax_amount`, `total_amount`, `split_mode`) VALUES
(1, 1, 'ZZ', '2026-03-20 17:35:26', 100, 0, 10, 110, 'NONE'),
(2, 2, 'ZZ', '2026-03-20 21:11:09', 100, 0, 10, 110, 'NONE'),
(3, 3, 'ZZ', '2026-03-20 21:17:46', 100, 0, 10, 110, 'NONE'),
(4, 4, 'ZZ', '2026-03-21 14:28:35', 1000, 0, 100, 1100, 'NONE'),
(5, 5, 'ZZ', '2026-03-21 16:50:16', 1000, 0, 100, 1100, 'NONE'),
(6, 6, 'ZZ', '2026-03-22 19:22:22', 1000, 0, 100, 1100, 'NONE'),
(7, 7, 'ZZ', '2026-03-22 19:24:21', 1000, 0, 100, 1100, 'NONE'),
(8, 8, 'ZZ', '2026-03-22 19:30:46', 1000, 0, 100, 1100, 'NONE'),
(9, 9, 'ZZ', '2026-03-22 19:33:19', 1000, 0, 100, 1100, 'NONE'),
(10, 10, 'ZZ', '2026-03-22 20:37:38', 1100, 0, 110, 1210, 'NONE'),
(11, 11, 'ZZ', '2026-03-22 20:46:12', 5000, 0, 500, 5500, 'NONE'),
(12, 12, 'ZZ', '2026-03-22 20:46:40', 5000, 0, 500, 5500, 'NONE'),
(13, 13, 'ZZ', '2026-03-22 20:46:51', 1000, 0, 100, 1100, 'NONE'),
(14, 14, 'ZZ', '2026-03-24 17:50:24', 11000, 0, 900, 11900, 'NONE'),
(15, 15, 'ZZ', '2026-03-24 19:49:48', 5000, 0, 500, 5500, 'NONE'),
(16, 16, 'ZZ', '2026-03-24 19:49:54', 1000, 0, 100, 1100, 'NONE'),
(17, 17, 'ZZ', '2026-03-27 13:09:28', 1000, 6, 100, 1094, 'NONE'),
(18, 18, 'ZZ', '2026-03-27 13:09:56', 500, 3, 50, 547, 'NONE'),
(19, 19, 'ZZ', '2026-03-30 17:32:36', 1000, 0, 100, 1100, 'NONE'),
(22, 22, 'ZA', '2026-04-05 20:11:47', 1000, 0, 100, 1100, 'NONE'),
(23, 23, 'ZA', '2026-04-05 20:14:03', 1000, 0, 100, 1100, 'NONE'),
(24, 24, 'ZZ', '2026-04-07 21:37:44', 5000, 0, 500, 5500, 'NONE'),
(25, 25, 'ZZ', '2026-04-07 22:34:26', 500, 10, 49, 539, 'NONE'),
(26, 26, 'ZZ', '2026-04-10 17:12:19', 99999900, 0, 9999991, 109999891, 'NONE'),
(27, 27, 'ZZ', '2026-04-19 14:46:56', 10000, 0, 1000, 11000, 'NONE'),
(28, 28, 'MH', '2026-04-25 16:45:06', 11000, 0, 1100, 12100, 'NONE'),
(29, 29, 'MH', '2026-04-25 16:47:54', 12000, 0, 1200, 13200, 'NONE'),
(30, 30, 'MH', '2026-04-25 16:49:56', 10000, 0, 1000, 11000, 'NONE'),
(31, 31, 'MH', '2026-04-25 16:50:08', 10000, 0, 1000, 11000, 'NONE'),
(32, 32, 'MH', '2026-04-25 16:51:48', 15000, 0, 1500, 16500, 'NONE'),
(33, 33, 'MH', '2026-04-25 19:10:28', 10000, 0, 1000, 11000, 'NONE'),
(34, 34, 'MH', '2026-04-25 19:10:40', 5000, 0, 500, 5500, 'NONE'),
(35, 35, 'MH', '2026-05-13 17:37:00', 4070, 0, 407, 4477, 'NONE'),
(36, 36, 'MH', '2026-05-13 18:17:42', 1850, 0, 185, 2035, 'NONE'),
(37, 37, 'MH', '2026-05-13 18:32:10', 1850, 0, 185, 2035, 'NONE'),
(38, 38, 'MH', '2026-05-19 02:46:10', 1332, 0, 134, 1466, 'NONE'),
(39, 39, 'MH', '2026-05-19 02:48:40', 777, 0, 78, 855, 'NONE'),
(40, 40, 'MH', '2026-05-19 02:48:58', 888, 0, 89, 977, 'NONE'),
(41, 41, 'MH', '2026-05-19 03:29:25', 1443, 0, 145, 1588, 'NONE'),
(42, 42, 'MH', '2026-05-19 03:30:15', 888, 0, 89, 977, 'NONE'),
(43, 43, 'MH', '2026-05-19 03:31:50', 5444, 0, 545, 5989, 'NONE'),
(44, 44, 'MH', '2026-05-19 03:32:50', 1443, 0, 145, 1588, 'NONE'),
(45, 45, 'MH', '2026-05-19 03:49:16', 9443, 0, 945, 10388, 'NONE'),
(46, 46, 'MH', '2026-05-19 03:49:46', 555, 0, 56, 611, 'NONE'),
(47, 47, 'MH', '2026-05-19 03:50:14', 222, 0, 23, 245, 'NONE'),
(48, 48, 'MH', '2026-05-19 03:55:06', 555, 0, 56, 611, 'NONE'),
(49, 49, 'MH', '2026-05-19 03:55:20', 888, 0, 89, 977, 'NONE'),
(50, 50, 'MH', '2026-05-26 07:29:49', 120, 0, 12, 132, 'NONE'),
(51, 51, 'MH', '2026-05-26 07:30:05', 55, 0, 6, 61, 'NONE'),
(52, 52, 'MH', '2026-05-26 07:34:15', 55, 0, 6, 61, 'NONE'),
(53, 53, 'MH', '2026-05-26 08:32:55', 55, 0, 6, 61, 'NONE'),
(54, 54, 'MH', '2026-05-26 08:33:06', 888, 0, 89, 977, 'NONE'),
(55, 57, 'MH', '2026-06-28 13:48:55', 555, 0, 56, 611, 'PERSON'),
(56, 58, 'MH', '2026-06-28 13:53:27', 4, 0, 1, 5, 'NONE'),
(57, 59, 'MH', '2026-06-28 13:53:44', 444, 0, 45, 489, 'PERSON'),
(58, 60, 'MH', '2026-06-29 01:00:36', 800, 0, 80, 880, 'AMOUNT');

-- --------------------------------------------------------

--
-- テーブルの構造 `bill_detail`
--

CREATE TABLE `bill_detail` (
  `bill_detail_id` bigint(20) NOT NULL COMMENT '???v???׍sID?i?̔ԁj',
  `bill_id` bigint(20) NOT NULL COMMENT '???vID?iBILL.bill_id?j',
  `menu_name` varchar(64) NOT NULL COMMENT '???j???[??',
  `category_name` varchar(32) DEFAULT NULL COMMENT '?J?e?S????',
  `qty` int(11) NOT NULL COMMENT '????',
  `unit_price` int(11) NOT NULL COMMENT '?P???i?Ŕ??j',
  `amount` int(11) NOT NULL COMMENT '???z?iunit_price ?~ qty?j',
  `tax_rate` int(11) NOT NULL COMMENT '?ŗ??i0?`100?j'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- テーブルのデータのダンプ `bill_detail`
--

INSERT INTO `bill_detail` (`bill_detail_id`, `bill_id`, `menu_name`, `category_name`, `qty`, `unit_price`, `amount`, `tax_rate`) VALUES
(1, 1, '手入力商品', '手入力', 1, 100, 100, 10),
(2, 2, '手入力商品', '手入力', 1, 100, 100, 10),
(3, 3, '手入力商品', '手入力', 1, 100, 100, 10),
(4, 4, '手入力商品', '手入力', 1, 1000, 1000, 10),
(5, 5, '手入力商品', '手入力', 1, 1000, 1000, 10),
(6, 6, '手入力商品', '手入力', 1, 1000, 1000, 10),
(7, 7, '手入力商品', '手入力', 1, 1000, 1000, 10),
(8, 8, '手入力商品', '手入力', 1, 1000, 1000, 10),
(9, 9, '手入力商品', '手入力', 1, 1000, 1000, 10),
(10, 10, '手入力商品', '手入力', 1, 1000, 1000, 10),
(11, 10, '手入力商品', '手入力', 1, 100, 100, 10),
(12, 11, '手入力商品', '手入力', 1, 5000, 5000, 10),
(13, 12, '手入力商品', '手入力', 1, 5000, 5000, 10),
(14, 13, '手入力商品', '手入力', 1, 1000, 1000, 10),
(15, 14, '手入力商品', '手入力', 1, 1000, 1000, 10),
(16, 14, '手入力商品', '手入力', 1, 10000, 10000, 8),
(17, 15, '手入力商品', '手入力', 1, 5000, 5000, 10),
(18, 16, '手入力商品', '手入力', 1, 1000, 1000, 10),
(19, 17, '手入力商品', '手入力', 1, 1000, 1000, 10),
(20, 18, '手入力商品', '手入力', 1, 500, 500, 10),
(21, 19, '手入力商品', '手入力', 1, 1000, 1000, 10),
(22, 22, '手入力商品', '手入力', 1, 1000, 1000, 10),
(23, 23, '手入力商品', '手入力', 1, 1000, 1000, 10),
(24, 24, '手入力商品', '手入力', 1, 1000, 1000, 10),
(25, 24, '手入力商品', '手入力', 1, 4000, 4000, 10),
(26, 25, '手入力商品', '手入力', 1, 500, 500, 10),
(27, 26, '手入力商品', '手入力', 99, 999999, 98999901, 10),
(28, 26, '手入力商品', '手入力', 1, 999999, 999999, 10),
(29, 27, '手入力商品', '手入力', 1, 10000, 10000, 10),
(30, 28, '手入力商品', '手入力', 1, 10000, 10000, 10),
(31, 28, '手入力商品', '手入力', 1, 1000, 1000, 10),
(32, 29, '手入力商品', '手入力', 1, 10000, 10000, 10),
(33, 29, '手入力商品', '手入力', 1, 2000, 2000, 10),
(34, 30, '手入力商品', '手入力', 1, 10000, 10000, 10),
(35, 31, '手入力商品', '手入力', 1, 2000, 2000, 10),
(36, 31, '手入力商品', '手入力', 2, 4000, 8000, 10),
(37, 32, '手入力商品', '手入力', 1, 10000, 10000, 10),
(38, 32, '手入力商品', '手入力', 1, 5000, 5000, 10),
(39, 33, '手入力商品', '手入力', 1, 10000, 10000, 10),
(40, 34, '手入力商品', '手入力', 1, 5000, 5000, 10),
(41, 35, '生ビール', 'ビール', 2, 600, 1200, 10),
(42, 35, '唐揚げ', '揚げ物', 1, 650, 650, 10),
(43, 35, 'えだまめ', 'おつまみ', 1, 300, 300, 10),
(44, 35, '刺身盛り合わせ', '刺身', 1, 1280, 1280, 10),
(45, 35, '烏龍茶', 'ソフトドリンク', 2, 320, 640, 10),
(46, 36, '生ビール', 'ビール', 2, 600, 1200, 10),
(47, 36, '唐揚げ', '揚げ物', 1, 650, 650, 10),
(48, 37, '生ビール', 'ビール', 2, 600, 1200, 10),
(49, 37, '唐揚げ', '揚げ物', 1, 650, 650, 10),
(50, 38, '手入力商品', '手入力', 1, 777, 777, 10),
(51, 38, '手入力商品', '手入力', 1, 555, 555, 10),
(52, 39, '手入力商品', '手入力', 1, 777, 777, 10),
(53, 40, '手入力商品', '手入力', 1, 888, 888, 10),
(54, 41, '手入力商品', '手入力', 1, 555, 555, 10),
(55, 41, '手入力商品', '手入力', 1, 888, 888, 10),
(56, 42, '手入力商品', '手入力', 1, 888, 888, 10),
(57, 43, '手入力商品', '手入力', 1, 5444, 5444, 10),
(58, 44, '手入力商品', '手入力', 1, 888, 888, 10),
(59, 44, '手入力商品', '手入力', 1, 555, 555, 10),
(60, 45, '手入力商品', '手入力', 1, 8888, 8888, 10),
(61, 45, '手入力商品', '手入力', 1, 555, 555, 10),
(62, 46, '手入力商品', '手入力', 1, 555, 555, 10),
(63, 47, '手入力商品', '手入力', 1, 222, 222, 10),
(64, 48, '手入力商品', '手入力', 1, 555, 555, 10),
(65, 49, '手入力商品', '手入力', 1, 888, 888, 10),
(66, 50, '手入力商品', '手入力', 1, 120, 120, 10),
(67, 51, '手入力商品', '手入力', 1, 55, 55, 10),
(68, 52, '手入力商品', '手入力', 1, 55, 55, 10),
(69, 53, '手入力商品', '手入力', 1, 55, 55, 10),
(70, 54, '手入力商品', '手入力', 1, 888, 888, 10),
(71, 55, '手入力商品', '手入力', 1, 555, 555, 10),
(72, 56, '手入力商品', '手入力', 1, 4, 4, 10),
(73, 57, '手入力商品', '手入力', 1, 444, 444, 10),
(74, 58, '手入力商品', '手入力', 1, 800, 800, 10);

-- --------------------------------------------------------

--
-- テーブルの構造 `bill_payment`
--

CREATE TABLE `bill_payment` (
  `bill_payment_id` bigint(20) NOT NULL COMMENT '?x????????ID?i?̔ԁj',
  `bill_id` bigint(20) NOT NULL COMMENT '???vID?iBILL.bill_id?j',
  `pay_method` varchar(16) NOT NULL COMMENT '?x?????i?iCASH / CARD / ELECTRONIC_MONEY?j',
  `pay_amount` int(11) NOT NULL COMMENT '?x???z',
  `pay_time` datetime NOT NULL COMMENT '?x?????m?莞??',
  `received_amount` int(11) DEFAULT NULL COMMENT '???̋??z?i???????K?{?A?J?[?h??NULL?j',
  `change_amount` int(11) DEFAULT NULL COMMENT '???ނ??i???????K?{?A?J?[?h??NULL?j',
  `provider` varchar(32) DEFAULT NULL COMMENT '???ώ??ƎҖ??iPayPay?Ȃǁj'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- テーブルのデータのダンプ `bill_payment`
--

INSERT INTO `bill_payment` (`bill_payment_id`, `bill_id`, `pay_method`, `pay_amount`, `pay_time`, `received_amount`, `change_amount`, `provider`) VALUES
(1, 1, 'CASH', 110, '2026-03-20 17:35:26', 110, 0, NULL),
(2, 2, 'CASH', 110, '2026-03-20 21:11:09', 110, 0, NULL),
(3, 3, 'CASH', 110, '2026-03-20 21:17:46', 110, 0, NULL),
(4, 4, 'CASH', 1100, '2026-03-21 14:28:35', 1100, 0, NULL),
(5, 5, 'CASH', 1100, '2026-03-21 16:50:16', 1100, 0, NULL),
(6, 6, 'CASH', 1100, '2026-03-22 19:22:22', 1100, 0, NULL),
(7, 7, 'CASH', 500, '2026-03-22 19:23:52', 500, 0, NULL),
(8, 7, 'CASH', 600, '2026-03-22 19:24:18', 600, 0, NULL),
(9, 8, 'CASH', 550, '2026-03-22 19:30:25', 550, 0, NULL),
(10, 8, 'CASH', 550, '2026-03-22 19:30:31', 550, 0, NULL),
(11, 9, 'CASH', 1000, '2026-03-22 19:32:38', 1000, 0, NULL),
(12, 9, 'CASH', 100, '2026-03-22 19:32:45', 100, 0, NULL),
(13, 10, 'ELECTRONIC_MONEY', 1210, '2026-03-22 20:37:38', NULL, NULL, NULL),
(14, 11, 'CASH', 5500, '2026-03-22 20:46:12', 5500, 0, NULL),
(15, 12, 'CASH', 5500, '2026-03-22 20:46:40', 10000, 4500, NULL),
(16, 13, 'CARD', 1100, '2026-03-22 20:46:51', NULL, NULL, NULL),
(17, 14, 'CASH', 11900, '2026-03-24 17:50:24', 11900, 0, NULL),
(18, 15, 'CASH', 5500, '2026-03-24 19:49:48', 10000, 4500, NULL),
(19, 16, 'CASH', 1100, '2026-03-24 19:49:54', 6000, 4900, NULL),
(20, 17, 'CASH', 1094, '2026-03-27 13:09:28', 5000, 3906, NULL),
(21, 18, 'CASH', 547, '2026-03-27 13:09:56', 5000, 4453, NULL),
(22, 19, 'CASH', 1100, '2026-03-30 17:32:36', 1100, 0, NULL),
(23, 22, 'CASH', 1100, '2026-04-05 20:11:47', 1100, 0, NULL),
(24, 23, 'CASH', 1100, '2026-04-05 20:14:03', 1100, 0, NULL),
(25, 24, 'CASH', 5500, '2026-04-07 21:37:44', 5500, 0, NULL),
(26, 25, 'CASH', 539, '2026-04-07 22:34:26', 5000, 4461, NULL),
(27, 26, 'CASH', 109999891, '2026-04-10 17:12:19', 999999999, 890000108, NULL),
(28, 27, 'CASH', 11000, '2026-04-19 14:46:56', 11000, 0, NULL),
(29, 28, 'CASH', 6050, '2026-04-25 16:44:53', 10000, 3950, NULL),
(30, 28, 'CASH', 6050, '2026-04-25 16:44:58', 10000, 3950, NULL),
(31, 29, 'CASH', 6600, '2026-04-25 16:46:51', 10000, 3400, NULL),
(32, 29, 'CASH', 6600, '2026-04-25 16:47:06', 10000, 3400, NULL),
(33, 30, 'CARD', 11000, '2026-04-25 16:49:56', NULL, NULL, NULL),
(34, 31, 'CASH', 11000, '2026-04-25 16:50:08', 11000, 0, NULL),
(35, 32, 'CASH', 5000, '2026-04-25 16:51:15', 5000, 0, NULL),
(36, 32, 'CASH', 11500, '2026-04-25 16:51:33', 11500, 0, NULL),
(37, 33, 'CASH', 11000, '2026-04-25 19:10:28', 11000, 0, NULL),
(38, 34, 'CASH', 5500, '2026-04-25 19:10:40', 5500, 0, NULL),
(39, 35, 'CASH', 4477, '2026-05-13 17:37:00', 4477, 0, NULL),
(40, 36, 'CASH', 2035, '2026-05-13 18:17:42', 2035, 0, NULL),
(41, 37, 'CASH', 2035, '2026-05-13 18:32:10', 2035, 0, NULL),
(42, 38, 'CASH', 733, '2026-05-19 02:46:10', 733, 0, NULL),
(43, 38, 'CASH', 733, '2026-05-19 02:46:32', 733, 0, NULL),
(44, 39, 'CASH', 855, '2026-05-19 02:48:40', 976, 121, NULL),
(45, 40, 'CASH', 977, '2026-05-19 02:48:58', 976100, 975123, NULL),
(46, 41, 'CASH', 1588, '2026-05-19 03:29:25', 1588, 0, NULL),
(47, 42, 'CARD', 977, '2026-05-19 03:30:15', NULL, NULL, 'VISA'),
(48, 43, 'ELECTRONIC_MONEY', 5989, '2026-05-19 03:31:50', NULL, NULL, 'Paypay'),
(49, 44, 'CASH', 794, '2026-05-19 03:32:50', 794, 0, NULL),
(50, 44, 'CASH', 794, '2026-05-19 03:32:59', 794, 0, NULL),
(51, 45, 'CASH', 10388, '2026-05-19 03:49:16', 10389, 1, NULL),
(52, 46, 'CASH', 611, '2026-05-19 03:49:46', 611, 0, NULL),
(53, 47, 'CASH', 245, '2026-05-19 03:50:14', 245, 0, NULL),
(54, 48, 'CASH', 611, '2026-05-19 03:55:06', 611, 0, NULL),
(55, 49, 'CASH', 977, '2026-05-19 03:55:20', 977, 0, NULL),
(56, 50, 'CASH', 132, '2026-05-26 07:29:49', 132, 0, NULL),
(57, 51, 'CASH', 61, '2026-05-26 07:30:05', 61, 0, NULL),
(58, 52, 'CASH', 61, '2026-05-26 07:34:15', 61, 0, NULL),
(59, 53, 'CASH', 61, '2026-05-26 08:32:55', 61, 0, NULL),
(60, 54, 'CASH', 977, '2026-05-26 08:33:06', 977, 0, NULL),
(61, 55, 'CASH', 306, '2026-06-28 13:48:55', 306, 0, NULL),
(62, 56, 'CASH', 5, '2026-06-28 13:53:27', 6, 1, NULL),
(63, 57, 'CASH', 245, '2026-06-28 13:53:44', 245, 0, NULL),
(64, 58, 'CASH', 400, '2026-06-29 01:00:36', 400, 0, NULL),
(65, 58, 'CASH', 400, '2026-06-29 01:00:43', 400, 0, NULL),
(66, 58, 'CASH', 80, '2026-06-29 01:00:49', 80, 0, NULL);

-- --------------------------------------------------------

--
-- テーブルの構造 `close_header`
--

CREATE TABLE `close_header` (
  `close_id` bigint(20) NOT NULL COMMENT '???W??ID',
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
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT '?X?V????'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='???W???w?b?_';

--
-- テーブルのデータのダンプ `close_header`
--

INSERT INTO `close_header` (`close_id`, `store_id`, `target_from`, `target_to`, `executed_at`, `executed_by_name`, `bill_count`, `subtotal_sum`, `discount_sum`, `tax_amount_sum`, `total_amount_sum`, `cash_sum`, `card_sum`, `electronic_money_sum`, `register_start_amount`, `expected_cash`, `actual_cash`, `cash_diff`, `open_order_count`, `open_order_amount`, `created_at`, `updated_at`) VALUES
(1, 'MH', '2026-05-13 00:00:00', '2026-05-13 17:39:18', '2026-05-13 17:39:18', '緑橋店01', 1, 4070, 0, 407, 4477, 4477, 0, 0, 0, 4477, 0, -4477, 0, 0, '2026-05-14 00:39:19', '2026-05-14 00:39:19'),
(2, 'MH', '2026-05-13 17:39:18', '2026-05-13 18:33:43', '2026-05-13 18:33:43', '緑橋店01', 2, 3700, 0, 370, 4070, 4070, 0, 0, 0, 4070, 0, -4070, 0, 0, '2026-05-14 01:33:44', '2026-05-14 01:33:44'),
(3, 'MH', '2026-05-13 18:33:43', '2026-06-29 02:26:45', '2026-06-29 02:26:45', '緑橋店01', 21, 31229, 0, 3134, 34363, 22034, 977, 5989, 0, 22034, 0, -22034, 0, 0, '2026-06-29 02:26:45', '2026-06-29 02:26:45'),
(5, 'MH', '2026-06-29 02:26:45', '2026-06-29 02:37:19', '2026-06-29 02:37:19', '緑橋店01', 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, '2026-06-29 02:37:19', '2026-06-29 02:37:19');

-- --------------------------------------------------------

--
-- テーブルの構造 `monthly_sales_summary`
--

CREATE TABLE `monthly_sales_summary` (
  `store_id` char(2) NOT NULL,
  `summary_year` smallint(5) UNSIGNED NOT NULL,
  `summary_month` tinyint(3) UNSIGNED NOT NULL,
  `bill_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `subtotal_sum` bigint(20) NOT NULL DEFAULT 0,
  `discount_sum` bigint(20) NOT NULL DEFAULT 0,
  `tax_amount_sum` bigint(20) NOT NULL DEFAULT 0,
  `total_amount_sum` bigint(20) NOT NULL DEFAULT 0,
  `cash_sum` bigint(20) NOT NULL DEFAULT 0,
  `card_sum` bigint(20) NOT NULL DEFAULT 0,
  `electronic_money_sum` bigint(20) NOT NULL DEFAULT 0,
  `summarized_at` datetime NOT NULL DEFAULT current_timestamp()
) ;

--
-- テーブルのデータのダンプ `monthly_sales_summary`
--

INSERT INTO `monthly_sales_summary` (`store_id`, `summary_year`, `summary_month`, `bill_count`, `subtotal_sum`, `discount_sum`, `tax_amount_sum`, `total_amount_sum`, `cash_sum`, `card_sum`, `electronic_money_sum`, `summarized_at`) VALUES
('AA', 2026, 6, 4, 1803, 0, 182, 1985, 1436, 0, 0, '2026-06-29 02:37:19');

-- --------------------------------------------------------

--
-- テーブルの構造 `order_bill`
--

CREATE TABLE `order_bill` (
  `order_bill_id` bigint(20) NOT NULL COMMENT '???????vID?i?̔ԁj',
  `created_at` datetime NOT NULL COMMENT '?쐬????'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='???????v';

--
-- テーブルのデータのダンプ `order_bill`
--

INSERT INTO `order_bill` (`order_bill_id`, `created_at`) VALUES
(1, '2026-03-20 17:35:26'),
(2, '2026-03-20 21:11:09'),
(3, '2026-03-20 21:17:46'),
(4, '2026-03-21 14:28:35'),
(5, '2026-03-21 16:50:16'),
(6, '2026-03-22 19:22:22'),
(7, '2026-03-22 19:24:21'),
(8, '2026-03-22 19:30:46'),
(9, '2026-03-22 19:33:19'),
(10, '2026-03-22 20:37:38'),
(11, '2026-03-22 20:46:12'),
(12, '2026-03-22 20:46:40'),
(13, '2026-03-22 20:46:51'),
(14, '2026-03-24 17:50:24'),
(15, '2026-03-24 19:49:48'),
(16, '2026-03-24 19:49:54'),
(17, '2026-03-27 13:09:28'),
(18, '2026-03-27 13:09:56'),
(19, '2026-03-30 17:32:36'),
(22, '2026-04-05 20:11:47'),
(23, '2026-04-05 20:14:03'),
(24, '2026-04-07 21:37:44'),
(25, '2026-04-07 22:34:26'),
(26, '2026-04-10 17:12:19'),
(27, '2026-04-19 14:46:56'),
(28, '2026-04-25 16:45:06'),
(29, '2026-04-25 16:47:54'),
(30, '2026-04-25 16:49:56'),
(31, '2026-04-25 16:50:08'),
(32, '2026-04-25 16:51:48'),
(33, '2026-04-25 19:10:28'),
(34, '2026-04-25 19:10:40'),
(35, '2026-05-13 17:37:00'),
(36, '2026-05-13 18:17:42'),
(37, '2026-05-13 18:32:10'),
(38, '2026-05-19 02:46:10'),
(39, '2026-05-19 02:48:40'),
(40, '2026-05-19 02:48:58'),
(41, '2026-05-19 03:29:25'),
(42, '2026-05-19 03:30:15'),
(43, '2026-05-19 03:31:50'),
(44, '2026-05-19 03:32:50'),
(45, '2026-05-19 03:49:16'),
(46, '2026-05-19 03:49:46'),
(47, '2026-05-19 03:50:14'),
(48, '2026-05-19 03:55:06'),
(49, '2026-05-19 03:55:20'),
(50, '2026-05-26 07:29:49'),
(51, '2026-05-26 07:30:05'),
(52, '2026-05-26 07:34:15'),
(53, '2026-05-26 08:32:55'),
(54, '2026-05-26 08:33:06'),
(57, '2026-06-28 13:48:55'),
(58, '2026-06-28 13:53:27'),
(59, '2026-06-28 13:53:44'),
(60, '2026-06-29 01:00:36');

-- --------------------------------------------------------

--
-- テーブルの構造 `order_header`
--

CREATE TABLE `order_header` (
  `order_id` bigint(20) NOT NULL COMMENT '????ID?i?̔ԁj',
  `order_bill_id` bigint(20) NOT NULL COMMENT '???????vID?iORDER_BILL.order_bill_id?j',
  `customer_id` char(7) NOT NULL COMMENT '?ڋqID',
  `entry_time` datetime NOT NULL COMMENT '???X????',
  `hash` varchar(64) NOT NULL COMMENT '?n?b?V??',
  `mos_update_status` int(11) DEFAULT NULL COMMENT 'API??MOS???ɓ`???????v????',
  `mos_error_code` varchar(100) DEFAULT NULL COMMENT 'API?ŃG???[?ɂȂ????ۂ̃G???[?R?[?h',
  `mos_error_message` varchar(255) DEFAULT NULL COMMENT 'API?ŃG???[?ɂȂ????ۂ̃G???[???b?Z?[?W',
  `mos_updated_at` datetime DEFAULT NULL COMMENT 'updateStatus???s????????',
  `created_at` datetime NOT NULL COMMENT '?쐬????',
  `updated_at` datetime NOT NULL COMMENT '?X?V????'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='????';

--
-- テーブルのデータのダンプ `order_header`
--

INSERT INTO `order_header` (`order_id`, `order_bill_id`, `customer_id`, `entry_time`, `hash`, `mos_update_status`, `mos_error_code`, `mos_error_message`, `mos_updated_at`, `created_at`, `updated_at`) VALUES
(1, 35, '0000001', '2026-05-13 17:10:00', '', NULL, NULL, NULL, NULL, '2026-05-13 17:37:00', '2026-05-13 17:37:00'),
(2, 36, '0000001', '2026-05-13 17:10:00', '', NULL, NULL, NULL, NULL, '2026-05-13 18:17:42', '2026-05-13 18:17:42'),
(3, 37, '0000001', '2026-05-13 17:10:00', '', NULL, NULL, NULL, NULL, '2026-05-13 18:32:10', '2026-05-13 18:32:10');

-- --------------------------------------------------------

--
-- テーブルの構造 `restore_history`
--

CREATE TABLE `restore_history` (
  `restore_id` bigint(20) UNSIGNED NOT NULL,
  `backup_id` bigint(20) UNSIGNED NOT NULL,
  `restore_scope` enum('FULL','MASTER_ONLY') NOT NULL,
  `executed_by_account_id` bigint(20) UNSIGNED DEFAULT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `status` enum('SUCCESS','FAILED') NOT NULL DEFAULT 'SUCCESS',
  `executed_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- テーブルの構造 `stores`
--

CREATE TABLE `stores` (
  `store_id` char(2) NOT NULL COMMENT '?X??ID?i?A???t?@?x?b?g2?????j',
  `store_name` varchar(64) NOT NULL COMMENT '?X?ܖ?',
  `store_address` varchar(128) NOT NULL COMMENT '?Z??',
  `store_phone` varchar(13) NOT NULL COMMENT '?d?b?ԍ?',
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT '?L???X?܂?',
  `created_at` datetime NOT NULL DEFAULT current_timestamp() COMMENT '?쐬????',
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT '?X?V????'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='?X?܃}?X?^';

--
-- テーブルのデータのダンプ `stores`
--

INSERT INTO `stores` (`store_id`, `store_name`, `store_address`, `store_phone`, `is_active`, `created_at`, `updated_at`) VALUES
('FB', '深江橋店\r\n', '大阪府大阪市東成区深江北1-3-20\r\n', '06-6976-0006', 1, '2026-04-25 23:14:21', '2026-07-01 22:31:30'),
('HM', '本町店\r\n', '大阪府大阪市中央区南本町3-2-4\r\n', '06-6251-0008', 1, '2026-04-25 23:15:14', '2026-07-01 22:31:30'),
('IM', '今里店\r\n', '大阪府大阪市東成区大今里南1-5-12\r\n', '06-6975-0005', 1, '2026-04-25 23:13:57', '2026-07-01 22:31:30'),
('KB', '京橋店\r\n', '大阪府大阪市都島区東野田町2-9-23\r\n', '06-6353-0009', 1, '2026-04-25 23:15:37', '2026-07-01 22:31:30'),
('MH', '緑橋本店', '大阪府大阪市東成区東中本1-2-10', '06-6971-0001', 1, '2026-04-25 23:11:26', '2026-07-01 22:31:30'),
('MN', '森ノ宮店\r\n', '大阪府大阪市中央区森ノ宮中央1-16-5\r\n', '06-6942-0002', 1, '2026-04-25 23:12:35', '2026-07-01 22:31:30'),
('NB', 'なんば店\r\n', '大阪府大阪市中央区難波3-6-11\r\n', '06-6643-0010', 1, '2026-04-25 23:16:16', '2026-07-01 22:31:30'),
('TH', '鶴橋店\r\n', '大阪府大阪市生野区鶴橋2-1-15\r\n', '06-6731-0004', 1, '2026-04-25 23:13:33', '2026-07-01 22:31:30'),
('TM', '玉造店\r\n', '大阪府大阪市天王寺区玉造元町3-8\r\n', '06-6768-0003', 1, '2026-04-25 23:13:03', '2026-07-01 22:31:30'),
('TY', '谷町四丁目店\r\n', '大阪府大阪市中央区谷町4-5-9\r\n', '06-6949-0007', 1, '2026-04-25 23:14:49', '2026-07-01 22:31:30'),
('ZA', 'みどり亭 2号店', '大阪市中央区1-1-1', '06-1111-1111', 0, '2026-03-31 01:17:16', '2026-04-25 23:38:42'),
('ZZ', 'みどり亭 本店', '大阪市東成区中本1-5-21', '06-0000-0000', 0, '2026-03-31 01:17:16', '2026-04-25 23:38:32');

-- --------------------------------------------------------

--
-- テーブルの構造 `yearly_sales_summary`
--

CREATE TABLE `yearly_sales_summary` (
  `store_id` char(2) NOT NULL,
  `summary_year` smallint(5) UNSIGNED NOT NULL,
  `bill_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `subtotal_sum` bigint(20) NOT NULL DEFAULT 0,
  `discount_sum` bigint(20) NOT NULL DEFAULT 0,
  `tax_amount_sum` bigint(20) NOT NULL DEFAULT 0,
  `total_amount_sum` bigint(20) NOT NULL DEFAULT 0,
  `cash_sum` bigint(20) NOT NULL DEFAULT 0,
  `card_sum` bigint(20) NOT NULL DEFAULT 0,
  `electronic_money_sum` bigint(20) NOT NULL DEFAULT 0,
  `summarized_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- テーブルのデータのダンプ `yearly_sales_summary`
--

INSERT INTO `yearly_sales_summary` (`store_id`, `summary_year`, `bill_count`, `subtotal_sum`, `discount_sum`, `tax_amount_sum`, `total_amount_sum`, `cash_sum`, `card_sum`, `electronic_money_sum`, `summarized_at`) VALUES
('AA', 2026, 4, 1803, 0, 182, 1985, 1436, 0, 0, '2026-06-29 02:37:19');

--
-- ダンプしたテーブルのインデックス
--

--
-- テーブルのインデックス `accounts`
--
ALTER TABLE `accounts`
  ADD PRIMARY KEY (`account_id`),
  ADD UNIQUE KEY `uk_accounts_login_id` (`login_id`),
  ADD KEY `idx_accounts_store_id` (`store_id`);

--
-- テーブルのインデックス `backup_history`
--
ALTER TABLE `backup_history`
  ADD PRIMARY KEY (`backup_id`),
  ADD KEY `idx_backup_history_created_at` (`created_at`),
  ADD KEY `fk_backup_history_account` (`created_by_account_id`);

--
-- テーブルのインデックス `bill`
--
ALTER TABLE `bill`
  ADD PRIMARY KEY (`bill_id`),
  ADD KEY `idx_bill_order_bill_id` (`order_bill_id`),
  ADD KEY `idx_bill_store_id` (`store_id`),
  ADD KEY `idx_bill_bill_time` (`bill_time`);

--
-- テーブルのインデックス `bill_detail`
--
ALTER TABLE `bill_detail`
  ADD PRIMARY KEY (`bill_detail_id`),
  ADD KEY `idx_bill_detail_bill_id` (`bill_id`);

--
-- テーブルのインデックス `bill_payment`
--
ALTER TABLE `bill_payment`
  ADD PRIMARY KEY (`bill_payment_id`),
  ADD KEY `idx_bill_payment_bill_id` (`bill_id`),
  ADD KEY `idx_bill_payment_pay_time` (`pay_time`);

--
-- テーブルのインデックス `close_header`
--
ALTER TABLE `close_header`
  ADD PRIMARY KEY (`close_id`),
  ADD KEY `idx_close_header_store_target_to` (`store_id`,`target_to`),
  ADD KEY `idx_close_header_store_executed_at` (`store_id`,`executed_at`);

--
-- テーブルのインデックス `monthly_sales_summary`
--
ALTER TABLE `monthly_sales_summary`
  ADD PRIMARY KEY (`store_id`,`summary_year`,`summary_month`),
  ADD KEY `idx_monthly_summary_year_month` (`summary_year`,`summary_month`);

--
-- テーブルのインデックス `order_bill`
--
ALTER TABLE `order_bill`
  ADD PRIMARY KEY (`order_bill_id`);

--
-- テーブルのインデックス `order_header`
--
ALTER TABLE `order_header`
  ADD PRIMARY KEY (`order_id`),
  ADD KEY `idx_order_header_order_bill_id` (`order_bill_id`),
  ADD KEY `idx_order_header_customer_id` (`customer_id`),
  ADD KEY `idx_order_header_entry_time` (`entry_time`);

--
-- テーブルのインデックス `restore_history`
--
ALTER TABLE `restore_history`
  ADD PRIMARY KEY (`restore_id`),
  ADD KEY `idx_restore_history_executed_at` (`executed_at`),
  ADD KEY `fk_restore_history_backup` (`backup_id`),
  ADD KEY `fk_restore_history_account` (`executed_by_account_id`);

--
-- テーブルのインデックス `stores`
--
ALTER TABLE `stores`
  ADD PRIMARY KEY (`store_id`);

--
-- テーブルのインデックス `yearly_sales_summary`
--
ALTER TABLE `yearly_sales_summary`
  ADD PRIMARY KEY (`store_id`,`summary_year`),
  ADD KEY `idx_yearly_summary_year` (`summary_year`);

--
-- ダンプしたテーブルの AUTO_INCREMENT
--

--
-- テーブルの AUTO_INCREMENT `accounts`
--
ALTER TABLE `accounts`
  MODIFY `account_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- テーブルの AUTO_INCREMENT `backup_history`
--
ALTER TABLE `backup_history`
  MODIFY `backup_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- テーブルの AUTO_INCREMENT `bill`
--
ALTER TABLE `bill`
  MODIFY `bill_id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT '???vID?i?̔ԁj', AUTO_INCREMENT=59;

--
-- テーブルの AUTO_INCREMENT `bill_detail`
--
ALTER TABLE `bill_detail`
  MODIFY `bill_detail_id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT '???v???׍sID?i?̔ԁj', AUTO_INCREMENT=75;

--
-- テーブルの AUTO_INCREMENT `bill_payment`
--
ALTER TABLE `bill_payment`
  MODIFY `bill_payment_id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT '?x????????ID?i?̔ԁj', AUTO_INCREMENT=67;

--
-- テーブルの AUTO_INCREMENT `close_header`
--
ALTER TABLE `close_header`
  MODIFY `close_id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT '???W??ID', AUTO_INCREMENT=6;

--
-- テーブルの AUTO_INCREMENT `order_bill`
--
ALTER TABLE `order_bill`
  MODIFY `order_bill_id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT '???????vID?i?̔ԁj', AUTO_INCREMENT=61;

--
-- テーブルの AUTO_INCREMENT `order_header`
--
ALTER TABLE `order_header`
  MODIFY `order_id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT '????ID?i?̔ԁj', AUTO_INCREMENT=4;

--
-- テーブルの AUTO_INCREMENT `restore_history`
--
ALTER TABLE `restore_history`
  MODIFY `restore_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- ダンプしたテーブルの制約
--

--
-- テーブルの制約 `accounts`
--
ALTER TABLE `accounts`
  ADD CONSTRAINT `fk_accounts_store` FOREIGN KEY (`store_id`) REFERENCES `stores` (`store_id`) ON UPDATE CASCADE;

--
-- テーブルの制約 `backup_history`
--
ALTER TABLE `backup_history`
  ADD CONSTRAINT `fk_backup_history_account` FOREIGN KEY (`created_by_account_id`) REFERENCES `accounts` (`account_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- テーブルの制約 `bill`
--
ALTER TABLE `bill`
  ADD CONSTRAINT `fk_bill_order_bill` FOREIGN KEY (`order_bill_id`) REFERENCES `order_bill` (`order_bill_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_bill_store` FOREIGN KEY (`store_id`) REFERENCES `stores` (`store_id`) ON UPDATE CASCADE;

--
-- テーブルの制約 `bill_detail`
--
ALTER TABLE `bill_detail`
  ADD CONSTRAINT `fk_bill_detail_bill` FOREIGN KEY (`bill_id`) REFERENCES `bill` (`bill_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- テーブルの制約 `bill_payment`
--
ALTER TABLE `bill_payment`
  ADD CONSTRAINT `fk_bill_payment_bill` FOREIGN KEY (`bill_id`) REFERENCES `bill` (`bill_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- テーブルの制約 `close_header`
--
ALTER TABLE `close_header`
  ADD CONSTRAINT `fk_close_header_store` FOREIGN KEY (`store_id`) REFERENCES `stores` (`store_id`) ON UPDATE CASCADE;

--
-- テーブルの制約 `order_header`
--
ALTER TABLE `order_header`
  ADD CONSTRAINT `fk_order_header_order_bill` FOREIGN KEY (`order_bill_id`) REFERENCES `order_bill` (`order_bill_id`) ON UPDATE CASCADE;

--
-- テーブルの制約 `restore_history`
--
ALTER TABLE `restore_history`
  ADD CONSTRAINT `fk_restore_history_account` FOREIGN KEY (`executed_by_account_id`) REFERENCES `accounts` (`account_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_restore_history_backup` FOREIGN KEY (`backup_id`) REFERENCES `backup_history` (`backup_id`) ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
