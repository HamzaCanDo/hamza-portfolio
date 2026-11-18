CREATE TABLE IF NOT EXISTS `portfolio_site_images` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slot` VARCHAR(64) NOT NULL,
  `image_url` VARCHAR(512) NOT NULL DEFAULT '',
  `alt_text` VARCHAR(255) NOT NULL DEFAULT '',
  `crop_x` INT UNSIGNED NOT NULL DEFAULT 0,
  `crop_y` INT UNSIGNED NOT NULL DEFAULT 0,
  `crop_width` INT UNSIGNED NOT NULL DEFAULT 0,
  `crop_height` INT UNSIGNED NOT NULL DEFAULT 0,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `portfolio_site_images_slot` (`slot`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `portfolio_skills` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category` VARCHAR(64) NOT NULL DEFAULT '',
  `name` VARCHAR(191) NOT NULL,
  `icon` VARCHAR(512) NOT NULL DEFAULT '',
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
  UNIQUE KEY `portfolio_projects_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `portfolio_project_screenshots` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `project_id` INT UNSIGNED NOT NULL,
  `image_url` VARCHAR(512) NOT NULL DEFAULT '',
  `alt_text` VARCHAR(255) NOT NULL DEFAULT '',
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `portfolio_screenshots_project` (`project_id`),
  CONSTRAINT `portfolio_screenshots_project_fk` FOREIGN KEY (`project_id`) REFERENCES `portfolio_projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `portfolio_timeline_items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `period` VARCHAR(191) NOT NULL DEFAULT '',
  `title` VARCHAR(191) NOT NULL,
  `description` TEXT NOT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
