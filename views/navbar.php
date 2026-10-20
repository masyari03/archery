<?php
// views/navbar.php - Top App Bar Ramping & Nyaman (54px) + Modern Dropdowns + RBAC Strict Filtering
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/rbac.php';

// DEFENSIVE GUARD: Jika halaman belum memuat views/header.php, otomatis injeksi <head> & stylesheet lengkap!
if (!defined('ARCHERY_HEADER_LOADED')) {
    define('ARCHERY_HEADER_LOADED', true);
    ?>
    <!DOCTYPE html>
    <html lang="id" data-theme="dark" data-bs-theme="dark">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
        <title><?= htmlspecialchars($pageTitle ?? 'Target Archery Scoring & Platform') ?></title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
        <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
        <script>
            (function() {
                const savedTheme = localStorage.getItem('archery_theme') || 'dark';
                document.documentElement.setAttribute('data-theme', savedTheme);
                document.documentElement.setAttribute('data-bs-theme', savedTheme);
            })();
        </script>
    </head>
    <body>
    <?php
}

$currentUser = Auth::user();
$currentArcher = Auth::currentArcher($pdo);
$children = Auth::getChildren($pdo);
$currentRole = $_SESSION['role'] ?? 'parent';
$isPinChild = isset($_SESSION['archer_device_id']);
$isImpersonating = !empty($_SESSION['impersonating_club_id']);
$currentPage = basename($_SERVER['PHP_SELF']);

// Ambil data klub aktif
$activeClubId = (int)($_SESSION['club_id'] ?? 1);
$stmtClub = $pdo->prepare("SELECT club_name, primary_color, subdomain_slug FROM archery_clubs WHERE id = :cid LIMIT 1");
$stmtClub->execute([':cid' => $activeClubId]);
$clubMeta = $stmtClub->fetch();
$primaryColor = $clubMeta['primary_color'] ?? '#0284c7';
$clubDisplayName = $clubMeta['club_name'] ?? 'Target Archery';

// Evaluasi Hak Akses RBAC untuk Menentukan Menu Aktif / Hilang Total
$canScoring   = Auth::check() && RBAC::can('scoring');
$canWebProfil = RBAC::can('web_profil');
$canRapot     = Auth::check() && RBAC::can('rapot');
$canRekap     = Auth::check() && RBAC::can('rekap');
$canMedsos    = Auth::check() && RBAC::can('medsos');
$canBills     = Auth::check() && RBAC::can('bills');

$isSuperOrMaster = in_array($currentRole, ['super_admin', 'master_admin']);
$canCmsAdmin     = $isSuperOrMaster && RBAC::can('cms_admin');
$canRbac         = $isSuperOrMaster && RBAC::can('rbac');
$canSettings     = $isSuperOrMaster && RBAC::can('settings');
$canDistances    = $isSuperOrMaster && RBAC::can('distances');
$canApprovals    = $isSuperOrMaster && RBAC::can('approvals');
$canCoachDash    = ($currentRole === 'coach' || $isSuperOrMaster) && RBAC::can('coach_dashboard');

// Evaluasi Dropdown: Jika semua submenu nonaktif, maka tulisan dropdown HILANG TOTAL dari header!
$hasLaporanMenu   = ($canRapot || $canRekap || $canMedsos);
$hasManajemenMenu = ($canCmsAdmin || $canRbac || $canSettings || $canDistances || $canApprovals || $canCoachDash);
$hasMasterMenu    = ($currentRole === 'master_admin');
?>

<style>
    :root {
        --primary: <?= htmlspecialchars($primaryColor) ?>;
    }
    /* STRICT RESPONSIVE GUARDS */
    @media (min-width: 992px) {
        .bottom-nav { display: none !important; }
        .no-desktop { display: none !important; }
    }
    @media (max-width: 991px) {
        .bottom-nav { display: flex !important; }
        .no-desktop { display: flex !important; }
        .desktop-nav-links { display: none !important; }
    }

    /* MODERN HEADER STYLING */
    .app-header {
        height: 56px;
        background: var(--bg-card);
        border-bottom: 1px solid var(--border-color);
        position: sticky;
        top: 0;
        z-index: 1030;
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
        display: flex;
        align-items: center;
        padding: 0 1.25rem;
    }
    .header-inner {
        max-width: 1400px;
        width: 100%;
        margin: 0 auto;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
    }
    .nav-brand-wrap {
        display: flex;
        align-items: center;
        gap: 0.65rem;
        text-decoration: none;
        color: var(--text-main);
    }
    .nav-brand-wrap:hover {
        color: var(--primary);
    }
    .brand-target-icon {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: conic-gradient(from 0deg, var(--ring-gold), var(--ring-red), var(--ring-blue), var(--ring-gold));
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 0 12px rgba(2, 132, 199, 0.45);
        font-size: 15px;
    }
    .nav-pill-link {
        font-size: 0.86rem;
        font-weight: 600;
        color: var(--text-muted);
        padding: 0.4rem 0.75rem;
        border-radius: 8px;
        transition: all 0.2s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
    }
    .nav-pill-link:hover, .nav-pill-link.active {
        color: var(--primary);
        background: var(--primary-light);
    }
    .nav-pill-dropdown {
        background: transparent;
        border: none;
        cursor: pointer;
    }
    .dropdown-menu-modern {
        background: var(--bg-card) !important;
        border: 1px solid var(--border-color) !important;
        border-radius: 12px !important;
        box-shadow: var(--shadow-md) !important;
        padding: 0.5rem !important;
        backdrop-filter: blur(20px) !important;
        min-width: 200px;
    }
    .dropdown-item-modern {
        font-size: 0.85rem !important;
        font-weight: 600 !important;
        color: var(--text-main) !important;
        padding: 0.5rem 0.85rem !important;
        border-radius: 6px !important;
        transition: all 0.15s ease !important;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    .dropdown-item-modern:hover, .dropdown-item-modern.active {
        background: var(--primary-light) !important;
        color: var(--primary) !important;
    }
</style>

<!-- BANNER NOTIFIKASI MODE IMPERSONASI MASTER ADMIN -->
<?php if ($isImpersonating): ?>
<div class="master-impersonate-bar no-print" style="background: linear-gradient(90deg, #d97706, #b45309); color: #fff; padding: 6px 16px; font-size: 0.82rem; display: flex; justify-content: space-between; align-items: center;">
    <div>
        👑 <b>MODE MASTER ADMIN</b> &bull; Anda sedang mengelola klub: <u><?= htmlspecialchars($clubDisplayName) ?></u>
    </div>
    <div>
        <a href="master_tenants.php?action=exit_impersonate" class="badge bg-dark text-white text-decoration-none px-2 py-1">
            ⬅ Kembali ke Portal Master Admin
        </a>
    </div>
</div>
<?php endif; ?>

<!-- 1. TOP APP BAR TERPADU (TINGGI 56PX, LAPANG & ELEGAN) -->
<header class="app-header no-print">
    <div class="header-inner">
        
        <!-- SISI KIRI: BRAND & CHILD SWITCHER -->
        <div class="d-flex align-items-center gap-3">
            <a href="<?= Auth::check() ? 'app_dashboard.php' : 'index.php' ?>" class="nav-brand-wrap">
                <div class="brand-target-icon">🎯</div>
                <div>
                    <div style="font-family: 'Outfit', sans-serif; font-weight: 800; font-size: 1.05rem; letter-spacing: -0.01em; line-height: 1.1;">
                        TARGET ARCHERY
                    </div>
                    <div style="font-size: 0.72rem; color: var(--text-muted); font-weight: 600;">
                        <?= htmlspecialchars($clubDisplayName) ?>
                    </div>
                </div>
            </a>

            <!-- Child Switcher jika Ortu punya > 1 Atlet -->
            <?php if (!empty($children) && count($children) > 1): ?>
                <div class="child-switcher-box d-none d-sm-flex align-items-center">
                    <span style="font-size: 11px; color: var(--text-dim); margin-right: 4px;">ATLET:</span>
                    <select class="child-switcher-select" id="childSwitcherSelect" onchange="switchChildProfile(this.value)">
                        <?php foreach ($children as $c): ?>
                            <option value="<?= (int)$c['id'] ?>" <?= ($currentArcher && $currentArcher['id'] == $c['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['full_name']) ?> (<?= $c['current_distance_meter'] ?>m)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php elseif ($currentArcher && !in_array($currentRole, ['super_admin', 'master_admin'])): ?>
                <div class="child-switcher-box d-none d-md-flex align-items-center">
                    <span style="font-size: 11px; font-weight: 700; color: var(--primary);">
                        🎯 <?= htmlspecialchars($currentArcher['nickname'] ?? $currentArcher['full_name']) ?> (<?= (int)$currentArcher['current_distance_meter'] ?>m)
                    </span>
                </div>
            <?php endif; ?>
        </div>

        <!-- SISI TENGAH: NAVIGATION LINKS (TERKELOMPOK RAMPING DENGAN STRICT RBAC) -->
        <nav class="desktop-nav-links d-flex align-items-center gap-1">

            <!-- 1. Menu Portal Scoring (Cepat) -->
            <?php if ($canScoring): ?>
                <a href="app_dashboard.php" class="nav-pill-link <?= $currentPage === 'app_dashboard.php' ? 'active' : '' ?>">
                    <span>📊</span> <span>Portal Scoring</span>
                </a>
            <?php endif; ?>

            <!-- 2. Menu Web Publik (Cepat) -->
            <?php if ($canWebProfil): ?>
                <a href="index.php" target="_blank" class="nav-pill-link" title="Lihat Web Publik Klub">
                    <span>🌐</span> <span>Web Publik</span>
                </a>
            <?php endif; ?>

            <!-- 3. Dropdown Laporan & Prestasi (HILANG TOTAL jika seluruh sub-item nonaktif di RBAC) -->
            <?php if ($hasLaporanMenu): ?>
                <div class="dropdown">
                    <button class="nav-pill-link nav-pill-dropdown dropdown-toggle <?= in_array($currentPage, ['report_session.php', 'report_monthly.php', 'share_card.php']) ? 'active' : '' ?>" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <span>📜</span> <span>Laporan</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-modern">
                        <?php if ($canRapot): ?>
                            <li>
                                <a class="dropdown-item dropdown-item-modern <?= $currentPage === 'report_session.php' ? 'active' : '' ?>" href="report_session.php">
                                    <i class="bi bi-file-earmark-text text-info"></i> Rapot Sesi A4
                                </a>
                            </li>
                        <?php endif; ?>
                        <?php if ($canRekap): ?>
                            <li>
                                <a class="dropdown-item dropdown-item-modern <?= $currentPage === 'report_monthly.php' ? 'active' : '' ?>" href="report_monthly.php">
                                    <i class="bi bi-calendar3 text-warning"></i> Rekap Bulanan
                                </a>
                            </li>
                        <?php endif; ?>
                        <?php if ($canMedsos): ?>
                            <li>
                                <a class="dropdown-item dropdown-item-modern <?= $currentPage === 'share_card.php' ? 'active' : '' ?>" href="share_card.php">
                                    <i class="bi bi-share text-primary"></i> Medsos Share Card
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- 4. Dropdown Manajemen Klub (HILANG TOTAL jika seluruh sub-item nonaktif di RBAC) -->
            <?php if ($hasManajemenMenu): ?>
                <div class="dropdown">
                    <button class="nav-pill-link nav-pill-dropdown dropdown-toggle <?= in_array($currentPage, ['cms_admin.php', 'admin_rbac.php', 'admin_distances.php', 'admin_approvals.php', 'settings.php', 'coach_dashboard.php']) ? 'active' : '' ?>" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <span>⚙️</span> <span>Manajemen</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-modern">
                        <?php if ($canCmsAdmin): ?>
                            <li>
                                <a class="dropdown-item dropdown-item-modern <?= $currentPage === 'cms_admin.php' ? 'active' : '' ?>" href="cms_admin.php">
                                    <i class="bi bi-window-desktop text-info"></i> Web CMS Profil
                                </a>
                            </li>
                        <?php endif; ?>
                        <?php if ($canRbac): ?>
                            <li>
                                <a class="dropdown-item dropdown-item-modern <?= $currentPage === 'admin_rbac.php' ? 'active' : '' ?>" href="admin_rbac.php">
                                    <i class="bi bi-shield-lock-fill text-warning"></i> Hak Akses RBAC
                                </a>
                            </li>
                        <?php endif; ?>
                        <?php if ($canDistances): ?>
                            <li>
                                <a class="dropdown-item dropdown-item-modern <?= $currentPage === 'admin_distances.php' ? 'active' : '' ?>" href="admin_distances.php">
                                    <i class="bi bi-rulers text-secondary"></i> Master Jarak
                                </a>
                            </li>
                        <?php endif; ?>
                        <?php if ($canApprovals): ?>
                            <li>
                                <a class="dropdown-item dropdown-item-modern <?= $currentPage === 'admin_approvals.php' ? 'active' : '' ?>" href="admin_approvals.php">
                                    <i class="bi bi-check-circle-fill text-success"></i> ACC Naik Jarak
                                </a>
                            </li>
                        <?php endif; ?>
                        <?php if ($canSettings): ?>
                            <li>
                                <a class="dropdown-item dropdown-item-modern <?= $currentPage === 'settings.php' ? 'active' : '' ?>" href="settings.php">
                                    <i class="bi bi-gear-fill text-primary"></i> Pengaturan Klub
                                </a>
                            </li>
                        <?php endif; ?>
                        <?php if ($canCoachDash): ?>
                            <li>
                                <a class="dropdown-item dropdown-item-modern <?= $currentPage === 'coach_dashboard.php' ? 'active' : '' ?>" href="coach_dashboard.php">
                                    <i class="bi bi-person-workspace text-warning"></i> Dashboard Pelatih
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- 5. Dropdown SaaS Platform (Khusus Master Admin Platform) -->
            <?php if ($hasMasterMenu): ?>
                <div class="dropdown">
                    <button class="nav-pill-link nav-pill-dropdown dropdown-toggle <?= in_array($currentPage, ['master_tenants.php', 'master_billing.php', 'master_cms.php']) ? 'active' : '' ?>" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="color: #f59e0b; font-weight: 700;">
                        <span>👑</span> <span>SaaS Master</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-modern">
                        <li>
                            <a class="dropdown-item dropdown-item-modern <?= $currentPage === 'master_tenants.php' ? 'active' : '' ?>" href="master_tenants.php">
                                <i class="bi bi-buildings-fill text-warning"></i> Manajemen Tenant Klub
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item dropdown-item-modern <?= $currentPage === 'master_billing.php' ? 'active' : '' ?>" href="master_billing.php">
                                <i class="bi bi-cash-stack text-success"></i> Tagihan SaaS Klub
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item dropdown-item-modern <?= $currentPage === 'master_cms.php' ? 'active' : '' ?>" href="master_cms.php">
                                <i class="bi bi-rocket-takeoff-fill text-info"></i> Marketing &amp; SEO SaaS
                            </a>
                        </li>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- 6. Menu Pembayaran / Tagihan Atlet (Cepat) -->
            <?php if ($canBills): ?>
                <a href="bills.php" class="nav-pill-link <?= $currentPage === 'bills.php' ? 'active' : '' ?>">
                    <span>💳</span> <span>Pembayaran</span>
                </a>
            <?php endif; ?>

        </nav>

        <!-- SISI KANAN: USER PROFILE, THEME TOGGLE, & LOGOUT -->
        <div class="d-flex align-items-center gap-2">

            <!-- Switcher Tema Terang / Gelap (Dengan Sinkronisasi Ganda data-theme & data-bs-theme) -->
            <button type="button" class="theme-toggle-btn" onclick="toggleAppTheme()" title="Ganti Tema Terang/Gelap" id="themeToggleBtn">
                🌙
            </button>

            <?php if (Auth::check()): ?>
                <!-- Role Badge Pill -->
                <span class="badge rounded-pill bg-primary bg-opacity-20 text-info border border-info border-opacity-25 px-2 py-1 d-none d-sm-inline-block" style="font-size: 0.75rem; letter-spacing: 0.04em;">
                    <?= strtoupper(str_replace('_', ' ', $currentRole)) ?>
                </span>

                <!-- Tombol Logout -->
                <a href="logout.php" class="nav-pill-link text-danger" title="Keluar dari Akun">
                    <i class="bi bi-box-arrow-right fs-5"></i>
                </a>
            <?php else: ?>
                <a href="login.php" class="btn btn-primary btn-sm fw-bold px-3 py-1">
                    Masuk
                </a>
            <?php endif; ?>
        </div>

    </div>
</header>

<!-- 2. MODERN BOTTOM NAVIGATION BAR (KHUSUS MOBILE SCREEN <= 991px, STRICT RBAC FILTERED) -->
<nav class="bottom-nav no-print">
    <?php if ($canScoring): ?>
        <a href="app_dashboard.php" class="bottom-nav-item <?= $currentPage === 'app_dashboard.php' ? 'active' : '' ?>">
            <span class="bottom-nav-icon">📊</span>
            <span>Scoring</span>
        </a>
    <?php endif; ?>

    <?php if ($canWebProfil): ?>
        <a href="index.php" class="bottom-nav-item <?= $currentPage === 'index.php' ? 'active' : '' ?>">
            <span class="bottom-nav-icon">🌐</span>
            <span>Web</span>
        </a>
    <?php endif; ?>

    <?php if ($hasMasterMenu): ?>
        <a href="master_tenants.php" class="bottom-nav-item <?= $currentPage === 'master_tenants.php' ? 'active' : '' ?>" style="color: #f59e0b;">
            <span class="bottom-nav-icon">👑</span>
            <span>SaaS</span>
        </a>
    <?php endif; ?>

    <?php if ($canRapot): ?>
        <a href="report_session.php" class="bottom-nav-item <?= $currentPage === 'report_session.php' ? 'active' : '' ?>">
            <span class="bottom-nav-icon">📜</span>
            <span>Rapot</span>
        </a>
    <?php endif; ?>

    <?php if ($canCmsAdmin): ?>
        <a href="cms_admin.php" class="bottom-nav-item <?= $currentPage === 'cms_admin.php' ? 'active' : '' ?>" style="color: #38bdf8;">
            <span class="bottom-nav-icon">📝</span>
            <span>CMS</span>
        </a>
    <?php elseif ($canCoachDash): ?>
        <a href="coach_dashboard.php" class="bottom-nav-item <?= $currentPage === 'coach_dashboard.php' ? 'active' : '' ?>">
            <span class="bottom-nav-icon">👨‍🏫</span>
            <span>Coach</span>
        </a>
    <?php elseif ($canSettings): ?>
        <a href="settings.php" class="bottom-nav-item <?= $currentPage === 'settings.php' ? 'active' : '' ?>">
            <span class="bottom-nav-icon">⚙️</span>
            <span>Admin</span>
        </a>
    <?php elseif ($canBills): ?>
        <a href="bills.php" class="bottom-nav-item <?= $currentPage === 'bills.php' ? 'active' : '' ?>">
            <span class="bottom-nav-icon">💳</span>
            <span>Iuran</span>
        </a>
    <?php endif; ?>

    <a href="logout.php" class="bottom-nav-item text-danger">
        <span class="bottom-nav-icon">🚪</span>
        <span>Keluar</span>
    </a>
</nav>

<script>
// SINKRONISASI TEMA TINGKAT TINGGI (data-theme & data-bs-theme)
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

// GANTI PROFIL ANAK (KORPUS ORTU)
function switchChildProfile(childId) {
    const formData = new FormData();
    formData.append('child_id', childId);
    fetch('api/archer_api.php?action=switch_child', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(res => {
        if (res.status === 'success') {
            window.location.reload();
        } else {
            alert(res.message);
        }
    })
    .catch(() => alert('Gagal mengganti profil anak'));
}
</script>
