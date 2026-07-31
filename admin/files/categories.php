<?php
require_once '../../config/database.php';
requireAdmin();
$db = getDB();

$msg = ''; $msgType = '';
$page = $_GET['page'] ?? 'categories'; 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $entity = $_POST['entity'] ?? 'category';

    if ($entity === 'category') {
        if ($action === 'add' || $action === 'edit') {
            $name       = trim($_POST['name']);
            $nameJp     = trim($_POST['name_jp'] ?? '');
            $desc       = trim($_POST['description'] ?? '');
            $icon       = trim($_POST['icon'] ?? '??');
            $color      = trim($_POST['color'] ?? '#58CC02');
            $orderNum   = intval($_POST['order_num'] ?? 0);
            $reqLevel   = intval($_POST['required_level'] ?? 1);

            if (!$name) { $msg = 'Nama kategori harus diisi!'; $msgType = 'error'; }
            else {
                if ($action === 'add') {
                    $db->prepare("INSERT INTO categories (name,name_jp,description,icon,color,order_num,required_level) VALUES(?,?,?,?,?,?,?)")
                       ->execute([$name,$nameJp,$desc,$icon,$color,$orderNum,$reqLevel]);
                    $msg = "Kategori \"$name\" berhasil ditambahkan!"; $msgType = 'success';
                } else {
                    $db->prepare("UPDATE categories SET name=?,name_jp=?,description=?,icon=?,color=?,order_num=?,required_level=? WHERE id=?")
                       ->execute([$name,$nameJp,$desc,$icon,$color,$orderNum,$reqLevel,intval($_POST['id'])]);
                    $msg = "Kategori berhasil diperbarui!"; $msgType = 'success';
                }
            }
        }
        if ($action === 'delete') {
            $db->prepare("DELETE FROM categories WHERE id=?")->execute([intval($_POST['id'])]);
            $msg = 'Kategori dan semua pelajarannya berhasil dihapus!'; $msgType = 'success';
        }
    }

    if ($entity === 'lesson') {
        if ($action === 'add' || $action === 'edit') {
            $catId    = intval($_POST['category_id']);
            $title    = trim($_POST['title']);
            $titleJp  = trim($_POST['title_jp'] ?? '');
            $desc     = trim($_POST['description'] ?? '');
            $orderNum = intval($_POST['order_num'] ?? 0);
            $xpReward = intval($_POST['xp_reward'] ?? 10);

            if (!$catId || !$title) { $msg = 'Kategori dan judul harus diisi!'; $msgType = 'error'; }
            else {
                if ($action === 'add') {
                    $db->prepare("INSERT INTO lessons (category_id,title,title_jp,description,order_num,xp_reward) VALUES(?,?,?,?,?,?)")
                       ->execute([$catId,$title,$titleJp,$desc,$orderNum,$xpReward]);
                    $msg = "Pelajaran \"$title\" berhasil ditambahkan!"; $msgType = 'success';
                } else {
                    $db->prepare("UPDATE lessons SET category_id=?,title=?,title_jp=?,description=?,order_num=?,xp_reward=? WHERE id=?")
                       ->execute([$catId,$title,$titleJp,$desc,$orderNum,$xpReward,intval($_POST['id'])]);
                    $msg = "Pelajaran berhasil diperbarui!"; $msgType = 'success';
                }
            }
        }
        if ($action === 'delete') {
            $db->prepare("DELETE FROM lessons WHERE id=?")->execute([intval($_POST['id'])]);
            $msg = 'Pelajaran dan semua soalnya berhasil dihapus!'; $msgType = 'success';
        }
    }
    $page = $_POST['page'] ?? $page;
}

$categories = $db->query("SELECT c.*, COUNT(DISTINCT l.id) as lesson_count, COUNT(DISTINCT q.id) as q_count FROM categories c LEFT JOIN lessons l ON l.category_id=c.id LEFT JOIN questions q ON q.lesson_id=l.id GROUP BY c.id ORDER BY c.order_num")->fetchAll();
$lessons = $db->query("SELECT l.*, c.name as cat_name, c.color as cat_color, COUNT(q.id) as q_count, COUNT(up.id) as completions FROM lessons l JOIN categories c ON c.id=l.category_id LEFT JOIN questions q ON q.lesson_id=l.id LEFT JOIN user_progress up ON up.lesson_id=l.id AND up.completed=1 GROUP BY l.id ORDER BY c.order_num, l.order_num")->fetchAll();
$adminUser = getCurrentUser();
$iconOptions = ['??','??','??','??','???????????','??','??','??','??','??','??','??','??','??','??','??'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $page==='categories'?'Kategori':'Pelajaran' ?> - Admin NihonGo!</title>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&family=Noto+Serif+JP:wght@400;700&display=swap" rel="stylesheet">
<style>
:root{--red:#FF4B4B;--green:#58CC02;--green-dark:#46a801;--blue:#1CB0F6;--yellow:#FFD900;--purple:#CE82FF;--orange:#FF9600;--bg:#070714;--sidebar:#0d0d24;--card:#12122a;--card2:#1a1a3a;--text:#ffffff;--text-muted:#8888aa;--border:#1e1e40;--sidebar-w:260px;}
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

/* Tabs */
.page-tabs{display:flex;gap:0.5rem;background:var(--card);border:1px solid var(--border);border-radius:14px;padding:4px;margin-bottom:1.5rem;width:fit-content;}
.tab-btn{padding:0.6rem 1.25rem;border:none;border-radius:10px;font-family:'Nunito',sans-serif;font-weight:700;font-size:0.9rem;cursor:pointer;color:var(--text-muted);background:transparent;transition:all 0.2s;text-decoration:none;}
.tab-btn.active{background:var(--red);color:white;}

.btn-primary{padding:0.7rem 1.25rem;background:var(--green);color:white;border:none;border-radius:12px;font-family:'Nunito',sans-serif;font-weight:800;font-size:0.9rem;cursor:pointer;transition:all 0.3s;text-decoration:none;display:inline-flex;align-items:center;gap:0.4rem;white-space:nowrap;}
.btn-primary:hover{background:var(--green-dark);}

/* Grid cards */
.cat-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:1rem;}
.cat-admin-card{background:var(--card);border:2px solid var(--border);border-radius:20px;padding:1.5rem;transition:all 0.3s;position:relative;}
.cat-admin-card:hover{border-color:var(--cat-color,var(--blue));transform:translateY(-2px);}
.cat-color-bar{position:absolute;top:0;left:0;right:0;height:3px;border-radius:18px 18px 0 0;background:var(--cat-color,var(--blue));}
.cat-header-row{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:0.75rem;}
.cat-icon-big{font-size:2.5rem;}
.cat-actions{display:flex;gap:0.4rem;}
.cat-name-big{font-size:1.1rem;font-weight:900;margin-bottom:0.15rem;}
.cat-name-jp{font-family:'Noto Serif JP',serif;font-size:0.85rem;color:var(--text-muted);margin-bottom:0.5rem;}
.cat-stats{display:flex;gap:0.75rem;font-size:0.8rem;}
.cat-stat-item{background:var(--card2);padding:0.25rem 0.6rem;border-radius:8px;color:var(--text-muted);}
.cat-stat-item strong{color:var(--text);}
.btn-sm{padding:0.3rem 0.65rem;border:none;border-radius:8px;font-family:'Nunito',sans-serif;font-size:0.75rem;font-weight:800;cursor:pointer;transition:all 0.2s;text-decoration:none;display:inline-block;white-space:nowrap;}
.btn-edit{background:rgba(28,176,246,0.2);color:var(--blue);}
.btn-edit:hover{background:var(--blue);color:white;}
.btn-del{background:rgba(255,75,75,0.15);color:var(--red);}
.btn-del:hover{background:var(--red);color:white;}

/* Table for lessons */
.table-wrap{background:var(--card);border:1px solid var(--border);border-radius:20px;overflow:hidden;}
.data-table{width:100%;border-collapse:collapse;}
.data-table th{font-size:0.72rem;text-transform:uppercase;letter-spacing:1px;color:var(--text-muted);font-weight:800;padding:0.9rem 1rem;text-align:left;border-bottom:1px solid var(--border);}
.data-table td{padding:0.85rem 1rem;font-size:0.9rem;border-bottom:1px solid rgba(255,255,255,0.03);vertical-align:middle;}
.data-table tr:last-child td{border-bottom:none;}
.data-table tr:hover td{background:rgba(255,255,255,0.02);}

/* Modal */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.75);z-index:500;align-items:center;justify-content:center;padding:1rem;overflow-y:auto;}
.modal-overlay.open{display:flex;}
.modal{background:var(--card);border:1px solid var(--border);border-radius:24px;padding:2rem;width:100%;max-width:520px;margin:auto;animation:fadeIn 0.3s ease;}
@keyframes fadeIn{from{opacity:0;transform:translateY(-15px)}to{opacity:1;transform:translateY(0)}}
.modal-title{font-size:1.3rem;font-weight:900;margin-bottom:1.5rem;}
.form-group{margin-bottom:1rem;}
.form-group label{display:block;font-size:0.8rem;font-weight:800;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:0.4rem;}
.form-control{width:100%;padding:0.8rem 1rem;background:var(--card2);border:1px solid var(--border);border-radius:12px;color:var(--text);font-family:'Nunito',sans-serif;font-size:0.95rem;transition:all 0.3s;}
.form-control:focus{outline:none;border-color:var(--blue);}
select.form-control option{background:var(--card2);}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;}
.icon-picker{display:flex;flex-wrap:wrap;gap:0.4rem;margin-top:0.4rem;}
.icon-opt{font-size:1.4rem;cursor:pointer;padding:0.3rem;border-radius:8px;border:2px solid transparent;transition:all 0.2s;}
.icon-opt:hover,.icon-opt.selected{border-color:var(--blue);background:rgba(28,176,246,0.1);}
.modal-footer{display:flex;gap:0.75rem;justify-content:flex-end;margin-top:1.5rem;border-top:1px solid var(--border);padding-top:1.25rem;}
.btn-cancel{padding:0.7rem 1.25rem;background:var(--card2);border:1px solid var(--border);border-radius:12px;color:var(--text-muted);font-family:'Nunito',sans-serif;font-weight:800;font-size:0.9rem;cursor:pointer;transition:all 0.3s;}
.btn-cancel:hover{border-color:var(--red);color:var(--red);}
.color-swatch{display:flex;gap:0.4rem;flex-wrap:wrap;margin-top:0.4rem;}
.swatch{width:28px;height:28px;border-radius:50%;cursor:pointer;border:3px solid transparent;transition:all 0.2s;}
.swatch.selected,.swatch:hover{border-color:white;transform:scale(1.1);}
</style>
</head>
<body>

<aside class="sidebar">
    <div class="sidebar-logo"><span style="font-size:2rem">??</span><div><div class="logo-text"><span>NihonGo!</span></div><div style="font-size:0.7rem;color:var(--text-muted);font-weight:700">Admin Panel</div></div></div>
    <div class="sidebar-section">Utama</div>
    <a href="index.php" class="nav-item"><span class="nav-icon">??</span> Dashboard</a>
    <a href="users.php" class="nav-item"><span class="nav-icon">??</span> Manajemen User</a>
    <a href="progress.php" class="nav-item"><span class="nav-icon">??</span> Progress User</a>
    <div class="sidebar-section">Konten</div>
    <a href="categories.php" class="nav-item active"><span class="nav-icon">???</span> Unit / Kategori</a>
    <a href="lessons.php" class="nav-item"><span class="nav-icon">??</span> Pelajaran</a>
    <a href="questions.php" class="nav-item"><span class="nav-icon">?</span> Soal & Quiz</a>
    <a href="vocabulary.php" class="nav-item"><span class="nav-icon">??</span> Kosakata</a>
    <div class="sidebar-section">Laporan</div>
    <a href="reports.php" class="nav-item"><span class="nav-icon">??</span> Statistik</a>
    <div class="sidebar-section">Sistem</div>
    <a href="../../user/dashboard.php" class="nav-item"><span class="nav-icon">??</span> Lihat Situs</a>
    <div class="sidebar-footer">
        <div style="display:flex;align-items:center;gap:0.75rem;"><div style="font-size:1.8rem"><?= $adminUser['avatar'] ?></div><div><div class="admin-name"><?= htmlspecialchars($adminUser['username']) ?></div><div class="admin-role">?? Admin</div></div></div>
        <a href="../index.php?logout=1" class="btn-logout-sm" onclick="return confirm('Keluar?')">?? Logout</a>
    </div>
</aside>

<div class="main">
    <div class="topbar">
        <div class="page-title">??? Kelola Unit & Pelajaran</div>
        <button onclick="openModal(currentPage==='categories'?'addCatModal':'addLesModal')" class="btn-primary">? Tambah <?= $page==='categories'?'Unit':'Pelajaran' ?></button>
    </div>
    <div class="content">
        <?php if ($msg): ?>
        <div class="alert <?= $msgType ?>"><?= $msgType==='success'?'?':'?' ?> <?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>

        <div class="page-tabs">
            <a href="?page=categories" class="tab-btn <?= $page==='categories'?'active':'' ?>">??? Unit (<?= count($categories) ?>)</a>
            <a href="?page=lessons" class="tab-btn <?= $page!=='categories'?'active':'' ?>">?? Pelajaran (<?= count($lessons) ?>)</a>
        </div>

        
        <?php if ($page === 'categories'): ?>
        <div class="cat-grid">
            <?php foreach ($categories as $cat): ?>
            <div class="cat-admin-card" style="--cat-color:<?= $cat['color'] ?>">
                <div class="cat-color-bar"></div>
                <div class="cat-header-row">
                    <div class="cat-icon-big"><?= $cat['icon'] ?></div>
                    <div class="cat-actions">
                        <button class="btn-sm btn-edit" onclick='openEditCat(<?= json_encode($cat) ?>)'>??</button>
                        <button class="btn-sm btn-del" onclick="if(confirm('Hapus unit \"<?= htmlspecialchars($cat['name']) ?>\" beserta SEMUA pelajaran dan soalnya?'))document.getElementById('delcat<?= $cat['id'] ?>').submit()">???</button>
                        <form id="delcat<?= $cat['id'] ?>" method="POST" style="display:none"><input type="hidden" name="action" value="delete"><input type="hidden" name="entity" value="category"><input type="hidden" name="id" value="<?= $cat['id'] ?>"><input type="hidden" name="page" value="categories"></form>
                    </div>
                </div>
                <div class="cat-name-big"><?= htmlspecialchars($cat['name']) ?></div>
                <div class="cat-name-jp"><?= htmlspecialchars($cat['name_jp']) ?></div>
                <div class="cat-stats">
                    <div class="cat-stat-item"><strong><?= $cat['lesson_count'] ?></strong> pelajaran</div>
                    <div class="cat-stat-item"><strong><?= $cat['q_count'] ?></strong> soal</div>
                    <div class="cat-stat-item">Min Lv.<strong><?= $cat['required_level'] ?></strong></div>
                    <div class="cat-stat-item">Urutan <strong>#<?= $cat['order_num'] ?></strong></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        
        <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>#</th><th>Unit</th><th>Judul Pelajaran</th><th>Judul JP</th><th>Soal</th><th>XP</th><th>Urutan</th><th>Aksi</th></tr></thead>
                <tbody>
                <?php foreach ($lessons as $l): ?>
                <tr>
                    <td style="color:var(--text-muted)"><?= $l['id'] ?></td>
                    <td><span style="background:<?= $l['cat_color'] ?>;width:10px;height:10px;border-radius:50%;display:inline-block;margin-right:0.4rem;vertical-align:middle"></span><?= htmlspecialchars($l['cat_name']) ?></td>
                    <td style="font-weight:700"><?= htmlspecialchars($l['title']) ?></td>
                    <td style="font-family:'Noto Serif JP',serif;color:var(--text-muted)"><?= htmlspecialchars($l['title_jp'] ?? '-') ?></td>
                    <td style="color:var(--blue);font-weight:700"><?= $l['q_count'] ?></td>
                    <td style="color:var(--orange);font-weight:700">+<?= $l['xp_reward'] ?> XP</td>
                    <td style="color:var(--text-muted)">#<?= $l['order_num'] ?></td>
                    <td>
                        <button class="btn-sm btn-edit" onclick='openEditLes(<?= json_encode($l) ?>)'>??</button>
                        <a href="questions.php?lesson=<?= $l['id'] ?>" class="btn-sm" style="background:rgba(206,130,255,0.15);color:var(--purple)">? Soal</a>
                        <button class="btn-sm btn-del" onclick="if(confirm('Hapus pelajaran ini beserta semua soalnya?'))document.getElementById('delles<?= $l['id'] ?>').submit()">???</button>
                        <form id="delles<?= $l['id'] ?>" method="POST" style="display:none"><input type="hidden" name="action" value="delete"><input type="hidden" name="entity" value="lesson"><input type="hidden" name="id" value="<?= $l['id'] ?>"><input type="hidden" name="page" value="lessons"></form>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>


<div class="modal-overlay" id="addCatModal">
    <div class="modal">
        <div class="modal-title">? Tambah Unit Baru</div>
        <form method="POST"><input type="hidden" name="action" value="add"><input type="hidden" name="entity" value="category"><input type="hidden" name="page" value="categories">
            <div class="form-row">
                <div class="form-group"><label>Nama Unit (Indonesia) *</label><input type="text" name="name" class="form-control" required placeholder="Hiragana Dasar"></div>
                <div class="form-group"><label>Nama Jepang</label><input type="text" name="name_jp" class="form-control" placeholder="????"></div>
            </div>
            <div class="form-group"><label>Deskripsi</label><input type="text" name="description" class="form-control" placeholder="Pelajari..."></div>
            <div class="form-group"><label>Ikon</label>
                <div class="icon-picker" id="addCatIconPicker"><?php foreach ($iconOptions as $ic): ?><span class="icon-opt <?= $ic==='??'?'selected':'' ?>" onclick="selectIcon(this,'addCatIcon')"><?= $ic ?></span><?php endforeach; ?></div>
                <input type="hidden" name="icon" id="addCatIcon" value="??">
            </div>
            <div class="form-group"><label>Warna</label>
                <div class="color-swatch"><?php foreach (['#58CC02','#1CB0F6','#FF4B4B','#FF9600','#FFD900','#CE82FF','#FF86D0','#00CD9C','#4C4A8F','#333399'] as $c): ?><div class="swatch <?= $c==='#58CC02'?'selected':'' ?>" style="background:<?= $c ?>" onclick="selectColor(this,'addCatColor','<?= $c ?>')"></div><?php endforeach; ?></div>
                <input type="hidden" name="color" id="addCatColor" value="#58CC02">
            </div>
            <div class="form-row">
                <div class="form-group"><label>Urutan</label><input type="number" name="order_num" class="form-control" value="0" min="0"></div>
                <div class="form-group"><label>Level Minimum</label><input type="number" name="required_level" class="form-control" value="1" min="1"></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn-cancel" onclick="closeModal('addCatModal')">Batal</button><button type="submit" class="btn-primary">?? Simpan</button></div>
        </form>
    </div>
</div>


<div class="modal-overlay" id="editCatModal">
    <div class="modal">
        <div class="modal-title">?? Edit Unit</div>
        <form method="POST"><input type="hidden" name="action" value="edit"><input type="hidden" name="entity" value="category"><input type="hidden" name="page" value="categories"><input type="hidden" name="id" id="ecId">
            <div class="form-row">
                <div class="form-group"><label>Nama Indonesia *</label><input type="text" name="name" id="ecName" class="form-control" required></div>
                <div class="form-group"><label>Nama Jepang</label><input type="text" name="name_jp" id="ecNameJp" class="form-control"></div>
            </div>
            <div class="form-group"><label>Deskripsi</label><input type="text" name="description" id="ecDesc" class="form-control"></div>
            <div class="form-group"><label>Ikon</label>
                <div class="icon-picker" id="editCatIconPicker"><?php foreach ($iconOptions as $ic): ?><span class="icon-opt" onclick="selectIcon(this,'editCatIcon')"><?= $ic ?></span><?php endforeach; ?></div>
                <input type="hidden" name="icon" id="editCatIcon">
            </div>
            <div class="form-group"><label>Warna</label>
                <div class="color-swatch"><?php foreach (['#58CC02','#1CB0F6','#FF4B4B','#FF9600','#FFD900','#CE82FF','#FF86D0','#00CD9C','#4C4A8F','#333399'] as $c): ?><div class="swatch" style="background:<?= $c ?>" onclick="selectColor(this,'editCatColor','<?= $c ?>')"></div><?php endforeach; ?></div>
                <input type="hidden" name="color" id="editCatColor">
            </div>
            <div class="form-row">
                <div class="form-group"><label>Urutan</label><input type="number" name="order_num" id="ecOrder" class="form-control" min="0"></div>
                <div class="form-group"><label>Level Minimum</label><input type="number" name="required_level" id="ecReqLevel" class="form-control" min="1"></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn-cancel" onclick="closeModal('editCatModal')">Batal</button><button type="submit" class="btn-primary">?? Simpan</button></div>
        </form>
    </div>
</div>


<div class="modal-overlay" id="addLesModal">
    <div class="modal">
        <div class="modal-title">? Tambah Pelajaran Baru</div>
        <form method="POST"><input type="hidden" name="action" value="add"><input type="hidden" name="entity" value="lesson"><input type="hidden" name="page" value="lessons">
            <div class="form-group"><label>Unit / Kategori *</label>
                <select name="category_id" class="form-control" required>
                    <?php foreach ($categories as $c): ?><option value="<?= $c['id'] ?>"><?= $c['icon'] ?> <?= htmlspecialchars($c['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Judul Indonesia *</label><input type="text" name="title" class="form-control" required placeholder="Vokal Dasar (?????)"></div>
                <div class="form-group"><label>Judul Jepang</label><input type="text" name="title_jp" class="form-control" placeholder="?????"></div>
            </div>
            <div class="form-group"><label>Deskripsi</label><input type="text" name="description" class="form-control" placeholder="Pelajaran tentang..."></div>
            <div class="form-row">
                <div class="form-group"><label>XP Reward</label><input type="number" name="xp_reward" class="form-control" value="10" min="1"></div>
                <div class="form-group"><label>Urutan</label><input type="number" name="order_num" class="form-control" value="0" min="0"></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn-cancel" onclick="closeModal('addLesModal')">Batal</button><button type="submit" class="btn-primary">?? Simpan</button></div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="editLesModal">
    <div class="modal">
        <div class="modal-title">?? Edit Pelajaran</div>
        <form method="POST"><input type="hidden" name="action" value="edit"><input type="hidden" name="entity" value="lesson"><input type="hidden" name="page" value="lessons"><input type="hidden" name="id" id="elId">
            <div class="form-group"><label>Unit *</label>
                <select name="category_id" id="elCat" class="form-control" required>
                    <?php foreach ($categories as $c): ?><option value="<?= $c['id'] ?>"><?= $c['icon'] ?> <?= htmlspecialchars($c['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Judul Indonesia *</label><input type="text" name="title" id="elTitle" class="form-control" required></div>
                <div class="form-group"><label>Judul Jepang</label><input type="text" name="title_jp" id="elTitleJp" class="form-control"></div>
            </div>
            <div class="form-group"><label>Deskripsi</label><input type="text" name="description" id="elDesc" class="form-control"></div>
            <div class="form-row">
                <div class="form-group"><label>XP Reward</label><input type="number" name="xp_reward" id="elXp" class="form-control" min="1"></div>
                <div class="form-group"><label>Urutan</label><input type="number" name="order_num" id="elOrder" class="form-control" min="0"></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn-cancel" onclick="closeModal('editLesModal')">Batal</button><button type="submit" class="btn-primary">?? Simpan</button></div>
        </form>
    </div>
</div>

<script>
const currentPage = '<?= $page ?>';
function openModal(id){document.getElementById(id).classList.add('open');}
function closeModal(id){document.getElementById(id).classList.remove('open');}
document.querySelectorAll('.modal-overlay').forEach(o=>o.addEventListener('click',e=>{if(e.target===o)o.classList.remove('open');}));
function selectIcon(el,inputId){el.closest('.icon-picker').querySelectorAll('.icon-opt').forEach(e=>e.classList.remove('selected'));el.classList.add('selected');document.getElementById(inputId).value=el.textContent;}
function selectColor(el,inputId,color){el.closest('.color-swatch').querySelectorAll('.swatch').forEach(e=>e.classList.remove('selected'));el.classList.add('selected');document.getElementById(inputId).value=color;}

function openEditCat(cat){
    document.getElementById('ecId').value=cat.id;
    document.getElementById('ecName').value=cat.name;
    document.getElementById('ecNameJp').value=cat.name_jp||'';
    document.getElementById('ecDesc').value=cat.description||'';
    document.getElementById('ecOrder').value=cat.order_num;
    document.getElementById('ecReqLevel').value=cat.required_level;
    document.getElementById('editCatIcon').value=cat.icon;
    document.getElementById('editCatColor').value=cat.color;
    document.querySelectorAll('#editCatIconPicker .icon-opt').forEach(el=>el.classList.toggle('selected',el.textContent===cat.icon));
    document.querySelectorAll('#editCatModal .swatch').forEach(el=>el.classList.toggle('selected',el.style.background===cat.color||el.getAttribute('onclick').includes(cat.color)));
    openModal('editCatModal');
}

function openEditLes(l){
    document.getElementById('elId').value=l.id;
    document.getElementById('elCat').value=l.category_id;
    document.getElementById('elTitle').value=l.title;
    document.getElementById('elTitleJp').value=l.title_jp||'';
    document.getElementById('elDesc').value=l.description||'';
    document.getElementById('elXp').value=l.xp_reward;
    document.getElementById('elOrder').value=l.order_num;
    openModal('editLesModal');
}
</script>
</body>
</html>
