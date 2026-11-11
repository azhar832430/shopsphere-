# Contributing to ShopSphere

Thank you for your interest in contributing to the **ShopSphere E-Commerce Management System**!

## 1. Development Principles

To maintain the architectural integrity of this project, all contributions must adhere to the core principles:

- **Frontend**: Native HTML5, modern CSS3, and Vanilla JavaScript (ES6+). Do not introduce external frontend bundlers or frameworks (e.g. React, Vue, webpack).
- **Backend**: Native PHP 8+ using PHP Data Objects (PDO) with parameterized prepared statements. Do not add heavy backend frameworks (e.g. Laravel).
- **Database**: Normalized MySQL 8.0+ / MariaDB 10.4+ schema using the InnoDB storage engine with strict foreign keys and check constraints.
- **Security**: All mutating API endpoints must enforce session authentication and CSRF token verification. All user-supplied HTML output must be escaped using `UI.escapeHtml()`.

---

## 2. Setting Up for Local Development

1. Fork the repository on GitHub.
2. Clone your fork locally into your XAMPP web root:
   ```bash
   git clone https://github.com/your-username/shopsphere-.git C:/xampp/htdocs/ShopSphere
   ```
3. Start **Apache** and **MySQL** in the XAMPP Control Panel.
4. Import `database/DEPLOYMENT_DATABASE.sql` (or `database/shopsphere.sql`) into phpMyAdmin.
5. Copy `.env.example` to `.env` if your local environment requires custom database credentials.
6. Open `http://localhost/ShopSphere/` in your browser.

---

## 3. Running Automated Tests

Before submitting any code changes or pull requests, verify that all automated test suites pass with a 100% success rate:

```powershell
& "C:\xampp\php\php.exe" tests/run_all_tests.php
python database/verify_database.py
```

All 207 automated checks must report `[PASS]`.

---

## 4. Submitting Pull Requests

1. Create a feature branch: `git checkout -b feature/your-feature-name`.
2. Commit your changes with clear, descriptive commit messages.
3. Push to your branch: `git push origin feature/your-feature-name`.
4. Open a Pull Request on GitHub detailing your modifications.
