<?php
/**
 * 校園失物招領管理系統配置範例文件
 * 請複製此文件為 config.php 並填入實際的配置值
 */

// 資料庫設定
$host = 'localhost';
$dbname = 'lost_and_found';
$username = 'your_username';
$password = 'your_password';

// 郵件服務設定 (SMTP)
$smtp_username = 'your_email@example.com';
$smtp_password = 'your_email_password';

// 應用程式設定
$base_url = 'http://localhost/';
$timezone = 'Asia/Taipei';

// 可選設定
// $google_maps_api_key = 'your_google_maps_api_key';
// $cloudflare_turnstile_site_key = 'your_turnstile_site_key';
// $cloudflare_turnstile_secret_key = 'your_turnstile_secret_key';

// 時區設定
date_default_timezone_set($timezone);

// 資料庫連線
try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+08:00'"
        ]
    );
} catch (PDOException $e) {
    logError("連線失敗: " . htmlspecialchars($e->getMessage()));
    die("連線失敗: 請聯繫系統管理員");
}

// 錯誤日誌函數
function logError($message) {
    error_log(date('[Y-m-d H:i:s] ') . $message);
}

// 定義常數
if (!defined('BASE_URL')) {
    define('BASE_URL', $base_url);
}
define('APP_ENV', 'production');
define('PAGE_SIZES', [5, 10, 20, 50]);
define('DEFAULT_PAGE_SIZE', 20);
define('UPLOAD_URL', BASE_URL . '/uploads/');
define('UPLOAD_DIR', __DIR__ . '/uploads/');
?>
