<?php
/**
 * ShopSphere - List Customers API Endpoint
 * Phase 5: Complete Customer Management CRUD
 * 
 * Route: GET /api/customers/index.php
 * Supports: Search (name, email, phone, city), city filter, status filter,
 *           sorting (name, created_at, orders, spent, etc.), server-side pagination.
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

    $search = sanitizeString($_GET['search'] ?? '');
    $city   = sanitizeString($_GET['city'] ?? '');
    $status = sanitizeString($_GET['status'] ?? '');
    $sort   = strtolower(sanitizeString($_GET['sort'] ?? 'id'));
    $order  = strtoupper(sanitizeString($_GET['order'] ?? 'DESC'));

    // 2. Sorting Allowlist
    $allowedSortColumns = [
        'id'           => 'c.id',
        'full_name'    => 'c.full_name',
        'name'         => 'c.full_name',
        'email'        => 'c.email',
        'city'         => 'c.city',
        'status'       => 'c.status',
        'created_at'   => 'c.created_at',
        'newest'       => 'c.id',
        'total_orders' => 'total_orders',
        'orders'       => 'total_orders',
        'total_spent'  => 'total_spent',
        'spent'        => 'total_spent'
    ];

    $sortColumn = $allowedSortColumns[$sort] ?? 'c.id';
    $orderDirection = in_array($order, ['ASC', 'DESC'], true) ? $order : 'DESC';

    // 3. Dynamic Filtering
    $whereConditions = [];
    $params = [];

    if ($search !== '') {
        $whereConditions[] = '(c.full_name LIKE :search_name OR c.email LIKE :search_email OR c.phone LIKE :search_phone OR c.city LIKE :search_city)';
        $searchWildcard = '%' . $search . '%';
        $params['search_name']  = $searchWildcard;
        $params['search_email'] = $searchWildcard;
        $params['search_phone'] = $searchWildcard;
        $params['search_city']  = $searchWildcard;
    }

    if ($city !== '' && strtolower($city) !== 'all') {
        $whereConditions[] = 'c.city = :city';
        $params['city'] = $city;
    }

    if ($status !== '' && strtolower($status) !== 'all') {
        $whereConditions[] = 'c.status = :status';
        $params['status'] = $status;
    }

    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

    // 4. Count total matching rows
    $countSql = "SELECT COUNT(*) FROM customers c {$whereClause}";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $totalItems = (int) $countStmt->fetchColumn();
    $totalPages = (int) ceil($totalItems / $limit);
    if ($totalPages < 1) $totalPages = 1;

    // 5. Query customer records with aggregated order metrics
    $sql = "
        SELECT 
            c.id,
            c.full_name,
            c.email,
            c.phone,
            c.address,
            c.city,
            c.postal_code,
            c.status,
            c.created_at,
            c.updated_at,
            COUNT(o.id) AS total_orders,
            COALESCE(SUM(CASE WHEN o.order_status != 'cancelled' THEN o.total_amount ELSE 0 END), 0.00) AS total_spent
        FROM customers c
        LEFT JOIN orders o ON c.id = o.customer_id
        {$whereClause}
        GROUP BY c.id
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
    $customers = $stmt->fetchAll();

    // Format output types
    $formatted = array_map(function ($row) {
        return [
            'id'           => (int) $row['id'],
            'full_name'    => $row['full_name'],
            'email'        => $row['email'],
            'phone'        => $row['phone'] ?? '',
            'address'      => $row['address'] ?? '',
            'city'         => $row['city'] ?? '',
            'postal_code'  => $row['postal_code'] ?? '',
            'status'       => $row['status'] ?? 'active',
            'created_at'   => $row['created_at'],
            'updated_at'   => $row['updated_at'],
            'total_orders' => (int) $row['total_orders'],
            'total_spent'  => (float) $row['total_spent']
        ];
    }, $customers);

    // 6. Fetch distinct non-empty cities for filter dropdown
    $citiesStmt = $pdo->query("SELECT DISTINCT city FROM customers WHERE city IS NOT NULL AND TRIM(city) != '' ORDER BY city ASC");
    $cities = $citiesStmt->fetchAll(PDO::FETCH_COLUMN);

    // 7. Status summary counts
    $summaryStmt = $pdo->query("
        SELECT 
            COUNT(*) AS total,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active,
            SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) AS inactive,
            SUM(CASE WHEN status = 'blocked' THEN 1 ELSE 0 END) AS blocked
        FROM customers
    ");
    $summary = $summaryStmt->fetch() ?: ['total' => 0, 'active' => 0, 'inactive' => 0, 'blocked' => 0];

    sendSuccess([
        'items'      => $formatted,
        'meta'       => [
            'page'        => $page,
            'limit'       => $limit,
            'total_items' => $totalItems,
            'total_pages' => $totalPages
        ],
        'cities'     => $cities,
        'summary'    => [
            'total'    => (int) ($summary['total'] ?? 0),
            'active'   => (int) ($summary['active'] ?? 0),
            'inactive' => (int) ($summary['inactive'] ?? 0),
            'blocked'  => (int) ($summary['blocked'] ?? 0)
        ]
    ], 'Customer records retrieved successfully.');

} catch (Throwable $e) {
    sendServerError('An error occurred while loading customer records.', ['error' => $e->getMessage()]);
}

