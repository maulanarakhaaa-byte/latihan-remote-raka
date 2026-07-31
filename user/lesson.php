<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/database.php';

if (!isLoggedIn()) redirect('../index.php');

$user     = getCurrentUser();
$db       = getDB();
$lessonId = intval($_GET['id'] ?? 0);

$lessonStmt = $db->prepare("
    SELECT l.*, c.name as cat_name, c.color as cat_color, c.id as cat_id
    FROM lessons l JOIN categories c ON c.id = l.category_id
    WHERE l.id = ?
");
$lessonStmt->execute([$lessonId]);
$lesson = $lessonStmt->fetch();
if (!$lesson) redirect('dashboard.php');

$questionsStmt = $db->prepare("SELECT * FROM questions WHERE lesson_id = ? ORDER BY order_num");
$questionsStmt->execute([$lessonId]);
$questions = $questionsStmt->fetchAll();

if (empty($questions)) {
    redirect('category.php?id=' . $lesson['cat_id']);
}


$questionsJson = json_encode($questions, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
if (!$questionsJson) $questionsJson = '[]';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($lesson['title']) ?> - NihonGo!</title>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&family=Noto+Serif+JP:wght@400;700&display=swap" rel="stylesheet">
<style>
:root {
    --red:#FF4B4B; --red-dark:#e03e3e;
    --green:#58CC02; --green-dark:#46a801;
    --blue:#1CB0F6; --yellow:#FFD900;
    --purple:#CE82FF; --orange:#FF9600;
    --bg:#0a0a1a; --card:#12122a; --card2:#1a1a3a;
    --text:#ffffff; --text-muted:#8888aa; --border:#2a2a4a;
    --accent:<?= htmlspecialchars($lesson['cat_color']) ?>;
}
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family:'Nunito',sans-serif; background:var(--bg); color:var(--text); min-height:100vh; }


.topbar {
    position:sticky; top:0; z-index:100;
    background:rgba(10,10,26,0.97); backdrop-filter:blur(20px);
    border-bottom:1px solid var(--border);
    padding:0 2rem; display:flex; align-items:center; gap:1rem; height:70px;
}
.close-btn {
    display:flex; align-items:center; justify-content:center;
    width:40px; height:40px; border-radius:50%;
    background:var(--card2); border:2px solid var(--border);
    color:var(--text-muted); text-decoration:none; font-size:1.2rem;
    transition:all 0.3s; flex-shrink:0;
}
.close-btn:hover { border-color:var(--red); color:var(--red); }
.progress-bar-wrap { flex:1; }
.progress-bar {
    height:16px; background:var(--card2); border-radius:8px;
    overflow:hidden; border:1px solid var(--border);
}
.progress-fill {
    height:100%; border-radius:8px;
    background:linear-gradient(90deg, var(--accent), var(--green));
    transition:width 0.5s ease; box-shadow:0 0 10px var(--accent);
}
.progress-label { font-size:0.8rem; color:var(--text-muted); text-align:center; margin-top:0.25rem; }
.heart-display { display:flex; gap:0.25rem; font-size:1.4rem; flex-shrink:0; }


.quiz-wrap {
    max-width:680px; margin:0 auto; padding:2rem;
    min-height:calc(100vh - 70px);
    display:flex; flex-direction:column; justify-content:center;
}


.q-card {
    background:var(--card); border:1px solid var(--border);
    border-radius:24px; padding:2.5rem; margin-bottom:2rem;
    animation:slideIn 0.4s ease; position:relative; overflow:hidden;
}
.q-card::before {
    content:''; position:absolute; top:0; left:0; right:0; height:3px;
    background:linear-gradient(90deg, var(--accent), var(--green));
}
@keyframes slideIn {
    from { opacity:0; transform:translateY(20px); }
    to   { opacity:1; transform:translateY(0); }
}
.q-type-label {
    display:inline-flex; align-items:center; gap:0.4rem;
    background:var(--card2); border:1px solid var(--border);
    border-radius:20px; padding:0.3rem 0.8rem;
    font-size:0.8rem; font-weight:700; color:var(--text-muted);
    text-transform:uppercase; letter-spacing:0.5px; margin-bottom:1.5rem;
}
.q-prompt { font-size:1rem; color:var(--text-muted); margin-bottom:0.5rem; font-weight:700; }
.q-text   { font-size:2rem; font-weight:900; font-family:'Noto Serif JP',serif; line-height:1.4; color:var(--text); }
.q-text.latin { font-family:'Nunito',sans-serif; }
.hint-box {
    display:none; background:rgba(255,150,0,0.1);
    border:1px solid rgba(255,150,0,0.3); border-radius:12px;
    padding:0.75rem 1rem; margin-top:1rem; font-size:0.9rem; color:var(--orange);
}


.options-grid { display:grid; gap:0.75rem; grid-template-columns:1fr 1fr; margin-bottom:1.5rem; }
.opt-btn {
    padding:1rem 1.25rem; border:2px solid var(--border);
    border-radius:16px; background:var(--card2); color:var(--text);
    font-family:'Noto Serif JP',serif; font-size:1.1rem; font-weight:700;
    cursor:pointer; transition:all 0.2s; text-align:center; line-height:1.3;
}
.opt-btn:hover:not(:disabled) { border-color:var(--accent); background:rgba(255,255,255,0.05); transform:translateY(-2px); box-shadow:0 6px 20px rgba(0,0,0,0.3); }
.opt-btn.selected { border-color:var(--blue); background:rgba(28,176,246,0.15); }
.opt-btn.correct  { border-color:var(--green)!important; background:rgba(88,204,2,0.2)!important; color:var(--green)!important; }
.opt-btn.wrong    { border-color:var(--red)!important; background:rgba(255,75,75,0.15)!important; color:var(--red)!important; }
.opt-btn:disabled { cursor:not-allowed; opacity:0.7; }


.text-input-wrap { margin-bottom:1.5rem; }
.text-input {
    width:100%; padding:1.2rem; border:2px solid var(--border); border-radius:16px;
    background:var(--card2); color:var(--text);
    font-family:'Noto Serif JP',serif; font-size:1.5rem; font-weight:700;
    text-align:center; transition:all 0.3s; letter-spacing:2px;
}
.text-input:focus  { outline:none; border-color:var(--accent); box-shadow:0 0 0 4px rgba(255,75,75,0.15); }
.text-input.correct{ border-color:var(--green); background:rgba(88,204,2,0.1); }
.text-input.wrong  { border-color:var(--red);   background:rgba(255,75,75,0.1); }


.feedback { padding:1.25rem 1.5rem; border-radius:16px; margin-bottom:1.5rem; display:none; animation:slideIn 0.3s ease; }
.feedback.correct { background:rgba(88,204,2,0.15); border:1px solid rgba(88,204,2,0.3); }
.feedback.wrong   { background:rgba(255,75,75,0.15); border:1px solid rgba(255,75,75,0.3); }
.feedback-title   { font-size:1.1rem; font-weight:900; margin-bottom:0.3rem; }
.feedback.correct .feedback-title { color:var(--green); }
.feedback.wrong   .feedback-title { color:var(--red); }
.feedback-sub  { font-size:0.95rem; color:var(--text-muted); }
.answer-show   { font-family:'Noto Serif JP',serif; font-weight:700; color:var(--text); font-size:1.1rem; }


.btn-check {
    width:100%; padding:1.1rem; border:none; cursor:pointer;
    border-radius:16px; font-family:'Nunito',sans-serif; font-size:1.1rem; font-weight:800;
    background:var(--green); color:white; box-shadow:0 4px 0 var(--green-dark); transition:all 0.15s;
}
.btn-check:hover:not(:disabled) { transform:translateY(-2px); box-shadow:0 6px 0 var(--green-dark); }
.btn-check:active:not(:disabled){ transform:translateY(2px);  box-shadow:0 2px 0 var(--green-dark); }
.btn-check:disabled { background:var(--card2); color:var(--text-muted); box-shadow:none; cursor:not-allowed; }
.btn-next {
    width:100%; padding:1.1rem; border:none; cursor:pointer;
    border-radius:16px; font-family:'Nunito',sans-serif; font-size:1.1rem; font-weight:800; display:none;
    background:var(--blue); color:white; box-shadow:0 4px 0 #1590c8; transition:all 0.15s;
}
.btn-next:hover { transform:translateY(-2px); box-shadow:0 6px 0 #1590c8; }


.complete-screen { display:none; text-align:center; animation:slideIn 0.5s ease; }
.complete-emoji  { font-size:6rem; margin-bottom:1rem; display:block; animation:bounce 0.5s ease 0.3s both; }
@keyframes bounce { 0%,100%{transform:scale(1)} 50%{transform:scale(1.2)} }
.complete-title  { font-size:2.5rem; font-weight:900; margin-bottom:0.5rem; }
.complete-sub    { color:var(--text-muted); font-size:1.1rem; margin-bottom:2rem; }
.complete-stats  { display:flex; gap:1rem; justify-content:center; margin-bottom:2rem; }
.complete-stat   { background:var(--card); border:1px solid var(--border); border-radius:20px; padding:1.25rem 1.75rem; text-align:center; }
.complete-stat-val   { font-size:2.5rem; font-weight:900; }
.complete-stat-label { font-size:0.85rem; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.5px; }
.xp-gained  { color:var(--green); }
.star-rating{ font-size:2.5rem; margin-bottom:1.5rem; }
.btn-finish {
    display:inline-block; padding:1rem 2.5rem; border:none; cursor:pointer;
    border-radius:16px; font-family:'Nunito',sans-serif; font-size:1.1rem; font-weight:800;
    background:var(--green); color:white; box-shadow:0 4px 0 var(--green-dark); transition:all 0.15s; text-decoration:none;
}
.btn-finish:hover { transform:translateY(-2px); box-shadow:0 6px 0 var(--green-dark); }


.particle { position:fixed; pointer-events:none; z-index:999; font-size:1.5rem; animation:particle-up 1s ease forwards; }
@keyframes particle-up { 0%{transform:translateY(0) scale(1);opacity:1} 100%{transform:translateY(-100px) scale(0.5);opacity:0} }

@media(max-width:640px) {
    .options-grid { grid-template-columns:1fr; }
    .q-text       { font-size:1.5rem; }
    .quiz-wrap    { padding:1rem; }
    .topbar       { padding:0 1rem; }
}
</style>
</head>
<body>


<div class="topbar">
    <a href="category.php?id=<?= $lesson['cat_id'] ?>"
       class="close-btn" id="closeBtn"
       onclick="return confirm('Keluar dari pelajaran? Progress akan hilang.')">✕</a>
    <div class="progress-bar-wrap">
        <div class="progress-bar">
            <div class="progress-fill" id="progressFill" style="width:0%"></div>
        </div>
        <div class="progress-label" id="progressLabel">0 / <?= count($questions) ?></div>
    </div>
    <div class="heart-display" id="hearts">❤️❤️❤️</div>
</div>


<div class="quiz-wrap">

    
    <div id="quizScreen">
        <div class="q-card" id="qCard">
            <div class="q-type-label" id="qTypeLabel">❓ Pertanyaan</div>
            <div class="q-prompt" id="qPrompt"></div>
            <div class="q-text" id="qText"></div>
            <div class="hint-box" id="hintBox"></div>
        </div>

        <div id="optionsArea"></div>

        <div class="feedback" id="feedbackBox">
            <div class="feedback-title" id="feedbackTitle"></div>
            <div class="feedback-sub"  id="feedbackSub"></div>
        </div>

        <button class="btn-check" id="btnCheck" disabled onclick="checkAnswer()">COBA</button>
        <button class="btn-next"  id="btnNext"  onclick="nextQuestion()">LANJUT →</button>
    </div>

    
    <div class="complete-screen" id="completeScreen">
        <span class="complete-emoji" id="completeEmoji">🎉</span>
        <div class="complete-title">Pelajaran Selesai!</div>
        <div class="complete-sub">Kerja bagus! テスト完了！</div>
        <div class="star-rating" id="starRating"></div>
        <div class="complete-stats">
            <div class="complete-stat">
                <div class="complete-stat-val xp-gained" id="xpGained">+0</div>
                <div class="complete-stat-label">⚡ XP</div>
            </div>
            <div class="complete-stat">
                <div class="complete-stat-val" id="correctCount" style="color:var(--green)">0</div>
                <div class="complete-stat-label">✅ Benar</div>
            </div>
            <div class="complete-stat">
                <div class="complete-stat-val" id="wrongCount" style="color:var(--red)">0</div>
                <div class="complete-stat-label">❌ Salah</div>
            </div>
        </div>
        <a href="category.php?id=<?= $lesson['cat_id'] ?>" class="btn-finish">🏠 Kembali ke Unit</a>
    </div>

</div>

<script>

const questions = <?= $questionsJson ?>;
const lessonId  = <?= (int)$lesson['id'] ?>;
console.log('Total soal:', questions.length, questions);
const xpReward  = <?= (int)$lesson['xp_reward'] ?>;
const saveUrl   = 'save_progress.php';

// ─── State ──────────────────────────────────────────────────
let currentQ       = 0;
let hearts         = 3;
let correctAnswers = 0;
let wrongAnswers   = 0;
let selectedAnswer = '';
let answered       = false;

const typeLabels = {
    'multiple_choice' : '🔤 Pilihan Ganda',
    'translate_id_jp' : '🇯🇵 Terjemahkan ke Jepang',
    'translate_jp_id' : '🇮🇩 Terjemahkan ke Indonesia',
    'fill_blank'      : '✏️ Isi Titik-Titik',
    'match_pair'      : '🔗 Cocokkan Pasangan',
};


function renderQuestion() {
    const q    = questions[currentQ];
    answered       = false;
    selectedAnswer = '';

    
    document.getElementById('progressFill').style.width =
        (currentQ / questions.length * 100) + '%';
    document.getElementById('progressLabel').textContent =
        currentQ + ' / ' + questions.length;

    
    document.getElementById('qTypeLabel').textContent =
        typeLabels[q.type] || '❓ Pertanyaan';

    
    const qTextEl = document.getElementById('qText');
    qTextEl.className = 'q-text';

    if (q.type === 'translate_id_jp') {
        document.getElementById('qPrompt').textContent = 'Terjemahkan kalimat ini ke bahasa Jepang:';
        qTextEl.classList.add('latin');
    } else if (q.type === 'translate_jp_id') {
        document.getElementById('qPrompt').textContent = 'Apa artinya dalam bahasa Indonesia?';
    } else if (q.type === 'fill_blank') {
        document.getElementById('qPrompt').textContent = 'Isi bagian yang kosong:';
        qTextEl.classList.add('latin');
    } else {
        document.getElementById('qPrompt').textContent = '';
        qTextEl.classList.add('latin');
    }
    qTextEl.textContent = q.question_text;

    
    const hintBox = document.getElementById('hintBox');
    if (q.hint) {
        hintBox.textContent   = '💡 ' + q.hint;
        hintBox.style.display = 'block';
    } else {
        hintBox.style.display = 'none';
    }

    
    const area = document.getElementById('optionsArea');
    area.innerHTML = '';

    let opts = null;
    if (q.options) {
        try { opts = (typeof q.options === 'string') ? JSON.parse(q.options) : q.options; }
        catch(e) { opts = null; }
    }

    if (opts && opts.length >= 2) {
        
        const grid = document.createElement('div');
        grid.className = 'options-grid';
        opts.forEach(opt => {
            const btn       = document.createElement('button');
            btn.className   = 'opt-btn';
            btn.textContent = opt;
            btn.onclick     = () => selectOption(btn, opt);
            grid.appendChild(btn);
        });
        area.appendChild(grid);
        document.getElementById('btnCheck').disabled = true;
    } else {
        
        const wrap  = document.createElement('div');
        wrap.className = 'text-input-wrap';
        const input    = document.createElement('input');
        input.type         = 'text';
        input.className    = 'text-input';
        input.id           = 'textAnswer';
        input.placeholder  = 'Ketik jawabanmu...';
        input.autocomplete = 'off';
        input.oninput  = () => {
            selectedAnswer = input.value.trim();
            document.getElementById('btnCheck').disabled = !selectedAnswer;
        };
        input.onkeydown = e => { if (e.key === 'Enter' && !answered) checkAnswer(); };
        wrap.appendChild(input);
        area.appendChild(wrap);
        setTimeout(() => input.focus(), 100);
        document.getElementById('btnCheck').disabled = true;
    }

    
    const fb = document.getElementById('feedbackBox');
    fb.style.display = 'none';
    fb.className     = 'feedback';
    document.getElementById('btnCheck').style.display = 'block';
    document.getElementById('btnNext').style.display  = 'none';
    document.getElementById('btnNext').textContent    = 'LANJUT →';

    
    const card = document.getElementById('qCard');
    card.style.animation = 'none';
    card.offsetHeight;
    card.style.animation = 'slideIn 0.4s ease';
}


function selectOption(btn, value) {
    if (answered) return;
    document.querySelectorAll('.opt-btn').forEach(b => b.classList.remove('selected'));
    btn.classList.add('selected');
    selectedAnswer = value;
    document.getElementById('btnCheck').disabled = false;
}


function normalize(str) {
    return (str || '').toLowerCase().trim().replace(/\s+/g,' ');
}


function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}


function checkAnswer() {
    if (answered || !selectedAnswer) return;
    answered = true;

    const q         = questions[currentQ];
    const isCorrect = normalize(q.correct_answer) === normalize(selectedAnswer);

    const fb    = document.getElementById('feedbackBox');
    const title = document.getElementById('feedbackTitle');
    const sub   = document.getElementById('feedbackSub');

    if (isCorrect) {
        correctAnswers++;
        fb.className      = 'feedback correct';
        title.textContent = getRandomPraise();
        sub.innerHTML     = '正解！ Jawabanmu benar! 🎉';
        spawnParticles();
        document.querySelectorAll('.opt-btn').forEach(b => {
            if (normalize(b.textContent) === normalize(q.correct_answer)) b.classList.add('correct');
        });
        const ti = document.getElementById('textAnswer');
        if (ti) ti.classList.add('correct');
    } else {
        wrongAnswers++;
        hearts = Math.max(0, hearts - 1);
        updateHearts();
        fb.className      = 'feedback wrong';
        title.textContent = 'Kurang tepat...';
        sub.innerHTML     = 'Jawaban yang benar: <span class="answer-show">' + escHtml(q.correct_answer) + '</span>';
        document.querySelectorAll('.opt-btn').forEach(b => {
            if (normalize(b.textContent) === normalize(q.correct_answer)) b.classList.add('correct');
            else if (normalize(b.textContent) === normalize(selectedAnswer)) b.classList.add('wrong');
        });
        const ti = document.getElementById('textAnswer');
        if (ti) ti.classList.add('wrong');
    }

    document.querySelectorAll('.opt-btn').forEach(b => b.disabled = true);
    const ti2 = document.getElementById('textAnswer');
    if (ti2) ti2.disabled = true;

    fb.style.display = 'block';
    document.getElementById('btnCheck').style.display = 'none';
    document.getElementById('btnNext').style.display  = 'block';

    if (hearts <= 0 && !isCorrect) {
        document.getElementById('btnNext').textContent = '🔄 Ulangi Pelajaran';
    }
}


function nextQuestion() {
    if (hearts <= 0) {
        currentQ = 0; hearts = 3; correctAnswers = 0; wrongAnswers = 0;
        updateHearts(); renderQuestion(); return;
    }
    currentQ++;
    if (currentQ >= questions.length) showComplete();
    else renderQuestion();
}


function showComplete() {
    document.getElementById('quizScreen').style.display     = 'none';
    document.getElementById('completeScreen').style.display = 'block';
    const cb = document.getElementById('closeBtn');
    if (cb) cb.removeAttribute('onclick');

    const total = questions.length;
    const pct   = Math.round(correctAnswers / Math.max(total,1) * 100);
    const xp    = Math.max(1, Math.round(xpReward * correctAnswers / Math.max(total,1)));

    document.getElementById('completeEmoji').textContent  = pct>=80?'🎉':pct>=50?'👍':'💪';
    document.getElementById('starRating').textContent     = pct>=80?'⭐⭐⭐':pct>=50?'⭐⭐':'⭐';
    document.getElementById('xpGained').textContent       = '+' + xp;
    document.getElementById('correctCount').textContent   = correctAnswers;
    document.getElementById('wrongCount').textContent     = wrongAnswers;
    document.getElementById('progressFill').style.width   = '100%';
    document.getElementById('progressLabel').textContent  = total + ' / ' + total;

    fetch(saveUrl, {
        method : 'POST',
        headers: {'Content-Type':'application/json'},
        body   : JSON.stringify({ lesson_id:lessonId, score:correctAnswers, total:total, xp:xp })
    }).catch(()=>{});
}

function updateHearts() {
    const d = ['❤️','❤️','❤️'];
    for (let i = hearts; i < 3; i++) d[i] = '🖤';
    document.getElementById('hearts').textContent = d.join('');
}

function getRandomPraise() {
    const p = ['Benar! 🎯','Bagus sekali! ✨','Hebat! 素晴らしい！','Luar biasa! すごい！','Tepat! 正解！','Kamu pintar! 🌟'];
    return p[Math.floor(Math.random() * p.length)];
}

function spawnParticles() {
    const e = ['⭐','✨','🌟','💫','🎉','🎊'];
    for (let i=0; i<6; i++) setTimeout(()=>{
        const p = document.createElement('div');
        p.className   = 'particle';
        p.textContent = e[Math.floor(Math.random()*e.length)];
        p.style.left  = (Math.random()*80+10) + 'vw';
        p.style.top   = (Math.random()*50+25) + 'vh';
        document.body.appendChild(p);
        setTimeout(()=>p.remove(), 1200);
    }, i*80);
}


try {
    renderQuestion();
} catch(e) {
    console.error('renderQuestion error:', e);
    document.getElementById('quizScreen').innerHTML = '<div style="color:red;padding:2rem;text-align:center"><h2>Error memuat soal</h2><pre>' + e.message + '</pre></div>';
}
</script>
</body>
</html>