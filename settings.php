<?php
// settings.php - Pengaturan Super Admin (Branding Klub, Warna, Fonnte WA Bot & Template Pesan)
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/includes/fonnte.php';
Auth::requireAuth();

$currentUser = Auth::user();
$currentRole = $_SESSION['role'] ?? 'parent';

// Proteksi RBAC: Hanya Super Admin
if ($currentRole !== 'super_admin') {
    die("Akses ditolak. Halaman pengaturan sistem hanya dapat diakses oleh Super Admin.");
}

$clubId = (int)($currentUser['club_id'] ?? 1);
$alertMsg = null;
$alertType = 'success';

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_branding') {
        $clubName = trim($_POST['club_name'] ?? '');
        $chairmanName = trim($_POST['chairman_name'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $adminWaPhone = trim($_POST['admin_wa_phone'] ?? '');
        $primaryColor = trim($_POST['primary_color'] ?? '#0284c7');
        $themeMode = in_array($_POST['theme_mode'] ?? '', ['dark', 'light']) ? $_POST['theme_mode'] : 'dark';

        $logoUrlInput = trim($_POST['logo_url'] ?? '');
        $faviconUrlInput = trim($_POST['favicon_url'] ?? '');

        // Handle File Upload Logo jika ada
        $logoUrl = !empty($logoUrlInput) ? $logoUrlInput : null;
        if (isset($_FILES['club_logo']) && $_FILES['club_logo']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['club_logo'];
            $allowedTypes = ['image/png', 'image/jpeg', 'image/webp', 'image/svg+xml'];
            $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($fileInfo, $file['tmp_name']);
            finfo_close($fileInfo);

            if (in_array($mime, $allowedTypes) && $file['size'] <= 2 * 1024 * 1024) {
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $newName = 'logo_' . $clubId . '_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . strtolower($ext);
                $uploadDir = __DIR__ . '/assets/img/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                $destPath = $uploadDir . $newName;
                if (move_uploaded_file($file['tmp_name'], $destPath)) {
                    $logoUrl = 'assets/img/' . $newName;
                }
            } else {
                $alertMsg = "Format logo tidak valid (harus PNG/JPG/WEBP/SVG) atau ukuran melebihi 2MB.";
                $alertType = 'danger';
            }
        }

        // Handle File Upload Navicon / Favicon Tab Browser jika ada
        $faviconUrl = !empty($faviconUrlInput) ? $faviconUrlInput : null;
        if (isset($_FILES['club_favicon']) && $_FILES['club_favicon']['error'] === UPLOAD_ERR_OK) {
            $fileFav = $_FILES['club_favicon'];
            $ext = strtolower(pathinfo($fileFav['name'], PATHINFO_EXTENSION));
            $allowedFavExts = ['ico', 'png', 'svg', 'webp', 'jpg', 'jpeg'];

            if (in_array($ext, $allowedFavExts) && $fileFav['size'] <= 1 * 1024 * 1024) {
                $newNameFav = 'favicon_' . $clubId . '_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
                $uploadDir = __DIR__ . '/assets/img/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                $destPathFav = $uploadDir . $newNameFav;
                if (move_uploaded_file($fileFav['tmp_name'], $destPathFav)) {
                    $faviconUrl = 'assets/img/' . $newNameFav;
                }
            } else {
                $alertMsg = "Format navicon/favicon tidak valid (ICO/PNG/SVG/WEBP) atau ukuran melebihi 1MB.";
                $alertType = 'danger';
            }
        }

        $sessionRememberDays = (int)($_POST['session_remember_days'] ?? 7);
        if (!in_array($sessionRememberDays, [1, 3, 7, 14, 30])) {
            $sessionRememberDays = 7;
        }

        if (!$alertMsg) {
            try {
                $params = [
                    ':cname' => $clubName,
                    ':chair' => $chairmanName,
                    ':addr' => $address,
                    ':ph' => $phone,
                    ':waph' => $adminWaPhone,
                    ':color' => $primaryColor,
                    ':th' => $themeMode,
                    ':srd' => $sessionRememberDays,
                    ':cid' => $clubId
                ];

                $sqlExtra = "";
                if ($logoUrl !== null) {
                    $sqlExtra .= ", logo_url = :logo";
                    $params[':logo'] = $logoUrl;
                }
                if ($faviconUrl !== null) {
                    $sqlExtra .= ", favicon_url = :favicon";
                    $params[':favicon'] = $faviconUrl;
                }

                $stmtUp = $pdo->prepare("
                    UPDATE archery_clubs 
                    SET club_name = :cname, 
                        chairman_name = :chair, 
                        address = :addr, 
                        phone = :ph, 
                        admin_wa_phone = :waph, 
                        primary_color = :color, 
                        theme_mode = :th,
                        session_remember_days = :srd
                        $sqlExtra
                    WHERE id = :cid
                ");
                $stmtUp->execute($params);
                $alertMsg = "✔ Profil klub, logo, navicon tab browser & masa aktif sesi berhasil diperbarui!";
            } catch (PDOException $e) {
                $alertMsg = "Gagal memperbarui profil: " . $e->getMessage();
                $alertType = 'danger';
            }
        }
    } elseif ($action === 'save_fonnte') {
        $fonnteEnabled = isset($_POST['fonnte_enabled']) ? 1 : 0;
        $fonnteToken = trim($_POST['fonnte_token'] ?? '');
        $payTemplate = trim($_POST['wa_payment_template'] ?? '');
        $promoTemplate = trim($_POST['wa_promotion_template'] ?? '');

        try {
            $stmtF = $pdo->prepare("
                UPDATE archery_clubs 
                SET fonnte_enabled = :enabled, 
                    fonnte_token = :token, 
                    wa_payment_template = :pay_tpl, 
                    wa_promotion_template = :promo_tpl 
                WHERE id = :cid
            ");
            $stmtF->execute([
                ':enabled' => $fonnteEnabled,
                ':token' => $fonnteToken,
                ':pay_tpl' => $payTemplate,
                ':promo_tpl' => $promoTemplate,
                ':cid' => $clubId
            ]);
            $alertMsg = "✔ Konfigurasi WhatsApp Bot (Fonnte) & template pesan berhasil disimpan!";
        } catch (PDOException $e) {
            $alertMsg = "Gagal menyimpan konfigurasi WhatsApp: " . $e->getMessage();
            $alertType = 'danger';
        }
    } elseif ($action === 'test_wa') {
        $testTarget = trim($_POST['test_phone'] ?? '');
        if ($testTarget) {
            $testMsg = "🎯 *TES KONEKSI WHATSAPP BOT (TARGET ARCHERY)*\n\nHalo Admin, ini adalah pesan uji coba dari sistem Target Archery. Bot Fonnte berhasil terkoneksi dengan status AKTIF!";
            $res = FonnteWA::sendCustomMessage($pdo, $clubId, $testTarget, $testMsg);
            if ($res['status'] === 'success') {
                $alertMsg = "✔ Pesan uji coba berhasil dikirim ke " . htmlspecialchars($testTarget);
            } else {
                $alertMsg = "Gagal kirim pesan uji coba: " . htmlspecialchars($res['message']);
                $alertType = 'danger';
            }
        } else {
            $alertMsg = "Nomor tujuan tes tidak boleh kosong.";
            $alertType = 'danger';
        }
    } elseif ($action === 'save_session_format') {
        $defEnds = max(1, min(24, (int)($_POST['default_total_ends'] ?? 6)));
        $defArrows = max(1, min(12, (int)($_POST['default_arrows_per_end'] ?? 5)));
        $defTarget = max(1, (int)($_POST['default_target_id'] ?? 4));

        try {
            $stmtS = $pdo->prepare("
                UPDATE archery_clubs 
                SET default_total_ends = :ends,
                    default_arrows_per_end = :arrows,
                    default_target_id = :target
                WHERE id = :cid
            ");
            $stmtS->execute([
                ':ends' => $defEnds,
                ':arrows' => $defArrows,
                ':target' => $defTarget,
                ':cid' => $clubId
            ]);
            $alertMsg = "✔ Konfigurasi Standar Format Sesi Panahan Klub berhasil disimpan!";
        } catch (PDOException $e) {
            $alertMsg = "Gagal menyimpan format sesi: " . $e->getMessage();
            $alertType = 'danger';
        }
    }
}

// Ambil Data Klub Terkini
$stmtClub = $pdo->prepare("SELECT * FROM archery_clubs WHERE id = :cid LIMIT 1");
$stmtClub->execute([':cid' => $clubId]);
$club = $stmtClub->fetch();

if (!$club) {
    die("Klub tidak ditemukan.");
}
$pageTitle = "Pengaturan Klub & Sistem - TARGET ARCHERY";
require_once __DIR__ . '/views/header.php';
?>
<style>
    .settings-tab-nav {
        display: flex;
        gap: 0.5rem;
        border-bottom: 1px solid var(--border-color);
        margin-bottom: 1.5rem;
        overflow-x: auto;
    }
    .tab-btn {
        background: none;
        border: none;
        border-bottom: 2px solid transparent;
        color: var(--text-muted);
        padding: 0.75rem 1.25rem;
        font-size: 0.95rem;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.2s;
        white-space: nowrap;
    }
    .tab-btn.active {
        color: var(--primary);
        border-bottom-color: var(--primary);
    }
    .color-palette-picker {
        display: flex;
        gap: 0.5rem;
        margin-top: 0.5rem;
        flex-wrap: wrap;
    }
    .palette-chip {
        width: 34px;
        height: 34px;
        border-radius: 50%;
        cursor: pointer;
        border: 2px solid #ffffff;
        transition: transform 0.2s;
    }
    .palette-chip:hover {
        transform: scale(1.15);
    }
    .variable-badge {
        background: rgba(2, 132, 199, 0.15);
        border: 1px solid var(--primary);
        color: var(--primary);
        padding: 2px 6px;
        border-radius: 4px;
        font-size: 0.75rem;
        cursor: pointer;
        font-family: monospace;
    }
</style>

<main class="container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h1 style="font-size: 1.6rem; margin-bottom: 0.25rem;">
                ⚙️ Pengaturan Klub & Sistem
            </h1>
            <p style="color: var(--text-muted); font-size: 0.85rem; margin: 0;">
                Kelola profil lembaga, identitas visual warna, logo, dan integrasi WhatsApp Bot Fonnte.
            </p>
        </div>
        <div style="display: flex; gap: 0.5rem;">
            <a href="admin_distances.php" class="btn btn-secondary btn-sm">📏 Kelola Jarak</a>
            <a href="admin_approvals.php" class="btn btn-secondary btn-sm">✅ ACC Naik Jarak</a>
        </div>
    </div>

    <?php if ($alertMsg): ?>
        <div class="card" style="margin-bottom: 1.5rem; padding: 1rem 1.25rem; border-left: 4px solid <?= $alertType === 'danger' ? 'var(--danger)' : 'var(--success)' ?>; background: rgba(0,0,0,0.2);">
            <?= htmlspecialchars($alertMsg) ?>
        </div>
    <?php endif; ?>

    <!-- TABS NAVIGATION -->
    <div class="settings-tab-nav">
        <button type="button" class="tab-btn active" onclick="switchSettingsTab('brandingTab', this)">
            🎨 Profil &amp; Branding Klub
        </button>
        <button type="button" class="tab-btn" onclick="switchSettingsTab('sessionFormatTab', this)">
            🏹 Format Sesi &amp; Seri Panahan
        </button>
        <button type="button" class="tab-btn" onclick="switchSettingsTab('fonnteTab', this)">
            🤖 WhatsApp Bot (Fonnte)
        </button>
        <button type="button" class="tab-btn" onclick="switchSettingsTab('securityTab', this)">
            🛡️ Bantuan Login &amp; Error Hubungi Admin
        </button>
    </div>

    <!-- TAB 1: PROFIL & BRANDING KLUB -->
    <div id="brandingTab" class="settings-tab-content">
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="save_branding">
            
            <div class="card" style="margin-bottom: 1.5rem;">
                <div class="card-header">
                    <h2 class="card-title">Identitas Lembaga & Pejabat Pengesah Rapot</h2>
                </div>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.25rem;">
                    <div class="form-group">
                        <label class="form-label">Nama Resmi Klub Panahan:</label>
                        <input type="text" class="form-control" name="club_name" value="<?= htmlspecialchars($club['club_name']) ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Nama Ketua Klub / Penanggung Jawab Rapot:</label>
                        <input type="text" class="form-control" name="chairman_name" value="<?= htmlspecialchars($club['chairman_name']) ?>" required>
                        <span style="font-size: 0.72rem; color: var(--text-dim);">Nama ini tercetak otomatis di lembar rapot cetak A4 resmi bersama Coach.</span>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Nomor WhatsApp Admin / Call Center:</label>
                        <input type="text" class="form-control" name="admin_wa_phone" value="<?= htmlspecialchars($club['admin_wa_phone']) ?>" placeholder="0812xxxxxxxx" required>
                        <span style="font-size: 0.72rem; color: var(--text-dim);">Muncul di tombol bantuan popup saat atlet/ortu gagal login.</span>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Nomor Telepon Sekretariat:</label>
                        <input type="text" class="form-control" name="phone" value="<?= htmlspecialchars($club['phone'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Alamat Lengkap Klub / Lapangan Panahan:</label>
                    <textarea class="form-control" name="address" rows="2"><?= htmlspecialchars($club['address'] ?? '') ?></textarea>
                </div>
            </div>

            <!-- IDENTITAS VISUAL: WARNA, LOGO & NAVICON -->
            <div class="card" style="margin-bottom: 1.5rem;">
                <div class="card-header">
                    <h2 class="card-title">🎨 Identitas Visual, Logo &amp; Navicon (Favicon) Browser</h2>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem;">
                    <!-- WARNA UTAMA -->
                    <div class="form-group">
                        <label class="form-label fw-bold">Warna Utama (Primary Color):</label>
                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                            <input type="color" name="primary_color" id="primaryColorInput" value="<?= htmlspecialchars($club['primary_color']) ?>" style="width: 50px; height: 42px; border: none; cursor: pointer; border-radius: var(--radius-sm); background: transparent;">
                            <input type="text" id="primaryColorHex" class="form-control" value="<?= htmlspecialchars($club['primary_color']) ?>" style="width: 120px;" oninput="document.getElementById('primaryColorInput').value = this.value">
                        </div>
                        <div class="color-palette-picker">
                            <div class="palette-chip" style="background: #0284c7;" title="Bright Sky Electric Blue (Default)" onclick="pickColor('#0284c7')"></div>
                            <div class="palette-chip" style="background: #10b981;" title="Emerald Green" onclick="pickColor('#10b981')"></div>
                            <div class="palette-chip" style="background: #f59e0b;" title="Amber Gold" onclick="pickColor('#f59e0b')"></div>
                            <div class="palette-chip" style="background: #6366f1;" title="Royal Indigo" onclick="pickColor('#6366f1')"></div>
                            <div class="palette-chip" style="background: #e11d48;" title="Crimson Red" onclick="pickColor('#e11d48')"></div>
                        </div>
                    </div>

                    <!-- LOGO KLUB -->
                    <div class="form-group">
                        <label class="form-label fw-bold">Logo Resmi Klub:</label>
                        <div class="mb-2">
                            <input type="text" class="form-control mb-2" name="logo_url" id="logoUrlInput" value="<?= htmlspecialchars($club['logo_url'] ?? '') ?>" placeholder="URL Logo (misal: assets/img/logo.png atau URL web)">
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-secondary">Upload File:</span>
                                <input type="file" class="form-control form-control-sm" name="club_logo" accept="image/png, image/jpeg, image/webp, image/svg+xml">
                            </div>
                        </div>
                        <div class="p-2 rounded d-flex align-items-center gap-3" style="background: rgba(255,255,255,0.03); border: 1px solid var(--border-color);">
                            <span style="font-size: 0.75rem; color: var(--text-dim);">Live Logo:</span>
                            <img src="<?= htmlspecialchars(!empty($club['logo_url']) ? $club['logo_url'] : 'assets/img/default_logo.png') ?>" alt="Logo Preview" id="logoLivePreview" style="height: 40px; max-width: 120px; object-fit: contain; border-radius: 4px; background: rgba(0,0,0,0.3); padding: 3px;">
                        </div>
                    </div>

                    <!-- NAVICON / FAVICON BROWSER -->
                    <div class="form-group">
                        <label class="form-label fw-bold">Navicon / Favicon Tab Browser:</label>
                        <div class="mb-2">
                            <input type="text" class="form-control mb-2" name="favicon_url" id="faviconUrlInput" value="<?= htmlspecialchars($club['favicon_url'] ?? '') ?>" placeholder="URL Favicon (misal: assets/img/favicon.png atau .ico)">
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-secondary">Upload File:</span>
                                <input type="file" class="form-control form-control-sm" name="club_favicon" accept="image/x-icon, image/png, image/svg+xml, image/webp">
                            </div>
                        </div>
                        <div class="p-2 rounded" style="background: rgba(255,255,255,0.03); border: 1px solid var(--border-color);">
                            <span style="font-size: 0.72rem; color: var(--text-dim); display: block; margin-bottom: 4px;">Simulasi Tampilan Tab Browser:</span>
                            <div class="d-flex align-items-center gap-2 px-3 py-1 rounded" style="background: #1e293b; border: 1px solid rgba(255,255,255,0.1); width: fit-content;">
                                <img src="<?= htmlspecialchars(!empty($club['favicon_url']) ? $club['favicon_url'] : 'assets/img/default_favicon.png') ?>" alt="Favicon Preview" style="width: 18px; height: 18px; object-fit: contain;">
                                <span style="font-size: 0.8rem; color: #e2e8f0; font-weight: 600;"><?= htmlspecialchars($club['club_name']) ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- DURASI SESI INGAT SAYA (LOGIN CACHE) -->
                <div class="mt-4 pt-3 border-top" style="border-color: var(--border-color) !important;">
                    <h3 style="font-size: 1rem; color: #fff; margin-bottom: 0.5rem;">
                        ⏱️ Pengaturan Masa Aktif "Ingat Saya" (Cache Sesi Login)
                    </h3>
                    <p style="font-size: 0.78rem; color: var(--text-muted); margin-bottom: 0.75rem;">
                        Tentukan berapa lama perangkat atlet &amp; wali murid tetap terautentikasi otomatis tanpa harus memasukkan kata sandi berulang kali saat opsi "Ingat Saya" dicentang.
                    </p>
                    <div style="max-width: 320px;">
                        <label class="form-label fw-bold">Durasi Aktif Sesi:</label>
                        <select name="session_remember_days" class="form-control">
                            <?php 
                            $curDays = (int)($club['session_remember_days'] ?? 7);
                            $durations = [
                                1 => '1 Hari (Sangat Ketat)',
                                3 => '3 Hari',
                                7 => '7 Hari (Rekomendasi Standar)',
                                14 => '14 Hari (2 Pekan)',
                                30 => '30 Hari (1 Bulan Penuh)'
                            ];
                            foreach ($durations as $val => $label):
                            ?>
                                <option value="<?= $val ?>" <?= $curDays === $val ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div style="margin-top: 1.5rem;">
                    <button type="submit" class="btn btn-primary" style="padding: 0.75rem 2rem;">
                        💾 Simpan Profil &amp; Branding Lengkap
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- TAB 2: WHATSAPP BOT (FONNTE) -->
    <div id="fonnteTab" class="settings-tab-content" style="display: none;">
        <form method="POST">
            <input type="hidden" name="action" value="save_fonnte">

            <div class="card" style="margin-bottom: 1.5rem;">
                <div class="card-header">
                    <h2 class="card-title">Integrasi WhatsApp Bot Fonnte</h2>
                </div>

                <!-- SWITCH ON / OFF -->
                <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1.25rem; padding: 1rem; background: rgba(255,255,255,0.03); border-radius: var(--radius-sm); border: 1px solid var(--border-color);">
                    <input type="checkbox" name="fonnte_enabled" id="fonnteEnabledSwitch" value="1" <?= $club['fonnte_enabled'] ? 'checked' : '' ?> style="width: 20px; height: 20px; cursor: pointer;">
                    <label for="fonnteEnabledSwitch" style="cursor: pointer; margin: 0;">
                        <span style="font-weight: 700; font-size: 1rem; color: #fff;">Aktifkan Layanan WhatsApp Bot Otomatis</span><br>
                        <span style="font-size: 0.78rem; color: var(--text-muted);">
                            Jika dimatikan, seluruh proses simpan iuran & kelulusan ujian tetap berjalan normal tanpa mengirim pesan WA.
                        </span>
                    </label>
                </div>

                <div class="form-group">
                    <label class="form-label">Fonnte API Token:</label>
                    <input type="password" class="form-control" name="fonnte_token" value="<?= htmlspecialchars($club['fonnte_token']) ?>" placeholder="Masukkan token Fonnte dari akun fonnte.com...">
                    <span style="font-size: 0.72rem; color: var(--text-dim);">
                        Token disimpan aman di server side. Dapatkan token di menu Device akun Anda di <a href="https://fonnte.com" target="_blank" style="color: var(--primary);">fonnte.com</a>.
                    </span>
                </div>

                <div style="margin-top: 1.25rem;">
                    <h3 style="font-size: 1rem; margin-bottom: 0.5rem; color: var(--primary);">
                        1. Template Pesan Kuitansi Pembayaran Iuran
                    </h3>
                    <div style="display: flex; gap: 0.35rem; margin-bottom: 0.5rem; flex-wrap: wrap;">
                        <span style="font-size: 0.75rem; color: var(--text-dim);">Variabel:</span>
                        <span class="variable-badge" onclick="insertTag('payTpl', '{nama_ortu}')">{nama_ortu}</span>
                        <span class="variable-badge" onclick="insertTag('payTpl', '{nama_atlet}')">{nama_atlet}</span>
                        <span class="variable-badge" onclick="insertTag('payTpl', '{nominal}')">{nominal}</span>
                        <span class="variable-badge" onclick="insertTag('payTpl', '{bulan}')">{bulan}</span>
                        <span class="variable-badge" onclick="insertTag('payTpl', '{ref}')">{ref}</span>
                        <span class="variable-badge" onclick="insertTag('payTpl', '{nama_klub}')">{nama_klub}</span>
                    </div>
                    <textarea class="form-control" name="wa_payment_template" id="payTpl" rows="5"><?= htmlspecialchars($club['wa_payment_template']) ?></textarea>
                </div>

                <div style="margin-top: 1.25rem;">
                    <h3 style="font-size: 1rem; margin-bottom: 0.5rem; color: #34d399;">
                        2. Template Pesan Kelulusan Kenaikan Jarak
                    </h3>
                    <div style="display: flex; gap: 0.35rem; margin-bottom: 0.5rem; flex-wrap: wrap;">
                        <span style="font-size: 0.75rem; color: var(--text-dim);">Variabel:</span>
                        <span class="variable-badge" onclick="insertTag('promoTpl', '{nama_atlet}')">{nama_atlet}</span>
                        <span class="variable-badge" onclick="insertTag('promoTpl', '{jarak_lama}')">{jarak_lama}</span>
                        <span class="variable-badge" onclick="insertTag('promoTpl', '{jarak_baru}')">{jarak_baru}</span>
                        <span class="variable-badge" onclick="insertTag('promoTpl', '{skor_ujian}')">{skor_ujian}</span>
                        <span class="variable-badge" onclick="insertTag('promoTpl', '{nama_coach}')">{nama_coach}</span>
                        <span class="variable-badge" onclick="insertTag('promoTpl', '{nama_ketua}')">{nama_ketua}</span>
                        <span class="variable-badge" onclick="insertTag('promoTpl', '{nama_klub}')">{nama_klub}</span>
                    </div>
                    <textarea class="form-control" name="wa_promotion_template" id="promoTpl" rows="6"><?= htmlspecialchars($club['wa_promotion_template']) ?></textarea>
                </div>

                <div style="margin-top: 1.5rem; display: flex; gap: 0.75rem;">
                    <button type="submit" class="btn btn-primary" style="padding: 0.75rem 2rem;">
                        💾 Simpan Konfigurasi Fonnte
                    </button>
                </div>
            </div>
        </form>

        <!-- TES KONEKSI WHATSAPP -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">🧪 Uji Coba Pengiriman Pesan WhatsApp</h3>
            </div>
            <p style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 1rem;">
                Pastikan token Fonnte sudah disimpan sebelum melakukan uji coba kirim pesan.
            </p>
            <form method="POST" style="display: flex; gap: 0.75rem; max-width: 500px;">
                <input type="hidden" name="action" value="test_wa">
                <input type="text" class="form-control" name="test_phone" placeholder="Nomor WA Tujuan (cth: 081299887766)" required>
                <button type="submit" class="btn btn-secondary" style="white-space: nowrap;">
                    🚀 Kirim Tes WA
                </button>
            </form>
        </div>
    </div>

    <!-- TAB 3: FORMAT SESI & SERI PANAHAN -->
    <div id="sessionFormatTab" class="settings-tab-content" style="display: none;">
        <form method="POST">
            <input type="hidden" name="action" value="save_session_format">
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">🏹 Pengaturan Format Sesi Latihan &amp; Jumlah Seri / Anak Panah</h2>
                </div>
                <p style="font-size: 0.85rem; color: var(--text-muted); line-height: 1.5;">
                    Atur format baku sesi latihan default untuk seluruh pelatih dan atlet di klub ini. Format ini akan otomatis dimuat setiap kali atlet atau pelatih memulai sesi baru. Pelatih tetap dapat memilih opsi sesi percobaan (misal 2 seri) atau ujian kenaikan jarak sewaktu-waktu.
                </p>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1rem; margin-top: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Jumlah Seri Default (Total Ends)</label>
                        <input type="number" class="form-control" name="default_total_ends" id="cfgTotalEnds" value="<?= (int)($club['default_total_ends'] ?? 6) ?>" min="1" max="24" required oninput="calcSettingsMaxScore()">
                        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 4px;">Standar World Archery / Klub umumnya 6 seri (atau 10 seri untuk indoor 300 round).</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Jumlah Anak Panah per Seri (Arrows per End)</label>
                        <input type="number" class="form-control" name="default_arrows_per_end" id="cfgArrowsPerEnd" value="<?= (int)($club['default_arrows_per_end'] ?? 5) ?>" min="1" max="12" required oninput="calcSettingsMaxScore()">
                        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 4px;">Umumnya 5 panah (skor max 300) atau 6 panah (skor max 360). Sesi indoor Vegas 3 panah.</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Papan Target Utama Default</label>
                        <select class="form-control" name="default_target_id">
                            <option value="4" <?= (($club['default_target_id'] ?? 4) == 4) ? 'selected' : '' ?>>WA 10-Ring Standard (80/122/40cm Full Face 1-10 + X)</option>
                            <option value="5" <?= (($club['default_target_id'] ?? 4) == 5) ? 'selected' : '' ?>>WA 6-Ring Center Spot (Compound/Spot 5-10 + X)</option>
                            <option value="1" <?= (($club['default_target_id'] ?? 4) == 1) ? 'selected' : '' ?>>WA Indoor 3-Spot Vertical (Triple Spot 6-10 + X)</option>
                            <option value="2" <?= (($club['default_target_id'] ?? 4) == 2) ? 'selected' : '' ?>>NFAA Indoor Blue Face (Blue 1-4, White 5 + X)</option>
                            <option value="3" <?= (($club['default_target_id'] ?? 4) == 3) ? 'selected' : '' ?>>WA Field Archery Face (Black 1-4, Gold 5 + X)</option>
                        </select>
                        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 4px;">Papan target default saat membuka target plotter scoring.</div>
                    </div>
                </div>

                <div class="alert alert-dark" style="margin-top: 1rem; border: 1px solid var(--border-color); background: rgba(0,0,0,0.3); padding: 1rem; border-radius: var(--radius-sm);">
                    <div style="font-size: 0.85rem; color: var(--text-dim);">📊 Kalkulasi Kapasitas Sesi:</div>
                    <div style="font-size: 1.15rem; font-weight: 700; color: var(--primary); margin-top: 4px;" id="cfgCalcDisplay">
                        <?= (int)($club['default_total_ends'] ?? 6) ?> Seri × <?= (int)($club['default_arrows_per_end'] ?? 5) ?> Panah = <?= ((int)($club['default_total_ends'] ?? 6)) * ((int)($club['default_arrows_per_end'] ?? 5)) ?> Tembakan (Maksimal <?= ((int)($club['default_total_ends'] ?? 6)) * ((int)($club['default_arrows_per_end'] ?? 5)) * 10 ?> Poin)
                    </div>
                </div>

                <div style="margin-top: 1.5rem;">
                    <button type="submit" class="btn btn-primary">
                        💾 Simpan Format Sesi Latihan Klub
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- TAB 4: BANTUAN LOGIN & HUBUNGI ADMIN -->
    <div id="securityTab" class="settings-tab-content" style="display: none;">
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Pratinjau Munculan Permohonan Maaf Saat Login Gagal</h2>
            </div>
            <p style="font-size: 0.85rem; color: var(--text-muted); line-height: 1.5;">
                Saat atlet atau wali murid salah memasukkan PIN atau password akun, sistem Target Archery menampilkan modal elegan bernuansa ramah, meminta maaf atas kendala, menampilkan logo klub, serta menyediakan tombol langsung untuk terhubung dengan WhatsApp Admin.
            </p>

            <div style="background: rgba(0,0,0,0.4); padding: 2rem; border-radius: var(--radius-md); max-width: 440px; margin: 1rem auto; text-align: center; border: 1px solid var(--border-color);">
                <div style="width: 56px; height: 56px; border-radius: 50%; background: rgba(2, 132, 199, 0.15); color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 26px; margin: 0 auto 1rem auto; border: 1px solid var(--primary);">
                    🎯
                </div>
                <h3 style="margin: 0 0 0.5rem 0; font-size: 1.15rem; color: #fff;">
                    Mohon Maaf, Terjadi Kendala Masuk
                </h3>
                <p style="font-size: 0.82rem; color: var(--text-muted); margin-bottom: 1.25rem;">
                    Kredensial atau PIN akses yang Anda masukkan belum sesuai dengan data atlet di <b><?= htmlspecialchars($club['club_name']) ?></b>.
                </p>
                <a href="https://wa.me/<?= preg_replace('/^0/', '62', preg_replace('/[^0-9]/', '', $club['admin_wa_phone'])) ?>?text=Halo%20Admin%20<?= urlencode($club['club_name']) ?>,%20saya%20mengalami%20kendala%20saat%20login%20Target%20Archery" target="_blank" class="btn btn-primary btn-block">
                    💬 Hubungi WhatsApp Admin (<?= htmlspecialchars($club['admin_wa_phone']) ?>)
                </a>
            </div>
        </div>
    </div>
</main>

<script>
function switchSettingsTab(tabId, btn) {
    document.querySelectorAll('.settings-tab-content').forEach(el => el.style.display = 'none');
    document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
    document.getElementById(tabId).style.display = 'block';
    btn.classList.add('active');
}

function pickColor(hex) {
    document.getElementById('primaryColorInput').value = hex;
    document.getElementById('primaryColorHex').value = hex;
}

function insertTag(textareaId, tag) {
    const el = document.getElementById(textareaId);
    if (!el) return;
    const start = el.selectionStart;
    const end = el.selectionEnd;
    const text = el.value;
    el.value = text.substring(0, start) + tag + text.substring(end);
    el.focus();
    el.selectionStart = el.selectionEnd = start + tag.length;
}

function calcSettingsMaxScore() {
    const ends = parseInt(document.getElementById('cfgTotalEnds').value) || 6;
    const arrows = parseInt(document.getElementById('cfgArrowsPerEnd').value) || 5;
    const totalShots = ends * arrows;
    const maxScore = totalShots * 10;
    const disp = document.getElementById('cfgCalcDisplay');
    if (disp) {
        disp.innerText = `${ends} Seri × ${arrows} Panah = ${totalShots} Tembakan (Maksimal ${maxScore} Poin)`;
    }
}
</script>

<?php include __DIR__ . '/views/footer.php'; ?>
