-- Add database indexes for query performance (idempotent - safe to re-run on any server)

-- ============================================================
-- managed_offices
-- ============================================================
CREATE TABLE IF NOT EXISTS `managed_offices` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `listing_type` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `area` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `latitude` double DEFAULT NULL,
  `longitude` double DEFAULT NULL,
  `price` varchar(255) DEFAULT NULL,
  `price_label` varchar(100) DEFAULT NULL,
  `total_seats` int(10) unsigned DEFAULT NULL,
  `min_inventory` int(10) unsigned DEFAULT NULL,
  `inventory_type` varchar(100) DEFAULT NULL,
  `total_area_sqft` int(10) unsigned NOT NULL DEFAULT 0,
  `amenities` longtext DEFAULT NULL,
  `images` longtext DEFAULT NULL,
  `featured` tinyint(1) NOT NULL DEFAULT 0,
  `status` varchar(20) NOT NULL DEFAULT 'draft',
  `feature_highlights` longtext DEFAULT NULL,
  `seo_text` text DEFAULT NULL,
  `office_space_type` varchar(20) NOT NULL DEFAULT 'rent',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `listing_code` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  UNIQUE KEY `listing_code` (`listing_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE managed_offices DROP INDEX IF EXISTS idx_managed_status;
ALTER TABLE managed_offices ADD INDEX idx_managed_status (status);
ALTER TABLE managed_offices DROP INDEX IF EXISTS idx_managed_city;
ALTER TABLE managed_offices ADD INDEX idx_managed_city (city);
ALTER TABLE managed_offices DROP INDEX IF EXISTS idx_managed_area;
ALTER TABLE managed_offices ADD INDEX idx_managed_area (area);
ALTER TABLE managed_offices DROP INDEX IF EXISTS idx_managed_slug;
ALTER TABLE managed_offices ADD INDEX idx_managed_slug (slug);
ALTER TABLE managed_offices DROP INDEX IF EXISTS idx_managed_listing_type;
ALTER TABLE managed_offices ADD INDEX idx_managed_listing_type (listing_type);
ALTER TABLE managed_offices DROP INDEX IF EXISTS idx_managed_created_at;
ALTER TABLE managed_offices ADD INDEX idx_managed_created_at (created_at);
ALTER TABLE managed_offices DROP INDEX IF EXISTS idx_managed_featured;
ALTER TABLE managed_offices ADD INDEX idx_managed_featured (featured);
ALTER TABLE managed_offices DROP INDEX IF EXISTS idx_managed_price;
ALTER TABLE managed_offices ADD INDEX idx_managed_price (price);
ALTER TABLE managed_offices DROP INDEX IF EXISTS idx_managed_total_seats;
ALTER TABLE managed_offices ADD INDEX idx_managed_total_seats (total_seats);
ALTER TABLE managed_offices DROP INDEX IF EXISTS idx_managed_coords;
ALTER TABLE managed_offices ADD INDEX idx_managed_coords (latitude, longitude);
ALTER TABLE managed_offices DROP INDEX IF EXISTS idx_managed_listing_status_city;
ALTER TABLE managed_offices ADD INDEX idx_managed_listing_status_city (status, city, listing_type);
ALTER TABLE managed_offices DROP INDEX IF EXISTS idx_managed_listing_status_area;
ALTER TABLE managed_offices ADD INDEX idx_managed_listing_status_area (status, area);

-- ============================================================
-- furnished_offices
-- ============================================================
CREATE TABLE IF NOT EXISTS `furnished_offices` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `listing_type` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `area` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `latitude` double DEFAULT NULL,
  `longitude` double DEFAULT NULL,
  `price` varchar(255) DEFAULT NULL,
  `price_label` varchar(100) DEFAULT NULL,
  `total_seats` int(10) unsigned DEFAULT NULL,
  `available_sqft` varchar(100) DEFAULT NULL,
  `min_inventory` varchar(50) DEFAULT NULL,
  `inventory_type` varchar(100) DEFAULT NULL,
  `total_area_sqft` int(10) unsigned NOT NULL DEFAULT 0,
  `office_space_type` varchar(20) NOT NULL DEFAULT 'rent',
  `amenities` longtext DEFAULT NULL,
  `images` longtext DEFAULT NULL,
  `featured` tinyint(1) NOT NULL DEFAULT 0,
  `feature_highlights` longtext DEFAULT NULL,
  `seo_text` text DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'draft',
  `listing_code` varchar(20) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE furnished_offices DROP INDEX IF EXISTS idx_furnished_status;
ALTER TABLE furnished_offices ADD INDEX idx_furnished_status (status);
ALTER TABLE furnished_offices DROP INDEX IF EXISTS idx_furnished_city;
ALTER TABLE furnished_offices ADD INDEX idx_furnished_city (city);
ALTER TABLE furnished_offices DROP INDEX IF EXISTS idx_furnished_area;
ALTER TABLE furnished_offices ADD INDEX idx_furnished_area (area);
ALTER TABLE furnished_offices DROP INDEX IF EXISTS idx_furnished_slug;
ALTER TABLE furnished_offices ADD INDEX idx_furnished_slug (slug);
ALTER TABLE furnished_offices DROP INDEX IF EXISTS idx_furnished_listing_type;
ALTER TABLE furnished_offices ADD INDEX idx_furnished_listing_type (listing_type);
ALTER TABLE furnished_offices DROP INDEX IF EXISTS idx_furnished_created_at;
ALTER TABLE furnished_offices ADD INDEX idx_furnished_created_at (created_at);
ALTER TABLE furnished_offices DROP INDEX IF EXISTS idx_furnished_featured;
ALTER TABLE furnished_offices ADD INDEX idx_furnished_featured (featured);
ALTER TABLE furnished_offices DROP INDEX IF EXISTS idx_furnished_price;
ALTER TABLE furnished_offices ADD INDEX idx_furnished_price (price);
ALTER TABLE furnished_offices DROP INDEX IF EXISTS idx_furnished_total_seats;
ALTER TABLE furnished_offices ADD INDEX idx_furnished_total_seats (total_seats);
ALTER TABLE furnished_offices DROP INDEX IF EXISTS idx_furnished_coords;
ALTER TABLE furnished_offices ADD INDEX idx_furnished_coords (latitude, longitude);
ALTER TABLE furnished_offices DROP INDEX IF EXISTS idx_furnished_status_city;
ALTER TABLE furnished_offices ADD INDEX idx_furnished_status_city (status, city);
ALTER TABLE furnished_offices DROP INDEX IF EXISTS idx_furnished_status_area;
ALTER TABLE furnished_offices ADD INDEX idx_furnished_status_area (status, area);

-- ============================================================
-- unfurnished_offices
-- ============================================================
CREATE TABLE IF NOT EXISTS `unfurnished_offices` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `listing_type` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `area` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `latitude` double DEFAULT NULL,
  `longitude` double DEFAULT NULL,
  `price` varchar(255) DEFAULT NULL,
  `price_label` varchar(100) DEFAULT NULL,
  `total_seats` int(10) unsigned DEFAULT NULL,
  `available_sqft` varchar(100) DEFAULT NULL,
  `min_inventory` varchar(50) DEFAULT NULL,
  `inventory_type` varchar(100) DEFAULT NULL,
  `total_area_sqft` int(10) unsigned NOT NULL DEFAULT 0,
  `office_space_type` varchar(20) NOT NULL DEFAULT 'rent',
  `amenities` longtext DEFAULT NULL,
  `images` longtext DEFAULT NULL,
  `featured` tinyint(1) NOT NULL DEFAULT 0,
  `feature_highlights` longtext DEFAULT NULL,
  `seo_text` text DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'draft',
  `listing_code` varchar(20) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE unfurnished_offices DROP INDEX IF EXISTS idx_unfurnished_status;
ALTER TABLE unfurnished_offices ADD INDEX idx_unfurnished_status (status);
ALTER TABLE unfurnished_offices DROP INDEX IF EXISTS idx_unfurnished_city;
ALTER TABLE unfurnished_offices ADD INDEX idx_unfurnished_city (city);
ALTER TABLE unfurnished_offices DROP INDEX IF EXISTS idx_unfurnished_area;
ALTER TABLE unfurnished_offices ADD INDEX idx_unfurnished_area (area);
ALTER TABLE unfurnished_offices DROP INDEX IF EXISTS idx_unfurnished_slug;
ALTER TABLE unfurnished_offices ADD INDEX idx_unfurnished_slug (slug);
ALTER TABLE unfurnished_offices DROP INDEX IF EXISTS idx_unfurnished_listing_type;
ALTER TABLE unfurnished_offices ADD INDEX idx_unfurnished_listing_type (listing_type);
ALTER TABLE unfurnished_offices DROP INDEX IF EXISTS idx_unfurnished_created_at;
ALTER TABLE unfurnished_offices ADD INDEX idx_unfurnished_created_at (created_at);
ALTER TABLE unfurnished_offices DROP INDEX IF EXISTS idx_unfurnished_featured;
ALTER TABLE unfurnished_offices ADD INDEX idx_unfurnished_featured (featured);
ALTER TABLE unfurnished_offices DROP INDEX IF EXISTS idx_unfurnished_price;
ALTER TABLE unfurnished_offices ADD INDEX idx_unfurnished_price (price);
ALTER TABLE unfurnished_offices DROP INDEX IF EXISTS idx_unfurnished_total_seats;
ALTER TABLE unfurnished_offices ADD INDEX idx_unfurnished_total_seats (total_seats);
ALTER TABLE unfurnished_offices DROP INDEX IF EXISTS idx_unfurnished_coords;
ALTER TABLE unfurnished_offices ADD INDEX idx_unfurnished_coords (latitude, longitude);
ALTER TABLE unfurnished_offices DROP INDEX IF EXISTS idx_unfurnished_status_city;
ALTER TABLE unfurnished_offices ADD INDEX idx_unfurnished_status_city (status, city);
ALTER TABLE unfurnished_offices DROP INDEX IF EXISTS idx_unfurnished_status_area;
ALTER TABLE unfurnished_offices ADD INDEX idx_unfurnished_status_area (status, area);

-- ============================================================
-- office_spaces
-- ============================================================
CREATE TABLE IF NOT EXISTS `office_spaces` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `listing_type` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `area` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `latitude` double DEFAULT NULL,
  `longitude` double DEFAULT NULL,
  `price` varchar(255) DEFAULT NULL,
  `price_label` varchar(100) DEFAULT NULL,
  `total_seats` int(10) unsigned DEFAULT NULL,
  `total_area_sqft` int(10) unsigned NOT NULL DEFAULT 0,
  `amenities` longtext DEFAULT NULL,
  `images` longtext DEFAULT NULL,
  `featured` tinyint(1) NOT NULL DEFAULT 0,
  `status` varchar(20) NOT NULL DEFAULT 'draft',
  `feature_highlights` longtext DEFAULT NULL,
  `seo_text` text DEFAULT NULL,
  `office_space_type` varchar(20) NOT NULL DEFAULT 'rent',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `listing_code` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  UNIQUE KEY `listing_code` (`listing_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE office_spaces DROP INDEX IF EXISTS idx_spaces_status;
ALTER TABLE office_spaces ADD INDEX idx_spaces_status (status);
ALTER TABLE office_spaces DROP INDEX IF EXISTS idx_spaces_city;
ALTER TABLE office_spaces ADD INDEX idx_spaces_city (city);
ALTER TABLE office_spaces DROP INDEX IF EXISTS idx_spaces_created_at;
ALTER TABLE office_spaces ADD INDEX idx_spaces_created_at (created_at);

-- ============================================================
-- contacts
-- ============================================================
CREATE TABLE IF NOT EXISTS `contacts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `email` varchar(255) NOT NULL,
  `interest` varchar(100) NOT NULL,
  `company` varchar(255) DEFAULT NULL,
  `seats` varchar(20) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `office_id` int(10) unsigned DEFAULT NULL,
  `listing_code` varchar(20) DEFAULT NULL,
  `source` varchar(255) DEFAULT NULL,
  `submitted_ip` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'new',
  `contacted_at` datetime DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL,
  `admin_notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE contacts DROP INDEX IF EXISTS idx_contacts_status;
ALTER TABLE contacts ADD INDEX idx_contacts_status (status);
ALTER TABLE contacts DROP INDEX IF EXISTS idx_contacts_created_at;
ALTER TABLE contacts ADD INDEX idx_contacts_created_at (created_at);
ALTER TABLE contacts DROP INDEX IF EXISTS idx_contacts_email;
ALTER TABLE contacts ADD INDEX idx_contacts_email (email);
ALTER TABLE contacts DROP INDEX IF EXISTS idx_contacts_phone;
ALTER TABLE contacts ADD INDEX idx_contacts_phone (phone);

-- ============================================================
-- activity_log
-- ============================================================
CREATE TABLE IF NOT EXISTS `activity_log` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `admin_id` int(10) unsigned NOT NULL,
  `admin_username` varchar(255) NOT NULL,
  `action` varchar(50) NOT NULL,
  `table_name` varchar(100) NOT NULL,
  `record_id` int(10) unsigned NOT NULL DEFAULT 0,
  `details` longtext DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE activity_log DROP INDEX IF EXISTS idx_activity_created_at;
ALTER TABLE activity_log ADD INDEX idx_activity_created_at (created_at);
ALTER TABLE activity_log DROP INDEX IF EXISTS idx_activity_admin_id;
ALTER TABLE activity_log ADD INDEX idx_activity_admin_id (admin_id);
ALTER TABLE activity_log DROP INDEX IF EXISTS idx_activity_table_name;
ALTER TABLE activity_log ADD INDEX idx_activity_table_name (table_name);

-- ============================================================
-- office_leasing_options
-- ============================================================
CREATE TABLE IF NOT EXISTS `office_leasing_options` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `office_id` int(10) unsigned NOT NULL,
  `option_title` varchar(255) NOT NULL,
  `option_desc` text DEFAULT NULL,
  `option_price` varchar(100) DEFAULT NULL,
  `option_image` varchar(255) DEFAULT NULL,
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE office_leasing_options DROP INDEX IF EXISTS idx_leasing_office_id;
ALTER TABLE office_leasing_options ADD INDEX idx_leasing_office_id (office_id);
ALTER TABLE office_leasing_options DROP INDEX IF EXISTS idx_leasing_active;
ALTER TABLE office_leasing_options ADD INDEX idx_leasing_active (is_active);

-- ============================================================
-- realtime_events
-- ============================================================
CREATE TABLE IF NOT EXISTS `realtime_events` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `event_type` varchar(50) NOT NULL,
  `entity_type` varchar(50) DEFAULT NULL,
  `entity_id` int(10) unsigned DEFAULT NULL,
  `summary` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE realtime_events DROP INDEX IF EXISTS idx_events_created_at;
ALTER TABLE realtime_events ADD INDEX idx_events_created_at (created_at);
ALTER TABLE realtime_events DROP INDEX IF EXISTS idx_events_type;
ALTER TABLE realtime_events ADD INDEX idx_events_type (event_type);

-- ============================================================
-- visitors_log
-- ============================================================
CREATE TABLE IF NOT EXISTS `visitors_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `page_url` varchar(500) DEFAULT NULL,
  `activity` varchar(255) DEFAULT NULL,
  `is_vpn` tinyint(1) DEFAULT 0,
  `vpn_detected_method` varchar(50) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `isp` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE visitors_log DROP INDEX IF EXISTS idx_visitors_created_at;
ALTER TABLE visitors_log ADD INDEX idx_visitors_created_at (created_at);
ALTER TABLE visitors_log DROP INDEX IF EXISTS idx_visitors_page_url;
ALTER TABLE visitors_log ADD INDEX idx_visitors_page_url (page_url(100));

-- ============================================================
-- password_resets
-- ============================================================
CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `admin_id` int(10) unsigned NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- listing_images (BLOB storage for uploaded images)
-- ============================================================
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

ALTER TABLE password_resets DROP INDEX IF EXISTS idx_resets_admin_id;
ALTER TABLE password_resets ADD INDEX idx_resets_admin_id (admin_id);
ALTER TABLE password_resets DROP INDEX IF EXISTS idx_resets_token;
ALTER TABLE password_resets ADD INDEX idx_resets_token (token);
ALTER TABLE password_resets DROP INDEX IF EXISTS idx_resets_expires;
ALTER TABLE password_resets ADD INDEX idx_resets_expires (expires_at);
