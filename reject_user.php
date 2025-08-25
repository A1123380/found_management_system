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
    $stmt = $pdo->prepare("DELETE FROM notifications WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $stmt = $pdo->prepare("DELETE FROM lost_items WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $stmt = $pdo->prepare("DELETE FROM claims WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $pdo->commit();

    header("Location: admin_dashboard.php?tab=review_users");
    exit();
} catch (Exception $e) {
    $pdo->rollBack();
    echo "<p class='error'>操作失敗: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><a href='admin_dashboard.php?tab=review_users'>返回</a></p>";
}
?>