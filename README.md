# IRIS — CLSU Performance Observatory (Laravel)

This branch is the Laravel implementation of the existing IRIS UI. The existing Tailwind/Flowbite/ECharts markup and CSS utility classes were kept intact; the legacy PHP page routing/database/session logic was moved into Laravel controllers, middleware, services, Blade views, migrations, and routes.

## What was implemented

- Laravel 12 application structure.
- Laravel routing for login, registration, logout, user dashboard, admin dashboard, Smart Upload, extraction review, CSV upload, and the JSON summary endpoint.
- Laravel session handling using the file session driver.
- Session regeneration on login and session invalidation on logout.
- Authentication and admin middleware based on the existing `users.role` field.
- CSRF protection on all POST forms.
- Laravel validation for login, registration, CSV uploads, and Smart Upload files.
- Query Builder database access instead of the old `mysqli_*` page code.
- Database migration and ranking-body seeder.
- CSV, DOCX, XLSX/XLS, PDF, JPG/JPEG/PNG Smart Upload support.
- AI extraction service using `CLAUDE_API_KEY` from `.env`.
- Review-before-save flow stored in Laravel session.
- Upload activity logging.
- Existing dashboard charts and data presentation retained.
- Supplied IRIS logo added to login/register and the left side of the navigation bars on user/admin pages.
- Existing CSS classes and CDN-based Tailwind/Flowbite styling were not redesigned.

## XAMPP setup

### 1. Requirements

- PHP 8.2+
- Composer
- XAMPP Apache + MySQL/MariaDB
- PHP extensions: `pdo_mysql`, `curl`, `fileinfo`, `zip`

### 2. Put the project in XAMPP

Copy this folder into:

```text
C:\xampp\htdocs\iris
```

For Apache, the Laravel document root should ideally be:

```text
C:\xampp\htdocs\iris\public
```

If your XAMPP setup does not use a virtual host, you can temporarily use `http://localhost/iris/public`.

### 3. Install Laravel dependencies

Open CMD/PowerShell inside the project:

```powershell
composer install
php artisan key:generate
```

### 4. Configure `.env`

The included `.env` is prepared for the common XAMPP setup where MariaDB/MySQL is running on port `3307`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3307
DB_DATABASE=iris_db
DB_USERNAME=root
DB_PASSWORD=

SESSION_DRIVER=file
SESSION_LIFETIME=120
```

Change `DB_PORT` to `3306` if that is the port shown by your XAMPP MySQL module.

### 5. Create the database

**Fresh Laravel database:**

```powershell
php artisan migrate --seed
```

This creates all IRIS tables and seeds the ranking bodies.

**If you already have the original `iris_db`:**

Do not run the migration against the existing database unless you know the database is empty. You can import the supplied `database/iris_db.sql` through phpMyAdmin instead, then use the Laravel app with that existing schema.

### 6. Start the application

For a quick development test:

```powershell
php artisan serve
```

Then open the URL printed by Artisan.

For XAMPP Apache, point the site's document root to the project's `public` folder.

## Login / roles

Registration creates a normal `user` account.

To promote an account to administrator, run:

```sql
UPDATE users SET role = 'admin' WHERE username = 'your_username';
```

The session stores:

- `user_id`
- `username`
- `role`
- pending Smart Upload extraction data during the review step

## Smart Upload AI

Set the API key in `.env`:

```env
CLAUDE_API_KEY=your_key_here
```

Then restart Apache if Apache is the PHP process using the environment configuration.

The Smart Upload page also displays a setup-status panel so missing PHP extensions or the API key are visible.

## Important project folders

```text
app/
├── Http/Controllers/
│   ├── AuthController.php
│   ├── DashboardController.php
│   ├── AdminController.php
│   ├── UploadController.php
│   └── SummaryController.php
├── Http/Middleware/
│   ├── EnsureAuthenticated.php
│   └── EnsureAdmin.php
└── Services/
    ├── DataImportService.php
    ├── DocumentExtractor.php
    └── AnthropicExtractionService.php

database/
├── migrations/2026_09_17_000000_create_iris_tables.php
├── seeders/DatabaseSeeder.php
└── iris_db.sql

resources/views/
├── auth/
├── user/
└── admin/

public/images/iris-logo.png
routes/web.php
```
