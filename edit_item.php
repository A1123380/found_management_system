<?php
session_start();
require 'config.php';
date_default_timezone_set('Asia/Taipei');

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

if (!isset($_GET['id'])) {
    header("Location: " . ($role == 'admin' ? 'admin_dashboard.php' : 'user_dashboard.php'));
    exit();
}

$item_id = $_GET['id'];

$stmt = $pdo->prepare("SELECT * FROM lost_items WHERE id = ?");
$stmt->execute([$item_id]);
$item = $stmt->fetch();

if (!$item) {
    header("Location: " . ($role == 'admin' ? 'admin_dashboard.php' : 'user_dashboard.php'));
    exit();
}

if ($role == 'user' && $item['user_id'] != $user_id) {
    echo "<p class='error'>您無權編輯此失物！</p>";
    exit();
}

if ($item['status'] == 'claimed') {
    echo "<p class='error'>此失物已被領取，無法編輯！</p>";
    exit();
}

$errors = [];
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $location = trim($_POST['location']);
    $item_type = $_POST['item_type'];
    $image_path = $item['image'];

    if (empty($title)) {
        $errors[] = "標題為必填欄位";
    }
    if (empty($description)) {
        $errors[] = "描述為必填欄位";
    }
    if (empty($location)) {
        $errors[] = "請在地圖上標記地點";
    }
    if (!preg_match('/^-?\d+\.\d+,-?\d+\.\d+$/', $location)) {
        $errors[] = "地點格式無效，必須為經緯度（例如：22.6246,120.2818）";
    }
    if (!in_array($item_type, ['lost_by_user', 'found_by_user'])) {
        $errors[] = "無效的類型";
    }

    if (isset($_FILES['image']) && $_FILES['image']['error'] != UPLOAD_ERR_NO_FILE) {
        $allowed_types = ['image/jpeg', 'image/png'];
        $max_size = 20 * 1024 * 1024;
        $file = $_FILES['image'];

        if (!in_array($file['type'], $allowed_types)) {
            $errors[] = "僅支援 JPG 或 PNG 格式的圖片";
        } elseif ($file['size'] > $max_size) {
            $errors[] = "圖片大小不得超過 20MB";
        } elseif ($file['error'] != UPLOAD_ERR_OK) {
            $errors[] = "圖片上傳失敗，錯誤代碼：" . $file['error'];
        } else {
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $type_prefix = ($item_type == 'lost_by_user') ? 'lost' : 'found';
            $filename = "{$type_prefix}_{$user_id}_{$item_id}_" . time() . "_" . bin2hex(random_bytes(2)) . "." . $ext;
            $destination = UPLOAD_DIR . $filename;

            if (!is_dir(UPLOAD_DIR)) {
                mkdir(UPLOAD_DIR, 0755, true);
            }

            if (move_uploaded_file($file['tmp_name'], $destination)) {
                if ($image_path && file_exists(UPLOAD_DIR . $image_path)) {
                    unlink(UPLOAD_DIR . $image_path);
                }
                $image_path = $filename;
            } else {
                $errors[] = "圖片儲存失敗，請稍後再試";
            }
        }
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                UPDATE lost_items 
                SET title = ?, description = ?, location = ?, item_type = ?, approval_status = ?, image = ? 
                WHERE id = ?
            ");
            $approval_status = ($role == 'admin' ? $item['approval_status'] : 'pending');
            $stmt->execute([$title, $description, $location, $item_type, $approval_status, $image_path, $item_id]);

            if ($role == 'admin') {
                $message = "管理員已編輯您的失物「{$title}」。";
                $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message, is_read, created_at) VALUES (?, ?, FALSE, NOW())");
                $stmt->execute([$item['user_id'], $message]);
            }

            $pdo->commit();
            header("Location: " . ($role == 'admin' ? 'admin_dashboard.php?tab=manage_items&success=物品更新成功' : 'user_dashboard.php?success=物品更新成功'));
            exit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            if ($image_path && $image_path != $item['image'] && file_exists(UPLOAD_DIR . $image_path)) {
                unlink(UPLOAD_DIR . $image_path);
            }
            $errors[] = "更新失敗: " . htmlspecialchars($e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>編輯失物</title>
    <link rel="icon" type="image/x-icon" href="assest/favicon-32x32.ico">
    <link rel="apple-touch-icon" href="assest/favicon.ico">
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
        <h1>編輯失物</h1>
        <?php if (!empty($errors)) { ?>
            <div class="error">
                <?php foreach ($errors as $error) { ?>
                    <p><?php echo htmlspecialchars($error); ?></p>
                <?php } ?>
            </div>
        <?php } ?>
        <form method="POST" enctype="multipart/form-data">
            <label for="title">標題</label>
            <input type="text" name="title" id="title" value="<?php echo htmlspecialchars($item['title']); ?>" required>
            <label for="description">描述</label>
            <textarea name="description" id="description" required><?php echo htmlspecialchars($item['description']); ?></textarea>
            <label for="location">地點（請在地圖上點擊選擇）</label>
            <input type="text" id="location" name="location" value="<?php echo htmlspecialchars($item['location']); ?>" readonly required>
            <div id="map" class="map"></div>
            <label for="item_type">類型</label>
            <select id="item_type" name="item_type" required>
                <option value="lost_by_user" <?php echo $item['item_type'] == 'lost_by_user' ? 'selected' : ''; ?>>遺失物品</option>
                <option value="found_by_user" <?php echo $item['item_type'] == 'found_by_user' ? 'selected' : ''; ?>>撿到物品</option>
            </select>
            <label for="image">物品圖片（選填，支援 JPG/PNG，最大 2MB）</label>
<?php if ($item['image']) { ?>
    <div style="margin-bottom: 10px;">
        <p>當前圖片：<span data-image="<?php echo htmlspecialchars(UPLOAD_URL . $item['image']); ?>" style="cursor: pointer; color: #4299e1;">查看圖片</span></p>
        <?php
        $image_src = UPLOAD_URL . $item['image']; 
        ?>
    </div>
<?php } ?>
            <input type="file" id="image" name="image" accept="image/jpeg,image/png">
            <div id="image-preview" style="margin-top: 10px;"></div>
            <button type="submit" class="primary">保存</button>
            <button type="button" class="secondary" onclick="window.location.href='<?php echo $role == 'admin' ? 'admin_dashboard.php?tab=manage_items' : 'user_dashboard.php'; ?>'">取消</button>
        </form>
    </div>
    <script src="https://maps.googleapis.com/maps/api/js?key=your api key"></script>
    <script>
        function initMap() {
            const kaohsiungCenter = { lat: 22.6246, lng: 120.2818 };
            let initialLatLng = kaohsiungCenter;
            const locationValue = "<?php echo addslashes($item['location']); ?>";
            if (locationValue) {
                const [lat, lng] = locationValue.split(',').map(Number);
                if (!isNaN(lat) && !isNaN(lng)) {
                    initialLatLng = { lat, lng };
                }
            }

            const map = new google.maps.Map(document.getElementById("map"), {
                center: initialLatLng,
                zoom: 12,
                restriction: {
                    latLngBounds: {
                        north: 22.9,
                        south: 22.4,
                        east: 120.6,
                        west: 120.1
                    },
                    strictBounds: true
                }
            });

            let marker = new google.maps.Marker({
                position: initialLatLng,
                map: map,
                title: "失物地點"
            });

            map.addListener("click", (event) => {
                const lat = event.latLng.lat();
                const lng = event.latLng.lng();
                document.getElementById("location").value = `${lat},${lng}`;
                marker.setMap(null);
                marker = new google.maps.Marker({
                    position: { lat, lng },
                    map: map,
                    title: "失物地點"
                });
            });
        }

        document.getElementById('image').addEventListener('change', function(e) {
            const preview = document.getElementById('image-preview');
            preview.innerHTML = '';
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    img.style.maxWidth = '200px';
                    img.style.maxHeight = '200px';
                    preview.appendChild(img);
                };
                reader.readAsDataURL(file);
            }
        });

        window.onload = initMap;
    </script>
</body>
</html>