<?php
session_start();
require 'config.php';
date_default_timezone_set('Asia/Taipei');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: admin_dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
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
    $role = 'admin'; 

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
            $final_password = empty($password) ? $user['password'] : $password;
            $stmt = $pdo->prepare("
                UPDATE users 
                SET uID = ?, username = ?, nickname = ?, last_name = ?, first_name = ?, email = ?, phone = ?, password = ?, address = ?, role = ?
                WHERE id = ?
            ");
            $stmt->execute([$uID, $username, $nickname, $last_name, $first_name, $email, $phone, $final_password, $address, $role, $user_id]);
            $message = empty($password) ? "您的個人資料已更新。" : "您的個人資料已更新，新密碼為「{$password}」。";
            $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message, is_read, created_at) VALUES (?, ?, FALSE, NOW())");
            $stmt->execute([$user_id, $message]);
            $pdo->commit();

            header("Location: admin_dashboard.php?tab=profile");
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
    <title>編輯個人資料 - 管理員</title>
    <link rel="icon" type="image/x-icon" href="assest/favicon-32x32.ico">
    <link rel="apple-touch-icon" href="assest/favicon-128x128.ico">
    <link rel="icon" type="image/x-icon" href="assest/favicon-128x128.ico" sizes="128x128">
    <link rel="icon" type="image/x-icon" href="assest/favicon-256x256.ico" sizes="256x256">
    <link rel="ms-icon" href="assest/favicon-128x128.ico" sizes="128x128">
    <link rel="icon" type="image/x-icon" href="assest/favicon.ico">
    <!--  -->
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
    <div class="container">
        <h1>編輯個人資料</h1>
        <?php if (!empty($errors)) { ?>
            <div class="error">
                <?php foreach ($errors as $error) { ?>
                    <p><?php echo htmlspecialchars($error); ?></p>
                <?php } ?>
            </div>
        <?php } ?>
        <form method="POST">
            <label for="sID">學號（不可編輯）</label>
            <input type="text" name="sID" id="sID" value="<?php echo htmlspecialchars($user['sID']); ?>" readonly>

            <label for="uID">身分證字號</label>
            <input type="text" name="uID" id="uID" value="<?php echo htmlspecialchars($user['uID']); ?>" required>

            <label for="username">用戶名稱</label>
            <input type="text" name="username" id="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>

            <label for="nickname">暱稱（選填）</label>
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

            <label for="phone">電話（選填）</label>
            <input type="text" name="phone" id="phone" value="<?php echo htmlspecialchars($user['phone'] ?: ''); ?>">

            <label for="password">新密碼（若不更改則留空，長度至少8個字元）</label>
            <input type="password" name="password" id="password" placeholder="********">

            <label for="confirm_password">確認新密碼</label>
            <input type="password" name="confirm_password" id="confirm_password" placeholder="********">

            <label>地址（選填）</label>
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

            <label for="role">角色（不可編輯）</label>
            <input type="text" name="role" id="role" value="admin" readonly>

            <button type="submit" class="primary">保存</button>
            <button type="button" class="secondary" onclick="window.location.href='admin_dashboard.php?tab=profile'">取消</button>
        </form>
    </div>
</body>
</html>