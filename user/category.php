<?php
require_once '../config/database.php';
if (!isLoggedIn()) redirect('../index.php');

$user = getCurrentUser();
$db = getDB();
$catId = intval($_GET['id'] ?? 0);

$cat = $db->prepare("SELECT * FROM categories WHERE id = ?");
$cat->execute([$catId]);
$category = $cat->fetch();
if (!$category) redirect('dashboard.php');

$lessons = $db->prepare("
    SELECT l.*,
        COALESCE(MAX(up.completed), 0) as completed,
        COALESCE(MAX(up.score), 0) as score,
        COALESCE(MAX(up.attempts), 0) as attempts,
        COUNT(DISTINCT q.id) as total_questions
    FROM lessons l
    LEFT JOIN user_progress up ON up.lesson_id = l.id AND up.user_id = ?
    LEFT JOIN questions q ON q.lesson_id = l.id
    WHERE l.category_id = ?
    GROUP BY l.id, l.title, l.description, l.order_num, l.xp_reward, l.category_id
    ORDER BY l.order_num
");
$lessons->execute([$user['id'], $catId]);
$allLessons = $lessons->fetchAll();

$completedCount = count(array_filter($allLessons, fn($l) => $l['completed']));
$totalCount = count($allLessons);
$pct = $totalCount > 0 ? round(($completedCount/$totalCount)*100) : 0;


$vocabs = $db->prepare("SELECT * FROM vocabulary WHERE category_id = ? LIMIT 12");
$vocabs->execute([$catId]);
$allVocab = $vocabs->fetchAll();

$pageTitle   = htmlspecialchars($category['name']) . ' - NihonGo!';
$pageCss     = 'category';
$accentColor = $category['color'];
require_once '../includes/header.php';
?>
<nav class="navbar">
    <a href="dashboard.php" class="back-btn">← Kembali</a>
    <div class="nav-title"><?= $category['icon'] ?> <?= htmlspecialchars($category['name']) ?></div>
</nav>

<div class="main">
    <div class="cat-header" data-jp="<?= htmlspecialchars($category['name_jp']) ?>">
        <div class="cat-big-icon"><?= $category['icon'] ?></div>
        <div class="cat-header-info">
            <div class="cat-title"><?= htmlspecialchars($category['name']) ?></div>
            <div class="cat-title-jp"><?= htmlspecialchars($category['name_jp']) ?></div>
            <div class="cat-desc"><?= htmlspecialchars($category['description']) ?></div>
            <div class="prog-wrap">
                <div class="prog-bar"><div class="prog-fill" style="width:<?= $pct ?>%"></div></div>
                <span class="prog-text"><?= $completedCount ?>/<?= $totalCount ?> selesai • <?= $pct ?>%</span>
            </div>
        </div>
    </div>

    <div class="section-title">📚 Daftar Pelajaran</div>
    <div class="lesson-list">
        <?php foreach ($allLessons as $i => $lesson): 
            $isDone = $lesson['completed'];
            $stars = '';
            if ($isDone) {
                $pctScore = $lesson['total_questions'] > 0 ? ($lesson['score'] / $lesson['total_questions']) * 100 : 0;
                $stars = $pctScore >= 80 ? '⭐⭐⭐' : ($pctScore >= 50 ? '⭐⭐' : '⭐');
            }
        ?>
        <?php if ($lesson['total_questions'] == 0): ?>
        <div class="lesson-card locked">
            <div class="lesson-num" style="background:var(--border);border-color:var(--border);color:var(--text-muted)">🔒</div>
            <div class="lesson-info">
                <div class="lesson-title" style="color:var(--text-muted)"><?= htmlspecialchars($lesson['title']) ?></div>
                <?php if ($lesson['title_jp']): ?>
                <div class="lesson-sub"><?= htmlspecialchars($lesson['title_jp']) ?></div>
                <?php endif; ?>
                <div class="lesson-meta">
                    <span class="lesson-badge" style="background:rgba(136,136,170,0.1);color:var(--text-muted)">⏳ Soal belum tersedia</span>
                </div>
            </div>
            <div class="lesson-arrow" style="color:var(--border)">🔒</div>
        </div>
        <?php else: ?>
        <a href="lesson.php?id=<?= $lesson['id'] ?>" class="lesson-card <?= $isDone ? 'done' : '' ?>">
            <div class="lesson-num <?= $isDone ? 'done-num' : '' ?>">
                <?= $isDone ? '✓' : ($i+1) ?>
            </div>
            <div class="lesson-info">
                <div class="lesson-title"><?= htmlspecialchars($lesson['title']) ?></div>
                <?php if ($lesson['title_jp']): ?>
                <div class="lesson-sub"><?= htmlspecialchars($lesson['title_jp']) ?></div>
                <?php endif; ?>
                <div class="lesson-meta">
                    <span class="lesson-badge badge-xp">⚡ +<?= $lesson['xp_reward'] ?> XP</span>
                    <span class="lesson-badge badge-q">❓ <?= $lesson['total_questions'] ?> soal</span>
                    <?php if ($isDone): ?>
                    <span class="lesson-badge badge-done">✅ Selesai <?= $stars ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="lesson-arrow">→</div>
        </a>
        <?php endif; ?>
        <?php endforeach; ?>
    </div>

    <?php if (count($allVocab) > 0): ?>
    <div class="section-title">📖 Kosakata dalam Unit Ini</div>
    <div class="vocab-grid">
        <?php foreach ($allVocab as $v): ?>
        <div class="vocab-card">
            <div class="vocab-jp"><?= htmlspecialchars($v['japanese']) ?></div>
            <div class="vocab-romaji"><?= htmlspecialchars($v['romaji']) ?></div>
            <div class="vocab-id"><?= htmlspecialchars($v['indonesian']) ?></div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>