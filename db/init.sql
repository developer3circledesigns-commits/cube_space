CREATE DATABASE IF NOT EXISTS `u814177917_cubespace`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `u814177917_cubespace`;

-- Admins
CREATE TABLE IF NOT EXISTS `admins` (
  `id`                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `username`           VARCHAR(255) NOT NULL UNIQUE,
  `email`              VARCHAR(255) NULL UNIQUE,
  `password`           VARCHAR(255) NOT NULL,
  `role`               VARCHAR(50)  NOT NULL DEFAULT 'admin',
  `is_active`          TINYINT(1)   NOT NULL DEFAULT 1,
  `last_login`         DATETIME     NULL,
  `reset_token`        VARCHAR(64)  NULL,
  `reset_token_expiry` DATETIME     NULL,
  `created_at`         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Contacts
CREATE TABLE IF NOT EXISTS `contacts` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`         VARCHAR(255) NOT NULL,
  `phone`        VARCHAR(20)  NOT NULL,
  `email`        VARCHAR(255) NOT NULL,
  `interest`     VARCHAR(100) NOT NULL,
  `company`      VARCHAR(255) NULL,
  `seats`        VARCHAR(20)  NULL,
  `message`      TEXT         NULL,
  `office_id`    INT UNSIGNED NULL,
  `source`       VARCHAR(255) NULL,
  `submitted_ip` VARCHAR(45)  NULL,
  `user_agent`   TEXT         NULL,
  `status`       VARCHAR(20)  NOT NULL DEFAULT 'new',
  `admin_notes`  TEXT         NULL,
  `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Partners
CREATE TABLE IF NOT EXISTS `partners` (
  `id`                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `establishment_type` VARCHAR(100) NOT NULL,
  `establishment_name` VARCHAR(255) NOT NULL,
  `ownership_type`     VARCHAR(50)  NULL,
  `city`               VARCHAR(100) NOT NULL,
  `address`            TEXT         NOT NULL,
  `first_name`         VARCHAR(255) NOT NULL,
  `last_name`          VARCHAR(255) NOT NULL,
  `phone`              VARCHAR(20)  NOT NULL,
  `email`              VARCHAR(255) NOT NULL,
  `images`             JSON         NULL,
  `status`             VARCHAR(20)  NOT NULL DEFAULT 'pending',
  `admin_notes`        TEXT         NULL,
  `created_at`         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Managed offices
CREATE TABLE IF NOT EXISTS `managed_offices` (
  `id`                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `title`              VARCHAR(255) NOT NULL,
  `slug`               VARCHAR(255) NOT NULL UNIQUE,
  `listing_type`       VARCHAR(50)  NULL,
  `description`        TEXT         NULL,
  `city`               VARCHAR(100) NULL,
  `area`               VARCHAR(255) NULL,
  `address`            TEXT         NULL,
  `latitude`           DOUBLE       NULL,
  `longitude`          DOUBLE       NULL,
  `price`              DOUBLE       NULL,
  `price_label`        VARCHAR(100) NULL,
  `total_seats`        INT UNSIGNED NULL,
  `total_area_sqft`    INT UNSIGNED NOT NULL DEFAULT 0,
  `amenities`          JSON         NULL,
  `images`             JSON         NULL,
  `featured`           TINYINT(1)   NOT NULL DEFAULT 0,
  `status`             VARCHAR(20)  NOT NULL DEFAULT 'draft',
  `feature_highlights` JSON         NULL,
  `seo_text`           TEXT         NULL,
  `office_space_type`  VARCHAR(20)  NOT NULL DEFAULT 'rent',
  `created_at`         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Office spaces (same structure as managed_offices)
CREATE TABLE IF NOT EXISTS `office_spaces` (
  `id`                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `title`              VARCHAR(255) NOT NULL,
  `slug`               VARCHAR(255) NOT NULL UNIQUE,
  `listing_type`       VARCHAR(50)  NULL,
  `description`        TEXT         NULL,
  `city`               VARCHAR(100) NULL,
  `area`               VARCHAR(255) NULL,
  `address`            TEXT         NULL,
  `latitude`           DOUBLE       NULL,
  `longitude`          DOUBLE       NULL,
  `price`              DOUBLE       NULL,
  `price_label`        VARCHAR(100) NULL,
  `total_seats`        INT UNSIGNED NULL,
  `total_area_sqft`    INT UNSIGNED NOT NULL DEFAULT 0,
  `amenities`          JSON         NULL,
  `images`             JSON         NULL,
  `featured`           TINYINT(1)   NOT NULL DEFAULT 0,
  `status`             VARCHAR(20)  NOT NULL DEFAULT 'draft',
  `feature_highlights` JSON         NULL,
  `seo_text`           TEXT         NULL,
  `office_space_type`  VARCHAR(20)  NOT NULL DEFAULT 'rent',
  `created_at`         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Realtime events
CREATE TABLE IF NOT EXISTS `realtime_events` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `event_type`  VARCHAR(50)  NOT NULL,
  `entity_type` VARCHAR(50)  NULL,
  `entity_id`   INT UNSIGNED NULL,
  `summary`     VARCHAR(255) NULL,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Activity log
CREATE TABLE IF NOT EXISTS `activity_log` (
  `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `admin_id`       INT UNSIGNED NOT NULL,
  `admin_username` VARCHAR(255) NOT NULL,
  `action`         VARCHAR(50)  NOT NULL,
  `table_name`     VARCHAR(100) NOT NULL,
  `record_id`      INT UNSIGNED NOT NULL DEFAULT 0,
  `details`        JSON         NULL,
  `ip_address`     VARCHAR(45)  NULL,
  `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Office reviews
CREATE TABLE IF NOT EXISTS `office_reviews` (
  `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `office_id`     INT UNSIGNED NOT NULL,
  `reviewer_name` VARCHAR(255) NOT NULL,
  `rating`        TINYINT UNSIGNED NOT NULL,
  `review_text`   TEXT         NULL,
  `status`        VARCHAR(20)  NOT NULL DEFAULT 'approved',
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Office FAQ
CREATE TABLE IF NOT EXISTS `office_faq` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `office_id`  INT UNSIGNED NOT NULL,
  `question`   TEXT         NOT NULL,
  `answer`     TEXT         NOT NULL,
  `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
  `is_active`  TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Office building details
CREATE TABLE IF NOT EXISTS `office_building_details` (
  `id`               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `office_id`        INT UNSIGNED NOT NULL UNIQUE,
  `building_name`    VARCHAR(255) NULL,
  `year_built`       VARCHAR(10)  NULL,
  `total_floors`     INT UNSIGNED NOT NULL DEFAULT 0,
  `floor_plate_area` VARCHAR(100) NULL,
  `elevators`        INT UNSIGNED NOT NULL DEFAULT 0,
  `parking`          VARCHAR(255) NULL,
  `nearest_metro`    VARCHAR(255) NULL,
  `nearest_railway`  VARCHAR(255) NULL,
  `airport`          VARCHAR(255) NULL,
  `bus_stop`         VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Office leasing options
CREATE TABLE IF NOT EXISTS `office_leasing_options` (
  `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `office_id`     INT UNSIGNED NOT NULL,
  `option_title`  VARCHAR(255) NOT NULL,
  `option_desc`   TEXT         NULL,
  `option_price`  VARCHAR(100) NULL,
  `option_image`  VARCHAR(255) NULL,
  `sort_order`    INT UNSIGNED NOT NULL DEFAULT 0,
  `is_active`     TINYINT(1)   NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed admin user (password: admin123)
INSERT INTO `admins` (`username`, `password`, `role`, `is_active`)
VALUES ('admin', '$2y$12$OR3eCUODz8q5D5SScnwiEOlJGACHlz/7oNi6EmOiBRVM4EELqysz.', 'super_admin', 1)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- Sample office listing
INSERT INTO `managed_offices` (`title`, `slug`, `description`, `city`, `area`, `address`, `latitude`, `longitude`, `price`, `price_label`, `total_seats`, `total_area_sqft`, `amenities`, `images`, `featured`, `status`, `office_space_type`)
VALUES (
  'CubeSpace Business Center - T Nagar',
  'cubespace-business-center-t-nagar',
  'Premium managed office space in the heart of T Nagar, Chennai. Fully furnished with high-speed internet, meeting rooms, and cafeteria.',
  'chennai',
  'T Nagar',
  '12, Ranganathan Street, T Nagar, Chennai - 600017',
  13.0418,
  80.2341,
  15000,
  '₹15,000/seat/month',
  50,
  2500,
  '["High-speed WiFi","Meeting Rooms","Cafeteria","24/7 Access","Power Backup","Security","Parking","AC"]',
  '["listing_6a2fa1f068250.jpg","listing_6a2fa1f0694d3.jpg"]',
  1,
  'published',
  'rent'
);

-- Sample review
INSERT INTO `office_reviews` (`office_id`, `reviewer_name`, `rating`, `review_text`, `status`)
VALUES (1, 'Rajesh Kumar', 5, 'Excellent workspace with great amenities. Highly recommended for startups.', 'approved');

-- Sample FAQ
INSERT INTO `office_faq` (`office_id`, `question`, `answer`, `sort_order`, `is_active`)
VALUES (1, 'What are the operating hours?', 'The center is open 24/7 for all members.', 1, 1);

-- Sample building details
INSERT INTO `office_building_details` (`office_id`, `building_name`, `year_built`, `total_floors`, `floor_plate_area`, `elevators`, `parking`, `nearest_metro`, `nearest_railway`, `airport`, `bus_stop`)
VALUES (1, 'CubeSpace Tower', '2020', 5, '5000 sq ft per floor', 2, '100+ car parking', 'T Nagar Metro', 'T Nagar Railway Station', 'Chennai International Airport - 15 km', 'T Nagar Bus Stand');

-- Sample leasing options
INSERT INTO `office_leasing_options` (`office_id`, `option_title`, `option_desc`, `option_price`, `sort_order`, `is_active`)
VALUES (1, 'Private Cabin', 'Fully furnished private cabin for 2-4 people', '₹35,000/month', 1, 1);

-- =====================================================
-- INDEXES FOR PERFORMANCE
-- =====================================================

-- Managed offices indexes
CREATE INDEX idx_managed_offices_status ON managed_offices(status);
CREATE INDEX idx_managed_offices_city ON managed_offices(city);
CREATE INDEX idx_managed_offices_area ON managed_offices(area);
CREATE INDEX idx_managed_offices_slug ON managed_offices(slug);
CREATE INDEX idx_managed_offices_featured ON managed_offices(featured, status);
CREATE INDEX idx_managed_offices_created ON managed_offices(created_at);
CREATE INDEX idx_managed_offices_price ON managed_offices(price);
CREATE INDEX idx_managed_offices_seats ON managed_offices(total_seats);

-- Office spaces indexes
CREATE INDEX idx_office_spaces_status ON office_spaces(status);
CREATE INDEX idx_office_spaces_city ON office_spaces(city);
CREATE INDEX idx_office_spaces_area ON office_spaces(area);
CREATE INDEX idx_office_spaces_slug ON office_spaces(slug);
CREATE INDEX idx_office_spaces_featured ON office_spaces(featured, status);

-- Contacts indexes
CREATE INDEX idx_contacts_status ON contacts(status);
CREATE INDEX idx_contacts_created ON contacts(created_at);
CREATE INDEX idx_contacts_office_id ON contacts(office_id);

-- Partners indexes
CREATE INDEX idx_partners_status ON partners(status);
CREATE INDEX idx_partners_city ON partners(city);
CREATE INDEX idx_partners_created ON partners(created_at);

-- Realtime events indexes
CREATE INDEX idx_realtime_events_type ON realtime_events(event_type);
CREATE INDEX idx_realtime_events_created ON realtime_events(created_at);
CREATE INDEX idx_realtime_events_entity ON realtime_events(entity_type, entity_id);

-- Activity log indexes
CREATE INDEX idx_activity_log_admin ON activity_log(admin_id);
CREATE INDEX idx_activity_log_table ON activity_log(table_name, record_id);
CREATE INDEX idx_activity_log_created ON activity_log(created_at);

-- Office reviews indexes
CREATE INDEX idx_office_reviews_office ON office_reviews(office_id);
CREATE INDEX idx_office_reviews_status ON office_reviews(status);

-- Office FAQ indexes
CREATE INDEX idx_office_faq_office ON office_faq(office_id);
CREATE INDEX idx_office_faq_active ON office_faq(is_active, sort_order);

-- Office building details indexes
CREATE INDEX idx_office_building_office ON office_building_details(office_id);

-- Office leasing options indexes
CREATE INDEX idx_office_leasing_office ON office_leasing_options(office_id);
CREATE INDEX idx_office_leasing_active ON office_leasing_options(is_active, sort_order);
