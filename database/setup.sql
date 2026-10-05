CREATE DATABASE IF NOT EXISTS sena_asset CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sena_asset;

-- Table: asset_categories (หมวดหมู่ครุภัณฑ์)
CREATE TABLE IF NOT EXISTS asset_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    registry_enabled TINYINT(1) NOT NULL DEFAULT 1,
    category_name VARCHAR(255) NOT NULL,
    category_code VARCHAR(50) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_category_name (category_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: inspection_items (บัญชีรายการตรวจสอบครุภัณฑ์ประจำปี)
CREATE TABLE IF NOT EXISTS inspection_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_number INT NULL COMMENT 'ลำดับที่',
    item_name VARCHAR(500) NOT NULL COMMENT 'รายการ',
    asset_code VARCHAR(100) NULL COMMENT 'รหัสครุภัณฑ์',
    asset_id_code VARCHAR(100) NULL COMMENT 'รหัสสินทรัพย์',
    status_usable TINYINT(1) DEFAULT 0 COMMENT 'ใช้ได้',
    status_damaged TINYINT(1) DEFAULT 0 COMMENT 'ชำรุด',
    status_degraded TINYINT(1) DEFAULT 0 COMMENT 'เสื่อมคุณภาพ',
    status_lost TINYINT(1) DEFAULT 0 COMMENT 'สูญไป',
    status_unused TINYINT(1) DEFAULT 0 COMMENT 'ไม่ใช้',
    remarks TEXT NULL COMMENT 'หมายเหตุ',
    fiscal_year INT DEFAULT 2568,
    is_overlap TINYINT(1) DEFAULT 0,
    category VARCHAR(255) NULL,
    location VARCHAR(255) NULL,
    inspector VARCHAR(100) NULL,
    price DECIMAL(15,2) NULL,
    image_url VARCHAR(500) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    source_file VARCHAR(255) NULL,
    source_sheet VARCHAR(255) NULL,
    source_row INT NULL,
    source_key VARCHAR(500) NULL,
    UNIQUE KEY uq_source_key (source_key(191)),
    INDEX idx_year_number (fiscal_year, item_number),
    INDEX idx_asset_code (asset_code),
    INDEX idx_asset_id_code (asset_id_code),
    INDEX idx_fiscal_year (fiscal_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: equipment_registry (ทะเบียนคุรุภัณฑ์)
CREATE TABLE IF NOT EXISTS equipment_registry (
    id INT AUTO_INCREMENT PRIMARY KEY,
    asset_type VARCHAR(255) NULL,
    acquisition_date_text TEXT NULL,
    source_row_end INT NULL,
    source_data LONGTEXT NULL,
    source_notes TEXT NULL,
    category VARCHAR(255) NULL COMMENT 'ประเภท',
    equipment_name VARCHAR(500) NOT NULL COMMENT 'ชื่อหรือชนิดครุภัณฑ์',
    equipment_code VARCHAR(100) NULL COMMENT 'เลขที่หรือรหัส',
    brand_description TEXT NULL COMMENT 'ยี่ห้อ ชนิด ขนาด และลักษณะ',
    serial_number VARCHAR(100) NULL COMMENT 'หมายเลข',
    unit_price DECIMAL(15,2) NULL COMMENT 'ราคาต่อหน่วย',
    acquisition_method VARCHAR(100) NULL COMMENT 'วิธีการได้มา',
    document_number VARCHAR(100) NULL COMMENT 'เลขที่เอกสาร',
    location VARCHAR(255) NULL COMMENT 'ใช้ประจำที่',
    receipt_evidence TEXT NULL COMMENT 'หลักฐานการจ่าย',
    change_details TEXT NULL COMMENT 'รายการเปลี่ยนแปลง',
    change_document VARCHAR(100) NULL COMMENT 'เลขที่เอกสารเปลี่ยนแปลง',
    remarks TEXT NULL COMMENT 'หมายเหตุ',
    acquisition_date DATE NULL,
    status ENUM('active','damaged','degraded','disposed','unused','unverified') DEFAULT 'unverified',
    image_url VARCHAR(500) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    source_file VARCHAR(255) NULL,
    source_sheet VARCHAR(255) NULL,
    source_row INT NULL,
    source_key VARCHAR(500) NULL,
    UNIQUE KEY uq_source_key (source_key(191)),
    INDEX idx_category_id (category, id),
    INDEX idx_equipment_code (equipment_code),
    INDEX idx_status (status),
    INDEX idx_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: users (เจ้าหน้าที่และผู้ใช้งานระบบ)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    fullname VARCHAR(255) NOT NULL,
    role VARCHAR(255) NOT NULL DEFAULT 'เจ้าหน้าที่พัสดุ',
    avatar VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS registry_source_sheets (
 source_file VARCHAR(255) NOT NULL, source_sheet VARCHAR(255) NOT NULL,
 category_name VARCHAR(255) NOT NULL, asset_type VARCHAR(255) NULL, equipment_name VARCHAR(255) NULL,
 item_count INT NOT NULL DEFAULT 0, source_header LONGTEXT NULL,
 PRIMARY KEY(source_file(100),source_sheet(100))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
