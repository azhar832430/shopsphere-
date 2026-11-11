<?php
/**
 * ShopSphere - Customer Analytics & Metrics API Endpoint
 * Phase 5: Complete Customer Management CRUD
 * 
 * Route: GET /api/customers/stats.php
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';

// Method check
requireMethod('GET');

try {
    $pdo = Database::getConnection();

    // 1. Overall counts
    $countsStmt = $pdo->query("
        SELECT 
            COUNT(*) AS total_customers,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active_customers,
            SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) AS inactive_customers,
            SUM(CASE WHEN status = 'blocked' THEN 1 ELSE 0 END) AS blocked_customers
        FROM customers
    ");
    $counts = $countsStmt->fetch() ?: [
        'total_customers'    => 0,
        'active_customers'   => 0,
        'inactive_customers' => 0,
        'blocked_customers'  => 0
    ];

    // 2. New customers count (registered in the last 30 days)
    // Supports both MySQL and SQLite
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'sqlite') {
        $newStmt = $pdo->query("SELECT COUNT(*) FROM customers WHERE created_at >= datetime('now', '-30 days')");
    } else {
        $newStmt = $pdo->query("SELECT COUNT(*) FROM customers WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    }
    $newCustomers = (int) ($newStmt ? $newStmt->fetchColumn() : 0);

    // 3. Top spenders
    $topStmt = $pdo->query("
        SELECT 
            c.id,
            c.full_name,
            c.email,
            c.city,
            COUNT(o.id) AS total_orders,
            COALESCE(SUM(CASE WHEN o.order_status != 'cancelled' THEN o.total_amount ELSE 0 END), 0.00) AS total_spent
        FROM customers c
        LEFT JOIN orders o ON c.id = o.customer_id
        GROUP BY c.id
        ORDER BY total_spent DESC
        LIMIT 5
    ");
    $topSpenders = $topStmt ? $topStmt->fetchAll() : [];

    $formattedTop = array_map(function ($row) {
        return [
            'id'           => (int) $row['id'],
            'full_name'    => $row['full_name'],
            'email'        => $row['email'],
            'city'         => $row['city'] ?? '',
            'total_orders' => (int) $row['total_orders'],
            'total_spent'  => (float) $row['total_spent']
        ];
    }, $topSpenders);

    sendSuccess([
        'total_customers'    => (int) ($counts['total_customers'] ?? 0),
        'active_customers'   => (int) ($counts['active_customers'] ?? 0),
        'inactive_customers' => (int) ($counts['inactive_customers'] ?? 0),
        'blocked_customers'  => (int) ($counts['blocked_customers'] ?? 0),
        'new_customers'      => $newCustomers,
        'top_spenders'       => $formattedTop
    ], 'Customer telemetry retrieved successfully.');

} catch (Throwable $e) {
    sendServerError('An error occurred while loading customer statistics.', ['error' => $e->getMessage()]);
}

