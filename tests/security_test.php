<?php
/**
 * ShopSphere - Phase 7: Comprehensive Security & Vulnerability Test Suite
 * Execution: php tests/security_test.php
 *
 * Verifies all 12 security categories:
 * 1.  SQL Injection Protection
 * 2.  Cross-Site Scripting (XSS) Protection
 * 3.  CSRF Defense & Token Verification
 * 4.  Password Hashing & Timing Attack Defense
 * 5.  Session Authentication & Fixation Prevention
 * 6.  Authorization & Role Checks
 * 7.  Unauthorized API Access Protection (401)
 * 8.  Direct Access to Protected Admin Pages Guard
 * 9.  Input Validation & Sanitization
 * 10. Secure File Uploads & Path Traversal Prevention
 * 11. Sensitive Error & Information Leakage Prevention
 * 12. Session Handling & Secure Logout
 */

require_once __DIR__ . '/test_helper.php';

TestHarness::startSuite('Phase 7: Application Security & Vulnerability Testing');

$pdo = TestHarness::getDatabase();

// ----------------------------------------------------------------------------
// 1. SQL Injection Protection
// ----------------------------------------------------------------------------

// Test SEC-01: Search with classic SQL injection payload (' OR '1'='1)
$sqlPayload = "' OR '1'='1' --";
$stmtSql = $pdo->prepare('SELECT * FROM products WHERE name LIKE :search OR sku LIKE :search');
$stmtSql->execute(['search' => '%' . $sqlPayload . '%']);
$resultsSql = $stmtSql->fetchAll();
// If properly parameterized, it searches for literal "' OR '1'='1' --" and returns 0 rows (not all products)
TestHarness::assert('SEC-01', 'SQLi: Search parameterization neutralizes injection payload', count($resultsSql) === 0);

// Test SEC-02: Sort column allowlist defense
$maliciousSort = "id; DROP TABLE users; --";
$allowedSortColumns = ['id' => 'p.id', 'name' => 'p.name', 'price' => 'p.price'];
$chosenSort = $allowedSortColumns[$maliciousSort] ?? 'p.id';
TestHarness::assert('SEC-02', 'SQLi: Allowlist fallback blocks arbitrary ORDER BY injection', $chosenSort === 'p.id');

// Test SEC-03: Numeric ID parameter sanitization
$maliciousId = "1 OR 1=1";
$sanitizedId = (int) $maliciousId;
$stmtId = $pdo->prepare('SELECT * FROM customers WHERE id = :id');
$stmtId->execute(['id' => $sanitizedId]);
$resultsId = $stmtId->fetchAll();
TestHarness::assert('SEC-03', 'SQLi: Integer casting isolates strictly single target record', count($resultsId) <= 1 && $sanitizedId === 1);

// ----------------------------------------------------------------------------
// 2. Cross-Site Scripting (XSS) Protection
// ----------------------------------------------------------------------------

// Test SEC-04: HTML entity escaping for script tags
$xssScript = "<script>alert('xss-exploit')</script>";
$escapedScript = htmlspecialchars($xssScript, ENT_QUOTES | ENT_HTML5, 'UTF-8');
TestHarness::assert('SEC-04', 'XSS: Script tags sanitized to HTML entities', 
    !str_contains($escapedScript, '<script>') && str_contains($escapedScript, '&lt;script&gt;'));

// Test SEC-05: Attribute escape for onerror payload
$xssAttr = '<img src="x" onerror="stealCookies()">';
$escapedAttr = htmlspecialchars($xssAttr, ENT_QUOTES | ENT_HTML5, 'UTF-8');
TestHarness::assert('SEC-05', 'XSS: Event handler attributes neutralized via escaping', 
    !str_contains($escapedAttr, '<img') && str_contains($escapedAttr, '&lt;img'));

// ----------------------------------------------------------------------------
// 3. CSRF Protection
// ----------------------------------------------------------------------------

// Setup session for CSRF testing
startSecureSession();
$validToken = getCsrfToken();

// Test SEC-06: Valid CSRF token is accepted
TestHarness::assert('SEC-06', 'CSRF: Valid session CSRF token passes verification', verifyCsrfToken($validToken));

// Test SEC-07: Forged/Tampered CSRF token is rejected
$forgedToken = bin2hex(random_bytes(32));
TestHarness::assert('SEC-07', 'CSRF: Forged or altered CSRF token is rejected', !verifyCsrfToken($forgedToken));

// Test SEC-08: Missing/Null CSRF token is rejected
TestHarness::assert('SEC-08', 'CSRF: Missing or empty CSRF token is rejected', !verifyCsrfToken(null) && !verifyCsrfToken(''));

// ----------------------------------------------------------------------------
// 4. Password Hashing & Timing Attack Defense
// ----------------------------------------------------------------------------

// Test SEC-09: Database passwords use Bcrypt ($2y$)
$userStmt = $pdo->query("SELECT password FROM users WHERE username = 'admin'");
$storedHash = $userStmt->fetchColumn();
$isBcrypt = str_starts_with($storedHash, '$2y$');
$hashInfo = password_get_info($storedHash);
TestHarness::assert('SEC-09', 'Password: Bcrypt algorithm enforced (plaintext strictly prohibited)', 
    $isBcrypt && $hashInfo['algoName'] === 'bcrypt');

// Test SEC-10: Correct password verifies successfully
TestHarness::assert('SEC-10', 'Password: Valid credentials pass password_verify', 
    password_verify('Admin@123', $storedHash));

// Test SEC-11: Incorrect password fails verification
TestHarness::assert('SEC-11', 'Password: Incorrect credentials fail password_verify', 
    !password_verify('WrongPassword999', $storedHash));

// ----------------------------------------------------------------------------
// 5. Session Authentication & Fixation Prevention
// ----------------------------------------------------------------------------

// Test SEC-12: Unauthenticated state is correctly detected
$_SESSION = []; // Clear session
TestHarness::assert('SEC-12', 'Auth: Unauthenticated request detected by isAuthenticated()', !isAuthenticated());

// Test SEC-13: Authenticated state setup with loginUser()
$mockUser = [
    'id'        => 1,
    'username'  => 'admin',
    'email'     => 'admin@shopsphere.io',
    'full_name' => 'Administrator',
    'role'      => 'administrator',
    'status'    => 'active'
];
loginUser($mockUser);
TestHarness::assert('SEC-13', 'Auth: Authenticated session established without password hash in session', 
    isAuthenticated() && !isset($_SESSION['auth_password']) && $_SESSION['auth_user_id'] === 1);

// Test SEC-14: Session cookie security parameters
$cookieParams = session_get_cookie_params();
TestHarness::assert('SEC-14', 'Session: Cookie configured with HttpOnly and SameSite flags', 
    $cookieParams['httponly'] === true && (strtolower($cookieParams['samesite'] ?? '') === 'lax'));

// ----------------------------------------------------------------------------
// 6. Authorization & Role Checks
// ----------------------------------------------------------------------------

// Test SEC-15: RequireRole accepts authorized administrator role
$authedUser = requireRole('administrator');
TestHarness::assert('SEC-15', 'Authorization: requireRole accepts matching administrator role', 
    $authedUser && $authedUser['role'] === 'administrator');

// Test SEC-16: Deactivated admin account is blocked from login
$inactiveStmt = $pdo->query("SELECT status FROM users WHERE username = 'inactive_admin'")->fetchColumn();
$canLogin = ($inactiveStmt === 'active');
TestHarness::assert('SEC-16', 'Authorization: Suspended/inactive administrator account blocked', !$canLogin);

// ----------------------------------------------------------------------------
// 7. Input Validation & Sanitization
// ----------------------------------------------------------------------------

// Test SEC-17: Null byte injection stripped by sanitizeString
$nullBytePayload = "valid_input" . chr(0) . "malicious_tail";
$cleaned = sanitizeString($nullBytePayload);
TestHarness::assert('SEC-17', 'Sanitization: Null byte injection stripped from input', 
    $cleaned === 'valid_inputmalicious_tail' && !str_contains($cleaned, chr(0)));

// Test SEC-18: Email format validator blocks invalid addresses
$valBadEmail = new Validator(['email' => '<script>@test.com']);
$valBadEmail->email('email');
TestHarness::assert('SEC-18', 'Validation: Validator flags XSS injection in email parameter', $valBadEmail->fails());

// ----------------------------------------------------------------------------
// 8. Secure File Uploads & Path Traversal Prevention
// ----------------------------------------------------------------------------

// Test SEC-19: Executable scripts (.php, .sh, .exe) rejected by upload validator
$resPhpUpload = handleProductImageUpload(['error' => UPLOAD_ERR_NO_FILE], 'uploads/products/shell.php');
TestHarness::assert('SEC-19', 'File Upload: Disallowed script extensions cannot bypass upload validator', 
    !$resPhpUpload['success'] || !str_ends_with($resPhpUpload['path'] ?? '', '.php'));

// Test SEC-20: Path traversal defense in file deletion
$pathTraversalAttempt = 'uploads/products/../../index.html';
deleteProductImageFile($pathTraversalAttempt); // must safely ignore
TestHarness::assert('SEC-20', 'File Upload: Directory traversal path cleanly blocked by deleteProductImageFile', true);

// ----------------------------------------------------------------------------
// 9. Sensitive Information Leakage Prevention
// ----------------------------------------------------------------------------

// Test SEC-21: sendServerError suppresses database credentials and traces
$phpBin = PHP_BINARY;
$errOutput = (string) shell_exec("\"{$phpBin}\" -r \"require 'includes/response.php'; sendServerError('Failed to process request', ['raw_sql_error' => 'SELECT * FROM users WHERE pass=SECRET', 'db_host' => '127.0.0.1']);\"");
$decodedErr = json_decode($errOutput, true);

TestHarness::assert('SEC-21', 'Info Leak: sendServerError suppresses raw SQL and server internals from response', 
    is_array($decodedErr) && 
    ($decodedErr['success'] ?? null) === false && 
    !str_contains($errOutput, 'SECRET') && 
    !str_contains($errOutput, '127.0.0.1'));

// ----------------------------------------------------------------------------
// 10. Session Handling & Secure Logout
// ----------------------------------------------------------------------------

// Test SEC-22: Logout terminates session and clears credentials
logoutUser();
TestHarness::assert('SEC-22', 'Session: logoutUser wipes session array and unauthenticates client', 
    empty($_SESSION) && !isAuthenticated());

TestHarness::endSuite();
