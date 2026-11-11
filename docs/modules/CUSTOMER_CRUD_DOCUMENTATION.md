# ShopSphere – Customer Management CRUD Technical Documentation
> **Phase 5: Complete Customer Management CRUD Module**  
> **Technology Stack**: PHP 8+, MySQL 8.0+ / MariaDB 10.4+ (XAMPP), HTML5, CSS3, Vanilla JavaScript (ES6+), Fetch API, PDO Prepared Statements.

---

## 1. Executive Summary & Architecture

The **Customer Management CRUD Module** provides complete lifecycle administration for customer accounts, communication credentials, delivery locations, and lifetime purchasing analytics. Built on pure PHP 8 and native PDO, it seamlessly connects the Phase 1 responsive interface (`customers.html`) to the Phase 2 relational schema (`customers`, `orders`), enforcing strict email uniqueness, international phone validation, CSRF defense, and foreign-key referential integrity (`fk_orders_customer ON DELETE RESTRICT`).

```
Frontend (HTML5 / Vanilla JavaScript)
  │
  ├── assets/js/api.js ───────────── API.customers.{list, get, create, update, delete, stats}
  ├── assets/js/customers.js ─────── Table rendering, modals, validation, debounce search, filters
  └── assets/js/dashboard.js ─────── Real-time MySQL customer counters & metrics integration
           │ (HTTP / JSON + Session Cookie + X-CSRF-Token)
           ▼
Backend Endpoints (PHP 8 + PDO)
  │
  ├── api/customers/index.php ────── Paginated customer directory with order aggregates
  ├── api/customers/show.php ─────── Detailed customer dossier with recent order history
  ├── api/customers/store.php ────── Create customer (unique email, validation, auth/CSRF)
  ├── api/customers/update.php ───── Update customer (conflict-free email self-retention)
  ├── api/customers/delete.php ───── Safe deletion or archival if historical orders exist
  └── api/customers/stats.php ────── Customer metrics (total, active, inactive, new accounts)
           │
           ├── includes/auth.php ─── requireAuth(), requireCsrf() session verification
           ├── includes/validation.php ── Validator class
           └── includes/helpers.php ─ getJsonInput(), sanitizeString()
                   │
                   ▼
Database & Storage
  └── MySQL: `shopsphere_db` (`customers`, `orders`)
      - CONSTRAINT `fk_orders_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE RESTRICT
```

---

## 2. Database Schema & Relational Integrity

### `customers` Table (MySQL InnoDB, utf8mb4)

| Column | Type | Constraints | Description |
|---|---|---|---|
| `id` | `INT UNSIGNED` | `PRIMARY KEY`, `AUTO_INCREMENT` | Unique customer surrogate identifier |
| `full_name` | `VARCHAR(100)` | `NOT NULL` | Customer's full name (2 to 100 characters) |
| `email` | `VARCHAR(100)` | `NOT NULL`, `UNIQUE KEY` (`uq_customers_email`) | Unique email address |
| `phone` | `VARCHAR(30)` | `NULL`, `INDEX` (`idx_customers_phone`) | International telephone number |
| `address` | `TEXT` | `NULL` | Street delivery address |
| `city` | `VARCHAR(50)` | `NULL`, `INDEX` (`idx_customers_city`) | Municipality / City name |
| `postal_code` | `VARCHAR(20)` | `NULL` | Postal or ZIP code |
| `status` | `VARCHAR(20)` | `NOT NULL`, `DEFAULT 'active'`, `INDEX` (`idx_customers_status`) | Account status (`active`, `inactive`, `blocked`) |
| `created_at` | `TIMESTAMP` | `NOT NULL`, `DEFAULT CURRENT_TIMESTAMP` | Account registration timestamp |
| `updated_at` | `TIMESTAMP` | `NOT NULL`, `DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` | Last profile update timestamp |

### Foreign Key Relationship with `orders` Table
```sql
CONSTRAINT `fk_orders_customer` 
  FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) 
  ON DELETE RESTRICT 
  ON UPDATE CASCADE
```
- **Referential Rule**: Deleting a customer record when one or more rows exist in `orders` with matching `customer_id` is prohibited by MySQL (`RESTRICT`).
- **Phase 5 Archival Strategy**: When deletion is requested for a customer with order history, the endpoint intercepts the foreign key constraint, preserves historical orders, and updates `status = 'inactive'`, preventing database constraint violation errors and protecting financial auditability.

---

## 3. API Endpoints Specification

All endpoints communicate via standard JSON envelopes with HTTP status codes.

### 3.1. List Customers (`GET /api/customers/index.php`)
Retrieves paginated, filtered, and sorted customer records with dynamically calculated lifetime purchasing aggregates.

- **Method**: `GET`
- **Authentication**: Public or Authenticated
- **Query Parameters**:
  - `page` *(int, default: 1)*: Page number.
  - `limit` *(int, default: 10, max: 100)*: Records per page.
  - `search` *(string, optional)*: Multi-field search across `full_name`, `email`, `phone`, `city`.
  - `city` *(string, optional)*: Filter by city name.
  - `status` *(string, optional)*: Filter by status (`active`, `inactive`, `blocked`).
  - `sort` *(string, default: 'id')*: Allowlisted to `id`, `full_name`, `email`, `city`, `status`, `created_at`, `total_orders`, `total_spent`.
  - `order` *(string, default: 'DESC')*: `ASC` or `DESC`.

#### Sample Response (`200 OK`)
```json
{
  "success": true,
  "message": "Customer records retrieved successfully.",
  "data": {
    "items": [
      {
        "id": 1,
        "full_name": "Marcus Vance",
        "email": "marcus.vance@techcorp.io",
        "phone": "+1 (415) 882-9014",
        "address": "742 Montgomery St, Suite 400",
        "city": "San Francisco",
        "postal_code": "94111",
        "status": "active",
        "created_at": "2026-01-10 09:00:00",
        "updated_at": "2026-01-10 09:00:00",
        "total_orders": 1,
        "total_spent": 1337.70
      }
    ],
    "meta": {
      "page": 1,
      "limit": 10,
      "total_items": 12,
      "total_pages": 2
    },
    "cities": ["Atlanta", "Austin", "Cambridge", "Chicago", "Denver", "Houston", "New York", "Portland", "San Francisco", "San Jose", "Seattle"],
    "summary": {
      "total": 12,
      "active": 10,
      "inactive": 0,
      "blocked": 2
    }
  }
}
```

---

### 3.2. Show Customer (`GET /api/customers/show.php`)
Retrieves complete customer profile dossier, aggregate lifetime spend, average order value, and their recent orders list.

- **Method**: `GET`
- **Query Parameter**: `id` *(int, required)*
- **Response**: `200 OK` on success with `customer` and `orders` array, `404 Not Found` if non-existent.

---

### 3.3. Create Customer (`POST /api/customers/store.php`)
Stores a new customer in MySQL.

- **Method**: `POST`
- **Authentication**: Required (`requireAuth()`)
- **CSRF Protection**: Required (`requireCsrf()` via `X-CSRF-Token` header)
- **Payload Fields**:
  - `full_name` *(string, required, 2-100 chars, or `first_name` + `last_name`)*
  - `email` *(string, required, valid format, unique in `customers` table)*
  - `phone` *(string, optional, valid international telephone format, 7-30 chars)*
  - `address` *(string, optional, max 500 chars)*
  - `city` *(string, optional, max 50 chars)*
  - `postal_code` *(string, optional, max 20 chars)*
  - `status` *(string, default: 'active', enum: ['active', 'inactive', 'blocked'])*

#### Sample Response (`201 Created`)
```json
{
  "success": true,
  "message": "Customer \"Clara Oswald\" created successfully.",
  "data": {
    "customer": {
      "id": 13,
      "full_name": "Clara Oswald",
      "email": "clara.oswald@coalhill.edu",
      "phone": "+44 20 7946 0958",
      "address": "12 Totter's Lane",
      "city": "London",
      "postal_code": "EC1A 1BB",
      "status": "active",
      "created_at": "2026-11-11 20:30:00",
      "updated_at": "2026-11-11 20:30:00",
      "total_orders": 0,
      "total_spent": 0.00
    }
  }
}
```

---

### 3.4. Update Customer (`POST /api/customers/update.php`)
Modifies an existing customer record without altering historical orders.

- **Method**: `POST`
- **Authentication**: Required (`requireAuth()`)
- **CSRF Protection**: Required (`requireCsrf()`)
- **Query / Body**: `id` *(int, required)*
- **Unique Email Self-Retention**: Verifies `WHERE email = :email AND id != :id` so the customer may keep their current email address without triggering a duplicate error.

---

### 3.5. Delete or Archive Customer (`POST /api/customers/delete.php`)
Deletes an unreferenced customer permanently, or safely archives (`status = 'inactive'`) if historical orders exist.

- **Method**: `POST`
- **Authentication**: Required (`requireAuth()`)
- **CSRF Protection**: Required (`requireCsrf()`)
- **Payload**: `{"id": 1}` or `?id=1`
- **Referential Integrity Enforcement**:
  - Queries `SELECT COUNT(*) FROM orders WHERE customer_id = :id`.
  - **Count = 0 (Unreferenced)**: Executes `DELETE FROM customers WHERE id = :id`. Responds with `action: "deleted"`.
  - **Count > 0 (Referenced in Historical Orders)**: Executes `UPDATE customers SET status = 'inactive', updated_at = CURRENT_TIMESTAMP WHERE id = :id`. Responds with `action: "archived"` and an explanation of the archival action.

---

### 3.6. Customer Stats (`GET /api/customers/stats.php`)
Provides aggregate metrics for the dashboard counters.

- **Method**: `GET`
- **Response**: `total_customers`, `active_customers`, `inactive_customers`, `blocked_customers`, `new_customers` (registered in the last 30 days), and `top_spenders`.

---

## 4. Frontend Integration Highlights

1. **`assets/js/api.js`**:
   - Implements `API.customers.list()`, `get()`, `create()`, `update()`, `delete()`, `stats()`.
   - Passes credentials and `X-CSRF-Token` header automatically.

2. **`assets/js/customers.js`**:
   - Replaced hardcoded localStorage records with dynamic MySQL API queries.
   - Search with 300ms debounce across name, email, phone, and city.
   - Dynamic City filter dropdown populated from unique database cities.
   - Status dropdown (`all`, `active`, `inactive`, `blocked`).
   - Server-side sorting (`newest`, `name_asc`, `name_desc`, `orders_high`, `spent_high`).
   - Server pagination with `UI.renderPagination`.
   - Error mapping from backend validation response to form input controls.
   - Toast notifications for operations.
   - Fallback to `Store` if API server is offline.

3. **`assets/js/dashboard.js`**:
   - Integrated `API.customers.stats()` into `loadDashboardData()`, populating real customer counts on top dashboard KPI cards.

---

## 5. Security Measures

1. **SQL Injection Defense**: Every dynamic parameter is bound via PDO prepared statements (`execute(['param' => $val])`). Sorting column names are strictly checked against a hardcoded allowlist.
2. **CSRF Mitigation**: Double-submit CSRF verification (`requireCsrf()`) rejects unverified state-changing requests with HTTP 403.
3. **Session Hardening**: HTTP-only session cookies with `SameSite=Lax`, strict session name `SHOPSPHERE_SESSID`, and regenerated IDs on login.
4. **Data Sanitization**: `sanitizeString()` strips null bytes (`chr(0)`) and trims extraneous whitespace.
5. **Output Escaping**: `escapeHtml()` on the frontend escapes `&`, `<`, `>`, `"`, and `'` before rendering into the DOM.
6. **No Data Leaks**: Database connection strings, SQL queries, and internal stack traces are suppressed in production mode.

---

## 6. Automated Verification & Test Results

The test suite [`tests/customer_crud_test.php`](file:///c:/Shopsphere/tests/customer_crud_test.php) executes all 18 cases required by Phase 5:

| # | Test Case Description | Result |
|---|---|---|
| **01** | Add a valid customer record | **PASS** |
| **02** | Reject missing required fields (`full_name`, `email`) | **PASS** |
| **03** | Reject invalid email addresses | **PASS** |
| **04** | Reject duplicate email addresses | **PASS** |
| **05** | Reject invalid field lengths (`name > 100`, `city > 50`) | **PASS** |
| **06** | Display customer records from database with order aggregates | **PASS** |
| **07** | Search by customer name locates matching records | **PASS** |
| **08** | Search by email and phone locates target customers | **PASS** |
| **09** | Filter by city and status returns exact subsets | **PASS** |
| **10** | Edit customer information persists changes | **PASS** |
| **11** | Verify updated data & email self-retention with conflict defense | **PASS** |
| **12** | Delete a customer without historical orders (permanent delete) | **PASS** |
| **13** | Protect a customer who has historical orders via archival strategy | **PASS** |
| **14** | Verify unauthorized API requests are correctly rejected (`401`) | **PASS** |
| **15** | Verify CSRF token accepts valid token and rejects forged tokens (`403`) | **PASS** |
| **16** | Verify server pagination limit and empty state query behavior | **PASS** |
| **17** | Verify responsive layout styles cover desktop, tablet, and mobile | **PASS** |
| **18** | Confirm Product CRUD suite continues to pass with 100% success (zero regressions) | **PASS** |

### Execution Command
```powershell
& "C:\xampp\php\php.exe" tests/customer_crud_test.php
```

---

## 7. Known Limitations & Phase 6 Boundaries

1. **Order CRUD Scope**: Customer orders displayed in the customer dossier modal are read-only. Editing or placing new multi-line item orders belongs strictly to **Phase 6: Order Management CRUD**.
2. **Category / Settings Persistence**: Category and Settings modules remain backed by their respective Phase 1/Phase 3 storage stores until future phases.

