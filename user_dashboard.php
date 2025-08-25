<?php
session_start();
require 'config.php';
require 'functions.php';
date_default_timezone_set('Asia/Taipei');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['session_token'])) {
    header("Location: index.php");
    exit();
}

$stmt = $pdo->prepare("SELECT session_token, username, sID, uID, last_name, first_name, nickname, email, phone, address, created_at, updated_at FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user || $user['session_token'] !== session_id()) {
    session_destroy();
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
if ($_SESSION['role'] != 'user') {
    header("Location: admin_dashboard.php");
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
                'message' => '已將 ' . $stmt->rowCount() . ' 條個人通知標記為已讀',
                'unread_count' => $unread_count
            ]);
        } elseif ($_GET['action'] === 'mark_all_announcements_read') {
            $stmt = $pdo->prepare("
                INSERT INTO announcement_reads (announcement_id, user_id, is_read)
                SELECT a.id, ?, TRUE
                FROM announcements a
                WHERE a.announcement_type = 'public'
                ON DUPLICATE KEY UPDATE is_read = TRUE, read_at = CURRENT_TIMESTAMP
            ");
            $stmt->execute([$user_id]);
            echo json_encode([
                'success' => true,
                'message' => '已將 ' . $stmt->rowCount() . ' 條官方公告標記為已讀'
            ]);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => '標記失敗: ' . $e->getMessage()]);
    }
    exit();
}

$unread_count = $pdo->query("SELECT COUNT(*) FROM notifications WHERE user_id = $user_id AND is_read = FALSE")->fetchColumn();
$claimed_items = $pdo->query("SELECT item_id FROM claims WHERE user_id = $user_id AND status = 'pending'")->fetchAll(PDO::FETCH_COLUMN);
$tabs = [
    'home' => [
        'title' => '失物地圖',
        'sql' => "SELECT li.*, u.username FROM lost_items li JOIN users u ON li.user_id = u.id WHERE li.approval_status = 'approved' AND li.user_id != ?",
        'filters' => [
            ['name' => 'search_type', 'label' => '類型', 'type' => 'select', 'options' => ['' => '全部', 'lost_by_user' => '遺失物品', 'found_by_user' => '撿到物品'], 'condition' => 'li.item_type = ?', 'placeholder' => ''],
            ['name' => 'search_status', 'label' => '操作', 'type' => 'select', 'options' => ['' => '全部', '不可申請' => '不可申請', '已歸還' => '已歸還', '申請提繳' => '申請提繳', '已申請' => '已申請'], 'condition' => 'li.status = ?', 'placeholder' => ''],
            ['name' => 'search_keyword', 'label' => '搜尋關鍵字', 'type' => 'text', 'condition' => '(li.title LIKE ? OR li.description LIKE ?)', 'placeholder' => '輸入標題或描述']
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
            ['label' => '結束時間', 'key' => 'ended_at', 'default' => '未結束', 'sort' => 'ended_at']
        ],
        'actionLogic' => function($item, $user_id, $claimed_items) {
            if (in_array($item['id'], $claimed_items)) {
                return '<button class="disabled-button" disabled>已申請（待審核）</button>';
            } elseif ($item['approval_status'] != 'approved') {
                return '<button class="disabled-button" disabled>不可申請（未通過審核）</button>';
            } elseif ($item['user_id'] == $user_id) {
                return '<button class="disabled-button" disabled>不可申請（自己的失物）</button>';
            } elseif ($item['status'] != 'available') {
                $msg = $item['status'] == 'claimed' ? ($item['item_type'] == 'lost_by_user' ? '已找回' : '已歸還') : '不可申請（已結束）';
                return '<button class="disabled-button" disabled>' . $msg . '</button>';
            }
            return '<button class="apply-button" onclick="window.location.href=\'claim_item.php?item_id=' . $item['id'] . '&tab=home\'">申請提繳</button>';
        },
        'queryParams' => [
            'tab' => 'home',
            'page' => $_GET['page'] ?? 1,
            'per_page' => $_GET['per_page'] ?? DEFAULT_PAGE_SIZE,
            'search_type' => $_GET['search_type'] ?? '',
            'search_status' => $_GET['search_status'] ?? '',
            'search_keyword' => $_GET['search_keyword'] ?? '',
            'sort_column' => $_GET['sort_column'] ?? 'id',
            'sort_order' => $_GET['sort_order'] ?? 'ASC',
            'page_param' => 'page',
            'per_page_param' => 'per_page',
            'search_type_param' => 'search_type',
            'search_status_param' => 'search_status',
            'search_approval_param' => 'search_approval',
            'search_keyword_param' => 'search_keyword',
            'sort_column_param' => 'sort_column',
            'sort_order_param' => 'sort_order'
        ]
    ],
    'lost_items' => [
        'title' => '遺失申報',
        'sql' => "SELECT li.*, c.id AS claim_id, c.status AS claim_status FROM lost_items li LEFT JOIN claims c ON li.id = c.item_id AND c.status = 'pending' WHERE li.user_id = ? AND li.item_type = 'lost_by_user'",
        'filters' => [
            ['name' => 'lost_search_status', 'label' => '狀態', 'type' => 'select', 'options' => ['' => '全部', 'declaring' => '申報中', 'claiming' => '申請中', 'claimed' => '已找回', 'unclaimed' => '尚未認領'], 'condition' => '', 'placeholder' => ''],
            ['name' => 'lost_search_approval', 'label' => '審核狀態', 'type' => 'select', 'options' => ['' => '全部', 'pending' => '待審核', 'approved' => '已通過', 'rejected' => '已拒絕'], 'condition' => 'li.approval_status = ?', 'placeholder' => ''],
            ['name' => 'lost_search_keyword', 'label' => '搜尋關鍵字', 'type' => 'text', 'condition' => '(li.title LIKE ? OR li.description LIKE ?)', 'placeholder' => '輸入標題或描述'],
        ],
        'columns' => [
            ['label' => '編號', 'key' => 'id', 'sort' => 'id'],
            ['label' => '狀態', 'class' => 'status', 'class_callback' => fn($item) => $item['approval_status'] == 'pending' ? 'status-declaring' : ($item['claim_id'] && $item['claim_status'] == 'pending' ? 'status-claiming' : ($item['status'] == 'claimed' ? 'status-claimed' : 'status-unclaimed')), 'value' => fn($item) => $item['approval_status'] == 'pending' ? '申報中' : ($item['claim_id'] && $item['claim_status'] == 'pending' ? '申請中' : ($item['status'] == 'claimed' ? '已找回' : '尚未認領')), 'sort' => 'status'],
            ['label' => '審核狀態', 'class' => 'approval-status', 'class_callback' => fn($item) => 'approval-' . $item['approval_status'], 'value' => fn($item) => $item['approval_status'] == 'pending' ? '待審核' : ($item['approval_status'] == 'approved' ? '已通過' : '已拒絕'), 'sort' => 'approval_status'],
            ['label' => '標題', 'key' => 'title', 'sort' => 'title', 'value' => fn($item) => '<span data-image="' . ($item['image'] ? htmlspecialchars(UPLOAD_URL . $item['image']) : 'assest/placeholder.jpg') . '" style="cursor: pointer;">' . htmlspecialchars($item['title']) . '</span>'],
            ['label' => '描述', 'key' => 'description', 'sort' => 'description'],
            ['label' => '地點', 'key' => 'location', 'sort' => 'location'],
            ['label' => '申請時間', 'key' => 'created_at', 'sort' => 'created_at'],
            ['label' => '修改時間', 'key' => 'updated_at', 'default' => '未修改', 'sort' => 'updated_at'],
            ['label' => '通過時間', 'key' => 'approved_at', 'default' => '未通過', 'sort' => 'approved_at'],
            ['label' => '結束時間', 'key' => 'ended_at', 'default' => '未結束', 'sort' => 'ended_at'],
        ],
        'actionLogic' => function($item, $user_id, $claimed_items) {
            if ($item['status'] == 'claimed') {
                return '<button class="disabled-button" disabled>已找回</button>';
            } elseif ($item['approval_status'] == 'rejected' && $item['rejected_at']) {
                $rejection_time = new DateTime($item['rejected_at']);
                $now = new DateTime();
                $interval = $rejection_time->diff($now);
                $hours = $interval->h + ($interval->days * 24);
                if ($hours < 24) {
                    return '<button class="disabled-button" disabled>需等待 ' . (24 - $hours) . ' 小時後編輯</button>';
                }
            }
            return '<button class="edit-button" onclick="window.location.href=\'edit_item.php?id=' . $item['id'] . '\'">編輯</button>
                    <button class="delete-button" onclick="if(confirm(\'確定要刪除？\')) window.location.href=\'delete_item.php?id=' . $item['id'] . '\'">刪除</button>';
        },
        'queryParams' => [
            'tab' => 'lost_items',
            'page' => $_GET['lost_page'] ?? 1,
            'per_page' => $_GET['lost_per_page'] ?? DEFAULT_PAGE_SIZE,
            'search_status' => $_GET['lost_search_status'] ?? '',
            'search_approval' => $_GET['lost_search_approval'] ?? '',
            'search_keyword' => $_GET['lost_search_keyword'] ?? '',
            'sort_column' => $_GET['lost_sort_column'] ?? 'id',
            'sort_order' => $_GET['lost_sort_order'] ?? 'ASC',
            'page_param' => 'lost_page',
            'per_page_param' => 'lost_per_page',
            'search_status_param' => 'lost_search_status',
            'search_approval_param' => 'lost_search_approval',
            'search_keyword_param' => 'lost_search_keyword',
            'sort_column_param' => 'lost_sort_column',
            'sort_order_param' => 'lost_sort_order',
        ],
    ],
    'found_items' => [
        'title' => '拾獲申報',
        'sql' => "SELECT li.*, c.id AS claim_id, c.status AS claim_status FROM lost_items li LEFT JOIN claims c ON li.id = c.item_id AND c.status = 'pending' WHERE li.user_id = ? AND li.item_type = 'found_by_user'",
        'filters' => [
            ['name' => 'found_search_status', 'label' => '狀態', 'type' => 'select', 'options' => ['' => '全部', 'declaring' => '申報中', 'claiming' => '申請中', 'claimed' => '已歸還', 'unclaimed' => '待領回'], 'condition' => '', 'placeholder' => ''],
            ['name' => 'found_search_approval', 'label' => '審核狀態', 'type' => 'select', 'options' => ['' => '全部', 'pending' => '待審核', 'approved' => '已通過', 'rejected' => '已拒絕'], 'condition' => 'li.approval_status = ?', 'placeholder' => ''],
            ['name' => 'found_search_keyword', 'label' => '搜尋關鍵字', 'type' => 'text', 'condition' => '(li.title LIKE ? OR li.description LIKE ?)', 'placeholder' => '輸入標題或描述'],
        ],
        'columns' => [
            ['label' => '編號', 'key' => 'id', 'sort' => 'id'],
            ['label' => '狀態', 'class' => 'status', 'class_callback' => fn($item) => $item['approval_status'] == 'pending' ? 'status-declaring' : ($item['claim_id'] && $item['claim_status'] == 'pending' ? 'status-claiming' : ($item['status'] == 'claimed' ? 'status-claimed' : 'status-unclaimed')), 'value' => fn($item) => $item['approval_status'] == 'pending' ? '申報中' : ($item['claim_id'] && $item['claim_status'] == 'pending' ? '申請中' : ($item['status'] == 'claimed' ? '已歸還' : '待領回')), 'sort' => 'status'],
            ['label' => '審核狀態', 'class' => 'approval-status', 'class_callback' => fn($item) => 'approval-' . $item['approval_status'], 'value' => fn($item) => $item['approval_status'] == 'pending' ? '待審核' : ($item['approval_status'] == 'approved' ? '已通過' : '已拒絕'), 'sort' => 'approval_status'],
            ['label' => '標題', 'key' => 'title', 'sort' => 'title', 'value' => fn($item) => '<span data-image="' . ($item['image'] ? htmlspecialchars(UPLOAD_URL . $item['image']) : 'assest/placeholder.jpg') . '" style="cursor: pointer;">' . htmlspecialchars($item['title']) . '</span>'],
            ['label' => '描述', 'key' => 'description', 'sort' => 'description'],
            ['label' => '地點', 'key' => 'location', 'sort' => 'location'],
            ['label' => '申請時間', 'key' => 'created_at', 'sort' => 'created_at'],
            ['label' => '修改時間', 'key' => 'updated_at', 'default' => '未修改', 'sort' => 'updated_at'],
            ['label' => '通過時間', 'key' => 'approved_at', 'default' => '未通過', 'sort' => 'approved_at'],
            ['label' => '結束時間', 'key' => 'ended_at', 'default' => '未結束', 'sort' => 'ended_at'],
        ],
        'actionLogic' => function($item, $user_id, $claimed_items) {
            if ($item['status'] == 'claimed') {
                return '<button class="disabled-button" disabled>已完成</button>';
            } elseif ($item['approval_status'] == 'rejected' && $item['rejected_at']) {
                $rejection_time = new DateTime($item['rejected_at']);
                $now = new DateTime();
                $interval = $rejection_time->diff($now);
                $hours = $interval->h + ($interval->days * 24);
                if ($hours < 24) {
                    return '<button class="disabled-button" disabled>需等待 ' . (24 - $hours) . ' 小時後編輯</button>';
                }
            }
            return '<button class="edit-button" onclick="window.location.href=\'edit_item.php?id=' . $item['id'] . '\'">編輯</button>
                    <button class="delete-button" onclick="if(confirm(\'確定要取消？\')) window.location.href=\'delete_item.php?id=' . $item['id'] . '\'">取消</button>';
        },
        'queryParams' => [
            'tab' => 'found_items',
            'page' => $_GET['found_page'] ?? 1,
            'per_page' => $_GET['found_per_page'] ?? DEFAULT_PAGE_SIZE,
            'search_status' => $_GET['found_search_status'] ?? '',
            'search_approval' => $_GET['found_search_approval'] ?? '',
            'search_keyword' => $_GET['found_search_keyword'] ?? '',
            'sort_column' => $_GET['found_sort_column'] ?? 'id',
            'sort_order' => $_GET['found_sort_order'] ?? 'ASC',
            'page_param' => 'found_page',
            'per_page_param' => 'found_per_page',
            'search_status_param' => 'found_search_status',
            'search_approval_param' => 'found_search_approval',
            'search_keyword_param' => 'found_search_keyword',
            'sort_column_param' => 'found_sort_column',
            'sort_order_param' => 'found_sort_order',
        ],
    ],
    'claim_records' => [
        'title' => '領取紀錄',
        'sql' => "SELECT c.item_id, li.title, li.image, u1.sID AS claimant_sID, u1.username AS claimant, u2.sID AS owner_sID, u2.username AS owner, c.created_at, c.updated_at AS claimed_at, c.status, c.rejected_at FROM lost_items li JOIN claims c ON li.id = c.item_id JOIN users u1 ON c.user_id = u1.id JOIN users u2 ON li.user_id = u2.id WHERE c.user_id = ?",
        'filters' => [
            ['name' => 'claim_search_status', 'label' => '申請狀態', 'type' => 'select', 'options' => ['' => '全部', 'pending' => '待審核', 'approved' => '申請成功', 'rejected' => '申請駁回'], 'condition' => 'c.status = ?', 'placeholder' => ''],
            ['name' => 'claim_search_keyword', 'label' => '搜尋關鍵字', 'type' => 'text', 'condition' => '(li.title LIKE ? OR u1.sID LIKE ? OR u1.username LIKE ? OR u2.sID LIKE ? OR u2.username LIKE ?)', 'placeholder' => '輸入標題、學號或用戶名'],
        ],
        'columns' => [
            ['label' => '編號', 'key' => 'item_id', 'sort' => 'item_id'],
            ['label' => '標題', 'key' => 'title', 'sort' => 'title', 'value' => fn($item) => '<span data-image="' . ($item['image'] ? htmlspecialchars(UPLOAD_URL . $item['image']) : 'assest/placeholder.jpg') . '" style="cursor: pointer;">' . htmlspecialchars($item['title']) . '</span>'],
            ['label' => '拾獲者學號', 'key' => 'owner_sID', 'sort' => 'owner_sID'],
            ['label' => '拾獲者', 'key' => 'owner', 'sort' => 'owner'],
            ['label' => '領取者學號', 'key' => 'claimant_sID', 'sort' => 'claimant_sID'],
            ['label' => '領取者', 'key' => 'claimant', 'sort' => 'claimant'],
            ['label' => '申請狀態', 'key' => 'status', 'value' => fn($item) => $item['status'] == 'pending' ? '待審核' : ($item['status'] == 'approved' ? '申請成功' : '申請駁回'), 'sort' => 'status'],
            ['label' => '申請時間', 'key' => 'created_at', 'sort' => 'created_at'],
            ['label' => '更新時間', 'key' => 'claimed_at', 'default' => '未知', 'sort' => 'claimed_at'],
        ],
        'actionLogic' => function($item, $user_id, $claimed_items) {
            if ($item['status'] == 'pending') {
                return '<button class="disabled-button" disabled>待審核</button>';
            } elseif ($item['status'] == 'approved') {
                return '<button class="success-button" disabled>申請成功</button>';
            } else {
                return '<button class="rejected-button" disabled>申請駁回</button>';
            }
        },
        'queryParams' => [
            'tab' => 'claim_records',
            'page' => $_GET['claim_page'] ?? 1,
            'per_page' => $_GET['claim_per_page'] ?? DEFAULT_PAGE_SIZE,
            'search_status' => $_GET['claim_search_status'] ?? '',
            'search_keyword' => $_GET['claim_search_keyword'] ?? '',
            'sort_column' => $_GET['claim_sort_column'] ?? 'item_id',
            'sort_order' => $_GET['claim_sort_order'] ?? 'ASC',
            'page_param' => 'claim_page',
            'per_page_param' => 'claim_per_page',
            'search_status_param' => 'claim_search_status',
            'search_keyword_param' => 'claim_search_keyword',
            'sort_column_param' => 'claim_sort_column',
            'sort_order_param' => 'claim_sort_order',
        ],
    ],
];

function applyCustomFilters($sql, $filters, $queryParams) {
    if (isset($queryParams['search_status']) && $queryParams['search_status']) {
        if ($queryParams['search_status'] == 'declaring') {
            $sql .= " AND li.approval_status = 'pending'";
        } elseif ($queryParams['search_status'] == 'claiming') {
            $sql .= " AND c.status = 'pending'";
        } elseif ($queryParams['search_status'] == 'claimed') {
            $sql .= " AND li.status = 'claimed'";
        } elseif ($queryParams['search_status'] == 'unclaimed') {
            $sql .= " AND li.status = 'available' AND c.id IS NULL";
        }
    }
    if (isset($queryParams['search_status']) && in_array($queryParams['search_status'], ['pending', 'approved', 'rejected'])) {
        $sql .= " AND c.status = '" . $queryParams['search_status'] . "'";
    }
    return $sql;
}

if (isset($_GET['export_csv']) && $_GET['export_csv'] === 'true') {
    $filename = htmlspecialchars($user['username']) . '_lost_items_data_' . date('Ymd');
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['類型', '編號', '標題', '描述', '地點', '申請時間', '修改時間', '通過時間', '結束時間', '領取者學號', '領取者', '領取時間']);
    $stmt = $pdo->prepare("SELECT * FROM lost_items WHERE user_id = ? AND item_type = 'lost_by_user'");
    $stmt->execute([$user_id]);
    $lost_items = $stmt->fetchAll();
    foreach ($lost_items as $item) {
        fputcsv($output, ['遺失申報', $item['id'], strip_tags($item['title']), $item['description'], $item['location'], $item['created_at'], $item['updated_at'], $item['approved_at'] ?: '', $item['ended_at'] ?: '', '', '', '']);
    }
    $stmt = $pdo->prepare("SELECT * FROM lost_items WHERE user_id = ? AND item_type = 'found_by_user'");
    $stmt->execute([$user_id]);
    $found_items = $stmt->fetchAll();
    foreach ($found_items as $item) {
        fputcsv($output, ['拾獲申報', $item['id'], strip_tags($item['title']), $item['description'], $item['location'], $item['created_at'], $item['updated_at'], $item['approved_at'] ?: '', $item['ended_at'] ?: '', '', '', '']);
    }
    $stmt = $pdo->prepare("SELECT c.item_id, li.title, li.description, li.location, li.created_at, li.updated_at, li.approved_at, li.ended_at, u1.sID AS claimant_sID, u1.username AS claimant, c.updated_at AS claimed_at FROM lost_items li JOIN claims c ON li.id = c.item_id JOIN users u1 ON c.user_id = u1.id WHERE (li.user_id = ? OR c.user_id = ?) AND c.status = 'approved'");
    $stmt->execute([$user_id, $user_id]);
    $claim_records = $stmt->fetchAll();
    foreach ($claim_records as $record) {
        fputcsv($output, ['領取紀錄', $record['item_id'], strip_tags($record['title']), $record['description'], $record['location'], $record['created_at'], $record['updated_at'], $record['approved_at'] ?: '', $record['ended_at'] ?: '', $record['claimant_sID'], $record['claimant'], $record['claimed_at'] ?: '']);
    }

    fclose($output);
    exit();
}

$active_tab = $_GET['tab'] ?? 'home';
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>用戶儀表板</title>
    <link rel="icon" type="image/x-icon" href="assest/favicon-32x32.ico">
    <link rel="apple-touch-icon" href="assest/favicon-128x128.ico">
    <link rel="icon" type="image/x-icon" href="assest/favicon-128x128.ico" sizes="128x128">
    <link rel="icon" type="image/x-icon" href="assest/favicon-256x256.ico" sizes="256x256">
    <link rel="ms-icon" href="assest/favicon-128x128.ico" sizes="128x128">
    <link rel="icon" type="image/x-icon" href="assest/favicon.ico">
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="css/user_dashboard.css">
    <link rel="stylesheet" href="css/notifications.css">
    <script src="https://maps.googleapis.com/maps/api/js?key=your api key"></script>
    <script src="js/map.js"></script>
    <script src="js/tooltip.js"></script>
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
                    <div class="notification-tabs">
                        <button class="notification-tab-button active" data-tab="notifications">個人通知</button>
                        <button class="notification-tab-button" data-tab="announcements">官方公告</button>
                    </div>
                    <div class="notification-content-wrapper">
                        <div class="notification-content" id="notifications" style="display: block;">
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
                                <ul id="notification-list" class="scrollable">
                                    <?php foreach ($notifications as $note) { ?>
                                        <li class="<?php echo $note['is_read'] ? 'read' : 'unread'; ?>" data-notification-id="<?php echo $note['id']; ?>">
                                            <?php echo htmlspecialchars($note['message']); ?> (<?php echo $note['created_at']; ?>)
                                        </li>
                                    <?php } ?>
                                </ul>
                            <?php } ?>
                        </div>
                        <div class="notification-content" id="announcements" style="display: none;">
                            <?php
                            try {
                                $stmt = $pdo->prepare("
                                    SELECT a.*, u.username, COALESCE(ar.is_read, FALSE) as is_read
                                    FROM announcements a 
                                    JOIN users u ON a.admin_id = u.id 
                                    LEFT JOIN announcement_reads ar ON a.id = ar.announcement_id AND ar.user_id = ?
                                    WHERE a.announcement_type = 'public' 
                                    ORDER BY a.created_at DESC
                                ");
                                $stmt->execute([$user_id]);
                                $announcements = $stmt->fetchAll();
                                foreach ($announcements as $ann) {
                                    $stmt = $pdo->prepare("
                                        INSERT IGNORE INTO announcement_reads (announcement_id, user_id, is_read)
                                        VALUES (?, ?, FALSE)
                                    ");
                                    $stmt->execute([$ann['id'], $user_id]);
                                }
                            } catch (PDOException $e) {
                                $announcements = [];
                                echo "<p class='error'>無法載入公告: " . htmlspecialchars($e->getMessage()) . "</p>";
                            }
                            ?>
                            <?php if (empty($announcements)) { ?>
                                <p>無公開公告</p>
                            <?php } else { ?>
                                <ul class="scrollable">
                                    <?php foreach ($announcements as $ann) { ?>
                                        <li class="announcement <?php echo $ann['is_read'] ? 'read' : ''; ?>" data-announcement-id="<?php echo $ann['id']; ?>">
                                            <div class="announcement-header">
                                                <strong><?php echo htmlspecialchars($ann['username']); ?></strong>
                                                <span class="announcement-time"><?php echo htmlspecialchars($ann['created_at']); ?></span>
                                            </div>
                                            <div class="announcement-content">
                                                <?php echo htmlspecialchars($ann['content']); ?>
                                            </div>
                                        </li>
                                    <?php } ?>
                                </ul>
                            <?php } ?>
                        </div>
                    </div>
                    <div class="mark-read-form" id="mark-all-read-form">
                        <button id="mark-all-read-button" class="mark-read-button">全部標記為已讀</button>
                    </div>
                </div>
            </div>
        </div>
        <script src="js/notifications.js"></script>

        <div class="tabs">
            <?php foreach ($tabs as $tab => $config) { ?>
                <button class="tab-button <?php echo $active_tab === $tab ? 'active' : ''; ?>" onclick="openTab(event, '<?php echo $tab; ?>')">
                    <?php echo htmlspecialchars($config['title']); ?>
                </button>
            <?php } ?>
            <button class="tab-button <?php echo $active_tab === 'profile' ? 'active' : ''; ?>" onclick="openTab(event, 'profile')">個人資料</button>
        </div>

        <?php foreach ($tabs as $tab => $config) { ?>
            <div id="<?php echo $tab; ?>" class="tab-content" style="display: <?php echo $active_tab === $tab ? 'block' : 'none'; ?>">
                <?php if ($tab == 'home') { ?>
                    <div class="header-section">
                        <h2>失物地圖</h2>
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
                <?php } else { ?>
                    <h2><?php echo htmlspecialchars($config['title']); ?></h2>
                    <?php if (in_array($tab, ['lost_items', 'found_items'])) { ?>
                        <button class="submit-item-button" onclick="window.location.href='add_item.php'">提交<?php echo $tab == 'lost_items' ? '遺失' : '拾獲'; ?>物品</button>
                    <?php } ?>
                <?php } ?>
                
<?php
renderFilterForm($tab, $config['filters'], $config['queryParams']);
$sql = applyCustomFilters($config['sql'], $config['filters'], $config['queryParams']);
$queryResult = buildQuery($pdo, $sql, $config['filters'], $config['queryParams'], $tab == 'claim_records' ? [$user_id] : [$user_id]);
$items = $queryResult['items'];
renderTable($pdo, $tab, $items, $user_id, $claimed_items, $config['columns'], $config['actionLogic'], $config['queryParams']);
renderPagination(ceil($queryResult['totalRows'] / $config['queryParams']['per_page']), $config['queryParams']['page'], $config['queryParams']);
if ($queryResult['error']) {
    echo "<p class='error'>查詢失敗: {$queryResult['error']}</p>";
}
?>
            </div>
        <?php } ?>

        <div id="profile" class="tab-content" style="display: <?php echo $active_tab === 'profile' ? 'block' : 'none'; ?>">
            <h2>個人資料</h2>
            <button onclick="window.location.href='user_profile.php'">編輯個人資料</button>
            <button onclick="window.location.href='?tab=profile&export_csv=true'">匯出 CSV</button>
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
                    <tr><td>電話</td><td><?php echo htmlspecialchars($user['phone']); ?></td></tr>
                    <tr><td>地址</td><td><?php echo htmlspecialchars($user['address'] ?: '未設定'); ?></td></tr>
                    <tr><td>加入時間</td><td><?php echo htmlspecialchars($user['created_at'] ?? '未知'); ?></td></tr>
                    <tr><td>修改時間</td><td><?php echo htmlspecialchars($user['updated_at'] ?? '未修改'); ?></td></tr>
                </table>
            </div>
            <button class="logout" onclick="window.location.href='logout.php'">登出</button>
        </div>
    </div>

    <script>
        const currentUserId = <?php echo json_encode($user_id); ?>;
        function openTab(evt, tabName) {
            document.querySelectorAll('.tab-content').forEach(el => el.style.display = 'none');
            document.querySelectorAll('.tab-button').forEach(el => el.classList.remove('active'));
            document.getElementById(tabName).style.display = 'block';
            evt.currentTarget.classList.add('active');
            history.pushState(null, null, '?tab=' + tabName + '&' + new URLSearchParams(window.location.search).toString().replace(/^(?:tab=[^&]*&)?/, ''));
            if (tabName === 'home') {
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
            const activeTab = "<?php echo $active_tab; ?>";
            const tabButton = document.querySelector(`.tab-button[onclick="openTab(event, '${activeTab}')"]`);
            if (tabButton) {
                openTab({ currentTarget: tabButton }, activeTab);
            }
        });
    </script>
</body>
</html>