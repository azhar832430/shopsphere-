# ShopSphere – Order Management CRUD Technical Documentation
> **Phase 6: Complete Order Management CRUD Module**  
> **Technology Stack**: PHP 8+, MySQL 8.0+ / MariaDB 10.4+ (XAMPP), HTML5, CSS3, Vanilla JavaScript (ES6+), Fetch API, PDO Prepared Statements, ACID Transactions.

---

## 1. Executive Summary & Architecture

The **Order Management CRUD Module** manages the complete sales transaction lifecycle for the ShopSphere administrative console. Built on native PHP 8 and PDO, it handles customer selection, dynamic multi-item line repeaters, real-time calculation of subtotals and totals, atomic inventory reservation via database transactions, fulfillment status tracking, safe cancellation with stock restoration, and financial telemetry.

```
Frontend (HTML5 / Vanilla JavaScript)
  │
  ├── assets/js/api.js ───────────── API.orders.{list, get, create, update, cancel, stats}
  ├── assets/js/orders.js ────────── Order builder, dynamic line repeater, modals, filters, pagination
  └── assets/js/dashboard.js ─────── Real-time MySQL orders, revenue & fulfillment telemetry
           │ (HTTP / JSON + Session Cookie + X-CSRF-Token)
           ▼
Backend Endpoints (PHP 8 + PDO)
  │
  ├── api/orders/index.php ───────── Server-paginated orders list with search, status & date filters
  ├── api/orders/show.php ────────── Detailed invoice, customer dossier & itemized lines breakdown
  ├── api/orders/store.php ───────── Atomic order creation with row locking & stock reservation
  ├── api/orders/update.php ──────── Status transition validation & editable notes
  ├── api/orders/cancel.php ──────── Safe cancellation with atomic stock restoration exactly once
  └── api/orders/stats.php ───────── Revenue metrics (excludes cancelled) & status distribution
           │
           ├── includes/auth.php ─── requireAuth(), requireCsrf() session verification
           ├── includes/helpers.php ─ getJsonInput(), sanitizeString()
           └── includes/validation.php ── Validator class
                   │
                   ▼
Database & Storage
  └── MySQL: `shopsphere_db` (`orders`, `order_items`, `products`, `customers`)
      - ACID Transactions (`beginTransaction`, `commit`, `rollBack`)
      - Row locking (`SELECT ... FOR UPDATE`)
      - Referential Integrity (`fk_order_items_order ON DELETE CASCADE`, `fk_orders_customer ON DELETE RESTRICT`)
```

---

## 2. Order Lifecycle & Status State Machine

The module enforces sensible order fulfillment and payment status transitions:

```
[Pending Order Created] ──(Stock Deducted)
         │
         ├──► [Processing] ──► [Shipped] ──► [Delivered / Fulfilled] (Final)
         │                                          │
         │                                          └── (Cancellation Prohibited)
         ▼
[Cancelled Order] ──(Stock Restored Exactly Once & Payment Refunded)
```

### Status Rules & Transition Guards
1. **Pending (Initial Mandatory State)**: Every newly placed order **always** starts with `pending` status regardless of client payload. Client payloads attempting to initiate an order directly as `cancelled` or `delivered` are strictly disallowed. Initial payment status cannot be `refunded`. Inventory stock is safely reserved and deducted from `products.stock` with row-level locks and atomic boundary checks (`AND stock >= :quantity`).
2. **Processing**: Order accepted for warehouse picking, packing, and validation.
3. **Shipped**: Order in transit with delivery carrier.
4. **Delivered**: Order completed and fulfilled. Once marked `delivered`, orders **cannot** be reverted to pending or cancelled directly to preserve transaction auditability and delivery proof.
5. **Cancelled**: Eligible orders (pending or processing) can be cancelled. When cancelled:
   - Every product line item quantity is added back to `products.stock`.
   - Restoration is executed **exactly once** inside an atomic transaction.
   - **Payment Refund Consistency**: If payment was `paid` (or client attempts to submit `paid` during cancellation), it is strictly transitioned to `refunded`. If payment was unpaid (`pending`, `failed`), it retains its unpaid status and is **not** marked `refunded`.
   - Subsequent cancellations on an already cancelled order are rejected.

---

## 3. Database Schema & Relational Integrity

### `orders` Table (MySQL InnoDB, utf8mb4)

| Column | Type | Constraints | Description |
|---|---|---|---|
| `id` | `INT UNSIGNED` | `PRIMARY KEY`, `AUTO_INCREMENT` | Unique order identifier |
| `order_number` | `VARCHAR(30)` | `NOT NULL`, `UNIQUE KEY` (`uq_orders_number`) | Formatted order reference (e.g. `ORD-7081`) |
| `customer_id` | `INT UNSIGNED` | `NOT NULL`, `FOREIGN KEY` (`fk_orders_customer`) | Customer reference (`ON DELETE RESTRICT`) |
| `order_date` | `DATETIME` | `NOT NULL`, `DEFAULT CURRENT_TIMESTAMP` | Order placement timestamp |
| `subtotal` | `DECIMAL(10,2)`| `NOT NULL`, `DEFAULT 0.00`, `CHECK >= 0` | Sum of item subtotals |
| `total_amount` | `DECIMAL(10,2)`| `NOT NULL`, `DEFAULT 0.00`, `CHECK >= 0` | Final payable total |
| `payment_method` | `VARCHAR(50)`| `NOT NULL`, `DEFAULT 'Credit Card'` | Payment channel |
| `payment_status` | `VARCHAR(20)`| `NOT NULL`, `DEFAULT 'pending'` | `pending`, `paid`, `failed`, `refunded` |
| `order_status` | `VARCHAR(20)`| `NOT NULL`, `DEFAULT 'pending'` | `pending`, `processing`, `shipped`, `delivered`, `cancelled` |
| `notes` | `TEXT` | `NULL` | Internal notes and delivery instructions |
| `created_at` | `TIMESTAMP` | `NOT NULL`, `DEFAULT CURRENT_TIMESTAMP` | Record creation timestamp |
| `updated_at` | `TIMESTAMP` | `NOT NULL`, `DEFAULT CURRENT_TIMESTAMP ON UPDATE` | Last status update timestamp |

### `order_items` Table (MySQL InnoDB, utf8mb4)

| Column | Type | Constraints | Description |
|---|---|---|---|
| `id` | `INT UNSIGNED` | `PRIMARY KEY`, `AUTO_INCREMENT` | Unique line item identifier |
| `order_id` | `INT UNSIGNED` | `NOT NULL`, `FOREIGN KEY` (`fk_order_items_order`) | Cascade deletes with order (`ON DELETE CASCADE`) |
| `product_id` | `INT UNSIGNED` | `NOT NULL`, `FOREIGN KEY` (`fk_order_items_product`)| Protects product deletion (`ON DELETE RESTRICT`) |
| `quantity` | `INT` | `NOT NULL`, `DEFAULT 1`, `CHECK > 0` | Number of purchased units |
| `unit_price` | `DECIMAL(10,2)`| `NOT NULL`, `CHECK >= 0` | Historical price snapshot at purchase |
| `subtotal` | `DECIMAL(10,2)`| `NOT NULL`, `CHECK >= 0` | Line total (`quantity * unit_price`) |
| `created_at` | `TIMESTAMP` | `NOT NULL`, `DEFAULT CURRENT_TIMESTAMP` | Item record creation timestamp |

---

## 4. API Endpoints Specification

### 4.1. List Orders (`GET /api/orders/index.php`)
Retrieves paginated orders with customer details, item quantities, and summary revenue metrics.

- **Method**: `GET`
- **Query Parameters**:
  - `page` *(int, default: 1)*
  - `limit` *(int, default: 10, max: 100)*
  - `search` *(string, optional)*: Partial match on order number or customer name/email.
  - `order_status` *(string, optional)*: Filter by `pending`, `processing`, `shipped`, `delivered`, `cancelled`.
  - `payment_status` *(string, optional)*: Filter by `paid`, `pending`, `failed`, `refunded`.
  - `date_from` / `date_to` *(string, optional)*: Date range filter (`YYYY-MM-DD`).
  - `sort` *(string, default: 'id')*: Allowlisted to `id`, `order_number`, `order_date`, `total_amount`, `order_status`, `payment_status`, `customer_name`, `item_count`.
  - `order` *(string, default: 'DESC')*: `ASC` or `DESC`.

#### Sample Response (`200 OK`)
```json
{
  "success": true,
  "message": "Orders retrieved successfully.",
  "data": {
    "items": [
      {
        "id": 1,
        "order_number": "ORD-7081",
        "customer_id": 1,
        "customer_name": "Marcus Vance",
        "customer_email": "marcus.vance@techcorp.io",
        "order_date": "2026-03-01 14:22:00",
        "subtotal": 1278.99,
        "total_amount": 1337.70,
        "payment_method": "Credit Card",
        "payment_status": "paid",
        "order_status": "delivered",
        "item_count": 2,
        "total_quantity": 2,
        "notes": "Front desk concierge signed for delivery."
      }
    ],
    "meta": {
      "page": 1,
      "limit": 10,
      "total_items": 12,
      "total_pages": 2
    },
    "summary": {
      "total_orders": 12,
      "pending_orders": 3,
      "processing_orders": 3,
      "shipped_orders": 2,
      "delivered_orders": 2,
      "cancelled_orders": 1,
      "total_revenue": 11488.72
    }
  }
}
```

---

### 4.2. Show Order Details & Invoice (`GET /api/orders/show.php`)
Retrieves complete order header, customer dossier, and itemized product line items.

- **Method**: `GET`
- **Query Parameter**: `id` *(int)* or `order_number` *(string)*
- **Response**: `200 OK` on success, `404 Not Found` if non-existent.

---

### 4.3. Create Order (`POST /api/orders/store.php`)
Atomically validates customer and stock, inserts order header and line items, and deducts inventory stock.

- **Method**: `POST`
- **Authentication**: Required (`requireAuth()`)
- **CSRF Protection**: Required (`requireCsrf()` via `X-CSRF-Token` header)
- **Lifecycle Guard (Task 1 Fix)**: Newly created orders **always start in `pending` status**. Any client payload attempting to create an order as `cancelled` or `delivered` is rejected with HTTP 422. Initial payment status cannot be `refunded`.
- **Atomic Stock Deduction**: Uses `UPDATE products SET stock = stock - :quantity WHERE id = :id AND stock >= :quantity` inside an active transaction. If any item fails or `rowCount() === 0`, the entire transaction rolls back immediately.
- **Payload Example**:
```json
{
  "customer_id": 1,
  "items": [
    { "product_id": 1, "quantity": 1 },
    { "product_id": 7, "quantity": 2 }
  ],
  "payment_method": "Credit Card",
  "payment_status": "pending",
  "notes": "Express delivery requested.",
  "tax": 10.50,
  "shipping": 0.00,
  "discount": 0.00
}
```

#### Sample Response (`201 Created`)
```json
{
  "success": true,
  "message": "Order #ORD-7100 created successfully with stock deducted.",
  "data": {
    "order": {
      "id": 13,
      "order_number": "ORD-7100",
      "customer_id": 1,
      "customer_name": "Marcus Vance",
      "subtotal": 1358.98,
      "total_amount": 1369.48,
      "payment_method": "Credit Card",
      "payment_status": "pending",
      "order_status": "pending",
      "items_count": 2
    }
  }
}
```

---

### 4.4. Update Order (`POST /api/orders/update.php`)
Updates order fulfillment status, payment status, and administrative notes.

- **Method**: `POST`
- **Authentication**: Required
- **CSRF Protection**: Required
- **Stock Restoration**: If transitioning to `cancelled`, restores stock automatically.
- **Refund Consistency Guard (Task 2 Fix)**: When transitioning to `cancelled`:
  - If the order was previously paid (`payment_status === 'paid'`) or the client attempts to send `payment_status: 'paid'`, the server strictly sets `payment_status` to `'refunded'`.
  - If the order was unpaid (`pending` or `failed`), it retains its unpaid status and is **never** marked `refunded`.

---

### 4.5. Cancel Order (`POST /api/orders/cancel.php`)
Safely cancels an order, marks payment as refunded if previously paid, preserves the historical record, and restores stock **exactly once**.

- **Method**: `POST`
- **Authentication**: Required
- **CSRF Protection**: Required
- **Payload**: `{"id": 12}`

#### Sample Response (`200 OK`)
```json
{
  "success": true,
  "message": "Order #ORD-7085 cancelled successfully. 3 product unit(s) were restored to catalog inventory.",
  "data": {
    "id": 5,
    "order_number": "ORD-7085",
    "order_status": "cancelled",
    "payment_status": "refunded",
    "restored_units_count": 3
  }
}
```

---

### 4.6. Order Stats (`GET /api/orders/stats.php`)
Retrieves aggregated financial telemetry, status distributions for dashboard charts, and latest 5 recent orders.

- **Revenue Rule**: Excludes cancelled orders (`WHERE order_status != 'cancelled'`).

---

## 5. Transaction and Stock Management Rules

1. **Row-Level Locking**: In MySQL InnoDB, product rows are locked using `SELECT ... FOR UPDATE` within the transaction to eliminate race conditions during concurrent checkouts.
2. **Pre-Deduction Availability Check**: If `stock < requested_quantity`, the transaction rolls back immediately with an error showing requested vs available units.
3. **Atomic Execution**: Insertion of the order header, all line items, and product stock deductions occur inside a single `beginTransaction()` block. If any step fails, `rollBack()` is invoked, leaving zero orphaned rows and zero leaked stock.
4. **Exact Stock Restoration on Cancel**: Order cancellation queries `order_items` for that order and restores product stock by the exact purchased quantity. Once cancelled, subsequent cancellation calls are rejected to prevent duplicate stock restoration.
5. **Delivered Order Guard**: Completed/delivered orders cannot be cancelled directly to maintain delivery proof and financial accounting.

---

## 6. Frontend Integration Highlights

1. **`assets/js/api.js`**:
   - Exposes `API.orders.{list, get, create, update, cancel, stats}`.
   - Automatically attaches credentials and CSRF tokens.

2. **`assets/js/orders.js`**:
   - Connected directly to MySQL through PHP APIs.
   - Dynamic line items repeater: Add/remove product rows with real-time stock and price display.
   - Live calculations: Automatically calculates item subtotals, tax (8.5%), shipping ($15 or free over $100), discount, and grand total.
   - Detailed invoice modal: Renders complete invoice layout with customer address, itemized specifications, financial summary, and browser print action.
   - Quick edit modal: Allows administrative status and payment transitions.
   - Cancellation confirmation modal: Prompts user before cancelling and displays restored unit counts.
   - Fallback to `Store` if API server is offline.

3. **`assets/js/dashboard.js`**:
   - Queries `API.orders.stats()` to display real MySQL order totals, revenue, status breakdown charts, and recent orders.

---

## 7. Automated Test Suite Results

The automated test suites in [`tests/order_crud_test.php`](file:///c:/Shopsphere/tests/order_crud_test.php) and [`tests/order_test.php`](file:///c:/Shopsphere/tests/order_test.php) verify complete order lifecycles and Task 1 & Task 2 fixes:

### Phase 7 Order CRUD & Integrity Suite (`tests/order_test.php` — 22 Tests)
| # | Test Case Description | Result |
|---|---|---|
| **ORD-01** | Create order with single product | **PASS** |
| **ORD-02** | Create order with multiple products atomically | **PASS** |
| **ORD-03** | Retrieve orders list with pagination | **PASS** |
| **ORD-04** | Search orders by order number | **PASS** |
| **ORD-05** | Filter orders by order_status | **PASS** |
| **ORD-06** | Filter orders by payment_status | **PASS** |
| **ORD-07** | Retrieve single order details with customer and items | **PASS** |
| **ORD-08** | Return 404 for non-existent order | **PASS** |
| **ORD-09** | Update order fulfillment status | **PASS** |
| **ORD-10** | Update order payment status | **PASS** |
| **ORD-11** | Cancel order and verify atomic stock restoration | **PASS** |
| **ORD-12** | Reject cancelling already-cancelled order (exactly-once stock restoration) | **PASS** |
| **ORD-13** | Reject ordering quantity greater than available stock | **PASS** |
| **ORD-14** | Atomic transaction rollback on failure (zero stock leak) | **PASS** |
| **ORD-15** | Reject order creation with invalid/inactive customer | **PASS** |
| **ORD-16** | Prohibit cancelling fulfilled (delivered) orders | **PASS** |
| **ORD-17** | **Task 1 Fix**: Newly created order forced to 'pending' even if client sends 'delivered' | **PASS** |
| **ORD-18** | **Task 1 Fix**: Reject creating order directly with 'cancelled' status (422) | **PASS** |
| **ORD-19** | **Task 1 Fix**: Reject creating order directly with 'refunded' payment status (422) | **PASS** |
| **ORD-20** | **Task 2 Fix**: When cancelling a paid order, payment status is strictly forced to 'refunded' | **PASS** |
| **ORD-21** | **Task 2 Fix**: Client attempting to send payment_status='paid' on cancelled order is overridden to 'refunded' | **PASS** |
| **ORD-22** | **Task 2 Fix**: Cancelling an unpaid order preserves unpaid status (not marked refunded) | **PASS** |

### Baseline Order CRUD Suite (`tests/order_crud_test.php` — 14 Tests)
All 14 baseline functional tests pass with 100% success rate.

### Execution Commands
```powershell
& "C:\xampp\php\php.exe" tests/order_test.php
& "C:\xampp\php\php.exe" tests/order_crud_test.php
```

---

## 8. Verification Across All Project Test Suites

```
 1. tests/order_test.php        -> 22 / 22 PASSED (Phase 7: Order Lifecycle & Integrity)
 2. tests/category_test.php     -> 16 / 16 PASSED (Phase 7: Category Management CRUD)
 3. tests/product_test.php      -> 20 / 20 PASSED (Phase 7: Product CRUD)
 4. tests/customer_test.php     -> 16 / 16 PASSED (Phase 7: Customer CRUD)
 5. tests/security_test.php     -> 22 / 22 PASSED (Phase 7: Application Security & OWASP)
 6. tests/integration_test.php  -> 15 / 15 PASSED (Phase 7: End-to-End Integration)
 7. tests/order_crud_test.php   -> 14 / 14 PASSED (Phase 6: Order CRUD Baseline)
 8. tests/customer_crud_test.php-> 18 / 18 PASSED (Phase 5: Customer CRUD Baseline)
 9. tests/product_crud_test.php -> 23 / 23 PASSED (Phase 4: Product CRUD Baseline)
10. tests/backend_test.php      -> 29 / 29 PASSED (Phase 3: Backend Foundation)
11. tests/endpoints_test.php    -> 12 / 12 PASSED (Phase 3: HTTP Contracts)
---------------------------------------------------------------------------------
TOTAL PASSED AUTOMATED CHECKS:    207 / 207 PASSED (100% SUCCESS)
```

