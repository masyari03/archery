<?php
// config/database.php - Koneksi Database PDO Aman untuk Target Archery
date_default_timezone_set('Asia/Jakarta');

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$dbname = getenv('DB_NAME') ?: 'archery';
$username = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : '';

try {
    $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
    $pdo = new PDO(
        $dsn,
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ]
    );
    $pdo->exec("SET time_zone = '+07:00'");
} catch (PDOException $e) {
    error_log('Target Archery DB Error: ' . $e->getMessage());
    http_response_code(500);
    die(json_encode([
        'status' => 'error',
        'message' => 'Layanan database sedang tidak tersedia. Silakan hubungi admin klub.'
    ]));
}

if (!function_exists('getDBConnection')) {
    function getDBConnection() {
        global $pdo;
        return $pdo;
    }
}
