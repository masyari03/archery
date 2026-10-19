<?php
// login.php - Dual Mode Login dengan Pop-up Modal Error Keren & Direct WA Admin
require_once __DIR__ . '/config/auth.php';

$error = '';
$stmtClub = $pdo->prepare("SELECT club_name, admin_wa_phone, logo_url FROM archery_clubs WHERE id = 1 LIMIT 1");
$stmtClub->execute();
$club = $stmtClub->fetch();
$adminWa = $club['admin_wa_phone'] ?? '081299887766';
$clubName = $club['club_name'] ?? 'AN-NAHL ARCHERY CLUB';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loginType = $_POST['login_type'] ?? 'user';

    // 1. Login Akun Normal (Ortu, Coach, Admin)
    if ($loginType === 'user') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (!empty($username) && !empty($password)) {
            $stmt = $pdo->prepare("SELECT * FROM archery_users WHERE (username = :u OR phone = :p OR email = :e) AND is_active = 1 LIMIT 1");
            $stmt->execute([':u' => $username, ':p' => $username, ':e' => $username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['phone'] = $user['phone'];
                $_SESSION['club_id'] = $user['club_id'];

                // Set anak aktif pertama jika orang tua
                $children = Auth::getChildren($pdo);
                if (!empty($children)) {
                    $_SESSION['active_child_id'] = $children[0]['id'];
                }

                // Redirect sesuai role
                if ($user['role'] === 'master_admin') {
                    header('Location: master_tenants.php');
                } elseif ($user['role'] === 'super_admin') {
                    header('Location: cms_admin.php');
                } elseif ($user['role'] === 'coach') {
                    header('Location: coach_dashboard.php');
                } else {
                    header('Location: app_dashboard.php');
                }
                exit;
            } else {
                $error = 'Username / No. WhatsApp atau password yang Anda masukkan tidak sesuai.';
            }
        } else {
            $error = 'Silakan isi seluruh kolom formulir.';
        }
    }

    // 2. Login Perangkat Lapangan Atlet (Quick PIN)
    if ($loginType === 'pin') {
        $pin = trim($_POST['access_pin'] ?? '');
        if (!empty($pin)) {
            $stmt = $pdo->prepare("SELECT * FROM archery_archers WHERE access_pin = :pin AND is_active = 1 LIMIT 1");
            $stmt->execute([':pin' => $pin]);
            $archer = $stmt->fetch();

            if ($archer) {
                $_SESSION['archer_device_id'] = $archer['id'];
                $_SESSION['role'] = 'archer';
                $_SESSION['full_name'] = $archer['full_name'];
                $_SESSION['active_child_id'] = $archer['id'];
                $_SESSION['club_id'] = $archer['club_id'];

                header('Location: app_dashboard.php');
                exit;
            } else {
                $error = 'PIN Akses Atlet Lapangan tidak ditemukan atau salah.';
            }
        } else {
            $error = 'Silakan masukkan 6-digit PIN anak.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= htmlspecialchars($clubName) ?></title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
    <script>
        (function() {
            const savedTheme = localStorage.getItem('archery_theme') || 'dark';
            document.documentElement.setAttribute('data-theme', savedTheme);
        })();
    </script>
    <style>
        .login-wrap {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }
        .login-card {
            max-width: 440px;
            width: 100%;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 2.25rem 1.75rem;
            box-shadow: var(--shadow-md);
            backdrop-filter: blur(20px);
        }
        .login-tabs {
            display: flex;
            background: rgba(0,0,0,0.2);
            border-radius: var(--radius-sm);
            padding: 4px;
            margin-bottom: 1.5rem;
            gap: 4px;
        }
        .login-tab-btn {
            flex: 1;
            padding: 0.65rem 0.5rem;
            border: none;
            background: transparent;
            color: var(--text-muted);
            font-family: 'Outfit', sans-serif;
            font-weight: 700;
            font-size: 0.85rem;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
            text-align: center;
        }
        .login-tab-btn.active {
            background: var(--primary);
            color: #fff;
            box-shadow: 0 2px 10px rgba(2, 132, 199, 0.4);
        }
        .demo-box {
            background: rgba(255, 255, 255, 0.03);
            border: 1px dashed var(--border-color);
            border-radius: var(--radius-sm);
            padding: 0.85rem;
            margin-top: 1.5rem;
            font-size: 0.78rem;
            color: var(--text-muted);
        }
        .demo-btn {
            background: var(--primary-light);
            border: 1px solid var(--border-glow);
            color: var(--primary);
            padding: 3px 8px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.75rem;
            font-weight: 700;
            margin: 2px;
        }
    </style>
</head>
<body>

<div class="login-wrap">
    <div class="login-card">
        <div style="display: flex; justify-content: flex-end; margin-bottom: -1rem;">
            <button type="button" class="theme-toggle-btn" onclick="toggleAppTheme()" title="Ganti Tema">
                🌙
            </button>
        </div>

        <div style="text-align: center; margin-bottom: 1.75rem;">
            <div style="width: 58px; height: 58px; margin: 0 auto 0.75rem; border-radius: 50%; background: conic-gradient(from 0deg, var(--ring-gold), var(--ring-red), var(--ring-blue), var(--ring-gold)); display: flex; align-items: center; justify-content: center; box-shadow: var(--shadow-glow);">
                <span style="font-size: 26px;">🎯</span>
            </div>
            <h1 style="font-size: 1.5rem; margin-bottom: 0.2rem;"><?= htmlspecialchars($clubName) ?></h1>
            <p style="color: var(--text-muted); font-size: 0.85rem;">Portal Skoring Panahan & Monitoring Atlet</p>
        </div>

        <!-- DUAL TABS: AKUN KELUARGA/COACH/ADMIN VS PERANGKAT LAPANGAN ATLET -->
        <div class="login-tabs">
            <button type="button" class="login-tab-btn active" id="tabUserBtn" onclick="switchLoginTab('user')">
                👤 Akun Ortu / Coach / Admin
            </button>
            <button type="button" class="login-tab-btn" id="tabPinBtn" onclick="switchLoginTab('pin')">
                📱 Mode PIN Anak (Lapangan)
            </button>
        </div>

        <!-- FORM 1: ORTU / COACH / ADMIN -->
        <form method="POST" id="formUser">
            <input type="hidden" name="login_type" value="user">
            <div class="form-group">
                <label class="form-label">Username / No. WhatsApp / Email</label>
                <input type="text" name="username" class="form-control" id="userInput" placeholder="Contoh: ortu_budi atau coach_fadli atau superadmin" required>
            </div>
            <div class="form-group">
                <label class="form-label">Kata Sandi (Password)</label>
                <input type="password" name="password" class="form-control" id="passInput" placeholder="••••••••" required>
            </div>
            <button type="submit" class="btn btn-primary btn-block" style="margin-top: 1rem;">
                Masuk ke Dashboard ➔
            </button>
        </form>

        <!-- FORM 2: DEVICE LAPANGAN (PIN MANDIRI ANAK) -->
        <form method="POST" id="formPin" style="display: none;">
            <input type="hidden" name="login_type" value="pin">
            <div class="form-group">
                <label class="form-label">6-Digit PIN Akses Atlet (Anak)</label>
                <input type="password" name="access_pin" class="form-control" id="pinInput" placeholder="Contoh: 111111" maxlength="6" style="letter-spacing: 0.5rem; text-align: center; font-size: 1.35rem; font-weight: 800;" required>
                <small style="display: block; color: var(--text-dim); margin-top: 0.5rem; font-size: 0.75rem;">
                    💡 Anak dapat memasukkan PIN di smartphone masing-masing untuk langsung membuka papan bidik tanpa melihat menu keuangan orang tua.
                </small>
            </div>
            <button type="submit" class="btn btn-primary btn-block" style="margin-top: 1rem;">
                Buka Papan Bidik Lapangan 🎯
            </button>
        </form>

        <!-- DEMO SHORTCUTS UNTUK TESTING CEPAT -->
        <div class="demo-box">
            <b>⚡ Akses Cepat Demo:</b><br>
            <div style="margin-top: 6px; display: flex; flex-wrap: wrap; gap: 4px;">
                <button type="button" class="demo-btn" style="color: #f59e0b; font-weight: 800; border-color: #f59e0b;" onclick="fillUser('masteradmin', 'admin123')">👑 Master Admin (SaaS)</button>
                <button type="button" class="demo-btn" onclick="fillUser('superadmin', 'admin123')">Admin An-Nahl</button>
                <button type="button" class="demo-btn" onclick="fillUser('admin_focus', 'admin123')">Admin Focus Archery</button>
                <button type="button" class="demo-btn" onclick="fillUser('coach_fadli', 'admin123')">Coach Fadli</button>
            </div>
            <div style="margin-top: 4px;">
                <b>Ortu (2 Anak):</b> <button type="button" class="demo-btn" onclick="fillUser('ortu_budi', 'admin123')">Login Ortu Budi</button>
            </div>
            <div style="margin-top: 4px;">
                <b>PIN Anak 1:</b> <button type="button" class="demo-btn" onclick="fillPin('111111')">111111 (Ahmad)</button>
                <b>PIN Anak 2:</b> <button type="button" class="demo-btn" onclick="fillPin('222222')">222222 (Fatimah)</button>
            </div>
        </div>

        <div style="margin-top: 1.25rem; text-align: center; font-size: 0.85rem; display: flex; justify-content: space-between; align-items: center;">
            <a href="index.php" style="color: var(--primary); text-decoration: none; font-weight: 600;">
                ⬅ Web Profil Publik
            </a>
            <a href="register_archer.php" style="color: var(--warning); text-decoration: none; font-weight: 700;">
                🏹 Daftar Santri Baru
            </a>
        </div>
    </div>
</div>

<!-- POP-UP MODAL ERROR LOGIN KEREN & RAMAH PENGGUNA -->
<div class="modal-backdrop" id="errorLoginModal" <?= !empty($error) ? 'style="display: flex;"' : '' ?>>
    <div class="modal-box" style="text-align: center;">
        <div style="width: 68px; height: 68px; margin: 0 auto 1.25rem; border-radius: 50%; background: conic-gradient(from 0deg, var(--ring-gold), var(--ring-red), var(--ring-blue), var(--ring-gold)); display: flex; align-items: center; justify-content: center; box-shadow: 0 0 25px rgba(239, 68, 68, 0.4);">
            <span style="font-size: 30px;">🎯</span>
        </div>

        <h3 style="font-size: 1.3rem; margin-bottom: 0.5rem; color: #ef4444;">
            Mohon Maaf, Akses Belum Sesuai
        </h3>

        <p style="color: var(--text-muted); font-size: 0.9rem; line-height: 1.5; margin-bottom: 1.5rem;">
            <?= htmlspecialchars($error) ?><br><br>
            Jika Anda adalah anggota baru, lupa kata sandi, atau memerlukan bantuan akses sistem <b><?= htmlspecialchars($clubName) ?></b>, silakan hubungi Administrator Klub melalui tautan di bawah ini.
        </p>

        <?php 
        $cleanWa = preg_replace('/[^0-9]/', '', $adminWa);
        if (substr($cleanWa, 0, 1) === '0') $cleanWa = '62' . substr($cleanWa, 1);
        $waUrl = "https://api.whatsapp.com/send?phone={$cleanWa}&text=" . urlencode("Halo Administrator {$clubName}, saya mengalami kendala login akun di sistem Target Archery. Mohon bantuannya.");
        ?>

        <div style="display: flex; flex-direction: column; gap: 0.65rem;">
            <a href="<?= $waUrl ?>" target="_blank" class="btn btn-primary btn-block" style="background: #25d366; color: #fff; box-shadow: 0 4px 15px rgba(37, 211, 102, 0.4);">
                💬 Hubungi Admin via WhatsApp
            </a>
            <button type="button" class="btn btn-secondary btn-block" onclick="closeErrorModal()">
                Tutup & Coba Lagi
            </button>
        </div>
    </div>
</div>

<script>
function closeErrorModal() {
    document.getElementById('errorLoginModal').style.display = 'none';
}

function switchLoginTab(type) {
    const btnUser = document.getElementById('tabUserBtn');
    const btnPin = document.getElementById('tabPinBtn');
    const formUser = document.getElementById('formUser');
    const formPin = document.getElementById('formPin');

    if (type === 'user') {
        btnUser.classList.add('active');
        btnPin.classList.remove('active');
        formUser.style.display = 'block';
        formPin.style.display = 'none';
    } else {
        btnPin.classList.add('active');
        btnUser.classList.remove('active');
        formPin.style.display = 'block';
        formUser.style.display = 'none';
    }
}

function fillUser(u, p) {
    switchLoginTab('user');
    document.getElementById('userInput').value = u;
    document.getElementById('passInput').value = p;
}

function fillPin(pin) {
    switchLoginTab('pin');
    document.getElementById('pinInput').value = pin;
}

function toggleAppTheme() {
    const current = document.documentElement.getAttribute('data-theme') || 'dark';
    const next = current === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', next);
    localStorage.setItem('archery_theme', next);
}
</script>

</body>
</html>
