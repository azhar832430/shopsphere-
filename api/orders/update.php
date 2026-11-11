<?php
/**
 * ShopSphere - Update Order Status & Details API Endpoint
 * Phase 6: Complete Order Management CRUD
 * 
 * Route: POST /api/orders/update.php?id={id}
 * Handles order status updates, payment transitions, and atomic stock restoration if cancelled.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/validation.php';
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

$newOrderStatus   = isset($input['order_status']) ? strtolower(sanitizeString($input['order_status'])) : null;
$newPaymentStatus = isset($input['payment_status']) ? strtolower(sanitizeString($input['payment_status'])) : null;
$notes            = isset($input['notes']) ? sanitizeString($input['notes']) : null;

$validOrderStatuses   = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
$validPaymentStatuses = ['pending', 'paid', 'failed', 'refunded'];

if ($newOrderStatus !== null && !in_array($newOrderStatus, $validOrderStatuses, true)) {
    sendError('Invalid order status.', [
        'order_status' => 'Allowed order statuses: ' . implode(', ', $validOrderStatuses)
    ], 400);
}

if ($newPaymentStatus !== null && !in_array($newPaymentStatus, $validPaymentStatuses, true)) {
    sendError('Invalid payment status.', [
        'payment_status' => 'Allowed payment statuses: ' . implode(', ', $validPaymentStatuses)
    ], 400);
}

try {
    $pdo = Database::getConnection();
    $pdo->beginTransaction();

    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $forUpdateClause = ($driver === 'mysql') ? ' FOR UPDATE' : '';

    // 1. Fetch current order state with row lock
    $orderStmt = $pdo->prepare("SELECT * FROM orders WHERE id = :id" . $forUpdateClause);
    $orderStmt->execute(['id' => $id]);
    $currentOrder = $orderStmt->fetch();

    if (!$currentOrder) {
        $pdo->rollBack();
        sendNotFound("Order with ID {$id} was not found.");
    }

    $currentStatus  = $currentOrder['order_status'];
    $currentPayment = $currentOrder['payment_status'];
    $orderNumber    = $currentOrder['order_number'];

    // 2. Validate Status Transitions & Protection Rules
    $stockRestored = false;

    if ($newOrderStatus !== null && $newOrderStatus !== $currentStatus) {
        // Prevent editing already cancelled orders
        if ($currentStatus === 'cancelled') {
            $pdo->rollBack();
            sendError("Order #{$orderNumber} is already cancelled and cannot be transitioned to {$newOrderStatus}.", [
                'order_status' => 'Cancelled orders are final.'
            ], 400);
        }

        // Prevent reverting or cancelling delivered/fulfilled orders
        if ($currentStatus === 'delivered') {
            $pdo->rollBack();
            sendError("Order #{$orderNumber} has already been fulfilled and delivered. It cannot be reverted or cancelled directly.", [
                'order_status' => 'Delivered orders cannot be reverted.'
            ], 400);
        }

        // If transitioning to cancelled: restore product stock exactly once!
        if ($newOrderStatus === 'cancelled') {
            $itemsStmt = $pdo->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = :order_id");
            $itemsStmt->execute(['order_id' => $id]);
            $orderItems = $itemsStmt->fetchAll();

            $stockRestoreStmt = $pdo->prepare("
                UPDATE products 
                SET stock = stock + :qty, updated_at = CURRENT_TIMESTAMP 
                WHERE id = :product_id
            ");

            foreach ($orderItems as $item) {
                $stockRestoreStmt->execute([
                    'qty'        => $item['quantity'],
                    'product_id' => $item['product_id']
                ]);
            }

            $stockRestored = true;

            // Task 2: When an order is cancelled:
            // 1. If it was paid (or client attempted to mark paid), enforce 'refunded' payment status.
            //    A client request cannot override this cancellation rule.
            // 2. If it was unpaid (e.g. pending/failed), do NOT mark as refunded; retain unpaid status.
            if ($currentPayment === 'paid' || $newPaymentStatus === 'paid') {
                $newPaymentStatus = 'refunded';
            } elseif ($newPaymentStatus === null) {
                $newPaymentStatus = $currentPayment;
            }
        }
    }

    // 3. Prepare Update Fields
    $targetOrderStatus   = $newOrderStatus ?? $currentStatus;
    $targetPaymentStatus = $newPaymentStatus ?? $currentPayment;

    // Defense-in-depth: A cancelled order can NEVER remain in 'paid' status
    if ($targetOrderStatus === 'cancelled' && $targetPaymentStatus === 'paid') {
        $targetPaymentStatus = 'refunded';
    }

    $targetNotes = $notes !== null ? $notes : ($currentOrder['notes'] ?? '');

    $updateStmt = $pdo->prepare("
        UPDATE orders SET
            order_status   = :order_status,
            payment_status = :payment_status,
            notes          = :notes,
            updated_at     = CURRENT_TIMESTAMP
        WHERE id = :id
    ");

    $updateStmt->execute([
        'order_status'   => $targetOrderStatus,
        'payment_status' => $targetPaymentStatus,
        'notes'          => $targetNotes !== '' ? $targetNotes : null,
        'id'             => $id
    ]);

    // Commit Transaction
    $pdo->commit();

    $message = "Order #{$orderNumber} updated successfully.";
    if ($stockRestored) {
        $message .= " Stock was safely restored to catalog inventory.";
    }

    sendSuccess([
        'order' => [
            'id'             => (int) $id,
            'order_number'   => $orderNumber,
            'order_status'   => $targetOrderStatus,
            'payment_status' => $targetPaymentStatus,
            'notes'          => $targetNotes,
            'stock_restored' => $stockRestored
        ]
    ], $message);

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    sendServerError('An error occurred while updating order status.', ['error' => $e->getMessage()]);
}

