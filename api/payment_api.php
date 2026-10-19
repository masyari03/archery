<?php
// api/payment_api.php - Payment Gateway & Bill Simulation
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/auth.php';

if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? '';

if ($action === 'pay_simulate') {
    $billId = (int)($_POST['bill_id'] ?? 0);
    $channel = htmlspecialchars($_POST['channel'] ?? 'QRIS Instant', ENT_QUOTES, 'UTF-8');

    try {
        $stmt = $pdo->prepare("SELECT * FROM archery_bills WHERE id = :id");
        $stmt->execute([':id' => $billId]);
        $bill = $stmt->fetch();

        if (!$bill) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Tagihan tidak ditemukan']);
            exit;
        }

        if ($bill['status'] === 'paid') {
            echo json_encode(['status' => 'info', 'message' => 'Tagihan sudah lunas']);
            exit;
        }

        $trxRef = 'TRX-' . strtoupper(bin2hex(random_bytes(6)));
        $stmtPay = $pdo->prepare("
            UPDATE archery_bills SET 
                status = 'paid', 
                payment_channel = :channel, 
                payment_ref = :ref, 
                paid_at = NOW() 
            WHERE id = :id
        ");
        $stmtPay->execute([
            ':channel' => $channel,
            ':ref' => $trxRef,
            ':id' => $billId
        ]);

        // Kirim Notifikasi WhatsApp Otomatis ke Orang Tua via Fonnte
        require_once __DIR__ . '/../includes/fonnte.php';
        try {
            FonnteWA::sendPaymentNotification($pdo, (int)($bill['club_id'] ?? 1), $billId, $trxRef);
        } catch (Exception $waEx) {
            error_log("WA Payment Error: " . $waEx->getMessage());
        }

        echo json_encode([
            'status' => 'success',
            'message' => 'Pembayaran Berhasil Diverifikasi via ' . $channel,
            'payment_ref' => $trxRef
        ]);

    } catch (Exception $e) {
        error_log('Error pay_simulate: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Gagal memproses pembayaran']);
    }
    exit;
}

http_response_code(400);
echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
