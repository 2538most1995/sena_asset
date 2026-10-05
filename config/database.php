<?php
/**
 * config/database.php
 * SENA_Asset Database Configuration with Git-Safe Overrides
 * 
 * กลไกป้องกัน Git เขียนทับการตั้งค่าฐานข้อมูลบน Server:
 * 1. ตรวจสอบไฟล์ config/db_config.php ก่อนเสมอ (ไฟล์นี้อยู่ใน .gitignore จะไม่ถูก Git ทับ)
 * 2. หากไม่มีไฟล์ db_config.php จึงจะใช้ค่า Environment Variables หรือค่าเริ่มต้นของ MAMP
 * 3. สามารถบันทึกการตั้งค่าผ่านหน้าเว็บหรือคัดลอกจาก config/db_config.example.php ได้
 */

// 1. ตรวจสอบไฟล์ Override (ซึ่งอยู่ใน .gitignore ไม่ถูก git ทับ)
$custom_config = [];
$override_files = [
    __DIR__ . '/db_config.php',
    __DIR__ . '/database.local.php',
    __DIR__ . '/production.php'
];

foreach ($override_files as $f) {
    if (file_exists($f)) {
        $loaded = require $f;
        if (is_array($loaded)) {
            $custom_config = $loaded;
            break;
        }
    }
}

// 2. ดึงค่าการเชื่อมต่อ (ลำดับความสำคัญ: db_config.php > $_ENV > ค่าเริ่มต้น)
$host     = $custom_config['host'] ?? getenv('DB_HOST') ?: '127.0.0.1';
$dbname   = $custom_config['dbname'] ?? getenv('DB_NAME') ?: 'sena_asset';
$username = $custom_config['username'] ?? getenv('DB_USER') ?: 'root';
$password = $custom_config['password'] ?? getenv('DB_PASS') ?: 'root';
$port     = $custom_config['port'] ?? getenv('DB_PORT') ?: null;
$charset  = $custom_config['charset'] ?? 'utf8mb4';

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

$pdo = null;
$GLOBALS['db_connection_error'] = null;

// 3. สร้างรายการ DSN เพื่อทดสอบเชื่อมต่อ (รองรับทั้ง Server จริง และ Local MAMP)
$dsn_list = [];

// ถ้ากำหนด port มาชัดเจน
if (!empty($port)) {
    $dsn_list[] = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";
}

// แบบระบุ host ปกติ (Default port 3306)
$dsn_list[] = "mysql:host={$host};dbname={$dbname};charset={$charset}";

// ถ้า host เป็น localhost หรือ 127.0.0.1 ลอง socket และ port ทั่วไป
if (in_array($host, ['localhost', '127.0.0.1'])) {
    // Linux / cPanel / Plesk sockets
    $linux_sockets = [
        '/var/run/mysqld/mysqld.sock',
        '/var/lib/mysql/mysql.sock',
        '/tmp/mysql.sock'
    ];
    foreach ($linux_sockets as $sock) {
        if (file_exists($sock)) {
            $dsn_list[] = "mysql:host=localhost;dbname={$dbname};charset={$charset};unix_socket={$sock}";
        }
    }

    // MAMP Socket & Port 8889
    if (file_exists('/Applications/MAMP/tmp/mysql/mysql.sock')) {
        $dsn_list[] = "mysql:host=localhost;port=8889;dbname={$dbname};charset={$charset};unix_socket=/Applications/MAMP/tmp/mysql/mysql.sock";
    }
    $dsn_list[] = "mysql:host=127.0.0.1;port=8889;dbname={$dbname};charset={$charset}";
    $dsn_list[] = "mysql:host=127.0.0.1;port=3306;dbname={$dbname};charset={$charset}";
}

// ดำเนินการเชื่อมต่อ
$last_error = null;
foreach ($dsn_list as $dsn) {
    try {
        $pdo = new PDO($dsn, $username, $password, $options);
        break;
    } catch (\Throwable $e) {
        $last_error = $e;
    }
}

// หากยังไม่สามารถเชื่อมต่อได้ และมีสิทธิ์สร้างฐานข้อมูล (เช่น บน localhost)
if (!$pdo && in_array($host, ['localhost', '127.0.0.1'])) {
    try {
        $root_pdo = new PDO("mysql:host={$host};port=8889;charset={$charset}", $username, $password, $options);
        $root_pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbname}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo = new PDO("mysql:host={$host};port=8889;dbname={$dbname};charset={$charset}", $username, $password, $options);
    } catch (\Throwable $e) {
        $last_error = $e;
    }
}

if (!$pdo && $last_error) {
    $GLOBALS['db_connection_error'] = $last_error->getMessage();
    error_log("Database connection failed: " . $last_error->getMessage());
}

// 4. Auto-migration / ตรวจสอบความสมบูรณ์ของโครงสร้างตาราง
if ($pdo) {
    sena_ensure_schema_ready($pdo);
}

/**
 * ฟังก์ชันช่วยตรวจสอบและสร้างตารางเริ่มต้นอัตโนมัติ
 */
function sena_ensure_schema_ready(PDO $pdo) {
    static $ready = false;
    if ($ready) return;

    try {
        // ตรวจสอบว่ามีตาราง users หรือไม่
        $pdo->query("SELECT 1 FROM users LIMIT 1");
    } catch (\Throwable $t) {
        // หากไม่มีตาราง ให้สร้างตารางหลักที่จำเป็น
        try {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `asset_categories` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `category_name` VARCHAR(255) NOT NULL,
                    `category_code` VARCHAR(50) NULL,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

                CREATE TABLE IF NOT EXISTS `inspection_items` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `item_number` INT NULL,
                    `item_name` VARCHAR(500) NOT NULL,
                    `asset_code` VARCHAR(100) NULL,
                    `asset_id_code` VARCHAR(100) NULL,
                    `status_usable` TINYINT(1) DEFAULT 0,
                    `status_damaged` TINYINT(1) DEFAULT 0,
                    `status_degraded` TINYINT(1) DEFAULT 0,
                    `status_lost` TINYINT(1) DEFAULT 0,
                    `status_unused` TINYINT(1) DEFAULT 0,
                    `remarks` TEXT NULL,
                    `fiscal_year` INT DEFAULT 2568,
                    `is_overlap` TINYINT(1) DEFAULT 0,
                    `category` VARCHAR(255) NULL,
                    `location` VARCHAR(255) NULL,
                    `price` DECIMAL(15,2) NULL,
                    `image_url` VARCHAR(500) NULL,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_asset_code (`asset_code`),
                    INDEX idx_fiscal_year (`fiscal_year`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

                CREATE TABLE IF NOT EXISTS `equipment_registry` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `category` VARCHAR(255) NULL,
                    `equipment_name` VARCHAR(500) NOT NULL,
                    `equipment_code` VARCHAR(100) NULL,
                    `brand_description` TEXT NULL,
                    `serial_number` VARCHAR(100) NULL,
                    `unit_price` DECIMAL(15,2) NULL,
                    `acquisition_method` VARCHAR(100) NULL,
                    `document_number` VARCHAR(100) NULL,
                    `location` VARCHAR(255) NULL,
                    `receipt_evidence` TEXT NULL,
                    `change_details` TEXT NULL,
                    `change_document` VARCHAR(100) NULL,
                    `remarks` TEXT NULL,
                    `acquisition_date` DATE NULL,
                    `status` ENUM('active','damaged','degraded','disposed','unused') DEFAULT 'active',
                    `image_url` VARCHAR(500) NULL,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_equipment_code (`equipment_code`),
                    INDEX idx_status (`status`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

                CREATE TABLE IF NOT EXISTS `users` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `username` VARCHAR(100) NOT NULL UNIQUE,
                    `password` VARCHAR(255) NOT NULL,
                    `fullname` VARCHAR(255) NOT NULL,
                    `role` VARCHAR(255) NOT NULL DEFAULT 'เจ้าหน้าที่พัสดุ',
                    `avatar` VARCHAR(255) NULL,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_username (`username`),
                    INDEX idx_role (`role`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");

            // เพิ่มผู้ใช้งานเริ่มต้นถ้ายังไม่มี
            $cnt = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
            if ($cnt === 0) {
                $hash = password_hash('123456', PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, password, fullname, role, avatar) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute(['admin', $hash, 'ผู้ดูแลระบบ', 'ผู้ดูแลระบบ', 'ผ']);
                $stmt->execute(['officer1', $hash, 'เจ้าหน้าที่พัสดุ', 'เจ้าหน้าที่พัสดุ', 'จ']);
            }
        } catch (\Throwable $ex) {
            error_log("Auto schema creation error: " . $ex->getMessage());
        }
    }
    $ready = true;
}

return $pdo;
