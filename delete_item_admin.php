<?php
session_start();
require 'config.php';
date_default_timezone_set('Asia/Taipei');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: admin_dashboard.php");
    exit();
}

if (!isset($_GET['id'])) {
    header("Location: admin_dashboard.php?tab=manage_items");
    exit();
}

$item_id = $_GET['id'];
$stmt = $pdo->prepare("SELECT li.*, u.username FROM lost_items li JOIN users u ON li.user_id = u.id WHERE li.id = ?");
$stmt->execute([$item_id]);
$item = $stmt->fetch();

if (!$item) {
    header("Location: admin_dashboard.php?tab=manage_items");
    exit();
}

try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("DELETE FROM claims WHERE item_id = ?");
    $stmt->execute([$item_id]);
    $stmt = $pdo->prepare("DELETE FROM lost_items WHERE id = ?");
    $stmt->execute([$item_id]);
    $message = "您的失物「{$item['title']}」已被管理員刪除。";
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message, is_read, created_at) VALUES (?, ?, FALSE, NOW())");
    $stmt->execute([$item['user_id'], $message]);
    $pdo->commit();
    header("Location: admin_dashboard.php?tab=manage_items");
    exit();
} catch (PDOException $e) {
    $pdo->rollBack();
    echo "<p class='error'>刪除失敗: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>