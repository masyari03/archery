<?php
// register_archer.php - Formulir Pendaftaran Atlet Baru Online (Web CMS Multi-Tenant)
require_once __DIR__ . '/config/tenant_resolver.php';
require_once __DIR__ . '/includes/fonnte.php';

$currentTenant = TenantResolver::resolve($pdo);
$tenantId = (int)$currentTenant['id'];

// Ambil Program Kursus yang Tersedia di Klub ini
$stmtProg = $pdo->prepare("SELECT program_name, bow_type, fee_info, target_audience FROM archery_cms_programs WHERE club_id = :cid AND is_active = 1 ORDER BY order_num ASC");
$stmtProg->execute([':cid' => $tenantId]);
$programs = $stmtProg->fetchAll();

$successMsg = '';
$errorMsg = '';
$prefillProgram = trim($_GET['program'] ?? '');

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $studentName = trim($_POST['student_name'] ?? '');
    $studentNickname = trim($_POST['student_nickname'] ?? '');
    $gender = in_array($_POST['gender'] ?? '', ['L', 'P']) ? $_POST['gender'] : 'L';
    $birthDate = !empty($_POST['birth_date']) ? $_POST['birth_date'] : null;
    $parentName = trim($_POST['parent_name'] ?? '');
    $parentPhone = trim($_POST['parent_phone'] ?? '');
    $parentEmail = trim($_POST['parent_email'] ?? '');
    $programChoice = trim($_POST['program_choice'] ?? 'Kelas Pemula');
    $hasOwnBow = !empty($_POST['has_own_bow']) ? 1 : 0;
    $notes = trim($_POST['notes'] ?? '');

    // Validasi
    if (empty($studentName) || empty($parentName) || empty($parentPhone)) {
        $errorMsg = 'Mohon lengkapi Nama Calon Atlet, Nama Orang Tua, dan Nomor WhatsApp aktif.';
    } else {
        try {
            $stmtInsert = $pdo->prepare("
                INSERT INTO `archery_cms_registrations`
                (`club_id`, `student_name`, `student_nickname`, `gender`, `birth_date`, `parent_name`, `parent_phone`, `parent_email`, `program_choice`, `has_own_bow`, `notes`, `status`)
                VALUES
                (:cid, :sname, :snick, :gender, :bdate, :pname, :pphone, :pemail, :prog, :bow, :notes, 'pending')
            ");
            $stmtInsert->execute([
                ':cid' => $tenantId,
                ':sname' => $studentName,
                ':snick' => $studentNickname,
                ':gender' => $gender,
                ':bdate' => $birthDate,
                ':pname' => $parentName,
                ':pphone' => $parentPhone,
                ':pemail' => $parentEmail ?: null,
                ':prog' => $programChoice,
                ':bow' => $hasOwnBow,
                ':notes' => $notes ?: null
            ]);

            // Kirim WhatsApp Otomatis ke Admin Klub via Fonnte jika aktif
            if (!empty($currentTenant['fonnte_enabled']) && !empty($currentTenant['admin_wa_phone'])) {
                $genderText = ($gender === 'L') ? 'Laki-laki' : 'Perempuan';
                $bowText = $hasOwnBow ? 'Punya Sendiri' : 'Pinjam Fasilitas Klub';
                $waMsg = "🎯 *PENDAFTARAN ATLET BARU DARI WEB CMS*\n\n"
                       . "Klub: *{$currentTenant['club_name']}*\n"
                       . "Nama Calon Atlet: *{$studentName}* ({$studentNickname}) - {$genderText}\n"
                       . "Tgl Lahir: " . ($birthDate ?: '-') . "\n"
                       . "Nama Orang Tua: *{$parentName}*\n"
                       . "WhatsApp: {$parentPhone}\n"
                       . "Pilihan Program: *{$programChoice}*\n"
                       . "Busur: {$bowText}\n"
                       . "Catatan: " . ($notes ?: '-') . "\n\n"
                       . "Silakan verifikasi pendaftaran ini di portal CMS Admin.";

                FonnteWA::send($currentTenant['admin_wa_phone'], $waMsg, $tenantId);
            }

            $successMsg = 'Alhamdulillah, pendaftaran calon santri/atlet berhasil dikirim! Admin klub kami akan segera menghubungi Anda melalui WhatsApp untuk jadwal verifikasi & pengenalan lapangan.';
        } catch (PDOException $e) {
            error_log("Registration DB Error: " . $e->getMessage());
            $errorMsg = 'Terjadi kesalahan sistem saat menyimpan pendaftaran. Silakan hubungi admin klub langsung via WhatsApp.';
        }
    }
}

$primaryColor = $currentTenant['primary_color'] ?? '#0284c7';
?>
<!DOCTYPE html>
<html lang="id" data-theme="dark" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pendaftaran Atlet Baru &bull; <?= htmlspecialchars($currentTenant['club_name']) ?></title>

    <!-- Anti-Flicker Dual Theme Engine -->
    <script>
        (function() {
            const savedTheme = localStorage.getItem('archery_theme') || 'dark';
            document.documentElement.setAttribute('data-theme', savedTheme);
            document.documentElement.setAttribute('data-bs-theme', savedTheme);
        })();
    </script>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
    <style>
        :root {
            --primary: <?= htmlspecialchars($primaryColor) ?>;
        }
        .reg-hero {
            background: radial-gradient(circle at top right, rgba(2, 132, 199, 0.25), rgba(11, 15, 25, 0.98));
            padding: 40px 0 20px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }
        .form-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
        }
    </style>
</head>
<body>

    <!-- TOP HEADER -->
    <header class="py-3 border-bottom" style="background: var(--bg-card); border-color: var(--border-color) !important;">
        <div class="container d-flex justify-content-between align-items-center">
            <a href="index.php?club=<?= urlencode($currentTenant['subdomain_slug']) ?>" class="d-flex align-items-center gap-2 text-decoration-none">
                <span class="fs-4">🎯</span>
                <div>
                    <h6 class="mb-0 fw-bold brand-font"><?= htmlspecialchars($currentTenant['club_name']) ?></h6>
                    <span class="text-muted small" style="font-size: 11px;">Pendaftaran Calon Santri &bull; Atlet Panahan</span>
                </div>
            </a>
            <div class="d-flex align-items-center gap-2">
                <!-- TOMBOL TOGGLE TEMA ☀️ / 🌙 -->
                <button type="button" class="theme-toggle-btn me-1" onclick="toggleAppTheme()" title="Ganti Tema Terang/Gelap" id="themeToggleBtn">
                    🌙
                </button>
                <a href="index.php?club=<?= urlencode($currentTenant['subdomain_slug']) ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left"></i> Kembali ke Beranda
                </a>
            </div>
        </div>
    </header>

    <div class="reg-hero text-center">
        <div class="container">
            <span class="badge bg-warning text-dark mb-2">Formulir Pendaftaran Online</span>
            <h2 class="fw-bold text-white mb-2">Gabung Menjadi Bagian Dari Generasi Pemanah Berprestasi</h2>
            <p class="text-muted mx-auto mb-0" style="max-width: 650px;">
                Silakan isi data calon atlet dan orang tua di bawah ini. Kami akan memandu langkah awal keselamatan memanah dan persiapan latihan ananda.
            </p>
        </div>
    </div>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <?php if ($successMsg): ?>
                    <div class="card p-5 form-card text-center border-success shadow-lg">
                        <div class="rounded-circle bg-success text-white mx-auto mb-3 d-flex align-items-center justify-content-center" style="width: 70px; height: 70px; font-size: 32px;">
                            <i class="bi bi-check-lg"></i>
                        </div>
                        <h4 class="fw-bold text-white mb-2">Pendaftaran Berhasil Terkirim!</h4>
                        <p class="text-muted mb-4"><?= htmlspecialchars($successMsg) ?></p>
                        <div class="d-flex justify-content-center gap-3">
                            <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $currentTenant['admin_wa_phone']) ?>?text=Halo%20Admin%20<?= urlencode($currentTenant['club_name']) ?>,%20saya%20sudah%20mengisi%20formulir%20pendaftaran%20online%20untuk%20ananda%20<?= urlencode($studentName ?? '') ?>" target="_blank" class="btn btn-success fw-bold px-4 py-2">
                                <i class="bi bi-whatsapp"></i> Konfirmasi ke WhatsApp Admin
                            </a>
                            <a href="index.php?club=<?= urlencode($currentTenant['subdomain_slug']) ?>" class="btn btn-outline-light px-4 py-2">
                                Kembali ke Web
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <?php if ($errorMsg): ?>
                        <div class="alert alert-danger mb-4 d-flex align-items-center gap-2">
                            <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                            <div><?= htmlspecialchars($errorMsg) ?></div>
                        </div>
                    <?php endif; ?>

                    <div class="card p-4 p-md-5 form-card shadow-lg">
                        <form action="" method="POST">
                            <!-- Section 1: Data Calon Atlet -->
                            <h5 class="fw-bold text-info mb-3 d-flex align-items-center gap-2 border-bottom border-secondary border-opacity-25 pb-2">
                                <i class="bi bi-person-fill"></i> 1. Identitas Calon Atlet / Santri
                            </h5>
                            <div class="row g-3 mb-4">
                                <div class="col-md-8">
                                    <label class="form-label fw-bold">Nama Lengkap Ananda <span class="text-danger">*</span></label>
                                    <input type="text" name="student_name" class="form-control" placeholder="Contoh: Muhammad Ahmad Fauzi" required value="<?= htmlspecialchars($_POST['student_name'] ?? '') ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-bold">Nama Panggilan</label>
                                    <input type="text" name="student_nickname" class="form-control" placeholder="Contoh: Fauzi" value="<?= htmlspecialchars($_POST['student_nickname'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Jenis Kelamin <span class="text-danger">*</span></label>
                                    <select name="gender" class="form-select" required>
                                        <option value="L" <?= ($_POST['gender'] ?? '') === 'L' ? 'selected' : '' ?>>Laki-laki (Ikhwan)</option>
                                        <option value="P" <?= ($_POST['gender'] ?? '') === 'P' ? 'selected' : '' ?>>Perempuan (Akhwat)</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Tanggal Lahir</label>
                                    <input type="date" name="birth_date" class="form-control" value="<?= htmlspecialchars($_POST['birth_date'] ?? '') ?>">
                                </div>
                            </div>

                            <!-- Section 2: Data Orang Tua / Wali -->
                            <h5 class="fw-bold text-warning mb-3 d-flex align-items-center gap-2 border-bottom border-secondary border-opacity-25 pb-2">
                                <i class="bi bi-people-fill"></i> 2. Data Orang Tua / Wali
                            </h5>
                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Nama Orang Tua / Wali <span class="text-danger">*</span></label>
                                    <input type="text" name="parent_name" class="form-control" placeholder="Contoh: Bapak Hendra Santoso" required value="<?= htmlspecialchars($_POST['parent_name'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Nomor WhatsApp Aktif <span class="text-danger">*</span></label>
                                    <input type="tel" name="parent_phone" class="form-control" placeholder="Contoh: 081288889999" required value="<?= htmlspecialchars($_POST['parent_phone'] ?? '') ?>">
                                    <div class="form-text text-muted">Nomor ini akan digunakan untuk notifikasi skor &amp; verifikasi login.</div>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Email (Opsional)</label>
                                    <input type="email" name="parent_email" class="form-control" placeholder="Contoh: orangtua@gmail.com" value="<?= htmlspecialchars($_POST['parent_email'] ?? '') ?>">
                                </div>
                            </div>

                            <!-- Section 3: Pilihan Kelas & Perlengkapan -->
                            <h5 class="fw-bold text-success mb-3 d-flex align-items-center gap-2 border-bottom border-secondary border-opacity-25 pb-2">
                                <i class="bi bi-bullseye"></i> 3. Program Kursus &amp; Peralatan
                            </h5>
                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Pilihan Program Latihan <span class="text-danger">*</span></label>
                                    <select name="program_choice" class="form-select" required>
                                        <?php if (empty($programs)): ?>
                                            <option value="Kelas Pemula Dasar">Kelas Pemula Dasar (Standard Bow)</option>
                                        <?php else: ?>
                                            <?php foreach ($programs as $prog): ?>
                                                <option value="<?= htmlspecialchars($prog['program_name']) ?>" <?= ($prefillProgram === $prog['program_name']) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($prog['program_name']) ?> (<?= htmlspecialchars($prog['fee_info']) ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Kepemilikan Busur Panah</label>
                                    <div class="form-check mt-2">
                                        <input class="form-check-input" type="checkbox" name="has_own_bow" value="1" id="ownBowCheck" <?= !empty($_POST['has_own_bow']) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="ownBowCheck">
                                            Sudah memiliki busur panah sendiri
                                        </label>
                                    </div>
                                    <div class="form-text text-muted small">Jika belum punya, klub menyediakan busur pinjaman untuk latihan awal.</div>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Catatan Tambahan / Pengalaman Sebelumnya</label>
                                    <textarea name="notes" class="form-control" rows="3" placeholder="Ceritakan riwayat latihan ananda atau preferensi jadwal latihan..."><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
                                </div>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-warning btn-lg fw-bold text-dark py-3 shadow">
                                    <i class="bi bi-send-fill"></i> Kirim Formulir Pendaftaran
                                </button>
                                <a href="index.php?club=<?= urlencode($currentTenant['subdomain_slug']) ?>" class="btn btn-outline-secondary">
                                    Batal &amp; Kembali
                                </a>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- FOOTER -->
    <footer class="py-4 text-center text-muted small border-top" style="background: var(--bg-card); border-color: var(--border-color) !important;">
        <div class="container">
            &copy; <?= date('Y') ?> <strong><?= htmlspecialchars($currentTenant['club_name']) ?></strong> &bull; Formulir Pendaftaran Atlet Resmi
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleAppTheme() {
            const current = document.documentElement.getAttribute('data-theme') || 'dark';
            const next = current === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', next);
            localStorage.setItem('archery_theme', next);
            updateThemeIcons(next);
        }

        function updateThemeIcons(theme) {
            const icon = theme === 'dark' ? '🌙' : '☀️';
            const btn = document.getElementById('themeToggleBtn');
            if (btn) btn.textContent = icon;
        }

        document.addEventListener('DOMContentLoaded', () => {
            const current = document.documentElement.getAttribute('data-theme') || 'dark';
            updateThemeIcons(current);
        });
    </script>
</body>
</html>
