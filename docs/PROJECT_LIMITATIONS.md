# ShopSphere – Project Limitations & Future Roadmap
> **Course**: University Web Engineering PBL (SW417)  
> **Project**: ShopSphere – E-Commerce Management System  
> **Document**: Known System Boundaries, Technical Limitations & Post-PBL Engineering Roadmap

---

## 1. Scope Boundary & Academic Context

The **ShopSphere** system was engineered as a university Project-Based Learning (PBL) solution focusing on full-stack web engineering fundamentals:
- Native HTML5, CSS3, and modern Vanilla JavaScript (ES6+).
- Secure backend REST architecture in PHP 8+ with PDO.
- Normalized relational database modeling in MySQL (InnoDB) with ACID transactions.

To satisfy course timeline constraints, avoid unnecessary framework complexity, and maintain a 100% auditable codebase without third-party dependencies, deliberate scoping decisions were made. This document details the technical boundaries of the current implementation and outlines planned enhancements.

---

## 2. Identified Technical & Architectural Limitations

### 2.1. Single Store / Single Tenant Architecture
- **Current State**: The database and administrative UI are structured for a single retail enterprise.
- **Limitation**: The system does not support multi-tenancy (multiple independent merchants sharing a single database via tenant IDs or separate database schemas).
- **Impact**: Suitable for an independent e-commerce brand or store, but cannot serve as a multi-vendor marketplace (such as Amazon or Etsy) without schema refactoring.

---

### 2.2. Local Filesystem Media Storage
- **Current State**: Uploaded product images are saved to the local web server filesystem in `uploads/products/` with UUID-based filenames and `.htaccess` execution restrictions.
- **Limitation**: Media assets are stored on the same server instance as the PHP code rather than an external distributed object store (e.g., Amazon S3, Google Cloud Storage, Cloudflare R2).
- **Impact**: In containerized, ephemeral cloud environments (e.g. Heroku, Render free tier), local file uploads are lost upon container recreation unless persistent volumes or S3 SDKs are configured.

---

### 2.3. Simulated Payment Settlement Gateway
- **Current State**: Orders track payment channels (*Credit Card*, *PayPal*, *Bank Transfer*, *Cash on Delivery*) and payment states (*pending*, *paid*, *refunded*, *failed*).
- **Limitation**: The platform does not integrate live financial payment gateway SDKs (e.g. Stripe Elements, PayPal Smart Buttons, Square API) with cryptographic webhook signatures.
- **Impact**: Payment confirmation and refund state transitions are managed by administrators via the console rather than real-time bank capture.

---

### 2.4. Synchronous Processing & Email Dispatch
- **Current State**: Customer invoice generation and audit logging occur synchronously within the PHP request lifecycle.
- **Limitation**: The system does not utilize an asynchronous message queue (e.g. Redis, RabbitMQ, Celery) or background cron workers for high-volume email dispatch.
- **Impact**: If SMTP or external network calls are added directly to checkout requests, slow mail servers could increase HTTP response latency.

---

### 2.5. Role-Based Access Control (RBAC) Granularity
- **Current State**: The system provides `administrator` and `store_manager` roles stored in the `users` table. Authentication protects all mutating administrative routes.
- **Limitation**: Fine-grained, permission-matrix authorization (e.g. "can view customers but cannot view order financials") is not yet implemented.
- **Impact**: Any authenticated staff member has broad oversight across catalog, orders, and customer modules.

---

### 2.6. Third-Party CDN Dependencies for Styling & Charts
- **Current State**: The application links to external CDNs for:
  - FontAwesome icons (`cdnjs.cloudflare.com`)
  - Google Fonts (`fonts.googleapis.com`)
  - Chart.js (`cdn.jsdelivr.net`)
- **Limitation**: In strictly offline environments (zero internet connection), icons may not render and Chart.js will log a graceful warning without rendering canvas charts.
- **Impact**: While all core CRUD functionality, forms, tables, and APIs operate 100% offline via local code, optimal visual fidelity requires internet connectivity for CDN fonts and charts.

---

## 3. Post-PBL Engineering Roadmap

Future releases of ShopSphere can extend the current solid architectural foundation with the following enterprise enhancements:

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                           SHOPSPHERE FUTURE ROADMAP                         │
├─────────────────────────────────────────────────────────────────────────────┤
│ Milestone 1: Distributed Media Storage                                      │
│ - Integrate AWS S3 / Cloudflare R2 adapter for cloud image storage.         │
│ - Image compression and thumbnail generation via WebP/AVIF pipeline.        │
├─────────────────────────────────────────────────────────────────────────────┤
│ Milestone 2: Live Payment Gateway Integration                               │
│ - Integrate Stripe API for tokenized customer payments.                     │
│ - Implement Stripe Webhook receiver for automated payment status callbacks. │
├─────────────────────────────────────────────────────────────────────────────┤
│ Milestone 3: Customer-Facing Storefront & Checkout                          │
│ - Develop an external customer shopping catalog and digital shopping cart.  │
│ - Allow customers to view real-time order tracking and invoice downloads.   │
├─────────────────────────────────────────────────────────────────────────────┤
│ Milestone 4: Asynchronous Queues & Automated Notification                   │
│ - Integrate lightweight SQLite/Redis background task runner.                │
│ - Automated PDF invoice emailing and SMS shipping alerts via Twilio/SendGrid│
├─────────────────────────────────────────────────────────────────────────────┤
│ Milestone 5: Fine-Grained Permissions & Audit Trail                         │
│ - Add permission matrix (`permissions` & `role_permissions` tables).        │
│ - Detailed administrative activity logging table for security compliance.   │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## 4. Academic Integrity & Honest Disclosure

In accordance with academic standards for the Web Engineering PBL course, all features described in [`PROJECT_OVERVIEW.md`](file:///c:/Shopsphere/PROJECT_OVERVIEW.md), [`SYSTEM_ARCHITECTURE.md`](file:///c:/Shopsphere/SYSTEM_ARCHITECTURE.md), [`USER_MANUAL.md`](file:///c:/Shopsphere/USER_MANUAL.md), and [`TESTING_REPORT.md`](file:///c:/Shopsphere/TESTING_REPORT.md) correspond strictly to implemented and verified capabilities. No mock or planned features are represented as functional.

