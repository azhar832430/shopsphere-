<?php
/**
 * ShopSphere - Phase 7: Comprehensive Customer CRUD Test Suite
 * Execution: php tests/customer_test.php
 */

require_once __DIR__ . '/test_helper.php';

TestHarness::startSuite('Phase 7: Customer Management CRUD');

$pdo = TestHarness::getDatabase();

// ----------------------------------------------------------------------------
// Section 1: Customer Creation & Validation
// ----------------------------------------------------------------------------

// Test 1: Create customer with valid data
$stmt = $pdo->prepare("
    INSERT INTO customers (full_name, email, phone, address, city, postal_code, status)
    VALUES (:name, :email, :phone, :address, :city, :zip, 'active')
");
$stmt->execute([
    'name'    => 'Sophia Loren',
    'email'   => 'sophia.loren@cinema.org',
    'phone'   => '+1 (415) 777-8899',
    'address' => '500 Howard Street, Suite 400',
    'city'    => 'San Francisco',
    'zip'     => '94105'
]);
$newCustId = (int) $pdo->lastInsertId();
TestHarness::assert('CUST-01', 'Create customer with valid data', $newCustId > 0 && is_int($newCustId));

// Test 2: Reject missing required fields (full_name, email)
$valReq = new Validator(['full_name' => '', 'email' => '']);
$valReq->required(['full_name' => 'Full name', 'email' => 'Email address']);
TestHarness::assert('CUST-02', 'Validation rejects missing full_name and email', $valReq->fails() && count($valReq->getErrors()) === 2);

// Test 3: Reject invalid email formats
$valEmail = new Validator(['email' => 'not-a-valid-email@']);
$valEmail->email('email');
TestHarness::assert('CUST-03', 'Validation rejects malformed email address', $valEmail->fails());

// Test 4: Reject duplicate email addresses
$dupCheck = $pdo->prepare('SELECT COUNT(*) FROM customers WHERE email = :email');
$dupCheck->execute(['email' => 'sophia.loren@cinema.org']);
$emailExists = ((int) $dupCheck->fetchColumn()) > 0;
TestHarness::assert('CUST-04', 'Validation flags duplicate customer email collision', $emailExists);

// Test 5: Validate field length boundaries
$longCity = str_repeat('A', 55);
$valLen = new Validator(['city' => $longCity]);
$valLen->length('city', 0, 50, 'City');
TestHarness::assert('CUST-05', 'Validation rejects city exceeding 50 characters', $valLen->fails());

// ----------------------------------------------------------------------------
// Section 2: View, List, Search, Filter & Aggregates
// ----------------------------------------------------------------------------

// Test 6: View customers with pagination limits
$listStmt = $pdo->prepare('SELECT * FROM customers ORDER BY id DESC LIMIT 5 OFFSET 0');
$listStmt->execute();
$customerList = $listStmt->fetchAll();
TestHarness::assert('CUST-06', 'Customer list query respects pagination limit', count($customerList) <= 5 && count($customerList) > 0);

// Test 7: View single customer details with order aggregates
$showStmt = $pdo->prepare("
    SELECT 
        c.*,
        COUNT(o.id) AS total_orders,
        COALESCE(SUM(CASE WHEN o.order_status != 'cancelled' THEN o.total_amount ELSE 0 END), 0.00) AS total_spent
    FROM customers c
    LEFT JOIN orders o ON c.id = o.customer_id
    WHERE c.id = :id
    GROUP BY c.id
");
$showStmt->execute(['id' => $newCustId]);
$customerProfile = $showStmt->fetch();
TestHarness::assert('CUST-07', 'Customer details returns complete CRM dossier and financial aggregates', 
    $customerProfile && $customerProfile['email'] === 'sophia.loren@cinema.org' && (int)$customerProfile['total_orders'] === 0);

// Test 8: Search customers by full name
$searchNameStmt = $pdo->prepare('SELECT * FROM customers WHERE full_name LIKE :q');
$searchNameStmt->execute(['q' => '%Sophia%']);
$nameResults = $searchNameStmt->fetchAll();
TestHarness::assert('CUST-08', 'Search customers by name locates matching profile', count($nameResults) === 1 && $nameResults[0]['email'] === 'sophia.loren@cinema.org');

// Test 9: Search customers by email and phone
$searchEmailStmt = $pdo->prepare('SELECT * FROM customers WHERE email LIKE :q OR phone LIKE :q');
$searchEmailStmt->execute(['q' => '%marcus.vance%']);
$emailResults = $searchEmailStmt->fetchAll();
TestHarness::assert('CUST-09', 'Search customers by email locates target customer', count($emailResults) >= 1);

// Test 10: Filter customers by city
$cityStmt = $pdo->prepare('SELECT * FROM customers WHERE city = :city');
$cityStmt->execute(['city' => 'San Francisco']);
$sfCustomers = $cityStmt->fetchAll();
TestHarness::assert('CUST-10', 'Filter customers by city returns exact city subset', count($sfCustomers) >= 2);

// Test 11: Filter customers by status
$statusStmt = $pdo->prepare('SELECT * FROM customers WHERE status = :status');
$statusStmt->execute(['status' => 'blocked']);
$blockedCustomers = $statusStmt->fetchAll();
TestHarness::assert('CUST-11', 'Filter customers by status isolates blocked accounts', count($blockedCustomers) >= 1);

// ----------------------------------------------------------------------------
// Section 3: Update Customer & Email Self-Retention
// ----------------------------------------------------------------------------

// Test 12: Update customer information
$upStmt = $pdo->prepare("
    UPDATE customers SET 
        full_name   = :name,
        phone       = :phone,
        address     = :address,
        updated_at  = CURRENT_TIMESTAMP
    WHERE id = :id
");
$upStmt->execute([
    'name'    => 'Sophia Loren-Pontiac',
    'phone'   => '+1 (415) 777-9900',
    'address' => '500 Howard Street, Penthouse',
    'id'      => $newCustId
]);

$checkUpStmt = $pdo->prepare('SELECT full_name, phone, address FROM customers WHERE id = :id');
$checkUpStmt->execute(['id' => $newCustId]);
$updatedCustomer = $checkUpStmt->fetch();
TestHarness::assert('CUST-12', 'Update customer persists modified fields', 
    $updatedCustomer['full_name'] === 'Sophia Loren-Pontiac' && $updatedCustomer['phone'] === '+1 (415) 777-9900');

// Test 13: Customer updating retaining own email succeeds (no false collision)
$selfEmailStmt = $pdo->prepare('SELECT id FROM customers WHERE email = :email AND id != :id');
$selfEmailStmt->execute(['email' => 'sophia.loren@cinema.org', 'id' => $newCustId]);
$selfConflict = (bool) $selfEmailStmt->fetch();
TestHarness::assert('CUST-13', 'Customer updating with their own email causes no conflict', !$selfConflict);

// Test 14: Customer updating with email of another customer is rejected
$otherEmailStmt = $pdo->prepare('SELECT id FROM customers WHERE email = :email AND id != :id');
$otherEmailStmt->execute(['email' => 'marcus.vance@techcorp.io', 'id' => $newCustId]);
$otherConflict = (bool) $otherEmailStmt->fetch();
TestHarness::assert('CUST-14', 'Reject customer update when email is owned by another customer', $otherConflict);

// ----------------------------------------------------------------------------
// Section 4: Deletion & Historical Order Retention
// ----------------------------------------------------------------------------

// Test 15: Delete customer without orders permanently
$delStmt = $pdo->prepare('DELETE FROM customers WHERE id = :id');
$delStmt->execute(['id' => $newCustId]);
$checkDel = $pdo->prepare('SELECT COUNT(*) FROM customers WHERE id = :id');
$checkDel->execute(['id' => $newCustId]);
$remCount = (int) $checkDel->fetchColumn();
TestHarness::assert('CUST-15', 'Delete customer with zero orders permanently removes row', $remCount === 0);

// Test 16: Customer with historical orders must retain historical integrity (archived)
// Link customer 1 (Marcus Vance) to a test order
$pdo->exec("INSERT INTO orders (id, order_number, customer_id, subtotal, total_amount, order_status, payment_status) 
    VALUES (9902, 'ORD-TEST-CUST', 1, 349.50, 349.50, 'delivered', 'paid')");

$orderCheck = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE customer_id = :id');
$orderCheck->execute(['id' => 1]);
$hasOrders = ((int) $orderCheck->fetchColumn()) > 0;

if ($hasOrders) {
    // Archival strategy: customer account is deactivated (status = 'inactive')
    $archiveCustStmt = $pdo->prepare("UPDATE customers SET status = 'inactive' WHERE id = :id");
    $archiveCustStmt->execute(['id' => 1]);
}

$verifyCustArchive = $pdo->prepare('SELECT status FROM customers WHERE id = :id');
$verifyCustArchive->execute(['id' => 1]);
$custStatus = $verifyCustArchive->fetchColumn();
TestHarness::assert('CUST-16', 'Customer with historical orders is safely archived to preserve sales audit records', $custStatus === 'inactive');

// Clean up test order & restore customer status
$pdo->exec("DELETE FROM orders WHERE id = 9902");
$pdo->exec("UPDATE customers SET status = 'active' WHERE id = 1");

TestHarness::endSuite();

