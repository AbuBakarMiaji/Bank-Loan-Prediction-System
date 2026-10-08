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

## 5. Machine Learning Integration (Bank_Loan.ipynb)

The prediction engine is powered directly by the trained Machine Learning model from `Bank_Loan(1).ipynb`:

- **Selected Model:** `RandomForestClassifier` (100 estimators, random state 42).
- **Notebook Evaluation Accuracy:** **92.0%** (vs 89.0% for Logistic Regression).
- **Artifacts Location:**
  - Trained Model: `model/loan_prediction_rf_model.pkl`
  - Fitted Label Encoders: `model/loan_prediction_label_encoders_dict.pkl`
  - Model Schema & Metadata: `model/model_metadata.json`
- **Features Used (13 features):** `Age`, `Gender`, `Education`, `Person Income`, `Employee Experience`, `Home Onwership`, `Loan Amount`, `Loan Intent`, `Loan interest Rate`, `Loan percentage`, `Credit History`, `Credit Score`, `Previous Loan`.

### How to Run the ML API Service:

1. Double-click `start_ml_api.bat` OR run in terminal:
   ```bash
   python ml_api.py
   ```
   The Flask API will start on `http://127.0.0.1:5000`.

2. In `predict.php`:
   - Primary: PHP makes an HTTP POST request to `http://127.0.0.1:5000/predict`.
   - Zero-Downtime Fallback: If the Flask service is not yet running, `predict.php` automatically invokes `predict_cli.py` via PHP subprocess execution to run the exact same trained model without failing.
   - Response: Returns exact prediction (`Approved` or `Rejected`), confidence percentage, probability, and factor contributions.

## 6. Security notes for production use

This is a demo/architecture build. Before deploying for real users:
- Serve over HTTPS and set `session.cookie_secure` / `httponly`.
- Add rate limiting to `login.php` and `register.php`.
- Add server-side validation limits (max loan amount, etc.) matching your policy.
- Rotate/replace the seeded admin password immediately.
- Consider 2FA for admin accounts.
