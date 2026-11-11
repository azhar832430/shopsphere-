<?php
/**
 * ShopSphere - Orders Telemetry & Financial Analytics API Endpoint
 * Phase 6: Complete Order Management CRUD
 * 
 * Route: GET /api/orders/stats.php
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';

// Method check
requireMethod('GET');

try {
    $pdo = Database::getConnection();

    // 1. Overall Order Statuses & Revenue Aggregation (excludes cancelled orders from revenue)
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) AS total_orders,
            SUM(CASE WHEN order_status = 'pending' THEN 1 ELSE 0 END) AS pending_orders,
            SUM(CASE WHEN order_status = 'processing' THEN 1 ELSE 0 END) AS processing_orders,
            SUM(CASE WHEN order_status = 'shipped' THEN 1 ELSE 0 END) AS shipped_orders,
            SUM(CASE WHEN order_status = 'delivered' THEN 1 ELSE 0 END) AS completed_orders,
            SUM(CASE WHEN order_status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_orders,
            COALESCE(SUM(CASE WHEN order_status != 'cancelled' THEN total_amount ELSE 0 END), 0.00) AS total_revenue,
            COALESCE(AVG(CASE WHEN order_status != 'cancelled' THEN total_amount ELSE NULL END), 0.00) AS average_order_value
        FROM orders
    ");
    $stats = $stmt->fetch() ?: [];

    // 2. Recent Orders for Dashboard Widget
    $recentStmt = $pdo->query("
        SELECT 
            o.id,
            o.order_number,
            o.order_date,
            o.total_amount,
            o.payment_status,
            o.order_status,
            COALESCE(c.full_name, 'Guest Customer') AS customer_name
        FROM orders o
        LEFT JOIN customers c ON o.customer_id = c.id
        ORDER BY o.order_date DESC
        LIMIT 5
    ");
    $recentOrders = $recentStmt ? $recentStmt->fetchAll() : [];

    $formattedRecent = array_map(function ($r) {
        return [
            'id'            => (int) $r['id'],
            'order_number'  => $r['order_number'],
            'orderNumber'   => $r['order_number'],
            'order_date'    => $r['order_date'],
            'orderDate'     => $r['order_date'],
            'total_amount'  => (float) $r['total_amount'],
            'totalAmount'   => (float) $r['total_amount'],
            'payment_status'=> $r['payment_status'],
            'paymentStatus' => $r['payment_status'],
            'order_status'  => $r['order_status'],
            'orderStatus'   => $r['order_status'],
            'customer_name' => $r['customer_name'],
            'customerName'  => $r['customer_name']
        ];
    }, $recentOrders);

    // 3. Status breakdown object for Doughnut Charts
    $statuses = [
        'delivered'  => (int) ($stats['completed_orders'] ?? 0),
        'shipped'    => (int) ($stats['shipped_orders'] ?? 0),
        'processing' => (int) ($stats['processing_orders'] ?? 0),
        'pending'    => (int) ($stats['pending_orders'] ?? 0),
        'cancelled'  => (int) ($stats['cancelled_orders'] ?? 0)
    ];

    sendSuccess([
        'total_orders'        => (int) ($stats['total_orders'] ?? 0),
        'pending_orders'      => (int) ($stats['pending_orders'] ?? 0),
        'processing_orders'   => (int) ($stats['processing_orders'] ?? 0),
        'shipped_orders'      => (int) ($stats['shipped_orders'] ?? 0),
        'completed_orders'    => (int) ($stats['completed_orders'] ?? 0),
        'delivered_orders'    => (int) ($stats['completed_orders'] ?? 0),
        'cancelled_orders'    => (int) ($stats['cancelled_orders'] ?? 0),
        'total_revenue'       => (float) ($stats['total_revenue'] ?? 0.00),
        'average_order_value' => round((float) ($stats['average_order_value'] ?? 0.00), 2),
        'order_statuses'      => $statuses,
        'recent_orders'       => $formattedRecent
    ], 'Orders telemetry and financial statistics retrieved successfully.');

} catch (Throwable $e) {
    sendServerError('An error occurred while loading orders telemetry.', ['error' => $e->getMessage()]);
}

