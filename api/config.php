<?php
// ============================================================
// ATTENDANCE APP – Configuration (Vercel Postgres / Neon)
// ============================================================

ob_start();

// ── App settings ─────────────────────────────────────────────
define('APP_NAME', 'AttendTrack');
define('APP_URL',  'https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));

define('UPLOAD_DIR', '/tmp/uploads/');
define('UPLOAD_URL', '/uploads/');

define('SESSION_TIMEOUT', 3600);
define('TIMEZONE', 'Asia/Jakarta');

date_default_timezone_set(TIMEZONE);

// ── Database Session Handler (required for Vercel serverless) ─
// Vercel runs each request on a different instance — /tmp doesn't persist.
// We store sessions in the postgres php_sessions table instead.
class DBSessionHandler implements SessionHandlerInterface {
    private $pdo;

    public function open($path, $name): bool {
        try {
            $databaseUrl = $_ENV['postgresql://postgres:VGFduleSXzUqUolfpVvCTnYkQupfbkJb@postgres.railway.internal:5432/railway'] ?? getenv('postgresql://postgres:VGFduleSXzUqUolfpVvCTnYkQupfbkJb@postgres.railway.internal:5432/railway');
            if ($databaseUrl) {
                $parsed   = parse_url($databaseUrl);
                $host     = $parsed['postgres.railway.internal'];
                $dbname   = ltrim($parsed['path'], '/');
                $user     = $parsed['postgres'];
                $password = $parsed['VGFduleSXzUqUolfpVvCTnYkQupfbkJb'];
                $port     = $parsed['port'] ?? '5432';
            } else {
                $host     = $_ENV['PGHOST']     ?? getenv('PGHOST');
                $dbname   = $_ENV['PGDATABASE'] ?? getenv('PGDATABASE');
                $user     = $_ENV['PGUSER']     ?? getenv('PGUSER');
                $password = $_ENV['PGPASSWORD'] ?? getenv('PGPASSWORD');
                $port     = $_ENV['PGPORT']     ?? getenv('PGPORT') ?? '5432';
            }
            $dsn = "pgsql:host={$host};port={$port};dbname={$dbname};sslmode=require";
            $this->pdo = new PDO($dsn, $user, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
        } catch (Exception $e) {
            return false;
        }
        return true;
    }

    public function close(): bool { return true; }

    public function read($id): string|false {
        try {
            $stmt = $this->pdo->prepare("SELECT data FROM php_sessions WHERE id = ?");
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? $row['data'] : '';
        } catch (Exception $e) { return ''; }
    }

    public function write($id, $data): bool {
        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO php_sessions (id, data, updated_at)
                 VALUES (?, ?, NOW())
                 ON CONFLICT (id) DO UPDATE SET data = EXCLUDED.data, updated_at = NOW()"
            );
            $stmt->execute([$id, $data]);
            return true;
        } catch (Exception $e) { return false; }
    }

    public function destroy($id): bool {
        try {
            $this->pdo->prepare("DELETE FROM php_sessions WHERE id = ?")->execute([$id]);
            return true;
        } catch (Exception $e) { return false; }
    }

    public function gc($max_lifetime): int|false {
        try {
            $stmt = $this->pdo->prepare(
                "DELETE FROM php_sessions WHERE updated_at < NOW() - INTERVAL '1 second' * ?"
            );
            $stmt->execute([$max_lifetime]);
            return $stmt->rowCount();
        } catch (Exception $e) { return false; }
    }
}

// ── Session ───────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    try {
        $handler = new DBSessionHandler();
        session_set_save_handler($handler, true);
    } catch (Exception $e) {
        // Fall back to default file-based sessions if DB unavailable
    }
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.gc_maxlifetime', SESSION_TIMEOUT);
    @session_start();
}

// ── Database connection (PDO PostgreSQL via Vercel Postgres) ──
function getDB() {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    try {
        // Support both individual PG vars and Railway's DATABASE_URL
        $databaseUrl = $_ENV['DATABASE_URL'] ?? getenv('DATABASE_URL');
        if ($databaseUrl) {
            $parsed   = parse_url($databaseUrl);
            $host     = $parsed['host'];
            $dbname   = ltrim($parsed['path'], '/');
            $user     = $parsed['user'];
            $password = $parsed['pass'];
            $port     = $parsed['port'] ?? '5432';
        } else {
            $host     = $_ENV['PGHOST']     ?? getenv('PGHOST');
            $dbname   = $_ENV['PGDATABASE'] ?? getenv('PGDATABASE');
            $user     = $_ENV['PGUSER']     ?? getenv('PGUSER');
            $password = $_ENV['PGPASSWORD'] ?? getenv('PGPASSWORD');
            $port     = $_ENV['PGPORT']     ?? getenv('PGPORT') ?? '5432';
        }

        $dsn = "pgsql:host={$host};port={$port};dbname={$dbname};sslmode=require";

        $opts = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // Must be TRUE for PostgreSQL on Vercel — prevents "cached plan must not
            // change result type" errors after column type changes (e.g. VARCHAR→TEXT)
            PDO::ATTR_EMULATE_PREPARES   => true,
        ];

        $pdo = new PDO($dsn, $user, $password, $opts);

    } catch (PDOException $e) {
        http_response_code(500);
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8">
              <title>Database Error</title>
              <style>body{font-family:sans-serif;max-width:600px;margin:80px auto;padding:20px}
              .box{background:#fee2e2;border:1px solid #fca5a5;border-radius:8px;padding:24px}
              h2{color:#991b1b;margin:0 0 12px}pre{font-size:.85rem;color:#7f1d1d;white-space:pre-wrap}</style>
              </head><body><div class="box">
              <h2>⚠️ Database Connection Failed</h2>
              <p>Check your Vercel Postgres environment variables (PGHOST, PGDATABASE, PGUSER, PGPASSWORD).</p>
              <pre>' . htmlspecialchars($e->getMessage()) . '</pre>
              </div></body></html>';
        exit;
    }
    return $pdo;
}

// ── Auth helpers ──────────────────────────────────────────────
function isLoggedIn() {
    if (!isset($_SESSION['user_id'], $_SESSION['last_activity'], $_SESSION['role'])) {
        return false;
    }
    if ((time() - $_SESSION['last_activity']) >= SESSION_TIMEOUT) {
        // Session expired — destroy it so it can't cause redirect loops
        session_unset();
        session_destroy();
        return false;
    }
    return true;
}

function isAdmin() {
    return isLoggedIn() && ($_SESSION['role'] ?? '') === 'admin';
}

function requireLogin() {
    if (!isLoggedIn()) {
        // Clear any stale session data before redirecting
        session_unset();
        header('Location: /?msg=session_expired');
        exit;
    }
    $_SESSION['last_activity'] = time();
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: /dashboard');
        exit;
    }
}

function redirect($url) {
    header('Location: ' . $url);
    exit;
}

// ── Helper: escape HTML ───────────────────────────────────────
function e($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}
