<?php
/**
 * ShopSphere - View Single Product Details
 * Phase 4: Product Management CRUD
 * 
 * Route: GET /api/products/show.php?id=1
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/helpers.php';

// Method check
requireMethod('GET');

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id <= 0) {
    sendError('Invalid or missing product ID.', ['id' => 'Valid product ID is required.'], 400);
}

try {
    $pdo = Database::getConnection();

    $stmt = $pdo->prepare('
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
            COALESCE(c.name, "Uncategorized") AS category_name,
            c.description AS category_description
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.id = :id
        LIMIT 1
    ');

    $stmt->execute(['id' => $id]);
    $product = $stmt->fetch();

    if (!$product) {
        sendNotFound("Product with ID {$id} was not found.");
    }

    sendSuccess('Product details retrieved successfully.', [
        'product' => $product
    ]);

} catch (PDOException $e) {
    logSystemError("Database error in products/show.php (ID: {$id}): " . $e->getMessage());
    sendServerError('Failed to fetch product details.');
} catch (Throwable $e) {
    logSystemError("Unexpected error in products/show.php: " . $e->getMessage());
    sendServerError('An unexpected error occurred while loading product details.');
}
