<?php
// admin_approvals.php - Halaman Review & Pengesahan (ACC) Ujian Kenaikan Jarak (Super Admin)
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/includes/fonnte.php';
Auth::requireAuth();

$currentUser = Auth::user();
$currentRole = $_SESSION['role'] ?? 'parent';

// Proteksi RBAC: Hanya Super Admin
if ($currentRole !== 'super_admin') {
    die("Akses ditolak. Halaman pengesahan ujian hanya dapat diakses oleh Super Admin.");
}

$clubId = (int)($currentUser['club_id'] ?? 1);
$adminId = (int)$currentUser['id'];
$alertMsg = null;
$alertType = 'success';

// Handle Action ACC atau Tolak Ujian
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $sessionId = (int)($_POST['session_id'] ?? 0);
    $adminNotes = trim($_POST['admin_notes'] ?? '');

    if ($sessionId > 0) {
        // Ambil data sesi ujian
        $stmtSess = $pdo->prepare("
            SELECT s.*, a.full_name AS archer_name, a.nis, a.parent_phone, a.current_distance_meter,
                   u.full_name AS coach_name, c.chairman_name
            FROM archery_sessions s
            JOIN archery_archers a ON a.id = s.archer_id
            LEFT JOIN archery_users u ON u.id = s.coach_id
            JOIN archery_clubs c ON c.id = s.club_id
            WHERE s.id = :sid AND s.club_id = :cid
        ");
        $stmtSess->execute([':sid' => $sessionId, ':cid' => $clubId]);
        $session = $stmtSess->fetch();

        if ($session) {
            $archerId = (int)$session['archer_id'];
            $fromDistance = (int)$session['distance_meter'];
            $score = (int)$session['total_score'];
            $coachName = $session['coach_name'] ?? 'Pelatih';
            $chairmanName = $session['chairman_name'] ?? 'Ketua Klub';

            if ($action === 'approve') {
                // Cari jarak jenjang berikutnya dari tabel archery_distances
                $stmtNext = $pdo->prepare("
                    SELECT distance_meter 
                    FROM archery_distances 
                    WHERE club_id = :cid AND distance_meter > :curr AND is_active = 1
                    ORDER BY distance_meter ASC 
                    LIMIT 1
                ");
                $stmtNext->execute([':cid' => $clubId, ':curr' => $fromDistance]);
                $nextDistance = (int)$stmtNext->fetchColumn();

                if ($nextDistance <= 0) {
                    $nextDistance = $fromDistance + 5; // Default kenaikan +5 meter jika tidak ada tingkatan lagi
                }

                // EKSEKUSI DATABASE TRANSACTION (ZERO DATA LOSS)
                try {
                    $pdo->beginTransaction();

                    // 1. Update status sesi ujian menjadi 'approved'
                    $stmtApprove = $pdo->prepare("
                        UPDATE archery_sessions 
                        SET exam_status = 'approved',
                            exam_reviewed_by = :admin_id,
                            exam_reviewed_at = NOW()
                        WHERE id = :sid
                    ");
                    $stmtApprove->execute([':admin_id' => $adminId, ':sid' => $sessionId]);

                    // 2. Update jarak resmi atlet & reset status izin ujian untuk jenjang baru
                    $stmtArcherUp = $pdo->prepare("
                        UPDATE archery_archers 
                        SET current_distance_meter = :next_dist,
                            can_exam_promotion = 0,
                            coach_exam_notes = NULL
                        WHERE id = :aid AND club_id = :cid
                    ");
                    $stmtArcherUp->execute([
                        ':next_dist' => $nextDistance,
                        ':aid' => $archerId,
                        ':cid' => $clubId
                    ]);

                    // 3. Catat riwayat resmi ke tabel archery_promotion_logs
                    $stmtLog = $pdo->prepare("
                        INSERT INTO archery_promotion_logs 
                        (club_id, archer_id, from_distance_meter, to_distance_meter, exam_session_id, exam_score, approved_by_user_id, coach_id, notes)
                        VALUES (:cid, :aid, :from_dist, :to_dist, :sid, :sc, :admin_id, :coach_id, :notes)
                    ");
                    $stmtLog->execute([
                        ':cid' => $clubId,
                        ':aid' => $archerId,
                        ':from_dist' => $fromDistance,
                        ':to_dist' => $nextDistance,
                        ':sid' => $sessionId,
                        ':sc' => $score,
                        ':admin_id' => $adminId,
                        ':coach_id' => $session['coach_id'],
                        ':notes' => $adminNotes ?: 'Selamat! Resmi dinyatakan lulus ujian kenaikan jarak.'
                    ]);

                    $pdo->commit();

                    // 4. DISPATCH WHATSAPP BOT FONNTE SECARA OTOMATIS
                    $waResult = FonnteWA::sendPromotionNotification(
                        $pdo,
                        $clubId,
                        $archerId,
                        $fromDistance,
                        $nextDistance,
                        $score,
                        $coachName,
                        $chairmanName
                    );

                    $alertMsg = "✔ Ujian atlet {$session['archer_name']} BERHASIL DI-ACC! Jarak resmi naik menjadi {$nextDistance} Meter.";
                    if ($waResult['status'] === 'success') {
                        $alertMsg .= " Notifikasi WhatsApp kelulusan telah dikirimkan ke orang tua.";
                    }
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $alertMsg = "Gagal memproses ACC: " . $e->getMessage();
                    $alertType = 'danger';
                }
            } elseif ($action === 'reject') {
                try {
                    $stmtReject = $pdo->prepare("
                        UPDATE archery_sessions 
                        SET exam_status = 'rejected',
                            exam_reviewed_by = :admin_id,
                            exam_reviewed_at = NOW(),
                            coach_recommendation = :recom
                        WHERE id = :sid
                    ");
                    $stmtReject->execute([
                        ':admin_id' => $adminId,
                        ':recom' => $adminNotes ?: 'Nilai belum memenuhi passing score minimal. Perlu pengulangan latihan form.',
                        ':sid' => $sessionId
                    ]);

                    $alertMsg = "⚠️ Ujian scoring belum memenuhi syarat kelulusan dan telah ditandai ditolak.";
                    $alertType = 'warning';
                } catch (PDOException $e) {
                    $alertMsg = "Gagal memperbarui status: " . $e->getMessage();
                    $alertType = 'danger';
                }
            }
        }
    }
}

// Ambil Sesi Ujian yang Menunggu ACC (Pending Approval)
$stmtPending = $pdo->prepare("
    SELECT s.*, a.full_name AS archer_name, a.nis, a.parent_phone, a.current_distance_meter,
           u.full_name AS coach_name, d.min_passing_score
    FROM archery_sessions s
    JOIN archery_archers a ON a.id = s.archer_id
    LEFT JOIN archery_users u ON u.id = s.coach_id
    LEFT JOIN archery_distances d ON d.club_id = s.club_id AND d.distance_meter = s.distance_meter
    WHERE s.club_id = :cid AND s.session_type = 'exam' AND s.exam_status = 'pending_approval'
    ORDER BY s.session_date DESC, s.id DESC
");
$stmtPending->execute([':cid' => $clubId]);
$pendingExams = $stmtPending->fetchAll();

// Ambil Riwayat Pengesahan Terakhir (Promotion Logs)
$stmtLogs = $pdo->prepare("
    SELECT l.*, a.full_name AS archer_name, a.nis, u.full_name AS admin_name, c.full_name AS coach_name
    FROM archery_promotion_logs l
    JOIN archery_archers a ON a.id = l.archer_id
    LEFT JOIN archery_users u ON u.id = l.approved_by_user_id
    LEFT JOIN archery_users c ON c.id = l.coach_id
    WHERE l.club_id = :cid
    ORDER BY l.approved_at DESC
    LIMIT 25
");
$stmtLogs->execute([':cid' => $clubId]);
$promotionLogs = $stmtLogs->fetchAll();
$pageTitle = "ACC Ujian Kenaikan Jarak - TARGET ARCHERY";
require_once __DIR__ . '/views/header.php';
?>

<main class="container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h1 style="font-size: 1.6rem; margin-bottom: 0.25rem;">
                ✅ Pengesahan (ACC) Ujian Kenaikan Jarak
            </h1>
            <p style="color: var(--text-muted); font-size: 0.85rem; margin: 0;">
                Tinjau sesi scoring ujian dari atlet & pelatih. Saat di-ACC, jarak atlet otomatis naik dan WhatsApp bot mengirim ucapan selamat resmi.
            </p>
        </div>
        <div>
            <a href="admin_distances.php" class="btn btn-secondary btn-sm">📏 Atur Syarat Jarak</a>
        </div>
    </div>

    <?php if ($alertMsg): ?>
        <div class="card" style="margin-bottom: 1.5rem; padding: 1rem 1.25rem; border-left: 4px solid <?= $alertType === 'danger' ? 'var(--danger)' : ($alertType === 'warning' ? '#f59e0b' : 'var(--success)') ?>; background: rgba(0,0,0,0.2);">
            <?= htmlspecialchars($alertMsg) ?>
        </div>
    <?php endif; ?>

    <!-- TABEL MENUNGGU PENGESAHAN (PENDING APPROVAL) -->
    <div class="card" style="margin-bottom: 2rem;">
        <div class="card-header">
            <h2 class="card-title">
                ⏳ Menunggu Pengesahan Super Admin (<?= count($pendingExams) ?> Permohonan)
            </h2>
            <span style="font-size: 0.8rem; color: var(--text-dim);">
                Periksa kesesuaian skor tembakan dengan passing score minimal
            </span>
        </div>

        <?php if (empty($pendingExams)): ?>
            <p style="color: var(--text-muted); text-align: center; padding: 2rem;">
                Tidak ada permohonan ujian kenaikan jarak yang menunggu verifikasi saat ini.
            </p>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table class="scorecard-table">
                    <thead>
                        <tr>
                            <th>Sesi ID</th>
                            <th>Tanggal & Atlet</th>
                            <th>Jarak Diuji</th>
                            <th>Skor Diperoleh</th>
                            <th>Catatan Pelatih</th>
                            <th>Aksi Pengesahan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendingExams as $e): 
                            $passingScore = (int)($e['min_passing_score'] ?? 250);
                            $isPassedScore = (int)$e['total_score'] >= $passingScore;
                        ?>
                            <tr>
                                <td><b>#<?= $e['id'] ?></b></td>
                                <td>
                                    <b><?= htmlspecialchars($e['archer_name']) ?></b><br>
                                    <span style="font-size: 0.75rem; color: var(--text-dim);">
                                        NIS: <?= htmlspecialchars($e['nis']) ?> • Tgl: <?= date('d M Y', strtotime($e['session_date'])) ?>
                                    </span><br>
                                    <span style="font-size: 0.75rem; color: var(--primary);">
                                        Pelatih: <?= htmlspecialchars($e['coach_name'] ?? 'Pelatih') ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-gold"><?= $e['distance_meter'] ?> Meter</span>
                                </td>
                                <td>
                                    <span style="font-size: 1.25rem; font-weight: 800; color: <?= $isPassedScore ? 'var(--success)' : '#f59e0b' ?>;">
                                        <?= (int)$e['total_score'] ?>
                                    </span>
                                    <span style="font-size: 0.75rem; color: var(--text-dim);">/ <?= $e['max_possible_score'] ?></span><br>
                                    <span style="font-size: 0.75rem; color: <?= $isPassedScore ? 'var(--success)' : '#ef4444' ?>;">
                                        <?= $isPassedScore ? '✔ Memenuhi Syarat (Min: ' . $passingScore . ')' : '⚠️ Di Bawah Min: ' . $passingScore ?>
                                    </span>
                                </td>
                                <td style="max-width: 250px; font-size: 0.8rem; color: var(--text-muted);">
                                    <?= htmlspecialchars($e['coach_notes_technique'] ?? 'Pelatih merekomendasikan kelulusan.') ?>
                                </td>
                                <td style="white-space: nowrap;">
                                    <div style="display: flex; gap: 0.35rem;">
                                        <button type="button" class="btn btn-primary btn-sm" onclick="openApproveModal(<?= $e['id'] ?>, '<?= htmlspecialchars(addslashes($e['archer_name'])) ?>', <?= $e['distance_meter'] ?>, <?= $e['total_score'] ?>)">
                                            ✅ ACC Lulus
                                        </button>
                                        <button type="button" class="btn btn-secondary btn-sm" style="color: var(--danger);" onclick="openRejectModal(<?= $e['id'] ?>, '<?= htmlspecialchars(addslashes($e['archer_name'])) ?>')">
                                            ❌ Tolak
                                        </button>
                                        <a href="report_session.php?session_id=<?= $e['id'] ?>" class="btn btn-secondary btn-sm" title="Lihat Rapot Skor">
                                            📜
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

    <!-- TABEL RIWAYAT PENGESAHAN KELULUSAN (PROMOTION LOGS) -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">
                🏆 Buku Riwayat Kelulusan Kenaikan Jarak
            </h2>
            <span style="font-size: 0.8rem; color: var(--text-dim);">
                Arsip resmi kelulusan berjenjang klub
            </span>
        </div>

        <?php if (empty($promotionLogs)): ?>
            <p style="color: var(--text-muted); text-align: center; padding: 2rem;">
                Belum ada riwayat kelulusan kenaikan jarak yang tercatat.
            </p>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table class="scorecard-table">
                    <thead>
                        <tr>
                            <th>Waktu ACC</th>
                            <th>Nama Atlet</th>
                            <th>Kenaikan Jarak</th>
                            <th>Skor Ujian</th>
                            <th>Penguji & Pengesah</th>
                            <th>Catatan Kelulusan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($promotionLogs as $l): ?>
                            <tr>
                                <td style="white-space: nowrap; font-size: 0.8rem;">
                                    <?= date('d M Y H:i', strtotime($l['approved_at'])) ?>
                                </td>
                                <td>
                                    <b><?= htmlspecialchars($l['archer_name']) ?></b><br>
                                    <span style="font-size: 0.75rem; color: var(--text-dim);">NIS: <?= htmlspecialchars($l['nis']) ?></span>
                                </td>
                                <td>
                                    <span style="color: var(--text-muted);"><?= $l['from_distance_meter'] ?>m</span>
                                    <span style="color: var(--primary); font-weight: 800; font-size: 1.05rem;"> ➔ <?= $l['to_distance_meter'] ?>m</span>
                                </td>
                                <td>
                                    <span class="badge badge-success" style="font-size: 0.85rem;">
                                        <?= (int)$l['exam_score'] ?> / 300
                                    </span>
                                </td>
                                <td style="font-size: 0.8rem;">
                                    Coach: <?= htmlspecialchars($l['coach_name'] ?? 'Pelatih') ?><br>
                                    <span style="color: var(--text-dim);">ACC: <?= htmlspecialchars($l['admin_name'] ?? 'Super Admin') ?></span>
                                </td>
                                <td style="font-size: 0.8rem; color: var(--text-muted);">
                                    <?= htmlspecialchars($l['notes'] ?? '-') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</main>

<!-- MODAL ACC KELULUSAN (BOOTSTRAP CENTERED MODAL) -->
<div class="modal fade" id="approveModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-secondary" style="background: #111827; color: #f8fafc;">
            <div class="modal-header border-secondary">
                <h5 class="modal-title fw-bold text-success d-flex align-items-center gap-2">
                    ✅ Sahkan Kelulusan Kenaikan Jarak
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="action" value="approve">
                    <input type="hidden" name="session_id" id="appSessionId">
                    <p class="small text-muted mb-2">
                        Sahkan kelulusan ananda <strong class="text-white" id="appArcherName"></strong> dari jarak <strong class="text-info" id="appFromDist"></strong>m dengan perolehan skor <strong class="text-success" id="appScore"></strong>/300.
                    </p>
                    <p class="small text-secondary mb-3">
                        Sistem akan menaikkan level jarak atlet secara otomatis di database dan mengirimkan notifikasi WhatsApp kelulusan kepada orang tua.
                    </p>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-white">Catatan Pengesahan Ketua / Admin:</label>
                        <textarea class="form-control bg-dark text-white border-secondary" name="admin_notes" rows="2" placeholder="Catatan resmi pengesahan rapot...">Selamat dan barakallahu fiikum. Terus giat berlatih di jarak yang baru!</textarea>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary fw-bold px-4">✔ ACC &amp; Kirim WA</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL TOLAK UJIAN (BOOTSTRAP CENTERED MODAL) -->
<div class="modal fade" id="rejectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-secondary" style="background: #111827; color: #f8fafc;">
            <div class="modal-header border-secondary">
                <h5 class="modal-title fw-bold text-danger d-flex align-items-center gap-2">
                    ❌ Tolak Pengesahan Ujian
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="action" value="reject">
                    <input type="hidden" name="session_id" id="rejSessionId">
                    <p class="small text-muted mb-3">
                        Tolak pengesahan ujian untuk ananda <strong class="text-white" id="rejArcherName"></strong> jika skor atau form dinilai belum layak naik jarak.
                    </p>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-white">Alasan Penolakan / Evaluasi Pengulangan:</label>
                        <textarea class="form-control bg-dark text-white border-secondary" name="admin_notes" rows="2" placeholder="Alasan penolakan..." required>Skor belum memenuhi passing score minimal. Dimohon melakukan latihan repetisi 2 pekan ke depan.</textarea>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger fw-bold px-4">❌ Tolak</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let approveModalInst = null;
let rejectModalInst = null;

function openApproveModal(sessionId, name, fromDist, score) {
    document.getElementById('appSessionId').value = sessionId;
    document.getElementById('appArcherName').textContent = name;
    document.getElementById('appFromDist').textContent = fromDist;
    document.getElementById('appScore').textContent = score;
    if (!approveModalInst) {
        approveModalInst = new bootstrap.Modal(document.getElementById('approveModal'));
    }
    approveModalInst.show();
}

function openRejectModal(sessionId, name) {
    document.getElementById('rejSessionId').value = sessionId;
    document.getElementById('rejArcherName').textContent = name;
    if (!rejectModalInst) {
        rejectModalInst = new bootstrap.Modal(document.getElementById('rejectModal'));
    }
    rejectModalInst.show();
}
</script>

<?php include __DIR__ . '/views/footer.php'; ?>
