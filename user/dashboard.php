<?php
require_once '../config/database.php';
if (!isLoggedIn()) redirect('../index.php');

$user = getCurrentUser();
$db = getDB();


$cats = $db->prepare("
    SELECT c.*,
        COUNT(DISTINCT l.id) as total_lessons,
        COUNT(DISTINCT up.lesson_id) as completed_lessons
    FROM categories c
    LEFT JOIN lessons l ON l.category_id = c.id
    LEFT JOIN user_progress up ON up.lesson_id = l.id AND up.user_id = ? AND up.completed = 1
    WHERE c.required_level <= ?
    GROUP BY c.id ORDER BY c.order_num
");
$cats->execute([$user['id'], $user['level']]);
$categories = $cats->fetchAll();


$xpLog = $db->prepare("SELECT * FROM xp_log WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$xpLog->execute([$user['id']]);
$recentXP = $xpLog->fetchAll();


$nextLevelXP = ($user['level'] * $user['level']) * 50;
$currentLevelXP = (($user['level'] - 1) * ($user['level'] - 1)) * 50;
$progressXP = $user['total_xp'] - $currentLevelXP;
$neededXP = $nextLevelXP - $currentLevelXP;
$levelPercent = min(100, round(($progressXP / max(1, $neededXP)) * 100));

// Logout
if (isset($_GET['logout'])) {
    session_destroy();
    redirect('../index.php');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>NihonGo! - Dashboard</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&family=Noto+Serif+JP:wght@400;700&display=swap" rel="stylesheet">
<style>
:root {
    --red: #FF4B4B; --red-dark: #e03e3e;
    --green: #58CC02; --green-dark: #46a801;
    --blue: #1CB0F6; --yellow: #FFD900;
    --purple: #CE82FF; --orange: #FF9600;
    --bg: #0a0a1a; --card: #12122a; --card2: #1a1a3a;
    --text: #ffffff; --text-muted: #8888aa; --border: #2a2a4a;
}
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: 'Nunito', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; }


.navbar {
    position: sticky; top: 0; z-index: 100;
    background: rgba(10,10,26,0.95);
    backdrop-filter: blur(20px);
    border-bottom: 1px solid var(--border);
    padding: 0 2rem;
    display: flex; align-items: center; justify-content: space-between;
    height: 70px;
}
.nav-logo { font-size: 1.5rem; font-weight: 900; display: flex; align-items: center; gap: 0.5rem; }
.nav-logo span { background: linear-gradient(135deg, #FF4B4B, #FF9600); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
.nav-right { display: flex; align-items: center; gap: 1.5rem; }
.nav-stat { display: flex; align-items: center; gap: 0.4rem; font-weight: 700; }
.nav-stat .icon { font-size: 1.3rem; }
.nav-avatar {
    width: 42px; height: 42px; border-radius: 50%;
    background: var(--card2); border: 2px solid var(--border);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.4rem; cursor: pointer; text-decoration: none;
    transition: border-color 0.3s;
}
.nav-avatar:hover { border-color: var(--red); }


.main { max-width: 1100px; margin: 0 auto; padding: 2rem; }


.hero-card {
    background: linear-gradient(135deg, var(--card) 0%, var(--card2) 100%);
    border: 1px solid var(--border); border-radius: 24px;
    padding: 2rem; margin-bottom: 2rem;
    display: flex; align-items: center; gap: 2rem;
    position: relative; overflow: hidden;
}
.hero-card::before {
    content: '日本語';
    position: absolute; right: -1rem; top: 50%;
    transform: translateY(-50%);
    font-family: 'Noto Serif JP', serif;
    font-size: 8rem; font-weight: 700;
    opacity: 0.04; color: white;
    white-space: nowrap;
}
.hero-avatar { font-size: 4rem; }
.hero-info { flex: 1; }
.hero-greeting { font-size: 1rem; color: var(--text-muted); }
.hero-name { font-size: 2rem; font-weight: 900; margin: 0.1rem 0; }
.level-badge {
    display: inline-flex; align-items: center; gap: 0.4rem;
    background: var(--yellow); color: #333;
    border-radius: 20px; padding: 0.25rem 0.75rem;
    font-size: 0.85rem; font-weight: 800; margin-bottom: 0.75rem;
}
.level-bar-wrap { display: flex; align-items: center; gap: 0.75rem; }
.level-bar {
    flex: 1; height: 12px; background: var(--card2);
    border-radius: 6px; overflow: hidden;
    border: 1px solid var(--border);
}
.level-fill {
    height: 100%; background: linear-gradient(90deg, var(--green), var(--blue));
    border-radius: 6px; transition: width 1s ease;
    box-shadow: 0 0 10px rgba(88,204,2,0.5);
}
.xp-text { font-size: 0.8rem; color: var(--text-muted); white-space: nowrap; }

.stats-row { display: flex; gap: 1rem; margin-left: auto; }
.stat-card {
    background: var(--card2); border: 1px solid var(--border);
    border-radius: 16px; padding: 1rem 1.25rem;
    text-align: center; min-width: 90px;
}
.stat-value { font-size: 1.8rem; font-weight: 900; }
.stat-label { font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; }


.section-title {
    font-size: 1.4rem; font-weight: 800; margin-bottom: 1.25rem;
    display: flex; align-items: center; gap: 0.5rem;
}


.cat-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 2.5rem; }
.cat-card {
    background: var(--card); border: 2px solid var(--border);
    border-radius: 20px; padding: 1.5rem;
    cursor: pointer; transition: all 0.3s;
    text-decoration: none; color: inherit;
    display: block; position: relative; overflow: hidden;
}
.cat-card::after {
    content: ''; position: absolute;
    inset: 0; background: linear-gradient(135deg, transparent 60%, rgba(255,255,255,0.03));
    border-radius: 18px;
}
.cat-card:hover { transform: translateY(-4px); border-color: var(--cat-color); box-shadow: 0 8px 30px rgba(0,0,0,0.3); }
.cat-icon { font-size: 2.5rem; margin-bottom: 0.75rem; display: block; }
.cat-name { font-size: 1rem; font-weight: 800; margin-bottom: 0.25rem; }
.cat-name-jp { font-family: 'Noto Serif JP', serif; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.75rem; }
.cat-progress-wrap { position: relative; }
.cat-bar {
    height: 8px; background: var(--card2); border-radius: 4px;
    overflow: hidden; margin-bottom: 0.4rem;
}
.cat-fill { height: 100%; border-radius: 4px; transition: width 1s; }
.cat-meta { font-size: 0.8rem; color: var(--text-muted); display: flex; justify-content: space-between; }
.cat-complete { color: var(--green); font-weight: 700; }


.vocab-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 0.75rem; margin-bottom: 2.5rem; }
.vocab-card {
    background: var(--card); border: 1px solid var(--border);
    border-radius: 16px; padding: 1rem; text-align: center;
    transition: all 0.3s; cursor: default;
}
.vocab-card:hover { border-color: var(--purple); transform: translateY(-2px); }
.vocab-jp { font-family: 'Noto Serif JP', serif; font-size: 1.6rem; font-weight: 700; color: var(--yellow); margin-bottom: 0.25rem; }
.vocab-romaji { font-size: 0.8rem; color: var(--blue); margin-bottom: 0.25rem; }
.vocab-id { font-size: 0.9rem; color: var(--text-muted); }


.btn-logout {
    background: transparent; border: 1px solid var(--border);
    color: var(--text-muted); padding: 0.4rem 0.9rem;
    border-radius: 10px; cursor: pointer; font-family: 'Nunito', sans-serif;
    font-size: 0.85rem; transition: all 0.3s;
}
.btn-logout:hover { border-color: var(--red); color: var(--red); }

@media (max-width: 768px) {
    .hero-card { flex-direction: column; text-align: center; }
    .stats-row { justify-content: center; }
    .navbar { padding: 0 1rem; }
    .main { padding: 1rem; }
}
</style>
</head>
<body>

<nav class="navbar">
    <div class="nav-logo">🎌 <span>NihonGo!</span></div>
    <div class="nav-right">
        <div class="nav-stat">
            <span class="icon">🔥</span>
            <span><?= $user['streak_days'] ?></span>
        </div>
        <div class="nav-stat">
            <span class="icon">⚡</span>
            <span><?= number_format($user['total_xp']) ?> XP</span>
        </div>
        <a href="vocabulary.php" style="text-decoration:none;color:var(--text-muted);font-size:0.9rem;font-weight:700">📖 Kosakata</a>
        <?php if ($user['role'] === 'admin'): ?>
        <a href="admin/index.php" style="text-decoration:none;background:var(--red);color:white;padding:0.4rem 0.9rem;border-radius:10px;font-size:0.85rem;font-weight:800;">👑 Admin</a>
        <?php endif; ?>
        <a href="?logout=1" class="btn-logout" onclick="return confirm('Yakin mau keluar?')">Keluar</a>
    </div>
</nav>

<div class="main">

    
    <div class="hero-card">
        <div class="hero-avatar"><?= $user['avatar'] ?></div>
        <div class="hero-info">
            <div class="hero-greeting">Selamat datang kembali,</div>
            <div class="hero-name"><?= htmlspecialchars($user['username']) ?>!</div>
            <div class="level-badge">⭐ Level <?= $user['level'] ?></div>
            <div class="level-bar-wrap">
                <div class="level-bar">
                    <div class="level-fill" style="width:<?= $levelPercent ?>%"></div>
                </div>
                <span class="xp-text"><?= $progressXP ?>/<?= $neededXP ?> XP</span>
            </div>
        </div>
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-value" style="color:var(--orange)"><?= $user['streak_days'] ?></div>
                <div class="stat-label">🔥 Streak</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color:var(--green)"><?= number_format($user['total_xp']) ?></div>
                <div class="stat-label">⚡ Total XP</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color:var(--blue)"><?= $user['level'] ?></div>
                <div class="stat-label">⭐ Level</div>
            </div>
        </div>
    </div>

    
    <div class="section-title">🗺️ Pilih Pelajaran</div>
    <div class="cat-grid">
        <?php foreach ($categories as $cat): 
            $pct = $cat['total_lessons'] > 0 ? round(($cat['completed_lessons']/$cat['total_lessons'])*100) : 0;
            $done = $cat['completed_lessons'] >= $cat['total_lessons'] && $cat['total_lessons'] > 0;
        ?>
        <a href="category.php?id=<?= $cat['id'] ?>" class="cat-card" style="--cat-color:<?= $cat['color'] ?>">
            <?php if ($done): ?><div style="position:absolute;top:1rem;right:1rem;font-size:1.2rem">✅</div><?php endif; ?>
            <span class="cat-icon"><?= $cat['icon'] ?></span>
            <div class="cat-name"><?= htmlspecialchars($cat['name']) ?></div>
            <div class="cat-name-jp"><?= htmlspecialchars($cat['name_jp']) ?></div>
            <div class="cat-bar">
                <div class="cat-fill" style="width:<?= $pct ?>%;background:<?= $cat['color'] ?>"></div>
            </div>
            <div class="cat-meta">
                <span><?= $cat['completed_lessons'] ?>/<?= $cat['total_lessons'] ?> pelajaran</span>
                <span <?= $done ? 'class="cat-complete"' : '' ?>><?= $pct ?>%</span>
            </div>
        </a>
        <?php endforeach; ?>
    </div>

    
    <div class="section-title">📖 Kosakata Hari Ini</div>
    <div class="vocab-grid">
        <?php
        $vocabs = $db->query("SELECT * FROM vocabulary ORDER BY RAND() LIMIT 8")->fetchAll();
        foreach ($vocabs as $v): ?>
        <div class="vocab-card">
            <div class="vocab-jp"><?= htmlspecialchars($v['japanese']) ?></div>
            <div class="vocab-romaji"><?= htmlspecialchars($v['romaji']) ?></div>
            <div class="vocab-id"><?= htmlspecialchars($v['indonesian']) ?></div>
        </div>
        <?php endforeach; ?>
    </div>

</div>
</body>
</html>