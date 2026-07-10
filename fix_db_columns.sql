-- Fix missing columns in production database
-- Run these against your hosting MySQL database

ALTER TABLE furnished_offices
  ADD COLUMN IF NOT EXISTS description TEXT DEFAULT NULL AFTER slug,
  ADD COLUMN IF NOT EXISTS latitude DECIMAL(10,8) DEFAULT NULL AFTER address,
  ADD COLUMN IF NOT EXISTS longitude DECIMAL(11,8) DEFAULT NULL AFTER latitude,
  ADD COLUMN IF NOT EXISTS feature_highlights TEXT DEFAULT NULL AFTER featured,
  ADD COLUMN IF NOT EXISTS seo_text TEXT DEFAULT NULL AFTER feature_highlights,
  ADD COLUMN IF NOT EXISTS listing_type VARCHAR(50) DEFAULT 'furnished' AFTER office_space_type,
  ADD COLUMN IF NOT EXISTS listing_code VARCHAR(20) DEFAULT NULL AFTER listing_type;

ALTER TABLE unfurnished_offices
  ADD COLUMN IF NOT EXISTS description TEXT DEFAULT NULL AFTER slug,
  ADD COLUMN IF NOT EXISTS latitude DECIMAL(10,8) DEFAULT NULL AFTER address,
  ADD COLUMN IF NOT EXISTS longitude DECIMAL(11,8) DEFAULT NULL AFTER latitude,
  ADD COLUMN IF NOT EXISTS feature_highlights TEXT DEFAULT NULL AFTER featured,
  ADD COLUMN IF NOT EXISTS seo_text TEXT DEFAULT NULL AFTER feature_highlights,
  ADD COLUMN IF NOT EXISTS listing_type VARCHAR(50) DEFAULT 'unfurnished' AFTER office_space_type,
  ADD COLUMN IF NOT EXISTS listing_code VARCHAR(20) DEFAULT NULL AFTER listing_type;

ALTER TABLE managed_offices
  ADD COLUMN IF NOT EXISTS description TEXT DEFAULT NULL AFTER slug,
  ADD COLUMN IF NOT EXISTS latitude DECIMAL(10,8) DEFAULT NULL AFTER address,
  ADD COLUMN IF NOT EXISTS longitude DECIMAL(11,8) DEFAULT NULL AFTER latitude,
  ADD COLUMN IF NOT EXISTS feature_highlights TEXT DEFAULT NULL AFTER featured,
  ADD COLUMN IF NOT EXISTS seo_text TEXT DEFAULT NULL AFTER feature_highlights,
  ADD COLUMN IF NOT EXISTS listing_type VARCHAR(50) DEFAULT 'managed' AFTER office_space_type,
  ADD COLUMN IF NOT EXISTS listing_code VARCHAR(20) DEFAULT NULL AFTER listing_type,
  ADD COLUMN IF NOT EXISTS min_inventory VARCHAR(100) DEFAULT NULL AFTER total_area_sqft,
  ADD COLUMN IF NOT EXISTS inventory_type VARCHAR(50) DEFAULT NULL AFTER min_inventory,
  ADD COLUMN IF NOT EXISTS billable_seats INT DEFAULT NULL AFTER total_area_sqft,
  ADD COLUMN IF NOT EXISTS remarks TEXT DEFAULT NULL AFTER billable_seats;
