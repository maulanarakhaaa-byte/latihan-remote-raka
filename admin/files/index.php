<?php
require_once '../../config/database.php';
requireAdmin();
$db = getDB();

$totalUsers   = $db->query("SELECT COUNT(*) FROM users WHERE role='user'")->fetchColumn();
$activeToday  = $db->query("SELECT COUNT(*) FROM users WHERE last_activity = CURDATE()")->fetchColumn();
$bannedUsers  = $db->query("SELECT COUNT(*) FROM users WHERE is_banned=1")->fetchColumn();
$totalXP      = $db->query("SELECT SUM(total_xp) FROM users")->fetchColumn();
$totalLessons = $db->query("SELECT COUNT(*) FROM lessons")->fetchColumn();
$totalQ       = $db->query("SELECT COUNT(*) FROM questions")->fetchColumn();
$totalVocab   = $db->query("SELECT COUNT(*) FROM vocabulary")->fetchColumn();
$completions  = $db->query("SELECT COUNT(*) FROM user_progress WHERE completed=1")->fetchColumn();

$newUsersChart = $db->query("
    SELECT DATE(created_at) as day, COUNT(*) as cnt
    FROM users WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY DATE(created_at) ORDER BY day
")->fetchAll();

$topUsers = $db->query("
    SELECT username, avatar, total_xp, level, streak_days, last_activity
    FROM users ORDER BY total_xp DESC LIMIT 8
")->fetchAll();

$recentActivity = $db->query("
    SELECT u.username, u.avatar, x.xp_gained, x.reason, x.created_at
    FROM xp_log x JOIN users u ON u.id = x.user_id
    ORDER BY x.created_at DESC LIMIT 10
")->fetchAll();

$topLessons = $db->query("
    SELECT l.title, c.name as cat, c.icon, COUNT(up.id) as cnt
    FROM user_progress up
    JOIN lessons l ON l.id = up.lesson_id
    JOIN categories c ON c.id = l.category_id
    WHERE up.completed=1
    GROUP BY l.id ORDER BY cnt DESC LIMIT 6
")->fetchAll();

$adminUser = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard - NihonGo!</title>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&family=Noto+Serif+JP:wght@400;700&display=swap" rel="stylesheet">
<style>
:root {
    --red:#FF4B4B;--red-dark:#c0392b;
    --green:#58CC02;--green-dark:#46a801;
    --blue:#1CB0F6;--blue-dark:#1590c8;
    --yellow:#FFD900;--purple:#CE82FF;--orange:#FF9600;
    --bg:#070714;--sidebar:#0d0d24;--card:#12122a;--card2:#1a1a3a;
    --text:#ffffff;--text-muted:#8888aa;--border:#1e1e40;
    --sidebar-w:260px;
}
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Nunito',sans-serif;background:var(--bg);color:var(--text);display:flex;min-height:100vh;}

.sidebar{
    width:var(--sidebar-w);flex-shrink:0;
    background:var(--sidebar);border-right:1px solid var(--border);
    display:flex;flex-direction:column;
    position:fixed;inset:0 auto 0 0;z-index:200;
    overflow-y:auto;
}
.sidebar-logo{
    padding:1.75rem 1.5rem;border-bottom:1px solid var(--border);
    display:flex;align-items:center;gap:0.75rem;
}
.logo-icon{font-size:2rem;}
.logo-text{font-size:1.3rem;font-weight:900;}
.logo-text span{background:linear-gradient(135deg,#FF4B4B,#FF9600);-webkit-background-clip:text;-webkit-text-fill-color:transparent;}
.logo-badge{font-size:0.65rem;background:var(--red);color:white;padding:0.15rem 0.5rem;border-radius:6px;font-weight:800;margin-left:0.25rem;}

.sidebar-section{padding:1.25rem 1rem 0.5rem;font-size:0.7rem;text-transform:uppercase;letter-spacing:1.5px;color:var(--text-muted);font-weight:800;}
.nav-item{
    display:flex;align-items:center;gap:0.85rem;
    padding:0.75rem 1.25rem;border-radius:12px;margin:0.15rem 0.75rem;
    text-decoration:none;color:var(--text-muted);font-weight:700;font-size:0.95rem;
    transition:all 0.2s;cursor:pointer;border:none;background:none;width:calc(100% - 1.5rem);text-align:left;
}
.nav-item:hover{background:var(--card);color:var(--text);}
.nav-item.active{background:linear-gradient(135deg,rgba(255,75,75,0.2),rgba(255,150,0,0.1));color:var(--red);border:1px solid rgba(255,75,75,0.2);}
.nav-item .nav-icon{font-size:1.15rem;width:24px;text-align:center;}
.nav-badge{margin-left:auto;background:var(--red);color:white;font-size:0.7rem;padding:0.1rem 0.5rem;border-radius:10px;font-weight:800;}

.sidebar-footer{
    margin-top:auto;padding:1.25rem;border-top:1px solid var(--border);
}
.admin-info{display:flex;align-items:center;gap:0.75rem;}
.admin-avatar{font-size:1.8rem;}
.admin-name{font-size:0.9rem;font-weight:800;}
.admin-role{font-size:0.75rem;color:var(--orange);font-weight:700;}
.btn-logout-sm{
    display:block;text-align:center;margin-top:0.75rem;
    padding:0.5rem;border-radius:10px;border:1px solid var(--border);
    color:var(--text-muted);text-decoration:none;font-size:0.85rem;font-weight:700;
    transition:all 0.3s;
}
.btn-logout-sm:hover{border-color:var(--red);color:var(--red);}

.main{margin-left:var(--sidebar-w);flex:1;display:flex;flex-direction:column;min-height:100vh;}

.topbar{
    height:65px;border-bottom:1px solid var(--border);
    background:rgba(7,7,20,0.9);backdrop-filter:blur(20px);
    display:flex;align-items:center;justify-content:space-between;
    padding:0 2rem;position:sticky;top:0;z-index:100;
}
.page-title{font-size:1.4rem;font-weight:900;}
.topbar-right{display:flex;align-items:center;gap:1rem;}
.date-badge{font-size:0.85rem;color:var(--text-muted);}

.content{padding:2rem;flex:1;}

.stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:2rem;}
.stat-card{
    background:var(--card);border:1px solid var(--border);
    border-radius:20px;padding:1.5rem;
    position:relative;overflow:hidden;transition:transform 0.3s;
}
.stat-card:hover{transform:translateY(-3px);}
.stat-card::after{
    content:'';position:absolute;top:-30px;right:-30px;
    width:100px;height:100px;border-radius:50%;
    background:var(--accent-color);opacity:0.08;
}
.stat-label{font-size:0.8rem;text-transform:uppercase;letter-spacing:1px;color:var(--text-muted);font-weight:800;margin-bottom:0.5rem;}
.stat-val{font-size:2.5rem;font-weight:900;margin-bottom:0.25rem;}
.stat-sub{font-size:0.8rem;color:var(--text-muted);}
.stat-icon{font-size:1.5rem;position:absolute;top:1.25rem;right:1.25rem;opacity:0.6;}

.grid-2{display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;margin-bottom:1.5rem;}
.grid-3{display:grid;grid-template-columns:2fr 1fr;gap:1.5rem;margin-bottom:1.5rem;}

.section-card{
    background:var(--card);border:1px solid var(--border);
    border-radius:20px;padding:1.5rem;
}
.section-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem;}
.section-title-sm{font-size:1rem;font-weight:900;display:flex;align-items:center;gap:0.5rem;}
.view-all{font-size:0.8rem;color:var(--blue);text-decoration:none;font-weight:700;}
.view-all:hover{text-decoration:underline;}

.data-table{width:100%;border-collapse:collapse;}
.data-table th{
    font-size:0.7rem;text-transform:uppercase;letter-spacing:1px;
    color:var(--text-muted);font-weight:800;padding:0.6rem 0.75rem;
    text-align:left;border-bottom:1px solid var(--border);
}
.data-table td{
    padding:0.75rem;font-size:0.9rem;
    border-bottom:1px solid rgba(255,255,255,0.03);vertical-align:middle;
}
.data-table tr:last-child td{border-bottom:none;}
.data-table tr:hover td{background:rgba(255,255,255,0.02);}

.u-avatar{font-size:1.3rem;margin-right:0.5rem;}
.u-name{font-weight:700;}
.level-pill{
    display:inline-block;padding:0.2rem 0.6rem;border-radius:8px;
    font-size:0.75rem;font-weight:800;
    background:rgba(255,217,0,0.15);color:var(--yellow);
}
.xp-bar-mini{height:4px;background:var(--card2);border-radius:2px;width:80px;display:inline-block;vertical-align:middle;margin-left:0.5rem;}
.xp-fill-mini{height:100%;background:var(--green);border-radius:2px;}

.bar-chart{display:flex;align-items:flex-end;gap:0.5rem;height:100px;margin-top:0.5rem;}
.bar-wrap{flex:1;display:flex;flex-direction:column;align-items:center;gap:0.3rem;}
.bar{
    width:100%;border-radius:6px 6px 0 0;
    background:linear-gradient(180deg, var(--blue), #0e6a9a);
    transition:height 0.8s ease;min-height:4px;
}
.bar-label{font-size:0.65rem;color:var(--text-muted);font-weight:700;}
.bar-val{font-size:0.7rem;color:var(--blue);font-weight:800;}

.activity-item{
    display:flex;align-items:center;gap:0.75rem;
    padding:0.6rem 0;border-bottom:1px solid rgba(255,255,255,0.03);
}
.activity-item:last-child{border-bottom:none;}
.act-avatar{font-size:1.2rem;width:30px;text-align:center;}
.act-info{flex:1;}
.act-name{font-weight:700;font-size:0.9rem;}
.act-reason{font-size:0.8rem;color:var(--text-muted);}
.act-xp{color:var(--green);font-weight:800;font-size:0.9rem;white-space:nowrap;}
.act-time{font-size:0.75rem;color:var(--text-muted);white-space:nowrap;}

.lesson-rank-item{
    display:flex;align-items:center;gap:0.75rem;
    padding:0.6rem 0;border-bottom:1px solid rgba(255,255,255,0.03);
}
.lesson-rank-item:last-child{border-bottom:none;}
.rank-num{width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:0.75rem;font-weight:800;background:var(--card2);color:var(--text-muted);}
.rank-num.gold{background:rgba(255,217,0,0.2);color:var(--yellow);}
.rank-num.silver{background:rgba(192,192,192,0.15);color:#ccc;}
.rank-num.bronze{background:rgba(205,127,50,0.15);color:#cd7f32;}
.lesson-info-sm{flex:1;}
.lesson-name-sm{font-size:0.9rem;font-weight:700;}
.lesson-cat-sm{font-size:0.75rem;color:var(--text-muted);}
.completion-count{font-size:0.85rem;font-weight:800;color:var(--blue);}

.quick-actions{display:grid;grid-template-columns:repeat(2,1fr);gap:0.75rem;margin-bottom:1.5rem;}
.qa-btn{
    display:flex;align-items:center;gap:0.75rem;
    background:var(--card);border:1px solid var(--border);
    border-radius:16px;padding:1rem 1.25rem;
    text-decoration:none;color:var(--text);font-weight:700;
    transition:all 0.3s;font-size:0.95rem;
}
.qa-btn:hover{border-color:var(--accent,var(--blue));background:rgba(28,176,246,0.05);transform:translateX(3px);}
.qa-icon{font-size:1.4rem;}

@media(max-width:1100px){.stats-grid{grid-template-columns:repeat(2,1fr);}}
@media(max-width:900px){.grid-2,.grid-3{grid-template-columns:1fr;}}
@media(max-width:768px){
    .sidebar{transform:translateX(-100%);}
    .main{margin-left:0;}
}
</style>
</head>
<body>

<aside class="sidebar">
    <div class="sidebar-logo">
        <span class="logo-icon">??</span>
        <div>
            <div class="logo-text"><span>NihonGo!</span></div>
            <div style="font-size:0.7rem;color:var(--text-muted);font-weight:700">Admin Panel</div>
        </div>
    </div>

    <div class="sidebar-section">Utama</div>
    <a href="index.php" class="nav-item active"><span class="nav-icon">??</span> Dashboard</a>
    <a href="users.php" class="nav-item"><span class="nav-icon">??</span> Manajemen User <span class="nav-badge"><?= $totalUsers ?></span></a>
    <a href="progress.php" class="nav-item"><span class="nav-icon">??</span> Progress User</a>

    <div class="sidebar-section">Konten</div>
    <a href="categories.php" class="nav-item"><span class="nav-icon">???</span> Unit / Kategori</a>
    <a href="lessons.php" class="nav-item"><span class="nav-icon">??</span> Pelajaran</a>
    <a href="questions.php" class="nav-item"><span class="nav-icon">?</span> Soal & Quiz</a>
    <a href="vocabulary.php" class="nav-item"><span class="nav-icon">??</span> Kosakata</a>

    <div class="sidebar-section">Laporan</div>
    <a href="reports.php" class="nav-item"><span class="nav-icon">??</span> Statistik</a>

    <div class="sidebar-section">Sistem</div>
    <a href="../../user/dashboard.php" class="nav-item"><span class="nav-icon">??</span> Lihat Situs</a>

    <div class="sidebar-footer">
        <div class="admin-info">
            <div class="admin-avatar"><?= $adminUser['avatar'] ?></div>
            <div>
                <div class="admin-name"><?= htmlspecialchars($adminUser['username']) ?></div>
                <div class="admin-role">?? Administrator</div>
            </div>
        </div>
        <a href="../index.php?logout=1" class="btn-logout-sm" onclick="return confirm('Keluar?')">?? Logout</a>
    </div>
</aside>

<div class="main">
    <div class="topbar">
        <div class="page-title">?? Dashboard Overview</div>
        <div class="topbar-right">
            <span class="date-badge">?? <?= date('d F Y') ?></span>
            <a href="users.php" style="background:var(--red);color:white;padding:0.45rem 1rem;border-radius:10px;font-weight:800;font-size:0.85rem;text-decoration:none;">+ Tambah User</a>
        </div>
    </div>

    <div class="content">

        <div class="stats-grid">
            <div class="stat-card" style="--accent-color:var(--blue)">
                <div class="stat-icon">??</div>
                <div class="stat-label">Total User</div>
                <div class="stat-val" style="color:var(--blue)"><?= number_format($totalUsers) ?></div>
                <div class="stat-sub">?? <?= $activeToday ?> aktif hari ini · ?? <?= $bannedUsers ?> dibanned</div>
            </div>
            <div class="stat-card" style="--accent-color:var(--green)">
                <div class="stat-icon">?</div>
                <div class="stat-label">Total XP Terkumpul</div>
                <div class="stat-val" style="color:var(--green)"><?= number_format($totalXP) ?></div>
                <div class="stat-sub">Dari seluruh pengguna</div>
            </div>
            <div class="stat-card" style="--accent-color:var(--orange)">
                <div class="stat-icon">?</div>
                <div class="stat-label">Pelajaran Diselesaikan</div>
                <div class="stat-val" style="color:var(--orange)"><?= number_format($completions) ?></div>
                <div class="stat-sub"><?= $totalLessons ?> total pelajaran · <?= $totalQ ?> soal</div>
            </div>
            <div class="stat-card" style="--accent-color:var(--purple)">
                <div class="stat-icon">??</div>
                <div class="stat-label">Total Kosakata</div>
                <div class="stat-val" style="color:var(--purple)"><?= number_format($totalVocab) ?></div>
                <div class="stat-sub">Di semua kategori</div>
            </div>
        </div>

        <div class="quick-actions">
            <a href="users.php" class="qa-btn" style="--accent:var(--blue)"><span class="qa-icon">??</span> Kelola User</a>
            <a href="questions.php?add=1" class="qa-btn" style="--accent:var(--green)"><span class="qa-icon">?</span> Tambah Soal Baru</a>
            <a href="vocabulary.php?add=1" class="qa-btn" style="--accent:var(--purple)"><span class="qa-icon">??</span> Tambah Kosakata</a>
            <a href="reports.php" class="qa-btn" style="--accent:var(--orange)"><span class="qa-icon">??</span> Lihat Laporan</a>
        </div>

        <div class="grid-3">
            
            <div class="section-card">
                <div class="section-header">
                    <div class="section-title-sm">?? Registrasi 7 Hari Terakhir</div>
                </div>
                <?php
                $days = [];
                for ($i = 6; $i >= 0; $i--) {
                    $days[date('Y-m-d', strtotime("-$i days"))] = 0;
                }
                foreach ($newUsersChart as $row) $days[$row['day']] = (int)$row['cnt'];
                $maxVal = max(max(array_values($days)), 1);
                ?>
                <div class="bar-chart">
                    <?php foreach ($days as $date => $cnt): ?>
                    <div class="bar-wrap">
                        <div class="bar-val"><?= $cnt ?></div>
                        <div class="bar" style="height:<?= round(($cnt/$maxVal)*80) ?>px"></div>
                        <div class="bar-label"><?= date('d/m', strtotime($date)) ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="section-card">
                <div class="section-header">
                    <div class="section-title-sm">? Aktivitas Terbaru</div>
                    <a href="reports.php" class="view-all">Lihat Semua ?</a>
                </div>
                <?php foreach ($recentActivity as $act): ?>
                <div class="activity-item">
                    <div class="act-avatar"><?= $act['avatar'] ?></div>
                    <div class="act-info">
                        <div class="act-name"><?= htmlspecialchars($act['username']) ?></div>
                        <div class="act-reason"><?= htmlspecialchars(mb_substr($act['reason'],0,35)) ?></div>
                    </div>
                    <div>
                        <div class="act-xp">+<?= $act['xp_gained'] ?> XP</div>
                        <div class="act-time"><?= date('H:i', strtotime($act['created_at'])) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($recentActivity)): ?><div style="color:var(--text-muted);font-size:0.9rem;text-align:center;padding:1rem">Belum ada aktivitas</div><?php endif; ?>
            </div>
        </div>

        <div class="grid-2">
            <div class="section-card">
                <div class="section-header">
                    <div class="section-title-sm">?? Top Users (XP Terbanyak)</div>
                    <a href="users.php" class="view-all">Kelola User ?</a>
                </div>
                <table class="data-table">
                    <thead>
                        <tr><th>#</th><th>User</th><th>Level</th><th>XP</th><th>??</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topUsers as $i => $u): ?>
                        <tr>
                            <td style="color:var(--text-muted);font-weight:800"><?= $i+1 ?></td>
                            <td><span class="u-avatar"><?= $u['avatar'] ?></span><span class="u-name"><?= htmlspecialchars($u['username']) ?></span></td>
                            <td><span class="level-pill">Lv.<?= $u['level'] ?></span></td>
                            <td style="color:var(--green);font-weight:800"><?= number_format($u['total_xp']) ?></td>
                            <td style="color:var(--orange);font-weight:700"><?= $u['streak_days'] ?>d</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="section-card">
                <div class="section-header">
                    <div class="section-title-sm">?? Pelajaran Terpopuler</div>
                    <a href="lessons.php" class="view-all">Kelola ?</a>
                </div>
                <?php foreach ($topLessons as $i => $l): ?>
                <div class="lesson-rank-item">
                    <div class="rank-num <?= $i===0?'gold':($i===1?'silver':($i===2?'bronze':'')) ?>"><?= $i+1 ?></div>
                    <div class="lesson-info-sm">
                        <div class="lesson-name-sm"><?= htmlspecialchars($l['title']) ?></div>
                        <div class="lesson-cat-sm"><?= $l['icon'] ?> <?= htmlspecialchars($l['cat']) ?></div>
                    </div>
                    <div class="completion-count"><?= $l['cnt'] ?>x</div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($topLessons)): ?><div style="color:var(--text-muted);font-size:0.9rem;text-align:center;padding:1rem">Belum ada data</div><?php endif; ?>
            </div>
        </div>

    </div><!
</div><!-- 
</body>
</html>
