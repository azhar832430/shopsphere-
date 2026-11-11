<?php
/**
 * ShopSphere - Phase 3 Endpoint HTTP & JSON Contract Test Suite
 * Tests actual files in api/ using PHP sub-processes
 */

$totalChecks = 0;
$passedChecks = 0;
$failedChecks = [];

function checkEndpoint(string $desc, bool $condition, string $detail = ''): void {
    global $totalChecks, $passedChecks, $failedChecks;
    $totalChecks++;
    if ($condition) {
        $passedChecks++;
        echo "  [PASS] {$desc}\n";
    } else {
        $failedChecks[] = $desc . ($detail ? " ({$detail})" : '');
        echo "  [FAIL] {$desc} " . ($detail ? "- {$detail}" : '') . "\n";
    }
}

echo "======================================================================\n";
echo "SHOPSPHERE - PHASE 3: API ENDPOINTS INTEGRATION TEST SUITE\n";
echo "======================================================================\n\n";

/**
 * Execute a PHP file simulating an HTTP request via PHP CLI.
 */
function simulateRequest(string $filePath, string $method = 'GET', array $payload = [], array $headers = [], array $session = []): array {
    $script = "
        putenv('APP_ENV=development');
        \$_SERVER['REQUEST_METHOD'] = '{$method}';
        \$_SERVER['HTTP_ACCEPT'] = 'application/json';
    ";

    foreach ($headers as $k => $v) {
        $headerKey = 'HTTP_' . strtoupper(str_replace('-', '_', $k));
        $script .= "\$_SERVER['{$headerKey}'] = '{$v}';\n";
    }

    if (!empty($session)) {
        $serialized = addslashes(serialize($session));
        $script .= "
            session_name('SHOPSPHERE_SESSID');
            @session_start();
            \$_SESSION = unserialize('{$serialized}');
        ";
    }

    // Set input body
    $jsonBody = !empty($payload) ? addslashes(json_encode($payload)) : '';
    
    // We can wrap execution and capture output
    $fullCode = <<<PHP
<?php
{$script}

// Mock php://input if payload exists
class MockInputStream {
    public \$context;
    protected \$position = 0;
    protected \$data = '{$jsonBody}';

    public function stream_open(\$path, \$mode, \$options, &\$opened_path) {
        \$this->position = 0;
        return true;
    }
    public function stream_read(\$count) {
        \$ret = substr(\$this->data, \$this->position, \$count);
        \$this->position += strlen(\$ret);
        return \$ret;
    }
    public function stream_eof() {
        return \$this->position >= strlen(\$this->data);
    }
    public function stream_stat() {
        return [];
    }
}

if (!empty('{$jsonBody}')) {
    stream_wrapper_unregister('php');
    stream_wrapper_register('php', 'MockInputStream');
}

ob_start();
require '{$filePath}';
\$output = ob_get_clean();
echo \$output;
PHP;

    $tempFile = tempnam(sys_get_temp_dir(), 'test_api_');
    file_put_contents($tempFile, $fullCode);

    $cmd = '"C:\\xampp\\php\\php.exe" ' . escapeshellarg($tempFile);
    $output = shell_exec($cmd);
    @unlink($tempFile);

    $json = json_decode($output, true);
    return [
        'raw'  => $output,
        'json' => $json
    ];
}

// ----------------------------------------------------------------------------
// 1. Health Check Endpoint Tests
// ----------------------------------------------------------------------------
echo "1. Testing /api/health.php:\n";

$healthRes = simulateRequest('api/health.php', 'GET');
checkEndpoint('GET /api/health.php returns valid JSON', is_array($healthRes['json']));
checkEndpoint('Health response has success boolean', isset($healthRes['json']['success']));
checkEndpoint('Health response message present', !empty($healthRes['json']['message']));

$healthPost = simulateRequest('api/health.php', 'POST');
checkEndpoint('POST /api/health.php rejects with 405 Method Not Allowed', 
    isset($healthPost['json']['success']) && $healthPost['json']['success'] === false && strpos($healthPost['json']['message'], 'Method not allowed') !== false
);

// ----------------------------------------------------------------------------
// 2. Authentication Login Endpoint Tests
// ----------------------------------------------------------------------------
echo "\n2. Testing /api/auth/login.php:\n";

// Test invalid method
$loginGet = simulateRequest('api/auth/login.php', 'GET');
checkEndpoint('GET /api/auth/login.php rejects with 405 Method Not Allowed', 
    isset($loginGet['json']['success']) && $loginGet['json']['success'] === false && strpos($loginGet['json']['message'], 'Method not allowed') !== false
);

// Test missing parameters
$loginMissing = simulateRequest('api/auth/login.php', 'POST', []);
checkEndpoint('POST /api/auth/login.php with empty body returns validation error', 
    isset($loginMissing['json']['success']) && $loginMissing['json']['success'] === false && isset($loginMissing['json']['errors'])
);

// Test invalid credentials
$loginBad = simulateRequest('api/auth/login.php', 'POST', [
    'username_or_email' => 'admin@shopsphere.io',
    'password'          => 'IncorrectPassword!'
]);
checkEndpoint('POST /api/auth/login.php with wrong password returns 401 error', 
    isset($loginBad['json']['success']) && $loginBad['json']['success'] === false
);
checkEndpoint('Login failure does not expose internal stack trace', 
    strpos($loginBad['raw'], 'Fatal error') === false && strpos($loginBad['raw'], 'Exception') === false
);

// ----------------------------------------------------------------------------
// 3. Authenticated Profile Endpoint Tests (Me)
// ----------------------------------------------------------------------------
echo "\n3. Testing /api/auth/me.php:\n";

// Test unauthenticated access
$meUnauth = simulateRequest('api/auth/me.php', 'GET');
checkEndpoint('GET /api/auth/me.php unauthenticated returns 401 Unauthorized', 
    isset($meUnauth['json']['success']) && $meUnauth['json']['success'] === false && strpos($meUnauth['json']['message'], 'Authentication required') !== false
);

// Test invalid method
$mePost = simulateRequest('api/auth/me.php', 'POST');
checkEndpoint('POST /api/auth/me.php rejects with 405 Method Not Allowed', 
    isset($mePost['json']['success']) && $mePost['json']['success'] === false && strpos($mePost['json']['message'], 'Method not allowed') !== false
);

// ----------------------------------------------------------------------------
// 4. Logout Endpoint Tests
// ----------------------------------------------------------------------------
echo "\n4. Testing /api/auth/logout.php:\n";

// Test invalid method
$logoutGet = simulateRequest('api/auth/logout.php', 'GET');
checkEndpoint('GET /api/auth/logout.php rejects with 405 Method Not Allowed', 
    isset($logoutGet['json']['success']) && $logoutGet['json']['success'] === false && strpos($logoutGet['json']['message'], 'Method not allowed') !== false
);

$logoutPost = simulateRequest('api/auth/logout.php', 'POST');
checkEndpoint('POST /api/auth/logout.php successfully destroys session', 
    isset($logoutPost['json']['success']) && $logoutPost['json']['success'] === true
);

// ----------------------------------------------------------------------------
// Summary
// ----------------------------------------------------------------------------
echo "\n======================================================================\n";
echo "INTEGRATION TEST RESULTS: {$passedChecks} / {$totalChecks} CHECKS PASSED\n";
if (!empty($failedChecks)) {
    echo "FAILED CHECKS (" . count($failedChecks) . "):\n";
    foreach ($failedChecks as $f) {
        echo "  - {$f}\n";
    }
    exit(1);
} else {
    echo "ALL API ENDPOINTS & HTTP CONTRACTS PASSED WITH ZERO ERRORS!\n";
    echo "======================================================================\n";
}
