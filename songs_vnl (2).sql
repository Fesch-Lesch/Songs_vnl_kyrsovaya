-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Хост: MySQL-8.0:3306
-- Время создания: Ноя 24 2026 г., 22:47
-- Версия сервера: 8.0.45
-- Версия PHP: 8.1.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- База данных: `songs_vnl`
--

DELIMITER $$
--
-- Процедуры
--
CREATE DEFINER=`admin`@`localhost` PROCEDURE `sp_calc_order_full_cost` (IN `p_order_id` INT, OUT `p_cost` DECIMAL(12,2))   BEGIN
  SELECT COALESCE(SUM(oi.qty * (COALESCE(m.mat_cost,0) + COALESCE(op.op_cost,0))), 0) INTO p_cost
  FROM order_items oi
  LEFT JOIN (SELECT pm.album_id, SUM(pm.norm * m.price) mat_cost
               FROM product_materials pm JOIN materials m ON m.id = pm.material_id
              GROUP BY pm.album_id) m ON m.album_id = oi.album_id
  LEFT JOIN (SELECT po.album_id, SUM(po.norm * o2.rate) op_cost
               FROM product_operations po JOIN operations o2 ON o2.id = po.operation_id
              GROUP BY po.album_id) op ON op.album_id = oi.album_id
  WHERE oi.order_id = p_order_id;
END$$

CREATE DEFINER=`admin`@`localhost` PROCEDURE `sp_calc_overdue_fine` (IN `p_order_id` INT, OUT `p_days` INT, OUT `p_fine` DECIMAL(10,2))   BEGIN
  SELECT COALESCE(MAX(GREATEST(DATEDIFF(CURDATE(), oi.rent_to), 0)), 0) INTO p_days
  FROM order_items oi JOIN orders o ON o.id = oi.order_id
  WHERE oi.order_id = p_order_id AND oi.type = 'rent'
    AND o.status = 'issued' AND oi.rent_to < CURDATE();
  SET p_fine = p_days * 50;
END$$

CREATE DEFINER=`admin`@`localhost` PROCEDURE `sp_extend_rent` (IN `p_item_id` INT, IN `p_days` INT)   BEGIN
  UPDATE order_items SET rent_to = DATE_ADD(rent_to, INTERVAL p_days DAY)
   WHERE id = p_item_id AND type = 'rent';
END$$

CREATE DEFINER=`admin`@`localhost` PROCEDURE `sp_order_summary` (IN `p_order_id` INT)   BEGIN
  SELECT o.id, o.status, o.total,
         (SELECT COALESCE(MAX(GREATEST(DATEDIFF(CURDATE(), oi.rent_to),0)),0) * 50
            FROM order_items oi
           WHERE oi.order_id = o.id AND oi.type = 'rent'
             AND o.status = 'issued' AND oi.rent_to < CURDATE()) AS fine
  FROM orders o WHERE o.id = p_order_id;
END$$

CREATE DEFINER=`admin`@`localhost` PROCEDURE `sp_register_failed_attempt` (IN `p_user_id` INT)   BEGIN
  UPDATE users SET failed_attempts = failed_attempts + 1 WHERE id = p_user_id;
END$$

CREATE DEFINER=`admin`@`localhost` PROCEDURE `sp_unblock_user` (IN `p_user_id` INT)   BEGIN
  UPDATE users SET is_blocked = 0, failed_attempts = 0 WHERE id = p_user_id;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Структура таблицы `albums`
--

CREATE TABLE `albums` (
  `id` int NOT NULL,
  `artist` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `title` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `year` int DEFAULT NULL,
  `country` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `format` enum('vinil','cd','dvd') COLLATE utf8mb4_general_ci DEFAULT 'vinil',
  `genre` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `rent_price` decimal(10,2) NOT NULL,
  `popularity` int DEFAULT '0',
  `stock` int DEFAULT '5',
  `about` text COLLATE utf8mb4_general_ci,
  `cover` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `audio` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `albums`
--

INSERT INTO `albums` (`id`, `artist`, `title`, `year`, `country`, `format`, `genre`, `price`, `rent_price`, `popularity`, `stock`, `about`, `cover`, `audio`, `created_at`) VALUES
(1, 'Radiohead', 'In Rainbows', 2007, 'UK', 'vinil', 'rock', 3600.00, 199.00, 95, 4, 'Седьмой студийный альбом британской рок-группы Radiohead. В 2007 году был выпущен в формате «pay what you want» и стал одним из самых обсуждаемых релизов десятилетия. Grammy за лучший альтернативный альбом.', 'images/covers/album_1792560391_566.jpg', 'audio/track_1795095955_343.mp3', '2026-10-21 04:59:58'),
(2, 'Pink Floyd', 'The Dark Side of the Moon', 1973, 'UK', 'vinil', 'rock', 4200.00, 249.00, 98, 0, 'Легендарный концептуальный альбом. Один из самых продаваемых альбомов всех времён — более 45 миллионов копий. Тема — давление современной жизни: время, деньги, безумие, смерть.', 'images/covers/album_1794463979_275.png', 'audio/track_1795097118_780.mp3', '2026-10-21 04:59:58'),
(3, 'Fleetwood Mac', 'Rumours', 1977, 'USA', 'vinil', 'rock', 3600.00, 199.00, 92, 1, 'Одиннадцатый студийный альбом. Записан в период распада отношений внутри группы, что придало песням особую эмоциональную глубину. Grammy за альбом года.', 'images/covers/album_1794464010_759.jpg', 'audio/track_1795096597_826.mp3', '2026-10-21 04:59:58'),
(4, 'Type O Negative', 'Life Is Killing Me', 2003, 'USA', 'cd', 'metal', 1990.00, 149.00, 78, 7, 'Шестой студийный альбом готик-метал группы из Бруклина. Мрачный юмор, тяжёлые риффы и характерный бас Питера Стилла.', 'images/covers/album_1794464050_206.png', 'audio/track_1795096702_462.mp3', '2026-10-21 04:59:58'),
(5, 'The Beatles', 'Love', 2006, 'UK', 'cd', 'rock', 2400.00, 179.00, 88, 6, 'Альбом-коллаж, созданный Джорджем и Джайлзом Мартином для шоу Cirque du Soleil. Мэшапы и ремиксы классических треков Beatles.', 'images/covers/album_1794464094_399.jpg', 'audio/track_1795097304_500.mp3', '2026-10-21 04:59:58'),
(6, 'Tame Impala', 'Borderline', 2019, 'Australia', 'cd', 'rock', 1590.00, 99.00, 70, 10, 'Сингл с альбома The Slow Rush. Психоделический поп с фирменным звучанием Кевина Паркера.', 'images/covers/album_1794464128_352.webp', 'audio/track_1795096922_668.mp3', '2026-10-21 04:59:58'),
(7, 'Hozier', 'Nina Cried Power', 2018, 'Ireland', 'vinil', 'folk', 2990.00, 189.00, 82, 3, 'EP-альбом ирландского музыканта. Заглавная песня записана совместно с Мавис Стэплс и посвящена песням протеста.', 'images/covers/album_1794464254_434.jpg', 'audio/track_1795097153_832.mp3', '2026-10-21 04:59:58'),
(8, 'Radiohead', 'The Bends', 1995, 'UK', 'cd', 'rock', 1990.00, 149.00, 85, 7, 'Второй студийный альбом. Переход от гитарного рока к более атмосферному звучанию, ставшему визитной карточкой группы.', 'images/covers/album_1794464293_579.jpg', 'audio/track_1795097187_406.mp3', '2026-10-21 04:59:58');

--
-- Триггеры `albums`
--
DELIMITER $$
CREATE TRIGGER `trg_albums_before_insert` BEFORE INSERT ON `albums` FOR EACH ROW BEGIN
  IF NEW.price <= 0 OR NEW.rent_price <= 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'price и rent_price должны быть > 0';
  END IF;
  IF NEW.stock < 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'stock не может быть отрицательным';
  END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_albums_before_update` BEFORE UPDATE ON `albums` FOR EACH ROW BEGIN
  IF NEW.price <= 0 OR NEW.rent_price <= 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'price и rent_price должны быть > 0';
  END IF;
  IF NEW.stock < 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'stock не может быть отрицательным';
  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Структура таблицы `audit_log`
--

CREATE TABLE `audit_log` (
  `id` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `action` varchar(60) COLLATE utf8mb4_general_ci NOT NULL,
  `entity_type` varchar(40) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `entity_id` varchar(60) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `description` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `snapshot_json` longtext COLLATE utf8mb4_general_ci,
  `ip_address` varchar(45) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `audit_log`
--

INSERT INTO `audit_log` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `snapshot_json`, `ip_address`, `created_at`) VALUES
(1, 1, 'login_success', 'user', '1', 'Успешный вход', NULL, '127.0.0.1', '2026-11-20 16:29:03'),
(2, 1, 'order_create', 'order', '11', 'Оформлен заказ #11 на сумму 5593', NULL, '127.0.0.1', '2026-11-20 16:29:49'),
(3, 1, 'rent_extend', 'order_item', '17', 'Продление аренды на 7 дн.', NULL, '127.0.0.1', '2026-11-20 16:29:55'),
(4, 1, 'order_delete', 'order', '11', 'Удаление заказа пользователем', '{\"id\":11,\"status\":\"issued\",\"has_overdue\":0}', '127.0.0.1', '2026-11-20 16:30:09'),
(5, 1, 'order_create', 'order', '12', 'Оформлен заказ #12 на сумму 5593', NULL, '127.0.0.1', '2026-11-20 16:31:04'),
(6, 1, 'supply_add', 'album', '1', 'Поставка +5 шт. (VinylDist)', NULL, '127.0.0.1', '2026-11-20 16:31:43'),
(7, 1, 'export_albums', 'albums', NULL, 'Экспорт albums в xls', NULL, '127.0.0.1', '2026-11-20 16:31:53'),
(8, 1, 'logout', 'user', '1', 'Выход из системы', NULL, '127.0.0.1', '2026-11-20 16:32:27'),
(9, 2, 'login_fail', 'user', '2', 'Неудачный вход (#1)', NULL, '127.0.0.1', '2026-11-20 16:32:41'),
(10, NULL, 'logout', 'user', '', 'Выход из системы', NULL, '127.0.0.1', '2026-11-20 16:33:19'),
(11, 3, 'register', 'user', '3', 'Регистрация пользователя', NULL, '127.0.0.1', '2026-11-20 18:05:14'),
(12, 3, 'order_create', 'order', '13', 'Оформлен заказ #13 на сумму 4993', NULL, '127.0.0.1', '2026-11-20 18:05:44'),
(13, 3, 'order_create', 'order', '14', 'Оформлен заказ #14 на сумму 4643', NULL, '127.0.0.1', '2026-11-20 18:05:59'),
(14, 3, 'order_create', 'order', '15', 'Оформлен заказ #15 на сумму 3081', NULL, '127.0.0.1', '2026-11-20 18:07:10'),
(15, 3, 'logout', 'user', '3', 'Выход из системы', NULL, '127.0.0.1', '2026-11-20 18:07:17'),
(16, 1, 'login_success', 'user', '1', 'Успешный вход', NULL, '127.0.0.1', '2026-11-20 18:07:25'),
(17, 1, 'backup_create', 'backup', 'backup_2026-11-20_18-08-21.sql', 'Создан бэкап backup_2026-11-20_18-08-21.sql', NULL, '127.0.0.1', '2026-11-20 18:08:21'),
(18, 1, 'login_success', 'user', '1', 'Успешный вход', NULL, '127.0.0.1', '2026-11-21 15:56:06'),
(19, 1, 'backup_create', 'backup', 'В_21_11_2026.sql', 'Создан/обновлён бэкап В_21_11_2026.sql', NULL, '127.0.0.1', '2026-11-21 15:56:16'),
(20, 1, 'backup_create', 'backup', 'В_21_11_2026.sql', 'Создан/обновлён бэкап В_21_11_2026.sql', NULL, '127.0.0.1', '2026-11-21 15:58:15'),
(21, 1, 'backup_create', 'backup', 'В_21_11_2026.sql', 'Создан/обновлён бэкап В_21_11_2026.sql', NULL, '127.0.0.1', '2026-11-21 16:10:00'),
(22, 1, 'backup_create', 'backup', 'В_21_11_2026.sql', 'Создан/обновлён бэкап В_21_11_2026.sql', NULL, '127.0.0.1', '2026-11-21 16:10:06'),
(23, 1, 'backup_create', 'backup', 'В_21_11_2026.sql', 'Создан/обновлён бэкап В_21_11_2026.sql', NULL, '127.0.0.1', '2026-11-21 16:10:17'),
(24, 1, 'backup_delete', 'backup', 'В_21_11_2026.sql', 'Удалён бэкап В_21_11_2026.sql', NULL, '127.0.0.1', '2026-11-21 16:10:41'),
(25, 1, 'backup_create', 'backup', 'В_21_11_2026.sql', 'Создан/обновлён бэкап В_21_11_2026.sql', NULL, '127.0.0.1', '2026-11-21 16:10:43'),
(26, 1, 'backup_create', 'backup', 'В_21_11_2026.sql', 'Создан/обновлён бэкап В_21_11_2026.sql', NULL, '127.0.0.1', '2026-11-21 16:16:27'),
(27, 1, 'login_success', 'user', '1', 'Успешный вход', NULL, '127.0.0.1', '2026-11-24 18:57:27'),
(28, 4, 'register', 'user', '4', 'Регистрация нового пользователя', NULL, '127.0.0.1', '2026-11-24 22:07:18'),
(29, 4, 'login_fail', 'user', '4', 'Неудачный вход (#1)', NULL, '127.0.0.1', '2026-11-24 22:11:00'),
(30, 4, 'login_fail', 'user', '4', 'Неудачный вход (#2)', NULL, '127.0.0.1', '2026-11-24 22:11:06'),
(31, 4, 'login_fail', 'user', '4', 'Неудачный вход (#3)', NULL, '127.0.0.1', '2026-11-24 22:11:18'),
(32, 1, 'login_success', 'user', '1', 'Успешный вход', NULL, '127.0.0.1', '2026-11-24 22:12:58'),
(33, 1, 'login_success', 'user', '1', 'Успешный вход', NULL, '127.0.0.1', '2026-11-24 22:31:17');

-- --------------------------------------------------------

--
-- Структура таблицы `doc_templates`
--

CREATE TABLE `doc_templates` (
  `doc_key` varchar(40) COLLATE utf8mb4_general_ci NOT NULL,
  `title` varchar(120) COLLATE utf8mb4_general_ci NOT NULL,
  `body_html` mediumtext COLLATE utf8mb4_general_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `doc_templates`
--

INSERT INTO `doc_templates` (`doc_key`, `title`, `body_html`) VALUES
('invoice', 'Товарная накладная №{{order_id}}', '<h2>Товарная накладная №{{order_id}}</h2><p>Покупатель: {{user_name}}</p><p>Доставка: {{delivery}} — {{address}}</p><p>Позиции: {{items}}</p><p>Итого: {{total}} ₽</p>'),
('rental_contract', 'Договор аренды диска №{{order_id}}', '<h2>Договор аренды №{{order_id}}</h2><p>Клиент: {{user_name}} ({{user_email}}, {{user_phone}})</p><p>Адрес/пункт: {{address}}</p><p>Состав: {{items}}</p><p>Сумма: {{total}} ₽ | Дата: {{created_at}}</p>'),
('return_act', 'Акт приёма-передачи / возврата по заказу №{{order_id}}', '<h2>Акт возврата по заказу №{{order_id}}</h2><p>Клиент: {{user_name}}</p><p>Позиции аренды: {{items}}</p><p>Статус заказа: {{status}}</p>');

-- --------------------------------------------------------

--
-- Структура таблицы `materials`
--

CREATE TABLE `materials` (
  `id` int NOT NULL,
  `name` varchar(120) COLLATE utf8mb4_general_ci NOT NULL,
  `price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `materials`
--

INSERT INTO `materials` (`id`, `name`, `price`) VALUES
(1, 'Заготовка виниловой пластинки', 450.00),
(2, 'Полиграфия: конверт альбома', 180.00),
(3, 'Антистатический внутренний пакет', 40.00);

-- --------------------------------------------------------

--
-- Структура таблицы `notes`
--

CREATE TABLE `notes` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `title` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `body` text COLLATE utf8mb4_general_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `notes`
--

INSERT INTO `notes` (`id`, `user_id`, `title`, `body`, `created_at`) VALUES
(1, 1, 'Пополнение коллекции', 'Приняли 20 пластинок 80-х, нужна опись и ценники.', '2026-10-01 10:12:00'),
(2, 1, 'Ревизия аренды', 'Три диска не вернулись с прошлой недели, проверить штрафы.', '2026-10-05 15:40:00'),
(3, 2, 'Хочу In Rainbows', 'Просьба забронировать винил на выходные.', '2026-10-08 09:05:00'),
(4, 1, 'Новая витрина', 'Обновить обложки на витрине к пятнице.', '2026-10-12 18:22:00');

-- --------------------------------------------------------

--
-- Структура таблицы `operations`
--

CREATE TABLE `operations` (
  `id` int NOT NULL,
  `name` varchar(120) COLLATE utf8mb4_general_ci NOT NULL,
  `rate` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `operations`
--

INSERT INTO `operations` (`id`, `name`, `rate`) VALUES
(1, 'Штамповка сторон A/B', 300.00),
(2, 'Печать и сборка буклета', 120.00),
(3, 'Контроль качества и упаковка', 90.00);

-- --------------------------------------------------------

--
-- Структура таблицы `orders`
--

CREATE TABLE `orders` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `status` enum('paid','issued','done','cancelled') COLLATE utf8mb4_general_ci DEFAULT 'paid',
  `delivery` enum('pickup','courier') COLLATE utf8mb4_general_ci DEFAULT 'pickup',
  `address` varchar(200) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `subtotal` decimal(10,2) DEFAULT NULL,
  `discount` decimal(10,2) DEFAULT '0.00',
  `delivery_cost` decimal(10,2) DEFAULT '0.00',
  `total` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `courier` varchar(120) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `delivery_status` varchar(40) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `orders`
--

INSERT INTO `orders` (`id`, `user_id`, `status`, `delivery`, `address`, `subtotal`, `discount`, `delivery_cost`, `total`, `created_at`, `courier`, `delivery_status`) VALUES
(1, 2, 'issued', 'pickup', 'Тверская, 12', 1393.00, 0.00, 0.00, 1393.00, '2026-10-14 07:28:00', NULL, 'pending'),
(2, 2, 'paid', 'courier', 'Москва, ул. Промышленная, 67', 3600.00, 0.00, 0.00, 3600.00, '2026-10-10 15:22:00', NULL, 'pending'),
(3, 2, 'issued', 'pickup', 'Ленинский, 45', 1393.00, 0.00, 0.00, 1393.00, '2026-10-03 06:46:00', NULL, 'pending'),
(12, 1, 'issued', 'pickup', 'Тверская, 12', 5593.00, 0.00, 0.00, 5593.00, '2026-11-20 09:31:04', NULL, 'paid'),
(13, 3, 'issued', 'pickup', 'Тверская, 12', 4993.00, 0.00, 0.00, 4993.00, '2026-11-20 11:05:44', NULL, 'paid'),
(14, 3, 'issued', 'pickup', 'Тверская, 12', 4643.00, 0.00, 0.00, 4643.00, '2026-11-20 11:05:59', NULL, 'paid'),
(15, 3, 'paid', 'courier', 'Тверская 8', 2990.00, 299.00, 390.00, 3081.00, '2026-11-20 11:07:10', NULL, 'paid');

--
-- Триггеры `orders`
--
DELIMITER $$
CREATE TRIGGER `trg_orders_after_insert` AFTER INSERT ON `orders` FOR EACH ROW BEGIN
  INSERT INTO orders_log (order_id, old_status, new_status) VALUES (NEW.id, NULL, NEW.status);
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_orders_after_update` AFTER UPDATE ON `orders` FOR EACH ROW BEGIN
  IF OLD.status <> NEW.status THEN
    INSERT INTO orders_log (order_id, old_status, new_status) VALUES (NEW.id, OLD.status, NEW.status);
  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Структура таблицы `orders_log`
--

CREATE TABLE `orders_log` (
  `id` int NOT NULL,
  `order_id` int NOT NULL,
  `old_status` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `new_status` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
  `changed_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `orders_log`
--

INSERT INTO `orders_log` (`id`, `order_id`, `old_status`, `new_status`, `changed_at`) VALUES
(1, 8, NULL, 'issued', '2026-11-19 09:27:35'),
(2, 9, NULL, 'issued', '2026-11-19 09:28:02'),
(3, 10, NULL, 'issued', '2026-11-19 20:40:09'),
(4, 11, NULL, 'issued', '2026-11-20 16:29:49'),
(5, 12, NULL, 'issued', '2026-11-20 16:31:04'),
(6, 13, NULL, 'issued', '2026-11-20 18:05:44'),
(7, 14, NULL, 'issued', '2026-11-20 18:05:59'),
(8, 15, NULL, 'paid', '2026-11-20 18:07:10');

-- --------------------------------------------------------

--
-- Структура таблицы `order_items`
--

CREATE TABLE `order_items` (
  `id` int NOT NULL,
  `order_id` int NOT NULL,
  `album_id` int NOT NULL,
  `type` enum('purchase','rent') COLLATE utf8mb4_general_ci NOT NULL,
  `qty` int NOT NULL DEFAULT '1',
  `price` decimal(10,2) NOT NULL,
  `rent_from` date DEFAULT NULL,
  `rent_to` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `album_id`, `type`, `qty`, `price`, `rent_from`, `rent_to`) VALUES
(1, 1, 1, 'rent', 1, 1393.00, '2026-10-14', '2026-10-28'),
(2, 2, 3, 'purchase', 1, 3600.00, NULL, NULL),
(3, 3, 8, 'rent', 1, 1393.00, '2026-10-03', '2026-10-10'),
(18, 12, 3, 'rent', 1, 1393.00, '2026-11-20', '2026-11-27'),
(19, 12, 2, 'purchase', 1, 4200.00, NULL, NULL),
(20, 13, 1, 'rent', 1, 1393.00, '2026-11-20', '2026-11-27'),
(21, 13, 3, 'purchase', 1, 3600.00, NULL, NULL),
(22, 14, 3, 'purchase', 1, 3600.00, NULL, NULL),
(23, 14, 8, 'rent', 1, 1043.00, '2026-11-20', '2026-11-27'),
(24, 15, 7, 'purchase', 1, 2990.00, NULL, NULL);

--
-- Триггеры `order_items`
--
DELIMITER $$
CREATE TRIGGER `trg_order_items_after_delete` AFTER DELETE ON `order_items` FOR EACH ROW BEGIN
  UPDATE albums SET stock = stock + OLD.qty WHERE id = OLD.album_id;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_order_items_after_insert` AFTER INSERT ON `order_items` FOR EACH ROW BEGIN
  UPDATE albums SET stock = GREATEST(stock - NEW.qty, 0) WHERE id = NEW.album_id;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_order_items_before_insert` BEFORE INSERT ON `order_items` FOR EACH ROW BEGIN
  DECLARE v_price DECIMAL(10,2); DECLARE v_rent DECIMAL(10,2);
  SELECT price, rent_price INTO v_price, v_rent FROM albums WHERE id = NEW.album_id;
  IF NEW.type = 'purchase' THEN
    SET NEW.price = v_price * NEW.qty;
  ELSE
    SET NEW.price = v_rent * GREATEST(DATEDIFF(NEW.rent_to, NEW.rent_from), 1) * NEW.qty;
  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Структура таблицы `password_history`
--

CREATE TABLE `password_history` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `password_history`
--

INSERT INTO `password_history` (`id`, `user_id`, `password_hash`, `created_at`) VALUES
(1, 3, '$2y$10$sGMjEqkqmWwwLDpe3zoPYe6Bn5VTfwiW/rBL3v9FEDoPTjLfzvl3i', '2026-11-20 18:05:14'),
(2, 4, '$2y$10$QUqp2toyxmSKXnuj3crPl.pHo5METL2mgn.6rYZYajGPghx2xL3Pu', '2026-11-24 22:07:18');

-- --------------------------------------------------------

--
-- Структура таблицы `product_materials`
--

CREATE TABLE `product_materials` (
  `album_id` int NOT NULL,
  `material_id` int NOT NULL,
  `norm` decimal(8,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `product_materials`
--

INSERT INTO `product_materials` (`album_id`, `material_id`, `norm`) VALUES
(1, 1, 1.00),
(1, 2, 1.00),
(1, 3, 1.00),
(2, 1, 1.00),
(2, 2, 1.00),
(2, 3, 1.00),
(3, 1, 1.00),
(3, 2, 1.00),
(3, 3, 1.00),
(4, 1, 1.00),
(4, 2, 1.00),
(4, 3, 1.00),
(5, 1, 1.00),
(5, 2, 1.00),
(5, 3, 1.00),
(6, 1, 1.00),
(6, 2, 1.00),
(6, 3, 1.00),
(7, 1, 1.00),
(7, 2, 1.00),
(7, 3, 1.00),
(8, 1, 1.00),
(8, 2, 1.00),
(8, 3, 1.00);

-- --------------------------------------------------------

--
-- Структура таблицы `product_operations`
--

CREATE TABLE `product_operations` (
  `album_id` int NOT NULL,
  `operation_id` int NOT NULL,
  `norm` decimal(8,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `product_operations`
--

INSERT INTO `product_operations` (`album_id`, `operation_id`, `norm`) VALUES
(1, 1, 1.00),
(1, 2, 1.00),
(1, 3, 1.00),
(2, 1, 1.00),
(2, 2, 1.00),
(2, 3, 1.00),
(3, 1, 1.00),
(3, 2, 1.00),
(3, 3, 1.00),
(4, 1, 1.00),
(4, 2, 1.00),
(4, 3, 1.00),
(5, 1, 1.00),
(5, 2, 1.00),
(5, 3, 1.00),
(6, 1, 1.00),
(6, 2, 1.00),
(6, 3, 1.00),
(7, 1, 1.00),
(7, 2, 1.00),
(7, 3, 1.00),
(8, 1, 1.00),
(8, 2, 1.00),
(8, 3, 1.00);

-- --------------------------------------------------------

--
-- Структура таблицы `promos`
--

CREATE TABLE `promos` (
  `id` int NOT NULL,
  `code` varchar(40) COLLATE utf8mb4_general_ci NOT NULL,
  `discount_percent` int NOT NULL DEFAULT '10',
  `active` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `promos`
--

INSERT INTO `promos` (`id`, `code`, `discount_percent`, `active`) VALUES
(1, 'VINYL10', 10, 1),
(2, 'RENT15', 15, 1);

-- --------------------------------------------------------

--
-- Структура таблицы `reviews`
--

CREATE TABLE `reviews` (
  `id` int NOT NULL,
  `album_id` int NOT NULL,
  `user_id` int NOT NULL,
  `rating` tinyint(1) NOT NULL,
  `comment` text COLLATE utf8mb4_general_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `songs`
--

CREATE TABLE `songs` (
  `id` int NOT NULL,
  `album_id` int NOT NULL,
  `side` enum('A','B') COLLATE utf8mb4_general_ci NOT NULL,
  `position` int NOT NULL,
  `title` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `duration_sec` int DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `songs`
--

INSERT INTO `songs` (`id`, `album_id`, `side`, `position`, `title`, `duration_sec`) VALUES
(171, 1, 'A', 1, '15 Step', 0),
(172, 1, 'A', 2, 'Bodysnatchers', 0),
(173, 1, 'A', 3, 'Nude', 0),
(174, 1, 'A', 4, 'Weird Fishes/Arpeggi', 0),
(175, 1, 'A', 5, 'All I Need', 0),
(176, 1, 'B', 1, 'Faust Arp', 0),
(177, 1, 'B', 2, 'Reckoner', 0),
(178, 1, 'B', 3, 'House of Cards', 0),
(179, 1, 'B', 4, 'Jigsaw Falling into Place', 0),
(180, 1, 'B', 5, 'Videotape', 0),
(181, 3, 'A', 1, 'Second Hand News', 0),
(182, 3, 'A', 2, 'Dreams', 0),
(183, 3, 'A', 3, 'Never Going Back Again', 0),
(184, 3, 'A', 4, 'Don\'t Stop', 0),
(185, 3, 'A', 5, 'Go Your Own Way', 0),
(186, 3, 'B', 1, 'Songbird', 0),
(187, 3, 'B', 2, 'The Chain', 0),
(188, 3, 'B', 3, 'You Make Loving Fun', 0),
(189, 3, 'B', 4, 'I Don\'t Want to Know', 0),
(190, 3, 'B', 5, 'Gold Dust Woman', 0),
(191, 4, 'A', 1, 'Life Is Killing Me', 0),
(192, 4, 'A', 2, 'How Could She?', 0),
(193, 4, 'A', 3, 'Anesthesia', 0),
(194, 4, 'A', 4, 'Less Than Zero', 0),
(195, 4, 'A', 5, 'Todd\'s Ship Gods', 0),
(196, 4, 'B', 1, 'I Don\'t Wanna Be Me', 0),
(197, 4, 'B', 2, 'I Like Goils', 0),
(198, 4, 'B', 3, '...A Dish Best Served Coldly', 0),
(199, 4, 'B', 4, 'The Dream Is Dead', 0),
(200, 4, 'B', 5, 'Thir13teen', 0),
(211, 6, 'A', 1, 'Borderline', 0),
(212, 6, 'A', 2, 'Patience', 0),
(213, 6, 'A', 3, 'Is It True', 0),
(214, 6, 'A', 4, 'Lost in Yesterday', 0),
(215, 6, 'A', 5, 'Breathe Deeper', 0),
(216, 6, 'B', 1, 'Tomorrow\'s Dust', 0),
(217, 6, 'B', 2, 'On Track', 0),
(218, 6, 'B', 3, 'One More Hour', 0),
(219, 6, 'B', 4, 'Posthumous Forgiveness', 0),
(220, 6, 'B', 5, 'It Might Be Sweet', 0),
(221, 2, 'A', 1, 'Speak to Me', 0),
(222, 2, 'A', 2, 'Breathe', 0),
(223, 2, 'A', 3, 'On the Run', 0),
(224, 2, 'A', 4, 'Time', 0),
(225, 2, 'A', 5, 'The Great Gig in the Sky', 0),
(226, 2, 'B', 1, 'Money', 0),
(227, 2, 'B', 2, 'Us and Them', 0),
(228, 2, 'B', 3, 'Any Colour You Like', 0),
(229, 2, 'B', 4, 'Brain Damage', 0),
(230, 2, 'B', 5, 'Eclipse', 0),
(231, 7, 'A', 1, 'Nina Cried Power (feat. Mavis Staples)', 0),
(232, 7, 'A', 2, 'Nina Cried Power (Solo)', 0),
(233, 7, 'A', 3, 'Shrike', 0),
(234, 7, 'A', 4, 'Wasteland, Baby!', 0),
(235, 7, 'B', 1, 'Sunlight', 0),
(236, 7, 'B', 2, 'Would That I Could', 0),
(237, 7, 'B', 3, 'Movement', 0),
(238, 7, 'B', 4, 'No Plan', 0),
(239, 8, 'A', 1, 'Planet Telex', 0),
(240, 8, 'A', 2, 'The Bends', 0),
(241, 8, 'A', 3, 'High and Dry', 0),
(242, 8, 'A', 4, 'Fake Plastic Trees', 0),
(243, 8, 'A', 5, 'Bones', 0),
(244, 8, 'B', 1, '(Nice Dream)', 0),
(245, 8, 'B', 2, 'Just', 0),
(246, 8, 'B', 3, 'My Iron Lung', 0),
(247, 8, 'B', 4, 'Bullet Proof..I Wish I Was', 0),
(248, 8, 'B', 5, 'Black Star', 0),
(249, 8, 'B', 6, 'Sulk', 0),
(250, 8, 'B', 7, 'Street Spirit (Fade Out)', 0),
(251, 5, 'A', 1, 'Because', 0),
(252, 5, 'A', 2, 'Get Back', 0),
(253, 5, 'A', 3, 'Glass Onion', 0),
(254, 5, 'A', 4, 'Eleanor Rigby / Julia', 0),
(255, 5, 'A', 5, 'I Am the Walrus', 0),
(256, 5, 'B', 1, 'Strawberry Fields Forever', 0),
(257, 5, 'B', 2, 'Within You Without You / Tomorrow Never Knows', 0),
(258, 5, 'B', 3, 'Lucy in the Sky with Diamonds', 0),
(259, 5, 'B', 4, 'Hey Jude', 0),
(260, 5, 'B', 5, 'All You Need Is Love', 0);

-- --------------------------------------------------------

--
-- Структура таблицы `stores`
--

CREATE TABLE `stores` (
  `id` int NOT NULL,
  `address` varchar(200) COLLATE utf8mb4_general_ci NOT NULL,
  `hours` varchar(100) COLLATE utf8mb4_general_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `stores`
--

INSERT INTO `stores` (`id`, `address`, `hours`) VALUES
(1, 'Тверская, 12', '10:00-22:00'),
(2, 'Ленинский, 45', '10:00-21:00'),
(3, 'Мира, 8', 'пн-пт 11:00-20:00');

-- --------------------------------------------------------

--
-- Структура таблицы `supplies`
--

CREATE TABLE `supplies` (
  `id` int NOT NULL,
  `album_id` int NOT NULL,
  `qty` int NOT NULL,
  `supplier` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `supplies`
--

INSERT INTO `supplies` (`id`, `album_id`, `qty`, `supplier`, `created_at`) VALUES
(1, 1, 5, 'VinylDist', '2026-11-20 16:31:43');

-- --------------------------------------------------------

--
-- Структура таблицы `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `login` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `role` enum('client','admin') COLLATE utf8mb4_general_ci DEFAULT 'client',
  `failed_attempts` int NOT NULL DEFAULT '0',
  `is_blocked` tinyint(1) NOT NULL DEFAULT '0',
  `name` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `phone` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `address` varchar(500) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `users`
--

INSERT INTO `users` (`id`, `login`, `email`, `password_hash`, `role`, `failed_attempts`, `is_blocked`, `name`, `phone`, `address`, `created_at`) VALUES
(1, 'admin', 'admin@songs.vnl', '$2y$10$.wY3IvND1OBnhhrauJfKHe5IyeqFbEqPrwFjEbNA2knzJEp8sm2iW', 'admin', 0, 0, 'Админ', '+7 (495) 123-45-67', 'Москва, ул. Тверская, 12', '2026-10-21 04:59:58'),
(2, 'client', 'client@songs.vnl', '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcfl7p92ldGxad68LJZdL17lhWy', 'client', 1, 0, 'Иван Петров', '+7 (900) 850-80-78', 'Москва, ул. Промышленная, 67', '2026-10-21 04:59:58'),
(3, 'client1', 'something@gmail.com', '$2y$10$sGMjEqkqmWwwLDpe3zoPYe6Bn5VTfwiW/rBL3v9FEDoPTjLfzvl3i', 'client', 0, 0, 'enc:7dyTQeNGA1Gh6s+0KbMwxBlBLvC1d3ss0rhgUnBVchN9cPdppuvXJiWBAZ4LwXBu', 'enc:mNUsIIeIUF33QIyAYanZXr1JpyYh7kEAw8cByK74We8=', 'enc:PrWz6SHL4cBA4Bk+vzAzX8W5uyMf4EseaylCJhBI/PA60xm6DtGz6NEgqy35+jdU', '2026-11-20 11:05:14'),
(4, 'client2', 'something1@gmail.com', '$2y$10$QUqp2toyxmSKXnuj3crPl.pHo5METL2mgn.6rYZYajGPghx2xL3Pu', 'client', 3, 1, 'enc:9sDTEzM2MNJLpQ2vB4phzQUTST7ka9TjE84l+mSyHCo=', 'enc:i7vrdOHlmuyBOryOmJrWhlAVXrZXd/gKl3rhI+RJfRo=', 'enc:M0yk9leBAcGL22hu4/0XZHAk+0UZtRdyfJwTEib4jp4svYBcWhLD+oI9sI6F/xQB', '2026-11-24 15:07:18');

--
-- Триггеры `users`
--
DELIMITER $$
CREATE TRIGGER `trg_users_before_update` BEFORE UPDATE ON `users` FOR EACH ROW BEGIN
  IF NEW.failed_attempts >= 3 THEN
    SET NEW.is_blocked = 1;
  END IF;
  IF OLD.is_blocked = 1 AND NEW.is_blocked = 0 THEN
    SET NEW.failed_attempts = 0;
  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Дублирующая структура для представления `v_order_full_cost`
-- (См. Ниже фактическое представление)
--
CREATE TABLE `v_order_full_cost` (
`order_id` int
,`login` varchar(50)
,`full_cost` decimal(65,4)
);

--
-- Индексы сохранённых таблиц
--

--
-- Индексы таблицы `albums`
--
ALTER TABLE `albums`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `audit_log`
--
ALTER TABLE `audit_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_audit_user` (`user_id`),
  ADD KEY `idx_audit_action` (`action`),
  ADD KEY `idx_audit_created` (`created_at`);

--
-- Индексы таблицы `doc_templates`
--
ALTER TABLE `doc_templates`
  ADD PRIMARY KEY (`doc_key`);

--
-- Индексы таблицы `materials`
--
ALTER TABLE `materials`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `notes`
--
ALTER TABLE `notes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Индексы таблицы `operations`
--
ALTER TABLE `operations`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Индексы таблицы `orders_log`
--
ALTER TABLE `orders_log`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `album_id` (`album_id`);

--
-- Индексы таблицы `password_history`
--
ALTER TABLE `password_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ph_user` (`user_id`);

--
-- Индексы таблицы `product_materials`
--
ALTER TABLE `product_materials`
  ADD PRIMARY KEY (`album_id`,`material_id`),
  ADD KEY `material_id` (`material_id`);

--
-- Индексы таблицы `product_operations`
--
ALTER TABLE `product_operations`
  ADD PRIMARY KEY (`album_id`,`operation_id`),
  ADD KEY `operation_id` (`operation_id`);

--
-- Индексы таблицы `promos`
--
ALTER TABLE `promos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_promo_code` (`code`);

--
-- Индексы таблицы `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_rev_album` (`album_id`),
  ADD KEY `fk_rev_user` (`user_id`);

--
-- Индексы таблицы `songs`
--
ALTER TABLE `songs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `album_id` (`album_id`);

--
-- Индексы таблицы `stores`
--
ALTER TABLE `stores`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `supplies`
--
ALTER TABLE `supplies`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sup_album` (`album_id`);

--
-- Индексы таблицы `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `login` (`login`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT для сохранённых таблиц
--

--
-- AUTO_INCREMENT для таблицы `albums`
--
ALTER TABLE `albums`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT для таблицы `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT для таблицы `materials`
--
ALTER TABLE `materials`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT для таблицы `notes`
--
ALTER TABLE `notes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT для таблицы `operations`
--
ALTER TABLE `operations`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT для таблицы `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT для таблицы `orders_log`
--
ALTER TABLE `orders_log`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT для таблицы `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT для таблицы `password_history`
--
ALTER TABLE `password_history`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT для таблицы `promos`
--
ALTER TABLE `promos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT для таблицы `reviews`
--
ALTER TABLE `reviews`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `songs`
--
ALTER TABLE `songs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=261;

--
-- AUTO_INCREMENT для таблицы `stores`
--
ALTER TABLE `stores`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT для таблицы `supplies`
--
ALTER TABLE `supplies`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT для таблицы `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

-- --------------------------------------------------------

--
-- Структура для представления `v_order_full_cost`
--
DROP TABLE IF EXISTS `v_order_full_cost`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_order_full_cost`  AS SELECT `o`.`id` AS `order_id`, `u`.`login` AS `login`, sum((`oi`.`qty` * (coalesce(`m`.`mat_cost`,0) + coalesce(`op`.`op_cost`,0)))) AS `full_cost` FROM ((((`orders` `o` join `users` `u` on((`u`.`id` = `o`.`user_id`))) join `order_items` `oi` on((`oi`.`order_id` = `o`.`id`))) left join (select `pm`.`album_id` AS `album_id`,sum((`pm`.`norm` * `m`.`price`)) AS `mat_cost` from (`product_materials` `pm` join `materials` `m` on((`m`.`id` = `pm`.`material_id`))) group by `pm`.`album_id`) `m` on((`m`.`album_id` = `oi`.`album_id`))) left join (select `po`.`album_id` AS `album_id`,sum((`po`.`norm` * `o2`.`rate`)) AS `op_cost` from (`product_operations` `po` join `operations` `o2` on((`o2`.`id` = `po`.`operation_id`))) group by `po`.`album_id`) `op` on((`op`.`album_id` = `oi`.`album_id`))) GROUP BY `o`.`id`, `u`.`login` ;

--
-- Ограничения внешнего ключа сохраненных таблиц
--

--
-- Ограничения внешнего ключа таблицы `notes`
--
ALTER TABLE `notes`
  ADD CONSTRAINT `notes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`album_id`) REFERENCES `albums` (`id`);

--
-- Ограничения внешнего ключа таблицы `password_history`
--
ALTER TABLE `password_history`
  ADD CONSTRAINT `fk_ph_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `product_materials`
--
ALTER TABLE `product_materials`
  ADD CONSTRAINT `product_materials_ibfk_1` FOREIGN KEY (`album_id`) REFERENCES `albums` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_materials_ibfk_2` FOREIGN KEY (`material_id`) REFERENCES `materials` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `product_operations`
--
ALTER TABLE `product_operations`
  ADD CONSTRAINT `product_operations_ibfk_1` FOREIGN KEY (`album_id`) REFERENCES `albums` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_operations_ibfk_2` FOREIGN KEY (`operation_id`) REFERENCES `operations` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `reviews`
--
ALTER TABLE `reviews`
  ADD CONSTRAINT `fk_rev_album` FOREIGN KEY (`album_id`) REFERENCES `albums` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_rev_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `songs`
--
ALTER TABLE `songs`
  ADD CONSTRAINT `songs_ibfk_1` FOREIGN KEY (`album_id`) REFERENCES `albums` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `supplies`
--
ALTER TABLE `supplies`
  ADD CONSTRAINT `fk_sup_album` FOREIGN KEY (`album_id`) REFERENCES `albums` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
