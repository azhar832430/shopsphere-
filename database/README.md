# ShopSphere – Database Schema & Migrations
> Target DBMS: MySQL 8.0+ / MariaDB 10.4+ (InnoDB Engine, `utf8mb4_unicode_ci`)

---

## 1. Schema Files Overview

- **`shopsphere.sql`**: The baseline relational database schema DDL script with InnoDB storage engine, foreign key constraints, check constraints, and initial seed records.
- **`DEPLOYMENT_DATABASE.sql`**: Canonical production deployment script with native `slug` column in `categories`, foreign keys, and seed datasets.
- **`migrations/`**: Incremental SQL migration scripts:
  - `migrations/migrate_category_slug.sql`: Adds `slug VARCHAR(100) UNIQUE NULL` to `categories` table.
- **`verify_database.py`**: Automated Python database constraint and relational integrity test suite (41/41 automated checks).

---

## 2. Quick Import via phpMyAdmin

1. Open `http://localhost/phpmyadmin/`.
2. Click the **Import** tab.
3. Choose `DEPLOYMENT_DATABASE.sql` (or `shopsphere.sql`).
4. Click **Import** (or **Go**).
5. The database `shopsphere_db` will be created with all 6 tables populated.

---

## 3. Detailed Documentation

For the full data dictionary, entity-relationship (ER) diagram, and indexing strategy, refer to:
👉 [`docs/DATABASE_DOCUMENTATION.md`](../docs/DATABASE_DOCUMENTATION.md)
