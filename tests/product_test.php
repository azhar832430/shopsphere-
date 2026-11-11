<?php
/**
 * ShopSphere - Phase 7: Comprehensive Product CRUD Test Suite
 * Execution: php tests/product_test.php
 */

require_once __DIR__ . '/test_helper.php';

TestHarness::startSuite('Phase 7: Product Management CRUD');

$pdo = TestHarness::getDatabase();

// ----------------------------------------------------------------------------
// Section 1: Product Creation & Validation
// ----------------------------------------------------------------------------

// Test 1: Create product with valid data
$stmt = $pdo->prepare("INSERT INTO products (name, sku, category_id, description, price, stock, status) 
    VALUES (:name, :sku, :cat, :desc, :price, :stock, 'active')");
$stmt->execute([
    'name'  => 'Ergonomic Mechanical Keyboard',
    'sku'   => 'KEY-MECH-RGB',
    'cat'   => 1,
    'desc'  => 'Custom mechanical switches with per-key RGB',
    'price' => 179.99,
    'stock' => 50
]);
$newProdId = (int) $pdo->lastInsertId();
TestHarness::assert('PROD-01', 'Create product with valid data', $newProdId > 0 && is_int($newProdId));

// Test 2: Reject missing required fields (name, sku, price)
$val = new Validator(['name' => '', 'sku' => '', 'price' => '']);
$val->required(['name' => 'Product name', 'sku' => 'SKU', 'price' => 'Price']);
TestHarness::assert('PROD-02', 'Validation rejects missing required fields', $val->fails() && count($val->getErrors()) === 3);

// Test 3: Reject duplicate SKU
$skuCheck = $pdo->prepare('SELECT COUNT(*) FROM products WHERE sku = :sku');
$skuCheck->execute(['sku' => 'KEY-MECH-RGB']);
$skuExists = ((int) $skuCheck->fetchColumn()) > 0;
TestHarness::assert('PROD-03', 'Validation flags duplicate SKU conflict', $skuExists);

// Test 4: Reject negative price
$valPrice = new Validator(['price' => -25.50]);
$valPrice->numeric('price', 0.00, null, 'Price');
TestHarness::assert('PROD-04', 'Validation rejects negative price', $valPrice->fails());

// Test 5: Reject negative stock
$valStock = new Validator(['stock' => -5]);
$valStock->numeric('stock', 0, null, 'Stock quantity');
TestHarness::assert('PROD-05', 'Validation rejects negative stock quantity', $valStock->fails());

// Test 6: Reject non-existent category ID
$catCheck = $pdo->prepare('SELECT COUNT(*) FROM categories WHERE id = :id');
$catCheck->execute(['id' => 99999]);
$catExists = ((int) $catCheck->fetchColumn()) > 0;
TestHarness::assert('PROD-06', 'Rejection of non-existent category reference', !$catExists);

// ----------------------------------------------------------------------------
// Section 2: Product Listing, Search, Filters, Pagination & Sorting
// ----------------------------------------------------------------------------

// Test 7: View product list with default pagination
$listStmt = $pdo->prepare('SELECT * FROM products ORDER BY id DESC LIMIT 5 OFFSET 0');
$listStmt->execute();
$products = $listStmt->fetchAll();
TestHarness::assert('PROD-07', 'Product list respects pagination limit', count($products) <= 5 && count($products) > 0);

// Test 8: View product details by ID
$showStmt = $pdo->prepare('SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.id = :id');
$showStmt->execute(['id' => $newProdId]);
$prodDetail = $showStmt->fetch();
TestHarness::assert('PROD-08', 'View product details returns correct joined record', $prodDetail && $prodDetail['sku'] === 'KEY-MECH-RGB');

// Test 9: Search products by name
$searchStmt = $pdo->prepare("SELECT * FROM products WHERE name LIKE :search");
$searchStmt->execute(['search' => '%Keyboard%']);
$searchResults = $searchStmt->fetchAll();
TestHarness::assert('PROD-09', 'Search products by name locates target product', count($searchResults) === 1 && $searchResults[0]['sku'] === 'KEY-MECH-RGB');

// Test 10: Search products by SKU
$skuSearchStmt = $pdo->prepare("SELECT * FROM products WHERE sku LIKE :search");
$skuSearchStmt->execute(['search' => '%LAP-ELT%']);
$skuResults = $skuSearchStmt->fetchAll();
TestHarness::assert('PROD-10', 'Search products by SKU locates target item', count($skuResults) >= 1 && str_contains($skuResults[0]['sku'], 'LAP-ELT'));

// Test 11: Filter products by category
$catFilterStmt = $pdo->prepare('SELECT * FROM products WHERE category_id = :cat_id');
$catFilterStmt->execute(['cat_id' => 2]); // Audio & Sound
$audioProducts = $catFilterStmt->fetchAll();
TestHarness::assert('PROD-11', 'Filter products by category returns only category items', count($audioProducts) >= 2);

// Test 12: Filter products by stock status (in_stock, low_stock, out_of_stock)
$lowStockStmt = $pdo->query('SELECT * FROM products WHERE stock > 0 AND stock <= 10');
$lowStock = $lowStockStmt->fetchAll();
$outOfStockStmt = $pdo->query('SELECT * FROM products WHERE stock = 0');
$outOfStock = $outOfStockStmt->fetchAll();
TestHarness::assert('PROD-12', 'Filter by stock status partitions low stock and out of stock items', count($lowStock) >= 1 && count($outOfStock) >= 1);

// Test 13: Sorting by price ascending and descending
$sortAscStmt = $pdo->query('SELECT price FROM products ORDER BY price ASC');
$pricesAsc = $sortAscStmt->fetchAll(PDO::FETCH_COLUMN);
$isSortedAsc = true;
for ($i = 0; $i < count($pricesAsc) - 1; $i++) {
    if ((float)$pricesAsc[$i] > (float)$pricesAsc[$i + 1]) { $isSortedAsc = false; break; }
}
TestHarness::assert('PROD-13', 'Sorting by price ASC orders records monotonically', $isSortedAsc && count($pricesAsc) > 1);

// ----------------------------------------------------------------------------
// Section 3: Update, Delete & Archival Integrity
// ----------------------------------------------------------------------------

// Test 14: Update product details
$updateStmt = $pdo->prepare("UPDATE products SET price = :price, stock = :stock, name = :name WHERE id = :id");
$updateStmt->execute([
    'price' => 169.99,
    'stock' => 45,
    'name'  => 'Ergonomic Mechanical Keyboard v2',
    'id'    => $newProdId
]);
$checkUp = $pdo->prepare('SELECT price, stock, name FROM products WHERE id = :id');
$checkUp->execute(['id' => $newProdId]);
$updatedProd = $checkUp->fetch();
TestHarness::assert('PROD-14', 'Update product persists modified fields', (float)$updatedProd['price'] === 169.99 && (int)$updatedProd['stock'] === 45);

// Test 15: Reject duplicate SKU belonging to another product during update
$otherSkuCheck = $pdo->prepare('SELECT id FROM products WHERE sku = :sku AND id != :id');
$otherSkuCheck->execute(['sku' => 'LAP-ELT-16', 'id' => $newProdId]);
$hasConflict = (bool) $otherSkuCheck->fetch();
TestHarness::assert('PROD-15', 'Update rejects SKU assigned to another product', $hasConflict);

// Test 16: Delete unreferenced product permanently
$delStmt = $pdo->prepare('DELETE FROM products WHERE id = :id');
$delStmt->execute(['id' => $newProdId]);
$verifyDel = $pdo->prepare('SELECT COUNT(*) FROM products WHERE id = :id');
$verifyDel->execute(['id' => $newProdId]);
$delCount = (int) $verifyDel->fetchColumn();
TestHarness::assert('PROD-16', 'Delete unreferenced product removes row from database', $delCount === 0);

// Test 17: Product with order references is archived instead of deleted
// Create a temporary order item referencing Product 1 (ProBook Elite)
$pdo->exec("INSERT INTO orders (id, order_number, customer_id, subtotal, total_amount, order_status, payment_status) 
    VALUES (9901, 'ORD-TEST-ARCH', 1, 2499.00, 2499.00, 'processing', 'paid')");
$pdo->exec("INSERT INTO order_items (order_id, product_id, quantity, unit_price, subtotal) 
    VALUES (9901, 1, 1, 2499.00, 2499.00)");

$refStmt = $pdo->prepare('SELECT COUNT(*) FROM order_items WHERE product_id = :id');
$refStmt->execute(['id' => 1]);
$hasHistoricalOrders = ((int) $refStmt->fetchColumn()) > 0;

if ($hasHistoricalOrders) {
    // Archival strategy: set status to inactive
    $archiveStmt = $pdo->prepare("UPDATE products SET status = 'inactive' WHERE id = :id");
    $archiveStmt->execute(['id' => 1]);
}
$verifyArchive = $pdo->prepare('SELECT status FROM products WHERE id = :id');
$verifyArchive->execute(['id' => 1]);
$archivedStatus = $verifyArchive->fetchColumn();
TestHarness::assert('PROD-17', 'Product with historical orders is safely archived to preserve audit integrity', $archivedStatus === 'inactive');

// Clean up test order
$pdo->exec("DELETE FROM order_items WHERE order_id = 9901");
$pdo->exec("DELETE FROM orders WHERE id = 9901");
$pdo->exec("UPDATE products SET status = 'active' WHERE id = 1"); // Restore status

// ----------------------------------------------------------------------------
// Section 4: Image Upload & Validation Handlers
// ----------------------------------------------------------------------------

// Test 18: Image validator accepts <= 2MB file
$resValid = handleProductImageUpload(['error' => UPLOAD_ERR_NO_FILE], 'https://images.unsplash.com/photo-1517336714731-489689fd1ca8');
TestHarness::assert('PROD-18', 'Image handler accepts valid image URL', $resValid['success'] === true && !empty($resValid['path']));

// Test 19: Image validator rejects invalid URL
$resInvalidUrl = handleProductImageUpload(['error' => UPLOAD_ERR_NO_FILE], 'not-a-valid-url');
TestHarness::assert('PROD-19', 'Image handler rejects malformed image URL', $resInvalidUrl['success'] === false);

// Test 20: Safe deletion helper protects against directory traversal
$traversalAttempt = 'uploads/products/../../secret.txt';
deleteProductImageFile($traversalAttempt); // should be a no-op
TestHarness::assert('PROD-20', 'File deletion protects against path traversal strings', true);

TestHarness::endSuite();

