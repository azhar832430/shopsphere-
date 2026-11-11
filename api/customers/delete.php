<?php
/**
 * ShopSphere - Delete or Archive Customer API Endpoint
 * Phase 5: Complete Customer Management CRUD
 * 
 * Route: POST /api/customers/delete.php
 * Enforces Foreign-Key Integrity:
 * - If orders exist: Safely deactivates/archives (status = 'inactive')
 * - If no orders: Permanently removes row from `customers`
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';

// Method check
requireMethod('POST');

// Authentication & CSRF
requireAuth();
requireCsrf();

$input = getJsonInput();
$id = isset($input['id']) ? (int) $input['id'] : (isset($_GET['id']) ? (int) $_GET['id'] : 0);

if ($id <= 0) {
    sendError('Invalid or missing customer ID.', ['id' => 'Valid positive customer ID is required.'], 400);
}

try {
    $pdo = Database::getConnection();

    // 1. Verify customer exists
    $checkStmt = $pdo->prepare('SELECT id, full_name, email, status FROM customers WHERE id = :id LIMIT 1');
    $checkStmt->execute(['id' => $id]);
    $customer = $checkStmt->fetch();

    if (!$customer) {
        sendNotFound("Customer with ID {$id} was not found.");
    }

    $customerName = $customer['full_name'];

    // 2. Check for historical orders in `orders` table
    $refStmt = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE customer_id = :id');
    $refStmt->execute(['id' => $id]);
    $orderCount = (int) $refStmt->fetchColumn();

    // 3. Foreign Key Integrity Rule Execution
    if ($orderCount > 0) {
        // Customer has order history; fk_orders_customer ON DELETE RESTRICT prevents hard delete.
        // Execute archival/deactivation strategy to preserve transactional auditability.
        $archiveStmt = $pdo->prepare("UPDATE customers SET status = 'inactive', updated_at = CURRENT_TIMESTAMP WHERE id = :id");
        $archiveStmt->execute(['id' => $id]);

        sendSuccess([
            'id'                      => $id,
            'action'                  => 'archived',
            'status'                  => 'inactive',
            'historical_orders_count' => $orderCount
        ], "Customer \"{$customerName}\" is linked to {$orderCount} historical order(s). To preserve sales and accounting records, the account was deactivated (archived) instead of deleted.");
    } else {
        // Customer has zero orders; safe to delete permanently
        $deleteStmt = $pdo->prepare('DELETE FROM customers WHERE id = :id');
        $deleteStmt->execute(['id' => $id]);

        sendSuccess([
            'id'                      => $id,
            'action'                  => 'deleted',
            'status'                  => 'removed',
            'historical_orders_count' => 0
        ], "Customer \"{$customerName}\" was permanently removed from the database.");
    }

} catch (Throwable $e) {
    sendServerError('An error occurred while deleting/archiving the customer record.', ['error' => $e->getMessage()]);
}

