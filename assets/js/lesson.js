    
let currentQ = 0;
let hearts = 3;
let correctAnswers = 0;
let wrongAnswers = 0;
let selectedAnswer = '';
let answered = false;

const typeLabels = {
    'multiple_choice':  '🔤 Pilihan Ganda',
    'translate_id_jp':  '🇯🇵 Terjemahkan ke Jepang',
    'translate_jp_id':  '🇮🇩 Terjemahkan ke Indonesia',
    'fill_blank':       '✏️ Isi Titik-Titik',
    'match_pair':       '🔗 Cocokkan Pasangan'
};

function renderQuestion() {
    const q = questions[currentQ];
    answered = false;
    selectedAnswer = '';

    
    const pct = (currentQ / questions.length) * 100;
    document.getElementById('progressFill').style.width = pct + '%';
    document.getElementById('progressLabel').textContent = currentQ + ' / ' + questions.length;

    
    document.getElementById('qTypeLabel').textContent = typeLabels[q.type] || '❓ Pertanyaan';

    
    const isJp = /[\u3040-\u30FF\u4E00-\u9FAF]/.test(q.question);
    const qTextEl = document.getElementById('qText');
    qTextEl.className = 'q-text' + (isJp ? '' : ' latin');

    if (q.type === 'multiple_choice') {
        document.getElementById('qPrompt').textContent = '';
        qTextEl.textContent = q.question;
    } else if (q.type === 'translate_id_jp') {
        document.getElementById('qPrompt').textContent = 'Terjemahkan kalimat ini ke bahasa Jepang:';
        qTextEl.className = 'q-text latin';
        qTextEl.textContent = q.question;
    } else if (q.type === 'translate_jp_id') {
        document.getElementById('qPrompt').textContent = 'Apa artinya dalam bahasa Indonesia?';
        qTextEl.textContent = q.question;
    } else {
        document.getElementById('qPrompt').textContent = 'Soal:';
        qTextEl.textContent = q.question;
    }

    
    const hintBox = document.getElementById('hintBox');
    if (q.hint) {
        hintBox.textContent = '💡 ' + q.hint;
        hintBox.style.display = 'block';
    } else {
        hintBox.style.display = 'none';
    }

    
    const area = document.getElementById('optionsArea');
    area.innerHTML = '';

    let opts = null;
    if (q.options) {
        try {
            const parsed = typeof q.options === 'string' ? JSON.parse(q.options) : q.options;
            if (Array.isArray(parsed) && parsed.length > 0) opts = parsed;
        } catch(e) {
            opts = null;
        }
    }

    if (opts) {
        const grid = document.createElement('div');
        grid.className = 'options-grid';
        opts.forEach(opt => {
            const btn = document.createElement('button');
            btn.className = 'opt-btn';
            btn.textContent = opt;
            btn.onclick = () => selectOption(btn, opt);
            grid.appendChild(btn);
        });
        area.appendChild(grid);

        document.getElementById('feedbackBox').style.display = 'none';
        document.getElementById('btnCheck').style.display = 'block';
        document.getElementById('btnCheck').disabled = true;   // aktif setelah pilih opsi
        document.getElementById('btnNext').style.display = 'none';
    } else {
        const wrap = document.createElement('div');
        wrap.className = 'text-input-wrap';
        const input = document.createElement('input');
        input.type = 'text';
        input.className = 'text-input';
        input.id = 'textAnswer';
        input.placeholder = 'Ketik jawabanmu...';
        input.autocomplete = 'off';
        input.oninput = () => {
            selectedAnswer = input.value.trim();
            document.getElementById('btnCheck').disabled = selectedAnswer === '';
        };
        input.onkeydown = (e) => { if (e.key === 'Enter' && !answered) checkAnswer(); };
        wrap.appendChild(input);
        area.appendChild(wrap);
        setTimeout(() => input.focus(), 100);

        document.getElementById('feedbackBox').style.display = 'none';
        document.getElementById('btnCheck').style.display = 'block';
        document.getElementById('btnCheck').disabled = true;   // aktif setelah ketik
        document.getElementById('btnNext').style.display = 'none';
    }

    
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
    return str.toLowerCase().trim()
        .replace(/[、。！？]/g, '')
        .replace(/\s+/g, ' ');
}

function checkAnswer() {
    if (answered || !selectedAnswer) return;
    answered = true;

    const q = questions[currentQ];
    const correct = normalize(q.correct_answer);
    const given   = normalize(selectedAnswer);
    const isCorrect = correct === given;

    const feedback      = document.getElementById('feedbackBox');
    const feedbackTitle = document.getElementById('feedbackTitle');
    const feedbackSub   = document.getElementById('feedbackSub');

    if (isCorrect) {
        correctAnswers++;
        feedback.className = 'feedback correct';
        feedbackTitle.textContent = getRandomPraise();
        feedbackSub.innerHTML = '正解！ Jawabanmu benar! 🎉';
        spawnParticles();

        if (q.options) {
            document.querySelectorAll('.opt-btn').forEach(b => {
                if (normalize(b.textContent) === correct) b.classList.add('correct');
            });
        } else {
            document.getElementById('textAnswer').classList.add('correct');
        }
    } else {
        wrongAnswers++;
        hearts = Math.max(0, hearts - 1);
        updateHearts();

        feedback.className = 'feedback wrong';
        feedbackTitle.textContent = 'Kurang tepat...';
        feedbackSub.innerHTML = 'Jawaban yang benar: <span class="answer-show">' + q.correct_answer + '</span>';

        if (q.options) {
            document.querySelectorAll('.opt-btn').forEach(b => {
                const bVal = normalize(b.textContent);
                if (bVal === correct) b.classList.add('correct');
                else if (bVal === given) b.classList.add('wrong');
            });
        } else {
            document.getElementById('textAnswer').classList.add('wrong');
        }
    }

    
    document.querySelectorAll('.opt-btn').forEach(b => b.disabled = true);
    const ti = document.getElementById('textAnswer');
    if (ti) ti.disabled = true;

    feedback.style.display = 'block';
    document.getElementById('btnCheck').style.display = 'none';
    document.getElementById('btnNext').style.display = 'block';

    if (hearts <= 0 && !isCorrect) {
        document.getElementById('btnNext').textContent = 'Ulangi Pelajaran →';
    }
}

function nextQuestion() {
    if (hearts <= 0 && wrongAnswers > 0) {
        
        currentQ = 0;
        hearts = 3;
        correctAnswers = 0;
        wrongAnswers = 0;
        updateHearts();
        renderQuestion();
        return;
    }

    currentQ++;
    if (currentQ >= questions.length) {
        showComplete();
    } else {
        renderQuestion();
    }
}

function showComplete() {
    document.getElementById('quizScreen').style.display = 'none';
    document.getElementById('completeScreen').style.display = 'block';
    document.getElementById('closeBtn').removeAttribute('onclick');

    const total = questions.length;
    const pct   = Math.round((correctAnswers / total) * 100);
    const stars = pct >= 80 ? '⭐⭐⭐' : pct >= 50 ? '⭐⭐' : '⭐';
    const emoji = pct >= 80 ? '🎉' : pct >= 50 ? '👍' : '💪';
    const xp    = Math.round(xpReward * (correctAnswers / total));

    document.getElementById('completeEmoji').textContent = emoji;
    document.getElementById('starRating').textContent    = stars;
    document.getElementById('xpGained').textContent      = '+' + xp;
    document.getElementById('correctCount').textContent  = correctAnswers;
    document.getElementById('wrongCount').textContent    = wrongAnswers;

    
    fetch('save_progress.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            lesson_id: lessonId,
            score: correctAnswers,
            total: total,
            xp: xp
        })
    });

    
    document.getElementById('progressFill').style.width = '100%';
    document.getElementById('progressLabel').textContent = total + ' / ' + total;
}

function updateHearts() {
    const arr = ['❤️', '❤️', '❤️'];
    for (let i = hearts; i < 3; i++) arr[i] = '🖤';
    document.getElementById('hearts').textContent = arr.join('');
}

function getRandomPraise() {
    const praises = [
        'Benar! 🎯', 'Bagus sekali! ✨', 'Hebat! 素晴らしい！',
        'Luar biasa! すごい！', 'Tepat! 正解！', 'Kamu pintar! 🌟'
    ];
    return praises[Math.floor(Math.random() * praises.length)];
}

function spawnParticles() {
    const emojis = ['⭐', '✨', '🌟', '💫', '🎉', '🎊'];
    for (let i = 0; i < 6; i++) {
        setTimeout(() => {
            const p = document.createElement('div');
            p.className = 'particle';
            p.textContent = emojis[Math.floor(Math.random() * emojis.length)];
            p.style.left  = (Math.random() * 80 + 10) + 'vw';
            p.style.top   = (Math.random() * 50 + 25) + 'vh';
            p.style.animationDelay = (Math.random() * 0.3) + 's';
            document.body.appendChild(p);
            setTimeout(() => p.remove(), 1200);
        }, i * 80);
    }
}


renderQuestion();
