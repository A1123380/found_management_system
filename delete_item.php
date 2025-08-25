<?php
session_start();
require 'config.php';
date_default_timezone_set('Asia/Taipei');

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

if (!isset($_GET['id'])) {
    header("Location: user_dashboard.php");
    exit();
}

$item_id = $_GET['id'];
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM lost_items WHERE id = ? AND user_id = ?");
$stmt->execute([$item_id, $user_id]);
$item = $stmt->fetch();

if (!$item) {
    header("Location: user_dashboard.php");
    exit();
}
if ($item['item_type'] == 'lost_by_user' && $item['status'] == 'claimed' && $item['approval_status'] == 'approved') {
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>錯誤</title>
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
    <div class="container">
        <h1>刪除失敗</h1>
        <p class='error'>此遺失物品已找回且審核通過，無法刪除！</p>
        <button onclick="window.location.href='user_dashboard.php?tab=records'">返回</button>
    </div>
</body>
</html>
<?php
    exit();
}

try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("DELETE FROM claims WHERE item_id = ?");
    $stmt->execute([$item_id]);
    $stmt = $pdo->prepare("DELETE FROM lost_items WHERE id = ? AND user_id = ?");
    $stmt->execute([$item_id, $user_id]);
    $pdo->commit();
    header("Location: user_dashboard.php?tab=records");
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
        <h1>刪除失敗</h1>
        <p class='error'>錯誤訊息: <?php echo htmlspecialchars($e->getMessage()); ?></p>
        <button onclick="window.location.href='user_dashboard.php?tab=records'">返回</button>
    </div>
</body>
</html>
<?php
}
?>