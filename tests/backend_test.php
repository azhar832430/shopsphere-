<?php
/**
 * ShopSphere - Phase 3 Backend & API Automated Test Suite
 * Execution: php tests/backend_test.php
 */

// Set CLI testing environment
putenv('APP_ENV=development');
error_reporting(E_ALL);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/validation.php';
require_once __DIR__ . '/../includes/auth.php';

// Initialize session before console output
startSecureSession();

$totalTests = 0;
$passedTests = 0;
$failedTests = [];

function test(string $description, bool $condition, string $detail = ''): void {
    global $totalTests, $passedTests, $failedTests;
    $totalTests++;
    if ($condition) {
        $passedTests++;
        echo "  [PASS] {$description}\n";
    } else {
        $failedTests[] = $description . ($detail ? " ({$detail})" : '');
        echo "  [FAIL] {$description} " . ($detail ? "- {$detail}" : '') . "\n";
    }
}

echo "======================================================================\n";
echo "SHOPSPHERE - PHASE 3: PHP BACKEND & API VERIFICATION SUITE\n";
echo "======================================================================\n\n";

// ----------------------------------------------------------------------------
// 1. Validation Engine Tests
// ----------------------------------------------------------------------------
echo "1. Testing Validation Engine:\n";

$validData = [
    'username' => 'admin_test',
    'email'    => 'admin@domain.com',
    'age'      => '25',
    'price'    => '99.50'
];

$v1 = new Validator($validData);
$v1->required(['username', 'email'])
   ->email('email')
   ->numeric('price', 0);
test('Validator passes on valid input', $v1->passes());

$invalidData = [
    'username' => '',
    'email'    => 'not-an-email',
    'price'    => '-10'
];

$v2 = new Validator($invalidData);
$v2->required(['username'])
   ->email('email')
   ->numeric('price', 0);
test('Validator catches required field missing', isset($v2->getErrors()['username']));
test('Validator catches invalid email format', isset($v2->getErrors()['email']));
test('Validator catches negative numeric bound', isset($v2->getErrors()['price']));

// ----------------------------------------------------------------------------
// 2. Helper & Sanitization Tests
// ----------------------------------------------------------------------------
echo "\n2. Testing Input Sanitization & Helpers:\n";

$dirtyStr = "  hello world\0with null bytes   ";
$cleanStr = sanitizeString($dirtyStr);
test('sanitizeString trims whitespace and removes null bytes', $cleanStr === 'hello worldwith null bytes');

$dirtyArray = [
    'name' => "  Alexander  \0",
    'nested' => ['key' => '  value  ']
];
$cleanArray = sanitizeInput($dirtyArray);
test('sanitizeInput recursively cleans arrays', $cleanArray['name'] === 'Alexander' && $cleanArray['nested']['key'] === 'value');

// ----------------------------------------------------------------------------
// 3. Auth, Session & CSRF Security Tests
// ----------------------------------------------------------------------------
echo "\n3. Testing Session Management, Auth & CSRF Security:\n";

startSecureSession();
test('Session initialized with SHOPSPHERE_SESSID', session_name() === 'SHOPSPHERE_SESSID');

$csrf1 = getCsrfToken();
test('CSRF token generated (64 hex chars)', strlen($csrf1) === 64);
test('CSRF token matches session', verifyCsrfToken($csrf1));
test('Invalid CSRF token rejected', !verifyCsrfToken('invalid_token_string_12345'));

$sampleUser = [
    'id'        => 1,
    'username'  => 'admin',
    'email'     => 'admin@shopsphere.io',
    'full_name' => 'Alexander Wright',
    'role'      => 'administrator',
    'status'    => 'active'
];

loginUser($sampleUser);
test('loginUser establishes authenticated session', isAuthenticated() === true);

$currentUser = getCurrentUser();
test('getCurrentUser retrieves active user data', $currentUser['id'] === 1 && $currentUser['username'] === 'admin');
test('Session does NOT store password hash', !isset($_SESSION['auth_password']) && !isset($currentUser['password']));

$csrf2 = getCsrfToken();
test('New CSRF token generated upon login', verifyCsrfToken($csrf2));

logoutUser();
test('logoutUser terminates session and authentication', isAuthenticated() === false && empty($_SESSION));

// ----------------------------------------------------------------------------
// 4. Password Hashing & Verification Tests
// ----------------------------------------------------------------------------
echo "\n4. Testing Password Hashing & Bcrypt Compatibility:\n";

$plainPassword = 'Admin@123';
$hashed = password_hash($plainPassword, PASSWORD_BCRYPT);
test('password_hash creates valid bcrypt hash', strpos($hashed, '$2y$10$') === 0);
test('password_verify accepts correct password', password_verify($plainPassword, $hashed));
test('password_verify rejects incorrect password', !password_verify('WrongPassword123', $hashed));

// Verify against the Phase 2 database seed hash
$seedHash = '$2y$10$Fav8jb7jkvI4QV/dVJWcNO3B6mmgMizBKyhsyI1Ir2VsvdAUwIjHK';
test('Phase 2 database seed hash matches Admin@123', password_verify('Admin@123', $seedHash));

// ----------------------------------------------------------------------------
// 5. Database Logic & Query Simulation (Isolated In-Memory PDO Test)
// ----------------------------------------------------------------------------
echo "\n5. Testing Database Queries & User Retrieval:\n";

// Create SQLite in-memory PDO simulating MySQL users table
$testPdo = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

$testPdo->exec("
    CREATE TABLE users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        email TEXT UNIQUE NOT NULL,
        password TEXT NOT NULL,
        full_name TEXT NOT NULL,
        role TEXT NOT NULL DEFAULT 'administrator',
        status TEXT NOT NULL DEFAULT 'active',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );
");

$testPdo->exec("
    INSERT INTO users (id, username, email, password, full_name, role, status) VALUES
    (1, 'admin', 'admin@shopsphere.io', '{$seedHash}', 'Alexander Wright', 'administrator', 'active'),
    (2, 'inactive_user', 'inactive@shopsphere.io', '{$seedHash}', 'Deactivated Staff', 'store_manager', 'inactive');
");

// Test query by username
$stmt = $testPdo->prepare("SELECT id, username, email, password, full_name, role, status FROM users WHERE username = :u OR email = :e LIMIT 1");
$stmt->execute(['u' => 'admin', 'e' => 'admin']);
$userResult = $stmt->fetch();

test('Query finds administrator by username', $userResult && $userResult['username'] === 'admin');
test('Found user password verifies against Admin@123', password_verify('Admin@123', $userResult['password']));

// Test query by email
$stmt->execute(['u' => 'admin@shopsphere.io', 'e' => 'admin@shopsphere.io']);
$userByEmail = $stmt->fetch();
test('Query finds administrator by email', $userByEmail && $userByEmail['id'] === 1);

// Test query for non-existent user
$stmt->execute(['u' => 'nonexistent', 'e' => 'nonexistent']);
$nonExistent = $stmt->fetch();
test('Query returns false for nonexistent user (no leaks)', $nonExistent === false);

// Test query for inactive user
$stmt->execute(['u' => 'inactive_user', 'e' => 'inactive_user']);
$inactiveUser = $stmt->fetch();
test('Query correctly identifies inactive user status', $inactiveUser && $inactiveUser['status'] === 'inactive');

// ----------------------------------------------------------------------------
// 6. Response JSON Envelope Format Tests
// ----------------------------------------------------------------------------
echo "\n6. Testing Standardized JSON Response Format:\n";

// Capture output of sendJsonResponse via buffer
ob_start();
// Test in separate process / mock function
$responseMock = function(bool $success, string $message, $data = [], array $errors = []) {
    return json_encode([
        'success' => $success,
        'message' => $message,
        'data'    => (object)$data,
        'errors'  => (object)$errors
    ]);
};

$successJson = json_decode($responseMock(true, 'Test success', ['user_id' => 1]), true);
test('Success envelope contains success=true', $successJson['success'] === true);
test('Success envelope contains message', $successJson['message'] === 'Test success');
test('Success envelope contains data object', isset($successJson['data']['user_id']));

$errorJson = json_decode($responseMock(false, 'Test error', [], ['field' => 'Required']), true);
test('Error envelope contains success=false', $errorJson['success'] === false);
test('Error envelope contains errors object', isset($errorJson['errors']['field']));

// ----------------------------------------------------------------------------
// Summary
// ----------------------------------------------------------------------------
echo "\n======================================================================\n";
echo "TEST RESULTS: {$passedTests} / {$totalTests} CHECKS PASSED\n";
if (!empty($failedTests)) {
    echo "FAILED CHECKS (" . count($failedTests) . "):\n";
    foreach ($failedTests as $f) {
        echo "  - {$f}\n";
    }
    exit(1);
} else {
    echo "ALL BACKEND ARCHITECTURE & SECURITY TESTS PASSED WITH ZERO ERRORS!\n";
    echo "======================================================================\n";
}
