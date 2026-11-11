<?php
/**
 * ShopSphere - Create Category API Endpoint
 * Phase 7: Complete Category CRUD
 * 
 * Route: POST /api/categories/store.php
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
    $errors['slug'] = 'Category slug may only contain lowercase letters, numbers, and hyphens (e.g., smart-phones).';
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

    // 2. Check for duplicate name
    $nameCheck = $pdo->prepare('SELECT id FROM categories WHERE LOWER(name) = LOWER(:name) LIMIT 1');
    $nameCheck->execute(['name' => $name]);
    if ($nameCheck->fetch()) {
        sendError('Category name already exists.', [
            'name' => "A category named \"{$name}\" already exists in the taxonomy."
        ], 400);
    }

    // 3. Check for duplicate slug
    $slugCheck = $pdo->prepare('SELECT id FROM categories WHERE LOWER(slug) = LOWER(:slug) LIMIT 1');
    $slugCheck->execute(['slug' => $slug]);
    if ($slugCheck->fetch()) {
        sendError('Category slug already exists.', [
            'slug' => "The slug \"{$slug}\" is already assigned to another category."
        ], 400);
    }

    // 4. Insert Category record
    $insertStmt = $pdo->prepare("
        INSERT INTO categories (name, slug, description, status, created_at, updated_at)
        VALUES (:name, :slug, :desc, :status, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
    ");

    $insertStmt->execute([
        'name'   => $name,
        'slug'   => $slug,
        'desc'   => $description !== '' ? $description : null,
        'status' => $status
    ]);

    $newId = (int) $pdo->lastInsertId();

    $fetchStmt = $pdo->prepare('SELECT * FROM categories WHERE id = :id LIMIT 1');
    $fetchStmt->execute(['id' => $newId]);
    $created = $fetchStmt->fetch();

    sendSuccess([
        'category' => [
            'id'            => (int) $created['id'],
            'name'          => $created['name'],
            'slug'          => $created['slug'],
            'description'   => $created['description'] ?? '',
            'status'        => $created['status'] ?? 'active',
            'product_count' => 0,
            'created_at'    => $created['created_at'],
            'updated_at'    => $created['updated_at']
        ]
    ], "Category \"{$name}\" created successfully.", 201);

} catch (PDOException $e) {
    logSystemError("Database error in categories/store.php: " . $e->getMessage());
    sendServerError('Failed to create category in database.');
} catch (Throwable $e) {
    logSystemError("Unexpected error in categories/store.php: " . $e->getMessage());
    sendServerError('An unexpected error occurred while creating the category.');
}

