ALTER TABLE managed_offices ADD COLUMN `billable_seats` int DEFAULT NULL AFTER `total_area_sqft`;
ALTER TABLE managed_offices ADD COLUMN `remarks` text DEFAULT NULL AFTER `billable_seats`;
