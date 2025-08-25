<?php
session_start();
require 'config.php';
date_default_timezone_set('Asia/Taipei');
session_regenerate_id(true);

if (isset($_SESSION['user_id'])) {
    header("Location: " . ($_SESSION['role'] == 'admin' ? 'admin_dashboard.php' : 'user_dashboard.php'));
    exit();
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['identifier'])) {
    // require 'cf-turnstile.inc';
    // if (!$turnstileResult['success']) {
    //     $errors[] = $turnstileResult['error'];
    // }

    $identifier = trim($_POST['identifier']);
    $password = $_POST['password'];

    if (empty($identifier)) $errors[] = "請輸入識別欄位";
    if (empty($password)) $errors[] = "請輸入密碼";

    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ? OR phone = ? OR uID = ? OR sID = ?");
        $stmt->execute([$identifier, $identifier, $identifier, $identifier, $identifier]);
        $user = $stmt->fetch();

        if ($user) {
            if ($user['email_verified'] == 0) {
                $errors[] = "您的電子郵件尚未驗證，請檢查您的收件箱（或垃圾郵件）並完成驗證。";
            } elseif ($user['approval_status'] === 'pending') {
                $errors[] = "您的申請正在審核中，請稍後再試。";
            } elseif ($user['approval_status'] === 'rejected') {
                $errors[] = "您的申請已被拒絕，請聯繫管理員。";
            } elseif (password_verify($password, $user['password'])) {
                $new_session_id = session_id();
                if ($user['session_token'] && $user['session_token'] !== $new_session_id) {
                    $tw_now = new DateTime("now", new DateTimeZone("Asia/Taipei"));
                    $tw_time_str = $tw_now->format("Y-m-d H:i:s");
                    $device_info = $_SERVER['HTTP_USER_AGENT'] . ' | IP: ' . $_SERVER['REMOTE_ADDR'];
                    $message = "您的帳號於 $tw_time_str 在另一裝置上登入。";
                    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message, is_read, created_at) VALUES (?, ?, FALSE, NOW())");
                    $stmt->execute([$user['id'], $message]);
                    session_write_close();
                    session_id($user['session_token']);
                    session_start();
                    session_destroy();
                    session_write_close();
                    session_id($new_session_id);
                    session_start();
                }
                $device_info = $_SERVER['HTTP_USER_AGENT'] . ' | IP: ' . $_SERVER['REMOTE_ADDR'];
                $last_login_utc = date('Y-m-d H:i:s');
                $session_token = session_id();

                $stmt = $pdo->prepare("UPDATE users SET session_token = ?, last_login = ?, device_info = ? WHERE id = ?");
                $stmt->execute([$session_token, $last_login_utc, $device_info, $user['id']]);

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['session_token'] = $session_token;

                header("Location: " . ($user['role'] == 'admin' ? 'admin_dashboard.php' : 'user_dashboard.php'));
                exit();
            } else {
                $errors[] = "識別欄位或密碼錯誤";
            }
        } else {
            $errors[] = "識別欄位或密碼錯誤";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>登入</title>
    <link rel="icon" type="image/x-icon" href="assest/favicon-32x32.ico">
    <link rel="apple-touch-icon" href="assest/favicon-128x128.ico">
    <link rel="icon" type="image/x-icon" href="assest/favicon-128x128.ico" sizes="128x128">
    <link rel="icon" type="image/x-icon" href="assest/favicon-256x256.ico" sizes="256x256">
    <link rel="ms-icon" href="assest/favicon-128x128.ico" sizes="128x128">
    <link rel="icon" type="image/x-icon" href="assest/favicon.ico">
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="css/login.css">
</head>
<body>
    <div class="container">
        <h1>失物招領系統 - 登入</h1>
        <?php if (!empty($errors)) { ?>
            <div class="error">
                <?php foreach ($errors as $error) { ?>
                    <p><?php echo htmlspecialchars($error); ?></p>
                <?php } ?>
            </div>
        <?php } ?>
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" defer></script>
        <form method="POST">
            <label for="identifier">學號 / 使用者名稱 / 電子郵件 / 電話 / 身分證字號</label>
            <input type="text" name="identifier" id="identifier" required>
            <label for="password">密碼</label>
            <input type="password" name="password" id="password" required>
            <!-- <div class="cf-turnstile" data-sitekey="your web key"></div> -->
            <button type="submit">登入</button>
        </form>
        <p>尚未註冊？<a href="register.php">點此註冊</a></p>
        <p>忘記密碼？<a href="forgot_password.php">點此重設</a></p>
    </div>
</body>
</html>