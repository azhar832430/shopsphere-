# ShopSphere – Category Management CRUD Technical Documentation
> **Phase 7 Extension: Complete Database-Connected Category Management CRUD**  
> **Technology Stack**: PHP 8+, MySQL 8.0+ / MariaDB 10.4+ (XAMPP), HTML5, CSS3, Vanilla JavaScript (ES6+), Fetch API, PDO Prepared Statements, ACID Transactions.

---

## 1. Executive Summary & Architecture

The **Category Management CRUD Module** manages product classification taxonomies and department hierarchies for the ShopSphere administrative console. Built on native PHP 8 and PDO, it provides full lifecycle management for categories: listing with live product counts, searching and filtering, single category inspection with assigned products, creation with auto-slug generation, update with duplicate collision guards, and transaction-safe deletion that preserves all catalog products by setting `category_id = NULL` (Uncategorized).

```
Frontend (HTML5 / Vanilla JavaScript)
  │
  ├── assets/js/api.js ───────────── API.categories.{list, get, create, update, destroy, delete}
  └── assets/js/categories.js ────── Category directory, modals, search, status filters, XSS defense
           │ (HTTP / JSON + Session Cookie + X-CSRF-Token)
           ▼
Backend Endpoints (PHP 8 + PDO)
  │
  ├── api/categories/index.php ───── Server-paginated categories with search, status & product counts
  ├── api/categories/show.php ────── Category details + list of assigned products
  ├── api/categories/store.php ───── Category creation with slug auto-generation & uniqueness check
  ├── api/categories/update.php ──── Category update with name/slug collision self-retention
  ├── api/categories/destroy.php ─── Transactional safe deletion decoupling products (SET NULL)
  └── api/categories/delete.php ──── Alias endpoint delegating to destroy.php
           │
           ├── includes/category_helper.php ── Slug generator, validator & auto-migration
           ├── includes/auth.php ───────────── requireAuth(), requireCsrf() session verification
           ├── includes/helpers.php ────────── getJsonInput(), sanitizeString()
           └── includes/validation.php ─────── Validator class
                   │
                   ▼
Database & Storage
  └── MySQL: `shopsphere_db` (`categories`, `products`)
      - Foreign Key: `fk_products_category` (`products.category_id` -> `categories.id` ON DELETE SET NULL)
      - Unique Constraints: `uq_categories_name`, `uq_categories_slug`
      - Transaction Safety: Decouples products before removing category row
```

---

## 2. Database Schema & Migration

### 2.1. `categories` Table (MySQL InnoDB, utf8mb4)

| Column | Type | Constraints | Description |
|---|---|---|---|
| `id` | `INT UNSIGNED` | `PRIMARY KEY`, `AUTO_INCREMENT` | Unique category identifier |
| `name` | `VARCHAR(100)` | `NOT NULL`, `UNIQUE KEY` (`uq_categories_name`) | Human-readable department name |
| `slug` | `VARCHAR(100)` | `NULL`, `UNIQUE KEY` (`uq_categories_slug`) | URL-safe slug identifier |
| `description` | `TEXT` | `NULL` | Department overview and scope |
| `status` | `VARCHAR(20)` | `NOT NULL`, `DEFAULT 'active'` | `active` or `inactive` |
| `created_at` | `TIMESTAMP` | `NOT NULL`, `DEFAULT CURRENT_TIMESTAMP` | Record creation timestamp |
| `updated_at` | `TIMESTAMP` | `NOT NULL`, `DEFAULT CURRENT_TIMESTAMP ON UPDATE` | Last modification timestamp |

### 2.2. Schema Evolution & The `slug` Column

In the original Phase 2 schema (`database/shopsphere.sql`), the `categories` table was provisioned without a `slug` column. The Phase 1 frontend expected a URL-safe slug (e.g. `electronics`, `home-kitchen`).

To establish complete frontend-backend parity without breaking existing installations:
1. **Migration Script**: `database/migrate_category_slug.sql` was added:
   ```sql
   ALTER TABLE `categories`
     ADD COLUMN IF NOT EXISTS `slug` VARCHAR(100) NULL AFTER `name`,
     ADD UNIQUE KEY IF NOT EXISTS `uq_categories_slug` (`slug`);
   ```
2. **Auto-Healing Helper (`includes/category_helper.php`)**: `ensureCategorySlugColumn($pdo)` checks `INFORMATION_SCHEMA.COLUMNS` (or `PRAGMA table_info` in SQLite) at runtime and automatically executes the migration if the column is absent.
3. **Slug Rules**:
   - Slugs must be URL-friendly (lowercase letters, numbers, hyphens).
   - If omitted by the client, `generateCategorySlug($name)` automatically converts names into sanitized slugs (e.g., `"Home & Kitchen"` &rarr; `"home-kitchen"`).

### 2.3. Relational Decoupling Safeguard (`ON DELETE SET NULL`)

The foreign key constraint between `products` and `categories` is defined as:
```sql
CONSTRAINT `fk_products_category` 
  FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) 
  ON DELETE SET NULL ON UPDATE CASCADE
```
When a category is deleted:
- The endpoint `api/categories/destroy.php` executes inside an atomic PDO transaction.
- It explicitly executes `UPDATE products SET category_id = NULL WHERE category_id = :id`.
- The category row is then deleted from `categories`.
- **Zero products are deleted**. Referenced products seamlessly transition to "Uncategorized" status.

---

## 3. Category CRUD Endpoints Specification

### 3.1. List Categories (`GET /api/categories/index.php`)
Retrieves paginated categories with real-time assigned product counts, search, and status filtering.

- **Method**: `GET`
- **Authentication**: Public (Read-only)
- **Query Parameters**:
  - `page` *(int, default: 1)*
  - `limit` *(int, default: 10, max: 100)*
  - `search` *(string, optional)*: Partial match against `name`, `slug`, or `description`.
  - `status` *(string, optional)*: Filter by `active` or `inactive`.
  - `sort` *(string, default: 'id')*: Allowlisted to `id`, `name`, `slug`, `status`, `product_count`, `created_at`.
  - `order` *(string, default: 'ASC')*: `ASC` or `DESC`.

#### Sample Response (`200 OK`)
```json
{
  "success": true,
  "message": "Categories retrieved successfully.",
  "data": {
    "items": [
      {
        "id": 1,
        "name": "Electronics",
        "slug": "electronics",
        "description": "Consumer electronics, gadgets, audio gear, and personal devices.",
        "status": "active",
        "product_count": 4,
        "created_at": "2026-03-01 10:00:00",
        "updated_at": "2026-03-01 10:00:00"
      }
    ],
    "meta": {
      "page": 1,
      "limit": 10,
      "total_items": 5,
      "total_pages": 1
    },
    "summary": {
      "total_categories": 5,
      "active_categories": 5,
      "inactive_categories": 0,
      "total_assigned_products": 12
    }
  }
}
```

---

### 3.2. Show Category Details & Products (`GET /api/categories/show.php`)
Retrieves a single category record along with the list of active products currently assigned to it.

- **Method**: `GET`
- **Query Parameters**: `id` *(int)* or `slug` *(string)*

#### Sample Response (`200 OK`)
```json
{
  "success": true,
  "message": "Category details retrieved successfully.",
  "data": {
    "category": {
      "id": 1,
      "name": "Electronics",
      "slug": "electronics",
      "description": "Consumer electronics, gadgets, audio gear, and personal devices.",
      "status": "active",
      "product_count": 2,
      "products": [
        {
          "id": 1,
          "name": "Aura Noise-Cancelling Headphones",
          "sku": "AUR-NC-001",
          "price": 249.99,
          "stock": 45,
          "status": "in_stock"
        },
        {
          "id": 2,
          "name": "Pulse Wireless Earbuds Pro",
          "sku": "PLS-WB-002",
          "price": 129.50,
          "stock": 80,
          "status": "in_stock"
        }
      ]
    }
  }
}
```

---

### 3.3. Create Category (`POST /api/categories/store.php`)
Validates name and slug, ensures uniqueness across existing categories, and creates the new category.

- **Method**: `POST`
- **Authentication**: Required (`requireAuth()`)
- **CSRF Protection**: Required (`requireCsrf()` via `X-CSRF-Token` header)
- **Request Body**:
```json
{
  "name": "Smart Home & IoT",
  "slug": "smart-home-iot",
  "description": "Connected smart home devices, sensors, and lighting.",
  "status": "active"
}
```
*Note: If `slug` is omitted, the server automatically generates `smart-home-iot` from `name`.*

#### Success Response (`201 Created`)
```json
{
  "success": true,
  "message": "Category created successfully.",
  "data": {
    "category": {
      "id": 6,
      "name": "Smart Home & IoT",
      "slug": "smart-home-iot",
      "description": "Connected smart home devices, sensors, and lighting.",
      "status": "active",
      "product_count": 0
    }
  }
}
```

---

### 3.4. Update Category (`POST /api/categories/update.php`)
Updates an existing category. Includes self-retention checks to prevent false duplicate errors when updating without changing the name or slug.

- **Method**: `POST`
- **Authentication**: Required
- **CSRF Protection**: Required
- **Request Body**:
```json
{
  "id": 6,
  "name": "Smart Home & Automation",
  "slug": "smart-home-automation",
  "description": "Updated description for connected home devices.",
  "status": "active"
}
```

#### Success Response (`200 OK`)
```json
{
  "success": true,
  "message": "Category updated successfully.",
  "data": {
    "category": {
      "id": 6,
      "name": "Smart Home & Automation",
      "slug": "smart-home-automation",
      "status": "active",
      "product_count": 0
    }
  }
}
```

---

### 3.5. Safe Delete Category (`POST /api/categories/destroy.php` / `delete.php`)
Decouples assigned products by setting their `category_id` to `NULL` within an atomic transaction, then removes the category record. Products are completely preserved.

- **Method**: `POST`
- **Authentication**: Required
- **CSRF Protection**: Required
- **Request Body**: `{"id": 6}`

#### Success Response (`200 OK`)
```json
{
  "success": true,
  "message": "Category 'Smart Home & Automation' deleted successfully. 0 assigned product(s) have been moved to uncategorized.",
  "data": {
    "deleted_id": 6,
    "name": "Smart Home & Automation",
    "decoupled_products_count": 0
  }
}
```

---

## 4. Frontend Integration & XSS Defense

### 4.1. API Service Layer (`assets/js/api.js`)
Exposes dedicated Category methods matching the other CRUD modules:
```javascript
API.categories = {
  list: (params) => API.get('categories/index.php', params),
  get: (id) => API.get('categories/show.php', { id }),
  create: (data) => API.post('categories/store.php', data),
  update: (data) => API.post('categories/update.php', data),
  destroy: (id) => API.post('categories/destroy.php', { id }),
  delete: (id) => API.post('categories/destroy.php', { id })
};
```

### 4.2. UI Controller (`assets/js/categories.js`)
- **Live Data**: Fetches categories from `API.categories.list()` with search, status filters, and sorting.
- **Graceful Offline Fallback**: If the PHP/MySQL server is not running, falls back transparently to browser `Store.getCategories()`.
- **XSS Defense**: All category names, slugs, descriptions, and assigned product names rendered into table rows or modals are strictly sanitized via `UI.escapeHtml()`:
```javascript
const name = UI.escapeHtml(cat.name || 'Unnamed');
const slug = UI.escapeHtml(cat.slug || '');
const desc = UI.escapeHtml(cat.description || 'No description');
```
This guarantees that malicious payloads (e.g. `<img src=x onerror=alert(1)>`) injected into category fields are safely escaped and neutralised.

---

## 5. Automated Regression Test Suite

The test suite in [`tests/category_test.php`](file:///c:/Shopsphere/tests/category_test.php) provides 16 automated test cases verifying all backend requirements:

| Test Code | Description | Result |
|---|---|---|
| `CAT-01` | List categories retrieves seeded records with product counts | **PASS** |
| `CAT-02` | Search categories by partial name filter matches correctly | **PASS** |
| `CAT-03` | Filter categories by status ('active' / 'inactive') | **PASS** |
| `CAT-04` | Get single category details by valid ID | **PASS** |
| `CAT-05` | Get single category details by valid slug | **PASS** |
| `CAT-06` | Get category returns 404 for non-existent category | **PASS** |
| `CAT-07` | Create category succeeds with valid data & auto-slug | **PASS** |
| `CAT-08` | Create category rejects missing name with 422 | **PASS** |
| `CAT-09` | Create category rejects duplicate category name | **PASS** |
| `CAT-10` | Create category rejects duplicate category slug | **PASS** |
| `CAT-11` | Update category successfully updates name & description | **PASS** |
| `CAT-12` | Update category allows self-retention of existing name & slug | **PASS** |
| `CAT-13` | Update category rejects renaming to existing different category name | **PASS** |
| `CAT-14` | Delete category safely decouples products (sets category_id = NULL) without deleting products | **PASS** |
| `CAT-15` | Delete category returns 404 for non-existent category ID | **PASS** |
| `CAT-16` | Delete category alias endpoint (delete.php) functions identically to destroy.php | **PASS** |

### Execution Command:
```powershell
& "C:\xampp\php\php.exe" tests/category_test.php
```
**Result**: **16 / 16 PASSED (100% Pass Rate)**.

