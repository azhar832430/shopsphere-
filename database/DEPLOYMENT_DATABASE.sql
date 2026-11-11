-- ============================================================================
-- SHOPSPHERE - E-COMMERCE MANAGEMENT SYSTEM
-- PHASE 8: PRODUCTION DEPLOYMENT DATABASE SCHEMA & CANONICAL SEED DATA
-- ============================================================================
-- Target DBMS: MySQL 8.0+ / MariaDB 10.4+ (XAMPP / phpMyAdmin / Cloud LAMP)
-- Character Set: utf8mb4 | Collation: utf8mb4_unicode_ci | Engine: InnoDB
-- Generated for Final University Web Engineering PBL Submission
-- ============================================================================

-- IMPORTANT SAFETY NOTICE:
-- This script creates the database `shopsphere_db` and all required tables.
-- If existing tables are present, DROP TABLE IF EXISTS will replace them.
-- To preserve existing transactions, perform a backup via phpMyAdmin Export before executing.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

-- ----------------------------------------------------------------------------
-- 1. DATABASE CREATION
-- ----------------------------------------------------------------------------
CREATE DATABASE IF NOT EXISTS `shopsphere_db` 
  CHARACTER SET utf8mb4 
  COLLATE utf8mb4_unicode_ci;

USE `shopsphere_db`;

-- ----------------------------------------------------------------------------
-- 2. TABLE DROP STATEMENTS (Reverse Dependency Order for Idempotency)
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `order_items`;
DROP TABLE IF EXISTS `orders`;
DROP TABLE IF EXISTS `products`;
DROP TABLE IF EXISTS `categories`;
DROP TABLE IF EXISTS `customers`;
DROP TABLE IF EXISTS `users`;

-- ----------------------------------------------------------------------------
-- 3. USERS TABLE (Administrator Accounts & Role-Based Access Control)
-- ----------------------------------------------------------------------------
CREATE TABLE `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL,
  `email` VARCHAR(100) NOT NULL,
  `password` VARCHAR(255) NOT NULL COMMENT 'Bcrypt / Argon2 password hash. Plaintext strictly prohibited.',
  `full_name` VARCHAR(100) NOT NULL,
  `role` VARCHAR(30) NOT NULL DEFAULT 'administrator',
  `status` VARCHAR(20) NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_username` (`username`),
  UNIQUE KEY `uq_users_email` (`email`),
  KEY `idx_users_status` (`status`),
  KEY `idx_users_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 4. CATEGORIES TABLE (Taxonomy & Department Hierarchy with URL Slugs)
-- ----------------------------------------------------------------------------
CREATE TABLE `categories` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `slug` VARCHAR(100) NULL,
  `description` TEXT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_categories_name` (`name`),
  UNIQUE KEY `uq_categories_slug` (`slug`),
  KEY `idx_categories_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 5. PRODUCTS TABLE (Catalog Inventory, SKUs & Relational Pricing)
-- ----------------------------------------------------------------------------
CREATE TABLE `products` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_id` INT UNSIGNED NULL,
  `name` VARCHAR(150) NOT NULL,
  `sku` VARCHAR(50) NOT NULL,
  `description` TEXT NULL,
  `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `stock` INT NOT NULL DEFAULT 0,
  `image` VARCHAR(255) NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_products_sku` (`sku`),
  KEY `idx_products_category_id` (`category_id`),
  KEY `idx_products_status` (`status`),
  KEY `idx_products_price` (`price`),
  CONSTRAINT `chk_products_price` CHECK (`price` >= 0.00),
  CONSTRAINT `chk_products_stock` CHECK (`stock` >= 0),
  CONSTRAINT `fk_products_category` 
    FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) 
    ON DELETE SET NULL 
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 6. CUSTOMERS TABLE (Consumer Accounts & CRM Profiles)
-- ----------------------------------------------------------------------------
CREATE TABLE `customers` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `full_name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL,
  `phone` VARCHAR(30) NULL,
  `address` TEXT NULL,
  `city` VARCHAR(50) NULL,
  `postal_code` VARCHAR(20) NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_customers_email` (`email`),
  KEY `idx_customers_status` (`status`),
  KEY `idx_customers_city` (`city`),
  KEY `idx_customers_phone` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 7. ORDERS TABLE (Sales Transactions & Order Fulfillment Headers)
-- ----------------------------------------------------------------------------
CREATE TABLE `orders` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_number` VARCHAR(30) NOT NULL,
  `customer_id` INT UNSIGNED NOT NULL,
  `order_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `subtotal` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `total_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `payment_method` VARCHAR(50) NOT NULL DEFAULT 'Credit Card',
  `payment_status` VARCHAR(20) NOT NULL DEFAULT 'pending',
  `order_status` VARCHAR(20) NOT NULL DEFAULT 'pending',
  `notes` TEXT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_orders_number` (`order_number`),
  KEY `idx_orders_customer_id` (`customer_id`),
  KEY `idx_orders_order_date` (`order_date`),
  KEY `idx_orders_order_status` (`order_status`),
  KEY `idx_orders_payment_status` (`payment_status`),
  CONSTRAINT `chk_orders_subtotal` CHECK (`subtotal` >= 0.00),
  CONSTRAINT `chk_orders_total` CHECK (`total_amount` >= 0.00),
  CONSTRAINT `fk_orders_customer` 
    FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) 
    ON DELETE RESTRICT 
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 8. ORDER_ITEMS TABLE (Itemized Product Lines & Historical Price Snapshots)
-- ----------------------------------------------------------------------------
CREATE TABLE `order_items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` INT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED NOT NULL,
  `quantity` INT NOT NULL DEFAULT 1,
  `unit_price` DECIMAL(10,2) NOT NULL,
  `subtotal` DECIMAL(10,2) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_order_items_order_id` (`order_id`),
  KEY `idx_order_items_product_id` (`product_id`),
  CONSTRAINT `chk_order_items_qty` CHECK (`quantity` > 0),
  CONSTRAINT `chk_order_items_unit_price` CHECK (`unit_price` >= 0.00),
  CONSTRAINT `chk_order_items_subtotal` CHECK (`subtotal` >= 0.00),
  CONSTRAINT `fk_order_items_order` 
    FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) 
    ON DELETE CASCADE 
    ON UPDATE CASCADE,
  CONSTRAINT `fk_order_items_product` 
    FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) 
    ON DELETE RESTRICT 
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Re-enable Foreign Key constraints
SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
-- 9. PRODUCTION CANONICAL SEED DATA
-- ============================================================================

-- ----------------------------------------------------------------------------
-- A. USERS SEED DATA (Pre-Seeded Administrator Credentials)
-- Default testing login:
--   Email:    admin@shopsphere.io (or Username: admin)
--   Password: Admin@123
-- ----------------------------------------------------------------------------
INSERT INTO `users` (`id`, `username`, `email`, `password`, `full_name`, `role`, `status`, `created_at`) VALUES
(1, 'admin', 'admin@shopsphere.io', '$2y$10$Fav8jb7jkvI4QV/dVJWcNO3B6mmgMizBKyhsyI1Ir2VsvdAUwIjHK', 'Alexander Wright', 'administrator', 'active', '2026-01-01 08:00:00'),
(2, 'jmanager', 'sarah.jenkins@shopsphere.io', '$2y$10$Fav8jb7jkvI4QV/dVJWcNO3B6mmgMizBKyhsyI1Ir2VsvdAUwIjHK', 'Sarah Jenkins', 'store_manager', 'active', '2026-01-15 09:30:00');

-- ----------------------------------------------------------------------------
-- B. CATEGORIES SEED DATA (7 Departments with Normalized Slugs)
-- ----------------------------------------------------------------------------
INSERT INTO `categories` (`id`, `name`, `slug`, `description`, `status`, `created_at`) VALUES
(1, 'Smartphones & Tablets', 'smartphones-tablets', 'Next-generation flagship phones and pro tablets with AMOLED displays', 'active', '2026-01-15 10:00:00'),
(2, 'Laptops & Computers', 'laptops-computers', 'Ultra-thin notebooks, workstation towers, and color-accurate displays', 'active', '2026-01-18 11:15:00'),
(3, 'Audio & Headphones', 'audio-headphones', 'Studio monitors, ANC wireless headphones, and audiophile earbuds', 'active', '2026-01-20 14:00:00'),
(4, 'Wearables & Smartwatches', 'wearables-smartwatches', 'Health monitoring smartwatches, biometric trackers, and sports bands', 'active', '2026-02-01 09:00:00'),
(5, 'Smart Home & IoT', 'smart-home-iot', 'Intelligent 4K security domes, ambient circadian lighting, and hubs', 'active', '2026-02-10 16:20:00'),
(6, 'Gaming & VR', 'gaming-vr', 'Mechanical custom keyboards, high-DPI optical mice, and headsets', 'active', '2026-02-15 13:45:00'),
(7, 'Accessories & Cables', 'accessories-cables', 'GaN high-wattage fast chargers, Thunderbolt hubs, and tech sleeves', 'active', '2026-02-22 15:30:00');

-- ----------------------------------------------------------------------------
-- C. PRODUCTS SEED DATA (16 Commercial Items)
-- ----------------------------------------------------------------------------
INSERT INTO `products` (`id`, `category_id`, `name`, `sku`, `description`, `price`, `stock`, `image`, `status`, `created_at`) VALUES
(1, 1, 'SpherePhone 15 Pro Max', 'SP-15PM-256', 'Titanium aerospace enclosure with 6.7-inch Super Retina OLED, 48MP triple telephoto optics, and all-day battery life.', 1199.00, 24, 'https://images.unsplash.com/photo-1592750475338-74b7b21085ab?w=400&auto=format&fit=crop&q=80', 'active', '2026-02-01 10:00:00'),
(2, 2, 'AeroBook Ultra M3 16"', 'AB-M3-16-1TB', '16-core workstation notebook with Liquid Retina XDR screen, 36GB unified memory, and 22-hour battery.', 2499.00, 12, 'https://images.unsplash.com/photo-1517336714731-489689fd1ca8?w=400&auto=format&fit=crop&q=80', 'active', '2026-02-05 11:30:00'),
(3, 3, 'SonicPulse ANC Studio Headphones', 'SP-ANC-900', 'Hybrid dual-chamber active noise cancellation with 40mm beryllium drivers and lossless spatial audio.', 349.00, 45, 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=400&auto=format&fit=crop&q=80', 'active', '2026-02-10 12:00:00'),
(4, 4, 'OmniWatch Horizon 2', 'OW-HZ-46', 'Sapphire crystal smartwatch with ECG sensor, blood oxygen tracking, offline maps, and dual-frequency GPS.', 399.00, 18, 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=400&auto=format&fit=crop&q=80', 'active', '2026-02-12 14:15:00'),
(5, 5, 'Nexus 4K Smart Camera Hub', 'NX-CAM-4K', 'Dual-lens 4K night-vision security dome with AI edge recognition and built-in Zigbee home gateway.', 179.00, 8, 'https://images.unsplash.com/photo-1557324232-b8917d3c3dcb?w=400&auto=format&fit=crop&q=80', 'active', '2026-02-15 15:45:00'),
(6, 6, 'Vortex Mechanical Pro Keyboard', 'VT-KB-PRO87', 'Hot-swappable custom linear switches, CNC machined brass weight plate, gasket mount, and per-key RGB.', 189.50, 32, 'https://images.unsplash.com/photo-1587829741301-dc798b83add3?w=400&auto=format&fit=crop&q=80', 'active', '2026-02-18 16:20:00'),
(7, 7, 'HyperDrive 140W GaN Charger', 'HD-GAN-140', 'Quad-port high-efficiency GaN charger supporting USB Power Delivery 3.1 with intelligent thermal throttle.', 79.99, 60, 'https://images.unsplash.com/photo-1583863788434-e58a36330cf0?w=400&auto=format&fit=crop&q=80', 'active', '2026-02-20 09:30:00'),
(8, 1, 'SpherePad Pro 12.9" M2', 'SP-PAD-129', 'Mini-LED display tablet with stylus hover responsiveness, dual thunderbolt ports, and HDR editing suite.', 1099.00, 5, 'https://images.unsplash.com/photo-1544244015-0df4b3ffc6b0?w=400&auto=format&fit=crop&q=80', 'active', '2026-02-25 10:45:00'),
(9, 3, 'TrueAir Wireless Earbuds V3', 'TA-EAR-V3', 'Ergonomic IPX7 water-resistant earbuds with wireless charging case and low latency gaming audio profile.', 149.00, 0, 'https://images.unsplash.com/photo-1590658268037-6bf12165a8df?w=400&auto=format&fit=crop&q=80', 'inactive', '2026-03-01 11:00:00'),
(10, 6, 'Quantum Precision Wireless Mouse', 'QM-MSE-8K', 'Ultralight 49-gram magnesium alloy mouse featuring 32,000 DPI optical sensor and 8000Hz polling rate.', 129.99, 4, 'https://images.unsplash.com/photo-1615663245857-ac93bb7c39e7?w=400&auto=format&fit=crop&q=80', 'active', '2026-03-03 13:10:00'),
(11, 2, 'Clarity 27" 4K Creator Monitor', 'CL-MON-274K', 'Color-accurate IPS Black panel with 99% DCI-P3 gamut, built-in 96W KVM dock and zero-bezel chassis.', 649.00, 14, 'https://images.unsplash.com/photo-1527443224154-c4a3942d3acf?w=400&auto=format&fit=crop&q=80', 'active', '2026-03-05 14:00:00'),
(12, 5, 'Aura Smart Mood Lamp', 'AU-SMP-LMP', 'Synchronized ambient lighting supporting Matter, HomeKit, and circadian rhythm auto-dimming.', 89.00, 28, 'https://images.unsplash.com/photo-1507473885765-e6ed057f782c?w=400&auto=format&fit=crop&q=80', 'active', '2026-03-08 15:20:00'),
(13, 2, 'ProStation Mini G4 Desktop', 'PS-MINI-G4', 'Compact 1.2-liter aluminum modular workstation PC with Ryzen 9, 64GB DDR5, and dual 2.5G Ethernet.', 1399.00, 9, 'https://images.unsplash.com/photo-1587202372775-e229f172b9d7?w=400&auto=format&fit=crop&q=80', 'active', '2026-03-09 10:00:00'),
(14, 7, 'Titanium Braided USB-C Cable (2m)', 'AC-TC2M-100', '100W PD certified braided silicone-coated silicone cable with 40Gbps Thunderbolt data transmission.', 29.50, 110, 'https://images.unsplash.com/photo-1544716278-ca5e3f4abd8c?w=400&auto=format&fit=crop&q=80', 'active', '2026-03-09 11:30:00'),
(15, 6, 'VisionPulse VR Headset Pro', 'VR-PLS-5K', '5K micro-OLED VR headset featuring pancake optics, eye-tracking foveated rendering, and wireless PC link.', 899.00, 7, 'https://images.unsplash.com/photo-1622979135225-d2ba269bc1df?w=400&auto=format&fit=crop&q=80', 'active', '2026-03-10 09:15:00'),
(16, 4, 'AeroBand Fitness Tracker', 'AB-FIT-HR', 'Slim silicone fitness band with continuous heart rate, sleep quality scoring, and 14-day battery reserve.', 69.00, 42, 'https://images.unsplash.com/photo-1575311373937-040b8e1fd5b6?w=400&auto=format&fit=crop&q=80', 'active', '2026-03-10 11:45:00');

-- ----------------------------------------------------------------------------
-- D. CUSTOMERS SEED DATA (12 Consumer Accounts)
-- ----------------------------------------------------------------------------
INSERT INTO `customers` (`id`, `full_name`, `email`, `phone`, `address`, `city`, `postal_code`, `status`, `created_at`) VALUES
(1, 'Marcus Vance', 'marcus.vance@techcorp.io', '+1 (415) 882-9014', '742 Montgomery St, Suite 400', 'San Francisco', '94111', 'active', '2026-01-10 09:00:00'),
(2, 'Elena Rostova', 'elena.rostova@designlab.co', '+1 (212) 493-2190', '185 Broadway Ave, Fl 9', 'New York', '10007', 'active', '2026-01-14 11:30:00'),
(3, 'David Chen', 'david.chen@ventures.com', '+1 (206) 774-8831', '1201 3rd Avenue, Penthouse', 'Seattle', '98101', 'active', '2026-01-22 14:15:00'),
(4, 'Sophia Williams', 'sophia.williams@gmail.com', '+1 (512) 349-1102', '304 Congress Ave', 'Austin', '78701', 'active', '2026-02-03 10:20:00'),
(5, 'Liam O''Connor', 'liam.oconnor@dublintech.eu', '+1 (312) 654-8900', '222 W Merchandise Mart Plaza', 'Chicago', '60654', 'active', '2026-02-11 16:45:00'),
(6, 'Amara Kanu', 'amara.kanu@creativehouse.org', '+1 (404) 923-4411', '1075 Peachtree St NE', 'Atlanta', '30309', 'active', '2026-02-18 13:10:00'),
(7, 'Julian Moreau', 'julian.moreau@polytechnic.fr', '+1 (617) 555-0199', '77 Massachusetts Ave', 'Cambridge', '02139', 'blocked', '2026-02-24 15:00:00'),
(8, 'Rachel Sterling', 'rachel.s@sterlingmedia.com', '+1 (303) 499-2810', '1600 California St', 'Denver', '80202', 'active', '2026-02-26 09:40:00'),
(9, 'Kenji Takahashi', 'kenji.takahashi@tokyostudio.jp', '+1 (408) 332-9901', '350 Santana Row, Apt 412', 'San Jose', '95128', 'active', '2026-03-01 12:15:00'),
(10, 'Fatima Al-Mansoor', 'fatima.almansoor@gulfinno.ae', '+1 (713) 890-4422', '1200 Post Oak Blvd', 'Houston', '77056', 'active', '2026-03-03 14:30:00'),
(11, 'Oliver Bennett', 'oliver.bennett@oxfordalumni.org', '+1 (617) 899-7104', '48 Mount Auburn St', 'Cambridge', '02138', 'active', '2026-03-05 10:50:00'),
(12, 'Maya Lin', 'maya.lin@urbandesign.net', '+1 (503) 620-3349', '1120 NW Couch St', 'Portland', '97209', 'active', '2026-03-07 16:00:00');

-- ----------------------------------------------------------------------------
-- E. ORDERS SEED DATA (12 Historical & Active Orders)
-- ----------------------------------------------------------------------------
INSERT INTO `orders` (`id`, `order_number`, `customer_id`, `order_date`, `subtotal`, `total_amount`, `payment_method`, `payment_status`, `order_status`, `notes`, `created_at`) VALUES
(1, 'ORD-7081', 1, '2026-03-01 14:22:00', 1278.99, 1337.70, 'Credit Card', 'paid', 'delivered', 'Front desk concierge signed for delivery.', '2026-03-01 14:22:00'),
(2, 'ORD-7082', 2, '2026-03-02 09:45:00', 3148.00, 3440.58, 'PayPal', 'paid', 'delivered', 'High value commercial shipment.', '2026-03-02 09:45:00'),
(3, 'ORD-7083', 3, '2026-03-03 11:15:00', 748.00, 801.58, 'Credit Card', 'paid', 'shipped', 'Priority FedEx tracking active.', '2026-03-03 11:15:00'),
(4, 'ORD-7084', 4, '2026-03-04 10:30:00', 319.49, 361.65, 'Credit Card', 'paid', 'shipped', 'Customer requested gift packaging.', '2026-03-04 10:30:00'),
(5, 'ORD-7085', 5, '2026-03-05 13:05:00', 536.00, 561.56, 'Bank Transfer', 'pending', 'processing', 'Awaiting domestic ACH wire receipt confirmation.', '2026-03-05 13:05:00'),
(6, 'ORD-7086', 6, '2026-03-06 15:40:00', 1099.00, 1192.42, 'Credit Card', 'paid', 'processing', 'Express domestic handling.', '2026-03-06 15:40:00'),
(7, 'ORD-7087', 8, '2026-03-07 10:15:00', 1428.50, 1550.00, 'Credit Card', 'paid', 'processing', 'Combined packaging requested.', '2026-03-07 10:15:00'),
(8, 'ORD-7088', 9, '2026-03-08 14:50:00', 1399.00, 1517.92, 'Credit Card', 'paid', 'pending', 'Order queued for warehouse pick and pack.', '2026-03-08 14:50:00'),
(9, 'ORD-7089', 10, '2026-03-09 11:00:00', 928.50, 1007.42, 'PayPal', 'paid', 'pending', 'Verified via PayPal Seller Protection.', '2026-03-09 11:00:00'),
(10, 'ORD-7090', 11, '2026-03-09 16:25:00', 649.00, 704.17, 'Credit Card', 'paid', 'pending', 'Direct carrier signature required.', '2026-03-09 16:25:00'),
(11, 'ORD-7091', 12, '2026-03-10 09:30:00', 488.00, 529.48, 'Cash on Delivery', 'pending', 'pending', 'Cash on delivery scheduled with local courier.', '2026-03-10 09:30:00'),
(12, 'ORD-7092', 7, '2026-03-10 13:40:00', 1199.00, 1300.92, 'Credit Card', 'failed', 'cancelled', 'Card authorization failed during gateway handshake.', '2026-03-10 13:40:00');

-- ----------------------------------------------------------------------------
-- F. ORDER_ITEMS SEED DATA (20 Detailed Line Items)
-- ----------------------------------------------------------------------------
INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `quantity`, `unit_price`, `subtotal`, `created_at`) VALUES
-- Items for Order #ORD-7081 ($1,278.99)
(1, 1, 1, 1, 1199.00, 1199.00, '2026-03-01 14:22:00'),
(2, 1, 7, 1, 79.99, 79.99, '2026-03-01 14:22:00'),

-- Items for Order #ORD-7082 ($3,148.00)
(3, 2, 2, 1, 2499.00, 2499.00, '2026-03-02 09:45:00'),
(4, 2, 11, 1, 649.00, 649.00, '2026-03-02 09:45:00'),

-- Items for Order #ORD-7083 ($748.00)
(5, 3, 4, 1, 399.00, 399.00, '2026-03-03 11:15:00'),
(6, 3, 3, 1, 349.00, 349.00, '2026-03-03 11:15:00'),

-- Items for Order #ORD-7084 ($319.49)
(7, 4, 6, 1, 189.50, 189.50, '2026-03-04 10:30:00'),
(8, 4, 10, 1, 129.99, 129.99, '2026-03-04 10:30:00'),

-- Items for Order #ORD-7085 ($536.00)
(9, 5, 5, 2, 179.00, 358.00, '2026-03-05 13:05:00'),
(10, 5, 12, 2, 89.00, 178.00, '2026-03-05 13:05:00'),

-- Items for Order #ORD-7086 ($1,099.00)
(11, 6, 8, 1, 1099.00, 1099.00, '2026-03-06 15:40:00'),

-- Items for Order #ORD-7087 ($1,428.50)
(12, 7, 13, 1, 1399.00, 1399.00, '2026-03-07 10:15:00'),
(13, 7, 14, 1, 29.50, 29.50, '2026-03-07 10:15:00'),

-- Items for Order #ORD-7088 ($1,399.00)
(14, 8, 13, 1, 1399.00, 1399.00, '2026-03-08 14:50:00'),

-- Items for Order #ORD-7089 ($928.50)
(15, 9, 15, 1, 899.00, 899.00, '2026-03-09 11:00:00'),
(16, 9, 14, 1, 29.50, 29.50, '2026-03-09 11:00:00'),

-- Items for Order #ORD-7090 ($649.00)
(17, 10, 11, 1, 649.00, 649.00, '2026-03-09 16:25:00'),

-- Items for Order #ORD-7091 ($488.00)
(18, 11, 4, 1, 399.00, 399.00, '2026-03-10 09:30:00'),
(19, 11, 12, 1, 89.00, 89.00, '2026-03-10 09:30:00'),

-- Items for Order #ORD-7092 ($1,199.00)
(20, 12, 1, 1, 1199.00, 1199.00, '2026-03-10 13:40:00');

-- ============================================================================
-- END OF DEPLOYMENT_DATABASE.SQL
-- ============================================================================

