<?php
// share_card.php - Generator Kartu Medsos Atlet (Ala Roti Bakar)
require_once __DIR__ . '/config/auth.php';
Auth::requireAuth();

$sessionId = (int)($_GET['session_id'] ?? 0);
$currentArcher = Auth::currentArcher($pdo);

if (!$currentArcher) {
    die("Atlet tidak ditemukan.");
}

$archerId = (int)$currentArcher['id'];

if ($sessionId <= 0) {
    // Ambil sesi terbaru atlet ini
    $stmtFind = $pdo->prepare("SELECT id FROM archery_sessions WHERE archer_id = :aid ORDER BY session_date DESC, id DESC LIMIT 1");
    $stmtFind->execute([':aid' => $archerId]);
    $sessionId = (int)$stmtFind->fetchColumn();
}

$stmt = $pdo->prepare("
    SELECT s.*, a.full_name AS archer_name, cl.club_name
    FROM archery_sessions s
    JOIN archery_archers a ON a.id = s.archer_id
    JOIN archery_clubs cl ON cl.id = s.club_id
    WHERE s.id = :id
");
$stmt->execute([':id' => $sessionId]);
$session = $stmt->fetch();

if (!$session) {
    die("Sesi latihan tidak ditemukan.");
}

// Ambil Titik-Titik Panah untuk diplot di kartu medsos
$stmtShots = $pdo->prepare("SELECT x_coord AS x, y_coord AS y, score, is_x FROM archery_shots WHERE session_id = :sid");
$stmtShots->execute([':sid' => $sessionId]);
$shots = $stmtShots->fetchAll();

$cardPayload = [
    'clubName' => $session['club_name'] ?? 'AN-NAHL ARCHERY CLUB',
    'archerName' => $session['archer_name'],
    'bowCategory' => $session['bow_category'],
    'distance' => $session['distance_meter'] . ' Meter',
    'sessionDate' => date('d M Y', strtotime($session['session_date'])),
    'totalScore' => (int)$session['total_score'],
    'maxPossible' => (int)$session['max_possible_score'],
    'accuracyPct' => (float)$session['accuracy_pct'],
    'tenCount' => (int)$session['ten_count'],
    'xCount' => (int)$session['x_count'],
    'shots' => array_map(function($s) {
        return [
            'x' => (float)$s['x'],
            'y' => (float)$s['y'],
            'score' => (int)$s['score'],
            'is_x' => (int)$s['is_x']
        ];
    }, $shots)
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kartu Medsos Atlet - <?= htmlspecialchars($session['archer_name']) ?></title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
    <style>
        .format-tabs {
            display: inline-flex;
            background: rgba(0,0,0,0.3);
            border-radius: var(--radius-sm);
            padding: 4px;
            gap: 4px;
            margin-bottom: 1.5rem;
        }
        .format-btn {
            background: transparent;
            border: none;
            color: var(--text-muted);
            padding: 0.5rem 1.25rem;
            border-radius: 6px;
            font-family: 'Outfit', sans-serif;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
        }
        .format-btn.active {
            background: var(--primary);
            color: #000;
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/views/navbar.php'; ?>

<main class="container">
    <div style="text-align: center; margin-bottom: 1.5rem;">
        <h1 style="font-size: 1.8rem; margin-bottom: 0.35rem;">📱 KARTU SHARE MEDIA SOSIAL</h1>
        <p style="color: var(--text-muted); font-size: 0.9rem;">
            Template infografis atlet profesional ala Roti Bakar dengan Logo Klub, Visual Target, dan Statistik Resmi.
        </p>

        <!-- PILIHAN FORMAT: STORY 9:16 VS FEED 1:1 -->
        <div class="format-tabs" style="margin-top: 1rem;">
            <button type="button" class="format-btn active" id="btnStory" onclick="changeCardFormat('story')">
                📱 Story / Status WA (9:16)
            </button>
            <button type="button" class="format-btn" id="btnFeed" onclick="changeCardFormat('feed')">
                🖼️ Feed Instagram (1:1)
            </button>
        </div>
    </div>

    <div class="social-card-preview-wrap">
        <!-- CANVAS SHARE CARD YANG BERSIFAT LIVE GENERATION -->
        <canvas id="socialCardCanvas"></canvas>

        <!-- TOMBOL AKSI CEPAT -->
        <div style="display: flex; gap: 0.75rem; flex-wrap: wrap; justify-content: center; width: 100%; max-width: 400px;">
            <button type="button" class="btn btn-primary btn-block" onclick="cardGen.downloadImage('Scorecard_<?= preg_replace('/\s+/', '_', $session['archer_name']) ?>.png')">
                ⬇️ Download Gambar PNG (HD)
            </button>
            <button type="button" class="btn btn-secondary btn-block" style="color: #22c55e;" onclick="cardGen.shareWhatsApp()">
                💬 Bagikan Langsung ke WhatsApp
            </button>
            <a href="report_session.php?session_id=<?= $session['id'] ?>" class="btn btn-secondary btn-block">
                📜 Lihat Lembar Rapot Resmi
            </a>
        </div>
    </div>
</main>

<script src="assets/js/social_card.js"></script>
<script>
const cardData = <?= json_encode($cardPayload) ?>;
const cardGen = new SocialCardGenerator('socialCardCanvas', cardData);

function changeCardFormat(fmt) {
    document.querySelectorAll('.format-btn').forEach(b => b.classList.remove('active'));
    if (fmt === 'story') {
        document.getElementById('btnStory').classList.add('active');
    } else {
        document.getElementById('btnFeed').classList.add('active');
    }
    cardGen.setFormat(fmt);
}
</script>

<?php include __DIR__ . '/views/footer.php'; ?>
