-- =============================================================
-- CubeSpace - Complete Database Schema
-- Database: u814177917_cubespace
-- =============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- Drop tables if they exist (order matters for FK)
DROP TABLE IF EXISTS realtime_events;
DROP TABLE IF EXISTS activity_log;
DROP TABLE IF EXISTS office_leasing_options;
DROP TABLE IF EXISTS contacts;
DROP TABLE IF EXISTS unfurnished_offices;
DROP TABLE IF EXISTS furnished_offices;
DROP TABLE IF EXISTS office_spaces;
DROP TABLE IF EXISTS managed_offices;
DROP TABLE IF EXISTS admins;

-- ===== 1. admins =====
CREATE TABLE admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    email VARCHAR(255) NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(50) NOT NULL DEFAULT 'admin',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    reset_token VARCHAR(64) NULL,
    reset_token_expiry DATETIME NULL,
    last_login DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===== 2. managed_offices =====
CREATE TABLE managed_offices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    listing_code VARCHAR(20) NULL,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    description TEXT NULL,
    listing_type VARCHAR(50) NULL DEFAULT 'managed',
    city VARCHAR(100) NOT NULL DEFAULT 'chennai',
    area VARCHAR(100) NULL,
    address VARCHAR(1000) NULL,
    latitude DECIMAL(10,7) NULL,
    longitude DECIMAL(10,7) NULL,
    price DECIMAL(12,2) NULL,
    price_label VARCHAR(120) NULL,
    total_seats INT NULL,
    min_inventory VARCHAR(50) NULL,
    inventory_type VARCHAR(50) NULL,
    total_area_sqft INT NOT NULL DEFAULT 0,
    office_space_type VARCHAR(20) NOT NULL DEFAULT 'rent',
    amenities TEXT NULL,
    images TEXT NULL,
    featured TINYINT(1) NOT NULL DEFAULT 0,
    feature_highlights TEXT NULL,
    seo_text TEXT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_slug (slug),
    INDEX idx_status (status),
    INDEX idx_city (city),
    INDEX idx_featured (featured),
    INDEX idx_listing_code (listing_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===== 3. furnished_offices =====
CREATE TABLE furnished_offices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    listing_code VARCHAR(20) NULL,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    description TEXT NULL,
    city VARCHAR(100) NOT NULL DEFAULT 'chennai',
    area VARCHAR(100) NULL,
    address VARCHAR(1000) NULL,
    latitude DECIMAL(10,7) NULL,
    longitude DECIMAL(10,7) NULL,
    price DECIMAL(12,2) NULL,
    price_label VARCHAR(120) NULL,
    total_seats INT NULL,
    available_sqft VARCHAR(50) NULL,
    min_inventory VARCHAR(50) NULL,
    inventory_type VARCHAR(50) NULL,
    total_area_sqft INT NOT NULL DEFAULT 0,
    office_space_type VARCHAR(20) NOT NULL DEFAULT 'rent',
    amenities TEXT NULL,
    images TEXT NULL,
    featured TINYINT(1) NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_slug (slug),
    INDEX idx_status (status),
    INDEX idx_city (city),
    INDEX idx_featured (featured),
    INDEX idx_listing_code (listing_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===== 4. unfurnished_offices =====
CREATE TABLE unfurnished_offices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    listing_code VARCHAR(20) NULL,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    description TEXT NULL,
    city VARCHAR(100) NOT NULL DEFAULT 'chennai',
    area VARCHAR(100) NULL,
    address VARCHAR(1000) NULL,
    latitude DECIMAL(10,7) NULL,
    longitude DECIMAL(10,7) NULL,
    price DECIMAL(12,2) NULL,
    price_label VARCHAR(120) NULL,
    total_seats INT NULL,
    available_sqft VARCHAR(50) NULL,
    min_inventory VARCHAR(50) NULL,
    inventory_type VARCHAR(50) NULL,
    total_area_sqft INT NOT NULL DEFAULT 0,
    office_space_type VARCHAR(20) NOT NULL DEFAULT 'rent',
    amenities TEXT NULL,
    images TEXT NULL,
    featured TINYINT(1) NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_slug (slug),
    INDEX idx_status (status),
    INDEX idx_city (city),
    INDEX idx_featured (featured),
    INDEX idx_listing_code (listing_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===== 5. office_spaces =====
CREATE TABLE office_spaces (
    id INT AUTO_INCREMENT PRIMARY KEY,
    listing_code VARCHAR(20) NULL,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    description TEXT NULL,
    listing_type VARCHAR(50) NULL DEFAULT 'commercial',
    city VARCHAR(100) NOT NULL DEFAULT 'chennai',
    area VARCHAR(100) NULL,
    address VARCHAR(1000) NULL,
    latitude DECIMAL(10,7) NULL,
    longitude DECIMAL(10,7) NULL,
    price DECIMAL(12,2) NULL,
    price_label VARCHAR(120) NULL,
    total_seats INT NULL,
    total_area_sqft INT NOT NULL DEFAULT 0,
    office_space_type VARCHAR(20) NOT NULL DEFAULT 'rent',
    amenities TEXT NULL,
    images TEXT NULL,
    featured TINYINT(1) NOT NULL DEFAULT 0,
    feature_highlights TEXT NULL,
    seo_text TEXT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_slug (slug),
    INDEX idx_status (status),
    INDEX idx_city (city),
    INDEX idx_featured (featured),
    INDEX idx_listing_code (listing_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===== 6. contacts =====
CREATE TABLE contacts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    email VARCHAR(255) NULL,
    interest VARCHAR(50) NULL,
    company VARCHAR(160) NULL,
    seats VARCHAR(20) NULL,
    message TEXT NULL,
    office_id INT NULL,
    listing_code VARCHAR(20) NULL,
    source VARCHAR(255) NULL,
    submitted_ip VARCHAR(45) NULL,
    user_agent TEXT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'new',
    admin_notes TEXT NULL,
    contacted_at DATETIME NULL,
    closed_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_interest (interest),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===== 7. office_leasing_options =====
CREATE TABLE office_leasing_options (
    id INT AUTO_INCREMENT PRIMARY KEY,
    office_id INT NOT NULL,
    option_title VARCHAR(255) NOT NULL,
    option_desc TEXT NULL,
    option_price VARCHAR(100) NULL,
    option_image VARCHAR(255) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_office_id (office_id),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===== 11. activity_log =====
CREATE TABLE activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL DEFAULT 0,
    admin_username VARCHAR(100) NOT NULL DEFAULT 'system',
    action VARCHAR(50) NOT NULL,
    table_name VARCHAR(100) NOT NULL,
    record_id INT NOT NULL DEFAULT 0,
    details TEXT NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_admin_id (admin_id),
    INDEX idx_action (action),
    INDEX idx_table_name (table_name),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===== 12. realtime_events =====
CREATE TABLE realtime_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_type VARCHAR(50) NOT NULL,
    entity_type VARCHAR(50) NULL,
    entity_id INT NULL,
    summary TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_event_type (event_type),
    INDEX idx_created_at (created_at),
    INDEX idx_entity (entity_type, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;
