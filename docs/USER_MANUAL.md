# ShopSphere – Administrative Console User Manual
> **Course**: University Web Engineering PBL (SW417)  
> **Project**: ShopSphere – E-Commerce Management System  
> **Target Audience**: Store Administrators, Inventory Managers, Customer Support Agents

---

## 1. Introduction & Getting Started

Welcome to **ShopSphere**, a modern SaaS-style administrative control center for e-commerce store operations. This manual provides step-by-step guidance on managing products, taxonomies, customer accounts, order processing, and system preferences.

### System Access
1. Open your web browser and navigate to:  
   `http://localhost/ShopSphere/` *(or your production URL).*
2. On the authentication portal (`index.html`), enter your administrative credentials:
   - **Email / Username**: `admin@shopsphere.io` *(or `admin`)*
   - **Password**: `Admin@123`
3. Click **Sign In**. On successful authentication, you will be redirected to the **Executive Analytics Dashboard** (`dashboard.html`).

---

## 2. Navigating the Administrative Console

The console is organized into a persistent collapsible sidebar and a universal top header:

- **Brand Logo & Home**: Clicking the **ShopSphere** logo returns you to the Dashboard.
- **Sidebar Menu**: Quick access to **Dashboard**, **Products**, **Categories**, **Customers**, **Orders**, and **Settings**.
- **Sidebar Collapse Toggle**: Click the collapse button on the top-left to toggle between full and compact icon-only sidebar views.
- **Global Search**: Press `Ctrl + K` (or `Cmd + K`) to open the instant search bar to find products, orders, or customers.
- **Theme Toggle**: Click the Sun/Moon icon in the top header to toggle between **Light Mode** and **Dark Mode**. Your preference is automatically remembered.
- **User Profile Menu**: Click the administrator avatar in the top-right to view account information or click **Sign Out** to securely terminate your session.

---

## 3. Executive Analytics Dashboard (`dashboard.html`)

The dashboard provides real-time telemetry aggregated directly from the database:

1. **KPI Metric Cards**:
   - **Total Revenue**: Sum of all non-cancelled paid transactions.
   - **Total Orders**: Count of all processed sales transactions.
   - **Total Customers**: Active CRM accounts in the directory.
   - **Low Stock Alerts**: Count of catalog items whose inventory is at or below the warning threshold (default: &le; 10 units).
2. **Interactive Visualizations**:
   - **Sales Trend Line Chart**: Compares current revenue trends against the previous period.
   - **Fulfillment Status Doughnut**: Color-coded breakdown of orders by status (*Delivered*, *Shipped*, *Processing*, *Pending*, *Cancelled*).
3. **Low Stock Inventory Center**: Displays products needing replenishment. Click **Restock** on any row to open the quick-inventory adjustment modal.
4. **Recent Orders Feed**: Displays the latest five customer orders with instant fulfillment status badges and direct links to view invoices.

---

## 4. Product Catalog Management (`products.html`)

The Product Catalog interface manages item specifications, stock, and media assets.

### 4.1. Browsing and Filtering Products
- **Search Bar**: Type any product name, SKU, or keyword to filter the catalog in real time.
- **Category Filter**: Select a department from the dropdown to view only items in that category.
- **Stock Status Filter**: Filter by `In Stock`, `Low Stock (&le; 10)`, or `Out of Stock`.
- **Sorting**: Sort by Product Name, Price (Low to High / High to Low), or Stock Quantity.

### 4.2. Adding a New Product
1. Click the **Add Product** button in the top-right header.
2. In the modal dialog, fill in:
   - **Product Name**: Commercial title (e.g., *SpherePhone 15 Pro Max*).
   - **SKU**: Unique Stock Keeping Unit code (e.g., *SP-15PM-256*). Must be unique across the catalog.
   - **Category**: Assign to an existing department.
   - **Retail Price**: Unit sales price (e.g., *1199.00*). Must be &ge; 0.
   - **Initial Stock**: Physical inventory units available. Must be &ge; 0.
   - **Product Image**: Choose either:
     - **File Upload**: Select a local image file (`.jpg`, `.png`, `.webp`, `.gif`, max 5MB).
     - **Image URL**: Enter an external HTTPS image URL.
   - **Status**: Set to `Active` (visible) or `Inactive` (hidden from store).
3. Click **Save Product**. An animated toast notification will confirm creation.

### 4.3. Editing & Deleting Products
- **Edit**: Click the Pencil icon on any product row. Update the fields and click **Update Product**.
- **Delete**: Click the Trash icon. 
  - If the product has never been ordered, it is safely removed from the database.
  - If the product is referenced in historical orders, ShopSphere prevents orphaned line items by **archiving** the product (`status = 'inactive'`), preserving historical sales records and invoice accuracy.

---

## 5. Category Taxonomy Management (`categories.html`)

Categories organize the catalog into structured commercial departments.

### 5.1. Department Telemetry
Each category card/row displays:
- **Department Name** (e.g. *Audio & Headphones*).
- **URL Slug** (e.g. *audio-headphones*).
- **Active Product Count**: Live count of active items currently assigned to this department.
- **Status Badge**: `Active` or `Inactive`.

### 5.2. Creating & Updating Categories
1. Click **Add Category**.
2. Enter the **Department Name** (e.g., *Smart Home & IoT*).
3. *(Optional)* Enter a custom **Slug**. If left empty, ShopSphere automatically generates a URL-safe slug from the name.
4. Enter an informative **Description**.
5. Click **Save Category**.

### 5.3. Safe Category Deletion Safeguard
When deleting a category:
- ShopSphere executes an atomic database transaction.
- All products assigned to the deleted category have their `category_id` updated to `NULL` (*Uncategorized*).
- **No catalog products are ever deleted when removing a category**.

---

## 6. Customer Directory & CRM (`customers.html`)

Manage customer profiles, contact info, and lifetime purchase history.

### 6.1. Customer Insights & Dossiers
- **Search**: Search customers by name, email, phone number, or city.
- **Customer Metrics**: Each row displays the customer's total completed orders and cumulative lifetime spend in store currency.
- **Customer Details Modal**: Click the Eye icon to view the customer's complete profile, delivery address, and full chronological order history.

### 6.2. Creating & Editing Customers
- Click **Add Customer** to register a new account with validated email, phone, and delivery address.
- Click **Edit** to update addresses or contact numbers.
- **Account Blocking**: You can toggle a customer's status between `Active` and `Blocked`. Blocked customers are prevented from placing new orders.

---

## 7. Order Processing & Invoicing (`orders.html`)

Manage sales transactions from initial placement through final delivery.

### 7.1. Placing a New Multi-Item Order
1. Click **Create Order** (or use the quick shortcut from the dashboard).
2. **Select Customer**: Choose an existing active customer from the searchable dropdown.
3. **Add Line Items**:
   - Select a product from the catalog. The unit price and current available stock will display automatically.
   - Enter the desired quantity. *(The system prevents ordering more units than available in physical inventory).*
   - Click **+ Add Item** to add additional product rows.
   - Watch the live math calculate item subtotals, order tax (8.5%), shipping ($15 or free over $100), and grand total.
4. **Payment Method & Notes**: Select payment channel (*Credit Card*, *PayPal*, *Bank Transfer*, *Cash on Delivery*) and enter optional delivery instructions.
5. Click **Create Order**. 
   - The order is created in **`pending`** status.
   - Physical stock is **automatically deducted** from the catalog inventory.

### 7.2. Order Status Progression & Fulfillment
Click the Status badge or Edit icon on any order to advance fulfillment:
- **`Pending`** &rarr; Order received and inventory reserved.
- **`Processing`** &rarr; Warehouse picking and packing initiated.
- **`Shipped`** &rarr; Carrier tracking assigned, package in transit.
- **`Delivered`** &rarr; Customer delivery confirmed. *(Once delivered, orders cannot be cancelled).*

### 7.3. Safe Order Cancellation & Stock Restoration
1. Open the order and click **Cancel Order**.
2. A confirmation prompt explains that physical inventory will be restored.
3. Upon confirmation:
   - Purchased quantities are added back to product catalog inventory **exactly once**.
   - If payment was `paid`, the payment status is automatically transitioned to **`refunded`**.
   - The historical order record is preserved for accounting and tax audits.

### 7.4. Printable Commercial Invoice
1. Click the Document / Invoice icon on any order row.
2. An enterprise invoice displays with your store logo, order number, customer shipping address, itemized specifications, tax breakdown, and payment status.
3. Click **Print Invoice** to open the browser print dialog formatted for standard 8.5" x 11" or A4 paper.

---

## 8. Store Settings & Sandbox Tools (`settings.html`)

Configure global store parameters and manage sandbox demonstration utilities:

1. **General Store Settings**:
   - Update Store Name, Tagline, Support Email, and Phone Number.
   - Set Default Currency code (e.g. `USD`, `EUR`, `GBP`) and Symbol (`$`, `€`, `£`).
   - Configure Sales Tax rate percentage and Free Shipping threshold.
   - Set the Low Stock Alert quantity (default: 10).
2. **Data Backup & Export**:
   - Click **Export JSON Data** to download a complete JSON snapshot of all products, categories, orders, and customer records.
3. **Factory Reset (Sandbox Mode)**:
   - When demonstrating the application, click **Reset Demo Data** to restore all original sample products, customers, and orders.

