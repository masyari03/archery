<?php
// config/rbac.php - RBAC Helper & Menu Visibility Manager
require_once __DIR__ . '/database.php';

class RBAC {
    public const MENUS = [
        'scoring'         => ['label' => 'Portal Scoring Member', 'icon' => '📊', 'url' => 'app_dashboard.php'],
        'web_profil'      => ['label' => 'Lihat Web Publik', 'icon' => '🌐', 'url' => 'index.php'],
        'rapot'           => ['label' => 'Rapot Sesi A4', 'icon' => '📜', 'url' => 'report_session.php'],
        'rekap'           => ['label' => 'Rekap Bulanan', 'icon' => '📅', 'url' => 'report_monthly.php'],
        'medsos'          => ['label' => 'Medsos Share Card', 'icon' => '📱', 'url' => 'share_card.php'],
        'cms_admin'       => ['label' => 'Kelola Web CMS', 'icon' => '📝', 'url' => 'cms_admin.php'],
        'settings'        => ['label' => 'Pengaturan Identitas Klub', 'icon' => '⚙️', 'url' => 'settings.php'],
        'distances'       => ['label' => 'Master Jarak', 'icon' => '📏', 'url' => 'admin_distances.php'],
        'approvals'       => ['label' => 'ACC Naik Jarak', 'icon' => '✅', 'url' => 'admin_approvals.php'],
        'coach_dashboard' => ['label' => 'Dashboard Pelatih', 'icon' => '👨‍🏫', 'url' => 'coach_dashboard.php'],
        'bills'           => ['label' => 'Iuran & Pembayaran', 'icon' => '💳', 'url' => 'bills.php'],
        'rbac'            => ['label' => 'Hak Akses Menu Header', 'icon' => '🛡️', 'url' => 'admin_rbac.php']
    ];

    private static array $cache = [];

    /**
     * Cek apakah menu tertentu diizinkan tampil untuk role & klub aktif
     */
    public static function can(string $menuKey, ?int $clubId = null, ?string $role = null, ?PDO $pdo = null): bool {
        global $pdo;
        
        $role = $role ?? ($_SESSION['role'] ?? 'archer');
        $clubId = $clubId ?? (int)($_SESSION['club_id'] ?? 1);

        // Master Admin selalu punya akses penuh ke semua menu
        if ($role === 'master_admin') {
            return true;
        }

        // Menu RBAC wajib selalu bisa diakses Super Admin agar tidak terkunci
        if ($menuKey === 'rbac' && $role === 'super_admin') {
            return true;
        }

        // Cache lookup
        $cacheKey = "{$clubId}_{$role}_{$menuKey}";
        if (isset(self::$cache[$cacheKey])) {
            return self::$cache[$cacheKey];
        }

        try {
            $stmt = $pdo->prepare("SELECT is_visible FROM archery_rbac_permissions WHERE club_id = :cid AND role = :role AND menu_key = :mkey LIMIT 1");
            $stmt->execute([':cid' => $clubId, ':role' => $role, ':mkey' => $menuKey]);
            $row = $stmt->fetch();

            if ($row !== false) {
                $allowed = (bool)$row['is_visible'];
                self::$cache[$cacheKey] = $allowed;
                return $allowed;
            }
        } catch (Exception $e) {
            error_log("RBAC Error: " . $e->getMessage());
        }

        // Default Fallbacks jika belum terkonfigurasi di database
        $defaults = [
            'super_admin' => true,
            'coach'       => in_array($menuKey, ['scoring', 'web_profil', 'rapot', 'rekap', 'medsos', 'coach_dashboard']),
            'parent'      => in_array($menuKey, ['scoring', 'web_profil', 'rapot', 'rekap', 'medsos', 'bills']),
            'archer'      => in_array($menuKey, ['scoring', 'web_profil'])
        ];

        $allowed = $defaults[$role] ?? false;
        self::$cache[$cacheKey] = $allowed;
        return $allowed;
    }

    /**
     * Dapatkan matriks seluruh izin menu untuk klub
     */
    public static function getClubMatrix(int $clubId, PDO $pdo): array {
        $stmt = $pdo->prepare("SELECT role, menu_key, is_visible FROM archery_rbac_permissions WHERE club_id = :cid");
        $stmt->execute([':cid' => $clubId]);
        $rows = $stmt->fetchAll();

        $matrix = [];
        foreach ($rows as $r) {
            $matrix[$r['role']][$r['menu_key']] = (int)$r['is_visible'];
        }
        return $matrix;
    }
}
