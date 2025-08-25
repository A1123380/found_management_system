<?php
session_start();
require 'config.php';
date_default_timezone_set('Asia/Taipei');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'user') {
    header("Location: user_dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

$errors = [];
$success_message = '';
$profile_email_verified = false;

if (isset($_SESSION['profile_email_verified']) && $_SESSION['profile_email_verified'] === true && isset($_SESSION['profile_email']) && $_SESSION['type'] === 'user') {
    $profile_email_verified = true;
    $profile_email = $_SESSION['profile_email'];
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['verify_email'])) {
    $email = trim($_POST['email']);

    if (empty($email)) {
        $errors[] = "請輸入電子郵件";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "請輸入有效的電子郵件地址";
    } elseif ($email !== $user['email']) {
        $errors[] = "電子郵件與您的帳號不符";
    }

    if (empty($errors)) {
        try {
            $token = bin2hex(random_bytes(32));
            $expires_at = date('Y-m-d H:i:s', strtotime('+24 hours'));
            $stmt = $pdo->prepare("INSERT INTO email_verifications (email, token, expires_at) VALUES (?, ?, ?)");
            $stmt->execute([$email, $token, $expires_at]);
            $mail = new PHPMailer(true);
            try {
                $mail->SMTPDebug = 0;
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'your email';
                $mail->Password = 'your password';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                $mail->Port = 465;
                $mail->CharSet = 'UTF-8';

                $mail->setFrom('no-reply@lostandfound.com', '失物招領系統');
                $mail->addAddress($email);

                $subject = "請驗證您的電子郵件以更新個人資料";
                $verify_link = BASE_URL . "/verify_email.php?token=" . $token . "&type=user";
                $content = nl2br("親愛的用戶，您好！<br><br>您正在嘗試更新個人資料。<br>請點擊以下連結驗證您的電子郵件：<br><a href='{$verify_link}' style='word-break: break-all;'>點擊驗證</a><br><br>此連結將在 24 小時後過期。<br>此信件為系統自動發送，請勿回覆。");

                $mail->isHTML(true);
                $mail->Subject = mb_encode_mimeheader($subject, "UTF-8");
                $mail->Body = $content;
                $mail->AltBody = strip_tags(str_replace("<br>", "\n", $content));

                $mail->send();
                $success_message = "驗證連結已發送到您的電子郵件，請檢查收件箱（或垃圾郵件）並點擊連結以繼續更新資料。";
                $_SESSION['pending_profile_email'] = $email;
            } catch (Exception $e) {
                throw new Exception("無法發送驗證郵件。錯誤: {$mail->ErrorInfo}");
            }
        } catch (Exception $e) {
            $errors[] = "操作失敗: " . htmlspecialchars($e->getMessage());
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile']) && $profile_email_verified) {
    $sID = trim($_POST['sID']);
    $uID = trim($_POST['uID']);
    $username = trim($_POST['username']);
    $nickname = trim($_POST['nickname']);
    $last_name = trim($_POST['last_name']);
    $first_name = trim($_POST['first_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $country = trim($_POST['country']);
    $state = trim($_POST['state']);
    $city = trim($_POST['city']);
    $district = trim($_POST['district']);
    $address1 = trim($_POST['address1']);
    $address2 = trim($_POST['address2']);

    if (empty($uID)) {
        $errors[] = "身分證字號為必填欄位";
    }
    if (empty($username)) {
        $errors[] = "用戶名稱為必填欄位";
    }
    if (empty($last_name)) {
        $errors[] = "姓氏為必填欄位";
    }
    if (empty($first_name)) {
        $errors[] = "名字為必填欄位";
    }
    if (empty($email)) {
        $errors[] = "電子郵件為必填欄位";
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "請輸入有效的電子郵件地址";
    }
    if (!preg_match('/^[A-Z][0-9]{9}$/', $uID)) {
        $errors[] = "身分證字號格式不正確（應為大寫字母開頭後接9位數字）";
    }
    if (!empty($password) && strlen($password) < 8) {
        $errors[] = "密碼長度至少為8個字元";
    }
    if ($password !== $confirm_password) {
        $errors[] = "密碼與確認密碼不一致";
    }
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE uID = ? AND id != ?");
    $stmt->execute([$uID, $user_id]);
    if ($stmt->fetchColumn() > 0) {
        $errors[] = "此身分證字號已被其他用戶使用";
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? AND id != ?");
    $stmt->execute([$username, $user_id]);
    if ($stmt->fetchColumn() > 0) {
        $errors[] = "此用戶名稱已被其他用戶使用";
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND id != ?");
    $stmt->execute([$email, $user_id]);
    if ($stmt->fetchColumn() > 0) {
        $errors[] = "此電子郵件已被其他用戶使用";
    }
    $address_parts = array_filter([$country, $state, $city, $district, $address1, $address2]);
    $address = implode(', ', $address_parts);

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            $final_password = empty($password) ? $user['password'] : password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                UPDATE users 
                SET uID = ?, username = ?, nickname = ?, last_name = ?, first_name = ?, email = ?, phone = ?, password = ?, address = ?
                WHERE id = ?
            ");
            $stmt->execute([$uID, $username, $nickname, $last_name, $first_name, $email, $phone, $final_password, $address, $user_id]);
            $message = empty($password) ? "您的個人資料已更新。" : "您的個人資料已更新，並已設定新密碼。";
            $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message, is_read, created_at) VALUES (?, ?, FALSE, NOW())");
            $stmt->execute([$user_id, $message]);
            $pdo->commit();
            unset($_SESSION['profile_email_verified']);
            unset($_SESSION['profile_email']);
            unset($_SESSION['pending_profile_email']);
            unset($_SESSION['type']);
            header("Location: user_dashboard.php?tab=profile");
            exit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = "更新失敗: " . htmlspecialchars($e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>編輯個人資料 - 用戶</title>
    <link rel="icon" type="image/x-icon" href="assest/favicon-32x32.ico">
    <link rel="apple-touch-icon" href="assest/favicon-128x128.ico">
    <link rel="icon" type="image/x-icon" href="assest/favicon-128x128.ico" sizes="128x128">
    <link rel="icon" type="image/x-icon" href="assest/favicon-256x256.ico" sizes="256x256">
    <link rel="ms-icon" href="assest/favicon-128x128.ico" sizes="128x128">
    <link rel="icon" type="image/x-icon" href="assest/favicon.ico">
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="css/register.css">
</head>
<body>
    <div class="container">
        <h1>編輯個人資料</h1>
        <?php if (!empty($success_message)) { ?>
            <div class="success">
                <p><?php echo htmlspecialchars($success_message); ?></p>
            </div>
        <?php } elseif (!empty($errors)) { ?>
            <div class="error">
                <?php foreach ($errors as $error) { ?>
                    <p><?php echo htmlspecialchars($error); ?></p>
                <?php } ?>
            </div>
        <?php } ?>
        <?php if (empty($success_message)) { ?>
            <?php if (!$profile_email_verified) { ?>
                <form method="POST">
                    <label for="email">請輸入您的電子郵件以進行驗證</label>
                    <input type="email" name="email" id="email" value="<?php echo isset($_SESSION['pending_profile_email']) ? htmlspecialchars($_SESSION['pending_profile_email']) : htmlspecialchars($user['email']); ?>" required>
                    <button type="submit" name="verify_email" class="primary">發送驗證信</button>
                    <button type="button" class="secondary" onclick="window.location.href='user_dashboard.php?tab=profile'">取消</button>
                </form>
            <?php } else { ?>
                <form method="POST">
                    <input type="hidden" name="email" value="<?php echo htmlspecialchars($profile_email); ?>">
                    <label for="sID">學號（不可編輯）</label>
                    <input type="text" name="sID" id="sID" value="<?php echo htmlspecialchars($user['sID']); ?>" readonly>

                    <label for="uID">身分證字號</label>
                    <input type="text" name="uID" id="uID" value="<?php echo htmlspecialchars($user['uID']); ?>" required>

                    <label for="username">用戶名稱</label>
                    <input type="text" name="username" id="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>

                    <label for="nickname">暱稱（選填） </label>
                    <input type="text" name="nickname" id="nickname" value="<?php echo htmlspecialchars($user['nickname'] ?: ''); ?>">

                    <div class="name-row">
                        <div class="name-field">
                            <label for="last_name">姓氏</label>
                            <input type="text" name="last_name" id="last_name" value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                        </div>
                        <div class="name-field">
                            <label for="first_name">名字</label>
                            <input type="text" name="first_name" id="first_name" value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                        </div>
                    </div>

                    <label for="email">電子郵件</label>
                    <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>

                    <label for="phone">電話（選填</label>）
                    <input type="text" name="phone" id="phone" value="<?php echo htmlspecialchars($user['phone'] ?: ''); ?>">

                    <label for="password">新密碼（若不更改則留空，長度至少8個字元）</label>
                    <input type="password" name="password" id="password" placeholder="********">

                    <label for="confirm_password">確認新密碼</label>
                    <input type="password" name="confirm_password" id="confirm_password" placeholder="********">

                    <label>地址（選填</label>）
                    <?php
                    $address_parts = $user['address'] ? explode(', ', $user['address']) : ['', '', '', '', '', ''];
                    $address_parts = array_pad($address_parts, 6, '');
                    ?>
                    <div class="address-row">
                        <div class="address-field">
                            <label for="country">國家</label>
                            <input type="text" name="country" id="country" value="<?php echo htmlspecialchars($address_parts[0]); ?>">
                        </div>
                        <div class="address-field">
                            <label for="state">州/省</label>
                            <input type="text" name="state" id="state" value="<?php echo htmlspecialchars($address_parts[1]); ?>">
                        </div>
                        <div class="address-field">
                            <label for="city">市/縣</label>
                            <input type="text" name="city" id="city" value="<?php echo htmlspecialchars($address_parts[2]); ?>">
                        </div>
                        <div class="address-field">
                            <label for="district">區</label>
                            <input type="text" name="district" id="district" value="<?php echo htmlspecialchars($address_parts[3]); ?>">
                        </div>
                    </div>
                    <label for="address1">地址欄位1</label>
                    <input type="text" name="address1" id="address1" value="<?php echo htmlspecialchars($address_parts[4]); ?>">
                    <label for="address2">地址欄位2</label>
                    <input type="text" name="address2" id="address2" value="<?php echo htmlspecialchars($address_parts[5]); ?>">

                    <button type="submit" name="update_profile" class="primary">保存</button>
                    <button type="button" class="secondary" onclick="window.location.href='user_dashboard.php?tab=profile'">取消</button>
                </form>
            <?php } ?>
        <?php } ?>
    </div>
</body>
</html>