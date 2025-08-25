<?php
session_start();
require 'config.php';
date_default_timezone_set('Asia/Taipei');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: admin_dashboard.php?tab=review_items");
    exit();
}

if (!isset($_GET['id'])) {
    header("Location: admin_dashboard.php?tab=review_items&error=無效的失物ID");
    exit();
}

$item_id = $_GET['id'];
$stmt = $pdo->prepare("SELECT li.*, u.username FROM lost_items li JOIN users u ON li.user_id = u.id WHERE li.id = ? AND li.approval_status = 'pending'");
$stmt->execute([$item_id]);
$item = $stmt->fetch();

if (!$item) {
    header("Location: admin_dashboard.php?tab=review_items&error=失物不存在或已處理");
    exit();
}

try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("UPDATE lost_items SET approval_status = 'rejected', rejected_at = NOW() WHERE id = ?");
    $stmt->execute([$item_id]);
    $message = "您的失物「{$item['title']}」申報已被拒絕，請在 24 小時後重新編輯或提交。";
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message, is_read, created_at) VALUES (?, ?, FALSE, NOW())");
    $stmt->execute([$item['user_id'], $message]);
    $pdo->commit();
    header("Location: admin_dashboard.php?tab=review_items&message=失物申報已拒絕");
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
        <a href="admin_dashboard.php?tab=review_items">返回</a>
    </div>
</body>
</html>
<?php
}
?>