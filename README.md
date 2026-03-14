# AttendTrack – Attendance Management System

A full-stack PHP attendance management app with GPS check-in, selfie capture, overtime tracking, and admin reporting. Deployed on **Vercel** with **Vercel Postgres** (Neon).

---

## 🌐 Live URL

**https://absencsm.vercel.app**

---

## 🗂️ Project Structure

```
vercel-app/
├── vercel.json                  ← Vercel routing config
├── api/                         ← All PHP files (serverless functions)
│   ├── config.php               ← DB connection, sessions, auth helpers
│   ├── functions.php            ← All app functions
│   ├── index.php                ← Login page
│   ├── dashboard.php            ← User attendance panel
│   ├── register.php             ← New account registration
│   ├── logout.php               ← Session destroy + redirect
│   ├── profile.php              ← Edit username / password / photo
│   ├── forgot-password.php      ← Token-based password reset
│   ├── install.php              ← One-click DB table installer
│   ├── attendance-action.php    ← JSON API for check-in/out
│   ├── admin/
│   │   ├── index.php            ← Admin dashboard (Chart.js)
│   │   ├── users.php            ← User CRUD + work schedule
│   │   └── reports.php          ← Reports + CSV / Excel export
│   └── includes/
│       └── nav.php              ← Shared sidebar + topbar
└── public/
    └── assets/
        ├── css/style.css        ← Full stylesheet (responsive)
        └── logo.svg             ← App logo
```

---

## ⚙️ Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | PHP 8.2 (Vercel serverless via `vercel-php@0.7.2`) |
| Database | PostgreSQL (Vercel Postgres / Neon) |
| Frontend | Vanilla JS + Chart.js |
| Hosting | Vercel |
| Auth | PHP sessions stored in Postgres |
| Styling | Custom CSS (DM Sans + DM Mono) |

---

## 🗄️ Database Schema

### Tables

| Table | Description |
|-------|-------------|
| `users` | Employees and admins |
| `attendance` | Check-in/out records per day |
| `password_resets` | Password reset tokens |
| `php_sessions` | Server-side session storage (required for Vercel) |

### Key columns — `users`

| Column | Type | Notes |
|--------|------|-------|
| `id` | SERIAL | Primary key |
| `username` | VARCHAR(30) | Unique |
| `email` | VARCHAR(100) | Unique |
| `password` | VARCHAR(255) | bcrypt hashed |
| `role` | VARCHAR(10) | `user` or `admin` |
| `work_start` | TIME | Scheduled start time |
| `work_end` | TIME | Scheduled end time |
| `is_active` | SMALLINT | 1 = active, 0 = disabled |

### Key columns — `attendance`

| Column | Type | Notes |
|--------|------|-------|
| `user_id` | INT | FK → users.id |
| `work_date` | DATE | Unique per user per day |
| `checkin_time` | TIME | Regular check-in |
| `checkin_lat/lng` | DECIMAL | GPS coordinates |
| `checkin_photo` | VARCHAR | Saved filename |
| `ot_checkin_time` | TIME | Overtime check-in |
| `status` | VARCHAR | `present`, `absent`, `leave`, `holiday` |

---

## 🚀 Deployment Guide

### 1. Clone and push to GitHub

```bash
git init
git add .
git commit -m "initial commit"
git remote add origin https://github.com/yourname/attendtrack.git
git push -u origin main
```

### 2. Import to Vercel

1. Go to [vercel.com](https://vercel.com) → **Add New Project**
2. Import your GitHub repo
3. Vercel auto-detects `vercel.json` — click **Deploy**

### 3. Add Vercel Postgres

1. Vercel Dashboard → **Storage** → **Create Database** → Postgres
2. Connect it to your project
3. Environment variables (`PGHOST`, `PGDATABASE`, etc.) are injected automatically

### 4. Run the database schema

1. Vercel Dashboard → Storage → your DB → **Query** tab
2. Paste and run `schema_postgres.sql`

---

## 🔑 Default Accounts

| Role | Username | Password |
|------|----------|----------|
| Admin | `admin` | `Admin@123` |
| User | `john_doe` | `User@123` |
| User | `jane_smith` | `User@123` |
| User | `ali_rahman` | `User@123` |
| User | `siti_nurhaliza` | `User@123` |
| User | `budi_santoso` | `User@123` |

> ⚠️ Change all passwords after first login.

---

## ✨ Features

### User
- 🔐 Login / Register / Forgot Password
- 📍 GPS-verified Check-In & Check-Out
- 📸 Selfie photo at every check-in
- ⏱️ Overtime Check-In & Check-Out
- 🕐 Live clock display
- 👤 Edit profile, change password, upload photo

### Admin
- 📊 Dashboard with Chart.js weekly trend
- 👥 User Management (create, edit, delete, set work schedule)
- 📑 Attendance Reports with filters
- 📥 Export to CSV and Excel
- 🔒 Role-based access control

---

## 🌍 URL Routes

| URL | Page |
|-----|------|
| `/` | Login |
| `/dashboard` | User dashboard |
| `/register` | Register |
| `/profile` | My profile |
| `/forgot-password` | Password reset |
| `/logout` | Logout |
| `/admin/index` | Admin dashboard |
| `/admin/users` | User management |
| `/admin/reports` | Reports & export |
| `/api/attendance-action` | Check-in/out API (POST) |

---

## 🔧 Configuration

All settings are in `api/config.php`:

```php
define('APP_NAME',       'AttendTrack');
define('SESSION_TIMEOUT', 3600);        // 1 hour
define('TIMEZONE',        'Asia/Jakarta');
```

Database credentials are read from **Vercel environment variables** automatically:

```php
$host     = $_ENV['PGHOST'];
$dbname   = $_ENV['PGDATABASE'];
$user     = $_ENV['PGUSER'];
$password = $_ENV['PGPASSWORD'];
```

---

## ⚠️ Known Limitations

| Issue | Reason | Workaround |
|-------|--------|------------|
| Photo uploads are temporary | Vercel filesystem is read-only (uses `/tmp`) | Integrate Cloudinary or AWS S3 |
| Cold starts | Vercel spins up containers on demand | Normal for serverless — first load may be slow |
| Sessions stored in DB | Vercel containers don't share `/tmp` | Already implemented via `php_sessions` table |

---

## 📦 Local Development

```bash
# Requires PHP 8.x + PostgreSQL
php -S localhost:8000 -t api/

# Or use Laravel Valet / XAMPP with a local Postgres DB
# Update config.php with local DB credentials
```

---

## 📄 License

MIT — free to use and modify.
