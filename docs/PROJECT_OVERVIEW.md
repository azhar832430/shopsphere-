# ShopSphere – Project Overview & Academic Specification
> **Course**: University Web Engineering PBL (SW417)  
> **Project Title**: ShopSphere – E-Commerce Management System  
> **Phase 8: Final University Submission & Project Handbook**  
> **Author**: Senior Full-Stack Engineering Team / Student Developer

---

## 1. Executive Summary

**ShopSphere** is a high-performance, commercial-grade administrative platform designed for managing modern retail e-commerce operations. Built specifically for an advanced university Web Engineering Project-Based Learning (PBL) curriculum, ShopSphere bridges enterprise software architectural patterns with clean, lightweight native web technologies:

- **Frontend**: Pure semantic **HTML5**, modular modern **CSS3** (with design tokens, CSS variables, and flex/grid responsive layouts), and modular **Vanilla JavaScript (ES6+)**.
- **Backend**: Native **PHP 8+** utilizing modern **PHP Data Objects (PDO)** with prepared statements and strict error modes.
- **Database**: Fully normalized, relational **MySQL 8.0+ / MariaDB 10.4+** storage engine running on **InnoDB** with strict ACID transactions, row-level locks, foreign key constraints, and check constraints.
- **Protocol**: REST-inspired JSON communication with stateful HTTP-only session cookies and cryptographic double-submit CSRF defenses.

The system delivers a unified administrative cockpit for managing products, taxonomies, customer dossiers, order processing, and real-time revenue telemetry without relying on heavy external frontend frameworks (e.g. React/Vue) or bloated backend frameworks (e.g. Laravel).

---

## 2. Problem Statement

Small-to-medium enterprise (SME) e-commerce businesses face significant challenges when managing multi-channel operations:
1. **Inventory Race Conditions**: Concurrent checkouts and simultaneous administrative edits often cause overselling and negative inventory balances.
2. **Disconnected Fulfillment Lifecycles**: Without rigid order state machines, orders can be prematurely cancelled after delivery or marked refunded without restoring physical inventory.
3. **Data Inconsistency & Orphaned Records**: Relational databases lacking strict cascade/restrict rules accumulate orphaned line items and broken references when categories or customers are modified or deleted.
4. **Security Vulnerabilities in Custom Portals**: Administrative backends frequently suffer from OWASP Top 10 vulnerabilities, including SQL injection, stored Cross-Site Scripting (XSS), Cross-Site Request Forgery (CSRF), and insecure direct object access.
5. **Over-Engineering & Framework Bloat**: Modern web projects often introduce massive node dependency trees (200MB+ `node_modules`), making auditing, local execution, and academic grading brittle and error-prone.

ShopSphere directly solves these industry pain points by implementing an enterprise-grade architecture using native, standardized, and auditable web standards.

---

## 3. Project Objectives

The primary engineering objectives of the ShopSphere project include:

1. **Enterprise-Grade UI/UX**: Deliver a sleek, responsive SaaS dashboard featuring dark/light theme support, accessible semantic markup, animated notifications, and interactive modals.
2. **Complete Lifecycle CRUD Modules**:
   - **Product Catalog Management**: Multi-attribute management with SKU enforcement, stock tracking, and secure media file uploads.
   - **Category Taxonomy Management**: Department hierarchy with auto-generated URL slugs, active product counters, and safe decoupling safeguards.
   - **Customer Directory (CRM)**: Complete dossiers tracking historical lifetime spend, total orders placed, and regional filtering.
   - **Order Processing & Invoicing**: Dynamic multi-item line repeater, live tax and shipping calculations, printable invoices, and transactional fulfillment tracking.
3. **Transactional Inventory Integrity**: Guarantee atomic stock deduction on order creation and exactly-once stock restoration on cancellation via database transactions and row-level locking (`SELECT ... FOR UPDATE`).
4. **Resilient Security Posture**: Enforce defense-in-depth across every layer:
   - Server-side parameter validation.
   - 100% parameterized PDO prepared statements eliminating SQL injection.
   - Output escaping via `UI.escapeHtml()` preventing XSS.
   - Cryptographic session-bound tokens preventing CSRF.
   - Restrictive `.htaccess` rules denying direct access to internal scripts and configuration.
5. **Comprehensive Verification & Testing**: Provide an automated verification test suite with 100% coverage across all CRUD operations, edge cases, and security controls.

---

## 4. User Personas & Target Roles

| Persona | Role | Key Workflow & Capabilities |
|---|---|---|
| **Store Owner / Super Administrator** | `administrator` | Full system access: reviews dashboard telemetry, updates company profile and tax rates, manages all products, categories, customers, and orders. |
| **Warehouse / Inventory Manager** | `store_manager` | Monitors low-stock alerts, updates inventory quantities, adjusts catalog pricing, and processes fulfillment transitions (`processing` &rarr; `shipped` &rarr; `delivered`). |
| **Customer Support Representative** | `support` | Inspects customer purchase histories, retrieves printable invoices, updates shipping addresses, and executes safe order cancellations with stock restoration. |

---

## 5. High-Level System Modules & Capabilities

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                           SHOPSPHERE ARCHITECTURE                           │
├─────────────────────────────────────────────────────────────────────────────┤
│ 1. Administrative Portal (index.html)                                      │
│    - Session login, Bcrypt credential verification, CSRF token generation    │
├─────────────────────────────────────────────────────────────────────────────┤
│ 2. Executive Analytics Dashboard (dashboard.html)                          │
│    - Total revenue, orders, active customers, catalog counters              │
│    - Low-stock inventory alert feed with quick-restock triggers             │
│    - Real-time sales trend area chart & order status breakdown doughnut     │
├─────────────────────────────────────────────────────────────────────────────┤
│ 3. Product Catalog Management (products.html)                               │
│    - Server-side paginated grid with instant search and category filtering   │
│    - SKU uniqueness validation and pricing bounds checks                    │
│    - Secure multipart image upload or URL fallback                          │
│    - Safe delete with archive fallback for ordered products                 │
├─────────────────────────────────────────────────────────────────────────────┤
│ 4. Taxonomy & Department Management (categories.html)                      │
│    - Real-time assigned product count calculation                           │
│    - Automated slug generation and collision prevention                     │
│    - Transaction-safe deletion decoupling products to 'Uncategorized'       │
├─────────────────────────────────────────────────────────────────────────────┤
│ 5. Customer Directory & CRM (customers.html)                               │
│    - Multi-attribute customer dossiers with order count and spend metrics   │
│    - Regional city and account status filters                               │
│    - Safe deletion preventing deletion of customers with order history      │
├─────────────────────────────────────────────────────────────────────────────┤
│ 6. Order Fulfillment & Invoicing (orders.html)                              │
│    - Interactive line-item builder with live subtotal/tax/shipping math     │
│    - Atomic stock reservation and row-level locking                         │
│    - Printable commercial invoice view with customer addresses and taxes    │
│    - Status transitions and guaranteed payment refund consistency           │
├─────────────────────────────────────────────────────────────────────────────┤
│ 7. System Configuration & Sandbox (settings.html)                          │
│    - Store name, currency, tax rate, shipping threshold preferences         │
│    - Complete JSON backup export and demo sandbox factory reset             │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## 6. Functional & Non-Functional Requirements Matrix

### Functional Requirements (FR)
- **FR-01 (Authentication)**: The system must authenticate administrative users via username/email and Bcrypt-hashed password.
- **FR-02 (Catalog Management)**: The system must support creating, reading, updating, and deleting products with image upload support.
- **FR-03 (Taxonomy Management)**: The system must support categories with unique names, URL-safe slugs, and dynamic product counts.
- **FR-04 (Customer Directory)**: The system must maintain customer profiles with unique emails, phone numbers, and calculate total historical spending.
- **FR-05 (Multi-Item Orders)**: The system must allow placing orders containing multiple products, quantities, dynamic subtotals, and auto-generated order numbers.
- **FR-06 (Transactional Stock Reservation)**: Product stock must be automatically and atomically deducted when an order is created.
- **FR-07 (Stock Restoration on Cancellation)**: When an order is cancelled, stock must be restored exactly once, and paid orders must be automatically marked refunded.
- **FR-08 (Reporting & Telemetry)**: The dashboard must compute total sales, monthly orders, average order values, and low-stock warnings from live database records.

### Non-Functional Requirements (NFR)
- **NFR-01 (Performance)**: All API endpoints must execute database queries in under 50ms under typical local conditions.
- **NFR-02 (Security)**: All database queries must use PDO prepared statements; all HTML output must be escaped; all state-changing endpoints must require CSRF validation.
- **NFR-03 (Reliability & ACID Compliance)**: All multi-table mutations (orders, order items, stock updates) must execute inside ACID transactions with automatic rollback on error.
- **NFR-04 (Portability)**: The project must run without external compilation, bundlers, or third-party package managers on standard XAMPP (Apache + PHP 8 + MySQL).
- **NFR-05 (Accessibility & Usability)**: The frontend must adapt seamlessly across desktop (1920px), laptop (1366px), tablet (768px), and mobile (375px) viewports.

---

## 7. Submission Context & Verification Status

ShopSphere has successfully fulfilled all technical, architectural, and verification requirements across all 8 development phases:
- **Phase 1**: Complete Frontend UI/UX Architecture.
- **Phase 2**: Complete Normalized MySQL Database (InnoDB).
- **Phase 3**: PHP 8 Backend Foundation & Administrative Authentication.
- **Phase 4**: Database-Connected Product CRUD.
- **Phase 5**: Database-Connected Customer CRUD.
- **Phase 6**: Database-Connected Order CRUD & Financial Invoicing.
- **Phase 7**: Comprehensive Testing, Security Hardening & Regression Suite (207/207 Automated Tests Passed).
- **Phase 8**: Production Readiness, XAMPP Deployment, Free Hosting Evaluation, and Final University Submission Package.

