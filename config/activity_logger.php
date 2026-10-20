<?php
// config/activity_logger.php - Audit Trail Logger & User Session Tracker Multi-Tenant
// Mencatat seluruh riwayat aktivitas sistem beserta IP Address & User Agent

class ActivityLogger {
    /**
     * Dapatkan IP address asli client (dukung Cloudflare CF-Connecting-IP & X-Forwarded-For)
     */
    public static function getClientIp(): string {
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            return trim($_SERVER['HTTP_CF_CONNECTING_IP']);
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($parts[0]);
        }
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    /**
     * Dapatkan User Agent browser
     */
    public static function getUserAgent(): string {
        return substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown Browser', 0, 255);
    }

    /**
     * Catat log aktivitas ke tabel archery_activity_logs
     */
    public static function log(PDO $pdo, int $clubId, ?int $userId, string $username, string $role, string $actionType, string $actionDesc): bool {
        try {
            $ip = self::getClientIp();
            $ua = self::getUserAgent();
            $stmt = $pdo->prepare("
                INSERT INTO `archery_activity_logs` 
                (`club_id`, `user_id`, `username`, `user_role`, `action_type`, `action_description`, `ip_address`, `user_agent`)
                VALUES (:cid, :uid, :u, :r, :at, :desc, :ip, :ua)
            ");
            return $stmt->execute([
                ':cid' => $clubId,
                ':uid' => $userId,
                ':u' => $username,
                ':r' => $role,
                ':at' => strtoupper($actionType),
                ':desc' => $actionDesc,
                ':ip' => $ip,
                ':ua' => $ua
            ]);
        } catch (Exception $e) {
            // Defensive: Zero HTTP 500 jika gagal menulis log
            error_log("ActivityLogger Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Catat sesi login aktif ke tabel archery_user_sessions
     */
    public static function recordSession(PDO $pdo, int $userId, string $username, string $role, int $clubId): bool {
        try {
            $sessionId = session_id();
            if (!$sessionId) {
                return false;
            }
            $ip = self::getClientIp();
            $ua = self::getUserAgent();

            $stmt = $pdo->prepare("
                INSERT INTO `archery_user_sessions`
                (`session_id`, `user_id`, `username`, `user_role`, `club_id`, `ip_address`, `user_agent`, `login_at`, `is_revoked`)
                VALUES (:sid, :uid, :u, :r, :cid, :ip, :ua, NOW(), 0)
                ON DUPLICATE KEY UPDATE 
                `ip_address` = VALUES(`ip_address`),
                `user_agent` = VALUES(`user_agent`),
                `last_activity_at` = NOW(),
                `is_revoked` = 0
            ");
            return $stmt->execute([
                ':sid' => $sessionId,
                ':uid' => $userId,
                ':u' => $username,
                ':r' => $role,
                ':cid' => $clubId,
                ':ip' => $ip,
                ':ua' => $ua
            ]);
        } catch (Exception $e) {
            error_log("RecordSession Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Periksa apakah sesi saat ini telah diputus (revoked) oleh Master Admin
     */
    public static function isSessionRevoked(PDO $pdo): bool {
        $sessionId = session_id();
        if (!$sessionId) {
            return false;
        }
        try {
            $stmt = $pdo->prepare("SELECT is_revoked FROM archery_user_sessions WHERE session_id = :sid LIMIT 1");
            $stmt->execute([':sid' => $sessionId]);
            $res = $stmt->fetch();
            return $res && (int)$res['is_revoked'] === 1;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Putus / cabut sesi tertentu (Kill Session)
     */
    public static function revokeSession(PDO $pdo, string $sessionId): bool {
        try {
            $stmt = $pdo->prepare("UPDATE archery_user_sessions SET is_revoked = 1 WHERE session_id = :sid");
            return $stmt->execute([':sid' => $sessionId]);
        } catch (Exception $e) {
            return false;
        }
    }
}
