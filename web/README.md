# 3Commas Web Platform

A complete investment platform built with PHP 8.1+, MySQL 8, Tailwind CSS CDN, and vanilla JavaScript.

## Requirements

- **PHP** 8.1 or higher (with extensions: pdo, pdo_mysql, openssl, mbstring)
- **MySQL** 8.0 or higher
- **Composer** 2.x
- An **AWS account** with SES configured (optional – emails gracefully degrade if not configured)

## Setup Instructions

### 1. Clone / Copy Files

Ensure all files are in the `/web` directory of the repository root.

### 2. Install PHP Dependencies

```bash
cd web
composer install
```

> **Note:** Composer is only required for AWS SES email sending. The platform works without it – emails will be silently skipped.

### 3. Configure Environment

Copy the example env file and fill in your values:

```bash
cp .env.example .env
```

Edit `.env`:

```env
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=commas_web
DB_USER=your_db_user
DB_PASS=your_db_password

APP_URL=http://localhost:8000
APP_KEY=change_this_to_a_32_char_random_string
APP_ENV=local

# AWS SES (optional – leave blank to skip emails)
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_REGION=us-east-1
SES_FROM_EMAIL=noreply@yourdomain.com
SES_FROM_NAME=3Commas
```

### 4. Create the Database

```bash
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS commas_web CHARACTER SET utf8mb4;"
mysql -u root -p commas_web < sql/schema.sql
```

The schema creates all tables and seeds:
- 3 default investment plans (Starter, Growth, Pro Trader)
- 3 sample deposit addresses (BTC, ETH, USDT)

### 5. Create an Admin User

After running the schema, register through the web UI, then promote yourself:

```sql
UPDATE commas_web.users SET role = 'admin' WHERE email = 'your@email.com';
```

### 6. Run Locally with PHP Built-in Server

```bash
cd web/public
php -S localhost:8000
```

Then visit: **http://localhost:8000**

Or to serve from the repo root (adjust paths accordingly):

```bash
php -S localhost:8000 -t web/public
```

---

## File Structure

```
web/
├── .env.example          # Environment variable template
├── .env                  # Your local config (gitignored)
├── composer.json         # AWS SDK dependency
├── vendor/               # Composer packages (after install)
├── sql/
│   └── schema.sql        # MySQL schema + seed data
├── src/
│   ├── config.php        # Env loader, PDO singleton, session bootstrap
│   ├── auth.php          # Login/logout/session helpers
│   ├── csrf.php          # CSRF token generation + verification
│   ├── email.php         # AWS SES email functions
│   └── helpers.php       # Utilities (redirect, flash, price fetch, etc.)
└── public/
    ├── index.php         # Landing page (dark hero, plans, features)
    ├── login.php         # Login form
    ├── register.php      # Registration form
    ├── logout.php        # Session destroy + redirect
    ├── forgot_password.php  # Password reset request
    ├── reset_password.php   # Password reset form (token-based)
    └── app/
    │   ├── index.php     # User dashboard (balance, watchlist, positions)
    │   ├── markets.php   # TradingView chart + market overview
    │   ├── trading.php   # Demo trading (simulated buy/sell)
    │   ├── wallet.php    # Deposit & withdrawal management
    │   └── profile.php   # User profile + change password
    └── admin/
        ├── index.php     # Admin dashboard (stats overview)
        ├── plans.php     # Investment plans CRUD
        ├── addresses.php # Deposit addresses CRUD
        ├── withdrawals.php  # Withdrawal request management
        └── users.php     # User management (role, status, balance)
```

---

## Features

### User Features
- **Authentication**: Register, login with bcrypt passwords, remember me, password reset via email
- **Dashboard**: Live balance, market watchlist (Binance API), open demo positions
- **Markets**: TradingView advanced chart widget, live prices for 6 pairs
- **Demo Trading**: Simulated buy/sell with real-time P&L, close positions
- **Wallet**: Request deposits (admin-approved), request withdrawals, transaction history
- **Profile**: View account info, change password

### Admin Features
- **Dashboard**: Stats (users, pending requests, active plans)
- **Investment Plans**: Full CRUD for plans (name, ROI, duration, min/max deposit)
- **Deposit Addresses**: Manage crypto receiving addresses shown to users
- **Withdrawal Management**: Approve/reject withdrawals with optional notes; sends email notification
- **User Management**: Toggle active/disabled, toggle user/admin role, set balance

### Technical
- **CSRF protection** on all POST forms
- **Secure sessions** (httponly, samesite=Lax, HTTPS-aware secure flag)
- **Dark theme** throughout (Tailwind slate-900 + emerald-500 accents)
- **Mobile-first** with bottom nav for app pages
- **Binance API** price fetching with graceful fallback to mock prices
- **AWS SES** emails via SDK (graceful fallback if not configured)

---

## Security Notes

1. Never commit `.env` to version control (add to `.gitignore`)
2. Use a strong random `APP_KEY` value
3. Ensure `web/src/` and `web/sql/` are not web-accessible in production
4. Use HTTPS in production (set `APP_URL=https://...`)
5. Consider adding rate limiting on login/registration endpoints
