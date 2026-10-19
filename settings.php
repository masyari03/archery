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

        // Handle File Upload Logo jika ada
        $logoUrl = null;
        if (isset($_FILES['club_logo']) && $_FILES['club_logo']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['club_logo'];
            $allowedTypes = ['image/png', 'image/jpeg', 'image/webp'];
            $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($fileInfo, $file['tmp_name']);
            finfo_close($fileInfo);

            if (in_array($mime, $allowedTypes) && $file['size'] <= 2 * 1024 * 1024) {
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $newName = 'logo_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . strtolower($ext);
                $uploadDir = __DIR__ . '/assets/img/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                $destPath = $uploadDir . $newName;
                if (move_uploaded_file($file['tmp_name'], $destPath)) {
                    $logoUrl = 'assets/img/' . $newName;
                }
            } else {
                $alertMsg = "Format logo tidak valid (harus PNG/JPG/WEBP) atau ukuran melebihi 2MB.";
                $alertType = 'danger';
            }
        }

        if (!$alertMsg) {
            try {
                if ($logoUrl) {
                    $stmtUp = $pdo->prepare("
                        UPDATE archery_clubs 
                        SET club_name = :cname, 
                            chairman_name = :chair, 
                            address = :addr, 
                            phone = :ph, 
                            admin_wa_phone = :waph, 
                            primary_color = :color, 
                            theme_mode = :th,
                            logo_url = :logo
                        WHERE id = :cid
                    ");
                    $stmtUp->execute([
                        ':cname' => $clubName,
                        ':chair' => $chairmanName,
                        ':addr' => $address,
                        ':ph' => $phone,
                        ':waph' => $adminWaPhone,
                        ':color' => $primaryColor,
                        ':th' => $themeMode,
                        ':logo' => $logoUrl,
                        ':cid' => $clubId
                    ]);
                } else {
                    $stmtUp = $pdo->prepare("
                        UPDATE archery_clubs 
                        SET club_name = :cname, 
                            chairman_name = :chair, 
                            address = :addr, 
                            phone = :ph, 
                            admin_wa_phone = :waph, 
                            primary_color = :color, 
                            theme_mode = :th
                        WHERE id = :cid
                    ");
                    $stmtUp->execute([
                        ':cname' => $clubName,
                        ':chair' => $chairmanName,
                        ':addr' => $address,
                        ':ph' => $phone,
                        ':waph' => $adminWaPhone,
                        ':color' => $primaryColor,
                        ':th' => $themeMode,
                        ':cid' => $clubId
                    ]);
                }
                $alertMsg = "✔ Profil klub & branding berhasil diperbarui!";
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
    }
}

// Ambil Data Klub Terkini
$stmtClub = $pdo->prepare("SELECT * FROM archery_clubs WHERE id = :cid LIMIT 1");
$stmtClub->execute([':cid' => $clubId]);
$club = $stmtClub->fetch();

if (!$club) {
    die("Klub tidak ditemukan.");
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Pengaturan Klub & Sistem - TARGET ARCHERY</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
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
</head>
<body>

<?php include __DIR__ . '/views/navbar.php'; ?>

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
            🎨 Profil & Branding Klub
        </button>
        <button type="button" class="tab-btn" onclick="switchSettingsTab('fonnteTab', this)">
            🤖 WhatsApp Bot (Fonnte)
        </button>
        <button type="button" class="tab-btn" onclick="switchSettingsTab('securityTab', this)">
            🛡️ Bantuan Login & Error Hubungi Admin
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

            <!-- TEMA & WARNA DASAR -->
            <div class="card" style="margin-bottom: 1.5rem;">
                <div class="card-header">
                    <h2 class="card-title">Warna Dasar & Logo Klub</h2>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.25rem;">
                    <div class="form-group">
                        <label class="form-label">Warna Utama (Primary Color):</label>
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

                    <div class="form-group">
                        <label class="form-label">Upload Logo Klub (Format PNG / JPG / WEBP, Max 2MB):</label>
                        <input type="file" class="form-control" name="club_logo" accept="image/png, image/jpeg, image/webp">
                        <?php if (!empty($club['logo_url'])): ?>
                            <div style="margin-top: 0.5rem; display: flex; align-items: center; gap: 0.5rem;">
                                <span style="font-size: 0.75rem; color: var(--text-dim);">Logo saat ini:</span>
                                <img src="<?= htmlspecialchars($club['logo_url']) ?>" alt="Logo Klub" style="height: 36px; border-radius: 4px; background: rgba(255,255,255,0.05); padding: 2px;">
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div style="margin-top: 1rem;">
                    <button type="submit" class="btn btn-primary" style="padding: 0.75rem 2rem;">
                        💾 Simpan Profil & Branding
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

    <!-- TAB 3: BANTUAN LOGIN & HUBUNGI ADMIN -->
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
</script>

<?php include __DIR__ . '/views/footer.php'; ?>
