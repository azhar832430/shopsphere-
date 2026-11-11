<?php
/**
 * ShopSphere - Delete or Archive Product
 * Phase 4: Product Management CRUD
 * 
 * Route: POST /api/products/delete.php
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/upload.php';

// Method check
requireMethod('POST');

// Authentication & CSRF
requireAuth();
requireCsrf();

$input = getJsonInput();
$id = isset($input['id']) ? (int) $input['id'] : (isset($_GET['id']) ? (int) $_GET['id'] : 0);

if ($id <= 0) {
    sendError('Invalid or missing product ID.', ['id' => 'Valid product ID is required.'], 400);
}

try {
    $pdo = Database::getConnection();

    // 1. Fetch product to verify existence
    $checkStmt = $pdo->prepare('SELECT id, name, sku, image, status FROM products WHERE id = :id LIMIT 1');
    $checkStmt->execute(['id' => $id]);
    $product = $checkStmt->fetch();

    if (!$product) {
        sendNotFound("Product with ID {$id} was not found.");
    }

    // 2. Check for historical order references in order_items
    $refStmt = $pdo->prepare('SELECT COUNT(*) FROM order_items WHERE product_id = :id');
    $refStmt->execute(['id' => $id]);
    $historicalRefCount = (int) $refStmt->fetchColumn();

    // 3. Business rule execution
    if ($historicalRefCount > 0) {
        // Product cannot be deleted due to ON DELETE RESTRICT foreign key
        // Execute archival strategy: deactivate status
        $archiveStmt = $pdo->prepare("UPDATE products SET status = 'inactive', updated_at = CURRENT_TIMESTAMP WHERE id = :id");
        $archiveStmt->execute(['id' => $id]);

        sendSuccess("Product '{$product['name']}' has historical order records and cannot be permanently deleted. It has been archived (status set to Inactive) to preserve audit integrity.", [
            'action'        => 'archived',
            'product_id'    => $id,
            'name'          => $product['name'],
            'order_records' => $historicalRefCount
        ]);
    } else {
        // Safe to execute permanent database deletion
        $delStmt = $pdo->prepare('DELETE FROM products WHERE id = :id');
        $delStmt->execute(['id' => $id]);

        // Clean up locally uploaded image asset if present
        deleteProductImageFile($product['image']);

        sendSuccess("Product '{$product['name']}' (SKU: {$product['sku']}) has been permanently deleted from the catalog.", [
            'action'     => 'deleted',
            'product_id' => $id,
            'name'       => $product['name']
        ]);
    }

} catch (PDOException $e) {
    logSystemError("Database error in products/delete.php (ID: {$id}): " . $e->getMessage());
    sendServerError('Failed to process product deletion. A database constraint error occurred.');
} catch (Throwable $e) {
    logSystemError("Unexpected error in products/delete.php: " . $e->getMessage());
    sendServerError('An unexpected error occurred during product deletion.');
}
