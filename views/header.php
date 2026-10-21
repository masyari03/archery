<?php
/**
 * views/header.php - Global Layout Header & Design System Integration
 * Target Archery Sport-Tech Platform
 */

if (!defined('ARCHERY_HEADER_LOADED')) {
    define('ARCHERY_HEADER_LOADED', true);
}

// Fallback jika auth belum di-include sebelumnya
if (!class_exists('Auth')) {
    require_once __DIR__ . '/../config/auth.php';
}
if (!class_exists('TenantResolver')) {
    require_once __DIR__ . '/../config/tenant_resolver.php';
}

$pageTitle = $pageTitle ?? 'Target Archery - Scoring & Monitoring Platform';

// Resolusi Favicon Klub Aktif
$activeFavicon = 'assets/img/default_favicon.png';
if (isset($pdo)) {
    $headerClubId = (int)($_SESSION['club_id'] ?? 1);
    $stmtFav = $pdo->prepare("SELECT favicon_url FROM archery_clubs WHERE id = :cid LIMIT 1");
    $stmtFav->execute([':cid' => $headerClubId]);
    $favRow = $stmtFav->fetch();
    if (!empty($favRow['favicon_url'])) {
        $activeFavicon = $favRow['favicon_url'];
    }
}
?>
<!DOCTYPE html>
<html lang="id" data-theme="dark" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= htmlspecialchars($pageTitle) ?></title>

    <!-- Navicon & Favicon Dinamis Klub -->
    <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars($activeFavicon) ?>">
    <link rel="shortcut icon" href="<?= htmlspecialchars($activeFavicon) ?>">

    <!-- 1. Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- 2. Bootstrap 5.3.3 CSS CDN -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">

    <!-- 3. Bootstrap Icons 1.11.3 CDN -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- 4. Target Archery Modern Sport-Tech Core Theme -->
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">

    <!-- 5. Anti-Flicker Dual Theme Inisialisasi Instan -->
    <script>
        (function() {
            const savedTheme = localStorage.getItem('archery_theme') || 'dark';
            document.documentElement.setAttribute('data-theme', savedTheme);
            document.documentElement.setAttribute('data-bs-theme', savedTheme);
        })();
    </script>

    <style>
    /* Perbaikan Kontras Dropdown Option di Tema Gelap (Windows Chrome / Mobile) */
    select.form-select option, 
    select.form-control option, 
    select option {
        background-color: #111827 !important;
        color: #f8fafc !important;
        padding: 8px 12px;
    }
    select.form-select:focus,
    select.form-control:focus {
        border-color: #f59e0b !important;
        box-shadow: 0 0 0 0.25rem rgba(245, 158, 11, 0.25) !important;
    }

    /* Standardisasi Global Modal Pop-up Terpusat (Anti Tulisan di Bawah) */
    .login-modal-overlay,
    .custom-modal-overlay {
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        right: 0 !important;
        bottom: 0 !important;
        width: 100vw !important;
        height: 100vh !important;
        background: rgba(0, 0, 0, 0.82) !important;
        backdrop-filter: blur(8px) !important;
        -webkit-backdrop-filter: blur(8px) !important;
        z-index: 99999 !important;
        display: none;
        align-items: center !important;
        justify-content: center !important;
        padding: 1rem !important;
    }
    .login-error-modal,
    .custom-modal-box {
        background: #111827 !important;
        border: 1px solid #374151 !important;
        border-radius: 14px !important;
        max-width: 540px !important;
        width: 100% !important;
        padding: 1.75rem !important;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.85) !important;
        color: #f8fafc !important;
        position: relative !important;
        max-height: 90vh !important;
        overflow-y: auto !important;
        animation: modalPopCenter 0.2s cubic-bezier(0.16, 1, 0.3, 1) !important;
    }
    @keyframes modalPopCenter {
        from { opacity: 0; transform: scale(0.92); }
        to { opacity: 1; transform: scale(1); }
    }
    </style>
</head>
<body>

<?php
// Muat Navbar Ramping & Terkelompok
require_once __DIR__ . '/navbar.php';
?>
