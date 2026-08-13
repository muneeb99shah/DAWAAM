<?php
/**
 * Dawaam - Local Business Continuity Software
 * PDO MySQL Database Connection Singleton Provider
 */

require_once __DIR__ . '/constants.php';

function get_db_connection() {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            DB_HOST,
            DB_PORT,
            DB_NAME,
            DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Local server database connection error handler
            error_log('Database Connection Error: ' . $e->getMessage());
            die('
                <div style="font-family: sans-serif; max-width: 600px; margin: 50px auto; padding: 25px; border: 2px solid #ef4444; background: #fef2f2; border-radius: 8px;">
                    <h2 style="color: #991b1b; margin-top:0;">Dawaam Local Server Database Error</h2>
                    <p style="color: #7f1d1d;">Unable to connect to local MySQL database. Please ensure XAMPP MySQL service is running.</p>
                    <p style="font-family: monospace; background: #fee2e2; padding: 10px; border-radius: 4px;">' . htmlspecialchars($e->getMessage()) . '</p>
                </div>
            ');
        }
    }

    return $pdo;
}
