<?php
/**
 * ShopSphere - Update Existing Product
 * Phase 4: Product Management CRUD
 * 
 * Route: POST /api/products/update.php
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

$id          = isset($input['id']) ? (int) $input['id'] : (isset($_GET['id']) ? (int) $_GET['id'] : 0);
$name        = sanitizeString($input['name'] ?? '');
$sku         = strtoupper(sanitizeString($input['sku'] ?? ''));
$categoryId  = !empty($input['category_id']) ? (int) $input['category_id'] : null;
$priceRaw    = $input['price'] ?? null;
$stockRaw    = $input['stock'] ?? null;
$description = sanitizeString($input['description'] ?? '');
$status      = sanitizeString($input['status'] ?? 'active');
$imageUrl    = sanitizeString($input['image'] ?? ($input['image_url'] ?? ''));

if ($id <= 0) {
    sendError('Invalid or missing product ID.', ['id' => 'Valid product ID is required.'], 400);
}

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

    // 2. Fetch existing product
    $existStmt = $pdo->prepare('SELECT * FROM products WHERE id = :id LIMIT 1');
    $existStmt->execute(['id' => $id]);
    $existing = $existStmt->fetch();

    if (!$existing) {
        sendNotFound("Product with ID {$id} does not exist.");
    }

    // 3. Check SKU Uniqueness (excluding self)
    $skuCheck = $pdo->prepare('SELECT id FROM products WHERE sku = :sku AND id != :id LIMIT 1');
    $skuCheck->execute(['sku' => $sku, 'id' => $id]);
    if ($skuCheck->fetch()) {
        sendError('Validation failed.', ['sku' => "SKU '{$sku}' is already assigned to another catalog product."], 400);
    }

    // 4. Verify Category Exists (if provided)
    if ($categoryId !== null) {
        $catCheck = $pdo->prepare('SELECT id FROM categories WHERE id = :cat_id LIMIT 1');
        $catCheck->execute(['cat_id' => $categoryId]);
        if (!$catCheck->fetch()) {
            sendError('Validation failed.', ['category_id' => 'The selected product category does not exist.'], 400);
        }
    }

    // 5. Handle Image (Preserve existing if no new upload/URL)
    $uploadedFile = $_FILES['image'] ?? ($_FILES['product_image'] ?? null);
    $uploadResult = handleProductImageUpload($uploadedFile, $imageUrl, $existing['image']);

    if (!$uploadResult['success']) {
        sendError('Image update failed.', ['image' => $uploadResult['error']], 400);
    }

    $finalImage = $uploadResult['path'];

    // 6. Update Record
    $stmt = $pdo->prepare('
        UPDATE products SET 
            category_id = :category_id,
            name        = :name,
            sku         = :sku,
            description = :description,
            price       = :price,
            stock       = :stock,
            image       = :image,
            status      = :status,
            updated_at  = CURRENT_TIMESTAMP
        WHERE id = :id
    ');

    $stmt->execute([
        'category_id' => $categoryId,
        'name'        => $name,
        'sku'         => $sku,
        'description' => $description !== '' ? $description : null,
        'price'       => $price,
        'stock'       => $stock,
        'image'       => $finalImage,
        'status'      => $status,
        'id'          => $id
    ]);

    // Fetch updated product for response
    $fetchStmt = $pdo->prepare('
        SELECT p.*, COALESCE(c.name, "Uncategorized") as category_name 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        WHERE p.id = :id
    ');
    $fetchStmt->execute(['id' => $id]);
    $updatedProduct = $fetchStmt->fetch();

    sendSuccess("Product '{$name}' updated successfully.", [
        'product' => $updatedProduct
    ]);

} catch (PDOException $e) {
    logSystemError("Database error in products/update.php (ID: {$id}): " . $e->getMessage());
    sendServerError('Failed to update product in database.');
} catch (Throwable $e) {
    logSystemError("Unexpected error in products/update.php: " . $e->getMessage());
    sendServerError('An unexpected error occurred while updating the product.');
}
