<?php
// report_monthly.php - Rekap Perkembangan Bulanan Atlet
require_once __DIR__ . '/config/auth.php';
Auth::requireAuth();

$currentUser = Auth::user();
$currentArcher = Auth::currentArcher($pdo);

if (!$currentArcher) {
    die("Atlet tidak ditemukan.");
}

$archerId = (int)$currentArcher['id'];
$selectedMonth = $_GET['month'] ?? date('Y-m');

// Ambil Informasi Klub
$stmtClub = $pdo->prepare("SELECT * FROM archery_clubs WHERE id = :cid LIMIT 1");
$stmtClub->execute([':cid' => (int)($currentArcher['club_id'] ?? 1)]);
$club = $stmtClub->fetch();
$clubName = $club['club_name'] ?? 'AN-NAHL ARCHERY CLUB';
$chairmanName = $club['chairman_name'] ?? "Ustadz Asy'ari, S.Pd.";

// Ambil Seluruh Sesi dalam Bulan Ini
$stmt = $pdo->prepare("
    SELECT s.*, c.full_name AS coach_name
    FROM archery_sessions s
    LEFT JOIN archery_users c ON c.id = s.coach_id
    WHERE s.archer_id = :aid AND s.session_date LIKE :m
    ORDER BY s.session_date ASC
");
$stmt->execute([':aid' => $archerId, ':m' => "{$selectedMonth}%"]);
$monthSessions = $stmt->fetchAll();

// Hitung Statistik Bulanan
$monthTotalSessions = count($monthSessions);
$monthTotalScore = 0;
$monthTotalPossible = 0;
$monthTotalArrows = 0;
$monthMaxScore = 0;
$monthTotal10s = 0;
$monthTotalXs = 0;

foreach ($monthSessions as $ms) {
    $monthTotalScore += (int)$ms['total_score'];
    $monthTotalPossible += (int)$ms['max_possible_score'];
    $monthTotalArrows += ((int)$ms['total_ends'] * (int)$ms['arrows_per_end']);
    $monthTotal10s += (int)$ms['ten_count'];
    $monthTotalXs += (int)$ms['x_count'];
    if ((int)$ms['total_score'] > $monthMaxScore) {
        $monthMaxScore = (int)$ms['total_score'];
    }
}

$monthAvgScore = $monthTotalSessions > 0 ? round($monthTotalScore / $monthTotalSessions, 1) : 0;
$monthAccuracy = $monthTotalPossible > 0 ? round(($monthTotalScore / $monthTotalPossible) * 100, 1) : 0.0;
$pageTitle = "Rekap Perkembangan Bulanan - " . htmlspecialchars($currentArcher['full_name']);
require_once __DIR__ . '/views/header.php';
?>
<link rel="stylesheet" href="assets/css/print_report.css">

<!-- FILTER TOOLBAR (NO-PRINT) -->
<div class="no-print" style="max-width: 860px; margin: 1.5rem auto 0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; padding: 0 1rem;">
    <form method="GET" style="display: flex; align-items: center; gap: 0.5rem;">
        <label style="color: var(--text-muted); font-size: 0.85rem; font-weight: 600;">PILIH BULAN:</label>
        <input type="month" name="month" class="form-control" value="<?= htmlspecialchars($selectedMonth) ?>" onchange="this.form.submit()" style="padding: 0.4rem 0.8rem; width: auto;">
    </form>

    <div style="display: flex; gap: 0.5rem;">
        <?php
        $mRole = $_SESSION['role'] ?? 'parent';
        $dashMonthly = in_array($mRole, ['coach', 'super_admin']) ? 'coach_dashboard.php' : 'app_dashboard.php';
        if ($mRole === 'master_admin') $dashMonthly = 'master_tenants.php';
        ?>
        <a href="<?= $dashMonthly ?>" class="btn btn-secondary btn-sm">⬅ Dashboard</a>
        <button type="button" class="btn btn-primary btn-sm" onclick="window.print()">🖨️ Cetak Rekap Bulanan</button>
    </div>
</div>

<div class="report-card-page" style="max-width: 860px;">
    <!-- KOP RESMI -->
    <div class="report-header">
        <div class="report-logo">🎯</div>
        <div class="report-title-box">
            <h1><?= htmlspecialchars($clubName) ?></h1>
            <p>LAPORAN REKAP PERKEMBANGAN & EVALUASI BULANAN ATLET</p>
            <p>Periode Bulan: <b><?= date('F Y', strtotime($selectedMonth . '-01')) ?></b></p>
        </div>
    </div>

    <!-- IDENTITAS ATLET -->
    <div class="identity-grid">
        <div class="identity-row">
            <span class="identity-label">Nama Atlet:</span>
            <span class="identity-val"><?= htmlspecialchars($currentArcher['full_name']) ?></span>
        </div>
        <div class="identity-row">
            <span class="identity-label">Nomor Induk (NIS):</span>
            <span class="identity-val"><?= htmlspecialchars($currentArcher['nis']) ?></span>
        </div>
        <div class="identity-row">
            <span class="identity-label">Kategori Busur:</span>
            <span class="identity-val"><?= htmlspecialchars($currentArcher['bow_category']) ?> (<?= $currentArcher['draw_weight_lbs'] ?> lbs)</span>
        </div>
        <div class="identity-row">
            <span class="identity-label">Grade / Level:</span>
            <span class="identity-val"><?= htmlspecialchars($currentArcher['current_level']) ?></span>
        </div>
    </div>

    <!-- 4 KARTU STATISTIK BULANAN -->
    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 0.75rem; margin-bottom: 1.5rem; text-align: center;">
        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 0.75rem;">
            <div style="font-size: 0.75rem; color: #64748b;">TOTAL SESI</div>
            <div style="font-size: 1.35rem; font-weight: 800; color: #0284c7;"><?= $monthTotalSessions ?></div>
            <div style="font-size: 0.7rem; color: #94a3b8;">Pertemuan</div>
        </div>
        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 0.75rem;">
            <div style="font-size: 0.75rem; color: #64748b;">TOTAL PANAH</div>
            <div style="font-size: 1.35rem; font-weight: 800; color: #0f172a;"><?= $monthTotalArrows ?></div>
            <div style="font-size: 0.7rem; color: #94a3b8;">Volume Tembak</div>
        </div>
        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 0.75rem;">
            <div style="font-size: 0.75rem; color: #64748b;">SKOR TERTINGGI</div>
            <div style="font-size: 1.35rem; font-weight: 800; color: #d97706;"><?= $monthMaxScore ?></div>
            <div style="font-size: 0.7rem; color: #94a3b8;">Bulan Ini</div>
        </div>
        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 0.75rem;">
            <div style="font-size: 0.75rem; color: #64748b;">AKURASI BULANAN</div>
            <div style="font-size: 1.35rem; font-weight: 800; color: #059669;"><?= $monthAccuracy ?>%</div>
            <div style="font-size: 0.7rem; color: #94a3b8;">Rata-rata Akurat</div>
        </div>
    </div>

    <!-- TABEL LOG SESI BULANAN & EVALUASI COACH -->
    <h3 style="font-size: 1rem; color: #1e293b; margin-bottom: 0.5rem; text-transform: uppercase;">
        Rincian Pertemuan & Catatan Kemajuan Atlet:
    </h3>

    <?php if (empty($monthSessions)): ?>
        <p style="text-align: center; color: #64748b; padding: 2rem; border: 1px dashed #cbd5e1; border-radius: 6px;">
            Tidak ada catatan latihan pada bulan <?= date('F Y', strtotime($selectedMonth . '-01')) ?>.
        </p>
    <?php else: ?>
        <table class="print-score-table">
            <thead>
                <tr>
                    <th style="width: 35px;">No</th>
                    <th style="width: 100px;">Tanggal</th>
                    <th style="width: 75px;">Jarak</th>
                    <th style="width: 80px;">Skor</th>
                    <th style="width: 70px;">Akurasi</th>
                    <th>Catatan Evaluasi Pelatih (Teknik & Form)</th>
                </tr>
            </thead>
            <tbody>
                <?php $no = 1; foreach ($monthSessions as $ms): ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td><b><?= date('d/m/Y', strtotime($ms['session_date'])) ?></b></td>
                        <td><?= $ms['distance_meter'] ?>m</td>
                        <td style="font-weight: 800; color: #b45309;"><?= $ms['total_score'] ?> / <?= $ms['max_possible_score'] ?></td>
                        <td style="font-weight: 700; color: #047857;"><?= $ms['accuracy_pct'] ?>%</td>
                        <td style="text-align: left; font-size: 0.8rem;">
                            <?= !empty($ms['coach_notes_technique']) ? htmlspecialchars($ms['coach_notes_technique']) : 'Latihan berjalan lancar, form stabil.' ?>
                            <?php if (!empty($ms['coach_recommendation'])): ?>
                                <br><small style="color: #0284c7;"><b>PR:</b> <?= htmlspecialchars($ms['coach_recommendation']) ?></small>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <!-- TANDA TANGAN RESMI REKAP BULANAN -->
    <div class="signatures-grid" style="margin-top: 3rem;">
        <div class="sig-box">
            <div class="sig-role">
                Mengetahui,<br>
                <b>Pelatih Kepala (Head Coach)</b>
            </div>
            <div class="sig-name">Coach Fadli Archery</div>
            <div class="sig-sub">Divisi Pembinaan Prestasi</div>
        </div>

        <div class="sig-box">
            <div class="sig-role">
                Disahkan oleh,<br>
                <b>Ketua <?= htmlspecialchars($clubName) ?></b>
            </div>
            <div class="sig-name"><?= htmlspecialchars($chairmanName) ?></div>
            <div class="sig-sub">Ketua Perkumpulan</div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/views/footer.php'; ?>
