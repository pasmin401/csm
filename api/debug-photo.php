<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

requireLogin();

$db = getDB();
$userId = $_SESSION['user_id'];

$stmt = $db->prepare("SELECT id, work_date, checkin_photo FROM attendance WHERE user_id = ? ORDER BY id DESC LIMIT 3");
$stmt->execute([$userId]);
$rows = $stmt->fetchAll();

header('Content-Type: text/plain');

foreach ($rows as $r) {
    $photo = $r['checkin_photo'];
    echo "ID: {$r['id']} | Date: {$r['work_date']}\n";
    echo "Photo length: " . strlen($photo) . " chars\n";
    echo "Photo prefix: " . substr($photo, 0, 60) . "\n";
    echo "Is data URL: " . (str_starts_with($photo, 'data:') ? 'YES' : 'NO') . "\n";
    echo "---\n";
}
