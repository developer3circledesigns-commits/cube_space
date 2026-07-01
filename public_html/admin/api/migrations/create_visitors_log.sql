CREATE TABLE IF NOT EXISTS visitors_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    user_agent VARCHAR(500) NULL,
    page_url VARCHAR(500) NULL,
    activity VARCHAR(255) NULL COMMENT 'Human-readable page name',
    is_vpn TINYINT(1) DEFAULT 0,
    vpn_detected_method VARCHAR(50) NULL,
    country VARCHAR(100) NULL,
    city VARCHAR(100) NULL,
    isp VARCHAR(255) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip (ip_address),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
