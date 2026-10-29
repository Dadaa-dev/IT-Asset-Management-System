-- Run once against an existing capstone_system database in phpMyAdmin.
ALTER TABLE `items`
  ADD COLUMN `asset_code` VARCHAR(20) NULL AFTER `property_no`;

UPDATE `items`
SET `asset_code` = CONCAT('AV', LPAD(`id`, 8, '0'))
WHERE `asset_code` IS NULL OR `asset_code` = '';

ALTER TABLE `items`
  MODIFY COLUMN `asset_code` VARCHAR(20) NOT NULL,
  ADD UNIQUE KEY `uq_items_asset_code` (`asset_code`);
