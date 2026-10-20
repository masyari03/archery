<?php
// config/auth.php - Auth & Session Manager dengan Child Switcher & PIN Device Mode
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    session_start();
}

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/activity_logger.php';

class Auth {
    /**
     * Cek apakah user atau device anak sedang login
     */
    public static function check(): bool {
        return isset($_SESSION['user_id']) || isset($_SESSION['archer_device_id']);
    }

    /**
     * Dapatkan info user login aktif
     */
    public static function user(): ?array {
        if (!isset($_SESSION['user_id'])) {
            return null;
        }
        return [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'] ?? '',
            'full_name' => $_SESSION['full_name'] ?? '',
            'role' => $_SESSION['role'] ?? 'parent',
            'phone' => $_SESSION['phone'] ?? '',
            'email' => $_SESSION['email'] ?? '',
            'club_id' => (int)($_SESSION['club_id'] ?? 1),
            'avatar_url' => $_SESSION['avatar_url'] ?? null
        ];
    }

    /**
     * Dapatkan profil atlet aktif (baik dari Child Switcher Ortu atau PIN Device)
     */
    public static function currentArcher(PDO $pdo): ?array {
        // 1. Jika login via PIN Device Anak mandiri
        if (isset($_SESSION['archer_device_id'])) {
            $stmt = $pdo->prepare("SELECT * FROM archery_archers WHERE id = :id AND is_active = 1 LIMIT 1");
            $stmt->execute([':id' => $_SESSION['archer_device_id']]);
            return $stmt->fetch() ?: null;
        }

        // 2. Jika login sebagai Ortu, ambil anak yang sedang dipilih di Child Switcher
        if (isset($_SESSION['user_id'])) {
            $selectedArcherId = $_SESSION['active_child_id'] ?? null;
            if ($selectedArcherId) {
                $stmt = $pdo->prepare("SELECT * FROM archery_archers WHERE id = :id AND is_active = 1 LIMIT 1");
                $stmt->execute([':id' => $selectedArcherId]);
                $child = $stmt->fetch();
                if ($child) return $child;
            }

            // Jika belum ada yang dipilih, ambil anak pertama milik ortu ini
            $userPhone = $_SESSION['phone'] ?? '';
            $stmt = $pdo->prepare("SELECT * FROM archery_archers WHERE parent_phone = :phone AND is_active = 1 ORDER BY id ASC LIMIT 1");
            $stmt->execute([':phone' => $userPhone]);
            $firstChild = $stmt->fetch();
            if ($firstChild) {
                $_SESSION['active_child_id'] = $firstChild['id'];
                return $firstChild;
            }
        }

        return null;
    }

    /**
     * Daftar seluruh anak milik orang tua yang login
     */
    public static function getChildren(PDO $pdo): array {
        if (!isset($_SESSION['user_id'])) {
            return [];
        }
        $userPhone = $_SESSION['phone'] ?? '';
        $userId = $_SESSION['user_id'];
        $stmt = $pdo->prepare("SELECT * FROM archery_archers 
            WHERE (parent_user_id = :user_id OR parent_phone = :phone) AND is_active = 1 
            ORDER BY id ASC");
        $stmt->execute([':user_id' => $userId, ':phone' => $userPhone]);
        return $stmt->fetchAll();
    }

    /**
     * Switch anak aktif (Child Switcher)
     */
    public static function switchChild(int $archerId, PDO $pdo): bool {
        if (!isset($_SESSION['user_id'])) return false;
        
        $role = $_SESSION['role'] ?? 'parent';
        // Admin & Coach bisa switch ke anak manapun
        if (in_array($role, ['super_admin', 'coach'])) {
            $_SESSION['active_child_id'] = $archerId;
            return true;
        }

        // Ortu hanya bisa switch ke anak kandungnya
        $children = self::getChildren($pdo);
        foreach ($children as $c) {
            if ($c['id'] == $archerId) {
                $_SESSION['active_child_id'] = $archerId;
                return true;
            }
        }
        return false;
    }

    /**
     * Wajib Login Guard dengan Cek Pemutusan Sesi (Kill Session) & Isolasi Multi-Tenant
     */
    public static function requireAuth(string $redirect = 'login.php'): void {
        global $pdo;

        if (!self::check()) {
            header("Location: {$redirect}");
            exit;
        }

        // 1. Cek apakah sesi telah diputus (revoked) oleh Master Admin
        if (isset($pdo) && ActivityLogger::isSessionRevoked($pdo)) {
            $_SESSION = [];
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
            }
            session_destroy();
            header("Location: {$redirect}?error=session_terminated");
            exit;
        }

        // 2. Strict Multi-Tenant Boundary: cegah user tenant A mengakses portal tenant B
        $userRole = $_SESSION['role'] ?? 'parent';
        if ($userRole !== 'master_admin' && isset($pdo)) {
            require_once __DIR__ . '/tenant_resolver.php';
            $currentTenant = TenantResolver::resolve($pdo);
            $tenantId = (int)$currentTenant['id'];
            $userClubId = (int)($_SESSION['club_id'] ?? 0);

            if ($userClubId !== $tenantId) {
                // Logout paksa dari tenant yang salah
                $_SESSION = [];
                if (ini_get("session.use_cookies")) {
                    $params = session_get_cookie_params();
                    setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
                }
                session_destroy();
                header("Location: {$redirect}?error=wrong_tenant");
                exit;
            }
        }
    }

    /**
     * Role Guard
     */
    public static function requireRole(array $allowedRoles, string $redirect = 'index.php'): void {
        self::requireAuth();
        $userRole = $_SESSION['role'] ?? 'archer';
        if (!in_array($userRole, $allowedRoles)) {
            header("Location: {$redirect}?error=unauthorized");
            exit;
        }
    }
}
