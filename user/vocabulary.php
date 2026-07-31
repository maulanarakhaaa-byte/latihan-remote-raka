<?php
require_once '../config/database.php';
if (!isLoggedIn()) redirect('../index.php');

$user = getCurrentUser();
$db = getDB();

$search     = trim($_GET['q'] ?? '');
$catFilter  = intval($_GET['cat'] ?? 0);
$jlptFilter = $_GET['jlpt'] ?? '';

$query  = "SELECT v.*, c.name as cat_name, c.color as cat_color FROM vocabulary v LEFT JOIN categories c ON c.id = v.category_id WHERE 1=1";
$params = [];

if ($search) {
    $query .= " AND (v.japanese LIKE ? OR v.romaji LIKE ? OR v.indonesian LIKE ? OR v.hiragana LIKE ?)";
    $s = "%$search%";
    $params = array_merge($params, [$s, $s, $s, $s]);
}
if ($catFilter) {
    $query .= " AND v.category_id = ?";
    $params[] = $catFilter;
}
if ($jlptFilter) {
    $query .= " AND v.jlpt_level = ?";
    $params[] = $jlptFilter;
}
$query .= " ORDER BY v.jlpt_level, v.id";

$stmt = $db->prepare($query);
$stmt->execute($params);
$vocabs = $stmt->fetchAll();

$categories = $db->query("SELECT * FROM categories ORDER BY order_num")->fetchAll();

$pageTitle = 'Kamus Kosakata - NihonGo!';
$pageCss   = 'vocabulary';
require_once '../includes/header.php';
?>
<nav class="navbar">
    <a href="dashboard.php" class="back-btn">← Dashboard</a>
    <div class="nav-title">📖 Kamus Kosakata</div>
</nav>

<div class="main">
    <div class="search-bar">
        <form method="GET">
            <div class="search-input-wrap">
                <span class="search-icon">🔍</span>
                <input type="text" name="q" class="search-input"
                    placeholder="Cari kata dalam Jepang, romaji, atau Indonesia..."
                    value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="filter-row">
                <a href="vocabulary.php" class="filter-btn <?= !$catFilter && !$jlptFilter && !$search ? 'active' : '' ?>">Semua</a>
                <?php foreach ($categories as $c): ?>
                <a href="vocabulary.php?cat=<?= $c['id'] ?><?= $search ? '&q='.urlencode($search) : '' ?>"
                   class="filter-btn <?= $catFilter == $c['id'] ? 'active' : '' ?>">
                    <?= $c['icon'] ?> <?= htmlspecialchars($c['name']) ?>
                </a>
                <?php endforeach; ?>
            </div>
            <div class="filter-row" style="margin-top:0.5rem">
                <?php foreach (['N5','N4','N3','N2','N1'] as $level): ?>
                <a href="vocabulary.php?jlpt=<?= $level ?><?= $search ? '&q='.urlencode($search) : '' ?>"
                   class="filter-btn <?= $jlptFilter == $level ? 'active' : '' ?>">
                    JLPT <?= $level ?>
                </a>
                <?php endforeach; ?>
            </div>
        </form>
    </div>

    <div class="vocab-count">
        Menampilkan <strong><?= count($vocabs) ?></strong> kosakata
        <?= $search ? "untuk pencarian \"<strong>" . htmlspecialchars($search) . "</strong>\"" : '' ?>
        · <em>Klik kartu untuk lihat contoh kalimat</em>
    </div>

    <?php if (empty($vocabs)): ?>
    <div class="empty-state">
        <span class="empty-emoji">🔎</span>
        <div style="font-size:1.2rem;font-weight:700;margin-bottom:0.5rem">Tidak ada kosakata ditemukan</div>
        <div>Coba kata kunci lain atau hapus filter</div>
    </div>
    <?php else: ?>
    <div class="vocab-grid">
        <?php foreach ($vocabs as $v): ?>
        <div class="vocab-card">
            <div class="jlpt-badge"><?= $v['jlpt_level'] ?></div>
            <div class="front">
                <?php if ($v['cat_color']): ?>
                <span class="cat-dot" style="background:<?= $v['cat_color'] ?>"></span>
                <?php endif; ?>
                <div class="vocab-main"><?= htmlspecialchars($v['japanese']) ?></div>
                <?php if ($v['hiragana'] && $v['hiragana'] !== $v['japanese']): ?>
                <div class="vocab-hira"><?= htmlspecialchars($v['hiragana']) ?></div>
                <?php endif; ?>
                <div class="vocab-romaji"><?= htmlspecialchars($v['romaji']) ?></div>
                <div class="vocab-meaning"><?= htmlspecialchars($v['indonesian']) ?></div>
                <div class="flip-hint">Klik untuk lihat contoh kalimat →</div>
            </div>
            <div class="back">
                <div class="vocab-main" style="font-size:1.5rem"><?= htmlspecialchars($v['japanese']) ?></div>
                <div style="font-weight:800;color:var(--blue);font-size:1.1rem"><?= htmlspecialchars($v['indonesian']) ?></div>
                <?php if ($v['example_sentence']): ?>
                <div style="margin-top:0.5rem">
                    <div style="font-size:0.75rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:0.3rem">Contoh Kalimat</div>
                    <div class="vocab-example vocab-example-jp"><?= htmlspecialchars($v['example_sentence']) ?></div>
                    <?php if ($v['example_translation']): ?>
                    <div class="vocab-example"><?= htmlspecialchars($v['example_translation']) ?></div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <div class="flip-hint">← Klik lagi untuk balik</div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<script src="../assets/js/vocabulary.js"></script>
<?php require_once '../includes/footer.php'; ?>