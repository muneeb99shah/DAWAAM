<?php
/**
 * Dawaam - Local Business Continuity Software
 * High-Performance Server-Side Pagination & Keyset Helper Library
 */

/**
 * Fetch Server-Side Paginated Dataset using PDO Prepared Statements
 *
 * @param PDO $pdo
 * @param array $options
 * @return array ['data' => array, 'pagination' => array]
 */
function get_paginated_data(PDO $pdo, array $options) {
    $table = $options['table'] ?? '';
    $select_fields = $options['select_fields'] ?? '*';
    $raw_where = trim($options['where_clause'] ?? '');
    $group_by = trim($options['group_by'] ?? '');
    $having = trim($options['having'] ?? '');
    $params = $options['params'] ?? [];
    $order_by = $options['order_by'] ?? 'id DESC';
    $count_field = $options['count_field'] ?? '*';

    // Legacy Protection: Extract GROUP BY if caller embedded it inside where_clause
    if (empty($group_by) && stripos($raw_where, 'GROUP BY') !== false) {
        $parts = preg_split('/GROUP\s+BY/i', $raw_where, 2);
        $where_clause = trim($parts[0]);
        $group_by = trim($parts[1]);
    } else {
        $where_clause = $raw_where;
    }

    // Page and Limit Calculations (Enforce strict safe boundaries)
    $page = max(1, (int)($options['page'] ?? 1));
    $requested_limit = (int)($options['limit'] ?? 15);
    $limit = max(5, min(100, $requested_limit)); // Strict server-side cap at 100 max records per payload

    $where_sql = !empty($where_clause) ? "WHERE " . $where_clause : "";
    $group_sql = !empty($group_by) ? "GROUP BY " . $group_by : "";
    $having_sql = !empty($having) ? "HAVING " . $having : "";

    // 1. Efficient Count Query
    if (!empty($having)) {
        $count_query = "SELECT COUNT(*) FROM (SELECT 1 FROM {$table} {$where_sql} {$group_sql} {$having_sql}) AS count_sub";
    } else {
        $count_query = "SELECT COUNT({$count_field}) FROM {$table} {$where_sql}";
    }

    $stmt_count = $pdo->prepare($count_query);
    $stmt_count->execute($params);
    $total_records = (int)$stmt_count->fetchColumn();

    $total_pages = max(1, (int)ceil($total_records / $limit));
    if ($page > $total_pages) {
        $page = $total_pages;
    }

    $offset = ($page - 1) * $limit;

    // 2. Fetch Only Current Page Records using Deferred Join Subquery Pattern or standard paginated query
    if (empty($where_clause) && empty($group_by) && strpos(strtolower($table), 'join') !== false && isset($options['primary_key'])) {
        $pk = $options['primary_key']; // e.g. 's.id'
        $main_table = explode(' ', trim($table))[0]; // e.g. 'sales'
        
        $data_query = "
            SELECT {$select_fields} 
            FROM (
                SELECT id FROM {$main_table} ORDER BY id DESC LIMIT :limit_val OFFSET :offset_val
            ) AS page_sub
            INNER JOIN {$table} ON page_sub.id = {$pk}
            ORDER BY {$order_by}
        ";
    } else {
        $data_query = "SELECT {$select_fields} FROM {$table} {$where_sql} {$group_sql} {$having_sql} ORDER BY {$order_by} LIMIT :limit_val OFFSET :offset_val";
    }

    $stmt_data = $pdo->prepare($data_query);

    // Bind parameters
    foreach ($params as $param_key => $param_val) {
        $stmt_data->bindValue($param_key, $param_val);
    }
    $stmt_data->bindValue(':limit_val', $limit, PDO::PARAM_INT);
    $stmt_data->bindValue(':offset_val', $offset, PDO::PARAM_INT);

    $stmt_data->execute();
    $data = $stmt_data->fetchAll();

    $start_record = $total_records > 0 ? ($offset + 1) : 0;
    $end_record = min($offset + $limit, $total_records);

    return [
        'data' => $data,
        'pagination' => [
            'current_page' => $page,
            'limit' => $limit,
            'total_records' => $total_records,
            'total_pages' => $total_pages,
            'start_record' => $start_record,
            'end_record' => $end_record,
            'has_prev' => ($page > 1),
            'has_next' => ($page < $total_pages),
            'prev_page' => max(1, $page - 1),
            'next_page' => min($total_pages, $page + 1)
        ]
    ];
}

/**
 * Fetch High-Volume Keyset / Cursor Paginated Dataset (O(1) performance for millions of records)
 *
 * @param PDO $pdo
 * @param array $options
 * @return array ['data' => array, 'next_cursor' => int|null]
 */
function get_cursor_paginated_data(PDO $pdo, array $options) {
    $table = $options['table'] ?? '';
    $select_fields = $options['select_fields'] ?? '*';
    $cursor = (int)($options['cursor'] ?? 0); // last seen primary ID
    $limit = max(5, min(100, (int)($options['limit'] ?? 25)));
    $where_clause = $options['where_clause'] ?? '';
    $params = $options['params'] ?? [];

    $conditions = [];
    if (!empty($where_clause)) {
        $conditions[] = "({$where_clause})";
    }
    if ($cursor > 0) {
        $conditions[] = "id < :last_cursor_id";
        $params[':last_cursor_id'] = $cursor;
    }

    $where_sql = count($conditions) > 0 ? "WHERE " . implode(" AND ", $conditions) : "";
    $query = "SELECT {$select_fields} FROM {$table} {$where_sql} ORDER BY id DESC LIMIT :limit_val";

    $stmt = $pdo->prepare($query);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->bindValue(':limit_val', $limit, PDO::PARAM_INT);
    $stmt->execute();

    $rows = $stmt->fetchAll();
    $next_cursor = count($rows) > 0 ? (int)end($rows)['id'] : null;

    return [
        'data' => $rows,
        'next_cursor' => $next_cursor
    ];
}

/**
 * Render Standardized Responsive Pagination Controls UI
 *
 * @param array $pagination
 * @param string $base_url
 * @param array $query_params
 * @return string HTML
 */
function render_pagination_links(array $pagination, string $base_url, array $query_params = []) {
    if ($pagination['total_pages'] <= 1) {
        return '<div class="d-flex justify-content-between align-items-center small text-muted px-3 py-2">
                    <span>Showing all ' . number_format($pagination['total_records']) . ' records</span>
                </div>';
    }

    $current_page = $pagination['current_page'];
    $total_pages = $pagination['total_pages'];

    $build_url = function($target_page) use ($base_url, $query_params) {
        $params = array_merge($query_params, ['page' => $target_page]);
        return $base_url . '?' . http_build_query($params);
    };

    $html = '<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 px-3 py-3 border-top bg-light bg-opacity-50 small">';
    
    // Status text
    $html .= '<div class="text-muted fw-medium">';
    $html .= 'Showing <strong class="text-dark">' . number_format($pagination['start_record']) . '</strong> to <strong class="text-dark">' . number_format($pagination['end_record']) . '</strong> of <strong class="text-dark">' . number_format($pagination['total_records']) . '</strong> records';
    $html .= '</div>';

    // Pagination Links List
    $html .= '<nav aria-label="Page navigation">';
    $html .= '<ul class="pagination pagination-sm mb-0 shadow-sm">';

    // Previous Button
    if ($pagination['has_prev']) {
        $html .= '<li class="page-item"><a class="page-item-link page-link" href="' . htmlspecialchars($build_url($pagination['prev_page'])) . '"><i class="bi bi-chevron-left"></i> Previous</a></li>';
    } else {
        $html .= '<li class="page-item disabled"><span class="page-link text-muted"><i class="bi bi-chevron-left"></i> Previous</span></li>';
    }

    // Page range logic
    $range = 2;
    $start_p = max(1, $current_page - $range);
    $end_p = min($total_pages, $current_page + $range);

    if ($start_p > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="' . htmlspecialchars($build_url(1)) . '">1</a></li>';
        if ($start_p > 2) {
            $html .= '<li class="page-item disabled"><span class="page-link text-muted">&hellip;</span></li>';
        }
    }

    for ($i = $start_p; $i <= $end_p; $i++) {
        if ($i === $current_page) {
            $html .= '<li class="page-item active" aria-current="page"><span class="page-link fw-bold bg-emerald border-emerald text-white" style="background-color:#059669; border-color:#059669;">' . $i . '</span></li>';
        } else {
            $html .= '<li class="page-item"><a class="page-link text-dark" href="' . htmlspecialchars($build_url($i)) . '">' . $i . '</a></li>';
        }
    }

    if ($end_p < $total_pages) {
        if ($end_p < $total_pages - 1) {
            $html .= '<li class="page-item disabled"><span class="page-link text-muted">&hellip;</span></li>';
        }
        $html .= '<li class="page-item"><a class="page-link" href="' . htmlspecialchars($build_url($total_pages)) . '">' . $total_pages . '</a></li>';
    }

    // Next Button
    if ($pagination['has_next']) {
        $html .= '<li class="page-item"><a class="page-link" href="' . htmlspecialchars($build_url($pagination['next_page'])) . '">Next <i class="bi bi-chevron-right"></i></a></li>';
    } else {
        $html .= '<li class="page-item disabled"><span class="page-link text-muted">Next <i class="bi bi-chevron-right"></i></span></li>';
    }

    $html .= '</ul>';
    $html .= '</nav>';
    $html .= '</div>';

    return $html;
}
