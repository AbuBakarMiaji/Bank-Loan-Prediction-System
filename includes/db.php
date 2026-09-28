<?php
/**
 * Database connection (PDO / MySQL)
 * Update the constants below to match your local MySQL setup.
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'loan_system');
define('DB_USER', 'root');
define('DB_PASS', '');

function get_db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );
            
            // Auto-migrate tables if needed
            ensure_schema_migrations($pdo);
        } catch (PDOException $e) {
            http_response_code(500);
            die('Database connection failed. Please verify config/db.php and that the "loan_system" '
                . 'database has been imported from database.sql. (' . htmlspecialchars($e->getMessage()) . ')');
        }
    }
    return $pdo;
}

/** Automatically add settings table and loan calculation columns if missing */
function ensure_schema_migrations(PDO $db): void {
    try {
        $db->exec("
            CREATE TABLE IF NOT EXISTS settings (
                setting_key   VARCHAR(50)  PRIMARY KEY,
                setting_value VARCHAR(255) NOT NULL
            ) ENGINE=InnoDB;
        ");
        $db->exec("
            INSERT INTO settings (setting_key, setting_value) VALUES ('annual_interest_rate', '8.50')
            ON DUPLICATE KEY UPDATE setting_value = setting_value;
        ");

        $columns = $db->query("SHOW COLUMNS FROM loans")->fetchAll(PDO::FETCH_COLUMN);

        if (!in_array('interest_rate', $columns)) {
            $db->exec("ALTER TABLE loans ADD COLUMN interest_rate DECIMAL(5,2) NOT NULL DEFAULT 8.50 AFTER loan_amount");
        }
        if (!in_array('loan_term_years', $columns)) {
            $db->exec("ALTER TABLE loans ADD COLUMN loan_term_years INT NOT NULL DEFAULT 1 AFTER interest_rate");
        }
        if (!in_array('monthly_payment', $columns)) {
            $db->exec("ALTER TABLE loans ADD COLUMN monthly_payment DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER loan_term_months");
        }
        if (!in_array('total_interest', $columns)) {
            $db->exec("ALTER TABLE loans ADD COLUMN total_interest DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER monthly_payment");
        }
        if (!in_array('total_payment', $columns)) {
            $db->exec("ALTER TABLE loans ADD COLUMN total_payment DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER total_interest");
        }
    } catch (Throwable $t) {
        // Table might not exist yet if database.sql hasn't been imported
    }
}

/** Get annual interest rate set by admin (defaults to 8.5%) */
function get_annual_interest_rate(): float {
    $db = get_db();
    try {
        $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = 'annual_interest_rate'");
        $stmt->execute();
        $val = $stmt->fetchColumn();
        return $val !== false ? (float)$val : 8.50;
    } catch (Throwable $t) {
        return 8.50;
    }
}

/** Update annual interest rate set by admin */
function set_annual_interest_rate(float $rate): void {
    $db = get_db();
    $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('annual_interest_rate', ?)
                          ON DUPLICATE KEY UPDATE setting_value = ?");
    $stmt->execute([number_format($rate, 2, '.', ''), number_format($rate, 2, '.', '')]);
}

