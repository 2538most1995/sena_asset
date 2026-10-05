<?php
/**
 * config/db_config.php
 * ตัวอย่างไฟล์ตั้งค่าฐานข้อมูลสำหรับเซิร์ฟเวอร์จริง (Production / Hosting / cPanel / Plesk)
 * 
 * วิธีใช้:
 * 1. คัดลอกไฟล์นี้เป็น config/db_config.php
 * 2. แก้ไขข้อมูล Host, Port, Database Name, Username, Password ให้ตรงกับเซิร์ฟเวอร์ของคุณ
 * 3. ไฟล์ config/db_config.php อยู่ใน .gitignore จึงจะไม่ถูก Git ดึงไปทับเมื่ออัปเดตระบบอีกต่อไป
 */

return [
    'host'     => 'localhost',     // เช่น localhost หรือ 127.0.0.1
    'port'     => '3306',          // พอร์ต MySQL มาตรฐานคือ 3306 (หรือ 8889 สำหรับ MAMP)
    'dbname'   => 'sena_asset',    // ชื่อฐานข้อมูลบนโฮสติ้ง เช่น krumostc_sena_asset
    'username' => 'root',          // ชื่อผู้ใช้ฐานข้อมูล
    'password' => '',              // รหัสผ่านฐานข้อมูล
    'charset'  => 'utf8mb4'
];
