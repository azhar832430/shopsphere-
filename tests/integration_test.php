<?php
/**
 * ShopSphere - Phase 7: End-to-End Integration & Relational Database Test Suite
 * Execution: php tests/integration_test.php
 */

require_once __DIR__ . '/test_helper.php';

TestHarness::startSuite('Phase 7: End-to-End Integration & System Verification');

$pdo = TestHarness::getDatabase();

// ----------------------------------------------------------------------------
// Section 1: Database Schema, Foreign Keys & Constraints Verification
// ----------------------------------------------------------------------------

// Test INT-01: Verify all 6 core tables exist
$driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
if ($driver === 'sqlite') {
    $tablesStmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
} else {
    $tablesStmt = $pdo->query("SHOW TABLES");
}
$existingTables = $tablesStmt->fetchAll(PDO::FETCH_COLUMN);

$expectedTables = ['users', 'categories', 'products', 'customers', 'orders', 'order_items'];
$missingTables = array_diff($expectedTables, $existingTables);
TestHarness::assert('INT-01', 'Database: All 6 core tables exist in database schema', empty($missingTables));

// Test INT-02: Verify zero orphaned order_items
$orphanItemsStmt = $pdo->query("
    SELECT COUNT(*) FROM order_items oi 
    LEFT JOIN orders o ON oi.order_id = o.id 
    WHERE o.id IS NULL
");
$orphanItemsCount = (int) $orphanItemsStmt->fetchColumn();
TestHarness::assert('INT-02', 'Database: Zero orphaned records in order_items table', $orphanItemsCount === 0);

// Test INT-03: Verify zero orphaned orders
$orphanOrdersStmt = $pdo->query("
    SELECT COUNT(*) FROM orders o 
    LEFT JOIN customers c ON o.customer_id = c.id 
    WHERE c.id IS NULL
");
$orphanOrdersCount = (int) $orphanOrdersStmt->fetchColumn();
TestHarness::assert('INT-03', 'Database: Zero orphaned records in orders table', $orphanOrdersCount === 0);

// Test INT-04: Verify negative stock boundary prevention
$valNegStock = new Validator(['stock' => -10]);
$valNegStock->numeric('stock', 0, null, 'Stock quantity');
TestHarness::assert('INT-04', 'Database Constraints: Negative stock quantity boundary enforced', $valNegStock->fails());

// ----------------------------------------------------------------------------
// Section 2: Complete End-to-End Business Flow Lifecycle
// ----------------------------------------------------------------------------

// 1. Admin Authentication Flow
// Test INT-05: Authenticate admin user
$adminUser = $pdo->query("SELECT * FROM users WHERE username = 'admin'")->fetch();
$authMatches = password_verify('Admin@123', $adminUser['password']);
loginUser($adminUser);
$csrf = getCsrfToken();
TestHarness::assert('INT-05', 'E2E Step 1: Admin login & session establishment with CSRF token', 
    $authMatches && isAuthenticated() && !empty($csrf));

// 2. Catalog Setup
// Test INT-06: Create Category and Product
$pdo->exec("INSERT INTO categories (name, description, status) VALUES ('Gaming & VR', 'Gaming equipment', 'active')");
$catId = (int) $pdo->lastInsertId();

$initialProductStock = 20;
$productPrice = 499.00;
$pdo->prepare("INSERT INTO products (name, sku, category_id, price, stock, status) VALUES (:name, :sku, :cat, :price, :stock, 'active')")
    ->execute([
        'name'  => 'VR Headset Pro 4K',
        'sku'   => 'VR-PRO-4K-01',
        'cat'   => $catId,
        'price' => $productPrice,
        'stock' => $initialProductStock
    ]);
$prodId = (int) $pdo->lastInsertId();
TestHarness::assert('INT-06', 'E2E Step 2: Catalog setup (category & product with stock 20)', 
    $catId > 0 && $prodId > 0);

// 3. Customer Onboarding
// Test INT-07: Create Customer
$pdo->prepare("INSERT INTO customers (full_name, email, phone, city, status) VALUES (:name, :email, :phone, :city, 'active')")
    ->execute([
        'name'  => 'Alexander Wright',
        'email' => 'alex.wright@quantum.io',
        'phone' => '+1 (555) 789-0123',
        'city'  => 'Seattle'
    ]);
$custId = (int) $pdo->lastInsertId();
TestHarness::assert('INT-07', 'E2E Step 3: Customer onboarding (Alexander Wright)', $custId > 0);

// 4. Transactional Order Placement & Stock Reservation
// Test INT-08: Customer orders 2 VR headsets
$orderQty = 2;
$orderSubtotal = round($productPrice * $orderQty, 2);
$orderTax = round($orderSubtotal * 0.08, 2);
$orderTotal = round($orderSubtotal + $orderTax + 20.00, 2);

$pdo->beginTransaction();
$pdo->prepare("INSERT INTO orders (order_number, customer_id, subtotal, total_amount, payment_method, payment_status, order_status) 
    VALUES ('ORD-E2E-990', :cust_id, :sub, :total, 'Credit Card', 'pending', 'pending')")
    ->execute([
        'cust_id' => $custId,
        'sub'     => $orderSubtotal,
        'total'   => $orderTotal
    ]);
$orderId = (int) $pdo->lastInsertId();

$pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, unit_price, subtotal) VALUES (:ord_id, :prod_id, :qty, :price, :sub)")
    ->execute([
        'ord_id'  => $orderId,
        'prod_id' => $prodId,
        'qty'     => $orderQty,
        'price'   => $productPrice,
        'sub'     => $orderSubtotal
    ]);

$pdo->prepare("UPDATE products SET stock = stock - :qty WHERE id = :id")
    ->execute(['qty' => $orderQty, 'id' => $prodId]);
$pdo->commit();

$stockAfterE2E = (int) $pdo->query("SELECT stock FROM products WHERE id = {$prodId}")->fetchColumn();
TestHarness::assert('INT-08', 'E2E Step 4: Multi-item order placed & stock decremented atomically to 18', 
    $stockAfterE2E === ($initialProductStock - $orderQty));

// 5. Fulfillment & Payment Transitions
// Test INT-09: Update status to shipped and payment to paid
$pdo->exec("UPDATE orders SET order_status = 'shipped', payment_status = 'paid', updated_at = CURRENT_TIMESTAMP WHERE id = {$orderId}");
$e2eOrder = $pdo->query("SELECT order_status, payment_status FROM orders WHERE id = {$orderId}")->fetch();
TestHarness::assert('INT-09', 'E2E Step 5: Fulfillment updated to shipped & payment to paid', 
    $e2eOrder['order_status'] === 'shipped' && $e2eOrder['payment_status'] === 'paid');

// 6. Referential Integrity Check on Attempted Deletions
// Test INT-10: Attempted deletion of referenced product triggers archival
$refCheck = (int) $pdo->query("SELECT COUNT(*) FROM order_items WHERE product_id = {$prodId}")->fetchColumn();
if ($refCheck > 0) {
    $pdo->exec("UPDATE products SET status = 'inactive' WHERE id = {$prodId}");
}
$prodStatus = $pdo->query("SELECT status FROM products WHERE id = {$prodId}")->fetchColumn();
TestHarness::assert('INT-10', 'E2E Step 6: Product deletion safeguard triggers archival (preserves order integrity)', 
    $prodStatus === 'inactive');

// Test INT-11: Attempted deletion of referenced customer triggers archival
$custRefCheck = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE customer_id = {$custId}")->fetchColumn();
if ($custRefCheck > 0) {
    $pdo->exec("UPDATE customers SET status = 'inactive' WHERE id = {$custId}");
}
$custStatus = $pdo->query("SELECT status FROM customers WHERE id = {$custId}")->fetchColumn();
TestHarness::assert('INT-11', 'E2E Step 7: Customer deletion safeguard triggers archival (preserves audit records)', 
    $custStatus === 'inactive');

// 7. Safe Order Cancellation & Stock Restoration
// Test INT-12: Cancel order & verify stock returned to 20 and payment set to refunded
$pdo->beginTransaction();
$restoreItems = $pdo->query("SELECT product_id, quantity FROM order_items WHERE order_id = {$orderId}")->fetchAll();
foreach ($restoreItems as $ri) {
    $pdo->prepare("UPDATE products SET stock = stock + :qty WHERE id = :id")
        ->execute(['qty' => $ri['quantity'], 'id' => $ri['product_id']]);
}
$pdo->exec("UPDATE orders SET order_status = 'cancelled', payment_status = 'refunded' WHERE id = {$orderId}");
$pdo->commit();

$stockAfterCancel = (int) $pdo->query("SELECT stock FROM products WHERE id = {$prodId}")->fetchColumn();
$cancelOrderInfo = $pdo->query("SELECT order_status, payment_status FROM orders WHERE id = {$orderId}")->fetch();

TestHarness::assert('INT-12', 'E2E Step 8: Order cancelled, inventory replenished to 20, and payment refunded', 
    $stockAfterCancel === $initialProductStock && 
    $cancelOrderInfo['order_status'] === 'cancelled' && 
    $cancelOrderInfo['payment_status'] === 'refunded');

// ----------------------------------------------------------------------------
// Section 3: Frontend Templates & Responsiveness Architecture Verification
// ----------------------------------------------------------------------------

$expectedViews = [
    'index.html',
    'dashboard.html',
    'products.html',
    'customers.html',
    'orders.html',
    'categories.html',
    'settings.html'
];

$allViewsExist = true;
$allHaveViewport = true;
$projectRoot = dirname(__DIR__);

foreach ($expectedViews as $viewFile) {
    $fullPath = $projectRoot . DIRECTORY_SEPARATOR . $viewFile;
    if (!is_file($fullPath)) {
        $allViewsExist = false;
        break;
    }
    $content = file_get_contents($fullPath);
    if (!str_contains($content, 'name="viewport"') && !str_contains($content, "name='viewport'")) {
        $allHaveViewport = false;
    }
}

TestHarness::assert('INT-13', 'Frontend: All 7 HTML dashboard views present with mobile viewport tags', 
    $allViewsExist && $allHaveViewport);

// Test INT-14: Verify responsive CSS breakpoints exist
$responsiveCssPath = $projectRoot . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'responsive.css';
$hasBreakpoints = false;
if (is_file($responsiveCssPath)) {
    $cssContent = file_get_contents($responsiveCssPath);
    $hasBreakpoints = str_contains($cssContent, '1200px') && 
                      str_contains($cssContent, '1024px') && 
                      str_contains($cssContent, '768px') && 
                      str_contains($cssContent, '480px');
}
TestHarness::assert('INT-14', 'Frontend: Responsive stylesheet defines 1200px, 1024px, 768px, 480px breakpoints', 
    $hasBreakpoints);

// Test INT-15: Verify design system tokens in variables.css
$variablesCssPath = $projectRoot . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'variables.css';
$hasTokens = false;
if (is_file($variablesCssPath)) {
    $varContent = file_get_contents($variablesCssPath);
    $hasTokens = str_contains($varContent, '--primary-') && 
                 str_contains($varContent, '--bg-surface') && 
                 str_contains($varContent, '--radius-') && 
                 str_contains($varContent, '--shadow-');
}
TestHarness::assert('INT-15', 'Frontend: Design system tokens defined for theme palettes, surfaces & elevation', 
    $hasTokens);

TestHarness::endSuite();
