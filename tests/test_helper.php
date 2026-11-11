<?php
/**
 * ShopSphere - Test Helper & Harness
 * Phase 7: Complete Testing, Security & Bug Fixing
 *
 * Provides shared test database bootstrap, assertions, and runner utilities.
 */

putenv('APP_ENV=development');
error_reporting(E_ALL);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/validation.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/upload.php';

class TestHarness {
    public static int $total = 0;
    public static int $passed = 0;
    public static array $failures = [];
    public static string $suiteName = '';

    public static function startSuite(string $name): void {
        self::$suiteName = $name;
        self::$total = 0;
        self::$passed = 0;
        self::$failures = [];

        echo "\n" . str_repeat('=', 70) . "\n";
        echo "SHOPSPHERE TEST SUITE: " . strtoupper($name) . "\n";
        echo str_repeat('=', 70) . "\n\n";
    }

    public static function assert(string $caseId, string $description, bool $condition, string $detail = ''): void {
        self::$total++;
        if ($condition) {
            self::$passed++;
            echo "  [PASS] {$caseId}: {$description}\n";
        } else {
            $msg = "{$caseId}: {$description}" . ($detail ? " ({$detail})" : '');
            self::$failures[] = $msg;
            echo "  [FAIL] {$caseId}: {$description} " . ($detail ? "- {$detail}" : '') . "\n";
        }
    }

    public static function endSuite(): array {
        echo "\n" . str_repeat('-', 70) . "\n";
        echo "SUITE SUMMARY: " . self::$suiteName . "\n";
        echo "Passed: " . self::$passed . " / " . self::$total . " tests";
        if (empty(self::$failures)) {
            echo " [ALL CHECKS PASSED]\n";
        } else {
            echo " [" . count(self::$failures) . " FAILED]\n";
            foreach (self::$failures as $fail) {
                echo "   * {$fail}\n";
            }
        }
        echo str_repeat('=', 70) . "\n\n";

        return [
            'name'     => self::$suiteName,
            'total'    => self::$total,
            'passed'   => self::$passed,
            'failed'   => count(self::$failures),
            'failures' => self::$failures
        ];
    }

    /**
     * Get isolated PDO instance for testing.
     * Uses MySQL if available, or in-memory SQLite with identical schema & fixtures.
     */
    public static function getDatabase(): PDO {
        static $cachedPdo = null;

        try {
            $mysqlPdo = Database::getConnection();
            $mysqlPdo->query('SELECT 1');
            return $mysqlPdo;
        } catch (\Throwable $e) {
            // Build isolated SQLite in-memory engine with full 6-table schema
            $sqlitePdo = new PDO('sqlite::memory:', null, null, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);

            self::buildSqliteSchema($sqlitePdo);
            self::seedFixtures($sqlitePdo);
            return $sqlitePdo;
        }
    }

    /**
     * Creates clean isolated in-memory tables matching database/shopsphere.sql
     */
    public static function buildSqliteSchema(PDO $pdo): void {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
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

            CREATE TABLE IF NOT EXISTS categories (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT UNIQUE NOT NULL,
                slug TEXT UNIQUE,
                description TEXT,
                status TEXT NOT NULL DEFAULT 'active',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS products (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                category_id INTEGER,
                name TEXT NOT NULL,
                sku TEXT UNIQUE NOT NULL,
                description TEXT,
                price REAL NOT NULL DEFAULT 0.00,
                stock INTEGER NOT NULL DEFAULT 0,
                image TEXT,
                status TEXT NOT NULL DEFAULT 'active',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE SET NULL
            );

            CREATE TABLE IF NOT EXISTS customers (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                full_name TEXT NOT NULL,
                email TEXT UNIQUE NOT NULL,
                phone TEXT,
                address TEXT,
                city TEXT,
                postal_code TEXT,
                status TEXT NOT NULL DEFAULT 'active',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS orders (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                order_number TEXT UNIQUE NOT NULL,
                customer_id INTEGER NOT NULL,
                order_date DATETIME DEFAULT CURRENT_TIMESTAMP,
                subtotal REAL NOT NULL DEFAULT 0.00,
                total_amount REAL NOT NULL DEFAULT 0.00,
                payment_method TEXT NOT NULL DEFAULT 'Credit Card',
                payment_status TEXT NOT NULL DEFAULT 'pending',
                order_status TEXT NOT NULL DEFAULT 'pending',
                notes TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE RESTRICT
            );

            CREATE TABLE IF NOT EXISTS order_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                order_id INTEGER NOT NULL,
                product_id INTEGER NOT NULL,
                quantity INTEGER NOT NULL DEFAULT 1,
                unit_price REAL NOT NULL,
                subtotal REAL NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE,
                FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE RESTRICT
            );
        ");
    }

    /**
     * Seeds realistic test fixtures.
     */
    public static function seedFixtures(PDO $pdo): void {
        // Admin user (Admin@123)
        $pdo->exec("INSERT OR IGNORE INTO users (id, username, email, password, full_name, role, status) VALUES 
            (1, 'admin', 'admin@shopsphere.io', '\$2y\$10\$Fav8jb7jkvI4QV/dVJWcNO3B6mmgMizBKyhsyI1Ir2VsvdAUwIjHK', 'Administrator', 'administrator', 'active'),
            (2, 'inactive_admin', 'inactive@shopsphere.io', '\$2y\$10\$Fav8jb7jkvI4QV/dVJWcNO3B6mmgMizBKyhsyI1Ir2VsvdAUwIjHK', 'Suspended Admin', 'administrator', 'inactive')
        ");

        // Categories
        $pdo->exec("INSERT OR IGNORE INTO categories (id, name, slug, description, status) VALUES 
            (1, 'Electronics', 'electronics', 'Smart gadgets and computers', 'active'),
            (2, 'Audio & Sound', 'audio-sound', 'Headphones and speakers', 'active'),
            (3, 'Apparel', 'apparel', 'Clothing and fashion', 'active'),
            (4, 'Seasonal', 'seasonal', 'Limited availability items', 'inactive')
        ");

        // Customers
        $pdo->exec("INSERT OR IGNORE INTO customers (id, full_name, email, phone, address, city, postal_code, status) VALUES 
            (1, 'Marcus Vance', 'marcus.vance@techcorp.io', '+1 (415) 882-9014', '742 Montgomery St', 'San Francisco', '94111', 'active'),
            (2, 'Elena Rostova', 'elena.rostova@designlab.co', '+1 (212) 493-2190', '185 Broadway Ave', 'New York', '10007', 'active'),
            (3, 'Devon Park', 'devon.park@zenith.ai', '+1 (312) 555-0182', '401 N Michigan Ave', 'Chicago', '60611', 'active'),
            (4, 'Blocked Account', 'blocked@test.com', '+1 (555) 000-0000', '123 Fake St', 'Nowhere', '00000', 'blocked')
        ");

        // Products
        $pdo->exec("INSERT OR IGNORE INTO products (id, category_id, name, sku, description, price, stock, image, status) VALUES 
            (1, 1, 'ProBook Elite 16\"', 'LAP-ELT-16', '16-inch M3 Pro Laptop', 2499.00, 35, 'assets/images/products/laptop.jpg', 'active'),
            (2, 2, 'Noise-Cancelling Headphones', 'AUD-ANC-700', 'Wireless over-ear headphones', 349.50, 48, 'assets/images/products/headphones.jpg', 'active'),
            (3, 1, 'UltraVision 4K Monitor 32\"', 'MON-4K-32', '32-inch 4K IPS Display', 799.00, 18, 'assets/images/products/monitor.jpg', 'active'),
            (4, 2, 'Studio Monitor Speakers', 'AUD-SPK-STUDIO', 'Nearfield studio monitors', 599.00, 5, 'assets/images/products/speakers.jpg', 'active'),
            (5, 3, 'Minimalist Desk Mat', 'ACC-DESK-MAT', 'Wool felt large desk pad', 45.00, 0, 'assets/images/products/deskmat.jpg', 'active'),
            (6, 4, 'Archived Discontinued Gizmo', 'DISC-GIZMO-01', 'Discontinued item', 29.99, 0, null, 'inactive')
        ");
    }
}
