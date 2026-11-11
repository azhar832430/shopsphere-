<?php
/**
 * ShopSphere - Update Category API Endpoint
 * Phase 7: Complete Category CRUD
 * 
 * Route: POST /api/categories/update.php?id={id}
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/validation.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/category_helper.php';

// Method check
requireMethod('POST');

// Authentication & CSRF
requireAuth();
requireCsrf();

$input = getJsonInput();
$id = isset($input['id']) ? (int) $input['id'] : (isset($_GET['id']) ? (int) $_GET['id'] : 0);

if ($id <= 0) {
    sendError('Invalid or missing category ID.', ['id' => 'Valid positive category ID is required.'], 400);
}

$name        = sanitizeString($input['name'] ?? '');
$slug        = sanitizeString($input['slug'] ?? '');
$description = sanitizeString($input['description'] ?? '');
$status      = strtolower(sanitizeString($input['status'] ?? 'active'));

if ($slug === '' && $name !== '') {
    $slug = generateCategorySlug($name);
}

// 1. Validation
$errors = [];

if ($name === '') {
    $errors['name'] = 'Category name is required.';
} elseif (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
    $errors['name'] = 'Category name must be between 2 and 100 characters.';
}

if ($slug === '') {
    $errors['slug'] = 'Category slug is required.';
} elseif (!isValidCategorySlug($slug)) {
    $errors['slug'] = 'Category slug may only contain lowercase letters, numbers, and hyphens.';
} elseif (mb_strlen($slug) > 100) {
    $errors['slug'] = 'Category slug cannot exceed 100 characters.';
}

if (!in_array($status, ['active', 'inactive'], true)) {
    $status = 'active';
}

if (!empty($errors)) {
    sendError('Validation failed. Please correct the highlighted errors.', $errors, 400);
}

try {
    $pdo = Database::getConnection();
    ensureCategorySlugColumn($pdo);

    // 2. Verify category exists
    $checkStmt = $pdo->prepare('SELECT id, name, slug FROM categories WHERE id = :id LIMIT 1');
    $checkStmt->execute(['id' => $id]);
    $existing = $checkStmt->fetch();

    if (!$existing) {
        sendNotFound("Category with ID {$id} was not found.");
    }

    // 3. Name collision check excluding self
    $nameCheck = $pdo->prepare('SELECT id FROM categories WHERE LOWER(name) = LOWER(:name) AND id != :id LIMIT 1');
    $nameCheck->execute(['name' => $name, 'id' => $id]);
    if ($nameCheck->fetch()) {
        sendError('Category name already in use.', [
            'name' => "Another category is already named \"{$name}\"."
        ], 400);
    }

    // 4. Slug collision check excluding self
    $slugCheck = $pdo->prepare('SELECT id FROM categories WHERE LOWER(slug) = LOWER(:slug) AND id != :id LIMIT 1');
    $slugCheck->execute(['slug' => $slug, 'id' => $id]);
    if ($slugCheck->fetch()) {
        sendError('Category slug already in use.', [
            'slug' => "The slug \"{$slug}\" is already assigned to another category."
        ], 400);
    }

    // 5. Update Category
    $updateStmt = $pdo->prepare("
        UPDATE categories SET
            name        = :name,
            slug        = :slug,
            description = :desc,
            status      = :status,
            updated_at  = CURRENT_TIMESTAMP
        WHERE id = :id
    ");

    $updateStmt->execute([
        'name'   => $name,
        'slug'   => $slug,
        'desc'   => $description !== '' ? $description : null,
        'status' => $status,
        'id'     => $id
    ]);

    // 6. Return updated record with product count
    $fetchStmt = $pdo->prepare("
        SELECT c.*, COUNT(p.id) AS product_count 
        FROM categories c 
        LEFT JOIN products p ON c.id = p.category_id 
        WHERE c.id = :id 
        GROUP BY c.id
    ");
    $fetchStmt->execute(['id' => $id]);
    $updated = $fetchStmt->fetch();

    sendSuccess([
        'category' => [
            'id'            => (int) $updated['id'],
            'name'          => $updated['name'],
            'slug'          => $updated['slug'],
            'description'   => $updated['description'] ?? '',
            'status'        => $updated['status'] ?? 'active',
            'product_count' => (int) $updated['product_count'],
            'created_at'    => $updated['created_at'],
            'updated_at'    => $updated['updated_at']
        ]
    ], "Category \"{$name}\" updated successfully.");

} catch (PDOException $e) {
    logSystemError("Database error in categories/update.php (ID: {$id}): " . $e->getMessage());
    sendServerError('Failed to update category in database.');
} catch (Throwable $e) {
    logSystemError("Unexpected error in categories/update.php: " . $e->getMessage());
    sendServerError('An unexpected error occurred while updating the category.');
}

