<?php
require_once '../../config/database.php';
requireAdmin();
$db = getDB();

$msg = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $username = trim($_POST['username']);
        $email    = trim($_POST['email']);
        $password = $_POST['password'];
        $role     = $_POST['role'] === 'admin' ? 'admin' : 'user';
        $avatar   = $_POST['avatar'] ?: '??';

        $check = $db->prepare("SELECT id FROM users WHERE email=? OR username=?");
        $check->execute([$email, $username]);
        if ($check->fetch()) {
            $msg = 'Email atau username sudah digunakan!'; $msgType = 'error';
        } elseif (strlen($password) < 6) {
            $msg = 'Password minimal 6 karakter!'; $msgType = 'error';
        } else {
            $db->prepare("INSERT INTO users (username,email,password,avatar,role) VALUES(?,?,?,?,?)")
               ->execute([$username, $email, password_hash($password, PASSWORD_DEFAULT), $avatar, $role]);
            $msg = "User \"$username\" berhasil ditambahkan!"; $msgType = 'success';
        }
    }

    if ($action === 'edit') {
        $id       = intval($_POST['id']);
        $username = trim($_POST['username']);
        $email    = trim($_POST['email']);
        $role     = $_POST['role'] === 'admin' ? 'admin' : 'user';
        $avatar   = $_POST['avatar'] ?: '??';
        $xp       = intval($_POST['total_xp']);
        $streak   = intval($_POST['streak_days']);
        $notes    = trim($_POST['notes'] ?? '');

        $check = $db->prepare("SELECT id FROM users WHERE (email=? OR username=?) AND id!=?");
        $check->execute([$email, $username, $id]);
        if ($check->fetch()) {
            $msg = 'Email atau username sudah digunakan user lain!'; $msgType = 'error';
        } else {
            $sql = "UPDATE users SET username=?,email=?,avatar=?,role=?,total_xp=?,level=GREATEST(1,FLOOR(1+SQRT(?/50))),streak_days=?,notes=? WHERE id=?";
            $db->prepare($sql)->execute([$username, $email, $avatar, $role, $xp, $xp, $streak, $notes, $id]);

            if (!empty($_POST['new_password']) && strlen($_POST['new_password']) >= 6) {
                $db->prepare("UPDATE users SET password=? WHERE id=?")->execute([password_hash($_POST['new_password'], PASSWORD_DEFAULT), $id]);
            }
            $msg = "User \"$username\" berhasil diperbarui!"; $msgType = 'success';
        }
    }

    if ($action === 'ban') {
        $id     = intval($_POST['id']);
        $reason = trim($_POST['ban_reason'] ?? 'Melanggar aturan');
        $db->prepare("UPDATE users SET is_banned=1, ban_reason=? WHERE id=? AND role!='admin'")->execute([$reason, $id]);
        $msg = 'User berhasil dibanned.'; $msgType = 'warning';
    }
    if ($action === 'unban') {
        $id = intval($_POST['id']);
        $db->prepare("UPDATE users SET is_banned=0, ban_reason=NULL WHERE id=?")->execute([$id]);
        $msg = 'User berhasil di-unban.'; $msgType = 'success';
    }

    if ($action === 'delete') {
        $id = intval($_POST['id']);
        $target = $db->prepare("SELECT username,role FROM users WHERE id=?");
        $target->execute([$id]);
        $t = $target->fetch();
        if ($t && $t['role'] !== 'admin') {
            $db->prepare("DELETE FROM users WHERE id=?")->execute([$id]);
            $msg = "User \"{$t['username']}\" berhasil dihapus."; $msgType = 'success';
        } else {
            $msg = 'Tidak bisa hapus akun admin!'; $msgType = 'error';
        }
    }

    if ($action === 'reset_xp') {
        $id = intval($_POST['id']);
        $db->prepare("UPDATE users SET total_xp=0,level=1,streak_days=0 WHERE id=? AND role!='admin'")->execute([$id]);
        $msg = 'XP user berhasil direset.'; $msgType = 'warning';
    }
}

$search   = trim($_GET['q'] ?? '');
$roleFilter = $_GET['role'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$sort = $_GET['sort'] ?? 'created_at';
$order = $_GET['order'] === 'asc' ? 'ASC' : 'DESC';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 15;

$allowedSort = ['username','email','total_xp','level','streak_days','created_at','last_activity'];
if (!in_array($sort, $allowedSort)) $sort = 'created_at';

$where = "WHERE 1=1";
$params = [];
if ($search) {
    $where .= " AND (username LIKE ? OR email LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%";
}
if ($roleFilter) { $where .= " AND role=?"; $params[] = $roleFilter; }
if ($statusFilter === 'banned') { $where .= " AND is_banned=1"; }
if ($statusFilter === 'active') { $where .= " AND is_banned=0 AND last_activity=CURDATE()"; }
if ($statusFilter === 'inactive') { $where .= " AND (last_activity IS NULL OR last_activity < DATE_SUB(CURDATE(), INTERVAL 7 DAY))"; }

$totalCount = $db->prepare("SELECT COUNT(*) FROM users $where");
$totalCount->execute($params);
$total = $totalCount->fetchColumn();
$totalPages = ceil($total / $perPage);
$offset = ($page - 1) * $perPage;

$stmt = $db->prepare("SELECT * FROM users $where ORDER BY $sort $order LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$users = $stmt->fetchAll();

$editUser = null;
if (isset($_GET['edit'])) {
    $s = $db->prepare("SELECT * FROM users WHERE id=?");
    $s->execute([intval($_GET['edit'])]);
    $editUser = $s->fetch();
}

$adminUser = getCurrentUser();
$avatarOptions = ['??','??','??','??','??','??','??','??','??','??','??','??','?','??','??'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manajemen User - Admin NihonGo!</title>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&display=swap" rel="stylesheet">
<style>
:root{
    --red:#FF4B4B;--red-dark:#c0392b;--green:#58CC02;--green-dark:#46a801;
    --blue:#1CB0F6;--yellow:#FFD900;--purple:#CE82FF;--orange:#FF9600;
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
.nav-badge{margin-left:auto;background:var(--red);color:white;font-size:0.7rem;padding:0.1rem 0.5rem;border-radius:10px;font-weight:800;}
.sidebar-footer{margin-top:auto;padding:1.25rem;border-top:1px solid var(--border);}
.admin-info{display:flex;align-items:center;gap:0.75rem;}
.admin-avatar{font-size:1.8rem;}
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
.alert.warning{background:rgba(255,150,0,0.15);border:1px solid rgba(255,150,0,0.3);color:var(--orange);}

.mini-stats{display:flex;gap:1rem;margin-bottom:1.5rem;}
.mini-stat{background:var(--card);border:1px solid var(--border);border-radius:14px;padding:1rem 1.25rem;flex:1;text-align:center;}
.mini-stat-val{font-size:1.8rem;font-weight:900;}
.mini-stat-label{font-size:0.75rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.5px;}

.toolbar{display:flex;gap:0.75rem;margin-bottom:1.25rem;flex-wrap:wrap;align-items:center;}
.search-input{flex:1;min-width:200px;padding:0.7rem 1rem;background:var(--card);border:1px solid var(--border);border-radius:12px;color:var(--text);font-family:'Nunito',sans-serif;font-size:0.9rem;transition:all 0.3s;}
.search-input:focus{outline:none;border-color:var(--blue);}
.filter-select{padding:0.7rem 1rem;background:var(--card);border:1px solid var(--border);border-radius:12px;color:var(--text);font-family:'Nunito',sans-serif;font-size:0.9rem;cursor:pointer;}
.filter-select:focus{outline:none;border-color:var(--blue);}
.btn-primary{padding:0.7rem 1.25rem;background:var(--green);color:white;border:none;border-radius:12px;font-family:'Nunito',sans-serif;font-weight:800;font-size:0.9rem;cursor:pointer;transition:all 0.3s;text-decoration:none;display:inline-flex;align-items:center;gap:0.4rem;}
.btn-primary:hover{background:var(--green-dark);transform:translateY(-1px);}
.btn-blue{background:var(--blue);}
.btn-blue:hover{background:var(--blue-dark);}
.btn-red{background:var(--red);}
.btn-red:hover{background:var(--red-dark);}

.table-wrap{background:var(--card);border:1px solid var(--border);border-radius:20px;overflow:hidden;}
.data-table{width:100%;border-collapse:collapse;}
.data-table th{font-size:0.75rem;text-transform:uppercase;letter-spacing:1px;color:var(--text-muted);font-weight:800;padding:1rem;text-align:left;border-bottom:1px solid var(--border);white-space:nowrap;cursor:pointer;}
.data-table th a{color:inherit;text-decoration:none;}
.data-table th:hover{color:var(--text);}
.data-table td{padding:0.85rem 1rem;font-size:0.9rem;border-bottom:1px solid rgba(255,255,255,0.03);vertical-align:middle;}
.data-table tr:last-child td{border-bottom:none;}
.data-table tr:hover td{background:rgba(255,255,255,0.02);}
.data-table .actions{display:flex;gap:0.4rem;flex-wrap:wrap;}

.u-cell{display:flex;align-items:center;gap:0.6rem;}
.u-avatar{font-size:1.4rem;}
.u-info .u-name{font-weight:800;font-size:0.95rem;}
.u-info .u-email{font-size:0.78rem;color:var(--text-muted);}

.badge{display:inline-block;padding:0.2rem 0.6rem;border-radius:8px;font-size:0.75rem;font-weight:800;white-space:nowrap;}
.badge-admin{background:rgba(255,75,75,0.2);color:var(--red);}
.badge-user{background:rgba(28,176,246,0.15);color:var(--blue);}
.badge-banned{background:rgba(255,75,75,0.2);color:var(--red);}
.badge-active{background:rgba(88,204,2,0.15);color:var(--green);}
.badge-inactive{background:rgba(136,136,170,0.15);color:var(--text-muted);}
.level-pill{display:inline-block;padding:0.2rem 0.5rem;border-radius:8px;font-size:0.75rem;font-weight:800;background:rgba(255,217,0,0.15);color:var(--yellow);}

.btn-sm{padding:0.3rem 0.65rem;border:none;border-radius:8px;font-family:'Nunito',sans-serif;font-size:0.75rem;font-weight:800;cursor:pointer;transition:all 0.2s;text-decoration:none;display:inline-block;white-space:nowrap;}
.btn-edit{background:rgba(28,176,246,0.2);color:var(--blue);}
.btn-edit:hover{background:var(--blue);color:white;}
.btn-ban{background:rgba(255,150,0,0.2);color:var(--orange);}
.btn-ban:hover{background:var(--orange);color:white;}
.btn-unban{background:rgba(88,204,2,0.2);color:var(--green);}
.btn-unban:hover{background:var(--green);color:white;}
.btn-del{background:rgba(255,75,75,0.15);color:var(--red);}
.btn-del:hover{background:var(--red);color:white;}
.btn-reset{background:rgba(206,130,255,0.15);color:var(--purple);}
.btn-reset:hover{background:var(--purple);color:white;}

.pagination{display:flex;gap:0.4rem;justify-content:center;margin-top:1.25rem;flex-wrap:wrap;}
.page-btn{padding:0.4rem 0.75rem;border-radius:8px;background:var(--card);border:1px solid var(--border);color:var(--text-muted);text-decoration:none;font-weight:700;font-size:0.85rem;transition:all 0.2s;}
.page-btn:hover,.page-btn.active{background:var(--blue);color:white;border-color:var(--blue);}

.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.7);z-index:500;align-items:center;justify-content:center;padding:1rem;}
.modal-overlay.open{display:flex;}
.modal{background:var(--card);border:1px solid var(--border);border-radius:24px;padding:2rem;width:100%;max-width:520px;max-height:90vh;overflow-y:auto;animation:slideIn 0.3s ease;}
@keyframes slideIn{from{opacity:0;transform:translateY(-20px)}to{opacity:1;transform:translateY(0)}}
.modal-title{font-size:1.3rem;font-weight:900;margin-bottom:1.5rem;display:flex;align-items:center;gap:0.5rem;}
.form-group{margin-bottom:1rem;}
.form-group label{display:block;font-size:0.8rem;font-weight:800;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:0.4rem;}
.form-control{width:100%;padding:0.8rem 1rem;background:var(--card2);border:1px solid var(--border);border-radius:12px;color:var(--text);font-family:'Nunito',sans-serif;font-size:0.95rem;transition:all 0.3s;}
.form-control:focus{outline:none;border-color:var(--blue);}
select.form-control option{background:var(--card2);}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;}
.avatar-picker{display:flex;flex-wrap:wrap;gap:0.4rem;margin-top:0.4rem;}
.avatar-opt{font-size:1.5rem;cursor:pointer;padding:0.3rem;border-radius:8px;border:2px solid transparent;transition:all 0.2s;}
.avatar-opt:hover,.avatar-opt.selected{border-color:var(--blue);background:rgba(28,176,246,0.1);}
.modal-footer{display:flex;gap:0.75rem;justify-content:flex-end;margin-top:1.5rem;border-top:1px solid var(--border);padding-top:1.25rem;}
.btn-cancel{padding:0.7rem 1.25rem;background:var(--card2);border:1px solid var(--border);border-radius:12px;color:var(--text-muted);font-family:'Nunito',sans-serif;font-weight:800;font-size:0.9rem;cursor:pointer;transition:all 0.3s;}
.btn-cancel:hover{border-color:var(--red);color:var(--red);}

.ban-modal{background:var(--card);border:1px solid var(--border);border-radius:20px;padding:1.75rem;width:100%;max-width:420px;}
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
    <a href="users.php" class="nav-item active"><span class="nav-icon">??</span> Manajemen User</a>
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
            <div><div class="admin-name"><?= htmlspecialchars($adminUser['username']) ?></div><div class="admin-role">?? Administrator</div></div>
        </div>
        <a href="../index.php?logout=1" class="btn-logout-sm" onclick="return confirm('Keluar?')">?? Logout</a>
    </div>
</aside>

<div class="main">
    <div class="topbar">
        <div class="page-title">?? Manajemen User</div>
        <div style="display:flex;gap:0.75rem">
            <button onclick="openModal('addModal')" class="btn-primary">? Tambah User</button>
        </div>
    </div>

    <div class="content">

        <?php if ($msg): ?>
        <div class="alert <?= $msgType ?>">
            <?= $msgType==='success'?'?':($msgType==='error'?'?':'??') ?> <?= htmlspecialchars($msg) ?>
        </div>
        <?php endif; ?>

        <?php
        $totalAll  = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $totalAdmin = $db->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();
        $totalBanned= $db->query("SELECT COUNT(*) FROM users WHERE is_banned=1")->fetchColumn();
        $totalActive= $db->query("SELECT COUNT(*) FROM users WHERE last_activity=CURDATE()")->fetchColumn();
        ?>
        <div class="mini-stats">
            <div class="mini-stat"><div class="mini-stat-val" style="color:var(--blue)"><?= $totalAll ?></div><div class="mini-stat-label">Total Akun</div></div>
            <div class="mini-stat"><div class="mini-stat-val" style="color:var(--green)"><?= $totalActive ?></div><div class="mini-stat-label">Aktif Hari Ini</div></div>
            <div class="mini-stat"><div class="mini-stat-val" style="color:var(--red)"><?= $totalBanned ?></div><div class="mini-stat-label">Dibanned</div></div>
            <div class="mini-stat"><div class="mini-stat-val" style="color:var(--orange)"><?= $totalAdmin ?></div><div class="mini-stat-label">Admin</div></div>
        </div>

        <form method="GET" class="toolbar">
            <input type="text" name="q" class="search-input" placeholder="?? Cari username atau email..." value="<?= htmlspecialchars($search) ?>">
            <select name="role" class="filter-select" onchange="this.form.submit()">
                <option value="">Semua Role</option>
                <option value="user" <?= $roleFilter==='user'?'selected':'' ?>>User</option>
                <option value="admin" <?= $roleFilter==='admin'?'selected':'' ?>>Admin</option>
            </select>
            <select name="status" class="filter-select" onchange="this.form.submit()">
                <option value="">Semua Status</option>
                <option value="active" <?= $statusFilter==='active'?'selected':'' ?>>Aktif Hari Ini</option>
                <option value="inactive" <?= $statusFilter==='inactive'?'selected':'' ?>>Tidak Aktif 7hr</option>
                <option value="banned" <?= $statusFilter==='banned'?'selected':'' ?>>Dibanned</option>
            </select>
            <select name="sort" class="filter-select" onchange="this.form.submit()">
                <option value="created_at" <?= $sort==='created_at'?'selected':'' ?>>Terbaru</option>
                <option value="total_xp" <?= $sort==='total_xp'?'selected':'' ?>>XP Terbanyak</option>
                <option value="level" <?= $sort==='level'?'selected':'' ?>>Level Tertinggi</option>
                <option value="streak_days" <?= $sort==='streak_days'?'selected':'' ?>>Streak Terpanjang</option>
                <option value="username" <?= $sort==='username'?'selected':'' ?>>Username A-Z</option>
                <option value="last_activity" <?= $sort==='last_activity'?'selected':'' ?>>Aktivitas Terakhir</option>
            </select>
            <button type="submit" class="btn-primary btn-blue">?? Cari</button>
            <a href="users.php" class="btn-primary" style="background:var(--card2);border:1px solid var(--border)">? Reset</a>
        </form>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th><a href="?sort=username&order=<?= $order==='DESC'?'asc':'desc' ?>&q=<?= urlencode($search) ?>">User <?= $sort==='username'?($order==='DESC'?'?':'?'):'' ?></a></th>
                        <th>Role</th>
                        <th><a href="?sort=level&order=<?= $order==='DESC'?'asc':'desc' ?>&q=<?= urlencode($search) ?>">Level</a></th>
                        <th><a href="?sort=total_xp&order=<?= $order==='DESC'?'asc':'desc' ?>&q=<?= urlencode($search) ?>">XP</a></th>
                        <th><a href="?sort=streak_days&order=<?= $order==='DESC'?'asc':'desc' ?>&q=<?= urlencode($search) ?>">?? Streak</a></th>
                        <th>Status</th>
                        <th><a href="?sort=last_activity&order=<?= $order==='DESC'?'asc':'desc' ?>&q=<?= urlencode($search) ?>">Last Active</a></th>
                        <th><a href="?sort=created_at&order=<?= $order==='DESC'?'asc':'desc' ?>&q=<?= urlencode($search) ?>">Bergabung</a></th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $u):
                    $isActive = $u['last_activity'] === date('Y-m-d');
                    $statusClass = $u['is_banned'] ? 'banned' : ($isActive ? 'active' : 'inactive');
                    $statusLabel = $u['is_banned'] ? '?? Banned' : ($isActive ? '?? Aktif' : '? Offline');
                ?>
                <tr>
                    <td>
                        <div class="u-cell">
                            <div class="u-avatar"><?= $u['avatar'] ?></div>
                            <div class="u-info">
                                <div class="u-name"><?= htmlspecialchars($u['username']) ?></div>
                                <div class="u-email"><?= htmlspecialchars($u['email']) ?></div>
                                <?php if ($u['notes']): ?><div style="font-size:0.7rem;color:var(--purple);margin-top:2px">?? <?= htmlspecialchars(mb_substr($u['notes'],0,40)) ?></div><?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td><span class="badge badge-<?= $u['role'] ?>"><?= $u['role']==='admin'?'?? Admin':'?? User' ?></span></td>
                    <td><span class="level-pill">Lv.<?= $u['level'] ?></span></td>
                    <td style="color:var(--green);font-weight:800"><?= number_format($u['total_xp']) ?></td>
                    <td style="color:var(--orange);font-weight:700"><?= $u['streak_days'] ?> hari</td>
                    <td>
                        <span class="badge badge-<?= $statusClass ?>"><?= $statusLabel ?></span>
                        <?php if ($u['is_banned'] && $u['ban_reason']): ?>
                        <div style="font-size:0.7rem;color:var(--text-muted);margin-top:2px"><?= htmlspecialchars(mb_substr($u['ban_reason'],0,30)) ?></div>
                        <?php endif; ?>
                    </td>
                    <td style="color:var(--text-muted);font-size:0.85rem"><?= $u['last_activity'] ? date('d/m/y', strtotime($u['last_activity'])) : '-' ?></td>
                    <td style="color:var(--text-muted);font-size:0.85rem"><?= date('d/m/y', strtotime($u['created_at'])) ?></td>
                    <td>
                        <div class="actions">
                            <button class="btn-sm btn-edit" onclick="openEdit(<?= htmlspecialchars(json_encode($u)) ?>)">?? Edit</button>
                            <?php if (!$u['is_banned'] && $u['role']!=='admin'): ?>
                            <button class="btn-sm btn-ban" onclick="openBan(<?= $u['id'] ?>, '<?= htmlspecialchars($u['username']) ?>')">?? Ban</button>
                            <?php elseif ($u['is_banned']): ?>
                            <form method="POST" style="display:inline"><input type="hidden" name="action" value="unban"><input type="hidden" name="id" value="<?= $u['id'] ?>"><button type="submit" class="btn-sm btn-unban">? Unban</button></form>
                            <?php endif; ?>
                            <?php if ($u['role']!=='admin'): ?>
                            <button class="btn-sm btn-reset" onclick="if(confirm('Reset XP user ini?'))document.getElementById('resetForm<?= $u['id'] ?>').submit()">?? Reset XP</button>
                            <form id="resetForm<?= $u['id'] ?>" method="POST" style="display:none"><input type="hidden" name="action" value="reset_xp"><input type="hidden" name="id" value="<?= $u['id'] ?>"></form>
                            <button class="btn-sm btn-del" onclick="if(confirm('Hapus user \"<?= htmlspecialchars($u['username']) ?>\"? Data progress ikut terhapus!'))document.getElementById('delForm<?= $u['id'] ?>').submit()">???</button>
                            <form id="delForm<?= $u['id'] ?>" method="POST" style="display:none"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $u['id'] ?>"></form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($users)): ?>
                <tr><td colspan="9" style="text-align:center;padding:3rem;color:var(--text-muted)">?? Tidak ada user ditemukan</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <span style="color:var(--text-muted);font-size:0.85rem;padding:0.4rem">Total: <?= $total ?> user</span>
            <?php for ($i=1;$i<=$totalPages;$i++): ?>
            <a href="?page=<?= $i ?>&q=<?= urlencode($search) ?>&role=<?= $roleFilter ?>&status=<?= $statusFilter ?>&sort=<?= $sort ?>&order=<?= strtolower($order) ?>"
               class="page-btn <?= $i==$page?'active':'' ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>

    </div>
</div>

<div class="modal-overlay" id="addModal">
    <div class="modal">
        <div class="modal-title">? Tambah User Baru</div>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="form-row">
                <div class="form-group">
                    <label>Username *</label>
                    <input type="text" name="username" class="form-control" required placeholder="username123">
                </div>
                <div class="form-group">
                    <label>Role</label>
                    <select name="role" class="form-control">
                        <option value="user">?? User</option>
                        <option value="admin">?? Admin</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Email *</label>
                <input type="email" name="email" class="form-control" required placeholder="user@email.com">
            </div>
            <div class="form-group">
                <label>Password * (min 6 karakter)</label>
                <input type="password" name="password" class="form-control" required placeholder="••••••••">
            </div>
            <div class="form-group">
                <label>Pilih Avatar</label>
                <div class="avatar-picker" id="addAvatarPicker">
                    <?php foreach ($avatarOptions as $av): ?>
                    <span class="avatar-opt <?= $av==='??'?'selected':'' ?>" onclick="selectAvatar(this,'addAvatar')"><?= $av ?></span>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" name="avatar" id="addAvatar" value="??">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeModal('addModal')">Batal</button>
                <button type="submit" class="btn-primary">?? Simpan User</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="editModal">
    <div class="modal">
        <div class="modal-title">?? Edit User</div>
        <form method="POST" id="editForm">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="editId">
            <div class="form-row">
                <div class="form-group">
                    <label>Username *</label>
                    <input type="text" name="username" id="editUsername" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Role</label>
                    <select name="role" id="editRole" class="form-control">
                        <option value="user">?? User</option>
                        <option value="admin">?? Admin</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Email *</label>
                <input type="email" name="email" id="editEmail" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Password Baru (kosongkan jika tidak diganti)</label>
                <input type="password" name="new_password" class="form-control" placeholder="••••••••">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Total XP</label>
                    <input type="number" name="total_xp" id="editXP" class="form-control" min="0">
                </div>
                <div class="form-group">
                    <label>Streak Hari</label>
                    <input type="number" name="streak_days" id="editStreak" class="form-control" min="0">
                </div>
            </div>
            <div class="form-group">
                <label>Catatan Admin</label>
                <input type="text" name="notes" id="editNotes" class="form-control" placeholder="Catatan tentang user ini...">
            </div>
            <div class="form-group">
                <label>Avatar</label>
                <div class="avatar-picker" id="editAvatarPicker">
                    <?php foreach ($avatarOptions as $av): ?>
                    <span class="avatar-opt" onclick="selectAvatar(this,'editAvatar')"><?= $av ?></span>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" name="avatar" id="editAvatar">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeModal('editModal')">Batal</button>
                <button type="submit" class="btn-primary">?? Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="banModal">
    <div class="ban-modal">
        <div class="modal-title" style="font-size:1.1rem">?? Ban User: <span id="banUsername" style="color:var(--red)"></span></div>
        <form method="POST">
            <input type="hidden" name="action" value="ban">
            <input type="hidden" name="id" id="banUserId">
            <div class="form-group">
                <label>Alasan Ban</label>
                <input type="text" name="ban_reason" class="form-control" placeholder="Contoh: Melanggar aturan komunitas" required>
            </div>
            <div class="modal-footer" style="margin-top:1rem;padding-top:1rem">
                <button type="button" class="btn-cancel" onclick="closeModal('banModal')">Batal</button>
                <button type="submit" class="btn-primary btn-red">?? Konfirmasi Ban</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(id) { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

document.querySelectorAll('.modal-overlay').forEach(o => {
    o.addEventListener('click', e => { if (e.target === o) o.classList.remove('open'); });
});

function selectAvatar(el, inputId) {
    el.closest('.avatar-picker').querySelectorAll('.avatar-opt').forEach(e => e.classList.remove('selected'));
    el.classList.add('selected');
    document.getElementById(inputId).value = el.textContent;
}

function openEdit(user) {
    document.getElementById('editId').value = user.id;
    document.getElementById('editUsername').value = user.username;
    document.getElementById('editEmail').value = user.email;
    document.getElementById('editRole').value = user.role;
    document.getElementById('editXP').value = user.total_xp;
    document.getElementById('editStreak').value = user.streak_days;
    document.getElementById('editNotes').value = user.notes || '';
    document.getElementById('editAvatar').value = user.avatar;

    document.querySelectorAll('#editAvatarPicker .avatar-opt').forEach(el => {
        el.classList.toggle('selected', el.textContent === user.avatar);
    });
    openModal('editModal');
}

function openBan(id, username) {
    document.getElementById('banUserId').value = id;
    document.getElementById('banUsername').textContent = username;
    openModal('banModal');
}
</script>
</body>
</html>
