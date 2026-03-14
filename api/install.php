<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>AttendTrack – Installer</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',sans-serif;background:#0f172a;color:#e2e8f0;min-height:100vh;display:flex;align-items:center;justify-content:center}
.card{background:#1e293b;border:1px solid #334155;border-radius:16px;padding:40px;max-width:600px;width:90%;box-shadow:0 25px 50px rgba(0,0,0,0.5)}
h1{font-size:2rem;color:#38bdf8;margin-bottom:8px}
p{color:#94a3b8;margin-bottom:24px}
.step{background:#0f172a;border-radius:10px;padding:20px;margin-bottom:16px;border-left:4px solid #38bdf8}
.step h3{color:#f1f5f9;margin-bottom:8px}
.step p{margin:0;font-size:.9rem}
.ok{border-left-color:#22c55e;color:#22c55e}
.err{border-left-color:#ef4444;color:#ef4444}
.warn{border-left-color:#f59e0b;color:#f59e0b}
pre{background:#0f172a;padding:12px;border-radius:6px;font-size:.8rem;overflow-x:auto;color:#94a3b8;margin-top:8px}
.btn{display:inline-block;background:#38bdf8;color:#0f172a;padding:14px 32px;border-radius:10px;text-decoration:none;font-weight:700;margin-top:8px;cursor:pointer;border:none;font-size:1rem;transition:.2s}
.btn:hover{background:#7dd3fc}
.btn-danger{background:#ef4444;color:#fff}
.btn-danger:hover{background:#f87171}
hr{border:none;border-top:1px solid #334155;margin:24px 0}
</style>
</head>
<body>
<div class="card">
<h1>🚀 AttendTrack Installer</h1>
<p>This script creates the database and all required tables.</p>

<?php
require_once __DIR__ . '/config.php';

$action = $_POST['action'] ?? '';
$results = [];

function check($label, $ok, $detail = '') {
    $class = $ok ? 'ok' : 'err';
    $icon  = $ok ? '✅' : '❌';
    echo "<div class='step $class'><h3>$icon $label</h3>" . ($detail ? "<p>$detail</p>" : "") . "</div>";
}

if ($action === 'install') {
    // Connect without DB first
    try {
        $pdo = new PDO("mysql:host=".DB_HOST.";charset=utf8mb4", DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        check('MySQL Connection', true, 'Connected to MySQL server');

        // Create DB
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `".DB_NAME."` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `".DB_NAME."`");
        check('Database', true, 'Database "'.DB_NAME.'" ready');

        // Tables
        $tables = [
            "users" => "CREATE TABLE IF NOT EXISTS `users` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `username` VARCHAR(60) NOT NULL UNIQUE,
                `email` VARCHAR(120) NOT NULL UNIQUE,
                `password` VARCHAR(255) NOT NULL,
                `role` ENUM('user','admin') DEFAULT 'user',
                `phone` VARCHAR(30) DEFAULT NULL,
                `department` VARCHAR(80) DEFAULT NULL,
                `profile_pic` VARCHAR(200) DEFAULT NULL,
                `is_active` TINYINT(1) DEFAULT 1,
                `created_at` DATETIME NOT NULL,
                `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            "attendance" => "CREATE TABLE IF NOT EXISTS `attendance` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `user_id` INT UNSIGNED NOT NULL,
                `work_date` DATE NOT NULL,
                `checkin_time` TIME DEFAULT NULL,
                `checkin_lat` DECIMAL(10,7) DEFAULT NULL,
                `checkin_lng` DECIMAL(10,7) DEFAULT NULL,
                `checkin_photo` VARCHAR(200) DEFAULT NULL,
                `checkout_time` TIME DEFAULT NULL,
                `checkout_lat` DECIMAL(10,7) DEFAULT NULL,
                `checkout_lng` DECIMAL(10,7) DEFAULT NULL,
                `checkout_photo` VARCHAR(200) DEFAULT NULL,
                `ot_checkin_time` TIME DEFAULT NULL,
                `ot_checkin_lat` DECIMAL(10,7) DEFAULT NULL,
                `ot_checkin_lng` DECIMAL(10,7) DEFAULT NULL,
                `ot_checkin_photo` VARCHAR(200) DEFAULT NULL,
                `ot_checkout_time` TIME DEFAULT NULL,
                `ot_checkout_lat` DECIMAL(10,7) DEFAULT NULL,
                `ot_checkout_lng` DECIMAL(10,7) DEFAULT NULL,
                `ot_checkout_photo` VARCHAR(200) DEFAULT NULL,
                `status` ENUM('present','absent','leave','holiday') DEFAULT 'present',
                `notes` TEXT DEFAULT NULL,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY `user_date` (`user_id`,`work_date`),
                FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            "password_resets" => "CREATE TABLE IF NOT EXISTS `password_resets` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `email` VARCHAR(120) NOT NULL,
                `token` VARCHAR(100) NOT NULL UNIQUE,
                `created_at` DATETIME NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        ];

        foreach ($tables as $name => $sql) {
            $pdo->exec($sql);
            check("Table: $name", true, "Created/verified successfully");
        }

        // Default admin
        $adminPwd = password_hash('Admin@123', PASSWORD_BCRYPT);
        $pdo->exec("INSERT IGNORE INTO `users` (username,email,password,role,created_at) VALUES ('admin','admin@attendtrack.com','$adminPwd','admin',NOW())");
        check('Default Admin', true, 'Username: <strong>admin</strong> | Password: <strong>Admin@123</strong> — <em>Change immediately!</em>');

        // Uploads dir
        if (!is_dir(UPLOAD_DIR)) {
            mkdir(UPLOAD_DIR, 0755, true);
            file_put_contents(UPLOAD_DIR . '.htaccess', "Options -Indexes\nDeny from all\n<FilesMatch \"\.(jpg|jpeg|png|gif|webp)$\">\n  Allow from all\n</FilesMatch>");
        }
        check('Uploads Directory', true, UPLOAD_DIR . ' created with .htaccess protection');

        echo "<hr><div style='text-align:center'><p style='margin-bottom:16px;color:#22c55e;font-size:1.1rem'>✅ Installation complete!</p><a href='index.php' class='btn'>Go to Login →</a></div>";

    } catch (PDOException $e) {
        check('Installation Failed', false, e($e->getMessage()));
    }

} else {
    // Show pre-checks
    echo "<div class='step'><h3>📋 Pre-Installation Checks</h3><p>Verify your config.php settings before installing.</p></div>";

    $phpOk = version_compare(PHP_VERSION, '7.4', '>=');
    check('PHP Version ≥ 7.4', $phpOk, 'Current: ' . PHP_VERSION);

    $pdoOk = extension_loaded('pdo_mysql');
    check('PDO MySQL Extension', $pdoOk, $pdoOk ? 'Available' : 'Install pdo_mysql extension');

    $gdOk = extension_loaded('gd');
    check('GD Extension', $gdOk, $gdOk ? 'Available (needed for image handling)' : 'GD not found — images may not work');

    $uploadOk = is_writable(dirname(UPLOAD_DIR));
    check('Uploads Directory Writable', $uploadOk, $uploadOk ? 'Parent dir is writable' : 'Make the uploads parent directory writable (chmod 755)');

    echo "<hr><h3 style='margin-bottom:12px;color:#f1f5f9'>Configuration</h3>";
    echo "<pre>DB_HOST = " . DB_HOST . "\nDB_NAME = " . DB_NAME . "\nDB_USER = " . DB_USER . "\nAPP_URL = " . APP_URL . "\nTIMEZONE = " . TIMEZONE . "</pre>";

    echo "<hr><form method='POST' style='text-align:center'><input type='hidden' name='action' value='install'><p style='margin-bottom:16px;color:#f59e0b'>⚠️ This will create the database. Existing data will NOT be lost (CREATE IF NOT EXISTS).</p><button type='submit' class='btn'>🚀 Install Now</button></form>";
}
?>
</div>
</body>
</html>
