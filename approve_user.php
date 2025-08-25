<?php
session_start();
require 'config.php';
date_default_timezone_set('Asia/Taipei');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: admin_dashboard.php");
    exit();
}

if (!isset($_GET['id'])) {
    header("Location: admin_dashboard.php?tab=review_users");
    exit();
}

$user_id = $_GET['id'];

try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND approval_status = 'pending'");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if (!$user) {
        throw new Exception("用戶不存在或已審核");
    }
    $stmt = $pdo->prepare("UPDATE users SET approval_status = 'approved', updated_at = NOW() WHERE id = ?");
    $stmt->execute([$user_id]);
    $message = "您的註冊申請已通過，現在可以登入系統。";
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message, is_read, created_at) VALUES (?, ?, FALSE, NOW())");
    $stmt->execute([$user_id, $message]);
    $pdo->commit();

    header("Location: admin_dashboard.php?tab=review_users");
    exit();
} catch (Exception $e) {
    $pdo->rollBack();
    echo "<p class='error'>操作失敗: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><a href='admin_dashboard.php?tab=review_users'>返回</a></p>";
}
?>