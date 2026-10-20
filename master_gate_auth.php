<?php
// master_gate_auth.php - Gerbang Otentikasi Rahasia Khusus Master Admin SaaS Platform
// Zero leakage untuk tenant umum: Akun biasa (Super Admin, Coach, Ortu) 100% ditolak di gerbang ini
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/activity_logger.php';

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    session_start();
}

// Jika sudah login sebagai master_admin, langsung lempar ke dashboard master
if (isset($_SESSION['role']) && $_SESSION['role'] === 'master_admin') {
    header('Location: master_tenants.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Anti-brute force slight delay
    usleep(250000);

    $username = trim($_POST['master_username'] ?? '');
    $password = $_POST['master_password'] ?? '';
    $securityKey = trim($_POST['security_pin'] ?? '');

    if (!empty($username) && !empty($password)) {
        $stmt = $pdo->prepare("SELECT * FROM archery_users WHERE (username = :u OR email = :e) AND is_active = 1 LIMIT 1");
        $stmt->execute([':u' => $username, ':e' => $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            // STRICT GATE GUARD: HANYA role master_admin yang diizinkan lewat
            if ($user['role'] !== 'master_admin') {
                $error = "AKSES DITOLAK: Kredensial ini terdaftar sebagai <b>" . strtoupper(str_replace('_', ' ', $user['role'])) . "</b>. Gerbang ini dikunci khusus untuk Platform Owner (Master Admin Codika). Silakan login di portal resmi klub Anda.";
                ActivityLogger::log($pdo, (int)$user['club_id'], (int)$user['id'], $user['username'], $user['role'], 'MASTER_GATE_BREACH_ATTEMPT', "Percobaan login ditolak ke Master Gate Auth");
            } else {
                // Login Berhasil
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = 'master_admin';
                $_SESSION['club_id'] = (int)$user['club_id'];
                $_SESSION['email'] = $user['email'] ?? '';
                $_SESSION['avatar_url'] = $user['avatar_url'] ?? null;

                // Catat sesi login aktif & audit trail
                ActivityLogger::recordSession($pdo, (int)$user['id'], $user['username'], 'master_admin', (int)$user['club_id']);
                ActivityLogger::log($pdo, (int)$user['club_id'], (int)$user['id'], $user['username'], 'master_admin', 'MASTER_LOGIN', "Master Admin berhasil login ke kontrol platform SaaS");

                header('Location: master_tenants.php');
                exit;
            }
        } else {
            $error = "Kredensial otentikasi Master Admin tidak valid atau dinonaktifkan.";
            ActivityLogger::log($pdo, 1, null, $username, 'unknown', 'MASTER_LOGIN_FAILED', "Percobaan gagal otentikasi Master Gate dari IP: " . ActivityLogger::getClientIp());
        }
    } else {
        $error = "Harap isi seluruh kredensial otentikasi.";
    }
}
?>
<!DOCTYPE html>
<html lang="id" data-theme="dark" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Master Admin Gate &bull; Codika Archery SaaS Platform</title>
    <link rel="icon" type="image/x-icon" href="assets/img/default_favicon.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            background: radial-gradient(circle at 50% 20%, rgba(2, 132, 199, 0.15) 0%, rgba(11, 15, 25, 0.98) 75%),
                        linear-gradient(180deg, #050811 0%, #0b0f19 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        }
        .master-gate-card {
            max-width: 460px;
            width: 100%;
            background: rgba(18, 24, 38, 0.85);
            border: 1px solid rgba(245, 158, 11, 0.35);
            border-radius: 16px;
            padding: 2.5rem 2rem;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.7), 0 0 30px rgba(245, 158, 11, 0.12);
            backdrop-filter: blur(20px);
            position: relative;
            overflow: hidden;
        }
        .master-gate-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #f59e0b, #0284c7, #f59e0b);
        }
        .gate-badge {
            background: rgba(245, 158, 11, 0.15);
            color: #fbbf24;
            border: 1px solid rgba(245, 158, 11, 0.3);
            font-size: 0.75rem;
            padding: 4px 12px;
            border-radius: 9999px;
            font-weight: 700;
            letter-spacing: 0.05em;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .form-control-master {
            background: rgba(11, 15, 25, 0.75);
            border: 1px solid rgba(255, 255, 255, 0.12);
            color: #f8fafc;
            border-radius: 8px;
            padding: 0.75rem 1rem;
        }
        .form-control-master:focus {
            background: rgba(15, 23, 42, 0.95);
            border-color: #f59e0b;
            box-shadow: 0 0 0 0.25rem rgba(245, 158, 11, 0.25);
            color: #fff;
        }
    </style>
</head>
<body>

<div class="master-gate-card">
    <div class="text-center mb-4">
        <div class="gate-badge mb-3">
            <i class="bi bi-shield-lock-fill"></i> RESTRICTED ACCESS &bull; MASTER PLATFORM
        </div>
        <div class="rounded-circle bg-dark text-warning mx-auto mb-3 d-flex align-items-center justify-content-center shadow" style="width: 64px; height: 64px; font-size: 30px; border: 2px solid rgba(245, 158, 11, 0.5);">
            👑
        </div>
        <h4 class="fw-extrabold text-white mb-1" style="letter-spacing: -0.02em;">Master Admin Gate</h4>
        <p class="text-muted small mb-0">Pusat Manajemen Multi-Tenant SaaS Codika Archery</p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger p-3 mb-4 rounded-3 small border-0" style="background: rgba(239, 68, 68, 0.15); color: #fca5a5; border-left: 4px solid #ef4444 !important;">
            <div class="d-flex align-items-start gap-2">
                <i class="bi bi-exclamation-triangle-fill fs-5 flex-shrink-0"></i>
                <div><?= $error ?></div>
            </div>
        </div>
    <?php endif; ?>

    <form method="POST" autocomplete="off">
        <div class="mb-3">
            <label class="form-label text-light small fw-bold">Master Username / Email</label>
            <div class="input-group">
                <span class="input-group-text bg-dark border-secondary border-opacity-25 text-muted"><i class="bi bi-person-fill"></i></span>
                <input type="text" name="master_username" class="form-control form-control-master" placeholder="masteradmin" required autofocus>
            </div>
        </div>

        <div class="mb-4">
            <label class="form-label text-light small fw-bold">Kata Sandi Master</label>
            <div class="input-group">
                <span class="input-group-text bg-dark border-secondary border-opacity-25 text-muted"><i class="bi bi-key-fill"></i></span>
                <input type="password" name="master_password" class="form-control form-control-master" placeholder="••••••••" required>
            </div>
        </div>

        <button type="submit" class="btn btn-warning w-100 fw-bold py-2 shadow d-flex align-items-center justify-content-center gap-2 text-dark">
            <i class="bi bi-unlock-fill"></i> Otentikasi Master Admin
        </button>
    </form>

    <div class="text-center mt-4 pt-3 border-top border-secondary border-opacity-25">
        <div class="text-muted" style="font-size: 11px;">
            <i class="bi bi-geo-alt"></i> IP Terdeteksi: <strong><?= htmlspecialchars(ActivityLogger::getClientIp()) ?></strong> &bull; Seluruh aktivitas diaudit.
        </div>
        <div class="mt-2">
            <a href="index.php" class="text-muted text-decoration-none small" style="font-size: 11px;">
                &larr; Kembali ke Website
            </a>
        </div>
    </div>
</div>

</body>
</html>
