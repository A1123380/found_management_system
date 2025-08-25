<?php
session_start();
require 'config.php';
date_default_timezone_set('Asia/Taipei');

$errors = [];
$success_message = '';
$token = isset($_GET['token']) ? trim($_GET['token']) : '';

if (empty($token)) {
    $errors[] = "無效的驗證連結";
}

if (empty($errors)) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM email_verifications WHERE token = ? AND expires_at > NOW()");
        $stmt->execute([$token]);
        $verification = $stmt->fetch();

        if ($verification) {
            $email = $verification['email'];
            $type = isset($_GET['type']) ? $_GET['type'] : 'register';
            if ($type === 'user' || $type === 'admin') {
                $_SESSION['profile_email_verified'] = true;
                $_SESSION['profile_email'] = $email;
                $_SESSION['type'] = $type;
            } else {
                $_SESSION['email_verified'] = true;
                $_SESSION['email'] = $email;
            }
            $stmt = $pdo->prepare("DELETE FROM email_verifications WHERE token = ?");
            $stmt->execute([$token]);
            if ($type === 'user') {
                header("Location: user_profile.php");
                exit();
            } elseif ($type === 'admin') {
                header("Location: admin_profile.php");
                exit();
            } else {
                header("Location: register.php");
                exit();
            }
        } else {
            $errors[] = "驗證連結無效或已過期。請重新發送驗證信";
        }
    } catch (PDOException $e) {
        $errors[] = "操作失敗: " . htmlspecialchars($e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>電子郵件驗證</title>
    <link rel="icon" type="image/x-icon" href="assest/favicon-32x32.ico">
    <link rel="apple-touch-icon" href="assest/favicon-128x128.ico">
    <link rel="icon" type="image/x-icon" href="assest/favicon-128x128.ico" sizes="128x128">
    <link rel="icon" href="assest/favicon-256x256.ico" sizes="256x256">
    <link rel="ms-icon" href="assest/favicon-128x128.ico" sizes="128x128">
    <link rel="icon" type="image/x-icon" href="assest/favicon.ico">
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="css/register.css">
</head>
<body>
    <div class="container">
        <h1>失物招領系統 - 電子郵件驗證</h1>
        <?php if (!empty($errors)) { ?>
            <div class="error">
                <?php foreach ($errors as $error) { ?>
                    <p><?php echo htmlspecialchars($error); ?></p>
                <?php } ?>
                <p><a href="<?php echo isset($_GET['type']) && $_GET['type'] === 'user' ? 'user_profile.php' : (isset($_GET['type']) && $_GET['type'] === 'admin' ? 'admin_profile.php' : 'register.php'); ?>">返回</a></p>
            </div>
        <?php } ?>
    </div>
</body>
</html>