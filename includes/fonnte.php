<?php
// includes/fonnte.php - Helper Integrasi WhatsApp Gateway Fonnte dengan Template Otomatis
require_once __DIR__ . '/../config/database.php';

class FonnteWA {
    /**
     * Kirim Pesan WhatsApp via Fonnte API
     */
    public static function send(string $phone, string $message, int $clubId = 1): array {
        global $pdo;

        // Ambil token dan status Fonnte klub
        $stmt = $pdo->prepare("SELECT fonnte_token, fonnte_enabled, club_name FROM archery_clubs WHERE id = :cid LIMIT 1");
        $stmt->execute([':cid' => $clubId]);
        $club = $stmt->fetch();

        if (!$club || empty($club['fonnte_enabled']) || empty($club['fonnte_token'])) {
            return ['status' => false, 'message' => 'Fonnte WA dinonaktifkan atau token belum diisi.'];
        }

        // Format nomor HP ke 08 / 62
        $target = preg_replace('/[^0-9]/', '', $phone);
        if (substr($target, 0, 1) === '0') {
            $target = '62' . substr($target, 1);
        }

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://api.fonnte.com/send',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 5, // Timeout 5s anti hang
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => [
                'target' => $target,
                'message' => $message,
                'countryCode' => '62',
            ],
            CURLOPT_HTTPHEADER => [
                'Authorization: ' . trim($club['fonnte_token'])
            ],
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);

        if ($err) {
            error_log("Fonnte WA cURL Error: " . $err);
            return ['status' => false, 'message' => $err];
        }

        return ['status' => true, 'response' => json_decode($response, true)];
    }

    /**
     * Kirim Pesan Bebas / Kustom (Uji Coba Fonnte)
     */
    public static function sendCustomMessage($pdo, int $clubId, string $phone, string $message): array {
        return self::send($phone, $message, $clubId);
    }

    /**
     * Kirim Notifikasi Pembayaran Lunas
     */
    public static function sendPaymentNotification($pdoOrData, $clubIdOrBillId = 1, $billId = null, $trxRef = null): array {
        global $pdo;
        $db = ($pdoOrData instanceof PDO) ? $pdoOrData : $pdo;

        if (is_array($pdoOrData)) {
            $billData = $pdoOrData;
            $clubId = is_int($clubIdOrBillId) ? $clubIdOrBillId : 1;
        } else {
            $clubId = (int)$clubIdOrBillId;
            $bid = (int)$billId;
            // Ambil data bill
            $stmt = $db->prepare("
                SELECT b.*, a.full_name AS archer_name, a.parent_phone, u.full_name AS parent_name
                FROM archery_bills b
                JOIN archery_archers a ON a.id = b.archer_id
                LEFT JOIN archery_users u ON u.id = a.parent_user_id
                WHERE b.id = :id
            ");
            $stmt->execute([':id' => $bid]);
            $bRow = $stmt->fetch();
            if (!$bRow) return ['status' => false, 'message' => 'Data tagihan tidak ditemukan'];

            $billData = [
                'parent_name' => $bRow['parent_name'] ?: 'Orang Tua ' . $bRow['archer_name'],
                'archer_name' => $bRow['archer_name'],
                'amount' => $bRow['amount'],
                'period_month' => $bRow['period_month'],
                'payment_ref' => $trxRef ?: $bRow['payment_ref'],
                'parent_phone' => $bRow['parent_phone']
            ];
        }

        $stmtClub = $db->prepare("SELECT wa_payment_template, club_name FROM archery_clubs WHERE id = :cid");
        $stmtClub->execute([':cid' => $clubId]);
        $club = $stmtClub->fetch();

        if (!$club || empty($club['wa_payment_template'])) return ['status' => false, 'message' => 'Template belum diatur'];

        $tpl = $club['wa_payment_template'];
        $vars = [
            '{nama_ortu}' => $billData['parent_name'] ?? 'Orang Tua',
            '{nama_atlet}' => $billData['archer_name'] ?? 'Siswa',
            '{nominal}' => number_format((float)($billData['amount'] ?? 0), 0, ',', '.'),
            '{bulan}' => $billData['period_month'] ?? date('Y-m'),
            '{ref}' => $billData['payment_ref'] ?? 'TRX-DEFAULT',
            '{nama_klub}' => $club['club_name'] ?? 'Archery Club'
        ];

        $msg = str_replace(array_keys($vars), array_values($vars), $tpl);
        return self::send($billData['parent_phone'], $msg, $clubId);
    }

    /**
     * Kirim Notifikasi Kelulusan Kenaikan Jarak
     */
    public static function sendPromotionNotification($pdoOrData, $clubId = 1, $archerId = 0, $fromDist = 0, $toDist = 0, $score = 0, $coachName = '', $chairmanName = ''): array {
        global $pdo;
        $db = ($pdoOrData instanceof PDO) ? $pdoOrData : $pdo;

        if (is_array($pdoOrData)) {
            $promoData = $pdoOrData;
            $cid = (int)$clubId;
        } else {
            $cid = (int)$clubId;
            // Ambil nomor HP orang tua atlet
            $stmtArch = $db->prepare("SELECT full_name, parent_phone FROM archery_archers WHERE id = :aid");
            $stmtArch->execute([':aid' => (int)$archerId]);
            $arch = $stmtArch->fetch();

            $promoData = [
                'archer_name' => $arch['full_name'] ?? 'Atlet',
                'parent_phone' => $arch['parent_phone'] ?? '',
                'from_distance' => $fromDist,
                'to_distance' => $toDist,
                'exam_score' => $score,
                'coach_name' => $coachName,
                'chairman_name' => $chairmanName
            ];
        }

        $stmtClub = $db->prepare("SELECT wa_promotion_template, club_name, chairman_name FROM archery_clubs WHERE id = :cid");
        $stmtClub->execute([':cid' => $cid]);
        $club = $stmtClub->fetch();

        if (!$club || empty($club['wa_promotion_template'])) return ['status' => false, 'message' => 'Template promosi belum diatur'];

        $tpl = $club['wa_promotion_template'];
        $vars = [
            '{nama_atlet}' => $promoData['archer_name'] ?? 'Siswa',
            '{jarak_lama}' => $promoData['from_distance'] ?? '5',
            '{jarak_baru}' => $promoData['to_distance'] ?? '7',
            '{skor_ujian}' => $promoData['exam_score'] ?? '0',
            '{nama_coach}' => $promoData['coach_name'] ?? 'Pelatih',
            '{nama_ketua}' => !empty($promoData['chairman_name']) ? $promoData['chairman_name'] : ($club['chairman_name'] ?? 'Ketua Klub'),
            '{nama_klub}' => $club['club_name'] ?? 'Archery Club'
        ];

        $msg = str_replace(array_keys($vars), array_values($vars), $tpl);
        return self::send($promoData['parent_phone'], $msg, $cid);
    }
}
