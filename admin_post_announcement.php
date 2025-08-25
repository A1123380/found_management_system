<?php
session_start();
require 'config.php';
date_default_timezone_set('Asia/Taipei');

if ($_SESSION['role'] != 'admin') {
    header("Location: admin_dashboard.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $content = $_POST['content'];
    $announcement_type = $_POST['announcement_type'];
    $admin_id = $_SESSION['user_id'];

    try {
        $stmt = $pdo->prepare("INSERT INTO announcements (admin_id, content, announcement_type, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$admin_id, $content, $announcement_type]);
        header("Location: admin_dashboard.php?tab=announcements");
        exit();
    } catch (PDOException $e) {
        echo "<p class='error'>無法發送公告: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
}
?>