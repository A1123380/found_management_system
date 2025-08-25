<?php
if (!defined('PAGE_SIZES')) {
    define('PAGE_SIZES', [5, 10, 20, 50]);
}
if (!defined('DEFAULT_PAGE_SIZE')) {
    define('DEFAULT_PAGE_SIZE', 20);
}

function renderFilterForm($tab, $filters, $queryParams) {
    ?>
    <form method="GET" id="<?php echo htmlspecialchars($tab); ?>FilterForm">
        <input type="hidden" name="tab" value="<?php echo htmlspecialchars($tab); ?>">
        <div class="filter-row">
            <select name="<?php echo htmlspecialchars($queryParams['per_page_param']); ?>">
                <?php foreach (PAGE_SIZES as $size) { ?>
                    <option value="<?php echo $size; ?>" <?php echo ($queryParams['per_page'] == $size) ? 'selected' : ''; ?>>
                        <?php echo $size; ?> 筆
                    </option>
                <?php } ?>
            </select>
            <?php foreach ($filters as $filter) { ?>
                <?php
                $paramKey = $filter['name'];
                if (array_key_exists($filter['name'], $queryParams)) {
                    $paramKey = $filter['name'];
                } elseif (array_key_exists($queryParams['search_status_param'] ?? '', $queryParams) && $filter['name'] == $queryParams['search_status_param']) {
                    $paramKey = 'search_status';
                } elseif (array_key_exists($queryParams['search_approval_param'] ?? '', $queryParams) && $filter['name'] == $queryParams['search_approval_param']) {
                    $paramKey = 'search_approval';
                } elseif (array_key_exists($queryParams['search_type_param'] ?? '', $queryParams) && $filter['name'] == $queryParams['search_type_param']) {
                    $paramKey = 'search_type';
                } elseif (array_key_exists($queryParams['search_keyword_param'] ?? '', $queryParams) && $filter['name'] == $queryParams['search_keyword_param']) {
                    $paramKey = 'search_keyword';
                }
                $filterValue = $queryParams[$paramKey] ?? '';
                ?>
                <?php if ($filter['type'] == 'select') { ?>
                    <select name="<?php echo htmlspecialchars($filter['name']); ?>">
                        <?php foreach ($filter['options'] as $value => $label) { ?>
                            <option value="<?php echo htmlspecialchars($value); ?>" 
                                    <?php echo $filterValue == $value ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($label); ?>
                            </option>
                        <?php } ?>
                    </select>
                <?php } elseif ($filter['type'] == 'text') { ?>
                    <input type="text" name="<?php echo htmlspecialchars($filter['name']); ?>" 
                           value="<?php echo htmlspecialchars($filterValue); ?>" 
                           placeholder="<?php echo htmlspecialchars($filter['placeholder']); ?>">
                <?php } ?>
            <?php } ?>
            <button type="submit">搜尋</button>
            <button type="button" onclick="window.location.href='?tab=<?php echo htmlspecialchars($tab); ?>&per_page=20'">重製篩選</button>
        </div>
    </form>
    <?php
}

function buildQuery($pdo, $baseSql, $filters, $queryParams, $defaultParams = [], $tab) {
    $sql = $baseSql;
    $params = $defaultParams;
    $whereClauses = [];

    foreach ($filters as $filter) {
        if (!empty($queryParams[$filter['name']])) {
            $whereClauses[] = $filter['condition'];
            if (strpos($filter['condition'], 'LIKE') !== false) {
                $searchTerm = "%{$queryParams[$filter['name']]}%";
                $count = substr_count($filter['condition'], '?');
                for ($i = 0; $i < $count; $i++) {
                    $params[] = $searchTerm;
                }
            } else {
                $params[] = $queryParams[$filter['name']];
            }
        }
    }

    if (!empty($whereClauses)) {
        if (stripos($baseSql, 'WHERE') !== false) {
            $sql .= " AND " . implode(" AND ", $whereClauses);
        } else {
            $sql .= " WHERE " . implode(" AND ", $whereClauses);
        }
    }

    $allowedSortColumns = [
        'manage_items' => [
            'id' => 'li.id',
            'item_type' => 'li.item_type',
            'status' => 'li.status',
            'approval_status' => 'li.approval_status',
            'title' => 'li.title',
            'created_at' => 'li.created_at',
            'updated_at' => 'li.updated_at',
            'approved_at' => 'li.approved_at',
            'ended_at' => 'li.ended_at'
        ],
        'review_items' => [
            'id' => 'li.id',
            'item_type' => 'li.item_type',
            'title' => 'li.title',
            'created_at' => 'li.created_at',
            'updated_at' => 'li.updated_at',
            'approved_at' => 'li.approved_at',
            'ended_at' => 'li.ended_at'
        ],
        'manage_claims' => [
            'id' => 'c.id',
            'created_at' => 'c.created_at'
        ],
        'claim_records' => [
            'item_id' => 'c.item_id',
            'claimed_at' => 'c.updated_at',
            'title' => 'li.title',
            'owner_sID' => 'u2.sID',
            'claimant_sID' => 'u1.sID'
        ],
        'review_users' => [
            'created_at' => 'created_at'
        ],
        'manage_users' => [
            'created_at' => 'created_at',
            'updated_at' => 'updated_at'
        ],
        'announcements' => [
            'id' => 'a.id',
            'created_at' => 'a.created_at'
        ]
    ];

    $sortColumnKey = $queryParams['sort_column'] ?? ($tab === 'manage_users' || $tab === 'review_users' || $tab === 'announcements' ? 'created_at' : 'id');
    $sortColumn = $allowedSortColumns[$tab][$sortColumnKey] ?? $allowedSortColumns[$tab][array_key_first($allowedSortColumns[$tab])];
    $sortOrder = strtoupper($queryParams['sort_order'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';
    $sql .= " ORDER BY $sortColumn $sortOrder";

    $countSqlBase = str_replace(['SELECT li.*', 'SELECT c.*, li.title, u1.sID AS claimant_sID, u1.username AS claimant, u2.sID AS owner_sID, u2.username AS owner', 'SELECT c.item_id, c.updated_at AS claimed_at, li.title, u1.sID AS claimant_sID, u1.username AS claimant, u2.sID AS owner_sID, u2.username AS owner', 'SELECT *', 'SELECT a.*, u.username'], 'SELECT 1', $baseSql);
    $countSql = "SELECT COUNT(*) FROM (" . $countSqlBase;
    if (!empty($whereClauses)) {
        if (stripos($baseSql, 'WHERE') !== false) {
            $countSql .= " AND " . implode(" AND ", $whereClauses);
        } else {
            $countSql .= " WHERE " . implode(" AND ", $whereClauses);
        }
    }
    $countSql .= ") AS count_table";
    try {
        $stmt = $pdo->prepare($countSql);
        $stmt->execute($params);
        $totalRows = $stmt->fetchColumn();
    } catch (PDOException $e) {
        logError("計數查詢失敗: " . $e->getMessage() . " | SQL: $countSql | 參數: " . json_encode($params));
        return ['items' => [], 'totalRows' => 0, 'error' => htmlspecialchars($e->getMessage())];
    }

    $page = max(1, (int)($queryParams['page'] ?? 1));
    $perPage = in_array((int)($queryParams['per_page'] ?? DEFAULT_PAGE_SIZE), PAGE_SIZES) ? (int)($queryParams['per_page'] ?? DEFAULT_PAGE_SIZE) : DEFAULT_PAGE_SIZE;
    $offset = ($page - 1) * $perPage;
    $sql .= " LIMIT $perPage OFFSET $offset";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return ['items' => $items, 'totalRows' => $totalRows, 'error' => null, 'page' => $page, 'perPage' => $perPage];
    } catch (PDOException $e) {
        logError("查詢失敗: " . $e->getMessage() . " | SQL: $sql | 參數: " . json_encode($params));
        return ['items' => [], 'totalRows' => 0, 'error' => htmlspecialchars($e->getMessage()), 'page' => $page, 'perPage' => $perPage];
    }
}

function renderTable($pdo, $tableId, $items, $user_id, $claimed_items, $columns, $actionLogic, $sortParams) {
    ?>
    <div class="table-container">
        <table id="<?php echo htmlspecialchars($tableId); ?>" class="data-table">
            <thead>
                <tr>
                    <?php foreach ($columns as $col) { ?>
                        <th class="<?php echo isset($col['class']) ? htmlspecialchars($col['class']) : ''; ?>">
                            <?php if (isset($col['sort'])) { ?>
                                <a href="?<?php
                                    $params = $sortParams;
                                    $params[$sortParams['sort_column_param']] = $col['sort'];
                                    $params[$sortParams['sort_order_param']] = ($sortParams['sort_column'] == $col['sort'] && $sortParams['sort_order'] == 'ASC') ? 'DESC' : 'ASC';
                                    echo http_build_query($params);
                                ?>">
                                    <?php echo htmlspecialchars($col['label']); ?>
                                    <?php if ($sortParams['sort_column'] == $col['sort']) { ?>
                                        <?php echo $sortParams['sort_order'] == 'ASC' ? '▼' : '▲'; ?>
                                    <?php } ?>
                                </a>
                            <?php } else { ?>
                                <?php echo htmlspecialchars($col['label']); ?>
                            <?php } ?>
                        </th>
                    <?php } ?>
                    <?php if ($actionLogic !== null) { ?>
                        <th class="action-column">操作</th>
                    <?php } ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)) { ?>
                    <tr><td colspan="<?php echo count($columns) + ($actionLogic !== null ? 1 : 0); ?>">無資料</td></tr>
                <?php } else { ?>
                    <?php foreach ($items as $item) { ?>
                        <tr>
                            <?php foreach ($columns as $col) { ?>
                                <td class="<?php
                                    $class = isset($col['class']) ? htmlspecialchars($col['class']) : '';
                                    if (isset($col['class_callback']) && is_callable($col['class_callback'])) {
                                        $class .= ' ' . htmlspecialchars($col['class_callback']($item));
                                    }
                                    echo trim($class);
                                ?>">
                                    <?php
                                    if (isset($col['value']) && is_callable($col['value'])) {
                                        echo $col['value']($item);
                                    } else {
                                        echo htmlspecialchars($item[$col['key']] ?? $col['default'] ?? '未知');
                                    }
                                    ?>
                                </td>
                            <?php } ?>
                            <?php if ($actionLogic !== null) { ?>
                                <td class="action-column">
                                    <?php echo $actionLogic($item, $user_id, $claimed_items); ?>
                                </td>
                            <?php } ?>
                        </tr>
                    <?php } ?>
                <?php } ?>
            </tbody>
        </table>
    </div>
    <?php
}

function renderPagination($totalPages, $page, $queryParams) {
    if ($totalPages <= 1) return;
    ?>
    <div class="pagination">
        <?php if ($page > 1) { ?>
            <?php $queryParams['page'] = $page - 1; ?>
            <a href="?<?php echo http_build_query($queryParams); ?>" class="prev">上一頁</a>
        <?php } ?>
        <?php
        $range = 2;
        $start = max(1, $page - $range);
        $end = min($totalPages, $page + $range);
        if ($start > 1) echo '<a href="?' . http_build_query(array_merge($queryParams, ['page' => 1])) . '">1</a>';
        if ($start > 2) echo '<span>...</span>';
        for ($i = $start; $i <= $end; $i++) { ?>
            <?php $queryParams['page'] = $i; ?>
            <a href="?<?php echo http_build_query($queryParams); ?>" class="<?php echo $page == $i ? 'active' : ''; ?>">
                <?php echo $i; ?>
            </a>
        <?php } ?>
        <?php if ($end < $totalPages - 1) echo '<span>...</span>'; ?>
        <?php if ($end < $totalPages) echo '<a href="?' . http_build_query(array_merge($queryParams, ['page' => $totalPages])) . '">' . $totalPages . '</a>'; ?>
        <?php if ($page < $totalPages) { ?>
            <?php $queryParams['page'] = $page + 1; ?>
            <a href="?<?php echo http_build_query($queryParams); ?>" class="next">下一頁</a>
        <?php } ?>
    </div>
    <?php
}
?>