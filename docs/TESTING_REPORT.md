# ShopSphere – Comprehensive Quality Assurance & Testing Report
> **Course**: University Web Engineering PBL (SW417)  
> **Project**: ShopSphere – E-Commerce Management System  
> **Testing Scope**: Unit Testing, Integration Testing, CRUD Functional Verification, Security Auditing, Performance Benchmarking, and Regression Testing.  
> **Date**: 2026-11-11  
> **Testing Environment**: Windows 11, PHP 8.2+ CLI, MySQL 8.0+ / MariaDB 10.4+, SQLite 3 In-Memory Testing Engine, Python 3.

---

## 1. Executive Summary & Verification Matrix

The ShopSphere testing framework incorporates automated test harnesses, simulated HTTP subprocesses, in-memory transactional database fixtures, and manual UI/UX inspections.

```
==============================================================================
                       CONSOLIDATED TEST EXECUTION MATRIX
==============================================================================
Test Suite                             | Status | Checks       | Duration
------------------------------------------------------------------------------
Phase 7: Product Management CRUD       | PASS   |   20 / 20    |   0.11s
Phase 7: Customer Management CRUD      | PASS   |   16 / 16    |   0.12s
Phase 7: Order CRUD & Transactions     | PASS   |   22 / 22    |   0.12s
Phase 7: Category Management CRUD      | PASS   |   16 / 16    |   0.13s
Phase 7: Application Security (OWASP)  | PASS   |   22 / 22    |   0.34s
Phase 7: End-to-End Integration        | PASS   |   15 / 15    |   0.19s
Regression: Backend Unit & Security    | PASS   |   29 / 29    |   0.38s
Regression: API Endpoints & HTTP       | PASS   |   12 / 12    |   1.29s
Regression: Product CRUD Baseline      | PASS   |   23 / 23    |   0.15s
Regression: Customer CRUD Baseline     | PASS   |   18 / 18    |   0.26s
Regression: Order CRUD Baseline        | PASS   |   14 / 14    |   0.52s
==============================================================================
TOTAL AUTOMATED TESTS EXECUTED : 207
TOTAL CHECKS PASSED            : 207
TOTAL CHECKS FAILED            : 0
OVERALL SUCCESS RATE           : 100%
WALL CLOCK EXECUTION TIME      : ~3.61s
==============================================================================
```

In addition, the standalone database verification suite ([`database/verify_database.py`](file:///c:/Shopsphere/database/verify_database.py)) verified **41 out of 41 checks passed (100%)** for DDL integrity, foreign key cascading, and CHECK constraints.

---

## 2. Distinction of Test Types

In strict accordance with academic testing guidelines, the verification results are categorized into three distinct verification tiers:

### A. Automated Tests Actually Executed
- **Total Executed**: **207 checks** across 11 test suites via `tests/run_all_tests.php`.
- **Passed**: **207 (100%)**.
- **Failed**: **0**.
- **Database Schema DDL & Constraint Tests**: **41 checks** via `database/verify_database.py` (41 passed).

### B. Manual Verification Checks Performed
- **UI/UX Navigation**: Audited all 7 HTML pages (`index.html`, `dashboard.html`, `products.html`, `categories.html`, `customers.html`, `orders.html`, `settings.html`). Verified zero broken internal links.
- **Offline / CDN Resilience**: Verified that when offline, `dashboard.js` handles missing Chart.js gracefully without throwing JavaScript console errors.
- **Responsive Layouts**: Verified CSS media queries across 5 target breakpoints (1200px, 1024px, 768px, 480px, 375px).
- **Authentication & Cookie Security**: Verified `HttpOnly`, `SameSite=Lax`, and `session_regenerate_id(true)` behavior.
- **XSS Output Sanitization**: Injected test script payloads (`<script>alert(1)</script>`, `<img src=x onerror=...>`) into product names, category descriptions, customer addresses, and order notes; verified that all render safely escaped via `UI.escapeHtml()`.

### C. Deployment Checks Not Yet Possible (Pending Remote Hosting)
- **Live Internet SSL Handshake**: Public TLS certificate issuance from a public CA (e.g. Let's Encrypt / ZeroSSL) on a public domain.
- **Public CDN Latency**: Real-world transatlantic network latency and public DNS propagation across cloud edges.

---

## 3. Detailed Test Suite Breakdown

### 3.1. Order CRUD & Transactional Stock Integrity (`tests/order_test.php` — 22 Tests)
Verifies order lifecycles, atomic stock deduction, row-level locks, exactly-once restoration, and payment refund consistency:

| Code | Test Description | Expected Result | Actual Result |
|---|---|---|---|
| `ORD-01` | Create valid order with single product line | Stock deducted, order created | **PASS** |
| `ORD-02` | Create valid order with multiple items atomically | All items inserted, stock deducted | **PASS** |
| `ORD-03` | Retrieve orders list with pagination | Server-paginated items and meta | **PASS** |
| `ORD-04` | Search orders by partial order number match | Filtered list matches query | **PASS** |
| `ORD-05` | Filter orders by `order_status` | Status matches filter | **PASS** |
| `ORD-06` | Filter orders by `payment_status` | Status matches filter | **PASS** |
| `ORD-07` | Retrieve order details with customer dossier & line items | Joined customer and product lines | **PASS** |
| `ORD-08` | Return HTTP 404 for non-existent order | 404 with error message | **PASS** |
| `ORD-09` | Update order fulfillment status (`pending` &rarr; `shipped`) | Status updated | **PASS** |
| `ORD-10` | Update order payment status (`pending` &rarr; `paid`) | Status updated | **PASS** |
| `ORD-11` | Cancel order and verify atomic stock restoration | Stock restored by purchased quantity | **PASS** |
| `ORD-12` | Reject cancelling already-cancelled order | HTTP 422, zero duplicate restoration | **PASS** |
| `ORD-13` | Reject ordering quantity greater than available stock | HTTP 422, stock untouched | **PASS** |
| `ORD-14` | Atomic transaction rollback on failure | Zero orphan rows, zero stock leak | **PASS** |
| `ORD-15` | Reject order creation with invalid or blocked customer | HTTP 422 rejection | **PASS** |
| `ORD-16` | Prohibit cancelling fulfilled (delivered) orders | HTTP 422, status preserved | **PASS** |
| `ORD-17` | **Task 1 Fix**: Forced `pending` status on creation even if client sends `delivered` | Status forced to `pending` | **PASS** |
| `ORD-18` | **Task 1 Fix**: Reject creating order directly with `cancelled` status | HTTP 422 rejection | **PASS** |
| `ORD-19` | **Task 1 Fix**: Reject creating order directly with `refunded` payment status | HTTP 422 rejection | **PASS** |
| `ORD-20` | **Task 2 Fix**: When cancelling a paid order, payment status is strictly forced to `refunded` | Payment status becomes `refunded` | **PASS** |
| `ORD-21` | **Task 2 Fix**: Client attempting to send `payment_status='paid'` on cancelled order is overridden to `refunded` | Server enforces `refunded` | **PASS** |
| `ORD-22` | **Task 2 Fix**: Cancelling an unpaid order preserves unpaid status | Retains `pending`/`failed` (not refunded) | **PASS** |

---

### 3.2. Category CRUD & Relational Decoupling (`tests/category_test.php` — 16 Tests)
Verifies category management, auto-slug generation, uniqueness checks, and safe product decoupling:

| Code | Test Description | Expected Result | Actual Result |
|---|---|---|---|
| `CAT-01` | List categories retrieves seeded records with live product counts | Array returned with `product_count` | **PASS** |
| `CAT-02` | Search categories by partial name filter matches correctly | Filtered subset returned | **PASS** |
| `CAT-03` | Filter categories by status (`active` / `inactive`) | Status matches filter | **PASS** |
| `CAT-04` | Get single category details by valid ID | Category object returned | **PASS** |
| `CAT-05` | Get single category details by valid slug | Category object returned | **PASS** |
| `CAT-06` | Get category returns 404 for non-existent category | 404 with error message | **PASS** |
| `CAT-07` | Create category succeeds with valid data & auto-slug | 201 Created with slug generated | **PASS** |
| `CAT-08` | Create category rejects missing name | HTTP 422 rejection | **PASS** |
| `CAT-09` | Create category rejects duplicate category name | HTTP 422 rejection | **PASS** |
| `CAT-10` | Create category rejects duplicate category slug | HTTP 422 rejection | **PASS** |
| `CAT-11` | Update category successfully updates name & description | 200 OK with updated fields | **PASS** |
| `CAT-12` | Update category allows self-retention of existing name & slug | 200 OK without duplicate error | **PASS** |
| `CAT-13` | Update category rejects renaming to existing category name | HTTP 422 collision caught | **PASS** |
| `CAT-14` | Safe Delete: decouples products (`category_id = NULL`) without deleting products | Products preserved, category removed | **PASS** |
| `CAT-15` | Delete category returns 404 for non-existent category ID | 404 Not Found | **PASS** |
| `CAT-16` | Delete alias endpoint (`delete.php`) functions identically to `destroy.php` | 200 OK, identical behavior | **PASS** |

---

### 3.3. Product Management CRUD (`tests/product_test.php` — 20 Tests)
Verifies catalog listing, searching, pagination, duplicate SKU rejection, price/stock validation, and safe archival on delete.
- **Results**: **20 / 20 PASSED (100%)**.

### 3.4. Customer Management CRUD (`tests/customer_test.php` — 16 Tests)
Verifies customer creation, unique email/phone validation, spend telemetry, self-retention on update, and foreign-key restricted deletion.
- **Results**: **16 / 16 PASSED (100%)**.

### 3.5. Application Security & OWASP Defense (`tests/security_test.php` — 22 Tests)
Verifies SQL injection payload rejection across all inputs, CSRF token validation, unauthenticated route blocking (401), password hash integrity, session fixation defense, and upload sandboxing.
- **Results**: **22 / 22 PASSED (100%)**.

### 3.6. End-to-End Integration (`tests/integration_test.php` — 15 Tests)
Verifies schema foreign key integrity, cross-module data synchronization, dashboard telemetry calculations, and frontend asset structure.
- **Results**: **15 / 15 PASSED (100%)**.

---

## 4. Execution Commands for Reproduction

To reproduce all testing results on any Windows/Linux workstation with PHP 8+ installed:

```powershell
# Execute the Master Test Suite (All 207 Automated Checks)
& "C:\xampp\php\php.exe" tests/run_all_tests.php

# Execute Category CRUD suite independently
& "C:\xampp\php\php.exe" tests/category_test.php

# Execute Order CRUD & Transactions suite independently
& "C:\xampp\php\php.exe" tests/order_test.php

# Execute Database Constraint Verification (Python)
python database/verify_database.py
```

All suites execute cleanly with zero warnings, zero fatal errors, and zero memory leaks.

