<?php
// tenant_activity_logs.php - Log Aktivitas Internal Klub untuk Super Admin Tenant
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/activity_logger.php';
require_once __DIR__ . '/config/tenant_resolver.php';

Auth::requireRole(['super_admin', 'master_admin'], 'login.php');

$currentUser = Auth::user();
$clubId = (int)($currentUser['club_id'] ?? 1);

// Ambil Nama Klub
$stmtClub = $pdo->prepare("SELECT club_name FROM archery_clubs WHERE id = :cid LIMIT 1");
$stmtClub->execute([':cid' => $clubId]);
$club = $stmtClub->fetch();
$clubName = $club['club_name'] ?? 'Klub Panahan';

// Filter
$filterRole = trim($_GET['role'] ?? '');
$filterAction = trim($_GET['action_type'] ?? '');
$filterDate = trim($_GET['date'] ?? '');
$search = trim($_GET['search'] ?? '');

$sql = "SELECT * FROM archery_activity_logs WHERE club_id = :cid";
$params = [':cid' => $clubId];

if ($filterRole !== '') {
    $sql .= " AND user_role = :role";
    $params[':role'] = $filterRole;
}
if ($filterAction !== '') {
    $sql .= " AND action_type = :atype";
    $params[':atype'] = $filterAction;
}
if ($filterDate !== '') {
    $sql .= " AND DATE(created_at) = :dt";
    $params[':dt'] = $filterDate;
}
if ($search !== '') {
    $sql .= " AND (username LIKE :q OR action_description LIKE :q OR ip_address LIKE :q)";
    $params[':q'] = "%{$search}%";
}

$sql .= " ORDER BY id DESC LIMIT 150";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Action Types untuk filter
$actionTypesStmt = $pdo->prepare("SELECT DISTINCT action_type FROM archery_activity_logs WHERE club_id = :cid ORDER BY action_type ASC");
$actionTypesStmt->execute([':cid' => $clubId]);
$actionTypes = $actionTypesStmt->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = 'Log Aktivitas Internal - ' . $clubName;
require_once __DIR__ . '/views/header.php';
require_once __DIR__ . '/views/navbar.php';
?>

<div class="container-fluid px-3 px-lg-5 py-4" style="max-width: 1560px;">

    <!-- TOP HEADER -->
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <div class="badge bg-primary bg-opacity-20 text-info border border-info border-opacity-25 px-3 py-1 rounded-pill mb-2">
                <i class="bi bi-shield-check"></i> Audit Keamanan Klub
            </div>
            <h2 class="fw-extrabold text-white mb-1">Log Aktivitas &bull; <?= htmlspecialchars($clubName) ?></h2>
            <p class="text-muted small mb-0">Catatan riwayat login, input scoring atlet, update konten, dan aktivitas pengurus klub.</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="cms_admin.php" class="btn btn-outline-info btn-sm px-3">
                <i class="bi bi-window-desktop"></i> Web CMS
            </a>
            <a href="settings.php" class="btn btn-outline-secondary btn-sm px-3">
                <i class="bi bi-gear"></i> Pengaturan Klub
            </a>
        </div>
    </div>

    <!-- FILTER CARD -->
    <div class="card p-3 mb-4 border-0 shadow-sm" style="background: var(--bg-card); border: 1px solid var(--border-color) !important;">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label text-muted small mb-1">Role Pengguna</label>
                <select name="role" class="form-select form-select-sm">
                    <option value="">-- Semua Role --</option>
                    <option value="super_admin" <?= $filterRole === 'super_admin' ? 'selected' : '' ?>>Super Admin</option>
                    <option value="coach" <?= $filterRole === 'coach' ? 'selected' : '' ?>>Coach (Pelatih)</option>
                    <option value="parent" <?= $filterRole === 'parent' ? 'selected' : '' ?>>Parent (Orang Tua)</option>
                    <option value="archer" <?= $filterRole === 'archer' ? 'selected' : '' ?>>Archer (Atlet)</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label text-muted small mb-1">Tipe Aksi</label>
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
            <div class="col-md-3">
                <label class="form-label text-muted small mb-1">Cari Keyword / IP</label>
                <input type="text" name="search" class="form-control form-control-sm" placeholder="User / Aksi..." value="<?= htmlspecialchars($search) ?>">
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
                            <th>Pengguna</th>
                            <th>Role</th>
                            <th>Tipe Aksi</th>
                            <th>Rincian Aktivitas</th>
                            <th>Alamat IP</th>
                            <th>Browser</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="bi bi-clock-history fs-2 d-block mb-2 opacity-50"></i>
                                    Belum ada log aktivitas yang tercatat untuk klub ini.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($logs as $l): ?>
                                <tr>
                                    <td class="px-3 text-muted text-nowrap font-monospace" style="font-size: 0.8rem;">
                                        <?= date('d/m/Y H:i:s', strtotime($l['created_at'])) ?>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-white"><?= htmlspecialchars($l['username'] ?: 'Guest') ?></div>
                                    </td>
                                    <td>
                                        <span class="badge bg-info bg-opacity-20 text-info" style="font-size: 0.72rem;">
                                            <?= strtoupper(str_replace('_', ' ', $l['user_role'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                            $badgeClass = 'bg-primary';
                                            if (str_contains($l['action_type'], 'LOGIN')) $badgeClass = 'bg-success';
                                            if (str_contains($l['action_type'], 'REJECT')) $badgeClass = 'bg-danger';
                                            if (str_contains($l['action_type'], 'UPDATE')) $badgeClass = 'bg-warning text-dark';
                                        ?>
                                        <span class="badge <?= $badgeClass ?> font-monospace" style="font-size: 0.72rem;">
                                            <?= htmlspecialchars($l['action_type']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="text-light" style="max-width: 420px;">
                                            <?= htmlspecialchars($l['action_description']) ?>
                                        </div>
                                    </td>
                                    <td class="font-monospace text-warning text-nowrap" style="font-size: 0.8rem;">
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
