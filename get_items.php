<?php
header('Content-Type: application/json');
require 'config.php';
date_default_timezone_set('Asia/Taipei');

try {
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

    $stmt = $pdo->query("
        SELECT li.*, 
               EXISTS (
                   SELECT 1 
                   FROM claims c 
                   WHERE c.item_id = li.id 
                   AND c.status = 'pending'
               ) AS has_pending_claim
        FROM lost_items li
        WHERE li.approval_status = 'approved'
    ");
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($items as &$item) {
        $item['has_pending_claim'] = (bool)$item['has_pending_claim'];
        $item['image'] = $item['image'] ? UPLOAD_URL . $item['image'] : null;
        if (empty($item['location'])) {
            $item['location'] = null;
        }
    }

    echo json_encode($items);
} catch (PDOException $e) {
    echo json_encode(['error' => '資料庫錯誤: ' . $e->getMessage()]);
}
?>