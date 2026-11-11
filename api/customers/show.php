<?php
/**
 * ShopSphere - Customer Details & Dossier API Endpoint
 * Phase 5: Complete Customer Management CRUD
 * 
 * Route: GET /api/customers/show.php?id={id}
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';

// Method check
requireMethod('GET');

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id <= 0) {
    sendError('Invalid or missing customer ID.', ['id' => 'Valid positive customer ID is required.'], 400);
}

try {
    $pdo = Database::getConnection();

    // 1. Fetch customer details
    $stmt = $pdo->prepare("
        SELECT 
            c.id,
            c.full_name,
            c.email,
            c.phone,
            c.address,
            c.city,
            c.postal_code,
            c.status,
            c.created_at,
            c.updated_at,
            COUNT(o.id) AS total_orders,
            COALESCE(SUM(CASE WHEN o.order_status != 'cancelled' THEN o.total_amount ELSE 0 END), 0.00) AS total_spent
        FROM customers c
        LEFT JOIN orders o ON c.id = o.customer_id
        WHERE c.id = :id
        GROUP BY c.id
        LIMIT 1
    ");
    $stmt->execute(['id' => $id]);
    $customer = $stmt->fetch();

    if (!$customer) {
        sendNotFound("Customer with ID {$id} was not found.");
    }

    $totalOrders = (int) $customer['total_orders'];
    $totalSpent  = (float) $customer['total_spent'];
    $avgOrderVal = $totalOrders > 0 ? round($totalSpent / $totalOrders, 2) : 0.00;

    // 2. Fetch customer recent order history
    $ordersStmt = $pdo->prepare("
        SELECT 
            id,
            order_number,
            order_date,
            total_amount,
            payment_method,
            payment_status,
            order_status,
            notes
        FROM orders
        WHERE customer_id = :id
        ORDER BY order_date DESC
        LIMIT 10
    ");
    $ordersStmt->execute(['id' => $id]);
    $orders = $ordersStmt->fetchAll();

    $formattedOrders = array_map(function ($o) {
        return [
            'id'             => (int) $o['id'],
            'order_number'   => $o['order_number'],
            'order_date'     => $o['order_date'],
            'total_amount'   => (float) $o['total_amount'],
            'payment_method' => $o['payment_method'],
            'payment_status' => $o['payment_status'],
            'order_status'   => $o['order_status'],
            'notes'          => $o['notes'] ?? ''
        ];
    }, $orders);

    sendSuccess([
        'customer' => [
            'id'                  => (int) $customer['id'],
            'full_name'           => $customer['full_name'],
            'email'               => $customer['email'],
            'phone'               => $customer['phone'] ?? '',
            'address'             => $customer['address'] ?? '',
            'city'                => $customer['city'] ?? '',
            'postal_code'         => $customer['postal_code'] ?? '',
            'status'              => $customer['status'] ?? 'active',
            'created_at'          => $customer['created_at'],
            'updated_at'          => $customer['updated_at'],
            'total_orders'        => $totalOrders,
            'total_spent'         => $totalSpent,
            'average_order_value' => $avgOrderVal
        ],
        'orders' => $formattedOrders
    ], 'Customer dossier retrieved successfully.');

} catch (Throwable $e) {
    sendServerError('An error occurred while loading customer details.', ['error' => $e->getMessage()]);
}

