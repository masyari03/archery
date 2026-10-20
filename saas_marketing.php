<?php
// saas_marketing.php - Halaman Penjualan Platform SaaS Target Archery (Master Admin Marketing Portal)
require_once __DIR__ . '/config/tenant_resolver.php';

// Ambil konten CMS Marketing
$stmtMkt = $pdo->query("SELECT * FROM archery_saas_marketing_cms WHERE id = 1 LIMIT 1");
$mkt = $stmtMkt->fetch() ?: [];

// Fallback data jika belum terisi
$heroBadge = $mkt['hero_badge'] ?? 'PLATFORM DIGITALISASI OLAHRAGA SUNNAH & PRESTASI';
$heroTitle = $mkt['hero_title'] ?? 'Aplikasi Manajemen & Scoring Panahan Multi-Club #1 di Indonesia';
$heroSubtitle = $mkt['hero_subtitle'] ?? 'Tingkatkan profesionalisme perkumpulan panahan Anda dengan sistem scoring standar World Archery, rapor resmi otomatis, website profil mandiri, dan integrasi WhatsApp.';
$ctaText = $mkt['cta_text'] ?? 'Daftarkan Klub Panahan Anda (Free Demo)';

$features = !empty($mkt['features_json']) ? json_decode($mkt['features_json'], true) : [];
$pricing = !empty($mkt['pricing_tiers_json']) ? json_decode($mkt['pricing_tiers_json'], true) : [];
$showcase = !empty($mkt['showcase_clubs_json']) ? json_decode($mkt['showcase_clubs_json'], true) : [];
$faq = !empty($mkt['faq_json']) ? json_decode($mkt['faq_json'], true) : [];

// Ambil juga tenant aktif asli dari database untuk showcase real-time
$allTenants = TenantResolver::getAllTenants($pdo);

// Ambil Kontak Master Admin
$stmtSaas = $pdo->query("SELECT * FROM archery_saas_settings WHERE id = 1 LIMIT 1");
$saasSettings = $stmtSaas->fetch() ?: [];
$masterWa = $saasSettings['master_admin_wa'] ?? '081299887766';
$cleanMasterWa = preg_replace('/[^0-9]/', '', $masterWa);
if (substr($cleanMasterWa, 0, 1) === '0') {
    $cleanMasterWa = '62' . substr($cleanMasterWa, 1);
}
?>
<!DOCTYPE html>
<html lang="id" data-theme="dark" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($mkt['meta_title'] ?? 'Target Archery - Software Manajemen & Scoring Panahan Terlengkap di Indonesia') ?></title>
    <meta name="description" content="<?= htmlspecialchars($mkt['meta_description'] ?? 'Platform SaaS digitalisasi klub panahan terbaik di Indonesia berstandar World Archery.') ?>">
    <meta name="keywords" content="<?= htmlspecialchars($mkt['meta_keywords'] ?? 'software panahan, aplikasi scoring archery, sistem klub panahan indonesia') ?>">
    <link rel="canonical" href="https://archery.codika.my.id/">

    <!-- Anti-Flicker Dual Theme Engine -->
    <script>
        (function() {
            const savedTheme = localStorage.getItem('archery_theme') || 'dark';
            document.documentElement.setAttribute('data-theme', savedTheme);
            document.documentElement.setAttribute('data-bs-theme', savedTheme);
        })();
    </script>

    <!-- Navicon & Favicon Platform -->
    <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars(!empty($mkt['platform_favicon_url']) ? $mkt['platform_favicon_url'] : 'assets/img/default_favicon.png') ?>">
    <link rel="shortcut icon" href="<?= htmlspecialchars(!empty($mkt['platform_favicon_url']) ? $mkt['platform_favicon_url'] : 'assets/img/default_favicon.png') ?>">

    <!-- OpenGraph Meta Tags untuk Viral Sharing di WhatsApp & Medsos -->
    <meta property="og:title" content="<?= htmlspecialchars($mkt['meta_title'] ?? 'Target Archery - Software Manajemen & Scoring Panahan') ?>">
    <meta property="og:description" content="<?= htmlspecialchars($mkt['meta_description'] ?? 'Sistem digitalisasi klub panahan terlengkap di Indonesia.') ?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://archery.codika.my.id/">
    <meta property="og:image" content="assets/img/default_logo.png">

    <!-- Structured Data Schema.org SoftwareApplication untuk Ranking Google Paling Atas -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "SoftwareApplication",
      "name": "Target Archery SaaS Platform",
      "operatingSystem": "All Web Browsers (Mobile & Desktop)",
      "applicationCategory": "SportsApplication",
      "offers": {
        "@type": "Offer",
        "price": "150000",
        "priceCurrency": "IDR"
      },
      "aggregateRating": {
        "@type": "AggregateRating",
        "ratingValue": "4.9",
        "ratingCount": "128"
      },
      "description": "Software manajemen klub dan scoring panahan presisi standar World Archery dengan rapor resmi A4 dan WhatsApp bot."
    }
    </script>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
    <style>
        html {
            scroll-behavior: smooth;
        }
        section[id] {
            scroll-margin-top: 85px;
        }
        .section-container {
            max-width: 1560px;
            margin-left: auto;
            margin-right: auto;
            width: 100%;
        }
        .saas-header {
            background: rgba(11, 15, 25, 0.92);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            z-index: 1050;
        }
        .saas-hero {
            background: radial-gradient(circle at 80% 20%, rgba(2, 132, 199, 0.35) 0%, rgba(11, 15, 25, 0.98) 70%),
                        linear-gradient(180deg, #0b0f19 0%, #0f172a 100%);
            padding: 100px 0 80px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            position: relative;
            overflow: hidden;
        }
        .saas-hero-title {
            font-size: clamp(2.1rem, 3.2vw, 3.25rem);
            font-weight: 800;
            line-height: 1.2;
            letter-spacing: -0.025em;
            max-width: 820px;
        }
        .feature-box {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 28px;
            height: 100%;
            transition: all 0.3s ease;
        }
        .feature-box:hover {
            transform: translateY(-6px);
            border-color: var(--primary);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.5), 0 0 25px rgba(2, 132, 199, 0.3);
        }
        .tenant-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 24px;
            transition: all 0.25s ease;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            height: 100%;
        }
        .tenant-card:hover {
            border-color: #f59e0b;
            transform: translateY(-4px);
        }
        .pricing-card-saas {
            background: var(--bg-card);
            border: 2px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 36px 28px;
            transition: all 0.3s;
        }
        .pricing-card-saas.popular {
            border-color: var(--primary);
            box-shadow: 0 10px 40px rgba(2, 132, 199, 0.3);
        }
        .simulation-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            backdrop-filter: blur(14px);
        }
    </style>
</head>
<body>

    <!-- TOP HEADER SAAS PLATFORM (FULL WIDTH LAPTOP COMFORT) -->
    <header class="py-3 border-bottom sticky-top saas-header">
        <div class="container-fluid px-3 px-lg-5 section-container d-flex justify-content-between align-items-center">
            <a href="saas_marketing.php" class="d-flex align-items-center gap-2 text-decoration-none">
                <?php if (!empty($mkt['platform_logo_url']) && $mkt['platform_logo_url'] !== 'assets/img/default_logo.png'): ?>
                    <img src="<?= htmlspecialchars($mkt['platform_logo_url']) ?>" alt="Logo" style="height: 42px; max-width: 150px; object-fit: contain;">
                <?php else: ?>
                    <div class="rounded-circle d-flex align-items-center justify-content-center bg-primary text-white fw-bold" style="width: 44px; height: 44px; font-size: 22px;">
                        🎯
                    </div>
                <?php endif; ?>
                <div>
                    <h5 class="mb-0 fw-bold brand-font" style="letter-spacing: -0.02em;"><?= htmlspecialchars($mkt['platform_brand_name'] ?? 'TARGET ARCHERY SAAS') ?></h5>
                    <span class="text-muted small" style="font-size: 11px;">Platform Bisnis &amp; Scoring Panahan Indonesia</span>
                </div>
            </a>

            <nav class="d-none d-lg-flex align-items-center gap-4">
                <a href="#fitur" class="nav-item small fw-medium">Fitur Software</a>
                <a href="#showcase" class="nav-item small fw-medium">Klub Mitra</a>
                <a href="#harga" class="nav-item small fw-medium">Paket Harga</a>
                <a href="#faq" class="nav-item small fw-medium">Tanya Jawab</a>
            </nav>

            <div class="d-flex align-items-center gap-2">
                <!-- TOMBOL TOGGLE TEMA ☀️ / 🌙 -->
                <button type="button" class="theme-toggle-btn me-1" onclick="toggleAppTheme()" title="Ganti Tema Terang/Gelap" id="themeToggleBtn">
                    🌙
                </button>
                <a href="https://wa.me/<?= $cleanMasterWa ?>?text=Halo%20Master%20Admin%20Target%20Archery,%20saya%20tertarik%20mendaftarkan%20klub%20panahan%20saya%20ke%20platform%20SaaS%20ini" target="_blank" class="btn btn-warning btn-sm fw-bold text-dark px-3 shadow-sm">
                    <i class="bi bi-whatsapp"></i> Hubungi Master Admin
                </a>
            </div>
        </div>
    </header>

    <!-- HERO SECTION MARKETING (FULL WIDTH 1560PX & PRECISE 2-LINE TITLE) -->
    <section class="saas-hero text-center text-lg-start">
        <div class="container-fluid px-3 px-lg-5 section-container position-relative">
            <div class="row align-items-center g-5">
                <div class="col-lg-7">
                    <div class="badge bg-primary bg-opacity-25 text-info border border-info border-opacity-25 px-3 py-2 rounded-pill mb-3">
                        <i class="bi bi-stars"></i> <?= htmlspecialchars($heroBadge) ?>
                    </div>
                    <h1 class="saas-hero-title text-white mb-3">
                        Platform Manajemen &amp; Scoring Panahan<br class="d-none d-md-inline">
                        <span class="text-info">Multi-Club Berstandar Resmi #1</span>
                    </h1>
                    <p class="lead text-muted mb-4" style="font-size: 1.15rem; max-width: 680px;">
                        <?= htmlspecialchars($heroSubtitle) ?>
                    </p>

                    <div class="d-flex flex-wrap gap-3 justify-content-center justify-content-lg-start mb-4">
                        <a href="https://wa.me/<?= $cleanMasterWa ?>?text=Halo%20Master%20Admin%20Target%20Archery,%20saya%20ingin%20mencoba%20demo%20gratis%20untuk%20klub%20saya" target="_blank" class="btn btn-warning btn-lg fw-bold text-dark px-4 py-3 shadow">
                            <i class="bi bi-rocket-takeoff-fill"></i> <?= htmlspecialchars($ctaText) ?>
                        </a>
                        <a href="#showcase" class="btn btn-outline-light btn-lg px-4 py-3">
                            <i class="bi bi-eye-fill"></i> Lihat Contoh Web Klub Mitra
                        </a>
                    </div>

                    <div class="d-flex flex-wrap gap-4 text-muted small justify-content-center justify-content-lg-start">
                        <span><i class="bi bi-check-circle-fill text-success"></i> Standar World Archery 10-Ring</span>
                        <span><i class="bi bi-check-circle-fill text-success"></i> Triple-Shield Anti-Hilang Skor</span>
                        <span><i class="bi bi-check-circle-fill text-success"></i> Domain &amp; Subdomain Sendiri</span>
                    </div>
                </div>

                <div class="col-lg-5 text-center">
                    <div class="card simulation-card p-4 shadow-lg text-start">
                        <div class="d-flex align-items-center gap-3 mb-3 pb-3 border-bottom border-secondary border-opacity-25">
                            <div class="rounded-circle bg-warning text-dark fw-bold d-flex align-items-center justify-content-center" style="width: 50px; height: 50px; font-size: 24px;">
                                🏹
                            </div>
                            <div>
                                <h6 class="fw-bold mb-0 text-white">Interactive Live Demo</h6>
                                <span class="text-muted small">Coba langsung sistem scoring tanpa registrasi</span>
                            </div>
                        </div>

                        <div class="mb-3 p-3 rounded" style="background: rgba(255, 255, 255, 0.04); border: 1px solid var(--border-color);">
                            <div class="d-flex align-items-center gap-2 mb-2 text-warning small fw-bold">
                                <i class="bi bi-key-fill"></i> AKUN UJI COBA LANGSUNG (INSTANT LOGIN)
                            </div>
                            <div class="d-flex flex-column gap-2 small">
                                <div class="d-flex justify-content-between align-items-center py-1 border-bottom border-secondary border-opacity-25">
                                    <span class="text-light"><i class="bi bi-person-badge-fill text-info me-1"></i> Pelatih / Coach:</span>
                                    <code class="text-warning">coach_fadli</code>
                                </div>
                                <div class="d-flex justify-content-between align-items-center py-1 border-bottom border-secondary border-opacity-25">
                                    <span class="text-light"><i class="bi bi-people-fill text-success me-1"></i> Orang Tua Atlet:</span>
                                    <code class="text-warning">ortu_budi</code>
                                </div>
                                <div class="d-flex justify-content-between align-items-center py-1">
                                    <span class="text-muted"><i class="bi bi-lock-fill me-1"></i> Password Demo:</span>
                                    <code class="text-light">password</code>
                                </div>
                            </div>
                        </div>

                        <p class="text-muted small mb-3">
                            Jelajahi fitur Target Plotter 10-Ring, kalkulasi arrow radius milimeter, rapor PDF A4 resmi, dan portal latihan secara instan.
                        </p>

                        <a href="login.php" class="btn btn-primary w-100 fw-bold py-2 shadow-sm d-flex align-items-center justify-content-center gap-2">
                            <i class="bi bi-play-circle-fill fs-5"></i> Buka Demo Sistem Sekarang
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- FITUR UTAMA SOFTWARE -->
    <section id="fitur" class="py-5">
        <div class="container-fluid px-3 px-lg-5 section-container py-4">
            <div class="text-center mb-5">
                <span class="badge bg-info text-dark mb-2">Keunggulan Teknologi</span>
                <h2 class="fw-bold text-white mb-2">Mengapa Klub Panahan Memilih Platform Ini?</h2>
                <p class="text-muted mx-auto" style="max-width: 650px;">
                    Dirancang khusus untuk memenuhi standar resmi World Archery dengan memadukan nilai ketenangan adab panahan sunnah.
                </p>
            </div>

            <div class="row g-4">
                <?php foreach ($features as $f): ?>
                    <div class="col-md-4">
                        <div class="feature-box">
                            <div class="rounded-circle bg-primary bg-opacity-20 text-info d-flex align-items-center justify-content-center mb-3" style="width: 52px; height: 52px; font-size: 24px;">
                                🎯
                            </div>
                            <h5 class="fw-bold text-white mb-2"><?= htmlspecialchars($f['title']) ?></h5>
                            <p class="text-muted small mb-0"><?= htmlspecialchars($f['desc']) ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- SHOWCASE TENANT KLUB MITRA (OTOMATIS BERTAMBAH DARI DATABASE & DIRECT SUBDOMAIN) -->
    <section id="showcase" class="py-5" style="background: rgba(15, 23, 42, 0.4); border-top: 1px solid var(--border-color); border-bottom: 1px solid var(--border-color);">
        <div class="container-fluid px-3 px-lg-5 section-container py-4">
            <div class="text-center mb-5">
                <span class="badge bg-warning text-dark mb-2">Tenant Showcase</span>
                <h2 class="fw-bold text-white mb-2">Klub-Klub Panahan yang Telah Bergabung</h2>
                <p class="text-muted mx-auto" style="max-width: 650px;">
                    Setiap klub memiliki website independen, subdomain resmi sendiri, dan domain kustom tanpa bercampur data.
                </p>
            </div>

            <div class="row g-4 justify-content-center">
                <?php foreach ($allTenants as $t): ?>
                    <?php 
                        $directClubUrl = TenantResolver::getBaseUrl($t);
                        $clubDomainDisplay = !empty($t['custom_domain']) ? $t['custom_domain'] : ($t['subdomain_slug'] . '.codika.my.id');
                    ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="tenant-card shadow-sm">
                            <div>
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <span class="badge bg-secondary"><i class="bi bi-geo-alt-fill text-danger"></i> <?= htmlspecialchars($t['city'] ?: 'Indonesia') ?></span>
                                    <span class="badge bg-success bg-opacity-25 text-success border border-success border-opacity-25"><i class="bi bi-check-circle-fill"></i> Tenant Aktif</span>
                                </div>
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <?php if (!empty($t['logo_url']) && $t['logo_url'] !== 'assets/img/default_logo.png'): ?>
                                        <img src="<?= htmlspecialchars($t['logo_url']) ?>" alt="Logo" style="width: 36px; height: 36px; object-fit: contain; border-radius: 6px;">
                                    <?php else: ?>
                                        <div class="rounded-circle d-flex align-items-center justify-content-center bg-primary text-white fw-bold" style="width: 36px; height: 36px; font-size: 18px;">
                                            🎯
                                        </div>
                                    <?php endif; ?>
                                    <h4 class="fw-bold text-white mb-0 fs-5"><?= htmlspecialchars($t['club_name']) ?></h4>
                                </div>
                                <span class="text-info small fw-bold d-block mb-3 font-monospace">
                                    🌐 <?= htmlspecialchars($clubDomainDisplay) ?>
                                </span>
                                <p class="text-muted small mb-4" style="line-height: 1.6;">
                                    <?= htmlspecialchars(!empty($t['address']) ? (strlen($t['address']) > 90 ? substr($t['address'], 0, 90) . '...' : $t['address']) : 'Klub panahan resmi dengan kurikulum berjenjang dan scoring digital real-time.') ?>
                                </p>
                            </div>
                            <div class="pt-3 border-top border-secondary border-opacity-25">
                                <a href="<?= htmlspecialchars($directClubUrl) ?>" target="_blank" class="btn btn-outline-warning w-100 fw-bold d-flex align-items-center justify-content-center gap-2">
                                    <i class="bi bi-box-arrow-up-right"></i> Kunjungi Website Resmi Klub
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- PAKET HARGA & SIMULASI -->
    <section id="harga" class="py-5">
        <div class="container-fluid px-3 px-lg-5 section-container py-4">
            <div class="text-center mb-5">
                <span class="badge bg-success mb-2">Investasi Terjangkau</span>
                <h2 class="fw-bold text-white mb-2">Skema Berlangganan SaaS yang Fleksibel</h2>
                <p class="text-muted mx-auto" style="max-width: 650px;">
                    Pilih skema biaya yang paling cocok untuk klub Anda. Dilengkapi aturan <strong>Fair Billing Prorata Tanggal 16</strong>.
                </p>
            </div>

            <div class="row g-4 justify-content-center">
                <?php foreach ($pricing as $idx => $p): ?>
                    <div class="col-md-5">
                        <div class="pricing-card-saas <?= $idx === 0 ? 'popular' : '' ?> text-center h-100 d-flex flex-column justify-content-between">
                            <div>
                                <?php if ($idx === 0): ?>
                                    <span class="badge bg-primary mb-2">Paling Diminati</span>
                                <?php endif; ?>
                                <h4 class="fw-bold text-white mb-1"><?= htmlspecialchars($p['name']) ?></h4>
                                <h2 class="fw-extrabold text-warning my-3"><?= htmlspecialchars($p['price']) ?></h2>
                                <p class="text-muted small mb-4"><?= htmlspecialchars($p['desc']) ?></p>

                                <ul class="list-unstyled text-start small text-light d-flex flex-column gap-2 mb-4">
                                    <li><i class="bi bi-check-circle-fill text-success"></i> Unlimited Scoring &amp; Plotting Atlet</li>
                                    <li><i class="bi bi-check-circle-fill text-success"></i> Web Profil CMS Mandiri</li>
                                    <li><i class="bi bi-check-circle-fill text-success"></i> Rapor Sesi &amp; Rekap Bulanan A4</li>
                                    <li><i class="bi bi-check-circle-fill text-success"></i> WhatsApp Bot Notifikasi Fonnte</li>
                                    <li><i class="bi bi-check-circle-fill text-success"></i> Dukungan Custom Domain Pribadi</li>
                                </ul>
                            </div>
                            <div>
                                <a href="https://wa.me/<?= $cleanMasterWa ?>?text=Halo%20Master%20Admin,%20saya%20ingin%20berlangganan%20paket%20<?= urlencode($p['name']) ?>" target="_blank" class="btn btn-warning w-100 fw-bold py-2 shadow-sm">
                                    Pilih Paket Ini
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- FAQ -->
    <section id="faq" class="py-5 saas-faq-section" style="border-top: 1px solid var(--border-color);">
        <div class="container-fluid px-3 px-lg-5 section-container py-4">
            <div class="text-center mb-5">
                <span class="badge bg-secondary mb-2">Bantuan &amp; FAQ</span>
                <h2 class="fw-bold mb-2">Pertanyaan yang Sering Diajukan</h2>
            </div>

            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="accordion" id="saasFaq">
                        <?php foreach ($faq as $idx => $f): ?>
                            <div class="accordion-item mb-3 rounded overflow-hidden" style="border: 1px solid var(--border-color); background: var(--bg-card);">
                                <h2 class="accordion-header" id="heading<?= $idx ?>">
                                    <button class="accordion-button fw-bold collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?= $idx ?>" style="background: var(--bg-card); color: var(--text-main);">
                                        <?= htmlspecialchars($f['q']) ?>
                                    </button>
                                </h2>
                                <div id="collapse<?= $idx ?>" class="accordion-collapse collapse" data-bs-parent="#saasFaq">
                                    <div class="accordion-body text-muted small" style="line-height: 1.8; background: var(--bg-card); color: var(--text-muted);">
                                        <?= htmlspecialchars($f['a']) ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- FOOTER SAAS -->
    <footer class="py-4 text-center text-muted small border-top" style="border-color: var(--border-color) !important; background: var(--bg-main);">
        <div class="container-fluid px-3 px-lg-5 section-container">
            <p class="mb-1">&copy; <?= date('Y') ?> <strong>TARGET ARCHERY SAAS PLATFORM</strong> &bull; Platform Digitalisasi Panahan Indonesia</p>
            <p class="mb-0" style="font-size: 11px; opacity: 0.7;">Powered by Codika Multi-Tenant Architecture &bull; World Archery Precision Engineering</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleAppTheme() {
            const current = document.documentElement.getAttribute('data-theme') || 'dark';
            const next = current === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', next);
            document.documentElement.setAttribute('data-bs-theme', next);
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
            document.documentElement.setAttribute('data-bs-theme', current);
            updateThemeIcons(current);
        });
    </script>
</body>
</html>
