<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
requireLogin();

$activePage = 'profile';
$user = getUserById($_SESSION['user_id']);
$flash = getFlash();
$success = $error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $section = $_POST['section'] ?? '';

    // ── Profile Info ──
    if ($section === 'info') {
        $username   = trim($_POST['username'] ?? '');
        $email      = trim($_POST['email'] ?? '');
        $phone      = trim($_POST['phone'] ?? '');
        $department = trim($_POST['department'] ?? '');

        if (empty($username) || empty($email)) {
            $error = 'Username and email are required.';
        } elseif (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username)) {
            $error = 'Username must be 3–30 characters (letters, numbers, underscore).';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email address.';
        } else {
            // Check uniqueness (exclude self)
            $db = getDB();
            $uCheck = $db->prepare("SELECT id FROM users WHERE username=? AND id!=?");
            $uCheck->execute([$username, $user['id']]);
            $eCheck = $db->prepare("SELECT id FROM users WHERE email=? AND id!=?");
            $eCheck->execute([$email, $user['id']]);

            if ($uCheck->fetch()) {
                $error = 'Username already taken.';
            } elseif ($eCheck->fetch()) {
                $error = 'Email already in use by another account.';
            } else {
                updateUser($user['id'], [
                    'username'   => $username,
                    'email'      => $email,
                    'phone'      => $phone,
                    'department' => $department,
                ]);
                $_SESSION['username'] = $username;
                $success = 'Profile updated successfully.';
                $user = getUserById($user['id']);
            }
        }
    }

    // ── Password ──
    if ($section === 'password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (!password_verify($current, $user['password'])) {
            $error = 'Current password is incorrect.';
        } elseif (strlen($new) < 6) {
            $error = 'New password must be at least 6 characters.';
        } elseif ($new !== $confirm) {
            $error = 'New passwords do not match.';
        } else {
            $hash = password_hash($new, PASSWORD_BCRYPT);
            updateUser($user['id'], ['password' => $hash]);
            $success = 'Password changed successfully.';
            $user = getUserById($user['id']);
        }
    }

    // ── Profile Photo ──
    if ($section === 'photo' && !empty($_POST['profile_pic_b64'])) {
        $dataUrl = $_POST['profile_pic_b64'];
        // Validate it's a real base64 data URL
        if (!preg_match('/^data:image\/(jpeg|png|gif|webp);base64,/i', $dataUrl)) {
            $error = 'Invalid image format.';
        } else {
            updateUser($user['id'], ['profile_pic' => $dataUrl]);
            $success = 'Profile photo updated.';
            $user = getUserById($user['id']);
        }
    }
}

$initials = strtoupper(substr($user['username'], 0, 2));
$workStats = getUserAttendance($user['id'], date('Y-m-01'), date('Y-m-t'), 100);
$totalDays = count($workStats);
$otDays = count(array_filter($workStats, fn($r) => $r['ot_checkin_time']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Profile – <?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="/assets/css/style.css">
<style>
.profile-grid { display: grid; grid-template-columns: 1fr 2fr; gap: 24px; }
@media(max-width:768px){ .profile-grid { grid-template-columns: 1fr; } }
.photo-upload-zone {
  border: 2px dashed var(--border2);
  border-radius: var(--radius);
  padding: 24px;
  text-align: center;
  cursor: pointer;
  transition: border-color var(--transition), background var(--transition);
}
.photo-upload-zone:hover { border-color: var(--primary); background: var(--primary-xl); }
.month-stat { text-align: center; }
.month-stat .num { font-size: 2rem; font-weight: 700; color: var(--primary); }
.month-stat .label { font-size: .8rem; color: var(--txt3); }
.month-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
</style>
</head>
<body>

<?php include __DIR__ . '/includes/nav.php'; ?>

  <main class="main-content">
    <div class="page-header">
      <h1>My Profile</h1>
      <p>Manage your account information and settings</p>
    </div>

    <?php if ($success): ?>
      <div class="alert alert-success">✅ <?= e($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="alert alert-danger">⚠️ <?= e($error) ?></div>
    <?php endif; ?>

    <!-- Profile Header -->
    <div class="profile-header">
      <div class="profile-avatar">
        <?php 
          $pic = $user['profile_pic'] ?? '';
          $isDataUrl = str_starts_with($pic, 'data:');
        ?>
        <?php if ($pic && $isDataUrl): ?>
          <img src="<?= $pic ?>" alt="Profile">
        <?php else: ?>
          <?= e($initials) ?>
        <?php endif; ?>
      </div>
      <div>
        <h2 style="font-size:1.4rem;font-weight:700"><?= e($user['username']) ?></h2>
        <p style="color:var(--txt2)"><?= e($user['email']) ?></p>
        <div style="display:flex;gap:12px;margin-top:8px;flex-wrap:wrap">
          <?php if ($user['department']): ?>
            <span class="badge badge-blue">🏢 <?= e($user['department']) ?></span>
          <?php endif; ?>
          <?php if ($user['phone']): ?>
            <span class="badge badge-grey">📞 <?= e($user['phone']) ?></span>
          <?php endif; ?>
          <span class="badge <?= $_SESSION['role'] === 'admin' ? 'badge-purple' : 'badge-green' ?>">
            <?= $_SESSION['role'] === 'admin' ? '⚙️ Admin' : '👤 User' ?>
          </span>
        </div>
      </div>

      <div style="margin-left:auto;text-align:center">
        <div style="font-size:.8rem;color:var(--txt3);margin-bottom:4px">Member since</div>
        <div style="font-weight:600"><?= date('M Y', strtotime($user['created_at'])) ?></div>
      </div>
    </div>

    <!-- Month Stats -->
    <div class="card" style="margin-bottom:24px">
      <div class="card-header"><h2>📅 This Month's Summary</h2></div>
      <div class="card-body">
        <div class="month-stats">
          <div class="month-stat">
            <div class="num"><?= $totalDays ?></div>
            <div class="label">Days Present</div>
          </div>
          <div class="month-stat">
            <div class="num"><?= $otDays ?></div>
            <div class="label">OT Days</div>
          </div>
          <div class="month-stat">
            <div class="num"><?= date('t') - $totalDays ?></div>
            <div class="label">Days Absent</div>
          </div>
        </div>
      </div>
    </div>

    <div class="profile-grid">
      <!-- Left Column -->
      <div style="display:flex;flex-direction:column;gap:20px">

        <!-- Profile Photo -->
        <div class="card">
          <div class="card-header"><h2>📸 Profile Photo</h2></div>
          <div class="card-body">
            <!-- Hidden canvas used to resize image before upload -->
            <canvas id="resize-canvas" style="display:none"></canvas>
            <form method="POST" id="photo-form">
              <input type="hidden" name="section" value="photo">
              <input type="hidden" name="profile_pic_b64" id="profile_pic_b64">
              <label class="photo-upload-zone" for="profile_pic_input" id="upload-label">
                <div style="font-size:2.5rem;margin-bottom:8px">📷</div>
                <div style="font-weight:600;margin-bottom:4px">Click to upload photo</div>
                <div class="text-muted">JPG, PNG, GIF, WEBP · Max 5MB</div>
              </label>
              <input type="file" id="profile_pic_input" accept="image/*" style="display:none"
                     onchange="resizeAndUpload(this)">
            </form>
          </div>
        </div>

        <!-- Quick Info -->
        <div class="card">
          <div class="card-header"><h2>ℹ️ Account Info</h2></div>
          <div class="card-body" style="display:flex;flex-direction:column;gap:12px">
            <div>
              <div class="text-muted" style="font-size:.78rem;margin-bottom:2px">USER ID</div>
              <div style="font-family:'DM Mono',monospace;font-size:.9rem">#<?= $user['id'] ?></div>
            </div>
            <div>
              <div class="text-muted" style="font-size:.78rem;margin-bottom:2px">ROLE</div>
              <div><?= ucfirst($user['role']) ?></div>
            </div>
            <div>
              <div class="text-muted" style="font-size:.78rem;margin-bottom:2px">ACCOUNT STATUS</div>
              <span class="badge <?= $user['is_active'] ? 'badge-green' : 'badge-red' ?>">
                <?= $user['is_active'] ? 'Active' : 'Inactive' ?>
              </span>
            </div>
            <div>
              <div class="text-muted" style="font-size:.78rem;margin-bottom:2px">JOINED</div>
              <div><?= formatDate($user['created_at']) ?></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Right Column -->
      <div style="display:flex;flex-direction:column;gap:20px">

        <!-- Profile Info Form -->
        <div class="card">
          <div class="card-header"><h2>✏️ Edit Profile</h2></div>
          <div class="card-body">
            <form method="POST">
              <input type="hidden" name="section" value="info">
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
                <div class="form-group">
                  <label>Username</label>
                  <input type="text" name="username" value="<?= e($user['username']) ?>" required maxlength="30">
                </div>
                <div class="form-group">
                  <label>Email</label>
                  <input type="email" name="email" value="<?= e($user['email']) ?>" required>
                </div>
                <div class="form-group">
                  <label>Phone</label>
                  <input type="tel" name="phone" value="<?= e($user['phone'] ?? '') ?>" placeholder="+62 xxx">
                </div>
                <div class="form-group">
                  <label>Department</label>
                  <input type="text" name="department" value="<?= e($user['department'] ?? '') ?>" placeholder="e.g. Engineering">
                </div>
              </div>
              <button type="submit" class="btn btn-primary">💾 Save Changes</button>
            </form>
          </div>
        </div>

        <!-- Change Password Form -->
        <div class="card">
          <div class="card-header"><h2>🔒 Change Password</h2></div>
          <div class="card-body">
            <form method="POST">
              <input type="hidden" name="section" value="password">
              <div class="form-group">
                <label>Current Password</label>
                <div class="input-wrap">
                  <input type="password" name="current_password" id="cur-pwd" class="has-icon"
                         placeholder="Enter current password" required>
                  <span class="input-icon" onclick="togglePassword('cur-pwd', this)">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                  </span>
                </div>
              </div>
              <div class="form-group">
                <label>New Password</label>
                <div class="input-wrap">
                  <input type="password" name="new_password" id="new-pwd" class="has-icon"
                         placeholder="Min. 6 characters" required>
                  <span class="input-icon" onclick="togglePassword('new-pwd', this)">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                  </span>
                </div>
              </div>
              <div class="form-group">
                <label>Confirm New Password</label>
                <div class="input-wrap">
                  <input type="password" name="confirm_password" id="conf-pwd" class="has-icon"
                         placeholder="Repeat new password" required>
                  <span class="input-icon" onclick="togglePassword('conf-pwd', this)">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                  </span>
                </div>
              </div>
              <button type="submit" class="btn btn-primary">🔑 Update Password</button>
            </form>
          </div>
        </div>
      </div>
    </div>
  </main>
</div>

<div id="toast-container"></div>

<script>
function togglePassword(inputId, icon) {
  const input = document.getElementById(inputId);
  const isPassword = input.type === 'password';
  input.type = isPassword ? 'text' : 'password';
  icon.innerHTML = isPassword
    ? `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>`
    : `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>`;
}
function toggleDropdown() {
  document.getElementById('user-dropdown').classList.toggle('open');
}
document.addEventListener('click', e => {
  if (!e.target.closest('.dropdown')) document.getElementById('user-dropdown').classList.remove('open');
});
function toggleSidebar() {
  const sb = document.getElementById('sidebar');
  const bd = document.getElementById('sidebar-backdrop');
  const isOpen = sb.classList.contains('mobile-open');
  if (isOpen) {
    sb.classList.remove('mobile-open');
    bd.classList.remove('visible');
  } else {
    sb.classList.add('mobile-open');
    bd.classList.add('visible');
  }
}
function closeSidebar() {
  document.getElementById('sidebar').classList.remove('mobile-open');
  document.getElementById('sidebar-backdrop').classList.remove('visible');
}
</script>

<script>
function resizeAndUpload(input) {
  const file = input.files[0];
  if (!file) return;

  const label = document.getElementById('upload-label');
  label.innerHTML = '<div style="font-size:1.5rem">⏳</div><div>Resizing...</div>';

  const reader = new FileReader();
  reader.onload = function(e) {
    const img = new Image();
    img.onload = function() {
      // Resize to max 300x300 for avatar use
      const MAX = 300;
      let w = img.width, h = img.height;
      if (w > h) { if (w > MAX) { h = h * MAX / w; w = MAX; } }
      else        { if (h > MAX) { w = w * MAX / h; h = MAX; } }

      const canvas = document.getElementById('resize-canvas');
      canvas.width  = w;
      canvas.height = h;
      const ctx = canvas.getContext('2d');
      ctx.drawImage(img, 0, 0, w, h);

      const dataUrl = canvas.toDataURL('image/jpeg', 0.85);
      document.getElementById('profile_pic_b64').value = dataUrl;

      label.innerHTML = '<div style="font-size:1.5rem">✅</div><div style="font-weight:600">Photo ready — saving...</div>';
      document.getElementById('photo-form').submit();
    };
    img.src = e.target.result;
  };
  reader.readAsDataURL(file);
}
</script>
</body>
</html>
