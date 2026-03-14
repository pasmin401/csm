<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!$input) {
    http_response_code(400);
    $kb = round(strlen($raw) / 1024, 1);
    echo json_encode(['error' => "Invalid JSON received ({$kb}KB). If 0KB, post_max_size limit was exceeded. Check php.ini post_max_size."]);
    exit;
}

$type  = $input['type']  ?? '';
$lat   = isset($input['lat']) ? (float)$input['lat'] : null;
$lng   = isset($input['lng']) ? (float)$input['lng'] : null;
$photo = $input['photo'] ?? '';

$userId = $_SESSION['user_id'];

if (!in_array($type, ['checkin','checkout','ot_checkin','ot_checkout'])) {
    echo json_encode(['error' => 'Invalid type']);
    exit;
}

if (!$lat || !$lng) {
    echo json_encode(['error' => 'Location is required']);
    exit;
}

if (empty($photo)) {
    echo json_encode(['error' => 'Photo is required — camera may not have captured an image']);
    exit;
}

$messages = [
    'checkin'     => 'Checked in successfully!',
    'checkout'    => 'Checked out successfully!',
    'ot_checkin'  => 'Overtime started!',
    'ot_checkout' => 'Overtime ended!',
];

try {
    switch ($type) {
        case 'checkin':
            $today = getTodayAttendance($userId);
            if ($today && $today['checkin_time']) {
                echo json_encode(['error' => 'Already checked in today']);
                exit;
            }
            $id = checkIn($userId, $lat, $lng, $photo);
            break;

        case 'checkout':
            $today = getTodayAttendance($userId);
            if (!$today || !$today['checkin_time']) {
                echo json_encode(['error' => 'Please check in first']);
                exit;
            }
            if ($today['checkout_time']) {
                echo json_encode(['error' => 'Already checked out today']);
                exit;
            }
            $id = checkOut($userId, $lat, $lng, $photo);
            break;

        case 'ot_checkin':
            $today = getTodayAttendance($userId);
            if (!$today || !$today['checkout_time']) {
                echo json_encode(['error' => 'Please complete regular attendance first']);
                exit;
            }
            if ($today['ot_checkin_time']) {
                echo json_encode(['error' => 'Overtime already started']);
                exit;
            }
            $id = otCheckIn($userId, $lat, $lng, $photo);
            break;

        case 'ot_checkout':
            $today = getTodayAttendance($userId);
            if (!$today || !$today['ot_checkin_time']) {
                echo json_encode(['error' => 'Please start overtime first']);
                exit;
            }
            if ($today['ot_checkout_time']) {
                echo json_encode(['error' => 'Overtime already ended']);
                exit;
            }
            $id = otCheckOut($userId, $lat, $lng, $photo);
            break;
    }

    if ($id) {
        echo json_encode([
            'success' => true,
            'message' => $messages[$type],
            'id'      => $id,
            'time'    => date('H:i:s'),
        ]);
    } else {
        // Diagnose the exact reason
        $uploadDir = UPLOAD_DIR;
        $reason = '';
        if (!is_dir($uploadDir))        $reason = "uploads/ folder does not exist at: $uploadDir";
        elseif (!is_writable($uploadDir)) $reason = "uploads/ folder is not writable (run: chmod 755 uploads/)";
        else                              $reason = "Photo decode/save failed — photo data may be corrupt or empty";
        echo json_encode(['error' => "Failed to save photo. $reason"]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}