<?php
require_once '../../config/database.php';
requireAdmin();
$db = getDB();

// Stats
$totalUsers     = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalXP        = $db->query("SELECT SUM(total_xp) FROM users")->fetchColumn();
$avgXP          = $db->query("SELECT AVG(total_xp) FROM users")->fetchColumn();
$avgLevel       = $db->query("SELECT AVG(level) FROM users")->fetchColumn();
$maxStreak      = $db->query("SELECT MAX(streak_days) FROM users")->fetchColumn();
$totalCompletions = $db->query("SELECT COUNT(*) FROM user_progress WHERE completed=1")->fetchColumn();
$totalQuestions = $db->query("SELECT COUNT(*) FROM questions")->fetchColumn();
$totalVocab     = $db->query("SELECT COUNT(*) FROM vocabulary")->fetchColumn();

$regChart = $db->query("
    SELECT DATE(created_at) as day, COUNT(*) as cnt
    FROM users WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
    GROUP BY DATE(created_at) ORDER BY day
")->fetchAll(PDO::FETCH_KEY_PAIR);

$regData = [];
for ($i = 29; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $regData[$d] = $regChart[$d] ?? 0;
}

$levelDist = $db->query("
    SELECT level, COUNT(*) as cnt FROM users GROUP BY level ORDER BY level
")->fetchAll();

$mostActive = $db->query("
    SELECT u.username, u.avatar, u.total_xp, u.streak_days, u.level,
        COUNT(up.id) as completions
    FROM users u LEFT JOIN user_progress up ON up.user_id=u.id AND up.completed=1
    GROUP BY u.id ORDER BY completions DESC LIMIT 10
")->fetchAll();

$catEngagement = $db->query("
    SELECT c.name, c.icon, c.color,
        COUNT(DISTINCT l.id) as total_lessons,
        COUNT(up.id) as total_completions,
        COUNT(DISTINCT up.user_id) as unique_users
    FROM categories c
    LEFT JOIN lessons l ON l.category_id=c.id
    LEFT JOIN user_progress up ON up.lesson_id=l.id AND up.completed=1
    GROUP BY c.id ORDER BY total_completions DESC
")->fetchAll();

$xpChart = $db->query("
    SELECT DATE(created_at) as day, SUM(xp_gained) as total_xp
    FROM xp_log WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
    GROUP BY DATE(created_at) ORDER BY day
")->fetchAll(PDO::FETCH_KEY_PAIR);

$xpData = [];
for ($i = 13; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $xpData[$d] = $xpChart[$d] ?? 0;
}

$adminUser = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Laporan & Statistik - Admin NihonGo!</title>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&display=swap" rel="stylesheet">
<style>
:root{
    --red:#FF4B4B;--green:#58CC02;--blue:#1CB0F6;--yellow:#FFD900;
    --purple:#CE82FF;--orange:#FF9600;
    --bg:#070714;--sidebar:#0d0d24;--card:#12122a;--card2:#1a1a3a;
    --text:#ffffff;--text-muted:#8888aa;--border:#1e1e40;--sidebar-w:260px;
}
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Nunito',sans-serif;background:var(--bg);color:var(--text);display:flex;min-height:100vh;}
.sidebar{width:var(--sidebar-w);flex-shrink:0;background:var(--sidebar);border-right:1px solid var(--border);display:flex;flex-direction:column;position:fixed;inset:0 auto 0 0;z-index:200;overflow-y:auto;}
.sidebar-logo{padding:1.75rem 1.5rem;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:0.75rem;}
.logo-text{font-size:1.3rem;font-weight:900;}
.logo-text span{background:linear-gradient(135deg,#FF4B4B,#FF9600);-webkit-background-clip:text;-webkit-text-fill-color:transparent;}
.sidebar-section{padding:1.25rem 1rem 0.5rem;font-size:0.7rem;text-transform:uppercase;letter-spacing:1.5px;color:var(--text-muted);font-weight:800;}
.nav-item{display:flex;align-items:center;gap:0.85rem;padding:0.75rem 1.25rem;border-radius:12px;margin:0.15rem 0.75rem;text-decoration:none;color:var(--text-muted);font-weight:700;font-size:0.95rem;transition:all 0.2s;}
.nav-item:hover{background:var(--card);color:var(--text);}
.nav-item.active{background:linear-gradient(135deg,rgba(255,75,75,0.2),rgba(255,150,0,0.1));color:var(--red);border:1px solid rgba(255,75,75,0.2);}
.nav-icon{font-size:1.15rem;width:24px;text-align:center;}
.sidebar-footer{margin-top:auto;padding:1.25rem;border-top:1px solid var(--border);}
.admin-info{display:flex;align-items:center;gap:0.75rem;}
.admin-name{font-size:0.9rem;font-weight:800;}
.admin-role{font-size:0.75rem;color:var(--orange);font-weight:700;}
.btn-logout-sm{display:block;text-align:center;margin-top:0.75rem;padding:0.5rem;border-radius:10px;border:1px solid var(--border);color:var(--text-muted);text-decoration:none;font-size:0.85rem;font-weight:700;transition:all 0.3s;}
.btn-logout-sm:hover{border-color:var(--red);color:var(--red);}

.main{margin-left:var(--sidebar-w);flex:1;display:flex;flex-direction:column;}
.topbar{height:65px;border-bottom:1px solid var(--border);background:rgba(7,7,20,0.9);backdrop-filter:blur(20px);display:flex;align-items:center;padding:0 2rem;position:sticky;top:0;z-index:100;}
.page-title{font-size:1.4rem;font-weight:900;}
.content{padding:2rem;flex:1;}

.stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:2rem;}
.stat-card{background:var(--card);border:1px solid var(--border);border-radius:20px;padding:1.5rem;position:relative;overflow:hidden;}
.stat-label{font-size:0.75rem;text-transform:uppercase;letter-spacing:1px;color:var(--text-muted);font-weight:800;margin-bottom:0.5rem;}
.stat-val{font-size:2.2rem;font-weight:900;margin-bottom:0.15rem;}
.stat-sub{font-size:0.8rem;color:var(--text-muted);}
.stat-icon{font-size:1.5rem;position:absolute;top:1.25rem;right:1.25rem;opacity:0.5;}

.grid-2{display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;margin-bottom:1.5rem;}
.section-card{background:var(--card);border:1px solid var(--border);border-radius:20px;padding:1.5rem;}
.section-title-sm{font-size:1rem;font-weight:900;margin-bottom:1.25rem;display:flex;align-items:center;gap:0.5rem;}

.line-chart-wrap{position:relative;height:120px;margin-top:0.5rem;}
.line-chart-svg{width:100%;height:100%;}

.hbar-item{margin-bottom:0.9rem;}
.hbar-header{display:flex;justify-content:space-between;margin-bottom:0.3rem;font-size:0.85rem;}
.hbar-name{font-weight:700;}
.hbar-val{color:var(--text-muted);}
.hbar-track{height:10px;background:var(--card2);border-radius:5px;overflow:hidden;}
.hbar-fill{height:100%;border-radius:5px;transition:width 1s;}

.data-table{width:100%;border-collapse:collapse;}
.data-table th{font-size:0.72rem;text-transform:uppercase;letter-spacing:1px;color:var(--text-muted);font-weight:800;padding:0.75rem;text-align:left;border-bottom:1px solid var(--border);}
.data-table td{padding:0.75rem;font-size:0.9rem;border-bottom:1px solid rgba(255,255,255,0.03);vertical-align:middle;}
.data-table tr:last-child td{border-bottom:none;}
.data-table tr:hover td{background:rgba(255,255,255,0.02);}
.level-pill{display:inline-block;padding:0.2rem 0.5rem;border-radius:8px;font-size:0.75rem;font-weight:800;background:rgba(255,217,0,0.15);color:var(--yellow);}

.level-dist{display:flex;align-items:flex-end;gap:0.5rem;height:80px;margin-top:0.75rem;}
.ld-col{flex:1;display:flex;flex-direction:column;align-items:center;gap:0.3rem;}
.ld-bar{width:100%;border-radius:4px 4px 0 0;background:linear-gradient(180deg,var(--purple),#8a3db8);min-height:4px;}
.ld-label{font-size:0.65rem;color:var(--text-muted);font-weight:700;}
.ld-val{font-size:0.65rem;color:var(--purple);font-weight:800;}

@media(max-width:1100px){.stats-grid{grid-template-columns:repeat(2,1fr);}}
@media(max-width:900px){.grid-2{grid-template-columns:1fr;}}
</style>
</head>
<body>

<aside class="sidebar">
    <div class="sidebar-logo">
        <span style="font-size:2rem">??</span>
        <div><div class="logo-text"><span>NihonGo!</span></div><div style="font-size:0.7rem;color:var(--text-muted);font-weight:700">Admin Panel</div></div>
    </div>
    <div class="sidebar-section">Utama</div>
    <a href="index.php" class="nav-item"><span class="nav-icon">??</span> Dashboard</a>
    <a href="users.php" class="nav-item"><span class="nav-icon">??</span> Manajemen User</a>
    <a href="progress.php" class="nav-item"><span class="nav-icon">??</span> Progress User</a>
    <div class="sidebar-section">Konten</div>
    <a href="categories.php" class="nav-item"><span class="nav-icon">???</span> Unit / Kategori</a>
    <a href="lessons.php" class="nav-item"><span class="nav-icon">??</span> Pelajaran</a>
    <a href="questions.php" class="nav-item"><span class="nav-icon">?</span> Soal & Quiz</a>
    <a href="vocabulary.php" class="nav-item"><span class="nav-icon">??</span> Kosakata</a>
    <div class="sidebar-section">Laporan</div>
    <a href="reports.php" class="nav-item active"><span class="nav-icon">??</span> Statistik</a>
    <div class="sidebar-section">Sistem</div>
    <a href="../../user/dashboard.php" class="nav-item"><span class="nav-icon">??</span> Lihat Situs</a>
    <div class="sidebar-footer">
        <div class="admin-info">
            <div style="font-size:1.8rem"><?= $adminUser['avatar'] ?></div>
            <div><div class="admin-name"><?= htmlspecialchars($adminUser['username']) ?></div><div class="admin-role">?? Administrator</div></div>
        </div>
        <a href="../index.php?logout=1" class="btn-logout-sm" onclick="return confirm('Keluar?')">?? Logout</a>
    </div>
</aside>

<div class="main">
    <div class="topbar"><div class="page-title">?? Laporan & Statistik</div></div>
    <div class="content">

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">??</div>
                <div class="stat-label">Total Pengguna</div>
                <div class="stat-val" style="color:var(--blue)"><?= number_format($totalUsers) ?></div>
                <div class="stat-sub">Rata-rata level <?= number_format($avgLevel, 1) ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">?</div>
                <div class="stat-label">Total XP Terkumpul</div>
                <div class="stat-val" style="color:var(--green)"><?= number_format($totalXP) ?></div>
                <div class="stat-sub">Rata-rata <?= number_format($avgXP, 0) ?> XP/user</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">?</div>
                <div class="stat-label">Total Penyelesaian</div>
                <div class="stat-val" style="color:var(--orange)"><?= number_format($totalCompletions) ?></div>
                <div class="stat-sub"><?= $totalQuestions ?> soal · <?= $totalVocab ?> kosakata</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">??</div>
                <div class="stat-label">Streak Terpanjang</div>
                <div class="stat-val" style="color:var(--red)"><?= $maxStreak ?></div>
                <div class="stat-sub">Hari berturut-turut</div>
            </div>
        </div>

        <div class="grid-2">
            
            <div class="section-card">
                <div class="section-title-sm">?? Registrasi 30 Hari Terakhir</div>
                <?php
                $maxReg = max(max(array_values($regData)), 1);
                $pts = [];
                $i = 0;
                $w = 100 / (count($regData) - 1);
                foreach ($regData as $d => $v) {
                    $x = $i * $w;
                    $y = 100 - round(($v/$maxReg)*90);
                    $pts[] = "$x,$y";
                    $i++;
                }
                $polyline = implode(' ', $pts);
                ?>
                <div class="line-chart-wrap">
                    <svg class="line-chart-svg" viewBox="0 0 100 100" preserveAspectRatio="none">
                        <defs>
                            <linearGradient id="grad1" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stop-color="#1CB0F6" stop-opacity="0.4"/>
                                <stop offset="100%" stop-color="#1CB0F6" stop-opacity="0"/>
                            </linearGradient>
                        </defs>
                        <polygon points="0,100 <?= $polyline ?> 100,100" fill="url(#grad1)"/>
                        <polyline points="<?= $polyline ?>" fill="none" stroke="#1CB0F6" stroke-width="2" vector-effect="non-scaling-stroke"/>
                        <?php foreach (array_values($regData) as $i2 => $v):
                            $x = $i2 * $w;
                            $y = 100 - round(($v/$maxReg)*90);
                            if ($v > 0): ?>
                        <circle cx="<?= $x ?>" cy="<?= $y ?>" r="1.5" fill="#1CB0F6" vector-effect="non-scaling-stroke"/>
                        <?php endif; endforeach; ?>
                    </svg>
                </div>
                <div style="display:flex;justify-content:space-between;font-size:0.7rem;color:var(--text-muted);margin-top:0.4rem">
                    <span><?= date('d M', strtotime('-29 days')) ?></span>
                    <span>Hari ini</span>
                </div>
            </div>

            
            <div class="section-card">
                <div class="section-title-sm">? XP Terkumpul 14 Hari Terakhir</div>
                <?php
                $maxXP2 = max(max(array_values($xpData)), 1);
                $pts2 = [];
                $i2 = 0;
                $w2 = 100 / (count($xpData) - 1);
                foreach ($xpData as $d => $v) {
                    $x = $i2 * $w2;
                    $y = 100 - round(($v/$maxXP2)*90);
                    $pts2[] = "$x,$y";
                    $i2++;
                }
                $poly2 = implode(' ', $pts2);
                ?>
                <div class="line-chart-wrap">
                    <svg class="line-chart-svg" viewBox="0 0 100 100" preserveAspectRatio="none">
                        <defs>
                            <linearGradient id="grad2" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stop-color="#58CC02" stop-opacity="0.4"/>
                                <stop offset="100%" stop-color="#58CC02" stop-opacity="0"/>
                            </linearGradient>
                        </defs>
                        <polygon points="0,100 <?= $poly2 ?> 100,100" fill="url(#grad2)"/>
                        <polyline points="<?= $poly2 ?>" fill="none" stroke="#58CC02" stroke-width="2" vector-effect="non-scaling-stroke"/>
                    </svg>
                </div>
                <div style="display:flex;justify-content:space-between;font-size:0.7rem;color:var(--text-muted);margin-top:0.4rem">
                    <span><?= date('d M', strtotime('-13 days')) ?></span>
                    <span>Hari ini · Total: <?= number_format(array_sum($xpData)) ?> XP</span>
                </div>
            </div>
        </div>

        <div class="grid-2">
            
            <div class="section-card">
                <div class="section-title-sm">??? Engagement per Kategori</div>
                <?php
                $maxComp = max(array_column($catEngagement, 'total_completions') ?: [1]);
                $maxComp = max($maxComp, 1);
                foreach ($catEngagement as $cat):
                    $pct = round(($cat['total_completions']/$maxComp)*100);
                ?>
                <div class="hbar-item">
                    <div class="hbar-header">
                        <span class="hbar-name"><?= $cat['icon'] ?> <?= htmlspecialchars($cat['name']) ?></span>
                        <span class="hbar-val"><?= $cat['total_completions'] ?> selesai · <?= $cat['unique_users'] ?> user</span>
                    </div>
                    <div class="hbar-track">
                        <div class="hbar-fill" style="width:<?= $pct ?>%;background:<?= $cat['color'] ?>"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            
            <div class="section-card">
                <div class="section-title-sm">? Distribusi Level User</div>
                <?php
                $maxLvl = max(array_column($levelDist, 'cnt') ?: [1]);
                $maxLvl = max($maxLvl, 1);
                ?>
                <div class="level-dist">
                    <?php foreach ($levelDist as $ld):
                        $h = round(($ld['cnt']/$maxLvl)*70);
                    ?>
                    <div class="ld-col">
                        <div class="ld-val"><?= $ld['cnt'] ?></div>
                        <div class="ld-bar" style="height:<?= $h ?>px"></div>
                        <div class="ld-label">Lv<?= $ld['level'] ?></div>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($levelDist)): ?><div style="color:var(--text-muted);font-size:0.9rem">Belum ada data</div><?php endif; ?>
                </div>
                <div style="margin-top:1.25rem">
                    <div style="font-size:0.85rem;color:var(--text-muted);margin-bottom:0.5rem;font-weight:700">Ringkasan Level</div>
                    <?php foreach ($levelDist as $ld): ?>
                    <div style="display:flex;justify-content:space-between;font-size:0.85rem;padding:0.25rem 0;border-bottom:1px solid rgba(255,255,255,0.03)">
                        <span>Level <?= $ld['level'] ?></span>
                        <span style="color:var(--purple);font-weight:800"><?= $ld['cnt'] ?> user (<?= round($ld['cnt']/$totalUsers*100) ?>%)</span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="section-card">
            <div class="section-title-sm">?? Top 10 User Paling Aktif</div>
            <table class="data-table">
                <thead>
                    <tr><th>#</th><th>User</th><th>Level</th><th>Total XP</th><th>Pelajaran Selesai</th><th>?? Streak</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($mostActive as $i => $u): ?>
                    <tr>
                        <td style="color:var(--text-muted);font-weight:900;font-size:1.1rem">
                            <?= $i===0?'??':($i===1?'??':($i===2?'??':$i+1)) ?>
                        </td>
                        <td>
                            <span style="font-size:1.3rem;margin-right:0.5rem"><?= $u['avatar'] ?></span>
                            <span style="font-weight:800"><?= htmlspecialchars($u['username']) ?></span>
                        </td>
                        <td><span class="level-pill">Lv.<?= $u['level'] ?></span></td>
                        <td style="color:var(--green);font-weight:800"><?= number_format($u['total_xp']) ?></td>
                        <td style="color:var(--blue);font-weight:700"><?= $u['completions'] ?> pelajaran</td>
                        <td style="color:var(--orange);font-weight:700"><?= $u['streak_days'] ?> hari</td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($mostActive)): ?>
                    <tr><td colspan="6" style="text-align:center;padding:2rem;color:var(--text-muted)">Belum ada data</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>
</div>
</body>
</html>
