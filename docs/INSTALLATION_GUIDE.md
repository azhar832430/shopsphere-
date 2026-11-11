# ShopSphere – Local Installation & XAMPP Setup Guide
> **Course**: University Web Engineering PBL (SW417)  
> **Project**: ShopSphere – E-Commerce Management System  
> **Target Environment**: Windows 10/11 / macOS / Linux with XAMPP (Apache + PHP 8+ + MySQL / MariaDB)

---

## 1. System Prerequisites

Before running ShopSphere locally, ensure your workstation has:

1. **XAMPP 8.0 or higher** (XAMPP 8.2+ with PHP 8.2 recommended):
   - Download from [Apache Friends](https://www.apachefriends.org/).
   - Includes Apache 2.4+, PHP 8.2+, MariaDB 10.4+ / MySQL 8.0+, and phpMyAdmin.
2. **PHP Extensions**:
   - `pdo_mysql` (Database PDO driver)
   - `session` (Admin state management)
   - `json` (REST API payload serialization)
   - `mbstring` (Multibyte string sanitization)
   - `fileinfo` (Image MIME type detection)
   *(All five extensions are enabled by default in standard XAMPP).*
3. **Web Browser**:
   - Google Chrome, Microsoft Edge, Mozilla Firefox, or Safari (modern evergreen browsers).

---

## 2. Step-by-Step Installation Instructions

### Step 1: Exact Folder Placement in XAMPP `htdocs`

Apache expects web projects to be located in the `htdocs` directory of your XAMPP installation:

```text
C:\xampp\htdocs\ShopSphere
```

#### Option A: Copy Project Directory
Copy the entire `ShopSphere` folder directly into `C:\xampp\htdocs\`:
```powershell
Copy-Item -Path "C:\Shopsphere" -Destination "C:\xampp\htdocs\ShopSphere" -Recurse
```

#### Option B: Directory Junction (Recommended for Active Development)
If developing in `C:\Shopsphere`, create a directory junction in `C:\xampp\htdocs\` so changes reflect instantly without copying:
```cmd
cmd /c mklink /J "C:\xampp\htdocs\ShopSphere" "C:\Shopsphere"
```

Verify that opening `C:\xampp\htdocs\ShopSphere\index.html` shows the project files.

---

### Step 2: Start Apache and MySQL Services

1. Launch the **XAMPP Control Panel** (Run as Administrator if prompted).
2. Click the **Start** button next to **Apache**.
3. Click the **Start** button next to **MySQL**.
4. Confirm both services show **green status indicators** and active ports:
   - **Apache**: Port `80` (or `443` for SSL)
   - **MySQL**: Port `3306`

> [!NOTE]
> If Port 80 is occupied (e.g. by Skype or IIS), open Apache `httpd.conf` in XAMPP and change `Listen 80` to `Listen 8080`, then access `http://localhost:8080/ShopSphere/`.

---

### Step 3: Import the Database (`DEPLOYMENT_DATABASE.sql`)

1. Open your browser and navigate to the phpMyAdmin dashboard:
   ```text
   http://localhost/phpmyadmin/
   ```
2. In the top navigation menu, click the **Import** tab.
3. Click **Choose File** (or Browse) and select:
   ```text
   C:\xampp\htdocs\ShopSphere\database\DEPLOYMENT_DATABASE.sql
   ```
   *(Alternatively, `C:\Shopsphere\database\DEPLOYMENT_DATABASE.sql`).*
4. Ensure the **Format** dropdown is set to **SQL**.
5. Scroll down and click **Import** (or **Go**).
6. phpMyAdmin will execute the DDL statements, create the database `shopsphere_db`, build all 6 relational tables with check constraints and foreign keys, and insert all sample seed records.
7. A green confirmation banner will appear:  
   *`"Import has been successfully finished, queries executed."`*

---

### Step 4: Verify Database Connection Configuration

ShopSphere works out-of-the-box with standard XAMPP default credentials:

- **Host**: `127.0.0.1`
- **Port**: `3306`
- **Database**: `shopsphere_db`
- **Username**: `root`
- **Password**: *(empty string)*

Inspect [`config/database.php`](file:///c:/Shopsphere/config/database.php):
```php
private static string $host = '127.0.0.1';
private static string $port = '3306';
private static string $dbName = 'shopsphere_db';
private static string $username = 'root';
private static string $password = '';
```

#### Optional: Custom Configuration via `.env`
If you set a MySQL root password in phpMyAdmin, simply copy `.env.example` to `.env` and configure your credentials:
```ini
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=shopsphere_db
DB_USER=root
DB_PASS=your_custom_password
```
The application will automatically read `.env` and connect cleanly.

---

### Step 5: Test System Health Endpoint

Before opening the user interface, verify backend and database health by visiting the health check API:

```text
http://localhost/ShopSphere/api/health.php
```

#### Expected JSON Response (`200 OK`):
```json
{
  "success": true,
  "message": "ShopSphere backend is operational and database connection is healthy.",
  "data": {
    "status": "healthy",
    "php_version": "8.2.12",
    "database": "connected",
    "server_time": "2026-11-11 17:30:00 UTC"
  }
}
```

---

### Step 6: Access the Application & Sign In

Open your web browser and navigate to:
```text
http://localhost/ShopSphere/
```
*(or `http://localhost/ShopSphere/index.html`).*

#### Pre-Configured Administrator Credentials:
- **Email**: `admin@shopsphere.io` *(or Username: `admin`)*
- **Password**: `Admin@123`
- **Role**: Super Administrator

Click **Sign In**. The portal authenticates via `POST /api/auth/login.php`, sets the hardened session cookie, and redirects to `dashboard.html`.

---

## 3. Running Automated Tests Locally

To verify all backend endpoints, validation rules, ACID transactions, and security controls directly on your workstation:

```powershell
# Run the Master Test Runner (207 tests across 11 suites)
& "C:\xampp\php\php.exe" C:\xampp\htdocs\ShopSphere\tests\run_all_tests.php

# Run the Phase 2 Python database schema verification
python C:\xampp\htdocs\ShopSphere\database\verify_database.py
```

All 207 automated checks will report `[PASS]` with a 100% success rate.

---

## 4. Local Troubleshooting & FAQ

### 1. "Database connection unavailable" on Login
- **Cause**: MySQL is not running in XAMPP, or database name/credentials differ.
- **Fix**: Open XAMPP Control Panel and verify MySQL shows green. If you configured a password on your root user, specify it in `.env` or `config/database.php`.

### 2. "Method Not Allowed" (HTTP 405)
- **Cause**: Sending a GET request to a POST endpoint or vice versa.
- **Fix**: Verify API requests match the verbs defined in [`API_DOCUMENTATION.md`](file:///c:/Shopsphere/API_DOCUMENTATION.md).

### 3. "CSRF verification failed" (HTTP 403)
- **Cause**: Sending a POST request without a valid session or missing `X-CSRF-Token` header.
- **Fix**: Call `/api/auth/me.php` to obtain a session and token. The built-in frontend `assets/js/api.js` attaches this header automatically.

### 4. Direct `file:///` Protocol Testing
If you double-click `dashboard.html` without a local web server (using `file:///C:/...`):
- The frontend will load and operate under **Offline Mock Mode** using [`assets/js/store.js`](file:///c:/Shopsphere/assets/js/store.js) in `localStorage`.
- To test real MySQL database persistence, always run via `http://localhost/ShopSphere/`.

