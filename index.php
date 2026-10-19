<?php
// index.php - Web Profil Publik & CMS Landing Page Klub Panahan Multi-Tenant
// Terinspirasi dari Sistem SaaS Codika Alquran & Standar World Archery
require_once __DIR__ . '/config/tenant_resolver.php';

// Jika request mengarah ke Domain Utama SaaS tanpa slug klub, tampilkan Web Penjualan Platform Master Admin
if (TenantResolver::isPlatformRoot()) {
    require_once __DIR__ . '/saas_marketing.php';
    exit;
}

$currentTenant = TenantResolver::resolve($pdo);
$tenantId = (int)$currentTenant['id'];
$allTenants = TenantResolver::getAllTenants($pdo);

// Ambil Profil Konten CMS Klub Aktif
$stmtCms = $pdo->prepare("SELECT * FROM archery_cms_content WHERE club_id = :cid LIMIT 1");
$stmtCms->execute([':cid' => $tenantId]);
$cms = $stmtCms->fetch() ?: [];

// Fallback Konten CMS Default jika belum diatur Super Admin
$heroHeadline = $cms['hero_headline'] ?? 'Pusat Pembinaan Karakter & Prestasi Panahan Modern Berstandar World Archery';
$heroSubheadline = $cms['hero_subheadline'] ?? 'Menghidupkan olahraga sunnah dengan pendekatan modern, pelatih berlisensi, analisis scoring digital presisi, dan fasilitas target range 5m - 50m lengkap.';
$heroCtaText = $cms['hero_cta_text'] ?? 'Daftar Calon Santri / Atlet';
$aboutTitle = $cms['about_title'] ?? 'Membangun Generasi Tangguh Berakhlak Mulia';
$aboutContent = $cms['about_content'] ?? 'Kami membina calon atlet panahan sejak usia dini dengan metode pembinaan karakter Islami, ketenangan mental, fokus, serta teknik tarikan dan rilis presisi berstandar resmi World Archery.';
$aboutPointsRaw = $cms['about_points'] ?? 'Pelatih Berlisensi Resmi,Fasilitas Indoor & Outdoor Standar WA,Kurikulum Berjenjang & Uji Kenaikan Jarak,Sistem Plotting Digital Real-Time,Peralatan Busur Lengkap Disediakan';
$aboutPoints = array_filter(array_map('trim', explode(',', $aboutPointsRaw)));

$philosophyTitle = $cms['philosophy_title'] ?? '3 Pilar Panahan: Ketenangan, Fokus, & Konsistensi';
$philosophyContent = $cms['philosophy_content'] ?? 'Memanah bukan sekadar melepaskan anak panah ke sasaran, melainkan melatih ketenangan jiwa, kestabilan nafas, dan kepasrahan kepada Allah setelah ikhtiar terbaik.';

// SEO & Meta Engine Dinamis
$clubBaseUrl = TenantResolver::getBaseUrl($currentTenant);
$canonicalUrl = TenantResolver::canonicalUrl('', $currentTenant);
$seoTitle = !empty($cms['meta_title']) ? $cms['meta_title'] : "{$currentTenant['club_name']} - Klub Panahan & Les Memanah Terbaik di {$currentTenant['city']} | Standar World Archery";
$seoDesc = !empty($cms['meta_description']) ? $cms['meta_description'] : $heroSubheadline;
$seoKeywords = !empty($cms['meta_keywords']) ? $cms['meta_keywords'] : "les panahan anak {$currentTenant['city']}, klub panahan sunnah, kursus memanah pemula, archery club {$currentTenant['city']}";
$ogImage = !empty($cms['og_image_url']) ? $cms['og_image_url'] : 'https://images.unsplash.com/photo-1511295742362-92c96b124e52?auto=format&fit=crop&w=1200&q=80';

// Ambil Program Kursus Klub
$stmtProg = $pdo->prepare("SELECT * FROM archery_cms_programs WHERE club_id = :cid AND is_active = 1 ORDER BY order_num ASC");
$stmtProg->execute([':cid' => $tenantId]);
$programs = $stmtProg->fetchAll();

// Ambil Jadwal Latihan Mingguan
$stmtSched = $pdo->prepare("SELECT * FROM archery_cms_schedules WHERE club_id = :cid ORDER BY order_num ASC");
$stmtSched->execute([':cid' => $tenantId]);
$schedules = $stmtSched->fetchAll();

// Ambil Tim Pelatih Klub
$stmtCoaches = $pdo->prepare("SELECT id, full_name, role, phone, email FROM archery_users WHERE club_id = :cid AND role IN ('coach', 'super_admin') AND is_active = 1 ORDER BY role ASC, full_name ASC");
$stmtCoaches->execute([':cid' => $tenantId]);
$coaches = $stmtCoaches->fetchAll();

// Ambil Galeri Foto & Prestasi
$stmtGalleries = $pdo->prepare("SELECT * FROM archery_cms_galleries WHERE club_id = :cid ORDER BY order_num ASC");
$stmtGalleries->execute([':cid' => $tenantId]);
$galleries = $stmtGalleries->fetchAll();

$isLoggedIn = isset($_SESSION['user_id']) || isset($_SESSION['archer_device_id']);
$isMasterAdmin = ($_SESSION['role'] ?? '') === 'master_admin';
$primaryColor = $currentTenant['primary_color'] ?? '#0284c7';

// Buat Opening Hours untuk Schema.org
$schemaOpeningHours = [];
foreach ($schedules as $sch) {
    $schemaOpeningHours[] = "{$sch['day_name']} {$sch['time_range']}";
}
?>
<!DOCTYPE html>
<html lang="id" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- SEO META TAGS LENGKAP -->
    <title><?= htmlspecialchars($seoTitle) ?></title>
    <meta name="description" content="<?= htmlspecialchars($seoDesc) ?>">
    <meta name="keywords" content="<?= htmlspecialchars($seoKeywords) ?>">
    <link rel="canonical" href="<?= htmlspecialchars($canonicalUrl) ?>">

    <?php if (!empty($cms['google_site_verification'])): ?>
        <meta name="google-site-verification" content="<?= htmlspecialchars($cms['google_site_verification']) ?>">
    <?php endif; ?>

    <!-- OPEN GRAPH (WA / FACEBOOK PREVIEW) -->
    <meta property="og:title" content="<?= htmlspecialchars($seoTitle) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($seoDesc) ?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= htmlspecialchars($canonicalUrl) ?>">
    <meta property="og:image" content="<?= htmlspecialchars($ogImage) ?>">

    <!-- TWITTER CARDS -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= htmlspecialchars($seoTitle) ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($seoDesc) ?>">
    <meta name="twitter:image" content="<?= htmlspecialchars($ogImage) ?>">

    <!-- STRUCTURED DATA SCHEMA.ORG UNTUK RANKING #1 GOOGLE & MAPS LOCAL SNIPPET -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "SportsClub",
      "name": "<?= htmlspecialchars($currentTenant['club_name']) ?>",
      "description": "<?= htmlspecialchars($seoDesc) ?>",
      "url": "<?= htmlspecialchars($canonicalUrl) ?>",
      "telephone": "<?= htmlspecialchars($currentTenant['admin_wa_phone']) ?>",
      "address": {
        "@type": "PostalAddress",
        "streetAddress": "<?= htmlspecialchars($currentTenant['address']) ?>",
        "addressLocality": "<?= htmlspecialchars($currentTenant['city']) ?>",
        "addressCountry": "ID"
      },
      "priceRange": "$$",
      "sport": "Archery",
      "openingHours": <?= json_encode($schemaOpeningHours) ?>
    }
    </script>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
    <style>
        :root {
            --primary: <?= htmlspecialchars($primaryColor) ?>;
            --primary-hover: <?= htmlspecialchars($primaryColor) ?>;
            --primary-glow: <?= htmlspecialchars($primaryColor) ?>40;
        }
        .cms-hero-section {
            position: relative;
            background: radial-gradient(circle at 80% 20%, rgba(2, 132, 199, 0.25) 0%, rgba(11, 15, 25, 0.95) 70%),
                        linear-gradient(180deg, rgba(11, 15, 25, 0.8) 0%, rgba(11, 15, 25, 1) 100%);
            padding: 90px 0 70px 0;
            overflow: hidden;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }
        .cms-hero-target-bg {
            position: absolute;
            right: -100px;
            top: 50%;
            transform: translateY(-50%);
            width: 480px;
            height: 480px;
            border-radius: 50%;
            background: radial-gradient(circle, #f59e0b 0% 10%, #ef4444 10% 30%, #0284c7 30% 50%, #1e293b 50% 70%, #ffffff 70% 90%, transparent 90%);
            opacity: 0.12;
            pointer-events: none;
            filter: blur(2px);
        }
        .tenant-top-bar {
            background: rgba(15, 23, 42, 0.95);
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            padding: 8px 0;
            font-size: 13px;
        }
        .nav-cms-header {
            background: rgba(11, 15, 25, 0.92);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            position: sticky;
            top: 0;
            z-index: 1020;
        }
        .program-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            transition: all 0.25s ease;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .program-card:hover {
            transform: translateY(-5px);
            border-color: var(--primary);
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.5), 0 0 20px var(--primary-glow);
        }
        .schedule-item-card {
            background: rgba(18, 24, 38, 0.6);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            padding: 16px;
            border-left: 4px solid var(--primary);
        }
        .gallery-card {
            position: relative;
            border-radius: var(--radius-md);
            overflow: hidden;
            background: #1e293b;
            height: 220px;
            border: 1px solid var(--border-color);
        }
        .gallery-card img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.4s ease;
        }
        .gallery-card:hover img {
            transform: scale(1.08);
        }
        .gallery-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg, transparent 40%, rgba(11, 15, 25, 0.95) 100%);
            display: flex;
            align-items: flex-end;
            padding: 16px;
        }
        .coach-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 24px;
            text-align: center;
        }
        .sunnah-badge {
            background: rgba(16, 185, 129, 0.15);
            color: #34d399;
            border: 1px solid rgba(16, 185, 129, 0.3);
            font-size: 13px;
            padding: 6px 14px;
            border-radius: 9999px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
    </style>
</head>
<body>

    <!-- TOP BAR: MULTI-TENANT SWITCHER & FAST CONTACT -->
    <div class="tenant-top-bar no-print">
        <div class="container d-flex flex-wrap justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-3">
                <span class="text-muted"><i class="bi bi-geo-alt-fill text-danger"></i> <?= htmlspecialchars($currentTenant['city']) ?>, Indonesia</span>
                <span class="text-muted d-none d-md-inline">&bull;</span>
                <span class="text-muted d-none d-md-inline"><i class="bi bi-whatsapp text-success"></i> <?= htmlspecialchars($currentTenant['admin_wa_phone']) ?></span>
            </div>
            <div class="d-flex align-items-center gap-2">
                <!-- Multi-Tenant Demo Switcher -->
                <span class="text-muted small d-none d-sm-inline">Pilih Klub:</span>
                <select class="form-select form-select-sm bg-dark text-white border-secondary py-0" style="font-size: 12px; width: auto;" onchange="location.href='index.php?club=' + this.value">
                    <?php foreach ($allTenants as $t): ?>
                        <option value="<?= htmlspecialchars($t['subdomain_slug'] ?: $t['club_code']) ?>" <?= $t['id'] == $tenantId ? 'selected' : '' ?>>
                            🎯 <?= htmlspecialchars($t['club_name']) ?> (<?= htmlspecialchars($t['city']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <a href="master_tenants.php" class="btn btn-outline-warning btn-sm py-0 ms-2" style="font-size: 11px;">
                    👑 Master SaaS
                </a>
            </div>
        </div>
    </div>

    <!-- MAIN NAVBAR WEB CMS -->
    <header class="nav-cms-header py-2">
        <div class="container d-flex justify-content-between align-items-center">
            <a href="index.php?club=<?= urlencode($currentTenant['subdomain_slug']) ?>" class="d-flex align-items-center gap-2 text-decoration-none text-white">
                <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold" style="width: 42px; height: 42px; background: var(--primary); font-size: 20px;">
                    🎯
                </div>
                <div>
                    <h5 class="mb-0 fw-bold" style="letter-spacing: -0.02em;"><?= htmlspecialchars($currentTenant['club_name']) ?></h5>
                    <span class="text-muted small" style="font-size: 11px;">Official Archery Club &bull; Member Platform</span>
                </div>
            </a>

            <!-- Menu Publik -->
            <nav class="d-none d-lg-flex align-items-center gap-4">
                <a href="#tentang" class="text-light text-decoration-none small fw-medium">Tentang Klub</a>
                <a href="#filosofi" class="text-light text-decoration-none small fw-medium">Filosofi Sunnah</a>
                <a href="#program" class="text-light text-decoration-none small fw-medium">Program Kursus</a>
                <a href="#jadwal" class="text-light text-decoration-none small fw-medium">Jadwal Lapangan</a>
                <a href="#pelatih" class="text-light text-decoration-none small fw-medium">Tim Pelatih</a>
                <a href="#galeri" class="text-light text-decoration-none small fw-medium">Galeri &amp; Fasilitas</a>
            </nav>

            <!-- Tombol Aksi: Masuk Portal / Daftar -->
            <div class="d-flex align-items-center gap-2">
                <a href="register_archer.php?club=<?= urlencode($currentTenant['subdomain_slug']) ?>" class="btn btn-warning btn-sm fw-bold text-dark px-3 shadow-sm">
                    <i class="bi bi-pencil-square"></i> Daftar Atlet Baru
                </a>
                <?php if ($isLoggedIn): ?>
                    <a href="app_dashboard.php" class="btn btn-outline-info btn-sm px-3 fw-bold">
                        <i class="bi bi-bullseye"></i> Portal Scoring
                    </a>
                <?php else: ?>
                    <a href="login.php?club=<?= urlencode($currentTenant['subdomain_slug']) ?>" class="btn btn-outline-light btn-sm px-3">
                        <i class="bi bi-box-arrow-in-right"></i> Masuk
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- 1. HERO SECTION -->
    <section class="cms-hero-section">
        <div class="cms-hero-target-bg"></div>
        <div class="container position-relative">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <div class="sunnah-badge mb-3">
                        <i class="bi bi-stars"></i> Pusat Olahraga Sunnah &amp; Prestasi World Archery
                    </div>
                    <h1 class="display-5 fw-extrabold text-white mb-3" style="line-height: 1.2;">
                        <?= htmlspecialchars($heroHeadline) ?>
                    </h1>
                    <p class="lead text-muted mb-4" style="font-size: 1.15rem; max-width: 680px;">
                        <?= htmlspecialchars($heroSubheadline) ?>
                    </p>

                    <div class="d-flex flex-wrap gap-3 align-items-center">
                        <a href="register_archer.php?club=<?= urlencode($currentTenant['subdomain_slug']) ?>" class="btn btn-warning btn-lg fw-bold text-dark px-4 py-3 shadow">
                            <i class="bi bi-arrow-through-heart-fill"></i> <?= htmlspecialchars($heroCtaText) ?>
                        </a>
                        <a href="<?= $isLoggedIn ? 'app_dashboard.php' : 'login.php' ?>" class="btn btn-outline-info btn-lg px-4 py-3">
                            <i class="bi bi-bullseye"></i> Portal Scoring Member
                        </a>
                        <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $currentTenant['admin_wa_phone']) ?>?text=Halo%20Admin%20<?= urlencode($currentTenant['club_name']) ?>,%20saya%20ingin%20tanya%20program%20latihan%20panahan" target="_blank" class="btn btn-outline-light btn-lg px-3 py-3" title="Hubungi via WhatsApp">
                            <i class="bi bi-whatsapp text-success"></i> WhatsApp
                        </a>
                    </div>

                    <!-- Key Metrics -->
                    <div class="row g-4 mt-4 pt-3 border-top border-secondary border-opacity-25">
                        <div class="col-4 col-sm-3">
                            <h3 class="fw-bold text-warning mb-0">5m - 50m</h3>
                            <span class="text-muted small">Target Range</span>
                        </div>
                        <div class="col-4 col-sm-3">
                            <h3 class="fw-bold text-info mb-0">300 Pts</h3>
                            <span class="text-muted small">Standar WA Indoor</span>
                        </div>
                        <div class="col-4 col-sm-3">
                            <h3 class="fw-bold text-success mb-0">100%</h3>
                            <span class="text-muted small">Digital Scoring</span>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 text-center mt-4 mt-lg-0">
                    <div class="card p-4 border-0 shadow-lg" style="background: rgba(18, 24, 38, 0.85); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.1) !important;">
                        <div class="p-3 bg-dark rounded-circle mx-auto mb-3 text-warning" style="width: 80px; height: 80px; font-size: 38px; display: flex; align-items: center; justify-content: center;">
                            🏹
                        </div>
                        <h5 class="fw-bold text-white mb-1">Mulai Uji Tembakan</h5>
                        <p class="text-muted small mb-3">Siswa &amp; atlet dapat langsung mencatat tembakan, mengevaluasi grouping seri, dan mengikuti ujian kenaikan jarak.</p>
                        <a href="login.php?club=<?= urlencode($currentTenant['subdomain_slug']) ?>" class="btn btn-primary w-100 fw-bold py-2">
                            Masuk Akun Atlet / Ortu
                        </a>
                        <div class="text-muted small mt-2">
                            Belum terdaftar? <a href="register_archer.php?club=<?= urlencode($currentTenant['subdomain_slug']) ?>" class="text-warning">Daftar Santri Baru</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- 2. TENTANG KLUB & ADAB MEMANAH -->
    <section id="tentang" class="py-5" style="background: rgba(15, 23, 42, 0.5);">
        <div class="container py-4">
            <div class="row align-items-center g-5">
                <div class="col-lg-6">
                    <span class="badge bg-primary mb-2">Profil Klub</span>
                    <h2 class="fw-bold text-white mb-3"><?= htmlspecialchars($aboutTitle) ?></h2>
                    <p class="text-muted" style="line-height: 1.8;">
                        <?= nl2br(htmlspecialchars($aboutContent)) ?>
                    </p>
                    <div class="row g-3 mt-2">
                        <?php foreach ($aboutPoints as $point): ?>
                            <div class="col-12 col-sm-6">
                                <div class="d-flex align-items-start gap-2">
                                    <i class="bi bi-check-circle-fill text-success fs-5"></i>
                                    <span class="text-light small"><?= htmlspecialchars($point) ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card p-4 border-secondary shadow-sm" style="background: linear-gradient(135deg, rgba(2, 132, 199, 0.1), rgba(18, 24, 38, 0.95)); border-left: 5px solid var(--primary) !important;">
                        <h5 class="fw-bold text-info mb-3 d-flex align-items-center gap-2">
                            <i class="bi bi-shield-check"></i> Komitmen Mutu Pembinaan
                        </h5>
                        <p class="text-muted small mb-3">
                            Setiap atlet dibimbing dengan standar keselamatan tinggi (Archery Safety Protocol), pembentukan postur tubuh simetris, dan kurikulum kenaikan jarak terukur (5m, 7m, 10m, 15m, 18m, hingga 30m &amp; 50m).
                        </p>
                        <div class="d-flex align-items-center gap-3 pt-3 border-top border-secondary border-opacity-25">
                            <div class="rounded-circle bg-warning text-dark fw-bold d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; font-size: 20px;">
                                🎯
                            </div>
                            <div>
                                <h6 class="mb-0 text-white fw-bold"><?= htmlspecialchars($currentTenant['chairman_name']) ?></h6>
                                <span class="text-muted small">Ketua &amp; Penanggung Jawab Perkumpulan</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- 3. FILOSOFI OLAHRAGA SUNNAH MEMANAH -->
    <section id="filosofi" class="py-5" style="border-top: 1px solid var(--border-color); border-bottom: 1px solid var(--border-color);">
        <div class="container text-center py-4">
            <div class="sunnah-badge mb-2">
                <i class="bi bi-moon-stars"></i> Sunnah Nabi ﷺ
            </div>
            <h2 class="fw-bold text-white mb-2"><?= htmlspecialchars($philosophyTitle) ?></h2>
            <p class="text-muted mx-auto mb-5" style="max-width: 750px;">
                <?= htmlspecialchars($philosophyContent) ?>
            </p>

            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card p-4 h-100 text-center border-0" style="background: var(--bg-card);">
                        <div class="mx-auto mb-3 rounded-circle d-flex align-items-center justify-content-center bg-primary text-white" style="width: 60px; height: 60px; font-size: 24px;">
                            🕊️
                        </div>
                        <h5 class="fw-bold text-white mb-2">Ketenangan Hati (Inner Peace)</h5>
                        <p class="text-muted small mb-0">
                            Memanah mengajarkan pengendalian detak jantung, ritme nafas yang dalam, serta keheningan batin sebelum anak panah dilepaskan.
                        </p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card p-4 h-100 text-center border-0" style="background: var(--bg-card);">
                        <div class="mx-auto mb-3 rounded-circle d-flex align-items-center justify-content-center bg-warning text-dark" style="width: 60px; height: 60px; font-size: 24px;">
                            👁️
                        </div>
                        <h5 class="fw-bold text-white mb-2">Fokus Pandangan &amp; Pikiran</h5>
                        <p class="text-muted small mb-0">
                            Membantu anak-anak masa kini mengatasi distraksi gadget. Membiasakan konsentrasi tajam mengunci satu titik target sasaran (Gold 10-Ring).
                        </p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card p-4 h-100 text-center border-0" style="background: var(--bg-card);">
                        <div class="mx-auto mb-3 rounded-circle d-flex align-items-center justify-content-center bg-success text-white" style="width: 60px; height: 60px; font-size: 24px;">
                            🔄
                        </div>
                        <h5 class="fw-bold text-white mb-2">Disiplin &amp; Repetisi Gerak</h5>
                        <p class="text-muted small mb-0">
                            Form tembakan yang konsisten (stance, set-up, draw, anchor, release) membentuk karakter istiqomah dan ketangguhan mental atlet.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- 4. PROGRAM KURSUS & KELAS PANAHAN -->
    <section id="program" class="py-5" style="background: rgba(15, 23, 42, 0.4);">
        <div class="container py-4">
            <div class="text-center mb-5">
                <span class="badge bg-warning text-dark mb-2">Pilihan Pembinaan</span>
                <h2 class="fw-bold text-white mb-2">Program Kelas &amp; Kategori Busur</h2>
                <p class="text-muted mx-auto" style="max-width: 600px;">
                    Kurikulum panahan terstruktur untuk berbagai tingkatan usia, mulai dari pemula hingga pembinaan atlet prestasi.
                </p>
            </div>

            <div class="row g-4">
                <?php if (empty($programs)): ?>
                    <div class="col-12 text-center py-4 text-muted">
                        Belum ada program kursus yang ditambahkan oleh admin klub.
                    </div>
                <?php else: ?>
                    <?php foreach ($programs as $prog): ?>
                        <div class="col-md-4">
                            <div class="program-card p-4">
                                <div>
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <span class="badge bg-primary"><?= htmlspecialchars($prog['target_audience']) ?></span>
                                        <?php if (!empty($prog['equipment_included'])): ?>
                                            <span class="badge bg-success small"><i class="bi bi-shield-check"></i> Busur Dipinjamkan</span>
                                        <?php endif; ?>
                                    </div>
                                    <h4 class="fw-bold text-white mt-2 mb-1"><?= htmlspecialchars($prog['program_name']) ?></h4>
                                    <div class="text-info small fw-bold mb-3">
                                        <i class="bi bi-lightning-charge"></i> <?= htmlspecialchars($prog['bow_type']) ?>
                                    </div>
                                    <p class="text-muted small mb-4">
                                        <?= nl2br(htmlspecialchars($prog['description'])) ?>
                                    </p>
                                </div>
                                <div class="pt-3 border-top border-secondary border-opacity-25">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <span class="text-muted small"><?= htmlspecialchars($prog['session_frequency']) ?></span>
                                        <strong class="text-warning fs-5"><?= htmlspecialchars($prog['fee_info']) ?></strong>
                                    </div>
                                    <a href="register_archer.php?club=<?= urlencode($currentTenant['subdomain_slug']) ?>&program=<?= urlencode($prog['program_name']) ?>" class="btn btn-outline-warning w-100 fw-bold">
                                        Daftar Kelas Ini
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- 5. JADWAL LATIHAN & LOKASI LAPANGAN -->
    <section id="jadwal" class="py-5">
        <div class="container py-4">
            <div class="row g-5 align-items-center">
                <div class="col-lg-6">
                    <span class="badge bg-info text-dark mb-2">Waktu Operasional</span>
                    <h2 class="fw-bold text-white mb-3">Jadwal Sesi Latihan Mingguan</h2>
                    <p class="text-muted mb-4">
                        Sesi tembakan dibagi per kelompok usia dan tingkat kemahiran agar setiap anak mendapatkan perhatian optimal dari pelatih.
                    </p>

                    <div class="d-flex flex-column gap-3">
                        <?php if (empty($schedules)): ?>
                            <div class="text-muted small">Jadwal latihan mingguan belum dikonfigurasi.</div>
                        <?php else: ?>
                            <?php foreach ($schedules as $sch): ?>
                                <div class="schedule-item-card">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <h6 class="fw-bold text-white mb-0"><?= htmlspecialchars($sch['day_name']) ?></h6>
                                        <span class="badge bg-warning text-dark"><?= htmlspecialchars($sch['time_range']) ?></span>
                                    </div>
                                    <div class="text-info small fw-bold mb-1">
                                        <?= htmlspecialchars($sch['session_type']) ?>
                                    </div>
                                    <div class="d-flex justify-content-between text-muted small mt-2">
                                        <span><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($sch['range_location']) ?></span>
                                        <span><i class="bi bi-person-badge"></i> <?= htmlspecialchars($sch['coach_in_charge']) ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card p-4 border-secondary shadow" style="background: var(--bg-card);">
                        <h5 class="fw-bold text-white mb-3 d-flex align-items-center gap-2">
                            <i class="bi bi-pin-map-fill text-danger"></i> Lokasi Range &amp; Kontak Klub
                        </h5>
                        <p class="text-muted mb-3">
                            <strong>Alamat Lapangan:</strong><br>
                            <?= nl2br(htmlspecialchars($currentTenant['address'])) ?>
                        </p>
                        <p class="text-muted mb-4">
                            <strong>Kontak WhatsApp Admin:</strong><br>
                            <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $currentTenant['admin_wa_phone']) ?>" class="text-success text-decoration-none fw-bold fs-6" target="_blank">
                                <i class="bi bi-whatsapp"></i> <?= htmlspecialchars($currentTenant['admin_wa_phone']) ?>
                            </a>
                        </p>
                        <a href="https://maps.google.com/?q=<?= urlencode($currentTenant['club_name'] . ' ' . $currentTenant['address']) ?>" target="_blank" class="btn btn-outline-info w-100 fw-bold py-2">
                            <i class="bi bi-map"></i> Buka Petunjuk Arah di Google Maps
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- 6. TIM PELATIH BERLISENSI -->
    <section id="pelatih" class="py-5" style="background: rgba(15, 23, 42, 0.4);">
        <div class="container py-4">
            <div class="text-center mb-5">
                <span class="badge bg-success mb-2">Instruktur Ahli</span>
                <h2 class="fw-bold text-white mb-2">Tim Pelatih Berlisensi</h2>
                <p class="text-muted mx-auto" style="max-width: 600px;">
                    Didampingi coach berpengalaman dengan lisensi kepelatihan resmi, berdedikasi membina teknik dan adab para pemanah.
                </p>
            </div>

            <div class="row g-4 justify-content-center">
                <?php if (empty($coaches)): ?>
                    <div class="col-12 text-center text-muted small">Data pelatih akan segera diperbarui.</div>
                <?php else: ?>
                    <?php foreach ($coaches as $coach): ?>
                        <div class="col-md-4 col-sm-6">
                            <div class="coach-card h-100">
                                <div class="rounded-circle bg-dark text-warning mx-auto mb-3 d-flex align-items-center justify-content-center border border-secondary" style="width: 80px; height: 80px; font-size: 32px;">
                                    🏹
                                </div>
                                <h5 class="fw-bold text-white mb-1"><?= htmlspecialchars($coach['full_name']) ?></h5>
                                <span class="badge bg-secondary mb-3">Coach Panahan <?= htmlspecialchars($currentTenant['club_name']) ?></span>
                                <p class="text-muted small mb-0">
                                    Spesialisasi teknik dasar, biomekanika tarikan, kestabilan anchor point, dan evaluasi scoring berstandar World Archery.
                                </p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- 7. GALERI FASILITAS & PRESTASI -->
    <section id="galeri" class="py-5">
        <div class="container py-4">
            <div class="text-center mb-5">
                <span class="badge bg-primary mb-2">Dokumentasi</span>
                <h2 class="fw-bold text-white mb-2">Fasilitas Range &amp; Prestasi Atlet</h2>
                <p class="text-muted mx-auto" style="max-width: 600px;">
                    Suasana lapangan yang aman, target face resmi World Archery, serta momen kejuaraan atlet binaan kami.
                </p>
            </div>

            <div class="row g-3">
                <?php if (empty($galleries)): ?>
                    <div class="col-12 text-center text-muted small">Galeri foto belum diunggah.</div>
                <?php else: ?>
                    <?php foreach ($galleries as $g): ?>
                        <div class="col-6 col-md-3">
                            <div class="gallery-card">
                                <img src="<?= htmlspecialchars($g['image_url']) ?>" alt="<?= htmlspecialchars($g['title']) ?>" onerror="this.src='https://images.unsplash.com/photo-1511295742362-92c96b124e52?auto=format&fit=crop&w=600&q=80';">
                                <div class="gallery-overlay">
                                    <div class="text-white small fw-bold">
                                        <span class="badge bg-warning text-dark mb-1" style="font-size: 10px;"><?= strtoupper($g['category']) ?></span>
                                        <div><?= htmlspecialchars($g['title']) ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- 8. BANNER CTA PENDAFTARAN ATLET BARU -->
    <section class="py-5" style="background: linear-gradient(135deg, rgba(2, 132, 199, 0.25), rgba(15, 23, 42, 0.98)); border-top: 1px solid var(--border-color);">
        <div class="container text-center py-4">
            <h2 class="fw-extrabold text-white mb-3">Siap Menghidupkan Olahraga Sunnah &amp; Meraih Prestasi?</h2>
            <p class="text-muted lead mx-auto mb-4" style="max-width: 650px;">
                Daftarkan putra-putri Anda sekarang di <?= htmlspecialchars($currentTenant['club_name']) ?>. Tempat terbatas untuk menjaga kualitas pembinaan intensif.
            </p>
            <div class="d-flex justify-content-center gap-3 flex-wrap">
                <a href="register_archer.php?club=<?= urlencode($currentTenant['subdomain_slug']) ?>" class="btn btn-warning btn-lg fw-bold text-dark px-5 py-3 shadow">
                    <i class="bi bi-pencil-square"></i> Isi Formulir Pendaftaran Online
                </a>
                <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $currentTenant['admin_wa_phone']) ?>?text=Halo%20Admin%20<?= urlencode($currentTenant['club_name']) ?>,%20saya%20ingin%20konsultasi%20pendaftaran%20panahan" target="_blank" class="btn btn-outline-success btn-lg px-4 py-3">
                    <i class="bi bi-whatsapp"></i> Chat Admin WhatsApp
                </a>
            </div>
        </div>
    </section>

    <!-- FOOTER WEB CMS -->
    <footer class="py-4" style="background: #070a10; border-top: 1px solid rgba(255, 255, 255, 0.06);">
        <div class="container text-center text-muted small">
            <div class="d-flex justify-content-center gap-4 mb-3">
                <a href="#tentang" class="text-muted text-decoration-none">Tentang</a>
                <a href="#program" class="text-muted text-decoration-none">Program</a>
                <a href="#jadwal" class="text-muted text-decoration-none">Jadwal</a>
                <a href="register_archer.php?club=<?= urlencode($currentTenant['subdomain_slug']) ?>" class="text-warning text-decoration-none fw-bold">Pendaftaran Atlet</a>
                <a href="login.php?club=<?= urlencode($currentTenant['subdomain_slug']) ?>" class="text-info text-decoration-none fw-bold">Portal Member</a>
                <a href="master_tenants.php" class="text-secondary text-decoration-none">SaaS Platform</a>
            </div>
            <p class="mb-1">&copy; <?= date('Y') ?> <strong><?= htmlspecialchars($currentTenant['club_name']) ?></strong>. All rights reserved.</p>
            <p class="mb-0" style="font-size: 11px; opacity: 0.7;">Powered by Target Archery Multi-Tenant SaaS Platform &bull; Built with World Archery Precision &amp; Sunnah Values.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
