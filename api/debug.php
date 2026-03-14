<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>PHP Info</h2>";
echo "PHP Version: " . phpversion() . "<br>";
echo "PDO drivers: " . implode(', ', PDO::getAvailableDrivers()) . "<br><br>";

echo "<h2>Environment Variables</h2>";
$vars = ['PGHOST','PGPORT','PGDATABASE','PGUSER','PGPASSWORD','DATABASE_URL'];
foreach ($vars as $v) {
    $val = $_ENV[$v] ?? getenv($v) ?? 'NOT SET';
    // Mask password
    if (in_array($v, ['PGPASSWORD','DATABASE_URL']) && $val !== 'NOT SET') {
        $val = substr($val, 0, 6) . '***';
    }
    echo "$v = $val<br>";
}

echo "<h2>Database Connection Test</h2>";
try {
    $databaseUrl = $_ENV['DATABASE_URL'] ?? getenv('DATABASE_URL');
    if ($databaseUrl) {
        $parsed   = parse_url($databaseUrl);
        $host     = $parsed['host'];
        $dbname   = ltrim($parsed['path'], '/');
        $user     = $parsed['user'];
        $password = $parsed['pass'];
        $port     = $parsed['port'] ?? '5432';
    } else {
        $host     = $_ENV['PGHOST']     ?? getenv('PGHOST')     ?? 'localhost';
        $dbname   = $_ENV['PGDATABASE'] ?? getenv('PGDATABASE') ?? 'postgres';
        $user     = $_ENV['PGUSER']     ?? getenv('PGUSER')     ?? 'root';
        $password = $_ENV['PGPASSWORD'] ?? getenv('PGPASSWORD') ?? '';
        $port     = $_ENV['PGPORT']     ?? getenv('PGPORT')     ?? '5432';
    }

    $dsn = "pgsql:host={$host};port={$port};dbname={$dbname};sslmode=require";
    $pdo = new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    echo "<span style='color:green'>✅ Connected successfully to PostgreSQL!</span><br>";
    echo "Host: $host | DB: $dbname | User: $user<br>";
} catch (Exception $e) {
    echo "<span style='color:red'>❌ Connection failed: " . htmlspecialchars($e->getMessage()) . "</span><br>";
}
