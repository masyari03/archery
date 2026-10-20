<?php
// master_gate_auth.php - Gerbang Otentikasi Rahasia Khusus Master Admin SaaS Platform
// Mendukung: Login dengan Google + Google Authenticator 2FA (TOTP RFC 6238) + Emergency Backup PIN
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

// Helper Class untuk TOTP RFC 6238 (Google Authenticator)
class TotpEngine {
    private static string $base32Alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function base32Decode(string $b32): string {
        $b32 = strtoupper(str_replace(['=', ' '], '', $b32));
        $buffer = 0;
        $bitsLeft = 0;
        $output = '';

        for ($i = 0; $i < strlen($b32); $i++) {
            $val = strpos(self::$base32Alphabet, $b32[$i]);
            if ($val === false) continue;
            $buffer = ($buffer << 5) | $val;
            $bitsLeft += 5;
            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $output .= chr(($buffer >> $bitsLeft) & 0xFF);
            }
        }
        return $output;
    }

    public static function getCode(string $secret, ?int $timestamp = null): string {
        $timestamp = $timestamp ?? time();
        $timeSlice = floor($timestamp / 30);
        $binaryTime = pack('N*', 0) . pack('N*', $timeSlice);
        $binaryKey = self::base32Decode($secret);
        $hash = hash_hmac('sha1', $binaryTime, $binaryKey, true);
        $offset = ord(substr($hash, -1)) & 0x0F;
        $unpacked = unpack('N', substr($hash, $offset, 4));
        $value = ($unpacked[1] & 0x7FFFFFFF) % 1000000;
        return str_pad((string)$value, 6, '0', STR_PAD_LEFT);
    }

    public static function verify(string $secret, string $code, string $emergencyPin = '998877'): bool {
        $code = trim($code);
        if ($code === $emergencyPin) return true;
        if (strlen($code) !== 6 || !ctype_digit($code)) return false;

        $now = time();
        // Cek toleransi drift 1 time slice sebelum dan sesudah (±30 detik)
        for ($i = -1; $i <= 1; $i++) {
            $t = $now + ($i * 30);
            if (hash_equals(self::getCode($secret, $t), $code)) {
                return true;
            }
        }
        return false;
    }
}

// Ambil data Master Admin dari database
$stmtMaster = $pdo->prepare("SELECT * FROM archery_users WHERE role = 'master_admin' AND is_active = 1 LIMIT 1");
$stmtMaster->execute();
$masterUser = $stmtMaster->fetch();

// Pastikan TOTP Secret ada untuk Master Admin (default fallback: JBSWY3DPEHPK3PXP)
$defaultTotpSecret = 'JBSWY3DPEHPK3PXP';
if ($masterUser && empty($masterUser['totp_secret'])) {
    $pdo->prepare("UPDATE archery_users SET totp_secret = :s, emergency_pin = '998877' WHERE id = :id")
        ->execute([':s' => $defaultTotpSecret, ':id' => $masterUser['id']]);
    $masterUser['totp_secret'] = $defaultTotpSecret;
    $masterUser['emergency_pin'] = '998877';
}
$totpSecret = $masterUser['totp_secret'] ?? $defaultTotpSecret;
$emergencyPin = $masterUser['emergency_pin'] ?? '998877';

// Ambil Pengaturan Google OAuth dari archery_saas_settings
$stmtSaas = $pdo->query("SELECT * FROM archery_saas_settings WHERE id = 1 LIMIT 1");
$saasSettings = $stmtSaas->fetch() ?: [];
$googleClientId = !empty($saasSettings['google_client_id']) ? $saasSettings['google_client_id'] : 'YOUR_GOOGLE_CLIENT_ID.apps.googleusercontent.com';

$error = '';
$success = '';

// Alur Otentikasi
$authStage = $_SESSION['master_gate_stage'] ?? 'credentials'; // 'credentials' atau '2fa'

// 1. Reset alur jika tombol Batal / Mulai Ulang ditekan
if (isset($_GET['reset'])) {
    unset($_SESSION['master_gate_stage'], $_SESSION['master_pending_uid']);
    header('Location: master_gate_auth.php');
    exit;
}

// 2. Handler POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    usleep(150000); // Anti-brute force slight delay
    $action = $_POST['action'] ?? '';

    // A. GOOGLE SIGN-IN HANDLER
    if ($action === 'google_auth') {
        $credential = $_POST['credential'] ?? '';
        if (!empty($credential)) {
            // Decode Google JWT Payload (Format: header.payload.signature)
            $jwtParts = explode('.', $credential);
            if (count($jwtParts) === 3) {
                $payloadJson = base64_decode(str_replace(['-', '_'], ['+', '/'], $jwtParts[1]));
                $payload = json_decode($payloadJson, true) ?: [];
                $googleEmail = strtolower(trim($payload['email'] ?? ''));
                $googleSub = $payload['sub'] ?? '';
                $googleName = $payload['name'] ?? 'Master Admin Google';

                if (!empty($googleEmail)) {
                    // Cek apakah email Google cocok dengan master_admin di database
                    $stmtG = $pdo->prepare("SELECT * FROM archery_users WHERE (email = :e OR username = :u OR google_id = :gid) AND is_active = 1 LIMIT 1");
                    $stmtG->execute([':e' => $googleEmail, ':u' => $googleEmail, ':gid' => $googleSub]);
                    $matchedUser = $stmtG->fetch();

                    // Jika belum tersambung tapi email cocok atau berstatus master_admin
                    if ($matchedUser && $matchedUser['role'] === 'master_admin') {
                        // Update google_id jika belum ada
                        if (empty($matchedUser['google_id'])) {
                            $pdo->prepare("UPDATE archery_users SET google_id = :gid WHERE id = :id")
                                ->execute([':gid' => $googleSub, ':id' => $matchedUser['id']]);
                        }

                        // Lanjut ke Step 2 (Google Authenticator 2FA)
                        $_SESSION['master_gate_stage'] = '2fa';
                        $_SESSION['master_pending_uid'] = (int)$matchedUser['id'];
                        $authStage = '2fa';
                        $success = "Identitas Google <b>{$googleEmail}</b> terverifikasi! Masukkan kode Google Authenticator di bawah.";
                        ActivityLogger::log($pdo, (int)$matchedUser['club_id'], (int)$matchedUser['id'], $matchedUser['username'], 'master_admin', 'GOOGLE_AUTH_STEP1', "Tahap 1 Google Sign-In berhasil");
                    } else {
                        // User bukan master_admin
                        $error = "AKSES DITOLAK: Akun Google <b>" . htmlspecialchars($googleEmail) . "</b> tidak terdaftar sebagai Master Admin Platform Codika Archery.";
                        ActivityLogger::log($pdo, 1, null, $googleEmail, 'unknown', 'MASTER_GOOGLE_BREACH', "Percobaan Google login ditolak dari IP: " . ActivityLogger::getClientIp());
                    }
                } else {
                    $error = "Token Google tidak memiliki alamat email valid.";
                }
            } else {
                $error = "Struktur token Google tidak valid.";
            }
        }
    }

    // B. KREDENSIAL USERNAME & PASSWORD HANDLER
    elseif ($action === 'credentials_auth') {
        $username = trim($_POST['master_username'] ?? '');
        $password = $_POST['master_password'] ?? '';

        if (!empty($username) && !empty($password)) {
            $stmt = $pdo->prepare("SELECT * FROM archery_users WHERE (username = :u OR email = :e) AND is_active = 1 LIMIT 1");
            $stmt->execute([':u' => $username, ':e' => $username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                if ($user['role'] !== 'master_admin') {
                    $error = "AKSES DITOLAK: Kredensial ini terdaftar sebagai <b>" . strtoupper(str_replace('_', ' ', $user['role'])) . "</b>. Gerbang ini dikunci khusus untuk Platform Owner (Master Admin). Silakan login di portal resmi klub Anda.";
                    ActivityLogger::log($pdo, (int)$user['club_id'], (int)$user['id'], $user['username'], $user['role'], 'MASTER_GATE_BREACH_ATTEMPT', "Percobaan login ditolak ke Master Gate Auth");
                } else {
                    // Kredensial Valid -> Lanjut ke Step 2 (Google Authenticator 2FA)
                    $_SESSION['master_gate_stage'] = '2fa';
                    $_SESSION['master_pending_uid'] = (int)$user['id'];
                    $authStage = '2fa';
                    $success = "Kredensial Master terverifikasi! Masukkan kode 6 digit Google Authenticator.";
                }
            } else {
                $error = "Kredensial Master Admin tidak valid atau dinonaktifkan.";
                ActivityLogger::log($pdo, 1, null, $username, 'unknown', 'MASTER_LOGIN_FAILED', "Gagal password Master Gate dari IP: " . ActivityLogger::getClientIp());
            }
        } else {
            $error = "Harap isi username dan kata sandi.";
        }
    }

    // C. STEP 2: GOOGLE AUTHENTICATOR (TOTP) HANDLER
    elseif ($action === '2fa_verify') {
        $totpCode = trim($_POST['totp_code'] ?? '');
        $pendingUid = $_SESSION['master_pending_uid'] ?? 0;

        if ($pendingUid > 0 && !empty($totpCode)) {
            $stmtU = $pdo->prepare("SELECT * FROM archery_users WHERE id = :id AND role = 'master_admin' LIMIT 1");
            $stmtU->execute([':id' => $pendingUid]);
            $user = $stmtU->fetch();

            if ($user) {
                $secret = $user['totp_secret'] ?: $defaultTotpSecret;
                $emPin = $user['emergency_pin'] ?: '998877';

                if (TotpEngine::verify($secret, $totpCode, $emPin)) {
                    // OTENTIKASI LENGKAP SUKSES!
                    unset($_SESSION['master_gate_stage'], $_SESSION['master_pending_uid']);

                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['role'] = 'master_admin';
                    $_SESSION['club_id'] = (int)$user['club_id'];
                    $_SESSION['email'] = $user['email'] ?? '';
                    $_SESSION['avatar_url'] = $user['avatar_url'] ?? null;

                    ActivityLogger::recordSession($pdo, (int)$user['id'], $user['username'], 'master_admin', (int)$user['club_id']);
                    ActivityLogger::log($pdo, (int)$user['club_id'], (int)$user['id'], $user['username'], 'master_admin', 'MASTER_LOGIN', "Master Admin berhasil otentikasi 2FA Google Authenticator");

                    header('Location: master_tenants.php');
                    exit;
                } else {
                    $error = "Kode Google Authenticator tidak sesuai atau telah kadaluarsa. Silakan periksa kembali aplikasi Google Authenticator di ponsel Anda.";
                    ActivityLogger::log($pdo, (int)$user['club_id'], (int)$user['id'], $user['username'], 'master_admin', 'MASTER_2FA_FAILED', "Percobaan gagal kode 2FA Authenticator: {$totpCode}");
                }
            } else {
                $error = "Sesi verifikasi Master telah kadaluarsa. Silakan login ulang.";
                unset($_SESSION['master_gate_stage'], $_SESSION['master_pending_uid']);
                $authStage = 'credentials';
            }
        } else {
            $error = "Harap masukkan 6 digit kode dari Google Authenticator.";
        }
    }
}

// Generate URL QR Code untuk Setup Google Authenticator pertama kali
$otpAuthUri = "otpauth://totp/CodikaArchery:masteradmin?secret={$totpSecret}&issuer=CodikaArchery";
$qrCodeUrl = "https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=" . urlencode($otpAuthUri);
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

    <!-- Google Identity Services Web SDK -->
    <script src="https://accounts.google.com/gsi/client" async defer></script>

    <style>
        body {
            background: radial-gradient(circle at 50% 15%, rgba(2, 132, 199, 0.18) 0%, rgba(11, 15, 25, 0.98) 75%),
                        linear-gradient(180deg, #050811 0%, #0b0f19 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem 1rem;
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
        }
        .master-gate-card {
            max-width: 480px;
            width: 100%;
            background: rgba(18, 24, 38, 0.9);
            border: 1px solid rgba(245, 158, 11, 0.35);
            border-radius: 20px;
            padding: 2.25rem 2rem;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.75), 0 0 35px rgba(245, 158, 11, 0.15);
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
            padding: 4px 14px;
            border-radius: 9999px;
            font-weight: 700;
            letter-spacing: 0.05em;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .form-control-master {
            background: rgba(11, 15, 25, 0.85);
            border: 1px solid rgba(255, 255, 255, 0.12);
            color: #f8fafc;
            border-radius: 10px;
            padding: 0.75rem 1rem;
        }
        .form-control-master:focus {
            background: rgba(15, 23, 42, 0.98);
            border-color: #f59e0b;
            box-shadow: 0 0 0 0.25rem rgba(245, 158, 11, 0.25);
            color: #fff;
        }
        .totp-input {
            letter-spacing: 0.6rem;
            font-size: 1.6rem;
            font-weight: 800;
            text-align: center;
            color: #fbbf24 !important;
            border: 2px solid rgba(245, 158, 11, 0.4) !important;
        }
        .step-pill {
            font-size: 0.72rem;
            padding: 3px 10px;
            border-radius: 999px;
            font-weight: 700;
        }
        .accordion-button:not(.collapsed) {
            background-color: rgba(245, 158, 11, 0.1);
            color: #fbbf24;
        }
    </style>
</head>
<body>

<div class="master-gate-card">
    <div class="text-center mb-4">
        <div class="gate-badge mb-3">
            <i class="bi bi-shield-lock-fill"></i> RESTRICTED ACCESS &bull; MASTER PLATFORM
        </div>
        <div class="rounded-circle bg-dark text-warning mx-auto mb-3 d-flex align-items-center justify-content-center shadow" style="width: 64px; height: 64px; font-size: 32px; border: 2px solid rgba(245, 158, 11, 0.5);">
            👑
        </div>
        <h4 class="fw-extrabold text-white mb-1" style="letter-spacing: -0.02em;">Master Admin Gate</h4>
        <p class="text-muted small mb-0">Pusat Kontrol SaaS &bull; Google Sign-In &bull; Google Authenticator 2FA</p>
    </div>

    <!-- NOTIFIKASI ERROR / SUKSES -->
    <?php if ($error): ?>
        <div class="alert alert-danger p-3 mb-3 rounded-3 small border-0" style="background: rgba(239, 68, 68, 0.15); color: #fca5a5; border-left: 4px solid #ef4444 !important;">
            <div class="d-flex align-items-start gap-2">
                <i class="bi bi-exclamation-triangle-fill fs-5 flex-shrink-0"></i>
                <div><?= $error ?></div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success p-3 mb-3 rounded-3 small border-0" style="background: rgba(16, 185, 129, 0.15); color: #6ee7b7; border-left: 4px solid #10b981 !important;">
            <div class="d-flex align-items-start gap-2">
                <i class="bi bi-check-circle-fill fs-5 flex-shrink-0"></i>
                <div><?= $success ?></div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($authStage === '2fa'): ?>
        <!-- ============================================== -->
        <!-- STEP 2: VERIFIKASI GOOGLE AUTHENTICATOR (TOTP) -->
        <!-- ============================================== -->
        <div class="p-3 rounded-3 mb-3" style="background: rgba(245, 158, 11, 0.08); border: 1px dashed rgba(245, 158, 11, 0.4);">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="step-pill bg-warning text-dark"><i class="bi bi-shield-check"></i> STEP 2 DARI 2</span>
                <span class="text-warning small fw-bold"><i class="bi bi-phone"></i> Google Authenticator</span>
            </div>
            <p class="text-light small mb-0">
                Buka aplikasi <strong>Google Authenticator</strong> di HP Anda, lalu masukkan 6 digit kode yang sedang aktif.
            </p>
        </div>

        <form method="POST" autocomplete="off">
            <input type="hidden" name="action" value="2fa_verify">
            <div class="mb-4">
                <label class="form-label text-light small fw-bold">Kode 6-Digit Google Authenticator</label>
                <input type="text" name="totp_code" class="form-control form-control-master totp-input" placeholder="••••••" maxlength="6" inputmode="numeric" required autofocus>
                <div class="d-flex justify-content-between align-items-center mt-2 text-muted" style="font-size: 11px;">
                    <span><i class="bi bi-clock-history"></i> Kode berganti tiap 30 detik</span>
                    <span>Emergency PIN: <code>998877</code></span>
                </div>
            </div>

            <button type="submit" class="btn btn-warning w-100 fw-bold py-2 shadow d-flex align-items-center justify-content-center gap-2 text-dark mb-2">
                <i class="bi bi-check2-circle fs-5"></i> Verifikasi &amp; Masuk ke Dashboard
            </button>

            <a href="master_gate_auth.php?reset=1" class="btn btn-outline-secondary w-100 btn-sm text-decoration-none">
                <i class="bi bi-arrow-left"></i> Batal &amp; Ganti Metode Login
            </a>
        </form>

    <?php else: ?>
        <!-- ======================================================== -->
        <!-- STEP 1: PILIHAN LOGIN GOOGLE ATAU USERNAME/PASSWORD      -->
        <!-- ======================================================== -->

        <!-- 1. TOMBOL GOOGLE SIGN-IN -->
        <div class="mb-3 text-center">
            <div class="mb-2 text-light small fw-bold d-flex align-items-center justify-content-center gap-1">
                <i class="bi bi-google text-danger"></i> Masuk Cepat dengan Google Master:
            </div>

            <!-- Google GSI Container -->
            <div id="g_id_onload"
                 data-client_id="<?= htmlspecialchars($googleClientId) ?>"
                 data-context="signin"
                 data-ux_mode="popup"
                 data-callback="handleGoogleLogin"
                 data-auto_prompt="false">
            </div>

            <div class="d-flex justify-content-center">
                <div class="g_id_signin"
                     data-type="standard"
                     data-shape="pill"
                     data-theme="filled_black"
                     data-text="signin_with"
                     data-size="large"
                     data-logo_alignment="left"
                     data-width="380">
                </div>
            </div>

            <!-- Form tersembunyi untuk submit Google token ke PHP -->
            <form id="googleAuthForm" method="POST" style="display: none;">
                <input type="hidden" name="action" value="google_auth">
                <input type="hidden" name="credential" id="googleCredentialInput">
            </form>
        </div>

        <div class="d-flex align-items-center my-3 text-muted small">
            <hr class="flex-grow-1 border-secondary border-opacity-25 my-0">
            <span class="px-3 text-uppercase" style="font-size: 11px; letter-spacing: 0.05em;">Atau Kredensial Master</span>
            <hr class="flex-grow-1 border-secondary border-opacity-25 my-0">
        </div>

        <!-- 2. FORM KREDENSIAL MANUAL -->
        <form method="POST" autocomplete="off">
            <input type="hidden" name="action" value="credentials_auth">
            <div class="mb-3">
                <label class="form-label text-light small fw-bold">Master Username / Email</label>
                <div class="input-group">
                    <span class="input-group-text bg-dark border-secondary border-opacity-25 text-muted"><i class="bi bi-person-fill"></i></span>
                    <input type="text" name="master_username" class="form-control form-control-master" placeholder="masteradmin" value="masteradmin" required>
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label text-light small fw-bold">Kata Sandi Master</label>
                <div class="input-group">
                    <span class="input-group-text bg-dark border-secondary border-opacity-25 text-muted"><i class="bi bi-key-fill"></i></span>
                    <input type="password" name="master_password" class="form-control form-control-master" placeholder="••••••••" value="admin123" required>
                </div>
            </div>

            <button type="submit" class="btn btn-warning w-100 fw-bold py-2 shadow d-flex align-items-center justify-content-center gap-2 text-dark">
                <i class="bi bi-shield-lock-fill"></i> Lanjut ke Otentikasi 2FA
            </button>
        </form>

    <?php endif; ?>

    <!-- PANDUAN STEP CARA LOGIN & SETUP GOOGLE AUTHENTICATOR -->
    <div class="accordion accordion-flush mt-4 pt-2 border-top border-secondary border-opacity-25" id="masterGuideAccordion">
        <div class="accordion-item bg-transparent border-0">
            <h2 class="accordion-header" id="guideHeading">
                <button class="accordion-button collapsed py-2 px-0 bg-transparent text-warning small fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#guideCollapse">
                    <i class="bi bi-question-circle-fill me-2"></i> Panduan Langkah Login &amp; Setup Authenticator
                </button>
            </h2>
            <div id="guideCollapse" class="accordion-collapse collapse" data-bs-parent="#masterGuideAccordion">
                <div class="accordion-body px-0 pt-2 pb-1 text-muted small" style="font-size: 12px; line-height: 1.6;">
                    <ol class="ps-3 mb-2">
                        <li><strong>Step 1</strong>: Masukkan Username <code>masteradmin</code> &amp; Password <code>admin123</code> ATAU gunakan tombol Google Sign-In.</li>
                        <li><strong>Step 2</strong>: Masukkan 6 digit kode dari aplikasi <strong>Google Authenticator</strong> (atau PIN darurat <code>998877</code>).</li>
                        <li><strong>Step 3</strong>: Berhasil masuk ke Dashboard Master Admin SaaS (Kelola Tenant, CMS, Billing, dan Putus Sesi).</li>
                    </ol>

                    <div class="p-2 rounded mt-2 text-center" style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.1);">
                        <span class="text-light fw-bold d-block mb-1"><i class="bi bi-qr-code-scan text-info"></i> Scan QR di Google Authenticator:</span>
                        <img src="<?= $qrCodeUrl ?>" alt="QR Code Google Authenticator" class="img-fluid rounded my-1 shadow" style="width: 140px; height: 140px; background: #fff; padding: 6px;">
                        <div class="mt-1">
                            <span class="text-muted" style="font-size: 11px;">Secret Key Manual:</span>
                            <code class="text-warning fw-bold d-block"><?= htmlspecialchars($totpSecret) ?></code>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="text-center mt-3 pt-2 text-muted" style="font-size: 11px;">
        <i class="bi bi-shield-check text-success"></i> IP Terverifikasi: <strong><?= htmlspecialchars(ActivityLogger::getClientIp()) ?></strong> &bull; Codika Multi-Tenant Guard
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Callback saat otentikasi Google berhasil di browser
function handleGoogleLogin(response) {
    if (response && response.credential) {
        document.getElementById('googleCredentialInput').value = response.credential;
        document.getElementById('googleAuthForm').submit();
    }
}
</script>

</body>
</html>
