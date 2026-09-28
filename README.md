# Ledger — Bank Loan Management & Prediction System

A working implementation of the Bank Loan Prediction System plan, built with
PHP (server logic), MySQL (storage), and plain HTML/CSS/JS (front end) —
no framework required.

## What's included

| Module (from the plan)         | File(s)                                  |
|---------------------------------|-------------------------------------------|
| User Registration / Login       | `register.php`, `login.php`, `logout.php` |
| Customer Dashboard               | `dashboard.php`                           |
| Loan Application                 | `loan_apply.php`                          |
| Loan Prediction (ML module)      | `includes/predict.php`                    |
| Loan History                     | `loan_history.php`                        |
| Admin Dashboard                  | `admin/dashboard.php`                     |
| Admin Search Customer            | `admin/search_customer.php`, `admin/customer_view.php` |
| Admin Reports                    | `admin/reports.php`                       |
| Shared layout / auth / DB        | `includes/header.php`, `includes/footer.php`, `includes/auth.php`, `includes/db.php` |

## 1. Requirements

- PHP 8.0+
- MySQL 5.7+ / MariaDB
- A local server stack: XAMPP, MAMP, Laragon, or `php -S` + a MySQL server

## 2. Database setup

1. Create the database and tables by importing `database.sql`:
   ```
   mysql -u root -p < database.sql
   ```
   This creates the `loan_system` database, the `users` and `loans` tables,
   and seeds one administrator account.

2. Open `includes/db.php` and update the connection constants if your
   MySQL username/password differ from the defaults (`root` / empty password):
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'loan_system');
   define('DB_USER', 'root');
   define('DB_PASS', '');
   ```

## 3. Run it

Using PHP's built-in server from the project root:
```
php -S localhost:8000
```
Then visit **http://localhost:8000/index.php**.

Or drop the whole `loan_system/` folder into your XAMPP/MAMP `htdocs` (or
Laragon `www`) directory and visit it through your local Apache server.

## 4. Demo login

An administrator account is seeded automatically:

- **Email:** `admin@ledger.bank`
- **Password:** `Admin@123`

Create a customer account any time via **Open an account** on the home page.

## 5. How the prediction works

`includes/predict.php` implements a transparent, weighted scoring model over
the same five signals described in the plan (credit history, income vs. loan
amount/term, dependents, education, property area). It returns an
Approved/Rejected result plus a confidence percentage and a breakdown of
which factors pushed the score up or down — shown to the customer right
after they apply, and logged with every record in `loans`.

This keeps the whole system runnable on plain PHP with no Python/ML runtime.
To connect a real trained model instead, replace the body of `predict_loan()`
with an HTTP call to your model's inference endpoint (e.g. a Flask/FastAPI
service or a cloud ML endpoint), keeping the same return shape.

## 6. Security notes for production use

This is a demo/architecture build. Before deploying for real users:
- Serve over HTTPS and set `session.cookie_secure` / `httponly`.
- Add rate limiting to `login.php` and `register.php`.
- Add server-side validation limits (max loan amount, etc.) matching your policy.
- Rotate/replace the seeded admin password immediately.
- Consider 2FA for admin accounts.
