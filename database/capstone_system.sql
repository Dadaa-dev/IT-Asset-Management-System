-- ============================================================
--  AssetVault SPIST — Full Database Schema v2
--  Run in phpMyAdmin: Import this file
-- ============================================================

CREATE DATABASE IF NOT EXISTS `capstone_system`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `capstone_system`;

-- USERS
CREATE TABLE IF NOT EXISTS `users` (
  `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `gmail`         VARCHAR(180) NOT NULL UNIQUE,
  `password`      VARCHAR(255) NULL,
  `full_name`     VARCHAR(120) NULL,
  `google_uid`    VARCHAR(128) NULL UNIQUE,
  `google_avatar` VARCHAR(512) NULL,
  `auth_provider` ENUM('local','google') NOT NULL DEFAULT 'local',
  `role`          ENUM('admin','staff') NOT NULL DEFAULT 'staff',
  `is_active`     TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_login`    TIMESTAMP NULL,
  INDEX `idx_gmail`      (`gmail`),
  INDEX `idx_google_uid` (`google_uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- BUILDINGS
CREATE TABLE IF NOT EXISTS `buildings` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`       VARCHAR(150) NOT NULL,
  `address`    VARCHAR(300) NULL,
  `photo`      VARCHAR(260) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- FLOORS
CREATE TABLE IF NOT EXISTS `floors` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `building_id`  INT UNSIGNED NOT NULL,
  `floor_number` SMALLINT NOT NULL,
  `label`        VARCHAR(100) NULL,
  `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_floor` (`building_id`,`floor_number`),
  CONSTRAINT `fk_floors_building` FOREIGN KEY (`building_id`) REFERENCES `buildings`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ROOMS
CREATE TABLE IF NOT EXISTS `rooms` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `floor_id`    INT UNSIGNED NOT NULL,
  `room_number` VARCHAR(20) NOT NULL,
  `label`       VARCHAR(150) NULL,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_room` (`floor_id`,`room_number`),
  CONSTRAINT `fk_rooms_floor` FOREIGN KEY (`floor_id`) REFERENCES `floors`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- CATEGORIES
CREATE TABLE IF NOT EXISTS `categories` (
  `id`   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ITEMS (Assets)
CREATE TABLE IF NOT EXISTS `items` (
  `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `room_id`       INT UNSIGNED NOT NULL,
  `category_id`   INT UNSIGNED NULL,
  `name`          VARCHAR(150) NOT NULL,
  `description`   TEXT NULL,
  `serial_no`     VARCHAR(100) NULL,
  `property_no`   VARCHAR(100) NULL,
  `asset_code`    VARCHAR(20) NULL,
  `quantity`      SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  `unit_cost`     DECIMAL(10,2) NULL DEFAULT 0.00,
  `condition`     ENUM('good','fair','poor') NOT NULL DEFAULT 'good',
  `status`        ENUM('active','under_repair','disposed','reserved') NOT NULL DEFAULT 'active',
  `acquired_date` DATE NULL,
  `photo`         VARCHAR(260) NULL,
  `added_by`      INT UNSIGNED NULL,
  `updated_by`    INT UNSIGNED NULL,
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_items_room`     (`room_id`),
  INDEX `idx_items_status`   (`status`),
  INDEX `idx_items_category` (`category_id`),
  UNIQUE KEY `uq_items_asset_code` (`asset_code`),
  CONSTRAINT `fk_items_room`     FOREIGN KEY (`room_id`)     REFERENCES `rooms`(`id`)       ON DELETE CASCADE,
  CONSTRAINT `fk_items_category` FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`)  ON DELETE SET NULL,
  CONSTRAINT `fk_items_user`     FOREIGN KEY (`added_by`)    REFERENCES `users`(`id`)        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ITEM AUDIT LOG
CREATE TABLE IF NOT EXISTS `item_logs` (
  `id`        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `item_id`   INT UNSIGNED NULL,
  `user_id`   INT UNSIGNED NULL,
  `action`    ENUM('add','edit','delete','status_change') NOT NULL,
  `note`      TEXT NULL,
  `logged_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_logs_item` (`item_id`),
  INDEX `idx_logs_user` (`user_id`),
  CONSTRAINT `fk_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- CONCERNS (Student reports)
CREATE TABLE IF NOT EXISTS `concerns` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `item_id`     INT UNSIGNED NULL,
  `room_id`     INT UNSIGNED NULL,
  `user_id`     INT UNSIGNED NOT NULL,
  `title`       VARCHAR(200) NOT NULL,
  `description` TEXT NOT NULL,
  `priority`    ENUM('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
  `status`      ENUM('open','in_progress','resolved','closed') NOT NULL DEFAULT 'open',
  `admin_note`  TEXT NULL,
  `resolved_by` INT UNSIGNED NULL,
  `resolved_at` TIMESTAMP NULL,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_concerns_user`   (`user_id`),
  INDEX `idx_concerns_status` (`status`),
  CONSTRAINT `fk_concerns_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_concerns_item` FOREIGN KEY (`item_id`) REFERENCES `items`(`id`)  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- MAINTENANCE SCHEDULES
CREATE TABLE IF NOT EXISTS `maintenance` (
  `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `item_id`       INT UNSIGNED NOT NULL,
  `scheduled_date` DATE NOT NULL,
  `performed_date` DATE NULL,
  `type`          ENUM('preventive','corrective','inspection') NOT NULL DEFAULT 'preventive',
  `description`   TEXT NULL,
  `performed_by`  INT UNSIGNED NULL,
  `cost`          DECIMAL(10,2) NULL DEFAULT 0.00,
  `status`        ENUM('scheduled','done','cancelled') NOT NULL DEFAULT 'scheduled',
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_maint_item` FOREIGN KEY (`item_id`) REFERENCES `items`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── SEED DATA ──────────────────────────────────────────────────
INSERT IGNORE INTO `buildings` (`id`,`name`,`address`) VALUES
  (1,'Main Bldg','SPIST Campus - Main'),
  (2,'Melloville Bldg','SPIST Campus - East'),
  (3,'Carbag Building','SPIST Campus - West');

UPDATE `buildings` SET `name` = 'Main Bldg' WHERE `name` = 'Main Building';
UPDATE `buildings` SET `name` = 'Melloville Bldg' WHERE `name` = 'Melloville Building';

INSERT IGNORE INTO `floors` (`building_id`,`floor_number`) VALUES
  (1,1),(1,2),(1,3),
  (2,1),(2,2),(2,3),(2,4),
  (3,1),(3,2),(3,3);

INSERT IGNORE INTO `rooms` (`floor_id`,`room_number`,`label`)
SELECT f.`id`, room_seed.`room_number`, room_seed.`label`
FROM (
  SELECT 1 AS `seed_order`, 'Main Bldg' AS `building_name`, 1 AS `floor_number`, '101' AS `room_number`, 'Computer Lab 1' AS `label`
  UNION ALL SELECT 2, 'Main Bldg', 1, '102', 'Computer Lab 2'
  UNION ALL SELECT 3, 'Main Bldg', 1, '103', 'Faculty Room'
  UNION ALL SELECT 4, 'Main Bldg', 1, '104', 'Library'
  UNION ALL SELECT 5, 'Main Bldg', 2, '201', 'Room 201'
  UNION ALL SELECT 6, 'Main Bldg', 2, '202', 'Room 202'
  UNION ALL SELECT 7, 'Main Bldg', 2, '203', 'AVR'
  UNION ALL SELECT 8, 'Main Bldg', 3, '301', 'Room 301'
  UNION ALL SELECT 9, 'Main Bldg', 3, '302', 'Room 302'
  UNION ALL SELECT 10, 'Melloville Bldg', 1, '201', 'Room 201'
  UNION ALL SELECT 11, 'Melloville Bldg', 1, '202', 'Room 202'
  UNION ALL SELECT 12, 'Melloville Bldg', 2, '301', 'Room 301'
  UNION ALL SELECT 13, 'Melloville Bldg', 2, '302', 'Room 302'
  UNION ALL SELECT 14, 'Melloville Bldg', 3, '401', 'Room 401'
  UNION ALL SELECT 15, 'Melloville Bldg', 3, '402', 'Room 402'
  UNION ALL SELECT 16, 'Melloville Bldg', 4, '501', 'Room 501'
  UNION ALL SELECT 17, 'Carbag Building', 1, '101', 'Room 101'
  UNION ALL SELECT 18, 'Carbag Building', 1, '102', 'Room 102'
  UNION ALL SELECT 19, 'Carbag Building', 2, '201', 'Room 201'
  UNION ALL SELECT 20, 'Carbag Building', 3, '301', 'Room 301'
) AS room_seed
JOIN `buildings` b ON b.`name` = room_seed.`building_name`
JOIN `floors` f ON f.`building_id` = b.`id` AND f.`floor_number` = room_seed.`floor_number`
ORDER BY room_seed.`seed_order`;

INSERT INTO `rooms` (`floor_id`,`room_number`,`label`)
SELECT f.`id`, labs.`room_number`, labs.`label`
FROM `buildings` b
JOIN `floors` f ON f.`building_id` = b.`id` AND f.`floor_number` = 3
CROSS JOIN (
  SELECT 'CLAB' AS `room_number`, 'Computer Laboratory' AS `label`
  UNION ALL SELECT 'ELAB', 'Electronics Laboratory'
) labs
WHERE b.`name` IN ('Main Bldg','Melloville Bldg')
ON DUPLICATE KEY UPDATE `label` = VALUES(`label`);

INSERT IGNORE INTO `categories` (`name`) VALUES
  ('Computer / Laptop'),('Keyboard'),('Mouse'),('Monitor'),('Printer'),
  ('Projector'),('Furniture'),('Networking Equipment'),('UPS / Power Supply'),
  ('Air Conditioner'),('CCTV / Security'),('Others');

-- Sample keyboard asset in room 1 (Computer Lab 1)
INSERT IGNORE INTO `items`
  (`id`,`room_id`,`category_id`,`name`,`description`,`serial_no`,`property_no`,`quantity`,`unit_cost`,`condition`,`status`,`acquired_date`)
VALUES
  (1, 1, 2, 'Mechanical Keyboard', 'Black mechanical keyboard for Lab 1 PC-01', 'KB-2024-001', 'PROP-001', 1, 850.00, 'good', 'active', '2024-01-15'),
  (2, 1, 2, 'Membrane Keyboard', 'Standard membrane keyboard for Lab 1 PC-02', 'KB-2024-002', 'PROP-002', 1, 350.00, 'good', 'active', '2024-01-15'),
  (3, 1, 1, 'Desktop Computer', 'Intel Core i5 Desktop PC', 'PC-2024-001', 'PROP-003', 1, 25000.00, 'good', 'active', '2024-01-10'),
  (4, 1, 4, 'LED Monitor 24"', '24 inch Full HD LED Monitor', 'MON-2024-001', 'PROP-004', 1, 8500.00, 'fair', 'active', '2024-01-10'),
  (5, 1, 3, 'Optical Mouse', 'USB Optical Mouse', 'MS-2024-001', 'PROP-005', 1, 250.00, 'poor', 'under_repair', '2024-01-15');

UPDATE `items`
SET `asset_code` = CONCAT('AV', LPAD(`id`, 8, '0'))
WHERE `asset_code` IS NULL OR `asset_code` = '';

ALTER TABLE `items`
  MODIFY COLUMN `asset_code` VARCHAR(20) NOT NULL;
