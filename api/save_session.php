<?php
// api/save_session.php - Finalisasi Sesi & Simpan Komentar Evaluasi Coach
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

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data || !isset($data['session_id'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Session ID diperlukan']);
    exit;
}

$sessionId = (int)$data['session_id'];
$notesTechnique = htmlspecialchars(trim($data['coach_notes_technique'] ?? ''), ENT_QUOTES, 'UTF-8');
$notesMental = htmlspecialchars(trim($data['coach_notes_mental'] ?? ''), ENT_QUOTES, 'UTF-8');
$recommendation = htmlspecialchars(trim($data['coach_recommendation'] ?? ''), ENT_QUOTES, 'UTF-8');
$status = ($data['mark_completed'] ?? false) ? 'completed' : 'in_progress';

try {
    $stmt = $pdo->prepare("
        UPDATE archery_sessions SET
            coach_notes_technique = :tech,
            coach_notes_mental = :mental,
            coach_recommendation = :recom,
            status = :status,
            end_time = CURTIME()
        WHERE id = :id
    ");
    $stmt->execute([
        ':tech' => $notesTechnique,
        ':mental' => $notesMental,
        ':recom' => $recommendation,
        ':status' => $status,
        ':id' => $sessionId
    ]);

    // Update Personal Best jika rekor baru
    $stmtCheckPB = $pdo->prepare("
        SELECT s.archer_id, s.total_score, a.personal_best_score 
        FROM archery_sessions s 
        JOIN archery_archers a ON a.id = s.archer_id 
        WHERE s.id = :id
    ");
    $stmtCheckPB->execute([':id' => $sessionId]);
    $row = $stmtCheckPB->fetch();

    if ($row && $row['total_score'] > $row['personal_best_score']) {
        $stmtUpdatePB = $pdo->prepare("UPDATE archery_archers SET personal_best_score = :score WHERE id = :id");
        $stmtUpdatePB->execute([
            ':score' => $row['total_score'],
            ':id' => $row['archer_id']
        ]);
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'Evaluasi coach dan sesi latihan berhasil disimpan',
        'is_new_pb' => ($row && $row['total_score'] > $row['personal_best_score'])
    ]);

} catch (Exception $e) {
    error_log('Error save_session.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Gagal memperbarui sesi']);
}
