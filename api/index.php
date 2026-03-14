<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// If already logged in, redirect to appropriate page
// Guard: only redirect if we didn't just come from a redirect (prevents loops)
if (isLoggedIn() && !isset($_GET['msg'])) {
    $dest = isAdmin() ? '/admin/index' : '/dashboard';
    header('Location: ' . $dest);
    exit;
}

$error = '';
$success = '';

if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'session_expired') $error = 'Your session has expired. Please log in again.';
    if ($_GET['msg'] === 'logged_out')      $success = 'You have been logged out successfully.';
    if ($_GET['msg'] === 'registered')      $success = 'Account created! Please log in.';
    if ($_GET['msg'] === 'reset_ok')        $success = 'Password reset successfully. Please log in.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login    = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($login) || empty($password)) {
        $error = 'Please enter your username/email and password.';
    } else {
        // Find by username or email
        $user = getUserByUsername($login) ?: getUserByEmail($login);
        if ($user && password_verify($password, $user['password'])) {
            if (!$user['is_active']) {
                $error = 'Your account has been deactivated. Contact admin.';
            } else {
                $_SESSION['user_id']       = $user['id'];
                $_SESSION['username']      = $user['username'];
                $_SESSION['role']          = $user['role'];
                $_SESSION['last_activity'] = time();
                if ($user['role'] === 'admin') {
                    header('Location: /admin/index');
                } else {
                    header('Location: /dashboard');
                }
                exit;
            }
        } else {
            $error = 'Invalid credentials. Please check your username/email and password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login – <?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="/assets/css/style.css">
<style>
.form-footer { text-align: center; margin-top: 20px; font-size: .875rem; color: var(--txt2); }
.form-footer a { font-weight: 600; }
.forgot-link { text-align: right; margin-top: -12px; margin-bottom: 16px; }
.forgot-link a { font-size: .8rem; color: var(--txt3); }
.forgot-link a:hover { color: var(--primary); }
</style>
</head>
<body>
<div class="auth-wrapper">
  <!-- Brand Side -->
  <div class="auth-brand">
    <div class="logo-mark"><img src="/assets/logo.svg" alt="Logo" style="width:48px;height:48px;object-fit:contain;border-radius:8px;"></div>
    <h1>Track Every<br>Moment That<br>Matters</h1>
    <p>Smart attendance management with geo-verified check-ins and real-time reporting.</p>
    <div class="auth-features">
      <div class="auth-feature-item">
        <span>📍</span>
        <span>GPS-verified location tracking</span>
      </div>
      <div class="auth-feature-item">
        <span>📸</span>
        <span>Photo proof at every check-in</span>
      </div>
      <div class="auth-feature-item">
        <span>⏱️</span>
        <span>Overtime monitoring & records</span>
      </div>
      <div class="auth-feature-item">
        <span>📊</span>
        <span>Instant Excel reports export</span>
      </div>
    </div>
  </div>

  <!-- Form Side -->
  <div class="auth-form-side">
    <div class="auth-form-container">
      <h2>Welcome back</h2>
      <p class="subtitle">Sign in to your <?= e(APP_NAME) ?> account</p>

      <?php if ($error): ?>
        <div class="alert alert-danger">⚠️ <?= e($error) ?></div>
      <?php endif; ?>
      <?php if ($success): ?>
        <div class="alert alert-success">✅ <?= e($success) ?></div>
      <?php endif; ?>

      <form method="POST" novalidate>
        <div class="form-group">
          <label for="login">Username or Email</label>
          <div class="input-wrap">
            <input type="text" id="login" name="login"
                   value="<?= e($_POST['login'] ?? '') ?>"
                   placeholder="Enter username or email" required autocomplete="username">
          </div>
        </div>

        <div class="form-group">
          <label for="password">Password</label>
          <div class="input-wrap">
            <input type="password" id="password" name="password"
                   class="has-icon" placeholder="Enter your password" required autocomplete="current-password">
            <span class="input-icon" onclick="togglePassword('password', this)" title="Show/hide password">
              <svg id="eye-password" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </span>
          </div>
        </div>

        <div class="forgot-link">
          <a href="/forgot-password">Forgot password?</a>
        </div>

        <button type="submit" class="btn btn-primary btn-full btn-lg">
          Sign In
        </button>
      </form>

      <div class="form-footer">
        Don't have an account? <a href="/register">Create one</a>
      </div>

      <div class="divider"></div>
      <p class="text-muted text-center" style="font-size:.8rem;">
        🔐 Secure login · Your data is encrypted
      </p>
    </div>
  </div>
</div>

<script>
function togglePassword(inputId, icon) {
  const input = document.getElementById(inputId);
  const isPassword = input.type === 'password';
  input.type = isPassword ? 'text' : 'password';
  icon.innerHTML = isPassword
    ? `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>`
    : `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>`;
}
</script>
</body>
</html>
