<?php
// api/archer_api.php - Child Switcher & Field Device PIN Auth
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/auth.php';

$action = $_GET['action'] ?? '';

if ($action === 'switch_child') {
    $childId = (int)($_POST['child_id'] ?? 0);
    if ($childId <= 0) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'ID anak tidak valid']);
        exit;
    }

    if (Auth::switchChild($childId, $pdo)) {
        echo json_encode(['status' => 'success', 'message' => 'Profil anak berhasil diganti', 'child_id' => $childId]);
    } else {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Anda tidak memiliki hak akses ke profil anak ini']);
    }
    exit;
}

if ($action === 'pin_login') {
    $pin = trim($_POST['pin'] ?? '');
    if (strlen($pin) < 4) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'PIN minimal 4-6 digit']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM archery_archers WHERE access_pin = :pin AND is_active = 1 LIMIT 1");
        $stmt->execute([':pin' => $pin]);
        $archer = $stmt->fetch();

        if ($archer) {
            $_SESSION['archer_device_id'] = $archer['id'];
            $_SESSION['role'] = 'archer';
            $_SESSION['full_name'] = $archer['full_name'];
            $_SESSION['active_child_id'] = $archer['id'];
            echo json_encode([
                'status' => 'success',
                'message' => 'Login Perangkat Lapangan Berhasil!',
                'redirect' => 'plotter.php'
            ]);
        } else {
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'PIN Akses Lapangan tidak cocok atau atlet belum terdaftar']);
        }
    } catch (Exception $e) {
        error_log('Error pin_login: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Terjadi gangguan server']);
    }
    exit;
}

http_response_code(400);
echo json_encode(['status' => 'error', 'message' => 'Action tidak dikenali']);
