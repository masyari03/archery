<?php
// cms_admin.php - Panel Manajemen Web CMS Profil Klub untuk Super Admin
require_once __DIR__ . '/config/auth.php';
Auth::requireRole(['super_admin', 'master_admin'], 'login.php');

$currentUser = Auth::user();
$clubId = (int)($currentUser['club_id'] ?? 1);
$currentRole = $_SESSION['role'] ?? 'super_admin';

// Ambil Profil Klub
$stmtClub = $pdo->prepare("SELECT * FROM archery_clubs WHERE id = :cid LIMIT 1");
$stmtClub->execute([':cid' => $clubId]);
$club = $stmtClub->fetch();

$alertMsg = '';
$alertType = 'success';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. Simpan Konten Utama CMS (Hero & About & Filosofi)
    if ($action === 'save_main_content') {
        $heroHeadline = trim($_POST['hero_headline'] ?? '');
        $heroSubheadline = trim($_POST['hero_subheadline'] ?? '');
        $heroCtaText = trim($_POST['hero_cta_text'] ?? '');
        $aboutTitle = trim($_POST['about_title'] ?? '');
        $aboutContent = trim($_POST['about_content'] ?? '');
        $aboutPoints = trim($_POST['about_points'] ?? '');
        $philosophyTitle = trim($_POST['philosophy_title'] ?? '');
        $philosophyContent = trim($_POST['philosophy_content'] ?? '');
        $instagramUrl = trim($_POST['instagram_url'] ?? '');
        $youtubeUrl = trim($_POST['youtube_url'] ?? '');

        try {
            $stmtSave = $pdo->prepare("
                INSERT INTO `archery_cms_content`
                (`club_id`, `hero_headline`, `hero_subheadline`, `hero_cta_text`, `about_title`, `about_content`, `about_points`, `philosophy_title`, `philosophy_content`, `instagram_url`, `youtube_url`)
                VALUES
                (:cid, :hhead, :hsub, :hcta, :atitle, :acont, :apoints, :ptitle, :pcont, :ig, :yt)
                ON DUPLICATE KEY UPDATE
                `hero_headline`=VALUES(`hero_headline`),
                `hero_subheadline`=VALUES(`hero_subheadline`),
                `hero_cta_text`=VALUES(`hero_cta_text`),
                `about_title`=VALUES(`about_title`),
                `about_content`=VALUES(`about_content`),
                `about_points`=VALUES(`about_points`),
                `philosophy_title`=VALUES(`philosophy_title`),
                `philosophy_content`=VALUES(`philosophy_content`),
                `instagram_url`=VALUES(`instagram_url`),
                `youtube_url`=VALUES(`youtube_url`)
            ");
            $stmtSave->execute([
                ':cid' => $clubId,
                ':hhead' => $heroHeadline,
                ':hsub' => $heroSubheadline,
                ':hcta' => $heroCtaText,
                ':atitle' => $aboutTitle,
                ':acont' => $aboutContent,
                ':apoints' => $aboutPoints,
                ':ptitle' => $philosophyTitle,
                ':pcont' => $philosophyContent,
                ':ig' => $instagramUrl ?: null,
                ':yt' => $youtubeUrl ?: null
            ]);
            $alertMsg = 'Konten profil Web CMS berhasil disimpan!';
        } catch (PDOException $e) {
            $alertMsg = 'Gagal menyimpan konten CMS: ' . $e->getMessage();
            $alertType = 'danger';
        }
    }

    // 2. Tambah Program Kursus Baru
    if ($action === 'add_program') {
        $progName = trim($_POST['program_name'] ?? '');
        $audience = trim($_POST['target_audience'] ?? '');
        $bowType = trim($_POST['bow_type'] ?? '');
        $feeInfo = trim($_POST['fee_info'] ?? '');
        $freq = trim($_POST['session_frequency'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $equipment = !empty($_POST['equipment_included']) ? 1 : 0;

        if ($progName) {
            $stmtAddP = $pdo->prepare("
                INSERT INTO `archery_cms_programs`
                (`club_id`, `program_name`, `target_audience`, `bow_type`, `fee_info`, `session_frequency`, `description`, `equipment_included`, `order_num`)
                VALUES
                (:cid, :pname, :aud, :bow, :fee, :freq, :desc, :eq, 1)
            ");
            $stmtAddP->execute([
                ':cid' => $clubId,
                ':pname' => $progName,
                ':aud' => $audience,
                ':bow' => $bowType,
                ':fee' => $feeInfo,
                ':freq' => $freq,
                ':desc' => $desc,
                ':eq' => $equipment
            ]);
            $alertMsg = 'Program kursus baru berhasil ditambahkan!';
        }
    }

    // 3. Hapus Program Kursus
    if ($action === 'delete_program') {
        $progId = (int)($_POST['program_id'] ?? 0);
        $stmtDelP = $pdo->prepare("DELETE FROM archery_cms_programs WHERE id = :pid AND club_id = :cid");
        $stmtDelP->execute([':pid' => $progId, ':cid' => $clubId]);
        $alertMsg = 'Program kursus berhasil dihapus.';
    }

    // 4. Tambah Jadwal Latihan Baru
    if ($action === 'add_schedule') {
        $dayName = trim($_POST['day_name'] ?? '');
        $timeRange = trim($_POST['time_range'] ?? '');
        $sessionType = trim($_POST['session_type'] ?? '');
        $rangeLoc = trim($_POST['range_location'] ?? '');
        $coachInCharge = trim($_POST['coach_in_charge'] ?? '');

        if ($dayName && $timeRange) {
            $stmtAddS = $pdo->prepare("
                INSERT INTO `archery_cms_schedules`
                (`club_id`, `day_name`, `time_range`, `session_type`, `range_location`, `coach_in_charge`, `order_num`)
                VALUES
                (:cid, :day, :time, :stype, :loc, :coach, 1)
            ");
            $stmtAddS->execute([
                ':cid' => $clubId,
                ':day' => $dayName,
                ':time' => $timeRange,
                ':stype' => $sessionType,
                ':loc' => $rangeLoc,
                ':coach' => $coachInCharge
            ]);
            $alertMsg = 'Jadwal latihan mingguan berhasil ditambahkan!';
        }
    }

    // 5. Hapus Jadwal Latihan
    if ($action === 'delete_schedule') {
        $schedId = (int)($_POST['schedule_id'] ?? 0);
        $stmtDelS = $pdo->prepare("DELETE FROM archery_cms_schedules WHERE id = :sid AND club_id = :cid");
        $stmtDelS->execute([':sid' => $schedId, ':cid' => $clubId]);
        $alertMsg = 'Jadwal latihan berhasil dihapus.';
    }

    // 6. Tambah Galeri Foto
    if ($action === 'add_gallery') {
        $title = trim($_POST['title'] ?? '');
        $imgUrl = trim($_POST['image_url'] ?? '');
        $cat = in_array($_POST['category'] ?? '', ['fasilitas', 'latihan', 'turnamen', 'prestasi']) ? $_POST['category'] : 'latihan';

        if ($title && $imgUrl) {
            $stmtAddG = $pdo->prepare("
                INSERT INTO `archery_cms_galleries` (`club_id`, `title`, `image_url`, `category`, `order_num`)
                VALUES (:cid, :title, :url, :cat, 1)
            ");
            $stmtAddG->execute([':cid' => $clubId, ':title' => $title, ':url' => $imgUrl, ':cat' => $cat]);
            $alertMsg = 'Foto dokumentasi galeri berhasil ditambahkan!';
        }
    }

    // 7. Hapus Galeri
    if ($action === 'delete_gallery') {
        $galId = (int)($_POST['gallery_id'] ?? 0);
        $stmtDelG = $pdo->prepare("DELETE FROM archery_cms_galleries WHERE id = :gid AND club_id = :cid");
        $stmtDelG->execute([':gid' => $galId, ':cid' => $clubId]);
        $alertMsg = 'Foto galeri berhasil dihapus.';
    }

    // 8. Update Status Pendaftaran Atlet Masuk
    if ($action === 'update_registration_status') {
        $regId = (int)($_POST['registration_id'] ?? 0);
        $newStatus = in_array($_POST['status'] ?? '', ['pending', 'verified', 'rejected']) ? $_POST['status'] : 'pending';
        $stmtUpdReg = $pdo->prepare("UPDATE archery_cms_registrations SET status = :st WHERE id = :rid AND club_id = :cid");
        $stmtUpdReg->execute([':st' => $newStatus, ':rid' => $regId, ':cid' => $clubId]);
        $alertMsg = 'Status pendaftaran atlet berhasil diubah menjadi: ' . strtoupper($newStatus);
    }

    // 9. Simpan Pengaturan SEO & Google Ranking
    if ($action === 'save_seo_settings') {
        $metaTitle = trim($_POST['meta_title'] ?? '');
        $metaDesc = trim($_POST['meta_description'] ?? '');
        $metaKw = trim($_POST['meta_keywords'] ?? '');
        $ogImg = trim($_POST['og_image_url'] ?? '');
        $gVerify = trim($_POST['google_site_verification'] ?? '');

        try {
            $stmtSeo = $pdo->prepare("
                UPDATE `archery_cms_content` SET
                `meta_title` = :mtitle,
                `meta_description` = :mdesc,
                `meta_keywords` = :mkw,
                `og_image_url` = :ogimg,
                `google_site_verification` = :gver
                WHERE `club_id` = :cid
            ");
            $stmtSeo->execute([
                ':mtitle' => $metaTitle ?: null,
                ':mdesc'  => $metaDesc ?: null,
                ':mkw'    => $metaKw ?: null,
                ':ogimg'  => $ogImg ?: null,
                ':gver'   => $gVerify ?: null,
                ':cid'    => $clubId
            ]);
            $alertMsg = 'Pengaturan SEO Google & OpenGraph berhasil diperbarui!';
        } catch (PDOException $e) {
            $alertMsg = 'Gagal menyimpan SEO: ' . $e->getMessage();
            $alertType = 'danger';
        }
    }

    // 10. Simpan Pengaturan Custom Domain
    if ($action === 'save_domain_settings') {
        $customDomain = trim(strtolower($_POST['custom_domain'] ?? ''));
        // Bersihkan https:// atau http:// jika user memasukkannya
        $customDomain = preg_replace('#^https?://#', '', $customDomain);
        $customDomain = rtrim($customDomain, '/');

        try {
            $stmtDom = $pdo->prepare("UPDATE `archery_clubs` SET `custom_domain` = :dom WHERE `id` = :cid");
            $stmtDom->execute([
                ':dom' => $customDomain ?: null,
                ':cid' => $clubId
            ]);
            $club['custom_domain'] = $customDomain ?: null;
            $alertMsg = 'Pengaturan domain berhasil diperbarui!';
        } catch (PDOException $e) {
            $alertMsg = 'Gagal menyimpan domain: ' . $e->getMessage();
            $alertType = 'danger';
        }
    }
}

// Ambil Data Konten CMS Saat Ini
$stmtCms = $pdo->prepare("SELECT * FROM archery_cms_content WHERE club_id = :cid LIMIT 1");
$stmtCms->execute([':cid' => $clubId]);
$cms = $stmtCms->fetch() ?: [];

// Ambil Program
$stmtProg = $pdo->prepare("SELECT * FROM archery_cms_programs WHERE club_id = :cid ORDER BY order_num ASC, id ASC");
$stmtProg->execute([':cid' => $clubId]);
$programs = $stmtProg->fetchAll();

// Ambil Jadwal
$stmtSched = $pdo->prepare("SELECT * FROM archery_cms_schedules WHERE club_id = :cid ORDER BY order_num ASC, id ASC");
$stmtSched->execute([':cid' => $clubId]);
$schedules = $stmtSched->fetchAll();

// Ambil Galeri
$stmtGal = $pdo->prepare("SELECT * FROM archery_cms_galleries WHERE club_id = :cid ORDER BY order_num ASC, id ASC");
$stmtGal->execute([':cid' => $clubId]);
$galleries = $stmtGal->fetchAll();

// Ambil Pendaftaran Masuk
$stmtRegs = $pdo->prepare("SELECT * FROM archery_cms_registrations WHERE club_id = :cid ORDER BY created_at DESC");
$stmtRegs->execute([':cid' => $clubId]);
$registrations = $stmtRegs->fetchAll();

$pageTitle = "Manajemen Web CMS - " . htmlspecialchars($club['club_name'] ?? 'Klub');
require_once __DIR__ . '/views/header.php';
?>

<div class="container pb-5">
    <div class="d-flex justify-content-between align-items-center mb-4 mt-2">
        <div>
            <h3 class="fw-bold mb-1 d-flex align-items-center gap-2">
                <i class="bi bi-window-desktop text-info"></i> Manajemen Web Profil CMS
            </h3>
            <p class="text-muted mb-0">
                Kelola konten halaman depan publik, kurikulum program kursus, jadwal latihan, dan verifikasi pendaftaran atlet baru untuk <strong><?= htmlspecialchars($club['club_name']) ?></strong>.
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="index.php?club=<?= urlencode($club['subdomain_slug'] ?? '') ?>" target="_blank" class="btn btn-warning fw-bold text-dark d-flex align-items-center gap-2 shadow-sm">
                <i class="bi bi-box-arrow-up-right"></i> Lihat Web Publik
            </a>
        </div>
    </div>

    <?php if ($alertMsg): ?>
        <div class="alert alert-<?= $alertType ?> alert-dismissible fade show d-flex align-items-center gap-2 mb-4" role="alert">
            <i class="bi bi-check-circle-fill fs-5"></i>
            <div><?= htmlspecialchars($alertMsg) ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Nav Tabs CMS -->
    <ul class="nav nav-tabs border-secondary mb-4" id="cmsTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active text-white fw-bold" id="main-tab" data-bs-toggle="tab" data-bs-target="#tab-main" type="button" role="tab">
                <i class="bi bi-sliders"></i> Hero &amp; Profil Utama
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link text-white fw-bold" id="programs-tab" data-bs-toggle="tab" data-bs-target="#tab-programs" type="button" role="tab">
                <i class="bi bi-bullseye text-warning"></i> Program Kursus (<?= count($programs) ?>)
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link text-white fw-bold" id="schedules-tab" data-bs-toggle="tab" data-bs-target="#tab-schedules" type="button" role="tab">
                <i class="bi bi-calendar3 text-info"></i> Jadwal Lapangan (<?= count($schedules) ?>)
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link text-white fw-bold" id="galleries-tab" data-bs-toggle="tab" data-bs-target="#tab-galleries" type="button" role="tab">
                <i class="bi bi-images text-success"></i> Galeri Dokumentasi (<?= count($galleries) ?>)
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link text-white fw-bold" id="registrations-tab" data-bs-toggle="tab" data-bs-target="#tab-registrations" type="button" role="tab">
                <i class="bi bi-person-plus-fill text-danger"></i> Pendaftaran Masuk (<?= count($registrations) ?>)
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link text-white fw-bold" id="seo-tab" data-bs-toggle="tab" data-bs-target="#tab-seo" type="button" role="tab">
                <i class="bi bi-google text-warning"></i> Mesin SEO &amp; Google Ranking
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link text-white fw-bold" id="domain-tab" data-bs-toggle="tab" data-bs-target="#tab-domain" type="button" role="tab">
                <i class="bi bi-globe text-info"></i> Domain &amp; Subdomain
            </button>
        </li>
        <li class="nav-item ms-auto" role="presentation">
            <a href="admin_rbac.php" class="btn btn-outline-warning btn-sm fw-bold py-2 px-3">
                <i class="bi bi-shield-lock-fill"></i> Atur Hak Akses RBAC Header
            </a>
        </li>
    </ul>

    <!-- Tab Contents -->
    <div class="tab-content" id="cmsTabContent">
        
        <!-- TAB 1: HERO & PROFIL UTAMA -->
        <div class="tab-pane fade show active" id="tab-main" role="tabpanel">
            <div class="card p-4 border-secondary shadow-sm">
                <form action="" method="POST">
                    <input type="hidden" name="action" value="save_main_content">

                    <h5 class="fw-bold text-info mb-3"><i class="bi bi-image"></i> 1. Hero Banner Atas</h5>
                    <div class="row g-3 mb-4">
                        <div class="col-md-8">
                            <label class="form-label fw-bold">Headline Judul Utama (Hero)</label>
                            <input type="text" name="hero_headline" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($cms['hero_headline'] ?? 'Pusat Pembinaan Karakter & Prestasi Panahan Modern Berstandar World Archery') ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Teks Tombol CTA</label>
                            <input type="text" name="hero_cta_text" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($cms['hero_cta_text'] ?? 'Daftar Calon Santri / Atlet') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">Sub-Headline Penjelas</label>
                            <textarea name="hero_subheadline" rows="2" class="form-control bg-dark text-white border-secondary"><?= htmlspecialchars($cms['hero_subheadline'] ?? 'Menghidupkan olahraga sunnah dengan pendekatan modern, pelatih berlisensi, analisis scoring digital presisi, dan fasilitas target range 5m - 50m lengkap.') ?></textarea>
                        </div>
                    </div>

                    <h5 class="fw-bold text-warning mb-3"><i class="bi bi-info-circle"></i> 2. Tentang Klub &amp; Keunggulan</h5>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Judul Bagian Tentang</label>
                            <input type="text" name="about_title" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($cms['about_title'] ?? 'Membangun Generasi Tangguh Berakhlak Mulia') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Poin Keunggulan (Pisahkan dengan koma)</label>
                            <input type="text" name="about_points" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($cms['about_points'] ?? 'Pelatih Berlisensi Resmi,Fasilitas Indoor & Outdoor Standar WA,Kurikulum Berjenjang & Uji Kenaikan Jarak,Sistem Plotting Digital Real-Time,Peralatan Busur Lengkap Disediakan') ?>">
                            <div class="form-text text-muted">Contoh: Poin 1, Poin 2, Poin 3</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">Deskripsi Profil Klub</label>
                            <textarea name="about_content" rows="4" class="form-control bg-dark text-white border-secondary"><?= htmlspecialchars($cms['about_content'] ?? '') ?></textarea>
                        </div>
                    </div>

                    <h5 class="fw-bold text-success mb-3"><i class="bi bi-moon-stars"></i> 3. Filosofi Sunnah &amp; Nilai Karakter</h5>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Judul Filosofi</label>
                            <input type="text" name="philosophy_title" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($cms['philosophy_title'] ?? '3 Pilar Panahan: Ketenangan, Fokus, & Konsistensi') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">Uraian Filosofi Memanah</label>
                            <textarea name="philosophy_content" rows="3" class="form-control bg-dark text-white border-secondary"><?= htmlspecialchars($cms['philosophy_content'] ?? '') ?></textarea>
                        </div>
                    </div>

                    <h5 class="fw-bold text-light mb-3"><i class="bi bi-share"></i> 4. Media Sosial &amp; Channel</h5>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label">Instagram Link</label>
                            <input type="url" name="instagram_url" class="form-control bg-dark text-white border-secondary" placeholder="https://instagram.com/..." value="<?= htmlspecialchars($cms['instagram_url'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">YouTube Link</label>
                            <input type="url" name="youtube_url" class="form-control bg-dark text-white border-secondary" placeholder="https://youtube.com/@..." value="<?= htmlspecialchars($cms['youtube_url'] ?? '') ?>">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-warning fw-bold text-dark px-4 py-2">
                        <i class="bi bi-save"></i> Simpan Perubahan Profil CMS
                    </button>
                </form>
            </div>
        </div>

        <!-- TAB 2: PROGRAM KURSUS -->
        <div class="tab-pane fade" id="tab-programs" role="tabpanel">
            <div class="card p-4 border-secondary shadow-sm mb-4">
                <h5 class="fw-bold text-warning mb-3"><i class="bi bi-plus-circle"></i> Tambah Program Kursus Baru</h5>
                <form action="" method="POST">
                    <input type="hidden" name="action" value="add_program">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Nama Program <span class="text-danger">*</span></label>
                            <input type="text" name="program_name" class="form-control bg-dark text-white border-secondary" placeholder="Contoh: Kelas Junior Foundation (Pemula)" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Sasaran Usia</label>
                            <input type="text" name="target_audience" class="form-control bg-dark text-white border-secondary" placeholder="Contoh: Usia 6 - 12 Tahun">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Kategori Busur</label>
                            <input type="text" name="bow_type" class="form-control bg-dark text-white border-secondary" placeholder="Contoh: Standard Bow & Barebow">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Biaya / Iuran</label>
                            <input type="text" name="fee_info" class="form-control bg-dark text-white border-secondary" placeholder="Contoh: Rp 250.000 / Bulan">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Frekuensi Latihan</label>
                            <input type="text" name="session_frequency" class="form-control bg-dark text-white border-secondary" placeholder="Contoh: 1x Sepekan (Sabtu/Ahad)">
                        </div>
                        <div class="col-md-4 d-flex align-items-center mt-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="equipment_included" value="1" id="eqCheck" checked>
                                <label class="form-check-label text-light fw-bold" for="eqCheck">
                                    Busur &amp; Target Disediakan Klub
                                </label>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">Deskripsi &amp; Kurikulum</label>
                            <textarea name="description" rows="2" class="form-control bg-dark text-white border-secondary" placeholder="Jelaskan fokus materi dan jenjang jarak..."></textarea>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-warning fw-bold text-dark px-4">
                                <i class="bi bi-plus-lg"></i> Tambah Program
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- List Programs -->
            <div class="row g-3">
                <?php foreach ($programs as $prog): ?>
                    <div class="col-md-6">
                        <div class="card p-3 border-secondary h-100 d-flex flex-column justify-content-between">
                            <div>
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h5 class="fw-bold text-white mb-0"><?= htmlspecialchars($prog['program_name']) ?></h5>
                                    <form action="" method="POST" onsubmit="return confirm('Hapus program kursus ini?')">
                                        <input type="hidden" name="action" value="delete_program">
                                        <input type="hidden" name="program_id" value="<?= $prog['id'] ?>">
                                        <button type="submit" class="btn btn-outline-danger btn-sm py-0 px-2">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                                <span class="badge bg-primary me-1"><?= htmlspecialchars($prog['target_audience']) ?></span>
                                <span class="badge bg-secondary me-1"><?= htmlspecialchars($prog['bow_type']) ?></span>
                                <?php if ($prog['equipment_included']): ?>
                                    <span class="badge bg-success">Busur Tersedia</span>
                                <?php endif; ?>
                                <p class="text-muted small mt-3 mb-2"><?= nl2br(htmlspecialchars($prog['description'])) ?></p>
                            </div>
                            <div class="pt-2 border-top border-secondary border-opacity-25 d-flex justify-content-between align-items-center">
                                <span class="text-muted small"><?= htmlspecialchars($prog['session_frequency']) ?></span>
                                <strong class="text-warning fs-6"><?= htmlspecialchars($prog['fee_info']) ?></strong>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- TAB 3: JADWAL LAPANGAN -->
        <div class="tab-pane fade" id="tab-schedules" role="tabpanel">
            <div class="card p-4 border-secondary shadow-sm mb-4">
                <h5 class="fw-bold text-info mb-3"><i class="bi bi-plus-circle"></i> Tambah Jadwal Latihan Mingguan</h5>
                <form action="" method="POST">
                    <input type="hidden" name="action" value="add_schedule">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Hari Latihan <span class="text-danger">*</span></label>
                            <input type="text" name="day_name" class="form-control bg-dark text-white border-secondary" placeholder="Contoh: Sabtu Pagi" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Jam Sesi <span class="text-danger">*</span></label>
                            <input type="text" name="time_range" class="form-control bg-dark text-white border-secondary" placeholder="Contoh: 08:00 - 10:00 WIB" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Tipe Sesi &amp; Peserta</label>
                            <input type="text" name="session_type" class="form-control bg-dark text-white border-secondary" placeholder="Contoh: Kelas Pemula & Junior (5m - 7m)">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Lokasi Line / Lapangan</label>
                            <input type="text" name="range_location" class="form-control bg-dark text-white border-secondary" placeholder="Contoh: Lapangan Target Utama (Outdoor Range)">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Coach Penanggung Jawab</label>
                            <input type="text" name="coach_in_charge" class="form-control bg-dark text-white border-secondary" placeholder="Contoh: Coach Fadli Archery">
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-info fw-bold text-dark px-4">
                                <i class="bi bi-plus-lg"></i> Tambah Jadwal Sesi
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- List Schedules -->
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>Hari</th>
                            <th>Jam</th>
                            <th>Tipe Sesi</th>
                            <th>Lokasi Lapangan</th>
                            <th>Coach</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($schedules as $s): ?>
                            <tr>
                                <td class="fw-bold text-white"><?= htmlspecialchars($s['day_name']) ?></td>
                                <td><span class="badge bg-warning text-dark"><?= htmlspecialchars($s['time_range']) ?></span></td>
                                <td class="text-info"><?= htmlspecialchars($s['session_type']) ?></td>
                                <td class="text-muted small"><?= htmlspecialchars($s['range_location']) ?></td>
                                <td><i class="bi bi-person-badge"></i> <?= htmlspecialchars($s['coach_in_charge']) ?></td>
                                <td class="text-end">
                                    <form action="" method="POST" onsubmit="return confirm('Hapus jadwal ini?')">
                                        <input type="hidden" name="action" value="delete_schedule">
                                        <input type="hidden" name="schedule_id" value="<?= $s['id'] ?>">
                                        <button type="submit" class="btn btn-outline-danger btn-sm py-0 px-2">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- TAB 4: GALERI DOKUMENTASI -->
        <div class="tab-pane fade" id="tab-galleries" role="tabpanel">
            <div class="card p-4 border-secondary shadow-sm mb-4">
                <h5 class="fw-bold text-success mb-3"><i class="bi bi-plus-circle"></i> Tambah Foto Dokumentasi Galeri</h5>
                <form action="" method="POST">
                    <input type="hidden" name="action" value="add_gallery">
                    <div class="row g-3">
                        <div class="col-md-5">
                            <label class="form-label fw-bold">Judul Foto / Momen <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control bg-dark text-white border-secondary" placeholder="Contoh: Evaluasi Scoring Jarak 18m" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">URL / Path Foto <span class="text-danger">*</span></label>
                            <input type="text" name="image_url" class="form-control bg-dark text-white border-secondary" placeholder="assets/img/... atau URL gambar" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Kategori</label>
                            <select name="category" class="form-select bg-dark text-white border-secondary">
                                <option value="latihan">Latihan Rutin</option>
                                <option value="fasilitas">Fasilitas Range</option>
                                <option value="turnamen">Turnamen</option>
                                <option value="prestasi">Prestasi Juara</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-success fw-bold text-white px-4">
                                <i class="bi bi-upload"></i> Tambah Foto Galeri
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- List Galleries -->
            <div class="row g-3">
                <?php foreach ($galleries as $g): ?>
                    <div class="col-md-3 col-sm-6">
                        <div class="card p-2 border-secondary h-100">
                            <img src="<?= htmlspecialchars($g['image_url']) ?>" class="card-img-top rounded" style="height: 140px; object-fit: cover;" onerror="this.src='https://images.unsplash.com/photo-1511295742362-92c96b124e52?auto=format&fit=crop&w=600&q=80';">
                            <div class="card-body p-2 d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="badge bg-secondary" style="font-size: 10px;"><?= strtoupper($g['category']) ?></span>
                                    <div class="text-white small fw-bold mt-1 text-truncate" style="max-width: 140px;"><?= htmlspecialchars($g['title']) ?></div>
                                </div>
                                <form action="" method="POST" onsubmit="return confirm('Hapus foto ini dari galeri?')">
                                    <input type="hidden" name="action" value="delete_gallery">
                                    <input type="hidden" name="gallery_id" value="<?= $g['id'] ?>">
                                    <button type="submit" class="btn btn-outline-danger btn-sm py-0 px-2">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- TAB 5: PENDAFTARAN MASUK DARI WEB -->
        <div class="tab-pane fade" id="tab-registrations" role="tabpanel">
            <div class="card border-secondary shadow-sm">
                <div class="card-header bg-transparent py-3 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold text-white mb-0 d-flex align-items-center gap-2">
                        <i class="bi bi-inbox-fill text-warning"></i> Data Calon Santri / Atlet yang Mendaftar Online
                    </h5>
                    <span class="badge bg-secondary"><?= count($registrations) ?> Pendaftar</span>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($registrations)): ?>
                        <div class="text-center py-5 text-muted">
                            Belum ada formulir pendaftaran baru yang masuk dari web CMS.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Waktu</th>
                                        <th>Nama Calon Atlet</th>
                                        <th>Orang Tua / Wali</th>
                                        <th>WhatsApp</th>
                                        <th>Pilihan Program</th>
                                        <th>Busur</th>
                                        <th>Status</th>
                                        <th class="text-end">Aksi Verifikasi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($registrations as $reg): 
                                        $cleanPhone = preg_replace('/[^0-9]/', '', $reg['parent_phone']);
                                    ?>
                                        <tr>
                                            <td class="text-muted small">
                                                <?= date('d M Y', strtotime($reg['created_at'])) ?><br>
                                                <?= date('H:i', strtotime($reg['created_at'])) ?> WIB
                                            </td>
                                            <td>
                                                <strong class="text-white"><?= htmlspecialchars($reg['student_name']) ?></strong>
                                                <div class="text-muted small">
                                                    (<?= htmlspecialchars($reg['student_nickname'] ?: '-') ?>) &bull; <?= $reg['gender'] === 'L' ? 'Ikhwan' : 'Akhwat' ?> &bull; Tgl Lahir: <?= $reg['birth_date'] ?: '-' ?>
                                                </div>
                                            </td>
                                            <td><?= htmlspecialchars($reg['parent_name']) ?></td>
                                            <td>
                                                <a href="https://wa.me/<?= $cleanPhone ?>?text=Halo%20Bapak/Ibu%20<?= urlencode($reg['parent_name']) ?>,%20kami%20dari%20<?= urlencode($club['club_name']) ?>%20ingin%20mengonfirmasi%20pendaftaran%20ananda%20<?= urlencode($reg['student_name']) ?>" target="_blank" class="btn btn-outline-success btn-sm d-inline-flex align-items-center gap-1">
                                                    <i class="bi bi-whatsapp"></i> <?= htmlspecialchars($reg['parent_phone']) ?>
                                                </a>
                                            </td>
                                            <td>
                                                <span class="badge bg-info text-dark"><?= htmlspecialchars($reg['program_choice']) ?></span>
                                            </td>
                                            <td>
                                                <?= $reg['has_own_bow'] ? '<span class="badge bg-success">Punya Busur</span>' : '<span class="badge bg-secondary">Pinjam Klub</span>' ?>
                                            </td>
                                            <td>
                                                <?php if ($reg['status'] === 'verified'): ?>
                                                    <span class="badge bg-success">Terverifikasi</span>
                                                <?php elseif ($reg['status'] === 'rejected'): ?>
                                                    <span class="badge bg-danger">Ditolak</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning text-dark">Pending</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end">
                                                <form action="" method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="update_registration_status">
                                                    <input type="hidden" name="registration_id" value="<?= $reg['id'] ?>">
                                                    <?php if ($reg['status'] !== 'verified'): ?>
                                                        <button type="submit" name="status" value="verified" class="btn btn-success btn-sm" title="Verifikasi Pendaftaran">
                                                            <i class="bi bi-check-lg"></i> Verifikasi
                                                        </button>
                                                    <?php else: ?>
                                                        <button type="submit" name="status" value="pending" class="btn btn-outline-secondary btn-sm" title="Kembalikan ke Pending">
                                                            Pending
                                                        </button>
                                                    <?php endif; ?>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <!-- TAB 6: MESIN SEO & GOOGLE RANKING -->
        <div class="tab-pane fade" id="tab-seo" role="tabpanel">
            <div class="row g-4">
                <div class="col-lg-7">
                    <div class="card p-4 border-secondary shadow-sm">
                        <h5 class="fw-bold text-warning mb-3 d-flex align-items-center gap-2">
                            <i class="bi bi-google"></i> Konfigurasi Meta SEO &amp; Google Search
                        </h5>
                        <form action="" method="POST">
                            <input type="hidden" name="action" value="save_seo_settings">

                            <div class="mb-3">
                                <div class="d-flex justify-content-between">
                                    <label class="form-label fw-bold">Meta Title Google <span class="text-danger">*</span></label>
                                    <span class="small text-muted" id="titleCharCount">0/60 karakter</span>
                                </div>
                                <input type="text" name="meta_title" id="seoInputTitle" class="form-control bg-dark text-white border-secondary" placeholder="Contoh: <?= htmlspecialchars($club['club_name']) ?> - Klub Panahan &amp; Les Memanah Anak di <?= htmlspecialchars($club['city']) ?>" value="<?= htmlspecialchars($cms['meta_title'] ?? '') ?>" oninput="updateGooglePreview()" required>
                                <div class="form-text text-muted">Judul utama yang muncul di Google. Disarankan menyertakan nama klub dan kota.</div>
                            </div>

                            <div class="mb-3">
                                <div class="d-flex justify-content-between">
                                    <label class="form-label fw-bold">Meta Description Google <span class="text-danger">*</span></label>
                                    <span class="small text-muted" id="descCharCount">0/160 karakter</span>
                                </div>
                                <textarea name="meta_description" id="seoInputDesc" rows="3" class="form-control bg-dark text-white border-secondary" placeholder="Contoh: Pusat pelatihan panahan anak &amp; dewasa berstandar World Archery di <?= htmlspecialchars($club['city']) ?>. Pelatih berlisensi resmi, busur dipinjamkan, dan kurikulum kenaikan jarak teruji." oninput="updateGooglePreview()" required><?= htmlspecialchars($cms['meta_description'] ?? '') ?></textarea>
                                <div class="form-text text-muted">Deskripsi rangkuman hasil pencarian Google. Usahakan persuasif dan mengundang klik orang tua.</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">Target Kata Kunci (Keywords)</label>
                                <input type="text" name="meta_keywords" id="seoInputKeywords" class="form-control bg-dark text-white border-secondary" placeholder="les panahan anak <?= htmlspecialchars($club['city']) ?>, klub panahan sunnah, kursus memanah pemula" value="<?= htmlspecialchars($cms['meta_keywords'] ?? '') ?>">
                                <div class="form-text text-muted mb-2">Pisahkan dengan koma. Kata kunci pencarian yang sering diketik oleh orang tua.</div>
                                <div class="d-flex flex-wrap gap-1">
                                    <button type="button" class="btn btn-outline-secondary btn-sm py-0" onclick="addKeyword('les panahan anak <?= htmlspecialchars($club['city']) ?>')">+ Les Anak</button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm py-0" onclick="addKeyword('klub panahan <?= htmlspecialchars($club['city']) ?>')">+ Klub Panahan</button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm py-0" onclick="addKeyword('olahraga sunnah memanah')">+ Olahraga Sunnah</button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm py-0" onclick="addKeyword('privat panahan berstandar WA')">+ Standar WA</button>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">URL Banner Share Medsos (OpenGraph Image)</label>
                                <input type="text" name="og_image_url" class="form-control bg-dark text-white border-secondary" placeholder="assets/img/... atau URL gambar (Rekomendasi 1200x630 px)" value="<?= htmlspecialchars($cms['og_image_url'] ?? '') ?>">
                                <div class="form-text text-muted">Gambar yang muncul otomatis saat link web klub Anda dibagikan ke WhatsApp atau Facebook.</div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-bold">Google Site Verification Code (Search Console)</label>
                                <input type="text" name="google_site_verification" class="form-control bg-dark text-white border-secondary" placeholder="Contoh: google-site-verification=abcde12345..." value="<?= htmlspecialchars($cms['google_site_verification'] ?? '') ?>">
                                <div class="form-text text-muted">Jika Anda menghubungkan Google Search Console untuk melacak ranking kata kunci.</div>
                            </div>

                            <button type="submit" class="btn btn-warning fw-bold text-dark px-4 py-2 shadow-sm">
                                <i class="bi bi-save-fill"></i> Simpan Pengaturan SEO
                            </button>
                        </form>
                    </div>
                </div>

                <!-- LIVE GOOGLE SERP PREVIEW BOX -->
                <div class="col-lg-5">
                    <div class="card p-4 border-secondary shadow-sm mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="fw-bold text-white mb-0 d-flex align-items-center gap-2">
                                <i class="bi bi-search text-info"></i> Pratinjau Tampilan di Google
                            </h6>
                            <span class="badge bg-success">Live SERP Preview</span>
                        </div>

                        <!-- Kotak Hasil Pencarian Google Realistis -->
                        <div class="p-3 rounded" style="background: #ffffff; color: #1f1f1f; font-family: Arial, sans-serif;">
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <div class="rounded-circle bg-light border d-flex align-items-center justify-content-center text-dark" style="width: 24px; height: 24px; font-size: 13px;">
                                    🎯
                                </div>
                                <div>
                                    <div style="font-size: 13px; color: #202124; font-weight: 500;"><?= htmlspecialchars($club['club_name']) ?></div>
                                    <div style="font-size: 11px; color: #4d5156;" id="previewUrl">
                                        <?= htmlspecialchars(TenantResolver::getBaseUrl($club)) ?>
                                    </div>
                                </div>
                            </div>

                            <h5 style="color: #1a0dab; font-size: 18px; line-height: 1.3; margin-bottom: 4px; font-weight: 400; cursor: pointer; text-decoration: none;" id="previewTitle">
                                <?= htmlspecialchars($cms['meta_title'] ?: ($club['club_name'] . ' - Pusat Pelatihan Panahan di ' . $club['city'])) ?>
                            </h5>

                            <p style="color: #4d5156; font-size: 13px; line-height: 1.5; margin-bottom: 0;" id="previewDesc">
                                <?= htmlspecialchars($cms['meta_description'] ?: ($cms['hero_subheadline'] ?? 'Program kursus panahan berstandar World Archery dengan pelatih berlisensi resmi.')) ?>
                            </p>
                        </div>

                        <!-- Structured Data Schema.org Active Badge -->
                        <div class="alert alert-dark border-secondary mt-3 mb-0 py-2 px-3 small">
                            <div class="d-flex align-items-center gap-2 text-success fw-bold mb-1">
                                <i class="bi bi-check-circle-fill"></i> Schema.org / SportsClub Aktif Otomatis
                            </div>
                            <div class="text-muted" style="font-size: 11px;">
                                Sistem menyuntikkan data terstruktur JSON-LD lokasi lapangan, koordinat GPS, nomor WhatsApp, dan jam buka agar muncul di Google Maps &amp; Knowledge Panel.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB 7: DOMAIN & SUBDOMAIN KLUB -->
        <div class="tab-pane fade" id="tab-domain" role="tabpanel">
            <div class="row g-4">
                <div class="col-lg-7">
                    <div class="card p-4 border-secondary shadow-sm">
                        <h5 class="fw-bold text-info mb-3 d-flex align-items-center gap-2">
                            <i class="bi bi-globe2"></i> Hubungkan Domain Sendiri (Custom Domain)
                        </h5>
                        <p class="text-muted small mb-4">
                            Jika klub Anda memiliki nama domain sendiri (contoh: <code>annahlarchery.com</code> atau <code>focusarchery.id</code>), Anda bisa memasangnya di sini agar website klub tampil sepenuhnya memakai brand domain Anda sendiri.
                        </p>

                        <form action="" method="POST">
                            <input type="hidden" name="action" value="save_domain_settings">

                            <div class="mb-3">
                                <label class="form-label text-muted small">Subdomain Bawaan Platform (Selalu Aktif):</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-dark text-muted border-secondary">https://</span>
                                    <input type="text" class="form-control bg-dark text-white border-secondary fw-bold" value="<?= htmlspecialchars($club['subdomain_slug']) ?>.codika.my.id" readonly>
                                    <span class="input-group-text bg-success bg-opacity-25 text-success border-secondary">
                                        <i class="bi bi-shield-check"></i> SSL Aktif (Level 1)
                                    </span>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-bold">Domain Kustom Pribadi Milik Klub (Opsional):</label>
                                <input type="text" name="custom_domain" class="form-control bg-dark text-white border-secondary fs-5" placeholder="contoh: annahlarchery.com" value="<?= htmlspecialchars($club['custom_domain'] ?? '') ?>">
                                <div class="form-text text-muted">Kosongkan jika belum memiliki domain pribadi dan ingin tetap menggunakan subdomain bawaan platform.</div>
                            </div>

                            <button type="submit" class="btn btn-info fw-bold text-dark px-4 py-2 shadow-sm">
                                <i class="bi bi-save-fill"></i> Simpan Pengaturan Domain
                            </button>
                        </form>
                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="card p-4 border-secondary shadow-sm">
                        <h6 class="fw-bold text-warning mb-3 d-flex align-items-center gap-2">
                            <i class="bi bi-info-circle-fill"></i> Panduan Pengaturan DNS Domain Anda
                        </h6>
                        <p class="text-muted small mb-3">
                            Jika Anda memasang domain kustom, silakan buka panel DNS domain Anda (di Niagahoster, Domainesia, Cloudflare, dll) dan buat catatan berikut:
                        </p>

                        <div class="table-responsive">
                            <table class="table table-sm table-dark border-secondary small mb-3">
                                <thead>
                                    <tr>
                                        <th>Tipe DNS</th>
                                        <th>Nama Host</th>
                                        <th>Target Nilai</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><span class="badge bg-primary">CNAME</span></td>
                                        <td><code>@</code> atau <code>www</code></td>
                                        <td><code>archery.codika.my.id</code></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="alert alert-dark border-secondary py-2 px-3 small text-muted mb-0" style="font-size: 11px;">
                            💡 Setelah CNAME diarahkan, sistem Target Archery akan otomatis mengenali domain Anda dan mengaktifkan sertifikat SSL HTTPS gratis.
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
function updateGooglePreview() {
    const inputTitle = document.getElementById('seoInputTitle').value;
    const inputDesc = document.getElementById('seoInputDesc').value;
    const previewTitle = document.getElementById('previewTitle');
    const previewDesc = document.getElementById('previewDesc');
    const titleCount = document.getElementById('titleCharCount');
    const descCount = document.getElementById('descCharCount');

    if (previewTitle) {
        previewTitle.innerText = inputTitle || '<?= htmlspecialchars($club['club_name']) ?> - Klub Panahan';
    }
    if (previewDesc) {
        previewDesc.innerText = inputDesc || 'Program kursus panahan berstandar World Archery.';
    }
    if (titleCount) {
        titleCount.innerText = `${inputTitle.length}/60 karakter`;
        titleCount.className = inputTitle.length > 60 ? 'small text-danger fw-bold' : 'small text-muted';
    }
    if (descCount) {
        descCount.innerText = `${inputDesc.length}/160 karakter`;
        descCount.className = inputDesc.length > 160 ? 'small text-danger fw-bold' : 'small text-muted';
    }
}

function addKeyword(kw) {
    const inputKw = document.getElementById('seoInputKeywords');
    if (!inputKw) return;
    const cur = inputKw.value.trim();
    if (cur) {
        if (!cur.toLowerCase().includes(kw.toLowerCase())) {
            inputKw.value = `${cur}, ${kw}`;
        }
    } else {
        inputKw.value = kw;
    }
}

document.addEventListener('DOMContentLoaded', () => {
    updateGooglePreview();
});
</script>

<?php require_once __DIR__ . '/views/footer.php'; ?>
