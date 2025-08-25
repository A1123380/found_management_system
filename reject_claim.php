<?php
session_start();
require 'config.php';
date_default_timezone_set('Asia/Taipei');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: index.php");
    exit();
}

if (!isset($_GET['id'])) {
    header("Location: admin_dashboard.php?tab=manage_claims&error=無效的申請ID");
    exit();
}

$claim_id = $_GET['id'];
$tab = isset($_GET['tab']) && in_array($_GET['tab'], ['manage_claims']) ? $_GET['tab'] : 'manage_claims';

$stmt = $pdo->prepare("
    SELECT c.*, li.user_id AS owner_id, li.title, u.sID AS claimant_sID, owner.sID AS owner_sID
    FROM claims c
    JOIN lost_items li ON c.item_id = li.id
    JOIN users u ON c.user_id = u.id
    JOIN users owner ON li.user_id = owner.id
    WHERE c.id = ? AND c.status = 'pending'
");
$stmt->execute([$claim_id]);
$claim = $stmt->fetch();

if (!$claim) {
    header("Location: admin_dashboard.php?tab=manage_claims&error=申請不存在或已處理");
    exit();
}

try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("UPDATE claims SET status = 'rejected', updated_at = NOW(), rejected_at = NOW() WHERE id = ?");
    $stmt->execute([$claim_id]);
    $stmt = $pdo->prepare("UPDATE lost_items SET status = 'available' WHERE id = ?");
    $stmt->execute([$claim['item_id']]);
    $owner_message = "用戶（學號: {$claim['claimant_sID']}）對您的失物「{$claim['title']}」的領取申請已被拒絕。";
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message, is_read, created_at) VALUES (?, ?, FALSE, NOW())");
    $stmt->execute([$claim['owner_id'], $owner_message]);
    $claimer_message = "您對失物「{$claim['title']}」（ID: {$claim['item_id']}）的領取申請已被拒絕。請在 24 小時後重新申請。";
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message, is_read, created_at) VALUES (?, ?, FALSE, NOW())");
    $stmt->execute([$claim['user_id'], $claimer_message]);
    $pdo->commit();
    header("Location: admin_dashboard.php?tab=manage_claims&message=申請已拒絕");
    exit();
} catch (PDOException $e) {
    $pdo->rollBack();
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>拒絕失敗</title>
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
    <div class="container">
        <h1>拒絕失敗</h1>
        <p>錯誤訊息: <?php echo htmlspecialchars($e->getMessage()); ?></p>
        <a href="admin_dashboard.php?tab=manage_claims">返回</a>
    </div>
</body>
</html>
<?php
}
?>