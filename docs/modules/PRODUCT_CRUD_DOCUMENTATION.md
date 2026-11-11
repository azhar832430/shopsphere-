# ShopSphere – Product Management CRUD Technical Documentation
> **Phase 4: Complete Product Management CRUD Module**  
> **Technology Stack**: PHP 8+, MySQL 8.0+ / MariaDB 10.4+ (XAMPP), HTML5, CSS3, Vanilla JavaScript (ES6+), Fetch API, PDO Prepared Statements.

---

## 1. Executive Summary & Architecture

The **Product Management CRUD Module** provides end-to-end administration of the ShopSphere inventory catalog. Built strictly on native PHP 8 and PDO, it bridges the Phase 1 responsive frontend with the Phase 2 MySQL relational schema (`products`, `categories`, `order_items`), enforcing enterprise validation, CSRF defense, secure file uploads, and foreign-key referential integrity.

```
Frontend (HTML5 / Vanilla JS)
  │
  ├── assets/js/api.js ───────────── API.products.{list, get, create, update, delete, stats}
  ├── assets/js/products.js ──────── DOM bindings, modals, validation, debounce search, pagination
  └── assets/js/dashboard.js ─────── Real-time MySQL product telemetry integration
           │ (HTTP / JSON / Multipart FormData + Cookie + X-CSRF-Token)
           ▼
Backend Endpoints (PHP 8 + PDO)
  │
  ├── api/products/index.php ─────── Read & Paginate (search, category, stock, allowlisted sort)
  ├── api/products/show.php ──────── Read single product with category details
  ├── api/products/store.php ─────── Create product (unique SKU, positive price, image upload/URL)
  ├── api/products/update.php ────── Update product (conflict check, image replacement/retention)
  ├── api/products/delete.php ────── Safe deletion or archival if referenced in order_items
  └── api/products/stats.php ─────── Inventory telemetry (total, active, low stock, out of stock)
           │
           ├── includes/upload.php ─ MIME validation (finfo), 2MB size limit, unique naming
           ├── includes/auth.php ─── requireAuth(), requireCsrf() session verification
           └── includes/validation.php ── Validator class
                   │
                   ▼
Database & Storage
  ├── MySQL: `shopsphere_db` (`products`, `categories`, `orders`, `order_items`)
  └── File System: `uploads/products/` protected with `.htaccess` (execution disabled)
```

---

## 2. API Endpoints Specification

All endpoints communicate via standard JSON envelopes with HTTP status codes.

### 2.1. List Products (`GET /api/products/index.php`)
Retrieves paginated, filtered, and sorted catalog items with active category metadata.

- **Method**: `GET`
- **Authentication**: Public or Authenticated (read-only)
- **Query Parameters**:
  - `page` *(int, default: 1)*: Current page number.
  - `limit` *(int, default: 10, max: 100)*: Items per page.
  - `search` *(string, optional)*: Partial match across product `name`, `sku`, or `description`.
  - `category_id` *(int, optional)*: Filter by category ID.
  - `stock_status` *(string, optional)*: `all`, `in_stock` (>0), `low_stock` (1-10), `out_of_stock` (=0).
  - `sort` *(string, default: 'id')*: Allowlisted to `id`, `name`, `price`, `stock`, `created_at`, `sku`.
  - `order` *(string, default: 'DESC')*: `ASC` or `DESC`.

#### Sample Response (`200 OK`)
```json
{
  "success": true,
  "message": "Products retrieved successfully.",
  "data": {
    "items": [
      {
        "id": 1,
        "category_id": 2,
        "category_name": "Audio",
        "name": "Pulse Wireless Headphones",
        "sku": "AUD-WL-01",
        "description": "High fidelity noise cancelling headphones",
        "price": 129.99,
        "stock": 25,
        "image": "uploads/products/prod_6731d102e.webp",
        "status": "active",
        "created_at": "2026-11-11 10:15:00",
        "updated_at": "2026-11-11 10:15:00"
      }
    ],
    "meta": {
      "page": 1,
      "limit": 10,
      "total_items": 1,
      "total_pages": 1
    },
    "categories": [
      { "id": 1, "name": "Electronics" },
      { "id": 2, "name": "Audio" }
    ]
  }
}
```

---

### 2.2. Show Product (`GET /api/products/show.php`)
Retrieves complete specifications for a single product.

- **Method**: `GET`
- **Query Parameter**: `id` *(int, required)*
- **Response**: `200 OK` on success, `404 Not Found` if nonexistent.

---

### 2.3. Create Product (`POST /api/products/store.php`)
Stores a new product in the database. Supports both JSON payload and `multipart/form-data` with file upload.

- **Method**: `POST`
- **Authentication**: Required (`requireAuth()`)
- **CSRF Protection**: Required (`requireCsrf()` via `X-CSRF-Token` header)
- **Payload Fields**:
  - `name` *(string, required, 2-150 chars)*
  - `sku` *(string, required, 2-50 chars, unique across database)*
  - `category_id` *(int, optional, must exist in `categories` table)*
  - `price` *(numeric decimal, required, >= 0.00)*
  - `stock` *(integer, required, >= 0)*
  - `description` *(string, optional)*
  - `status` *(string, default: 'active', enum: ['active', 'inactive'])*
  - `image` / `image_url` *(string, optional, valid URL)*
  - `image_file` *(binary file, optional, multipart upload: JPG/PNG/WebP, <= 2MB)*

#### Sample Response (`201 Created`)
```json
{
  "success": true,
  "message": "Product \"Pulse Wireless Headphones\" created successfully.",
  "data": {
    "product": {
      "id": 15,
      "name": "Pulse Wireless Headphones",
      "sku": "AUD-WL-01",
      "price": 129.99,
      "stock": 25,
      "image": "uploads/products/prod_6732e41.png"
    }
  }
}
```

---

### 2.4. Update Product (`POST /api/products/update.php`)
Updates an existing product's fields. Supports both JSON and `multipart/form-data`.

- **Method**: `POST`
- **Authentication**: Required (`requireAuth()`)
- **CSRF Protection**: Required (`requireCsrf()`)
- **Payload Fields**: Same as Store, plus `id` (or query param `?id=...`).
- **Unique SKU Logic**: Verifies `WHERE sku = :sku AND id != :id` so the product may keep its current SKU without throwing a false duplicate error.
- **Image Replacement**: If a new file or image URL is uploaded, previous uploaded local file in `uploads/products/` is safely removed from disk.

---

### 2.5. Delete or Archive Product (`POST /api/products/delete.php`)
Deletes an unreferenced product permanently, or safely deactivates (archives) a product if historical customer orders exist.

- **Method**: `POST`
- **Authentication**: Required (`requireAuth()`)
- **CSRF Protection**: Required (`requireCsrf()`)
- **Payload**: `{"id": 12}` or query param `?id=12`.
- **Referential Integrity Enforcement**:
  - Queries `SELECT COUNT(*) FROM order_items WHERE product_id = :id`.
  - **If Count = 0**: Executes `DELETE FROM products WHERE id = :id` and deletes uploaded image file from disk. Returns `action: "deleted"`.
  - **If Count > 0**: Because `order_items.product_id` references `products.id` with `ON DELETE RESTRICT`, attempting a hard delete would violate foreign key constraints. The system automatically sets `status = 'inactive'`, preserving order history while removing the product from active sales. Returns `action: "archived"`.

#### Sample Response When Referenced (`200 OK`)
```json
{
  "success": true,
  "message": "Product \"Pulse Wireless Headphones\" is associated with 3 existing customer order(s). To preserve historical sales records, the product has been safely archived (marked inactive) instead of deleted.",
  "data": {
    "id": 1,
    "action": "archived",
    "historical_orders_count": 3
  }
}
```

---

### 2.6. Product Inventory Stats (`GET /api/products/stats.php`)
Provides aggregate metrics for the administration dashboard and inventory monitoring.

- **Method**: `GET`
- **Response**: Total products, active count, inactive count, in-stock count, low-stock count, out-of-stock count, average unit price, and list of top low-stock items.

---

## 3. Image Upload Security Architecture

To prevent arbitrary file upload vulnerabilities and remote code execution:

1. **Storage Isolation**: Uploads are saved to `c:\Shopsphere\uploads\products\`.
2. **Execution Disabled via `.htaccess`**:
   ```apache
   Options -Indexes -ExecCGI
   RemoveHandler .php .phtml .php3 .php4 .php5 .php7 .phps .cgi .pl .py .asp .exe
   <FilesMatch "\.(php|phtml|php3|php4|php5|php7|phps|cgi|pl|py|asp|exe)$">
       Require all denied
   </FilesMatch>
   <IfModule mod_php.c>
       php_flag engine off
   </IfModule>
   ```
3. **MIME Verification**: Inspects raw binary headers using `finfo(FILEINFO_MIME_TYPE)`. Only `image/jpeg`, `image/png`, and `image/webp` are permitted.
4. **Size Constraint**: Maximum file size is strictly limited to 2,097,152 bytes (2MB).
5. **Randomized Renaming**: Filenames are regenerated with `prod_` + `uniqid()` + cryptographically random bytes + verified extension (`prod_6731d102e88a1.webp`). Original user-supplied filenames are never used directly on the filesystem.
6. **Safe File Removal**: `deleteProductImageFile()` verifies that the file path resides strictly inside `uploads/products/` and prevents directory traversal before calling `unlink()`.

---

## 4. Frontend Integration Highlights

1. **`assets/js/api.js`**:
   - Exposes `API.products.list()`, `get()`, `create()`, `update()`, `delete()`, `stats()`.
   - Handles `FormData` instances without setting hardcoded `Content-Type: application/json`, allowing the browser to generate proper multipart boundaries.
   - Automatically attaches `X-CSRF-Token` and session cookies on state-changing requests.

2. **`assets/js/products.js`**:
   - Fetches and displays products from MySQL.
   - Full search with 300ms debounce.
   - Live category and stock status filtering.
   - Server-side pagination and sort selection.
   - Live image preview for both URL inputs and file uploads.
   - Submits `FormData` when file is attached or clean JSON when using URL.
   - Maps server validation errors directly to the corresponding form controls.
   - Displays clear toast notifications (`success`, `warning`, `error`).
   - Retains fallback to `Store` if backend connection is offline.

3. **`assets/js/dashboard.js`**:
   - Queries `API.products.stats()` to display real MySQL product counts, low stock counts, and low stock alert items on the dashboard.

---

## 5. Verification & Test Suite

The test suite in `tests/product_crud_test.php` validates all 17 required cases plus 6 security checks:

| # | Test Case Description | Result |
|---|---|---|
| **01** | Product creation with valid data | **PASS** |
| **02** | Validation error on missing required fields | **PASS** |
| **03** | Validation error on duplicate SKU detection | **PASS** |
| **04** | Validation error on negative price | **PASS** |
| **05** | Validation error on negative stock | **PASS** |
| **06** | Rejection of non-existent category ID | **PASS** |
| **07** | Product listing with default pagination | **PASS** |
| **08** | Product listing with custom page size | **PASS** |
| **09** | Product search by name | **PASS** |
| **10** | Product search by SKU | **PASS** |
| **11** | Product filtering by category | **PASS** |
| **12** | Product filtering by status | **PASS** |
| **13** | Product sorting by price (ASC) | **PASS** |
| **14** | Product update with valid data | **PASS** |
| **15** | Product update rejects duplicate SKU of another product | **PASS** |
| **16** | Product deletion when not referenced in orders (hard delete) | **PASS** |
| **17** | Product deletion handling when referenced in orders (archived) | **PASS** |
| **Sec 01** | Image validator accepts <= 2MB file size | **PASS** |
| **Sec 02** | Image validator flags > 2MB file size as invalid | **PASS** |
| **Sec 03** | Unauthenticated request correctly identified | **PASS** |
| **Sec 04** | Authenticated session established | **PASS** |
| **Sec 05** | CSRF token verification accepts valid session token | **PASS** |
| **Sec 06** | CSRF token verification rejects invalid/forged token | **PASS** |

### Running the Test Suite
```powershell
& "C:\xampp\php\php.exe" tests/product_crud_test.php
```

