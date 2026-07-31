<?php
require_once '../../config/database.php';
requireAdmin();
$db = getDB();

$msg = ''; $msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $lessonId  = intval($_POST['lesson_id']);
        $type      = $_POST['type'];
        $qText     = trim($_POST['question_text']);
        $correct   = trim($_POST['correct_answer']);
        $hint      = trim($_POST['hint'] ?? '');
        $order     = intval($_POST['order_num'] ?? 0);
        $optRaw    = trim($_POST['options_raw'] ?? '');
        $options   = null;

        if ($optRaw) {
            $opts = array_filter(array_map('trim', explode("\n", $optRaw)));
            if (count($opts) >= 2) $options = json_encode(array_values($opts));
        }

        if (!$lessonId || !$qText || !$correct) {
            $msg = 'Pelajaran, teks soal, dan jawaban benar harus diisi!'; $msgType = 'error';
        } else {
            if ($action === 'add') {
                $db->prepare("INSERT INTO questions (lesson_id,type,question_text,correct_answer,options,hint,order_num) VALUES(?,?,?,?,?,?,?)")
                   ->execute([$lessonId,$type,$qText,$correct,$options,$hint?:null,$order]);
                $msg = 'Soal berhasil ditambahkan!'; $msgType = 'success';
            } else {
                $id = intval($_POST['id']);
                $db->prepare("UPDATE questions SET lesson_id=?,type=?,question_text=?,correct_answer=?,options=?,hint=?,order_num=? WHERE id=?")
                   ->execute([$lessonId,$type,$qText,$correct,$options,$hint?:null,$order,$id]);
                $msg = 'Soal berhasil diperbarui!'; $msgType = 'success';
            }
        }
    }

    if ($action === 'delete') {
        $db->prepare("DELETE FROM questions WHERE id=?")->execute([intval($_POST['id'])]);
        $msg = 'Soal berhasil dihapus!'; $msgType = 'success';
    }
}

// Filters
$lessonFilter = intval($_GET['lesson'] ?? 0);
$typeFilter   = $_GET['type'] ?? '';
$search       = trim($_GET['q'] ?? '');

$where = "WHERE 1=1";
$params = [];
if ($lessonFilter) { $where .= " AND q.lesson_id=?"; $params[] = $lessonFilter; }
if ($typeFilter)   { $where .= " AND q.type=?"; $params[] = $typeFilter; }
if ($search) {
    $where .= " AND (q.question_text LIKE ? OR q.correct_answer LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%";
}

$questions = $db->prepare("
    SELECT q.*, l.title as lesson_title, c.name as cat_name, c.icon as cat_icon
    FROM questions q
    JOIN lessons l ON l.id=q.lesson_id = 1
    JOIN categories c ON c.id=l.category_id
    $where ORDER BY c.order_num, l.order_num, q.order_num LIMIT 100
");
$questions->execute($params);
$allQ = $questions->fetchAll();

$lessons = $db->query("SELECT l.*, c.name as cat_name FROM lessons l JOIN categories c ON c.id=l.category_id ORDER BY c.order_num, l.order_num")->fetchAll();
$editQ = null;
if (isset($_GET['edit'])) {
    $s = $db->prepare("SELECT * FROM questions WHERE id=?"); $s->execute([intval($_GET['edit'])]); $editQ = $s->fetch();
}

$typeLabels = [
    'multiple_choice' => '?? Pilihan Ganda',
    'translate_id_jp' => '???? Terjemahkan ke Jepang',
    'translate_jp_id' => '???? Terjemahkan ke Indonesia',
    'fill_blank'      => '?? Isi Titik-Titik',
    'match_pair'      => '?? Cocokkan',
];
$adminUser = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manajemen Soal - Admin NihonGo!</title>
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

.type-badge{display:inline-block;padding:0.2rem 0.6rem;border-radius:8px;font-size:0.72rem;font-weight:800;white-space:nowrap;}
.q-text-cell{font-family:'Noto Serif JP',serif;font-size:0.95rem;}
.btn-sm{padding:0.3rem 0.65rem;border:none;border-radius:8px;font-family:'Nunito',sans-serif;font-size:0.75rem;font-weight:800;cursor:pointer;transition:all 0.2s;text-decoration:none;display:inline-block;white-space:nowrap;margin-right:0.3rem;}
.btn-edit{background:rgba(28,176,246,0.2);color:var(--blue);}
.btn-edit:hover{background:var(--blue);color:white;}
.btn-del{background:rgba(255,75,75,0.15);color:var(--red);}
.btn-del:hover{background:var(--red);color:white;}

/* Modal */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.75);z-index:500;align-items:flex-start;justify-content:center;padding:2rem 1rem;overflow-y:auto;}
.modal-overlay.open{display:flex;}
.modal{background:var(--card);border:1px solid var(--border);border-radius:24px;padding:2rem;width:100%;max-width:580px;margin:auto;animation:slideIn 0.3s ease;}
@keyframes slideIn{from{opacity:0;transform:translateY(-20px)}to{opacity:1;transform:translateY(0)}}
.modal-title{font-size:1.3rem;font-weight:900;margin-bottom:1.5rem;}
.form-group{margin-bottom:1rem;}
.form-group label{display:block;font-size:0.8rem;font-weight:800;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:0.4rem;}
.form-control{width:100%;padding:0.8rem 1rem;background:var(--card2);border:1px solid var(--border);border-radius:12px;color:var(--text);font-family:'Nunito',sans-serif;font-size:0.95rem;transition:all 0.3s;}
.form-control:focus{outline:none;border-color:var(--blue);}
select.form-control option{background:var(--card2);}
textarea.form-control{resize:vertical;min-height:80px;font-family:'Noto Serif JP',serif;}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;}
.modal-footer{display:flex;gap:0.75rem;justify-content:flex-end;margin-top:1.5rem;border-top:1px solid var(--border);padding-top:1.25rem;}
.btn-cancel{padding:0.7rem 1.25rem;background:var(--card2);border:1px solid var(--border);border-radius:12px;color:var(--text-muted);font-family:'Nunito',sans-serif;font-weight:800;font-size:0.9rem;cursor:pointer;transition:all 0.3s;}
.btn-cancel:hover{border-color:var(--red);color:var(--red);}
.hint-text{font-size:0.78rem;color:var(--text-muted);margin-top:0.3rem;}
.options-hint{background:rgba(28,176,246,0.1);border:1px solid rgba(28,176,246,0.2);border-radius:10px;padding:0.6rem 0.8rem;font-size:0.8rem;color:var(--blue);margin-top:0.3rem;}
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
    <a href="questions.php" class="nav-item active"><span class="nav-icon">?</span> Soal & Quiz</a>
    <a href="vocabulary.php" class="nav-item"><span class="nav-icon">??</span> Kosakata</a>
    <div class="sidebar-section">Laporan</div>
    <a href="reports.php" class="nav-item"><span class="nav-icon">??</span> Statistik</a>
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
    <div class="topbar">
        <div class="page-title">? Manajemen Soal & Quiz</div>
        <button onclick="openModal('addModal')" class="btn-primary">? Tambah Soal</button>
    </div>

    <div class="content">
        <?php if ($msg): ?>
        <div class="alert <?= $msgType ?>"><?= $msgType==='success'?'?':'?' ?> <?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>

        <form method="GET" class="toolbar">
            <input type="text" name="q" class="search-input" placeholder="?? Cari teks soal atau jawaban..." value="<?= htmlspecialchars($search) ?>">
            <select name="lesson" class="filter-select" onchange="this.form.submit()">
                <option value="">Semua Pelajaran</option>
                <?php foreach ($lessons as $l): ?>
                <option value="<?= $l['id'] ?>" <?= $lessonFilter==$l['id']?'selected':'' ?>><?= htmlspecialchars($l['cat_name'].' > '.$l['title']) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="type" class="filter-select" onchange="this.form.submit()">
                <option value="">Semua Tipe</option>
                <?php foreach ($typeLabels as $k=>$v): ?>
                <option value="<?= $k ?>" <?= $typeFilter===$k?'selected':'' ?>><?= $v ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn-primary" style="background:var(--blue)">??</button>
            <a href="questions.php" class="btn-primary" style="background:var(--card2);border:1px solid var(--border)">?</a>
        </form>

        <div style="color:var(--text-muted);font-size:0.85rem;margin-bottom:0.75rem">Menampilkan <?= count($allQ) ?> soal</div>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr><th>#</th><th>Pelajaran</th><th>Tipe</th><th>Soal</th><th>Jawaban Benar</th><th>Opsi</th><th>Urutan</th><th>Aksi</th></tr>
                </thead>
                <tbody>
                <?php foreach ($allQ as $q):
                    $opts = $q['options'] ? implode(', ', json_decode($q['options'],true)) : '-';
                    $typeColor = ['multiple_choice'=>'rgba(28,176,246,0.15)','translate_id_jp'=>'rgba(255,75,75,0.15)','translate_jp_id'=>'rgba(88,204,2,0.15)','fill_blank'=>'rgba(255,150,0,0.15)','match_pair'=>'rgba(206,130,255,0.15)'];
                    $typeTextColor = ['multiple_choice'=>'var(--blue)','translate_id_jp'=>'var(--red)','translate_jp_id'=>'var(--green)','fill_blank'=>'var(--orange)','match_pair'=>'var(--purple)'];
                ?>
                <tr>
                    <td style="color:var(--text-muted)"><?= $q['id'] ?></td>
                    <td style="font-size:0.82rem"><span style="color:var(--text-muted)"><?= $q['cat_icon'] ?> <?= htmlspecialchars($q['cat_name']) ?> �</span><br><strong><?= htmlspecialchars($q['lesson_title']) ?></strong></td>
                    <td>
                        <span class="type-badge" style="background:<?= $typeColor[$q['type']]??'rgba(255,255,255,0.1)' ?>;color:<?= $typeTextColor[$q['type']]??'white' ?>">
                            <?= $typeLabels[$q['type']] ?? $q['type'] ?>
                        </span>
                    </td>
                    <td class="q-text-cell" style="max-width:220px"><?= htmlspecialchars(mb_substr($q['question_text'],0,80)) ?><?= mb_strlen($q['question_text'])>80?'�':'' ?></td>
                    <td style="font-family:'Noto Serif JP',serif;font-weight:700;color:var(--green)"><?= htmlspecialchars(mb_substr($q['correct_answer'],0,40)) ?></td>
                    <td style="font-size:0.78rem;color:var(--text-muted);max-width:150px"><?= htmlspecialchars(mb_substr($opts,0,60)) ?><?= strlen($opts)>60?'�':'' ?></td>
                    <td style="text-align:center;color:var(--text-muted)"><?= $q['order_num'] ?></td>
                    <td>
                        <button class="btn-sm btn-edit" onclick='openEditQ(<?= json_encode($q) ?>)'>??</button>
                        <button class="btn-sm btn-del" onclick="if(confirm('Hapus soal ini?'))document.getElementById('del<?= $q['id'] ?>').submit()">???</button>
                        <form id="del<?= $q['id'] ?>" method="POST" style="display:none"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $q['id'] ?>"></form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($allQ)): ?><tr><td colspan="8" style="text-align:center;padding:3rem;color:var(--text-muted)">Tidak ada soal ditemukan</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal-overlay" id="addModal">
    <div class="modal">
        <div class="modal-title">? Tambah Soal Baru</div>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="form-row">
                <div class="form-group">
                    <label>Pelajaran *</label>
                    <select name="lesson_id" class="form-control" required>
                        <option value="">-- Pilih --</option>
                        <?php foreach ($lessons as $l): ?>
                        <option value="<?= $l['id'] ?>" <?= $lessonFilter==$l['id']?'selected':'' ?>><?= htmlspecialchars($l['cat_name'].' > '.$l['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Tipe Soal *</label>
                    <select name="type" class="form-control" required>
                        <?php foreach ($typeLabels as $k=>$v): ?>
                        <option value="<?= $k ?>"><?= $v ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Teks Soal *</label>
                <textarea name="question_text" class="form-control" required placeholder="Tulis pertanyaan di sini... (bisa huruf Jepang)"></textarea>
            </div>
            <div class="form-group">
                <label>Jawaban Benar *</label>
                <input type="text" name="correct_answer" class="form-control" required placeholder="Jawaban yang benar">
            </div>
            <div class="form-group">
                <label>Opsi Jawaban (untuk pilihan ganda)</label>
                <textarea name="options_raw" class="form-control" style="min-height:70px" placeholder="Satu opsi per baris, minimal 2 baris&#10;Contoh:&#10;Selamat pagi&#10;Selamat siang&#10;Selamat malam&#10;Sampai jumpa"></textarea>
                <div class="options-hint">?? Tulis satu opsi per baris. Biarkan kosong jika soal berupa input teks bebas.</div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Petunjuk (Hint)</label>
                    <input type="text" name="hint" class="form-control" placeholder="Petunjuk opsional...">
                </div>
                <div class="form-group">
                    <label>Urutan</label>
                    <input type="number" name="order_num" class="form-control" value="0" min="0">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeModal('addModal')">Batal</button>
                <button type="submit" class="btn-primary">?? Simpan Soal</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="editModal">
    <div class="modal">
        <div class="modal-title">?? Edit Soal</div>
        <form method="POST" id="editQForm">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="eq_id">
            <div class="form-row">
                <div class="form-group">
                    <label>Pelajaran *</label>
                    <select name="lesson_id" id="eq_lesson" class="form-control" required>
                        <?php foreach ($lessons as $l): ?>
                        <option value="<?= $l['id'] ?>"><?= htmlspecialchars($l['cat_name'].' > '.$l['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Tipe Soal *</label>
                    <select name="type" id="eq_type" class="form-control" required>
                        <?php foreach ($typeLabels as $k=>$v): ?>
                        <option value="<?= $k ?>"><?= $v ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Teks Soal *</label>
                <textarea name="question_text" id="eq_qtext" class="form-control" required></textarea>
            </div>
            <div class="form-group">
                <label>Jawaban Benar *</label>
                <input type="text" name="correct_answer" id="eq_correct" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Opsi (satu per baris)</label>
                <textarea name="options_raw" id="eq_options" class="form-control" style="min-height:70px"></textarea>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Petunjuk</label>
                    <input type="text" name="hint" id="eq_hint" class="form-control">
                </div>
                <div class="form-group">
                    <label>Urutan</label>
                    <input type="number" name="order_num" id="eq_order" class="form-control" min="0">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeModal('editModal')">Batal</button>
                <button type="submit" class="btn-primary">?? Simpan</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(id){document.getElementById(id).classList.add('open');}
function closeModal(id){document.getElementById(id).classList.remove('open');}
document.querySelectorAll('.modal-overlay').forEach(o=>{o.addEventListener('click',e=>{if(e.target===o)o.classList.remove('open');});});

function openEditQ(q) {
    document.getElementById('eq_id').value = q.id;
    document.getElementById('eq_lesson').value = q.lesson_id;
    document.getElementById('eq_type').value = q.type;
    document.getElementById('eq_qtext').value = q.question_text;
    document.getElementById('eq_correct').value = q.correct_answer;
    document.getElementById('eq_hint').value = q.hint || '';
    document.getElementById('eq_order').value = q.order_num;
    // Options
    let opts = '';
    if (q.options) {
        try { opts = JSON.parse(q.options).join('\n'); } catch(e){}
    }
    document.getElementById('eq_options').value = opts;
    openModal('editModal');
}
</script>
</body>
</html>
