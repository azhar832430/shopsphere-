# ShopSphere – Cloud & Free Hosting Deployment Guide
> **Phase 8: Production Deployment, Hosting Evaluation & Operational Playbook**  
> **Course**: University Web Engineering PBL (SW417)  
> **Stack**: PHP 8+, MySQL 8.0+ / MariaDB 10.4+ (InnoDB), Apache Web Server, Vanilla JavaScript (ES6+), Fetch API.

---

## 1. Executive Summary & Platform Requirements

**ShopSphere** is a monolithic full-stack e-commerce management platform built on native PHP 8 and MySQL. To run reliably in a cloud environment, the hosting provider must satisfy the following minimum technical requirements:

| Component | Minimum Requirement | Recommended |
|---|---|---|
| **Web Server** | Apache 2.4+ with `.htaccess` and `mod_rewrite` / `mod_headers` | Apache 2.4+ |
| **PHP Runtime** | PHP 8.0+ | PHP 8.2+ |
| **PHP Extensions** | `pdo_mysql`, `session`, `json`, `filter`, `mbstring`, `fileinfo` | All standard in modern LAMP stacks |
| **Database** | MySQL 8.0+ or MariaDB 10.4+ (InnoDB engine support mandatory) | MariaDB 10.4+ or MySQL 8.0+ |
| **Storage** | 50 MB for core code + 100 MB for product image uploads | 200 MB+ |
| **Security** | HTTPS / TLS (Let's Encrypt / ZeroSSL) with HTTP-only cookies | Valid SSL certificate |

> [!IMPORTANT]
> **Static Hosts Are Unsupported for the Monolithic Backend**: Platforms such as **GitHub Pages**, **Vercel (static)**, and **Netlify (static)** only serve pre-rendered HTML/CSS/JS and cannot execute server-side PHP scripts or connect directly to a relational MySQL database without an external serverless API rewrite.

---

## 2. Evaluation of Free Hosting Providers for PHP & MySQL

| Provider | PHP 8+ | MySQL | Free Plan Quotas | File Uploads | HTTPS | Deployment Friction | Verdict |
|---|---|---|---|---|---|---|---|
| **InfinityFree** | **Yes** (8.2+) | **Yes** (phpMyAdmin) | Unlimited disk (30,000 inodes), unlimited bandwidth | **Yes** (FTP/FTPS, Web Manager) | **Yes** (Free SSL via cPanel) | **Very Low** (cPanel / standard `htdocs`) | **RECOMMENDED** |
| **Alwaysdata** | **Yes** (8.0–8.3) | **Yes** (phpMyAdmin) | 100 MB total storage shared across files and DB | **Yes** (SSH, SFTP, WebDAV) | **Yes** (Automatic Let's Encrypt) | **Low** (Modern dashboard & CLI) | **Strong Alternative** |
| **Freehostia** | **Yes** (8.1) | **Yes** (1 DB, 10MB) | 250 MB disk, 10 MB DB limit, 6GB bandwidth | **Yes** (FTP) | **Yes** (Self-signed or custom) | **Moderate** (10MB DB limit is restrictive) | Acceptable |
| **Render** | **Yes** (Docker) | **No** (PostgreSQL only) | 750 free instance hrs; DB expires after 30 days | Ephemeral (Wiped on container restart) | **Yes** (Automatic) | **High** (Requires Docker & DB migration) | Not Recommended |
| **Railway** | **Yes** (Docker) | **Yes** (MySQL) | Trial credit only ($5 one-time, then requires card) | Ephemeral disk | **Yes** (Automatic) | **Moderate** (Not indefinitely free) | Not Recommended |

### Recommended Option: **InfinityFree**
**Why InfinityFree is the top recommendation for ShopSphere:**
1. **Zero Architecture Changes**: InfinityFree uses the standard LAMP architecture with Apache and phpMyAdmin, identical to local XAMPP.
2. **Generous Quotas**: Provides unlimited disk space and unlimited bandwidth without payment cards.
3. **Native Database Connectivity**: Full MySQL database with phpMyAdmin for one-click import of [`database/DEPLOYMENT_DATABASE.sql`](file:///c:/Shopsphere/database/DEPLOYMENT_DATABASE.sql).
4. **Permanent Free Tier**: Unlike container PaaS solutions (Render/Railway) where free databases expire after 30 days, InfinityFree accounts remain active indefinitely with regular traffic.

---

## 3. Step-by-Step Deployment on InfinityFree

### Step 1: Create Free Account & Register Domain
1. Visit [InfinityFree.net](https://www.infinityfree.com/) and register a free account.
2. Click **Create Account** in the client area.
3. Choose a free subdomain (e.g., `shopsphere.infinityfreeapp.com` or `shopsphere.epizy.com`).
4. Wait 1–2 minutes for hosting account activation and note your:
   - **FTP Username** (e.g., `epiz_12345678`)
   - **FTP Password**
   - **FTP Hostname** (e.g., `ftpupload.net`)

---

### Step 2: Create MySQL Database via phpMyAdmin
1. In the InfinityFree control panel, navigate to **MySQL Databases**.
2. Create a new database named `shopsphere_db` (the platform will prefix it, e.g., `epiz_12345678_shopsphere_db`).
3. Note your database connection details displayed on the page:
   - **MySQL Hostname**: (e.g., `sql123.epizy.com` — *do not use localhost on InfinityFree!*)
   - **MySQL Database Name**: (e.g., `epiz_12345678_shopsphere_db`)
   - **MySQL Username**: (e.g., `epiz_12345678`)
   - **MySQL Password**: (your vPanel password)
4. Click **phpMyAdmin** beside the created database.
5. In phpMyAdmin, click the **Import** tab.
6. Select [`database/DEPLOYMENT_DATABASE.sql`](file:///c:/Shopsphere/database/DEPLOYMENT_DATABASE.sql) from your local project.
7. Click **Import** (or **Go**) to build all tables, foreign keys, and seed data.

---

### Step 3: Configure Environment Variables (`.env`)
Create a production `.env` file in your local project root before uploading (or create it directly in the hosting file manager):

```ini
# Production Cloud Configuration
APP_NAME="ShopSphere"
APP_ENV=production
APP_URL=https://shopsphere.infinityfreeapp.com

# Database Connection (Enter details from InfinityFree MySQL page)
DB_HOST=sql123.epizy.com
DB_PORT=3306
DB_NAME=epiz_12345678_shopsphere_db
DB_USER=epiz_12345678
DB_PASS=YourHostingPasswordHere
DB_CHARSET=utf8mb4

# Security
SESSION_LIFETIME=7200
DISPLAY_ERRORS=0
```

> [!TIP]
> Thanks to ShopSphere's built-in `.env` loader in [`config/app.php`](file:///c:/Shopsphere/config/app.php), the application automatically detects and applies these settings without requiring hardcoded edits to [`config/database.php`](file:///c:/Shopsphere/config/database.php).

---

### Step 4: Upload Application Files via FTP
1. Download and open [FileZilla Client](https://filezilla-project.org/) (or use the web file manager).
2. Connect using the FTP credentials from Step 1:
   - **Host**: `ftpupload.net`
   - **Username**: `epiz_12345678`
   - **Password**: *(your hosting password)*
   - **Port**: `21`
3. In the remote pane, open the **`htdocs/`** directory.
4. Upload all files from `C:\Shopsphere\` into `htdocs/`:
   ```
   htdocs/
   ├── .env
   ├── .htaccess
   ├── api/
   ├── assets/
   ├── config/
   ├── includes/
   ├── uploads/
   ├── index.html
   ├── dashboard.html
   ├── products.html
   ├── categories.html
   ├── customers.html
   ├── orders.html
   └── settings.html
   ```
5. Ensure permissions on `uploads/` and `uploads/products/` are set to `755` or `775` to allow image uploads.

---

### Step 5: Enable Free HTTPS (SSL)
1. In the InfinityFree client area, click **Free SSL Certificates**.
2. Select your custom domain or subdomain (`shopsphere.infinityfreeapp.com`).
3. Choose the **Let's Encrypt** or **ZeroSSL** automated provider.
4. Follow the prompt to install the SSL certificate into your hosting account automatically.
5. Once issued (typically 5–15 minutes), verify that `https://shopsphere.infinityfreeapp.com/` loads with a secure padlock.

---

## 4. Alternative Deployment: Alwaysdata

For evaluators or developers requiring **SSH/SFTP terminal access**:

1. Register at [Alwaysdata.com](https://www.alwaysdata.com/) (free plan includes 100MB disk).
2. Navigate to **Databases > MySQL** and create a database and user.
3. Import [`database/DEPLOYMENT_DATABASE.sql`](file:///c:/Shopsphere/database/DEPLOYMENT_DATABASE.sql) through phpMyAdmin.
4. Under **Web > Sites**, verify your default site points to `/home/username/www`.
5. Upload files via SFTP or Git clone:
   ```bash
   cd ~/www
   # Place ShopSphere files in www root
   ```
6. Create `.env` in `~/www/` with your Alwaysdata MySQL credentials.
7. Access `https://username.alwaysdata.net/` with automatic SSL pre-configured.

---

## 5. Post-Deployment Verification Checklist

Once deployed to cloud hosting, execute this verification runbook:

- [ ] **System Health Check**: Visit `https://your-domain/api/health.php` and confirm `{"status": "healthy", "database": "connected"}`.
- [ ] **Admin Authentication**: Navigate to `index.html` and sign in with `admin@shopsphere.io` / `Admin@123`.
- [ ] **Session Persistence**: Refresh the dashboard and verify the session remains active without redirecting.
- [ ] **Catalog CRUD**: Visit `products.html`, add a test product with an image upload, and verify the record is saved.
- [ ] **Taxonomy CRUD**: Visit `categories.html` and verify live product counts are computed.
- [ ] **CRM CRUD**: Visit `customers.html` and verify customer profiles load with their spending totals.
- [ ] **Order CRUD**: Visit `orders.html`, place a test order with line items, and verify inventory stock decrements accurately.
- [ ] **Security**: Verify `https://your-domain/config/database.php` and `https://your-domain/.env` return **HTTP 403 Forbidden** via `.htaccess`.

---

## 6. Common Production Troubleshooting

| Issue | Likely Cause | Solution |
|---|---|---|
| **`SQLSTATE[HY000] [2002]`** | `DB_HOST` is set to `127.0.0.1` or `localhost` on a cloud provider where the database resides on a distinct cluster. | Change `DB_HOST` in `.env` to the specific MySQL hostname assigned in your hosting control panel (e.g., `sql123.epizy.com`). |
| **`HTTP 500 Internal Server Error`** | Syntax error in `.htaccess` unsupported by the provider's Apache build. | Check error logs. Temporarily remove `RedirectMatch 403` or custom header directives in `.htaccess`. |
| **Images Fail to Upload** | Target directory permissions lack write access. | Set permissions of `uploads/` and `uploads/products/` to `755` or `775` via FTP client. |
| **Session Lost on Refresh** | Browser blocking cross-origin session cookies or missing `SameSite=Lax`. | Ensure `APP_URL` uses `https://` matching your exact browser domain. |
| **Case-Sensitivity 404s** | Local Windows filesystems are case-insensitive, while Linux cloud hosts are case-sensitive. | Ensure file references match exact casing (e.g., `style.css`, `api/orders/index.php`). ShopSphere uses strictly lowercase file paths throughout. |

---

## 7. Current Project Deployment Status

- **Local Production Execution (XAMPP)**: **100% OPERATIONAL & VERIFIED** at `http://localhost/ShopSphere/`.
- **Cloud Free Hosting**: Fully evaluated, documented, and prepared with production `.env.example`, `.htaccess`, and `DEPLOYMENT_DATABASE.sql`.
- **Honest Status Disclosure**: Per course guidelines, no unauthorized third-party accounts were pre-registered. All deployment instructions are reproducible and ready for student execution.

