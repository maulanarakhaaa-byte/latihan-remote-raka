<?php
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['lesson_id'])) {
    echo json_encode(['error' => 'Invalid data']);
    exit;
}

$db = getDB();
$userId = $_SESSION['user_id'];
$lessonId = intval($data['lesson_id']);
$score = intval($data['score'] ?? 0);
$total = intval($data['total'] ?? 1);
$xp = intval($data['xp'] ?? 0);


$stmt = $db->prepare("
    INSERT INTO user_progress (user_id, lesson_id, completed, score, attempts, completed_at)
    VALUES (?, ?, 1, ?, 1, NOW())
    ON DUPLICATE KEY UPDATE
        completed = 1,
        score = GREATEST(score, VALUES(score)),
        attempts = attempts + 1,
        completed_at = IF(score < VALUES(score), NOW(), completed_at)
");
$stmt->execute([$userId, $lessonId, $score]);


if ($xp > 0) {
    addXP($userId, $xp, "Menyelesaikan pelajaran #$lessonId");
}

echo json_encode(['success' => true, 'xp_gained' => $xp]);