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

if (isset($_SESSION['user_id'])) {
    header("Location: " . ($_SESSION['role'] == 'admin' ? 'admin_dashboard.php' : 'user_dashboard.php'));
    exit();
}

$errors = [];
$success_message = '';
$email_verified = false;

if (isset($_SESSION['email_verified']) && $_SESSION['email_verified'] === true && isset($_SESSION['email'])) {
    $email_verified = true;
    $email = $_SESSION['email'];
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['verify_email'])) {
    $email = trim($_POST['email']);
    if (empty($email)) {
        $errors[] = "請輸入電子郵件";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "請輸入有效的電子郵件地址";
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = "此電子郵件已被註冊";
        }
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
                $subject = "請驗證您的電子郵件地址";
                $verify_link = BASE_URL . "/verify_email.php?token=" . $token;
                $content = nl2br("親愛的用戶，您好！<br><br>感謝您註冊失物招領系統。<br>請點擊以下連結驗證您的電子郵件：<br><a href='{$verify_link}' style='word-break: break-all;'>點擊驗證</a><br><br>此連結將在 24 小時後過期。<br>此信件為系統自動發送，請勿回覆。");
                $mail->isHTML(true);
                $mail->Subject = mb_encode_mimeheader($subject, "UTF-8");
                $mail->Body = $content;
                $mail->AltBody = strip_tags(str_replace("<br>", "\n", $content));
                $mail->send();
                $success_message = "驗證連結已發送到您的電子郵件，請檢查收件箱（或垃圾郵件）並點擊連結以繼續註冊。";
                $_SESSION['pending_email'] = $email; 
            } catch (Exception $e) {
                throw new Exception("無法發送驗證郵件。錯誤: {$mail->ErrorInfo}");
            }
        } catch (Exception $e) {
            $errors[] = "操作失敗: " . htmlspecialchars($e->getMessage());
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['register']) && $email_verified) {
    $sID = trim($_POST['sID']);
    $uID = trim($_POST['uID']);
    $username = trim($_POST['username']);
    $nickname = trim($_POST['nickname']);
    $last_name = trim($_POST['last_name']);
    $first_name = trim($_POST['first_name']);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $country = trim($_POST['country']);
    $state = trim($_POST['state']);
    $city = trim($_POST['city']);
    $district = trim($_POST['district']);
    $address1 = trim($_POST['address1']);
    $address2 = trim($_POST['address2']);
    $role = 'user';

    if (empty($sID)) {
        $errors[] = "學號為必填欄位";
    }
    if (empty($uID)) {
        $errors[] = "身分證字號為必填欄位（10個字元）";
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
    if (!preg_match('/^[A-Z][0-9]{9}$/', $uID)) {
        $errors[] = "身分證字號格式不正確（應為大寫字母開頭後接9位數字）";
    }
    if (strlen($password) < 8) {
        $errors[] = "密碼長度至少為8個字元";
    }
    if ($password !== $confirm_password) {
        $errors[] = "密碼與確認密碼不一致";
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE sID = ?");
    $stmt->execute([$sID]);
    if ($stmt->fetchColumn() > 0) {
        $errors[] = "此學號已被註冊";
    }
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE uID = ?");
    $stmt->execute([$uID]);
    if ($stmt->fetchColumn() > 0) {
        $errors[] = "此身分證字號已被註冊";
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetchColumn() > 0) {
        $errors[] = "此用戶名稱已被使用";
    }
    $address_parts = array_filter([$country, $state, $city, $district, $address1, $address2]);
    $address = implode(', ', $address_parts);

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->query("SELECT MAX(id) as max_id FROM users");
            $row = $stmt->fetch();
            $new_id = $row['max_id'] ? $row['max_id'] + 1 : 1;
            $final_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                INSERT INTO users (id, sID, uID, username, nickname, last_name, first_name, email, email_verified, phone, password, address, role, approval_status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?, 'pending', NOW())
            ");
            $stmt->execute([$new_id, $sID, $uID, $username, $nickname, $last_name, $first_name, $email, $phone, $final_password, $address, $role]);
            $stmt = $pdo->prepare("SELECT id FROM users WHERE role = 'admin'");
            $stmt->execute();
            $admins = $stmt->fetchAll(PDO::FETCH_COLUMN);
            foreach ($admins as $admin_id) {
                $message = "新用戶註冊申請：用戶名 {$username}，學號 {$sID}，請審核。";
                $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message, is_read, created_at) VALUES (?, ?, FALSE, NOW())");
                $stmt->execute([$admin_id, $message]);
            }

            $pdo->commit();
            $success_message = "註冊申請已提交！請等待管理員審核。";
            unset($_SESSION['email_verified']);
            unset($_SESSION['email']);
            unset($_SESSION['pending_email']);
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "註冊失敗: " . htmlspecialchars($e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>註冊</title>
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
        <h1>失物招領系統 - 註冊</h1>
        <?php if (!empty($success_message)) { ?>
            <div class="success">
                <p><?php echo htmlspecialchars($success_message); ?></p>
                <p><a href="index.php">返回登入頁面</a></p>
            </div>
        <?php } elseif (!empty($errors)) { ?>
            <div class="error">
                <?php foreach ($errors as $error) { ?>
                    <p><?php echo htmlspecialchars($error); ?></p>
                <?php } ?>
            </div>
        <?php } ?>
        <?php if (empty($success_message)) { ?>
            <?php if (!$email_verified) { ?>
                <form method="POST">
                    <label for="email">電子郵件</label>
                    <input type="email" name="email" id="email" value="<?php echo isset($_SESSION['pending_email']) ? htmlspecialchars($_SESSION['pending_email']) : ''; ?>" required>
                    <button type="submit" name="verify_email" class="primary">發送驗證信</button>
                </form>
            <?php } else { ?>
                <form method="POST">
                    <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                    <label for="sID">學號</label>
                    <input type="text" name="sID" id="sID" maxlength="10" value="<?php echo isset($_POST['sID']) ? htmlspecialchars($_POST['sID']) : ''; ?>" required>

                    <label for="uID">身分證字號（大寫字母+9位數字）</label>
                    <input type="text" name="uID" id="uID" maxlength="10" value="<?php echo isset($_POST['uID']) ? htmlspecialchars($_POST['uID']) : ''; ?>" required>

                    <label for="username">用戶名稱</label>
                    <input type="text" name="username" id="username" maxlength="50" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>

                    <label for="nickname">暱稱（選填）</label>
                    <input type="text" name="nickname" id="nickname" maxlength="20" value="<?php echo isset($_POST['nickname']) ? htmlspecialchars($_POST['nickname']) : ''; ?>">

                    <div class="name-row">
                        <div class="name-field">
                            <label for="last_name">姓氏</label>
                            <input type="text" name="last_name" id="last_name" maxlength="20" value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>" required>
                        </div>
                        <div class="name-field">
                            <label for="first_name">名字</label>
                            <input type="text" name="first_name" id="first_name" maxlength="20" value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>" required>
                        </div>
                    </div>

                    <label for="phone">電話（選填）</label>
                    <input type="text" name="phone" id="phone" maxlength="20" value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">

                    <label for="password">密碼（至少8個字元）</label>
                    <input type="password" name="password" id="password" required>

                    <label for="confirm_password">確認密碼</label>
                    <input type="password" name="confirm_password" id="confirm_password" required>

                    <label>地址（選填）</label>
                    <div class="address-row">
                        <div class="address-field">
                            <label for="country">國家</label>
                            <input type="text" name="country" id="country" value="<?php echo isset($_POST['country']) ? htmlspecialchars($_POST['country']) : ''; ?>">
                        </div>
                        <div class="address-field">
                            <label for="state">州/省</label>
                            <input type="text" name="state" id="state" value="<?php echo isset($_POST['state']) ? htmlspecialchars($_POST['state']) : ''; ?>">
                        </div>
                        <div class="address-field">
                            <label for="city">市/縣</label>
                            <input type="text" name="city" id="city" value="<?php echo isset($_POST['city']) ? htmlspecialchars($_POST['city']) : ''; ?>">
                        </div>
                        <div class="address-field">
                            <label for="district">區</label>
                            <input type="text" name="district" id="district" value="<?php echo isset($_POST['district']) ? htmlspecialchars($_POST['district']) : ''; ?>">
                        </div>
                    </div>
                    <label for="address1">地址欄位1</label>
                    <input type="text" name="address1" id="address1" value="<?php echo isset($_POST['address1']) ? htmlspecialchars($_POST['address1']) : ''; ?>">
                    <label for="address2">地址欄位2</label>
                    <input type="text" name="address2" id="address2" value="<?php echo isset($_POST['address2']) ? htmlspecialchars($_POST['address2']) : ''; ?>">

                    <button type="submit" name="register" class="primary">提交註冊申請</button>
                </form>
            <?php } ?>
        <?php } ?>
        <p>已有帳號？<a href="index.php">點此登入</a></p>
    </div>
</body>
</html>