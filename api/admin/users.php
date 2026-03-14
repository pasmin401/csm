<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';
requireAdmin();

$activePage = 'users';
$success = $error = '';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ── Create User ────────────────────────────────────────────
    if ($action === 'create') {
        $username    = trim($_POST['username']    ?? '');
        $email       = trim($_POST['email']       ?? '');
        $password    = $_POST['password']         ?? '';
        $role        = in_array($_POST['role'] ?? '', ['user','admin']) ? $_POST['role'] : 'user';
        $department  = trim($_POST['department']  ?? '');
        $phone       = trim($_POST['phone']       ?? '');
        $work_start  = trim($_POST['work_start']  ?? '');
        $work_end    = trim($_POST['work_end']     ?? '');

        if (!$username || !$email || !$password) {
            $error = 'Username, email and password are required.';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters.';
        } elseif (getUserByUsername($username)) {
            $error = 'Username already taken.';
        } elseif (getUserByEmail($email)) {
            $error = 'Email already registered.';
        } else {
            try {
                $uid = createUser($username, $email, $password, $role);
                if ($uid) {
                    $extra = array_filter([
                        'department' => $department,
                        'phone'      => $phone,
                        'work_start' => $work_start ?: null,
                        'work_end'   => $work_end   ?: null,
                    ], fn($v) => $v !== null && $v !== '');
                    if ($extra) updateUser($uid, $extra);
                    $success = "User '<strong>$username</strong>' created successfully.";
                } else {
                    $error = 'Failed to create user. Please try again.';
                }
            } catch (PDOException $e) {
                $error = $e->getCode() == 23000
                    ? 'Username or email already exists.'
                    : 'Database error: ' . htmlspecialchars($e->getMessage());
            }
        }
    }

    // ── Edit User ──────────────────────────────────────────────
    if ($action === 'edit') {
        $uid         = (int)($_POST['user_id']    ?? 0);
        $username    = trim($_POST['username']    ?? '');
        $email       = trim($_POST['email']       ?? '');
        $role        = in_array($_POST['role'] ?? '', ['user','admin']) ? $_POST['role'] : 'user';
        $department  = trim($_POST['department']  ?? '');
        $phone       = trim($_POST['phone']       ?? '');
        $work_start  = trim($_POST['work_start']  ?? '');
        $work_end    = trim($_POST['work_end']     ?? '');
        $is_active   = isset($_POST['is_active']) ? 1 : 0;

        if (!$uid || !$username || !$email) {
            $error = 'Missing required fields.';
        } else {
            $db = getDB();
            $uChk = $db->prepare("SELECT id FROM users WHERE username=? AND id!=?");
            $uChk->execute([$username, $uid]);
            $eChk = $db->prepare("SELECT id FROM users WHERE email=? AND id!=?");
            $eChk->execute([$email, $uid]);

            if ($uChk->fetch()) {
                $error = 'Username already taken.';
            } elseif ($eChk->fetch()) {
                $error = 'Email already in use.';
            } else {
                $data = [
                    'username'   => $username,
                    'email'      => $email,
                    'role'       => $role,
                    'department' => $department,
                    'phone'      => $phone,
                    'work_start' => $work_start ?: null,
                    'work_end'   => $work_end   ?: null,
                    'is_active'  => $is_active,
                ];
                if (!empty($_POST['new_password'])) {
                    if (strlen($_POST['new_password']) < 6) {
                        $error = 'New password must be at least 6 characters.';
                    } else {
                        $data['password'] = password_hash($_POST['new_password'], PASSWORD_BCRYPT);
                    }
                }
                if (!$error) {
                    updateUser($uid, $data);
                    $success = 'User updated successfully.';
                }
            }
        }
    }

    // ── Toggle Active ──────────────────────────────────────────
    if ($action === 'toggle') {
        $uid = (int)($_POST['user_id'] ?? 0);
        $target = getUserById($uid);
        if ($target && $target['role'] !== 'admin') {
            updateUser($uid, ['is_active' => $target['is_active'] ? 0 : 1]);
            $success = 'User status updated.';
        }
    }

    // ── Delete ─────────────────────────────────────────────────
    if ($action === 'delete') {
        $uid = (int)($_POST['user_id'] ?? 0);
        deleteUser($uid);
        $success = 'User deleted.';
    }
}

// ── List users ─────────────────────────────────────────────────
$search  = trim($_GET['search'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$offset  = ($page - 1) * $perPage;
$total   = countUsers($search);
$pages   = ceil($total / $perPage);
$users   = getAllUsers($search, $perPage, $offset);

// Helper: format time for display
function fmtWorkTime($t) {
    return $t ? date('H:i', strtotime($t)) : null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>User Management – <?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="/assets/css/style.css">
<style>
/* Modal wider for more fields */
#create-modal .modal,
#edit-modal   .modal { max-width: 620px; }

.work-time-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}
.section-divider {
    font-size: .7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .08em;
    color: var(--txt3);
    padding: 12px 0 6px;
    border-top: 1px solid var(--border);
    margin-top: 4px;
}
.time-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: .78rem;
    font-family: 'DM Mono', monospace;
    font-weight: 500;
    background: var(--primary-lt);
    color: var(--primary-dk);
    padding: 3px 9px;
    border-radius: 20px;
    white-space: nowrap;
}
.time-badge.no-schedule {
    background: var(--border);
    color: var(--txt3);
}
</style>
</head>
<body>

<?php include __DIR__ . '/../includes/nav.php'; ?>

  <main class="main-content">
    <div class="page-header">
      <h1>👥 User Management</h1>
      <p>Manage employee accounts, roles and work schedules</p>
    </div>

    <?php if ($success): ?>
      <div class="alert alert-success">✅ <?= $success ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="alert alert-danger">⚠️ <?= e($error) ?></div>
    <?php endif; ?>

    <!-- Controls -->
    <div class="filters" style="margin-bottom:20px">
      <form method="GET" style="display:flex;gap:10px;flex:1;flex-wrap:wrap">
        <div class="search-wrap" style="flex:1;min-width:200px">
          <input type="text" name="search" value="<?= e($search) ?>"
                 placeholder="Search by name or email…" onchange="this.form.submit()">
        </div>
      </form>
      <button class="btn btn-primary" onclick="openModal('create-modal')">
        ➕ Add User
      </button>
    </div>

    <!-- Users Table -->
    <div class="card">
      <div class="card-header">
        <h2>All Users <span class="badge badge-blue" style="margin-left:8px"><?= $total ?></span></h2>
      </div>
      <div class="table-wrap">
        <?php if (empty($users)): ?>
          <div class="empty-state"><div class="icon">👥</div><p>No users found</p></div>
        <?php else: ?>
        <table>
          <thead>
            <tr>
              <th>#</th>
              <th>User</th>
              <th>Email</th>
              <th>Department</th>
              <th>Phone</th>
              <th>Work Schedule</th>
              <th>Role</th>
              <th>Status</th>
              <th>Joined</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($users as $i => $u): ?>
            <tr>
              <td class="text-muted"><?= $offset + $i + 1 ?></td>
              <td>
                <div style="display:flex;align-items:center;gap:10px">
                  <div style="width:34px;height:34px;border-radius:50%;
                              background:<?= $u['role']==='admin' ? 'var(--ot)' : 'var(--primary)' ?>;
                              color:#fff;display:flex;align-items:center;justify-content:center;
                              font-weight:700;font-size:.8rem;flex-shrink:0;overflow:hidden">
                    <?php if ($u['profile_pic']): ?>
                      <img src="<?= $u['profile_pic'] ?>"
                           alt="" style="width:100%;height:100%;object-fit:cover">
                    <?php else: ?>
                      <?= strtoupper(substr($u['username'], 0, 2)) ?>
                    <?php endif; ?>
                  </div>
                  <span style="font-weight:600"><?= e($u['username']) ?></span>
                </div>
              </td>
              <td><?= e($u['email']) ?></td>
              <td><?= e($u['department'] ?? '—') ?></td>
              <td><?= e($u['phone'] ?? '—') ?></td>
              <td>
                <?php
                  $ws = fmtWorkTime($u['work_start'] ?? null);
                  $we = fmtWorkTime($u['work_end']   ?? null);
                ?>
                <?php if ($ws && $we): ?>
                  <span class="time-badge">
                    🕐 <?= $ws ?> – <?= $we ?>
                  </span>
                <?php else: ?>
                  <span class="time-badge no-schedule">Not set</span>
                <?php endif; ?>
              </td>
              <td>
                <span class="badge <?= $u['role']==='admin' ? 'badge-purple' : 'badge-blue' ?>">
                  <?= ucfirst($u['role']) ?>
                </span>
              </td>
              <td>
                <span class="badge <?= $u['is_active'] ? 'badge-green' : 'badge-red' ?>">
                  <?= $u['is_active'] ? 'Active' : 'Inactive' ?>
                </span>
              </td>
              <td class="text-muted" style="font-size:.8rem">
                <?= date('d M Y', strtotime($u['created_at'])) ?>
              </td>
              <td>
                <div style="display:flex;gap:6px;flex-wrap:wrap">
                  <button class="btn btn-outline btn-sm"
                    onclick="editUser(<?= htmlspecialchars(json_encode($u), ENT_QUOTES) ?>)">
                    ✏️ Edit
                  </button>

                  <?php if ($u['role'] !== 'admin'): ?>
                  <form method="POST" style="display:inline">
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                    <button type="submit"
                            class="btn btn-sm <?= $u['is_active'] ? 'btn-outline' : 'btn-success' ?>"
                            onclick="return confirm('Toggle this user\'s status?')">
                      <?= $u['is_active'] ? '🚫 Deactivate' : '✅ Activate' ?>
                    </button>
                  </form>

                  <form method="POST" style="display:inline">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                    <button type="submit" class="btn btn-danger btn-sm"
                            onclick="return confirm('Permanently delete this user? All attendance records will also be deleted.')">
                      🗑
                    </button>
                  </form>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>
      </div>

      <!-- Pagination -->
      <?php if ($pages > 1): ?>
      <div class="pagination" style="padding:16px 24px">
        <?php if ($page > 1): ?>
          <a href="?page=<?= $page-1 ?>&search=<?= urlencode($search) ?>" class="page-btn">‹</a>
        <?php endif; ?>
        <?php for ($p = max(1,$page-2); $p <= min($pages,$page+2); $p++): ?>
          <a href="?page=<?= $p ?>&search=<?= urlencode($search) ?>"
             class="page-btn <?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
        <?php endfor; ?>
        <?php if ($page < $pages): ?>
          <a href="?page=<?= $page+1 ?>&search=<?= urlencode($search) ?>" class="page-btn">›</a>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
  </main>
</div>

<!-- ═══════════════════════════════════════════════════════════
     CREATE USER MODAL
════════════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="create-modal">
  <div class="modal">
    <div class="modal-header">
      <h3>➕ Add New User</h3>
      <button class="modal-close" onclick="closeModal('create-modal')">×</button>
    </div>
    <div class="modal-body">
      <form method="POST">
        <input type="hidden" name="action" value="create">

        <!-- Account Info -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <div class="form-group">
            <label>Username <span style="color:var(--danger)">*</span></label>
            <input type="text" name="username" required maxlength="30" placeholder="john_doe" autocomplete="off">
          </div>
          <div class="form-group">
            <label>Email <span style="color:var(--danger)">*</span></label>
            <input type="email" name="email" required placeholder="user@email.com" autocomplete="off">
          </div>
          <div class="form-group">
            <label>Password <span style="color:var(--danger)">*</span></label>
            <div class="input-wrap">
              <input type="password" name="password" id="create-pwd" class="has-icon"
                     required placeholder="Min. 6 characters" autocomplete="new-password">
              <span class="input-icon" onclick="togglePassword('create-pwd', this)" title="Show/hide">
                <?= eyeIconSvg() ?>
              </span>
            </div>
          </div>
          <div class="form-group">
            <label>Role</label>
            <select name="role">
              <option value="user">👤 User</option>
              <option value="admin">⚙️ Admin</option>
            </select>
          </div>
          <div class="form-group">
            <label>Department</label>
            <input type="text" name="department" placeholder="e.g. Engineering">
          </div>
          <div class="form-group">
            <label>Phone</label>
            <input type="tel" name="phone" placeholder="+62 xxx-xxxx-xxxx">
          </div>
        </div>

        <!-- Work Schedule -->
        <div class="section-divider">🕐 Work Schedule</div>
        <div class="work-time-row">
          <div class="form-group">
            <label for="create-work-start">
              Work Start Time
              <span style="font-size:.75rem;color:var(--txt3);font-weight:400">(shift begins)</span>
            </label>
            <input type="time" name="work_start" id="create-work-start" value="08:00">
          </div>
          <div class="form-group">
            <label for="create-work-end">
              Work End Time
              <span style="font-size:.75rem;color:var(--txt3);font-weight:400">(shift ends)</span>
            </label>
            <input type="time" name="work_end" id="create-work-end" value="17:00">
          </div>
        </div>
        <div id="create-schedule-preview" style="font-size:.82rem;color:var(--txt2);margin-bottom:12px;padding:8px 12px;background:var(--primary-xl);border-radius:8px;border:1px solid var(--primary-lt)">
          📅 Schedule preview will appear here
        </div>

        <div class="modal-actions" style="margin-top:4px">
          <button type="button" class="btn btn-outline" onclick="closeModal('create-modal')" style="flex:1">
            Cancel
          </button>
          <button type="submit" class="btn btn-primary" style="flex:2">
            ✅ Create User
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     EDIT USER MODAL
════════════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="edit-modal">
  <div class="modal">
    <div class="modal-header">
      <h3>✏️ Edit User</h3>
      <button class="modal-close" onclick="closeModal('edit-modal')">×</button>
    </div>
    <div class="modal-body">
      <form method="POST">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="user_id" id="edit-user-id">

        <!-- Account Info -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <div class="form-group">
            <label>Username <span style="color:var(--danger)">*</span></label>
            <input type="text" name="username" id="edit-username" required maxlength="30">
          </div>
          <div class="form-group">
            <label>Email <span style="color:var(--danger)">*</span></label>
            <input type="email" name="email" id="edit-email" required>
          </div>
          <div class="form-group">
            <label>New Password <small style="color:var(--txt3)">(blank = keep current)</small></label>
            <div class="input-wrap">
              <input type="password" name="new_password" id="edit-pwd" class="has-icon"
                     placeholder="Leave blank to keep" autocomplete="new-password">
              <span class="input-icon" onclick="togglePassword('edit-pwd', this)" title="Show/hide">
                <?= eyeIconSvg() ?>
              </span>
            </div>
          </div>
          <div class="form-group">
            <label>Role</label>
            <select name="role" id="edit-role">
              <option value="user">👤 User</option>
              <option value="admin">⚙️ Admin</option>
            </select>
          </div>
          <div class="form-group">
            <label>Department</label>
            <input type="text" name="department" id="edit-department" placeholder="e.g. Engineering">
          </div>
          <div class="form-group">
            <label>Phone</label>
            <input type="tel" name="phone" id="edit-phone" placeholder="+62 xxx">
          </div>
        </div>

        <!-- Work Schedule -->
        <div class="section-divider">🕐 Work Schedule</div>
        <div class="work-time-row">
          <div class="form-group">
            <label for="edit-work-start">
              Work Start Time
              <span style="font-size:.75rem;color:var(--txt3);font-weight:400">(shift begins)</span>
            </label>
            <input type="time" name="work_start" id="edit-work-start">
          </div>
          <div class="form-group">
            <label for="edit-work-end">
              Work End Time
              <span style="font-size:.75rem;color:var(--txt3);font-weight:400">(shift ends)</span>
            </label>
            <input type="time" name="work_end" id="edit-work-end">
          </div>
        </div>
        <div id="edit-schedule-preview" style="font-size:.82rem;color:var(--txt2);margin-bottom:12px;padding:8px 12px;background:var(--primary-xl);border-radius:8px;border:1px solid var(--primary-lt)">
          📅 Schedule preview will appear here
        </div>

        <!-- Active status -->
        <div class="form-group" style="margin-bottom:0">
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:500">
            <input type="checkbox" name="is_active" id="edit-active" value="1"
                   style="width:16px;height:16px;accent-color:var(--primary)">
            Account Active
          </label>
        </div>

        <div class="modal-actions" style="margin-top:16px">
          <button type="button" class="btn btn-outline" onclick="closeModal('edit-modal')" style="flex:1">
            Cancel
          </button>
          <button type="submit" class="btn btn-primary" style="flex:2">
            💾 Save Changes
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<div id="toast-container"></div>

<?php
// Helper: render SVG eye icon inline (avoids repeating the big string)
function eyeIconSvg() {
    return '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
         fill="none" stroke="currentColor" stroke-width="2"
         stroke-linecap="round" stroke-linejoin="round">
      <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
      <circle cx="12" cy="12" r="3"/>
    </svg>';
}
?>

<script>
// ── Modal helpers ───────────────────────────────────────────
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

// ── Populate edit modal ─────────────────────────────────────
function editUser(u) {
  document.getElementById('edit-user-id').value    = u.id;
  document.getElementById('edit-username').value   = u.username  || '';
  document.getElementById('edit-email').value      = u.email     || '';
  document.getElementById('edit-role').value       = u.role      || 'user';
  document.getElementById('edit-department').value = u.department|| '';
  document.getElementById('edit-phone').value      = u.phone     || '';
  document.getElementById('edit-active').checked   = (u.is_active == 1);
  document.getElementById('edit-pwd').value        = '';

  // Work time — stored as "HH:MM:SS", input[type=time] needs "HH:MM"
  document.getElementById('edit-work-start').value = toHHMM(u.work_start);
  document.getElementById('edit-work-end').value   = toHHMM(u.work_end);

  updatePreview('edit');
  openModal('edit-modal');
}

// ── Time helpers ────────────────────────────────────────────
function toHHMM(t) {
  if (!t) return '';
  // "08:00:00" → "08:00"  |  "08:00" → "08:00"
  return t.substring(0, 5);
}

function toAmPm(hhmm) {
  if (!hhmm) return '—';
  // Return as-is in 24-hour format (HH:MM)
  return hhmm.substring(0, 5);
}

function calcHours(start, end) {
  if (!start || !end) return null;
  const [sh, sm] = start.split(':').map(Number);
  const [eh, em] = end.split(':').map(Number);
  let mins = (eh * 60 + em) - (sh * 60 + sm);
  if (mins <= 0) mins += 24 * 60; // overnight shift
  const h = Math.floor(mins / 60);
  const m = mins % 60;
  return m > 0 ? `${h}h ${m}m` : `${h}h`;
}

function updatePreview(prefix) {
  const startEl   = document.getElementById(`${prefix}-work-start`);
  const endEl     = document.getElementById(`${prefix}-work-end`);
  const previewEl = document.getElementById(`${prefix}-schedule-preview`);
  const s = startEl.value;
  const e = endEl.value;

  if (s && e) {
    const hrs = calcHours(s, e);
    previewEl.innerHTML =
      `🕐 <strong>${toAmPm(s)}</strong> to <strong>${toAmPm(e)}</strong>` +
      (hrs ? ` &nbsp;·&nbsp; <strong>${hrs}</strong> shift` : '');
    previewEl.style.display = 'block';
  } else if (s || e) {
    previewEl.innerHTML = '⚠️ Please set both start and end time';
    previewEl.style.background = 'var(--warning-lt)';
    previewEl.style.borderColor = 'var(--warning)';
  } else {
    previewEl.innerHTML = '📅 No schedule set — leave blank to skip';
    previewEl.style.background = 'var(--surface2)';
    previewEl.style.borderColor = 'var(--border)';
  }
}

// Live preview on input
['create-work-start','create-work-end'].forEach(id => {
  document.getElementById(id).addEventListener('input', () => updatePreview('create'));
});
['edit-work-start','edit-work-end'].forEach(id => {
  document.getElementById(id).addEventListener('input', () => updatePreview('edit'));
});

// Init create preview
updatePreview('create');

// ── Toggle password eye ─────────────────────────────────────
function togglePassword(inputId, iconEl) {
  const input = document.getElementById(inputId);
  const show  = input.type === 'password';
  input.type  = show ? 'text' : 'password';
  iconEl.innerHTML = show
    ? `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
           fill="none" stroke="currentColor" stroke-width="2"
           stroke-linecap="round" stroke-linejoin="round">
         <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8
                  a18.45 18.45 0 0 1 5.06-5.94
                  M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8
                  a18.5 18.5 0 0 1-2.16 3.19
                  m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>
         <line x1="1" y1="1" x2="23" y2="23"/>
       </svg>`
    : `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
           fill="none" stroke="currentColor" stroke-width="2"
           stroke-linecap="round" stroke-linejoin="round">
         <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
         <circle cx="12" cy="12" r="3"/>
       </svg>`;
}

// ── Topbar dropdown ─────────────────────────────────────────
function toggleDropdown() {
  document.getElementById('user-dropdown').classList.toggle('open');
}
document.addEventListener('click', e => {
  if (!e.target.closest('.dropdown'))
    document.getElementById('user-dropdown').classList.remove('open');
});

// ── Mobile sidebar ──────────────────────────────────────────
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
</body>
</html>
