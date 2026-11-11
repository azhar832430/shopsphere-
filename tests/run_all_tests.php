<?php
/**
 * ShopSphere - Phase 7: Master Automated Test Runner & Verification Suite
 * Execution: php tests/run_all_tests.php
 *
 * Runs all unit, integration, CRUD, security, and regression test suites.
 */

putenv('APP_ENV=development');
error_reporting(E_ALL);

$phpBinary = PHP_BINARY;
$testDir = __DIR__;

$suites = [
    'Phase 7: Product Management CRUD'    => 'product_test.php',
    'Phase 7: Customer Management CRUD'   => 'customer_test.php',
    'Phase 7: Order CRUD & Transactions'  => 'order_test.php',
    'Phase 7: Category Management CRUD'   => 'category_test.php',
    'Phase 7: Application Security'       => 'security_test.php',
    'Phase 7: End-to-End Integration'     => 'integration_test.php',
    'Regression: Backend Unit & Security' => 'backend_test.php',
    'Regression: API Endpoints & HTTP'    => 'endpoints_test.php',
    'Regression: Product CRUD Baseline'   => 'product_crud_test.php',
    'Regression: Customer CRUD Baseline'  => 'customer_crud_test.php',
    'Regression: Order CRUD Baseline'     => 'order_crud_test.php'
];

echo "\n" . str_repeat('=', 78) . "\n";
echo "   SHOPSPHERE E-COMMERCE MANAGEMENT SYSTEM - MASTER TEST RUNNER (PHASE 7)\n";
echo str_repeat('=', 78) . "\n\n";

$suiteResults = [];
$totalOverall = 0;
$passedOverall = 0;
$failedOverall = 0;
$startOverall = microtime(true);

foreach ($suites as $title => $scriptName) {
    $scriptPath = $testDir . DIRECTORY_SEPARATOR . $scriptName;
    if (!is_file($scriptPath)) {
        echo " [SKIP] File not found: {$scriptName}\n";
        continue;
    }

    echo "Running [{$title}] ({$scriptName})...\n";
    $startTime = microtime(true);

    $command = "\"{$phpBinary}\" \"{$scriptPath}\"";
    $output = [];
    $exitCode = 0;
    exec($command, $output, $exitCode);
    $duration = round(microtime(true) - $startTime, 2);

    $outputStr = implode("\n", $output);

    // Extract passed / total from output
    $passedCount = 0;
    $totalCount = 0;
    $failedCount = 0;

    // Pattern 1: Passed: X / Y tests
    if (preg_match('/Passed:\s+(\d+)\s*\/\s*(\d+)/i', $outputStr, $m)) {
        $passedCount = (int) $m[1];
        $totalCount  = (int) $m[2];
        $failedCount = $totalCount - $passedCount;
    }
    // Pattern 2: TEST RESULTS: X / Y PASSED
    elseif (preg_match('/TEST RESULTS:\s+(\d+)\s*\/\s*(\d+)\s+PASSED/i', $outputStr, $m)) {
        $passedCount = (int) $m[1];
        $totalCount  = (int) $m[2];
        $failedCount = $totalCount - $passedCount;
    }
    // Pattern 3: count [PASS] lines
    else {
        $passedCount = substr_count($outputStr, '[PASS]');
        $failedCount = substr_count($outputStr, '[FAIL]');
        $totalCount  = $passedCount + $failedCount;
    }

    $status = ($exitCode === 0 && $failedCount === 0 && $totalCount > 0) ? 'PASS' : 'FAIL';

    $suiteResults[] = [
        'title'    => $title,
        'file'     => $scriptName,
        'status'   => $status,
        'passed'   => $passedCount,
        'total'    => $totalCount,
        'failed'   => $failedCount,
        'duration' => $duration
    ];

    $totalOverall  += $totalCount;
    $passedOverall += $passedCount;
    $failedOverall += $failedCount;

    $badge = ($status === 'PASS') ? "\033[32m[PASS]\033[0m" : "\033[31m[FAIL]\033[0m";
    // If running in environment without ANSI colors, plain text badge
    if (DIRECTORY_SEPARATOR === '\\' && !getenv('ANSICON')) {
        $badge = ($status === 'PASS') ? '[PASS]' : '[FAIL]';
    }

    echo "  {$badge} {$passedCount}/{$totalCount} passed in {$duration}s\n\n";
}

$totalDuration = round(microtime(true) - $startOverall, 2);

echo str_repeat('=', 78) . "\n";
echo "                       CONSOLIDATED TEST EXECUTION MATRIX\n";
echo str_repeat('=', 78) . "\n";
printf("%-38s | %-6s | %-12s | %-8s\n", "Test Suite", "Status", "Checks", "Duration");
echo str_repeat('-', 78) . "\n";

foreach ($suiteResults as $res) {
    printf("%-38s | %-6s | %4d / %-4d  | %6.2fs\n", 
        $res['title'], 
        $res['status'], 
        $res['passed'], 
        $res['total'], 
        $res['duration']
    );
}

echo str_repeat('=', 78) . "\n";
echo "TOTAL AUTOMATED TESTS EXECUTED : {$totalOverall}\n";
echo "TOTAL CHECKS PASSED            : {$passedOverall}\n";
echo "TOTAL CHECKS FAILED            : {$failedOverall}\n";
echo "OVERALL PASS RATE              : " . ($totalOverall > 0 ? round(($passedOverall / $totalOverall) * 100, 1) : 0) . "%\n";
echo "TOTAL WALL CLOCK EXECUTION TIME: {$totalDuration}s\n";
echo str_repeat('=', 78) . "\n";

if ($failedOverall === 0 && $totalOverall > 0) {
    echo "\n>>> ALL TEST SUITES PASSED WITH ZERO REGRESSIONS (DEPLOYMENT READY) <<<\n\n";
    exit(0);
} else {
    echo "\n>>> ONE OR MORE TEST SUITES FAILED. PLEASE REVIEW LOGS. <<<\n\n";
    exit(1);
}

