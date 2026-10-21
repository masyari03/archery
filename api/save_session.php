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

// Cek apakah payload menyertakan data evaluasi coach
$hasCoachNotes = isset($data['coach_notes_technique']) || isset($data['coach_notes_mental']) || isset($data['coach_recommendation']) || isset($data['evaluation_checklist_json']);
$status = ($data['mark_completed'] ?? false) ? 'completed' : 'in_progress';

try {
    if ($hasCoachNotes) {
        $notesTechnique = htmlspecialchars(trim($data['coach_notes_technique'] ?? ''), ENT_QUOTES, 'UTF-8');
        $notesMental = htmlspecialchars(trim($data['coach_notes_mental'] ?? ''), ENT_QUOTES, 'UTF-8');
        $recommendation = htmlspecialchars(trim($data['coach_recommendation'] ?? ''), ENT_QUOTES, 'UTF-8');
        
        $checklistJson = null;
        if (isset($data['evaluation_checklist_json'])) {
            $checklistJson = is_string($data['evaluation_checklist_json']) 
                ? $data['evaluation_checklist_json'] 
                : json_encode($data['evaluation_checklist_json'], JSON_UNESCAPED_UNICODE);
        }
        $checklistScore = isset($data['evaluation_checklist_score']) ? (int)$data['evaluation_checklist_score'] : null;
        $checklistMax = isset($data['evaluation_max_score']) ? (int)$data['evaluation_max_score'] : null;

        $stmt = $pdo->prepare("
            UPDATE archery_sessions SET
                coach_notes_technique = :tech,
                coach_notes_mental = :mental,
                coach_recommendation = :recom,
                evaluation_checklist_json = :chk,
                evaluation_checklist_score = :chk_sc,
                evaluation_max_score = :chk_mx,
                status = :status,
                end_time = CURTIME()
            WHERE id = :id
        ");
        $stmt->execute([
            ':tech' => $notesTechnique,
            ':mental' => $notesMental,
            ':recom' => $recommendation,
            ':chk' => $checklistJson,
            ':chk_sc' => $checklistScore,
            ':chk_mx' => $checklistMax,
            ':status' => $status,
            ':id' => $sessionId
        ]);
    } else {
        // Hanya finalisasi status selesai tanpa menimpa catatan coach yang sudah ada
        $stmt = $pdo->prepare("
            UPDATE archery_sessions SET
                status = :status,
                end_time = CURTIME()
            WHERE id = :id
        ");
        $stmt->execute([
            ':status' => $status,
            ':id' => $sessionId
        ]);
    }

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
