<?php
// report_session.php - Rapot Sesi Latihan Resmi (Printable A4 dengan TTD Coach & Ketua Klub)
require_once __DIR__ . '/config/auth.php';
Auth::requireAuth();

$sessionId = (int)($_GET['session_id'] ?? ($_GET['id'] ?? 0));
if ($sessionId <= 0) {
    $role = $_SESSION['role'] ?? 'parent';
    $errBack = in_array($role, ['coach', 'super_admin']) ? 'coach_dashboard.php' : 'app_dashboard.php';
    die("<div style='padding:40px;text-align:center;font-family:sans-serif;'><h3>Session ID tidak valid.</h3><p>Silakan pilih sesi latihan dari daftar riwayat latihan.</p><a href='{$errBack}'>Kembali ke Dashboard</a></div>");
}

// Ambil Detail Sesi Lengkap
$stmt = $pdo->prepare("
    SELECT s.*, a.full_name AS archer_name, a.nis, a.gender, a.current_level,
           cl.club_name, cl.address AS club_address, cl.phone AS club_phone, cl.chairman_name,
           co.full_name AS coach_name,
           t.target_name, t.diameter_cm
    FROM archery_sessions s
    JOIN archery_archers a ON a.id = s.archer_id
    JOIN archery_clubs cl ON cl.id = s.club_id
    LEFT JOIN archery_users co ON co.id = s.coach_id
    LEFT JOIN archery_targets t ON t.id = s.target_id
    WHERE s.id = :id
");
$stmt->execute([':id' => $sessionId]);
$session = $stmt->fetch();

if (!$session) {
    die("Sesi latihan tidak ditemukan.");
}

// Ambil Detail Seri dan Panah
$stmtEnds = $pdo->prepare("
    SELECT e.id AS end_id, e.end_number, e.end_score
    FROM archery_ends e
    WHERE e.session_id = :sid
    ORDER BY e.end_number ASC
");
$stmtEnds->execute([':sid' => $sessionId]);
$ends = $stmtEnds->fetchAll();

$stmtShots = $pdo->prepare("
    SELECT s.end_id, s.arrow_number, s.score, s.is_x, s.x_coord, s.y_coord
    FROM archery_shots s
    WHERE s.session_id = :sid
    ORDER BY s.end_id ASC, s.arrow_number ASC
");
$stmtShots->execute([':sid' => $sessionId]);
$allShots = $stmtShots->fetchAll();

$shotsByEnd = [];
foreach ($allShots as $shot) {
    $shotsByEnd[$shot['end_id']][] = $shot;
}

$currentRole = $_SESSION['role'] ?? 'parent';
$dashboardBackUrl = 'app_dashboard.php';
if ($currentRole === 'coach' || $currentRole === 'super_admin') {
    $dashboardBackUrl = 'coach_dashboard.php';
} elseif ($currentRole === 'master_admin') {
    $dashboardBackUrl = 'master_tenants.php';
}

// Cek kelayakan cetak: Sesi resmi wajib memiliki evaluasi catatan teknik atau skor checklist dari Coach
$isEvaluated = !empty(trim($session['coach_notes_technique'] ?? '')) || !empty($session['evaluation_checklist_score']);

// Ambil Kriteria Rubrik Evaluasi untuk Klub ini
$stmtCriteria = $pdo->prepare("SELECT * FROM archery_eval_criteria WHERE club_id = :cid AND is_active = 1 ORDER BY sort_order ASC, id ASC");
$stmtCriteria->execute([':cid' => (int)$session['club_id']]);
$evalCriteriaList = $stmtCriteria->fetchAll();
if (empty($evalCriteriaList)) {
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

$checklistItems = [];
if (!empty($session['evaluation_checklist_json'])) {
    $decoded = json_decode($session['evaluation_checklist_json'], true);
    if (is_array($decoded)) {
        $checklistItems = $decoded;
    }
}

$pageTitle = "Rapot Sesi Latihan - " . htmlspecialchars($session['archer_name']);
require_once __DIR__ . '/views/header.php';
?>
<link rel="stylesheet" href="assets/css/print_report.css">

<!-- TOOLBAR ACTION (TIDAK TERCETAK SAAT PRINT) -->
<div class="no-print" style="max-width: 820px; margin: 1.5rem auto 0; display: flex; justify-content: space-between; align-items: center; padding: 0 1rem; flex-wrap: wrap; gap: 0.5rem;">
    <a href="<?= $dashboardBackUrl ?>" class="btn btn-secondary btn-sm fw-bold">
        ⬅ Kembali ke Dashboard
    </a>
    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center;">
        <?php if (in_array($currentRole, ['coach', 'super_admin'])): ?>
            <button type="button" class="btn btn-outline-warning btn-sm fw-bold shadow-sm" onclick="openEvalModal()">
                ✍️ Lembar Evaluasi &amp; Rubrik Pelatih
            </button>
        <?php endif; ?>
        <a href="share_card.php?session_id=<?= $session['id'] ?>" class="btn btn-secondary btn-sm" style="color: #38bdf8;">
            📱 Kartu Medsos
        </a>
        <?php if ($isEvaluated): ?>
            <button type="button" class="btn btn-primary btn-sm fw-bold shadow-sm" onclick="window.print()">
                🖨️ Cetak Rapot Resmi (Print/PDF)
            </button>
        <?php else: ?>
            <button type="button" class="btn btn-secondary btn-sm fw-bold" disabled title="Lembar rapot terkunci hingga Coach mengisi evaluasi">
                🔒 Cetak Terkunci (Menunggu Evaluasi Coach)
            </button>
        <?php endif; ?>
    </div>
</div>

<div class="report-card-page">
    <?php if (!$isEvaluated): ?>
        <div class="alert alert-warning py-3 px-4 mb-3 d-flex align-items-center gap-3 no-print" style="background: rgba(245, 158, 11, 0.12); border: 2px dashed #f59e0b; color: #fde68a; border-radius: 8px;">
            <div style="font-size: 2rem;">🔒</div>
            <div>
                <h5 class="mb-1 fw-bold text-warning">Lembar Rapot Resmi Belum Disahkan Coach</h5>
                <p class="mb-0 small" style="color: #cbd5e1;">
                    Sesi latihan ini telah selesai ditembakkan, namun masih menunggu evaluasi form &amp; catatan teknik dari <b>Coach <?= htmlspecialchars($session['coach_name'] ?? 'Pelatih') ?></b>. Dokumen resmi cetak A4 akan terbuka otomatis setelah Coach memberikan penilaian.
                </p>
            </div>
        </div>
    <?php endif; ?>

    <!-- 1. KOP SURAT RESMI KLUB PANAHAN -->
    <div class="report-header">
        <div class="report-logo">🎯</div>
        <div class="report-title-box">
            <h1><?= htmlspecialchars($session['club_name']) ?></h1>
            <p><?= htmlspecialchars($session['club_address']) ?></p>
            <p>Kontak / WhatsApp: <?= htmlspecialchars($session['club_phone']) ?> • Standar World Archery</p>
        </div>
    </div>

    <div style="text-align: center; margin-bottom: 1.25rem;">
        <h2 style="font-size: 1.15rem; color: #0f172a; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 2px;">
            LEMBAR HASIL SKORING & RAPOT PERTEMUAN LATIHAN
        </h2>
        <span style="font-size: 0.8rem; color: #64748b;">
            Nomor Sesi: #<?= str_pad($session['id'], 5, '0', STR_PAD_LEFT) ?> / ARC-DOC / <?= date('m/Y', strtotime($session['session_date'])) ?>
        </span>
    </div>

    <!-- 2. IDENTITAS ATLET & PARAMETER LATIHAN -->
    <div class="identity-grid">
        <div class="identity-row">
            <span class="identity-label">Nama Atlet:</span>
            <span class="identity-val"><?= htmlspecialchars($session['archer_name']) ?></span>
        </div>
        <div class="identity-row">
            <span class="identity-label">Tanggal Latihan:</span>
            <span class="identity-val"><?= date('d F Y', strtotime($session['session_date'])) ?></span>
        </div>
        <div class="identity-row">
            <span class="identity-label">Nomor Induk (NIS):</span>
            <span class="identity-val"><?= htmlspecialchars($session['nis']) ?></span>
        </div>
        <div class="identity-row">
            <span class="identity-label">Jarak Tembak:</span>
            <span class="identity-val"><?= $session['distance_meter'] ?> Meter</span>
        </div>
        <div class="identity-row">
            <span class="identity-label">Kategori Busur:</span>
            <span class="identity-val"><?= htmlspecialchars($session['bow_category']) ?></span>
        </div>
        <div class="identity-row">
            <span class="identity-label">Target Face:</span>
            <span class="identity-val"><?= htmlspecialchars($session['target_name']) ?> (<?= $session['diameter_cm'] ?>cm)</span>
        </div>
        <div class="identity-row">
            <span class="identity-label">Level Atlet:</span>
            <span class="identity-val"><?= htmlspecialchars($session['current_level']) ?></span>
        </div>
        <div class="identity-row">
            <span class="identity-label">Pelatih (Coach):</span>
            <span class="identity-val"><?= htmlspecialchars($session['coach_name'] ?? 'Coach Pendamping') ?></span>
        </div>
    </div>

    <!-- 3. TABEL RESMI SCORECARD PER SERI -->
    <table class="print-score-table">
        <thead>
            <tr>
                <th style="width: 55px;">Seri</th>
                <th colspan="<?= (int)$session['arrows_per_end'] ?>">Nilai Anak Panah</th>
                <th style="width: 80px;">Skor Seri</th>
                <th style="width: 90px;">Total Berjalan</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $runningTotal = 0;
            for ($i = 1; $i <= (int)$session['total_ends']; $i++): 
                $endObj = null;
                foreach ($ends as $e) {
                    if ((int)$e['end_number'] === $i) { $endObj = $e; break; }
                }
                $endScore = $endObj ? (int)$endObj['end_score'] : 0;
                $runningTotal += $endScore;
                $shots = $endObj ? ($shotsByEnd[$endObj['end_id']] ?? []) : [];
            ?>
                <tr>
                    <td><b><?= $i ?></b></td>
                    <?php for ($a = 0; $a < (int)$session['arrows_per_end']; $a++): 
                        $s = $shots[$a] ?? null;
                    ?>
                        <td style="font-weight: 700; width: 45px;">
                            <?php if ($s): ?>
                                <?= $s['is_x'] ? 'X' : ($s['score'] == 0 ? 'M' : $s['score']) ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                    <?php endfor; ?>
                    <td style="font-weight: 700;"><?= $endScore ?></td>
                    <td style="font-weight: 800; background: #f8fafc;"><?= $runningTotal ?></td>
                </tr>
            <?php endfor; ?>
            
            <tr class="total-row">
                <td colspan="<?= (int)$session['arrows_per_end'] + 1 ?>" style="text-align: right; padding-right: 1rem;">
                    TOTAL SKOR KESELURUHAN (MAKS <?= $session['max_possible_score'] ?>)
                </td>
                <td style="font-size: 1.1rem; color: #b45309;"><?= $session['total_score'] ?></td>
                <td style="font-size: 1.1rem; color: #047857;"><?= $session['total_score'] ?></td>
            </tr>
        </tbody>
    </table>

    <!-- RINGKASAN METRIK AKURASI -->
    <div style="display: flex; justify-content: space-around; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 0.65rem; margin-bottom: 1.5rem; text-align: center; font-size: 0.85rem;">
        <div>Persentase Akurasi: <b style="color: #047857; font-size: 1rem;"><?= $session['accuracy_pct'] ?>%</b></div>
        <div>Rata-rata / Panah: <b style="color: #0369a1; font-size: 1rem;"><?= $session['avg_arrow_score'] ?> pts</b></div>
        <div>Jumlah 10s: <b style="color: #b45309; font-size: 1rem;"><?= $session['ten_count'] ?></b></div>
        <div>Jumlah Xs: <b style="color: #b45309; font-size: 1rem;"><?= $session['x_count'] ?></b></div>
    </div>

    <!-- 4. CATATAN & EVALUASI RESMI DARI PELATIH -->
    <div class="coach-eval-box">
        <div class="coach-eval-title">
            📝 EVALUASI &amp; CATATAN PERKEMBANGAN DARI PELATIH
        </div>
        <div class="coach-eval-content">
            <?php if (!empty($checklistItems)): ?>
                <div style="margin-bottom: 0.65rem; padding: 0.45rem 0.75rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.35rem;">
                        <b style="font-size: 0.8rem; color: #0f172a;">🎯 Capaian Rubrik Standar:</b>
                        <span style="font-size: 0.8rem; font-weight: 700; color: #0284c7;">
                            Skor: <?= (int)$session['evaluation_checklist_score'] ?> / <?= (int)($session['evaluation_max_score'] ?: $totalMaxPoints) ?> Poin (<?= round(((int)$session['evaluation_checklist_score'] / max(1, (int)($session['evaluation_max_score'] ?: $totalMaxPoints))) * 100) ?>%)
                        </span>
                    </div>
                    <div style="display: flex; flex-wrap: wrap; gap: 4px;">
                        <?php foreach ($checklistItems as $it): ?>
                            <span style="font-size: 0.72rem; background: #e0f2fe; color: #0369a1; padding: 2px 6px; border-radius: 4px; border: 1px solid #bae6fd;">
                                ✔ <?= htmlspecialchars($it['title'] ?? '') ?> (+<?= (int)($it['points'] ?? 0) ?>pt)
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            <p style="margin-bottom: 0.35rem;">
                <b>1. Form & Eksekusi Teknik:</b><br>
                <?= !empty($session['coach_notes_technique']) ? nl2br(htmlspecialchars($session['coach_notes_technique'])) : 'Teknik tarikan dan anchor point cukup konsisten. Pertahankan kestabilan bow arm.' ?>
            </p>
            <p style="margin-bottom: 0.35rem;">
                <b>2. Fokus & Mental Atlet:</b><br>
                <?= !empty($session['coach_notes_mental']) ? nl2br(htmlspecialchars($session['coach_notes_mental'])) : 'Fokus sangat baik, ketenangan dalam membidik terjaga.' ?>
            </p>
            <p>
                <b>3. Rekomendasi / PR Latihan Mandiri di Rumah:</b><br>
                <?= !empty($session['coach_recommendation']) ? nl2br(htmlspecialchars($session['coach_recommendation'])) : 'Lakukan stretching dan latihan tarikan karet (resistance band) 15 menit setiap pagi.' ?>
            </p>
        </div>
    </div>

    <!-- 5. TANDA TANGAN RESMI COACH & KETUA KLUB -->
    <div class="signatures-grid">
        <div class="sig-box">
            <div class="sig-role">
                Mengetahui & Menguji,<br>
                <b>Pelatih Pendamping (Coach)</b>
            </div>
            <div class="sig-name">
                <?= htmlspecialchars($session['coach_name'] ?? 'Coach Pendamping') ?>
            </div>
            <div class="sig-sub">Pelatih Berlisensi Klub</div>
        </div>

        <div class="sig-box">
            <div class="sig-role">
                Disahkan oleh,<br>
                <b>Ketua An-Nahl Archery Club</b>
            </div>
            <div class="sig-name">
                <?= htmlspecialchars($session['chairman_name']) ?>
            </div>
            <div class="sig-sub">Ketua / Penanggung Jawab</div>
        </div>
    </div>
</div>

<!-- MODAL EVALUASI PELATIH (BOOTSTRAP CENTERED MODAL) -->
<div class="modal fade" id="evalModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-secondary" style="background: #111827; color: #f8fafc;">
            <div class="modal-header border-secondary">
                <h5 class="modal-title fw-bold text-white d-flex align-items-center gap-2">
                    ✍️ Lembar Evaluasi Pelatih Sesi #<?= $session['id'] ?> (<?= htmlspecialchars($session['archer_name']) ?>)
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="evalForm" onsubmit="submitEvalForm(event)">
                <div class="modal-body p-3 p-md-4">
                    <input type="hidden" name="session_id" value="<?= $session['id'] ?>">

                    <!-- RUBRIK EVALUASI CHECKLIST KETERAMPILAN DINAMIS -->
                    <div class="card mb-4 border-secondary" style="background: rgba(15, 23, 42, 0.7);">
                        <div class="card-header border-secondary d-flex justify-content-between align-items-center py-2 px-3">
                            <span class="small fw-bold text-warning d-flex align-items-center gap-1">
                                <i class="bi bi-ui-checks"></i> Rubrik Kriteria Penilaian Atlet
                            </span>
                            <span class="badge bg-dark border border-warning text-warning" id="reportLiveScoreBadge">
                                Skor: 0 / <?= $totalMaxPoints ?> Poin (0%)
                            </span>
                        </div>
                        <div class="card-body p-3" style="max-height: 240px; overflow-y: auto;">
                            <?php if (empty($groupedCriteria)): ?>
                                <p class="text-muted small mb-0">Belum ada rubrik evaluasi yang disetup oleh Super Admin.</p>
                            <?php else: ?>
                                <?php 
                                $activeChecklistIds = array_map(function($x) { return (int)($x['id'] ?? 0); }, $checklistItems);
                                foreach ($groupedCriteria as $catName => $items): 
                                ?>
                                    <div class="mb-3">
                                        <div class="small fw-bold text-info border-bottom border-secondary border-opacity-25 pb-1 mb-2">
                                            <?= htmlspecialchars($catName) ?>
                                        </div>
                                        <div class="row g-2">
                                            <?php foreach ($items as $crit): 
                                                $isChecked = in_array((int)$crit['id'], $activeChecklistIds);
                                            ?>
                                                <div class="col-md-6">
                                                    <div class="form-check p-2 rounded border border-secondary border-opacity-25" style="background: rgba(255,255,255,0.02);">
                                                        <input class="form-check-input report-eval-item" type="checkbox" 
                                                               id="rep_crit_<?= $crit['id'] ?>" 
                                                               value="<?= $crit['id'] ?>" 
                                                               data-points="<?= (int)$crit['points'] ?>"
                                                               data-title="<?= htmlspecialchars($crit['title']) ?>"
                                                               data-category="<?= htmlspecialchars($crit['category']) ?>"
                                                               <?= $isChecked ? 'checked' : '' ?>
                                                               onchange="calcReportLiveScore()">
                                                        <label class="form-check-label small text-light d-flex justify-content-between align-items-center" for="rep_crit_<?= $crit['id'] ?>">
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
                        <label class="form-label small fw-bold text-light mb-1">1. Form &amp; Eksekusi Teknik (Stance, Anchor, Release) <span class="text-danger">*</span></label>
                        <textarea class="form-control bg-dark text-white border-secondary" name="coach_notes_technique" id="modalTechnique" rows="3" placeholder="Evaluasi posisi badan, tarikan, jangkar, dan pelepasan panah..." required><?= htmlspecialchars($session['coach_notes_technique'] ?? '') ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-light mb-1">2. Fokus &amp; Mental Atlet</label>
                        <input type="text" class="form-control bg-dark text-white border-secondary" name="coach_notes_mental" id="modalMental" value="<?= htmlspecialchars($session['coach_notes_mental'] ?? '') ?>" placeholder="Tingkat ketenangan, ritme pernapasan, konsentrasi...">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-light mb-1">3. Rekomendasi / PR Latihan Mandiri di Rumah</label>
                        <input type="text" class="form-control bg-dark text-white border-secondary" name="coach_recommendation" id="modalRecom" value="<?= htmlspecialchars($session['coach_recommendation'] ?? '') ?>" placeholder="Tugas latihan SPT, karet resistensi, push up...">
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary fw-bold px-4" id="saveEvalBtn">💾 Simpan Evaluasi &amp; Buka Rapot</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let reportEvalModalInst = null;

function openEvalModal() {
    calcReportLiveScore();
    if (!reportEvalModalInst) {
        reportEvalModalInst = new bootstrap.Modal(document.getElementById('evalModal'));
    }
    reportEvalModalInst.show();
}

function calcReportLiveScore() {
    let earned = 0;
    const max = <?= (int)$totalMaxPoints ?>;
    document.querySelectorAll('.report-eval-item:checked').forEach(cb => {
        earned += parseInt(cb.dataset.points) || 0;
    });
    const pct = max > 0 ? Math.round((earned / max) * 100) : 0;
    const badge = document.getElementById('reportLiveScoreBadge');
    if (badge) {
        badge.textContent = `Skor: ${earned} / ${max} Poin (${pct}%)`;
    }
    return { earned, max, pct };
}

async function submitEvalForm(e) {
    e.preventDefault();
    const btn = document.getElementById('saveEvalBtn');
    btn.disabled = true;
    btn.textContent = 'Menyimpan...';

    const selectedCriteria = [];
    let checklistScore = 0;
    document.querySelectorAll('.report-eval-item:checked').forEach(cb => {
        checklistScore += parseInt(cb.dataset.points) || 0;
        selectedCriteria.push({
            id: parseInt(cb.value),
            title: cb.dataset.title,
            category: cb.dataset.category,
            points: parseInt(cb.dataset.points) || 0
        });
    });

    const payload = {
        session_id: <?= (int)$session['id'] ?>,
        coach_notes_technique: document.getElementById('modalTechnique').value,
        coach_notes_mental: document.getElementById('modalMental').value,
        coach_recommendation: document.getElementById('modalRecom').value,
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
            alert('✔ Catatan evaluasi pelatih & rubrik capaian berhasil disimpan! Rapot kini dapat dicetak.');
            location.reload();
        } else {
            alert('Gagal: ' + (json.message || 'Terjadi kesalahan'));
        }
    } catch (err) {
        alert('Terjadi kesalahan jaringan: ' + err.message);
    } finally {
        btn.disabled = false;
        btn.textContent = '💾 Simpan Evaluasi & Buka Rapot';
    }
}
</script>

<?php include __DIR__ . '/views/footer.php'; ?>
