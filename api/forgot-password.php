<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

if (isLoggedIn()) {
    header('Location: /dashboard');
    exit;
}

$step = isset($_GET['token']) ? 'reset' : 'forgot';
$error = $success = '';

// Handle forgot password
if ($step === 'forgot' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $token = createResetToken($email);
        if ($token) {
            // In production, email this link. For demo, show it.
            $resetLink = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/forgot-password.php?token=' . $token;
            $success = "Reset link generated! In production, this would be emailed. <br><strong>Demo link:</strong> <a href='$resetLink'>Click here to reset</a>";
        } else {
            // Don't reveal if email exists or not (security)
            $success = 'If that email is registered, a reset link has been sent.';
        }
    }
}

// Handle password reset
if ($step === 'reset' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $token    = trim($_GET['token'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm'] ?? '';
    if (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        if (resetPassword($token, $password)) {
            header('Location: /?msg=reset_ok');
            exit;
        } else {
            $error = 'Invalid or expired reset link. Please request a new one.';
        }
    }
}

// Validate token for reset form display
if ($step === 'reset' && !$_SERVER['REQUEST_METHOD'] === 'POST') {
    $tokenData = validateResetToken($_GET['token'] ?? '');
    if (!$tokenData) {
        $step = 'expired';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>
  <?= $step === 'reset' ? 'Reset Password' : 'Forgot Password' ?> – <?= e(APP_NAME) ?>
</title>
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<div class="auth-wrapper">
  <div class="auth-brand">
    <div class="logo-mark">🔐</div>
    <h1><?= $step === 'reset' ? 'Set New<br>Password' : 'Password<br>Recovery' ?></h1>
    <p><?= $step === 'reset'
      ? 'Create a strong new password to secure your account.'
      : 'Enter your registered email and we\'ll send you a reset link.' ?></p>
    <div class="auth-features">
      <div class="auth-feature-item"><span>🛡️</span><span>Secure reset process</span></div>
      <div class="auth-feature-item"><span>⏰</span><span>Links expire in 1 hour</span></div>
      <div class="auth-feature-item"><span>🔒</span><span>Passwords are encrypted</span></div>
    </div>
  </div>

  <div class="auth-form-side">
    <div class="auth-form-container">

      <?php if ($step === 'expired'): ?>
        <h2>Link Expired</h2>
        <p class="subtitle">This reset link is invalid or has expired.</p>
        <div class="alert alert-danger">⚠️ Reset links are only valid for 1 hour.</div>
        <a href="/forgot-password" class="btn btn-primary btn-full btn-lg">
          Request New Link
        </a>

      <?php elseif ($step === 'reset'): ?>
        <h2>Set New Password</h2>
        <p class="subtitle">Enter your new password below</p>

        <?php if ($error): ?>
          <div class="alert alert-danger">⚠️ <?= e($error) ?></div>
        <?php endif; ?>

        <form method="POST" novalidate>
          <div class="form-group">
            <label for="password">New Password</label>
            <div class="input-wrap">
              <input type="password" id="password" name="password" class="has-icon"
                     placeholder="Min. 6 characters" required>
              <span class="input-icon" onclick="togglePassword('password', this)">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
              </span>
            </div>
          </div>
          <div class="form-group">
            <label for="confirm">Confirm New Password</label>
            <div class="input-wrap">
              <input type="password" id="confirm" name="confirm" class="has-icon"
                     placeholder="Repeat password" required>
              <span class="input-icon" onclick="togglePassword('confirm', this)">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
              </span>
            </div>
          </div>
          <button type="submit" class="btn btn-primary btn-full btn-lg">
            Reset Password
          </button>
        </form>

      <?php else: // Forgot step ?>
        <h2>Forgot password?</h2>
        <p class="subtitle">Enter your email to receive a reset link</p>

        <?php if ($error): ?>
          <div class="alert alert-danger">⚠️ <?= e($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
          <div class="alert alert-success">✅ <?= $success ?></div>
        <?php endif; ?>

        <form method="POST" novalidate>
          <div class="form-group">
            <label for="email">Registered Email</label>
            <input type="email" id="email" name="email"
                   value="<?= e($_POST['email'] ?? '') ?>"
                   placeholder="you@email.com" required>
          </div>
          <button type="submit" class="btn btn-primary btn-full btn-lg">
            Send Reset Link
          </button>
        </form>

        <div style="text-align:center;margin-top:20px;font-size:.875rem">
          <a href="/">← Back to Login</a>
        </div>
      <?php endif; ?>

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
