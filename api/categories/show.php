<?php
/**
 * ShopSphere - Get Single Category Details API Endpoint
 * Phase 7: Complete Category CRUD
 * 
 * Route: GET /api/categories/show.php?id={id}
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/category_helper.php';

// Method check
requireMethod('GET');

$id   = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$slug = isset($_GET['slug']) ? sanitizeString($_GET['slug']) : '';

if ($id <= 0 && $slug === '') {
    sendError('Valid category ID or slug is required.', ['id' => 'Parameter id or slug is missing.'], 400);
}

try {
    $pdo = Database::getConnection();
    ensureCategorySlugColumn($pdo);

    if ($id > 0) {
        $stmt = $pdo->prepare('SELECT * FROM categories WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
    } else {
        $stmt = $pdo->prepare('SELECT * FROM categories WHERE slug = :slug LIMIT 1');
        $stmt->execute(['slug' => $slug]);
    }

    $category = $stmt->fetch();

    if (!$category) {
        sendNotFound('Category was not found in database.');
    }

    $categoryId = (int) $category['id'];

    // Fetch assigned products
    $prodStmt = $pdo->prepare("
        SELECT id, name, sku, CAST(price AS DOUBLE) AS price, stock, image, status, created_at 
        FROM products 
        WHERE category_id = :cat_id 
        ORDER BY name ASC
    ");
    $prodStmt->execute(['cat_id' => $categoryId]);
    $products = $prodStmt->fetchAll();

    sendSuccess([
        'category' => [
            'id'            => $categoryId,
            'name'          => $category['name'],
            'slug'          => $category['slug'] ?? generateCategorySlug($category['name']),
            'description'   => $category['description'] ?? '',
            'status'        => $category['status'] ?? 'active',
            'product_count' => count($products),
            'created_at'    => $category['created_at'],
            'updated_at'    => $category['updated_at']
        ],
        'products' => $products
    ], 'Category details retrieved successfully.');

} catch (PDOException $e) {
    logSystemError("Database error in categories/show.php (ID: {$id}): " . $e->getMessage());
    sendServerError('Failed to retrieve category details from database.');
} catch (Throwable $e) {
    logSystemError('Unexpected error in categories/show.php: ' . $e->getMessage());
    sendServerError('An unexpected error occurred while loading category details.');
}

