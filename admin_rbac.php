<?php
// admin_rbac.php - Pengaturan Hak Akses Menu Header & Navigasi (RBAC Manager)
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/rbac.php';

Auth::requireRole(['super_admin', 'master_admin'], 'login.php');

$currentUser = Auth::user();
$clubId = (int)($currentUser['club_id'] ?? 1);
$currentRole = $_SESSION['role'] ?? 'super_admin';

// Ambil info klub
$stmtClub = $pdo->prepare("SELECT club_name, primary_color FROM archery_clubs WHERE id = :cid LIMIT 1");
$stmtClub->execute([':cid' => $clubId]);
$club = $stmtClub->fetch();

$alertMsg = '';
$alertType = 'success';

// Handle POST Save Permissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_rbac') {
        $permissions = $_POST['perm'] ?? []; // format: [role][menu_key] = 1

        $stmtSave = $pdo->prepare("
            INSERT INTO `archery_rbac_permissions` (`club_id`, `role`, `menu_key`, `is_visible`)
            VALUES (:cid, :role, :mkey, :vis)
            ON DUPLICATE KEY UPDATE `is_visible` = :vis2
        ");

        $managedRoles = ['coach', 'parent', 'archer'];
        foreach ($managedRoles as $r) {
            foreach (RBAC::MENUS as $mKey => $mInfo) {
                // Jangan nonaktifkan menu khusus admin untuk role selain admin
                if (in_array($mKey, ['cms_admin', 'settings', 'distances', 'approvals', 'rbac']) && $r !== 'super_admin') {
                    $isVisible = 0; // Role non-admin dilarang mengakses menu pengaturan sistem
                } else {
                    $isVisible = isset($permissions[$r][$mKey]) ? 1 : 0;
                }

                $stmtSave->execute([
                    ':cid' => $clubId,
                    ':role' => $r,
                    ':mkey' => $mKey,
                    ':vis'  => $isVisible,
                    ':vis2' => $isVisible
                ]);
            }
        }

        $alertMsg = 'Hak akses menu header berhasil disimpan! Tampilan navbar langsung disesuaikan dengan aturan ini.';
    }

    if ($action === 'reset_default') {
        $roleDefaults = [
            'coach'       => ['scoring', 'web_profil', 'rapot', 'rekap', 'medsos', 'coach_dashboard'],
            'parent'      => ['scoring', 'web_profil', 'rapot', 'rekap', 'medsos', 'bills'],
            'archer'      => ['scoring', 'web_profil']
        ];

        $stmtReset = $pdo->prepare("
            INSERT INTO `archery_rbac_permissions` (`club_id`, `role`, `menu_key`, `is_visible`)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE `is_visible` = VALUES(`is_visible`);
        ");

        foreach ($roleDefaults as $r => $allowedList) {
            foreach (RBAC::MENUS as $mKey => $mInfo) {
                $isVisible = in_array($mKey, $allowedList) ? 1 : 0;
                $stmtReset->execute([$clubId, $r, $mKey, $isVisible]);
            }
        }
        $alertMsg = 'Hak akses menu berhasil dikembalikan ke standar bawaan sistem.';
    }
}

// Ambil matriks izin saat ini
$matrix = RBAC::getClubMatrix($clubId, $pdo);

$pageTitle = "Hak Akses Menu Header (RBAC) - " . htmlspecialchars($club['club_name'] ?? 'Klub');
require_once __DIR__ . '/views/navbar.php';
?>

<div class="container pb-5">
    <div class="d-flex justify-content-between align-items-center mb-4 mt-2">
        <div>
            <h3 class="fw-bold mb-1 d-flex align-items-center gap-2">
                <i class="bi bi-shield-lock-fill text-warning"></i> Pengaturan Hak Akses Menu Header (RBAC)
            </h3>
            <p class="text-muted mb-0">
                Tentukan menu mana saja yang boleh tampil di navigasi atas (Desktop) &amp; menu bawah (Mobile) untuk tiap role pengguna di <strong><?= htmlspecialchars($club['club_name']) ?></strong>.
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="cms_admin.php" class="btn btn-outline-info btn-sm d-flex align-items-center gap-1">
                <i class="bi bi-window-desktop"></i> Kelola Web CMS
            </a>
            <a href="settings.php" class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-1">
                <i class="bi bi-gear-fill"></i> Pengaturan Klub
            </a>
        </div>
    </div>

    <?php if ($alertMsg): ?>
        <div class="alert alert-<?= $alertType ?> alert-dismissible fade show d-flex align-items-center gap-2 mb-4" role="alert">
            <i class="bi bi-check-circle-fill fs-5"></i>
            <div><?= htmlspecialchars($alertMsg) ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="card p-4 border-secondary shadow-sm">
        <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom border-secondary border-opacity-25">
            <div>
                <h5 class="fw-bold text-white mb-1"><i class="bi bi-layout-text-sidebar-reverse"></i> Matriks Visibilitas Menu Header</h5>
                <span class="text-muted small">Centang (✔) untuk memunculkan menu, hilangkan centang untuk menyembunyikan dari role tersebut.</span>
            </div>
            <form action="" method="POST" onsubmit="return confirm('Kembalikan seluruh hak akses menu ke pengaturan default bawaan?')">
                <input type="hidden" name="action" value="reset_default">
                <button type="submit" class="btn btn-outline-danger btn-sm">
                    <i class="bi bi-arrow-counterclockwise"></i> Reset ke Default
                </button>
            </form>
        </div>

        <form action="" method="POST">
            <input type="hidden" name="action" value="save_rbac">

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th style="min-width: 200px;">Nama Menu Navigasi</th>
                            <th class="text-center" style="width: 140px;">
                                <span class="badge bg-info text-dark fs-6">Pelatih (Coach)</span>
                            </th>
                            <th class="text-center" style="width: 140px;">
                                <span class="badge bg-warning text-dark fs-6">Orang Tua (Parent)</span>
                            </th>
                            <th class="text-center" style="width: 140px;">
                                <span class="badge bg-secondary fs-6">Mode PIN Anak</span>
                            </th>
                            <th class="text-center" style="width: 140px;">
                                <span class="badge bg-primary fs-6">Super Admin</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (RBAC::MENUS as $mKey => $mInfo): 
                            $isAdminOnly = in_array($mKey, ['cms_admin', 'settings', 'distances', 'approvals', 'rbac']);
                        ?>
                            <tr>
                                <td>
                                    <div class="fw-bold text-white d-flex align-items-center gap-2">
                                        <span class="fs-5"><?= $mInfo['icon'] ?></span>
                                        <span><?= htmlspecialchars($mInfo['label']) ?></span>
                                    </div>
                                    <span class="text-muted small" style="font-size: 11px;">Rute File: <code><?= $mInfo['url'] ?></code></span>
                                </td>

                                <!-- Coach Checkbox -->
                                <td class="text-center">
                                    <?php if ($isAdminOnly): ?>
                                        <span class="badge bg-dark text-muted" title="Menu khusus Super Admin">Terkunci</span>
                                    <?php else: ?>
                                        <div class="form-check form-switch d-flex justify-content-center">
                                            <input class="form-check-input" type="checkbox" name="perm[coach][<?= $mKey ?>]" value="1" <?= (!empty($matrix['coach'][$mKey])) ? 'checked' : '' ?>>
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <!-- Parent Checkbox -->
                                <td class="text-center">
                                    <?php if ($isAdminOnly): ?>
                                        <span class="badge bg-dark text-muted" title="Menu khusus Super Admin">Terkunci</span>
                                    <?php else: ?>
                                        <div class="form-check form-switch d-flex justify-content-center">
                                            <input class="form-check-input" type="checkbox" name="perm[parent][<?= $mKey ?>]" value="1" <?= (!empty($matrix['parent'][$mKey])) ? 'checked' : '' ?>>
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <!-- Archer (PIN Device) Checkbox -->
                                <td class="text-center">
                                    <?php if ($isAdminOnly || in_array($mKey, ['bills', 'coach_dashboard'])): ?>
                                        <span class="badge bg-dark text-muted">Terkunci</span>
                                    <?php else: ?>
                                        <div class="form-check form-switch d-flex justify-content-center">
                                            <input class="form-check-input" type="checkbox" name="perm[archer][<?= $mKey ?>]" value="1" <?= (!empty($matrix['archer'][$mKey])) ? 'checked' : '' ?>>
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <!-- Super Admin Always Checked -->
                                <td class="text-center">
                                    <span class="badge bg-success"><i class="bi bi-check-circle-fill"></i> Selalu Aktif</span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="mt-4 pt-3 border-top border-secondary border-opacity-25 d-flex justify-content-between align-items-center">
                <span class="text-muted small">
                    <i class="bi bi-info-circle"></i> Pengaturan ini langsung berlaku secara real-time saat pengguna memuat ulang halaman.
                </span>
                <button type="submit" class="btn btn-warning fw-bold text-dark px-4 py-2 shadow-sm">
                    <i class="bi bi-save-fill"></i> Simpan Pengaturan Hak Akses Header
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/views/footer.php'; ?>
