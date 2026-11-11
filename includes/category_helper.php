<?php
/**
 * ShopSphere - Category Helper Utilities & Schema Synchronizer
 * Phase 7: Complete Testing, Security & Category CRUD
 */

require_once __DIR__ . '/helpers.php';

/**
 * Generate a clean URL-friendly slug from a category name.
 */
function generateCategorySlug(string $name): string {
    $slug = strtolower($name);
    // Replace ampersand with 'and'
    $slug = str_replace('&', 'and', $slug);
    // Remove non-alphanumeric characters with hyphen
    $slug = preg_replace('/[^a-z0-9]+/i', '-', $slug);
    // Trim hyphens from beginning and end
    $slug = trim($slug, '-');
    return $slug !== '' ? $slug : 'category-' . bin2hex(random_bytes(3));
}

/**
 * Validate that a slug contains only lowercase alphanumeric characters and hyphens.
 */
function isValidCategorySlug(string $slug): bool {
    return (bool) preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug);
}

/**
 * Dynamically check and ensure the `slug` column exists in `categories` table.
 * Idempotent and compatible with both MySQL and SQLite engines.
 */
function ensureCategorySlugColumn(PDO $pdo): void {
    static $synchronized = false;
    if ($synchronized) return;

    try {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $colStmt = $pdo->query("SHOW COLUMNS FROM `categories` LIKE 'slug'");
            $exists = $colStmt && $colStmt->fetch();

            if (!$exists) {
                @$pdo->exec("ALTER TABLE `categories` ADD COLUMN `slug` VARCHAR(100) NULL AFTER `name`");
                @$pdo->exec("ALTER TABLE `categories` ADD UNIQUE KEY `uq_categories_slug` (`slug`)");
                
                // Backfill slugs for any existing rows lacking slug
                $cats = $pdo->query("SELECT id, name FROM `categories` WHERE `slug` IS NULL OR `slug` = ''")->fetchAll();
                $upStmt = $pdo->prepare("UPDATE `categories` SET `slug` = :slug WHERE id = :id");
                foreach ($cats as $cat) {
                    $upStmt->execute([
                        'slug' => generateCategorySlug($cat['name']),
                        'id'   => $cat['id']
                    ]);
                }
            }
        } elseif ($driver === 'sqlite') {
            $cols = $pdo->query("PRAGMA table_info(categories)")->fetchAll(PDO::FETCH_ASSOC);
            $hasSlug = false;
            foreach ($cols as $c) {
                if (strtolower($c['name']) === 'slug') {
                    $hasSlug = true;
                    break;
                }
            }
            if (!$hasSlug) {
                @$pdo->exec("ALTER TABLE categories ADD COLUMN slug TEXT");
                $cats = $pdo->query("SELECT id, name FROM categories WHERE slug IS NULL OR slug = ''")->fetchAll();
                $upStmt = $pdo->prepare("UPDATE categories SET slug = :slug WHERE id = :id");
                foreach ($cats as $cat) {
                    $upStmt->execute([
                        'slug' => generateCategorySlug($cat['name']),
                        'id'   => $cat['id']
                    ]);
                }
            }
        }
    } catch (\Throwable $e) {
        // Silently continue if column already exists or restricted in sandbox
    }

    $synchronized = true;
}

