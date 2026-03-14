<?php
// PHP built-in server router — mirrors vercel.json routes
error_reporting(E_ALL);
ini_set("display_errors", "1");

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = ltrim($uri, '/');

// Serve static assets directly
if (preg_match('/^assets\//', $uri)) {
    $file = __DIR__ . '/public/' . $uri;
    if (file_exists($file)) return false;
}

// Route map
$routes = [
    'admin/users'            => 'api/admin/users.php',
    'admin/reports'          => 'api/admin/reports.php',
    'admin/index'            => 'api/admin/index.php',
    'admin'                  => 'api/admin/index.php',
    'api/attendance-action'  => 'api/attendance-action.php',
    'dashboard'              => 'api/dashboard.php',
    'register'               => 'api/register.php',
    'logout'                 => 'api/logout.php',
    'profile'                => 'api/profile.php',
    'forgot-password'        => 'api/forgot-password.php',
    'install'                => 'api/install.php',
    'debug-photo'            => 'api/debug-photo.php',
    'debug'                  => 'api/debug.php',
];

foreach ($routes as $pattern => $target) {
    if ($uri === $pattern || str_starts_with($uri, $pattern . '/') || str_starts_with($uri, $pattern . '?')) {
        require __DIR__ . '/' . $target;
        exit;
    }
}

// Default: login page
require __DIR__ . '/api/index.php';
