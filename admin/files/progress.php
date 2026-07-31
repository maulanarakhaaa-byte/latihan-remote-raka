<?php
require_once '../../config/database.php';
requireAdmin();
$db = getDB();

$msg = ''; $msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $uid    = intval($_POST['user_id']   ?? 0);
    $lid    = intval($_POST['lesson_id'] ?? 0);

    if ($action === 'edit' && $uid && $lid) {
        $score     = max(0, intval($_POST['score']));
        $attempts  = max(1, intval($_POST['attempts']));
        $completed = isset($_POST['completed']) ? 1 : 0;
        $db->prepare("UPDATE user_progress SET score=?, attempts=?, completed=? WHERE user_id=? AND lesson_id=?")
           ->execute([$score, $attempts, $completed, $uid, $lid]);
        $msg = 'Progress berhasil diperbarui!'; $msgType = 'success';
    }

    if ($action === 'delete' && $uid && $lid) {
        $db->prepare("DELETE FROM user_progress WHERE user_id=? AND lesson_id=?")->execute([$uid, $lid]);
        $msg = 'Progress berhasil dihapus!'; $msgType = 'success';
    }

    if ($action === 'reset_user' && $uid) {
        $db->prepare("DELETE FROM user_progress WHERE user_id=?")->execute([$uid]);
        $msg = 'Semua progress user berhasil direset!'; $msgType = 'success';
    }
}

// Filters
$userFilter   = intval($_GET['user']   ?? 0);
$lessonFilter = intval($_GET['lesson'] ?? 0);
$search       = trim($_GET['q']        ?? '');

$where  = "WHERE 1=1";
$params = [];
if ($userFilter)   { $where .= " AND up.user_id=?";   $params[] = $userFilter; }
if ($lessonFilter) { $where .= " AND up.lesson_id=?";  $params[] = $lessonFilter; }
if ($search) {
    $where .= " AND (u.username LIKE ? OR l.title LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%";
}

$stmt = $db->prepare("
    SELECT up.user_id, up.lesson_id, up.completed, up.score, up.attempts, up.completed_at,
           u.username, u.avatar, u.level as user_level,
           l.title as lesson_title, l.xp_reward,
           c.name as cat_name, c.icon as cat_icon,
           (SELECT COUNT(*) FROM questions WHERE lesson_id = l.id) as total_q
    FROM user_progress up
    JOIN users u ON u.id = up.user_id
    JOIN lessons l ON l.id = up.lesson_id
    JOIN categories c ON c.id = l.category_id
    $where
    ORDER BY up.completed_at DESC
    LIMIT 200
");
$stmt->execute($params);
$allProgress = $stmt->fetchAll();

$users   = $db->query("SELECT id, username, avatar FROM users ORDER BY username")->fetchAll();
$lessons = $db->query("SELECT l.id, l.title, c.name as cat_name FROM lessons l JOIN categories c ON c.id=l.category_id ORDER BY c.order_num, l.order_num")->fetchAll();

// For edit modal
$editRow = null;
if (isset($_GET['edit_user'], $_GET['edit_lesson'])) {
    $s = $db->prepare("
        SELECT up.*, u.username, l.title as lesson_title
        FROM user_progress up
        JOIN users u ON u.id = up.user_id
        JOIN lessons l ON l.id = up.lesson_id
        WHERE up.user_id=? AND up.lesson_id=?
    ");
    $s->execute([intval($_GET['edit_user']), intval($_GET['edit_lesson'])]);
    $editRow = $s->fetch();
}

$adminUser = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Progress User - Admin NihonGo!</title>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&family=Noto+Serif+JP:wght@400;700&display=swap" rel="stylesheet">
<style>
:root{
    --red:#FF4B4B;--green:#58CC02;--green-dark:#46a801;--blue:#1CB0F6;
    --yellow:#FFD900;--purple:#CE82FF;--orange:#FF9600;
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
.topbar{height:65px;border-bottom:1px solid var(--border);background:rgba(7,7,20,0.9);backdrop-filter:blur(20px);display:flex;align-items:center;justify-content:space-between;padding:0 2rem;position:sticky;top:0;z-index:100;}
.page-title{font-size:1.4rem;font-weight:900;}
.content{padding:2rem;flex:1;}

.alert{padding:1rem 1.25rem;border-radius:14px;margin-bottom:1.5rem;font-weight:700;display:flex;align-items:center;gap:0.75rem;}
.alert.success{background:rgba(88,204,2,0.15);border:1px solid rgba(88,204,2,0.3);color:var(--green);}
.alert.error{background:rgba(255,75,75,0.15);border:1px solid rgba(255,75,75,0.3);color:var(--red);}

.toolbar{display:flex;gap:0.75rem;margin-bottom:1.25rem;flex-wrap:wrap;align-items:center;}
.search-input{flex:1;min-width:200px;padding:0.7rem 1rem;background:var(--card);border:1px solid var(--border);border-radius:12px;color:var(--text);font-family:'Nunito',sans-serif;font-size:0.9rem;}
.search-input:focus{outline:none;border-color:var(--blue);}
.filter-select{padding:0.7rem 1rem;background:var(--card);border:1px solid var(--border);border-radius:12px;color:var(--text);font-family:'Nunito',sans-serif;font-size:0.9rem;}
.filter-select:focus{outline:none;border-color:var(--blue);}
.btn-primary{padding:0.7rem 1.25rem;background:var(--green);color:white;border:none;border-radius:12px;font-family:'Nunito',sans-serif;font-weight:800;font-size:0.9rem;cursor:pointer;transition:all 0.3s;text-decoration:none;display:inline-flex;align-items:center;gap:0.4rem;white-space:nowrap;}
.btn-primary:hover{background:var(--green-dark);}

.table-wrap{background:var(--card);border:1px solid var(--border);border-radius:20px;overflow:hidden;}
.data-table{width:100%;border-collapse:collapse;}
.data-table th{font-size:0.72rem;text-transform:uppercase;letter-spacing:1px;color:var(--text-muted);font-weight:800;padding:0.9rem 1rem;text-align:left;border-bottom:1px solid var(--border);}
.data-table td{padding:0.85rem 1rem;font-size:0.9rem;border-bottom:1px solid rgba(255,255,255,0.03);vertical-align:middle;}
.data-table tr:last-child td{border-bottom:none;}
.data-table tr:hover td{background:rgba(255,255,255,0.02);}

.badge{display:inline-block;padding:0.2rem 0.65rem;border-radius:8px;font-size:0.75rem;font-weight:800;}
.badge-done{background:rgba(88,204,2,0.15);color:var(--green);}
.badge-pending{background:rgba(255,150,0,0.15);color:var(--orange);}

.btn-sm{padding:0.3rem 0.65rem;border:none;border-radius:8px;font-family:'Nunito',sans-serif;font-size:0.75rem;font-weight:800;cursor:pointer;transition:all 0.2s;text-decoration:none;display:inline-block;white-space:nowrap;margin-right:0.3rem;}
.btn-edit{background:rgba(28,176,246,0.2);color:var(--blue);}
.btn-edit:hover{background:var(--blue);color:white;}
.btn-del{background:rgba(255,75,75,0.15);color:var(--red);}
.btn-del:hover{background:var(--red);color:white;}
.btn-orange{background:rgba(255,150,0,0.15);color:var(--orange);}
.btn-orange:hover{background:var(--orange);color:white;}

.score-bar-wrap{display:flex;align-items:center;gap:0.5rem;}
.score-bar{height:6px;border-radius:3px;background:var(--border);flex:1;max-width:60px;}
.score-fill{height:100%;border-radius:3px;background:var(--green);}

/* Modal */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.75);z-index:500;align-items:flex-start;justify-content:center;padding:2rem 1rem;overflow-y:auto;}
.modal-overlay.open{display:flex;}
.modal{background:var(--card);border:1px solid var(--border);border-radius:24px;padding:2rem;width:100%;max-width:500px;margin:auto;animation:slideIn 0.3s ease;}
@keyframes slideIn{from{opacity:0;transform:translateY(-20px)}to{opacity:1;transform:translateY(0)}}
.modal-title{font-size:1.3rem;font-weight:900;margin-bottom:1.5rem;}
.form-group{margin-bottom:1rem;}
.form-group label{display:block;font-size:0.8rem;font-weight:800;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:0.4rem;}
.form-control{width:100%;padding:0.8rem 1rem;background:var(--card2);border:1px solid var(--border);border-radius:12px;color:var(--text);font-family:'Nunito',sans-serif;font-size:0.95rem;transition:all 0.3s;}
.form-control:focus{outline:none;border-color:var(--blue);}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;}
.modal-footer{display:flex;gap:0.75rem;justify-content:flex-end;margin-top:1.5rem;border-top:1px solid var(--border);padding-top:1.25rem;}
.btn-cancel{padding:0.7rem 1.25rem;background:var(--card2);border:1px solid var(--border);border-radius:12px;color:var(--text-muted);font-family:'Nunito',sans-serif;font-weight:800;font-size:0.9rem;cursor:pointer;transition:all 0.3s;}
.btn-cancel:hover{border-color:var(--red);color:var(--red);}
.checkbox-row{display:flex;align-items:center;gap:0.65rem;padding:0.75rem 1rem;background:var(--card2);border:1px solid var(--border);border-radius:12px;}
.checkbox-row input[type=checkbox]{width:18px;height:18px;cursor:pointer;accent-color:var(--green);}
.info-card{background:var(--card2);border:1px solid var(--border);border-radius:14px;padding:1rem 1.25rem;margin-bottom:1rem;}
.info-card-row{display:flex;justify-content:space-between;align-items:center;padding:0.3rem 0;font-size:0.9rem;}
.info-card-row:not(:last-child){border-bottom:1px solid rgba(255,255,255,0.04);}
.info-label{color:var(--text-muted);font-size:0.82rem;}
</style>
</head>
<body>

<aside class="sidebar">
    <div class="sidebar-logo">
        <span style="font-size:2rem">🎌</span>
        <div><div class="logo-text"><span>NihonGo!</span></div><div style="font-size:0.7rem;color:var(--text-muted);font-weight:700">Admin Panel</div></div>
    </div>
    <div class="sidebar-section">Utama</div>
    <a href="index.php" class="nav-item"><span class="nav-icon">📊</span> Dashboard</a>
    <a href="users.php" class="nav-item"><span class="nav-icon">👥</span> Manajemen User</a>
    <a href="progress.php" class="nav-item active"><span class="nav-icon">📝</span> Progress User</a>
    <div class="sidebar-section">Konten</div>
    <a href="categories.php" class="nav-item"><span class="nav-icon">🗂️</span> Unit / Kategori</a>
    <a href="lessons.php" class="nav-item"><span class="nav-icon">📚</span> Pelajaran</a>
    <a href="questions.php" class="nav-item"><span class="nav-icon">❓</span> Soal & Quiz</a>
    <a href="vocabulary.php" class="nav-item"><span class="nav-icon">📖</span> Kosakata</a>
    <div class="sidebar-section">Laporan</div>
    <a href="reports.php" class="nav-item"><span class="nav-icon">📈</span> Statistik</a>
    <div class="sidebar-section">Sistem</div>
    <a href="../../user/dashboard.php" class="nav-item"><span class="nav-icon">🌐</span> Lihat Situs</a>
    <div class="sidebar-footer">
        <div class="admin-info">
            <div style="font-size:1.8rem"><?= $adminUser['avatar'] ?></div>
            <div><div class="admin-name"><?= htmlspecialchars($adminUser['username']) ?></div><div class="admin-role">👑 Administrator</div></div>
        </div>
        <a href="../index.php?logout=1" class="btn-logout-sm" onclick="return confirm('Keluar?')">🚪 Logout</a>
    </div>
</aside>

<div class="main">
    <div class="topbar">
        <div class="page-title">📝 Progress & Jawaban User</div>
        <div style="font-size:0.85rem;color:var(--text-muted)">Total: <?= count($allProgress) ?> record</div>
    </div>

    <div class="content">
        <?php if ($msg): ?>
        <div class="alert <?= $msgType ?>"><?= $msgType==='success'?'✅':'❌' ?> <?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>

        <!-- Toolbar / Filter -->
        <form method="GET" class="toolbar">
            <input type="text" name="q" class="search-input" placeholder="🔍 Cari username atau nama pelajaran..." value="<?= htmlspecialchars($search) ?>">
            <select name="user" class="filter-select" onchange="this.form.submit()">
                <option value="">Semua User</option>
                <?php foreach ($users as $u): ?>
                <option value="<?= $u['id'] ?>" <?= $userFilter==$u['id']?'selected':'' ?>><?= htmlspecialchars($u['avatar'].' '.$u['username']) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="lesson" class="filter-select" onchange="this.form.submit()">
                <option value="">Semua Pelajaran</option>
                <?php foreach ($lessons as $l): ?>
                <option value="<?= $l['id'] ?>" <?= $lessonFilter==$l['id']?'selected':'' ?>><?= htmlspecialchars($l['cat_name'].' > '.$l['title']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn-primary" style="background:var(--blue)">🔍</button>
            <a href="progress.php" class="btn-primary" style="background:var(--card2);border:1px solid var(--border)">↺ Reset</a>
            <?php if ($userFilter): ?>
            <button type="button" class="btn-primary" style="background:rgba(255,75,75,0.2);color:var(--red);border:1px solid rgba(255,75,75,0.3)"
                onclick="if(confirm('Hapus SEMUA progress user ini?'))document.getElementById('resetUserForm').submit()">
                🗑️ Reset Semua Progress User Ini
            </button>
            <form id="resetUserForm" method="POST" style="display:none">
                <input type="hidden" name="action" value="reset_user">
                <input type="hidden" name="user_id" value="<?= $userFilter ?>">
            </form>
            <?php endif; ?>
        </form>

        <!-- Table -->
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Pelajaran</th>
                        <th>Status</th>
                        <th>Skor</th>
                        <th>Percobaan</th>
                        <th>Terakhir Selesai</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($allProgress as $p):
                    $pct = $p['total_q'] > 0 ? round($p['score'] / $p['total_q'] * 100) : 0;
                ?>
                <tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:0.5rem">
                            <span style="font-size:1.4rem"><?= $p['avatar'] ?></span>
                            <div>
                                <div style="font-weight:800"><?= htmlspecialchars($p['username']) ?></div>
                                <div style="font-size:0.75rem;color:var(--text-muted)">Lv.<?= $p['user_level'] ?></div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div style="font-size:0.8rem;color:var(--text-muted)"><?= $p['cat_icon'] ?> <?= htmlspecialchars($p['cat_name']) ?> ›</div>
                        <div style="font-weight:800"><?= htmlspecialchars($p['lesson_title']) ?></div>
                    </td>
                    <td>
                        <?php if ($p['completed']): ?>
                        <span class="badge badge-done">✅ Selesai</span>
                        <?php else: ?>
                        <span class="badge badge-pending">⏳ Belum</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="score-bar-wrap">
                            <span style="font-weight:800;min-width:32px"><?= $p['score'] ?>/<?= $p['total_q'] ?></span>
                            <div class="score-bar">
                                <div class="score-fill" style="width:<?= $pct ?>%;background:<?= $pct>=80?'var(--green)':($pct>=50?'var(--orange)':'var(--red)') ?>"></div>
                            </div>
                            <span style="font-size:0.78rem;color:var(--text-muted)"><?= $pct ?>%</span>
                        </div>
                    </td>
                    <td style="text-align:center">
                        <span style="font-weight:800"><?= $p['attempts'] ?>x</span>
                    </td>
                    <td style="font-size:0.82rem;color:var(--text-muted)">
                        <?= $p['completed_at'] ? date('d M Y H:i', strtotime($p['completed_at'])) : '-' ?>
                    </td>
                    <td>
                        <a href="progress.php?edit_user=<?= $p['user_id'] ?>&edit_lesson=<?= $p['lesson_id'] ?><?= $userFilter?"&user=$userFilter":'' ?><?= $lessonFilter?"&lesson=$lessonFilter":'' ?><?= $search?"&q=".urlencode($search):'' ?>"
                           class="btn-sm btn-edit">✏️ Edit</a>
                        <button class="btn-sm btn-del"
                            onclick="if(confirm('Hapus progress ini?'))document.getElementById('del_<?= $p['user_id'].'_'.$p['lesson_id'] ?>').submit()">🗑️</button>
                        <form id="del_<?= $p['user_id'].'_'.$p['lesson_id'] ?>" method="POST" style="display:none">
                            <input type="hidden" name="action"    value="delete">
                            <input type="hidden" name="user_id"   value="<?= $p['user_id'] ?>">
                            <input type="hidden" name="lesson_id" value="<?= $p['lesson_id'] ?>">
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($allProgress)): ?>
                <tr><td colspan="7" style="text-align:center;padding:3rem;color:var(--text-muted)">🔍 Tidak ada progress ditemukan</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<?php if ($editRow): ?>
<div class="modal-overlay open" id="editModal">
    <div class="modal">
        <div class="modal-title">✏️ Edit Progress</div>
        <div class="info-card">
            <div class="info-card-row">
                <span class="info-label">👤 User</span>
                <strong><?= htmlspecialchars($editRow['username']) ?></strong>
            </div>
            <div class="info-card-row">
                <span class="info-label">📚 Pelajaran</span>
                <strong><?= htmlspecialchars($editRow['lesson_title']) ?></strong>
            </div>
        </div>
        <form method="POST">
            <input type="hidden" name="action"    value="edit">
            <input type="hidden" name="user_id"   value="<?= $editRow['user_id'] ?>">
            <input type="hidden" name="lesson_id" value="<?= $editRow['lesson_id'] ?>">
            <div class="form-row">
                <div class="form-group">
                    <label>Skor (jawaban benar)</label>
                    <input type="number" name="score" class="form-control" min="0" value="<?= $editRow['score'] ?>" required>
                </div>
                <div class="form-group">
                    <label>Jumlah Percobaan</label>
                    <input type="number" name="attempts" class="form-control" min="1" value="<?= $editRow['attempts'] ?>" required>
                </div>
            </div>
            <div class="form-group">
                <label>Status</label>
                <div class="checkbox-row">
                    <input type="checkbox" name="completed" id="chkCompleted" <?= $editRow['completed']?'checked':'' ?>>
                    <label for="chkCompleted" style="cursor:pointer;font-size:0.95rem;color:var(--text)">✅ Tandai sebagai Selesai</label>
                </div>
            </div>
            <div class="modal-footer">
                <a href="progress.php<?= $userFilter?"?user=$userFilter":'' ?>" class="btn-cancel">Batal</a>
                <button type="submit" class="btn-primary">💾 Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

</body>
</html>
