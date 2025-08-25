<?php
session_start();
require 'config.php';
date_default_timezone_set('Asia/Taipei');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'user') {
    header("Location: user_dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];

if (!isset($_GET['item_id'])) {
    header("Location: user_dashboard.php?tab=home&error=無效的失物ID");
    exit();
}

$item_id = $_GET['item_id'];
$tab = isset($_GET['tab']) && in_array($_GET['tab'], ['home', 'public_items', 'user_items']) ? $_GET['tab'] : 'home';

try {
    $stmt = $pdo->prepare("
        SELECT li.*, u.sID AS owner_sID 
        FROM lost_items li 
        JOIN users u ON li.user_id = u.id 
        WHERE li.id = ? AND li.approval_status = 'approved' AND li.status = 'available' AND li.user_id != ?
    ");
    $stmt->execute([$item_id, $user_id]);
    $item = $stmt->fetch();

    if (!$item) {
        throw new Exception("失物不存在、不可申請（可能是自己的失物、未通過審核或已領取）");
    }
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM claims WHERE item_id = ? AND user_id = ? AND status = 'pending'");
    $stmt->execute([$item_id, $user_id]);
    if ($stmt->fetchColumn() > 0) {
        throw new Exception("您已申請此失物，請等待審核");
    }
    $stmt = $pdo->prepare("
        SELECT rejected_at 
        FROM claims 
        WHERE item_id = ? AND user_id = ? AND status = 'rejected' 
        ORDER BY rejected_at DESC LIMIT 1
    ");
    $stmt->execute([$item_id, $user_id]);
    $last_rejection = $stmt->fetch();

    if ($last_rejection && $last_rejection['rejected_at']) {
        $rejection_time = new DateTime($last_rejection['rejected_at']);
        $now = new DateTime();
        $interval = $rejection_time->diff($now);
        $hours = $interval->h + ($interval->days * 24);

        if ($hours < 24) {
            throw new Exception("您的申請被拒絕，請在 " . (24 - $hours) . " 小時後重新申請");
        }
    }

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $pdo->beginTransaction();）
        $stmt = $pdo->prepare("DELETE FROM claims WHERE item_id = ? AND user_id = ? AND status = 'rejected'");
        $stmt->execute([$item_id, $user_id]);
        $stmt = $pdo->prepare("INSERT INTO claims (item_id, user_id, status, created_at) VALUES (?, ?, 'pending', NOW())");
        $stmt->execute([$item_id, $user_id]);
        $stmt = $pdo->prepare("UPDATE lost_items SET status = 'pending' WHERE id = ?");
        $stmt->execute([$item_id]);
        $stmt = $pdo->prepare("SELECT sID FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $claimant_sID = $stmt->fetchColumn();
        $admin_stmt = $pdo->prepare("SELECT id FROM users WHERE role = 'admin'");
        $admin_stmt->execute();
        $admins = $admin_stmt->fetchAll(PDO::FETCH_COLUMN);
        foreach ($admins as $admin_id) {
            $message = "新失物申請待審核：{$item['title']}（申請者學號: {$claimant_sID}，失物ID: {$item_id}）";
            $pdo->prepare("INSERT INTO notifications (user_id, message, is_read, created_at) VALUES (?, ?, FALSE, NOW())")->execute([$admin_id, $message]);
        }
        $message = "您的失物「{$item['title']}」（ID: {$item_id}）有新的領取申請（申請者學號: {$claimant_sID}），待管理員審核。";
        $pdo->prepare("INSERT INTO notifications (user_id, message, is_read, created_at) VALUES (?, ?, FALSE, NOW())")->execute([$item['user_id'], $message]);

        $pdo->commit();
        header("Location: user_dashboard.php?tab=$tab&message=申請已提交");
        exit();
    }
} catch (Exception $e) {
    $error = htmlspecialchars($e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>申請領取失物</title>
    <link rel="icon" type="image/x-icon" href="assest/favicon-32x32.ico">
    <link rel="apple-touch-icon" href="assest/favicon-128x128.ico">
    <link rel="icon" type="image/x-icon" href="assest/favicon-128x128.ico" sizes="128x128">
    <link rel="icon" type="image/x-icon" href="assest/favicon-256x256.ico" sizes="256x256">
    <link rel="ms-icon" href="assest/favicon-128x128.ico" sizes="128x128">
    <link rel="icon" type="image/x-icon" href="assest/favicon.ico">
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="css/user_dashboard.css">
    <script src="js/tooltip.js"></script>
</head>
<body>
    <div class="container">
        <h1>申請領取失物</h1>
        <?php if (isset($error)) { ?>
            <p class="error"><?php echo $error; ?></p>
            <a href="user_dashboard.php?tab=<?php echo htmlspecialchars($tab); ?>">返回</a>
        <?php } else { ?>
            <h2><?php echo htmlspecialchars($item['title']); ?></h2>
            <p><strong>描述：</strong> <?php echo htmlspecialchars($item['description']); ?></p>
            <p><strong>地點：</strong> <?php echo htmlspecialchars($item['location']); ?></p>
            <p><strong>類型：</strong> <?php echo $item['item_type'] == 'lost_by_user' ? '遺失物品' : '撿到物品'; ?></p>
            <p><strong>提交者學號：</strong> <?php echo htmlspecialchars($item['owner_sID']); ?></p>
            <?php if ($item['image']) { ?>
                <p><strong>圖片：</strong> <span data-image="<?php echo htmlspecialchars(UPLOAD_URL . $item['image']); ?>" style="cursor: pointer; color: #4299e1;">查看圖片</span></p>
            <?php } ?>
            <form method="POST">
                <p>確認要申請此失物？提交後需等待管理員審核。</p>
                <button type="submit" class="primary">確認申請</button>
                <button type="button" class="secondary" onclick="window.location.href='user_dashboard.php?tab=<?php echo htmlspecialchars($tab); ?>'">取消</button>
            </form>
        <?php } ?>
    </div>
</body>
</html>