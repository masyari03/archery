<?php
// views/navbar.php - Top App Bar Ramping (50px) + Modern Bottom Navigation Bar
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/rbac.php';

$currentUser = Auth::user();
$currentArcher = Auth::currentArcher($pdo);
$children = Auth::getChildren($pdo);
$currentRole = $_SESSION['role'] ?? 'parent';
$isPinChild = isset($_SESSION['archer_device_id']);
$isImpersonating = !empty($_SESSION['impersonating_club_id']);

// Ambil primary_color & nama klub sesuai sesi tenant aktif
$activeClubId = (int)($_SESSION['club_id'] ?? 1);
$stmtClub = $pdo->prepare("SELECT club_name, primary_color FROM archery_clubs WHERE id = :cid LIMIT 1");
$stmtClub->execute([':cid' => $activeClubId]);
$clubMeta = $stmtClub->fetch();
$primaryColor = $clubMeta['primary_color'] ?? '#0284c7';
?>
<style>
    :root {
        --primary: <?= htmlspecialchars($primaryColor) ?>;
    }
    /* STRICT DESKTOP & MOBILE GUARDS - Mencegah leak menu bawah ke desktop */
    @media (min-width: 769px) {
        .bottom-nav { display: none !important; }
        .no-desktop { display: none !important; }
    }
    @media (max-width: 768px) {
        .bottom-nav { display: flex !important; }
        .no-desktop { display: flex !important; }
    }
</style>

<!-- SCRIPT INISIALISASI TEMA TERANG / GELAP INSTAN (ANTI-FLICKER) -->
<script>
    (function() {
        const savedTheme = localStorage.getItem('archery_theme') || 'dark';
        document.documentElement.setAttribute('data-theme', savedTheme);
    })();
</script>

<!-- BANNER NOTIFIKASI MODE IMPERSONASI MASTER ADMIN -->
<?php if ($isImpersonating): ?>
<div class="master-impersonate-bar no-print">
    <div>
        👑 <b>MODE MASTER ADMIN</b> &bull; Anda sedang mengelola klub: <u><?= htmlspecialchars($clubMeta['club_name'] ?? 'Klub') ?></u>
    </div>
    <div>
        <a href="master_tenants.php?action=exit_impersonate">
            ⬅ Kembali ke Portal Master Admin
        </a>
    </div>
</div>
<?php endif; ?>

<!-- 1. TOP APP BAR RAMPING (TINGGI 52PX) -->
<nav class="navbar no-print">
    <div class="nav-container">
        <a href="<?= Auth::check() ? 'app_dashboard.php' : 'index.php' ?>" class="nav-brand">
            <div class="target-icon">
                <span style="font-size: 14px;">🎯</span>
            </div>
            <span style="font-size: 1.05rem; letter-spacing: -0.01em;">TARGET ARCHERY</span>
        </a>

        <!-- CHILD SWITCHER JIKA ORTU MEMILIKI 2 ANAK ATAU LEBIH -->
        <?php if (!empty($children) && count($children) > 1): ?>
        <div class="child-switcher-box">
            <span style="font-size: 11px; color: var(--text-dim);">ANAK:</span>
            <select class="child-switcher-select" id="childSwitcherSelect" onchange="switchChildProfile(this.value)">
                <?php foreach ($children as $c): ?>
                    <option value="<?= (int)$c['id'] ?>" <?= ($currentArcher && $currentArcher['id'] == $c['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['full_name']) ?> (<?= $c['current_distance_meter'] ?>m)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php elseif ($currentArcher): ?>
        <div class="child-switcher-box">
            <span style="font-size: 12px; font-weight:700; color: var(--primary);">
                <?= htmlspecialchars($currentArcher['nickname'] ?? $currentArcher['full_name']) ?> (<?= $currentArcher['current_distance_meter'] ?>m)
            </span>
        </div>
        <?php endif; ?>

        <!-- DESKTOP NAV LINKS (DIFILTER DINAMIS DENGAN RBAC ENGINE) -->
        <ul class="nav-links">
            <?php if (RBAC::can('web_profil')): ?>
                <li><a href="index.php" class="nav-item" target="_blank" title="Lihat Tampilan Web Publik Klub">🌐 Web Publik</a></li>
            <?php endif; ?>

            <?php if (Auth::check() && RBAC::can('scoring')): ?>
                <li><a href="app_dashboard.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'app_dashboard.php' ? 'active' : '' ?>">📊 Portal Scoring</a></li>
            <?php endif; ?>

            <!-- MENU KHUSUS MASTER ADMIN SAAS (PLATFORM OWNER) -->
            <?php if ($currentRole === 'master_admin'): ?>
                <li><a href="master_tenants.php" class="nav-item" style="color: #f59e0b; font-weight: 800;">👑 Master Tenant</a></li>
                <li><a href="master_billing.php" class="nav-item" style="color: #34d399; font-weight: 700;">💰 Tagihan SaaS</a></li>
                <li><a href="master_cms.php" class="nav-item" style="color: #60a5fa; font-weight: 700;">🚀 Marketing SaaS</a></li>
            <?php endif; ?>

            <!-- RAPOT & REKAP SESI (RBAC CHECKED) -->
            <?php if (Auth::check() && RBAC::can('rapot')): ?>
                <li><a href="report_session.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'report_session.php' ? 'active' : '' ?>">📜 Rapot Sesi</a></li>
            <?php endif; ?>

            <?php if (Auth::check() && RBAC::can('rekap')): ?>
                <li><a href="report_monthly.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'report_monthly.php' ? 'active' : '' ?>">📅 Rekap Bulanan</a></li>
            <?php endif; ?>

            <?php if (Auth::check() && RBAC::can('medsos')): ?>
                <li><a href="share_card.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'share_card.php' ? 'active' : '' ?>">📱 Medsos Card</a></li>
            <?php endif; ?>

            <!-- MENU KHUSUS SUPER ADMIN KLUB (RBAC CHECKED) -->
            <?php if ($currentRole === 'super_admin' || $currentRole === 'master_admin'): ?>
                <?php if (RBAC::can('cms_admin')): ?>
                    <li><a href="cms_admin.php" class="nav-item" style="color: #38bdf8; font-weight: 700;">📝 Web CMS</a></li>
                <?php endif; ?>
                <?php if (RBAC::can('rbac')): ?>
                    <li><a href="admin_rbac.php" class="nav-item" style="color: #f59e0b; font-weight: 700;">🛡️ Akses RBAC</a></li>
                <?php endif; ?>
                <?php if (RBAC::can('settings')): ?>
                    <li><a href="settings.php" class="nav-item">⚙️ Pengaturan</a></li>
                <?php endif; ?>
                <?php if (RBAC::can('distances')): ?>
                    <li><a href="admin_distances.php" class="nav-item">📏 Jarak</a></li>
                <?php endif; ?>
                <?php if (RBAC::can('approvals')): ?>
                    <li><a href="admin_approvals.php" class="nav-item">✅ ACC Ujian</a></li>
                <?php endif; ?>
            <?php elseif ($currentRole === 'coach' && RBAC::can('coach_dashboard')): ?>
                <li><a href="coach_dashboard.php" class="nav-item">👨‍🏫 Dashboard Pelatih</a></li>
            <?php endif; ?>

            <?php if (Auth::check() && RBAC::can('bills')): ?>
                <li><a href="bills.php" class="nav-item">💳 Pembayaran</a></li>
            <?php endif; ?>

            <!-- TOGGLE TEMA ☀️ / 🌙 -->
            <li>
                <button type="button" class="theme-toggle-btn" onclick="toggleAppTheme()" title="Ganti Tema Terang/Gelap" id="themeToggleBtn">
                    🌙
                </button>
            </li>

            <?php if (Auth::check()): ?>
                <li>
                    <span class="badge badge-primary">
                        <?= strtoupper(str_replace('_', ' ', $currentRole)) ?>
                    </span>
                </li>
                <li>
                    <a href="logout.php" class="nav-item" style="color: var(--danger);" title="Keluar">
                        🚪
                    </a>
                </li>
            <?php else: ?>
                <li><a href="login.php" class="btn btn-primary btn-sm">Masuk Portal</a></li>
            <?php endif; ?>
        </ul>

        <!-- TOMBOL TEMA UNTUK TAMPILAN MOBILE (DI SEBELAH LOGO/SWITCHER) -->
        <div style="display: flex; gap: 8px; align-items: center;" class="no-desktop">
            <button type="button" class="theme-toggle-btn" onclick="toggleAppTheme()" title="Ganti Tema" id="mobileThemeToggleBtn">
                🌙
            </button>
        </div>
    </div>
</nav>

<!-- 2. MODERN BOTTOM NAVIGATION BAR (KHUSUS MOBILE SCREEN <= 768px, RBAC FILTERED) -->
<div class="bottom-nav no-print" style="display: none;">
    <?php if (RBAC::can('scoring')): ?>
    <a href="app_dashboard.php" class="bottom-nav-item <?= basename($_SERVER['PHP_SELF']) === 'app_dashboard.php' ? 'active' : '' ?>">
        <span class="bottom-nav-icon">📊</span>
        <span>Scoring</span>
    </a>
    <?php endif; ?>

    <?php if (RBAC::can('web_profil')): ?>
    <a href="index.php" class="bottom-nav-item <?= basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : '' ?>">
        <span class="bottom-nav-icon">🌐</span>
        <span>Web</span>
    </a>
    <?php endif; ?>

    <?php if ($currentRole === 'master_admin'): ?>
    <a href="master_tenants.php" class="bottom-nav-item <?= basename($_SERVER['PHP_SELF']) === 'master_tenants.php' ? 'active' : '' ?>" style="color: #f59e0b;">
        <span class="bottom-nav-icon">👑</span>
        <span>SaaS</span>
    </a>
    <?php endif; ?>

    <?php if (Auth::check() && RBAC::can('rapot')): ?>
    <a href="report_session.php" class="bottom-nav-item <?= basename($_SERVER['PHP_SELF']) === 'report_session.php' ? 'active' : '' ?>">
        <span class="bottom-nav-icon">📜</span>
        <span>Rapot</span>
    </a>
    <?php endif; ?>

    <?php if ($currentRole === 'super_admin' || $currentRole === 'master_admin'): ?>
        <?php if (RBAC::can('cms_admin')): ?>
        <a href="cms_admin.php" class="bottom-nav-item <?= basename($_SERVER['PHP_SELF']) === 'cms_admin.php' ? 'active' : '' ?>" style="color: #38bdf8;">
            <span class="bottom-nav-icon">📝</span>
            <span>CMS</span>
        </a>
        <?php endif; ?>
        <?php if (RBAC::can('settings')): ?>
        <a href="settings.php" class="bottom-nav-item <?= basename($_SERVER['PHP_SELF']) === 'settings.php' ? 'active' : '' ?>">
            <span class="bottom-nav-icon">⚙️</span>
            <span>Admin</span>
        </a>
        <?php endif; ?>
    <?php elseif ($currentRole === 'coach' && RBAC::can('coach_dashboard')): ?>
    <a href="coach_dashboard.php" class="bottom-nav-item <?= basename($_SERVER['PHP_SELF']) === 'coach_dashboard.php' ? 'active' : '' ?>">
        <span class="bottom-nav-icon">👨‍🏫</span>
        <span>Coach</span>
    </a>
    <?php elseif (Auth::check() && RBAC::can('bills')): ?>
    <a href="bills.php" class="bottom-nav-item <?= basename($_SERVER['PHP_SELF']) === 'bills.php' ? 'active' : '' ?>">
        <span class="bottom-nav-icon">💳</span>
        <span>Iuran</span>
    </a>
    <?php endif; ?>

    <a href="logout.php" class="bottom-nav-item" style="color: var(--danger);">
        <span class="bottom-nav-icon">🚪</span>
        <span>Keluar</span>
    </a>
</div>

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
    const btn1 = document.getElementById('themeToggleBtn');
    const btn2 = document.getElementById('mobileThemeToggleBtn');
    if (btn1) btn1.textContent = icon;
    if (btn2) btn2.textContent = icon;
}

document.addEventListener('DOMContentLoaded', () => {
    const current = document.documentElement.getAttribute('data-theme') || 'dark';
    updateThemeIcons(current);
});

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
