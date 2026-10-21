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

$pageTitle = "Rapot Sesi Latihan - " . htmlspecialchars($session['archer_name']);
require_once __DIR__ . '/views/header.php';
?>
<link rel="stylesheet" href="assets/css/print_report.css">

<!-- TOOLBAR ACTION (TIDAK TERCETAK SAAT PRINT) -->
<div class="no-print" style="max-width: 820px; margin: 1.5rem auto 0; display: flex; justify-content: space-between; align-items: center; padding: 0 1rem; flex-wrap: wrap; gap: 0.5rem;">
    <a href="<?= $dashboardBackUrl ?>" class="btn btn-secondary btn-sm fw-bold">
        ⬅ Kembali ke Dashboard
    </a>
    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
        <?php if (in_array($currentRole, ['coach', 'super_admin'])): ?>
            <button type="button" class="btn btn-outline-warning btn-sm fw-bold" onclick="openEvalModal()">
                ✍️ Catatan / Evaluasi Pelatih
            </button>
        <?php endif; ?>
        <a href="share_card.php?session_id=<?= $session['id'] ?>" class="btn btn-secondary btn-sm" style="color: #38bdf8;">
            📱 Buat Kartu Medsos
        </a>
        <button type="button" class="btn btn-primary btn-sm fw-bold" onclick="window.print()">
            🖨️ Cetak Rapot Resmi (Print/PDF)
        </button>
    </div>
</div>

<div class="report-card-page">
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
            📝 EVALUASI & CATATAN PERKEMBANGAN DARI PELATIH
        </div>
        <div class="coach-eval-content">
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

<!-- MODAL EVALUASI PELATIH (KHUSUS COACH & SUPER ADMIN) -->
<div id="evalModal" class="login-modal-overlay" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.75); z-index: 1050; align-items: center; justify-content: center; backdrop-filter: blur(6px);">
    <div class="card" style="max-width: 520px; width: 92%; background: #0f172a; border: 1px solid var(--border-color); border-radius: 14px; padding: 1.5rem; box-shadow: 0 20px 40px rgba(0,0,0,0.6);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.75rem;">
            <h3 style="font-size: 1.15rem; font-weight: 700; margin: 0; color: #fff;">
                ✍️ Catatan &amp; Evaluasi Pelatih Sesi #<?= $session['id'] ?>
            </h3>
            <button type="button" onclick="closeEvalModal()" style="background: transparent; border: none; color: #94a3b8; font-size: 1.3rem; cursor: pointer;">&times;</button>
        </div>
        <form id="evalForm" onsubmit="submitEvalForm(event)">
            <input type="hidden" name="session_id" value="<?= $session['id'] ?>">
            <div class="form-group mb-3">
                <label class="form-label small fw-bold text-light mb-1">1. Form &amp; Eksekusi Teknik (Stance, Anchor, Release)</label>
                <textarea class="form-control" name="coach_notes_technique" id="modalTechnique" rows="3" placeholder="Evaluasi posisi badan, tarikan, jangkar, dan pelepasan panah..." style="background: rgba(255,255,255,0.06); color: #fff; border: 1px solid rgba(255,255,255,0.15);"><?= htmlspecialchars($session['coach_notes_technique'] ?? '') ?></textarea>
            </div>
            <div class="form-group mb-3">
                <label class="form-label small fw-bold text-light mb-1">2. Fokus &amp; Mental Atlet</label>
                <input type="text" class="form-control" name="coach_notes_mental" id="modalMental" value="<?= htmlspecialchars($session['coach_notes_mental'] ?? '') ?>" placeholder="Tingkat ketenangan, ritme pernapasan, konsentrasi..." style="background: rgba(255,255,255,0.06); color: #fff; border: 1px solid rgba(255,255,255,0.15);">
            </div>
            <div class="form-group mb-3">
                <label class="form-label small fw-bold text-light mb-1">3. Rekomendasi / PR Latihan Mandiri di Rumah</label>
                <input type="text" class="form-control" name="coach_recommendation" id="modalRecom" value="<?= htmlspecialchars($session['coach_recommendation'] ?? '') ?>" placeholder="Tugas latihan SPT, karet resistensi, push up..." style="background: rgba(255,255,255,0.06); color: #fff; border: 1px solid rgba(255,255,255,0.15);">
            </div>
            <div style="display: flex; gap: 0.75rem; justify-content: flex-end; margin-top: 1.25rem;">
                <button type="button" class="btn btn-secondary btn-sm" onclick="closeEvalModal()">Batal</button>
                <button type="submit" class="btn btn-primary btn-sm fw-bold px-3" id="saveEvalBtn">💾 Simpan Evaluasi</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEvalModal() {
    const m = document.getElementById('evalModal');
    if (m) m.style.display = 'flex';
}
function closeEvalModal() {
    const m = document.getElementById('evalModal');
    if (m) m.style.display = 'none';
}
async function submitEvalForm(e) {
    e.preventDefault();
    const btn = document.getElementById('saveEvalBtn');
    btn.disabled = true;
    btn.textContent = 'Menyimpan...';

    const payload = {
        session_id: <?= (int)$session['id'] ?>,
        coach_notes_technique: document.getElementById('modalTechnique').value,
        coach_notes_mental: document.getElementById('modalMental').value,
        coach_recommendation: document.getElementById('modalRecom').value,
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
            alert('✔ Catatan evaluasi pelatih berhasil disimpan!');
            location.reload();
        } else {
            alert('Gagal: ' + (json.message || 'Terjadi kesalahan'));
        }
    } catch (err) {
        alert('Terjadi kesalahan jaringan: ' + err.message);
    } finally {
        btn.disabled = false;
        btn.textContent = '💾 Simpan Evaluasi';
    }
}
</script>

<?php include __DIR__ . '/views/footer.php'; ?>
