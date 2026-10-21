<?php
// views/navbar.php - Top App Bar Ramping & Nyaman (54px) + Modern Dropdowns + RBAC Strict Filtering + User Avatar Profile
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
$stmtClub = $pdo->prepare("SELECT club_name, primary_color, subdomain_slug, logo_url, favicon_url FROM archery_clubs WHERE id = :cid LIMIT 1");
$stmtClub->execute([':cid' => $activeClubId]);
$clubMeta = $stmtClub->fetch();
$primaryColor = $clubMeta['primary_color'] ?? '#0284c7';
$clubDisplayName = $clubMeta['club_name'] ?? 'Target Archery';

// Avatar pengguna
$userAvatar = 'assets/img/default_avatar.png';
if ($currentUser) {
    if (!empty($currentUser['avatar_url']) && file_exists(__DIR__ . '/../' . $currentUser['avatar_url'])) {
        $userAvatar = $currentUser['avatar_url'];
    } elseif (!empty($_SESSION['avatar_url']) && file_exists(__DIR__ . '/../' . $_SESSION['avatar_url'])) {
        $userAvatar = $_SESSION['avatar_url'];
    } else {
        // Cek DB jika session belum terisi
        $stmtAv = $pdo->prepare("SELECT avatar_url FROM archery_users WHERE id = :uid LIMIT 1");
        $stmtAv->execute([':uid' => $currentUser['id']]);
        $rowAv = $stmtAv->fetch();
        if (!empty($rowAv['avatar_url']) && file_exists(__DIR__ . '/../' . $rowAv['avatar_url'])) {
            $userAvatar = $rowAv['avatar_url'];
            $_SESSION['avatar_url'] = $userAvatar;
        }
    }
}

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

    /* FLUID RESPONSIVE HEADER ENGINE */
    .app-header {
        height: clamp(52px, 5vw, 60px);
        background: var(--bg-card);
        border-bottom: 1px solid var(--border-color);
        position: sticky;
        top: 0;
        z-index: 1030;
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
        display: flex;
        align-items: center;
        padding: 0 clamp(0.5rem, 1.8vw, 1.25rem);
    }
    .header-inner {
        max-width: 1400px;
        width: 100%;
        margin: 0 auto;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: clamp(0.4rem, 1vw, 1rem);
    }
    .nav-brand-wrap {
        display: flex;
        align-items: center;
        gap: clamp(0.35rem, 0.8vw, 0.65rem);
        text-decoration: none;
        color: var(--text-main);
        min-width: 0;
    }
    .nav-brand-wrap:hover {
        color: var(--primary);
    }
    .brand-target-icon {
        width: clamp(28px, 3.2vw, 34px);
        height: clamp(28px, 3.2vw, 34px);
        border-radius: 50%;
        background: conic-gradient(from 0deg, var(--ring-gold), var(--ring-red), var(--ring-blue), var(--ring-gold));
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 0 12px rgba(2, 132, 199, 0.45);
        font-size: clamp(12px, 1.5vw, 15px);
        flex-shrink: 0;
    }
    .nav-brand-logo-img {
        height: clamp(28px, 3.2vw, 36px);
        max-width: clamp(34px, 4vw, 44px);
        object-fit: contain;
        border-radius: 6px;
        flex-shrink: 0;
    }
    .brand-title-text {
        font-family: 'Outfit', sans-serif;
        font-weight: 800;
        font-size: clamp(0.82rem, 1.1vw, 1.05rem);
        letter-spacing: -0.01em;
        line-height: 1.1;
        white-space: nowrap;
    }
    .brand-subtitle-text {
        font-size: clamp(0.62rem, 0.85vw, 0.72rem);
        color: var(--text-muted);
        font-weight: 600;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: clamp(90px, 15vw, 220px);
    }
    .nav-pill-link {
        font-size: clamp(0.74rem, 0.85vw, 0.86rem);
        font-weight: 600;
        color: var(--text-muted);
        padding: clamp(0.25rem, 0.4vw, 0.4rem) clamp(0.4rem, 0.6vw, 0.75rem);
        border-radius: 8px;
        transition: all 0.2s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        white-space: nowrap;
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
        min-width: 210px;
    }
    .dropdown-item-modern {
        font-size: 0.84rem !important;
        font-weight: 600 !important;
        color: var(--text-main) !important;
        padding: 0.45rem 0.8rem !important;
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
    .user-profile-header-btn {
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid var(--border-color);
        padding: 3px 8px 3px 4px;
        border-radius: 999px;
        display: flex;
        align-items: center;
        gap: clamp(0.35rem, 0.8vw, 0.55rem);
        cursor: pointer;
        transition: all 0.2s ease;
        color: var(--text-main);
    }
    .user-profile-header-btn:hover {
        background: rgba(255, 255, 255, 0.1);
        border-color: var(--primary);
    }
    .header-user-avatar {
        width: clamp(28px, 3.2vw, 34px);
        height: clamp(28px, 3.2vw, 34px);
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid var(--primary);
        background: #1e293b;
        flex-shrink: 0;
    }
    .theme-toggle-btn {
        background: transparent;
        border: 1px solid var(--border-color);
        color: var(--text-muted);
        width: clamp(30px, 3.2vw, 34px);
        height: clamp(30px, 3.2vw, 34px);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.2s;
        font-size: 0.95rem;
        flex-shrink: 0;
    }
    .theme-toggle-btn:hover {
        border-color: var(--primary);
        color: var(--primary);
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

<?php if (!empty($_SESSION['is_demo'])): ?>
<!-- DEMO MODE SIMULATION BANNER -->
<div class="demo-mode-bar no-print" style="background: linear-gradient(90deg, #b45309, #d97706); color: #fff; padding: 6px 16px; font-size: 0.82rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem; z-index: 1040; position: relative;">
    <div class="d-flex align-items-center gap-2">
        <span class="badge bg-dark text-warning fw-bold px-2 py-1">⚡ SIMULASI DEMO</span>
        <span>Anda sedang mencoba sistem sebagai <b><?= htmlspecialchars($_SESSION['full_name'] ?? 'User Demo') ?> (<?= strtoupper($currentRole) ?>)</b></span>
    </div>
    <div class="d-flex align-items-center gap-2">
        <div class="dropdown d-inline-block">
            <button class="btn btn-dark btn-sm dropdown-toggle py-0 px-2" style="font-size: 0.75rem;" type="button" data-bs-toggle="dropdown">
                🔄 Ganti Role Demo
            </button>
            <ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end shadow">
                <li><a class="dropdown-item small" href="demo_launch.php?role=coach">🏹 Masuk sbg Pelatih</a></li>
                <li><a class="dropdown-item small" href="demo_launch.php?role=admin">🛡️ Masuk sbg Super Admin</a></li>
                <li><a class="dropdown-item small" href="demo_launch.php?role=parent">👨‍👩‍👧‍👦 Masuk sbg Wali Santri</a></li>
                <li><a class="dropdown-item small" href="demo_launch.php?role=archer">🎯 Coba Scoring Plotter</a></li>
            </ul>
        </div>
        <a href="logout.php?demo=1" class="btn btn-outline-light btn-sm py-0 px-2" style="font-size: 0.75rem;">
            🚪 Keluar ke Web Utama
        </a>
    </div>
</div>
<?php endif; ?>

<!-- 1. TOP APP BAR TERPADU (FLUID RESPONSIVE UNTUK TABLET & HP) -->
<header class="app-header no-print">
    <div class="header-inner">
        
        <!-- SISI KIRI: BRAND & CHILD SWITCHER -->
        <div class="d-flex align-items-center gap-2 gap-md-3 min-w-0">
<?php
$brandHomeUrl = 'app_dashboard.php';
if ($currentRole === 'coach') {
    $brandHomeUrl = 'coach_dashboard.php';
} elseif ($currentRole === 'super_admin') {
    $brandHomeUrl = 'coach_dashboard.php';
} elseif ($currentRole === 'master_admin') {
    $brandHomeUrl = 'master_tenants.php';
}
?>
            <a href="<?= Auth::check() ? $brandHomeUrl : 'index.php' ?>" class="nav-brand-wrap" title="Beranda Dashboard">
                <?php if (!empty($clubMeta['logo_url']) && $clubMeta['logo_url'] !== 'assets/img/default_logo.png' && file_exists(__DIR__ . '/../' . $clubMeta['logo_url'])): ?>
                    <img src="<?= htmlspecialchars($clubMeta['logo_url']) ?>" alt="Logo" class="nav-brand-logo-img" loading="lazy" decoding="async">
                <?php else: ?>
                    <div class="brand-target-icon">🎯</div>
                <?php endif; ?>
                <div class="min-w-0">
                    <div class="brand-title-text">
                        TARGET ARCHERY
                    </div>
                    <div class="brand-subtitle-text">
                        <?= htmlspecialchars($clubDisplayName) ?>
                    </div>
                </div>
            </a>

            <!-- Child Switcher jika Ortu punya > 1 Atlet -->
            <?php if (!empty($children) && count($children) > 1): ?>
                <div class="child-switcher-box d-none d-lg-flex align-items-center">
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
                <div class="child-switcher-box d-none d-xl-flex align-items-center">
                    <span style="font-size: 11px; font-weight: 700; color: var(--primary);">
                        🎯 <?= htmlspecialchars($currentArcher['nickname'] ?? $currentArcher['full_name']) ?> (<?= (int)$currentArcher['current_distance_meter'] ?>m)
                    </span>
                </div>
            <?php endif; ?>
        </div>

        <!-- SISI TENGAH: NAVIGATION LINKS (TERKELOMPOK RAMPING DENGAN STRICT RBAC) -->
        <nav class="desktop-nav-links d-flex align-items-center gap-1">

            <!-- Dropdown Laporan & Prestasi (HILANG TOTAL jika seluruh sub-item nonaktif di RBAC) -->
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
                                    <i class="bi bi-window-desktop text-info"></i> Web CMS Profil &amp; ACC
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
                    <button class="nav-pill-link nav-pill-dropdown dropdown-toggle <?= in_array($currentPage, ['master_tenants.php', 'master_billing.php', 'master_cms.php', 'master_activity_logs.php', 'master_user_sessions.php']) ? 'active' : '' ?>" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="color: #f59e0b; font-weight: 700;">
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
                        <li>
                            <a class="dropdown-item dropdown-item-modern <?= $currentPage === 'master_activity_logs.php' ? 'active' : '' ?>" href="master_activity_logs.php">
                                <i class="bi bi-shield-check text-info"></i> Audit Log Seluruh Tenant
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item dropdown-item-modern <?= $currentPage === 'master_user_sessions.php' ? 'active' : '' ?>" href="master_user_sessions.php">
                                <i class="bi bi-broadcast text-danger"></i> Pantau Sesi &amp; Putus IP
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

        <!-- SISI KANAN: USER PROFILE AVATAR, THEME TOGGLE, & LOGOUT -->
        <div class="d-flex align-items-center gap-2">

            <!-- Switcher Tema Terang / Gelap -->
            <button type="button" class="theme-toggle-btn" onclick="toggleAppTheme()" title="Ganti Tema Terang/Gelap" id="themeToggleBtn">
                🌙
            </button>

            <?php if (Auth::check()): ?>
                <!-- USER PROFILE AVATAR WITH DROPDOWN -->
                <div class="dropdown">
                    <button class="user-profile-header-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <img src="<?= htmlspecialchars($userAvatar) ?>" alt="Avatar" class="header-user-avatar" loading="lazy" decoding="async">
                        <div class="d-none d-md-block text-start" style="line-height: 1.15;">
                            <div class="text-truncate" style="max-width: 120px; font-weight: 700; font-size: 0.82rem; color: #fff;">
                                <?= htmlspecialchars($currentUser['full_name'] ?: $currentUser['username']) ?>
                            </div>
                            <div style="font-size: 0.68rem; color: var(--text-dim); text-transform: uppercase; font-weight: 600;">
                                <?= htmlspecialchars(str_replace('_', ' ', $currentRole)) ?>
                            </div>
                        </div>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end dropdown-menu-modern">
                        <li class="px-3 py-2 border-bottom border-secondary border-opacity-25 mb-1">
                            <div class="fw-bold text-white small"><?= htmlspecialchars($currentUser['full_name'] ?: $currentUser['username']) ?></div>
                            <div class="text-muted" style="font-size: 0.72rem;">@<?= htmlspecialchars($currentUser['username']) ?> &bull; <?= strtoupper($currentRole) ?></div>
                        </li>
                        <li>
                            <a class="dropdown-item dropdown-item-modern <?= $currentPage === 'profile.php' ? 'active' : '' ?>" href="profile.php">
                                <i class="bi bi-person-gear text-primary"></i> Profil &amp; Ganti Sandi
                            </a>
                        </li>
                        <?php if ($currentRole === 'super_admin'): ?>
                            <li>
                                <a class="dropdown-item dropdown-item-modern <?= $currentPage === 'tenant_activity_logs.php' ? 'active' : '' ?>" href="tenant_activity_logs.php">
                                    <i class="bi bi-clock-history text-info"></i> Log Aktivitas Klub
                                </a>
                            </li>
                        <?php elseif ($currentRole === 'master_admin'): ?>
                            <li>
                                <a class="dropdown-item dropdown-item-modern <?= $currentPage === 'master_activity_logs.php' ? 'active' : '' ?>" href="master_activity_logs.php">
                                    <i class="bi bi-shield-check text-warning"></i> Audit Log Seluruh Tenant
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item dropdown-item-modern <?= $currentPage === 'master_user_sessions.php' ? 'active' : '' ?>" href="master_user_sessions.php">
                                    <i class="bi bi-broadcast text-danger"></i> Pantau Sesi &amp; Putus IP
                                </a>
                            </li>
                        <?php endif; ?>
                        <?php if ($canWebProfil): ?>
                            <li>
                                <a class="dropdown-item dropdown-item-modern" href="index.php" target="_blank">
                                    <i class="bi bi-box-arrow-up-right text-info"></i> Lihat Web Publik Klub
                                </a>
                            </li>
                        <?php endif; ?>
                        <li><hr class="dropdown-divider my-1 border-secondary border-opacity-25"></li>
                        <li>
                            <a class="dropdown-item dropdown-item-modern text-danger" href="logout.php">
                                <i class="bi bi-box-arrow-right text-danger"></i> Keluar (Logout)
                            </a>
                        </li>
                    </ul>
                </div>
            <?php else: ?>
                <a href="login.php" class="btn btn-primary btn-sm fw-bold px-3 py-1" style="font-size: 0.82rem; border-radius: 8px;">
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

    <?php if (Auth::check()): ?>
        <a href="profile.php" class="bottom-nav-item <?= $currentPage === 'profile.php' ? 'active' : '' ?>">
            <span class="bottom-nav-icon">👤</span>
            <span>Profil</span>
        </a>
    <?php else: ?>
        <a href="login.php" class="bottom-nav-item text-primary">
            <span class="bottom-nav-icon">🔑</span>
            <span>Masuk</span>
        </a>
    <?php endif; ?>
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
    if (document.documentElement.hasAttribute('data-bs-theme')) {
        document.documentElement.setAttribute('data-bs-theme', current);
    }
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
