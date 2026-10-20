<?php
// master_activity_logs.php - Audit Trail & Log Aktivitas Seluruh Tenant (Master Admin Platform)
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/activity_logger.php';
require_once __DIR__ . '/config/tenant_resolver.php';

Auth::requireRole(['master_admin'], 'login.php');

$currentUser = Auth::user();
$alertMsg = '';

// Filter Parameters
$filterClub = (int)($_GET['club_id'] ?? 0);
$filterRole = trim($_GET['role'] ?? '');
$filterAction = trim($_GET['action_type'] ?? '');
$filterDate = trim($_GET['date'] ?? '');
$search = trim($_GET['search'] ?? '');

// Build Query
$sql = "
    SELECT l.*, c.club_name
    FROM archery_activity_logs l
    LEFT JOIN archery_clubs c ON c.id = l.club_id
    WHERE 1=1
";
$params = [];

if ($filterClub > 0) {
    $sql .= " AND l.club_id = :cid";
    $params[':cid'] = $filterClub;
}
if ($filterRole !== '') {
    $sql .= " AND l.user_role = :role";
    $params[':role'] = $filterRole;
}
if ($filterAction !== '') {
    $sql .= " AND l.action_type = :atype";
    $params[':atype'] = $filterAction;
}
if ($filterDate !== '') {
    $sql .= " AND DATE(l.created_at) = :dt";
    $params[':dt'] = $filterDate;
}
if ($search !== '') {
    $sql .= " AND (l.username LIKE :q OR l.action_description LIKE :q OR l.ip_address LIKE :q)";
    $params[':q'] = "%{$search}%";
}

$sql .= " ORDER BY l.id DESC LIMIT 200";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Daftar Seluruh Tenant untuk Dropdown Filter
$allTenants = TenantResolver::getAllTenants($pdo);

// Daftar Action Types unik untuk filter
$actionTypesStmt = $pdo->query("SELECT DISTINCT action_type FROM archery_activity_logs ORDER BY action_type ASC");
$actionTypes = $actionTypesStmt->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = 'Audit Trail & Log Aktivitas Seluruh Tenant';
require_once __DIR__ . '/views/header.php';
require_once __DIR__ . '/views/navbar.php';
?>

<div class="container-fluid px-3 px-lg-5 py-4" style="max-width: 1560px;">

    <!-- TOP HEADER -->
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <div class="badge bg-warning bg-opacity-20 text-warning border border-warning border-opacity-25 px-3 py-1 rounded-pill mb-2">
                <i class="bi bi-shield-check"></i> Platform Audit Trail
            </div>
            <h2 class="fw-extrabold text-white mb-1">Log Aktivitas Seluruh Tenant</h2>
            <p class="text-muted small mb-0">Audit keamanan dan pencatatan aksi lintas tenant klub di platform SaaS.</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="master_user_sessions.php" class="btn btn-outline-danger fw-bold btn-sm px-3 shadow-sm">
                <i class="bi bi-laptop"></i> Monitor Sesi Online &amp; Putus Sesi
            </a>
            <a href="master_tenants.php" class="btn btn-outline-secondary btn-sm px-3">
                <i class="bi bi-arrow-left"></i> Kembali ke Tenant
            </a>
        </div>
    </div>

    <!-- FILTER CARD -->
    <div class="card p-3 mb-4 border-0 shadow-sm" style="background: var(--bg-card); border: 1px solid var(--border-color) !important;">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label text-muted small mb-1">Filter Tenant Klub</label>
                <select name="club_id" class="form-select form-select-sm">
                    <option value="0">-- Semua Tenant Klub --</option>
                    <?php foreach ($allTenants as $t): ?>
                        <option value="<?= $t['id'] ?>" <?= $filterClub === (int)$t['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($t['club_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label text-muted small mb-1">Role Pengguna</label>
                <select name="role" class="form-select form-select-sm">
                    <option value="">-- Semua Role --</option>
                    <option value="master_admin" <?= $filterRole === 'master_admin' ? 'selected' : '' ?>>Master Admin</option>
                    <option value="super_admin" <?= $filterRole === 'super_admin' ? 'selected' : '' ?>>Super Admin</option>
                    <option value="coach" <?= $filterRole === 'coach' ? 'selected' : '' ?>>Coach</option>
                    <option value="parent" <?= $filterRole === 'parent' ? 'selected' : '' ?>>Parent</option>
                    <option value="archer" <?= $filterRole === 'archer' ? 'selected' : '' ?>>Archer</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label text-muted small mb-1">Jenis Aktivitas</label>
                <select name="action_type" class="form-select form-select-sm">
                    <option value="">-- Semua Aksi --</option>
                    <?php foreach ($actionTypes as $at): ?>
                        <option value="<?= htmlspecialchars($at) ?>" <?= $filterAction === $at ? 'selected' : '' ?>>
                            <?= htmlspecialchars($at) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label text-muted small mb-1">Tanggal</label>
                <input type="date" name="date" class="form-select form-select-sm" value="<?= htmlspecialchars($filterDate) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label text-muted small mb-1">Cari Keyword / IP</label>
                <input type="text" name="search" class="form-control form-control-sm" placeholder="User / IP / Aksi..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-primary btn-sm w-100 fw-bold">
                    <i class="bi bi-funnel"></i> Filter
                </button>
            </div>
        </form>
    </div>

    <!-- LOG TABLE -->
    <div class="card border-0 shadow-sm" style="background: var(--bg-card); border: 1px solid var(--border-color) !important;">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="font-size: 0.85rem;">
                    <thead style="background: rgba(0,0,0,0.25);">
                        <tr>
                            <th class="py-3 px-3">Waktu (WIB)</th>
                            <th>Tenant Klub</th>
                            <th>User &amp; Role</th>
                            <th>Tipe Aksi</th>
                            <th>Deskripsi Aktivitas</th>
                            <th>IP Address</th>
                            <th>Device / Browser</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="bi bi-journal-x fs-2 d-block mb-2 opacity-50"></i>
                                    Tidak ada catatan log aktivitas yang cocok dengan kriteria filter.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($logs as $l): ?>
                                <tr>
                                    <td class="px-3 text-muted text-nowrap font-monospace" style="font-size: 0.8rem;">
                                        <?= date('d/m/Y H:i:s', strtotime($l['created_at'])) ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary bg-opacity-25 text-light border border-secondary border-opacity-25">
                                            <?= htmlspecialchars($l['club_name'] ?: 'Platform Root') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-white"><?= htmlspecialchars($l['username'] ?: 'Guest') ?></div>
                                        <span class="badge bg-info bg-opacity-20 text-info" style="font-size: 0.7rem;">
                                            <?= strtoupper(str_replace('_', ' ', $l['user_role'] ?: 'UNKNOWN')) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                            $badgeClass = 'bg-primary';
                                            if (str_contains($l['action_type'], 'LOGIN')) $badgeClass = 'bg-success';
                                            if (str_contains($l['action_type'], 'REJECT') || str_contains($l['action_type'], 'BREACH')) $badgeClass = 'bg-danger';
                                            if (str_contains($l['action_type'], 'UPDATE')) $badgeClass = 'bg-warning text-dark';
                                        ?>
                                        <span class="badge <?= $badgeClass ?> font-monospace" style="font-size: 0.72rem;">
                                            <?= htmlspecialchars($l['action_type']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="text-light" style="max-width: 380px;">
                                            <?= htmlspecialchars($l['action_description']) ?>
                                        </div>
                                    </td>
                                    <td class="font-monospace text-info text-nowrap" style="font-size: 0.8rem;">
                                        <?= htmlspecialchars($l['ip_address']) ?>
                                    </td>
                                    <td class="text-muted small" style="max-width: 220px; font-size: 0.75rem;">
                                        <?= htmlspecialchars(substr($l['user_agent'], 0, 50)) ?>...
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
