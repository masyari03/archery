<?php
// plotter.php - Papan Plotting Target Panahan Interaktif (Adopsi MyTargets Engine)
require_once __DIR__ . '/config/auth.php';
Auth::requireAuth();

$currentUser = Auth::user();
$currentArcher = Auth::currentArcher($pdo);

if (!$currentArcher) {
    die("Atlet tidak ditemukan. Silakan pilih atlet terlebih dahulu.");
}

$archerId = (int)$currentArcher['id'];
$sessionId = (int)($_GET['session_id'] ?? 0);

// Jika tidak ada session_id, cari sesi in_progress hari ini atau buat sesi baru
if ($sessionId <= 0) {
    $today = date('Y-m-d');
    $stmtFind = $pdo->prepare("SELECT id FROM archery_sessions WHERE archer_id = :aid AND session_date = :dt AND status = 'in_progress' ORDER BY id DESC LIMIT 1");
    $stmtFind->execute([':aid' => $archerId, ':dt' => $today]);
    $sessionId = (int)$stmtFind->fetchColumn();

    if ($sessionId <= 0) {
        // Buat sesi baru default (6 Seri @ 5 Panah = 300 Max)
        $stmtNew = $pdo->prepare("
            INSERT INTO archery_sessions 
            (club_id, archer_id, coach_id, session_date, start_time, distance_meter, bow_category, target_id, total_ends, arrows_per_end, max_possible_score, status)
            VALUES (1, :aid, 2, :dt, CURTIME(), 18, :bow, 4, 6, 5, 300, 'in_progress')
        ");
        $stmtNew->execute([
            ':aid' => $archerId,
            ':dt' => $today,
            ':bow' => $currentArcher['bow_category'] ?? 'Barebow'
        ]);
        $sessionId = (int)$pdo->lastInsertId();
    }
}

// Ambil Data Sesi
$stmtSess = $pdo->prepare("
    SELECT s.*, a.full_name AS archer_name, a.nis, a.bow_category AS default_bow, t.target_name, t.code AS target_code
    FROM archery_sessions s
    JOIN archery_archers a ON a.id = s.archer_id
    LEFT JOIN archery_targets t ON t.id = s.target_id
    WHERE s.id = :id
");
$stmtSess->execute([':id' => $sessionId]);
$session = $stmtSess->fetch();

if (!$session) {
    die("Sesi latihan tidak ditemukan.");
}

// Ambil seluruh shots yang sudah ada di database untuk sesi ini
$stmtShots = $pdo->prepare("
    SELECT s.arrow_number, s.x_coord AS x, s.y_coord AS y, s.radius, s.score, s.is_x, e.end_number
    FROM archery_shots s
    JOIN archery_ends e ON e.id = s.end_id
    WHERE s.session_id = :sid
    ORDER BY e.end_number ASC, s.arrow_number ASC
");
$stmtShots->execute([':sid' => $sessionId]);
$allShots = $stmtShots->fetchAll();

$existingEnds = [];
for ($i = 1; $i <= (int)$session['total_ends']; $i++) {
    $existingEnds[$i] = [];
}
foreach ($allShots as $shot) {
    $endNum = (int)$shot['end_number'];
    $existingEnds[$endNum][] = [
        'arrow_num' => (int)$shot['arrow_number'],
        'x' => (float)$shot['x'],
        'y' => (float)$shot['y'],
        'radius' => (float)$shot['radius'],
        'score' => (int)$shot['score'],
        'is_x' => (int)$shot['is_x']
    ];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Papan Bidik Target - TARGET ARCHERY</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
    <style>
        .plotter-grid {
            display: grid;
            grid-template-columns: 1.15fr 0.85fr;
            gap: 1.5rem;
            align-items: start;
        }
        @media (max-width: 900px) {
            .plotter-grid {
                grid-template-columns: 1fr;
            }
        }
        .ends-pill-bar {
            display: flex;
            gap: 0.5rem;
            overflow-x: auto;
            padding-bottom: 0.5rem;
            margin-bottom: 1rem;
        }
        .end-btn {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-color);
            color: var(--text-muted);
            padding: 0.5rem 1rem;
            border-radius: var(--radius-sm);
            cursor: pointer;
            font-family: 'Outfit', sans-serif;
            font-weight: 700;
            white-space: nowrap;
            transition: all 0.2s;
        }
        .end-btn.active {
            background: var(--primary);
            color: #000;
            border-color: var(--primary);
            box-shadow: 0 0 15px rgba(245, 158, 11, 0.4);
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/views/navbar.php'; ?>

<main class="container">
    <!-- SESI INFO BAR -->
    <div class="card" style="margin-bottom: 1.25rem; padding: 1rem 1.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
            <div>
                <span class="badge badge-gold">SESI AKTIF #<?= $session['id'] ?></span>
                <span style="font-weight: 700; font-size: 1.1rem; margin-left: 0.5rem;">
                    <?= htmlspecialchars($session['archer_name']) ?>
                </span>
                <span style="color: var(--text-muted); font-size: 0.85rem; margin-left: 0.5rem;">
                    • Jarak: <b><?= $session['distance_meter'] ?>m</b> • Busur: <b><?= htmlspecialchars($session['bow_category']) ?></b> • <?= date('d M Y', strtotime($session['session_date'])) ?>
                </span>
            </div>
            <div style="display: flex; gap: 0.5rem; align-items: center;">
                <div id="syncStatusIndicator" style="font-size: 0.8rem; font-weight: 600; margin-right: 0.5rem;">
                    <span style="color: #10b981;">✔ Data Tersinkron</span>
                </div>
                <a href="report_session.php?session_id=<?= $session['id'] ?>" class="btn btn-secondary btn-sm">
                    📜 Rapot Cetak
                </a>
                <a href="share_card.php?session_id=<?= $session['id'] ?>" class="btn btn-secondary btn-sm" style="color: #38bdf8;">
                    📱 Share Card
                </a>
            </div>
        </div>
    </div>

    <div class="plotter-grid">
        <!-- KOLOM KIRI: TARGET FACE CANVAS + KACA PEMBESAR (LOUPE) -->
        <div class="card target-plotter-container">
            <div style="width: 100%; display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <span id="activeEndBadge" class="badge badge-gold" style="font-size: 0.9rem;">Seri 1 / <?= $session['total_ends'] ?></span>
                <span style="font-size: 0.8rem; color: var(--text-dim);">💡 Sentuh & geser jari untuk membidik presisi</span>
            </div>

            <!-- CANVAS TARGET WRAP DENGAN FLOATING MAGNIFIER LOUPE -->
            <div class="target-canvas-wrap">
                <canvas id="targetCanvas"></canvas>
                <!-- Kaca Pembesar Zoom (Loupe) Melayang -->
                <div class="magnifier-loupe" id="magnifierLoupe">
                    <canvas></canvas>
                    <div class="magnifier-crosshair"></div>
                    <div class="magnifier-score-badge">10</div>
                </div>
            </div>

            <!-- SERI CONTROLS & ARROW SLOTS -->
            <div style="width: 100%; margin-top: 1.25rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <span style="font-size: 0.85rem; font-weight: 600; color: var(--text-muted);">Panah Seri Ini:</span>
                        <div id="arrowSlots" style="display: flex; gap: 5px;"></div>
                    </div>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="plotter.undoLastArrow()" title="Hapus Panah Terakhir">
                        ↩️ Undo Panah
                    </button>
                </div>

                <!-- END SELECTOR BAR -->
                <div class="ends-pill-bar">
                    <?php for ($i = 1; $i <= (int)$session['total_ends']; $i++): ?>
                        <button type="button" class="end-btn <?= ($i === 1) ? 'active' : '' ?>" id="endBtn_<?= $i ?>" onclick="switchEndNumber(<?= $i ?>)">
                            Seri <?= $i ?>
                        </button>
                    <?php endfor; ?>
                </div>

                <!-- COACHING GROUPING ADVICE BOX (WINDAGE & ELEVATION) -->
                <div id="groupingAdviceBox" class="card" style="background: rgba(0,0,0,0.3); border: 1px solid var(--border-color); padding: 0.75rem; margin-top: 0.5rem;">
                    <!-- Diisi otomatis oleh JavaScript -->
                </div>
            </div>
        </div>

        <!-- KOLOM KANAN: LIVE SCORECARD + FORM EVALUASI COACH -->
        <div>
            <!-- STATS COUNTER CARD -->
            <div class="card" style="margin-bottom: 1.25rem;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; text-align: center;">
                    <div>
                        <span class="stat-label">TOTAL SKOR</span>
                        <div class="stat-value" id="totalScoreDisplay" style="color: var(--primary);">0</div>
                        <span class="stat-sub">Maksimal: <?= $session['max_possible_score'] ?></span>
                    </div>
                    <div>
                        <span class="stat-label">AKURASI</span>
                        <div class="stat-value" id="accuracyDisplay" style="color: #34d399;">0.0%</div>
                        <span class="stat-sub">10s & Xs: <b id="tenXDisplay">0 / 0</b></span>
                    </div>
                </div>

                <!-- TABEL NILAI PER SERI -->
                <table class="scorecard-table" style="margin-top: 1rem;">
                    <thead>
                        <tr>
                            <th>Seri</th>
                            <th>Target Panah</th>
                            <th>Skor Seri</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php for ($i = 1; $i <= (int)$session['total_ends']; $i++): ?>
                            <tr>
                                <td><b>Seri <?= $i ?></b></td>
                                <td style="color: var(--text-dim);"><?= $session['arrows_per_end'] ?> Anak Panah</td>
                                <td id="endScoreVal_<?= $i ?>" style="font-size: 1.1rem; color: #fff;">0</td>
                                <td>
                                    <button type="button" class="btn btn-secondary btn-sm" onclick="switchEndNumber(<?= $i ?>)" style="padding: 2px 8px; font-size: 0.75rem;">
                                        Pilih ➔
                                    </button>
                                </td>
                            </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>

            <!-- PEMBERITAHUAN SESI MASIH BERJALAN (SEBELUM 6 SERI LENGKAP) -->
            <div id="sessionInProgressNotice" class="card" style="border-left: 4px solid var(--primary); background: rgba(2, 132, 199, 0.08); padding: 1.25rem; margin-bottom: 1.25rem;">
                <div style="display: flex; gap: 0.75rem; align-items: flex-start;">
                    <div style="font-size: 1.5rem;">🎯</div>
                    <div>
                        <h4 style="margin: 0 0 0.25rem 0; font-size: 0.95rem; font-weight: 700; color: var(--primary);">
                            Selesaikan Seluruh <?= $session['total_ends'] ?> Seri
                        </h4>
                        <p style="margin: 0; font-size: 0.8rem; color: var(--text-muted); line-height: 1.4;">
                            Sesuai standar latihan, form evaluasi dan catatan pelatih akan otomatis terbuka setelah kamu menyelesaikan seluruh <?= $session['total_ends'] ?> seri (total <?= $session['total_ends'] * $session['arrows_per_end'] ?> panah). Nilai tersimpan otomatis setiap tembakan!
                        </p>
                    </div>
                </div>
            </div>

            <!-- FORM EVALUASI COACH DI SETIAP SESI (TERKUNCI HINGGA 6 SERI LENGKAP) -->
            <div id="coachEvalSection" class="card" style="display: none;">
                <div class="card-header">
                    <h3 class="card-title" style="display: flex; align-items: center; justify-content: space-between;">
                        <span>👨‍🏫 Evaluasi Pelatih di Sesi Ini</span>
                        <span class="badge badge-success" style="font-size: 0.75rem;">6 Seri Selesai</span>
                    </h3>
                </div>
                <form id="coachEvalForm" onsubmit="saveCoachEvaluation(event)">
                    <div class="form-group">
                        <label class="form-label">1. Evaluasi Form & Teknik (Stance, Anchor, Release)</label>
                        <textarea class="form-control" name="coach_notes_technique" id="coachTechnique" rows="2" placeholder="Contoh: Tarikan siku konsisten, perbaiki rilis agar tidak menyentak..."><?= htmlspecialchars($session['coach_notes_technique'] ?? '') ?></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">2. Evaluasi Mental & Fokus</label>
                        <input type="text" class="form-control" name="coach_notes_mental" id="coachMental" value="<?= htmlspecialchars($session['coach_notes_mental'] ?? '') ?>" placeholder="Contoh: Sangat tenang saat tembakan terakhir">
                    </div>

                    <div class="form-group">
                        <label class="form-label">3. Rekomendasi / PR Latihan di Rumah</label>
                        <input type="text" class="form-control" name="coach_recommendation" id="coachRecom" value="<?= htmlspecialchars($session['coach_recommendation'] ?? '') ?>" placeholder="Contoh: Latihan SPT 15 menit setiap pagi">
                    </div>

                    <div style="display: flex; gap: 0.75rem;">
                        <button type="submit" class="btn btn-secondary btn-block">
                            💾 Simpan Catatan
                        </button>
                        <button type="button" class="btn btn-primary btn-block" onclick="finishAndPrintReport()">
                            ✅ Selesai & Cetak Rapot ➔
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>

<script src="assets/js/target_plotter.js"></script>
<script>
const plotter = new ArcheryPlotter('targetCanvas', 'magnifierLoupe', {
    sessionId: <?= (int)$session['id'] ?>,
    archerId: <?= (int)$session['archer_id'] ?>,
    totalEnds: <?= (int)$session['total_ends'] ?>,
    arrowsPerEnd: <?= (int)$session['arrows_per_end'] ?>,
    existingEnds: <?= json_encode($existingEnds) ?>
});

// Jalankan pengecekan inisial untuk status kelengkapan 6 seri
plotter.updateScorecardUI();
plotter.checkSessionCompletion();

function switchEndNumber(num) {
    plotter.switchEnd(num);
    document.querySelectorAll('.end-btn').forEach(btn => btn.classList.remove('active'));
    const targetBtn = document.getElementById(`endBtn_${num}`);
    if (targetBtn) targetBtn.classList.add('active');
}

function saveCoachEvaluation(e, markCompleted = false) {
    if (e) e.preventDefault();

    const payload = {
        session_id: <?= (int)$session['id'] ?>,
        coach_notes_technique: document.getElementById('coachTechnique').value,
        coach_notes_mental: document.getElementById('coachMental').value,
        coach_recommendation: document.getElementById('coachRecom').value,
        mark_completed: markCompleted
    };

    fetch('api/save_session.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(res => {
        if (res.status === 'success') {
            if (markCompleted) {
                window.location.href = 'report_session.php?session_id=<?= (int)$session['id'] ?>';
            } else {
                alert('✔ Catatan evaluasi coach berhasil disimpan!');
            }
        } else {
            alert('Gagal simpan evaluasi: ' + res.message);
        }
    })
    .catch(() => alert('Terjadi gangguan jaringan saat menyimpan catatan coach'));
}

function finishAndPrintReport() {
    if (confirm('Tandai sesi latihan ini selesai dan cetak rapot resmi?')) {
        saveCoachEvaluation(null, true);
    }
}
</script>

<?php include __DIR__ . '/views/footer.php'; ?>
