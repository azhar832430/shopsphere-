<?php
/**
 * ShopSphere - Database Connection & Configuration
 * Phase 3: Backend & API Foundation
 * 
 * Target DBMS: MySQL 8.0+ / MariaDB 10.4+ (XAMPP Default)
 * Default XAMPP Credentials:
 *   Host: 127.0.0.1
 *   Port: 3306
 *   Database: shopsphere_db
 *   User: root
 *   Password: (empty)
 */

require_once __DIR__ . '/app.php';

class Database {
    // Default XAMPP Connection Parameters
    private static string $host = '127.0.0.1';
    private static string $port = '3306';
    private static string $dbName = 'shopsphere_db';
    private static string $username = 'root';
    private static string $password = '';
    private static string $charset = 'utf8mb4';

    private static ?PDO $instance = null;

    /**
     * Retrieve the singleton PDO database connection instance.
     *
     * @return PDO
     * @throws Exception If database connection fails (without exposing credentials).
     */
    public static function getConnection(): PDO {
        if (self::$instance === null) {
            // Read from environment if configured
            $host = getenv('DB_HOST') ?: self::$host;
            $port = getenv('DB_PORT') ?: self::$port;
            $dbName = getenv('DB_NAME') ?: self::$dbName;
            $username = getenv('DB_USER') ?: self::$username;
            $password = getenv('DB_PASS') !== false ? getenv('DB_PASS') : self::$password;
            $charset = getenv('DB_CHARSET') ?: self::$charset;

            $dsn = "mysql:host={$host};port={$port};dbname={$dbName};charset={$charset}";

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset} COLLATE utf8mb4_unicode_ci"
            ];

            try {
                self::$instance = new PDO($dsn, $username, $password, $options);
            } catch (PDOException $e) {
                // Log real internal error safely to server error log
                error_log('[ShopSphere DB Connection Error] ' . $e->getMessage());

                // Throw generic safe message to prevent exposing host, user, or password
                throw new Exception('Unable to establish a secure database connection. Please verify database server status.');
            }
        }

        return self::$instance;
    }

    /**
     * Helper to test whether the connection can be established.
     *
     * @return array [bool $connected, string $message]
     */
    public static function testConnection(): array {
        try {
            $pdo = self::getConnection();
            $stmt = $pdo->query('SELECT 1');
            return [true, 'Database connection established successfully.'];
        } catch (Exception $e) {
            return [false, $e->getMessage()];
        }
    }
}

