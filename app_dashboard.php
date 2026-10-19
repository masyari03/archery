<?php
// app_dashboard.php - Dashboard Internal Atlet & Ortu: Scoring, Jam Terbang, & Riwayat Latihan
require_once __DIR__ . '/config/auth.php';
Auth::requireAuth();

$currentUser = Auth::user();
$currentRole = $_SESSION['role'] ?? 'parent';
$isPinChild = isset($_SESSION['archer_device_id']);

// Jika login sebagai master_admin, arahkan ke master_tenants.php
if ($currentRole === 'master_admin') {
    header("Location: master_tenants.php");
    exit;
}

$currentArcher = Auth::currentArcher($pdo);

if (!$currentArcher) {
    // Jika admin/coach yang belum pilih atlet, arahkan ke dashboard coach atau admin
    if (in_array($currentRole, ['coach', 'super_admin'])) {
        header("Location: coach_dashboard.php");
        exit;
    }
    die("<div style='padding:40px;text-align:center;font-family:sans-serif;'><h3>Data atlet tidak ditemukan untuk akun ini.</h3><p>Silakan hubungi admin klub untuk mendaftarkan data atlet.</p><a href='logout.php'>Logout</a></div>");
}

$archerId = (int)$currentArcher['id'];

// Ambil Riwayat Sesi Latihan Atlet ini
$stmtSessions = $pdo->prepare("
    SELECT s.*, c.full_name AS coach_name, t.target_name
    FROM archery_sessions s
    LEFT JOIN archery_users c ON c.id = s.coach_id
    LEFT JOIN archery_targets t ON t.id = s.target_id
    WHERE s.archer_id = :aid
    ORDER BY s.session_date DESC, s.id DESC
");
$stmtSessions->execute([':aid' => $archerId]);
$sessions = $stmtSessions->fetchAll();

// Hitung Statistik Kumulatif
$totalSessionsCount = count($sessions);
$totalScoreSum = 0;
$totalPossibleSum = 0;
$highestScore = $currentArcher['personal_best_score'] ?? 0;

foreach ($sessions as $s) {
    $totalScoreSum += (int)$s['total_score'];
    $totalPossibleSum += (int)$s['max_possible_score'];
    if ($s['total_score'] > $highestScore) {
        $highestScore = (int)$s['total_score'];
    }
}
$cumulativeAccuracy = $totalPossibleSum > 0 ? round(($totalScoreSum / $totalPossibleSum) * 100, 1) : 0.0;

// Ambil Master Jarak Atlet Saat Ini & Syarat Kenaikan Jarak Berikutnya
$currentDist = (int)$currentArcher['current_distance_meter'];
$stmtDistRule = $pdo->prepare("SELECT * FROM archery_distances WHERE distance_meter = :dm AND club_id = :cid LIMIT 1");
$stmtDistRule->execute([':dm' => $currentDist, ':cid' => $currentArcher['club_id']]);
$currentDistRule = $stmtDistRule->fetch();
if (!$currentDistRule) {
    // Fallback rule jika club distance belum diset spesifik
    $stmtDistRule = $pdo->prepare("SELECT * FROM archery_distances WHERE distance_meter = :dm LIMIT 1");
    $stmtDistRule->execute([':dm' => $currentDist]);
    $currentDistRule = $stmtDistRule->fetch();
}

$minSessionsRequired = $currentDistRule['min_sessions_required'] ?? 12;
$minPassingScore = $currentDistRule['min_passing_score'] ?? 250;

// Cek kelayakan ujian naik jarak: apakah jam terbang cukup ATAU sudah dapat izin rekomendasi coach
$isEligibleBySessions = ($totalSessionsCount >= $minSessionsRequired);
$isCoachApproved = !empty($currentArcher['can_exam_promotion']);
$canTakeExam = ($isEligibleBySessions || $isCoachApproved);

$progressPct = min(100, round(($totalSessionsCount / max(1, $minSessionsRequired)) * 100));

// Ambil Daftar Coach Aktif untuk Dropdown Modal
$stmtCoaches = $pdo->prepare("SELECT id, full_name FROM archery_users WHERE role IN ('coach', 'super_admin') AND is_active = 1 AND club_id = :cid ORDER BY full_name ASC");
$stmtCoaches->execute([':cid' => $currentArcher['club_id']]);
$coachesList = $stmtCoaches->fetchAll();
if (empty($coachesList)) {
    // Fallback jika tidak ada coach spesifik klub
    $stmtCoaches = $pdo->query("SELECT id, full_name FROM archery_users WHERE role IN ('coach', 'super_admin') AND is_active = 1 ORDER BY full_name ASC");
    $coachesList = $stmtCoaches->fetchAll();
}

// Ambil Master Jarak untuk Info Kenaikan Jarak Berikutnya
$stmtNextDist = $pdo->prepare("SELECT * FROM archery_distances WHERE distance_meter > :dm AND club_id = :cid ORDER BY distance_meter ASC LIMIT 1");
$stmtNextDist->execute([':dm' => $currentDist, ':cid' => $currentArcher['club_id']]);
$nextDistRule = $stmtNextDist->fetch();

$pageTitle = "Portal Scoring Atlet - " . htmlspecialchars($currentArcher['full_name']);
require_once __DIR__ . '/views/navbar.php';
?>

<div class="container pb-5">
    <!-- Top Athlete Profile Card -->
    <div class="card mb-4 mt-2 border-0 shadow-sm" style="background: linear-gradient(135deg, rgba(2, 132, 199, 0.15), rgba(15, 23, 42, 0.95)); border-left: 5px solid var(--accent-color) !important;">
        <div class="card-body p-4">
            <div class="row align-items-center">
                <div class="col-md-7">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-circle d-flex align-items-center justify-content-center bg-primary text-white" style="width: 60px; height: 60px; font-size: 24px; font-weight: 700;">
                            <?= strtoupper(substr($currentArcher['nickname'] ?: $currentArcher['full_name'], 0, 1)) ?>
                        </div>
                        <div>
                            <div class="d-flex align-items-center gap-2">
                                <h3 class="mb-0 fw-bold"><?= htmlspecialchars($currentArcher['full_name']) ?></h3>
                                <span class="badge bg-secondary"><?= htmlspecialchars($currentArcher['nis']) ?></span>
                            </div>
                            <p class="text-muted mb-0 mt-1">
                                <i class="bi bi-shield-check text-info"></i> <?= htmlspecialchars($currentArcher['current_level']) ?> &bull; 
                                <i class="bi bi-bullseye text-warning"></i> Jarak Aktif: <strong><?= (int)$currentArcher['current_distance_meter'] ?>m</strong> &bull;
                                <i class="bi bi-lightning-charge text-danger"></i> <?= htmlspecialchars($currentArcher['bow_category']) ?> (<?= (float)$currentArcher['draw_weight_lbs'] ?> lbs)
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-md-5 text-md-end mt-3 mt-md-0 d-flex flex-wrap justify-content-md-end gap-2">
                    <button type="button" class="btn btn-warning px-4 py-2 fw-bold text-dark shadow-sm d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#newSessionModal">
                        <i class="bi bi-plus-circle-fill fs-5"></i> Input Tembakan Baru
                    </button>
                    <a href="report_monthly.php" class="btn btn-outline-info px-3 py-2 d-flex align-items-center gap-2">
                        <i class="bi bi-calendar3"></i> Rekap Bulanan
                    </a>
                </div>
            </div>

            <!-- Jam Terbang & Syarat Kenaikan Jarak Indicator -->
            <div class="mt-4 pt-3 border-top border-secondary border-opacity-25">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="small text-muted">
                        <i class="bi bi-hourglass-split text-info"></i> Jam Terbang Latihan Jarak <?= $currentDist ?>m: 
                        <strong class="text-white"><?= $totalSessionsCount ?> Sesi</strong> / Syarat: <?= $minSessionsRequired ?> Sesi
                    </span>
                    <span class="small fw-bold <?= $canTakeExam ? 'text-success' : 'text-warning' ?>">
                        <?php if ($isCoachApproved): ?>
                            <i class="bi bi-check-circle-fill"></i> Diizinkan Coach untuk Ujian
                        <?php elseif ($isEligibleBySessions): ?>
                            <i class="bi bi-check-circle-fill"></i> Memenuhi Jam Terbang
                        <?php else: ?>
                            <i class="bi bi-clock-history"></i> Butuh <?= max(0, $minSessionsRequired - $totalSessionsCount) ?> sesi lagi
                        <?php endif; ?>
                    </span>
                </div>
                <div class="progress" style="height: 10px; background-color: rgba(255,255,255,0.1);">
                    <div class="progress-bar progress-bar-striped progress-bar-animated <?= $canTakeExam ? 'bg-success' : 'bg-info' ?>" role="progressbar" style="width: <?= $progressPct ?>%" aria-valuenow="<?= $progressPct ?>" aria-valuemin="0" aria-valuemax="100"></div>
                </div>

                <?php if ($isCoachApproved && !empty($currentArcher['coach_exam_notes'])): ?>
                    <div class="alert alert-info py-2 px-3 mt-3 mb-0 d-flex align-items-center gap-2 small">
                        <i class="bi bi-megaphone-fill fs-6 text-info"></i>
                        <div>
                            <strong>Catatan Coach:</strong> <?= htmlspecialchars($currentArcher['coach_exam_notes']) ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Quick Stats Grid -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card p-3 h-100 text-center">
                <span class="text-muted small">Total Sesi Latihan</span>
                <h3 class="fw-bold mt-1 text-info mb-0"><?= $totalSessionsCount ?></h3>
                <span class="small text-muted mt-1">Sesi Terdokumentasi</span>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card p-3 h-100 text-center">
                <span class="text-muted small">Personal Best (PB)</span>
                <h3 class="fw-bold mt-1 text-warning mb-0"><?= $highestScore ?><span class="fs-6 text-muted">/300</span></h3>
                <span class="small text-muted mt-1">Skor Tertinggi</span>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card p-3 h-100 text-center">
                <span class="text-muted small">Akurasi Kumulatif</span>
                <h3 class="fw-bold mt-1 text-success mb-0"><?= $cumulativeAccuracy ?>%</h3>
                <span class="small text-muted mt-1">Rata-rata Tembakan</span>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card p-3 h-100 text-center">
                <span class="text-muted small">Target Berikutnya</span>
                <h3 class="fw-bold mt-1 text-primary mb-0"><?= $nextDistRule ? $nextDistRule['distance_meter'] . 'm' : 'Master' ?></h3>
                <span class="small text-muted mt-1"><?= $nextDistRule ? 'Min Skor: ' . $nextDistRule['min_passing_score'] : 'Level Puncak' ?></span>
            </div>
        </div>
    </div>

    <!-- Riwayat Sesi Latihan -->
    <div class="card">
        <div class="card-header bg-transparent d-flex justify-content-between align-items-center py-3">
            <h5 class="mb-0 fw-bold d-flex align-items-center gap-2">
                <i class="bi bi-clock-history text-info"></i> Riwayat Scoring &amp; Evaluasi Coach
            </h5>
            <span class="badge bg-secondary"><?= count($sessions) ?> Catatan</span>
        </div>
        <div class="card-body p-0">
            <?php if (empty($sessions)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-bullseye fs-1 d-block mb-3 opacity-50"></i>
                    <p class="mb-2">Belum ada sesi latihan yang dicatat untuk ananda.</p>
                    <button type="button" class="btn btn-warning btn-sm fw-bold text-dark" data-bs-toggle="modal" data-bs-target="#newSessionModal">
                        Mulai Tembakan Sekarang
                    </button>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th style="width: 50px;">#</th>
                                <th>Tanggal &amp; Waktu</th>
                                <th>Jarak &amp; Target</th>
                                <th>Tipe Sesi</th>
                                <th>Coach Pendamping</th>
                                <th class="text-center">Skor / 300</th>
                                <th class="text-center">Akurasi</th>
                                <th>Status Evaluasi Coach</th>
                                <th class="text-end" style="width: 140px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sessions as $idx => $s): 
                                $isExam = ($s['session_type'] === 'exam_promotion');
                                $evalStatus = $s['coach_evaluation_status'] ?? 'pending';
                                $accuracy = $s['max_possible_score'] > 0 ? round(($s['total_score'] / $s['max_possible_score']) * 100, 1) : 0;
                            ?>
                                <tr>
                                    <td class="text-muted small"><?= $idx + 1 ?></td>
                                    <td>
                                        <div class="fw-bold"><?= date('d M Y', strtotime($s['session_date'])) ?></div>
                                        <div class="text-muted small"><?= date('H:i', strtotime($s['created_at'])) ?> WIB</div>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary fs-6"><?= (int)$s['distance_meter'] ?> Meter</span>
                                        <div class="text-muted small mt-1"><?= htmlspecialchars($s['target_name'] ?? 'Target 10-Ring') ?></div>
                                    </td>
                                    <td>
                                        <?php if ($isExam): ?>
                                            <span class="badge bg-warning text-dark"><i class="bi bi-trophy-fill"></i> Ujian Naik Jarak</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary"><i class="bi bi-bullseye"></i> Latihan Rutin</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <i class="bi bi-person-badge text-info"></i> <?= htmlspecialchars($s['coach_name'] ?? 'Coach Pendamping') ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="fw-bold fs-5 text-warning"><?= (int)$s['total_score'] ?></span>
                                        <span class="text-muted small">/<?= (int)$s['max_possible_score'] ?></span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-dark border border-secondary"><?= $accuracy ?>%</span>
                                    </td>
                                    <td>
                                        <?php if ($evalStatus === 'approved'): ?>
                                            <span class="badge bg-success"><i class="bi bi-check-circle-fill"></i> Lulus Kenaikan Jarak</span>
                                        <?php elseif ($evalStatus === 'rejected'): ?>
                                            <span class="badge bg-danger"><i class="bi bi-x-circle-fill"></i> Ditunda / Perlu Latihan</span>
                                        <?php elseif ($evalStatus === 'evaluated'): ?>
                                            <span class="badge bg-info text-dark"><i class="bi bi-chat-left-text-fill"></i> Terevaluasi</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary text-muted"><i class="bi bi-hourglass-split"></i> Menunggu Evaluasi (6 Seri)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm">
                                            <a href="plotter.php?session_id=<?= $s['id'] ?>" class="btn btn-outline-warning" title="Buka Target Plotter">
                                                <i class="bi bi-bullseye"></i>
                                            </a>
                                            <a href="report_session.php?id=<?= $s['id'] ?>" class="btn btn-outline-info" title="Rapor Sesi A4">
                                                <i class="bi bi-file-earmark-text"></i>
                                            </a>
                                            <a href="share_card.php?session_id=<?= $s['id'] ?>" class="btn btn-outline-light" title="Share Card Sosial">
                                                <i class="bi bi-share"></i>
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
    </div>
</div>

<!-- Modal Input Tembakan Baru (Auto Jarak & Jam Kalibrasi) -->
<div class="modal fade" id="newSessionModal" tabindex="-1" aria-labelledby="newSessionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-secondary shadow">
            <form action="api/session_save.php" method="POST" id="newSessionForm">
                <input type="hidden" name="action" value="create_session">
                <input type="hidden" name="archer_id" value="<?= $archerId ?>">
                <input type="hidden" name="distance_meter" value="<?= $currentDist ?>">

                <div class="modal-header bg-dark border-bottom border-secondary">
                    <h5 class="modal-title fw-bold text-warning d-flex align-items-center gap-2" id="newSessionModalLabel">
                        <i class="bi bi-bullseye"></i> Mulai Sesi Tembakan Baru
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <!-- Info Otomatis Jarak & Jam -->
                    <div class="alert alert-dark border-secondary d-flex align-items-center justify-content-between mb-3 py-2 px-3">
                        <div>
                            <span class="text-muted small d-block">Jarak Tembak Terkalibrasi:</span>
                            <strong class="text-info fs-5"><?= $currentDist ?> Meter</strong>
                            <span class="badge bg-secondary ms-2"><?= htmlspecialchars($currentDistRule['distance_label'] ?? 'Standar') ?></span>
                        </div>
                        <div class="text-end">
                            <span class="text-muted small d-block">Waktu Real-time:</span>
                            <span class="badge bg-dark border border-secondary text-warning" id="liveClockBadge">
                                <?= date('H:i:s') ?> WIB
                            </span>
                        </div>
                    </div>

                    <!-- Pilih Coach Pendamping -->
                    <div class="mb-3">
                        <label class="form-label fw-bold">Pilih Coach Pendamping <span class="text-danger">*</span></label>
                        <select class="form-select bg-dark text-white border-secondary" name="coach_id" required>
                            <option value="">-- Pilih Coach yang Membimbing --</option>
                            <?php foreach ($coachesList as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= ($currentArcher['assigned_coach_id'] == $c['id']) ? 'selected' : '' ?>>
                                    Coach <?= htmlspecialchars($c['full_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text text-muted">Evaluasi &amp; catatan pelatih akan dikirim ke portal coach yang dipilih.</div>
                    </div>

                    <!-- Pilih Tipe Sesi (Latihan Biasa vs Ujian Kenaikan Jarak) -->
                    <div class="mb-3">
                        <label class="form-label fw-bold">Tipe Sesi Latihan <span class="text-danger">*</span></label>
                        <div class="d-flex gap-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="session_type" id="typeRegular" value="regular" checked>
                                <label class="form-check-label" for="typeRegular">
                                    Latihan Rutin
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="session_type" id="typeExam" value="exam_promotion" <?= $canTakeExam ? '' : 'disabled' ?>>
                                <label class="form-check-label <?= $canTakeExam ? 'text-warning fw-bold' : 'text-muted' ?>" for="typeExam">
                                    Ujian Kenaikan Jarak (<?= $canTakeExam ? 'Tersedia' : 'Terkunci' ?>)
                                </label>
                            </div>
                        </div>
                        <?php if (!$canTakeExam): ?>
                            <div class="small text-danger mt-1">
                                <i class="bi bi-lock-fill"></i> Ujian naik jarak terkunci. Butuh minimal <strong><?= $minSessionsRequired ?> sesi</strong> latihan atau izin langsung rekomendasi Coach.
                            </div>
                        <?php else: ?>
                            <div class="small text-success mt-1">
                                <i class="bi bi-unlock-fill"></i> Syarat terpenuhi! Ananda siap mengambil ujian kenaikan jarak.
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Pilihan Target Face & Format Seri -->
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small text-muted">Format Sesi</label>
                            <input type="text" class="form-control form-control-sm bg-dark text-white border-secondary" value="6 Seri &bull; 5 Anak Panah (30 Arrows)" readonly>
                        </div>
                        <div class="col-6">
                            <label class="form-label small text-muted">Maksimal Skor</label>
                            <input type="text" class="form-control form-control-sm bg-dark text-white border-secondary" value="300 Poin (WA Standar)" readonly>
                        </div>
                    </div>

                    <div class="form-text text-muted small">
                        <i class="bi bi-info-circle"></i> Sesi tembakan dilengkapi <strong>Triple-Shield Auto-Save</strong> via AJAX per seri tembakan. Data tidak akan hilang saat reload.
                    </div>
                </div>
                <div class="modal-footer bg-dark border-top border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-warning fw-bold text-dark px-4">
                        <i class="bi bi-play-circle-fill"></i> Buka Papan Bidik (Plotter)
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Jam Real-time Kalibrasi Wilayah
setInterval(function() {
    const now = new Date();
    const hours = String(now.getHours()).padStart(2, '0');
    const minutes = String(now.getMinutes()).padStart(2, '0');
    const seconds = String(now.getSeconds()).padStart(2, '0');
    const badge = document.getElementById('liveClockBadge');
    if (badge) {
        badge.innerText = `${hours}:${minutes}:${seconds} WIB`;
    }
}, 1000);
</script>

<?php require_once __DIR__ . '/views/footer.php'; ?>
