-- Migration: Add CAM column to furnished / unfurnished / managed office tables
-- CAM is alphanumeric text (no 255 limit), stored as TEXT, displayed as Per sq ft / Month
ALTER TABLE `managed_offices` ADD COLUMN IF NOT EXISTS `cam` TEXT DEFAULT NULL AFTER `price`;
ALTER TABLE `furnished_offices` ADD COLUMN IF NOT EXISTS `cam` TEXT DEFAULT NULL AFTER `price`;
ALTER TABLE `unfurnished_offices` ADD COLUMN IF NOT EXISTS `cam` TEXT DEFAULT NULL AFTER `price`;
-- For existing installs that already have varchar(255), expand to TEXT
ALTER TABLE `managed_offices` MODIFY COLUMN `cam` TEXT DEFAULT NULL;
ALTER TABLE `furnished_offices` MODIFY COLUMN `cam` TEXT DEFAULT NULL;
ALTER TABLE `unfurnished_offices` MODIFY COLUMN `cam` TEXT DEFAULT NULL;
