<?php
/**
 * ShopSphere - Create New Product
 * Phase 4: Product Management CRUD
 * 
 * Route: POST /api/products/store.php
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/validation.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/upload.php';

// Method check
requireMethod('POST');

// Authentication & CSRF
requireAuth();
requireCsrf();

// Determine input payload (supports JSON or multipart form-data)
$input = getJsonInput();

$name        = sanitizeString($input['name'] ?? '');
$sku         = strtoupper(sanitizeString($input['sku'] ?? ''));
$categoryId  = !empty($input['category_id']) ? (int) $input['category_id'] : null;
$priceRaw    = $input['price'] ?? null;
$stockRaw    = $input['stock'] ?? null;
$description = sanitizeString($input['description'] ?? '');
$status      = sanitizeString($input['status'] ?? 'active');
$imageUrl    = sanitizeString($input['image'] ?? ($input['image_url'] ?? ''));

// 1. Validation
$validator = new Validator([
    'name'  => $name,
    'sku'   => $sku,
    'price' => $priceRaw,
    'stock' => $stockRaw
]);

$validator->required([
    'name'  => 'Product name',
    'sku'   => 'Product SKU',
    'price' => 'Product price',
    'stock' => 'Stock quantity'
])
->length('name', 2, 150, 'Product name')
->length('sku', 2, 50, 'Product SKU')
->numeric('price', 0.00, null, 'Price')
->numeric('stock', 0, null, 'Stock quantity');

if (!in_array($status, ['active', 'inactive'], true)) {
    $status = 'active';
}

if ($validator->fails()) {
    sendError('Validation failed. Please correct the highlighted errors.', $validator->getErrors(), 400);
}

$price = round((float) $priceRaw, 2);
$stock = (int) $stockRaw;

try {
    $pdo = Database::getConnection();

    // 2. Check SKU Uniqueness
    $skuCheck = $pdo->prepare('SELECT id FROM products WHERE sku = :sku LIMIT 1');
    $skuCheck->execute(['sku' => $sku]);
    if ($skuCheck->fetch()) {
        sendError('Validation failed.', ['sku' => "Product SKU '{$sku}' is already in use by another catalog item."], 400);
    }

    // 3. Verify Category Exists (if provided)
    if ($categoryId !== null) {
        $catCheck = $pdo->prepare('SELECT id FROM categories WHERE id = :cat_id LIMIT 1');
        $catCheck->execute(['cat_id' => $categoryId]);
        if (!$catCheck->fetch()) {
            sendError('Validation failed.', ['category_id' => 'The selected product category does not exist.'], 400);
        }
    }

    // 4. Handle Image Upload or URL
    $uploadedFile = $_FILES['image'] ?? ($_FILES['product_image'] ?? null);
    $uploadResult = handleProductImageUpload($uploadedFile, $imageUrl);

    if (!$uploadResult['success']) {
        sendError('Image validation failed.', ['image' => $uploadResult['error']], 400);
    }

    $finalImage = $uploadResult['path'];

    // 5. Insert Record
    $stmt = $pdo->prepare('
        INSERT INTO products (category_id, name, sku, description, price, stock, image, status)
        VALUES (:category_id, :name, :sku, :description, :price, :stock, :image, :status)
    ');

    $stmt->execute([
        'category_id' => $categoryId,
        'name'        => $name,
        'sku'         => $sku,
        'description' => $description !== '' ? $description : null,
        'price'       => $price,
        'stock'       => $stock,
        'image'       => $finalImage,
        'status'      => $status
    ]);

    $newId = (int) $pdo->lastInsertId();

    // Fetch created product for response
    $fetchStmt = $pdo->prepare('
        SELECT p.*, COALESCE(c.name, "Uncategorized") as category_name 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        WHERE p.id = :id
    ');
    $fetchStmt->execute(['id' => $newId]);
    $createdProduct = $fetchStmt->fetch();

    sendSuccess("Product '{$name}' created successfully.", [
        'product' => $createdProduct
    ], 201);

} catch (PDOException $e) {
    logSystemError('Database error in products/store.php: ' . $e->getMessage());
    sendServerError('Failed to save product to database.');
} catch (Throwable $e) {
    logSystemError('Unexpected error in products/store.php: ' . $e->getMessage());
    sendServerError('An unexpected error occurred while creating the product.');
}

