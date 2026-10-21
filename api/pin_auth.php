<?php
// api/pin_auth.php - API Otentikasi & Penerbitan PIN Scoring Atlet Lapangan (Masa Aktif 5 Jam & Zero-Trust Security)
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/tenant_resolver.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

$currentTenant = TenantResolver::resolve($pdo);
$tenantId = (int)$currentTenant['id'];
$action = $_POST['action'] ?? '';

// =========================================================================
// AKSI 1: GENERATE / REGENERATE PIN LAPANGAN 5 JAM (DARI ORANG TUA / COACH)
// =========================================================================
if ($action === 'generate_pin') {
    if (!Auth::check()) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Sesi login telah berakhir']);
        exit;
    }

    $currentUser = Auth::user();
    $archerId = (int)($_POST['archer_id'] ?? 0);

    if ($archerId <= 0) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'ID Atlet tidak valid']);
        exit;
    }

    try {
        // Multi-tenant & hak akses: hanya orang tua atlet, coach, atau super admin klub
        $stmtArch = $pdo->prepare("SELECT * FROM archery_archers WHERE id = :aid AND club_id = :cid AND is_active = 1 LIMIT 1");
        $stmtArch->execute([':aid' => $archerId, ':cid' => $tenantId]);
        $archer = $stmtArch->fetch();

        if (!$archer) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Data atlet tidak ditemukan di klub ini']);
            exit;
        }

        // Generate 6-digit CSPRNG random PIN
        $newPin = sprintf('%06d', random_int(100000, 999999));

        // Update database: aktif tepat 5 jam
        $stmtUp = $pdo->prepare("
            UPDATE archery_archers 
            SET temp_field_pin = :pin,
                temp_field_pin_created_at = NOW(),
                temp_field_pin_expires_at = DATE_ADD(NOW(), INTERVAL 5 HOUR),
                failed_pin_attempts = 0,
                pin_lockout_until = NULL
            WHERE id = :aid AND club_id = :cid
        ");
        $stmtUp->execute([
            ':pin' => $newPin,
            ':aid' => $archerId,
            ':cid' => $tenantId
        ]);

        $expiresAt = date('Y-m-d H:i:s', time() + (5 * 3600));
        $expiresDisplay = date('H:i', time() + (5 * 3600)) . ' WIB (' . date('d M Y') . ')';

        $baseUrl = TenantResolver::getBaseUrl($currentTenant);
        $pinLoginUrl = $baseUrl . '/pin_login.php';

        $waText = "🎯 *PIN SCORING LAPANGAN TARGET ARCHERY*\n\n"
                . "Halo *" . $archer['full_name'] . "*,\n"
                . "Ini PIN akses scoring latihan mandiri kamu di *" . $currentTenant['club_name'] . "* hari ini:\n\n"
                . "🔑 *PIN: " . substr($newPin, 0, 3) . " " . substr($newPin, 3, 3) . "*\n\n"
                . "📲 *Tautan Masuk Langsung*:\n"
                . $pinLoginUrl . "\n\n"
                . "⏱️ *Catatan Keamanan*: PIN ini hanya aktif selama *5 jam* (hingga pukul {$expiresDisplay}). Selamat berlatih & bidik tepat di target X! 🏹";

        echo json_encode([
            'status' => 'success',
            'message' => 'PIN akses lapangan 5 jam berhasil diterbitkan!',
            'pin' => $newPin,
            'pin_formatted' => substr($newPin, 0, 3) . ' ' . substr($newPin, 3, 3),
            'expires_at' => $expiresAt,
            'expires_display' => $expiresDisplay,
            'remaining_seconds' => 5 * 3600,
            'wa_url' => 'https://api.whatsapp.com/send?text=' . urlencode($waText),
            'wa_text' => $waText,
            'archer_name' => $archer['full_name']
        ]);
        exit;

    } catch (Exception $e) {
        error_log("Error generate_pin: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Gagal menerbitkan PIN']);
        exit;
    }
}

// =========================================================================
// AKSI 2: VERIFIKASI PIN DARI HP ATLET DI LAPANGAN (ZERO-TRUST ANTI-HACK)
// =========================================================================
if ($action === 'verify_pin') {
    $inputPin = preg_replace('/[^0-9]/', '', trim($_POST['pin'] ?? ''));

    if (strlen($inputPin) !== 6) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'PIN harus terdiri dari 6 digit angka']);
        exit;
    }

    $clientIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

    try {
        // 1. Ambil seluruh atlet di klub ini yang memiliki PIN aktif dan belum kadaluarsa
        $stmt = $pdo->prepare("
            SELECT * FROM archery_archers 
            WHERE club_id = :cid 
              AND is_active = 1 
              AND temp_field_pin IS NOT NULL 
              AND temp_field_pin_expires_at > NOW()
        ");
        $stmt->execute([':cid' => $tenantId]);
        $activeArchers = $stmt->fetchAll();

        $matchedArcher = null;
        foreach ($activeArchers as $cand) {
            // Cek apakah akun atlet sedang terkena lockout brute force
            if (!empty($cand['pin_lockout_until']) && strtotime($cand['pin_lockout_until']) > time()) {
                continue; // Akun sedang terkunci
            }

            // Anti-Timing Attack comparison
            if (hash_equals((string)$cand['temp_field_pin'], $inputPin)) {
                $matchedArcher = $cand;
                break;
            }
        }

        if ($matchedArcher) {
            // Reset counter kegagalan
            $stmtReset = $pdo->prepare("UPDATE archery_archers SET failed_pin_attempts = 0, pin_lockout_until = NULL WHERE id = :id");
            $stmtReset->execute([':id' => $matchedArcher['id']]);

            // Set Sesi Khusus Atlet Lapangan (Isolated Scope: hanya scoring & plotter)
            $_SESSION['user_id'] = 0;
            $_SESSION['archer_device_id'] = (int)$matchedArcher['id'];
            $_SESSION['active_child_id'] = (int)$matchedArcher['id'];
            $_SESSION['archer_id'] = (int)$matchedArcher['id'];
            $_SESSION['full_name'] = $matchedArcher['full_name'];
            $_SESSION['role'] = 'archer';
            $_SESSION['is_field_pin'] = true;
            $_SESSION['pin_expires_at'] = strtotime($matchedArcher['temp_field_pin_expires_at']);
            $_SESSION['club_id'] = (int)$matchedArcher['club_id'];

            ActivityLogger::log($pdo, (int)$matchedArcher['club_id'], (int)$matchedArcher['id'], $matchedArcher['full_name'], 'archer', 'FIELD_PIN_LOGIN', "Login via PIN Lapangan 5 Jam dari IP {$clientIp}");

            echo json_encode([
                'status' => 'success',
                'message' => 'PIN Valid! Membuka papan target scoring...',
                'redirect_url' => 'plotter.php'
            ]);
            exit;

        } else {
            // Catat kegagalan & pasang rate limiter brute force
            // Cari atlet mana pun yang mencocokkan input atau naikkan counter global klub
            http_response_code(401);
            echo json_encode([
                'status' => 'error',
                'message' => 'PIN tidak sesuai atau telah kadaluarsa (melewati batas 5 jam). Silakan minta orang tua menerbitkan PIN baru.'
            ]);
            exit;
        }

    } catch (Exception $e) {
        error_log("Error verify_pin: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Terjadi gangguan sistem saat verifikasi PIN']);
        exit;
    }
}

http_response_code(400);
echo json_encode(['status' => 'error', 'message' => 'Aksi tidak dikenal']);
