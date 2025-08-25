<?php
session_start();
require 'config.php';
date_default_timezone_set('Asia/Taipei');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: admin_dashboard.php");
    exit();
}

if (!isset($_GET['id'])) {
    header("Location: admin_dashboard.php?tab=manage_claims");
    exit();
}

$claim_id = $_GET['id'];
$stmt = $pdo->prepare("
    SELECT c.*, li.user_id AS owner_id, li.title, li.id AS item_id, u.sID AS claimant_sID, owner.sID AS owner_sID
    FROM claims c
    JOIN lost_items li ON c.item_id = li.id
    JOIN users u ON c.user_id = u.id
    JOIN users owner ON li.user_id = owner.id
    WHERE c.id = ? AND c.status = 'pending'
");
$stmt->execute([$claim_id]);
$claim = $stmt->fetch();

if (!$claim) {
    header("Location: admin_dashboard.php?tab=manage_claims");
    exit();
}

try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("UPDATE claims SET status = 'approved', updated_at = NOW() WHERE id = ?");
    $stmt->execute([$claim_id]);
    $stmt = $pdo->prepare("
        UPDATE claims
        SET status = 'rejected', updated_at = NOW()
        WHERE item_id = ? AND status = 'pending' AND id != ?
    ");
    $stmt->execute([$claim['item_id'], $claim_id]);
    $stmt = $pdo->prepare("UPDATE lost_items SET status = 'claimed', ended_at = NOW() WHERE id = ?");
    $stmt->execute([$claim['item_id']]);
    $owner_message = "您的失物「{$claim['title']}」已被用戶（學號: {$claim['claimant_sID']}）領取。";
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message, is_read, created_at) VALUES (?, ?, FALSE, NOW())");
    $stmt->execute([$claim['owner_id'], $owner_message]);
    $claimer_message = "您領取的失物「{$claim['title']}」已通過審核，可以領取了！";
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message, is_read, created_at) VALUES (?, ?, FALSE, NOW())");
    $stmt->execute([$claim['user_id'], $claimer_message]);
    $stmt = $pdo->prepare("
        SELECT user_id
        FROM claims
        WHERE item_id = ? AND status = 'rejected' AND id != ? AND updated_at = NOW()
    ");
    $stmt->execute([$claim['item_id'], $claim_id]);
    $rejected_users = $stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($rejected_users as $rejected_user_id) {
        $stmt = $pdo->prepare("SELECT sID FROM users WHERE id = ?");
        $stmt->execute([$rejected_user_id]);
        $rejected_sID = $stmt->fetchColumn();
        $reject_message = "您對失物「{$claim['title']}」的領取申請已被拒絕，因為其他申請（學號: {$claim['claimant_sID']}）已通過。";
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message, is_read, created_at) VALUES (?, ?, FALSE, NOW())");
        $stmt->execute([$rejected_user_id, $reject_message]);
    }
    $pdo->commit();
    header("Location: admin_dashboard.php?tab=manage_claims");
    exit();
} catch (PDOException $e) {
    $pdo->rollBack();
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>錯誤</title>
    <link rel="icon" type="image/x-icon" href="assest/favicon-32x32.ico">
    <link rel="apple-touch-icon" href="assest/favicon-128x128.ico">
    <link rel="icon" type="image/x-icon" href="assest/favicon-128x128.ico" sizes="128x128">
    <link rel="icon" type="image/x-icon" href="assest/favicon-256x256.ico" sizes="256x256">
    <link rel="ms-icon" href="assest/favicon-128x128.ico" sizes="128x128">
    <link rel="icon" type="image/x-icon" href="assest/favicon.ico">
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
    <div class="container">
        <h1>審核失敗</h1>
        <p class="error">錯誤訊息: <?php echo htmlspecialchars($e->getMessage()); ?></p>
        <button onclick="window.location.href='admin_dashboard.php?tab=manage_claims'">返回</button>
    </div>
</body>
</html>
<?php
}
?>