<?php
// includes/nav.php — Shared navigation/sidebar
// $pageTitle, $activePage should be set by including page

$user = getUserById($_SESSION['user_id']);
$initials = strtoupper(substr($user['username'] ?? 'U', 0, 2));
$role = $_SESSION['role'] ?? 'user';
$pic = $user['profile_pic'] ?? '';
$isDataUrl = str_starts_with($pic, 'data:');
?>
<div id="toast-container"></div>

<!-- Lightbox -->
<div class="lightbox" id="lightbox" onclick="closeLightbox()">
  <img id="lightbox-img" src="" alt="">
</div>

<div class="app-layout">
  <!-- Topbar -->
  <header class="topbar">
    <button class="hamburger" onclick="toggleSidebar()">☰</button>
    <div class="brand">
      <div class="icon">📋</div>
      <?= e(APP_NAME) ?>
    </div>

    <div class="topbar-date" id="topbar-datetime">
      <?= date('l, d M Y') ?>
    </div>

    <div class="topbar-right">
      <div class="dropdown">
        <button class="avatar-btn" onclick="toggleDropdown()">
          <div class="avatar" style="position:relative;overflow:hidden;width:36px;height:36px;border-radius:50%;flex-shrink:0;">
            <?php if ($isDataUrl): ?>
              <img src="<?= $pic ?>" alt="" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;object-position:center;border-radius:50%;">
            <?php else: ?>
              <?= e($initials) ?>
            <?php endif; ?>
          </div>
          <span class="avatar-name"><?= e($user['username']) ?></span>
          <span style="font-size:.75rem;color:var(--txt3)">▼</span>
        </button>
        <div class="dropdown-menu" id="user-dropdown">
          <a class="dropdown-item" href="/profile">👤 My Profile</a>
          <?php if ($role === 'admin'): ?>
          <a class="dropdown-item" href="/admin/index">⚙️ Admin Panel</a>
          <?php endif; ?>
          <a class="dropdown-item" href="/dashboard">🏠 Dashboard</a>
          <div class="dropdown-divider"></div>
          <a class="dropdown-item danger" href="/logout">🚪 Log Out</a>
        </div>
      </div>
    </div>
  </header>


  <!-- Sidebar -->
  <nav class="sidebar" id="sidebar">
    <div class="nav-section-label">Main</div>

    <a href="/dashboard.php"
       class="nav-item <?= ($activePage ?? '') === 'dashboard' ? 'active' : '' ?>">
      <span>🏠</span> Dashboard
    </a>

    <a href="/profile.php"
       class="nav-item <?= ($activePage ?? '') === 'profile' ? 'active' : '' ?>">
      <span>👤</span> My Profile
    </a>

    <?php if ($role === 'admin'): ?>
    <div class="nav-section-label">Admin</div>
    <a href="/admin/index.php"
       class="nav-item <?= ($activePage ?? '') === 'admin-dashboard' ? 'active' : '' ?>">
      <span>📊</span> Admin Dashboard
    </a>
    <a href="/admin/users.php"
       class="nav-item <?= ($activePage ?? '') === 'users' ? 'active' : '' ?>">
      <span>👥</span> User Management
    </a>
    <a href="/admin/reports.php"
       class="nav-item <?= ($activePage ?? '') === 'reports' ? 'active' : '' ?>">
      <span>📑</span> Reports & Export
    </a>
    <?php endif; ?>

    <div class="sidebar-footer">
      <a href="/logout.php" class="nav-item">
        <span>🚪</span> Log Out
      </a>
    </div>
  </nav>
