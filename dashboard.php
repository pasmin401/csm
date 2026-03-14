<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
requireLogin();

$activePage = 'dashboard';
$user = getUserById($_SESSION['user_id']);
$today = getTodayAttendance($_SESSION['user_id']);
$recentRecords = getUserAttendance($_SESSION['user_id'], null, null, 10);
$flash = getFlash();

// Determine today's status flags
$canCheckIn     = !$today || !$today['checkin_time'];
$canCheckOut    = $today && $today['checkin_time'] && !$today['checkout_time'];
$canOtCheckIn   = $today && $today['checkout_time'] && !$today['ot_checkin_time'];
$canOtCheckOut  = $today && $today['ot_checkin_time'] && !$today['ot_checkout_time'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard – <?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="/assets/css/style.css">
<style>
.action-buttons { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 16px; }
.geo-coords { font-family: 'DM Mono', monospace; font-size: .78rem; color: var(--txt3); }
.record-photo-grid { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
.photo-avatar-wrap { display: flex; flex-direction: column; align-items: center; gap: 3px; cursor: pointer; }
.photo-avatar {
  width: 38px; height: 38px;
  border-radius: 50%;
  object-fit: cover;
  border: 2px solid var(--border);
  transition: transform .2s, border-color .2s;
}
.photo-avatar:hover { transform: scale(1.15); border-color: var(--primary); }
.photo-avatar-label { font-size: .6rem; color: var(--txt3); font-weight: 600; text-transform: uppercase; }
.checkin-status-dot {
  display: inline-block;
  width: 8px; height: 8px;
  border-radius: 50%;
  margin-right: 6px;
}
.dot-green  { background: var(--success); box-shadow: 0 0 0 3px rgba(16,185,129,.2); }
.dot-grey   { background: var(--txt3); }
.dot-purple { background: var(--ot); box-shadow: 0 0 0 3px rgba(124,58,237,.2); }
</style>
</head>
<body>

<?php include __DIR__ . '/includes/nav.php'; ?>

  <main class="main-content">
    <div class="page-header">
      <h1>Good <?= date('H') < 12 ? 'Morning' : (date('H') < 17 ? 'Afternoon' : 'Evening') ?>, <?= e($user['username']) ?>! 👋</h1>
      <p>Here's your attendance overview for today</p>
    </div>

    <?php if ($flash): ?>
      <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?>">
        <?= $flash['type'] === 'success' ? '✅' : '⚠️' ?> <?= e($flash['msg']) ?>
      </div>
    <?php endif; ?>

    <!-- Clock Card -->
    <div class="clock-card">
      <div id="live-clock">--:--:--</div>
      <div id="live-date"><?= date('l, d F Y') ?></div>
    </div>

    <!-- Attendance Action Cards -->
    <div class="attend-grid">

      <!-- Regular Check-In -->
      <div class="attend-card">
        <div class="type-badge regular">📅 Regular</div>
        <h3>Check In</h3>
        <div class="time-display">
          <?= $today && $today['checkin_time'] ? date('H:i', strtotime($today['checkin_time'])) : '--:--' ?>
        </div>
        <div class="sub">
          <?php if ($today && $today['checkin_time']): ?>
            <span class="checkin-status-dot dot-green"></span>Clocked in at <?= formatTime($today['checkin_time']) ?>
            <?php if ($today['checkin_lat']): ?>
              <br><span class="geo-coords">📍 <?= number_format($today['checkin_lat'],6) ?>, <?= number_format($today['checkin_lng'],6) ?></span>
            <?php endif; ?>
          <?php else: ?>
            <span class="checkin-status-dot dot-grey"></span>Not yet clocked in
          <?php endif; ?>
        </div>
        <?php if ($canCheckIn): ?>
          <button class="btn btn-primary btn-full" onclick="openModal('checkin')">
            📍 Check In Now
          </button>
        <?php else: ?>
          <button class="btn btn-outline btn-full" disabled>✅ Already Checked In</button>
        <?php endif; ?>
      </div>

      <!-- Regular Check-Out -->
      <div class="attend-card">
        <div class="type-badge regular">📅 Regular</div>
        <h3>Check Out</h3>
        <div class="time-display">
          <?= $today && $today['checkout_time'] ? date('H:i', strtotime($today['checkout_time'])) : '--:--' ?>
        </div>
        <div class="sub">
          <?php if ($today && $today['checkout_time']): ?>
            <span class="checkin-status-dot dot-green"></span>Clocked out at <?= formatTime($today['checkout_time']) ?>
            <?php if ($today['checkin_time'] && $today['checkout_time']): ?>
              <br>⏱ Total: <?= computeWorkHours($today['checkin_time'], $today['checkout_time']) ?>
            <?php endif; ?>
          <?php else: ?>
            <span class="checkin-status-dot dot-grey"></span>Not yet clocked out
          <?php endif; ?>
        </div>
        <?php if ($canCheckOut): ?>
          <button class="btn btn-danger btn-full" onclick="openModal('checkout')">
            📍 Check Out Now
          </button>
        <?php else: ?>
          <button class="btn btn-outline btn-full" disabled>
            <?= ($today && $today['checkout_time']) ? '✅ Checked Out' : '🔒 Check In First' ?>
          </button>
        <?php endif; ?>
      </div>

      <!-- OT Check-In -->
      <div class="attend-card">
        <div class="type-badge ot">⏰ Overtime</div>
        <h3>OT Check In</h3>
        <div class="time-display ot-time">
          <?= $today && $today['ot_checkin_time'] ? date('H:i', strtotime($today['ot_checkin_time'])) : '--:--' ?>
        </div>
        <div class="sub">
          <?php if ($today && $today['ot_checkin_time']): ?>
            <span class="checkin-status-dot dot-purple"></span>OT started at <?= formatTime($today['ot_checkin_time']) ?>
          <?php else: ?>
            <span class="checkin-status-dot dot-grey"></span>No overtime started
          <?php endif; ?>
        </div>
        <?php if ($canOtCheckIn): ?>
          <button class="btn btn-ot btn-full" onclick="openModal('ot_checkin')">
            ⏰ Start Overtime
          </button>
        <?php else: ?>
          <button class="btn btn-outline btn-full" disabled>
            <?= ($today && $today['ot_checkin_time']) ? '✅ OT Started' : '🔒 Check Out First' ?>
          </button>
        <?php endif; ?>
      </div>

      <!-- OT Check-Out -->
      <div class="attend-card">
        <div class="type-badge ot">⏰ Overtime</div>
        <h3>OT Check Out</h3>
        <div class="time-display ot-time">
          <?= $today && $today['ot_checkout_time'] ? date('H:i', strtotime($today['ot_checkout_time'])) : '--:--' ?>
        </div>
        <div class="sub">
          <?php if ($today && $today['ot_checkout_time']): ?>
            <span class="checkin-status-dot dot-purple"></span>OT ended at <?= formatTime($today['ot_checkout_time']) ?>
            <?php if ($today['ot_checkin_time'] && $today['ot_checkout_time']): ?>
              <br>⏱ OT Hours: <?= computeWorkHours($today['ot_checkin_time'], $today['ot_checkout_time']) ?>
            <?php endif; ?>
          <?php else: ?>
            <span class="checkin-status-dot dot-grey"></span>No overtime ended
          <?php endif; ?>
        </div>
        <?php if ($canOtCheckOut): ?>
          <button class="btn btn-ot btn-full" onclick="openModal('ot_checkout')">
            ⏰ End Overtime
          </button>
        <?php else: ?>
          <button class="btn btn-outline btn-full" disabled>
            <?= ($today && $today['ot_checkout_time']) ? '✅ OT Ended' : '🔒 Start OT First' ?>
          </button>
        <?php endif; ?>
      </div>
    </div>

    <!-- Recent Records -->
    <div class="card">
      <div class="card-header">
        <h2>📋 Recent Attendance</h2>
        <span class="text-muted">Last 10 records</span>
      </div>
      <div class="table-wrap">
        <?php if (empty($recentRecords)): ?>
          <div class="empty-state">
            <div class="icon">📭</div>
            <p>No attendance records yet. Start by checking in!</p>
          </div>
        <?php else: ?>
        <table>
          <thead>
            <tr>
              <th>Date</th>
              <th>Check In</th>
              <th>Check Out</th>
              <th>Hours</th>
              <th>OT In</th>
              <th>OT Out</th>
              <th>OT Hours</th>
              <th>Photos</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($recentRecords as $r): ?>
            <tr>
              <td><?= formatDate($r['work_date']) ?></td>
              <td>
                <?= formatTime($r['checkin_time']) ?>
                <?php if ($r['checkin_lat']): ?>
                  <br><a class="map-link" href="https://maps.google.com/?q=<?= $r['checkin_lat'] ?>,<?= $r['checkin_lng'] ?>" target="_blank">📍 Map</a>
                <?php endif; ?>
              </td>
              <td>
                <?= formatTime($r['checkout_time']) ?>
                <?php if ($r['checkout_lat']): ?>
                  <br><a class="map-link" href="https://maps.google.com/?q=<?= $r['checkout_lat'] ?>,<?= $r['checkout_lng'] ?>" target="_blank">📍 Map</a>
                <?php endif; ?>
              </td>
              <td><?= computeWorkHours($r['checkin_time'], $r['checkout_time']) ?? '—' ?></td>
              <td><?= formatTime($r['ot_checkin_time']) ?></td>
              <td><?= formatTime($r['ot_checkout_time']) ?></td>
              <td><?= computeWorkHours($r['ot_checkin_time'], $r['ot_checkout_time']) ?? '—' ?></td>
              <td>
                <div class="record-photo-grid">
                  <?php foreach(['checkin_photo'=>'CI','checkout_photo'=>'CO','ot_checkin_photo'=>'OT-IN','ot_checkout_photo'=>'OT-OUT'] as $col=>$label): ?>
                    <?php if ($r[$col]): ?>
                      <div class="photo-avatar-wrap" title="<?= $label ?>" onclick="openLightbox('<?= htmlspecialchars($r[$col], ENT_QUOTES) ?>')">
                        <img src="<?= $r[$col] ?>" class="photo-avatar">
                        <span class="photo-avatar-label"><?= $label ?></span>
                      </div>
                    <?php endif; ?>
                  <?php endforeach; ?>
                </div>
              </td>
              <td>
                <span class="badge <?= $r['status'] === 'present' ? 'badge-green' : 'badge-amber' ?>">
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

<!-- Check-In/Out Modal -->
<div class="modal-overlay" id="attend-modal">
  <div class="modal">
    <div class="modal-header">
      <h3 id="modal-title">Check In</h3>
      <button class="modal-close" onclick="closeModal()">×</button>
    </div>
    <div class="modal-body">
      <!-- Geo Info -->
      <div class="geo-info getting" id="geo-status">
        <span class="geo-icon">📡</span>
        <span id="geo-text">Getting your location...</span>
      </div>
      <input type="hidden" id="geo-lat" value="">
      <input type="hidden" id="geo-lng" value="">

      <!-- Camera -->
      <div class="camera-wrap">
        <video id="camera-video" autoplay muted playsinline></video>
        <canvas id="camera-canvas"></canvas>
        <img id="camera-preview" class="camera-captured-preview" src="" alt="">
      </div>

      <div style="display:flex;gap:10px;margin-bottom:12px;">
        <button class="btn btn-outline btn-sm" id="btn-retake" onclick="retakePhoto()" style="display:none">🔄 Retake</button>
        <button class="btn btn-primary btn-sm" id="btn-capture" onclick="capturePhoto()">📸 Capture Photo</button>
      </div>
      <input type="hidden" id="photo-data" value="">

      <div class="modal-actions">
        <button class="btn btn-outline" onclick="closeModal()" style="flex:1">Cancel</button>
        <button class="btn btn-primary" id="btn-submit" onclick="submitAttendance()" style="flex:2" disabled>
          <span id="btn-submit-text">Submit</span>
          <span class="spinner" id="btn-spinner" style="display:none"></span>
        </button>
      </div>
    </div>
  </div>
</div>

<script>
let currentType = '';
let mediaStream = null;
let photoCaptured = false;
let geoReady = false;

const typeConfig = {
  'checkin':     { title: '📍 Check In',         btnClass: 'btn-primary', btnText: 'Confirm Check In' },
  'checkout':    { title: '📍 Check Out',         btnClass: 'btn-danger',  btnText: 'Confirm Check Out' },
  'ot_checkin':  { title: '⏰ Start Overtime',    btnClass: 'btn-ot',      btnText: 'Start Overtime' },
  'ot_checkout': { title: '⏰ End Overtime',      btnClass: 'btn-ot',      btnText: 'End Overtime' },
};

function openModal(type) {
  currentType = type;
  const cfg = typeConfig[type];
  document.getElementById('modal-title').textContent = cfg.title;
  const submitBtn = document.getElementById('btn-submit');
  submitBtn.className = 'btn ' + cfg.btnClass;
  submitBtn.style.flex = '2';
  document.getElementById('btn-submit-text').textContent = cfg.btnText;
  submitBtn.disabled = true;
  photoCaptured = false;
  geoReady = false;

  document.getElementById('attend-modal').classList.add('open');
  startCamera();
  getLocation();
}

function closeModal() {
  document.getElementById('attend-modal').classList.remove('open');
  stopCamera();
  document.getElementById('camera-preview').style.display = 'none';
  document.getElementById('camera-video').style.display = 'block';
  document.getElementById('btn-capture').style.display = 'block';
  document.getElementById('btn-retake').style.display = 'none';
  document.getElementById('photo-data').value = '';
  document.getElementById('geo-lat').value = '';
  document.getElementById('geo-lng').value = '';
}

function startCamera() {
  navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' }, audio: false })
    .then(stream => {
      mediaStream = stream;
      const video = document.getElementById('camera-video');
      video.srcObject = stream;
      video.style.display = 'block';
    })
    .catch(err => {
      showToast('Camera not available: ' + err.message, 'error');
    });
}

function stopCamera() {
  if (mediaStream) {
    mediaStream.getTracks().forEach(t => t.stop());
    mediaStream = null;
  }
}

function capturePhoto() {
  const video  = document.getElementById('camera-video');
  const canvas = document.getElementById('camera-canvas');

  // Resize to max 480x360 to keep payload under ~80KB
  const MAX_W = 480, MAX_H = 360;
  let w = video.videoWidth  || 640;
  let h = video.videoHeight || 480;
  if (w > MAX_W) { h = Math.round(h * MAX_W / w); w = MAX_W; }
  if (h > MAX_H) { w = Math.round(w * MAX_H / h); h = MAX_H; }
  canvas.width  = w;
  canvas.height = h;
  canvas.getContext('2d').drawImage(video, 0, 0, w, h);

  // Quality 0.6 keeps file small (~40–80KB base64)
  const dataUrl = canvas.toDataURL('image/jpeg', 0.6);
  document.getElementById('photo-data').value = dataUrl;

  // Show preview
  const preview = document.getElementById('camera-preview');
  preview.src = dataUrl;
  preview.style.display = 'block';
  video.style.display = 'none';
  document.getElementById('btn-capture').style.display = 'none';
  document.getElementById('btn-retake').style.display = 'inline-flex';

  photoCaptured = true;
  stopCamera();
  checkReady();
}

function retakePhoto() {
  photoCaptured = false;
  document.getElementById('photo-data').value = '';
  document.getElementById('camera-preview').style.display = 'none';
  document.getElementById('camera-video').style.display = 'block';
  document.getElementById('btn-capture').style.display = 'block';
  document.getElementById('btn-retake').style.display = 'none';
  startCamera();
  checkReady();
}

function getLocation() {
  const status = document.getElementById('geo-status');
  const text   = document.getElementById('geo-text');

  if (!navigator.geolocation) {
    status.className = 'geo-info failed';
    text.textContent = 'Geolocation not supported.';
    geoReady = false;
    return;
  }

  status.className = 'geo-info getting';
  text.textContent = 'Getting your location...';

  navigator.geolocation.getCurrentPosition(
    pos => {
      document.getElementById('geo-lat').value = pos.coords.latitude;
      document.getElementById('geo-lng').value = pos.coords.longitude;
      status.className = 'geo-info got';
      text.textContent = `📍 ${pos.coords.latitude.toFixed(6)}, ${pos.coords.longitude.toFixed(6)} (±${Math.round(pos.coords.accuracy)}m)`;
      geoReady = true;
      checkReady();
    },
    err => {
      status.className = 'geo-info failed';
      text.textContent = 'Location access denied. Please allow location.';
      geoReady = false;
    },
    { enableHighAccuracy: true, timeout: 15000 }
  );
}

function checkReady() {
  document.getElementById('btn-submit').disabled = !(photoCaptured && geoReady);
}

async function submitAttendance() {
  const lat   = document.getElementById('geo-lat').value;
  const lng   = document.getElementById('geo-lng').value;
  const photo = document.getElementById('photo-data').value;

  if (!lat || !lng) { showToast('Location required', 'error'); return; }
  if (!photo)       { showToast('Photo required', 'error'); return; }

  document.getElementById('btn-submit').disabled = true;
  document.getElementById('btn-submit-text').style.display = 'none';
  document.getElementById('btn-spinner').style.display = 'inline-block';

  try {
    const payload = JSON.stringify({ type: currentType, lat, lng, photo });
    const res = await fetch('/api/attendance-action', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: payload
    });
    const raw = await res.text();
    let data;
    try {
      data = JSON.parse(raw);
    } catch (parseErr) {
      // Server returned non-JSON — show raw response (truncated)
      showToast('Server error: ' + raw.substring(0, 200), 'error');
      document.getElementById('btn-submit').disabled = false;
      return;
    }
    if (data.success) {
      showToast(data.message, 'success');
      closeModal();
      setTimeout(() => window.location.href = window.location.pathname + '?r=' + Date.now(), 1200);
    } else {
      showToast(data.error || 'Failed', 'error');
      document.getElementById('btn-submit').disabled = false;
    }
  } catch (e) {
    showToast('Network error: ' + e.message, 'error');
    document.getElementById('btn-submit').disabled = false;
  } finally {
    document.getElementById('btn-submit-text').style.display = 'inline';
    document.getElementById('btn-spinner').style.display = 'none';
  }
}

// Live clock
function updateClock() {
  const now = new Date();
  const h = String(now.getHours()).padStart(2,'0');
  const m = String(now.getMinutes()).padStart(2,'0');
  const s = String(now.getSeconds()).padStart(2,'0');
  document.getElementById('live-clock').textContent = `${h}:${m}:${s}`;
}
setInterval(updateClock, 1000);
updateClock();

// Lightbox
function openLightbox(src) {
  document.getElementById('lightbox-img').src = src;
  document.getElementById('lightbox').classList.add('open');
}
function closeLightbox() {
  document.getElementById('lightbox').classList.remove('open');
}

// Toast
function showToast(msg, type = 'info') {
  const tc = document.getElementById('toast-container');
  const t = document.createElement('div');
  t.className = `toast ${type}`;
  t.innerHTML = (type === 'success' ? '✅' : type === 'error' ? '❌' : 'ℹ️') + ' ' + msg;
  tc.appendChild(t);
  setTimeout(() => t.remove(), 4000);
}

// Dropdown
function toggleDropdown() {
  document.getElementById('user-dropdown').classList.toggle('open');
}
document.addEventListener('click', e => {
  if (!e.target.closest('.dropdown')) {
    document.getElementById('user-dropdown').classList.remove('open');
  }
});

// Sidebar mobile
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
