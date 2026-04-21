-- CIAO SPRITZ - Kompletní databázová struktura FINAL
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name_cs` varchar(255) NOT NULL,
  `name_en` varchar(255) DEFAULT NULL,
  `description_cs` text,
  `description_en` text,
  `price` decimal(10,2) NOT NULL,
  `category` enum('napoje','sety','merch') NOT NULL DEFAULT 'napoje',
  `stock` int(11) NOT NULL DEFAULT 0,
  `image` varchar(255) DEFAULT NULL,
  `featured` tinyint(1) DEFAULT 0,
  `active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `products` (`name_cs`,`name_en`,`description_cs`,`description_en`,`price`,`category`,`stock`,`featured`,`active`) VALUES
('Ciao Spritz 0,75 l','Ciao Spritz 0.75 l','Ciao Spritz evergreenem mezi italskými alkoholickými aperitivy, dokonale kombinuje vonné tóny bílého a exotického ovoce.','Ciao Spritz, an evergreen among Italian alcoholic aperitifs, perfectly combines aromatic notes of white and exotic fruit.',159.00,'napoje',100,1,1),
('Ciao Spritz - set 3 láhve','Ciao Spritz - set 3 bottles','Výhodný set 3 lahví Ciao Spritz za skvělou cenu.','Great value set of 3 Ciao Spritz bottles.',399.00,'sety',50,1,1),
('Ciao Spritz - Set 6 lahví','Ciao Spritz - Set 6 bottles','Nejlepší cena za láhev! Set 6 lahví Ciao Spritz.','Best price per bottle! Set of 6 Ciao Spritz bottles.',799.00,'sety',30,1,1),
('Glera – sud 20 litrů','Glera – 20 litre keg','Pro větší akci nebo k doplnění vašeho nápojového sortimentu.','For larger events or to complement your beverage range.',2850.00,'napoje',10,0,1),
('Klobouk – Ciao Spritz','Hat – Ciao Spritz','Barevný módní polyesterový klobouk, vhodný na festivaly i volný čas.','Colorful fashion polyester hat, suitable for festivals and leisure.',149.00,'merch',50,0,1),
('Brýle – Ciao Spritz – Oranžové','Glasses – Ciao Spritz – Orange','Reklamní brýle Ciao Spritz v oranžové barvě.','Ciao Spritz promotional glasses in orange.',99.00,'merch',80,0,1),
('Vratné kelímky - Ciao Spritz','Reusable cups - Ciao Spritz','Ekologické vratné kelímky, odolnější než jednorázové.','Eco-friendly reusable cups, more durable than disposables.',149.00,'merch',60,0,1),
('Sklenička z tritanu','Tritan glass','Špičková sklenička z tritanu, moderního a ekologického materiálu.','Premium tritan glass, made from modern and eco-friendly material.',299.00,'merch',40,0,1);

CREATE TABLE IF NOT EXISTS `orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_number` varchar(20) NOT NULL,
  `customer_name` varchar(255) NOT NULL,
  `customer_email` varchar(255) NOT NULL,
  `customer_phone` varchar(20) DEFAULT NULL,
  `address` text NOT NULL,
  `city` varchar(100) NOT NULL,
  `zip` varchar(10) NOT NULL,
  `total` decimal(10,2) NOT NULL,
  `shipping_method` enum('kuryrem','osobni') NOT NULL,
  `shipping_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `payment_method` enum('karta','prevod','dobirika','osobni') NOT NULL,
  `status` enum('nova','zpracovava','odeslana','dorucena','zrusena') NOT NULL DEFAULT 'nova',
  `note` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_number` (`order_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `order_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `quantity` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `articles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title_cs` varchar(255) NOT NULL,
  `title_en` varchar(255) DEFAULT NULL,
  `perex_cs` text,
  `perex_en` text,
  `content_cs` longtext,
  `content_en` longtext,
  `image` varchar(255) DEFAULT NULL,
  `slug` varchar(255) NOT NULL,
  `published` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `articles` (`title_cs`,`title_en`,`perex_cs`,`perex_en`,`content_cs`,`content_en`,`slug`,`published`) VALUES
('Značka, která přináší letní atmosféru','A brand that brings summer vibes','Ciao Spritz je více než jen nápoj — je to životní styl plný slunce, přátelství a italské dolce vita.','Ciao Spritz is more than just a drink — it is a lifestyle full of sunshine, friendship and Italian dolce vita.','Ciao Spritz je více než jen nápoj — je to životní styl plný slunce, přátelství a italské dolce vita. Každá lahev v sobě nese kousek italského léta.','Ciao Spritz is more than just a drink — it is a lifestyle full of sunshine, friendship and Italian dolce vita. Every bottle carries a piece of Italian summer.','znacka-ktera-prinasi-letni-atmosferu',1),
('Ciao Spritz, nový osvěžující drink','Ciao Spritz, a new refreshing drink','Představujeme vám Ciao Spritz — aperitiv, který si zamilujete na první doušek.','Introducing Ciao Spritz — an aperitif you will fall in love with at first sip.','Představujeme vám Ciao Spritz — aperitiv, který si zamilujete na první doušek. Jedinečná kombinace chutí, která osvěží každou příležitost.','Introducing Ciao Spritz — an aperitif you will fall in love with at first sip. A unique combination of flavors that refreshes every occasion.','ciao-spritz-novy-osvezujici-drink',1),
('Už jste ochutnali Ciao Spritz?','Have you tried Ciao Spritz yet?','Tisíce spokojených zákazníků po celé České republice si již oblíbily chuť pravého italského aperitivu.','Thousands of satisfied customers across the Czech Republic have already fallen in love with the taste of authentic Italian aperitif.','Tisíce spokojených zákazníků po celé České republice si již oblíbily chuť pravého italského aperitivu. Přidejte se k nim!','Thousands of satisfied customers across the Czech Republic have already fallen in love with the taste of authentic Italian aperitif. Join them!','uz-jste-ochutnali-ciao-spritz',1);

CREATE TABLE IF NOT EXISTS `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `key_name` varchar(100) NOT NULL,
  `value` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_name` (`key_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `settings` (`key_name`,`value`) VALUES
('free_shipping_from','2000'),('shipping_price','120'),('shop_name','Ciao Spritz'),
('shop_email','rcaffe@email.cz'),('shop_phone','602 556 323'),
('facebook_url','https://www.facebook.com/ciaospritz'),
('instagram_url','https://www.instagram.com/ciao_spritz/');

CREATE TABLE IF NOT EXISTS `admins` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Heslo: admin123 — ZMĚŇ IHNED!
INSERT INTO `admins` (`username`,`password`,`email`) VALUES
('admin','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','rcaffe@email.cz');

CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `zip` varchar(10) DEFAULT NULL,
  `active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `gallery_albums` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name_cs` varchar(255) NOT NULL,
  `name_en` varchar(255) DEFAULT NULL,
  `description_cs` text,
  `description_en` text,
  `cover_image` varchar(255) DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `gallery_albums` (`name_cs`,`name_en`,`description_cs`,`description_en`,`sort_order`,`active`) VALUES
('Akce 2024','Events 2024','Fotky z různých akcí roku 2024','Photos from various events in 2024',1,1),
('Stánek','Our stand','Náš mobilní stánek Ciao Spritz','Our Ciao Spritz mobile stand',2,1),
('Produkty','Products','Produktové fotografie','Product photography',3,1);

CREATE TABLE IF NOT EXISTS `gallery_photos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `album_id` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `caption` varchar(500) DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `album_id` (`album_id`),
  CONSTRAINT `gallery_photos_ibfk_1` FOREIGN KEY (`album_id`) REFERENCES `gallery_albums` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `reservations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `date_from` date NOT NULL,
  `date_to` date NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `event_type` varchar(100) DEFAULT NULL,
  `message` text,
  `status` enum('ceka','schvaleno','zamitnuto') NOT NULL DEFAULT 'ceka',
  `admin_note` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==================== VĚRNOSTNÍ BODY ====================
CREATE TABLE IF NOT EXISTS `loyalty_points` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `points` int(11) NOT NULL,
  `type` enum('earned','spent','expired','bonus') NOT NULL DEFAULT 'earned',
  `description` varchar(255) DEFAULT NULL,
  `expires_at` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==================== ZPRÁVY K OBJEDNÁVKÁM ====================
CREATE TABLE IF NOT EXISTS `order_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `sender` enum('admin','customer') NOT NULL DEFAULT 'admin',
  `message` text NOT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==================== KUPÓNY / BENEFITY ====================
CREATE TABLE IF NOT EXISTS `coupons` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `type` enum('percent','fixed','free_shipping') NOT NULL DEFAULT 'fixed',
  `value` decimal(10,2) NOT NULL,
  `min_order` decimal(10,2) DEFAULT 0,
  `max_uses` int(11) DEFAULT NULL,
  `used_count` int(11) DEFAULT 0,
  `user_id` int(11) DEFAULT NULL,
  `expires_at` date DEFAULT NULL,
  `active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Nastavení věrnostního programu
INSERT IGNORE INTO `settings` (`key_name`,`value`) VALUES
('loyalty_points_per_100czk','1'),
('loyalty_points_value','0.5'),
('loyalty_points_min_redeem','50'),
('loyalty_points_expiry_days','365');

-- ==================== ZAMĚSTNANCI / ROLE ====================
CREATE TABLE IF NOT EXISTS `staff` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','manager','prodavac') NOT NULL DEFAULT 'prodavac',
  `phone` varchar(20) DEFAULT NULL,
  `note` text,
  `active` tinyint(1) DEFAULT 1,
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Hlavní admin účet (heslo: admin123)
INSERT IGNORE INTO `staff` (`name`,`email`,`password`,`role`) VALUES
('Hlavní Admin','rcaffe@email.cz','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','admin');

-- ==================== PŘÍSTUPOVÝ LOG ====================
CREATE TABLE IF NOT EXISTS `staff_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `staff_id` int(11) NOT NULL,
  `action` varchar(255) NOT NULL,
  `detail` text,
  `ip` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `staff_id` (`staff_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==================== ROZŠÍŘENÍ PRODUKTŮ ====================
-- Přidej tyto sloupce do existující tabulky products
ALTER TABLE `products`
  ADD COLUMN IF NOT EXISTS `name_cs` varchar(255) NOT NULL DEFAULT '' AFTER `id`,
  ADD COLUMN IF NOT EXISTS `slug` varchar(255) DEFAULT NULL AFTER `name_en`,
  ADD COLUMN IF NOT EXISTS `short_desc_cs` text AFTER `description_en`,
  ADD COLUMN IF NOT EXISTS `short_desc_en` text AFTER `short_desc_cs`,
  ADD COLUMN IF NOT EXISTS `price_sale` decimal(10,2) DEFAULT NULL AFTER `price`,
  ADD COLUMN IF NOT EXISTS `price_sale_from` date DEFAULT NULL AFTER `price_sale`,
  ADD COLUMN IF NOT EXISTS `price_sale_to` date DEFAULT NULL AFTER `price_sale_from`,
  ADD COLUMN IF NOT EXISTS `badge` varchar(50) DEFAULT NULL AFTER `featured`,
  ADD COLUMN IF NOT EXISTS `meta_title_cs` varchar(255) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `meta_desc_cs` text,
  ADD COLUMN IF NOT EXISTS `meta_title_en` varchar(255) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `meta_desc_en` text,
  ADD COLUMN IF NOT EXISTS `stock_min` int(11) DEFAULT 5,
  ADD COLUMN IF NOT EXISTS `weight` decimal(8,3) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `sort_order` int(11) DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- ==================== FOTKY PRODUKTŮ ====================
CREATE TABLE IF NOT EXISTS `product_images` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `alt_cs` varchar(255) DEFAULT NULL,
  `alt_en` varchar(255) DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `is_main` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `product_images_fk` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==================== ŠTÍTKY ====================
CREATE TABLE IF NOT EXISTS `tags` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name_cs` varchar(100) NOT NULL,
  `name_en` varchar(100) DEFAULT NULL,
  `slug` varchar(100) NOT NULL,
  `color` varchar(20) DEFAULT '#E8631A',
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `tags` (`name_cs`,`name_en`,`slug`,`color`) VALUES
('Novinka','New','novinka','#2D7A3A'),
('Akce','Sale','akce','#dc3545'),
('Doprodej','Clearance','doprodej','#E8631A'),
('Bestseller','Bestseller','bestseller','#6f42c1'),
('Doporučujeme','Recommended','doporucujeme','#007bff'),
('Limitovaná edice','Limited edition','limitovana-edice','#fd7e14'),
('Bez alkoholu','Non-alcoholic','bez-alkoholu','#20c997'),
('Výhodné balení','Value pack','vyhodne-baleni','#17a2b8');

-- ==================== PRODUKTY - ŠTÍTKY ====================
CREATE TABLE IF NOT EXISTS `product_tags` (
  `product_id` int(11) NOT NULL,
  `tag_id` int(11) NOT NULL,
  PRIMARY KEY (`product_id`,`tag_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==================== VARIACE PRODUKTŮ ====================
CREATE TABLE IF NOT EXISTS `product_variants` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `name_cs` varchar(255) NOT NULL,
  `name_en` varchar(255) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `price_sale` decimal(10,2) DEFAULT NULL,
  `stock` int(11) DEFAULT 0,
  `sku` varchar(100) DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==================== KŘÍŽOVÝ PRODEJ ====================
CREATE TABLE IF NOT EXISTS `product_related` (
  `product_id` int(11) NOT NULL,
  `related_id` int(11) NOT NULL,
  PRIMARY KEY (`product_id`,`related_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
