# ShopSphere – REST API Technical Documentation
> **Phase 3: Backend Foundation & Administrative Authentication**  
> **Target Environment**: PHP 8+, MySQL 8.0+ / MariaDB 10.4+ (XAMPP Default)

---

## 1. Overview & Protocol Specification

The **ShopSphere API** provides a secure, lightweight REST-inspired JSON backend designed for the ShopSphere administrative console. Built on modern PHP 8 and native PDO prepared statements, the architecture avoids heavy external dependencies while guaranteeing enterprise-grade security.

### Core Protocols:
- **Base Endpoint**: `http://localhost/shopsphere/api/` (or relative `api/`)
- **Data Exchange**: JSON (`Content-Type: application/json; charset=UTF-8`)
- **Authentication**: Stateful, hardened HTTP-only Session Cookies (`SHOPSPHERE_SESSID`)
- **CSRF Defense**: Cryptographic double-submit / session-bound token via `X-CSRF-Token` header for state-changing requests (`POST`, `PUT`, `DELETE`).
- **Database Driver**: PHP Data Objects (`PDO`) with emulated prepares disabled (`PDO::ATTR_EMULATE_PREPARES => false`).

---

## 2. Standardized Response Envelopes

All API endpoints strictly emit a consistent JSON envelope with appropriate HTTP status codes:

### Success Response (`200 OK` / `201 Created`)
```json
{
  "success": true,
  "message": "Operation completed successfully.",
  "data": { ... }
}
```

### Error Response (`400`, `401`, `403`, `404`, `405`, `500`)
```json
{
  "success": false,
  "message": "A human-readable explanation of what failed.",
  "errors": {
    "field_name": "Specific validation or error detail"
  }
}
```

---

## 3. Endpoints Reference

### 3.1. System Health Check
Verify backend PHP availability, server time, and live MySQL database connectivity.

- **Route**: `GET /api/health.php`
- **Authentication**: None (Public)
- **Rate Limit**: Unrestricted

#### Success Response (`200 OK`)
```json
{
  "success": true,
  "message": "ShopSphere backend is operational and database connection is healthy.",
  "data": {
    "status": "healthy",
    "php_version": "8.2.12",
    "database": "connected",
    "server_time": "2026-11-11 12:30:00 UTC"
  }
}
```

#### Degraded Response (`503 Service Unavailable`)
```json
{
  "success": false,
  "message": "Service degraded: Database connection unavailable.",
  "errors": {
    "database": "disconnected"
  }
}
```

---

### 3.2. Administrator Login
Authenticates an administrator against the `users` table via `password_verify()`. On success, regenerates the session ID to prevent session fixation and returns an active CSRF token.

- **Route**: `POST /api/auth/login.php`
- **Authentication**: None (Public)
- **Supported Identifiers**: Username or Email address

#### Request Body
```json
{
  "username_or_email": "admin@shopsphere.io",
  "password": "Admin@123"
}
```

#### Success Response (`200 OK`)
```json
{
  "success": true,
  "message": "Authentication successful. Welcome to ShopSphere.",
  "data": {
    "user": {
      "id": 1,
      "username": "admin",
      "email": "admin@shopsphere.io",
      "full_name": "Alexander Wright",
      "role": "administrator",
      "status": "active"
    },
    "csrf_token": "a1b2c3d4e5f60718293a4b5c6d7e8f90a1b2c3d4e5f60718293a4b5c6d7e8f90"
  }
}
```
*Response Header includes: `Set-Cookie: SHOPSPHERE_SESSID=...; Path=/; HttpOnly; SameSite=Lax`*

#### Failure Response (`401 Unauthorized`)
```json
{
  "success": false,
  "message": "Invalid username/email or password.",
  "errors": {
    "auth": "Invalid credentials"
  }
}
```

#### Inactive Account (`403 Forbidden`)
```json
{
  "success": false,
  "message": "Your administrative account has been deactivated or suspended. Please contact support.",
  "errors": {
    "permission": "Access denied"
  }
}
```

---

### 3.3. Current Authenticated Profile
Retrieves the logged-in administrator's profile from the session and confirms the account is still active in the database.

- **Route**: `GET /api/auth/me.php`
- **Authentication**: Required (`SHOPSPHERE_SESSID` session cookie)
- **Headers Required**:
  ```http
  Cookie: SHOPSPHERE_SESSID=<session_id>
  ```

#### Success Response (`200 OK`)
```json
{
  "success": true,
  "message": "Authenticated user profile retrieved.",
  "data": {
    "user": {
      "id": 1,
      "username": "admin",
      "email": "admin@shopsphere.io",
      "full_name": "Alexander Wright",
      "role": "administrator",
      "status": "active"
    },
    "csrf_token": "a1b2c3d4e5f60718293a4b5c6d7e8f90a1b2c3d4e5f60718293a4b5c6d7e8f90"
  }
}
```

#### Unauthenticated Response (`401 Unauthorized`)
```json
{
  "success": false,
  "message": "Authentication required. You must be signed in to access this administrative resource.",
  "errors": {
    "auth": "Unauthenticated"
  }
}
```

---

### 3.4. Administrator Logout
Securely destroys the PHP session, expires the session cookie in the client's browser, and clears all session state.

- **Route**: `POST /api/auth/logout.php`
- **Authentication**: Required (`SHOPSPHERE_SESSID`)
- **Headers Required**:
  ```http
  Cookie: SHOPSPHERE_SESSID=<session_id>
  X-CSRF-Token: <active_csrf_token>
  ```

#### Success Response (`200 OK`)
```json
{
  "success": true,
  "message": "You have been securely signed out of ShopSphere.",
  "data": {}
}
```

---

### 3.5. Product Catalog Management (Phase 4)

#### A. List Products
- **Route**: `GET /api/products/index.php`
- **Query Params**: `page`, `limit`, `search`, `category_id`, `stock_status`, `sort`, `order`
- **Response**: List of items, pagination metadata, and active categories list.

#### B. Show Product
- **Route**: `GET /api/products/show.php?id=<id>`
- **Response**: Full specifications of single product with category name.

#### C. Create Product
- **Route**: `POST /api/products/store.php`
- **Auth / CSRF**: Required
- **Body**: JSON or `multipart/form-data` with `name`, `sku`, `price`, `stock`, `category_id`, `description`, `status`, `image` or `image_file`.
- **Response**: `201 Created` with created product.

#### D. Update Product
- **Route**: `POST /api/products/update.php?id=<id>`
- **Auth / CSRF**: Required
- **Body**: JSON or `multipart/form-data` with updated fields.
- **Response**: `200 OK` with updated product.

#### E. Delete / Archive Product
- **Route**: `POST /api/products/delete.php`
- **Auth / CSRF**: Required
- **Body**: `{"id": <id>}`
- **Referential Integrity**: Hard-deletes if unreferenced; archives (`status = 'inactive'`) if historical orders exist in `order_items`.
- **Response**: `200 OK` with `action: "deleted"` or `action: "archived"`.

#### F. Product Telemetry & Stats
- **Route**: `GET /api/products/stats.php`
- **Response**: `200 OK` with total, active, low stock, out of stock, average price, and top low stock alerts.

---

### 3.6. Customer Directory Management (Phase 5)

#### A. List Customers
- **Route**: `GET /api/customers/index.php`
- **Query Params**: `page`, `limit`, `search`, `city`, `status`, `sort`, `order`
- **Response**: List of customers with order counts and lifetime spend, pagination metadata, distinct cities, and account status summary.

#### B. Show Customer Dossier
- **Route**: `GET /api/customers/show.php?id=<id>`
- **Response**: Full customer profile specifications, lifetime spending metrics, and recent order history.

#### C. Create Customer
- **Route**: `POST /api/customers/store.php`
- **Auth / CSRF**: Required
- **Body**: JSON with `full_name`, `email`, `phone`, `address`, `city`, `postal_code`, `status`.
- **Response**: `201 Created` with created customer.

#### D. Update Customer
- **Route**: `POST /api/customers/update.php?id=<id>`
- **Auth / CSRF**: Required
- **Body**: JSON with updated customer fields.
- **Email Conflict Logic**: Verifies `WHERE email = :email AND id != :id` so customer can retain their current email address.
- **Response**: `200 OK` with updated customer.

#### E. Delete / Archive Customer
- **Route**: `POST /api/customers/delete.php`
- **Auth / CSRF**: Required
- **Body**: `{"id": <id>}`
- **Referential Integrity**: Hard-deletes if customer has 0 orders; archives (`status = 'inactive'`) if historical orders exist in `orders`.
- **Response**: `200 OK` with `action: "deleted"` or `action: "archived"`.

#### F. Customer Telemetry & Stats
- **Route**: `GET /api/customers/stats.php`
- **Response**: `200 OK` with total, active, inactive, blocked, new customers (last 30 days), and top spenders.

---

### 3.7. Order Management & Transactions (Phase 6)

#### A. List Orders
- **Route**: `GET /api/orders/index.php`
- **Query Params**: `page`, `limit`, `search`, `order_status`, `payment_status`, `date_from`, `date_to`, `sort`, `order`
- **Response**: List of orders with customer name, item count, totals, pagination metadata, and status distribution summary.

#### B. Show Order Details & Invoice
- **Route**: `GET /api/orders/show.php?id=<id>` (or `?order_number=<ORD-XXXX>`)
- **Response**: Complete order header, customer dossier, and itemized lines with product names, SKUs, quantities, and prices.

#### C. Create Order
- **Route**: `POST /api/orders/store.php`
- **Auth / CSRF**: Required
- **Body**: JSON with `customer_id`, `items` array (`product_id`, `quantity`), `payment_method`, optional `payment_status`, `notes`.
- **Status Guard (Task 1 Fix)**: Every newly created order **strictly starts in `pending` status**. Initial requests specifying `order_status: cancelled` or `delivered` are rejected with HTTP 422. Initial payment status cannot be `refunded`.
- **Stock Management**: Transaction locks product rows (`FOR UPDATE`), checks availability, inserts order and line items atomically, and deducts inventory stock with defensive bound verification (`AND stock >= :quantity`).
- **Response**: `201 Created` with created order and item details.

#### D. Update Order Status
- **Route**: `POST /api/orders/update.php?id=<id>`
- **Auth / CSRF**: Required
- **Body**: JSON with `order_status`, `payment_status`, `notes`.
- **Guards**: Delivered orders cannot be cancelled or reverted. Cancelled orders are final. Cancelling restores stock.
- **Refund Guard (Task 2 Fix)**: When transitioning an order to `cancelled`:
  - If the order was previously paid (or the client attempts to set `payment_status: paid`), the server enforces `payment_status = 'refunded'`.
  - If the order was unpaid (`pending` or `failed`), it retains its unpaid status and is not marked `refunded`.
- **Response**: `200 OK` with updated order.

#### E. Cancel Order
- **Route**: `POST /api/orders/cancel.php`
- **Auth / CSRF**: Required
- **Body**: `{"id": <id>}`
- **Stock Management**: Restores inventory stock for all order items atomically exactly once; refunds payment if paid (retains unpaid status if not paid); preserves order row.
- **Response**: `200 OK` with restored units count.

#### F. Orders Telemetry & Financial Stats
- **Route**: `GET /api/orders/stats.php`
- **Response**: `200 OK` with total orders, counts by status, total revenue (excluding cancelled orders), average order value, status distribution, and 5 latest recent orders.

---

### 3.8. Category Management Endpoints

The Category Management endpoints provide complete CRUD capability for department taxonomies with live product counts and relational decoupling safeguards.

#### A. List Categories
- **Route**: `GET /api/categories/index.php`
- **Authentication**: Public (Read-only)
- **Query Params**:
  - `page` *(int, default: 1)*
  - `limit` *(int, default: 10)*
  - `search` *(string, optional)*: Matches `name`, `slug`, or `description`.
  - `status` *(string, optional)*: Filter by `active` or `inactive`.
  - `sort` *(string, default: 'id')*: Allowlisted (`id`, `name`, `slug`, `status`, `product_count`, `created_at`).
  - `order` *(string, default: 'ASC')*: `ASC` or `DESC`.
- **Response**: Paginated list of categories with dynamic `product_count` (`COUNT(p.id)`), pagination meta, and distribution summary.

#### B. Show Category Details & Assigned Products
- **Route**: `GET /api/categories/show.php?id=<id>` (or `?slug=<slug>`)
- **Authentication**: Public (Read-only)
- **Response**: Complete category details plus an array of all active products assigned to this category.

#### C. Create Category
- **Route**: `POST /api/categories/store.php`
- **Auth / CSRF**: Required
- **Body**:
```json
{
  "name": "Smart Home & IoT",
  "slug": "smart-home-iot",
  "description": "Connected devices and sensors.",
  "status": "active"
}
```
- **Validation**: Requires `name` (unique). If `slug` is omitted, auto-generates slug from `name`. Rejects duplicate slugs.
- **Response**: `201 Created` with created category object.

#### D. Update Category
- **Route**: `POST /api/categories/update.php`
- **Auth / CSRF**: Required
- **Body**:
```json
{
  "id": 6,
  "name": "Smart Home & Automation",
  "slug": "smart-home-automation",
  "description": "Updated scope.",
  "status": "active"
}
```
- **Validation**: Self-retention allows keeping same name/slug. Rejects collisions with other categories.
- **Response**: `200 OK` with updated category.

#### E. Safe Delete Category
- **Route**: `POST /api/categories/destroy.php` (Alias: `POST /api/categories/delete.php`)
- **Auth / CSRF**: Required
- **Body**: `{"id": 6}`
- **Relational Safeguard**: Executes inside an atomic transaction. Safely decouples all assigned products by updating their `category_id = NULL` (Uncategorized) before deleting the category record. Products are **never** deleted.
- **Response**: `200 OK` with deleted ID and count of decoupled products.

---

## 4. HTTP Status Code Reference

| Status Code | Meaning | Typical Scenario |
|---|---|---|
| `200 OK` | Request succeeded | Successful GET, valid login, clean logout |
| `400 Bad Request` | Client validation failed | Missing email or password, malformed JSON |
| `401 Unauthorized` | Not authenticated | Incorrect password, missing session cookie |
| `403 Forbidden` | Access denied | Account suspended, CSRF token mismatch |
| `404 Not Found` | Route doesn't exist | Invalid API URL |
| `405 Method Not Allowed` | Wrong HTTP verb | Sending POST to a GET endpoint |
| `500 Server Error` | Unhandled backend fault | General unhandled server exceptions (no leaks) |
| `503 Service Unavailable`| Service degraded | Database connection down in health check |

---

## 5. Postman & cURL Testing Examples

### 1. Health Check
```bash
curl -X GET "http://localhost/shopsphere/api/health.php" -H "Accept: application/json"
```

### 2. Login
```bash
curl -X POST "http://localhost/shopsphere/api/auth/login.php" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -c cookies.txt \
  -d '{"username_or_email": "admin@shopsphere.io", "password": "Admin@123"}'
```

### 3. Check Authenticated User (`Me`)
```bash
curl -X GET "http://localhost/shopsphere/api/auth/me.php" \
  -H "Accept: application/json" \
  -b cookies.txt
```

### 4. Logout (with CSRF Token)
```bash
curl -X POST "http://localhost/shopsphere/api/auth/logout.php" \
  -H "Accept: application/json" \
  -H "X-CSRF-Token: <YOUR_CSRF_TOKEN>" \
  -b cookies.txt
```

