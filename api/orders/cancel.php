<?php
/**
 * ShopSphere - Cancel Order API Endpoint
 * Phase 6: Complete Order Management CRUD
 * 
 * Route: POST /api/orders/cancel.php
 * Safely cancels an eligible order, preserves order record for auditability,
 * and restores inventory stock atomically exactly once.
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
    sendError('Invalid or missing order ID.', ['id' => 'Valid positive order ID is required.'], 400);
}

try {
    $pdo = Database::getConnection();
    $pdo->beginTransaction();

    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $forUpdateClause = ($driver === 'mysql') ? ' FOR UPDATE' : '';

    // 1. Fetch target order with row lock
    $orderStmt = $pdo->prepare("SELECT * FROM orders WHERE id = :id" . $forUpdateClause);
    $orderStmt->execute(['id' => $id]);
    $order = $orderStmt->fetch();

    if (!$order) {
        $pdo->rollBack();
        sendNotFound("Order with ID {$id} was not found.");
    }

    $orderNumber   = $order['order_number'];
    $currentStatus = $order['order_status'];
    $currentPay    = $order['payment_status'];

    // 2. Validate Cancellation Eligibility
    if ($currentStatus === 'cancelled') {
        $pdo->rollBack();
        sendError("Order #{$orderNumber} has already been cancelled. Inventory stock was previously restored.", [
            'order_status' => 'Order is already cancelled.'
        ], 400);
    }

    if ($currentStatus === 'delivered') {
        $pdo->rollBack();
        sendError("Order #{$orderNumber} has already been fulfilled and delivered. Completed shipments cannot be cancelled directly.", [
            'order_status' => 'Delivered orders cannot be cancelled.'
        ], 400);
    }

    // 3. Fetch line items and restore inventory stock atomically
    $itemsStmt = $pdo->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = :order_id");
    $itemsStmt->execute(['order_id' => $id]);
    $orderItems = $itemsStmt->fetchAll();

    $stockRestoreStmt = $pdo->prepare("
        UPDATE products 
        SET stock = stock + :qty, updated_at = CURRENT_TIMESTAMP 
        WHERE id = :product_id
    ");

    $totalRestoredUnits = 0;
    foreach ($orderItems as $item) {
        $qty = (int) $item['quantity'];
        $stockRestoreStmt->execute([
            'qty'        => $qty,
            'product_id' => $item['product_id']
        ]);
        $totalRestoredUnits += $qty;
    }

    // 4. Update order status and refund payment if necessary
    $newPaymentStatus = ($currentPay === 'paid') ? 'refunded' : $currentPay;

    $updateStmt = $pdo->prepare("
        UPDATE orders SET
            order_status   = 'cancelled',
            payment_status = :payment_status,
            updated_at     = CURRENT_TIMESTAMP
        WHERE id = :id
    ");

    $updateStmt->execute([
        'payment_status' => $newPaymentStatus,
        'id'             => $id
    ]);

    // Commit Transaction
    $pdo->commit();

    sendSuccess([
        'id'                   => (int) $id,
        'order_number'         => $orderNumber,
        'order_status'         => 'cancelled',
        'payment_status'       => $newPaymentStatus,
        'items_count'          => count($orderItems),
        'restored_units_count' => $totalRestoredUnits
    ], "Order #{$orderNumber} cancelled successfully. {$totalRestoredUnits} product unit(s) were restored to catalog inventory.");

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    sendServerError('An error occurred while cancelling the order.', ['error' => $e->getMessage()]);
}

