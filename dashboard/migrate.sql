-- migrate.sql
-- Select the database created by your hosting control panel before importing
-- this file. Shared hosting usually does not allow CREATE DATABASE or USE.

-- users table
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(191) NOT NULL,
  `email` VARCHAR(191) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('admin','user') NOT NULL DEFAULT 'user',
  `created_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- workers table
CREATE TABLE IF NOT EXISTS `workers` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(191) NOT NULL,
  `days` INT NOT NULL DEFAULT 0,
  `hours` INT NOT NULL DEFAULT 0,
  `performance` INT NOT NULL DEFAULT 0,
  `status` VARCHAR(64) DEFAULT NULL,
  `created_at` DATETIME NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed example users with password_hash-compatible bcrypt values.
INSERT INTO `users` (`name`,`email`,`password`,`role`,`created_at`) VALUES
('Admin','admin@example.com','$2y$10$fR.lKtjqcV6f/tOD5kfENOKXr2PHr0BDVoHKmseVbMQfGEn5ZW1.u','admin',NOW()),
('John Doe','john@example.com','$2y$10$BUAlClddbFPlRdlpThtea.R7NqHPzULgDtTdFyhvvgbW8CsXbD89W','user',NOW())
ON DUPLICATE KEY UPDATE `email` = VALUES(`email`);

-- Seed example workers
INSERT INTO `workers` (`name`,`days`,`hours`,`performance`,`status`,`created_at`)
SELECT * FROM (
  SELECT 'Amin Rahman',20,160,88,'Excellent',NOW()
  UNION ALL SELECT 'Sadia Karim',18,144,76,'Good',NOW()
  UNION ALL SELECT 'Rafi Hossain',22,176,92,'Excellent',NOW()
  UNION ALL SELECT 'Nila Akter',16,128,70,'Focus',NOW()
) AS seed
WHERE NOT EXISTS (SELECT 1 FROM `workers` LIMIT 1);

CREATE TABLE IF NOT EXISTS `portfolio_content` (
  `id` TINYINT UNSIGNED NOT NULL,
  `content_json` LONGTEXT NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Portfolio content managed by api_portfolio.php
CREATE TABLE IF NOT EXISTS `portfolio_site_images` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slot` VARCHAR(64) NOT NULL,
  `image_url` VARCHAR(512) NOT NULL,
  `alt_text` VARCHAR(255) NOT NULL DEFAULT '',
  `crop_x` INT UNSIGNED NOT NULL DEFAULT 0,
  `crop_y` INT UNSIGNED NOT NULL DEFAULT 0,
  `crop_width` INT UNSIGNED NOT NULL DEFAULT 0,
  `crop_height` INT UNSIGNED NOT NULL DEFAULT 0,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `portfolio_site_images_slot_unique` (`slot`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `portfolio_skills` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category` VARCHAR(64) NOT NULL,
  `name` VARCHAR(191) NOT NULL,
  `icon` VARCHAR(255) NOT NULL DEFAULT '',
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `portfolio_projects` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(191) NOT NULL,
  `slug` VARCHAR(191) NOT NULL,
  `description` TEXT NOT NULL,
  `url` VARCHAR(512) NOT NULL DEFAULT '',
  `repository_url` VARCHAR(512) NOT NULL DEFAULT '',
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `portfolio_projects_slug_unique` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `portfolio_project_screenshots` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `project_id` INT UNSIGNED NOT NULL,
  `image_url` VARCHAR(512) NOT NULL,
  `alt_text` VARCHAR(255) NOT NULL DEFAULT '',
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `portfolio_project_screenshots_project_idx` (`project_id`),
  CONSTRAINT `portfolio_project_screenshots_project_fk`
    FOREIGN KEY (`project_id`) REFERENCES `portfolio_projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `portfolio_timeline_items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `period` VARCHAR(128) NOT NULL,
  `title` VARCHAR(191) NOT NULL,
  `description` TEXT NOT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
