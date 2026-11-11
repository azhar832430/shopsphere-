<?php
/**
 * ShopSphere - List Categories API Endpoint
 * Phase 7: Complete Category CRUD
 * 
 * Route: GET /api/categories/index.php
 * Supports: Search (name, slug, description), status filter, pagination, sorting, dynamic product counts.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/category_helper.php';

// Method check
requireMethod('GET');

try {
    $pdo = Database::getConnection();
    ensureCategorySlugColumn($pdo);

    // 1. Parse query parameters
    $page   = max(1, (int) ($_GET['page'] ?? 1));
    $limit  = min(100, max(1, (int) ($_GET['limit'] ?? 50)));
    $offset = ($page - 1) * $limit;

    $search = sanitizeString($_GET['search'] ?? '');
    $status = strtolower(sanitizeString($_GET['status'] ?? 'all'));
    $sort   = strtolower(sanitizeString($_GET['sort'] ?? 'name'));
    $order  = strtoupper(sanitizeString($_GET['order'] ?? 'ASC'));

    // 2. Sorting Allowlist
    $allowedSortColumns = [
        'id'            => 'c.id',
        'name'          => 'c.name',
        'slug'          => 'c.slug',
        'status'        => 'c.status',
        'created_at'    => 'c.created_at',
        'product_count' => 'product_count',
        'products'      => 'product_count'
    ];

    $sortSql = $allowedSortColumns[$sort] ?? 'c.name';
    $orderSql = in_array($order, ['ASC', 'DESC'], true) ? $order : 'ASC';

    // 3. Dynamic Filter Conditions
    $whereConditions = [];
    $params = [];

    if ($search !== '') {
        $whereConditions[] = '(c.name LIKE :search_name OR c.slug LIKE :search_slug OR c.description LIKE :search_desc)';
        $searchParam = '%' . $search . '%';
        $params['search_name'] = $searchParam;
        $params['search_slug'] = $searchParam;
        $params['search_desc'] = $searchParam;
    }

    if ($status !== 'all' && in_array($status, ['active', 'inactive'], true)) {
        $whereConditions[] = 'c.status = :status';
        $params['status'] = $status;
    }

    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

    // 4. Count total matching categories
    $countSql = "SELECT COUNT(*) FROM categories c {$whereClause}";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $totalItems = (int) $countStmt->fetchColumn();
    $totalPages = (int) ceil($totalItems / $limit);
    if ($totalPages < 1) $totalPages = 1;

    // 5. Query Categories with dynamic product count join
    $sql = "
        SELECT 
            c.id,
            c.name,
            c.slug,
            c.description,
            c.status,
            c.created_at,
            c.updated_at,
            COUNT(p.id) AS product_count
        FROM categories c
        LEFT JOIN products p ON c.id = p.category_id
        {$whereClause}
        GROUP BY c.id
        ORDER BY {$sortSql} {$orderSql}
        LIMIT :limit OFFSET :offset
    ";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue(':' . $k, $v);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rawCategories = $stmt->fetchAll();

    $categories = array_map(function ($row) {
        return [
            'id'            => (int) $row['id'],
            'name'          => $row['name'],
            'slug'          => $row['slug'] ?? generateCategorySlug($row['name']),
            'description'   => $row['description'] ?? '',
            'status'        => $row['status'] ?? 'active',
            'product_count' => (int) $row['product_count'],
            'created_at'    => $row['created_at'],
            'updated_at'    => $row['updated_at']
        ];
    }, $rawCategories);

    // 6. Summary metrics
    $summaryStmt = $pdo->query("
        SELECT 
            COUNT(*) AS total_categories,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active_categories,
            SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) AS inactive_categories
        FROM categories
    ");
    $summary = $summaryStmt->fetch() ?: [];

    sendSuccess([
        'items'      => $categories,
        'categories' => $categories, // backward-compatibility alias
        'meta'       => [
            'page'        => $page,
            'limit'       => $limit,
            'total_items' => $totalItems,
            'total_pages' => $totalPages
        ],
        'summary'    => [
            'total'    => (int) ($summary['total_categories'] ?? 0),
            'active'   => (int) ($summary['active_categories'] ?? 0),
            'inactive' => (int) ($summary['inactive_categories'] ?? 0)
        ]
    ], 'Categories retrieved successfully.');

} catch (PDOException $e) {
    logSystemError('Database error in categories/index.php: ' . $e->getMessage());
    sendServerError('Failed to retrieve categories from database.');
} catch (Throwable $e) {
    logSystemError('Unexpected error in categories/index.php: ' . $e->getMessage());
    sendServerError('An unexpected error occurred while loading categories.');
}

