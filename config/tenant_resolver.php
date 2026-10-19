<?php
// config/tenant_resolver.php - Resolusi Multi-Tenant Subdomain, Custom Domain, & CMS Resolver
// Berdasarkan Arsitektur SaaS Codika Alquran

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    session_start();
}

require_once __DIR__ . '/database.php';

class TenantResolver {
    private static ?array $cachedTenant = null;

    /**
     * Resolusi Tenant Klub Aktif
     * Urutan Prioritas:
     * 1. Query parameter ?club=slug (untuk testing lokal / link langsung)
     * 2. Custom Domain (archery_clubs.custom_domain == HTTP_HOST)
     * 3. Subdomain Slug (subdomain.domain.tld == archery_clubs.subdomain_slug)
     * 4. Session club_id (jika sudah login)
     * 5. Default Club ID 1
     */
    public static function resolve(PDO $pdo): array {
        if (self::$cachedTenant !== null) {
            return self::$cachedTenant;
        }

        $host = strtolower($_SERVER['HTTP_HOST'] ?? 'localhost');
        // Hapus port jika ada (contoh: localhost:3008 -> localhost)
        $cleanHost = preg_replace('/:\d+$/', '', $host);

        // 1. Cek Query Param ?club=slug
        if (!empty($_GET['club'])) {
            $slug = trim($_GET['club']);
            $stmt = $pdo->prepare("SELECT * FROM archery_clubs WHERE (subdomain_slug = :slug1 OR club_code = :slug2) AND is_active = 1 LIMIT 1");
            $stmt->execute([':slug1' => $slug, ':slug2' => $slug]);
            $tenant = $stmt->fetch();
            if ($tenant) {
                $_SESSION['current_tenant_id'] = $tenant['id'];
                self::$cachedTenant = $tenant;
                return $tenant;
            }
        }

        // 2. Cek Custom Domain
        $stmt = $pdo->prepare("SELECT * FROM archery_clubs WHERE custom_domain = :domain AND is_active = 1 LIMIT 1");
        $stmt->execute([':domain' => $cleanHost]);
        $tenant = $stmt->fetch();
        if ($tenant) {
            $_SESSION['current_tenant_id'] = $tenant['id'];
            self::$cachedTenant = $tenant;
            return $tenant;
        }

        // 3. Cek Subdomain (misal: annahl.archery.codika.my.id -> slug: annahl, atau annahl-archery.codika.my.id -> slug: annahl)
        $parts = explode('.', $cleanHost);
        if (count($parts) >= 3) {
            $subdomain = $parts[0];
            $cleanSlug = $subdomain;
            if (str_ends_with($subdomain, '-archery')) {
                $cleanSlug = substr($subdomain, 0, -8);
            }

            // Abaikan subdomain www, app, portal, archery (root platform)
            if (!in_array($cleanSlug, ['www', 'app', 'portal', 'master', 'archery'])) {
                $stmt = $pdo->prepare("SELECT * FROM archery_clubs WHERE (subdomain_slug = :sub OR club_code = :code) AND is_active = 1 LIMIT 1");
                $stmt->execute([':sub' => $cleanSlug, ':code' => $cleanSlug]);
                $tenant = $stmt->fetch();
                if ($tenant) {
                    $_SESSION['current_tenant_id'] = $tenant['id'];
                    self::$cachedTenant = $tenant;
                    return $tenant;
                }
            }
        }

        // 4. Cek Session aktif
        if (isset($_SESSION['current_tenant_id'])) {
            $stmt = $pdo->prepare("SELECT * FROM archery_clubs WHERE id = :id AND is_active = 1 LIMIT 1");
            $stmt->execute([':id' => $_SESSION['current_tenant_id']]);
            $tenant = $stmt->fetch();
            if ($tenant) {
                self::$cachedTenant = $tenant;
                return $tenant;
            }
        }

        if (isset($_SESSION['club_id'])) {
            $stmt = $pdo->prepare("SELECT * FROM archery_clubs WHERE id = :id AND is_active = 1 LIMIT 1");
            $stmt->execute([':id' => $_SESSION['club_id']]);
            $tenant = $stmt->fetch();
            if ($tenant) {
                self::$cachedTenant = $tenant;
                return $tenant;
            }
        }

        // 5. Fallback ke Klub Pertama (ID = 1)
        $stmt = $pdo->query("SELECT * FROM archery_clubs WHERE is_active = 1 ORDER BY id ASC LIMIT 1");
        $tenant = $stmt->fetch();
        if (!$tenant) {
            // Safety fallback jika tabel belum ada isinya
            $tenant = [
                'id' => 1,
                'club_name' => 'TARGET ARCHERY INDONESIA',
                'club_code' => 'TARGET-ARC',
                'subdomain_slug' => 'default',
                'custom_domain' => null,
                'city' => 'Bogor',
                'address' => 'Pusat Pelatihan Panahan Nasional',
                'phone' => '081299887766',
                'admin_wa_phone' => '081299887766',
                'chairman_name' => 'Master Coach',
                'primary_color' => '#0284c7',
                'theme_mode' => 'dark',
                'fonnte_token' => '',
                'fonnte_enabled' => 0,
                'subscription_status' => 'active'
            ];
        }

        self::$cachedTenant = $tenant;
        return $tenant;
    }

    /**
     * Dapatkan Seluruh Daftar Tenant Aktif
     */
    public static function getAllTenants(PDO $pdo): array {
        $stmt = $pdo->query("SELECT id, club_name, club_code, subdomain_slug, custom_domain, city, primary_color, subscription_status FROM archery_clubs WHERE is_active = 1 ORDER BY club_name ASC");
        return $stmt->fetchAll();
    }

    /**
     * Cek apakah request saat ini mengarah ke Root Platform SaaS (Web Marketing Penjualan Master Admin)
     */
    public static function isPlatformRoot(): bool {
        if (!empty($_GET['club'])) {
            return false;
        }

        $host = strtolower($_SERVER['HTTP_HOST'] ?? 'localhost');
        $cleanHost = preg_replace('/:\d+$/', '', $host);

        // Jika root domain platform langsung
        $rootDomains = [
            'archery.codika.my.id',
            'archery.codika.id',
            'codika.my.id',
            'codika.id',
            'archery.local'
        ];

        return in_array($cleanHost, $rootDomains);
    }

    /**
     * Dapatkan Base URL Resmi Klub (Otomatis beralih ke Custom Domain jika ada, atau Subdomain Platform jika belum ada)
     */
    public static function getBaseUrl(?array $club = null): string {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';

        if (!empty($club['custom_domain'])) {
            return "{$scheme}://" . trim($club['custom_domain']);
        }

        if (!empty($club['subdomain_slug'])) {
            $slug = trim($club['subdomain_slug']);
            // Domain induk platform
            $parentDomain = 'archery.codika.my.id';
            return "{$scheme}://{$slug}.{$parentDomain}";
        }

        $host = $_SERVER['HTTP_HOST'] ?? 'localhost:3008';
        return "{$scheme}://{$host}";
    }

    /**
     * Dapatkan Canonical URL SEO Absolut untuk Halaman
     */
    public static function canonicalUrl(string $path = '', ?array $club = null): string {
        $base = self::getBaseUrl($club);
        $cleanPath = ltrim($path, '/');
        return $cleanPath ? "{$base}/{$cleanPath}" : $base;
    }

    /**
     * Helper URL untuk tenant saat ini
     */
    public static function url(string $path, ?array $club = null): string {
        $slug = $club['subdomain_slug'] ?? ($_SESSION['current_tenant_slug'] ?? null);
        if ($slug && !isset($_GET['club'])) {
            $connector = strpos($path, '?') !== false ? '&' : '?';
            return $path . $connector . 'club=' . urlencode($slug);
        }
        return $path;
    }
}
