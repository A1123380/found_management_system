<?php
session_start();
require 'config.php';
date_default_timezone_set('Asia/Taipei');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: admin_dashboard.php");
    exit();
}

if (!isset($_GET['id'])) {
    header("Location: admin_dashboard.php?tab=review_items");
    exit();
}

$item_id = $_GET['id'];

$stmt = $pdo->prepare("SELECT li.*, u.username FROM lost_items li JOIN users u ON li.user_id = u.id WHERE li.id = ? AND li.approval_status = 'pending'");
$stmt->execute([$item_id]);
$item = $stmt->fetch();

if (!$item) {
    header("Location: admin_dashboard.php?tab=review_items");
    exit();
}

try {
    $pdo->beginTransaction();
    $location = $item['location'];
    $default_location = '22.6246,120.2818'; 
    if (!preg_match('/^-?\d+\.\d+,-?\d+\.\d+$/', $location)) {
        error_log("無效的 location 格式: item_id=$item_id, title={$item['title']}, location=$location");
        $stmt = $pdo->prepare("UPDATE lost_items SET location = ? WHERE id = ?");
        $stmt->execute([$default_location, $item_id]);
        $location = $default_location;
    }
    $stmt = $pdo->prepare("UPDATE lost_items SET approval_status = 'approved', approved_at = NOW() WHERE id = ?");
    $stmt->execute([$item_id]);
    $message = "您的失物「{$item['title']}」申報已通過審核，現已上架。";
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message, is_read, created_at) VALUES (?, ?, FALSE, NOW())");
    $stmt->execute([$item['user_id'], $message]);
    $pdo->commit();
    $tab = $_GET['tab'] ?? 'review_items';
    header("Location: admin_dashboard.php?tab=$tab");
    exit();
} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("審核失物失敗: item_id=$item_id, error=" . $e->getMessage());
    echo "<p class='error'>審核失敗，請聯繫系統管理員。錯誤訊息已記錄。</p>";
    echo "<p><a href='admin_dashboard.php?tab=review_items'>返回</a></p>";
}
?>