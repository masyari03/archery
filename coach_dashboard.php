<?php
// coach_dashboard.php - Dashboard Khusus Pelatih (Kelola Atlet Binaan, Izin Naik Jarak & Evaluasi Sesi)
require_once __DIR__ . '/config/auth.php';
Auth::requireAuth();

$currentUser = Auth::user();
$currentRole = $_SESSION['role'] ?? 'parent';

// Proteksi RBAC: Hanya Coach & Super Admin yang berhak mengakses
if ($currentRole !== 'coach' && $currentRole !== 'super_admin') {
    die("Akses ditolak. Halaman ini khusus untuk Pelatih dan Super Admin.");
}

$coachId = (int)$currentUser['id'];
$clubId = (int)($currentUser['club_id'] ?? 1);
$alertMsg = null;
$alertType = 'success';

// Handle Action: Beri Izin Ujian atau Tunda/Batalkan Izin Ujian Naik Jarak
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $archerId = (int)($_POST['archer_id'] ?? 0);
    $notes = trim($_POST['coach_notes'] ?? '');

    if ($archerId > 0) {
        try {
            if ($action === 'grant_exam') {
                $stmtGrant = $pdo->prepare("
                    UPDATE archery_archers 
                    SET can_exam_promotion = 1, 
                        coach_exam_notes = :notes 
                    WHERE id = :aid AND club_id = :cid
                ");
                $stmtGrant->execute([
                    ':notes' => $notes ?: 'Direkomendasikan oleh Coach untuk Ujian Kenaikan Jarak.',
                    ':aid' => $archerId,
                    ':cid' => $clubId
                ]);
                $alertMsg = "✔ Izin ujian kenaikan jarak berhasil DIBERIKAN kepada atlet.";
            } elseif ($action === 'revoke_exam') {
                $stmtRevoke = $pdo->prepare("
                    UPDATE archery_archers 
                    SET can_exam_promotion = 0, 
                        coach_exam_notes = :notes 
                    WHERE id = :aid AND club_id = :cid
                ");
                $stmtRevoke->execute([
                    ':notes' => $notes ?: 'Izin ujian ditunda sementara oleh Coach untuk pematangan teknik.',
                    ':aid' => $archerId,
                    ':cid' => $clubId
                ]);
                $alertMsg = "⚠️ Izin ujian kenaikan jarak berhasil DITUNDA / DIBATALKAN.";
                $alertType = 'warning';
            }
        } catch (PDOException $e) {
            $alertMsg = "Gagal memproses perubahan: " . $e->getMessage();
            $alertType = 'danger';
        }
    }
}

// Ambil Daftar Atlet yang dipegang Coach ini (atau semua jika super_admin)
if ($currentRole === 'super_admin') {
    $stmtArchers = $pdo->prepare("
        SELECT a.*, u.full_name AS coach_name
        FROM archery_archers a
        LEFT JOIN archery_users u ON u.id = a.assigned_coach_id
        WHERE a.club_id = :cid AND a.is_active = 1
        ORDER BY a.full_name ASC
    ");
    $stmtArchers->execute([':cid' => $clubId]);
} else {
    $stmtArchers = $pdo->prepare("
        SELECT a.*, u.full_name AS coach_name
        FROM archery_archers a
        LEFT JOIN archery_users u ON u.id = a.assigned_coach_id
        WHERE a.club_id = :cid AND (a.assigned_coach_id = :coach_id OR a.assigned_coach_id IS NULL) AND a.is_active = 1
        ORDER BY a.full_name ASC
    ");
    $stmtArchers->execute([':cid' => $clubId, ':coach_id' => $coachId]);
}
$assignedArchers = $stmtArchers->fetchAll();

// Ambil Riwayat Sesi Latihan Terbaru yang ditangani oleh Coach ini
if ($currentRole === 'super_admin') {
    $stmtRecentSessions = $pdo->prepare("
        SELECT s.*, a.full_name AS archer_name, a.nis
        FROM archery_sessions s
        JOIN archery_archers a ON a.id = s.archer_id
        WHERE s.club_id = :cid
        ORDER BY s.session_date DESC, s.id DESC
        LIMIT 20
    ");
    $stmtRecentSessions->execute([':cid' => $clubId]);
} else {
    $stmtRecentSessions = $pdo->prepare("
        SELECT s.*, a.full_name AS archer_name, a.nis
        FROM archery_sessions s
        JOIN archery_archers a ON a.id = s.archer_id
        WHERE s.club_id = :cid AND s.coach_id = :coach_id
        ORDER BY s.session_date DESC, s.id DESC
        LIMIT 20
    ");
    $stmtRecentSessions->execute([':cid' => $clubId, ':coach_id' => $coachId]);
}
$recentSessions = $stmtRecentSessions->fetchAll();

// Ambil Master Kriteria Evaluasi / Rubrik Penilaian Atlet untuk Klub ini
$stmtCriteria = $pdo->prepare("SELECT * FROM archery_eval_criteria WHERE club_id = :cid AND is_active = 1 ORDER BY sort_order ASC, id ASC");
$stmtCriteria->execute([':cid' => $clubId]);
$evalCriteriaList = $stmtCriteria->fetchAll();
if (empty($evalCriteriaList)) {
    // Fallback jika belum ada kriteria spesifik klub
    $stmtCriteria = $pdo->query("SELECT * FROM archery_eval_criteria WHERE is_active = 1 ORDER BY sort_order ASC, id ASC");
    $evalCriteriaList = $stmtCriteria->fetchAll();
}

$groupedCriteria = [];
$totalMaxPoints = 0;
foreach ($evalCriteriaList as $item) {
    $cat = $item['category'] ?: 'Umum';
    $groupedCriteria[$cat][] = $item;
    $totalMaxPoints += (int)$item['points'];
}

$pageTitle = "Dashboard Pelatih - TARGET ARCHERY";
require_once __DIR__ . '/views/header.php';
?>
<style>
    .coach-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 1.25rem;
        margin-top: 1rem;
    }
    .archer-card {
        border: 1px solid var(--border-color);
        background: var(--bg-card);
        border-radius: var(--radius-md);
        padding: 1.25rem;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        box-shadow: var(--shadow);
        transition: transform 0.2s, border-color 0.2s;
    }
    .archer-card:hover {
        border-color: var(--primary);
        transform: translateY(-2px);
    }
    .archer-card-header {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        margin-bottom: 0.75rem;
    }
    .archer-avatar {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        background: rgba(2, 132, 199, 0.15);
        color: var(--primary);
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        font-size: 1.2rem;
        border: 1px solid var(--primary);
    }
    .stat-chip {
        background: rgba(255, 255, 255, 0.04);
        border: 1px solid var(--border-color);
        padding: 0.35rem 0.65rem;
        border-radius: var(--radius-sm);
        font-size: 0.78rem;
    }
</style>

<main class="container">
    <!-- HEADER BAR -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h1 style="font-size: 1.6rem; margin-bottom: 0.25rem;">
                👨‍🏫 Dashboard Pelatih & Penguji
            </h1>
            <p style="color: var(--text-muted); font-size: 0.85rem; margin: 0;">
                Kelola penilaian atlet binaan, rekomendasi kenaikan jarak, dan catatan evaluasi sesi.
            </p>
        </div>
        <div>
            <span class="badge badge-primary" style="font-size: 0.85rem;">
                Pelatih: <?= htmlspecialchars($currentUser['full_name']) ?>
            </span>
        </div>
    </div>

    <?php if ($alertMsg): ?>
        <div class="card" style="margin-bottom: 1.5rem; padding: 1rem 1.25rem; border-left: 4px solid <?= $alertType === 'danger' ? 'var(--danger)' : ($alertType === 'warning' ? '#f59e0b' : 'var(--success)') ?>; background: rgba(0,0,0,0.2);">
            <?= htmlspecialchars($alertMsg) ?>
        </div>
    <?php endif; ?>

    <!-- SECTION 1: ATLET BINAAN & REKOMENDASI UJIAN NAIK JARAK -->
    <div class="card" style="margin-bottom: 2rem;">
        <div class="card-header">
            <h2 class="card-title">
                🏹 Atlet Binaan Saya (<?= count($assignedArchers) ?> Atlet)
            </h2>
            <span style="font-size: 0.8rem; color: var(--text-dim);">
                Pelatih berhak memberikan atau menunda izin ujian kenaikan jarak
            </span>
        </div>

        <?php if (empty($assignedArchers)): ?>
            <p style="color: var(--text-muted); text-align: center; padding: 2rem;">
                Belum ada atlet yang ditugaskan ke Anda. Hubungi Super Admin untuk penugasan atlet.
            </p>
        <?php else: ?>
            <div class="coach-grid">
                <?php foreach ($assignedArchers as $a): ?>
                    <div class="archer-card">
                        <div>
                            <div class="archer-card-header">
                                <div class="archer-avatar">
                                    <?= strtoupper(substr($a['full_name'], 0, 1)) ?>
                                </div>
                                <div style="flex: 1; overflow: hidden;">
                                    <div style="font-weight: 700; font-size: 1rem; color: #fff; text-overflow: ellipsis; white-space: nowrap; overflow: hidden;">
                                        <?= htmlspecialchars($a['full_name']) ?>
                                    </div>
                                    <div style="font-size: 0.78rem; color: var(--text-muted);">
                                        NIS: <?= htmlspecialchars($a['nis']) ?> • <?= htmlspecialchars($a['bow_category']) ?>
                                    </div>
                                </div>
                            </div>

                            <!-- STATS RINGKAS -->
                            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 1rem;">
                                <div class="stat-chip">
                                    🎯 Jarak: <b style="color: var(--primary);"><?= $a['current_distance_meter'] ?>m</b>
                                </div>
                                <div class="stat-chip">
                                    🏹 Sesi: <b><?= (int)$a['total_sessions_count'] ?>x</b>
                                </div>
                                <div class="stat-chip">
                                    ⭐ PB: <b><?= (int)$a['personal_best_score'] ?></b>
                                </div>
                            </div>

                            <!-- STATUS IZIN UJIAN KENAIKAN JARAK -->
                            <div style="padding: 0.75rem; border-radius: var(--radius-sm); margin-bottom: 1rem; background: <?= $a['can_exam_promotion'] ? 'rgba(16, 185, 129, 0.1)' : 'rgba(255, 255, 255, 0.03)' ?>; border: 1px solid <?= $a['can_exam_promotion'] ? 'var(--success)' : 'var(--border-color)' ?>;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.25rem;">
                                    <span style="font-size: 0.78rem; font-weight: 700; color: <?= $a['can_exam_promotion'] ? 'var(--success)' : 'var(--text-muted)' ?>;">
                                        <?= $a['can_exam_promotion'] ? '✔ IZIN UJIAN AKTIF' : '🔒 BELUM DIIZINKAN UJIAN' ?>
                                    </span>
                                </div>
                                <div style="font-size: 0.75rem; color: var(--text-dim); font-style: italic;">
                                    <?= htmlspecialchars($a['coach_exam_notes'] ?: 'Belum ada catatan rekomendasi pelatih.') ?>
                                </div>
                            </div>
                        </div>

                        <!-- AKSI PELATIH: BERI IZIN ATAU TUNDA -->
                        <div style="border-top: 1px solid var(--border-color); padding-top: 0.75rem; display: flex; flex-direction: column; gap: 0.5rem;">
                            <?php if (!$a['can_exam_promotion']): ?>
                                <button type="button" class="btn btn-primary btn-sm btn-block" onclick="openGrantModal(<?= $a['id'] ?>, '<?= htmlspecialchars(addslashes($a['full_name'])) ?>')">
                                    ⭐ Beri Izin Ujian Naik Jarak
                                </button>
                            <?php else: ?>
                                <button type="button" class="btn btn-secondary btn-sm btn-block" style="color: #f59e0b;" onclick="openRevokeModal(<?= $a['id'] ?>, '<?= htmlspecialchars(addslashes($a['full_name'])) ?>')">
                                    ⏸️ Tunda / Batalkan Izin Ujian
                                </button>
                            <?php endif; ?>
                            <a href="app_dashboard.php?archer_id=<?= $a['id'] ?>" class="btn btn-secondary btn-sm btn-block">
                                📊 Lihat Tren Nilai Atlet ➔
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- SECTION 2: SESI LATIHAN TERBARU & EVALUASI CATATAN -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">
                📋 Riwayat Sesi Latihan Terbaru
            </h2>
            <span style="font-size: 0.8rem; color: var(--text-dim);">
                Klik "Detail / Evaluasi" untuk melengkapi atau merevisi catatan pelatih
            </span>
        </div>

        <?php if (empty($recentSessions)): ?>
            <p style="color: var(--text-muted); text-align: center; padding: 2rem;">
                Belum ada data sesi latihan yang tercatat.
            </p>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table class="scorecard-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Tanggal</th>
                            <th>Nama Atlet</th>
                            <th>Jarak & Tipe</th>
                            <th>Skor / Akurasi</th>
                            <th>Status Ujian</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentSessions as $s): ?>
                            <tr>
                                <td><b>#<?= $s['id'] ?></b></td>
                                <td style="white-space: nowrap;">
                                    <?= date('d M Y', strtotime($s['session_date'])) ?><br>
                                    <span style="font-size: 0.75rem; color: var(--text-dim);"><?= $s['start_time'] ?? '-' ?></span>
                                </td>
                                <td>
                                    <b><?= htmlspecialchars($s['archer_name']) ?></b><br>
                                    <span style="font-size: 0.75rem; color: var(--text-dim);"><?= htmlspecialchars($s['nis']) ?></span>
                                </td>
                                <td>
                                    <span class="badge badge-gold"><?= $s['distance_meter'] ?> Meter</span><br>
                                    <span style="font-size: 0.75rem; font-weight: 600; color: <?= $s['session_type'] === 'exam' ? '#f59e0b' : '#38bdf8' ?>;">
                                        <?= $s['session_type'] === 'exam' ? '🎯 UJIAN KENAIKAN' : '🏹 Latihan Rutin' ?>
                                    </span>
                                </td>
                                <td>
                                    <span style="font-size: 1.1rem; font-weight: 800; color: #fff;">
                                        <?= (int)$s['total_score'] ?>
                                    </span>
                                    <span style="font-size: 0.75rem; color: var(--text-dim);">/ <?= $s['max_possible_score'] ?></span><br>
                                    <span style="font-size: 0.75rem; color: #34d399;">
                                        Akurasi: <?= $s['accuracy_pct'] ?>% (10s: <?= $s['ten_count'] ?>, Xs: <?= $s['x_count'] ?>)
                                    </span>
                                </td>
                                <td>
                                    <?php if ($s['session_type'] === 'exam'): ?>
                                        <?php if ($s['exam_status'] === 'approved'): ?>
                                            <span class="badge badge-success">✔ LULUS (Disahkan)</span>
                                        <?php elseif ($s['exam_status'] === 'pending_approval'): ?>
                                            <span class="badge badge-gold">⏳ Menunggu ACC Admin</span>
                                        <?php else: ?>
                                            <span class="badge" style="background: rgba(239,68,68,0.2); color: #ef4444;">❌ Belum Lulus</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="color: var(--text-dim); font-size: 0.8rem;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td style="white-space: nowrap;">
                                    <div style="display: flex; gap: 0.35rem;">
                                        <button type="button" class="btn btn-outline-warning btn-sm fw-bold" title="Beri / Edit Catatan Evaluasi Pelatih" onclick="openCoachEvalModal(<?= $s['id'] ?>, '<?= htmlspecialchars(addslashes($s['archer_name'])) ?>', <?= htmlspecialchars(json_encode($s['coach_notes_technique'] ?? '')) ?>, <?= htmlspecialchars(json_encode($s['coach_notes_mental'] ?? '')) ?>, <?= htmlspecialchars(json_encode($s['coach_recommendation'] ?? '')) ?>, <?= htmlspecialchars(json_encode($s['evaluation_checklist_json'] ?? '[]')) ?>)">
                                            ✍️ Evaluasi
                                        </button>
                                        <a href="plotter.php?session_id=<?= $s['id'] ?>" class="btn btn-secondary btn-sm" title="Buka Papan Plotter">
                                            🎯 Plotter
                                        </a>
                                        <a href="report_session.php?session_id=<?= $s['id'] ?>" class="btn btn-secondary btn-sm" title="Lihat Rapot Cetak">
                                            📜 Rapot
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</main>

<!-- MODAL EVALUASI PELATIH SESI (BOOTSTRAP CENTERED MODAL) -->
<div class="modal fade" id="coachEvalModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-secondary" style="background: #111827; color: #f8fafc;">
            <div class="modal-header border-secondary">
                <h5 class="modal-title fw-bold text-white d-flex align-items-center gap-2">
                    ✍️ Evaluasi &amp; Lembar Penilaian Sesi #<span id="evalSessionIdSpan"></span> 
                    <span class="badge bg-primary text-white" id="evalArcherNameSpan"></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="coachEvalFormDirect" onsubmit="submitCoachEval(event)">
                <div class="modal-body p-3 p-md-4">
                    <input type="hidden" id="evalSessionIdInput">
                    
                    <!-- RUBRIK EVALUASI CHECKLIST KETERAMPILAN DINAMIS -->
                    <div class="card mb-4 border-secondary" style="background: rgba(15, 23, 42, 0.7);">
                        <div class="card-header border-secondary d-flex justify-content-between align-items-center py-2 px-3">
                            <span class="small fw-bold text-warning d-flex align-items-center gap-1">
                                <i class="bi bi-ui-checks"></i> Rubrik Penilaian &amp; Capaian Atlet
                            </span>
                            <span class="badge bg-dark border border-warning text-warning" id="liveScoreBadge">
                                Skor Capaian: 0 / <?= $totalMaxPoints ?> Poin (0%)
                            </span>
                        </div>
                        <div class="card-body p-3" style="max-height: 260px; overflow-y: auto;">
                            <?php if (empty($groupedCriteria)): ?>
                                <p class="text-muted small mb-0">Belum ada rubrik evaluasi yang disetup oleh Super Admin.</p>
                            <?php else: ?>
                                <?php foreach ($groupedCriteria as $catName => $items): ?>
                                    <div class="mb-3">
                                        <div class="small fw-bold text-info border-bottom border-secondary border-opacity-25 pb-1 mb-2">
                                            <?= htmlspecialchars($catName) ?>
                                        </div>
                                        <div class="row g-2">
                                            <?php foreach ($items as $crit): ?>
                                                <div class="col-md-6">
                                                    <div class="form-check p-2 rounded border border-secondary border-opacity-25" style="background: rgba(255,255,255,0.02);">
                                                        <input class="form-check-input eval-checklist-item" type="checkbox" 
                                                               id="crit_<?= $crit['id'] ?>" 
                                                               value="<?= $crit['id'] ?>" 
                                                               data-points="<?= (int)$crit['points'] ?>"
                                                               data-title="<?= htmlspecialchars($crit['title']) ?>"
                                                               data-category="<?= htmlspecialchars($crit['category']) ?>"
                                                               onchange="calcLiveChecklistScore()">
                                                        <label class="form-check-label small text-light d-flex justify-content-between align-items-center" for="crit_<?= $crit['id'] ?>">
                                                            <span><?= htmlspecialchars($crit['title']) ?></span>
                                                            <span class="badge bg-secondary ms-2">+<?= (int)$crit['points'] ?>pt</span>
                                                        </label>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-white">1. Evaluasi Form &amp; Teknik (Stance, Anchor, Release) <span class="text-danger">*</span></label>
                        <textarea class="form-control bg-dark text-white border-secondary" id="evalTechnique" rows="3" placeholder="Contoh: Tarikan siku konsisten, perbaiki rilis agar tidak menyentak..." required></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-white">2. Evaluasi Mental &amp; Fokus Atlet</label>
                        <input type="text" class="form-control bg-dark text-white border-secondary" id="evalMental" placeholder="Contoh: Sangat tenang saat tembakan terakhir">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-white">3. Rekomendasi / PR Latihan Mandiri di Rumah</label>
                        <input type="text" class="form-control bg-dark text-white border-secondary" id="evalRecom" placeholder="Contoh: Latihan SPT 15 menit setiap pagi">
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary fw-bold px-4" id="btnSaveCoachEval">
                        <i class="bi bi-check-circle-fill"></i> Simpan Evaluasi &amp; Buka Kunci Rapot
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL BERI IZIN UJIAN KENAIKAN JARAK (BOOTSTRAP CENTERED MODAL) -->
<div class="modal fade" id="grantModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-secondary" style="background: #111827; color: #f8fafc;">
            <div class="modal-header border-secondary">
                <h5 class="modal-title fw-bold text-warning d-flex align-items-center gap-2">
                    ⭐ Beri Izin Ujian Kenaikan Jarak
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="action" value="grant_exam">
                    <input type="hidden" name="archer_id" id="grantArcherId">
                    <p class="small text-muted mb-3">
                        Anda memberikan rekomendasi resmi bahwa ananda <strong class="text-white" id="grantArcherName"></strong> telah siap secara fisik dan teknik untuk mengambil sesi ujian scoring naik jarak.
                    </p>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-white">Catatan Rekomendasi Pelatih:</label>
                        <textarea class="form-control bg-dark text-white border-secondary" name="coach_notes" rows="3" placeholder="Alasan rekomendasi...">Form tarikan dan konsistensi grouping sangat stabil. Direkomendasikan untuk uji kenaikan jarak.</textarea>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary fw-bold px-4">✔ Berikan Izin</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL TUNDA / BATALKAN IZIN UJIAN (BOOTSTRAP CENTERED MODAL) -->
<div class="modal fade" id="revokeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-secondary" style="background: #111827; color: #f8fafc;">
            <div class="modal-header border-secondary">
                <h5 class="modal-title fw-bold text-warning d-flex align-items-center gap-2">
                    ⏸️ Tunda Izin Ujian Kenaikan Jarak
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="action" value="revoke_exam">
                    <input type="hidden" name="archer_id" id="revokeArcherId">
                    <p class="small text-muted mb-3">
                        Tunda sementara izin ujian untuk ananda <strong class="text-white" id="revokeArcherName"></strong> jika teknik/form perlu pemantapan lebih lanjut.
                    </p>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-white">Alasan Penundaan / PR Evaluasi:</label>
                        <textarea class="form-control bg-dark text-white border-secondary" name="coach_notes" rows="3" placeholder="Alasan penundaan...">Perlu pemantapan stabilitas bow arm dan follow-through sebelum mengambil sesi ujian scoring.</textarea>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-warning fw-bold px-4 text-dark">⏸️ Tunda Izin</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let coachModalInstance = null;
let grantModalInstance = null;
let revokeModalInstance = null;

function openCoachEvalModal(sessId, archerName, tech, mental, recom, checklistJson) {
    document.getElementById('evalSessionIdInput').value = sessId;
    document.getElementById('evalSessionIdSpan').textContent = sessId;
    document.getElementById('evalArcherNameSpan').textContent = archerName;
    document.getElementById('evalTechnique').value = tech || '';
    document.getElementById('evalMental').value = mental || '';
    document.getElementById('evalRecom').value = recom || '';

    // Populate checklist checkboxes
    let activeIds = [];
    if (checklistJson) {
        try {
            const parsed = typeof checklistJson === 'string' ? JSON.parse(checklistJson) : checklistJson;
            if (Array.isArray(parsed)) {
                activeIds = parsed.map(x => String(x.id || x));
            }
        } catch(e){}
    }
    document.querySelectorAll('.eval-checklist-item').forEach(cb => {
        cb.checked = activeIds.includes(String(cb.value));
    });
    calcLiveChecklistScore();

    if (!coachModalInstance) {
        coachModalInstance = new bootstrap.Modal(document.getElementById('coachEvalModal'));
    }
    coachModalInstance.show();
}

function calcLiveChecklistScore() {
    let earned = 0;
    const max = <?= (int)$totalMaxPoints ?>;
    document.querySelectorAll('.eval-checklist-item:checked').forEach(cb => {
        earned += parseInt(cb.dataset.points) || 0;
    });
    const pct = max > 0 ? Math.round((earned / max) * 100) : 0;
    const badge = document.getElementById('liveScoreBadge');
    if (badge) {
        badge.textContent = `Skor Capaian: ${earned} / ${max} Poin (${pct}%)`;
    }
    return { earned, max, pct };
}

async function submitCoachEval(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSaveCoachEval');
    btn.disabled = true;
    btn.textContent = 'Menyimpan...';

    const sessId = document.getElementById('evalSessionIdInput').value;
    const selectedCriteria = [];
    let checklistScore = 0;
    document.querySelectorAll('.eval-checklist-item:checked').forEach(cb => {
        checklistScore += parseInt(cb.dataset.points) || 0;
        selectedCriteria.push({
            id: parseInt(cb.value),
            title: cb.dataset.title,
            category: cb.dataset.category,
            points: parseInt(cb.dataset.points) || 0
        });
    });

    const payload = {
        session_id: parseInt(sessId),
        coach_notes_technique: document.getElementById('evalTechnique').value,
        coach_notes_mental: document.getElementById('evalMental').value,
        coach_recommendation: document.getElementById('evalRecom').value,
        evaluation_checklist_json: selectedCriteria,
        evaluation_checklist_score: checklistScore,
        evaluation_max_score: <?= (int)$totalMaxPoints ?>,
        mark_completed: true
    };

    try {
        const res = await fetch('api/save_session.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const json = await res.json();
        if (json.status === 'success') {
            alert('✔ Catatan evaluasi pelatih & skor rubrik berhasil disimpan!');
            location.reload();
        } else {
            alert('Gagal: ' + (json.message || 'Terjadi kesalahan'));
        }
    } catch (err) {
        alert('Terjadi kesalahan jaringan: ' + err.message);
    } finally {
        btn.disabled = false;
        btn.textContent = '💾 Simpan Evaluasi & Buka Kunci Rapot';
    }
}

function openGrantModal(id, name) {
    document.getElementById('grantArcherId').value = id;
    document.getElementById('grantArcherName').textContent = name;
    if (!grantModalInstance) {
        grantModalInstance = new bootstrap.Modal(document.getElementById('grantModal'));
    }
    grantModalInstance.show();
}

function openRevokeModal(id, name) {
    document.getElementById('revokeArcherId').value = id;
    document.getElementById('revokeArcherName').textContent = name;
    if (!revokeModalInstance) {
        revokeModalInstance = new bootstrap.Modal(document.getElementById('revokeModal'));
    }
    revokeModalInstance.show();
}
</script>

<?php include __DIR__ . '/views/footer.php'; ?>
