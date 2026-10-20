<?php
// admin_distances.php - Manajemen Jarak Tembak Fleksibel & Syarat Kenaikan Jarak (Super Admin)
require_once __DIR__ . '/config/auth.php';
Auth::requireAuth();

$currentUser = Auth::user();
$currentRole = $_SESSION['role'] ?? 'parent';

// Proteksi RBAC: Hanya Super Admin
if ($currentRole !== 'super_admin') {
    die("Akses ditolak. Halaman master jarak hanya dapat diakses oleh Super Admin.");
}

$clubId = (int)($currentUser['club_id'] ?? 1);
$alertMsg = null;
$alertType = 'success';

// Handle Add / Edit / Toggle Distance
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_distance') {
        $distanceId = (int)($_POST['distance_id'] ?? 0);
        $distanceMeter = (int)($_POST['distance_meter'] ?? 0);
        $distanceLabel = trim($_POST['distance_label'] ?? '');
        $minSessions = (int)($_POST['min_sessions_required'] ?? 12);
        $minMonths = (int)($_POST['min_months_required'] ?? 2);
        $minScore = (int)($_POST['min_passing_score'] ?? 250);
        $description = trim($_POST['description'] ?? '');

        if ($distanceMeter <= 0 || empty($distanceLabel)) {
            $alertMsg = "Jarak meter dan label jarak wajib diisi.";
            $alertType = 'danger';
        } else {
            try {
                if ($distanceId > 0) {
                    // Update
                    $stmtUp = $pdo->prepare("
                        UPDATE archery_distances 
                        SET distance_meter = :dm, 
                            distance_label = :dl, 
                            min_sessions_required = :mins, 
                            min_months_required = :minm, 
                            min_passing_score = :minsc, 
                            description = :desc 
                        WHERE id = :id AND club_id = :cid
                    ");
                    $stmtUp->execute([
                        ':dm' => $distanceMeter,
                        ':dl' => $distanceLabel,
                        ':mins' => $minSessions,
                        ':minm' => $minMonths,
                        ':minsc' => $minScore,
                        ':desc' => $description,
                        ':id' => $distanceId,
                        ':cid' => $clubId
                    ]);
                    $alertMsg = "✔ Parameter jarak berhasil diperbarui!";
                } else {
                    // Insert
                    $stmtIn = $pdo->prepare("
                        INSERT INTO archery_distances 
                        (club_id, distance_meter, distance_label, min_sessions_required, min_months_required, min_passing_score, description)
                        VALUES (:cid, :dm, :dl, :mins, :minm, :minsc, :desc)
                        ON DUPLICATE KEY UPDATE 
                            distance_label = VALUES(distance_label),
                            min_sessions_required = VALUES(min_sessions_required),
                            min_passing_score = VALUES(min_passing_score),
                            description = VALUES(description)
                    ");
                    $stmtIn->execute([
                        ':cid' => $clubId,
                        ':dm' => $distanceMeter,
                        ':dl' => $distanceLabel,
                        ':mins' => $minSessions,
                        ':minm' => $minMonths,
                        ':minsc' => $minScore,
                        ':desc' => $description
                    ]);
                    $alertMsg = "✔ Jarak tembak baru berhasil ditambahkan!";
                }
            } catch (PDOException $e) {
                $alertMsg = "Gagal menyimpan data jarak: " . $e->getMessage();
                $alertType = 'danger';
            }
        }
    } elseif ($action === 'toggle_status') {
        $distId = (int)($_POST['distance_id'] ?? 0);
        $newStatus = (int)($_POST['is_active'] ?? 1);
        try {
            $stmtTog = $pdo->prepare("UPDATE archery_distances SET is_active = :st WHERE id = :id AND club_id = :cid");
            $stmtTog->execute([':st' => $newStatus, ':id' => $distId, ':cid' => $clubId]);
            $alertMsg = "✔ Status jarak berhasil diubah.";
        } catch (PDOException $e) {
            $alertMsg = "Gagal mengubah status: " . $e->getMessage();
            $alertType = 'danger';
        }
    }
}

// Ambil seluruh master jarak untuk klub ini
$stmtDistances = $pdo->prepare("
    SELECT * FROM archery_distances 
    WHERE club_id = :cid 
    ORDER BY distance_meter ASC
");
$stmtDistances->execute([':cid' => $clubId]);
$distances = $stmtDistances->fetchAll();
$pageTitle = "Master Jarak & Syarat Kenaikan - TARGET ARCHERY";
require_once __DIR__ . '/views/header.php';
?>

<main class="container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h1 style="font-size: 1.6rem; margin-bottom: 0.25rem;">
                📏 Master Jarak & Syarat Ujian Kenaikan
            </h1>
            <p style="color: var(--text-muted); font-size: 0.85rem; margin: 0;">
                Tentukan jarak tembak fleksibel, jam terbang latihan minimal, dan batas skor kelulusan ujian (Passing Score / 300).
            </p>
        </div>
        <div>
            <button type="button" class="btn btn-primary btn-sm" onclick="openAddDistanceModal()">
                ➕ Tambah Jarak Baru
            </button>
        </div>
    </div>

    <?php if ($alertMsg): ?>
        <div class="card" style="margin-bottom: 1.5rem; padding: 1rem 1.25rem; border-left: 4px solid <?= $alertType === 'danger' ? 'var(--danger)' : 'var(--success)' ?>; background: rgba(0,0,0,0.2);">
            <?= htmlspecialchars($alertMsg) ?>
        </div>
    <?php endif; ?>

    <!-- PENJELASAN ATURAN JAM TERBANG & DUAL PATHWAY -->
    <div class="card" style="margin-bottom: 1.5rem; border-left: 4px solid var(--primary); background: rgba(2, 132, 199, 0.08); padding: 1.25rem;">
        <h3 style="margin: 0 0 0.5rem 0; font-size: 1rem; color: var(--primary);">
            💡 Solusi Fleksibilitas Jam Terbang Atlet
        </h3>
        <p style="font-size: 0.82rem; color: var(--text-muted); line-height: 1.5; margin: 0;">
            Sistem Target Archery menggunakan **Dual Pathway** agar adil bagi atlet yang latihan 1x seminggu maupun 3x+ seminggu:<br>
            1. <b>Jalur Jam Terbang Latihan (Total Sesi)</b>: Jika atlet mencapai kuota sesi latihan (cth: 12 sesi), sistem otomatis membuka hak Ujian Kenaikan Jarak tanpa harus menunggu berbulan-bulan.<br>
            2. <b>Jalur Rekomendasi Khusus Coach (Fast-Track)</b>: Coach berhak memberikan izin ujian sewaktu-waktu jika teknik atlet sudah sangat matang sebelum kuota sesi terpenuhi.
        </p>
    </div>

    <!-- TABEL MASTER JARAK -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Daftar Jarak Tembak Terdaftar</h2>
        </div>

        <div style="overflow-x: auto;">
            <table class="scorecard-table">
                <thead>
                    <tr>
                        <th>Jarak (Meter)</th>
                        <th>Label Tingkatan</th>
                        <th>Min. Sesi Latihan</th>
                        <th>Min. Passing Score</th>
                        <th>Keterangan / Fokus Teknik</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($distances as $d): ?>
                        <tr>
                            <td>
                                <span class="badge badge-gold" style="font-size: 0.95rem; font-weight: 800;">
                                    <?= $d['distance_meter'] ?> Meter
                                </span>
                            </td>
                            <td>
                                <b><?= htmlspecialchars($d['distance_label']) ?></b>
                            </td>
                            <td>
                                <b><?= (int)$d['min_sessions_required'] ?> Sesi</b><br>
                                <span style="font-size: 0.72rem; color: var(--text-dim);">(atau ~<?= (int)$d['min_months_required'] ?> Bulan)</span>
                            </td>
                            <td>
                                <b style="color: #34d399; font-size: 1.05rem;"><?= (int)$d['min_passing_score'] ?></b>
                                <span style="font-size: 0.75rem; color: var(--text-dim);">/ 300</span>
                            </td>
                            <td style="font-size: 0.82rem; color: var(--text-muted); max-width: 250px;">
                                <?= htmlspecialchars($d['description'] ?? '-') ?>
                            </td>
                            <td>
                                <?php if ($d['is_active']): ?>
                                    <span class="badge badge-success">Aktif</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Nonaktif</span>
                                <?php endif; ?>
                            </td>
                            <td style="white-space: nowrap;">
                                <button type="button" class="btn btn-secondary btn-sm" onclick='openEditModal(<?= json_encode($d) ?>)'>
                                    ✏️ Edit
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- MODAL ADD / EDIT DISTANCE -->
<div id="distanceModal" class="login-modal-overlay" style="display: none;">
    <div class="login-error-modal" style="max-width: 520px; text-align: left;">
        <h3 id="modalTitle" style="margin-top: 0; color: var(--primary);">➕ Tambah Jarak Tembak Baru</h3>
        
        <form method="POST">
            <input type="hidden" name="action" value="save_distance">
            <input type="hidden" name="distance_id" id="distId" value="0">

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                <div class="form-group">
                    <label class="form-label">Jarak (Angka Meter):</label>
                    <input type="number" class="form-control" name="distance_meter" id="distMeter" required placeholder="cth: 5">
                </div>

                <div class="form-group">
                    <label class="form-label">Min. Passing Score (/300):</label>
                    <input type="number" class="form-control" name="min_passing_score" id="distMinScore" required placeholder="cth: 240">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Label Tingkatan Jarak:</label>
                <input type="text" class="form-control" name="distance_label" id="distLabel" required placeholder="cth: Jarak 5 Meter (Dasar Pemula)">
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                <div class="form-group">
                    <label class="form-label">Min. Sesi Latihan:</label>
                    <input type="number" class="form-control" name="min_sessions_required" id="distMinSessions" value="12" required>
                    <span style="font-size: 0.72rem; color: var(--text-dim);">Flight hours minimal sebelum ujian terbuka.</span>
                </div>

                <div class="form-group">
                    <label class="form-label">Estimasi Bulan:</label>
                    <input type="number" class="form-control" name="min_months_required" id="distMinMonths" value="2" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Keterangan / Fokus Penilaian:</label>
                <input type="text" class="form-control" name="description" id="distDesc" placeholder="cth: Fokus anchor point konsisten & follow-through">
            </div>

            <div style="display: flex; gap: 0.75rem; margin-top: 1.25rem;">
                <button type="button" class="btn btn-secondary btn-block" onclick="closeDistanceModal()">Batal</button>
                <button type="submit" class="btn btn-primary btn-block">💾 Simpan Jarak</button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddDistanceModal() {
    document.getElementById('modalTitle').textContent = '➕ Tambah Jarak Tembak Baru';
    document.getElementById('distId').value = '0';
    document.getElementById('distMeter').value = '';
    document.getElementById('distLabel').value = '';
    document.getElementById('distMinSessions').value = '12';
    document.getElementById('distMinMonths').value = '2';
    document.getElementById('distMinScore').value = '250';
    document.getElementById('distDesc').value = '';
    document.getElementById('distanceModal').style.display = 'flex';
}

function openEditModal(d) {
    document.getElementById('modalTitle').textContent = '✏️ Edit Jarak ' + d.distance_meter + ' Meter';
    document.getElementById('distId').value = d.id;
    document.getElementById('distMeter').value = d.distance_meter;
    document.getElementById('distLabel').value = d.distance_label;
    document.getElementById('distMinSessions').value = d.min_sessions_required;
    document.getElementById('distMinMonths').value = d.min_months_required;
    document.getElementById('distMinScore').value = d.min_passing_score;
    document.getElementById('distDesc').value = d.description || '';
    document.getElementById('distanceModal').style.display = 'flex';
}

function closeDistanceModal() {
    document.getElementById('distanceModal').style.display = 'none';
}
</script>

<?php include __DIR__ . '/views/footer.php'; ?>
