<?php
// api/create_session.php - API Pembuatan Sesi Baru dengan Kalibrasi Otomatis & Smart Lock Ujian
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$archerId = (int)($_POST['archer_id'] ?? 0);
$coachId = (int)($_POST['coach_id'] ?? 2);
$sessionType = ($_POST['session_type'] ?? 'practice') === 'exam' ? 'exam' : 'practice';
$totalEnds = max(1, min(24, (int)($_POST['total_ends'] ?? 6)));
$arrowsPerEnd = max(1, min(12, (int)($_POST['arrows_per_end'] ?? 5)));
$targetId = max(1, (int)($_POST['target_id'] ?? 4));
$maxPossibleScore = $totalEnds * $arrowsPerEnd * 10;
$isHtmlRedirect = !empty($_POST['redirect_html']);

try {
    // 1. Ambil Data Atlet
    $stmtArch = $pdo->prepare("SELECT * FROM archery_archers WHERE id = :id AND is_active = 1 LIMIT 1");
    $stmtArch->execute([':id' => $archerId]);
    $archer = $stmtArch->fetch();

    if (!$archer) {
        if ($isHtmlRedirect) {
            die("<div style='padding:40px;text-align:center;'>Data atlet tidak ditemukan. <a href='../app_dashboard.php'>Kembali</a></div>");
        }
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Data atlet tidak ditemukan']);
        exit;
    }

    $distanceMeter = (int)$archer['current_distance_meter'];

    // 2. Jika tipe ujian, cek apakah berhak
    $examStatus = 'not_exam';
    if ($sessionType === 'exam') {
        $stmtDist = $pdo->prepare("SELECT min_sessions_required FROM archery_distances WHERE distance_meter = :dm LIMIT 1");
        $stmtDist->execute([':dm' => $distanceMeter]);
        $minSessions = (int)($stmtDist->fetchColumn() ?: 12);

        $isEligible = ($archer['total_sessions_count'] >= $minSessions) || !empty($archer['can_exam_promotion']);
        if (!$isEligible) {
            if ($isHtmlRedirect) {
                die("<div style='padding:40px;text-align:center;'>Belum memenuhi syarat ujian. <a href='../app_dashboard.php'>Kembali</a></div>");
            }
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Atlet belum memenuhi syarat jam terbang atau izin rekomendasi coach untuk mengikuti ujian']);
            exit;
        }
        $examStatus = 'pending_approval';
    }

    // 3. Insert Sesi Baru
    $stmtInsert = $pdo->prepare("
        INSERT INTO archery_sessions 
        (club_id, archer_id, coach_id, session_date, start_time, distance_meter, bow_category, target_id, session_type, exam_status, total_ends, arrows_per_end, max_possible_score, status)
        VALUES (:cid, :aid, :coach, CURDATE(), CURTIME(), :dm, :bow, :target, :stype, :estatus, :ends, :arrows, :max_score, 'in_progress')
    ");
    $stmtInsert->execute([
        ':cid' => $archer['club_id'] ?? 1,
        ':aid' => $archerId,
        ':coach' => $coachId,
        ':dm' => $distanceMeter,
        ':bow' => $archer['bow_category'] ?? 'Standard Bow',
        ':target' => $targetId,
        ':stype' => $sessionType,
        ':estatus' => $examStatus,
        ':ends' => $totalEnds,
        ':arrows' => $arrowsPerEnd,
        ':max_score' => $maxPossibleScore
    ]);
    $newSessionId = (int)$pdo->lastInsertId();

    // 4. Update akumulasi sesi atlet
    $stmtInc = $pdo->prepare("UPDATE archery_archers SET total_sessions_count = total_sessions_count + 1 WHERE id = :id");
    $stmtInc->execute([':id' => $archerId]);

    if ($isHtmlRedirect) {
        header("Location: ../plotter.php?session_id={$newSessionId}");
        exit;
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'Sesi tembakan berhasil disiapkan',
        'session_id' => $newSessionId
    ]);

} catch (Exception $e) {
    error_log('Error create_session.php: ' . $e->getMessage());
    if ($isHtmlRedirect) {
        die("<div style='padding:40px;text-align:center;'>Gagal membuat sesi baru: " . htmlspecialchars($e->getMessage()) . " <a href='../app_dashboard.php'>Kembali</a></div>");
    }
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Gagal membuat sesi baru']);
}
