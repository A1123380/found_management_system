<?php
session_start();
require 'config.php';
date_default_timezone_set('Asia/Taipei');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: admin_dashboard.php");
    exit();
}

if (!isset($_GET['id'])) {
    header("Location: admin_dashboard.php?tab=announcements");
    exit();
}

$announcement_id = $_GET['id'];

try {
    $stmt = $pdo->prepare("DELETE FROM announcements WHERE id = ?");
    $stmt->execute([$announcement_id]);

    header("Location: admin_dashboard.php?tab=announcements");
    exit();
} catch (PDOException $e) {
    echo "<p class='error'>刪除失敗: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><a href='admin_dashboard.php?tab=announcements'>返回</a></p>";
}
?>