<?php
require_once '../../config/database.php';
requireAdmin();
$db = getDB();

$msg = ''; $msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add' || $action === 'edit') {
        $catId   = intval($_POST['category_id']) ?: null;
        $jp      = trim($_POST['japanese']);
        $hira    = trim($_POST['hiragana'] ?? '');
        $romaji  = trim($_POST['romaji'] ?? '');
        $id_     = trim($_POST['indonesian']);
        $exSen   = trim($_POST['example_sentence'] ?? '');
        $exTrans = trim($_POST['example_translation'] ?? '');
        $jlpt    = $_POST['jlpt_level'] ?? 'N5';

        if (!$jp || !$id_) { $msg = 'Kata Jepang dan arti Indonesia harus diisi!'; $msgType = 'error'; }
        else {
            if ($action === 'add') {
                $db->prepare("INSERT INTO vocabulary (category_id,japanese,hiragana,romaji,indonesian,example_sentence,example_translation,jlpt_level) VALUES(?,?,?,?,?,?,?,?)")
                   ->execute([$catId,$jp,$hira,$romaji,$id_,$exSen?:null,$exTrans?:null,$jlpt]);
                $msg = "Kosakata \"$jp\" berhasil ditambahkan!"; $msgType = 'success';
            } else {
                $db->prepare("UPDATE vocabulary SET category_id=?,japanese=?,hiragana=?,romaji=?,indonesian=?,example_sentence=?,example_translation=?,jlpt_level=? WHERE id=?")
                   ->execute([$catId,$jp,$hira,$romaji,$id_,$exSen?:null,$exTrans?:null,$jlpt,intval($_POST['id'])]);
                $msg = "Kosakata berhasil diperbarui!"; $msgType = 'success';
            }
        }
    }
    if ($action === 'delete') {
        $db->prepare("DELETE FROM vocabulary WHERE id=?")->execute([intval($_POST['id'])]);
        $msg = 'Kosakata berhasil dihapus!'; $msgType = 'success';
    }
}

$search = trim($_GET['q'] ?? '');
$catFilter = intval($_GET['cat'] ?? 0);
$jlptFilter = $_GET['jlpt'] ?? '';

$where = "WHERE 1=1";
$params = [];
if ($search) { $where .= " AND (v.japanese LIKE ? OR v.romaji LIKE ? OR v.indonesian LIKE ?)"; $s = "%$search%"; $params = [$s,$s,$s]; }
if ($catFilter) { $where .= " AND v.category_id=?"; $params[] = $catFilter; }
if ($jlptFilter) { $where .= " AND v.jlpt_level=?"; $params[] = $jlptFilter; }

$stmt = $db->prepare("SELECT v.*, c.name as cat_name FROM vocabulary v LEFT JOIN categories c ON c.id=v.category_id $where ORDER BY v.jlpt_level, v.id LIMIT 200");
$stmt->execute($params);
$vocabs = $stmt->fetchAll();

$categories = $db->query("SELECT * FROM categories ORDER BY order_num")->fetchAll();
$totalVocab = $db->query("SELECT COUNT(*) FROM vocabulary")->fetchColumn();
$adminUser = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kosakata - Admin NihonGo!</title>
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
.btn-logout-sm{display:block;text-align:center;margin-top:0.75rem;padding:0.5rem;border-radius:10px;border:1px solid var(--border);color:var(--text-muted);text-decoration:none;font-size:0.85rem;font-weight:700;transition:all 0.3s;}
.btn-logout-sm:hover{border-color:var(--red);color:var(--red);}
.main{margin-left:var(--sidebar-w);flex:1;display:flex;flex-direction:column;}
.topbar{height:65px;border-bottom:1px solid var(--border);background:rgba(7,7,20,0.9);backdrop-filter:blur(20px);display:flex;align-items:center;justify-content:space-between;padding:0 2rem;position:sticky;top:0;z-index:100;}
.page-title{font-size:1.4rem;font-weight:900;}
.content{padding:2rem;flex:1;}
.alert{padding:1rem 1.25rem;border-radius:14px;margin-bottom:1.5rem;font-weight:700;display:flex;align-items:center;gap:0.75rem;}
.alert.success{background:rgba(88,204,2,0.15);border:1px solid rgba(88,204,2,0.3);color:var(--green);}
.alert.error{background:rgba(255,75,75,0.15);border:1px solid rgba(255,75,75,0.3);color:var(--red);}
.toolbar{display:flex;gap:0.75rem;margin-bottom:1.25rem;flex-wrap:wrap;}
.search-input{flex:1;min-width:200px;padding:0.7rem 1rem;background:var(--card);border:1px solid var(--border);border-radius:12px;color:var(--text);font-family:'Nunito',sans-serif;font-size:0.9rem;}
.search-input:focus{outline:none;border-color:var(--blue);}
.filter-select{padding:0.7rem 1rem;background:var(--card);border:1px solid var(--border);border-radius:12px;color:var(--text);font-family:'Nunito',sans-serif;font-size:0.9rem;}
.btn-primary{padding:0.7rem 1.25rem;background:var(--green);color:white;border:none;border-radius:12px;font-family:'Nunito',sans-serif;font-weight:800;font-size:0.9rem;cursor:pointer;transition:all 0.3s;text-decoration:none;display:inline-flex;align-items:center;gap:0.4rem;}
.btn-primary:hover{background:var(--green-dark);}
.table-wrap{background:var(--card);border:1px solid var(--border);border-radius:20px;overflow:hidden;}
.data-table{width:100%;border-collapse:collapse;}
.data-table th{font-size:0.72rem;text-transform:uppercase;letter-spacing:1px;color:var(--text-muted);font-weight:800;padding:0.9rem 1rem;text-align:left;border-bottom:1px solid var(--border);}
.data-table td{padding:0.85rem 1rem;font-size:0.9rem;border-bottom:1px solid rgba(255,255,255,0.03);vertical-align:middle;}
.data-table tr:last-child td{border-bottom:none;}
.data-table tr:hover td{background:rgba(255,255,255,0.02);}
.jlpt-badge{display:inline-block;padding:0.2rem 0.5rem;border-radius:8px;font-size:0.72rem;font-weight:800;background:rgba(255,217,0,0.15);color:var(--yellow);}
.btn-sm{padding:0.3rem 0.65rem;border:none;border-radius:8px;font-family:'Nunito',sans-serif;font-size:0.75rem;font-weight:800;cursor:pointer;transition:all 0.2s;text-decoration:none;display:inline-block;margin-right:0.3rem;}
.btn-edit{background:rgba(28,176,246,0.2);color:var(--blue);}
.btn-edit:hover{background:var(--blue);color:white;}
.btn-del{background:rgba(255,75,75,0.15);color:var(--red);}
.btn-del:hover{background:var(--red);color:white;}
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.75);z-index:500;align-items:center;justify-content:center;padding:1rem;overflow-y:auto;}
.modal-overlay.open{display:flex;}
.modal{background:var(--card);border:1px solid var(--border);border-radius:24px;padding:2rem;width:100%;max-width:560px;margin:auto;animation:fadeIn 0.3s ease;}
@keyframes fadeIn{from{opacity:0;transform:translateY(-15px)}to{opacity:1;transform:translateY(0)}}
.modal-title{font-size:1.3rem;font-weight:900;margin-bottom:1.5rem;}
.form-group{margin-bottom:1rem;}
.form-group label{display:block;font-size:0.8rem;font-weight:800;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:0.4rem;}
.form-control{width:100%;padding:0.8rem 1rem;background:var(--card2);border:1px solid var(--border);border-radius:12px;color:var(--text);font-family:'Nunito',sans-serif;font-size:0.95rem;transition:all 0.3s;}
.form-control:focus{outline:none;border-color:var(--blue);}
.form-control.jp-font{font-family:'Noto Serif JP',serif;font-size:1.1rem;}
select.form-control option{background:var(--card2);}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;}
.modal-footer{display:flex;gap:0.75rem;justify-content:flex-end;margin-top:1.5rem;border-top:1px solid var(--border);padding-top:1.25rem;}
.btn-cancel{padding:0.7rem 1.25rem;background:var(--card2);border:1px solid var(--border);border-radius:12px;color:var(--text-muted);font-family:'Nunito',sans-serif;font-weight:800;font-size:0.9rem;cursor:pointer;transition:all 0.3s;}
.btn-cancel:hover{border-color:var(--red);color:var(--red);}
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
    <a href="categories.php" class="nav-item"><span class="nav-icon">???</span> Unit / Kategori</a>
    <a href="lessons.php" class="nav-item"><span class="nav-icon">??</span> Pelajaran</a>
    <a href="questions.php" class="nav-item"><span class="nav-icon">?</span> Soal & Quiz</a>
    <a href="vocabulary.php" class="nav-item active"><span class="nav-icon">??</span> Kosakata</a>
    <div class="sidebar-section">Laporan</div>
    <a href="reports.php" class="nav-item"><span class="nav-icon">??</span> Statistik</a>
    <div class="sidebar-section">Sistem</div>
    <a href="../../user/dashboard.php" class="nav-item"><span class="nav-icon">??</span> Lihat Situs</a>
    <div class="sidebar-footer">
        <div style="display:flex;align-items:center;gap:0.75rem;"><div style="font-size:1.8rem"><?= $adminUser['avatar'] ?></div><div><div style="font-size:0.9rem;font-weight:800"><?= htmlspecialchars($adminUser['username']) ?></div><div style="font-size:0.75rem;color:var(--orange);font-weight:700">?? Admin</div></div></div>
        <a href="../index.php?logout=1" class="btn-logout-sm" onclick="return confirm('Keluar?')">?? Logout</a>
    </div>
</aside>

<div class="main">
    <div class="topbar">
        <div class="page-title">?? Manajemen Kosakata <span style="font-size:0.9rem;color:var(--text-muted);font-weight:700">(Total: <?= $totalVocab ?>)</span></div>
        <button onclick="openModal('addModal')" class="btn-primary">? Tambah Kosakata</button>
    </div>
    <div class="content">
        <?php if ($msg): ?>
        <div class="alert <?= $msgType ?>"><?= $msgType==='success'?'?':'?' ?> <?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>

        <form method="GET" class="toolbar">
            <input type="text" name="q" class="search-input" placeholder="?? Cari dalam Jepang, romaji, atau Indonesia..." value="<?= htmlspecialchars($search) ?>">
            <select name="cat" class="filter-select" onchange="this.form.submit()">
                <option value="">Semua Kategori</option>
                <?php foreach ($categories as $c): ?><option value="<?= $c['id'] ?>" <?= $catFilter==$c['id']?'selected':'' ?>><?= $c['icon'] ?> <?= htmlspecialchars($c['name']) ?></option><?php endforeach; ?>
            </select>
            <select name="jlpt" class="filter-select" onchange="this.form.submit()">
                <option value="">Semua JLPT</option>
                <?php foreach (['N5','N4','N3','N2','N1'] as $lvl): ?><option value="<?= $lvl ?>" <?= $jlptFilter===$lvl?'selected':'' ?>><?= $lvl ?></option><?php endforeach; ?>
            </select>
            <button type="submit" class="btn-primary" style="background:var(--blue)">??</button>
            <a href="vocabulary.php" class="btn-primary" style="background:var(--card2);border:1px solid var(--border)">?</a>
        </form>

        <div style="color:var(--text-muted);font-size:0.85rem;margin-bottom:0.75rem">Menampilkan <?= count($vocabs) ?> kosakata</div>

        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>#</th><th>Jepang</th><th>Hiragana</th><th>Romaji</th><th>Indonesia</th><th>Kategori</th><th>JLPT</th><th>Contoh</th><th>Aksi</th></tr></thead>
                <tbody>
                <?php foreach ($vocabs as $v): ?>
                <tr>
                    <td style="color:var(--text-muted)"><?= $v['id'] ?></td>
                    <td style="font-family:'Noto Serif JP',serif;font-size:1.3rem;font-weight:700;color:var(--yellow)"><?= htmlspecialchars($v['japanese']) ?></td>
                    <td style="font-family:'Noto Serif JP',serif;color:var(--blue)"><?= htmlspecialchars($v['hiragana'] ?? '-') ?></td>
                    <td style="color:var(--text-muted)"><?= htmlspecialchars($v['romaji'] ?? '-') ?></td>
                    <td style="font-weight:700"><?= htmlspecialchars($v['indonesian']) ?></td>
                    <td style="font-size:0.82rem;color:var(--text-muted)"><?= $v['cat_name'] ? htmlspecialchars($v['cat_name']) : '-' ?></td>
                    <td><span class="jlpt-badge"><?= $v['jlpt_level'] ?></span></td>
                    <td style="font-size:0.78rem;color:var(--text-muted);max-width:160px"><?= $v['example_sentence'] ? htmlspecialchars(mb_substr($v['example_sentence'],0,40)) : '-' ?></td>
                    <td>
                        <button class="btn-sm btn-edit" onclick='openEditV(<?= json_encode($v) ?>)'>??</button>
                        <button class="btn-sm btn-del" onclick="if(confirm('Hapus kosakata ini?'))document.getElementById('delv<?= $v['id'] ?>').submit()">???</button>
                        <form id="delv<?= $v['id'] ?>" method="POST" style="display:none"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $v['id'] ?>"></form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($vocabs)): ?><tr><td colspan="9" style="text-align:center;padding:3rem;color:var(--text-muted)">Tidak ada kosakata ditemukan</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Modal -->
<div class="modal-overlay" id="addModal">
    <div class="modal">
        <div class="modal-title">? Tambah Kosakata Baru</div>
        <form method="POST"><input type="hidden" name="action" value="add">
            <div class="form-row">
                <div class="form-group"><label>Tulisan Jepang *</label><input type="text" name="japanese" class="form-control jp-font" required placeholder="????"></div>
                <div class="form-group"><label>Hiragana</label><input type="text" name="hiragana" class="form-control jp-font" placeholder="????"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Romaji</label><input type="text" name="romaji" class="form-control" placeholder="ohayou"></div>
                <div class="form-group"><label>Arti Indonesia *</label><input type="text" name="indonesian" class="form-control" required placeholder="Selamat pagi"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Kategori</label>
                    <select name="category_id" class="form-control"><option value="">-- Tanpa Kategori --</option><?php foreach ($categories as $c): ?><option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option><?php endforeach; ?></select>
                </div>
                <div class="form-group"><label>Level JLPT</label>
                    <select name="jlpt_level" class="form-control"><?php foreach (['N5','N4','N3','N2','N1'] as $lvl): ?><option value="<?= $lvl ?>"><?= $lvl ?></option><?php endforeach; ?></select>
                </div>
            </div>
            <div class="form-group"><label>Contoh Kalimat (Jepang)</label><input type="text" name="example_sentence" class="form-control jp-font" placeholder="?????????!"></div>
            <div class="form-group"><label>Terjemahan Contoh</label><input type="text" name="example_translation" class="form-control" placeholder="Selamat pagi!"></div>
            <div class="modal-footer"><button type="button" class="btn-cancel" onclick="closeModal('addModal')">Batal</button><button type="submit" class="btn-primary">?? Simpan</button></div>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal-overlay" id="editModal">
    <div class="modal">
        <div class="modal-title">?? Edit Kosakata</div>
        <form method="POST"><input type="hidden" name="action" value="edit"><input type="hidden" name="id" id="evId">
            <div class="form-row">
                <div class="form-group"><label>Tulisan Jepang *</label><input type="text" name="japanese" id="evJp" class="form-control jp-font" required></div>
                <div class="form-group"><label>Hiragana</label><input type="text" name="hiragana" id="evHira" class="form-control jp-font"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Romaji</label><input type="text" name="romaji" id="evRomaji" class="form-control"></div>
                <div class="form-group"><label>Arti Indonesia *</label><input type="text" name="indonesian" id="evId2" class="form-control" required></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Kategori</label>
                    <select name="category_id" id="evCat" class="form-control"><option value="">-- Tanpa Kategori --</option><?php foreach ($categories as $c): ?><option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option><?php endforeach; ?></select>
                </div>
                <div class="form-group"><label>Level JLPT</label>
                    <select name="jlpt_level" id="evJlpt" class="form-control"><?php foreach (['N5','N4','N3','N2','N1'] as $lvl): ?><option value="<?= $lvl ?>"><?= $lvl ?></option><?php endforeach; ?></select>
                </div>
            </div>
            <div class="form-group"><label>Contoh Kalimat (Jepang)</label><input type="text" name="example_sentence" id="evEx" class="form-control jp-font"></div>
            <div class="form-group"><label>Terjemahan Contoh</label><input type="text" name="example_translation" id="evExT" class="form-control"></div>
            <div class="modal-footer"><button type="button" class="btn-cancel" onclick="closeModal('editModal')">Batal</button><button type="submit" class="btn-primary">?? Simpan</button></div>
        </form>
    </div>
</div>

<script>
function openModal(id){document.getElementById(id).classList.add('open');}
function closeModal(id){document.getElementById(id).classList.remove('open');}
document.querySelectorAll('.modal-overlay').forEach(o=>o.addEventListener('click',e=>{if(e.target===o)o.classList.remove('open');}));
function openEditV(v){
    document.getElementById('evId').value=v.id;
    document.getElementById('evJp').value=v.japanese;
    document.getElementById('evHira').value=v.hiragana||'';
    document.getElementById('evRomaji').value=v.romaji||'';
    document.getElementById('evId2').value=v.indonesian;
    document.getElementById('evCat').value=v.category_id||'';
    document.getElementById('evJlpt').value=v.jlpt_level||'N5';
    document.getElementById('evEx').value=v.example_sentence||'';
    document.getElementById('evExT').value=v.example_translation||'';
    openModal('editModal');
}
</script>
</body>
</html>
