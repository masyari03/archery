<?php
// master_user_sessions.php - Manajemen Sesi Pengguna Aktif & Fitur Putus Sesi (Kill Session)
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/activity_logger.php';
require_once __DIR__ . '/config/tenant_resolver.php';

Auth::requireRole(['master_admin'], 'login.php');

$currentUser = Auth::user();
$alertMsg = '';
$alertType = 'success';

// Handle Revoke / Kill Session
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'revoke_session') {
    $sid = trim($_POST['session_id'] ?? '');
    if (!empty($sid)) {
        if (ActivityLogger::revokeSession($pdo, $sid)) {
            $alertMsg = "✔ Sesi pengguna berhasil diputus secara instan! Pengguna tersebut akan otomatis logout pada permintaan halaman berikutnya.";
            ActivityLogger::log($pdo, 1, $currentUser['id'], $currentUser['username'], 'master_admin', 'SESSION_TERMINATED', "Master Admin memutuskan paksa sesi ID: " . substr($sid, 0, 16) . "...");
        } else {
            $alertMsg = "Gagal memutuskan sesi.";
            $alertType = 'danger';
        }
    }
}

// Ambil Seluruh Sesi Aktif
$stmtSessions = $pdo->query("
    SELECT s.*, c.club_name, u.full_name
    FROM archery_user_sessions s
    LEFT JOIN archery_clubs c ON c.id = s.club_id
    LEFT JOIN archery_users u ON u.id = s.user_id
    ORDER BY s.is_revoked ASC, s.last_activity_at DESC
    LIMIT 100
");
$sessions = $stmtSessions->fetchAll();

$pageTitle = 'Monitor Sesi Online & Putus Sesi';
require_once __DIR__ . '/views/header.php';
require_once __DIR__ . '/views/navbar.php';
?>

<div class="container-fluid px-3 px-lg-5 py-4" style="max-width: 1560px;">

    <!-- TOP HEADER -->
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <div class="badge bg-danger bg-opacity-20 text-danger border border-danger border-opacity-25 px-3 py-1 rounded-pill mb-2">
                <i class="bi bi-shield-lock-fill"></i> Real-Time Session Security
            </div>
            <h2 class="fw-extrabold text-white mb-1">Monitor Sesi Pengguna &amp; Kontrol Akses IP</h2>
            <p class="text-muted small mb-0">Pantau seluruh pengguna yang sedang online. Putus paksa sesi mencurigakan secara instan.</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="master_activity_logs.php" class="btn btn-outline-warning fw-bold btn-sm px-3 shadow-sm">
                <i class="bi bi-journal-text"></i> Lihat Log Aktivitas Audit
            </a>
            <a href="master_tenants.php" class="btn btn-outline-secondary btn-sm px-3">
                <i class="bi bi-arrow-left"></i> Kembali ke Tenant
            </a>
        </div>
    </div>

    <?php if ($alertMsg): ?>
        <div class="alert alert-<?= $alertType ?> alert-dismissible fade show rounded-3 p-3 mb-4 shadow-sm" role="alert">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-check-circle-fill fs-5"></i>
                <div><?= $alertMsg ?></div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- SESSION TABLE -->
    <div class="card border-0 shadow-sm" style="background: var(--bg-card); border: 1px solid var(--border-color) !important;">
        <div class="card-header bg-dark bg-opacity-50 py-3 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold text-white mb-0 fs-6">
                <i class="bi bi-broadcast text-success animate-pulse me-2"></i> Daftar Sesi Pengguna Terkoneksi (Maks. 100 Sesi Terbaru)
            </h5>
            <span class="badge bg-secondary"><?= count($sessions) ?> Sesi Tercatat</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="font-size: 0.85rem;">
                    <thead style="background: rgba(0,0,0,0.25);">
                        <tr>
                            <th class="py-3 px-3">Status</th>
                            <th>Pengguna</th>
                            <th>Role &amp; Tenant</th>
                            <th>IP Address</th>
                            <th>Device / Browser</th>
                            <th>Waktu Login</th>
                            <th>Aktivitas Terakhir</th>
                            <th class="text-end px-3">Tindakan Keamanan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($sessions)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">
                                    <i class="bi bi-laptop-fill fs-2 d-block mb-2 opacity-50"></i>
                                    Belum ada catatan sesi login aktif di database.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($sessions as $s): ?>
                                <?php 
                                    $isRevoked = (int)$s['is_revoked'] === 1;
                                    $isCurrentSession = ($s['session_id'] === session_id());
                                ?>
                                <tr class="<?= $isRevoked ? 'opacity-50 bg-dark bg-opacity-25' : '' ?>">
                                    <td class="px-3">
                                        <?php if ($isRevoked): ?>
                                            <span class="badge bg-danger"><i class="bi bi-x-circle"></i> Diputus</span>
                                        <?php else: ?>
                                            <span class="badge bg-success bg-opacity-25 text-success border border-success border-opacity-25">
                                                <i class="bi bi-circle-fill" style="font-size: 8px;"></i> Aktif
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-white"><?= htmlspecialchars($s['full_name'] ?: $s['username']) ?></div>
                                        <span class="text-muted small">@<?= htmlspecialchars($s['username']) ?></span>
                                    </td>
                                    <td>
                                        <div class="badge bg-info bg-opacity-20 text-info mb-1" style="font-size: 0.7rem;">
                                            <?= strtoupper(str_replace('_', ' ', $s['user_role'])) ?>
                                        </div>
                                        <div class="text-muted small"><?= htmlspecialchars($s['club_name'] ?: 'Platform Root') ?></div>
                                    </td>
                                    <td class="font-monospace text-warning fw-bold text-nowrap">
                                        <i class="bi bi-geo-alt-fill text-danger me-1"></i> <?= htmlspecialchars($s['ip_address']) ?>
                                    </td>
                                    <td class="text-muted small" style="max-width: 250px;">
                                        <?= htmlspecialchars(substr($s['user_agent'], 0, 60)) ?>...
                                    </td>
                                    <td class="text-muted text-nowrap font-monospace" style="font-size: 0.8rem;">
                                        <?= date('d/m H:i', strtotime($s['login_at'])) ?>
                                    </td>
                                    <td class="text-muted text-nowrap font-monospace" style="font-size: 0.8rem;">
                                        <?= date('d/m H:i:s', strtotime($s['last_activity_at'])) ?>
                                    </td>
                                    <td class="text-end px-3">
                                        <?php if ($isCurrentSession): ?>
                                            <span class="badge bg-primary">Sesi Anda Saat Ini</span>
                                        <?php elseif ($isRevoked): ?>
                                            <span class="text-muted small">Telah Dicabut</span>
                                        <?php else: ?>
                                            <form method="POST" onsubmit="return confirm('PERINGATAN: Apakah Anda yakin ingin MEMUTUS PAKSA sesi pengguna ini? Pengguna akan langsung terlempar keluar dari sistem.');" style="display: inline;">
                                                <input type="hidden" name="action" value="revoke_session">
                                                <input type="hidden" name="session_id" value="<?= htmlspecialchars($s['session_id']) ?>">
                                                <button type="submit" class="btn btn-outline-danger btn-sm py-1 px-2 fw-bold" title="Putus Sesi Sekarang">
                                                    <i class="bi bi-power"></i> Putus Sesi
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/views/footer.php'; ?>
