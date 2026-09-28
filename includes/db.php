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
        } catch (PDOException $e) {
            http_response_code(500);
            die('Database connection failed. Please verify config/db.php and that the "loan_system" '
                . 'database has been imported from database.sql. (' . htmlspecialchars($e->getMessage()) . ')');
        }
    }
    return $pdo;
}
