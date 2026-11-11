<?php
/**
 * ShopSphere - Update Customer API Endpoint
 * Phase 5: Complete Customer Management CRUD
 * 
 * Route: POST /api/customers/update.php?id={id}
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
$id = isset($input['id']) ? (int) $input['id'] : (isset($_GET['id']) ? (int) $_GET['id'] : 0);

if ($id <= 0) {
    sendError('Invalid or missing customer ID.', ['id' => 'Valid positive customer ID is required.'], 400);
}

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

// Phone format check
if ($phone !== '') {
    if (strlen($phone) < 7 || strlen($phone) > 30 || !preg_match('/^\+?[0-9\s\-\(\)\.]{7,30}$/', $phone)) {
        $errors['phone'] = 'Please provide a valid phone number (7 to 30 digits, symbols +, -, (), spaces).';
    }
}

// Length checks
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

    // 2. Verify target customer existence
    $checkStmt = $pdo->prepare('SELECT id, full_name, email FROM customers WHERE id = :id LIMIT 1');
    $checkStmt->execute(['id' => $id]);
    $existing = $checkStmt->fetch();

    if (!$existing) {
        sendNotFound("Customer with ID {$id} was not found.");
    }

    // 3. Unique email check excluding current customer
    $dupStmt = $pdo->prepare('SELECT id FROM customers WHERE email = :email AND id != :id LIMIT 1');
    $dupStmt->execute(['email' => $email, 'id' => $id]);
    if ($dupStmt->fetch()) {
        sendError('Email address already registered.', [
            'email' => 'This email address is already assigned to another customer.'
        ], 400);
    }

    // 4. Update customer using prepared statement (does not alter historical orders)
    $updateStmt = $pdo->prepare("
        UPDATE customers SET
            full_name   = :full_name,
            email       = :email,
            phone       = :phone,
            address     = :address,
            city        = :city,
            postal_code = :postal_code,
            status      = :status,
            updated_at  = CURRENT_TIMESTAMP
        WHERE id = :id
    ");

    $updateStmt->execute([
        'full_name'   => $fullName,
        'email'       => $email,
        'phone'       => $phone !== '' ? $phone : null,
        'address'     => $address !== '' ? $address : null,
        'city'        => $city !== '' ? $city : null,
        'postal_code' => $postalCode !== '' ? $postalCode : null,
        'status'      => $status,
        'id'          => $id
    ]);

    // 5. Retrieve updated customer with order aggregates
    $fetchStmt = $pdo->prepare("
        SELECT 
            c.*,
            COUNT(o.id) AS total_orders,
            COALESCE(SUM(CASE WHEN o.order_status != 'cancelled' THEN o.total_amount ELSE 0 END), 0.00) AS total_spent
        FROM customers c
        LEFT JOIN orders o ON c.id = o.customer_id
        WHERE c.id = :id
        GROUP BY c.id
        LIMIT 1
    ");
    $fetchStmt->execute(['id' => $id]);
    $updated = $fetchStmt->fetch();

    sendSuccess([
        'customer' => [
            'id'           => (int) $updated['id'],
            'full_name'    => $updated['full_name'],
            'email'        => $updated['email'],
            'phone'        => $updated['phone'] ?? '',
            'address'      => $updated['address'] ?? '',
            'city'         => $updated['city'] ?? '',
            'postal_code'  => $updated['postal_code'] ?? '',
            'status'       => $updated['status'] ?? 'active',
            'created_at'   => $updated['created_at'],
            'updated_at'   => $updated['updated_at'],
            'total_orders' => (int) $updated['total_orders'],
            'total_spent'  => (float) $updated['total_spent']
        ]
    ], "Customer \"{$fullName}\" updated successfully.");

} catch (Throwable $e) {
    sendServerError('An error occurred while updating the customer record.', ['error' => $e->getMessage()]);
}

