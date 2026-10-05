CREATE DATABASE IF NOT EXISTS sena_asset CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sena_asset;

-- Table: asset_categories (หมวดหมู่ครุภัณฑ์)
CREATE TABLE IF NOT EXISTS asset_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(255) NOT NULL,
    category_code VARCHAR(50) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
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
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_asset_code (asset_code),
    INDEX idx_asset_id_code (asset_id_code),
    INDEX idx_fiscal_year (fiscal_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: equipment_registry (ทะเบียนคุรุภัณฑ์)
CREATE TABLE IF NOT EXISTS equipment_registry (
    id INT AUTO_INCREMENT PRIMARY KEY,
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
    status ENUM('active','damaged','degraded','disposed','unused') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_equipment_code (equipment_code),
    INDEX idx_status (status),
    INDEX idx_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
