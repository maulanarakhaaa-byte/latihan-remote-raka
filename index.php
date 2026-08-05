<?php
require_once 'config/database.php';
require_once 'config/helper/authentication.php';


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $db = getDB();
    
    if ($_POST['action'] === 'login') {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ? OR username = ?");
        $stmt->execute([$email, $email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            redirect('user/dashboard.php');
        } else {
            $error = 'Email/username atau password salah!';
        }
    }
    
    if ($_POST['action'] === 'register') {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $avatars = ['🐱','🐶','🐼','🦊','🐨','🐯','🦁','🐸','🐧','🦉'];
        $avatar = $avatars[array_rand($avatars)];
        
        if (strlen($username) < 3) {
            $error = 'Username minimal 3 karakter!';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Format email tidak valid!';
        } elseif (strlen($password) < 6) {
            $error = 'Password minimal 6 karakter!';
        } else {
            $check = $db->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
            $check->execute([$email, $username]);
            if ($check->fetch()) {
                $error = 'Email atau username sudah digunakan!';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("INSERT INTO users (username, email, password, avatar) VALUES (?, ?, ?, ?)");
                $stmt->execute([$username, $email, $hash, $avatar]);
                $_SESSION['user_id'] = $db->lastInsertId();
                redirect('user/dashboard.php');
            }
        }
    }
}

if (isLoggedIn()) redirect('user/dashboard.php');
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>NihonGo! - Belajar Bahasa Jepang Seru!</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&family=Noto+Serif+JP:wght@400;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/base.css">
<link rel="stylesheet" href="assets/css/auth.css">
</head>
<body>
<div class="bg-anim"></div>


<div class="floating" style="left:5%;animation-duration:15s;animation-delay:0s">日</div>
<div class="floating" style="left:15%;animation-duration:20s;animation-delay:3s">本</div>
<div class="floating" style="left:25%;animation-duration:18s;animation-delay:6s">語</div>
<div class="floating" style="left:70%;animation-duration:22s;animation-delay:1s">学</div>
<div class="floating" style="left:85%;animation-duration:16s;animation-delay:8s">習</div>
<div class="floating" style="left:50%;animation-duration:19s;animation-delay:4s">あ</div>
<div class="floating" style="left:60%;animation-duration:17s;animation-delay:9s">い</div>

<div class="wrapper">
    
    <div class="hero">
        <div class="logo">🎌</div>
        <div class="logo-text">NihonGo!</div>
        <div class="logo-jp">日本語を学ぼう</div>
        <p class="hero-desc">Belajar bahasa Jepang dengan cara yang menyenangkan! Seperti bermain game, kumpulkan XP, jaga streak harian, dan kuasai bahasa Jepang. Ayo mulai sekarang!</p>
        
        <div class="hero-stats">
            <div class="stat-item">
                <div class="stat-num">8+</div>
                <div class="stat-label">Unit</div>
            </div>
            <div class="stat-item">
                <div class="stat-num">50+</div>
                <div class="stat-label">Pelajaran</div>
            </div>
            <div class="stat-item">
                <div class="stat-num">300+</div>
                <div class="stat-label">Kosakata</div>
            </div>
        </div>

        <div class="features">
            <div class="feature-item">
                <div class="feature-icon">⚡</div>
                <div class="feature-text">
                    <strong>Sistem XP & Level</strong>
                    Kumpulkan poin dan naik level setiap hari
                </div>
            </div>
            <div class="feature-item">
                <div class="feature-icon">🔥</div>
                <div class="feature-text">
                    <strong>Daily Streak</strong>
                    Jaga konsistensi belajar setiap hari
                </div>
            </div>
            <div class="feature-item">
                <div class="feature-icon">🎯</div>
                <div class="feature-text">
                    <strong>Quiz Interaktif</strong>
                    Latihan soal seru & langsung ada feedback
                </div>
            </div>
        </div>
    </div>

    
    <div class="auth-side">
        <div class="auth-box">
            <div class="tabs">
                <button class="tab-btn active" id="loginTab" onclick="switchTab('login')">Masuk</button>
                <button class="tab-btn" id="registerTab" onclick="switchTab('register')">Daftar</button>
            </div>

            <?php if (isset($error)): ?>
            <div class="error-msg">⚠️ <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            
            <form id="loginForm" method="POST">
                <input type="hidden" name="action" value="login">
                <div class="form-group">
                    <label>Email / Username</label>
                    <input type="text" name="email" placeholder="Masukkan email atau username" required>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" placeholder="Masukkan password" required>
                </div>
                <button type="submit" class="btn-primary">🚀 MULAI BELAJAR</button>
            </form>

            
            <form id="registerForm" method="POST" style="display:none">
                <input type="hidden" name="action" value="register">
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" placeholder="Pilih username unik" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" placeholder="Masukkan email kamu" required>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" placeholder="Minimal 6 karakter" required>
                </div>
                <button type="submit" class="btn-primary">🎌 BUAT AKUN GRATIS</button>
            </form>
        </div>
    </div>
</div>

<script>
function switchTab(tab) {
    document.getElementById('loginForm').style.display = tab === 'login' ? 'block' : 'none';
    document.getElementById('registerForm').style.display = tab === 'register' ? 'block' : 'none';
    document.getElementById('loginTab').classList.toggle('active', tab === 'login');
    document.getElementById('registerTab').classList.toggle('active', tab === 'register');
}

<?php if (isset($error) && isset($_POST['action']) && $_POST['action'] === 'register'): ?>
switchTab('register');
<?php endif; ?>
</script>
</body>
</html>