<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';
requireAdmin();

// Filters
$from   = $_GET['from']    ?? date('Y-m-01');
$to     = $_GET['to']      ?? date('Y-m-d');
$userId = !empty($_GET['user_id']) ? (int)$_GET['user_id'] : null;
$export = $_GET['export']  ?? '';
$format = $_GET['format']  ?? 'csv'; // csv or excel

$records = getAllAttendance($from, $to, $userId, 1000);
$allUsers = getAllUsers('', 1000, 0);

// ── EXPORT HANDLER ──
if ($export) {
    $filename = 'attendance_' . $from . '_to_' . $to;

    if ($format === 'excel') {
        // Excel HTML table format
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
        header('Cache-Control: max-age=0');
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>';
        echo '<table border="1">';
        echo '<tr style="background:#0891b2;color:white;font-weight:bold">';
        echo '<th>No</th><th>Date</th><th>Employee</th><th>Email</th><th>Department</th>';
        echo '<th>Check In</th><th>Check Out</th><th>Work Hours</th>';
        echo '<th>OT Check In</th><th>OT Check Out</th><th>OT Hours</th>';
        echo '<th>CI Lat</th><th>CI Lng</th><th>CO Lat</th><th>CO Lng</th>';
        echo '<th>OT-CI Lat</th><th>OT-CI Lng</th><th>OT-CO Lat</th><th>OT-CO Lng</th>';
        echo '<th>Status</th>';
        echo '</tr>';
        foreach ($records as $i => $r) {
            $wh  = computeWorkHours($r['checkin_time'], $r['checkout_time'])  ?? '-';
            $oth = computeWorkHours($r['ot_checkin_time'], $r['ot_checkout_time']) ?? '-';
            echo '<tr>';
            echo '<td>' . ($i+1) . '</td>';
            echo '<td>' . $r['work_date'] . '</td>';
            echo '<td>' . htmlspecialchars($r['username']) . '</td>';
            echo '<td>' . htmlspecialchars($r['email']) . '</td>';
            echo '<td>' . htmlspecialchars($r['department'] ?? '') . '</td>';
            echo '<td>' . ($r['checkin_time'] ?? '-') . '</td>';
            echo '<td>' . ($r['checkout_time'] ?? '-') . '</td>';
            echo '<td>' . $wh . '</td>';
            echo '<td>' . ($r['ot_checkin_time'] ?? '-') . '</td>';
            echo '<td>' . ($r['ot_checkout_time'] ?? '-') . '</td>';
            echo '<td>' . $oth . '</td>';
            echo '<td>' . ($r['checkin_lat'] ?? '') . '</td>';
            echo '<td>' . ($r['checkin_lng'] ?? '') . '</td>';
            echo '<td>' . ($r['checkout_lat'] ?? '') . '</td>';
            echo '<td>' . ($r['checkout_lng'] ?? '') . '</td>';
            echo '<td>' . ($r['ot_checkin_lat'] ?? '') . '</td>';
            echo '<td>' . ($r['ot_checkin_lng'] ?? '') . '</td>';
            echo '<td>' . ($r['ot_checkout_lat'] ?? '') . '</td>';
            echo '<td>' . ($r['ot_checkout_lng'] ?? '') . '</td>';
            echo '<td>' . ($r['status'] ?? 'present') . '</td>';
            echo '</tr>';
        }
        echo '</table></body></html>';
    } else {
        // CSV export
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        header('Cache-Control: max-age=0');
        $out = fopen('php://output', 'w');
        // BOM for Excel UTF-8
        fputs($out, "\xEF\xBB\xBF");
        fputcsv($out, [
            'No','Date','Employee','Email','Department',
            'Check In','Check Out','Work Hours',
            'OT Check In','OT Check Out','OT Hours',
            'CI Latitude','CI Longitude','CO Latitude','CO Longitude',
            'OT-CI Latitude','OT-CI Longitude','OT-CO Latitude','OT-CO Longitude',
            'Status'
        ]);
        foreach ($records as $i => $r) {
            fputcsv($out, [
                $i + 1,
                $r['work_date'],
                $r['username'],
                $r['email'],
                $r['department'] ?? '',
                $r['checkin_time'] ?? '',
                $r['checkout_time'] ?? '',
                computeWorkHours($r['checkin_time'], $r['checkout_time']) ?? '',
                $r['ot_checkin_time'] ?? '',
                $r['ot_checkout_time'] ?? '',
                computeWorkHours($r['ot_checkin_time'], $r['ot_checkout_time']) ?? '',
                $r['checkin_lat'] ?? '',
                $r['checkin_lng'] ?? '',
                $r['checkout_lat'] ?? '',
                $r['checkout_lng'] ?? '',
                $r['ot_checkin_lat'] ?? '',
                $r['ot_checkin_lng'] ?? '',
                $r['ot_checkout_lat'] ?? '',
                $r['ot_checkout_lng'] ?? '',
                $r['status'] ?? 'present',
            ]);
        }
        fclose($out);
    }
    exit;
}

// Stats summary for date range
$totalPresent = count($records);
$totalOT = count(array_filter($records, fn($r) => $r['ot_checkin_time']));
$uniqueEmployees = count(array_unique(array_column($records, 'user_id')));

$activePage = 'reports';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reports – <?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="/assets/css/style.css">
<style>
.export-box {
  background: linear-gradient(135deg, #0891b2, #0e7490);
  border-radius: var(--radius-lg);
  padding: 24px 28px;
  color: #fff;
  margin-bottom: 24px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 20px;
  flex-wrap: wrap;
}
.export-box h3 { font-size: 1.1rem; font-weight: 700; }
.export-box p { opacity: .85; font-size: .875rem; margin-top: 4px; }
.export-btns { display: flex; gap: 10px; flex-wrap: wrap; }
.btn-white { background: #fff; color: var(--primary-dk); font-weight: 700; }
.btn-white:hover { background: #f0f9ff; color: var(--primary-dk); }
.btn-white-outline { background: transparent; border: 1.5px solid rgba(255,255,255,.5); color: #fff; font-weight: 600; }
.btn-white-outline:hover { background: rgba(255,255,255,.15); border-color: #fff; color: #fff; }
.summary-row {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  gap: 14px;
  margin-bottom: 24px;
}
.summary-item {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: 16px;
  text-align: center;
}
.summary-item .num { font-size: 1.8rem; font-weight: 700; color: var(--primary); }
.summary-item .lbl { font-size: .8rem; color: var(--txt3); margin-top: 2px; }
</style>
</head>
<body>

<?php include __DIR__ . '/../includes/nav.php'; ?>

  <main class="main-content">
    <div class="page-header">
      <h1>📑 Reports & Export</h1>
      <p>View, filter and export attendance records</p>
    </div>

    <!-- Export Box -->
    <div class="export-box">
      <div>
        <h3>📊 Export Attendance Report</h3>
        <p>Download attendance data for the selected date range in your preferred format</p>
      </div>
      <div class="export-btns">
        <a href="?from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?><?= $userId ? '&user_id='.$userId : '' ?>&export=1&format=csv"
           class="btn btn-white">
          📄 Download CSV
        </a>
        <a href="?from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?><?= $userId ? '&user_id='.$userId : '' ?>&export=1&format=excel"
           class="btn btn-white-outline">
          📊 Download Excel (.xls)
        </a>
      </div>
    </div>

    <!-- Filters -->
    <form method="GET" class="card" style="padding:20px;margin-bottom:20px">
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;align-items:end">
        <div class="form-group" style="margin:0">
          <label>From Date</label>
          <input type="date" name="from" value="<?= e($from) ?>">
        </div>
        <div class="form-group" style="margin:0">
          <label>To Date</label>
          <input type="date" name="to" value="<?= e($to) ?>">
        </div>
        <div class="form-group" style="margin:0">
          <label>Employee</label>
          <select name="user_id">
            <option value="">All Employees</option>
            <?php foreach ($allUsers as $u): ?>
              <option value="<?= $u['id'] ?>" <?= $userId == $u['id'] ? 'selected' : '' ?>>
                <?= e($u['username']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <button type="submit" class="btn btn-primary btn-full">🔍 Filter</button>
        </div>
        <div>
          <a href="reports.php" class="btn btn-outline btn-full">✕ Clear</a>
        </div>
      </div>
    </form>

    <!-- Summary -->
    <div class="summary-row">
      <div class="summary-item">
        <div class="num"><?= $totalPresent ?></div>
        <div class="lbl">Total Records</div>
      </div>
      <div class="summary-item">
        <div class="num"><?= $uniqueEmployees ?></div>
        <div class="lbl">Unique Employees</div>
      </div>
      <div class="summary-item">
        <div class="num"><?= $totalOT ?></div>
        <div class="lbl">OT Records</div>
      </div>
      <div class="summary-item">
        <div class="num"><?= (new DateTime($from))->diff(new DateTime($to))->days + 1 ?></div>
        <div class="lbl">Days in Range</div>
      </div>
    </div>

    <!-- Records Table -->
    <div class="card">
      <div class="card-header">
        <h2>
          📋 Attendance Records
          <span class="badge badge-blue" style="margin-left:8px"><?= count($records) ?></span>
        </h2>
        <div style="font-size:.875rem;color:var(--txt2)">
          <?= date('d M Y', strtotime($from)) ?> → <?= date('d M Y', strtotime($to)) ?>
        </div>
      </div>
      <div class="table-wrap">
        <?php if (empty($records)): ?>
          <div class="empty-state">
            <div class="icon">📭</div>
            <p>No records found for the selected filters</p>
          </div>
        <?php else: ?>
        <table>
          <thead>
            <tr>
              <th>#</th>
              <th>Date</th>
              <th>Employee</th>
              <th>Dept</th>
              <th>Check In</th>
              <th>Check Out</th>
              <th>Hours</th>
              <th>OT In</th>
              <th>OT Out</th>
              <th>OT Hours</th>
              <th>Location</th>
              <th>Photos</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($records as $i => $r): ?>
            <tr>
              <td class="text-muted"><?= $i+1 ?></td>
              <td>
                <div style="font-weight:600;font-size:.875rem"><?= date('d M', strtotime($r['work_date'])) ?></div>
                <div class="text-muted" style="font-size:.75rem"><?= date('Y', strtotime($r['work_date'])) ?></div>
              </td>
              <td>
                <div style="font-weight:600"><?= e($r['username']) ?></div>
                <div class="text-muted" style="font-size:.75rem"><?= e($r['email']) ?></div>
              </td>
              <td><?= e($r['department'] ?? '—') ?></td>
              <td>
                <?php if ($r['checkin_time']): ?>
                  <div style="font-family:'DM Mono',monospace;font-size:.85rem"><?= date('H:i', strtotime($r['checkin_time'])) ?></div>
                  <?php if ($r['checkin_lat']): ?>
                    <a class="map-link" href="https://maps.google.com/?q=<?= $r['checkin_lat'] ?>,<?= $r['checkin_lng'] ?>" target="_blank">📍 View</a>
                  <?php endif; ?>
                <?php else: ?>—<?php endif; ?>
              </td>
              <td>
                <?php if ($r['checkout_time']): ?>
                  <div style="font-family:'DM Mono',monospace;font-size:.85rem"><?= date('H:i', strtotime($r['checkout_time'])) ?></div>
                  <?php if ($r['checkout_lat']): ?>
                    <a class="map-link" href="https://maps.google.com/?q=<?= $r['checkout_lat'] ?>,<?= $r['checkout_lng'] ?>" target="_blank">📍 View</a>
                  <?php endif; ?>
                <?php else: ?>
                  <?php if ($r['checkin_time']): ?><span class="badge badge-amber">Active</span><?php else: ?>—<?php endif; ?>
                <?php endif; ?>
              </td>
              <td>
                <?php $wh = computeWorkHours($r['checkin_time'], $r['checkout_time']); ?>
                <?php if ($wh): ?>
                  <span class="badge badge-green"><?= $wh ?></span>
                <?php else: ?>—<?php endif; ?>
              </td>
              <td>
                <?php if ($r['ot_checkin_time']): ?>
                  <div style="font-family:'DM Mono',monospace;font-size:.85rem;color:var(--ot)"><?= date('H:i', strtotime($r['ot_checkin_time'])) ?></div>
                  <?php if ($r['ot_checkin_lat']): ?>
                    <a class="map-link" href="https://maps.google.com/?q=<?= $r['ot_checkin_lat'] ?>,<?= $r['ot_checkin_lng'] ?>" target="_blank">📍 View</a>
                  <?php endif; ?>
                <?php else: ?>—<?php endif; ?>
              </td>
              <td>
                <?php if ($r['ot_checkout_time']): ?>
                  <div style="font-family:'DM Mono',monospace;font-size:.85rem;color:var(--ot)"><?= date('H:i', strtotime($r['ot_checkout_time'])) ?></div>
                <?php elseif ($r['ot_checkin_time']): ?>
                  <span class="badge badge-purple">Active OT</span>
                <?php else: ?>—<?php endif; ?>
              </td>
              <td>
                <?php $oth = computeWorkHours($r['ot_checkin_time'], $r['ot_checkout_time']); ?>
                <?php if ($oth): ?>
                  <span class="badge badge-purple"><?= $oth ?></span>
                <?php else: ?>—<?php endif; ?>
              </td>
              <td style="font-size:.75rem">
                <?php if ($r['checkin_lat']): ?>
                  <div class="text-muted"><?= number_format($r['checkin_lat'],4) ?></div>
                  <div class="text-muted"><?= number_format($r['checkin_lng'],4) ?></div>
                <?php else: ?>—<?php endif; ?>
              </td>
              <td>
                <div style="display:flex;gap:4px;flex-wrap:wrap">
                  <?php foreach(['checkin_photo'=>'CI','checkout_photo'=>'CO','ot_checkin_photo'=>'OT↑','ot_checkout_photo'=>'OT↓'] as $col=>$label): ?>
                    <?php if ($r[$col]): ?>
                      <img src="<?= $r[$col] ?>" style="width:36px;height:36px;border-radius:50%;object-fit:cover;border:2px solid var(--border);cursor:pointer;"
                           title="<?= $label ?>"
                           onclick="document.getElementById('lightbox-img').src=this.src; document.getElementById('lightbox').classList.add('open')">
                    <?php endif; ?>
                  <?php endforeach; ?>
                </div>
              </td>
              <td>
                <span class="badge <?= $r['status']==='present' ? 'badge-green' : 'badge-amber' ?>">
                  <?= ucfirst(e($r['status'])) ?>
                </span>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>
      </div>
    </div>
  </main>
</div>

<div id="toast-container"></div>

<script>
function toggleDropdown() { document.getElementById('user-dropdown').classList.toggle('open'); }
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
