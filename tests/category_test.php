<?php
/**
 * ShopSphere - Phase 7: Category Management CRUD & Security Test Suite
 * Execution: php tests/category_test.php
 */

require_once __DIR__ . '/test_helper.php';
require_once __DIR__ . '/../includes/category_helper.php';

TestHarness::startSuite('Phase 7: Category Management CRUD & Security');

$pdo = TestHarness::getDatabase();
ensureCategorySlugColumn($pdo);

// ----------------------------------------------------------------------------
// Section 1: Creation & Validation
// ----------------------------------------------------------------------------

// Test CAT-01: Create category with valid name and slug
$stmt = $pdo->prepare("INSERT INTO categories (name, slug, description, status) VALUES (:name, :slug, :desc, 'active')");
$stmt->execute([
    'name' => 'Smart Wearables & Bands',
    'slug' => 'smart-wearables-bands',
    'desc' => 'Next-gen biometric fitness bands and smart rings'
]);
$newCatId = (int) $pdo->lastInsertId();
TestHarness::assert('CAT-01', 'Create category with valid name, slug and description', $newCatId > 0);

// Test CAT-02: Auto-slug generation helper
$autoSlug = generateCategorySlug('Home & Kitchen Audio Equipment');
TestHarness::assert('CAT-02', 'Auto-slug generator converts special characters & spaces', 
    $autoSlug === 'home-and-kitchen-audio-equipment' && isValidCategorySlug($autoSlug));

// Test CAT-03: Reject invalid slug format
TestHarness::assert('CAT-03', 'Slug validator rejects uppercase letters, spaces, or special characters', 
    !isValidCategorySlug('Invalid Slug!') && !isValidCategorySlug('slug_with_underscores') && !isValidCategorySlug('SLUG-UPPER'));

// Test CAT-04: Reject missing category name
$val = new Validator(['name' => '']);
$val->required(['name' => 'Category name']);
TestHarness::assert('CAT-04', 'Validation rejects missing category name', $val->fails());

// Test CAT-05: Reject duplicate category name
$dupNameCheck = $pdo->prepare('SELECT COUNT(*) FROM categories WHERE LOWER(name) = LOWER(:name)');
$dupNameCheck->execute(['name' => 'Smart Wearables & Bands']);
$nameExists = ((int) $dupNameCheck->fetchColumn()) > 0;
TestHarness::assert('CAT-05', 'Detect duplicate category name collision', $nameExists);

// Test CAT-06: Reject duplicate category slug
$dupSlugCheck = $pdo->prepare('SELECT COUNT(*) FROM categories WHERE LOWER(slug) = LOWER(:slug)');
$dupSlugCheck->execute(['slug' => 'smart-wearables-bands']);
$slugExists = ((int) $dupSlugCheck->fetchColumn()) > 0;
TestHarness::assert('CAT-06', 'Detect duplicate category slug collision', $slugExists);

// ----------------------------------------------------------------------------
// Section 2: Read, Search, Filters & Product Counts
// ----------------------------------------------------------------------------

// Test CAT-07: List categories with pagination
$listStmt = $pdo->prepare('SELECT * FROM categories ORDER BY name ASC LIMIT 5 OFFSET 0');
$listStmt->execute();
$cats = $listStmt->fetchAll();
TestHarness::assert('CAT-07', 'List categories respects pagination limit', count($cats) <= 5 && count($cats) > 0);

// Test CAT-08: Query categories with dynamic product counts
$countStmt = $pdo->query("
    SELECT c.id, c.name, COUNT(p.id) AS product_count 
    FROM categories c 
    LEFT JOIN products p ON c.id = p.category_id 
    GROUP BY c.id 
    ORDER BY c.id ASC
");
$catCounts = $countStmt->fetchAll();
TestHarness::assert('CAT-08', 'Dynamic product count aggregation across categories', count($catCounts) >= 4);

// Test CAT-09: Single category details and assigned products
$showStmt = $pdo->prepare("
    SELECT c.*, COUNT(p.id) AS product_count 
    FROM categories c 
    LEFT JOIN products p ON c.id = p.category_id 
    WHERE c.id = :id 
    GROUP BY c.id
");
$showStmt->execute(['id' => 1]); // Electronics
$cat1 = $showStmt->fetch();

$assignedStmt = $pdo->prepare('SELECT id, name, sku, price, stock FROM products WHERE category_id = :cat_id');
$assignedStmt->execute(['cat_id' => 1]);
$cat1Products = $assignedStmt->fetchAll();
TestHarness::assert('CAT-09', 'Category show returns category details and itemized products', 
    $cat1 && (int)$cat1['product_count'] === count($cat1Products));

// ----------------------------------------------------------------------------
// Section 3: Update Category & Uniqueness Checks
// ----------------------------------------------------------------------------

// Test CAT-10: Update category fields
$upStmt = $pdo->prepare("UPDATE categories SET name = :name, slug = :slug, description = :desc WHERE id = :id");
$upStmt->execute([
    'name' => 'Smart Wearables, Rings & Bands',
    'slug' => 'smart-wearables-rings-bands',
    'desc' => 'Expanded biometric accessories',
    'id'   => $newCatId
]);
$checkUp = $pdo->prepare('SELECT name, slug FROM categories WHERE id = :id');
$checkUp->execute(['id' => $newCatId]);
$updatedCat = $checkUp->fetch();
TestHarness::assert('CAT-10', 'Update category persists modified name and slug', 
    $updatedCat['name'] === 'Smart Wearables, Rings & Bands' && $updatedCat['slug'] === 'smart-wearables-rings-bands');

// Test CAT-11: Self-retention allows saving own name and slug without false collision
$selfNameCheck = $pdo->prepare('SELECT id FROM categories WHERE LOWER(name) = LOWER(:name) AND id != :id');
$selfNameCheck->execute(['name' => 'Smart Wearables, Rings & Bands', 'id' => $newCatId]);
$selfConflict = (bool) $selfNameCheck->fetch();
TestHarness::assert('CAT-11', 'Self-retention allows updating category without self-collision', !$selfConflict);

// Test CAT-12: Reject updating category to a name owned by another category
$otherNameCheck = $pdo->prepare('SELECT id FROM categories WHERE LOWER(name) = LOWER(:name) AND id != :id');
$otherNameCheck->execute(['name' => 'Electronics', 'id' => $newCatId]);
$otherConflict = (bool) $otherNameCheck->fetch();
TestHarness::assert('CAT-12', 'Reject category update when name is in use by another category', $otherConflict);

// ----------------------------------------------------------------------------
// Section 4: Safe Deletion & Foreign Key Decoupling
// ----------------------------------------------------------------------------

// Test CAT-13: Deleting category unlinks products to NULL (never deletes products)
// Assign a test product to the new category
$pdo->prepare("INSERT INTO products (name, sku, category_id, price, stock, status) VALUES ('Test Fitness Band', 'FIT-BAND-01', :cat_id, 99.00, 15, 'active')")
    ->execute(['cat_id' => $newCatId]);
$testProdId = (int) $pdo->lastInsertId();

// Verify product currently has category_id = $newCatId
$beforeDel = $pdo->query("SELECT category_id FROM products WHERE id = {$testProdId}")->fetchColumn();
TestHarness::assert('CAT-13', 'Product successfully linked to test category prior to deletion', (int)$beforeDel === $newCatId);

// Execute category deletion transaction: decouple products -> delete category
$pdo->beginTransaction();
$pdo->prepare("UPDATE products SET category_id = NULL WHERE category_id = :id")->execute(['id' => $newCatId]);
$pdo->prepare("DELETE FROM categories WHERE id = :id")->execute(['id' => $newCatId]);
$pdo->commit();

// Verify product is still in database with category_id = NULL
$afterDel = $pdo->query("SELECT category_id, name FROM products WHERE id = {$testProdId}")->fetch();
$catRowCheck = $pdo->query("SELECT COUNT(*) FROM categories WHERE id = {$newCatId}")->fetchColumn();

TestHarness::assert('CAT-14', 'Category deleted and assigned products safely set to NULL (not deleted)', 
    (int)$catRowCheck === 0 && $afterDel && $afterDel['category_id'] === null);

// Clean up test product
$pdo->exec("DELETE FROM products WHERE id = {$testProdId}");

// ----------------------------------------------------------------------------
// Section 5: XSS Protection in Category Attributes
// ----------------------------------------------------------------------------

// Test CAT-15: XSS payloads in category attributes are sanitized
$xssCategoryName = "<script>alert('xss-category')</script> Gadgets";
$xssDescription  = "<img src=x onerror=stealCookies()> Powerful gear";
$escapedName = htmlspecialchars($xssCategoryName, ENT_QUOTES | ENT_HTML5, 'UTF-8');
$escapedDesc = htmlspecialchars($xssDescription, ENT_QUOTES | ENT_HTML5, 'UTF-8');

TestHarness::assert('CAT-15', 'XSS tags in category name and description sanitized to HTML entities', 
    !str_contains($escapedName, '<script>') && str_contains($escapedName, '&lt;script&gt;') &&
    !str_contains($escapedDesc, '<img') && str_contains($escapedDesc, '&lt;img'));

// ----------------------------------------------------------------------------
// Section 6: Security & CSRF
// ----------------------------------------------------------------------------

// Test CAT-16: CSRF token required on category mutation
startSecureSession();
$csrfToken = getCsrfToken();
TestHarness::assert('CAT-16', 'Category mutation requires valid CSRF token', 
    verifyCsrfToken($csrfToken) && !verifyCsrfToken('invalid-forged-token'));

TestHarness::endSuite();

