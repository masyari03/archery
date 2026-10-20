<?php
// master_tenants.php - Portal Manajemen Tenant Klub Panahan (Khusus Master Admin / Platform Owner)
require_once __DIR__ . '/config/auth.php';
Auth::requireAuth();

// Proteksi Akses: Hanya Master Admin
if (($_SESSION['role'] ?? '') !== 'master_admin') {
    die("Akses ditolak. Halaman ini khusus untuk Master Admin (Platform Owner SaaS).");
}

$alertMsg = null;
$alertType = 'success';

// Handle Action: Keluar dari Mode Impersonasi
if (isset($_GET['action']) && $_GET['action'] === 'exit_impersonate') {
    unset($_SESSION['impersonating_club_id']);
    $_SESSION['club_id'] = 1;
    header('Location: master_tenants.php?msg=' . urlencode("✔ Anda telah kembali ke Portal Master Admin."));
    exit;
}

// Handle Action: Masuk / Impersonasi ke Klub
if (isset($_GET['action']) && $_GET['action'] === 'impersonate') {
    $targetClubId = (int)($_GET['club_id'] ?? 0);
    if ($targetClubId > 0) {
        $stmtCheck = $pdo->prepare("SELECT id, club_name FROM archery_clubs WHERE id = :id");
        $stmtCheck->execute([':id' => $targetClubId]);
        $targetClub = $stmtCheck->fetch();

        if ($targetClub) {
            $_SESSION['impersonating_club_id'] = $targetClubId;
            $_SESSION['club_id'] = $targetClubId;
            header('Location: index.php');
            exit;
        }
    }
}

// Handle Form Submission: Tambah Tenant Baru atau Ubah Tenant
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_tenant') {
        $clubName = trim($_POST['club_name'] ?? '');
        $clubCode = strtoupper(trim($_POST['club_code'] ?? ''));
        $city = trim($_POST['city'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $chairmanName = trim($_POST['chairman_name'] ?? '');
        
        $adminUsername = trim($_POST['admin_username'] ?? '');
        $adminPassword = $_POST['admin_password'] ?? '';
        $adminFullName = trim($_POST['admin_fullname'] ?? 'Admin ' . $clubName);
        $adminPhone = trim($_POST['admin_phone'] ?? $phone);

        $pricingScheme = in_array($_POST['pricing_scheme'] ?? '', ['flat_monthly', 'per_archer']) ? $_POST['pricing_scheme'] : 'flat_monthly';
        $flatFee = (float)($_POST['monthly_flat_fee'] ?? 150000);
        $perArcherFee = (float)($_POST['fee_per_archer'] ?? 10000);
        $cutoffDay = (int)($_POST['cutoff_day'] ?? 16);
        $maxQuota = (int)($_POST['max_archers_quota'] ?? 100);
        $expiresAt = !empty($_POST['subscription_expires_at']) ? $_POST['subscription_expires_at'] : date('Y-m-d', strtotime('+30 days'));

        if (empty($clubName) || empty($clubCode) || empty($adminUsername) || empty($adminPassword)) {
            $alertMsg = "Nama klub, kode klub, username admin, dan password wajib diisi.";
            $alertType = 'danger';
        } else {
            try {
                $pdo->beginTransaction();

                // 1. Insert Klub Baru
                $defaultPayTpl = "Halo Bapak/Ibu {nama_ortu},\n\nAlhamdulillah pembayaran iuran panahan ananda *{nama_atlet}* sebesar *Rp {nominal}* untuk periode *{bulan}* telah BERHASIL diverifikasi (Ref: {ref}).\n\nTerima kasih atas dukungannya untuk prestasi ananda di *{nama_klub}* 🎯";
                $defaultPromoTpl = "🎉 *SELAMAT! KELULUSAN KENAIKAN JARAK* 🎯\n\nKpd Yth Orang Tua ananda *{nama_atlet}*,\n\nDengan bangga kami sampaikan bahwa ananda telah dinyatakan *LULUS* uji kompetensi dan resmi *NAIK JARAK TEMBAK* dari *{jarak_lama} Meter* ke *{jarak_baru} Meter* dengan perolehan skor *{skor_ujian}/300*.\n\nPenguji: Coach {nama_coach}\nDisahkan oleh: {nama_ketua}\n\nTerus semangat berlatih! 🏹";

                $stmtNewClub = $pdo->prepare("
                    INSERT INTO archery_clubs 
                    (club_name, club_code, city, address, phone, admin_wa_phone, chairman_name, primary_color, pricing_scheme, monthly_flat_fee, fee_per_archer, cutoff_day, subscription_status, subscription_expires_at, max_archers_quota, wa_payment_template, wa_promotion_template)
                    VALUES 
                    (:cname, :ccode, :city, :addr, :ph, :waph, :chair, '#0284c7', :pscheme, :flat, :perarch, :cutoff, 'active', :exp, :quota, :paytpl, :promotpl)
                ");
                $stmtNewClub->execute([
                    ':cname' => $clubName,
                    ':ccode' => $clubCode,
                    ':city' => $city,
                    ':addr' => $address,
                    ':ph' => $phone,
                    ':waph' => $adminPhone,
                    ':chair' => $chairmanName ?: 'Ketua Perkumpulan',
                    ':pscheme' => $pricingScheme,
                    ':flat' => $flatFee,
                    ':perarch' => $perArcherFee,
                    ':cutoff' => $cutoffDay,
                    ':exp' => $expiresAt,
                    ':quota' => $maxQuota,
                    ':paytpl' => $defaultPayTpl,
                    ':promotpl' => $defaultPromoTpl
                ]);
                $newClubId = (int)$pdo->lastInsertId();

                // 2. Insert User Super Admin Klub
                $hash = password_hash($adminPassword, PASSWORD_BCRYPT);
                $stmtNewUser = $pdo->prepare("
                    INSERT INTO archery_users 
                    (club_id, username, password_hash, full_name, role, phone)
                    VALUES (:cid, :u, :p, :fname, 'super_admin', :ph)
                ");
                $stmtNewUser->execute([
                    ':cid' => $newClubId,
                    ':u' => $adminUsername,
                    ':p' => $hash,
                    ':fname' => $adminFullName,
                    ':ph' => $adminPhone
                ]);

                // 3. Salin Standar Master Jarak ke Klub Baru
                $defaultDistances = [
                    [5, 'Jarak 5 Meter (Dasar Pemula)', 8, 1, 240, 'Fokus form dan anchor point dasar'],
                    [7, 'Jarak 7 Meter (Pemula Madya)', 12, 2, 250, 'Konsistensi rilis'],
                    [10, 'Jarak 10 Meter (Standar Junior)', 16, 3, 260, 'Stabilitas repetisi tarikan'],
                    [15, 'Jarak 15 Meter (Pra-Lanjutan)', 20, 4, 265, 'Persiapan kompetisi junior'],
                    [18, 'Jarak 18 Meter (Standar Indoor)', 24, 6, 275, 'Jarak resmi kompetisi indoor'],
                    [30, 'Jarak 30 Meter (Outdoor)', 30, 8, 280, 'Kompensasi windage outdoor']
                ];
                $stmtDistCopy = $pdo->prepare("
                    INSERT INTO archery_distances (club_id, distance_meter, distance_label, min_sessions_required, min_months_required, min_passing_score, description)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                foreach ($defaultDistances as $dd) {
                    $stmtDistCopy->execute([$newClubId, $dd[0], $dd[1], $dd[2], $dd[3], $dd[4], $dd[5]]);
                }

                $pdo->commit();
                $alertMsg = "✔ Tenant klub baru *{$clubName}* berhasil didaftarkan dan siap beroperasi!";
            } catch (Exception $e) {
                $pdo->rollBack();
                $alertMsg = "Gagal mendaftarkan tenant baru: " . $e->getMessage();
                $alertType = 'danger';
            }
        }
    } elseif ($action === 'toggle_status') {
        $clubId = (int)($_POST['club_id'] ?? 0);
        $newStatus = in_array($_POST['status'] ?? '', ['active', 'suspended', 'trial', 'past_due']) ? $_POST['status'] : 'active';
        try {
            $stmtUp = $pdo->prepare("UPDATE archery_clubs SET subscription_status = :st WHERE id = :id");
            $stmtUp->execute([':st' => $newStatus, ':id' => $clubId]);
            $alertMsg = "✔ Status langganan klub berhasil diubah menjadi: " . strtoupper($newStatus);
        } catch (PDOException $e) {
            $alertMsg = "Gagal mengubah status: " . $e->getMessage();
            $alertType = 'danger';
        }
    }
}

// Ambil Seluruh Tenant Klub & Hitung Jumlah Atlet
$stmtClubs = $pdo->prepare("
    SELECT c.*, 
           COUNT(DISTINCT a.id) AS total_archers,
           COUNT(DISTINCT s.id) AS total_sessions,
           u.full_name AS admin_name,
           u.username AS admin_username
    FROM archery_clubs c
    LEFT JOIN archery_archers a ON a.club_id = c.id AND a.is_active = 1
    LEFT JOIN archery_sessions s ON s.club_id = c.id
    LEFT JOIN archery_users u ON u.club_id = c.id AND u.role = 'super_admin'
    GROUP BY c.id
    ORDER BY c.id ASC
");
$stmtClubs->execute();
$allClubs = $stmtClubs->fetchAll();

// Hitung Ringkasan SaaS
$totalTenants = count($allClubs);
$totalAthletesGlobal = array_sum(array_column($allClubs, 'total_archers'));
$activeTenants = count(array_filter($allClubs, fn($c) => $c['subscription_status'] === 'active'));
$pageTitle = "Portal Master Admin (SaaS Tenant) - TARGET ARCHERY";
require_once __DIR__ . '/views/header.php';
?>

<main class="container">
    <!-- HEADER BAR -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h1 style="font-size: 1.6rem; margin-bottom: 0.25rem;">
                👑 Portal Master Admin: Manajemen Tenant Klub
            </h1>
            <p style="color: var(--text-muted); font-size: 0.85rem; margin: 0;">
                Kelola seluruh klub panahan mitra (tenant), skema tarif sewa SaaS, dan akses 1-click impersonasi.
            </p>
        </div>
        <div style="display: flex; gap: 0.5rem;">
            <a href="master_billing.php" class="btn btn-secondary btn-sm" style="color: #34d399; font-weight: 700;">
                💰 Laporan Tagihan SaaS
            </a>
            <button type="button" class="btn btn-primary btn-sm" onclick="openAddTenantModal()">
                ➕ Daftarkan Klub Baru
            </button>
        </div>
    </div>

    <?php if ($alertMsg || isset($_GET['msg'])): ?>
        <div class="card" style="margin-bottom: 1.5rem; padding: 1rem 1.25rem; border-left: 4px solid <?= $alertType === 'danger' ? 'var(--danger)' : 'var(--success)' ?>; background: rgba(0,0,0,0.2);">
            <?= htmlspecialchars($alertMsg ?: $_GET['msg']) ?>
        </div>
    <?php endif; ?>

    <!-- STATS COUNTER SAAS METRICS -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 1.75rem;">
        <div class="card" style="text-align: center; border-left: 4px solid #f59e0b;">
            <span class="stat-label">TOTAL KLUB MITRA</span>
            <div class="stat-value" style="color: #f59e0b;"><?= $totalTenants ?> Klub</div>
            <span class="stat-sub"><?= $activeTenants ?> Aktif Berlangganan</span>
        </div>
        <div class="card" style="text-align: center; border-left: 4px solid var(--primary);">
            <span class="stat-label">TOTAL ATLET SE-INDONESIA</span>
            <div class="stat-value" style="color: var(--primary);"><?= $totalAthletesGlobal ?> Anak</div>
            <span class="stat-sub">Terdata di seluruh tenant</span>
        </div>
        <div class="card" style="text-align: center; border-left: 4px solid #34d399;">
            <span class="stat-label">STATUS PLATFORM SAAS</span>
            <div class="stat-value" style="color: #34d399; font-size: 1.35rem;">100% ONLINE</div>
            <span class="stat-sub">Multi-Tenant Partitioned</span>
        </div>
    </div>

    <!-- TABEL MANAJEMEN TENANT KLUB -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Daftar Klub Panahan Terdaftar (Tenant)</h2>
            <span style="font-size: 0.8rem; color: var(--text-dim);">
                Klik "Masuk ke Klub" untuk memeriksa dashboard klub secara instan
            </span>
        </div>

        <div style="overflow-x: auto;">
            <table class="scorecard-table">
                <thead>
                    <tr>
                        <th>ID & Kode</th>
                        <th>Nama Klub & Kota</th>
                        <th>Admin & Kontak</th>
                        <th>Skema Tarif SaaS</th>
                        <th>Kuota Atlet</th>
                        <th>Masa Aktif</th>
                        <th>Status</th>
                        <th>Aksi Master Admin</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($allClubs as $club): ?>
                        <tr>
                            <td>
                                <b>#<?= $club['id'] ?></b><br>
                                <span class="badge badge-gold" style="font-size: 0.75rem;"><?= htmlspecialchars($club['club_code']) ?></span>
                            </td>
                            <td>
                                <b style="font-size: 1rem;"><?= htmlspecialchars($club['club_name']) ?></b><br>
                                <span style="font-size: 0.75rem; color: var(--text-muted);"><?= htmlspecialchars($club['city'] ?? 'Kota') ?> • <?= htmlspecialchars($club['chairman_name']) ?></span><br>
                                <a href="<?= htmlspecialchars(TenantResolver::getBaseUrl($club)) ?>" target="_blank" style="font-size: 0.72rem; color: #38bdf8; font-weight: 600;">
                                    🌐 <?= htmlspecialchars(str_replace(['http://', 'https://'], '', TenantResolver::getBaseUrl($club))) ?> ➔
                                </a>
                            </td>
                            <td>
                                <span style="font-size: 0.85rem; font-weight: 600; color: var(--primary);">
                                    <?= htmlspecialchars($club['admin_username'] ?? 'admin') ?>
                                </span><br>
                                <span style="font-size: 0.75rem; color: var(--text-dim);">WA: <?= htmlspecialchars($club['admin_wa_phone']) ?></span>
                            </td>
                            <td>
                                <?php if ($club['pricing_scheme'] === 'flat_monthly'): ?>
                                    <span class="badge badge-primary">Flat Rp <?= number_format($club['monthly_flat_fee'], 0, ',', '.') ?>/bln</span>
                                <?php else: ?>
                                    <span class="badge" style="background: rgba(16,185,129,0.15); color: #34d399; border: 1px solid #10b981;">
                                        Rp <?= number_format($club['fee_per_archer'], 0, ',', '.') ?> / anak / bln
                                    </span><br>
                                    <span style="font-size: 0.72rem; color: var(--text-dim);">(Cut-off tgl <?= $club['cutoff_day'] ?>)</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <b><?= (int)$club['total_archers'] ?></b> / <?= (int)$club['max_archers_quota'] ?> Anak<br>
                                <span style="font-size: 0.72rem; color: var(--text-dim);"><?= (int)$club['total_sessions'] ?> sesi latihan</span>
                            </td>
                            <td>
                                <span style="font-size: 0.82rem; white-space: nowrap;">
                                    <?= date('d M Y', strtotime($club['subscription_expires_at'] ?? '+1 year')) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($club['subscription_status'] === 'active'): ?>
                                    <span class="badge badge-success">AKTIF</span>
                                <?php elseif ($club['subscription_status'] === 'suspended'): ?>
                                    <span class="badge badge-danger">SUSPENDED</span>
                                <?php else: ?>
                                    <span class="badge badge-gold"><?= strtoupper($club['subscription_status']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td style="white-space: nowrap;">
                                <div style="display: flex; gap: 0.35rem;">
                                    <!-- TOMBOL 1-CLICK IMPERSONASI -->
                                    <a href="master_tenants.php?action=impersonate&club_id=<?= $club['id'] ?>" class="btn btn-primary btn-sm" title="Masuk ke Dashboard Klub ini">
                                        🚀 Masuk ke Klub
                                    </a>

                                    <!-- SUSPEND / AKTIFKAN -->
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Ubah status sewa SaaS klub ini?');">
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="club_id" value="<?= $club['id'] ?>">
                                        <?php if ($club['subscription_status'] === 'active'): ?>
                                            <input type="hidden" name="status" value="suspended">
                                            <button type="submit" class="btn btn-secondary btn-sm" style="color: var(--danger);" title="Bekukan Klub">
                                                ⏸️ Suspend
                                            </button>
                                        <?php else: ?>
                                            <input type="hidden" name="status" value="active">
                                            <button type="submit" class="btn btn-secondary btn-sm" style="color: var(--success);" title="Aktifkan Kembali">
                                                ✔ Aktifkan
                                            </button>
                                        <?php endif; ?>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- MODAL TAMBAH TENANT KLUB BARU -->
<div id="tenantModal" class="login-modal-overlay" style="display: none;">
    <div class="login-error-modal" style="max-width: 650px; text-align: left;">
        <h3 style="margin-top: 0; color: #f59e0b;">➕ Daftarkan Klub Panahan Baru (Tenant)</h3>
        <p style="font-size: 0.82rem; color: var(--text-muted); margin-bottom: 1rem;">
            Sistem otomatis menyiapkan ruang kerja terisolasi (`club_id`), akun Super Admin klub, serta menyalin master jarak standar World Archery.
        </p>

        <form method="POST">
            <input type="hidden" name="action" value="create_tenant">

            <div style="display: grid; grid-template-columns: 1.2fr 0.8fr; gap: 0.75rem;">
                <div class="form-group">
                    <label class="form-label">Nama Klub Panahan Mitra:</label>
                    <input type="text" class="form-control" name="club_name" placeholder="cth: Garuda Archery Solo" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Kode Singkatan / Slug:</label>
                    <input type="text" class="form-control" name="club_code" placeholder="cth: GARUDA-ARC" required>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                <div class="form-group">
                    <label class="form-label">Kota / Wilayah:</label>
                    <input type="text" class="form-control" name="city" placeholder="cth: Surakarta, Jawa Tengah">
                </div>
                <div class="form-group">
                    <label class="form-label">Nama Ketua Klub:</label>
                    <input type="text" class="form-control" name="chairman_name" placeholder="cth: Coach Sulaiman">
                </div>
            </div>

            <div style="padding: 0.75rem; background: rgba(255,255,255,0.03); border: 1px dashed var(--border-color); border-radius: var(--radius-sm); margin-bottom: 1rem;">
                <b style="font-size: 0.85rem; color: var(--primary);">👤 Kredensial Akun Super Admin Klub:</b>
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 0.5rem; margin-top: 0.5rem;">
                    <div>
                        <label style="font-size: 0.72rem; color: var(--text-dim);">Username:</label>
                        <input type="text" class="form-control" name="admin_username" placeholder="admin_garuda" required>
                    </div>
                    <div>
                        <label style="font-size: 0.72rem; color: var(--text-dim);">Password Awal:</label>
                        <input type="password" class="form-control" name="admin_password" placeholder="••••••••" required>
                    </div>
                    <div>
                        <label style="font-size: 0.72rem; color: var(--text-dim);">No. WA Admin:</label>
                        <input type="text" class="form-control" name="admin_phone" placeholder="0812xxxxxxxx" required>
                    </div>
                </div>
            </div>

            <!-- SKEMA PEMBAYARAN SAAS -->
            <div style="padding: 0.75rem; background: rgba(2,132,199,0.06); border: 1px solid var(--border-color); border-radius: var(--radius-sm); margin-bottom: 1rem;">
                <b style="font-size: 0.85rem; color: #34d399;">💰 Skema Tarif Sewa SaaS untuk Klub Ini:</b>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-top: 0.5rem;">
                    <div>
                        <label class="form-label">Pilihan Skema Pembayaran:</label>
                        <select class="form-control" name="pricing_scheme" id="schemeSelect" onchange="togglePricingFields(this.value)">
                            <option value="flat_monthly">1. Flat Per Bulan (Rp 150.000 / bln)</option>
                            <option value="per_archer">2. Dihitung Per Anak (Rp 10.000 / anak)</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Masa Berlaku Berlangganan Hingga:</label>
                        <input type="date" class="form-control" name="subscription_expires_at" value="<?= date('Y-m-d', strtotime('+30 days')) ?>">
                    </div>
                </div>

                <div id="perArcherRuleBox" style="display: none; margin-top: 0.75rem; padding: 0.5rem; background: rgba(0,0,0,0.3); border-radius: 4px; font-size: 0.75rem; color: var(--text-muted); line-height: 1.4;">
                    🎯 <b>Aturan Prorata Atlet Masuk:</b> Cut-off tagihan diatur tanggal <b>16</b>. Atlet baru yang terdaftar setelah tanggal 16 di bulan berjalan **bebas biaya / belum ditagihkan** pada bulan tersebut, dan baru akan mulai dihitung pada tagihan bulan berikutnya.
                </div>
            </div>

            <div style="display: flex; gap: 0.75rem;">
                <button type="button" class="btn btn-secondary btn-block" onclick="closeAddTenantModal()">Batal</button>
                <button type="submit" class="btn btn-primary btn-block">🚀 Daftarkan & Buat Klub</button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddTenantModal() {
    document.getElementById('tenantModal').style.display = 'flex';
}
function closeAddTenantModal() {
    document.getElementById('tenantModal').style.display = 'none';
}
function togglePricingFields(scheme) {
    const box = document.getElementById('perArcherRuleBox');
    if (scheme === 'per_archer') {
        box.style.display = 'block';
    } else {
        box.style.display = 'none';
    }
}
</script>

<?php include __DIR__ . '/views/footer.php'; ?>
