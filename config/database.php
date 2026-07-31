<?php


define('DB_HOST', 'localhost');
define('DB_USER', 'root');       
define('DB_PASS', '');           
define('DB_NAME', 'nihongo');
define('DB_CHARSET', 'utf8mb4');

function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die(json_encode(['error' => 'Koneksi database gagal: ' . $e->getMessage()]));
        }
    }
    return $pdo;
}


session_start();


function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getCurrentUser() {
    if (!isLoggedIn()) return null;
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

function redirect($url) {
    header("Location: $url");
    exit();
}

function requireAdmin() {
    if (!isLoggedIn()) redirect('../index.php');
    $user = getCurrentUser();
    if (!$user || ($user['role'] ?? '') !== 'admin') {
        redirect('../user/dashboard.php');
    }
}

function addXP($userId, $xp, $reason = '') {
    $db = getDB();
    $db->prepare("UPDATE users SET total_xp = total_xp + ?, level = FLOOR(1 + SQRT(total_xp/50)) WHERE id = ?")->execute([$xp, $userId]);
    $db->prepare("INSERT INTO xp_log (user_id, xp_gained, reason) VALUES (?, ?, ?)")->execute([$userId, $xp, $reason]);
    
    
    $user = getCurrentUser();
    $today = date('Y-m-d');
    if ($user['last_activity'] !== $today) {
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $newStreak = ($user['last_activity'] === $yesterday) ? $user['streak_days'] + 1 : 1;
        $db->prepare("UPDATE users SET streak_days = ?, last_activity = ? WHERE id = ?")->execute([$newStreak, $today, $userId]);
    }
}