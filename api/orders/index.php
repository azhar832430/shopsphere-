<?php
/**
 * ShopSphere - List Orders API Endpoint
 * Phase 6: Complete Order Management CRUD
 * 
 * Route: GET /api/orders/index.php
 * Supports: Search (order #, customer name, email), order status filter,
 *           payment status filter, date range filter, allowlisted sorting,
 *           and server-side pagination.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';

// Method check
requireMethod('GET');

try {
    $pdo = Database::getConnection();

    // 1. Parse query parameters
    $page   = max(1, (int) ($_GET['page'] ?? 1));
    $limit  = min(100, max(1, (int) ($_GET['limit'] ?? 10)));
    $offset = ($page - 1) * $limit;

    $search        = sanitizeString($_GET['search'] ?? '');
    $orderStatus   = sanitizeString($_GET['order_status'] ?? ($_GET['status'] ?? ''));
    $paymentStatus = sanitizeString($_GET['payment_status'] ?? '');
    $dateFrom      = sanitizeString($_GET['date_from'] ?? '');
    $dateTo        = sanitizeString($_GET['date_to'] ?? '');
    $sort          = strtolower(sanitizeString($_GET['sort'] ?? 'id'));
    $order         = strtoupper(sanitizeString($_GET['order'] ?? 'DESC'));

    // 2. Sorting Allowlist
    $allowedSortColumns = [
        'id'            => 'o.id',
        'order_number'  => 'o.order_number',
        'number'        => 'o.order_number',
        'order_date'    => 'o.order_date',
        'date'          => 'o.order_date',
        'total_amount'  => 'o.total_amount',
        'total'         => 'o.total_amount',
        'order_status'  => 'o.order_status',
        'status'        => 'o.order_status',
        'payment_status'=> 'o.payment_status',
        'payment'       => 'o.payment_status',
        'customer_name' => 'customer_name',
        'customer'      => 'customer_name',
        'items'         => 'item_count',
        'item_count'    => 'item_count'
    ];

    $sortColumn = $allowedSortColumns[$sort] ?? 'o.id';
    $orderDirection = in_array($order, ['ASC', 'DESC'], true) ? $order : 'DESC';

    // 3. Dynamic Filter Conditions
    $whereConditions = [];
    $params = [];

    if ($search !== '') {
        $whereConditions[] = '(o.order_number LIKE :search_num OR c.full_name LIKE :search_name OR c.email LIKE :search_email)';
        $searchWildcard = '%' . $search . '%';
        $params['search_num']   = $searchWildcard;
        $params['search_name']  = $searchWildcard;
        $params['search_email'] = $searchWildcard;
    }

    if ($orderStatus !== '' && strtolower($orderStatus) !== 'all') {
        $whereConditions[] = 'o.order_status = :order_status';
        $params['order_status'] = $orderStatus;
    }

    if ($paymentStatus !== '' && strtolower($paymentStatus) !== 'all') {
        $whereConditions[] = 'o.payment_status = :payment_status';
        $params['payment_status'] = $paymentStatus;
    }

    if ($dateFrom !== '') {
        $whereConditions[] = 'DATE(o.order_date) >= :date_from';
        $params['date_from'] = $dateFrom;
    }

    if ($dateTo !== '') {
        $whereConditions[] = 'DATE(o.order_date) <= :date_to';
        $params['date_to'] = $dateTo;
    }

    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

    // 4. Count total matching orders for pagination
    $countSql = "
        SELECT COUNT(DISTINCT o.id) 
        FROM orders o 
        LEFT JOIN customers c ON o.customer_id = c.id 
        {$whereClause}
    ";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $totalItems = (int) $countStmt->fetchColumn();
    $totalPages = (int) ceil($totalItems / $limit);
    if ($totalPages < 1) $totalPages = 1;

    // 5. Query order records with customer details and item count
    $sql = "
        SELECT 
            o.id,
            o.order_number,
            o.customer_id,
            o.order_date,
            o.subtotal,
            o.total_amount,
            o.payment_method,
            o.payment_status,
            o.order_status,
            o.notes,
            o.created_at,
            o.updated_at,
            COALESCE(c.full_name, 'Guest Customer') AS customer_name,
            c.email AS customer_email,
            c.phone AS customer_phone,
            COUNT(oi.id) AS item_count,
            COALESCE(SUM(oi.quantity), 0) AS total_quantity
        FROM orders o
        LEFT JOIN customers c ON o.customer_id = c.id
        LEFT JOIN order_items oi ON o.id = oi.order_id
        {$whereClause}
        GROUP BY o.id
        ORDER BY {$sortColumn} {$orderDirection}
        LIMIT :limit OFFSET :offset
    ";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $val) {
        $stmt->bindValue(':' . $key, $val);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $orders = $stmt->fetchAll();

    // Format output
    $formatted = array_map(function ($row) {
        return [
            'id'             => (int) $row['id'],
            'order_number'   => $row['order_number'],
            'customer_id'    => (int) $row['customer_id'],
            'customer_name'  => $row['customer_name'],
            'customer_email' => $row['customer_email'] ?? '',
            'customer_phone' => $row['customer_phone'] ?? '',
            'order_date'     => $row['order_date'],
            'subtotal'       => (float) $row['subtotal'],
            'total_amount'   => (float) $row['total_amount'],
            'payment_method' => $row['payment_method'],
            'payment_status' => $row['payment_status'],
            'order_status'   => $row['order_status'],
            'item_count'     => (int) $row['item_count'],
            'total_quantity' => (int) $row['total_quantity'],
            'notes'          => $row['notes'] ?? '',
            'created_at'     => $row['created_at'],
            'updated_at'     => $row['updated_at']
        ];
    }, $orders);

    // 6. Overall order metrics summary
    $summaryStmt = $pdo->query("
        SELECT 
            COUNT(*) AS total_orders,
            SUM(CASE WHEN order_status = 'pending' THEN 1 ELSE 0 END) AS pending_orders,
            SUM(CASE WHEN order_status = 'processing' THEN 1 ELSE 0 END) AS processing_orders,
            SUM(CASE WHEN order_status = 'shipped' THEN 1 ELSE 0 END) AS shipped_orders,
            SUM(CASE WHEN order_status = 'delivered' THEN 1 ELSE 0 END) AS delivered_orders,
            SUM(CASE WHEN order_status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_orders,
            COALESCE(SUM(CASE WHEN order_status != 'cancelled' THEN total_amount ELSE 0 END), 0.00) AS total_revenue
        FROM orders
    ");
    $summary = $summaryStmt->fetch() ?: [];

    sendSuccess([
        'items'   => $formatted,
        'meta'    => [
            'page'        => $page,
            'limit'       => $limit,
            'total_items' => $totalItems,
            'total_pages' => $totalPages
        ],
        'summary' => [
            'total_orders'      => (int) ($summary['total_orders'] ?? 0),
            'pending_orders'    => (int) ($summary['pending_orders'] ?? 0),
            'processing_orders' => (int) ($summary['processing_orders'] ?? 0),
            'shipped_orders'    => (int) ($summary['shipped_orders'] ?? 0),
            'delivered_orders'  => (int) ($summary['delivered_orders'] ?? 0),
            'cancelled_orders'  => (int) ($summary['cancelled_orders'] ?? 0),
            'total_revenue'     => (float) ($summary['total_revenue'] ?? 0.00)
        ]
    ], 'Orders retrieved successfully.');

} catch (Throwable $e) {
    sendServerError('An error occurred while loading orders.', ['error' => $e->getMessage()]);
}

