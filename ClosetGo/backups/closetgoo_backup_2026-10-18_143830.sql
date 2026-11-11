-- ------------------------------------------------------------
-- ClosetGoo Database Backup
-- Database   : `gown_management`
-- Generated  : 2026-10-18 14:38:30
-- Server     : MySQL 10.4.32-MariaDB
-- ------------------------------------------------------------

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
SET time_zone = '+00:00';

-- ------------------------------------------------------------
-- Table structure for `admin_chat_messages`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `admin_chat_messages`;
CREATE TABLE `admin_chat_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `sender` enum('user','admin') NOT NULL DEFAULT 'user',
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `idx_user_msg` (`user_id`,`id`),
  KEY `idx_unread` (`user_id`,`sender`,`is_read`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=68 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data for `admin_chat_messages`
INSERT INTO `admin_chat_messages` (`id`, `user_id`, `sender`, `message`, `is_read`, `created_at`) VALUES
('1', '19', 'user', 'boi?ikaw ba yan', '1', '2026-08-21 17:05:16'),
('2', '19', 'admin', 'ano un boi?', '1', '2026-08-21 17:06:08'),
('3', '19', 'admin', 'a', '1', '2026-08-21 17:06:13'),
('4', '19', 'admin', 'aa', '1', '2026-08-21 17:06:16'),
('5', '4', 'user', 'hey can i ask?', '1', '2026-08-21 17:08:44'),
('6', '4', 'admin', 'yes?', '1', '2026-08-21 17:52:08'),
('7', '19', 'user', 'man', '1', '2026-08-21 17:58:53'),
('8', '19', 'admin', 'yes?', '1', '2026-08-21 18:49:49'),
('9', '19', 'admin', 'f', '1', '2026-08-21 19:02:04'),
('10', '19', 'admin', 'aa', '1', '2026-08-21 19:14:20'),
('11', '19', 'user', 'aa', '1', '2026-08-21 19:15:22'),
('12', '4', 'admin', 'a', '1', '2026-08-21 19:22:20'),
('13', '19', 'admin', 'a', '1', '2026-08-21 19:23:00'),
('14', '19', 'admin', 'few', '1', '2026-08-21 19:28:41'),
('15', '19', 'user', '[INQUIRY_ITEMS][{\"id\":81,\"cart_id\":183,\"name\":\"try\",\"category\":\"Ball Gown\",\"price\":\"12.00\",\"price_raw\":12,\"dates\":\"Sep 23 - Sep 23, 2026\",\"image_path\":\"images/gowns/1787144546_1_lv_0_20260227175953.jpg\",\"type\":\"cart\",\"source\":\"In Cart\"}][/INQUIRY_ITEMS]', '1', '2026-08-21 19:50:50'),
('16', '19', 'user', 'can i ask for this product?', '1', '2026-08-21 19:51:21'),
('17', '19', 'user', '[INQUIRY_ITEMS][{\"id\":79,\"reserve_id\":47,\"name\":\"Barongnia\",\"category\":\"Barong (Men)\",\"price\":\"1,500.00\",\"price_raw\":1500,\"dates\":\"Sep 11 - Sep 12, 2026\",\"status\":\"Confirmed\",\"order_status\":\"confirmed\",\"image_path\":\"images/gowns/1785465241_1_748845016_1339924388269501_2354671537121417441_n.jpg\",\"type\":\"purchase\",\"source\":\"Order #47 (Confirmed)\"},{\"id\":76,\"reserve_id\":48,\"name\":\"Gowniana\",\"category\":\"Filipiniana\",\"price\":\"120.00\",\"price_raw\":120,\"dates\":\"Sep 02 - Sep 07, 2026\",\"status\":\"Completed\",\"order_status\":\"completed\",\"image_path\":\"images/gowns/1785463191_1_748948195_1007589628804293_152641743643515275_n.jpg\",\"type\":\"purchase\",\"source\":\"Order #48 (Completed)\"}][/INQUIRY_ITEMS]', '1', '2026-08-21 19:54:48'),
('18', '19', 'admin', 'yes how can i help you?', '1', '2026-08-21 20:01:12'),
('19', '19', 'admin', 'hmm', '1', '2026-08-21 20:08:57'),
('20', '19', 'user', 'p', '1', '2026-08-23 09:18:34'),
('21', '19', 'admin', 'oik', '1', '2026-08-23 10:57:02'),
('22', '19', 'user', '[INQUIRY_ITEMS][{\"id\":18,\"reserve_id\":56,\"name\":\"Formal \",\"category\":\"Men\",\"price\":\"14,198.00\",\"price_raw\":14198,\"dates\":\"Aug 27 - Sep 01, 2026\",\"status\":\"Pending\",\"order_status\":\"pending\",\"image_path\":\"uploads/1772014454_1_me3.jpg\",\"type\":\"purchase\",\"source\":\"Order #56 (Pending)\"}][/INQUIRY_ITEMS]', '1', '2026-08-24 23:50:10'),
('23', '19', 'user', 'can i buy this?', '1', '2026-08-24 23:50:29'),
('24', '19', 'admin', 'yes ofcourse', '1', '2026-08-24 23:50:56'),
('25', '19', 'user', 'hey', '1', '2026-08-27 21:42:39'),
('26', '19', 'admin', 'yes?', '1', '2026-08-27 21:44:04'),
('27', '19', 'user', 'boi?', '1', '2026-08-28 07:24:25'),
('28', '19', 'admin', 'bakit?', '1', '2026-08-28 07:24:57'),
('29', '19', 'user', 'hhh', '1', '2026-08-28 07:26:28'),
('30', '27', 'user', 'a', '1', '2026-09-01 20:55:23'),
('31', '27', 'admin', '?', '1', '2026-09-01 20:55:55'),
('33', '19', 'user', '[INQUIRY_ITEMS][{\"id\":100,\"name\":\"a\",\"category\":\"Barong (Men)\",\"price\":\"499.00\",\"price_raw\":499,\"purchase_price\":\"2,000.00\",\"discount\":0,\"color\":\"a\",\"image_path\":\"uploads/1789363807_1_Screenshot 2026-09-14 132840.png\",\"type\":\"product\",\"source\":\"Store Product\"}][/INQUIRY_ITEMS]', '1', '2026-09-15 08:33:15'),
('34', '19', 'user', '[INQUIRY_ITEMS][{\"id\":95,\"name\":\"a\",\"category\":\"Ball Gown (Kids)\",\"price\":\"99.00\",\"price_raw\":99,\"purchase_price\":\"1,000.00\",\"discount\":0,\"color\":\"a\",\"image_path\":\"images/gowns/1789054412_1_6aa2cdcca99cb.jpg\",\"type\":\"product\",\"source\":\"Store Product\"}][/INQUIRY_ITEMS]', '1', '2026-09-16 05:58:57'),
('35', '19', 'user', 'dewqdede', '1', '2026-09-16 05:58:59'),
('36', '30', 'user', '[INQUIRY_ITEMS][{\"id\":105,\"name\":\"Lavender Bow Elegance\",\"category\":\"Bridesmaid Gowns\",\"price\":\"999.00\",\"price_raw\":999,\"purchase_price\":\"2,500.00\",\"discount\":0,\"color\":\"violet\",\"image_path\":\"images/gowns/1789581087_1_6aaad71f684c6.jpg\",\"type\":\"product\",\"source\":\"Store Product\"}][/INQUIRY_ITEMS]\nddede', '1', '2026-09-22 00:40:31'),
('37', '30', 'admin', 'sas', '1', '2026-09-22 00:41:40'),
('38', '30', 'user', 'sasa', '1', '2026-09-22 00:45:34'),
('39', '30', 'user', '[INQUIRY_ITEMS][{\"id\":105,\"name\":\"Lavender Bow Elegance\",\"category\":\"Bridesmaid Gowns\",\"price\":\"999.00\",\"price_raw\":999,\"purchase_price\":\"2,500.00\",\"discount\":0,\"color\":\"violet\",\"image_path\":\"images/gowns/1789581087_1_6aaad71f684c6.jpg\",\"type\":\"product\",\"source\":\"Store Product\"}][/INQUIRY_ITEMS]\ndad', '1', '2026-09-22 00:48:22'),
('40', '30', 'user', 'sas', '1', '2026-09-22 01:08:45'),
('42', '19', 'user', 'a', '1', '2026-10-10 14:33:02'),
('43', '19', 'admin', 'aa', '1', '2026-10-10 14:47:25'),
('48', '30', 'admin', 'aa', '0', '2026-10-10 15:44:27'),
('49', '19', 'admin', 'aa', '1', '2026-10-10 15:44:31'),
('50', '19', 'user', '[INQUIRY_ITEMS][{\"id\":108,\"name\":\"avbb\",\"category\":\"Ball Gown\",\"price\":\"1.00\",\"price_raw\":1,\"purchase_price\":\"2.00\",\"discount\":0,\"color\":\"dwq\",\"image_path\":\"images/gowns/1790319317_1_6ab61ad500f9b.jpg\",\"type\":\"product\",\"source\":\"Store Product\"}][/INQUIRY_ITEMS]', '1', '2026-10-10 16:08:10'),
('51', '1', 'user', 'Hello from PHP CLI test', '1', '2026-10-10 16:25:02'),
('52', '19', 'user', 'aa', '1', '2026-10-10 17:01:13'),
('53', '19', 'user', 's', '1', '2026-10-10 17:44:08'),
('54', '19', 'user', '[INQUIRY_ITEMS][{\"id\":108,\"name\":\"avbb\",\"category\":\"Ball Gown\",\"price\":\"1.00\",\"price_raw\":1,\"purchase_price\":\"2.00\",\"discount\":0,\"color\":\"dwq\",\"image_path\":\"images/gowns/1790319317_1_6ab61ad500f9b.jpg\",\"type\":\"product\",\"source\":\"Store Product\"}][/INQUIRY_ITEMS]', '1', '2026-10-10 17:59:48'),
('55', '1', 'user', 'Testing from test script', '1', '2026-10-10 18:12:40'),
('56', '1', 'user', 'Hello Admin, this is a test from customer', '1', '2026-10-10 18:35:00'),
('57', '19', 'user', 'aa', '1', '2026-10-10 18:35:15'),
('58', '1', 'admin', 'aa', '0', '2026-10-10 18:46:56'),
('59', '19', 'admin', 'aa', '1', '2026-10-10 18:47:06'),
('60', '19', 'user', 'aa', '1', '2026-10-10 19:02:01'),
('61', '1', 'user', 'test', '1', '2026-10-10 19:04:37'),
('62', '1', 'user', 'FormData test message', '1', '2026-10-10 19:11:43'),
('63', '19', 'user', '[INQUIRY_ITEMS][{\"id\":108,\"name\":\"avbb\",\"category\":\"Ball Gown\",\"price\":\"1.00\",\"price_raw\":1,\"purchase_price\":\"2.00\",\"discount\":0,\"color\":\"dwq\",\"image_path\":\"images/gowns/1790319317_1_6ab61ad500f9b.jpg\",\"type\":\"product\",\"source\":\"Store Product\"},{\"id\":109,\"name\":\"a\",\"category\":\"Ball Gown\",\"price\":\"1.00\",\"price_raw\":1,\"purchase_price\":\"2.00\",\"discount\":0,\"color\":\"fcedws\",\"image_path\":\"images/gowns/1790320942_1_6ab6212e2b2ba.jpg\",\"type\":\"product\",\"source\":\"Store Product\"}][/INQUIRY_ITEMS]\r\nwdwdwdwdw', '1', '2026-10-10 19:13:27'),
('64', '19', 'user', 'J', '1', '2026-10-10 19:26:15'),
('65', '19', 'user', '[INQUIRY_ITEMS][{\"id\":93,\"name\":\"Ivory Lace Elegance\",\"category\":\"Wedding Gowns\",\"price\":\"5,000.00\",\"price_raw\":5000,\"purchase_price\":\"18,000.00\",\"discount\":50,\"color\":\"Ivory\",\"image_path\":\"images/gowns/1788970151_1_6aa184a7be73c.jpg\",\"type\":\"product\",\"source\":\"Store Product\"}][/INQUIRY_ITEMS]', '1', '2026-10-13 18:09:29'),
('66', '19', 'admin', 'yes?', '1', '2026-10-13 18:11:20'),
('67', '19', 'user', '[INQUIRY_ITEMS][{\"id\":102,\"name\":\"Ivory Elegance\",\"category\":\"Wedding Gowns\",\"price\":\"6,000.00\",\"price_raw\":6000,\"purchase_price\":\"18,000.00\",\"discount\":0,\"color\":\"Ivory, Champagne\",\"image_path\":\"images/gowns/1789578453_1_6aaaccd5560f9.jpg\",\"type\":\"product\",\"source\":\"Store Product\"},{\"id\":103,\"name\":\"Champagne_Elegance\",\"category\":\"Wedding Gowns\",\"price\":\"6,500.00\",\"price_raw\":6500,\"purchase_price\":\"14,500.00\",\"discount\":0,\"color\":\"Ivory, Champagne\",\"image_path\":\"images/gowns/1789578585_1_6aaacd590aa04.jpg\",\"type\":\"product\",\"source\":\"Store Product\"}][/INQUIRY_ITEMS]\r\nas', '1', '2026-10-13 18:15:18');

-- ------------------------------------------------------------
-- Table structure for `admin_users`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `admin_users`;
CREATE TABLE `admin_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `firstname` varchar(50) NOT NULL,
  `lastname` varchar(50) NOT NULL,
  `suffix` varchar(20) DEFAULT NULL,
  `middlename` varchar(50) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `verification_token` varchar(255) DEFAULT NULL,
  `token_expiry` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=235056 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data for `admin_users`
INSERT INTO `admin_users` (`id`, `firstname`, `lastname`, `suffix`, `middlename`, `email`, `password`, `created_at`, `verification_token`, `token_expiry`) VALUES
('7', 'Alfred', 'Vergara', NULL, 'Carolino', 'vergaraalfred143@gmail.com', '$2y$10$/ATm/PvxwT6.Mh6gpyKJhuBMGuZQn82Kw95nLXQ94hdgcXZc6/O5e', '2026-02-09 13:58:50', NULL, NULL),
('141717', 'Alfred', 'Vergara', NULL, 'Carolino', 'francisvillasper833@gmail.com', '$2y$10$J1hmhfLJBnCLJoaBhCSZ3O0KYXZyBoBPNu3Un5BWxCoG7AxMuC1t.', '2026-08-26 06:09:00', NULL, NULL),
('221508', 'Rojemar', 'Notarte', NULL, 'Rentutar', 'notarterojemar@gmail.com', '$2y$10$/8561OaW/WRdWzM2IcTJ3OQG/feE2I6k6HKseYrNuiqUNOZYLJMo6', '2026-09-04 14:10:47', '37fc9c766ea627f13bb42d021453cf4baec188378629802561df2b75ade9c99b', '1789049447'),
('221552', 'Aljon', 'Lucero', NULL, '', 'aljonlucero81@gmail.com', '$2y$10$/IMWa4YqVkTjiPUaGEm2CuYCL0M3DU0DVIGli2UAkGWTIPe7S0RSm', '2026-09-04 14:15:13', '433f79bbc8cd929df5e9c6209a3f0a4f6f55e39501be246706f83504cd8541fb', '1789049713'),
('221593', 'Angelica', 'Aquino', NULL, '', 'Annje2727@gmail.com', '$2y$10$XnxW4bdX5S7ISSQ.LMpjruPsQjbIx79nRYJXnLHWIUEW9LaJtvjOC', '2026-09-04 14:16:09', '1d4ec0e1213cdaa2500bb8b0ff2890ded64e43ce74ec527521e08b1038c1a33b', '1789049769'),
('235052', 'Alfred', 'Vergara', 'V', 'Carolino', 'alfcode4@gmail.com', '$2y$10$iP/BkXp3XGYm63gfiLoKde5rZX88IJi87OeJfmvSKDyon0njcm.a2', '2026-09-16 14:10:41', NULL, NULL),
('235053', 'Admin', 'User', NULL, '', 'admin@gown.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '2026-09-26 08:20:59', NULL, NULL);

-- ------------------------------------------------------------
-- Table structure for `ai_conversations`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `ai_conversations`;
CREATE TABLE `ai_conversations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `user_identifier` varchar(255) DEFAULT NULL,
  `question` text NOT NULL,
  `answer` text NOT NULL,
  `product_ids` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data for `ai_conversations`
INSERT INTO `ai_conversations` (`id`, `user_id`, `user_identifier`, `question`, `answer`, `product_ids`, `created_at`) VALUES
('20', '30', NULL, 'What\'s your budget range? (Buy: ₱1,000 - ₱3,000)', 'Here are products within your buying budget of ₱1,000 - ₱3,000.', '[78,95,76]', '2026-09-21 03:03:24');

-- ------------------------------------------------------------
-- Table structure for `ai_questions`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `ai_questions`;
CREATE TABLE `ai_questions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `question` text NOT NULL,
  `ai_message` text NOT NULL,
  `response_type` enum('skin_tone','size','color','style','budget','none') NOT NULL DEFAULT 'none',
  `options_config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`options_config`)),
  `display_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data for `ai_questions`
INSERT INTO `ai_questions` (`id`, `question`, `ai_message`, `response_type`, `options_config`, `display_order`, `is_active`, `created_at`, `updated_at`) VALUES
('1', 'Can you suggest products based on my skin?', 'Select your skin type from the options below.', 'skin_tone', '{\"light\":[\"White\",\"Ivory\",\"Cream\",\"Beige\",\"Camel\",\"Light Gray\",\"Charcoal\",\"Navy\",\"Royal Blue\",\"Cobalt Blue\",\"Sky Blue\",\"Baby Blue\",\"Emerald Green\",\"Forest Green\",\"Teal\",\"Turquoise\",\"Mint Green\",\"Lavender\",\"Lilac\",\"Blush Pink\",\"Rose Pink\",\"Coral\",\"Peach\",\"Burgundy\",\"Wine\",\"Soft Red\",\"Plum\"],\"medium-light\":[\"Ivory\",\"Cream\",\"Beige\",\"Camel\",\"Light Gray\",\"Navy\",\"Royal Blue\",\"Sky Blue\",\"Emerald Green\",\"Teal\",\"Turquoise\",\"Lavender\",\"Lilac\",\"Blush Pink\",\"Rose Pink\",\"Coral\",\"Peach\"],\"medium\":[\"Beige\",\"Camel\",\"Light Gray\",\"Navy\",\"Royal Blue\",\"Emerald Green\",\"Teal\",\"Lavender\",\"Rose Pink\",\"Coral\",\"Burgundy\",\"Wine\"],\"medium-dark\":[\"Camel\",\"Charcoal\",\"Navy\",\"Forest Green\",\"Emerald Green\",\"Burgundy\",\"Wine\",\"Plum\"],\"dark\":[\"Charcoal\",\"Navy\",\"Forest Green\",\"Burgundy\",\"Wine\",\"Plum\"]}', '1', '1', '2026-08-01 16:04:22', '2026-08-01 16:04:22'),
('2', 'What size do you need?', 'Select your preferred size from the options below.', 'size', '[\"XS\",\"S\",\"M\",\"L\",\"XL\",\"XXL\",\"XXXL\"]', '2', '1', '2026-08-01 16:04:22', '2026-08-01 16:04:22');

-- ------------------------------------------------------------
-- Table structure for `ai_skinbased`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `ai_skinbased`;
CREATE TABLE `ai_skinbased` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `question` text NOT NULL,
  `ai_message` text NOT NULL,
  `skin_tone_colors` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`skin_tone_colors`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data for `ai_skinbased`
INSERT INTO `ai_skinbased` (`id`, `question`, `ai_message`, `skin_tone_colors`, `created_at`, `updated_at`) VALUES
('1', 'Can you suggest products based on my skin?', 'Select your skin type from the options below.', '{\"light\":[\"White\",\"Ivory\",\"Cream\",\"Beige\",\"Camel\",\"Light Gray\",\"Charcoal\",\"Navy\",\"Royal Blue\",\"Cobalt Blue\",\"Sky Blue\",\"Baby Blue\",\"Emerald Green\",\"Forest Green\",\"Teal\",\"Turquoise\",\"Mint Green\",\"Lavender\",\"Lilac\",\"Blush Pink\",\"Rose Pink\",\"Coral\",\"Peach\",\"Burgundy\",\"Wine\",\"Soft Red\",\"Plum\",\"Boy\",\"a\"],\"medium-light\":[\"Ivory\",\"Cream\",\"Beige\",\"Camel\",\"Light Gray\",\"Navy\",\"Royal Blue\",\"Sky Blue\",\"Emerald Green\",\"Teal\",\"Turquoise\",\"Lavender\",\"Lilac\",\"Blush Pink\",\"Rose Pink\",\"Coral\",\"Peach\"],\"medium\":[\"Beige\",\"Camel\",\"Light Gray\",\"Navy\",\"Royal Blue\",\"Emerald Green\",\"Teal\",\"Lavender\",\"Rose Pink\",\"Coral\",\"Burgundy\",\"Wine\"],\"medium-dark\":[\"kulay na may bitaw\"],\"dark\":[\"Charcoal\",\"Navy\",\"Forest Green\",\"Burgundy\",\"Wine\",\"Plum\",\"arayko\",\"marlon color\"]}', '2026-07-14 23:51:30', '2026-08-25 12:55:58');

-- ------------------------------------------------------------
-- Table structure for `app_settings`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `app_settings`;
CREATE TABLE `app_settings` (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data for `app_settings`
INSERT INTO `app_settings` (`setting_key`, `setting_value`, `updated_at`) VALUES
('footer_about_link', 'login_user/about_us.php', '2026-10-02 04:33:58'),
('footer_about_title', 'ABOUT CLOSETGOO', '2026-09-01 16:39:41'),
('footer_app_download_link', '#', '2026-09-01 16:39:41'),
('footer_app_qr', 'uploads/gcash/app_qr_1788194871_6248.png', '2026-09-01 16:47:51'),
('footer_app_title', 'CLOSETGOO APP DOWNLOAD', '2026-09-01 16:39:41'),
('footer_contact_title', 'CONTACT US', '2026-09-01 16:39:41'),
('footer_facebook', 'https://www.facebook.com/share/1ByZXxHFGR/?mibextid=wwXIfr', '2026-10-02 04:16:46'),
('footer_instagram', 'https://www.instagram.com/iam_alff?igsh=YWdjcmQ2MmJ0OXkw', '2026-09-01 16:39:41'),
('footer_linkedin', 'https://linkedin.com', '2026-09-01 16:39:41'),
('footer_privacy_link', 'login_user/privacy.php', '2026-09-01 16:39:41'),
('footer_social_title', 'FOLLOW US', '2026-09-01 16:39:41'),
('footer_terms_link', 'login_user/terms.php', '2026-09-01 16:39:41'),
('footer_twitter', 'https://twitter.com', '2026-09-01 16:39:41'),
('gcash_account_name', 'Alfred Vergara', '2026-09-01 16:18:27'),
('gcash_number', '09', '2026-09-04 16:19:17'),
('gcash_qr_path', 'uploads/gcash/gcash_qr_1789494613_4545.jpg', '2026-09-16 17:50:13'),
('site_address', 'San Jose Drive Poblacion, Alaminos City, Pangasinan', '2026-09-01 16:47:26'),
('site_email', 'jmbmanagementservices18@gmail.com', '2026-09-01 16:39:41'),
('site_name', 'ClosetGo', '2026-09-25 10:01:23'),
('site_phone', '09511760135', '2026-09-01 16:47:26');

-- ------------------------------------------------------------
-- Table structure for `banners`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `banners`;
CREATE TABLE `banners` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) DEFAULT '',
  `image_path` varchar(500) NOT NULL,
  `link_url` varchar(500) DEFAULT NULL,
  `display_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data for `banners`
INSERT INTO `banners` (`id`, `title`, `image_path`, `link_url`, `display_order`, `is_active`, `created_at`, `updated_at`) VALUES
('1', 'ClosetGo Promo 1', 'uploads/banners/banner_1789088622_1610.jpg', '', '1', '1', '2026-09-04 11:31:34', '2026-09-12 01:03:42'),
('2', 'ClosetGo Promo 2', 'uploads/banners/banner_1789088635_2484.jpg', '', '2', '1', '2026-09-04 11:31:34', '2026-09-12 01:03:55'),
('3', 'ClosetGo Promo 3', 'uploads/banners/banner_1789088644_4445.jpg', '', '3', '1', '2026-09-04 11:31:34', '2026-09-12 01:04:04'),
('4', 'ClosetGo Promo 4', 'images/background/4.jpg', '', '4', '0', '2026-09-04 11:31:34', '2026-10-13 16:18:31'),
('6', '', 'uploads/banners/banner_1788437060_6970.png', 'https://www.youtube.com/', '5', '0', '2026-09-04 12:04:20', '2026-09-04 12:09:15');

-- ------------------------------------------------------------
-- Table structure for `categories`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category_name` varchar(255) NOT NULL,
  `category_image` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `size_label_1` varchar(255) DEFAULT 'Size 1',
  `size_label_2` varchar(255) DEFAULT 'Size 2',
  `size_label_3` varchar(255) DEFAULT 'Size 3',
  `size_label_1_min` int(11) DEFAULT 60,
  `size_label_1_max` int(11) DEFAULT 70,
  `size_label_2_min` int(11) DEFAULT 70,
  `size_label_2_max` int(11) DEFAULT 80,
  `size_label_3_min` int(11) DEFAULT 80,
  `size_label_3_max` int(11) DEFAULT 90,
  `size_guide_image` varchar(255) DEFAULT NULL,
  `size_label_4` varchar(255) DEFAULT 'Size 4',
  `size_label_5` varchar(255) DEFAULT 'Size 5',
  `size_label_6` varchar(255) DEFAULT 'Size 6',
  `size_label_7` varchar(255) DEFAULT 'Size 7',
  `size_label_8` varchar(255) DEFAULT 'Size 8',
  `size_label_9` varchar(255) DEFAULT 'Size 9',
  `size_label_10` varchar(255) DEFAULT 'Size 10',
  PRIMARY KEY (`id`),
  UNIQUE KEY `category_name` (`category_name`)
) ENGINE=InnoDB AUTO_INCREMENT=43 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data for `categories`
INSERT INTO `categories` (`id`, `category_name`, `category_image`, `created_at`, `size_label_1`, `size_label_2`, `size_label_3`, `size_label_1_min`, `size_label_1_max`, `size_label_2_min`, `size_label_2_max`, `size_label_3_min`, `size_label_3_max`, `size_guide_image`, `size_label_4`, `size_label_5`, `size_label_6`, `size_label_7`, `size_label_8`, `size_label_9`, `size_label_10`) VALUES
('27', 'Ball Gown', 'uploads/categories/1785462239_749891888_1429256962584601_3703562816299015350_n.jpg', '2026-08-01 01:11:34', 'Bust', 'Hips', 'Waist', '60', '70', '70', '80', '80', '90', 'uploads/categories/1785460294_sizeguide_Screenshot 2026-07-29 225430.png', 'Length', '', '', '', '', '', ''),
('29', 'Wedding Wear for Men', 'uploads/categories/1785462674_747819868_2052013565403088_5970661662051004986_n.jpg', '2026-08-01 01:36:12', 'Shoulder', 'Chest', 'Waist', '60', '70', '70', '80', '80', '90', 'uploads/categories/1785461772_sizeguide_Screenshot 2026-07-29 225819.png', 'Sleeve', 'Top Length', 'Lower Length', '', '', '', ''),
('30', 'Filipiniana', 'uploads/categories/1785463064_751097365_1036866295498007_6571478545646815775_n.jpg', '2026-08-01 01:57:44', 'Bust', 'Hips', 'Waist', '60', '70', '70', '80', '80', '90', NULL, 'Length', '', '', '', '', '', ''),
('31', 'Ball Gown (Kids)', 'uploads/categories/1785463592_750814842_1025478363451937_1157314207985503136_n.jpg', '2026-08-01 02:06:32', 'Bust', 'Hips', 'Waist', '60', '70', '70', '80', '80', '90', 'uploads/categories/1786799083_sizeguide_Screenshot 2026-07-29 225430.png', 'Length', '', '', '', '', '', ''),
('32', 'Tuxedo Suit (Kids)', 'uploads/categories/1785464250_749096275_2194238664742268_5756820309430037964_n.jpg', '2026-08-01 02:17:30', 'Chest', 'Hips', 'Waist', '60', '70', '70', '80', '80', '90', 'uploads/categories/1785464474_sizeguide_Screenshot 2026-07-29 225819.png', 'Shoulder', 'Top Length', 'Thigh', 'Outseam', '', '', ''),
('33', 'Barong (Men)', 'uploads/categories/1785464656_748845016_1339924388269501_2354671537121417441_n.jpg', '2026-08-01 02:24:16', 'Chest', 'Shoulder', 'Waist', '60', '70', '70', '80', '80', '90', 'uploads/categories/1785464656_sizeguide_Screenshot 2026-07-29 225819.png', 'Length', 'Sleeve', '', '', '', '', ''),
('36', 'Formal Wear', 'uploads/categories/1788761031_Screenshot 2026-09-04 095041.png', '2026-09-08 06:03:51', 'Bust', 'Hips', 'Waist', '60', '70', '70', '80', '80', '90', 'uploads/categories/1789089248_sizeguide_Screenshot 2026-07-29 225430.png', 'Length ', '', '', '', '', '', ''),
('37', 'Menswear', 'uploads/categories/1788798808_Screenshot 2026-09-08 003300.png', '2026-09-08 16:33:28', 'Bust', 'Hips', 'Waist', '60', '70', '70', '80', '80', '90', NULL, 'Shoulder', 'Top Length', 'Thigh', 'Outseam', '', '', ''),
('39', 'Wedding Gowns', 'uploads/categories/1789314436_y.jpg', '2026-09-14 04:22:21', 'Bust', 'Hips', 'Waist', '60', '70', '70', '80', '80', '90', 'uploads/categories/1789314436_sizeguide_Screenshot 2026-07-29 225602.png', 'Length', '', '', '', '', '', ''),
('41', 'Bridesmaid Gowns', 'uploads/categories/1789580207_5fb52d88-94c0-4151-998b-cccbb6b13670.jpg', '2026-09-17 17:36:47', 'Bust', 'Hips', 'Waist', '60', '70', '70', '80', '80', '90', 'uploads/categories/1789580207_sizeguide_Screenshot 2026-07-29 225430.png', 'Length', '', '', '', '', '', ''),
('42', 'alf', 'uploads/categories/1791828017_Screenshot 2026-09-14 132840.png', '2026-10-13 18:00:17', 'Bust', 'Hips', '', '60', '70', '70', '80', '80', '90', 'uploads/categories/1791828017_sizeguide_Screenshot 2026-09-04 095041.png', '', '', '', '', '', '', '');

-- ------------------------------------------------------------
-- Table structure for `gowns`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `gowns`;
CREATE TABLE `gowns` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `category` varchar(255) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `purchase_price` decimal(10,2) DEFAULT 0.00,
  `discount` decimal(5,2) DEFAULT 0.00,
  `color` varchar(50) NOT NULL DEFAULT 'N/A',
  `description` text DEFAULT NULL,
  `image_path` varchar(500) DEFAULT NULL,
  `image_path2` varchar(500) DEFAULT NULL,
  `image_path3` varchar(500) DEFAULT NULL,
  `image_path4` varchar(500) DEFAULT NULL,
  `model_path` varchar(500) DEFAULT NULL,
  `stock` int(11) DEFAULT 0,
  `size_1_min` int(11) DEFAULT 70,
  `size_1_max` int(11) DEFAULT 120,
  `size_2_min` int(11) DEFAULT 60,
  `size_2_max` int(11) DEFAULT 100,
  `size_3_min` int(11) DEFAULT 80,
  `size_3_max` int(11) DEFAULT 130,
  `size_4_min` int(11) DEFAULT 0,
  `size_4_max` int(11) DEFAULT 0,
  `size_5_min` int(11) DEFAULT 0,
  `size_5_max` int(11) DEFAULT 0,
  `size_6_min` int(11) DEFAULT 0,
  `size_6_max` int(11) DEFAULT 0,
  `size_7_min` int(11) DEFAULT 0,
  `size_7_max` int(11) DEFAULT 0,
  `size_8_min` int(11) DEFAULT 0,
  `size_8_max` int(11) DEFAULT 0,
  `size_9_min` int(11) DEFAULT 0,
  `size_9_max` int(11) DEFAULT 0,
  `size_10_min` int(11) DEFAULT 0,
  `size_10_max` int(11) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=112 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data for `gowns`
INSERT INTO `gowns` (`id`, `name`, `category`, `price`, `purchase_price`, `discount`, `color`, `description`, `image_path`, `image_path2`, `image_path3`, `image_path4`, `model_path`, `stock`, `size_1_min`, `size_1_max`, `size_2_min`, `size_2_max`, `size_3_min`, `size_3_max`, `size_4_min`, `size_4_max`, `size_5_min`, `size_5_max`, `size_6_min`, `size_6_max`, `size_7_min`, `size_7_max`, `size_8_min`, `size_8_max`, `size_9_min`, `size_9_max`, `size_10_min`, `size_10_max`) VALUES
('72', 'WedGown', 'Wedding Gowns', '4000.00', '10000.00', '50.00', 'white', 'Wedgown white', 'uploads/1789088538_1_748480368_1596137428820819_6521129400747979225_n.jpg', 'uploads/1789088542_2_747760993_2081291166081506_6781778182935383264_n.jpg', 'uploads/1789088551_3_749066253_1383323766993840_5088743261391763147_n.jpg', NULL, NULL, '1', '25', '100', '25', '100', '25', '100', '25', '130', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
('73', 'Lavender Dream Gown', 'Ball Gown', '4500.00', '8000.00', '50.00', 'Soft Lavender', 'Elegant gown with sparkling floral embellishments, a fitted corset bodice, and flowing tulle skirt.', 'uploads/1789089103_1_749891888_1429256962584601_3703562816299015350_n (1).jpg', 'uploads/1789089109_2_752488451_1572050401058576_2079822153790773202_n.jpg', 'uploads/1789089117_3_749891888_1429256962584601_3703562816299015350_n.jpg', NULL, 'models/gowns/1789258615_Lavender_Elegance.glb', '4', '25', '95', '25', '72', '25', '86', '25', '155', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
('75', 'WebMen', 'Wedding Wear for Men', '3000.00', '7500.00', '0.00', 'white', 'white wear gown', 'uploads/1789088594_1_750643570_1667382987700317_6094309802904023815_n.jpg', 'uploads/1789088600_2_748385047_1370536048508379_1006682755457678373_n.jpg', NULL, NULL, 'models/gowns/1790371270_Blush_Floral_Tuxedo__1_.glb', '1', '70', '120', '60', '100', '80', '130', '12', '13', '12', '131', '12', '14', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
('76', 'Golden Leaf Gown', 'Filipiniana', '6000.00', '3000.00', '0.00', 'Champagne Gold', 'Elegant mermaid gown with ruched sleeves and delicate golden floral-leaf appliqué detailing.', 'uploads/1789253238_1_3a0d217a-9eb7-4a83-9252-569c892aea6e.jpg', 'uploads/1789253242_2_c7b9a7c6-2736-4f37-9577-7a5b6dd8574f.jpg', NULL, 'uploads/1789253252_4_2cc4d72e-9074-42bc-a04d-fdee3edd1bb0.jpg', 'models/gowns/1789253392_Meshy_AI_Golden_Blossom_Gown_0912224032_texture.glb', '5', '25', '105', '25', '100', '25', '100', '25', '346', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
('78', 'TuxKid', 'Tuxedo Suit (Kids)', '1500.00', '2000.00', '0.00', 'a', 'TuxKid ', 'uploads/1789088454_1_749096275_2194238664742268_5756820309430037964_n.jpg', 'uploads/1789088464_2_746715676_3672216702916240_1124246547704807555_n.jpg', NULL, NULL, NULL, '2', '25', '72', '25', '70', '25', '71', '25', '38', '25', '47', '25', '36', '25', '82', NULL, NULL, NULL, NULL, NULL, NULL),
('79', 'Barongnia', 'Barong (Men)', '1500.00', '3000.00', '0.00', 'gold brown', 'a..', 'uploads/1789089149_1_748845016_1339924388269501_2354671537121417441_n.jpg', 'uploads/1789089154_2_747160162_1594977845677724_8379968117188115819_n.jpg', NULL, NULL, NULL, '5', '25', '160', '25', '160', '25', '35', '25', '35', '25', '35', '25', '35', '25', '35', NULL, NULL, NULL, NULL, NULL, NULL),
('83', 'Duchess', 'Formal Wear', '1.00', '2.00', '0.00', 'Blush, Champagne', 'Duchess Colors: Blush, Champagne', 'uploads/1789088506_1_Screenshot 2026-09-04 095041.png', NULL, NULL, NULL, NULL, '1', '25', '90', '25', '90', '25', '90', '25', '87', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
('84', 'Isabeau', 'Formal Wear', '1.00', '2.00', '0.00', 'Slate Blue, Silver', 'Isabeau Colors: Slate Blue, Silver', 'images/gowns/1788765632_1_6a9e65c099072.png', 'images/gowns/1788765632_2_6a9e65c09932d.png', NULL, NULL, NULL, '1', '25', '90', '25', '90', '24', '90', '25', '90', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('85', 'Valeria', 'Formal Wear', '1.00', '2.00', '0.00', 'Burgundy, Gold', 'Valeria Colors: Burgundy, Gold', 'images/gowns/1788766282_1_6a9e684af1429.png', 'images/gowns/1788766282_2_6a9e684af1f17.png', NULL, NULL, NULL, '1', '25', '90', '25', '90', '25', '90', '24', '110', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('86', 'Adelina ', 'Formal Wear', '1.00', '2.00', '0.00', 'Ivory, Champagne', 'Adelina Colors: Ivory, Champagne', 'images/gowns/1788766499_1_6a9e6923b7e04.png', 'images/gowns/1788766499_2_6a9e6923b84e0.png', NULL, NULL, NULL, '1', '25', '90', '25', '90', '25', '90', '25', '90', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('87', 'Odessa', 'Formal Wear', '1.00', '2.00', '0.00', 'Rust, Gold', 'Odessa Colors: Rust, Gold', 'images/gowns/1788766650_1_6a9e69bab2b89.png', 'images/gowns/1788766650_2_6a9e69bab2ea7.png', NULL, NULL, NULL, '1', '25', '90', '25', '90', '25', '90', '25', '90', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('88', 'Marisol', 'Formal Wear', '1.00', '2.00', '0.00', 'Colors: Dusty Rose', 'Marisol Colors: Dusty Rose', 'images/gowns/1788766758_1_6a9e6a268d7f9.png', 'images/gowns/1788766758_2_6a9e6a268da5c.png', NULL, NULL, NULL, '1', '25', '90', '25', '90', '25', '90', '25', '90', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('89', 'Steel Blue Sequin Blazer', 'Menswear', '1.00', '2.00', '0.00', 'Steel Blue, Silver Sequin', 'Steel Blue Sequin Blazer Colors: Steel Blue, Silver Sequin', 'images/gowns/1788798903_1_6a9ee7b7bbedc.png', 'images/gowns/1788798903_2_6a9ee7b7bc748.png', NULL, NULL, NULL, '1', '25', '90', '25', '90', '25', '90', '25', '90', '25', '90', '25', '90', '28', '86', '0', '0', '0', '0', '0', '0'),
('90', 'a', 'Wedding Gowns', '1.00', '2.00', '0.00', 'white', 'a', 'images/gowns/1788969148_1_6aa180bc6e11e.jpg', 'images/gowns/1788969148_2_6aa180bc6e7e6.jpg', 'images/gowns/1788969148_3_6aa180bc6ee93.jpg', NULL, 'models/gowns/1789061271_Meshy_AI_Silver_Empress_0910172215_texture.glb', '1', '25', '100', '25', '100', '25', '100', '25', '130', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
('91', 'b', 'Wedding Gowns', '95.00', '2.00', '0.00', 'white', 'b', 'images/gowns/1788969227_1_6aa1810b2559b.jpg', 'images/gowns/1788969227_2_6aa1810b259d0.jpg', 'images/gowns/1788969227_3_6aa1810b25d43.jpg', NULL, 'models/gowns/1789051335_Meshy_AI_Ivory_Elegance_0910143643_texture.glb', '1', '25', '100', '25', '100', '25', '100', '25', '130', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
('92', 'Pearl Grace', 'Wedding Gowns', '8000.00', '35000.00', '50.00', 'white', 'Elegant white A-line gown covered in intricate pearl and bead embellishments, featuring a square neckline, delicate shoulder straps, flowing sheer sleeves, a fitted waist, and a dramatic floor-length train.', 'images/gowns/1788970090_1_6aa1846aac0ca.jpg', 'images/gowns/1788970090_2_6aa1846aac3de.jpg', 'images/gowns/1788970090_3_6aa1846aac6ef.jpg', NULL, 'models/gowns/1789260191_Pearl_Grace.glb', '1', '25', '100', '25', '100', '25', '100', '25', '140', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
('93', 'Ivory Lace Elegance', 'Wedding Gowns', '5000.00', '18000.00', '50.00', 'Ivory', 'Sophisticated fitted bridal gown with delicate floral lace appliqués, a sweetheart neckline, structured corset bodice, elegant mermaid silhouette, and flowing lace-trimmed train.', 'images/gowns/1788970151_1_6aa184a7be73c.jpg', 'images/gowns/1788970151_2_6aa184a7beae6.jpg', 'images/gowns/1788970151_3_6aa184a7beee5.jpg', 'uploads/1789259238_4_www.jpg', 'models/gowns/1789258797_Ivory_Lace_Elegance.glb', '1', '25', '100', '25', '100', '25', '100', '25', '330', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
('94', 'e', 'Wedding Gowns', '1.00', '2.00', '0.00', 'white', 'e', 'images/gowns/1788970226_1_6aa184f26fb13.jpg', 'images/gowns/1788970226_2_6aa184f270303.jpg', 'images/gowns/1788970226_3_6aa184f270866.jpg', NULL, NULL, '1', '25', '100', '25', '100', '25', '100', '25', '130', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
('95', 'a', 'Ball Gown (Kids)', '99.00', '1000.00', '0.00', 'a', 'a', 'images/gowns/1789054412_1_6aa2cdcca99cb.jpg', 'images/gowns/1789054412_2_6aa2cdcca9e4a.jpg', 'images/gowns/1789054412_3_6aa2cdccaa303.jpg', 'images/gowns/1789054412_4_6aa2cdccaa73d.jpg', 'models/gowns/1789054412_Meshy_AI_Tribal_Elegance_0910152717_texture.glb', '1', '25', '90', '25', '90', '25', '90', '25', '90', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
('96', 'b', 'Ball Gown (Kids)', '1.00', '2.00', '0.00', 'black', 'a', 'images/gowns/1789059531_1_6aa2e1cbdace3.jpg', 'images/gowns/1789059531_2_6aa2e1cbdafe2.jpg', 'images/gowns/1789059531_3_6aa2e1cbdb37d.jpg', 'images/gowns/1789059531_4_6aa2e1cbdb773.jpg', 'models/gowns/1789060479_Meshy_AI_Scarlet_Elegance_0910164617_texture__1_.glb', '1', '25', '90', '25', '90', '25', '90', '25', '90', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
('97', 'Pearl Elegance Gown', 'Wedding Gowns', '6000.00', '15000.00', '50.00', 'Ivory White', 'Elegant off-shoulder bridal gown featuring intricate pearl and crystal embellishments, a fitted corset-style bodice, layered lace detailing, and a flowing full-length skirt for a romantic, luxurious look.', 'images/gowns/1789061077_1_6aa2e7d52b70b.jpg', 'images/gowns/1789061077_2_6aa2e7d52bbfd.jpg', 'images/gowns/1789061077_3_6aa2e7d52c09c.jpg', 'images/gowns/1789061077_4_6aa2e7d52c777.jpg', 'models/gowns/1789258460_Pearl_Elegance_Gown.glb', '1', '25', '100', '25', '100', '25', '100', '25', '130', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
('98', 'Ruby Bloom Gown', 'Filipiniana', '1000.00', '7000.00', '0.00', 'Deep Ruby Red', 'Glamorous fitted gown with sparkling details, dramatic one-shoulder ruffle, and flowing floral accents.', 'images/gowns/1789254189_1_6aa5da2d56100.jpg', 'images/gowns/1789254189_2_6aa5da2d56452.jpg', 'images/gowns/1789254189_3_6aa5da2d56791.jpg', 'images/gowns/1789254189_4_6aa5da2d56abf.jpg', 'models/gowns/1789254382_Meshy_AI_Crimson_Elegance_0912225456_texture.glb', '1', '25', '100', '25', '100', '25', '100', '25', '340', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
('100', 'Red Embroidered Barong', 'Barong (Men)', '499.00', '2000.00', '0.00', 'Vibrant Red / Crimson Red with White Embroidery', 'A bold crimson-red barong with elegant white embroidery, designed for a distinctive and sophisticated Filipino formal look.', 'uploads/1789581616_1_4a488e10-f101-4a3c-9f39-a3599f456876.jpg', 'uploads/1789581624_2_ac61fd64-530a-421d-80ad-ab98b39ac779.jpg', 'uploads/1789581629_3_39d4c147-d5fc-43c0-9d5e-7aed0e2be837.jpg', 'uploads/1789581641_4_0f5c42d9-407f-4bbb-8554-e0395057c765.jpg', NULL, '2', '26', '100', '26', '100', '26', '100', '26', '100', '26', '100', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
('101', 'Pearl Radiance', 'Wedding Gowns', '5000.00', '15000.00', '0.00', 'Pearl Radiance', 'Pearl_Radiance', 'images/gowns/1789575066_1_6aaabf9ae64fe.jpg', 'images/gowns/1789575066_2_6aaabf9ae6bcb.jpg', 'images/gowns/1789575066_3_6aaabf9ae7297.jpg', 'images/gowns/1789575066_4_6aaabf9ae7843.jpg', 'models/gowns/1789575066_Pearl_Radiance.glb', '1', '25', '100', '252', '100', '25', '100', '25', '330', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('102', 'Ivory Elegance', 'Wedding Gowns', '6000.00', '18000.00', '0.00', 'Ivory, Champagne', 'Ivory Elegance', 'images/gowns/1789578453_1_6aaaccd5560f9.jpg', 'images/gowns/1789578453_2_6aaaccd556400.jpg', 'images/gowns/1789578453_3_6aaaccd556862.jpg', 'images/gowns/1789578453_4_6aaaccd556b8e.jpg', 'models/gowns/1789578453_Ivory_Elegance.glb', '1', '25', '100', '25', '100', '25', '100', '25', '350', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('103', 'Champagne_Elegance', 'Wedding Gowns', '6500.00', '14500.00', '0.00', 'Ivory, Champagne', 'Champagne Elegance', 'images/gowns/1789578585_1_6aaacd590aa04.jpg', 'images/gowns/1789578585_2_6aaacd590afbc.jpg', 'images/gowns/1789578585_3_6aaacd590b36a.jpg', 'images/gowns/1789578585_4_6aaacd590b6df.jpg', 'models/gowns/1789578585_Champagne_Elegance.glb', '1', '25', '100', '25', '100', '252', '100', '25', '330', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('104', 'Blush Ruffle Gown', 'Bridesmaid Gowns', '499.00', '1999.00', '0.00', 'Blush, Champagne', 'Blush Ruffle Gown', 'images/gowns/1789580339_1_6aaad4339b26c.jpg', 'images/gowns/1789580339_2_6aaad4339b593.jpg', 'images/gowns/1789580339_3_6aaad4339b9db.jpg', 'images/gowns/1789580339_4_6aaad4339bc52.jpg', 'models/gowns/1789580339_Blush_Ruffle_Gown.glb', '1', '25', '100', '25', '100', '25', '100', '25', '300', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('105', 'Lavender Bow Elegance', 'Bridesmaid Gowns', '999.00', '2500.00', '0.00', 'violet', 'Lavender Bow Elegance', 'images/gowns/1789581087_1_6aaad71f684c6.jpg', 'images/gowns/1789581087_2_6aaad71f687dc.jpg', 'images/gowns/1789581087_3_6aaad71f68cb5.jpg', 'images/gowns/1789581087_4_6aaad71f690cb.jpg', 'models/gowns/1789581087_Lavender_Bow_Elegance.glb', '1', '25', '100', '25', '100', '25', '100', '25', '300', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('106', 'Classic Embroidered Barong', 'Barong (Men)', '499.00', '2500.00', '0.00', 'Slate Blue / Grayish Blue', 'A timeless symbol of Filipino elegance, tradition, and pride. BARONG celebrates the beauty of the classic Filipino garment with a clean, modern identity.', 'images/gowns/1789583302_1_6aaadfc6e2cce.jpg', 'images/gowns/1789583302_2_6aaadfc6e32aa.jpg', 'images/gowns/1789583302_3_6aaadfc6e3a0b.jpg', 'images/gowns/1789583302_4_6aaadfc6e3d97.jpg', NULL, '8', '25', '100', '25', '100', '25', '100', '25', '100', '25', '100', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
('107', 'Olive Green Embroidered Barong', 'Barong (Men)', '1000.00', '2500.00', '0.00', 'Olive Green / Sage Green', 'A refined olive green barong with elegant cream embroidery, combining traditional Filipino style with a modern, sophisticated look.', 'images/gowns/1789583406_1_6aaae02e8c63c.jpg', 'images/gowns/1789583406_2_6aaae02e8ca7d.jpg', 'images/gowns/1789583406_3_6aaae02e8cd5c.jpg', 'images/gowns/1789583406_4_6aaae02e8f650.jpg', NULL, '2', '25', '100', '25', '100', '25', '100', '25', '100', '25', '100', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('108', 'avbb', 'Ball Gown', '1.00', '2.00', '0.00', 'dwq', 'de', 'images/gowns/1790319317_1_6ab61ad500f9b.jpg', NULL, NULL, NULL, NULL, '2', '12', '122', '12', '122', '1', '2', '1', '2', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
('109', 'a', 'Ball Gown', '1.00', '2.00', '0.00', 'fcedws', 'fwe', 'images/gowns/1790320942_1_6ab6212e2b2ba.jpg', NULL, NULL, NULL, NULL, '2', '1', '2', '1', '2', '1', '2', '1', '2', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('110', 'ads', 'Menswear', '1.00', '1.99', '0.00', 'vcws', 'few', 'images/gowns/1790321056_1_6ab621a02309b.jpg', 'images/gowns/1790321056_2_6ab621a0234ab.jpg', 'images/gowns/1790321056_3_6ab621a0237e9.jpg', 'images/gowns/1790321056_4_6ab621a023cec.png', NULL, '1', '1', '2', '1', '2', '1', '2', '1', '2', '1', '2', '0', '0', '1', '2', '0', '0', '0', '0', '0', '0'),
('111', 'a', 'alf', '1.00', '2.00', '0.00', 'Mlue', 'ew', 'images/gowns/1791828069_1_6acd2065d1ee9.png', NULL, NULL, NULL, NULL, '1', '1', '11', '1', '11', '80', '130', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0');

-- ------------------------------------------------------------
-- Table structure for `order_status_logs`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `order_status_logs`;
CREATE TABLE `order_status_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `reservation_id` int(11) DEFAULT NULL,
  `reservation_code` varchar(50) DEFAULT NULL,
  `reservation_group_id` varchar(50) DEFAULT NULL,
  `product_name` varchar(255) DEFAULT NULL,
  `customer_name` varchar(255) DEFAULT NULL,
  `order_type` varchar(50) DEFAULT 'reserve',
  `old_status` varchar(50) DEFAULT NULL,
  `new_status` varchar(50) NOT NULL,
  `changed_by_type` enum('staff','admin','customer') DEFAULT 'staff',
  `staff_id` int(11) DEFAULT NULL,
  `staff_name` varchar(150) NOT NULL,
  `staff_email` varchar(255) DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_created` (`created_at`),
  KEY `idx_staff_id` (`staff_id`)
) ENGINE=InnoDB AUTO_INCREMENT=42 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data for `order_status_logs`
INSERT INTO `order_status_logs` (`id`, `reservation_id`, `reservation_code`, `reservation_group_id`, `product_name`, `customer_name`, `order_type`, `old_status`, `new_status`, `changed_by_type`, `staff_id`, `staff_name`, `staff_email`, `reason`, `created_at`) VALUES
('1', '65', 'CGW-260826-48F70', 'RES-20260826182257-aa1d', 'Barongnia', 'bb bbb (Walk-In: 09511760135)', 'buy', 'pending', 'completed', 'staff', '1', 'Alf Staff', 'alfcode4@gmail.com', NULL, '2026-08-27 16:22:57'),
('2', '64', 'CGW-260826-56A61', 'RES-20260826180032-4406', 'Alfred', 'Alfred Vergara (Walk-In: 09511760135)', 'buy', 'pending', 'confirmed', 'staff', '1', 'Alf Staff', 'alfcode4@gmail.com', NULL, '2026-08-27 16:00:32'),
('3', '63', 'CGW-260826-75CF1', 'RES-20260826175242-7179', 'Alfred', 'Alfred Vergara (Walk-In: 09511760135)', 'buy', 'pending', 'confirmed', 'staff', '1', 'Alf Staff', 'alfcode4@gmail.com', NULL, '2026-08-27 15:52:42'),
('4', '62', 'CGW-260826-4418E', 'RES-20260826164009-0159', 'viola', 'Maria Santos (Walk-In: 09123456789)', 'reserve', 'pending', 'confirmed', 'staff', '1', 'Alf Staff', 'alfcode4@gmail.com', NULL, '2026-08-27 14:40:09'),
('5', '61', 'CGW-260826-A614E', 'RES-20260826163610-26d4', 'Alfred', 'Alfred Vergara (Walk-In: 09511760135)', 'reserve', 'pending', 'confirmed', 'staff', '1', 'Alf Staff', 'alfcode4@gmail.com', NULL, '2026-08-27 14:36:10'),
('6', '56', 'CG-260822-69685', 'RES-20260822074141-35d8', 'Formal ', 'arsenio endaya', 'reserve', 'pending', 'confirmed', 'staff', '1', 'Alf Staff', 'alfcode4@gmail.com', NULL, '2026-08-23 05:41:41'),
('7', '55', 'CG-260821-AFCBE', 'RES-20260821064445-bc8d', 'viola', 'a vergara', 'reserve', 'pending', 'confirmed', 'staff', '1', 'Alf Staff', 'alfcode4@gmail.com', NULL, '2026-08-22 04:44:45'),
('8', '50', NULL, 'RES-20260818184938-b033', 'KidGo', 'arsenio endaya', 'reserve', 'pending', 'completed', 'staff', '1', 'Alf Staff', 'alfcode4@gmail.com', NULL, '2026-08-19 16:49:38'),
('9', '51', NULL, 'RES-20260818184938-b033', 'zzz', 'arsenio endaya', 'buy', 'pending', 'completed', 'staff', '1', 'Alf Staff', 'alfcode4@gmail.com', NULL, '2026-08-19 16:49:38'),
('10', '48', NULL, 'RES-20260818175019-6d95', 'Gowniana', 'arsenio endaya', 'reserve', 'pending', 'completed', 'staff', '1', 'Alf Staff', 'alfcode4@gmail.com', NULL, '2026-08-19 15:50:19'),
('11', '64', 'CGW-260826-56A61', 'RES-20260826180032-4406', 'Alfred', 'Alfred Vergara (Walk-In: 09511760135)', 'buy', 'confirmed', '0', 'staff', '1', 'Alf Staff', 'alfcode4@gmail.com', '', '2026-09-01 03:56:59'),
('12', '65', 'CGW-260826-48F70', 'RES-20260826182257-aa1d', 'Barongnia', 'bb bbb (Walk-In: 09511760135)', 'buy', 'completed', '0', 'staff', '1', 'Alf Staff', 'alfcode4@gmail.com', '', '2026-09-01 04:45:37'),
('13', '54', 'CG-260821-4E032', 'RES-20260821063158-8c14', 'try', 'arsenio endaya', 'reserve', 'pending', '0', 'staff', '1', 'Alf Staff', 'alfcode4@gmail.com', '', '2026-09-01 04:45:51'),
('14', '66', 'CG-260831-C01F6', 'RES-20260831183315-c362', 'Ember Elegance', 'arsenio endaya', 'reserve', 'pending', '0', 'staff', '102083', 'thea humilde', 'discoveranda@gmail.com', '', '2026-09-02 03:14:49'),
('15', '65', 'CGW-260826-48F70', 'RES-20260826182257-aa1d', 'Barongnia', 'bb bbb (Walk-In: 09511760135)', 'buy', 'confirmed', '0', 'staff', '102083', 'thea humilde', 'discoveranda@gmail.com', '', '2026-09-02 03:22:29'),
('18', '67', 'CG-260907-3DBD8', 'RES-20260907080734-223b', 'Duchess', 'senio endaya', 'reserve', 'pending', 'confirmed', 'admin', '7', 'Alfred Vergara', 'vergaraalfred143@gmail.com', NULL, '2026-09-15 17:00:08'),
('19', '76', 'TEST-SIM-1', '', 'Sim Dress', 'Sim User', 'reserve', 'pending', 'cancelled', 'admin', '7', 'Alfred Vergara', 'vergaraalfred143@gmail.com', 'no pay', '2026-09-15 17:00:23'),
('20', '76', 'TEST-SIM-1', '', 'Sim Dress', 'Sim User', 'reserve', 'cancelled', '0', 'staff', '1', 'Alf Staff', 'alfcode4@gmail.com', '', '2026-09-16 17:17:14'),
('21', '62', 'CGW-260826-4418E', 'RES-20260826164009-0159', 'viola', 'Maria Santos (Walk-In: 09123456789)', 'reserve', 'confirmed', 'completed', 'admin', '235052', 'Alfred Carolino Vergara V', 'alfcode4@gmail.com', NULL, '2026-09-17 14:11:27'),
('22', '44', '', '', 'KidGo', 'arsenio endaya', 'reserve', 'confirmed', 'completed', 'admin', '235052', 'Alfred Carolino Vergara V', 'alfcode4@gmail.com', NULL, '2026-09-17 14:15:34'),
('23', '46', '', 'RES-20260818174304-fb18', 'KidGo', 'arsenio endaya', 'reserve', 'confirmed', 'completed', 'admin', '235052', 'Alfred Carolino Vergara V', 'alfcode4@gmail.com', NULL, '2026-09-17 14:16:12'),
('24', '47', '', 'RES-20260818174304-fb18', 'Barongnia', 'arsenio endaya', 'reserve', 'confirmed', 'completed', 'admin', '235052', 'Alfred Carolino Vergara V', 'alfcode4@gmail.com', NULL, '2026-09-17 14:16:12'),
('25', '78', 'CG-260916-A319B', 'RES-20260916173145-b4a9', 'WedGown', 'senio endaya', 'reserve', 'pending', 'completed', 'admin', '235052', 'Alfred Carolino Vergara V', 'alfcode4@gmail.com', NULL, '2026-09-17 15:33:49'),
('26', '79', 'CG-260916-A6A4D', 'RES-20260916174122-fd9c', 'WedGown', 'senio endaya', 'reserve', 'pending', 'cancelled', 'admin', '235052', 'Alfred Carolino Vergara V', 'alfcode4@gmail.com', 'fwfwwef', '2026-09-17 15:41:34'),
('27', '74', 'CG-260914-05DC6', 'RES-20260914182035-3116', 'a', 'senio endaya', 'buy', 'confirmed', 'pending', 'admin', '235052', 'Alfred Carolino Vergara V', 'alfcode4@gmail.com', NULL, '2026-09-18 17:18:06'),
('28', '72', 'CG-260911-0CACD', 'RES-20260911050753-199e', 'Duchess', 'senio endaya', 'reserve', 'confirmed', 'completed', 'admin', '235052', 'Alfred Carolino Vergara V', 'alfcode4@gmail.com', NULL, '2026-09-22 09:21:40'),
('29', '69', 'CG-260911-B706B', 'RES-20260911045549-129c', 'a', 'senio endaya', 'reserve', 'confirmed', 'completed', 'admin', '235052', 'Alfred Carolino Vergara V', 'alfcode4@gmail.com', NULL, '2026-09-22 09:21:49'),
('30', '70', 'CG-260911-B706B', 'RES-20260911045549-129c', 'a', 'senio endaya', 'reserve', 'confirmed', 'completed', 'admin', '235052', 'Alfred Carolino Vergara V', 'alfcode4@gmail.com', NULL, '2026-09-22 09:21:49'),
('31', '71', 'CG-260911-B706B', 'RES-20260911045549-129c', 'Gowniana', 'senio endaya', 'reserve', 'confirmed', 'completed', 'admin', '235052', 'Alfred Carolino Vergara V', 'alfcode4@gmail.com', NULL, '2026-09-22 09:21:49'),
('32', '73', 'CG-260914-6E97A', 'RES-20260914172435-c4ae', 'a', 'senio endaya', 'reserve', 'confirmed', '0', 'staff', '1', 'Alf Staff', 'alfcode4@gmail.com', '', '2026-09-26 08:39:41'),
('33', '68', 'CGW-260909-76D9F', 'RES-20260909152631-b647', 'zzz', 'Alfred Vergara (Walk-In: 09511760135)', 'reserve', 'confirmed', '0', 'staff', '1', 'Alf Staff', 'alfcode4@gmail.com', '', '2026-09-26 08:40:00'),
('34', '76', 'TEST-SIM-1', '', 'Sim Dress', 'Sim User', 'reserve', 'pending', '0', 'staff', '1', 'Alf Staff', 'alfcode4@gmail.com', 'casdeed', '2026-09-26 08:42:04'),
('35', '80', 'CG-260928-003FF', 'RES-20260928022259-736f', 'Pearl Elegance Gown', 'Alfred endaya', 'reserve', 'pending', 'confirmed', 'admin', '7', 'Alfred Carolino Vergara', 'vergaraalfred143@gmail.com', NULL, '2026-10-04 02:49:19'),
('36', '74', 'CG-260914-05DC6', 'RES-20260914182035-3116', 'a', 'senio endaya', 'buy', 'pending', 'cancelled', 'admin', '7', 'Alfred Carolino Vergara', 'vergaraalfred143@gmail.com', 'no payment', '2026-10-07 01:54:25'),
('37', '66', 'CG-260831-C01F6', 'RES-20260831183315-c362', 'Ember Elegance', 'arsenio endaya', 'reserve', 'confirmed', 'cancelled', 'admin', '7', 'Alfred Carolino Vergara', 'vergaraalfred143@gmail.com', 'Item Damaged or Stained (Requires Repair / Dry Cleaning) — Note: gregr', '2026-10-07 02:27:03'),
('38', '80', 'CG-260928-003FF', 'RES-20260928022259-736f', 'Pearl Elegance Gown', 'Alfred endaya', 'reserve', 'confirmed', '0', 'staff', '1', 'Alf Staff', 'alfcode4@gmail.com', '', '2026-10-08 17:35:13'),
('39', '67', 'CG-260907-3DBD8', 'RES-20260907080734-223b', 'Duchess', 'senio endaya', 'reserve', 'confirmed', '0', 'staff', '1', 'Alf Staff', 'alfcode4@gmail.com', '', '2026-10-08 17:35:27'),
('40', '81', 'CG-261007-F46D2', 'RES-20261007142013-3b5f', 'Pearl Elegance Gown', 'a a', 'reserve', 'pending', '0', 'staff', '1', 'Alf Staff', 'alfcode4@gmail.com', '', '2026-10-08 17:52:17');

-- ------------------------------------------------------------
-- Table structure for `payment_proofs`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `payment_proofs`;
CREATE TABLE `payment_proofs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `reservation_group_id` varchar(50) DEFAULT NULL,
  `image_path` varchar(500) NOT NULL,
  `payment_method` varchar(50) DEFAULT 'gcash',
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data for `payment_proofs`
INSERT INTO `payment_proofs` (`id`, `user_id`, `reservation_group_id`, `image_path`, `payment_method`, `uploaded_at`) VALUES
('1', '0', 'TEST-RES-123', 'uploads/payment_proofs/proof_20260831_182956_90c9988a.jpg', 'gcash', '2026-09-01 16:29:56'),
('2', '19', '', 'uploads/payment_proofs/proof_20260831_183051_a9a5ab58.jpg', 'gcash', '2026-09-01 16:30:51'),
('3', '19', '', 'uploads/payment_proofs/proof_20260831_183312_db831bf9.png', 'gcash', '2026-09-01 16:33:12'),
('4', '19', '', 'uploads/payment_proofs/proof_20260907_080732_c1dae4af.png', 'gcash', '2026-09-08 06:07:32'),
('5', '19', '', 'uploads/payment_proofs/proof_20260911_045449_e93bc896.png', 'gcash', '2026-09-12 02:54:49'),
('6', '19', '', 'uploads/payment_proofs/proof_20260911_050752_b48338f1.png', 'gcash', '2026-09-12 03:07:52'),
('7', '19', '', 'uploads/payment_proofs/proof_20260914_172434_8b9568fc.png', 'QR Ph (RCBC)', '2026-09-15 15:24:34'),
('8', '19', '', 'uploads/payment_proofs/proof_20260914_182034_4607779a.png', 'QR Ph (RCBC)', '2026-09-15 16:20:34'),
('9', '19', '', 'uploads/payment_proofs/proof_20260916_173142_e47c6199.png', 'QR Ph (RCBC)', '2026-09-17 15:31:42'),
('10', '19', '', 'uploads/payment_proofs/proof_20260916_174120_df5895d3.png', 'QR Ph (RCBC)', '2026-09-17 15:41:20'),
('11', '19', '', 'uploads/payment_proofs/proof_20260928_022257_07381048.jpg', 'QR Ph (RCBC)', '2026-09-29 00:22:57'),
('12', '19', '', 'uploads/payment_proofs/proof_20261007_142012_9bd37d68.jpg', 'QR Ph (RCBC)', '2026-10-08 12:20:12'),
('13', '19', '', 'uploads/payment_proofs/proof_20261012_152902_1f7c5ea1.png', 'QR Ph (RCBC)', '2026-10-13 13:29:02');

-- ------------------------------------------------------------
-- Table structure for `product_reviews`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `product_reviews`;
CREATE TABLE `product_reviews` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `reserve_product_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL DEFAULT 0,
  `user_id` int(11) NOT NULL,
  `rating` int(11) NOT NULL DEFAULT 5,
  `review_text` text DEFAULT NULL,
  `photo1_path` varchar(500) DEFAULT NULL,
  `photo2_path` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_review` (`reserve_product_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `product_reviews_ibfk_1` FOREIGN KEY (`reserve_product_id`) REFERENCES `reserve_products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `product_reviews_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `system_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data for `product_reviews`
INSERT INTO `product_reviews` (`id`, `reserve_product_id`, `product_id`, `user_id`, `rating`, `review_text`, `photo1_path`, `photo2_path`, `created_at`) VALUES
('1', '15', '4', '19', '3', 'nice!!', 'images/reviews/1785385075_review_15_1.jpg', 'images/reviews/1785385075_review_15_2.jpg', '2026-07-31 04:17:55'),
('2', '20', '32', '4', '3', 'good', NULL, NULL, '2026-07-31 04:33:13'),
('3', '14', '4', '19', '4', 'very nice', 'images/reviews/1785386237_review_14_1.jpg', NULL, '2026-07-31 04:37:17'),
('4', '21', '4', '4', '5', 'super satiesfied', 'images/reviews/1785387228_review_21_1.jpg', NULL, '2026-07-31 04:53:48'),
('5', '19', '4', '4', '4', 'wow', NULL, NULL, '2026-07-31 04:54:09'),
('6', '28', '77', '19', '3', 'The quality is excellent! The fabric is soft, comfortable, and perfect for my child. Highly recommended!', 'images/reviews/1785510386_review_28_1.jpg', NULL, '2026-08-01 15:06:26'),
('7', '22', '4', '19', '3', 'q', NULL, NULL, '2026-08-01 15:12:23'),
('8', '17', '32', '19', '4', 'a', NULL, NULL, '2026-08-01 15:12:44'),
('9', '13', '39', '19', '5', 'a', NULL, NULL, '2026-08-01 15:29:20'),
('10', '41', '73', '4', '5', 'nice!', NULL, NULL, '2026-08-14 05:57:09'),
('11', '40', '75', '4', '4', 'goodie', NULL, NULL, '2026-08-14 05:57:31'),
('12', '18', '56', '4', '5', 'may bitaw', NULL, NULL, '2026-08-14 05:57:41'),
('13', '39', '78', '19', '3', 'nice', NULL, NULL, '2026-08-15 16:02:20'),
('14', '48', '76', '19', '4', 'this product so fit and comfortable', NULL, NULL, '2026-08-19 16:12:53'),
('15', '49', '77', '19', '4', 'this product so fit and comfortable', NULL, NULL, '2026-08-19 16:12:53'),
('16', '50', '77', '19', '5', '', NULL, NULL, '2026-08-23 14:52:37'),
('17', '51', '32', '19', '5', '', NULL, NULL, '2026-08-23 14:52:37'),
('18', '78', '72', '19', '5', 'Excellent', NULL, NULL, '2026-09-21 04:41:58'),
('21', '46', '77', '19', '5', 'Excellent', NULL, NULL, '2026-09-21 04:42:34'),
('22', '47', '79', '19', '5', 'Excellent', NULL, NULL, '2026-09-21 04:42:34'),
('27', '43', '79', '19', '5', 'nice barong very comfortable', 'images/reviews/1789981911_review_43_1.jpg', NULL, '2026-09-22 09:11:51'),
('28', '72', '83', '19', '5', 'nice wear, very comfortable', NULL, NULL, '2026-09-22 09:22:38'),
('29', '25', '77', '19', '3', 'nice', 'images/reviews/1790319026_review_25_1.jpg', 'images/reviews/1790319026_review_25_2.jpg', '2026-09-26 06:50:26');

-- ------------------------------------------------------------
-- Table structure for `reserve_products`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `reserve_products`;
CREATE TABLE `reserve_products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `reservation_group_id` varchar(50) DEFAULT NULL,
  `reservation_code` varchar(50) DEFAULT NULL,
  `product_id` int(11) DEFAULT 0,
  `product_name` varchar(255) NOT NULL,
  `category` varchar(100) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `model_path` varchar(500) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `payment_method` varchar(50) DEFAULT 'reserve',
  `payment_option` varchar(50) DEFAULT 'full',
  `payment_proof` varchar(500) DEFAULT NULL,
  `downpayment_amount` decimal(10,2) DEFAULT 0.00,
  `balance_amount` decimal(10,2) DEFAULT 0.00,
  `total_price` decimal(10,2) NOT NULL,
  `reserve_date` date NOT NULL,
  `return_date` date NOT NULL,
  `reserve_time` varchar(20) DEFAULT NULL,
  `return_time` varchar(20) DEFAULT NULL,
  `size_1` varchar(50) DEFAULT NULL,
  `size_2` varchar(50) DEFAULT NULL,
  `size_3` varchar(50) DEFAULT NULL,
  `size_4` varchar(50) DEFAULT NULL,
  `size_5` varchar(50) DEFAULT NULL,
  `size_6` varchar(50) DEFAULT NULL,
  `size_7` varchar(50) DEFAULT NULL,
  `size_8` varchar(50) DEFAULT NULL,
  `size_9` varchar(50) DEFAULT NULL,
  `size_10` varchar(50) DEFAULT NULL,
  `quantity` int(11) DEFAULT 1,
  `order_status` enum('pending','confirmed','completed','cancelled') NOT NULL DEFAULT 'pending',
  `product_status` varchar(20) DEFAULT 'reserve',
  `cancellation_reason` text DEFAULT NULL,
  `cancellation_images` text DEFAULT NULL,
  `reserved_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_rescheduled` tinyint(1) NOT NULL DEFAULT 0,
  `rescheduled_at` datetime DEFAULT NULL,
  `original_reserve_date` date DEFAULT NULL,
  `original_return_date` date DEFAULT NULL,
  `reschedule_reason` text DEFAULT NULL,
  `is_rebooked` tinyint(1) NOT NULL DEFAULT 0,
  `rebooked_at` datetime DEFAULT NULL,
  `rebooked_from_code` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `reserve_products_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `system_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=83 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data for `reserve_products`
INSERT INTO `reserve_products` (`id`, `user_id`, `reservation_group_id`, `reservation_code`, `product_id`, `product_name`, `category`, `price`, `model_path`, `first_name`, `last_name`, `payment_method`, `payment_option`, `payment_proof`, `downpayment_amount`, `balance_amount`, `total_price`, `reserve_date`, `return_date`, `reserve_time`, `return_time`, `size_1`, `size_2`, `size_3`, `size_4`, `size_5`, `size_6`, `size_7`, `size_8`, `size_9`, `size_10`, `quantity`, `order_status`, `product_status`, `cancellation_reason`, `cancellation_images`, `reserved_at`, `is_rescheduled`, `rescheduled_at`, `original_reserve_date`, `original_return_date`, `reschedule_reason`, `is_rebooked`, `rebooked_at`, `rebooked_from_code`) VALUES
('13', '19', NULL, NULL, '39', 'alfred', 'Men', '5000.00', 'models/1774285180_Copilot3D-6760bd79-4b48-4c5d-b327-5413d3e50dee.glb', 'arsenio', 'endaya', 'reserve', 'full', NULL, '0.00', '0.00', '10000.00', '2026-07-30', '2026-07-30', '00:00:00', '00:00:00', '70', '60', '80', '', '', '', '', '', '', '', '2', 'completed', 'reserve', NULL, NULL, '2026-07-30 18:11:18', '0', NULL, NULL, NULL, NULL, '0', NULL, NULL),
('14', '19', NULL, NULL, '4', 'Ember Elegance', 'Men', '75.00', 'models/1770917072_Copilot3D-6760bd79-4b48-4c5d-b327-5413d3e50dee.glb', 'arsenio', 'endaya', 'reserve', 'full', NULL, '0.00', '0.00', '75.00', '2026-07-31', '2026-07-31', '00:00:00', '00:00:00', '70', '60', '80', '', '', '', '', '', '', '', '1', 'completed', 'reserve', NULL, NULL, '2026-07-30 18:11:18', '0', NULL, NULL, NULL, NULL, '0', NULL, NULL),
('15', '19', NULL, NULL, '4', 'Ember Elegance', 'Men', '75.00', 'models/1770917072_Copilot3D-6760bd79-4b48-4c5d-b327-5413d3e50dee.glb', 'arsenio', 'endaya', 'reserve', 'full', NULL, '0.00', '0.00', '75.00', '2026-07-29', '2026-07-31', '00:00:00', '00:00:00', '70', '61', '98', '', '', '', '', '', '', '', '1', 'completed', 'reserve', NULL, NULL, '2026-07-30 18:11:18', '0', NULL, NULL, NULL, NULL, '0', NULL, NULL),
('16', '19', NULL, NULL, '70', 'asd', 'alf', '6767.00', '', 'arsenio', 'endaya', 'reserve', 'full', NULL, '0.00', '0.00', '6767.00', '2026-07-30', '2026-07-31', '00:00:00', '00:00:00', '13', '12', '12', '20', '', '1', '', '', '5', '2', '1', 'cancelled', 'reserve', NULL, NULL, '2026-07-30 18:11:18', '0', NULL, NULL, NULL, NULL, '0', NULL, NULL),
('17', '19', NULL, NULL, '32', 'zzz', 'Men', '111.00', 'models/1774279776_Copilot3D-24ed6f87-c342-4169-b75b-5f6c9b8cd9f3.glb', 'arsenio', 'endaya', 'reserve', 'full', NULL, '0.00', '0.00', '111.00', '2026-07-31', '2026-08-31', '00:00:00', '00:00:00', '1', '6', '12', '', '', '', '', '', '', '', '1', 'completed', 'reserve', NULL, NULL, '2026-07-30 18:50:12', '0', NULL, NULL, NULL, NULL, '0', NULL, NULL),
('18', '4', NULL, NULL, '56', 'redgownie', 'Women', '12.00', 'models/1781889384_Copilot3D-8cbc9c06-33cd-4e69-9647-aad448ba00fc.glb', 'galred', 'Vergara', 'reserve', 'full', NULL, '0.00', '0.00', '24.00', '2026-08-14', '2026-08-15', '00:00:00', '00:00:00', '90', '62', '84', '', '', '', '', '', '', '', '2', 'completed', 'reserve', NULL, NULL, '2026-07-31 04:23:53', '0', NULL, NULL, NULL, NULL, '0', NULL, NULL),
('19', '4', NULL, NULL, '4', 'Ember Elegance', 'Men', '75.00', 'models/1770917072_Copilot3D-6760bd79-4b48-4c5d-b327-5413d3e50dee.glb', 'galred', 'Vergara', 'reserve', 'full', NULL, '0.00', '0.00', '75.00', '2026-07-30', '2026-07-31', '00:00:00', '00:00:00', '106', '96', '116', '', '', '', '', '', '', '', '1', 'completed', 'reserve', NULL, NULL, '2026-07-31 04:29:08', '0', NULL, NULL, NULL, NULL, '0', NULL, NULL),
('20', '4', NULL, NULL, '32', 'zzz', 'Men', '700.00', 'models/1774279776_Copilot3D-24ed6f87-c342-4169-b75b-5f6c9b8cd9f3.glb', 'galred', 'Vergara', 'reserve', 'full', NULL, '0.00', '0.00', '700.00', '2026-07-28', '2026-07-31', '00:00:00', '00:00:00', '1', '5', '9', '', '', '', '', '', '', '', '1', 'completed', 'reserve', NULL, NULL, '2026-07-31 04:31:24', '0', NULL, NULL, NULL, NULL, '0', NULL, NULL),
('21', '4', NULL, NULL, '4', 'Ember Elegance', 'Men', '75.00', 'models/1770917072_Copilot3D-6760bd79-4b48-4c5d-b327-5413d3e50dee.glb', 'galred', 'Vergara', 'reserve', 'full', NULL, '0.00', '0.00', '75.00', '2026-07-30', '2026-07-31', '00:00:00', '00:00:00', '106', '96', '116', '', '', '', '', '', '', '', '1', 'completed', 'reserve', NULL, NULL, '2026-07-31 04:52:45', '0', NULL, NULL, NULL, NULL, '0', NULL, NULL),
('22', '19', NULL, NULL, '4', 'Ember Elegance', 'Men', '2222.00', 'models/1770917072_Copilot3D-6760bd79-4b48-4c5d-b327-5413d3e50dee.glb', 'arsenio', 'endaya', 'reserve', 'full', NULL, '0.00', '0.00', '2222.00', '2026-07-31', '2026-07-31', '00:00:00', '00:00:00', '70', '60', '80', '', '', '', '', '', '', '', '1', 'completed', 'reserve', NULL, NULL, '2026-08-01 01:20:16', '0', NULL, NULL, NULL, NULL, '0', NULL, NULL),
('23', '19', NULL, NULL, '79', 'Barongnia', 'Barong', '3000.00', '', 'arsenio', 'endaya', 'reserve', 'full', NULL, '0.00', '0.00', '3000.00', '2026-07-31', '2026-08-01', '00:00:00', '00:00:00', '88', '25', '85', '25', '25', '26', '25', '', '', '', '1', 'cancelled', 'reserve', NULL, NULL, '2026-08-01 10:02:42', '0', NULL, NULL, NULL, NULL, '0', NULL, NULL),
('24', '19', NULL, NULL, '77', 'KidGo', 'Ball Gown (Kids)', '4500.00', '', 'arsenio', 'endaya', 'reserve', 'full', NULL, '0.00', '0.00', '4500.00', '2026-07-31', '2026-07-31', '00:00:00', '00:00:00', '25', '25', '25', '25', '', '', '', '', '', '', '1', 'cancelled', 'reserve', NULL, NULL, '2026-08-01 10:51:29', '0', NULL, NULL, NULL, NULL, '0', NULL, NULL),
('25', '19', NULL, NULL, '77', 'KidGo', 'Ball Gown (Kids)', '2500.00', '', 'arsenio', 'endaya', 'reserve', 'full', NULL, '0.00', '0.00', '2500.00', '2026-09-14', '2026-09-17', '00:00:00', '00:00:00', '25', '25', '25', '25', '', '', '', '', '', '', '1', 'completed', 'reserve', NULL, NULL, '2026-08-01 11:01:23', '0', NULL, NULL, NULL, NULL, '0', NULL, NULL),
('26', '19', NULL, NULL, '77', 'KidGo', 'Ball Gown (Kids)', '2500.00', '', 'arsenio', 'endaya', 'reserve', 'full', NULL, '0.00', '0.00', '2500.00', '2026-10-01', '2026-10-05', '00:00:00', '00:00:00', '25', '25', '25', '25', '', '', '', '', '', '', '1', 'completed', 'reserve', NULL, NULL, '2026-08-01 11:26:14', '0', NULL, NULL, NULL, NULL, '0', NULL, NULL),
('27', '19', NULL, NULL, '77', 'KidGo', 'Ball Gown (Kids)', '2500.00', '', 'arsenio', 'endaya', 'reserve', 'full', NULL, '0.00', '0.00', '2500.00', '2026-10-15', '2026-10-19', '00:00:00', '00:00:00', '25', '25', '25', '25', '', '', '', '', '', '', '1', 'completed', 'reserve', NULL, NULL, '2026-08-01 11:30:11', '0', NULL, NULL, NULL, NULL, '0', NULL, NULL),
('28', '19', NULL, NULL, '77', 'KidGo', 'Ball Gown (Kids)', '2500.00', '', 'arsenio', 'endaya', 'reserve', 'full', NULL, '0.00', '0.00', '2500.00', '2026-10-16', '2026-10-23', '00:00:00', '00:00:00', '25', '25', '25', '25', '', '', '', '', '', '', '1', 'completed', 'reserve', NULL, NULL, '2026-08-01 11:33:13', '0', NULL, NULL, NULL, NULL, '0', NULL, NULL),
('29', '19', NULL, NULL, '77', 'KidGo', 'Ball Gown (Kids)', '2500.00', '', 'arsenio', 'endaya', 'reserve', 'full', NULL, '0.00', '0.00', '5000.00', '2026-10-22', '2026-10-26', '00:00:00', '00:00:00', '25', '25', '25', '25', '', '', '', '', '', '', '2', 'completed', 'reserve', NULL, NULL, '2026-08-01 13:25:19', '0', NULL, NULL, NULL, NULL, '0', NULL, NULL),
('30', '19', NULL, NULL, '77', 'KidGo', 'Ball Gown (Kids)', '2500.00', '', 'arsenio', 'endaya', 'reserve', 'full', NULL, '0.00', '0.00', '5000.00', '2026-11-10', '2026-11-12', '00:00:00', '00:00:00', '25', '25', '25', '25', '', '', '', '', '', '', '2', 'completed', 'reserve', NULL, NULL, '2026-08-01 13:35:52', '0', NULL, NULL, NULL, NULL, '0', NULL, NULL),
('31', '19', NULL, NULL, '77', 'KidGo', 'Ball Gown (Kids)', '2500.00', '', 'arsenio', 'endaya', 'reserve', 'full', NULL, '0.00', '0.00', '2500.00', '2026-10-16', '2026-10-23', '00:00:00', '00:00:00', '25', '25', '25', '25', '', '', '', '', '', '', '1', 'completed', 'reserve', NULL, NULL, '2026-08-01 13:35:52', '0', NULL, NULL, NULL, NULL, '0', NULL, NULL),
('32', '19', NULL, NULL, '77', 'KidGo', 'Ball Gown (Kids)', '2500.00', '', 'arsenio', 'endaya', 'reserve', 'full', NULL, '0.00', '0.00', '2500.00', '2026-10-15', '2026-10-19', '00:00:00', '00:00:00', '25', '25', '25', '25', '', '', '', '', '', '', '1', 'cancelled', 'reserve', NULL, NULL, '2026-08-01 13:35:52', '0', NULL, NULL, NULL, NULL, '0', NULL, NULL),
('33', '19', NULL, NULL, '77', 'KidGo', 'Ball Gown (Kids)', '2500.00', '', 'arsenio', 'endaya', 'reserve', 'full', NULL, '0.00', '0.00', '5000.00', '2026-09-14', '2026-09-17', '00:00:00', '00:00:00', '25', '25', '25', '25', '', '', '', '', '', '', '2', 'cancelled', 'reserve', NULL, NULL, '2026-08-01 13:40:19', '0', NULL, NULL, NULL, NULL, '0', NULL, NULL),
('34', '19', NULL, NULL, '76', 'Gowniana', 'Filipiniana', '6000.00', '', 'arsenio', 'endaya', 'reserve', 'full', NULL, '0.00', '0.00', '6000.00', '2026-09-02', '2026-09-05', '00:00:00', '00:00:00', '26', '25', '26', '25', '', '', '', '', '', '', '1', 'confirmed', 'reserve', NULL, NULL, '2026-08-01 13:54:12', '0', NULL, NULL, NULL, NULL, '0', NULL, NULL),
('35', '19', NULL, NULL, '76', 'Gowniana', 'Filipiniana', '6000.00', '', 'arsenio', 'endaya', 'reserve', 'full', NULL, '0.00', '0.00', '12000.00', '2026-09-02', '2026-09-07', '00:00:00', '00:00:00', '26', '25', '26', '25', '', '', '', '', '', '', '2', 'cancelled', 'reserve', 'a', NULL, '2026-08-01 13:55:58', '0', NULL, NULL, NULL, NULL, '0', NULL, NULL),
('36', '19', NULL, NULL, '76', 'Gowniana', 'Filipiniana', '6000.00', '', 'arsenio', 'endaya', 'reserve', 'full', NULL, '0.00', '0.00', '6000.00', '2026-09-28', '2026-09-30', '00:00:00', '00:00:00', '26', '25', '26', '25', '', '', '', '', '', '', '1', 'cancelled', 'reserve', 'no payment received', '[\"uploads\\/cancellations\\/cancel_36_1786938632_0.png\"]', '2026-08-01 14:13:16', '0', NULL, NULL, NULL, NULL, '0', NULL, NULL),
('37', '19', NULL, NULL, '76', 'Gowniana', 'Filipiniana', '6000.00', '', 'arsenio', 'endaya', 'reserve', 'full', NULL, '0.00', '0.00', '6000.00', '2026-09-28', '2026-09-30', '00:00:00', '00:00:00', '26', '25', '26', '25', '', '', '', '', '', '', '1', 'confirmed', 'reserve', NULL, NULL, '2026-08-01 14:14:04', '0', NULL, NULL, NULL, NULL, '0', NULL, NULL),
('38', '19', NULL, NULL, '76', 'Gowniana', 'Filipiniana', '6000.00', '', 'arsenio', 'endaya', 'reserve', 'full', NULL, '0.00', '0.00', '12000.00', '2026-09-28', '2026-09-30', '00:00:00', '00:00:00', '26', '25', '26', '25', '', '', '', '', '', '', '2', 'confirmed', 'reserve', NULL, NULL, '2026-08-01 14:14:52', '0', NULL, NULL, NULL, NULL, '0', NULL, NULL),
('39', '19', NULL, NULL, '78', 'TuxKid', 'Tuxedo Suit (Kids)', '1500.00', '', 'arsenio', 'endaya', 'reserve', 'full', NULL, '0.00', '0.00', '1500.00', '2026-12-01', '2026-12-03', '00:00:00', '00:00:00', '26', '25', '25', '25', '26', '25', '25', '', '', '', '1', 'completed', 'reserve', NULL, NULL, '2026-08-01 14:17:46', '0', NULL, NULL, NULL, NULL, '0', NULL, NULL),
('40', '4', NULL, NULL, '75', 'WebMen', 'Wedding Wear for Men', '3000.00', '', 'galred', 'Vergara', 'reserve', 'full', NULL, '0.00', '0.00', '3000.00', '2026-08-26', '2026-08-29', '00:00:00', '00:00:00', '83', '75', '95', '12', '30', '13', '', '', '', '', '1', 'completed', 'reserve', NULL, NULL, '2026-08-14 05:33:29', '0', NULL, NULL, NULL, NULL, '0', NULL, NULL),
('41', '4', NULL, NULL, '73', 'viola', 'Ball Gown', '4500.00', '', 'galred', 'Vergara', 'reserve', 'full', NULL, '0.00', '0.00', '4500.00', '2026-08-20', '2026-08-21', '00:00:00', '00:00:00', '40', '41', '43', '44', '', '', '', '', '', '', '1', 'completed', 'reserve', NULL, NULL, '2026-08-14 05:46:36', '0', NULL, NULL, NULL, NULL, '0', NULL, NULL),
('42', '4', NULL, NULL, '73', 'viola', 'Ball Gown', '8000.00', '', 'galred', 'Vergara', 'reserve', 'full', NULL, '0.00', '0.00', '8000.00', '2026-08-20', '2026-08-21', '00:00:00', '00:00:00', '41', '41', '43', '44', '', '', '', '', '', '', '1', 'completed', 'reserve', NULL, NULL, '2026-08-14 06:06:42', '0', NULL, NULL, NULL, NULL, '0', NULL, NULL),
('43', '19', NULL, NULL, '79', 'Barongnia', 'Barong (Men)', '3000.00', '', 'arsenio', 'endaya', 'reserve', 'full', NULL, '0.00', '0.00', '3000.00', '2026-08-14', '2026-08-15', '00:00:00', '00:00:00', '25', '25', '25', '26', '26', '26', '27', '', '', '', '1', 'completed', 'reserve', NULL, NULL, '2026-08-15 14:39:46', '0', NULL, NULL, NULL, NULL, '0', NULL, NULL),
('44', '19', NULL, NULL, '77', 'KidGo', 'Ball Gown (Kids)', '1280.00', '', 'arsenio', 'endaya', 'reserve', 'full', NULL, '1280.00', '0.00', '1280.00', '2026-08-31', '2026-09-20', '00:00:00', '00:00:00', '39', '40', '40', '35', '', '', '', '', '', '', '1', 'completed', 'reserve', NULL, NULL, '2026-08-19 15:25:59', '0', NULL, NULL, NULL, NULL, '0', NULL, NULL),
('45', '19', NULL, NULL, '78', 'TuxKid', 'Tuxedo Suit (Kids)', '2000.00', '', 'arsenio', 'endaya', 'reserve', 'full', NULL, '4000.00', '0.00', '4000.00', '2026-12-01', '2026-12-03', '00:00:00', '00:00:00', '25', '26', '25', '25', '25', '25', '25', '', '', '', '2', 'cancelled', 'reserve', NULL, NULL, '2026-08-19 15:25:59', '0', NULL, NULL, NULL, NULL, '0', NULL, NULL),
('46', '19', 'RES-20260818174304-fb18', NULL, '77', 'KidGo', 'Ball Gown (Kids)', '1280.00', '', 'arsenio', 'endaya', 'reserve', 'full', NULL, '1280.00', '0.00', '1280.00', '2026-08-31', '2026-09-20', '00:00:00', '00:00:00', '39', '40', '40', '35', '', '', '', '', '', '', '1', 'completed', 'reserve', NULL, NULL, '2026-08-19 15:43:04', '0', NULL, NULL, NULL, NULL, '0', NULL, NULL),
('47', '19', 'RES-20260818174304-fb18', NULL, '79', 'Barongnia', 'Barong (Men)', '1500.00', '', 'arsenio', 'endaya', 'reserve', 'full', NULL, '1500.00', '0.00', '1500.00', '2026-09-11', '2026-09-12', '00:00:00', '00:00:00', '42', '43', '25', '35', '35', '35', '35', '', '', '', '1', 'completed', 'reserve', NULL, NULL, '2026-08-19 15:43:04', '0', NULL, NULL, NULL, NULL, '0', NULL, NULL),
('48', '19', 'RES-20260818175019-6d95', NULL, '76', 'Gowniana', 'Filipiniana', '60.00', '', 'arsenio', 'endaya', 'reserve', 'first_payment', NULL, '60.00', '60.00', '120.00', '2026-09-02', '2026-09-07', '00:00:00', '00:00:00', '26', '25', '26', '25', '', '', '', '', '', '', '2', 'completed', 'reserve', NULL, NULL, '2026-08-19 15:50:19', '0', NULL, NULL, NULL, NULL, '0', NULL, NULL),
('49', '19', 'RES-20260818175019-6d95', NULL, '77', 'KidGo', 'Ball Gown (Kids)', '2000.00', '', 'arsenio', 'endaya', 'reserve', 'first_payment', NULL, '1000.00', '1000.00', '2000.00', '2026-09-14', '2026-09-17', '00:00:00', '00:00:00', '25', '25', '25', '25', '', '', '', '', '', '', '1', 'completed', 'reserve', NULL, NULL, '2026-08-19 15:50:19', '0', NULL, NULL, NULL, NULL, '0', NULL, NULL),
('50', '19', 'RES-20260818184938-b033', NULL, '77', 'KidGo', 'Ball Gown (Kids)', '2000.00', '', 'arsenio', 'endaya', 'reserve', 'first_payment', NULL, '2000.00', '2000.00', '4000.00', '2026-09-14', '2026-09-17', '00:00:00', '00:00:00', '25', '25', '25', '25', '', '', '', '', '', '', '2', 'completed', 'reserve', NULL, NULL, '2026-08-19 16:49:38', '0', NULL, NULL, NULL, NULL, '0', NULL, NULL),
('51', '19', 'RES-20260818184938-b033', NULL, '32', 'zzz', 'Men', '111.00', 'models/1774279776_Copilot3D-24ed6f87-c342-4169-b75b-5f6c9b8cd9f3.glb', 'arsenio', 'endaya', 'reserve', 'first_payment', NULL, '55.50', '55.50', '111.00', '2026-07-31', '2026-08-31', '00:00:00', '00:00:00', '1', '6', '12', '', '', '', '', '', '', '', '1', 'completed', 'buy', NULL, NULL, '2026-08-19 16:49:38', '0', NULL, NULL, NULL, NULL, '0', NULL, NULL),
('52', '19', 'RES-20260819145509-b5f4', NULL, '79', 'Barongnia', 'Barong (Men)', '3000.00', '', 'arsenio', 'endaya', 'reserve', 'first_payment', NULL, '1500.00', '1500.00', '3000.00', '2026-08-25', '2026-08-30', '00:00:00', '00:00:00', '25', '26', '27', '28', '29', '30', '31', '', '', '', '1', 'confirmed', 'buy', NULL, NULL, '2026-08-20 12:55:09', '0', NULL, NULL, NULL, NULL, '0', NULL, NULL),
('53', '19', 'RES-20260819151629-50f7', NULL, '81', 'try', 'Ball Gown', '12.00', '', 'arsenio', 'endaya', 'reserve', 'full', NULL, '12.00', '0.00', '12.00', '2026-10-22', '2026-10-27', '00:00:00', '00:00:00', '2', '25', '25', '25', '', '', '', '', '', '', '1', 'confirmed', 'reserve', NULL, NULL, '2026-08-20 13:16:29', '1', '2026-10-07 19:46:06', '2026-09-16', '2026-09-21', '', '0', NULL, NULL),
('54', '19', 'RES-20260821063158-8c14', 'CG-260821-4E032', '81', 'try', 'Ball Gown', '12.00', '', 'arsenio', 'endaya', 'reserve', 'full', NULL, '12.00', '0.00', '12.00', '2026-09-23', '2026-09-23', '00:00:00', '00:00:00', '2', '25', '25', '25', '', '', '', '', '', '', '1', 'confirmed', 'reserve', NULL, NULL, '2026-08-22 04:31:58', '0', NULL, NULL, NULL, NULL, '0', NULL, NULL),
('56', '19', 'RES-20260822074141-35d8', 'CG-260822-69685', '18', 'Formal ', 'Men', '7099.00', 'models/gowns/1784853291_Copilot3D-8cbc9c06-33cd-4e69-9647-aad448ba00fc.glb', 'arsenio', 'endaya', 'reserve', 'first_payment', NULL, '7099.00', '7099.00', '14198.00', '2026-08-27', '2026-09-01', '00:00:00', '00:00:00', '14', '60', '80', '', '', '', '', '', '', '', '2', 'confirmed', 'reserve', NULL, NULL, '2026-08-23 05:41:41', '0', NULL, NULL, NULL, NULL, '0', NULL, NULL),
('61', '23', 'RES-20260826163610-26d4', 'CGW-260826-A614E', '80', 'Alfred', 'Ball Gown', '700.00', '', 'Alfred', 'Vergara (Walk-In: 09511760135)', 'cash', 'full', NULL, '700.00', '0.00', '700.00', '2026-08-26', '2026-08-31', '10:00 AM', '05:00 PM', '12', '13', '14', '15', '', '', '', '', '', '', '1', 'confirmed', 'reserve', NULL, NULL, '2026-08-27 14:36:10', '0', NULL, NULL, NULL, NULL, '0', NULL, NULL),
('62', '24', 'RES-20260826164009-0159', 'CGW-260826-4418E', '73', 'viola', 'Ball Gown', '4500.00', 'models/gowns/1786937327_Meshy_AI_Lavender_Blossom_Ball_0817032145_texture.glb', 'Maria', 'Santos (Walk-In: 09123456789)', 'cash', 'full', NULL, '4500.00', '0.00', '4500.00', '2026-08-30', '2026-09-09', '10:00 AM', '05:00 PM', '34', '26', '36', '55', '', '', '', '', '', '', '1', 'completed', 'reserve', NULL, NULL, '2026-08-27 14:40:09', '0', NULL, NULL, NULL, NULL, '0', NULL, NULL),
('63', '8', 'RES-20260826175242-7179', 'CGW-260826-75CF1', '80', 'Alfred', 'Ball Gown', '700.00', '', 'Alfred', 'Vergara (Walk-In: 09511760135)', 'cash', 'first_payment', NULL, '350.00', '350.00', '700.00', '2026-08-27', '2026-08-27', '10:00 AM', '05:00 PM', '12', '13', '14', '15', '', '', '', '', '', '', '1', 'confirmed', 'buy', NULL, NULL, '2026-08-27 15:52:42', '0', NULL, NULL, NULL, NULL, '0', NULL, NULL),
('64', '1', 'RES-20260826180032-4406', 'CGW-260826-56A61', '80', 'Alfred', 'Ball Gown', '700.00', '', 'Alfred', 'Vergara (Walk-In: 09511760135)', 'cash', 'full', NULL, '1400.00', '0.00', '1400.00', '2026-12-01', '2026-12-01', '10:00 AM', '05:00 PM', '12', '13', '14', '15', '', '', '', '', '', '', '2', 'completed', 'buy', NULL, NULL, '2026-08-27 16:00:32', '0', NULL, NULL, NULL, NULL, '0', NULL, NULL),
('65', '25', 'RES-20260826182257-aa1d', 'CGW-260826-48F70', '79', 'Barongnia', 'Barong (Men)', '1500.00', '', 'bb', 'bbb (Walk-In: 09511760135)', 'cash', 'first_payment', NULL, '750.00', '750.00', '1500.00', '2026-09-14', '2026-09-14', '10:00 AM', '05:00 PM', '12', '1', '12', '2', '12', '3', '12', '', '', '', '1', 'completed', 'buy', NULL, NULL, '2026-08-27 16:22:57', '0', NULL, NULL, NULL, NULL, '0', NULL, NULL),
('66', '19', 'RES-20260831183315-c362', 'CG-260831-C01F6', '4', 'Ember Elegance', 'Men', '37.50', '', 'arsenio', 'endaya', 'reserve', 'full', 'uploads/payment_proofs/proof_20260831_183312_db831bf9.png', '37.50', '0.00', '37.50', '2026-10-08', '2026-10-08', '00:00:00', '00:00:00', '70', '61', '80', '', '', '', '', '', '', '', '1', 'cancelled', 'reserve', 'Item Damaged or Stained (Requires Repair / Dry Cleaning) — Note: gregr', NULL, '2026-09-01 16:33:15', '0', NULL, NULL, NULL, NULL, '0', NULL, NULL),
('67', '19', 'RES-20260907080734-223b', 'CG-260907-3DBD8', '83', 'Duchess', 'Formal Wear', '1.00', '', 'senio', 'endaya', 'reserve', 'full', 'uploads/payment_proofs/proof_20260907_080732_c1dae4af.png', '1.00', '0.00', '1.00', '2026-09-15', '2026-09-22', '00:00:00', '00:00:00', '25', '26', '27', '28', '', '', '', '', '', '', '1', 'completed', 'reserve', NULL, NULL, '2026-09-08 06:07:34', '0', NULL, NULL, NULL, NULL, '0', NULL, NULL),
('68', '26', 'RES-20260909152631-b647', 'CGW-260909-76D9F', '32', 'zzz', 'Men', '700.00', 'models/1774279776_Copilot3D-24ed6f87-c342-4169-b75b-5f6c9b8cd9f3.glb', 'Alfred', 'Vergara (Walk-In: 09511760135)', 'gcash', 'first_payment', NULL, '350.00', '350.00', '700.00', '2026-09-09', '2026-09-16', '10:00 AM', '05:00 PM', '12', '13', '1', '', '', '', '', '', '', '', '1', 'completed', 'reserve', NULL, NULL, '2026-09-10 13:26:31', '0', NULL, NULL, NULL, NULL, '0', NULL, NULL),
('69', '19', 'RES-20260911045549-129c', 'CG-260911-B706B', '95', 'a', 'Ball Gown (Kids)', '0.01', 'models/gowns/1789054412_Meshy_AI_Tribal_Elegance_0910152717_texture.glb', 'senio', 'endaya', 'reserve', 'first_payment', 'uploads/payment_proofs/proof_20260911_045449_e93bc896.png', '0.01', '0.00', '0.01', '2026-09-21', '2026-09-30', '00:00:00', '00:00:00', '26', '28', '40', '38', '', '', '', '', '', '', '1', 'completed', 'reserve', NULL, NULL, '2026-09-12 02:55:49', '0', NULL, NULL, NULL, NULL, '0', NULL, NULL),
('70', '19', 'RES-20260911045549-129c', 'CG-260911-B706B', '95', 'a', 'Ball Gown (Kids)', '0.01', 'models/gowns/1789054412_Meshy_AI_Tribal_Elegance_0910152717_texture.glb', 'senio', 'endaya', 'reserve', 'first_payment', 'uploads/payment_proofs/proof_20260911_045449_e93bc896.png', '0.01', '0.00', '0.01', '2026-09-21', '2026-09-21', '00:00:00', '00:00:00', '42', '37', '34', '37', '', '', '', '', '', '', '1', 'completed', 'reserve', NULL, NULL, '2026-09-12 02:55:49', '0', NULL, NULL, NULL, NULL, '0', NULL, NULL),
('71', '19', 'RES-20260911045549-129c', 'CG-260911-B706B', '76', 'Gowniana', 'Filipiniana', '6000.00', '', 'senio', 'endaya', 'reserve', 'first_payment', 'uploads/payment_proofs/proof_20260911_045449_e93bc896.png', '6000.00', '6000.00', '12000.00', '2026-09-14', '2026-09-14', '00:00:00', '00:00:00', '38', '35', '36', '', '', '', '', '', '', '', '2', 'completed', 'reserve', NULL, NULL, '2026-09-12 02:55:49', '0', NULL, NULL, NULL, NULL, '0', NULL, NULL),
('72', '19', 'RES-20260911050753-199e', 'CG-260911-0CACD', '83', 'Duchess', 'Formal Wear', '1.00', '', 'senio', 'endaya', 'reserve', 'first_payment', 'uploads/payment_proofs/proof_20260911_050752_b48338f1.png', '0.50', '0.50', '1.00', '2026-10-01', '2026-10-08', '00:00:00', '00:00:00', '39', '39', '36', '35', '', '', '', '', '', '', '1', 'completed', 'reserve', NULL, NULL, '2026-09-12 03:07:53', '0', NULL, NULL, NULL, NULL, '0', NULL, NULL),
('73', '19', 'RES-20260914172435-c4ae', 'CG-260914-6E97A', '100', 'a', 'Barong (Men)', '999.00', '', 'senio', 'endaya', 'QR Ph (RCBC)', 'full', 'uploads/payment_proofs/proof_20260914_172434_8b9568fc.png', '1998.00', '0.00', '1998.00', '2026-09-30', '2026-10-11', '00:00:00', '00:00:00', '45', '46', '46', '46', '45', '', '', '', '', '', '2', 'completed', 'reserve', NULL, NULL, '2026-09-15 15:24:35', '0', NULL, NULL, NULL, NULL, '0', NULL, NULL),
('74', '19', 'RES-20260914182035-3116', 'CG-260914-05DC6', '95', 'a', 'Ball Gown (Kids)', '1000.00', 'models/gowns/1789054412_Meshy_AI_Tribal_Elegance_0910152717_texture.glb', 'senio', 'endaya', 'QR Ph (RCBC)', 'first_payment', 'uploads/payment_proofs/proof_20260914_182034_4607779a.png', '500.00', '500.00', '1000.00', '2026-09-16', '2026-09-14', '00:00:00', '00:00:00', '44', '44', '44', '43', '', '', '', '', '', '', '1', 'cancelled', 'buy', 'no payment', NULL, '2026-09-15 16:20:35', '0', NULL, NULL, NULL, NULL, '0', NULL, NULL),
('76', '9', NULL, 'TEST-SIM-1', '0', 'Sim Dress', '', '0.00', '', 'Sim', 'User', 'reserve', 'full', NULL, '0.00', '0.00', '0.00', '2026-09-15', '2026-09-20', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '1', 'cancelled', 'reserve', 'casdeed', NULL, '2026-09-15 16:54:33', '0', NULL, NULL, NULL, NULL, '0', NULL, NULL),
('78', '19', 'RES-20260916173145-b4a9', 'CG-260916-A319B', '72', 'WedGown', 'Wedding Gowns', '4500.00', '', 'senio', 'endaya', 'QR Ph (RCBC)', 'first_payment', 'uploads/payment_proofs/proof_20260916_173142_e47c6199.png', '2250.00', '2250.00', '4500.00', '2026-09-30', '2026-10-11', '00:00:00', '00:00:00', '41', '40', '39', '38', '', '', '', '', '', '', '1', 'completed', 'reserve', NULL, NULL, '2026-09-17 15:31:45', '0', NULL, NULL, NULL, NULL, '0', NULL, NULL),
('79', '19', 'RES-20260916174122-fd9c', 'CG-260916-A6A4D', '72', 'WedGown', 'Wedding Gowns', '4000.00', '', 'senio', 'endaya', 'QR Ph (RCBC)', 'full', 'uploads/payment_proofs/proof_20260916_174120_df5895d3.png', '4000.00', '0.00', '4000.00', '2026-09-17', '2026-09-27', '00:00:00', '00:00:00', '42', '42', '42', '42', '', '', '', '', '', '', '1', 'cancelled', 'reserve', 'fwfwwef', NULL, '2026-09-17 15:41:22', '0', NULL, NULL, NULL, NULL, '0', NULL, NULL),
('80', '19', 'RES-20260928022259-736f', 'CG-260928-003FF', '97', 'Pearl Elegance Gown', 'Wedding Gowns', '3000.00', 'models/gowns/1789258460_Pearl_Elegance_Gown.glb', 'Alfred', 'endaya', 'QR Ph (RCBC)', 'full', 'uploads/payment_proofs/proof_20260928_022257_07381048.jpg', '3000.00', '0.00', '3000.00', '2026-10-10', '2026-10-20', '00:00:00', '00:00:00', '25', '25', '25', '25', '', '', '', '', '', '', '1', 'completed', 'reserve', NULL, NULL, '2026-09-29 00:22:59', '1', '2026-10-06 22:42:00', '2026-10-14', '2026-10-24', '', '0', NULL, NULL),
('81', '19', 'RES-20261007142013-3b5f', 'CG-261007-F46D2', '97', 'Pearl Elegance Gown', 'Wedding Gowns', '3000.00', 'models/gowns/1789258460_Pearl_Elegance_Gown.glb', 'a', 'a', 'QR Ph (RCBC)', 'full', 'uploads/payment_proofs/proof_20261007_142012_9bd37d68.jpg', '3000.00', '0.00', '3000.00', '2026-12-17', '2026-12-24', '00:00:00', '00:00:00', '44', '43', '43', '43', '', '', '', '', '', '', '1', 'confirmed', 'reserve', NULL, NULL, '2026-10-08 12:20:13', '1', '2026-10-12 21:53:55', '2026-11-03', '2026-11-10', '', '0', NULL, NULL),
('82', '19', 'RES-20261012152903-543f', 'CG-261012-ABE20', '97', 'Pearl Elegance Gown', 'Wedding Gowns', '3000.00', 'models/gowns/1789258460_Pearl_Elegance_Gown.glb', 'a', 'a', 'QR Ph (RCBC)', 'first_payment', 'uploads/payment_proofs/proof_20261012_152902_1f7c5ea1.png', '500.00', '2500.00', '3000.00', '2026-12-07', '2026-12-14', '00:00:00', '00:00:00', '39', '42', '41', '43', '', '', '', '', '', '', '1', 'pending', 'reserve', NULL, NULL, '2026-10-13 13:29:03', '0', NULL, NULL, NULL, NULL, '0', NULL, NULL);

-- ------------------------------------------------------------
-- Table structure for `reserve_products_items`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `reserve_products_items`;
CREATE TABLE `reserve_products_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `reserve_product_id` int(11) NOT NULL COMMENT 'Foreign key to reserve_products table',
  `item_number` int(11) NOT NULL COMMENT 'Item number (2, 3, 4, etc.)',
  `size_1` varchar(50) DEFAULT NULL,
  `size_2` varchar(50) DEFAULT NULL,
  `size_3` varchar(50) DEFAULT NULL,
  `size_4` varchar(50) DEFAULT NULL,
  `size_5` varchar(50) DEFAULT NULL,
  `size_6` varchar(50) DEFAULT NULL,
  `size_7` varchar(50) DEFAULT NULL,
  `size_8` varchar(50) DEFAULT NULL,
  `size_9` varchar(50) DEFAULT NULL,
  `size_10` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `reserve_product_id` (`reserve_product_id`),
  KEY `item_number` (`item_number`),
  CONSTRAINT `fk_reserve_product_id` FOREIGN KEY (`reserve_product_id`) REFERENCES `reserve_products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data for `reserve_products_items`
INSERT INTO `reserve_products_items` (`id`, `reserve_product_id`, `item_number`, `size_1`, `size_2`, `size_3`, `size_4`, `size_5`, `size_6`, `size_7`, `size_8`, `size_9`, `size_10`, `created_at`) VALUES
('1', '71', '2', '38', '35', '36', '', '', '', '', '', '', '', '2026-09-12 02:55:49'),
('2', '73', '2', '48', '26', '26', '26', '26', '', '', '', '', '', '2026-09-15 15:24:35');

-- ------------------------------------------------------------
-- Table structure for `saved_products`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `saved_products`;
CREATE TABLE `saved_products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `model_path` varchar(500) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `product_category` varchar(100) NOT NULL,
  `product_price` decimal(10,2) NOT NULL,
  `purchase_price` decimal(10,2) DEFAULT 0.00,
  `reserve_date` date DEFAULT NULL,
  `return_date` date DEFAULT NULL,
  `size_1` int(11) DEFAULT NULL,
  `size_2` int(11) DEFAULT NULL,
  `size_3` int(11) DEFAULT NULL,
  `size_4` varchar(50) DEFAULT NULL,
  `size_5` varchar(50) DEFAULT NULL,
  `size_6` varchar(50) DEFAULT NULL,
  `size_7` varchar(50) DEFAULT NULL,
  `size_8` varchar(50) DEFAULT NULL,
  `size_9` varchar(50) DEFAULT NULL,
  `size_10` varchar(50) DEFAULT NULL,
  `quantity` int(11) DEFAULT 1,
  `product_status` enum('saved','buy','reserve') DEFAULT 'saved',
  `stock` int(11) DEFAULT 999,
  `saved_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `saved_products_ibfk_1` (`user_id`),
  KEY `saved_products_ibfk_2` (`product_id`),
  CONSTRAINT `saved_products_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `system_users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `saved_products_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `gowns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=218 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data for `saved_products`
INSERT INTO `saved_products` (`id`, `user_id`, `product_id`, `model_path`, `product_name`, `product_category`, `product_price`, `purchase_price`, `reserve_date`, `return_date`, `size_1`, `size_2`, `size_3`, `size_4`, `size_5`, `size_6`, `size_7`, `size_8`, `size_9`, `size_10`, `quantity`, `product_status`, `stock`, `saved_at`) VALUES
('155', '16', '72', '', 'may bitaw', 'Weeding Gown', '4000.00', '10000.00', '2026-07-30', '2026-07-31', '25', NULL, '25', NULL, '25', NULL, NULL, '25', NULL, NULL, '1', 'saved', '1', '2026-07-30 15:10:27'),
('195', '26', '79', '', 'Barongnia', 'Barong (Men)', '3000.00', '3000.00', '0000-00-00', '2026-09-16', '26', '26', '25', '25', '25', '25', '25', NULL, NULL, NULL, '1', 'buy', '999', '2026-09-04 16:09:06'),
('207', '19', '76', 'models/gowns/1789253392_Meshy_AI_Golden_Blossom_Gown_0912224032_texture.glb', 'Gowniana', 'Filipiniana', '3000.00', '3000.00', '2026-09-23', NULL, '43', '42', '42', '42', NULL, NULL, NULL, NULL, NULL, NULL, '1', 'buy', '1', '2026-09-13 22:53:04'),
('209', '19', '78', '', 'TuxKid', 'Tuxedo Suit (Kids)', '1500.00', '2000.00', '2026-09-16', '2026-09-23', '42', '45', '42', '37', '44', '33', '39', NULL, NULL, NULL, '1', 'saved', '2', '2026-09-14 17:00:07'),
('216', '19', '73', 'models/gowns/1789258615_Lavender_Elegance.glb', 'Lavender Dream Gown', 'Ball Gown', '4500.00', '0.00', '2026-10-13', '2026-10-20', '41', '41', '41', '43', NULL, NULL, NULL, NULL, NULL, NULL, '1', 'saved', '4', '2026-10-13 13:27:18');

-- ------------------------------------------------------------
-- Table structure for `saved_products_items`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `saved_products_items`;
CREATE TABLE `saved_products_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `saved_product_id` int(11) NOT NULL COMMENT 'Foreign key to saved_products table',
  `item_number` int(11) NOT NULL COMMENT 'Item number (2, 3, 4, etc.)',
  `size_1` varchar(50) DEFAULT NULL,
  `size_2` varchar(50) DEFAULT NULL,
  `size_3` varchar(50) DEFAULT NULL,
  `size_4` varchar(50) DEFAULT NULL,
  `size_5` varchar(50) DEFAULT NULL,
  `size_6` varchar(50) DEFAULT NULL,
  `size_7` varchar(50) DEFAULT NULL,
  `size_8` varchar(50) DEFAULT NULL,
  `size_9` varchar(50) DEFAULT NULL,
  `size_10` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `saved_product_id` (`saved_product_id`),
  KEY `item_number` (`item_number`),
  CONSTRAINT `fk_saved_product_id` FOREIGN KEY (`saved_product_id`) REFERENCES `saved_products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=62 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------
-- Table structure for `system_settings`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `system_settings`;
CREATE TABLE `system_settings` (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data for `system_settings`
INSERT INTO `system_settings` (`setting_key`, `setting_value`, `updated_at`) VALUES
('extension_price_per_day', '50', '2026-10-06 23:40:14'),
('max_extension_days', '60', '2026-09-12 16:44:15'),
('overdue_penalty_percent', '5', '2026-10-06 23:40:14'),
('rental_duration_days', '7', '2026-10-08 11:49:32'),
('rental_overdue_fee_percent', '10', '2026-08-25 08:51:13');

-- ------------------------------------------------------------
-- Table structure for `system_staffs`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `system_staffs`;
CREATE TABLE `system_staffs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `verification_token` varchar(255) DEFAULT NULL,
  `token_expiry` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `firstname` varchar(50) DEFAULT NULL,
  `lastname` varchar(50) DEFAULT NULL,
  `suffix` varchar(20) DEFAULT NULL,
  `middlename` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=124272 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data for `system_staffs`
INSERT INTO `system_staffs` (`id`, `name`, `email`, `password`, `is_active`, `verification_token`, `token_expiry`, `created_at`, `firstname`, `lastname`, `suffix`, `middlename`) VALUES
('1', 'Alf Staff', 'alfcode4@gmail.com', '$2y$10$94DKQiP0fMEhBO0NjSuKY.fcb0RjtQEcKq1ShTeYw7eg65v5hZZku', '1', NULL, NULL, '2026-08-22 14:10:43', NULL, NULL, NULL, NULL);

-- ------------------------------------------------------------
-- Table structure for `system_users`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `system_users`;
CREATE TABLE `system_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `province` varchar(100) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `barangay` varchar(100) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `suffix` varchar(50) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `id_verified` tinyint(1) DEFAULT 0,
  `id_type` varchar(150) DEFAULT NULL,
  `id_front_image` varchar(500) DEFAULT NULL,
  `id_back_image` varchar(500) DEFAULT NULL,
  `id_verified_at` datetime DEFAULT NULL,
  `id_extracted_data` text DEFAULT NULL,
  `reset_otp` varchar(10) DEFAULT NULL,
  `reset_expiry` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data for `system_users`
INSERT INTO `system_users` (`id`, `email`, `province`, `city`, `barangay`, `password`, `profile_image`, `first_name`, `middle_name`, `last_name`, `suffix`, `phone`, `created_at`, `id_verified`, `id_type`, `id_front_image`, `id_back_image`, `id_verified_at`, `id_extracted_data`, `reset_otp`, `reset_expiry`) VALUES
('1', 'vergara@gmail.com', NULL, NULL, NULL, '$2y$10$yT26NDNjl2WSMcL6/oHaCee57hU1HcZmtgaX6BvhlxWIKJ3cbBFg6', 'uploads/profile_images/profile_699819ed325f99.97672112_1771575789.jpg', 'Alfredo', 'Carolino', 'Vergara', '', NULL, '2026-02-21 07:36:34', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
('4', 'vergara143@gmail.com', 'Pangasinan', 'Anda', 'Poblacion', '$2y$10$27yZpu6EimhevBDsbpRJ2OKyBADfCKh/dNQpyxaTTZeQOqsvGOawW', 'uploads/profile_images/profile_6a4d8855af2704.56263435_1783466069.png', 'galred', 'a', 'Vergara', '', '', '2026-02-23 08:06:21', '0', 'Philippine Identification (PhilSys / National ID / ePhilID)', 'uploads/valid_ids/id_user_6ac6580d32d41_1791383565.png', 'uploads/valid_ids/id_user_6ac6580d32d41_1791383565.png', NULL, NULL, NULL, NULL),
('5', 'abc@gmail.com', NULL, NULL, NULL, '$2y$10$ADsIyvRb6lpvTt4Lhhl2UO0SepLSQF7PaStZVLH2h6qBkIhzvJv3S', 'uploads/profile_images/profile_699af6e32786e2.29084619_1771763427.png', NULL, NULL, NULL, NULL, NULL, '2026-02-23 12:29:54', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
('7', 'pogi@gmail.com', NULL, NULL, NULL, '$2y$10$EN6mJ1j6ey9IfhjVvPZicOgJ99t0q35NNXs1TCs/FooHxRzTCEazC', NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-24 15:04:17', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
('8', 'ver@gmail.com', '', '', '', '$2y$10$O8SRFDsM717JWboK8pNIjewH7QtABKd4BWssnIHfcqtlRN4ngwasu', NULL, 'a', 'b', 'cmum', '', '', '2026-02-25 05:34:16', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
('9', 'a@gmail.com', NULL, NULL, NULL, '$2y$10$FyqM2eMTp7pBh0y9NEtdAuyjZHBSD0Ozdr4mWat4H5wLp3fZd7dR.', NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-25 10:30:38', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
('11', 'mob421109@gmail.com', NULL, NULL, NULL, '$2y$10$ZBcESAlbkwLXJjkns2W9JuzDL3kWbh9LD/9.hU6TCk1F7iKMvtYSu', NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-25 23:38:21', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
('12', 'discoveranda@gmail.com', 'Pangasinan', 'Anda', 'Tondol', '$2y$10$tYa1MJvmlFX3l0EiZavHee8pucXJeRImjbu9Wa.i8IcK4zf2sYI1m', NULL, 'a', 'aa', 'aaa', '', '', '2026-02-26 00:12:37', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
('16', 'annje2727@gmail.com', 'Pangasinan', 'City of Alaminos', 'Poblacion', '$2y$10$JKx0px0aU3VvS7Kl41RsiutHkQdl/2RH./xF4NWQqQMrEHF1pCm4q', NULL, 'a', 's', 'd', '', '', '2026-03-25 06:21:15', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
('17', 'clos3tgo@gmail.com', 'Pangasinan', 'Anda', '', '$2y$10$lh58SKjm/0YmX6KEBFLaLu4fck0HbZMwZwgoU6Z7eR.NlgVkNOS.e', 'uploads/profile_images/profile_6a4d8c3c97fc80.44786749_1783467068.jpg', 'A Zz', 'Carolino', 'a', 'II', '', '2026-03-31 08:37:57', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
('19', 'endayajrarsenio@gmail.com', 'Pangasinan', 'Burgos', 'Poblacion', '$2y$10$cBvJdapwOnCCCVTrVZNXGux/yTCe0y0h9Hfgg8.U/ljrDUD97Jsuu', 'uploads/profile_images/profile_6ab910e5d4a9e2.64345534_1790513381.png', 'a', 'a', 'a', 'a', '09111111111', '2026-07-21 04:46:21', '0', 'Philippine Identification (PhilSys / National ID / ePhilID)', 'uploads/valid_ids/id_front_6a9afed012dcc_1788542672.png', 'uploads/valid_ids/id_back_6a9afed013160_1788542672.png', NULL, NULL, NULL, NULL),
('22', 'ddesa7223@gmail.com', NULL, NULL, NULL, '$2y$10$9hmrPCpNzHqEbC5LTnQZXuTYEMHbQLEy3mfYXkUjWNfZFZihVcIdC', NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-23 04:14:50', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
('23', 'vergaraalfred1431@gmail.com', NULL, NULL, NULL, '$2y$10$.lVBtjetSJrfZQjKtCBGoeX7fEYWmxeEda0I5sHaAS075FYG9mcry', NULL, 'Alfred', NULL, 'Vergara', NULL, '09511760135', '2026-08-27 14:36:10', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
('24', 'maria@test.com', NULL, NULL, NULL, '$2y$10$eL5v6a2d1TishCp46ct/S.eGB8v4G6a/B.QL3sGC29tbPl6zn5h9K', NULL, 'Maria', NULL, 'Santos', NULL, '09123456789', '2026-08-27 14:39:23', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
('25', 'b@gmail.com', NULL, NULL, NULL, '$2y$10$WX47VTP74e8iNT6rajE/oukbsGvS4jDNA6GSHVwaB6GD3RQJK/mdW', NULL, 'bb', NULL, 'bbb', NULL, '09511760135', '2026-08-27 16:22:57', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
('26', 'vergaraalfred143@gmail.com', NULL, NULL, NULL, '$2y$10$j40helwwAV44q2/ipXXvSu5q825RP93mvxfd7Bzxxn2l3Y/tQcDtG', NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-28 04:04:01', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
('27', 'apogi5003@gmail.com', NULL, NULL, NULL, '$2y$10$/A0p0slHd1hvVuU4sourLuo3Pykp60M6pnQPG/GNB3MIHHIZ4sGda', NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-02 04:49:23', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
('28', 'gmbclosetgo@gmail.com', 'Pangasinan', 'Anda', '', '$2y$10$N76IhP/TzVF6yLpf5o.Y1.t2mwkPFXElQaiRevqIHYaRJfdmz9mD6', 'uploads/profile_images/profile_6a9a70a8daa9c0.70892048_1788506280.png', 'Alfred', 'Carolino', 'Ie Vergara Oy', '', '', '2026-09-05 05:37:26', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
('29', 'dadyko284@gmail.com', 'Ilocos Norte', 'City of Batac', '', '$2y$10$Jw3V4NBc7nwF4LP8HuAjDelvVotcdTFJfV/Bp4ImE2sPrY9.ExU2S', NULL, '', '', '', '', '', '2026-09-10 16:12:29', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
('32', 'alfcode4@gmail.com', NULL, NULL, NULL, '$2y$10$Bi2FjcpTPZDVl7LeRO6ceenvBjNIjTh7tM5pKOsta7IP8sCm5bKhe', NULL, NULL, NULL, NULL, NULL, NULL, '2026-10-12 12:37:07', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL);

-- ------------------------------------------------------------
-- Table structure for `users_no`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `users_no`;
CREATE TABLE `users_no` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `phone_number` varchar(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `phone_number` (`phone_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

SET FOREIGN_KEY_CHECKS = 1;
-- ------------------------------------------------------------
-- Backup successfully generated at 2026-10-18 14:38:30
-- ------------------------------------------------------------
