<?php
/**
 * ShopSphere - Product Statistics & Telemetry
 * Phase 4: Product Management CRUD / Dashboard Integration
 * 
 * Route: GET /api/products/stats.php
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/helpers.php';

// Method check
requireMethod('GET');

try {
    $pdo = Database::getConnection();

    // Query aggregate product counts
    $statsStmt = $pdo->query('
        SELECT 
            COUNT(*) AS total_products,
            COUNT(CASE WHEN status = "active" THEN 1 END) AS active_products,
            COUNT(CASE WHEN stock > 0 AND stock <= 10 THEN 1 END) AS low_stock_products,
            COUNT(CASE WHEN stock = 0 THEN 1 END) AS out_of_stock_products,
            ROUND(AVG(price), 2) AS average_price
        FROM products
    ');
    $summary = $statsStmt->fetch();

    // Query items needing restock
    $lowStockStmt = $pdo->query('
        SELECT 
            p.id, 
            p.name, 
            p.sku, 
            p.stock, 
            CAST(p.price AS DOUBLE) as price, 
            p.image,
            COALESCE(c.name, "Uncategorized") as category_name
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.stock <= 10
        ORDER BY p.stock ASC
        LIMIT 5
    ');
    $lowStockItems = $lowStockStmt->fetchAll();

    sendSuccess('Product statistics retrieved successfully.', [
        'total_products'        => (int) $summary['total_products'],
        'active_products'       => (int) $summary['active_products'],
        'low_stock_products'    => (int) $summary['low_stock_products'],
        'out_of_stock_products' => (int) $summary['out_of_stock_products'],
        'average_price'         => (float) $summary['average_price'],
        'low_stock_items'       => $lowStockItems
    ]);

} catch (PDOException $e) {
    logSystemError('Database error in products/stats.php: ' . $e->getMessage());
    sendServerError('Failed to calculate product telemetry.');
} catch (Throwable $e) {
    logSystemError('Unexpected error in products/stats.php: ' . $e->getMessage());
    sendServerError('An unexpected error occurred while computing product statistics.');
}

