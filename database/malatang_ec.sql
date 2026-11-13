-- phpMyAdmin SQL Dump
-- version 5.1.2
-- https://www.phpmyadmin.net/
--
-- ホスト: localhost:3306
-- 生成日時: 2026-10-27 01:50:46
-- サーバのバージョン： 5.7.24
-- PHP のバージョン: 8.3.1

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- データベース: `malatang_ec`
--

DELIMITER $$
--
-- 関数
--
CREATE DEFINER=`root`@`localhost` FUNCTION `fn_bowl_price` (`p_menu_id` INT, `p_soup_id` INT, `p_spice_level_id` INT, `p_quantity` INT, `p_items` TEXT) RETURNS INT(11) READS SQL DATA BEGIN
    DECLARE v_total INT DEFAULT 0;
    DECLARE v_price INT;
    DECLARE v_ok INT;
    DECLARE v_count INT DEFAULT 0;
    DECLARE v_i INT DEFAULT 0;
    DECLARE v_id INT;
    DECLARE v_qty INT;
    DECLARE v_action VARCHAR(10);
    DECLARE v_stock INT;
    DECLARE v_removable INT;

    IF p_quantity IS NULL OR p_quantity < 1 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Quantity must be 1 or more';
    END IF;

    -- Menu
    SELECT price, (is_available AND NOT is_deleted) INTO v_price, v_ok
    FROM menus WHERE id = p_menu_id;
    IF v_price IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Menu not found';
    END IF;
    IF v_ok = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Menu is sold out';
    END IF;
    SET v_total = v_price;

    -- Soup (optional)
    IF p_soup_id IS NOT NULL THEN
        SET v_price = NULL;
        SELECT price, is_available INTO v_price, v_ok FROM soups WHERE id = p_soup_id;
        IF v_price IS NULL THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Soup not found';
        END IF;
        IF v_ok = 0 THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Soup is sold out';
        END IF;
        SET v_total = v_total + v_price;
    END IF;

    -- Spice level (optional)
    IF p_spice_level_id IS NOT NULL THEN
        SET v_price = NULL;
        SELECT extra_price INTO v_price FROM spice_levels WHERE id = p_spice_level_id;
        IF v_price IS NULL THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Spice level not found';
        END IF;
        SET v_total = v_total + v_price;
    END IF;

    -- Ingredients
    IF p_items IS NOT NULL AND p_items <> '' THEN
        IF JSON_VALID(p_items) = 0 THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Ingredient list is not valid JSON';
        END IF;
        SET v_count = JSON_LENGTH(p_items);
        WHILE v_i < v_count DO
            SET v_id = JSON_UNQUOTE(JSON_EXTRACT(p_items, CONCAT('$[', v_i, '].id')));
            SET v_qty = COALESCE(JSON_UNQUOTE(JSON_EXTRACT(p_items, CONCAT('$[', v_i, '].qty'))), 1);
            SET v_action = COALESCE(JSON_UNQUOTE(JSON_EXTRACT(p_items, CONCAT('$[', v_i, '].action'))), 'added');
            IF v_qty < 1 THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Ingredient quantity must be 1 or more';
            END IF;

            IF v_action = 'removed' THEN
                -- Only a default ingredient that is allowed to be removed
                SET v_removable = NULL;
                SELECT is_removable INTO v_removable
                FROM menu_ingredients WHERE menu_id = p_menu_id AND ingredient_id = v_id;
                IF v_removable IS NULL OR v_removable = 0 THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'This ingredient cannot be removed';
                END IF;
            ELSE
                SET v_price = NULL;
                SELECT price, (is_available AND NOT is_deleted), stock
                INTO v_price, v_ok, v_stock
                FROM ingredients WHERE id = v_id;
                IF v_price IS NULL THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Ingredient not found';
                END IF;
                IF v_ok = 0 OR v_stock < v_qty * p_quantity THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Ingredient is sold out';
                END IF;
                SET v_total = v_total + v_price * v_qty;
            END IF;
            SET v_i = v_i + 1;
        END WHILE;
    END IF;

    RETURN v_total * p_quantity;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- テーブルの構造 `announcements`
--

CREATE TABLE `announcements` (
  `id` int(11) NOT NULL,
  `title` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `body` text COLLATE utf8mb4_unicode_ci,
  `is_published` tinyint(1) NOT NULL DEFAULT '0',
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- テーブルのデータのダンプ `announcements`
--

INSERT INTO `announcements` (`id`, `title`, `body`, `is_published`, `start_date`, `end_date`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'オープン記念', 'トッピング1品無料！', 1, NULL, NULL, NULL, '2026-10-20 02:42:52', '2026-10-20 02:42:52');

-- --------------------------------------------------------

--
-- テーブルの構造 `cart_bowls`
--

CREATE TABLE `cart_bowls` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `guest_token` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `menu_id` int(11) NOT NULL,
  `has_soup` tinyint(1) NOT NULL DEFAULT '1',
  `soup_id` int(11) NOT NULL,
  `spice_level_id` int(11) DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- トリガ `cart_bowls`
--
DELIMITER $$
CREATE TRIGGER `trg_cart_bowls_owner` BEFORE INSERT ON `cart_bowls` FOR EACH ROW BEGIN
    IF NEW.user_id IS NULL AND (NEW.guest_token IS NULL OR NEW.guest_token = '') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'cart_bowls needs user_id or guest_token';
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- テーブルの構造 `cart_bowl_ingredients`
--

CREATE TABLE `cart_bowl_ingredients` (
  `cart_bowl_id` int(11) NOT NULL,
  `ingredient_id` int(11) NOT NULL,
  `action` enum('added','removed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'added',
  `quantity` int(11) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- テーブルの構造 `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- テーブルのデータのダンプ `categories`
--

INSERT INTO `categories` (`id`, `name`, `sort_order`) VALUES
(1, '野菜', 1),
(2, '肉', 3),
(3, '海鮮', 4),
(4, '麺', 7),
(5, '豆腐・湯葉', 6),
(6, 'きのこ', 2),
(7, '団子・練り物', 5),
(8, '点心', 8),
(9, 'トッピング', 9);

-- --------------------------------------------------------

--
-- テーブルの構造 `favorites`
--

CREATE TABLE `favorites` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `nickname` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `menu_id` int(11) NOT NULL,
  `has_soup` tinyint(1) NOT NULL DEFAULT '1',
  `soup_id` int(11) NOT NULL,
  `spice_level_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- テーブルの構造 `favorite_ingredients`
--

CREATE TABLE `favorite_ingredients` (
  `favorite_id` int(11) NOT NULL,
  `ingredient_id` int(11) NOT NULL,
  `action` enum('added','removed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'added',
  `quantity` int(11) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- テーブルの構造 `friends`
--

CREATE TABLE `friends` (
  `user_id` int(11) NOT NULL,
  `friend_id` int(11) NOT NULL,
  `relation` enum('friend','family') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'friend',
  `nickname` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- トリガ `friends`
--
DELIMITER $$
CREATE TRIGGER `trg_friends_not_self` BEFORE INSERT ON `friends` FOR EACH ROW BEGIN
    IF NEW.user_id = NEW.friend_id THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'You cannot add yourself as a friend';
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- テーブルの構造 `ingredients`
--

CREATE TABLE `ingredients` (
  `id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `price` int(11) NOT NULL,
  `stock` int(11) NOT NULL DEFAULT '0',
  `is_available` tinyint(1) NOT NULL DEFAULT '1',
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `image_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT '0',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- テーブルのデータのダンプ `ingredients`
--

INSERT INTO `ingredients` (`id`, `category_id`, `name`, `price`, `stock`, `is_available`, `is_deleted`, `image_url`, `sort_order`, `updated_at`) VALUES
(1, 1, 'チンゲン菜', 100, 50, 1, 0, NULL, 1, '2026-10-20 02:42:52'),
(2, 1, 'もやし', 80, 50, 1, 0, NULL, 2, '2026-10-20 02:42:52'),
(3, 1, 'きくらげ', 100, 40, 1, 0, NULL, 3, '2026-10-20 02:42:52'),
(4, 2, '豚バラ', 200, 30, 1, 0, NULL, 1, '2026-10-20 02:42:52'),
(5, 2, '牛肉', 250, 30, 1, 0, NULL, 2, '2026-10-20 02:42:52'),
(6, 3, 'エビ', 250, 20, 1, 0, NULL, 1, '2026-10-20 02:42:52'),
(7, 3, 'つみれ', 150, 30, 1, 0, NULL, 2, '2026-10-20 02:42:52'),
(8, 4, '春雨', 100, 50, 1, 0, NULL, 1, '2026-10-20 02:42:52'),
(9, 4, 'ラーメン', 120, 50, 1, 0, NULL, 2, '2026-10-20 02:42:52'),
(10, 5, '豆腐', 100, 40, 1, 0, NULL, 1, '2026-10-20 02:42:52'),
(11, 5, '湯葉', 150, 30, 1, 0, NULL, 2, '2026-10-20 02:42:52'),
(12, 1, '白菜', 100, 50, 1, 0, NULL, 4, '2026-10-23 00:53:26'),
(13, 1, 'ほうれん草', 100, 40, 1, 0, NULL, 5, '2026-10-23 00:53:26'),
(14, 1, '空芯菜', 150, 0, 0, 0, NULL, 6, '2026-10-27 00:29:16'),
(15, 1, 'ニラ', 100, 40, 1, 0, NULL, 7, '2026-10-23 00:53:26'),
(16, 1, 'レタス', 100, 40, 1, 0, NULL, 8, '2026-10-23 00:53:26'),
(17, 1, 'トマト', 120, 30, 1, 0, NULL, 9, '2026-10-23 00:53:26'),
(18, 1, 'じゃがいも', 100, 40, 1, 0, NULL, 10, '2026-10-23 00:53:26'),
(19, 1, 'れんこん', 120, 30, 1, 0, NULL, 11, '2026-10-23 00:53:26'),
(20, 1, 'ブロッコリー', 120, 30, 1, 0, NULL, 12, '2026-10-23 00:53:26'),
(21, 1, '昆布', 100, 40, 1, 0, NULL, 13, '2026-10-23 00:53:26'),
(22, 6, 'えのき', 100, 50, 1, 0, NULL, 1, '2026-10-23 00:53:26'),
(23, 6, 'しいたけ', 120, 40, 1, 0, NULL, 2, '2026-10-23 00:53:26'),
(24, 6, 'ふくろたけ', 150, 30, 1, 0, NULL, 3, '2026-10-23 00:53:26'),
(25, 6, 'エリンギ', 120, 30, 1, 0, NULL, 4, '2026-10-23 00:53:26'),
(26, 2, '鶏もも肉', 200, 30, 1, 0, NULL, 3, '2026-10-23 00:53:26'),
(27, 2, '羊肉', 300, 0, 0, 0, NULL, 4, '2026-10-23 00:53:26'),
(28, 2, 'ランチョンミート', 180, 30, 1, 0, NULL, 5, '2026-10-23 00:53:26'),
(29, 2, 'ウインナー', 150, 40, 1, 0, NULL, 6, '2026-10-23 00:53:26'),
(30, 3, 'イカ', 200, 20, 1, 0, NULL, 3, '2026-10-23 00:53:26'),
(31, 3, 'ホタテ', 300, 15, 1, 0, NULL, 4, '2026-10-23 00:53:26'),
(32, 3, 'あさり', 200, 20, 1, 0, NULL, 5, '2026-10-23 00:53:26'),
(33, 7, '牛肉団子', 150, 40, 1, 0, NULL, 1, '2026-10-23 00:53:26'),
(34, 7, 'えび団子', 150, 40, 1, 0, NULL, 2, '2026-10-23 00:53:26'),
(35, 7, 'いか団子', 150, 40, 1, 0, NULL, 3, '2026-10-23 00:53:26'),
(36, 7, '魚団子', 120, 40, 1, 0, NULL, 4, '2026-10-23 00:53:26'),
(37, 7, 'カニカマ', 100, 50, 1, 0, NULL, 5, '2026-10-23 00:53:26'),
(38, 7, 'ちくわ', 100, 50, 1, 0, NULL, 6, '2026-10-23 00:53:26'),
(39, 5, '厚揚げ', 120, 30, 1, 0, NULL, 3, '2026-10-23 00:53:26'),
(40, 5, '凍み豆腐', 120, 30, 1, 0, NULL, 4, '2026-10-23 00:53:26'),
(41, 5, '腐竹（棒湯葉）', 150, 30, 1, 0, NULL, 5, '2026-10-23 00:53:26'),
(42, 4, '幅広春雨', 120, 40, 1, 0, NULL, 3, '2026-10-23 00:53:26'),
(43, 4, 'うどん', 120, 40, 1, 0, NULL, 4, '2026-10-23 00:53:26'),
(44, 4, 'トッポギ', 150, 30, 1, 0, NULL, 5, '2026-10-23 00:53:26'),
(45, 8, '水餃子', 150, 40, 1, 0, NULL, 1, '2026-10-23 00:53:26'),
(46, 8, '肉しゅうまい', 150, 40, 1, 0, NULL, 2, '2026-10-23 00:53:26'),
(47, 8, '小籠包', 200, 30, 1, 0, NULL, 3, '2026-10-23 00:53:26'),
(48, 9, '味玉', 100, 30, 1, 0, NULL, 1, '2026-10-23 00:53:26'),
(49, 9, 'うずらの卵', 100, 40, 1, 0, NULL, 2, '2026-10-23 00:53:26'),
(50, 9, 'パクチー', 100, 30, 1, 0, NULL, 3, '2026-10-23 00:53:26'),
(51, 9, 'チーズ', 150, 30, 1, 0, NULL, 4, '2026-10-23 00:53:26'),
(52, 9, '肉味噌', 100, 30, 1, 0, NULL, 5, '2026-10-23 00:53:26'),
(53, 1, 'さつまいも', 150, 0, 0, 1, NULL, 0, '2026-10-23 01:33:30'),
(54, 6, 'まいたけ', 132, 0, 0, 0, NULL, 0, '2026-10-26 00:37:38');

--
-- トリガ `ingredients`
--
DELIMITER $$
CREATE TRIGGER `trg_ingredients_price_history` AFTER UPDATE ON `ingredients` FOR EACH ROW BEGIN
    IF NEW.price <> OLD.price THEN
        INSERT INTO price_history (target_type, target_id, old_price, new_price, changed_by)
        VALUES ('ingredient', NEW.id, OLD.price, NEW.price, @current_user_id);
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_ingredients_stock_soldout` BEFORE UPDATE ON `ingredients` FOR EACH ROW BEGIN
    IF NEW.stock = 0 AND OLD.stock > 0 THEN
        SET NEW.is_available = FALSE;
    ELSEIF NEW.stock > 0 AND OLD.stock = 0 THEN
        SET NEW.is_available = TRUE;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- テーブルの構造 `menus`
--

CREATE TABLE `menus` (
  `id` int(11) NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `price` int(11) NOT NULL,
  `image_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_available` tinyint(1) NOT NULL DEFAULT '1',
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `sort_order` int(11) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- テーブルのデータのダンプ `menus`
--

INSERT INTO `menus` (`id`, `name`, `description`, `price`, `image_url`, `is_available`, `is_deleted`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, '自分で作る麻辣湯', '春雨とスープから、好きな具材を自由に選んで作る', 500, NULL, 1, 0, 0, '2026-10-20 02:42:52', '2026-10-26 02:33:54'),
(2, 'お肉たっぷり麻辣湯', '豚バラと牛肉入り', 800, NULL, 1, 0, 2, '2026-10-20 02:42:52', '2026-10-20 02:42:52'),
(3, '野菜たっぷり麻辣湯', '白菜・青梗菜・ほうれん草・きのこ入り。ヘルシーな一杯', 850, NULL, 0, 0, 3, '2026-10-26 01:11:34', '2026-10-27 00:57:42'),
(4, '海鮮麻辣湯', 'エビ・イカ・ホタテ・あさりの旨みたっぷり', 1180, NULL, 1, 0, 4, '2026-10-26 01:11:34', '2026-10-26 01:11:34'),
(5, '団子いろいろ麻辣湯', '牛肉団子・えび団子・魚団子の食べくらべ', 950, NULL, 0, 0, 5, '2026-10-26 01:11:34', '2026-10-27 01:11:15'),
(6, '豆腐と湯葉の麻辣湯', '豆腐・湯葉・厚揚げで、やさしいたんぱく質', 880, NULL, 1, 0, 6, '2026-10-26 01:11:34', '2026-10-26 01:11:34'),
(7, 'しびれ麻辣ラーメン', '春雨のかわりにラーメン。豚バラとニラでがっつり', 980, NULL, 1, 0, 7, '2026-10-26 01:11:34', '2026-10-26 01:11:34'),
(8, 'チーズ麻辣湯', 'とろけるチーズとトッポギ。辛さがマイルドに', 980, NULL, 1, 0, 8, '2026-10-26 01:11:34', '2026-10-26 01:11:34'),
(9, '点心のせ麻辣湯', '水餃子・しゅうまい・小籠包をのせた贅沢な一杯', 1080, NULL, 1, 0, 9, '2026-10-26 01:11:35', '2026-10-26 01:11:35'),
(10, '【期間限定】羊肉麻辣湯', '羊肉とパクチーの本場の味', 1280, NULL, 0, 0, 10, '2026-10-26 01:11:35', '2026-10-26 01:11:35'),
(11, 'テスト麻辣湯', NULL, 950, NULL, 0, 1, 11, '2026-10-27 00:47:52', '2026-10-27 00:54:10');

--
-- トリガ `menus`
--
DELIMITER $$
CREATE TRIGGER `trg_menus_price_history` AFTER UPDATE ON `menus` FOR EACH ROW BEGIN
    IF NEW.price <> OLD.price THEN
        INSERT INTO price_history (target_type, target_id, old_price, new_price, changed_by)
        VALUES ('menu', NEW.id, OLD.price, NEW.price, @current_user_id);
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- テーブルの構造 `menu_ingredients`
--

CREATE TABLE `menu_ingredients` (
  `menu_id` int(11) NOT NULL,
  `ingredient_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT '1',
  `is_removable` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- テーブルのデータのダンプ `menu_ingredients`
--

INSERT INTO `menu_ingredients` (`menu_id`, `ingredient_id`, `quantity`, `is_removable`) VALUES
(1, 8, 1, 0),
(2, 4, 1, 1),
(2, 5, 1, 1),
(2, 8, 1, 0),
(3, 1, 1, 1),
(3, 3, 1, 1),
(3, 8, 1, 0),
(3, 12, 1, 1),
(3, 13, 1, 1),
(3, 22, 1, 1),
(4, 1, 1, 1),
(4, 6, 1, 1),
(4, 8, 1, 0),
(4, 30, 1, 1),
(4, 31, 1, 1),
(4, 32, 1, 1),
(5, 2, 1, 1),
(5, 8, 1, 0),
(5, 33, 1, 1),
(5, 34, 1, 1),
(5, 36, 1, 1),
(6, 2, 1, 1),
(6, 8, 1, 0),
(6, 10, 1, 1),
(6, 11, 1, 1),
(6, 39, 1, 1),
(7, 2, 1, 1),
(7, 4, 1, 1),
(7, 9, 1, 0),
(7, 15, 1, 1),
(7, 48, 1, 1),
(8, 2, 1, 1),
(8, 8, 1, 0),
(8, 29, 1, 1),
(8, 44, 1, 1),
(8, 51, 1, 1),
(9, 1, 1, 1),
(9, 8, 1, 0),
(9, 45, 1, 1),
(9, 46, 1, 1),
(9, 47, 1, 1),
(10, 8, 1, 0),
(10, 12, 1, 1),
(10, 27, 1, 1),
(10, 50, 1, 1),
(11, 8, 1, 0);

-- --------------------------------------------------------

--
-- テーブルの構造 `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `order_number` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `customer_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pickup_time` datetime NOT NULL,
  `total_price` int(11) NOT NULL DEFAULT '0',
  `payment_method` enum('mock_card') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'mock_card',
  `payment_status` enum('unpaid','paid','refunded') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unpaid',
  `status` enum('pending','confirmed','preparing','ready','completed','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `note` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- トリガ `orders`
--
DELIMITER $$
CREATE TRIGGER `trg_orders_status_change` AFTER UPDATE ON `orders` FOR EACH ROW BEGIN
    IF NEW.status <> OLD.status THEN
        INSERT INTO order_status_history (order_id, status, changed_by)
        VALUES (NEW.id, NEW.status, @current_user_id);
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_orders_status_first` AFTER INSERT ON `orders` FOR EACH ROW BEGIN
    INSERT INTO order_status_history (order_id, status, changed_by)
    VALUES (NEW.id, NEW.status, @current_user_id);
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- テーブルの構造 `order_bowls`
--

CREATE TABLE `order_bowls` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `shared_recipe_id` int(11) DEFAULT NULL,
  `menu_id` int(11) DEFAULT NULL,
  `menu_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `menu_price` int(11) NOT NULL,
  `has_soup` tinyint(1) NOT NULL DEFAULT '1',
  `soup_id` int(11) DEFAULT NULL,
  `soup_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `soup_price` int(11) NOT NULL DEFAULT '0',
  `spice_level_id` int(11) DEFAULT NULL,
  `spice_name` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `spice_price` int(11) NOT NULL DEFAULT '0',
  `quantity` int(11) NOT NULL DEFAULT '1',
  `bowl_total` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- トリガ `order_bowls`
--
DELIMITER $$
CREATE TRIGGER `trg_order_bowls_share_ordered` AFTER INSERT ON `order_bowls` FOR EACH ROW BEGIN
    IF NEW.shared_recipe_id IS NOT NULL THEN
        UPDATE recipe_shares SET status = 'ordered' WHERE id = NEW.shared_recipe_id;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- テーブルの構造 `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_bowl_id` int(11) NOT NULL,
  `ingredient_id` int(11) DEFAULT NULL,
  `ingredient_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `action` enum('added','removed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'added',
  `unit_price` int(11) NOT NULL DEFAULT '0',
  `quantity` int(11) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- トリガ `order_items`
--
DELIMITER $$
CREATE TRIGGER `trg_order_items_reduce_stock` AFTER INSERT ON `order_items` FOR EACH ROW BEGIN
    IF NEW.action = 'added' AND NEW.ingredient_id IS NOT NULL THEN
        UPDATE ingredients
        SET stock = stock - NEW.quantity *
            (SELECT quantity FROM order_bowls WHERE id = NEW.order_bowl_id)
        WHERE id = NEW.ingredient_id;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- テーブルの構造 `order_status_history`
--

CREATE TABLE `order_status_history` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `status` enum('pending','confirmed','preparing','ready','completed','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL,
  `changed_by` int(11) DEFAULT NULL,
  `changed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- テーブルの構造 `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `method` enum('mock_card') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'mock_card',
  `amount` int(11) NOT NULL,
  `status` enum('pending','succeeded','failed','refunded') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `mock_transaction_id` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `card_last4` char(4) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- トリガ `payments`
--
DELIMITER $$
CREATE TRIGGER `trg_payments_mark_order_insert` AFTER INSERT ON `payments` FOR EACH ROW BEGIN
    IF NEW.status = 'succeeded' THEN
        UPDATE orders SET payment_status = 'paid' WHERE id = NEW.order_id;
    ELSEIF NEW.status = 'refunded' THEN
        UPDATE orders SET payment_status = 'refunded' WHERE id = NEW.order_id;
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_payments_mark_order_update` AFTER UPDATE ON `payments` FOR EACH ROW BEGIN
    IF NEW.status <> OLD.status THEN
        IF NEW.status = 'succeeded' THEN
            UPDATE orders SET payment_status = 'paid' WHERE id = NEW.order_id;
        ELSEIF NEW.status = 'refunded' THEN
            UPDATE orders SET payment_status = 'refunded' WHERE id = NEW.order_id;
        END IF;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- テーブルの構造 `price_history`
--

CREATE TABLE `price_history` (
  `id` int(11) NOT NULL,
  `target_type` enum('menu','ingredient','soup','spice') COLLATE utf8mb4_unicode_ci NOT NULL,
  `target_id` int(11) NOT NULL,
  `old_price` int(11) NOT NULL,
  `new_price` int(11) NOT NULL,
  `changed_by` int(11) DEFAULT NULL,
  `changed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- テーブルのデータのダンプ `price_history`
--

INSERT INTO `price_history` (`id`, `target_type`, `target_id`, `old_price`, `new_price`, `changed_by`, `changed_at`) VALUES
(1, 'ingredient', 53, 120, 150, NULL, '2026-10-23 01:27:44'),
(2, 'ingredient', 54, 130, 132, NULL, '2026-10-26 00:37:38'),
(3, 'spice', 4, 50, 0, NULL, '2026-10-26 03:17:54'),
(4, 'spice', 5, 100, 0, NULL, '2026-10-26 03:17:54'),
(5, 'menu', 11, 900, 950, NULL, '2026-10-27 00:50:35');

-- --------------------------------------------------------

--
-- テーブルの構造 `recipe_shares`
--

CREATE TABLE `recipe_shares` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `menu_id` int(11) NOT NULL,
  `has_soup` tinyint(1) NOT NULL DEFAULT '1',
  `soup_id` int(11) NOT NULL,
  `spice_level_id` int(11) DEFAULT NULL,
  `message` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('new','seen','ordered') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'new',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- トリガ `recipe_shares`
--
DELIMITER $$
CREATE TRIGGER `trg_recipe_shares_only_friends` BEFORE INSERT ON `recipe_shares` FOR EACH ROW BEGIN
    IF NOT EXISTS (SELECT 1 FROM friends
                   WHERE user_id = NEW.sender_id AND friend_id = NEW.receiver_id) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'You can only share with your friends';
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- テーブルの構造 `recipe_share_ingredients`
--

CREATE TABLE `recipe_share_ingredients` (
  `share_id` int(11) NOT NULL,
  `ingredient_id` int(11) NOT NULL,
  `action` enum('added','removed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'added',
  `quantity` int(11) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- テーブルの構造 `soups`
--

CREATE TABLE `soups` (
  `id` int(11) NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `price` int(11) NOT NULL DEFAULT '0',
  `is_available` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- テーブルのデータのダンプ `soups`
--

INSERT INTO `soups` (`id`, `name`, `description`, `price`, `is_available`, `sort_order`) VALUES
(1, '麻辣スープ', 'しびれる辛さの四川風スープ', 0, 1, 1),
(2, '白湯スープ', 'まろやかな豚骨スープ（辛くない）', 0, 1, 2),
(3, 'トマトスープ', '甘酸っぱいトマトスープ', 50, 1, 3),
(4, 'きのこスープ', 'あっさりきのこスープ', 50, 1, 4);

--
-- トリガ `soups`
--
DELIMITER $$
CREATE TRIGGER `trg_soups_price_history` AFTER UPDATE ON `soups` FOR EACH ROW BEGIN
    IF NEW.price <> OLD.price THEN
        INSERT INTO price_history (target_type, target_id, old_price, new_price, changed_by)
        VALUES ('soup', NEW.id, OLD.price, NEW.price, @current_user_id);
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- テーブルの構造 `spice_levels`
--

CREATE TABLE `spice_levels` (
  `id` int(11) NOT NULL,
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `level` int(11) NOT NULL,
  `extra_price` int(11) NOT NULL DEFAULT '0',
  `is_available` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- テーブルのデータのダンプ `spice_levels`
--

INSERT INTO `spice_levels` (`id`, `name`, `level`, `extra_price`, `is_available`) VALUES
(1, '辛くない', 1, 0, 0),
(2, 'ピリ辛', 2, 0, 1),
(3, '中辛', 3, 0, 1),
(4, '大辛', 4, 0, 1),
(5, '激辛', 5, 0, 1),
(6, 'super spicy', 6, 100, 1);

--
-- トリガ `spice_levels`
--
DELIMITER $$
CREATE TRIGGER `trg_spice_price_history` AFTER UPDATE ON `spice_levels` FOR EACH ROW BEGIN
    IF NEW.extra_price <> OLD.extra_price THEN
        INSERT INTO price_history (target_type, target_id, old_price, new_price, changed_by)
        VALUES ('spice', NEW.id, OLD.extra_price, NEW.extra_price, @current_user_id);
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- テーブルの構造 `store_photos`
--

CREATE TABLE `store_photos` (
  `id` int(11) NOT NULL,
  `image_url` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `caption` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT '0',
  `is_visible` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- テーブルのデータのダンプ `store_photos`
--

INSERT INTO `store_photos` (`id`, `image_url`, `caption`, `sort_order`, `is_visible`, `created_at`) VALUES
(1, '/images/store/front.jpg', '店の外観', 1, 1, '2026-10-20 02:42:52'),
(2, '/images/store/bowl.jpg', '人気の麻辣湯', 2, 1, '2026-10-20 02:42:52');

-- --------------------------------------------------------

--
-- テーブルの構造 `store_settings`
--

CREATE TABLE `store_settings` (
  `id` int(11) NOT NULL DEFAULT '1',
  `store_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `opening_hours` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_open` tinyint(1) NOT NULL DEFAULT '1',
  `banner_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- テーブルのデータのダンプ `store_settings`
--

INSERT INTO `store_settings` (`id`, `store_name`, `description`, `address`, `phone`, `opening_hours`, `is_open`, `banner_url`, `updated_at`) VALUES
(1, 'Chilli 鍋', '自分だけの麻辣湯を作ろう', NULL, NULL, '11:00〜21:00', 1, NULL, '2026-10-20 02:42:52');

-- --------------------------------------------------------

--
-- テーブルの構造 `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `role` enum('customer','admin') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'customer',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- ビュー用の代替構造 `v_admin_orders`
-- (実際のビューを参照するには下にあります)
--
CREATE TABLE `v_admin_orders` (
`id` int(11)
,`order_number` varchar(30)
,`customer_name` varchar(100)
,`customer_phone` varchar(20)
,`pickup_time` datetime
,`total_price` int(11)
,`payment_method` enum('mock_card')
,`payment_status` enum('unpaid','paid','refunded')
,`status` enum('pending','confirmed','preparing','ready','completed','cancelled')
,`is_guest` int(1)
,`bowl_count` decimal(32,0)
,`note` text
,`created_at` timestamp
);

-- --------------------------------------------------------

--
-- ビュー用の代替構造 `v_admin_order_details`
-- (実際のビューを参照するには下にあります)
--
CREATE TABLE `v_admin_order_details` (
`order_id` int(11)
,`order_number` varchar(30)
,`pickup_time` datetime
,`status` enum('pending','confirmed','preparing','ready','completed','cancelled')
,`payment_status` enum('unpaid','paid','refunded')
,`order_bowl_id` int(11)
,`menu_name` varchar(100)
,`has_soup` tinyint(1)
,`soup_name` varchar(100)
,`spice_name` varchar(50)
,`quantity` int(11)
,`added_ingredients` varchar(256)
,`removed_ingredients` varchar(256)
,`from_shared_recipe` int(1)
,`bowl_total` int(11)
);

-- --------------------------------------------------------

--
-- ビュー用の代替構造 `v_cart_bowl_prices`
-- (実際のビューを参照するには下にあります)
--
CREATE TABLE `v_cart_bowl_prices` (
`cart_bowl_id` int(11)
,`user_id` int(11)
,`guest_token` varchar(64)
,`menu_name` varchar(100)
,`has_soup` tinyint(1)
,`soup_name` varchar(100)
,`spice_name` varchar(50)
,`quantity` int(11)
,`menu_price` int(11)
,`soup_price` bigint(11)
,`spice_price` bigint(11)
,`added_price` decimal(42,0)
,`bowl_total` decimal(53,0)
);

-- --------------------------------------------------------

--
-- ビュー用の代替構造 `v_ingredient_display`
-- (実際のビューを参照するには下にあります)
--
CREATE TABLE `v_ingredient_display` (
`category_id` int(11)
,`category_name` varchar(100)
,`category_order` int(11)
,`id` int(11)
,`name` varchar(100)
,`price` int(11)
,`image_url` varchar(500)
,`is_available` tinyint(1)
);

-- --------------------------------------------------------

--
-- ビュー用の代替構造 `v_menu_display`
-- (実際のビューを参照するには下にあります)
--
CREATE TABLE `v_menu_display` (
`id` int(11)
,`name` varchar(100)
,`description` text
,`price` int(11)
,`image_url` varchar(500)
,`is_available` tinyint(1)
);

-- --------------------------------------------------------

--
-- ビュー用の代替構造 `v_order_history`
-- (実際のビューを参照するには下にあります)
--
CREATE TABLE `v_order_history` (
`user_id` int(11)
,`order_id` int(11)
,`order_number` varchar(30)
,`ordered_at` timestamp
,`pickup_time` datetime
,`status` enum('pending','confirmed','preparing','ready','completed','cancelled')
,`payment_status` enum('unpaid','paid','refunded')
,`total_price` int(11)
,`bowl_count` decimal(32,0)
,`bowls_summary` varchar(256)
);

-- --------------------------------------------------------

--
-- ビュー用の代替構造 `v_order_history_bowls`
-- (実際のビューを参照するには下にあります)
--
CREATE TABLE `v_order_history_bowls` (
`user_id` int(11)
,`order_id` int(11)
,`order_number` varchar(30)
,`ordered_at` timestamp
,`status` enum('pending','confirmed','preparing','ready','completed','cancelled')
,`order_bowl_id` int(11)
,`menu_id` int(11)
,`menu_name` varchar(100)
,`has_soup` tinyint(1)
,`soup_id` int(11)
,`soup_name` varchar(100)
,`spice_level_id` int(11)
,`spice_name` varchar(50)
,`quantity` int(11)
,`added_ingredients` varchar(256)
,`removed_ingredients` varchar(256)
,`bowl_total` int(11)
,`from_shared_recipe` int(1)
);

-- --------------------------------------------------------

--
-- ビュー用の代替構造 `v_recipe_inbox`
-- (実際のビューを参照するには下にあります)
--
CREATE TABLE `v_recipe_inbox` (
`share_id` int(11)
,`receiver_id` int(11)
,`sender_id` int(11)
,`sender_name` varchar(100)
,`status` enum('new','seen','ordered')
,`menu_name` varchar(100)
,`has_soup` tinyint(1)
,`soup_name` varchar(100)
,`spice_level` int(11)
,`spice_name` varchar(50)
,`ingredients` varchar(256)
,`price_now` decimal(43,0)
,`message` varchar(200)
,`created_at` timestamp
);

-- --------------------------------------------------------

--
-- ビュー用の代替構造 `v_store_announcements`
-- (実際のビューを参照するには下にあります)
--
CREATE TABLE `v_store_announcements` (
`id` int(11)
,`title` varchar(100)
,`body` text
,`start_date` date
,`end_date` date
);

-- --------------------------------------------------------

--
-- ビュー用の構造 `v_admin_orders`
--
DROP TABLE IF EXISTS `v_admin_orders`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_admin_orders`  AS SELECT `o`.`id` AS `id`, `o`.`order_number` AS `order_number`, `o`.`customer_name` AS `customer_name`, `o`.`customer_phone` AS `customer_phone`, `o`.`pickup_time` AS `pickup_time`, `o`.`total_price` AS `total_price`, `o`.`payment_method` AS `payment_method`, `o`.`payment_status` AS `payment_status`, `o`.`status` AS `status`, isnull(`o`.`user_id`) AS `is_guest`, (select sum(`ob`.`quantity`) from `order_bowls` `ob` where (`ob`.`order_id` = `o`.`id`)) AS `bowl_count`, `o`.`note` AS `note`, `o`.`created_at` AS `created_at` FROM `orders` AS `o` WHERE (`o`.`status` not in ('completed','cancelled'))  ;

-- --------------------------------------------------------

--
-- ビュー用の構造 `v_admin_order_details`
--
DROP TABLE IF EXISTS `v_admin_order_details`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_admin_order_details`  AS SELECT `o`.`id` AS `order_id`, `o`.`order_number` AS `order_number`, `o`.`pickup_time` AS `pickup_time`, `o`.`status` AS `status`, `o`.`payment_status` AS `payment_status`, `ob`.`id` AS `order_bowl_id`, `ob`.`menu_name` AS `menu_name`, `ob`.`has_soup` AS `has_soup`, `ob`.`soup_name` AS `soup_name`, `ob`.`spice_name` AS `spice_name`, `ob`.`quantity` AS `quantity`, (select group_concat(concat(`oi`.`ingredient_name`,' ×',`oi`.`quantity`) separator ', ') from `order_items` `oi` where ((`oi`.`order_bowl_id` = `ob`.`id`) and (`oi`.`action` = 'added'))) AS `added_ingredients`, (select group_concat(`oi`.`ingredient_name` separator ', ') from `order_items` `oi` where ((`oi`.`order_bowl_id` = `ob`.`id`) and (`oi`.`action` = 'removed'))) AS `removed_ingredients`, (`ob`.`shared_recipe_id` is not null) AS `from_shared_recipe`, `ob`.`bowl_total` AS `bowl_total` FROM (`orders` `o` join `order_bowls` `ob` on((`ob`.`order_id` = `o`.`id`))) WHERE (`o`.`status` not in ('completed','cancelled'))  ;

-- --------------------------------------------------------

--
-- ビュー用の構造 `v_cart_bowl_prices`
--
DROP TABLE IF EXISTS `v_cart_bowl_prices`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_cart_bowl_prices`  AS SELECT `cb`.`id` AS `cart_bowl_id`, `cb`.`user_id` AS `user_id`, `cb`.`guest_token` AS `guest_token`, `m`.`name` AS `menu_name`, `cb`.`has_soup` AS `has_soup`, `s`.`name` AS `soup_name`, `sp`.`name` AS `spice_name`, `cb`.`quantity` AS `quantity`, `m`.`price` AS `menu_price`, coalesce(`s`.`price`,0) AS `soup_price`, coalesce(`sp`.`extra_price`,0) AS `spice_price`, coalesce((select sum((`i`.`price` * `ci`.`quantity`)) from (`cart_bowl_ingredients` `ci` join `ingredients` `i` on((`i`.`id` = `ci`.`ingredient_id`))) where ((`ci`.`cart_bowl_id` = `cb`.`id`) and (`ci`.`action` = 'added'))),0) AS `added_price`, ((((`m`.`price` + coalesce(`s`.`price`,0)) + coalesce(`sp`.`extra_price`,0)) + coalesce((select sum((`i`.`price` * `ci`.`quantity`)) from (`cart_bowl_ingredients` `ci` join `ingredients` `i` on((`i`.`id` = `ci`.`ingredient_id`))) where ((`ci`.`cart_bowl_id` = `cb`.`id`) and (`ci`.`action` = 'added'))),0)) * `cb`.`quantity`) AS `bowl_total` FROM (((`cart_bowls` `cb` join `menus` `m` on((`m`.`id` = `cb`.`menu_id`))) left join `soups` `s` on((`s`.`id` = `cb`.`soup_id`))) left join `spice_levels` `sp` on((`sp`.`id` = `cb`.`spice_level_id`)))  ;

-- --------------------------------------------------------

--
-- ビュー用の構造 `v_ingredient_display`
--
DROP TABLE IF EXISTS `v_ingredient_display`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_ingredient_display`  AS SELECT `c`.`id` AS `category_id`, `c`.`name` AS `category_name`, `c`.`sort_order` AS `category_order`, `i`.`id` AS `id`, `i`.`name` AS `name`, `i`.`price` AS `price`, `i`.`image_url` AS `image_url`, `i`.`is_available` AS `is_available` FROM (`ingredients` `i` join `categories` `c` on((`c`.`id` = `i`.`category_id`))) WHERE (`i`.`is_deleted` = FALSE)  ;

-- --------------------------------------------------------

--
-- ビュー用の構造 `v_menu_display`
--
DROP TABLE IF EXISTS `v_menu_display`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_menu_display`  AS SELECT `menus`.`id` AS `id`, `menus`.`name` AS `name`, `menus`.`description` AS `description`, `menus`.`price` AS `price`, `menus`.`image_url` AS `image_url`, `menus`.`is_available` AS `is_available` FROM `menus` WHERE (`menus`.`is_deleted` = FALSE)  ;

-- --------------------------------------------------------

--
-- ビュー用の構造 `v_order_history`
--
DROP TABLE IF EXISTS `v_order_history`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_order_history`  AS SELECT `o`.`user_id` AS `user_id`, `o`.`id` AS `order_id`, `o`.`order_number` AS `order_number`, `o`.`created_at` AS `ordered_at`, `o`.`pickup_time` AS `pickup_time`, `o`.`status` AS `status`, `o`.`payment_status` AS `payment_status`, `o`.`total_price` AS `total_price`, (select sum(`ob`.`quantity`) from `order_bowls` `ob` where (`ob`.`order_id` = `o`.`id`)) AS `bowl_count`, (select group_concat(concat(`ob`.`menu_name`,'（',`ob`.`soup_name`,'）×',`ob`.`quantity`) order by `ob`.`id` ASC separator ' / ') from `order_bowls` `ob` where (`ob`.`order_id` = `o`.`id`)) AS `bowls_summary` FROM `orders` AS `o` WHERE (`o`.`user_id` is not null)  ;

-- --------------------------------------------------------

--
-- ビュー用の構造 `v_order_history_bowls`
--
DROP TABLE IF EXISTS `v_order_history_bowls`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_order_history_bowls`  AS SELECT `o`.`user_id` AS `user_id`, `o`.`id` AS `order_id`, `o`.`order_number` AS `order_number`, `o`.`created_at` AS `ordered_at`, `o`.`status` AS `status`, `ob`.`id` AS `order_bowl_id`, `ob`.`menu_id` AS `menu_id`, `ob`.`menu_name` AS `menu_name`, `ob`.`has_soup` AS `has_soup`, `ob`.`soup_id` AS `soup_id`, `ob`.`soup_name` AS `soup_name`, `ob`.`spice_level_id` AS `spice_level_id`, `ob`.`spice_name` AS `spice_name`, `ob`.`quantity` AS `quantity`, (select group_concat(concat(`oi`.`ingredient_name`,' ×',`oi`.`quantity`) order by `oi`.`id` ASC separator ', ') from `order_items` `oi` where ((`oi`.`order_bowl_id` = `ob`.`id`) and (`oi`.`action` = 'added'))) AS `added_ingredients`, (select group_concat(`oi`.`ingredient_name` order by `oi`.`id` ASC separator ', ') from `order_items` `oi` where ((`oi`.`order_bowl_id` = `ob`.`id`) and (`oi`.`action` = 'removed'))) AS `removed_ingredients`, `ob`.`bowl_total` AS `bowl_total`, (`ob`.`shared_recipe_id` is not null) AS `from_shared_recipe` FROM (`orders` `o` join `order_bowls` `ob` on((`ob`.`order_id` = `o`.`id`))) WHERE (`o`.`user_id` is not null)  ;

-- --------------------------------------------------------

--
-- ビュー用の構造 `v_recipe_inbox`
--
DROP TABLE IF EXISTS `v_recipe_inbox`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_recipe_inbox`  AS SELECT `rs`.`id` AS `share_id`, `rs`.`receiver_id` AS `receiver_id`, `rs`.`sender_id` AS `sender_id`, `u`.`name` AS `sender_name`, `rs`.`status` AS `status`, `m`.`name` AS `menu_name`, `rs`.`has_soup` AS `has_soup`, `s`.`name` AS `soup_name`, `sp`.`level` AS `spice_level`, `sp`.`name` AS `spice_name`, (select group_concat(`i`.`name` order by `i`.`id` ASC separator ', ') from (`recipe_share_ingredients` `ri` join `ingredients` `i` on((`i`.`id` = `ri`.`ingredient_id`))) where ((`ri`.`share_id` = `rs`.`id`) and (`ri`.`action` = 'added'))) AS `ingredients`, (((`m`.`price` + `s`.`price`) + coalesce(`sp`.`extra_price`,0)) + coalesce((select sum((`i`.`price` * `ri`.`quantity`)) from (`recipe_share_ingredients` `ri` join `ingredients` `i` on((`i`.`id` = `ri`.`ingredient_id`))) where ((`ri`.`share_id` = `rs`.`id`) and (`ri`.`action` = 'added'))),0)) AS `price_now`, `rs`.`message` AS `message`, `rs`.`created_at` AS `created_at` FROM ((((`recipe_shares` `rs` join `users` `u` on((`u`.`id` = `rs`.`sender_id`))) join `menus` `m` on((`m`.`id` = `rs`.`menu_id`))) join `soups` `s` on((`s`.`id` = `rs`.`soup_id`))) left join `spice_levels` `sp` on((`sp`.`id` = `rs`.`spice_level_id`)))  ;

-- --------------------------------------------------------

--
-- ビュー用の構造 `v_store_announcements`
--
DROP TABLE IF EXISTS `v_store_announcements`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_store_announcements`  AS SELECT `announcements`.`id` AS `id`, `announcements`.`title` AS `title`, `announcements`.`body` AS `body`, `announcements`.`start_date` AS `start_date`, `announcements`.`end_date` AS `end_date` FROM `announcements` WHERE ((`announcements`.`is_published` = TRUE) AND (isnull(`announcements`.`start_date`) OR (`announcements`.`start_date` <= curdate())) AND (isnull(`announcements`.`end_date`) OR (`announcements`.`end_date` >= curdate())))  ;

--
-- ダンプしたテーブルのインデックス
--

--
-- テーブルのインデックス `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

--
-- テーブルのインデックス `cart_bowls`
--
ALTER TABLE `cart_bowls`
  ADD PRIMARY KEY (`id`),
  ADD KEY `menu_id` (`menu_id`),
  ADD KEY `soup_id` (`soup_id`),
  ADD KEY `spice_level_id` (`spice_level_id`),
  ADD KEY `idx_cart_user` (`user_id`),
  ADD KEY `idx_cart_guest` (`guest_token`);

--
-- テーブルのインデックス `cart_bowl_ingredients`
--
ALTER TABLE `cart_bowl_ingredients`
  ADD PRIMARY KEY (`cart_bowl_id`,`ingredient_id`),
  ADD KEY `ingredient_id` (`ingredient_id`);

--
-- テーブルのインデックス `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`);

--
-- テーブルのインデックス `favorites`
--
ALTER TABLE `favorites`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `menu_id` (`menu_id`),
  ADD KEY `soup_id` (`soup_id`),
  ADD KEY `spice_level_id` (`spice_level_id`);

--
-- テーブルのインデックス `favorite_ingredients`
--
ALTER TABLE `favorite_ingredients`
  ADD PRIMARY KEY (`favorite_id`,`ingredient_id`),
  ADD KEY `ingredient_id` (`ingredient_id`);

--
-- テーブルのインデックス `friends`
--
ALTER TABLE `friends`
  ADD PRIMARY KEY (`user_id`,`friend_id`),
  ADD KEY `friend_id` (`friend_id`);

--
-- テーブルのインデックス `ingredients`
--
ALTER TABLE `ingredients`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`);

--
-- テーブルのインデックス `menus`
--
ALTER TABLE `menus`
  ADD PRIMARY KEY (`id`);

--
-- テーブルのインデックス `menu_ingredients`
--
ALTER TABLE `menu_ingredients`
  ADD PRIMARY KEY (`menu_id`,`ingredient_id`),
  ADD KEY `ingredient_id` (`ingredient_id`);

--
-- テーブルのインデックス `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_number` (`order_number`),
  ADD KEY `idx_orders_user` (`user_id`,`created_at`),
  ADD KEY `idx_orders_status` (`status`,`pickup_time`);

--
-- テーブルのインデックス `order_bowls`
--
ALTER TABLE `order_bowls`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `shared_recipe_id` (`shared_recipe_id`),
  ADD KEY `menu_id` (`menu_id`),
  ADD KEY `soup_id` (`soup_id`),
  ADD KEY `spice_level_id` (`spice_level_id`);

--
-- テーブルのインデックス `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_bowl_id` (`order_bowl_id`),
  ADD KEY `ingredient_id` (`ingredient_id`);

--
-- テーブルのインデックス `order_status_history`
--
ALTER TABLE `order_status_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `changed_by` (`changed_by`);

--
-- テーブルのインデックス `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`);

--
-- テーブルのインデックス `price_history`
--
ALTER TABLE `price_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `changed_by` (`changed_by`);

--
-- テーブルのインデックス `recipe_shares`
--
ALTER TABLE `recipe_shares`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `menu_id` (`menu_id`),
  ADD KEY `soup_id` (`soup_id`),
  ADD KEY `spice_level_id` (`spice_level_id`),
  ADD KEY `idx_shares_inbox` (`receiver_id`,`status`,`created_at`);

--
-- テーブルのインデックス `recipe_share_ingredients`
--
ALTER TABLE `recipe_share_ingredients`
  ADD PRIMARY KEY (`share_id`,`ingredient_id`),
  ADD KEY `ingredient_id` (`ingredient_id`);

--
-- テーブルのインデックス `soups`
--
ALTER TABLE `soups`
  ADD PRIMARY KEY (`id`);

--
-- テーブルのインデックス `spice_levels`
--
ALTER TABLE `spice_levels`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `level` (`level`);

--
-- テーブルのインデックス `store_photos`
--
ALTER TABLE `store_photos`
  ADD PRIMARY KEY (`id`);

--
-- テーブルのインデックス `store_settings`
--
ALTER TABLE `store_settings`
  ADD PRIMARY KEY (`id`);

--
-- テーブルのインデックス `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- ダンプしたテーブルの AUTO_INCREMENT
--

--
-- テーブルの AUTO_INCREMENT `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- テーブルの AUTO_INCREMENT `cart_bowls`
--
ALTER TABLE `cart_bowls`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- テーブルの AUTO_INCREMENT `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- テーブルの AUTO_INCREMENT `favorites`
--
ALTER TABLE `favorites`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- テーブルの AUTO_INCREMENT `ingredients`
--
ALTER TABLE `ingredients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=55;

--
-- テーブルの AUTO_INCREMENT `menus`
--
ALTER TABLE `menus`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- テーブルの AUTO_INCREMENT `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- テーブルの AUTO_INCREMENT `order_bowls`
--
ALTER TABLE `order_bowls`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- テーブルの AUTO_INCREMENT `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- テーブルの AUTO_INCREMENT `order_status_history`
--
ALTER TABLE `order_status_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- テーブルの AUTO_INCREMENT `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- テーブルの AUTO_INCREMENT `price_history`
--
ALTER TABLE `price_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- テーブルの AUTO_INCREMENT `recipe_shares`
--
ALTER TABLE `recipe_shares`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- テーブルの AUTO_INCREMENT `soups`
--
ALTER TABLE `soups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- テーブルの AUTO_INCREMENT `spice_levels`
--
ALTER TABLE `spice_levels`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- テーブルの AUTO_INCREMENT `store_photos`
--
ALTER TABLE `store_photos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- テーブルの AUTO_INCREMENT `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- ダンプしたテーブルの制約
--

--
-- テーブルの制約 `announcements`
--
ALTER TABLE `announcements`
  ADD CONSTRAINT `announcements_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- テーブルの制約 `cart_bowls`
--
ALTER TABLE `cart_bowls`
  ADD CONSTRAINT `cart_bowls_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cart_bowls_ibfk_2` FOREIGN KEY (`menu_id`) REFERENCES `menus` (`id`),
  ADD CONSTRAINT `cart_bowls_ibfk_3` FOREIGN KEY (`soup_id`) REFERENCES `soups` (`id`),
  ADD CONSTRAINT `cart_bowls_ibfk_4` FOREIGN KEY (`spice_level_id`) REFERENCES `spice_levels` (`id`);

--
-- テーブルの制約 `cart_bowl_ingredients`
--
ALTER TABLE `cart_bowl_ingredients`
  ADD CONSTRAINT `cart_bowl_ingredients_ibfk_1` FOREIGN KEY (`cart_bowl_id`) REFERENCES `cart_bowls` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cart_bowl_ingredients_ibfk_2` FOREIGN KEY (`ingredient_id`) REFERENCES `ingredients` (`id`);

--
-- テーブルの制約 `favorites`
--
ALTER TABLE `favorites`
  ADD CONSTRAINT `favorites_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `favorites_ibfk_2` FOREIGN KEY (`menu_id`) REFERENCES `menus` (`id`),
  ADD CONSTRAINT `favorites_ibfk_3` FOREIGN KEY (`soup_id`) REFERENCES `soups` (`id`),
  ADD CONSTRAINT `favorites_ibfk_4` FOREIGN KEY (`spice_level_id`) REFERENCES `spice_levels` (`id`);

--
-- テーブルの制約 `favorite_ingredients`
--
ALTER TABLE `favorite_ingredients`
  ADD CONSTRAINT `favorite_ingredients_ibfk_1` FOREIGN KEY (`favorite_id`) REFERENCES `favorites` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `favorite_ingredients_ibfk_2` FOREIGN KEY (`ingredient_id`) REFERENCES `ingredients` (`id`);

--
-- テーブルの制約 `friends`
--
ALTER TABLE `friends`
  ADD CONSTRAINT `friends_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `friends_ibfk_2` FOREIGN KEY (`friend_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- テーブルの制約 `ingredients`
--
ALTER TABLE `ingredients`
  ADD CONSTRAINT `ingredients_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`);

--
-- テーブルの制約 `menu_ingredients`
--
ALTER TABLE `menu_ingredients`
  ADD CONSTRAINT `menu_ingredients_ibfk_1` FOREIGN KEY (`menu_id`) REFERENCES `menus` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `menu_ingredients_ibfk_2` FOREIGN KEY (`ingredient_id`) REFERENCES `ingredients` (`id`);

--
-- テーブルの制約 `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- テーブルの制約 `order_bowls`
--
ALTER TABLE `order_bowls`
  ADD CONSTRAINT `order_bowls_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_bowls_ibfk_2` FOREIGN KEY (`shared_recipe_id`) REFERENCES `recipe_shares` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `order_bowls_ibfk_3` FOREIGN KEY (`menu_id`) REFERENCES `menus` (`id`),
  ADD CONSTRAINT `order_bowls_ibfk_4` FOREIGN KEY (`soup_id`) REFERENCES `soups` (`id`),
  ADD CONSTRAINT `order_bowls_ibfk_5` FOREIGN KEY (`spice_level_id`) REFERENCES `spice_levels` (`id`);

--
-- テーブルの制約 `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_bowl_id`) REFERENCES `order_bowls` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`ingredient_id`) REFERENCES `ingredients` (`id`);

--
-- テーブルの制約 `order_status_history`
--
ALTER TABLE `order_status_history`
  ADD CONSTRAINT `order_status_history_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_status_history_ibfk_2` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- テーブルの制約 `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE;

--
-- テーブルの制約 `price_history`
--
ALTER TABLE `price_history`
  ADD CONSTRAINT `price_history_ibfk_1` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- テーブルの制約 `recipe_shares`
--
ALTER TABLE `recipe_shares`
  ADD CONSTRAINT `recipe_shares_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `recipe_shares_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `recipe_shares_ibfk_3` FOREIGN KEY (`menu_id`) REFERENCES `menus` (`id`),
  ADD CONSTRAINT `recipe_shares_ibfk_4` FOREIGN KEY (`soup_id`) REFERENCES `soups` (`id`),
  ADD CONSTRAINT `recipe_shares_ibfk_5` FOREIGN KEY (`spice_level_id`) REFERENCES `spice_levels` (`id`);

--
-- テーブルの制約 `recipe_share_ingredients`
--
ALTER TABLE `recipe_share_ingredients`
  ADD CONSTRAINT `recipe_share_ingredients_ibfk_1` FOREIGN KEY (`share_id`) REFERENCES `recipe_shares` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `recipe_share_ingredients_ibfk_2` FOREIGN KEY (`ingredient_id`) REFERENCES `ingredients` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
