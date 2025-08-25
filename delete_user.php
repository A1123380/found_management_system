<?php
session_start();
require 'config.php';
date_default_timezone_set('Asia/Taipei');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: admin_dashboard.php");
    exit();
}

if (!isset($_GET['id'])) {
    header("Location: admin_dashboard.php?tab=manage_users");
    exit();
}

$user_id = $_GET['id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    header("Location: admin_dashboard.php?tab=manage_users");
    exit();
}

try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("DELETE FROM claims WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $stmt = $pdo->prepare("DELETE FROM lost_items WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $pdo->commit();
    header("Location: admin_dashboard.php?tab=manage_users");
    exit();
} catch (PDOException $e) {
    $pdo->rollBack();
    echo "<p class='error'>刪除失敗: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>