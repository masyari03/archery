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

// Jika Coach atau Admin ingin melihat grafik tren atlet tertentu via ?archer_id=X
if (!empty($_GET['archer_id']) && in_array($currentRole, ['coach', 'super_admin', 'master_admin'])) {
    $targetArcherId = (int)$_GET['archer_id'];
    Auth::switchChild($targetArcherId, $pdo);
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

// Ambil Daftar Coach Aktif untuk Dropdown Modal (Khusus role coach, tanpa super admin)
$stmtCoaches = $pdo->prepare("SELECT id, full_name FROM archery_users WHERE role = 'coach' AND is_active = 1 AND club_id = :cid ORDER BY full_name ASC");
$stmtCoaches->execute([':cid' => $currentArcher['club_id']]);
$coachesList = $stmtCoaches->fetchAll();
if (empty($coachesList)) {
    // Fallback jika tidak ada coach spesifik klub
    $stmtCoaches = $pdo->query("SELECT id, full_name FROM archery_users WHERE role = 'coach' AND is_active = 1 ORDER BY full_name ASC");
    $coachesList = $stmtCoaches->fetchAll();
}

// Ambil Master Jarak untuk Info Kenaikan Jarak Berikutnya
$stmtNextDist = $pdo->prepare("SELECT * FROM archery_distances WHERE distance_meter > :dm AND club_id = :cid ORDER BY distance_meter ASC LIMIT 1");
$stmtNextDist->execute([':dm' => $currentDist, ':cid' => $currentArcher['club_id']]);
$nextDistRule = $stmtNextDist->fetch();

$pageTitle = "Portal Scoring Atlet - " . htmlspecialchars($currentArcher['full_name']);
require_once __DIR__ . '/views/header.php';
?>

<div class="container pb-5">
    <?php if (in_array($currentRole, ['coach', 'super_admin', 'master_admin'])): ?>
        <div class="mb-3 mt-2 d-flex justify-content-between align-items-center flex-wrap gap-2 p-2 px-3 rounded shadow-sm" style="background: rgba(2, 132, 199, 0.12); border: 1px solid rgba(2, 132, 199, 0.35);">
            <div class="small text-info fw-bold d-flex align-items-center gap-2">
                <i class="bi bi-eye-fill"></i> Mode Pantau Tren &amp; Statistik Atlet oleh Coach/Admin
            </div>
            <a href="coach_dashboard.php" class="btn btn-sm btn-outline-info d-flex align-items-center gap-1 shadow-sm fw-bold">
                <i class="bi bi-arrow-left"></i> Kembali ke Dashboard Pelatih
            </a>
        </div>
    <?php endif; ?>

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
    <!-- 🔑 Akses Scoring Mandiri Atlet di Lapangan (PIN Dinamis 5 Jam) -->
    <?php
    $isPinActive = (!empty($currentArcher['temp_field_pin']) && !empty($currentArcher['temp_field_pin_expires_at']) && strtotime($currentArcher['temp_field_pin_expires_at']) > time());
    $pinExpiresTs = $isPinActive ? strtotime($currentArcher['temp_field_pin_expires_at']) : 0;
    $pinRemainingSecs = $isPinActive ? max(0, $pinExpiresTs - time()) : 0;
    $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $portalUrl = $scheme . '://' . $host . '/pin_login.php';
    $childShortName = htmlspecialchars($currentArcher['nickname'] ?: $currentArcher['full_name']);
    $waText = "Assalamu'alaikum {$childShortName}, ini PIN scoring latihan mandiri kamu: " . ($currentArcher['temp_field_pin'] ?? '') . ". Masukkan NIS {$currentArcher['nis']} dan PIN tersebut di: {$portalUrl} (Aktif 5 jam). Semangat memanah!";
    $waShareUrl = "https://wa.me/?text=" . urlencode($waText);
    ?>
    <div class="card mb-4 border-0 shadow-sm" style="background: linear-gradient(135deg, rgba(30, 41, 59, 0.95), rgba(15, 23, 42, 0.95)); border: 1px solid rgba(56, 189, 248, 0.3) !important;">
        <div class="card-body p-3 p-md-4">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle d-flex align-items-center justify-content-center bg-info bg-opacity-25 text-info" style="width: 50px; height: 50px; font-size: 24px;">
                        <i class="bi bi-key-fill"></i>
                    </div>
                    <div>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <h5 class="mb-0 fw-bold text-white">Akses Scoring Mandiri di Lapangan</h5>
                            <?php if ($isPinActive): ?>
                                <span class="badge bg-success bg-opacity-25 text-success border border-success"><i class="bi bi-shield-check"></i> PIN AKTIF (5 JAM)</span>
                            <?php else: ?>
                                <span class="badge bg-secondary bg-opacity-50 text-light"><i class="bi bi-shield-lock"></i> PIN KEDALUWARSA / BELUM DIBUAT</span>
                            <?php endif; ?>
                        </div>
                        <p class="text-muted small mb-0 mt-1">
                            Berikan PIN ini kepada ananda agar bisa menginput skor di HP sendiri di lapangan tanpa memegang akun orang tua.
                        </p>
                    </div>
                </div>

                <div class="d-flex flex-wrap align-items-center gap-2">
                    <?php if ($isPinActive): ?>
                        <div class="px-3 py-1 rounded bg-dark border border-info d-flex align-items-center gap-2">
                            <span class="small text-muted">PIN:</span>
                            <span class="fs-4 fw-bold text-info font-monospace" style="letter-spacing: 4px;"><?= htmlspecialchars($currentArcher['temp_field_pin']) ?></span>
                        </div>
                        <a href="<?= $waShareUrl ?>" target="_blank" rel="noopener" class="btn btn-sm btn-success fw-bold d-flex align-items-center gap-1">
                            <i class="bi bi-whatsapp"></i> Kirim ke WA Anak
                        </a>
                        <button type="button" class="btn btn-sm btn-outline-info" onclick="generateFieldPin(<?= $archerId ?>)" title="Generate Ulang PIN Baru">
                            <i class="bi bi-arrow-clockwise"></i> Ganti PIN
                        </button>
                    <?php else: ?>
                        <button type="button" class="btn btn-info fw-bold text-dark px-3 py-2 shadow-sm d-flex align-items-center gap-2" onclick="generateFieldPin(<?= $archerId ?>)">
                            <i class="bi bi-shield-plus fs-6"></i> Buat PIN 5 Jam Sekarang
                        </button>
                    <?php endif; ?>
                    <a href="pin_login.php" target="_blank" class="btn btn-sm btn-outline-secondary text-light d-flex align-items-center gap-1" title="Lihat Tampilan Layar HP Anak">
                        <i class="bi bi-box-arrow-up-right"></i> Buka Portal Lapangan
                    </a>
                </div>
            </div>

            <?php if ($isPinActive): ?>
                <div class="mt-3 pt-2 border-top border-secondary border-opacity-25 d-flex flex-wrap justify-content-between align-items-center gap-2 small text-muted">
                    <div>
                        <i class="bi bi-clock-history text-warning"></i> Sisa Masa Berlaku: 
                        <strong class="text-warning font-monospace" id="fieldPinCountdown">Menghitung...</strong>
                        (Berakhir pk. <?= date('H:i', $pinExpiresTs) ?> WIB)
                    </div>
                    <div>
                        <i class="bi bi-phone"></i> Ananda cukup login di <code>pin_login.php</code> dengan NIS: <strong class="text-white"><?= htmlspecialchars($currentArcher['nis']) ?></strong>
                    </div>
                </div>
                <script>
                (function() {
                    let remaining = <?= (int)$pinRemainingSecs ?>;
                    const el = document.getElementById('fieldPinCountdown');
                    function update() {
                        if (remaining <= 0) {
                            if (el) el.innerHTML = '<span class="text-danger fw-bold">KEDALUWARSA (Silakan buat PIN baru)</span>';
                            return;
                        }
                        const h = String(Math.floor(remaining / 3600)).padStart(2, '0');
                        const m = String(Math.floor((remaining % 3600) / 60)).padStart(2, '0');
                        const s = String(remaining % 60).padStart(2, '0');
                        if (el) el.innerText = `${h}:${m}:${s}`;
                        remaining--;
                    }
                    update();
                    setInterval(update, 1000);
                })();
                </script>
            <?php endif; ?>
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
                                $isExam = ($s['session_type'] === 'exam_promotion' || $s['session_type'] === 'exam');
                                $hasNotes = !empty($s['coach_notes_technique']) || !empty($s['coach_recommendation']) || ($s['status'] === 'completed');
                                $evalStatus = $s['exam_status'] ?? 'pending';
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
                                        <?php if ($isExam && $evalStatus === 'approved'): ?>
                                            <span class="badge bg-success"><i class="bi bi-check-circle-fill"></i> Lulus Ujian Jarak</span>
                                        <?php elseif ($isExam && $evalStatus === 'rejected'): ?>
                                            <span class="badge bg-danger"><i class="bi bi-x-circle-fill"></i> Ditunda / Perlu Latihan</span>
                                        <?php elseif ($hasNotes): ?>
                                            <span class="badge bg-success"><i class="bi bi-check-circle-fill"></i> Sudah Dievaluasi Coach</span>
                                            <?php if (!empty($s['coach_notes_technique'])): ?>
                                                <div class="small text-muted mt-1 text-truncate" style="max-width: 220px;" title="<?= htmlspecialchars($s['coach_notes_technique']) ?>">
                                                    <i class="bi bi-chat-quote"></i> "<?= htmlspecialchars(mb_strimwidth($s['coach_notes_technique'], 0, 38, '...')) ?>"
                                                </div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="badge bg-secondary text-muted"><i class="bi bi-hourglass-split"></i> Menunggu Evaluasi (<?= (int)$s['total_ends'] ?> Seri)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm">
                                            <a href="plotter.php?session_id=<?= $s['id'] ?>" class="btn btn-outline-warning" title="Buka Target Plotter">
                                                <i class="bi bi-bullseye"></i>
                                            </a>
                                            <a href="report_session.php?session_id=<?= $s['id'] ?>" class="btn btn-outline-info" title="Rapor Sesi A4">
                                                <i class="bi bi-file-earmark-text"></i>
                                            </a>
                                            <a href="share_card.php?session_id=<?= $s['id'] ?>" class="btn btn-outline-light" title="Share Card Sosial">
                                                <i class="bi bi-share"></i>
                                            </a>
                                            <?php if (in_array($currentRole, ['coach', 'super_admin', 'master_admin'])): ?>
                                                <button type="button" class="btn btn-outline-success" onclick="openDirectEval(<?= $s['id'] ?>, '<?= htmlspecialchars(addslashes($s['coach_notes_technique'] ?? '')) ?>', '<?= htmlspecialchars(addslashes($s['coach_notes_mental'] ?? '')) ?>', '<?= htmlspecialchars(addslashes($s['coach_recommendation'] ?? '')) ?>')" title="Catatan &amp; Evaluasi Pelatih">
                                                    <i class="bi bi-pencil-square"></i>
                                                </button>
                                            <?php endif; ?>
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

<!-- Modal Input Tembakan Baru (Auto Jarak & Preset Format) -->
<div class="modal fade" id="newSessionModal" tabindex="-1" aria-labelledby="newSessionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-secondary shadow" style="background: #111827; color: #f8fafc;">
            <form action="api/create_session.php" method="POST" id="newSessionForm">
                <input type="hidden" name="redirect_html" value="1">
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
                            <?php foreach ($coachesList as $c): 
                                $cleanName = preg_replace('/^Coach\s+/i', '', trim($c['full_name']));
                            ?>
                                <option value="<?= $c['id'] ?>" <?= ($currentArcher['assigned_coach_id'] == $c['id']) ? 'selected' : '' ?>>
                                    Coach <?= htmlspecialchars($cleanName) ?>
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
                                <input class="form-check-input" type="radio" name="session_type" id="typeRegular" value="practice" checked>
                                <label class="form-check-label" for="typeRegular">
                                    Latihan Rutin
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="session_type" id="typeExam" value="exam" <?= $canTakeExam ? '' : 'disabled' ?>>
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

                    <!-- Preset Format Sesi (Pilihan Seri & Anak Panah) -->
                    <div class="mb-3">
                        <label class="form-label fw-bold">Pilihan Format Sesi <span class="text-warning">*</span></label>
                        <select class="form-select bg-dark text-white border-secondary" id="sessionPresetSelect" onchange="applySessionPreset(this.value)">
                            <option value="6x5" selected>🏹 Latihan Rutin: 6 Seri × 5 Panah (30 Panah / Max 300)</option>
                            <option value="6x6">🏆 Standar World Archery: 6 Seri × 6 Panah (36 Panah / Max 360)</option>
                            <option value="10x3">🎯 Indoor Round: 10 Seri × 3 Panah (30 Panah / Max 300)</option>
                            <option value="2x3">⚡ Sesi Percobaan Singkat: 2 Seri × 3 Panah (6 Panah / Max 60)</option>
                            <option value="2x6">🔍 Sesi Percobaan Standar: 2 Seri × 6 Panah (12 Panah / Max 120)</option>
                            <option value="custom">⚙️ Kustom (Atur Seri &amp; Panah Sendiri)</option>
                        </select>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small text-muted">Jumlah Seri (Ends)</label>
                            <input type="number" name="total_ends" id="inputTotalEnds" class="form-control bg-dark text-white border-secondary" value="6" min="1" max="24" required oninput="calcMaxScore()">
                        </div>
                        <div class="col-6">
                            <label class="form-label small text-muted">Panah per Seri (Arrows)</label>
                            <input type="number" name="arrows_per_end" id="inputArrowsPerEnd" class="form-control bg-dark text-white border-secondary" value="5" min="1" max="12" required oninput="calcMaxScore()">
                        </div>
                    </div>

                    <!-- Pilihan Target Face Standar Internasional -->
                    <div class="mb-3">
                        <label class="form-label fw-bold">Papan Target (International Target Face)</label>
                        <select class="form-select bg-dark text-white border-secondary" name="target_id" id="targetFaceSelect">
                            <option value="4" selected>🎯 WA 10-Ring Standard (80/122/40cm Full Face 1-10 + X)</option>
                            <option value="5">🎯 WA 6-Ring Center Spot (Compound/Spot 5-10 + X)</option>
                            <option value="1">🎯 WA Indoor 3-Spot Vertical (Triple Spot 6-10 + X)</option>
                            <option value="2">🎯 NFAA Indoor Blue Face (Blue 1-4, White 5 + X)</option>
                            <option value="3">🎯 WA Field Archery Face (Black 1-4, Gold 5 + X)</option>
                        </select>
                    </div>

                    <div class="alert alert-dark border-secondary py-2 px-3 d-flex justify-content-between align-items-center mb-0">
                        <span class="small text-muted">Kapasitas Maksimal Skor:</span>
                        <strong class="text-warning fs-6" id="maxScoreDisplay">300 Poin (6 Seri × 5 Panah)</strong>
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

<!-- Modal Evaluasi Langsung Coach -->
<div class="modal fade" id="coachEvalModalDirect" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-secondary" style="background: #111827; color: #f8fafc;">
            <div class="modal-header border-secondary">
                <h5 class="modal-title text-warning fw-bold"><i class="bi bi-pencil-square"></i> Evaluasi Pelatih Sesi #<span id="evalSessionIdSpan"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" id="evalDirectSessionId">
                <div class="mb-3">
                    <label class="form-label fw-bold">1. Catatan Form &amp; Teknik (Stance, Anchor, Release)</label>
                    <textarea class="form-control bg-dark text-white border-secondary" id="evalDirectTechnique" rows="3" placeholder="Contoh: Tarikan siku konsisten, perbaiki rilis agar tidak menyentak..."></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">2. Evaluasi Mental &amp; Fokus</label>
                    <input type="text" class="form-control bg-dark text-white border-secondary" id="evalDirectMental" placeholder="Contoh: Sangat tenang saat tembakan terakhir">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">3. Rekomendasi / PR Latihan di Rumah</label>
                    <input type="text" class="form-control bg-dark text-white border-secondary" id="evalDirectRecom" placeholder="Contoh: Latihan SPT 15 menit setiap hari">
                </div>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-success fw-bold px-4" onclick="submitDirectEval()">
                    <i class="bi bi-check-circle-fill"></i> Simpan Catatan Evaluasi
                </button>
            </div>
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

function applySessionPreset(preset) {
    const endsInput = document.getElementById('inputTotalEnds');
    const arrowsInput = document.getElementById('inputArrowsPerEnd');
    if (preset === '6x5') {
        endsInput.value = 6;
        arrowsInput.value = 5;
    } else if (preset === '6x6') {
        endsInput.value = 6;
        arrowsInput.value = 6;
    } else if (preset === '10x3') {
        endsInput.value = 10;
        arrowsInput.value = 3;
    } else if (preset === '2x3') {
        endsInput.value = 2;
        arrowsInput.value = 3;
    } else if (preset === '2x6') {
        endsInput.value = 2;
        arrowsInput.value = 6;
    }
    calcMaxScore();
}

function calcMaxScore() {
    const ends = parseInt(document.getElementById('inputTotalEnds').value) || 6;
    const arrows = parseInt(document.getElementById('inputArrowsPerEnd').value) || 5;
    const maxScore = ends * arrows * 10;
    const display = document.getElementById('maxScoreDisplay');
    if (display) {
        display.innerText = `${maxScore} Poin (${ends} Seri × ${arrows} Panah)`;
    }
}

function openDirectEval(sessionId, tech, mental, recom) {
    document.getElementById('evalSessionIdSpan').innerText = sessionId;
    document.getElementById('evalDirectSessionId').value = sessionId;
    document.getElementById('evalDirectTechnique').value = tech || '';
    document.getElementById('evalDirectMental').value = mental || '';
    document.getElementById('evalDirectRecom').value = recom || '';
    const modal = new bootstrap.Modal(document.getElementById('coachEvalModalDirect'));
    modal.show();
}

function submitDirectEval() {
    const sid = document.getElementById('evalDirectSessionId').value;
    const tech = document.getElementById('evalDirectTechnique').value;
    const mental = document.getElementById('evalDirectMental').value;
    const recom = document.getElementById('evalDirectRecom').value;

    fetch('api/save_session.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            session_id: sid,
            coach_notes_technique: tech,
            coach_notes_mental: mental,
            coach_recommendation: recom,
            mark_completed: true
        })
    })
    .then(r => r.json())
    .then(res => {
        if (res.status === 'success') {
            alert('✔ Catatan evaluasi coach berhasil disimpan!');
            location.reload();
        } else {
            alert('Gagal: ' + res.message);
        }
    })
function generateFieldPin(aid) {
    if (!confirm('Buat PIN baru (6 digit, masa aktif 5 jam) untuk ananda di lapangan?')) return;
    fetch('api/pin_auth.php?action=generate_pin', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ archer_id: aid })
    })
    .then(r => r.json())
    .then(res => {
        if (res.status === 'success') {
            alert('✔ PIN 5 Jam Berhasil Dibuat: ' + res.pin + '\n\nSilakan bagikan via WhatsApp kepada ananda!');
            location.reload();
        } else {
            alert('Gagal: ' + res.message);
        }
    })
    .catch(() => alert('Terjadi gangguan jaringan saat membuat PIN.'));
}
</script>

<?php require_once __DIR__ . '/views/footer.php'; ?>
