<?php
/**
 * Database Configuration
 * Uses PDO for database connection with UTF-8 support
 * Includes automatic port and socket fallback for MAMP / standard MySQL
 */

$host = '127.0.0.1';
$dbname = 'sena_asset';
$username = 'root';
$password = 'root';
$charset = 'utf8mb4';

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

$pdo = null;

// Try MAMP socket & port 8889, then fallback to 3306
$dsn_list = [
    "mysql:host=localhost;port=8889;dbname=$dbname;charset=$charset;unix_socket=/Applications/MAMP/tmp/mysql/mysql.sock",
    "mysql:host=127.0.0.1;port=8889;dbname=$dbname;charset=$charset",
    "mysql:host=localhost;dbname=$dbname;charset=$charset;unix_socket=/Applications/MAMP/tmp/mysql/mysql.sock",
    "mysql:host=127.0.0.1;port=3306;dbname=$dbname;charset=$charset",
    "mysql:host=localhost;dbname=$dbname;charset=$charset"
];

$last_error = null;
foreach ($dsn_list as $dsn) {
    try {
        $pdo = new PDO($dsn, $username, $password, $options);
        break;
    } catch (\PDOException $e) {
        $last_error = $e;
    }
}

if (!$pdo) {
    // Attempt connecting without dbname and create db if needed
    try {
        $root_pdo = new PDO("mysql:host=127.0.0.1;port=8889;charset=$charset", $username, $password, $options);
        $root_pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo = new PDO("mysql:host=127.0.0.1;port=8889;dbname=$dbname;charset=$charset", $username, $password, $options);
    } catch (\PDOException $e) {
        // Log error
        error_log("Database connection failed: " . $e->getMessage());
    }
}

return $pdo;
