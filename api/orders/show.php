<?php
/**
 * ShopSphere - Order Details & Invoice API Endpoint
 * Phase 6: Complete Order Management CRUD
 * 
 * Route: GET /api/orders/show.php?id={id}
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';

// Method check
requireMethod('GET');

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$orderNumber = sanitizeString($_GET['order_number'] ?? '');

if ($id <= 0 && $orderNumber === '') {
    sendError('Invalid or missing order identifier.', ['id' => 'Valid order ID or order number is required.'], 400);
}

try {
    $pdo = Database::getConnection();

    // 1. Fetch order header and customer details
    $orderSql = "
        SELECT 
            o.id,
            o.order_number,
            o.customer_id,
            o.order_date,
            o.subtotal,
            o.total_amount,
            o.payment_method,
            o.payment_status,
            o.order_status,
            o.notes,
            o.created_at,
            o.updated_at,
            c.full_name AS customer_name,
            c.email AS customer_email,
            c.phone AS customer_phone,
            c.address AS customer_address,
            c.city AS customer_city,
            c.postal_code AS customer_postal_code,
            c.status AS customer_status
        FROM orders o
        LEFT JOIN customers c ON o.customer_id = c.id
        WHERE " . ($id > 0 ? "o.id = :id" : "o.order_number = :num") . "
        LIMIT 1
    ";

    $orderStmt = $pdo->prepare($orderSql);
    if ($id > 0) {
        $orderStmt->execute(['id' => $id]);
    } else {
        $orderStmt->execute(['num' => $orderNumber]);
    }
    $order = $orderStmt->fetch();

    if (!$order) {
        sendNotFound("Order " . ($id > 0 ? "with ID {$id}" : "#{$orderNumber}") . " was not found.");
    }

    $actualOrderId = (int) $order['id'];

    // 2. Fetch itemized line items with product details
    $itemsSql = "
        SELECT 
            oi.id,
            oi.order_id,
            oi.product_id,
            oi.quantity,
            oi.unit_price,
            oi.subtotal,
            oi.created_at,
            p.name AS product_name,
            p.sku AS product_sku,
            p.image AS product_image,
            p.stock AS current_product_stock
        FROM order_items oi
        LEFT JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = :order_id
        ORDER BY oi.id ASC
    ";

    $itemsStmt = $pdo->prepare($itemsSql);
    $itemsStmt->execute(['order_id' => $actualOrderId]);
    $items = $itemsStmt->fetchAll();

    $formattedItems = array_map(function ($it) {
        return [
            'id'             => (int) $it['id'],
            'order_id'       => (int) $it['order_id'],
            'product_id'     => (int) $it['product_id'],
            'product_name'   => $it['product_name'] ?? 'Custom Product',
            'product_sku'    => $it['product_sku'] ?? 'N/A',
            'product_image'  => $it['product_image'] ?? '',
            'quantity'       => (int) $it['quantity'],
            'unit_price'     => (float) $it['unit_price'],
            'subtotal'       => (float) $it['subtotal'],
            'current_stock'  => (int) ($it['current_product_stock'] ?? 0),
            'created_at'     => $it['created_at']
        ];
    }, $items);

    sendSuccess([
        'order' => [
            'id'             => $actualOrderId,
            'order_number'   => $order['order_number'],
            'customer_id'    => (int) $order['customer_id'],
            'order_date'     => $order['order_date'],
            'subtotal'       => (float) $order['subtotal'],
            'total_amount'   => (float) $order['total_amount'],
            'payment_method' => $order['payment_method'],
            'payment_status' => $order['payment_status'],
            'order_status'   => $order['order_status'],
            'notes'          => $order['notes'] ?? '',
            'created_at'     => $order['created_at'],
            'updated_at'     => $order['updated_at']
        ],
        'customer' => [
            'id'          => (int) $order['customer_id'],
            'full_name'   => $order['customer_name'] ?? 'Guest Customer',
            'email'       => $order['customer_email'] ?? '',
            'phone'       => $order['customer_phone'] ?? '',
            'address'     => $order['customer_address'] ?? '',
            'city'        => $order['customer_city'] ?? '',
            'postal_code' => $order['customer_postal_code'] ?? '',
            'status'      => $order['customer_status'] ?? 'active'
        ],
        'items' => $formattedItems
    ], 'Order details retrieved successfully.');

} catch (Throwable $e) {
    sendServerError('An error occurred while loading order details.', ['error' => $e->getMessage()]);
}

