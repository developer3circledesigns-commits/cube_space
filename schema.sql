-- CubeSpace Database Schema
SET FOREIGN_KEY_CHECKS=0;

-- Create listing_cities table
CREATE TABLE IF NOT EXISTS `listing_cities` (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `city` varchar(100) NOT NULL UNIQUE,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create listing_areas table
CREATE TABLE IF NOT EXISTS `listing_areas` (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `area` varchar(150) NOT NULL,
  `city` varchar(100) NOT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `area_city` (`area`, `city`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create managed_offices table
CREATE TABLE IF NOT EXISTS `managed_offices` (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) UNIQUE,
  `description` longtext,
  `city` varchar(100),
  `area` varchar(150),
  `address` varchar(500),
  `latitude` decimal(10, 7),
  `longitude` decimal(10, 7),
  `price` varchar(255),
  `price_label` varchar(100),
  `total_seats` int,
  `total_area_sqft` int,
  `billable_seats` int DEFAULT NULL,
  `remarks` text,
  `min_inventory` varchar(50),
  `inventory_type` varchar(100),
  `remarks` text DEFAULT NULL,
  `amenities` json,
  `images` json,
  `status` enum('draft', 'published', 'archived') DEFAULT 'draft',
  `featured` tinyint(1) DEFAULT 0,
  `feature_highlights` json,
  `seo_text` longtext,
  `office_space_type` varchar(100),
  `listing_type` varchar(50),
  `listing_code` varchar(50),
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create furnished_offices table
CREATE TABLE IF NOT EXISTS `furnished_offices` (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) UNIQUE,
  `description` longtext,
  `city` varchar(100),
  `area` varchar(150),
  `address` varchar(500),
  `latitude` decimal(10, 7),
  `longitude` decimal(10, 7),
  `price` varchar(255),
  `price_label` varchar(100),
  `total_seats` int,
  `available_sqft` varchar(100),
  `min_inventory` varchar(50),
  `inventory_type` varchar(100),
  `total_area_sqft` int,
  `amenities` json,
  `images` json,
  `status` enum('draft', 'published', 'archived') DEFAULT 'draft',
  `featured` tinyint(1) DEFAULT 0,
  `feature_highlights` json,
  `seo_text` longtext,
  `office_space_type` varchar(100),
  `listing_type` varchar(50),
  `listing_code` varchar(50),
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create unfurnished_offices table
CREATE TABLE IF NOT EXISTS `unfurnished_offices` (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) UNIQUE,
  `description` longtext,
  `city` varchar(100),
  `area` varchar(150),
  `address` varchar(500),
  `latitude` decimal(10, 7),
  `longitude` decimal(10, 7),
  `price` varchar(255),
  `price_label` varchar(100),
  `total_seats` int,
  `available_sqft` varchar(100),
  `min_inventory` varchar(50),
  `inventory_type` varchar(100),
  `remarks` text DEFAULT NULL,
  `total_area_sqft` int,
  `amenities` json,
  `images` json,
  `status` enum('draft', 'published', 'archived') DEFAULT 'draft',
  `featured` tinyint(1) DEFAULT 0,
  `feature_highlights` json,
  `seo_text` longtext,
  `office_space_type` varchar(100),
  `listing_type` varchar(50),
  `listing_code` varchar(50),
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create office_spaces table (commercial)
CREATE TABLE IF NOT EXISTS `office_spaces` (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) UNIQUE,
  `description` longtext,
  `city` varchar(100),
  `area` varchar(150),
  `address` varchar(500),
  `latitude` decimal(10, 7),
  `longitude` decimal(10, 7),
  `price` varchar(255),
  `price_label` varchar(100),
  `total_seats` int,
  `total_area_sqft` int,
  `min_inventory` varchar(50),
  `inventory_type` varchar(100),
  `amenities` json,
  `images` json,
  `status` enum('draft', 'published', 'archived') DEFAULT 'draft',
  `featured` tinyint(1) DEFAULT 0,
  `feature_highlights` json,
  `seo_text` longtext,
  `office_space_type` varchar(100),
  `listing_type` varchar(50),
  `listing_code` varchar(50),
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create office_leasing_options table
CREATE TABLE IF NOT EXISTS `office_leasing_options` (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `office_id` int NOT NULL,
  `option_title` varchar(255),
  `option_desc` varchar(500),
  `option_price` varchar(100),
  `sort_order` int DEFAULT 1,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `office_id` (`office_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create contacts table
CREATE TABLE IF NOT EXISTS `contacts` (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `name` varchar(255) NOT NULL,
  `phone` varchar(20),
  `email` varchar(255),
  `interest` varchar(255),
  `company` varchar(255),
  `seats` varchar(50),
  `message` longtext,
  `office_id` int,
  `listing_code` varchar(50),
  `source` varchar(100),
  `submitted_ip` varchar(50),
  `user_agent` longtext,
  `status` enum('new', 'contacted', 'closed') DEFAULT 'new',
  `admin_notes` longtext,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `status` (`status`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create admins table
CREATE TABLE IF NOT EXISTS `admins` (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `username` varchar(100) NOT NULL UNIQUE,
  `email` varchar(255) NOT NULL UNIQUE,
  `password` varchar(255) NOT NULL,
  `role` enum('super_admin', 'manager', 'editor', 'support', 'viewer') DEFAULT 'viewer',
  `is_active` tinyint(1) DEFAULT 1,
  `last_login` timestamp NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create activity_log table
CREATE TABLE IF NOT EXISTS `activity_log` (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `admin_id` int,
  `admin_username` varchar(100),
  `action` varchar(50),
  `table_name` varchar(100),
  `record_id` int,
  `details` json,
  `ip_address` varchar(50),
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  KEY `admin_id` (`admin_id`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create visitors_log table
CREATE TABLE IF NOT EXISTS `visitors_log` (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `ip_address` varchar(50),
  `page_url` varchar(500),
  `activity` varchar(100),
  `is_vpn` tinyint(1) DEFAULT 0,
  `vpn_detected_method` varchar(100),
  `country` varchar(100),
  `city` varchar(100),
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  KEY `ip_address` (`ip_address`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create listing_images table (BLOB storage for uploaded images)
CREATE TABLE IF NOT EXISTS `listing_images` (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `listing_type` varchar(50) NOT NULL,
  `listing_id` int NOT NULL,
  `image_data` longblob NOT NULL,
  `image_mime` varchar(50) NOT NULL DEFAULT 'image/jpeg',
  `sort_order` int NOT NULL DEFAULT 0,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_listing` (`listing_type`, `listing_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS=1;
