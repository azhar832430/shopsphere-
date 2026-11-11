<?php
/**
 * ShopSphere - List & Filter Products
 * Phase 4: Product Management CRUD
 * 
 * Route: GET /api/products/index.php
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/helpers.php';

// Method check
requireMethod('GET');

try {
    $pdo = Database::getConnection();

    // 1. Sanitize query parameters
    $search      = sanitizeString($_GET['search'] ?? '');
    $categoryId  = isset($_GET['category_id']) && $_GET['category_id'] !== 'all' ? (int) $_GET['category_id'] : null;
    $status      = sanitizeString($_GET['status'] ?? 'all');
    $stockStatus = sanitizeString($_GET['stock_status'] ?? 'all');

    $sortColumn = sanitizeString($_GET['sort'] ?? 'id');
    $sortOrder  = strtoupper(sanitizeString($_GET['order'] ?? 'DESC'));

    $page  = max(1, (int) ($_GET['page'] ?? 1));
    $limit = min(100, max(1, (int) ($_GET['limit'] ?? 8)));
    $offset = ($page - 1) * $limit;

    // 2. Sorting Allowlist Validation
    $allowedSortColumns = [
        'id'         => 'p.id',
        'name'       => 'p.name',
        'price'      => 'p.price',
        'stock'      => 'p.stock',
        'created_at' => 'p.created_at',
        'sku'        => 'p.sku'
    ];

    $sortSql = $allowedSortColumns[$sortColumn] ?? 'p.id';
    $orderSql = ($sortOrder === 'ASC') ? 'ASC' : 'DESC';

    // 3. Build WHERE Clauses
    $whereConditions = [];
    $params = [];

    // Search filter
    if ($search !== '') {
        $whereConditions[] = '(p.name LIKE :search_name OR p.sku LIKE :search_sku OR p.description LIKE :search_desc)';
        $searchParam = '%' . $search . '%';
        $params['search_name'] = $searchParam;
        $params['search_sku']  = $searchParam;
        $params['search_desc'] = $searchParam;
    }

    // Category filter
    if ($categoryId !== null && $categoryId > 0) {
        $whereConditions[] = 'p.category_id = :category_id';
        $params['category_id'] = $categoryId;
    }

    // Status filter
    if ($status !== 'all' && in_array($status, ['active', 'inactive'], true)) {
        $whereConditions[] = 'p.status = :status';
        $params['status'] = $status;
    }

    // Stock Status filter
    if ($stockStatus === 'in_stock') {
        $whereConditions[] = 'p.stock > 0';
    } elseif ($stockStatus === 'low_stock') {
        $whereConditions[] = 'p.stock > 0 AND p.stock <= 10';
    } elseif ($stockStatus === 'out_of_stock') {
        $whereConditions[] = 'p.stock = 0';
    }

    $whereSql = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

    // 4. Count Total Matching Records
    $countSql = "SELECT COUNT(*) FROM products p {$whereSql}";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $totalItems = (int) $countStmt->fetchColumn();
    $totalPages = (int) ceil($totalItems / $limit);

    // 5. Fetch Paginated Records
    $querySql = "
        SELECT 
            p.id,
            p.category_id,
            p.name,
            p.sku,
            p.description,
            CAST(p.price AS DOUBLE) AS price,
            p.stock,
            p.image,
            p.status,
            p.created_at,
            p.updated_at,
            COALESCE(c.name, 'Uncategorized') AS category_name
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        {$whereSql}
        ORDER BY {$sortSql} {$orderSql}
        LIMIT :limit OFFSET :offset
    ";

    $stmt = $pdo->prepare($querySql);

    // Bind filter parameters
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    // Bind pagination integers explicitly
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

    $stmt->execute();
    $products = $stmt->fetchAll();

    // 6. Also load available categories for UI dropdowns
    $catStmt = $pdo->query("SELECT id, name, status FROM categories WHERE status = 'active' ORDER BY name ASC");
    $categories = $catStmt->fetchAll();

    sendSuccess('Products retrieved successfully.', [
        'products' => $products,
        'pagination' => [
            'total_items'  => $totalItems,
            'total_pages'  => max(1, $totalPages),
            'current_page' => $page,
            'limit'        => $limit
        ],
        'categories' => $categories
    ]);

} catch (PDOException $e) {
    logSystemError('Database error in products/index.php: ' . $e->getMessage());
    sendServerError('Failed to retrieve products catalog from database.');
} catch (Throwable $e) {
    logSystemError('Unexpected error in products/index.php: ' . $e->getMessage());
    sendServerError('An unexpected error occurred while loading products.');
}

