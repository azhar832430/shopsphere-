-- ============================================================================
-- SHOPSPHERE - DATABASE MIGRATION: ADD SLUG TO CATEGORIES TABLE
-- Target DBMS: MySQL 8.0+ / MariaDB 10.4+ (XAMPP / phpMyAdmin)
-- ============================================================================

USE `shopsphere_db`;

-- Add slug column and unique index
ALTER TABLE `categories`
  ADD COLUMN `slug` VARCHAR(100) NULL AFTER `name`,
  ADD UNIQUE KEY `uq_categories_slug` (`slug`);

-- Seed URL slugs for default sample categories
UPDATE `categories` SET `slug` = 'smartphones-tablets' WHERE `id` = 1 AND `slug` IS NULL;
UPDATE `categories` SET `slug` = 'laptops-computers' WHERE `id` = 2 AND `slug` IS NULL;
UPDATE `categories` SET `slug` = 'audio-headphones' WHERE `id` = 3 AND `slug` IS NULL;
UPDATE `categories` SET `slug` = 'wearables-smartwatches' WHERE `id` = 4 AND `slug` IS NULL;
UPDATE `categories` SET `slug` = 'smart-home-iot' WHERE `id` = 5 AND `slug` IS NULL;
UPDATE `categories` SET `slug` = 'gaming-vr' WHERE `id` = 6 AND `slug` IS NULL;
UPDATE `categories` SET `slug` = 'accessories-cables' WHERE `id` = 7 AND `slug` IS NULL;

