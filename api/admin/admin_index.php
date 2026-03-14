<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';
requireAdmin();

$activePage = 'admin-dashboard';
$stats = getDashboardStats();
$today = date('Y-m-d');
$month = date('Y-m');

$db = getDB();

// Today's attendance list
$todayRecords = $db->query("
    SELECT a.*, u.username, u.email, u.department
    FROM attendance a
    JOIN users u ON a.user_id = u.id
    WHERE a.work_date = '$today'
    ORDER BY a.checkin_time DESC
")->fetchAll();

// Weekly trend (last 7 days)
$weeklyData = $db->query("
    SELECT work_date, COUNT(*) as cnt
    FROM attendance
    WHERE work_date >= CURRENT_DATE - INTERVAL '6 days'
    GROUP BY work_date
    ORDER BY work_date ASC
")->fetchAll();

// Monthly top OT
$topOT = $db->query("
    SELECT u.username, u.department,
           COUNT(a.id) as ot_days,
           SUM(EXTRACT(EPOCH FROM (a.ot_checkout_time::time - a.ot_checkin_time::time))/60)::int as ot_minutes
    FROM attendance a
    JOIN users u ON a.user_id = u.id
    WHERE DATE_TRUNC('month', a.work_date) = DATE_TRUNC('month', CURRENT_DATE) AND a.ot_checkin_time IS NOT NULL AND a.ot_checkout_time IS NOT NULL
    GROUP BY a.user_id, u.username, u.department
    ORDER BY ot_minutes DESC
    LIMIT 5
")->fetchAll();

// Recent registrations
$newUsers = $db->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 5")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard – <?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="/assets/css/style.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
<style>
.two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
@media(max-width:900px){ .two-col { grid-template-columns: 1fr; } }
.three-col { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; }
@media(max-width:900px){ .three-col { grid-template-columns: 1fr 1fr; } }
@media(max-width:600px){ .three-col { grid-template-columns: 1fr; } }
.progress-bar-wrap { height: 6px; background: var(--border); border-radius: 3px; margin-top: 4px; }
.progress-bar { height: 100%; border-radius: 3px; background: var(--primary); }
</style>
</head>
<body>

<?php include __DIR__ . '/../includes/nav.php'; ?>

  <main class="main-content">
    <div class="page-header">
      <h1>⚙️ Admin Dashboard</h1>
      <p><?= date('l, d F Y') ?> — Real-time attendance overview</p>
    </div>

    <!-- Stat Cards -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon blue">👥</div>
        <div class="stat-body">
          <h3><?= $stats['total_users'] ?></h3>
          <p>Total Employees</p>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon green">✅</div>
        <div class="stat-body">
          <h3><?= $stats['present_today'] ?></h3>
          <p>Present Today</p>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon red">❌</div>
        <div class="stat-body">
          <h3><?= $stats['absent_today'] ?></h3>
          <p>Absent Today</p>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon purple">⏰</div>
        <div class="stat-body">
          <h3><?= $stats['ot_today'] ?></h3>
          <p>Overtime Today</p>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon amber">📋</div>
        <div class="stat-body">
          <h3><?= $stats['month_records'] ?></h3>
          <p>This Month Records</p>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon blue">📈</div>
        <div class="stat-body">
          <h3><?= $stats['total_users'] > 0 ? round(($stats['present_today'] / $stats['total_users']) * 100) : 0 ?>%</h3>
          <p>Attendance Rate</p>
        </div>
      </div>
    </div>

    <!-- Charts Row -->
    <div class="two-col" style="margin-bottom:20px">
      <!-- Weekly Trend -->
      <div class="card">
        <div class="card-header">
          <h2>📈 7-Day Attendance Trend</h2>
          <a href="/admin/reports" class="btn btn-outline btn-sm">Full Report</a>
        </div>
        <div class="card-body">
          <canvas id="weeklyChart" height="200"></canvas>
        </div>
      </div>

      <!-- Today's Pie -->
      <div class="card">
        <div class="card-header"><h2>🍩 Today's Breakdown</h2></div>
        <div class="card-body" style="display:flex;align-items:center;gap:24px">
          <canvas id="todayPie" style="max-width:180px;max-height:180px"></canvas>
          <div style="flex:1">
            <div style="margin-bottom:12px">
              <div style="display:flex;justify-content:space-between;font-size:.875rem;margin-bottom:4px">
                <span>✅ Present</span><strong><?= $stats['present_today'] ?></strong>
              </div>
              <div class="progress-bar-wrap">
                <div class="progress-bar" style="width:<?= $stats['total_users'] ? round($stats['present_today']/$stats['total_users']*100) : 0 ?>%;background:var(--success)"></div>
              </div>
            </div>
            <div style="margin-bottom:12px">
              <div style="display:flex;justify-content:space-between;font-size:.875rem;margin-bottom:4px">
                <span>❌ Absent</span><strong><?= $stats['absent_today'] ?></strong>
              </div>
              <div class="progress-bar-wrap">
                <div class="progress-bar" style="width:<?= $stats['total_users'] ? round($stats['absent_today']/$stats['total_users']*100) : 0 ?>%;background:var(--danger)"></div>
              </div>
            </div>
            <div>
              <div style="display:flex;justify-content:space-between;font-size:.875rem;margin-bottom:4px">
                <span>⏰ Overtime</span><strong><?= $stats['ot_today'] ?></strong>
              </div>
              <div class="progress-bar-wrap">
                <div class="progress-bar" style="width:<?= $stats['total_users'] ? round($stats['ot_today']/$stats['total_users']*100) : 0 ?>%;background:var(--ot)"></div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Today's Attendance Table -->
    <div class="card" style="margin-bottom:20px">
      <div class="card-header">
        <h2>📋 Today's Attendance — <?= date('d M Y') ?></h2>
        <div style="display:flex;gap:8px">
          <a href="/admin/reports?from=<?= $today ?>&to=<?= $today ?>" class="btn btn-outline btn-sm">View Details</a>
          <a href="/admin/reports?export=1&from=<?= $today ?>&to=<?= $today ?>" class="btn btn-success btn-sm">⬇ Export CSV</a>
        </div>
      </div>
      <div class="table-wrap">
        <?php if (empty($todayRecords)): ?>
          <div class="empty-state"><div class="icon">📭</div><p>No attendance records yet today</p></div>
        <?php else: ?>
        <table>
          <thead>
            <tr>
              <th>#</th>
              <th>Employee</th>
              <th>Dept</th>
              <th>Check In</th>
              <th>Check Out</th>
              <th>Hours</th>
              <th>OT</th>
              <th>Photos</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($todayRecords as $i => $r): ?>
            <tr>
              <td class="text-muted"><?= $i+1 ?></td>
              <td>
                <div style="font-weight:600"><?= e($r['username']) ?></div>
                <div class="text-muted" style="font-size:.78rem"><?= e($r['email']) ?></div>
              </td>
              <td><?= e($r['department'] ?? '—') ?></td>
              <td>
                <?php if ($r['checkin_time']): ?>
                  <div><?= formatTime($r['checkin_time']) ?></div>
                  <?php if ($r['checkin_lat']): ?>
                    <a class="map-link" href="https://maps.google.com/?q=<?= $r['checkin_lat'] ?>,<?= $r['checkin_lng'] ?>" target="_blank">📍 Map</a>
                  <?php endif; ?>
                <?php else: ?>
                  <span class="badge badge-grey">—</span>
                <?php endif; ?>
              </td>
              <td>
                <?= $r['checkout_time'] ? formatTime($r['checkout_time']) : '<span class="badge badge-amber">In Progress</span>' ?>
              </td>
              <td><?= computeWorkHours($r['checkin_time'], $r['checkout_time']) ?? '—' ?></td>
              <td>
                <?php if ($r['ot_checkin_time']): ?>
                  <span class="badge badge-purple">⏰ <?= computeWorkHours($r['ot_checkin_time'], $r['ot_checkout_time']) ?? 'Active' ?></span>
                <?php else: ?>
                  <span class="text-muted">—</span>
                <?php endif; ?>
              </td>
              <td>
                <div style="display:flex;gap:4px;flex-wrap:wrap">
                  <?php foreach(['checkin_photo'=>'CI','checkout_photo'=>'CO','ot_checkin_photo'=>'OT-IN','ot_checkout_photo'=>'OT-OUT'] as $col=>$label): ?>
                    <?php if ($r[$col]): ?>
                      <img src="<?= UPLOAD_URL . e($r[$col]) ?>" class="photo-thumb" title="<?= $label ?>"
                           onclick="document.getElementById('lightbox-img').src=this.src; document.getElementById('lightbox').classList.add('open')">
                    <?php endif; ?>
                  <?php endforeach; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>
      </div>
    </div>

    <!-- Bottom Row -->
    <div class="two-col">
      <!-- Top OT -->
      <div class="card">
        <div class="card-header"><h2>⏰ Top Overtime This Month</h2></div>
        <div class="card-body">
          <?php if (empty($topOT)): ?>
            <p class="text-muted">No overtime recorded this month.</p>
          <?php else: ?>
            <?php foreach ($topOT as $i => $ot): ?>
              <div style="display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid var(--border)">
                <div style="width:28px;height:28px;border-radius:50%;background:var(--primary);color:#fff;display:flex;align-items:center;justify-content:center;font-size:.8rem;font-weight:700;flex-shrink:0"><?= $i+1 ?></div>
                <div style="flex:1">
                  <div style="font-weight:600"><?= e($ot['username']) ?></div>
                  <div class="text-muted" style="font-size:.8rem"><?= e($ot['department'] ?? '') ?></div>
                </div>
                <div style="text-align:right">
                  <div style="font-weight:700;color:var(--ot)"><?= floor($ot['ot_minutes']/60) ?>h <?= $ot['ot_minutes']%60 ?>m</div>
                  <div class="text-muted" style="font-size:.78rem"><?= $ot['ot_days'] ?> day(s)</div>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

      <!-- Recent Users -->
      <div class="card">
        <div class="card-header">
          <h2>🆕 Recent Registrations</h2>
          <a href="/admin/users" class="btn btn-outline btn-sm">Manage All</a>
        </div>
        <div class="card-body">
          <?php foreach ($newUsers as $u): ?>
            <div style="display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid var(--border)">
              <div style="width:36px;height:36px;border-radius:50%;background:var(--primary);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;flex-shrink:0">
                <?= strtoupper(substr($u['username'],0,2)) ?>
              </div>
              <div style="flex:1">
                <div style="font-weight:600"><?= e($u['username']) ?></div>
                <div class="text-muted" style="font-size:.8rem"><?= e($u['email']) ?></div>
              </div>
              <div>
                <span class="badge <?= $u['role']==='admin' ? 'badge-purple' : 'badge-green' ?>">
                  <?= ucfirst($u['role']) ?>
                </span>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </main>
</div>

<div id="toast-container"></div>

<script>
// Weekly trend chart
const weeklyData = <?= json_encode($weeklyData) ?>;
const labels = [];
const data   = [];
// Fill all 7 days
for (let i = 6; i >= 0; i--) {
  const d = new Date();
  d.setDate(d.getDate() - i);
  const dateStr = d.toISOString().split('T')[0];
  labels.push(d.toLocaleDateString('en', {weekday:'short', month:'short', day:'numeric'}));
  const found = weeklyData.find(r => r.work_date === dateStr);
  data.push(found ? parseInt(found.cnt) : 0);
}

new Chart(document.getElementById('weeklyChart'), {
  type: 'bar',
  data: {
    labels,
    datasets: [{
      label: 'Employees Present',
      data,
      backgroundColor: 'rgba(8,145,178,.7)',
      borderColor: 'rgba(8,145,178,1)',
      borderWidth: 2,
      borderRadius: 6,
    }]
  },
  options: {
    responsive: true,
    plugins: { legend: { display: false } },
    scales: {
      y: {
        beginAtZero: true,
        ticks: { stepSize: 1 },
        grid: { color: 'rgba(0,0,0,.04)' },
      },
      x: { grid: { display: false } }
    }
  }
});

// Today Pie
const present = <?= $stats['present_today'] ?>;
const absent  = <?= $stats['absent_today'] ?>;
const ot      = <?= $stats['ot_today'] ?>;

new Chart(document.getElementById('todayPie'), {
  type: 'doughnut',
  data: {
    labels: ['Present', 'Absent', 'OT'],
    datasets: [{
      data: [present, absent, ot],
      backgroundColor: ['#10b981','#ef4444','#7c3aed'],
      borderWidth: 0,
      hoverOffset: 4,
    }]
  },
  options: {
    responsive: true,
    plugins: { legend: { display: false } },
    cutout: '72%',
  }
});

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
function closeLightbox() {
  document.getElementById('lightbox').classList.remove('open');
}
</script>
</body>
</html>
