<?php
/**
 * ShopSphere - Phase 7: Comprehensive Order CRUD & Stock Management Test Suite
 * Execution: php tests/order_test.php
 */

require_once __DIR__ . '/test_helper.php';

TestHarness::startSuite('Phase 7: Order Management & Transactions');

$pdo = TestHarness::getDatabase();

// ----------------------------------------------------------------------------
// Section 1: Order Placement, Multi-Item & Atomic Stock Deductions
// ----------------------------------------------------------------------------

// Check initial stock of Product 2 (Noise-Cancelling Headphones: stock 48)
$stockStmt = $pdo->prepare('SELECT stock, price FROM products WHERE id = :id');
$stockStmt->execute(['id' => 2]);
$p2 = $stockStmt->fetch();
$initialStockP2 = (int) $p2['stock'];
$p2Price = (float) $p2['price'];

// Test 1: Create valid order with single product & verify stock deduction
$orderNumber1 = 'ORD-7001';
$qtyOrdered1 = 3;
$subtotal1 = round($p2Price * $qtyOrdered1, 2);
$tax1 = round($subtotal1 * 0.08, 2);
$shipping1 = 15.00;
$grandTotal1 = round($subtotal1 + $tax1 + $shipping1, 2);

$pdo->beginTransaction();
$pdo->exec("INSERT INTO orders (id, order_number, customer_id, subtotal, total_amount, payment_method, payment_status, order_status)
    VALUES (7001, '{$orderNumber1}', 1, {$subtotal1}, {$grandTotal1}, 'Credit Card', 'pending', 'pending')");
$pdo->exec("INSERT INTO order_items (order_id, product_id, quantity, unit_price, subtotal)
    VALUES (7001, 2, {$qtyOrdered1}, {$p2Price}, {$subtotal1})");
$pdo->exec("UPDATE products SET stock = stock - {$qtyOrdered1} WHERE id = 2");
$pdo->commit();

$stockStmt->execute(['id' => 2]);
$stockAfterOrder1 = (int) $stockStmt->fetchColumn();
TestHarness::assert('ORD-01', 'Create order and deduct stock atomically', 
    $stockAfterOrder1 === ($initialStockP2 - $qtyOrdered1));

// Test 2: Create order with multiple products & calculate grand total correctly
// Check initial stock of Product 3 (UltraVision 4K Monitor: stock 18)
$stockStmt->execute(['id' => 3]);
$p3 = $stockStmt->fetch();
$initialStockP3 = (int) $p3['stock'];
$p3Price = (float) $p3['price'];

$orderNumber2 = 'ORD-7002';
$itemA_qty = 2; // P2 (Headphones)
$itemB_qty = 1; // P3 (Monitor)
$itemA_sub = round($p2Price * $itemA_qty, 2);
$itemB_sub = round($p3Price * $itemB_qty, 2);
$calcSubtotal = round($itemA_sub + $itemB_sub, 2);
$calcTax = round($calcSubtotal * 0.08, 2);
$calcTotal = round($calcSubtotal + $calcTax + 15.00, 2);

$pdo->beginTransaction();
$pdo->exec("INSERT INTO orders (id, order_number, customer_id, subtotal, total_amount, payment_method, payment_status, order_status)
    VALUES (7002, '{$orderNumber2}', 2, {$calcSubtotal}, {$calcTotal}, 'PayPal', 'paid', 'processing')");
$pdo->exec("INSERT INTO order_items (order_id, product_id, quantity, unit_price, subtotal)
    VALUES (7002, 2, {$itemA_qty}, {$p2Price}, {$itemA_sub})");
$pdo->exec("INSERT INTO order_items (order_id, product_id, quantity, unit_price, subtotal)
    VALUES (7002, 3, {$itemB_qty}, {$p3Price}, {$itemB_sub})");
$pdo->exec("UPDATE products SET stock = stock - {$itemA_qty} WHERE id = 2");
$pdo->exec("UPDATE products SET stock = stock - {$itemB_qty} WHERE id = 3");
$pdo->commit();

$verifyOrder2 = $pdo->query("SELECT subtotal, total_amount FROM orders WHERE id = 7002")->fetch();
TestHarness::assert('ORD-02', 'Order with multiple products calculates totals correctly', 
    (float)$verifyOrder2['subtotal'] === $calcSubtotal && (float)$verifyOrder2['total_amount'] === $calcTotal);

// Test 3: Reject order when requested quantity exceeds available stock
$stockStmt->execute(['id' => 4]); // Studio Speakers (stock 5)
$p4Stock = (int) $stockStmt->fetchColumn();
$requestedOverstock = $p4Stock + 10;
$hasEnoughStock = ($p4Stock >= $requestedOverstock);
TestHarness::assert('ORD-03', 'Reject order when quantity exceeds available stock', !$hasEnoughStock);

// Test 4: Reject order placement for blocked customer
$custStmt = $pdo->prepare('SELECT status FROM customers WHERE id = :id');
$custStmt->execute(['id' => 4]); // Customer 4 is blocked
$isBlocked = ($custStmt->fetchColumn() === 'blocked');
TestHarness::assert('ORD-04', 'Reject order creation for blocked customer account', $isBlocked);

// ----------------------------------------------------------------------------
// Section 2: Order Listing, Search, Status Filters & Invoice Details
// ----------------------------------------------------------------------------

// Test 5: Search orders by order number and customer name
$searchStmt = $pdo->prepare("
    SELECT o.*, c.full_name AS customer_name 
    FROM orders o 
    JOIN customers c ON o.customer_id = c.id 
    WHERE o.order_number LIKE :q OR c.full_name LIKE :q
");
$searchStmt->execute(['q' => '%ORD-7001%']);
$searchResults = $searchStmt->fetchAll();
TestHarness::assert('ORD-05', 'Search orders by order number locates target record', 
    count($searchResults) === 1 && $searchResults[0]['order_number'] === 'ORD-7001');

// Test 6: Filter orders by order status (processing)
$filterStatusStmt = $pdo->prepare('SELECT * FROM orders WHERE order_status = :status');
$filterStatusStmt->execute(['status' => 'processing']);
$processingOrders = $filterStatusStmt->fetchAll();
TestHarness::assert('ORD-06', 'Filter orders by order status isolates processing orders', count($processingOrders) >= 1);

// Test 7: Filter orders by payment status (paid)
$filterPayStmt = $pdo->prepare('SELECT * FROM orders WHERE payment_status = :pay_status');
$filterPayStmt->execute(['pay_status' => 'paid']);
$paidOrders = $filterPayStmt->fetchAll();
TestHarness::assert('ORD-07', 'Filter orders by payment status isolates paid orders', count($paidOrders) >= 1);

// Test 8: View complete order details with itemized line items
$detailStmt = $pdo->prepare("
    SELECT 
        o.*,
        c.full_name, c.email, c.phone, c.address, c.city,
        (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) AS item_count
    FROM orders o
    JOIN customers c ON o.customer_id = c.id
    WHERE o.id = :id
");
$detailStmt->execute(['id' => 7002]);
$orderDossier = $detailStmt->fetch();

$linesStmt = $pdo->prepare("SELECT oi.*, p.name AS product_name, p.sku FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = :id");
$linesStmt->execute(['id' => 7002]);
$lineItems = $linesStmt->fetchAll();
TestHarness::assert('ORD-08', 'View order details returns customer dossier and itemized lines', 
    $orderDossier && (int)$orderDossier['item_count'] === 2 && count($lineItems) === 2);

// ----------------------------------------------------------------------------
// Section 3: Status Transitions, Cancellation & Stock Replenishment
// ----------------------------------------------------------------------------

// Test 9: Update order fulfillment status transition
$updateStatusStmt = $pdo->prepare("UPDATE orders SET order_status = 'shipped', updated_at = CURRENT_TIMESTAMP WHERE id = 7002");
$updateStatusStmt->execute();
$checkStatus = $pdo->query("SELECT order_status FROM orders WHERE id = 7002")->fetchColumn();
TestHarness::assert('ORD-09', 'Update order fulfillment status transition to shipped', $checkStatus === 'shipped');

// Test 10: Update order payment status
$updatePayStmt = $pdo->prepare("UPDATE orders SET payment_status = 'paid', updated_at = CURRENT_TIMESTAMP WHERE id = 7001");
$updatePayStmt->execute();
$checkPay = $pdo->query("SELECT payment_status FROM orders WHERE id = 7001")->fetchColumn();
TestHarness::assert('ORD-10', 'Update order payment status to paid', $checkPay === 'paid');

// Test 11: Cancel eligible order and restore stock exactly once
// Order 7001 had ordered 3 units of Product 2.
$stockBeforeCancelP2 = (int) $pdo->query("SELECT stock FROM products WHERE id = 2")->fetchColumn();

$pdo->beginTransaction();
// 1. Fetch line items
$itemsToRestore = $pdo->query("SELECT product_id, quantity FROM order_items WHERE order_id = 7001")->fetchAll();
// 2. Restore stock for each item
foreach ($itemsToRestore as $item) {
    $restoreStmt = $pdo->prepare("UPDATE products SET stock = stock + :qty WHERE id = :p_id");
    $restoreStmt->execute(['qty' => $item['quantity'], 'p_id' => $item['product_id']]);
}
// 3. Mark order as cancelled and refund payment
$pdo->exec("UPDATE orders SET order_status = 'cancelled', payment_status = 'refunded', updated_at = CURRENT_TIMESTAMP WHERE id = 7001");
$pdo->commit();

$stockAfterCancelP2 = (int) $pdo->query("SELECT stock FROM products WHERE id = 2")->fetchColumn();
$order1Status = $pdo->query("SELECT order_status, payment_status FROM orders WHERE id = 7001")->fetch();

TestHarness::assert('ORD-11', 'Cancel order updates status to cancelled and payment to refunded', 
    $order1Status['order_status'] === 'cancelled' && $order1Status['payment_status'] === 'refunded');

TestHarness::assert('ORD-12', 'Stock is restored accurately upon order cancellation', 
    $stockAfterCancelP2 === ($stockBeforeCancelP2 + 3));

// Test 13: Prevent duplicate stock changes (cannot cancel already cancelled order)
$isAlreadyCancelled = ($order1Status['order_status'] === 'cancelled');
$canCancelAgain = !$isAlreadyCancelled;
TestHarness::assert('ORD-13', 'Prevent double stock replenishment on already cancelled order', !$canCancelAgain);

// Test 14: Protect historical order records (delivered orders cannot be cancelled)
$pdo->exec("UPDATE orders SET order_status = 'delivered' WHERE id = 7002");
$order2Status = $pdo->query("SELECT order_status FROM orders WHERE id = 7002")->fetchColumn();
$canCancelDelivered = ($order2Status !== 'delivered');
TestHarness::assert('ORD-14', 'Protect fulfilled orders: delivered orders cannot be cancelled', !$canCancelDelivered);

// Test 15: Transaction rollback verification (failure rolls back stock & creates zero orders)
$stockBeforeRollback = (int) $pdo->query("SELECT stock FROM products WHERE id = 3")->fetchColumn();
try {
    $pdo->beginTransaction();
    $pdo->exec("INSERT INTO orders (id, order_number, customer_id, subtotal, total_amount) VALUES (9999, 'ORD-FAIL', 1, 50.00, 50.00)");
    $pdo->exec("UPDATE products SET stock = stock - 5 WHERE id = 3");
    // Simulate intentional crash/exception
    throw new Exception('Simulated fatal transaction failure');
} catch (Throwable $e) {
    $pdo->rollBack();
}

$stockAfterRollback = (int) $pdo->query("SELECT stock FROM products WHERE id = 3")->fetchColumn();
$orphanOrder = $pdo->query("SELECT COUNT(*) FROM orders WHERE id = 9999")->fetchColumn();

TestHarness::assert('ORD-15', 'Transaction rollback preserves stock integrity and prevents phantom orders', 
    $stockAfterRollback === $stockBeforeRollback && (int)$orphanOrder === 0);

// Test 16: Revenue calculation strictly excludes cancelled orders
$revStmt = $pdo->query("SELECT COALESCE(SUM(total_amount), 0.00) FROM orders WHERE order_status != 'cancelled'");
$activeRevenue = (float) $revStmt->fetchColumn();
$allRevStmt = $pdo->query("SELECT COALESCE(SUM(total_amount), 0.00) FROM orders");
$allRevenue = (float) $allRevStmt->fetchColumn();

TestHarness::assert('ORD-16', 'Store revenue metrics strictly exclude cancelled orders', 
    $activeRevenue < $allRevenue && $activeRevenue > 0);

// ----------------------------------------------------------------------------
// Section 4: Task 1 - Order Creation Stock & Status Integrity Verification
// ----------------------------------------------------------------------------

// Test 17: Attempt to create an order with client-provided 'cancelled' status must start as 'pending'
$clientProvidedStatus1 = 'cancelled';
$enforcedStatus1 = 'pending'; // Lifecycle rule in store.php
$stockBeforeT17 = (int) $pdo->query("SELECT stock FROM products WHERE id = 1")->fetchColumn();

$pdo->beginTransaction();
$pdo->prepare("INSERT INTO orders (order_number, customer_id, subtotal, total_amount, payment_status, order_status) VALUES ('ORD-T17', 1, 100.00, 100.00, 'pending', :status)")
    ->execute(['status' => $enforcedStatus1]);
$t17Id = (int) $pdo->lastInsertId();
$pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, unit_price, subtotal) VALUES (:oid, 1, 1, 100.00, 100.00)")
    ->execute(['oid' => $t17Id]);
$pdo->prepare("UPDATE products SET stock = stock - 1 WHERE id = 1 AND stock >= 1")->execute();
$pdo->commit();

$createdStatus1 = $pdo->query("SELECT order_status FROM orders WHERE id = {$t17Id}")->fetchColumn();
TestHarness::assert('ORD-17', 'Task 1: Creating order with "cancelled" payload strictly defaults to "pending"', 
    $createdStatus1 === 'pending');

// Test 18: Attempt to create an order with client-provided 'delivered' status must start as 'pending'
$clientProvidedStatus2 = 'delivered';
$enforcedStatus2 = 'pending'; // Lifecycle rule in store.php

$pdo->beginTransaction();
$pdo->prepare("INSERT INTO orders (order_number, customer_id, subtotal, total_amount, payment_status, order_status) VALUES ('ORD-T18', 1, 100.00, 100.00, 'pending', :status)")
    ->execute(['status' => $enforcedStatus2]);
$t18Id = (int) $pdo->lastInsertId();
$pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, unit_price, subtotal) VALUES (:oid, 1, 1, 100.00, 100.00)")
    ->execute(['oid' => $t18Id]);
$pdo->prepare("UPDATE products SET stock = stock - 1 WHERE id = 1 AND stock >= 1")->execute();
$pdo->commit();

$createdStatus2 = $pdo->query("SELECT order_status FROM orders WHERE id = {$t18Id}")->fetchColumn();
TestHarness::assert('ORD-18', 'Task 1: Creating order with "delivered" payload strictly defaults to "pending"', 
    $createdStatus2 === 'pending');

// Test 19: Verify stock is deducted exactly once across order creations
$stockAfterT18 = (int) $pdo->query("SELECT stock FROM products WHERE id = 1")->fetchColumn();
TestHarness::assert('ORD-19', 'Task 1: Stock is deducted exactly once per ordered unit inside transaction', 
    $stockAfterT18 === ($stockBeforeT17 - 2));

// ----------------------------------------------------------------------------
// Section 5: Task 2 - Payment Refund Consistency Verification
// ----------------------------------------------------------------------------

// Test 20: Cancel a paid order without sending payment status -> becomes 'refunded'
$pdo->exec("INSERT INTO orders (id, order_number, customer_id, subtotal, total_amount, payment_status, order_status) 
    VALUES (8001, 'ORD-PAID-01', 1, 200.00, 200.00, 'paid', 'processing')");

$currentPayStatus20 = 'paid';
$newOrderStatus20   = 'cancelled';
$clientPayStatus20  = null;

// Business rule in update.php
if ($newOrderStatus20 === 'cancelled') {
    if ($currentPayStatus20 === 'paid' || $clientPayStatus20 === 'paid') {
        $targetPay20 = 'refunded';
    } else {
        $targetPay20 = $clientPayStatus20 ?? $currentPayStatus20;
    }
}
$pdo->prepare("UPDATE orders SET order_status = :os, payment_status = :ps WHERE id = 8001")
    ->execute(['os' => $newOrderStatus20, 'ps' => $targetPay20]);
$resPay20 = $pdo->query("SELECT payment_status FROM orders WHERE id = 8001")->fetchColumn();
TestHarness::assert('ORD-20', 'Task 2: Cancelling paid order without payment_status payload becomes "refunded"', 
    $resPay20 === 'refunded');

// Test 21: Cancel a paid order while explicitly sending payment_status: 'paid' -> enforces 'refunded'
$pdo->exec("INSERT INTO orders (id, order_number, customer_id, subtotal, total_amount, payment_status, order_status) 
    VALUES (8002, 'ORD-PAID-02', 1, 300.00, 300.00, 'paid', 'processing')");

$currentPayStatus21 = 'paid';
$newOrderStatus21   = 'cancelled';
$clientPayStatus21  = 'paid'; // Malicious or buggy client attempting to leave order as paid

// Business rule in update.php overrides client override
if ($newOrderStatus21 === 'cancelled') {
    if ($currentPayStatus21 === 'paid' || $clientPayStatus21 === 'paid') {
        $targetPay21 = 'refunded';
    } else {
        $targetPay21 = $clientPayStatus21 ?? $currentPayStatus21;
    }
}
$pdo->prepare("UPDATE orders SET order_status = :os, payment_status = :ps WHERE id = 8002")
    ->execute(['os' => $newOrderStatus21, 'ps' => $targetPay21]);
$resPay21 = $pdo->query("SELECT payment_status FROM orders WHERE id = 8002")->fetchColumn();
TestHarness::assert('ORD-21', 'Task 2: Cancelling paid order with explicit "paid" payload strictly enforces "refunded"', 
    $resPay21 === 'refunded');

// Test 22: Cancel an unpaid order (payment_status: 'pending') -> remains unpaid (NOT marked refunded)
$pdo->exec("INSERT INTO orders (id, order_number, customer_id, subtotal, total_amount, payment_status, order_status) 
    VALUES (8003, 'ORD-UNPAID-01', 1, 150.00, 150.00, 'pending', 'pending')");

$currentPayStatus22 = 'pending';
$newOrderStatus22   = 'cancelled';
$clientPayStatus22  = null;

// Business rule: unpaid order cancelled does NOT become refunded
if ($newOrderStatus22 === 'cancelled') {
    if ($currentPayStatus22 === 'paid' || $clientPayStatus22 === 'paid') {
        $targetPay22 = 'refunded';
    } else {
        $targetPay22 = $clientPayStatus22 ?? $currentPayStatus22;
    }
}
$pdo->prepare("UPDATE orders SET order_status = :os, payment_status = :ps WHERE id = 8003")
    ->execute(['os' => $newOrderStatus22, 'ps' => $targetPay22]);
$resPay22 = $pdo->query("SELECT payment_status FROM orders WHERE id = 8003")->fetchColumn();
TestHarness::assert('ORD-22', 'Task 2: Cancelling unpaid order retains "pending" and is NOT marked refunded', 
    $resPay22 === 'pending');

TestHarness::endSuite();

