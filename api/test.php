<?php
// Completely standalone - no dependencies
echo "<h2>PHP is working!</h2>";
echo "PHP Version: " . phpversion() . "<br>";
echo "PDO drivers: " . implode(', ', PDO::getAvailableDrivers()) . "<br><br>";

echo "<h2>Env Vars</h2>";
foreach (['PGHOST','PGPORT','PGDATABASE','PGUSER','PGPASSWORD','DATABASE_URL'] as $v) {
    $val = $_ENV[$v] ?? getenv($v) ?? 'NOT SET';
    if (strpos($v, 'PASS') !== false || $v === 'DATABASE_URL') {
        $val = $val !== 'NOT SET' ? substr($val,0,8).'***' : 'NOT SET';
    }
    echo "$v = <b>$val</b><br>";
}

echo "<br><h2>DB Test</h2>";
try {
    $url = $_ENV['DATABASE_URL'] ?? getenv('DATABASE_URL');
    if ($url) {
        $p = parse_url($url);
        $dsn = "pgsql:host={$p['host']};port=".($p['port']??5432).";dbname=".ltrim($p['path'],'/').";sslmode=require";
        $pdo = new PDO($dsn, $p['user'], $p['pass']);
        echo "<span style='color:green'>✅ DB Connected!</span>";
    } else {
        $h = getenv('PGHOST'); $d = getenv('PGDATABASE'); $u = getenv('PGUSER'); $pw = getenv('PGPASSWORD'); $pt = getenv('PGPORT')?:'5432';
        $dsn = "pgsql:host=$h;port=$pt;dbname=$d;sslmode=require";
        $pdo = new PDO($dsn, $u, $pw);
        echo "<span style='color:green'>✅ DB Connected!</span>";
    }
} catch (Exception $e) {
    echo "<span style='color:red'>❌ " . htmlspecialchars($e->getMessage()) . "</span>";
}
