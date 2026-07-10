ALTER TABLE furnished_offices ADD COLUMN `remarks` text DEFAULT NULL AFTER `inventory_type`;
ALTER TABLE unfurnished_offices ADD COLUMN `remarks` text DEFAULT NULL AFTER `inventory_type`;
