<?php
session_start();
require_once 'config.php';
require_once 'admin_functions.php';
date_default_timezone_set('Asia/Taipei');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['session_token'])) {
    header("Location: index.php");
    exit();
}

$stmt = $pdo->prepare("SELECT session_token FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user || $user['session_token'] !== session_id()) {
    session_destroy();
    header("Location: index.php");
    exit();
}

$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];
if ($role != 'admin') {
    header("Location: user_dashboard.php");
    exit();
}

if (isset($_GET['action']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    try {
        if ($_GET['action'] === 'mark_all_notifications_read') {
            $stmt = $pdo->prepare("UPDATE notifications SET is_read = TRUE WHERE user_id = ? AND is_read = FALSE");
            $stmt->execute([$user_id]);
            $unread_count = $pdo->query("SELECT COUNT(*) FROM notifications WHERE user_id = $user_id AND is_read = FALSE")->fetchColumn();
            echo json_encode([
                'success' => true,
                'message' => '已將 ' . $stmt->rowCount() . ' 條通知標記為已讀',
                'unread_count' => $unread_count
            ]);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => '標記失敗: ' . $e->getMessage()]);
    }
    exit();
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
$pending_items_count = $pdo->query("SELECT COUNT(*) FROM lost_items WHERE approval_status = 'pending'")->fetchColumn();
$pending_claims_count = $pdo->query("SELECT COUNT(*) FROM claims WHERE status = 'pending'")->fetchColumn();
$pending_users_count = $pdo->query("SELECT COUNT(*) FROM users WHERE approval_status = 'pending'")->fetchColumn();
$unread_stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = FALSE");
$unread_stmt->execute([$user_id]);
$unread_count = $unread_stmt->fetchColumn();
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'manage_items';
$tabs = [
    'manage_items' => [
        'title' => '管理失物',
        'sql' => "SELECT li.*, u.username FROM lost_items li LEFT JOIN users u ON li.user_id = u.id",
        'filters' => [
            ['name' => 'item_type', 'label' => '類型', 'type' => 'select', 'options' => ['' => '所有類型', 'lost_by_user' => '遺失物品', 'found_by_user' => '撿到物品'], 'condition' => 'li.item_type = ?', 'placeholder' => ''],
            ['name' => 'status', 'label' => '狀態', 'type' => 'select', 'options' => ['' => '所有狀態', 'claimed' => '已找回/已歸還', 'unclaimed' => '尚未認領/待領回'], 'condition' => 'li.status = ?', 'placeholder' => ''],
            ['name' => 'approval_status', 'label' => '審核狀態', 'type' => 'select', 'options' => ['' => '所有審核狀態', 'approved' => '已通過', 'pending' => '待審核', 'rejected' => '已拒絕'], 'condition' => 'li.approval_status = ?', 'placeholder' => ''],
            ['name' => 'search', 'label' => '搜尋', 'type' => 'text', 'condition' => '(li.id LIKE ? OR li.title LIKE ? OR li.description LIKE ? OR li.location LIKE ? OR u.username LIKE ?)', 'placeholder' => '搜尋...'],
        ],
        'columns' => [
            ['label' => '編號', 'key' => 'id', 'sort' => 'id'],
            ['label' => '類型', 'class' => 'status', 'class_callback' => fn($item) => 'status-' . $item['item_type'], 'value' => fn($item) => $item['item_type'] == 'lost_by_user' ? '遺失物品' : '撿到物品', 'sort' => 'item_type'],
            ['label' => '狀態', 'class' => 'status', 'class_callback' => fn($item) => 'status-' . ($item['status'] == 'claimed' ? 'claimed' : 'unclaimed'), 'value' => fn($item) => $item['status'] == 'claimed' ? ($item['item_type'] == 'lost_by_user' ? '已找回' : '已歸還') : ($item['item_type'] == 'lost_by_user' ? '尚未認領' : '待領回'), 'sort' => 'status'],
            ['label' => '審核狀態', 'class' => 'approval-status', 'class_callback' => fn($item) => 'approval-' . $item['approval_status'], 'value' => fn($item) => $item['approval_status'] == 'pending' ? '待審核' : ($item['approval_status'] == 'approved' ? '已通過' : '已拒絕'), 'sort' => 'approval_status'],
            ['label' => '標題', 'key' => 'title', 'sort' => 'title', 'value' => fn($item) => '<span data-image="' . ($item['image'] ? htmlspecialchars(UPLOAD_URL . $item['image']) : 'assest/placeholder.jpg') . '" style="cursor: pointer;">' . htmlspecialchars($item['title']) . '</span>'],
            ['label' => '描述', 'key' => 'description', 'sort' => 'description'],
            ['label' => '地點', 'key' => 'location', 'sort' => 'location'],
            ['label' => '提交者', 'key' => 'username', 'sort' => 'username'],
            ['label' => '申請時間', 'key' => 'created_at', 'sort' => 'created_at'],
            ['label' => '修改時間', 'key' => 'updated_at', 'default' => '未修改', 'sort' => 'updated_at'],
            ['label' => '通過時間', 'key' => 'approved_at', 'default' => '未通過', 'sort' => 'approved_at'],
            ['label' => '結束時間', 'key' => 'ended_at', 'default' => '未結束', 'sort' => 'ended_at'],
        ],
        'actionLogic' => function($item, $user_id, $claimed_items) {
            return '<button ' . ($item['status'] == 'claimed' ? 'disabled' : 'onclick="window.location.href=\'edit_item.php?id=' . ($item['id'] ?? '') . '\'"') . '>編輯</button>
                    <button class="delete" onclick="if(confirm(\'確定要刪除？\')) window.location.href=\'delete_item_admin.php?id=' . ($item['id'] ?? '') . '\'">刪除</button>';
        },
        'queryParams' => [
            'tab' => 'manage_items',
            'page' => $_GET['page'] ?? 1,
            'per_page' => $_GET['per_page'] ?? DEFAULT_PAGE_SIZE,
            'item_type' => $_GET['item_type'] ?? '',
            'status' => $_GET['status'] ?? '',
            'approval_status' => $_GET['approval_status'] ?? '',
            'search' => $_GET['search'] ?? '',
            'sort_column' => $_GET['sort_column'] ?? 'id',
            'sort_order' => $_GET['sort_order'] ?? 'ASC',
            'page_param' => 'page',
            'per_page_param' => 'per_page',
            'sort_column_param' => 'sort_column',
            'sort_order_param' => 'sort_order',
        ],
    ],
    'review_items' => [
        'title' => '審核申報',
        'sql' => "SELECT li.*, u.username FROM lost_items li LEFT JOIN users u ON li.user_id = u.id WHERE li.approval_status = 'pending'",
        'filters' => [
            ['name' => 'item_type', 'label' => '類型', 'type' => 'select', 'options' => ['' => '所有類型', 'lost_by_user' => '遺失物品', 'found_by_user' => '撿到物品'], 'condition' => 'li.item_type = ?', 'placeholder' => ''],
            ['name' => 'search', 'label' => '搜尋', 'type' => 'text', 'condition' => '(li.id LIKE ? OR li.title LIKE ? OR li.description LIKE ? OR li.location LIKE ? OR u.username LIKE ?)', 'placeholder' => '搜尋...'],
        ],
        'columns' => [
            ['label' => '編號', 'key' => 'id', 'sort' => 'id'],
            ['label' => '類型', 'class' => 'status', 'class_callback' => fn($item) => 'status-' . $item['item_type'], 'value' => fn($item) => $item['item_type'] == 'lost_by_user' ? '遺失物品' : '撿到物品', 'sort' => 'item_type'],
            ['label' => '標題', 'key' => 'title', 'sort' => 'title', 'value' => fn($item) => '<span data-image="' . ($item['image'] ? htmlspecialchars(UPLOAD_URL . $item['image']) : 'assest/placeholder.jpg') . '" style="cursor: pointer;">' . htmlspecialchars($item['title']) . '</span>'],
            ['label' => '描述', 'key' => 'description', 'sort' => 'description'],
            ['label' => '地點', 'key' => 'location', 'sort' => 'location'],
            ['label' => '提交者', 'key' => 'username', 'sort' => 'username'],
            ['label' => '申請時間', 'key' => 'created_at', 'sort' => 'created_at'],
            ['label' => '修改時間', 'key' => 'updated_at', 'default' => '未修改', 'sort' => 'updated_at'],
            ['label' => '通過時間', 'key' => 'approved_at', 'default' => '未通過', 'sort' => 'approved_at'],
            ['label' => '結束時間', 'key' => 'ended_at', 'default' => '未結束', 'sort' => 'ended_at'],
        ],
        'actionLogic' => function($item, $user_id, $claimed_items) {
            return '<button onclick="window.location.href=\'approve_item.php?id=' . $item['id'] . '&tab=review_items\'">通過</button>
                    <button class="delete" onclick="if(confirm(\'確定要拒絕？\')) window.location.href=\'reject_item.php?id=' . $item['id'] . '&tab=review_items\'">拒絕</button>';
        },
        'queryParams' => [
            'tab' => 'review_items',
            'page' => $_GET['page'] ?? 1,
            'per_page' => $_GET['per_page'] ?? DEFAULT_PAGE_SIZE,
            'item_type' => $_GET['item_type'] ?? '',
            'search' => $_GET['search'] ?? '',
            'sort_column' => $_GET['sort_column'] ?? 'id',
            'sort_order' => $_GET['sort_order'] ?? 'ASC',
            'page_param' => 'page',
            'per_page_param' => 'per_page',
            'sort_column_param' => 'sort_column',
            'sort_order_param' => 'sort_order',
        ],
    ],
    'manage_claims' => [
        'title' => '管理申請',
        'sql' => "SELECT c.*, li.title, li.image, u1.sID AS claimant_sID, u1.username AS claimant, u2.sID AS owner_sID, u2.username AS owner FROM claims c JOIN lost_items li ON c.item_id = li.id JOIN users u1 ON c.user_id = u1.id JOIN users u2 ON li.user_id = u2.id WHERE c.status = 'pending'",
        'filters' => [
            ['name' => 'search', 'label' => '搜尋', 'type' => 'text', 'condition' => '(c.id LIKE ? OR li.title LIKE ? OR u1.sID LIKE ? OR u1.username LIKE ? OR u2.sID LIKE ? OR u2.username LIKE ?)', 'placeholder' => '搜尋...'],
        ],
        'columns' => [
            ['label' => '編號', 'key' => 'id', 'sort' => 'id'],
            ['label' => '失物標題', 'key' => 'title', 'sort' => 'title', 'value' => fn($item) => '<span data-image="' . ($item['image'] ? htmlspecialchars(UPLOAD_URL . $item['image']) : 'assest/placeholder.jpg') . '" style="cursor: pointer;">' . htmlspecialchars($item['title']) . '</span>'],
            ['label' => '申請者學號', 'key' => 'claimant_sID', 'sort' => 'claimant_sID'],
            ['label' => '申請者', 'key' => 'claimant', 'sort' => 'claimant'],
            ['label' => '失主學號', 'key' => 'owner_sID', 'sort' => 'owner_sID'],
            ['label' => '失主', 'key' => 'owner', 'sort' => 'owner'],
            ['label' => '申請時間', 'key' => 'created_at', 'sort' => 'created_at'],
        ],
        'actionLogic' => function($item, $user_id, $claimed_items) {
            return '<button onclick="window.location.href=\'approve_claim.php?id=' . $item['id'] . '\'">通過</button>
                    <button class="delete" onclick="if(confirm(\'確定要拒絕？\')) window.location.href=\'reject_claim.php?id=' . $item['id'] . '\'">拒絕</button>';
        },
        'queryParams' => [
            'tab' => 'manage_claims',
            'page' => $_GET['page'] ?? 1,
            'per_page' => $_GET['per_page'] ?? DEFAULT_PAGE_SIZE,
            'search' => $_GET['search'] ?? '',
            'sort_column' => $_GET['sort_column'] ?? 'id',
            'sort_order' => $_GET['sort_order'] ?? 'ASC',
            'page_param' => 'page',
            'per_page_param' => 'per_page',
            'sort_column_param' => 'sort_column',
            'sort_order_param' => 'sort_order',
        ],
    ],
    'claim_records' => [
        'title' => '領取紀錄',
        'sql' => "SELECT c.item_id, c.updated_at AS claimed_at, li.title, li.image, u1.sID AS claimant_sID, u1.username AS claimant, u2.sID AS owner_sID, u2.username AS owner FROM claims c JOIN lost_items li ON c.item_id = li.id JOIN users u1 ON c.user_id = u1.id JOIN users u2 ON li.user_id = u2.id WHERE c.status = 'approved'",
        'filters' => [
            ['name' => 'search', 'label' => '搜尋', 'type' => 'text', 'condition' => '(c.item_id LIKE ? OR li.title LIKE ? OR u1.sID LIKE ? OR u1.username LIKE ? OR u2.sID LIKE ? OR u2.username LIKE ?)', 'placeholder' => ''],
        ],
        'columns' => [
            ['label' => '編號', 'key' => 'item_id', 'sort' => 'item_id'],
            ['label' => '失物標題', 'key' => 'title', 'sort' => 'title', 'value' => fn($item) => '<span data-image="' . ($item['image'] ? htmlspecialchars(UPLOAD_URL . $item['image']) : 'assest/placeholder.jpg') . '" style="cursor: pointer;">' . htmlspecialchars($item['title']) . '</span>'],
            ['label' => '拾獲者學號', 'key' => 'owner_sID', 'sort' => 'owner_sID'],
            ['label' => '拾獲者', 'key' => 'owner', 'sort' => 'owner'],
            ['label' => '領取者學號', 'key' => 'claimant_sID', 'sort' => 'claimant_sID'],
            ['label' => '領取者', 'key' => 'claimant', 'sort' => 'claimant'],
            ['label' => '領取時間', 'key' => 'claimed_at', 'sort' => 'claimed_at'],
        ],
        'actionLogic' => function($item, $user_id, $claimed_items) {
            return '<button disabled>已結束</button>';
        },
        'queryParams' => [
            'tab' => 'claim_records',
            'page' => $_GET['page'] ?? 1,
            'per_page' => $_GET['per_page'] ?? DEFAULT_PAGE_SIZE,
            'search' => $_GET['search'] ?? '',
            'sort_column' => $_GET['sort_column'] ?? 'item_id',
            'sort_order' => $_GET['sort_order'] ?? 'ASC',
            'page_param' => 'page',
            'per_page_param' => 'per_page',
            'sort_column_param' => 'sort_column',
            'sort_order_param' => 'sort_order',
        ],
    ],
    'review_users' => [
        'title' => '審核註冊',
        'sql' => "SELECT * FROM users WHERE approval_status = 'pending'",
        'filters' => [
            ['name' => 'search', 'label' => '搜尋', 'type' => 'text', 'condition' => '(sID LIKE ? OR uID LIKE ? OR username LIKE ? OR last_name LIKE ? OR first_name LIKE ? OR email LIKE ? OR phone LIKE ? OR address LIKE ?)', 'placeholder' => ''],
        ],
        'columns' => [
            ['label' => '學號', 'key' => 'sID', 'sort' => 'sID'],
            ['label' => '身分證字號', 'key' => 'uID', 'sort' => 'uID'],
            ['label' => '用戶名稱', 'key' => 'username', 'sort' => 'username'],
            ['label' => '姓氏', 'key' => 'last_name', 'sort' => 'last_name'],
            ['label' => '名字', 'key' => 'first_name', 'sort' => 'first_name'],
            ['label' => '電子郵件', 'key' => 'email', 'sort' => 'email'],
            ['label' => '電話', 'default' => '未設定', 'key' => 'phone', 'sort' => 'phone'],
            ['label' => '地址', 'default' => '住所', 'key' => 'address', 'sort' => 'address'],
            ['label' => '申請時間', 'key' => 'created_at', 'sort' => 'created_at'],
        ],
        'actionLogic' => function($item, $user_id, $claimed_items) {
            return '<button onclick="window.location.href=\'approve_user.php?id=' . $item['id'] . '\'">通過</button>
                    <button class="delete" onclick="if(confirm(\'拒絕用戶將刪除其所有通知，確定要拒絕？\')) window.location.href=\'reject_user.php?id=' . $item['id'] . '\'">拒絕</button>';
        },
        'queryParams' => [
            'tab' => 'review_users',
            'page' => $_GET['page'] ?? 1,
            'per_page' => $_GET['per_page'] ?? DEFAULT_PAGE_SIZE,
            'search' => $_GET['search'] ?? '',
            'sort_column' => $_GET['sort_column'] ?? 'created_at',
            'sort_order' => $_GET['sort_order'] ?? 'ASC',
            'page_param' => 'page',
            'per_page_param' => 'per_page',
            'sort_column_param' => 'sort_column',
            'sort_order_param' => 'sort_order',
        ],
    ],
    'manage_users' => [
        'title' => '管理用戶',
        'sql' => "SELECT * FROM users WHERE approval_status = 'approved'",
        'filters' => [
            ['name' => 'search', 'label' => '搜尋', 'type' => 'text', 'condition' => '(sID LIKE ? OR uID LIKE ? OR username LIKE ? OR nickname LIKE ? OR last_name LIKE ? OR first_name LIKE ? OR email LIKE ? OR phone LIKE ? OR address LIKE ?)', 'placeholder' => '搜尋...'],
            ['name' => 'role', 'label' => '角色', 'type' => 'select', 'options' => ['' => '所有角色', 'user' => '一般用戶', 'admin' => '管理員'], 'condition' => 'role = ?', 'placeholder' => ''],
        ],
        'columns' => [
            ['label' => '學號', 'key' => 'sID', 'sort' => 'sID'],
            ['label' => '身分證字號', 'key' => 'uID', 'sort' => 'uID'],
            ['label' => '使用者名稱', 'key' => 'username', 'sort' => 'username'],
            ['label' => '暱稱', 'key' => 'nickname', 'default' => '未設定', 'sort' => 'nickname'],
            ['label' => '姓氏', 'key' => 'last_name', 'sort' => 'last_name'],
            ['label' => '名字', 'key' => 'first_name', 'sort' => 'first_name'],
            ['label' => '電子郵件', 'key' => 'email', 'sort' => 'email'],
            ['label' => '電話', 'key' => 'phone', 'default' => '未設定', 'sort' => 'phone'],
            ['label' => '密碼', 'value' => fn($item) => '********'],
            ['label' => '地址', 'key' => 'address', 'default' => '未設定', 'sort' => 'address'],
            ['label' => '角色', 'key' => 'role', 'sort' => 'role'],
            ['label' => '加入時間', 'key' => 'created_at', 'default' => '未知', 'sort' => 'created_at'],
            ['label' => '修改時間', 'key' => 'updated_at', 'default' => '未修改', 'sort' => 'updated_at'],
        ],
        'actionLogic' => function($item, $user_id, $claimed_items) {
            return '<button class="primary" onclick="window.location.href=\'edit_user.php?id=' . $item['id'] . '\'">編輯</button>
                    <button class="delete" onclick="if(confirm(\'確定要刪除？\')) window.location.href=\'delete_user.php?id=' . $item['id'] . '\'">刪除</button>';
        },
        'queryParams' => [
            'tab' => 'manage_users',
            'page' => $_GET['page'] ?? 1,
            'per_page' => $_GET['per_page'] ?? DEFAULT_PAGE_SIZE,
            'search' => $_GET['search'] ?? '',
            'sort_column' => $_GET['sort_column'] ?? 'created_at',
            'sort_order' => $_GET['sort_order'] ?? 'ASC',
            'page_param' => 'page',
            'per_page_param' => 'per_page',
            'sort_column_param' => 'sort_column',
            'sort_order_param' => 'sort_order',
        ],
    ],
    'announcements' => [
        'title' => '公告管理',
        'sql' => "SELECT a.*, u.username FROM announcements a JOIN users u ON a.admin_id = u.id",
        'filters' => [
            ['name' => 'search', 'label' => '搜尋', 'type' => 'text', 'condition' => '(a.content LIKE ? OR u.username LIKE ?)', 'placeholder' => '搜尋公告內容或發佈者...'],
        ],
        'columns' => [
            ['label' => '編號', 'key' => 'id', 'sort' => 'id'],
            ['label' => '公告類型', 'key' => 'announcement_type', 'value' => fn($item) => $item['announcement_type'] == 'public' ? '公開' : '管理員專用', 'sort' => 'announcement_type'],
            ['label' => '內容', 'key' => 'content', 'sort' => 'content'],
            ['label' => '發佈者', 'key' => 'username', 'sort' => 'username'],
            ['label' => '發佈時間', 'key' => 'created_at', 'sort' => 'created_at'],
        ],
        'actionLogic' => function($item, $user_id, $claimed_items) {
            return '<button class="delete" onclick="if(confirm(\'確定要刪除此公告？\')) window.location.href=\'delete_announcement.php?id=' . $item['id'] . '\'">刪除</button>';
        },
        'queryParams' => [
            'tab' => 'announcements',
            'page' => $_GET['page'] ?? 1,
            'per_page' => $_GET['per_page'] ?? DEFAULT_PAGE_SIZE,
            'search' => $_GET['search'] ?? '',
            'sort_column' => $_GET['sort_column'] ?? 'created_at',
            'sort_order' => $_GET['sort_order'] ?? 'ASC',
            'page_param' => 'page',
            'per_page_param' => 'per_page',
            'sort_column_param' => 'sort_column',
            'sort_order_param' => 'sort_order',
        ],
    ],
];

$claimed_items = [];
foreach ($tabs as $tab => &$config) {
    $result = buildQuery($pdo, $config['sql'], $config['filters'], $config['queryParams'], [], $tab);
    $config['items'] = $result['items'];
    $config['totalRows'] = $result['totalRows'];
    $config['error'] = $result['error'];
    $config['totalPages'] = ceil($config['totalRows'] / $config['queryParams']['per_page']);
}
unset($config);

if (isset($_GET['export']) && $active_tab === 'profile' && in_array($_GET['export'], ['items', 'users'])) {
    $type = $_GET['export'];
    $config = $tabs['manage_' . $type];
    $filename = $type . '_' . date('Ymd_His') . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    $columns = array_map(fn($col) => $col['label'], $config['columns']);
    fputcsv($output, $columns);

    $stmt = $pdo->prepare($config['sql']);
    $stmt->execute();
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($items as $item) {
        $row = [];
        foreach ($config['columns'] as $col) {
            $value = $col['value'] ?? fn($i) => $i[$col['key']] ?? $col['default'] ?? '';
            $row[] = is_callable($value) ? strip_tags($value($item)) : ($item[$col['key']] ?? $col['default'] ?? '');
        }
        fputcsv($output, $row);
    }

    fclose($output);
    exit();
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>管理員儀表板</title>
    <link rel="icon" type="image/x-icon" href="assest/favicon-32x32.ico">
    <link rel="apple-touch-icon" href="assest/favicon-128x128.ico">
    <link rel="icon" type="image/x-icon" href="assest/favicon-128x128.ico" sizes="128x128">
    <link rel="icon" type="image/x-icon" href="assest/favicon-256x256.ico" sizes="256x256">
    <link rel="ms-icon" href="assest/favicon-128x128.ico" sizes="128x128">
    <link rel="icon" type="image/x-icon" href="assest/favicon.ico">
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="css/admin_dashboard.css">
    <script src="https://maps.googleapis.com/maps/api/js?key=your api key"></script>
    <script src="js/map.js"></script>
    <script src="js/tooltip.js"></script>
    <script>
        function resetFilters(tab) {
            window.location.href = `?tab=${tab}`;
        }
    </script>
</head>
<body>
    <script>
        fetch('loading.html')
            .then(response => response.text())
            .then(html => {
                document.body.insertAdjacentHTML('afterbegin', html);
                const loadingOverlay = document.getElementById('loadingOverlay');
                setTimeout(() => {
                    loadingOverlay.classList.add('fade-out');
                    setTimeout(() => {
                        loadingOverlay.style.display = 'none';
                    }, 300);
                }, 1500);
            })
            .catch(error => console.error('載入 loading.html 失敗:', error));
    </script>

    <div class="container">
        <div class="header">
            <h1>歡迎，<?php echo htmlspecialchars($user['username']); ?>！</h1>
            <div class="notification-bell">
                <span class="bell-icon">🔔</span>
                <?php if ($unread_count > 0) { ?>
                    <span class="badge"><?php echo $unread_count; ?></span>
                <?php } ?>
                <div class="notification-dropdown" id="notificationDropdown">
                    <h3>通知</h3>
                    <?php
                    try {
                        $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
                        $stmt->execute([$user_id]);
                        $notifications = $stmt->fetchAll();
                    } catch (PDOException $e) {
                        $notifications = [];
                        echo "<p class='error'>無法載入通知: " . htmlspecialchars($e->getMessage()) . "</p>";
                    }
                    ?>
                    <?php if (empty($notifications)) { ?>
                        <p>無通知</p>
                    <?php } else { ?>
                        <div class="mark-read-form">
                            <button id="mark-all-read-button" class="mark-all-read-button">全部標記為已讀</button>
                        </div>
                        <ul id="notification-list" class="scrollable">
                            <?php foreach ($notifications as $note) { ?>
                                <li class="<?php echo $note['is_read'] ? 'read' : 'unread'; ?>" data-notification-id="<?php echo $note['id']; ?>">
                                    <?php echo htmlspecialchars($note['message']); ?> (<?php echo $note['created_at']; ?>)
                                </li>
                            <?php } ?>
                        </ul>
                    <?php } ?>
                </div>
            </div>
        </div>

        <div class="tabs">
            <button class="tab-button <?php echo $active_tab === 'manage_items' ? 'active' : ''; ?>" onclick="openTab(event, 'manage_items')">管理失物</button>
            <button class="tab-button <?php echo $active_tab === 'review_items' ? 'active' : ''; ?>" onclick="openTab(event, 'review_items')">
                審核申報<?php if ($pending_items_count > 0): ?><span class="notification"><?php echo $pending_items_count; ?></span><?php endif; ?>
            </button>
            <button class="tab-button <?php echo $active_tab === 'manage_claims' ? 'active' : ''; ?>" onclick="openTab(event, 'manage_claims')">
                管理申請<?php if ($pending_claims_count > 0): ?><span class="notification"><?php echo $pending_claims_count; ?></span><?php endif; ?>
            </button>
            <button class="tab-button <?php echo $active_tab === 'claim_records' ? 'active' : ''; ?>" onclick="openTab(event, 'claim_records')">領取紀錄</button>
            <button class="tab-button <?php echo $active_tab === 'review_users' ? 'active' : ''; ?>" onclick="openTab(event, 'review_users')">
                審核註冊<?php if ($pending_users_count > 0): ?><span class="notification"><?php echo $pending_users_count; ?></span><?php endif; ?>
            </button>
            <button class="tab-button <?php echo $active_tab === 'manage_users' ? 'active' : ''; ?>" onclick="openTab(event, 'manage_users')">管理用戶</button>
            <button class="tab-button <?php echo $active_tab === 'announcements' ? 'active' : ''; ?>" onclick="openTab(event, 'announcements')">公告管理</button>
            <button class="tab-button <?php echo $active_tab === 'profile' ? 'active' : ''; ?>" onclick="openTab(event, 'profile')">個人資料</button>
        </div>

        <div id="manage_items" class="tab-content" style="display: <?php echo $active_tab === 'manage_items' ? 'block' : 'none'; ?>">
            <div class="header-section">
                <h2>管理失物</h2>
                <button class="refresh-button" onclick="initMap()">重新整理地圖</button>
            </div>
            <div class="map-filters">
                <div class="filter-group">
                    <label>
                        <img src="http://maps.google.com/mapfiles/ms/icons/green-dot.png" alt="已找回">
                        <input type="checkbox" id="showClaimed" onchange="initMap()"> 顯示已找回失物
                    </label>
                    <label>
                        <img src="http://maps.google.com/mapfiles/ms/icons/yellow-dot.png" alt="待審核申請">
                        <input type="checkbox" id="showPendingClaims" onchange="initMap()"> 顯示待審核申請失物
                    </label>
                    <label>
                        <img src="http://maps.google.com/mapfiles/ms/icons/blue-dot.png" alt="拾獲">
                        <input type="checkbox" id="showFound" checked onchange="initMap()"> 顯示拾獲失物
                    </label>
                    <label>
                        <img src="http://maps.google.com/mapfiles/ms/icons/red-dot.png" alt="遺失">
                        <input type="checkbox" id="showLost" checked onchange="initMap()"> 顯示遺失失物
                    </label>
                </div>
            </div>
            <div id="map" class="map"></div>
            <div id="map-error" style="color: red; display: none;">無法載入地圖，請檢查網路或稍後重試。</div>
            <div id="map-status"></div>
            <?php renderFilterForm('manage_items', $tabs['manage_items']['filters'], $tabs['manage_items']['queryParams']); ?>
            <?php if ($tabs['manage_items']['error']) { ?>
                <p class="error">查詢失敗，請稍後重試或聯繫系統管理員：<?php echo htmlspecialchars($tabs['manage_items']['error']); ?></p>
            <?php } else { ?>
                <?php renderTable($pdo, 'manage_items', $tabs['manage_items']['items'], $user_id, $claimed_items, $tabs['manage_items']['columns'], $tabs['manage_items']['actionLogic'], $tabs['manage_items']['queryParams']); ?>
                <?php renderPagination($tabs['manage_items']['totalPages'], $tabs['manage_items']['queryParams']['page'], $tabs['manage_items']['queryParams']); ?>
            <?php } ?>
        </div>

        <div id="review_items" class="tab-content" style="display: <?php echo $active_tab === 'review_items' ? 'block' : 'none'; ?>">
            <h2>審核申報</h2>
            <?php renderFilterForm('review_items', $tabs['review_items']['filters'], $tabs['review_items']['queryParams']); ?>
            <?php if ($tabs['review_items']['error']) { ?>
                <p class="error">查詢失敗，請稍後重試或聯繫系統管理員：<?php echo htmlspecialchars($tabs['review_items']['error']); ?></p>
            <?php } else { ?>
                <?php renderTable($pdo, 'review_items', $tabs['review_items']['items'], $user_id, $claimed_items, $tabs['review_items']['columns'], $tabs['review_items']['actionLogic'], $tabs['review_items']['queryParams']); ?>
                <?php renderPagination($tabs['review_items']['totalPages'], $tabs['review_items']['queryParams']['page'], $tabs['review_items']['queryParams']); ?>
            <?php } ?>
        </div>

        <div id="manage_claims" class="tab-content" style="display: <?php echo $active_tab === 'manage_claims' ? 'block' : 'none'; ?>">
            <h2>管理申請</h2>
            <?php renderFilterForm('manage_claims', $tabs['manage_claims']['filters'], $tabs['manage_claims']['queryParams']); ?>
            <?php if ($tabs['manage_claims']['error']) { ?>
                <p class="error">查詢失敗，請稍後重試或聯繫系統管理員：<?php echo htmlspecialchars($tabs['manage_claims']['error']); ?></p>
            <?php } else { ?>
                <?php renderTable($pdo, 'manage_claims', $tabs['manage_claims']['items'], $user_id, $claimed_items, $tabs['manage_claims']['columns'], $tabs['manage_claims']['actionLogic'], $tabs['manage_claims']['queryParams']); ?>
                <?php renderPagination($tabs['manage_claims']['totalPages'], $tabs['manage_claims']['queryParams']['page'], $tabs['manage_claims']['queryParams']); ?>
            <?php } ?>
        </div>

        <div id="claim_records" class="tab-content" style="display: <?php echo $active_tab === 'claim_records' ? 'block' : 'none'; ?>">
            <h2>領取紀錄</h2>
            <?php renderFilterForm('claim_records', $tabs['claim_records']['filters'], $tabs['claim_records']['queryParams']); ?>
            <?php if ($tabs['claim_records']['error']) { ?>
                <p class="error">查詢失敗，請稍後重試或聯繫系統管理員：<?php echo htmlspecialchars($tabs['claim_records']['error']); ?></p>
            <?php } else { ?>
                <?php renderTable($pdo, 'claim_records', $tabs['claim_records']['items'], $user_id, $claimed_items, $tabs['claim_records']['columns'], $tabs['claim_records']['actionLogic'], $tabs['claim_records']['queryParams']); ?>
                <?php renderPagination($tabs['claim_records']['totalPages'], $tabs['claim_records']['queryParams']['page'], $tabs['claim_records']['queryParams']); ?>
            <?php } ?>
        </div>

        <div id="review_users" class="tab-content" style="display: <?php echo $active_tab === 'review_users' ? 'block' : 'none'; ?>">
            <h2>審核註冊</h2>
            <?php renderFilterForm('review_users', $tabs['review_users']['filters'], $tabs['review_users']['queryParams']); ?>
            <?php if ($tabs['review_users']['error']) { ?>
                <p class="error">查詢失敗，請稍後重試或聯繫系統管理員：<?php echo htmlspecialchars($tabs['review_users']['error']); ?></p>
            <?php } else { ?>
                <?php renderTable($pdo, 'review_users', $tabs['review_users']['items'], $user_id, $claimed_items, $tabs['review_users']['columns'], $tabs['review_users']['actionLogic'], $tabs['review_users']['queryParams']); ?>
                <?php renderPagination($tabs['review_users']['totalPages'], $tabs['review_users']['queryParams']['page'], $tabs['review_users']['queryParams']); ?>
            <?php } ?>
        </div>

        <div id="manage_users" class="tab-content" style="display: <?php echo $active_tab === 'manage_users' ? 'block' : 'none'; ?>">
            <h2>管理用戶</h2>
            <?php renderFilterForm('manage_users', $tabs['manage_users']['filters'], $tabs['manage_users']['queryParams']); ?>
            <?php if ($tabs['manage_users']['error']) { ?>
                <p class="error">查詢失敗，請稍後重試或聯繫系統管理員：<?php echo htmlspecialchars($tabs['manage_users']['error']); ?></p>
            <?php } elseif (empty($tabs['manage_users']['items'])) { ?>
                <p class="info">目前沒有符合條件的用戶（角色為一般用戶且已通過審核）。</p>
            <?php } else { ?>
                <?php renderTable($pdo, 'manage_users', $tabs['manage_users']['items'], $user_id, $claimed_items, $tabs['manage_users']['columns'], $tabs['manage_users']['actionLogic'], $tabs['manage_users']['queryParams']); ?>
                <?php renderPagination($tabs['manage_users']['totalPages'], $tabs['manage_users']['queryParams']['page'], $tabs['manage_users']['queryParams']); ?>
            <?php } ?>
        </div>

        <div id="announcements" class="tab-content" style="display: <?php echo $active_tab === 'announcements' ? 'block' : 'none'; ?>">
            <h2>公告管理</h2>
            <?php renderFilterForm('announcements', $tabs['announcements']['filters'], $tabs['announcements']['queryParams']); ?>
            <?php if ($tabs['announcements']['error']) { ?>
                <p class="error">查詢失敗，請稍後重試或聯繫系統管理員：<?php echo htmlspecialchars($tabs['announcements']['error']); ?></p>
            <?php } else { ?>
                <?php renderTable($pdo, 'announcements', $tabs['announcements']['items'], $user_id, $claimed_items, $tabs['announcements']['columns'], $tabs['announcements']['actionLogic'], $tabs['announcements']['queryParams']); ?>
                <?php renderPagination($tabs['announcements']['totalPages'], $tabs['announcements']['queryParams']['page'], $tabs['announcements']['queryParams']); ?>
            <?php } ?>
            <h3>發送公告</h3>
            <form action="admin_post_announcement.php" method="POST">
                <label for="announcement_type">公告類型</label>
                <select name="announcement_type" id="announcement_type" required>
                    <option value="public">公開公告（所有用戶可見）</option>
                    <option value="admin_only">管理員公告（僅管理員可見）</option>
                </select>
                <label for="content">公告內容</label>
                <textarea name="content" id="content" required></textarea>
                <button type="submit">發送</button>
            </form>
        </div>

        <div id="profile" class="tab-content" style="display: <?php echo $active_tab === 'profile' ? 'block' : 'none'; ?>">
            <h2>個人資料</h2>
            <button onclick="window.location.href='admin_profile.php'">編輯個人資料</button>
            <button onclick="window.location.href='?tab=profile&export=items'">匯出失物 CSV</button>
            <button onclick="window.location.href='?tab=profile&export=users'">匯出用戶 CSV</button>
            <div class="table-container">
                <table class="profile-table">
                    <tr><th>欄位</th><th>值</th></tr>
                    <tr><td>學號</td><td><?php echo htmlspecialchars($user['sID']); ?></td></tr>
                    <tr><td>身分證字號</td><td><?php echo htmlspecialchars($user['uID']); ?></td></tr>
                    <tr><td>姓氏</td><td><?php echo htmlspecialchars($user['last_name']); ?></td></tr>
                    <tr><td>名字</td><td><?php echo htmlspecialchars($user['first_name']); ?></td></tr>
                    <tr><td>暱稱</td><td><?php echo htmlspecialchars($user['nickname'] ?: '未設定'); ?></td></tr>
                    <tr><td>密碼</td><td>********</td></tr>
                    <tr><td>電子郵件</td><td><?php echo htmlspecialchars($user['email']); ?></td></tr>
                    <tr><td>電話</td><td><?php echo htmlspecialchars($user['phone'] ?: '未設定'); ?></td></tr>
                    <tr><td>地址</td><td><?php echo htmlspecialchars($user['address'] ?: '未設定'); ?></td></tr>
                    <tr><td>加入時間</td><td><?php echo htmlspecialchars($user['created_at'] ?? '未知'); ?></td></tr>
                    <tr><td>修改時間</td><td><?php echo htmlspecialchars($user['updated_at'] ?? '未修改'); ?></td></tr>
                </table>
            </div>
            <button class="logout" onclick="window.location.href='logout.php'">登出</button>
        </div>
    </div>

<script>
    const currentUserId = null;

    function openTab(evt, tabName) {
        const tabContents = document.getElementsByClassName("tab-content");
        for (let i = 0; i < tabContents.length; i++) {
            tabContents[i].style.display = "none";
        }
        const tabButtons = document.getElementsByClassName("tab-button");
        for (let i = 0; i < tabButtons.length; i++) {
            tabButtons[i].classList.remove("active");
        }
        document.getElementById(tabName).style.display = "block";
        evt.currentTarget.classList.add("active");
        history.pushState(null, null, '?tab=' + tabName);
        if (tabName === 'manage_items') {
            const mapError = document.getElementById('map-error');
            setTimeout(() => {
                try {
                    initMap();
                    mapError.style.display = 'none';
                } catch (error) {
                    console.error('地圖初始化失敗:', error);
                    mapError.style.display = 'block';
                }
            }, 500);
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        const activeTab = "<?php echo htmlspecialchars($active_tab); ?>";
        const tabButton = document.querySelector(`.tab-button[onclick="openTab(event, '${activeTab}')"]`);
        if (tabButton) {
            openTab({ currentTarget: tabButton }, activeTab);
        }

        const bell = document.querySelector('.notification-bell');
        const dropdown = document.getElementById('notificationDropdown');
        bell.addEventListener('click', (e) => {
            e.stopPropagation();
            dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
        });
        document.addEventListener('click', (e) => {
            if (!bell.contains(e.target) && !dropdown.contains(e.target)) {
                dropdown.style.display = 'none';
            }
        });

        const markAllReadButton = document.getElementById('mark-all-read-button');
        if (markAllReadButton) {
            markAllReadButton.addEventListener('click', function() {
                fetch('?action=mark_all_notifications_read', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const notificationItems = document.querySelectorAll('#notification-list li');
                        notificationItems.forEach(item => {
                            item.classList.remove('unread');
                            item.classList.add('read');
                        });
                        const badge = document.querySelector('.notification-bell .badge');
                        if (badge) badge.remove();
                    } else {
                        console.error('標記失敗:', data.message);
                    }
                })
                .catch(error => console.error('AJAX 請求失敗:', error));
            });
        }
    });
</script>
</body>
</html>