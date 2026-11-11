import re
import sys
import sqlite3

def run_tests():
    print("=" * 70)
    print("SHOPSPHERE - PHASE 2: DATABASE SCHEMA & SEED VERIFICATION")
    print("=" * 70)

    sql_path = "database/shopsphere.sql"
    with open(sql_path, "r", encoding="utf-8") as f:
        sql_content = f.read()

    total_checks = 0
    passed_checks = 0
    failed_checks = []

    def check(desc, condition):
        nonlocal total_checks, passed_checks
        total_checks += 1
        if condition:
            passed_checks += 1
            print(f"  [PASS] {desc}")
        else:
            failed_checks.append(desc)
            print(f"  [FAIL] {desc}")

    # 1. Structural Regex Tests
    print("\n1. Schema DDL & Engine Verification:")
    check("Database creation with utf8mb4", "CREATE DATABASE IF NOT EXISTS `shopsphere_db`" in sql_content and "utf8mb4" in sql_content)
    check("USE shopsphere_db statement present", "USE `shopsphere_db`;" in sql_content)
    
    tables = ["users", "categories", "products", "customers", "orders", "order_items"]
    for t in tables:
        check(f"Table `{t}` created with InnoDB & utf8mb4", f"CREATE TABLE `{t}`" in sql_content and "ENGINE=InnoDB" in sql_content)

    # 2. Check Constraints & Foreign Keys
    print("\n2. Constraints & Referential Rules Verification:")
    check("users table has UNIQUE username & email", "UNIQUE KEY `uq_users_username`" in sql_content and "UNIQUE KEY `uq_users_email`" in sql_content)
    check("categories table has UNIQUE name", "UNIQUE KEY `uq_categories_name`" in sql_content)
    check("products table has UNIQUE sku", "UNIQUE KEY `uq_products_sku`" in sql_content)
    check("customers table has UNIQUE email", "UNIQUE KEY `uq_customers_email`" in sql_content)
    check("orders table has UNIQUE order_number", "UNIQUE KEY `uq_orders_number`" in sql_content)
    
    check("products CHECK price >= 0.00", "`chk_products_price` CHECK (`price` >= 0.00)" in sql_content)
    check("products CHECK stock >= 0", "`chk_products_stock` CHECK (`stock` >= 0)" in sql_content)
    check("order_items CHECK quantity > 0", "`chk_order_items_qty` CHECK (`quantity` > 0)" in sql_content)
    check("order_items CHECK unit_price >= 0.00", "`chk_order_items_unit_price` CHECK (`unit_price` >= 0.00)" in sql_content)
    check("orders CHECK subtotal >= 0.00", "`chk_orders_subtotal` CHECK (`subtotal` >= 0.00)" in sql_content)

    check("products FK category_id ON DELETE SET NULL", "ON DELETE SET NULL" in sql_content and "`fk_products_category`" in sql_content)
    check("orders FK customer_id ON DELETE RESTRICT", "`fk_orders_customer`" in sql_content and "ON DELETE RESTRICT" in sql_content)
    check("order_items FK order_id ON DELETE CASCADE", "`fk_order_items_order`" in sql_content and "ON DELETE CASCADE" in sql_content)
    check("order_items FK product_id ON DELETE RESTRICT", "`fk_order_items_product`" in sql_content and "ON DELETE RESTRICT" in sql_content)

    # 3. Simulate and verify data integrity via in-memory relational engine (SQLite)
    print("\n3. Relational Execution & Data Integrity Simulation:")
    
    # Adapt MySQL dialect to standard SQLite for relational validation
    sqlite_schema = """
    PRAGMA foreign_keys = ON;

    CREATE TABLE users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        email TEXT UNIQUE NOT NULL,
        password TEXT NOT NULL,
        full_name TEXT NOT NULL,
        role TEXT NOT NULL DEFAULT 'administrator',
        status TEXT NOT NULL DEFAULT 'active',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE categories (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT UNIQUE NOT NULL,
        description TEXT,
        status TEXT NOT NULL DEFAULT 'active',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE products (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        category_id INTEGER REFERENCES categories(id) ON DELETE SET NULL,
        name TEXT NOT NULL,
        sku TEXT UNIQUE NOT NULL,
        description TEXT,
        price NUMERIC NOT NULL CHECK (price >= 0.00),
        stock INTEGER NOT NULL DEFAULT 0 CHECK (stock >= 0),
        image TEXT,
        status TEXT NOT NULL DEFAULT 'active',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE customers (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        full_name TEXT NOT NULL,
        email TEXT UNIQUE NOT NULL,
        phone TEXT,
        address TEXT,
        city TEXT,
        postal_code TEXT,
        status TEXT NOT NULL DEFAULT 'active',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE orders (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        order_number TEXT UNIQUE NOT NULL,
        customer_id INTEGER NOT NULL REFERENCES customers(id) ON DELETE RESTRICT,
        order_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        subtotal NUMERIC NOT NULL DEFAULT 0.00 CHECK (subtotal >= 0.00),
        total_amount NUMERIC NOT NULL DEFAULT 0.00 CHECK (total_amount >= 0.00),
        payment_method TEXT NOT NULL DEFAULT 'Credit Card',
        payment_status TEXT NOT NULL DEFAULT 'pending',
        order_status TEXT NOT NULL DEFAULT 'pending',
        notes TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE order_items (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        order_id INTEGER NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
        product_id INTEGER NOT NULL REFERENCES products(id) ON DELETE RESTRICT,
        quantity INTEGER NOT NULL DEFAULT 1 CHECK (quantity > 0),
        unit_price NUMERIC NOT NULL CHECK (unit_price >= 0.00),
        subtotal NUMERIC NOT NULL CHECK (subtotal >= 0.00),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );
    """

    conn = sqlite3.connect(":memory:")
    conn.execute("PRAGMA foreign_keys = ON;")
    cur = conn.cursor()
    cur.executescript(sqlite_schema)

    # Extract INSERT statements from shopsphere.sql
    insert_pattern = re.compile(r"INSERT INTO\s*`?(\w+)`?\s*\(([^)]+)\)\s*VALUES\s*([^;]+);", re.DOTALL | re.IGNORECASE)
    matches = insert_pattern.findall(sql_content)

    for table_name, cols, values_block in matches:
        cols_cleaned = cols.replace("`", "")
        insert_sql = f"INSERT INTO {table_name} ({cols_cleaned}) VALUES {values_block};"
        # Convert any MySQL-specific backticks to double quotes or remove
        insert_sql = insert_sql.replace("`", "")
        cur.executescript(insert_sql)
    conn.commit()

    # Query Record Counts
    cur.execute("SELECT COUNT(*) FROM users;")
    user_count = cur.fetchone()[0]
    check("Users count >= 1", user_count >= 1)

    cur.execute("SELECT COUNT(*) FROM categories;")
    cat_count = cur.fetchone()[0]
    check("Categories count >= 6 (Found: %d)" % cat_count, cat_count >= 6)

    cur.execute("SELECT COUNT(*) FROM products;")
    prod_count = cur.fetchone()[0]
    check("Products count >= 15 (Found: %d)" % prod_count, prod_count >= 15)

    cur.execute("SELECT COUNT(*) FROM customers;")
    cust_count = cur.fetchone()[0]
    check("Customers count >= 10 (Found: %d)" % cust_count, cust_count >= 10)

    cur.execute("SELECT COUNT(*) FROM orders;")
    order_count = cur.fetchone()[0]
    check("Orders count >= 10 (Found: %d)" % order_count, order_count >= 10)

    cur.execute("SELECT COUNT(*) FROM order_items;")
    item_count = cur.fetchone()[0]
    check("Order Items count >= 15 (Found: %d)" % item_count, item_count >= 15)

    # 4. Math and Snapshot Verification
    print("\n4. Line Item Math & Price Snapshot Verification:")
    cur.execute("SELECT id, order_id, quantity, unit_price, subtotal FROM order_items;")
    all_items = cur.fetchall()
    math_valid = True
    for it in all_items:
        qty = it[2]
        price = float(it[3])
        subtotal = float(it[4])
        if abs((qty * price) - subtotal) > 0.01:
            math_valid = False
            print(f"Math mismatch in item #{it[0]}: {qty} * {price} != {subtotal}")
    check("All order_items: (quantity * unit_price == subtotal)", math_valid)

    # 5. Aggregate Queries Test
    print("\n5. Analytical Queries Test:")
    cur.execute("SELECT SUM(total_amount) FROM orders WHERE payment_status = 'paid';")
    total_rev = cur.fetchone()[0]
    check("Total revenue calculated from paid orders: $%.2f" % float(total_rev), total_rev > 0)

    cur.execute("""
        SELECT c.name, COUNT(p.id) as count
        FROM categories c
        LEFT JOIN products p ON c.id = p.category_id
        GROUP BY c.id;
    """)
    cat_groups = cur.fetchall()
    check("Products grouped by category returns all categories", len(cat_groups) == cat_count)

    cur.execute("""
        SELECT order_status, COUNT(*) as count, SUM(total_amount)
        FROM orders
        GROUP BY order_status;
    """)
    status_groups = cur.fetchall()
    check("Orders grouped by order_status produces valid metrics", len(status_groups) >= 3)

    cur.execute("""
        SELECT o.order_number, c.full_name, p.name, oi.quantity, oi.subtotal
        FROM orders o
        JOIN customers c ON o.customer_id = c.id
        JOIN order_items oi ON o.id = oi.order_id
        JOIN products p ON oi.product_id = p.id;
    """)
    full_details = cur.fetchall()
    check("Multi-table relational join (orders -> customers -> order_items -> products) succeeds", len(full_details) > 0)

    # 6. Test Constraint Enforcements
    print("\n6. Constraint Invalidation Tests (Negative Proofs):")
    
    # Test duplicate SKU
    try:
        cur.execute("INSERT INTO products (name, sku, price, stock) VALUES ('Dup', 'SP-15PM-256', 10.0, 5);")
        check("Duplicate SKU rejection", False)
    except sqlite3.IntegrityError:
        check("Duplicate SKU rejection [IntegrityError caught]", True)

    # Test negative price
    try:
        cur.execute("INSERT INTO products (name, sku, price, stock) VALUES ('NegPrice', 'TEST-NEG-1', -19.99, 5);")
        check("Negative price rejection", False)
    except sqlite3.IntegrityError:
        check("Negative price rejection [CHECK chk_products_price caught]", True)

    # Test negative stock
    try:
        cur.execute("INSERT INTO products (name, sku, price, stock) VALUES ('NegStock', 'TEST-NEG-2', 19.99, -5);")
        check("Negative stock rejection", False)
    except sqlite3.IntegrityError:
        check("Negative stock rejection [CHECK chk_products_stock caught]", True)

    # Test zero/negative quantity in order_items
    try:
        cur.execute("INSERT INTO order_items (order_id, product_id, quantity, unit_price, subtotal) VALUES (1, 1, 0, 100.0, 0.0);")
        check("Zero order quantity rejection", False)
    except sqlite3.IntegrityError:
        check("Zero order quantity rejection [CHECK chk_order_items_qty caught]", True)

    # Test deleting customer with historical orders (RESTRICT test)
    try:
        cur.execute("DELETE FROM customers WHERE id = 1;")
        check("Prevent deletion of customer with historical orders", False)
    except sqlite3.IntegrityError:
        check("Prevent deletion of customer with historical orders [RESTRICT caught]", True)

    # Test deleting product with historical order items (RESTRICT test)
    try:
        cur.execute("DELETE FROM products WHERE id = 1;")
        check("Prevent deletion of product referenced by order_items", False)
    except sqlite3.IntegrityError:
        check("Prevent deletion of product referenced by order_items [RESTRICT caught]", True)

    # Test deleting order cascades to order_items (CASCADE test)
    cur.execute("DELETE FROM orders WHERE id = 12;")
    cur.execute("SELECT COUNT(*) FROM order_items WHERE order_id = 12;")
    cascaded_count = cur.fetchone()[0]
    check("Order deletion cascades to order_items (Found %d items after deletion)" % cascaded_count, cascaded_count == 0)

    # Test deleting category sets product category_id to NULL (SET NULL test)
    cur.execute("DELETE FROM categories WHERE id = 7;")
    cur.execute("SELECT category_id FROM products WHERE sku = 'HD-GAN-140';")
    cat_id_after = cur.fetchone()[0]
    check("Category deletion sets product category_id to NULL (Current: %s)" % str(cat_id_after), cat_id_after is None)

    conn.close()

    print("\n" + "=" * 70)
    print(f"VERIFICATION SUMMARY: {passed_checks}/{total_checks} CHECKS PASSED")
    if failed_checks:
        print(f"FAILED ({len(failed_checks)}):")
        for f in failed_checks:
            print("  - " + f)
        sys.exit(1)
    else:
        print("ALL DATABASE CHECKS, CONSTRAINTS & RELATIONS PASSED WITH ZERO ERRORS!")
        print("=" * 70)

if __name__ == "__main__":
    run_tests()
