<?php
// login.php - Dual Mode Login dengan Pop-up Modal Error Keren & Direct WA Admin
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/tenant_resolver.php';

$error = '';

// Handle Error Redirect dari Middleware
if (isset($_GET['error'])) {
    if ($_GET['error'] === 'wrong_tenant') {
        $error = 'Sesi Anda terputus karena akun Anda tidak terdaftar di klub ini. Silakan login di portal resmi klub Anda.';
    } elseif ($_GET['error'] === 'session_terminated') {
        $error = 'Sesi login Anda telah diputus oleh Administrator demi keamanan. Silakan login kembali.';
    }
}

// Resolusi Tenant Dinamis dari Subdomain Host
$currentTenant = TenantResolver::resolve($pdo);
$tenantId = (int)$currentTenant['id'];
$adminWa = $currentTenant['admin_wa_phone'] ?? '081299887766';
$clubName = $currentTenant['club_name'] ?? 'AN-NAHL ARCHERY CLUB';
$clubLogo = $currentTenant['logo_url'] ?? '';
$clubFavicon = $currentTenant['favicon_url'] ?? 'assets/img/default_favicon.png';

// Auto-Login via Cookie "Ingat Saya"
if (!Auth::check() && !empty($_COOKIE['archery_remember'])) {
    $parts = explode(':', $_COOKIE['archery_remember']);
    if (count($parts) === 2) {
        $rawTok = $parts[0];
        $uid = (int)$parts[1];
        $tokHash = hash('sha256', $rawTok);

        $stmtAuto = $pdo->prepare("SELECT * FROM archery_users WHERE id = :uid AND remember_token = :tok AND remember_expires_at > NOW() AND is_active = 1 LIMIT 1");
        $stmtAuto->execute([':uid' => $uid, ':tok' => $tokHash]);
        $autoUser = $stmtAuto->fetch();

        if ($autoUser && (int)$autoUser['club_id'] === $tenantId && $autoUser['role'] !== 'master_admin') {
            $_SESSION['user_id'] = $autoUser['id'];
            $_SESSION['username'] = $autoUser['username'];
            $_SESSION['full_name'] = $autoUser['full_name'];
            $_SESSION['role'] = $autoUser['role'];
            $_SESSION['phone'] = $autoUser['phone'];
            $_SESSION['email'] = $autoUser['email'] ?? '';
            $_SESSION['club_id'] = (int)$autoUser['club_id'];
            $_SESSION['avatar_url'] = $autoUser['avatar_url'] ?? null;

            ActivityLogger::recordSession($pdo, (int)$autoUser['id'], $autoUser['username'], $autoUser['role'], (int)$autoUser['club_id']);
            ActivityLogger::log($pdo, (int)$autoUser['club_id'], (int)$autoUser['id'], $autoUser['username'], $autoUser['role'], 'AUTO_LOGIN', "Login otomatis via token Ingat Saya");

            header('Location: app_dashboard.php');
            exit;
        }
    }
}

// 1-Click Instant Demo Login (HANYA untuk Tenant Sandbox Demo ID: 3)
if ($tenantId === 3 && isset($_GET['auto']) && !Auth::check()) {
    $autoRole = $_GET['auto'];
    $demoUsername = match($autoRole) {
        'coach' => 'demo_coach',
        'admin' => 'demo_admin',
        'parent' => 'demo_parent',
        default => null
    };

    if ($demoUsername) {
        $stmtAuto = $pdo->prepare("SELECT * FROM archery_users WHERE username = :u AND club_id = 3 AND is_active = 1 LIMIT 1");
        $stmtAuto->execute([':u' => $demoUsername]);
        $autoUser = $stmtAuto->fetch();
        if ($autoUser) {
            $_SESSION['user_id'] = $autoUser['id'];
            $_SESSION['username'] = $autoUser['username'];
            $_SESSION['full_name'] = $autoUser['full_name'];
            $_SESSION['role'] = $autoUser['role'];
            $_SESSION['phone'] = $autoUser['phone'];
            $_SESSION['email'] = $autoUser['email'] ?? '';
            $_SESSION['club_id'] = 3;
            $_SESSION['avatar_url'] = $autoUser['avatar_url'] ?? null;
            ActivityLogger::recordSession($pdo, (int)$autoUser['id'], $autoUser['username'], $autoUser['role'], 3);
            ActivityLogger::log($pdo, 3, (int)$autoUser['id'], $autoUser['username'], $autoUser['role'], 'DEMO_LOGIN', "1-Click auto login sandbox demo sebagai {$autoRole}");

            $redirectTarget = ($autoUser['role'] === 'coach') ? 'coach_dashboard.php' : (($autoUser['role'] === 'super_admin') ? 'cms_admin.php' : 'app_dashboard.php');
            header("Location: {$redirectTarget}");
            exit;
        }
    } elseif ($autoRole === 'pin') {
        $stmtPin = $pdo->prepare("SELECT * FROM archery_archers WHERE access_pin = '1234' AND club_id = 3 LIMIT 1");
        $stmtPin->execute();
        $archer = $stmtPin->fetch();
        if ($archer) {
            $_SESSION['user_id'] = 0;
            $_SESSION['archer_id'] = $archer['id'];
            $_SESSION['full_name'] = $archer['full_name'];
            $_SESSION['role'] = 'archer';
            $_SESSION['club_id'] = 3;
            header("Location: app_dashboard.php");
            exit;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loginType = $_POST['login_type'] ?? 'user';

    // 1. Login Akun Normal (Ortu, Coach, Admin Klub)
    if ($loginType === 'user') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $rememberMe = !empty($_POST['remember_me']);

        if (!empty($username) && !empty($password)) {
            $stmt = $pdo->prepare("SELECT * FROM archery_users WHERE (username = :u OR phone = :p OR email = :e) AND is_active = 1 LIMIT 1");
            $stmt->execute([':u' => $username, ':p' => $username, ':e' => $username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                // Multi-Tenant Guard 1: Master Admin dilarang login di portal tenant klub biasa
                if ($user['role'] === 'master_admin') {
                    $error = "Akun Master Admin Platform wajib login melalui gerbang otentikasi khusus Master Admin.";
                    ActivityLogger::log($pdo, (int)$user['club_id'], $user['id'], $user['username'], $user['role'], 'LOGIN_REJECTED', "Mencoba login di portal klub tenant {$clubName}");
                    goto end_login_handler;
                }

                // Multi-Tenant Guard 2: Cegah mutlak login lintas tenant / beda sekolah
                if ((int)$user['club_id'] !== $tenantId) {
                    $userClubStmt = $pdo->prepare("SELECT club_name, subdomain_slug, custom_domain FROM archery_clubs WHERE id = :cid LIMIT 1");
                    $userClubStmt->execute([':cid' => $user['club_id']]);
                    $userClub = $userClubStmt->fetch();
                    $correctDomain = $userClub ? TenantResolver::getBaseUrl($userClub) . '/login.php' : 'login.php';

                    $error = "Akses Ditolak: Akun Anda terdaftar di <b>" . htmlspecialchars($userClub['club_name'] ?? 'Klub Lain') . "</b>. Anda tidak diizinkan masuk ke portal <b>" . htmlspecialchars($clubName) . "</b>.<br><a href='{$correctDomain}' class='btn btn-warning btn-sm mt-2 fw-bold text-dark'>Buka Portal " . htmlspecialchars($userClub['club_name'] ?? 'Klub Anda') . "</a>";
                    ActivityLogger::log($pdo, $tenantId, $user['id'], $user['username'], $user['role'], 'CROSS_TENANT_REJECTED', "Mencoba login ke tenant {$clubName} (ID: {$tenantId}), padahal terdaftar di tenant ID: {$user['club_id']}");
                    goto end_login_handler;
                }

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['phone'] = $user['phone'];
                $_SESSION['email'] = $user['email'] ?? '';
                $_SESSION['club_id'] = (int)$user['club_id'];
                $_SESSION['avatar_url'] = $user['avatar_url'] ?? null;

                // Handle "Ingat Saya" Token & Durasi Cache Sesi
                if ($rememberMe) {
                    $rememberDays = (int)($currentTenant['session_remember_days'] ?? 7);
                    if ($rememberDays <= 0) $rememberDays = 7;
                    $expireTime = time() + ($rememberDays * 86400);
                    $tokenRaw = bin2hex(random_bytes(32));
                    $tokenHash = hash('sha256', $tokenRaw);

                    $stmtTok = $pdo->prepare("UPDATE archery_users SET remember_token = :tok, remember_expires_at = :exp WHERE id = :uid");
                    $stmtTok->execute([
                        ':tok' => $tokenHash,
                        ':exp' => date('Y-m-d H:i:s', $expireTime),
                        ':uid' => $user['id']
                    ]);

                    setcookie('archery_remember', $tokenRaw . ':' . $user['id'], [
                        'expires' => $expireTime,
                        'path' => '/',
                        'httponly' => true,
                        'samesite' => 'Lax'
                    ]);
                }

                // Catat sesi login aktif & audit trail
                ActivityLogger::recordSession($pdo, (int)$user['id'], $user['username'], $user['role'], (int)$user['club_id']);
                ActivityLogger::log($pdo, (int)$user['club_id'], (int)$user['id'], $user['username'], $user['role'], 'LOGIN', "Berhasil login ke portal {$clubName}");

                // Set anak aktif pertama jika orang tua
                $children = Auth::getChildren($pdo);
                if (!empty($children)) {
                    $_SESSION['active_child_id'] = $children[0]['id'];
                }

                // Redirect sesuai role secara aman
                if ($user['role'] === 'super_admin') {
                    header('Location: app_dashboard.php');
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

    // 2. Login Perangkat Lapangan Atlet (Dynamic 5-Hour Field PIN & Legacy PIN)
    if ($loginType === 'pin') {
        $pin = trim($_POST['access_pin'] ?? '');
        $cleanPin = preg_replace('/[^0-9]/', '', $pin);
        if (!empty($cleanPin)) {
            $stmt = $pdo->prepare("
                SELECT * FROM archery_archers 
                WHERE ( (temp_field_pin = :p1 AND temp_field_pin_expires_at > NOW()) OR access_pin = :p2 )
                  AND club_id = :cid
                  AND is_active = 1 
                LIMIT 1
            ");
            $stmt->execute([':p1' => $cleanPin, ':p2' => $cleanPin, ':cid' => $tenantId]);
            $archer = $stmt->fetch();

            if ($archer) {
                $_SESSION['user_id'] = 0;
                $_SESSION['archer_device_id'] = (int)$archer['id'];
                $_SESSION['role'] = 'archer';
                $_SESSION['is_field_pin'] = true;
                $_SESSION['pin_expires_at'] = !empty($archer['temp_field_pin_expires_at']) ? strtotime($archer['temp_field_pin_expires_at']) : (time() + 18000);
                $_SESSION['full_name'] = $archer['full_name'];
                $_SESSION['active_child_id'] = (int)$archer['id'];
                $_SESSION['archer_id'] = (int)$archer['id'];
                $_SESSION['club_id'] = (int)$archer['club_id'];

                ActivityLogger::recordSession($pdo, (int)$archer['id'], $archer['nis'], 'archer', (int)$archer['club_id']);
                ActivityLogger::log($pdo, (int)$archer['club_id'], (int)$archer['id'], $archer['full_name'], 'archer', 'PIN_LOGIN', "Login perangkat scoring atlet via PIN");

                header('Location: plotter.php');
                exit;
            } else {
                $error = 'PIN Akses Lapangan tidak sesuai atau telah kadaluarsa (melewati batas 5 jam). Minta orang tua untuk menerbitkan PIN baru.';
            }
        } else {
            $error = 'Silakan masukkan 6-digit PIN anak.';
        }
    }
}
end_login_handler:
?>
<!DOCTYPE html>
<html lang="id" data-theme="dark" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= htmlspecialchars($clubName) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
    <script>
        (function() {
            const savedTheme = localStorage.getItem('archery_theme') || 'dark';
            document.documentElement.setAttribute('data-theme', savedTheme);
            document.documentElement.setAttribute('data-bs-theme', savedTheme);
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
            <?php if (!empty($clubLogo) && $clubLogo !== 'assets/img/default_logo.png'): ?>
                <img src="<?= htmlspecialchars($clubLogo) ?>" alt="Logo" style="height: 60px; max-width: 120px; object-fit: contain; margin-bottom: 0.75rem;">
            <?php else: ?>
                <div style="width: 58px; height: 58px; margin: 0 auto 0.75rem; border-radius: 50%; background: conic-gradient(from 0deg, var(--ring-gold), var(--ring-red), var(--ring-blue), var(--ring-gold)); display: flex; align-items: center; justify-content: center; box-shadow: var(--shadow-glow);">
                    <span style="font-size: 26px;">🎯</span>
                </div>
            <?php endif; ?>
            <h1 style="font-size: 1.5rem; margin-bottom: 0.2rem;"><?= htmlspecialchars($clubName) ?></h1>
            <p style="color: var(--text-muted); font-size: 0.85rem;">Portal Skoring Panahan &amp; Monitoring Atlet</p>
        </div>

        <?php if ($tenantId === 3): ?>
            <div style="background: rgba(245, 158, 11, 0.12); border: 1px solid rgba(245, 158, 11, 0.35); border-radius: var(--radius-sm); padding: 0.85rem; margin-bottom: 1.25rem; text-align: left;">
                <div style="font-weight: 700; color: #fbbf24; font-size: 0.8rem; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 6px;">
                    <span>⚡</span> <span>AKUN SIMULASI DEMO 1-KLIK (SANDBOX)</span>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.4rem; font-size: 0.75rem;">
                    <a href="login.php?club=demo&auto=coach" class="btn btn-warning btn-sm fw-bold text-dark text-nowrap" style="padding: 4px 8px; font-size: 0.72rem;">
                        🏹 sbg Pelatih (Coach)
                    </a>
                    <a href="login.php?club=demo&auto=admin" class="btn btn-outline-info btn-sm fw-bold text-nowrap" style="padding: 4px 8px; font-size: 0.72rem;">
                        🛡️ sbg Super Admin
                    </a>
                    <a href="login.php?club=demo&auto=parent" class="btn btn-outline-light btn-sm fw-bold text-nowrap" style="padding: 4px 8px; font-size: 0.72rem;">
                        👨‍👩‍👧‍👦 sbg Wali / Ortu
                    </a>
                    <a href="login.php?club=demo&auto=pin" class="btn btn-outline-success btn-sm fw-bold text-nowrap" style="padding: 4px 8px; font-size: 0.72rem;">
                        📱 PIN Anak (1234)
                    </a>
                </div>
                <div style="margin-top: 0.5rem; font-size: 0.7rem; color: var(--text-dim);">
                    *Atau login manual: username <code class="text-warning">demo_coach</code> / <code class="text-warning">demo_admin</code> / <code class="text-warning">demo_parent</code> & password <code class="text-light">demo123</code>
                </div>
            </div>
        <?php endif; ?>

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
                <input type="text" name="username" class="form-control" id="userInput" placeholder="Masukkan username atau no. WA" required>
            </div>
            <div class="form-group">
                <label class="form-label">Kata Sandi (Password)</label>
                <input type="password" name="password" class="form-control" id="passInput" placeholder="••••••••" required>
            </div>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; margin: 0; font-size: 0.82rem; color: var(--text-muted);">
                    <input type="checkbox" name="remember_me" value="1" checked style="width: 16px; height: 16px; accent-color: var(--primary);">
                    <span>Ingat saya di perangkat ini</span>
                </label>
                <span style="font-size: 0.72rem; color: var(--text-dim); background: rgba(255,255,255,0.05); padding: 2px 8px; border-radius: 999px;">
                    Sesi: <?= (int)($currentTenant['session_remember_days'] ?? 7) ?> Hari
                </span>
            </div>
            <button type="submit" class="btn btn-primary btn-block">
                Masuk ke Dashboard ➔
            </button>
        </form>

        <!-- FORM 2: DEVICE LAPANGAN (PIN MANDIRI ANAK) -->
        <form method="POST" id="formPin" style="display: none;">
            <input type="hidden" name="login_type" value="pin">
            <div class="form-group">
                <label class="form-label">6-Digit PIN Akses Atlet (Anak)</label>
                <input type="password" name="access_pin" class="form-control" id="pinInput" placeholder="••••••" maxlength="6" style="letter-spacing: 0.5rem; text-align: center; font-size: 1.35rem; font-weight: 800;" required>
                <small style="display: block; color: var(--text-dim); margin-top: 0.5rem; font-size: 0.75rem;">
                    💡 Anak dapat memasukkan PIN di smartphone masing-masing untuk langsung membuka papan bidik tanpa melihat menu keuangan orang tua.
                </small>
            </div>
            <button type="submit" class="btn btn-primary btn-block" style="margin-top: 1rem;">
                Buka Papan Bidik Lapangan 🎯
            </button>
        </form>

        <!-- INFO BANTUAN OPERASIONAL RESMI KLUB -->
        <div style="margin-top: 1.5rem; padding: 0.85rem; border-radius: var(--radius-sm); background: rgba(255, 255, 255, 0.03); border: 1px solid var(--border-color); font-size: 0.78rem; text-align: center;">
            <span class="text-muted"><i class="bi bi-shield-lock-fill text-info"></i> Portal Terautentikasi Standar World Archery</span><br>
            <span class="text-muted" style="font-size: 0.72rem;">Mengalami kendala akun? Hubungi Admin Perkumpulan:</span>
            <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $adminWa) ?>?text=Halo%20Admin%20<?= urlencode($clubName) ?>,%20saya%20mengalami%20kendala%20saat%20login%20ke%20sistem" target="_blank" class="text-success fw-bold text-decoration-none d-block mt-1">
                <i class="bi bi-whatsapp"></i> Bantuan WhatsApp Admin (<?= htmlspecialchars($adminWa) ?>)
            </a>
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
<div class="modal-backdrop" id="errorLoginModal" style="<?= !empty($error) ? 'display: flex;' : 'display: none;' ?>">
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
    document.documentElement.setAttribute('data-bs-theme', next);
    localStorage.setItem('archery_theme', next);
}
</script>

</body>
</html>
