<?php
/**
 * ShopSphere - Delete Category API Endpoint
 * Phase 7: Complete Category CRUD
 * 
 * Route: POST /api/categories/destroy.php?id={id}
 * Safe referential deletion:
 * Reassigns any assigned products' category_id to NULL (Uncategorized)
 * before deleting category row. Products are NEVER deleted.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';

// Method check
requireMethod(['POST', 'DELETE']);

// Authentication & CSRF
requireAuth();
requireCsrf();

$input = getJsonInput();
$id = isset($input['id']) ? (int) $input['id'] : (isset($_GET['id']) ? (int) $_GET['id'] : 0);

if ($id <= 0) {
    sendError('Invalid or missing category ID.', ['id' => 'Valid positive category ID is required.'], 400);
}

try {
    $pdo = Database::getConnection();

    // 1. Verify category exists
    $checkStmt = $pdo->prepare('SELECT id, name, slug FROM categories WHERE id = :id LIMIT 1');
    $checkStmt->execute(['id' => $id]);
    $category = $checkStmt->fetch();

    if (!$category) {
        sendNotFound("Category with ID {$id} was not found.");
    }

    $categoryName = $category['name'];

    // 2. Count assigned products
    $prodCountStmt = $pdo->prepare('SELECT COUNT(*) FROM products WHERE category_id = :id');
    $prodCountStmt->execute(['id' => $id]);
    $assignedProducts = (int) $prodCountStmt->fetchColumn();

    // 3. Begin Transaction for atomic category removal and product decoupling
    $pdo->beginTransaction();

    // Reassign products to NULL (Uncategorized) to protect catalog products
    if ($assignedProducts > 0) {
        $reassignStmt = $pdo->prepare('UPDATE products SET category_id = NULL, updated_at = CURRENT_TIMESTAMP WHERE category_id = :id');
        $reassignStmt->execute(['id' => $id]);
    }

    // Delete category row
    $delStmt = $pdo->prepare('DELETE FROM categories WHERE id = :id');
    $delStmt->execute(['id' => $id]);

    $pdo->commit();

    $msg = "Category \"{$categoryName}\" has been permanently removed.";
    if ($assignedProducts > 0) {
        $msg .= " {$assignedProducts} assigned product(s) were safely set to Uncategorized.";
    }

    sendSuccess([
        'category_id'         => $id,
        'name'                => $categoryName,
        'action'              => 'deleted',
        'reassigned_products' => $assignedProducts
    ], $msg);

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    logSystemError("Database error in categories/destroy.php (ID: {$id}): " . $e->getMessage());
    sendServerError('An error occurred while deleting the category.');
}

