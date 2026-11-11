<?php
/**
 * ShopSphere - Create Customer API Endpoint
 * Phase 5: Complete Customer Management CRUD
 * 
 * Route: POST /api/customers/store.php
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/validation.php';
require_once __DIR__ . '/../../includes/auth.php';

// Method check
requireMethod('POST');

// Authentication & CSRF
requireAuth();
requireCsrf();

$input = getJsonInput();

// Handle full_name or combined first_name + last_name
$fullName = sanitizeString($input['full_name'] ?? '');
if (empty($fullName) && (!empty($input['first_name']) || !empty($input['last_name']))) {
    $fullName = trim(($input['first_name'] ?? '') . ' ' . ($input['last_name'] ?? ''));
}

$email      = strtolower(sanitizeString($input['email'] ?? ''));
$phone      = sanitizeString($input['phone'] ?? '');
$address    = sanitizeString($input['address'] ?? '');
$city       = sanitizeString($input['city'] ?? '');
$postalCode = sanitizeString($input['postal_code'] ?? ($input['postalCode'] ?? ''));
$status     = strtolower(sanitizeString($input['status'] ?? 'active'));

if (!in_array($status, ['active', 'inactive', 'blocked'], true)) {
    $status = 'active';
}

// 1. Validation Engine
$validator = new Validator([
    'full_name' => $fullName,
    'email'     => $email
]);

$validator->required([
    'full_name' => 'Full name',
    'email'     => 'Email address'
])
->length('full_name', 2, 100, 'Full name')
->email('email', 'Email address');

$errors = $validator->getErrors();

// Validate optional phone number format (valid international patterns)
if ($phone !== '') {
    if (strlen($phone) < 7 || strlen($phone) > 30 || !preg_match('/^\+?[0-9\s\-\(\)\.]{7,30}$/', $phone)) {
        $errors['phone'] = 'Please provide a valid phone number (7 to 30 digits, symbols +, -, (), spaces).';
    }
}

// Validate field length bounds
if (strlen($address) > 500) {
    $errors['address'] = 'Street address cannot exceed 500 characters.';
}
if (strlen($city) > 50) {
    $errors['city'] = 'City name cannot exceed 50 characters.';
}
if (strlen($postalCode) > 20) {
    $errors['postal_code'] = 'Postal / Zip code cannot exceed 20 characters.';
}

if (!empty($errors)) {
    sendError('Validation failed. Please correct the highlighted errors.', $errors, 400);
}

try {
    $pdo = Database::getConnection();

    // 2. Check for duplicate email uniqueness
    $emailStmt = $pdo->prepare('SELECT id FROM customers WHERE email = :email LIMIT 1');
    $emailStmt->execute(['email' => $email]);
    if ($emailStmt->fetch()) {
        sendError('Email address already registered.', [
            'email' => 'This email address is already assigned to an existing customer.'
        ], 400);
    }

    // 3. Insert customer record using prepared statement
    $insertStmt = $pdo->prepare("
        INSERT INTO customers (
            full_name,
            email,
            phone,
            address,
            city,
            postal_code,
            status,
            created_at,
            updated_at
        ) VALUES (
            :full_name,
            :email,
            :phone,
            :address,
            :city,
            :postal_code,
            :status,
            CURRENT_TIMESTAMP,
            CURRENT_TIMESTAMP
        )
    ");

    $insertStmt->execute([
        'full_name'   => $fullName,
        'email'       => $email,
        'phone'       => $phone !== '' ? $phone : null,
        'address'     => $address !== '' ? $address : null,
        'city'        => $city !== '' ? $city : null,
        'postal_code' => $postalCode !== '' ? $postalCode : null,
        'status'      => $status
    ]);

    $newId = (int) $pdo->lastInsertId();

    // 4. Retrieve saved record
    $fetchStmt = $pdo->prepare('SELECT * FROM customers WHERE id = :id LIMIT 1');
    $fetchStmt->execute(['id' => $newId]);
    $created = $fetchStmt->fetch();

    sendSuccess([
        'customer' => [
            'id'           => (int) $created['id'],
            'full_name'    => $created['full_name'],
            'email'        => $created['email'],
            'phone'        => $created['phone'] ?? '',
            'address'      => $created['address'] ?? '',
            'city'         => $created['city'] ?? '',
            'postal_code'  => $created['postal_code'] ?? '',
            'status'       => $created['status'] ?? 'active',
            'created_at'   => $created['created_at'],
            'updated_at'   => $created['updated_at'],
            'total_orders' => 0,
            'total_spent'  => 0.00
        ]
    ], "Customer \"{$fullName}\" created successfully.", 201);

} catch (Throwable $e) {
    sendServerError('An error occurred while creating the customer record.', ['error' => $e->getMessage()]);
}

