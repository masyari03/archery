<?php
// api/save_shot.php - API Penyimpanan Seri Tembakan (Atomic Transaction & Validasi Ketat)
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

// Boleh diakses jika user login atau device anak login
if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data || !isset($data['session_id'], $data['end_number'], $data['arrows'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Payload tidak lengkap']);
    exit;
}

$sessionId = (int)$data['session_id'];
$endNumber = (int)$data['end_number'];
$arrows = (array)$data['arrows'];

try {
    $pdo->beginTransaction();

    // 1. Validasi Keberadaan Sesi
    $stmtSess = $pdo->prepare("SELECT * FROM archery_sessions WHERE id = :id FOR UPDATE");
    $stmtSess->execute([':id' => $sessionId]);
    $session = $stmtSess->fetch();

    if (!$session) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Sesi latihan tidak ditemukan']);
        exit;
    }

    // 2. Insert or Update Seri (archery_ends)
    $endScore = 0;
    foreach ($arrows as $arr) {
        $endScore += (int)($arr['score'] ?? 0);
    }

    $stmtEnd = $pdo->prepare("
        INSERT INTO archery_ends (session_id, end_number, end_score)
        VALUES (:sess_id, :end_num, :score)
        ON DUPLICATE KEY UPDATE end_score = VALUES(end_score)
    ");
    $stmtEnd->execute([
        ':sess_id' => $sessionId,
        ':end_num' => $endNumber,
        ':score' => $endScore
    ]);

    // Dapatkan end_id
    $stmtGetEnd = $pdo->prepare("SELECT id FROM archery_ends WHERE session_id = :sess_id AND end_number = :end_num");
    $stmtGetEnd->execute([':sess_id' => $sessionId, ':end_num' => $endNumber]);
    $endId = (int)$stmtGetEnd->fetchColumn();

    // 3. Simpan Detail Tembakan Panah (archery_shots)
    $stmtDelShots = $pdo->prepare("DELETE FROM archery_shots WHERE end_id = :end_id");
    $stmtDelShots->execute([':end_id' => $endId]);

    $stmtInsertShot = $pdo->prepare("
        INSERT INTO archery_shots (end_id, session_id, arrow_number, x_coord, y_coord, radius, score, is_x)
        VALUES (:end_id, :sess_id, :arrow_num, :x, :y, :r, :score, :is_x)
    ");

    foreach ($arrows as $idx => $arr) {
        $arrowNum = $idx + 1;
        $x = (float)($arr['x'] ?? 0.0);
        $y = (float)($arr['y'] ?? 0.0);
        $r = (float)($arr['radius'] ?? sqrt($x*$x + $y*$y));
        $score = (int)($arr['score'] ?? 0);
        $isX = !empty($arr['is_x']) ? 1 : 0;

        $stmtInsertShot->execute([
            ':end_id' => $endId,
            ':sess_id' => $sessionId,
            ':arrow_num' => $arrowNum,
            ':x' => $x,
            ':y' => $y,
            ':r' => $r,
            ':score' => $score,
            ':is_x' => $isX
        ]);
    }

    // 4. Rekalkulasi Grand Total & Statistik Sesi
    $stmtStats = $pdo->prepare("
        SELECT 
            COALESCE(SUM(s.score), 0) AS total_score,
            COUNT(s.id) AS total_arrows,
            SUM(CASE WHEN s.score = 10 THEN 1 ELSE 0 END) AS ten_count,
            SUM(CASE WHEN s.is_x = 1 THEN 1 ELSE 0 END) AS x_count
        FROM archery_shots s
        WHERE s.session_id = :sess_id
    ");
    $stmtStats->execute([':sess_id' => $sessionId]);
    $stats = $stmtStats->fetch();

    $totScore = (int)$stats['total_score'];
    $totArrows = (int)$stats['total_arrows'];
    $tenCount = (int)$stats['ten_count'];
    $xCount = (int)$stats['x_count'];

    $maxPossible = (int)$session['max_possible_score'];
    $accuracyPct = $maxPossible > 0 ? round(($totScore / $maxPossible) * 100, 2) : 0.00;
    $avgArrow = $totArrows > 0 ? round($totScore / $totArrows, 2) : 0.00;

    $stmtUpdateSess = $pdo->prepare("
        UPDATE archery_sessions SET
            total_score = :tot_score,
            ten_count = :ten_cnt,
            x_count = :x_cnt,
            accuracy_pct = :acc_pct,
            avg_arrow_score = :avg_arrow
        WHERE id = :sess_id
    ");
    $stmtUpdateSess->execute([
        ':tot_score' => $totScore,
        ':ten_cnt' => $tenCount,
        ':x_cnt' => $xCount,
        ':acc_pct' => $accuracyPct,
        ':avg_arrow' => $avgArrow,
        ':sess_id' => $sessionId
    ]);

    $pdo->commit();

    echo json_encode([
        'status' => 'success',
        'message' => 'Seri tembakan berhasil disimpan',
        'data' => [
            'end_number' => $endNumber,
            'end_score' => $endScore,
            'session_total_score' => $totScore,
            'accuracy_pct' => $accuracyPct,
            'avg_arrow_score' => $avgArrow,
            'ten_count' => $tenCount,
            'x_count' => $xCount
        ]
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Error save_shot.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan tembakan ke database']);
}
