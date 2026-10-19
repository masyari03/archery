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
<html lang="id">
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
        })();
    </script>

    <!-- OpenGraph Meta Tags untuk Viral Sharing di WhatsApp & Medsos -->
    <meta property="og:title" content="<?= htmlspecialchars($mkt['meta_title'] ?? 'Target Archery - Software Manajemen & Scoring Panahan') ?>">
    <meta property="og:description" content="<?= htmlspecialchars($mkt['meta_description'] ?? 'Sistem digitalisasi klub panahan terlengkap di Indonesia.') ?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://archery.codika.my.id/">
    <meta property="og:image" content="https://images.unsplash.com/photo-1511295742362-92c96b124e52?auto=format&fit=crop&w=1200&q=80">

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

    <!-- TOP HEADER SAAS PLATFORM -->
    <header class="py-3 border-bottom sticky-top saas-header">
        <div class="container d-flex justify-content-between align-items-center">
            <a href="index.php" class="d-flex align-items-center gap-2 text-decoration-none">
                <div class="rounded-circle d-flex align-items-center justify-content-center bg-primary text-white fw-bold" style="width: 44px; height: 44px; font-size: 22px;">
                    🎯
                </div>
                <div>
                    <h5 class="mb-0 fw-bold brand-font" style="letter-spacing: -0.02em;">TARGET ARCHERY SAAS</h5>
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
                <a href="login.php" class="btn btn-outline-info btn-sm px-3 fw-bold">
                    <i class="bi bi-box-arrow-in-right"></i> Masuk Akun
                </a>
                <a href="https://wa.me/<?= $cleanMasterWa ?>?text=Halo%20Master%20Admin%20Target%20Archery,%20saya%20tertarik%20mendaftarkan%20klub%20panahan%20saya%20ke%20platform%20SaaS%20ini" target="_blank" class="btn btn-warning btn-sm fw-bold text-dark px-3 shadow-sm">
                    <i class="bi bi-whatsapp"></i> Hubungi Master Admin
                </a>
            </div>
        </div>
    </header>

    <!-- HERO SECTION MARKETING -->
    <section class="saas-hero text-center text-lg-start">
        <div class="container position-relative">
            <div class="row align-items-center g-5">
                <div class="col-lg-7">
                    <div class="badge bg-primary bg-opacity-25 text-info border border-info border-opacity-25 px-3 py-2 rounded-pill mb-3">
                        <i class="bi bi-stars"></i> <?= htmlspecialchars($heroBadge) ?>
                    </div>
                    <h1 class="display-4 fw-extrabold text-white mb-3" style="line-height: 1.15;">
                        <?= htmlspecialchars($heroTitle) ?>
                    </h1>
                    <p class="lead text-muted mb-4" style="font-size: 1.15rem;">
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
                                <h6 class="fw-bold mb-0">Live Simulation Portal</h6>
                                <span class="text-muted small">Coba langsung sistem scoring klub</span>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small">Pilih Klub Contoh yang Ingin Dilihat:</label>
                            <select class="form-select" onchange="if(this.value) window.open(this.value, '_blank')">
                                <option value="">-- Pilih Demo Klub Panahan --</option>
                                <?php foreach ($allTenants as $t): ?>
                                    <option value="index.php?club=<?= urlencode($t['subdomain_slug']) ?>">
                                        🎯 <?= htmlspecialchars($t['club_name']) ?> (<?= htmlspecialchars($t['city']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <p class="text-muted small mb-3">
                            Setiap klub yang bermitra mendapatkan website profil publik sendiri, portal scoring, sistem ujian naik jarak, dan bot WhatsApp otomatis.
                        </p>

                        <a href="login.php" class="btn btn-primary w-100 fw-bold py-2">
                            Masuk Portal Pengguna (Login)
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- FITUR UTAMA SOFTWARE -->
    <section id="fitur" class="py-5">
        <div class="container py-4">
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

    <!-- SHOWCASE TENANT KLUB MITRA -->
    <section id="showcase" class="py-5" style="background: rgba(15, 23, 42, 0.4); border-top: 1px solid var(--border-color); border-bottom: 1px solid var(--border-color);">
        <div class="container py-4">
            <div class="text-center mb-5">
                <span class="badge bg-warning text-dark mb-2">Tenant Showcase</span>
                <h2 class="fw-bold text-white mb-2">Klub-Klub Panahan yang Telah Bergabung</h2>
                <p class="text-muted mx-auto" style="max-width: 650px;">
                    Setiap klub memiliki website independen, subdomain sendiri, dan domain kustom tanpa bercampur data.
                </p>
            </div>

            <div class="row g-4 justify-content-center">
                <?php foreach ($allTenants as $t): ?>
                    <div class="col-md-5 col-lg-4">
                        <div class="tenant-card">
                            <div>
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <span class="badge bg-secondary"><?= htmlspecialchars($t['city']) ?></span>
                                    <span class="badge bg-success">Tenant Aktif</span>
                                </div>
                                <h4 class="fw-bold text-white mb-1"><?= htmlspecialchars($t['club_name']) ?></h4>
                                <span class="text-info small fw-bold d-block mb-3">
                                    🌐 <?= htmlspecialchars($t['subdomain_slug']) ?>.archery.codika.my.id
                                </span>
                                <p class="text-muted small mb-4">
                                    Klub panahan resmi dengan kurikulum berjenjang 5m - 50m dan scoring digital real-time.
                                </p>
                            </div>
                            <div class="pt-3 border-top border-secondary border-opacity-25">
                                <a href="index.php?club=<?= urlencode($t['subdomain_slug']) ?>" target="_blank" class="btn btn-outline-warning w-100 fw-bold">
                                    <i class="bi bi-box-arrow-up-right"></i> Buka Website Resmi Klub
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
        <div class="container py-4">
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
        <div class="container py-4">
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
        <div class="container">
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
